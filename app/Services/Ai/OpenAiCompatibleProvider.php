<?php

namespace App\Services\Ai;

use App\Models\User;
use App\Services\Ai\Contracts\AiProvider;
use Closure;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class OpenAiCompatibleProvider implements AiProvider
{
    /**
     * Trần số vòng gọi công cụ trong một lượt trả lời.
     *
     * Không có trần thì một model bối rối có thể gọi đi gọi lại mãi và giữ kết
     * nối tới khi PHP hết thời gian chạy.
     */
    private const MAX_TOOL_ROUNDS = 4;

    public function __construct(
        private ?AiActionCatalog $actions = null,
        private ?AiToolRegistry $tools = null,
        private ?AiToolExecutor $toolExecutor = null,
    ) {}

    /**
     * Lượt hỏi đáp một chiều, không công cụ, không phát tiến trình.
     *
     * Giữ lại cho các lời gọi nội bộ và cho kiểm thử; luồng người dùng thật đi
     * qua converse().
     *
     * @param  array<int, array<string, mixed>>  $messages
     */
    public function chat(array $messages): AiProviderResult
    {
        $this->guardConfigured();
        $startedAt = hrtime(true);

        $response = $this->client()
            ->post(config('ai.base_url').'/chat/completions', $this->withTemperature([
                'model' => config('ai.model'),
                'messages' => $messages,
                'max_tokens' => config('ai.max_output_tokens'),
                'response_format' => ['type' => 'json_object'],
            ]))
            ->throw();

        $latencyMs = $this->elapsedMs($startedAt);
        $rawContent = $response->json('choices.0.message.content');

        if (! is_string($rawContent) || trim($rawContent) === '') {
            throw new RuntimeException('Nhà cung cấp AI không trả về nội dung hợp lệ.');
        }

        $document = $this->decodeDocument($rawContent);
        $content = $this->plainContent($document);
        $blocks = $this->sanitizeBlocks(Arr::get($document, 'blocks', []));
        $actions = $this->sanitizeActions(Arr::get($document, 'actions', []));

        if ($blocks === []) {
            $blocks = [['type' => 'text', 'content' => $content]];
        }

        return new AiProviderResult(
            content: $content,
            blocks: $blocks,
            actions: $actions,
            provider: (string) config('ai.provider'),
            model: (string) config('ai.model'),
            promptTokens: $this->nullableInt($response->json('usage.prompt_tokens')),
            completionTokens: $this->nullableInt($response->json('usage.completion_tokens')),
            totalTokens: $this->nullableInt($response->json('usage.total_tokens')),
            latencyMs: $latencyMs,
            providerReference: $this->nullableString($response->json('id')),
        );
    }

    /**
     * Lượt trả lời đầy đủ: model tự gọi công cụ để lấy dữ liệu, vừa chạy vừa
     * phát chữ ra giao diện.
     *
     * @param  array<int, array<string, mixed>>  $messages
     * @param  Closure(AiStreamEvent): void|null  $onEvent
     */
    public function converse(
        array $messages,
        User $user,
        ?Closure $onEvent = null,
        ?array $domains = null,
    ): AiProviderResult {
        $this->guardConfigured();

        $registry = $this->tools ?? app(AiToolRegistry::class);
        $executor = $this->toolExecutor ?? app(AiToolExecutor::class);
        $startedAt = hrtime(true);

        $conversation = $messages;
        $promptTokens = 0;
        $completionTokens = 0;
        $reference = null;
        $toolsUsed = [];

        // Kết quả của từng lời gọi trong lượt này, khóa theo tên công cụ kèm
        // tham số. Model đôi khi gọi lại y hệt một bước đã chạy; trả lại kết quả
        // cũ rẻ hơn nhiều so với chạy lại truy vấn và tốn thêm một vòng.
        $seen = [];

        for ($round = 0; $round <= self::MAX_TOOL_ROUNDS; $round++) {
            // Vòng cuối bỏ công cụ đi để model buộc phải chốt câu trả lời thay
            // vì gọi thêm một công cụ nữa rồi bị cắt ngang.
            $offerTools = $round < self::MAX_TOOL_ROUNDS;

            $turn = $this->streamTurn($conversation, $registry, $offerTools, $onEvent, $domains);

            $promptTokens += $turn['prompt_tokens'] ?? 0;
            $completionTokens += $turn['completion_tokens'] ?? 0;
            $reference ??= $turn['id'] ?? null;

            if ($turn['tool_calls'] === []) {
                $document = $this->decodeDocument($turn['content']);

                return $this->buildResult(
                    document: $document,
                    promptTokens: $promptTokens ?: null,
                    completionTokens: $completionTokens ?: null,
                    latencyMs: $this->elapsedMs($startedAt),
                    reference: $reference,
                    toolsUsed: $toolsUsed,
                );
            }

            $conversation[] = [
                'role' => 'assistant',
                'content' => $turn['content'],
                'tool_calls' => $turn['tool_calls'],
            ];

            foreach ($turn['tool_calls'] as $call) {
                $name = (string) Arr::get($call, 'function.name', '');
                $rawArguments = (string) Arr::get($call, 'function.arguments', '{}');
                $arguments = json_decode($rawArguments, true);
                $arguments = is_array($arguments) ? $arguments : [];

                $signature = $name.':'.json_encode(
                    $this->sortRecursively($arguments),
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
                );

                if (array_key_exists($signature, $seen)) {
                    // Lặp lại y hệt thì không báo tiến trình và không chạy lại
                    // truy vấn; chỉ đưa lại kết quả cũ để model đi tiếp.
                    $conversation[] = [
                        'role' => 'tool',
                        'tool_call_id' => (string) Arr::get($call, 'id', ''),
                        'content' => $seen[$signature],
                    ];

                    continue;
                }

                if ($onEvent !== null) {
                    $onEvent(AiStreamEvent::toolCall($name, $this->toolLabel($name)));
                }

                try {
                    $output = $executor->run($user, $name, $arguments);
                } catch (Throwable $exception) {
                    report($exception);
                    $output = ['error' => 'Không lấy được dữ liệu cho bước này.'];
                }

                $toolsUsed[] = ['tool' => $name, 'arguments' => $arguments];

                $encoded = json_encode($output, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $seen[$signature] = $encoded;

                $conversation[] = [
                    'role' => 'tool',
                    'tool_call_id' => (string) Arr::get($call, 'id', ''),
                    'content' => $encoded,
                ];
            }
        }

        throw new RuntimeException('Trợ lý AI không hoàn tất được câu trả lời.');
    }

    /**
     * Chạy một lượt gọi tới provider ở chế độ stream và gom lại kết quả.
     *
     * @param  array<int, array<string, mixed>>  $messages
     * @param  Closure(AiStreamEvent): void|null  $onEvent
     * @return array{content: string, tool_calls: array<int, array<string, mixed>>, prompt_tokens: int, completion_tokens: int, id: ?string}
     */
    private function streamTurn(
        array $messages,
        AiToolRegistry $registry,
        bool $offerTools,
        ?Closure $onEvent,
        ?array $domains = null,
    ): array {
        $payload = $this->withTemperature([
            'model' => config('ai.model'),
            'messages' => $messages,
            'max_tokens' => config('ai.max_output_tokens'),
            'stream' => true,
            'stream_options' => ['include_usage' => true],
        ]);

        if ($offerTools) {
            $payload['tools'] = $registry->toolSchemas($domains);
            $payload['tool_choice'] = 'auto';
        } else {
            // Chỉ ép JSON ở lượt chốt. Ép từ đầu thì model không gọi được công cụ
            // vì nhiều provider cấm dùng chung response_format với tools.
            $payload['response_format'] = ['type' => 'json_object'];
        }

        $response = $this->client()
            ->withOptions(['stream' => true])
            ->post(config('ai.base_url').'/chat/completions', $payload)
            ->throw();

        $body = $response->toPsrResponse()->getBody();

        $content = '';
        $toolCalls = [];
        $promptTokens = 0;
        $completionTokens = 0;
        $id = null;
        $buffer = '';

        while (! $body->eof()) {
            $chunk = $body->read(8192);

            if ($chunk === '') {
                continue;
            }

            $buffer .= $chunk;

            // SSE phân tách bằng dòng trống; giữ lại phần đuôi chưa trọn vẹn cho
            // vòng đọc sau, nếu không một sự kiện bị cắt đôi sẽ hỏng JSON.
            while (($breakPos = strpos($buffer, "\n")) !== false) {
                $line = trim(substr($buffer, 0, $breakPos));
                $buffer = substr($buffer, $breakPos + 1);

                if ($line === '' || ! str_starts_with($line, 'data:')) {
                    continue;
                }

                $data = trim(substr($line, 5));

                if ($data === '[DONE]') {
                    break 2;
                }

                $event = json_decode($data, true);

                if (! is_array($event)) {
                    continue;
                }

                $id ??= $this->nullableString($event['id'] ?? null);
                $promptTokens = $this->nullableInt(Arr::get($event, 'usage.prompt_tokens')) ?? $promptTokens;
                $completionTokens = $this->nullableInt(Arr::get($event, 'usage.completion_tokens')) ?? $completionTokens;

                $delta = Arr::get($event, 'choices.0.delta', []);

                if (is_string($delta['content'] ?? null) && $delta['content'] !== '') {
                    $content .= $delta['content'];

                    if ($onEvent !== null) {
                        $onEvent(AiStreamEvent::delta($delta['content']));
                    }
                }

                foreach (Arr::get($delta, 'tool_calls', []) ?? [] as $partial) {
                    $this->mergeToolCall($toolCalls, $partial);
                }
            }
        }

        $body->close();

        return [
            'content' => $content,
            'tool_calls' => array_values($toolCalls),
            'prompt_tokens' => $promptTokens,
            'completion_tokens' => $completionTokens,
            'id' => $id,
        ];
    }

    /**
     * Ghép các mảnh tool call rải rác qua nhiều chunk.
     *
     * Provider gửi tên ở chunk đầu rồi nhỏ giọt phần arguments qua các chunk sau,
     * nên phải cộng dồn theo chỉ số thay vì ghi đè.
     *
     * @param  array<int, array<string, mixed>>  $toolCalls
     * @param  array<string, mixed>  $partial
     */
    private function mergeToolCall(array &$toolCalls, mixed $partial): void
    {
        if (! is_array($partial)) {
            return;
        }

        $index = (int) ($partial['index'] ?? 0);

        $toolCalls[$index] ??= [
            'id' => '',
            'type' => 'function',
            'function' => ['name' => '', 'arguments' => ''],
        ];

        if (is_string($partial['id'] ?? null) && $partial['id'] !== '') {
            $toolCalls[$index]['id'] = $partial['id'];
        }

        $name = Arr::get($partial, 'function.name');

        if (is_string($name) && $name !== '') {
            $toolCalls[$index]['function']['name'] = $name;
        }

        $arguments = Arr::get($partial, 'function.arguments');

        if (is_string($arguments) && $arguments !== '') {
            $toolCalls[$index]['function']['arguments'] .= $arguments;
        }
    }

    /**
     * Nhãn tiếng Việt cho bước đang chạy, hiện lên giao diện trong lúc chờ.
     */
    private function toolLabel(string $tool): string
    {
        return match ($tool) {
            'get_overview' => 'Đang xem tình hình chung…',
            'get_invoices' => 'Đang tra doanh thu…',
            'get_appointments', 'find_appointment' => 'Đang tra lịch hẹn…',
            'find_customer' => 'Đang tìm khách hàng…',
            'get_services' => 'Đang xem dịch vụ…',
            'get_cash_flow' => 'Đang tra sổ quỹ…',
            'get_inventory' => 'Đang kiểm tồn kho…',
            'get_attendance' => 'Đang tra chấm công…',
            'get_payroll' => 'Đang tra bảng lương…',
            'get_employees' => 'Đang xem nhân viên…',
            default => 'Đang tra dữ liệu…',
        };
    }

    /**
     * @param  array<string, mixed>  $document
     * @param  array<int, array<string, mixed>>  $toolsUsed
     */
    private function buildResult(
        array $document,
        ?int $promptTokens,
        ?int $completionTokens,
        int $latencyMs,
        ?string $reference,
        array $toolsUsed,
    ): AiProviderResult {
        $content = $this->plainContent($document);
        $blocks = $this->sanitizeBlocks(Arr::get($document, 'blocks', []));
        $actions = $this->sanitizeActions(Arr::get($document, 'actions', []));

        if ($blocks === []) {
            $blocks = [['type' => 'text', 'content' => $content]];
        }

        return new AiProviderResult(
            content: $content,
            blocks: $blocks,
            actions: $actions,
            provider: (string) config('ai.provider'),
            model: (string) config('ai.model'),
            promptTokens: $promptTokens,
            completionTokens: $completionTokens,
            totalTokens: $promptTokens !== null && $completionTokens !== null
                ? $promptTokens + $completionTokens
                : null,
            latencyMs: $latencyMs,
            providerReference: $reference,
            toolsUsed: $toolsUsed,
        );
    }

    private function client(): PendingRequest
    {
        return Http::acceptJson()
            ->asJson()
            ->withToken((string) config('ai.api_key'))
            ->connectTimeout((int) config('ai.connect_timeout'))
            ->timeout((int) config('ai.timeout'))
            // Một lần thử lại là đủ cho lỗi tạm thời. Hai lần cộng với timeout dài
            // đẩy tổng thời gian chờ vượt quá giới hạn chạy của PHP, và người dùng
            // nhận được một trang trắng thay vì câu trả lời.
            ->retry(1, 300, function (Throwable $exception): bool {
                return ! method_exists($exception, 'response')
                    || $exception->response?->serverError() === true;
            }, throw: false);
    }

    private function guardConfigured(): void
    {
        if (! config('ai.enabled')) {
            throw new RuntimeException('Trợ lý AI đang tắt. Hãy bật AI_ENABLED sau khi cấu hình provider.');
        }

        if (blank(config('ai.api_key')) || blank(config('ai.base_url')) || blank(config('ai.model'))) {
            throw new RuntimeException('Cấu hình nhà cung cấp AI chưa đầy đủ.');
        }
    }

    /** @return array<string, mixed> */
    private function decodeDocument(string $content): array
    {
        $decoded = $this->tryDecodeDocument($content);

        if ($decoded !== null) {
            return $decoded;
        }

        $plain = $this->stripJsonFence($content);

        // Lượt có kèm công cụ không gửi được `response_format`, nên model đôi khi
        // trả thẳng văn xuôi. Câu trả lời đó vẫn dùng được: gói lại đúng hình
        // dạng document còn hơn báo lỗi và bắt người dùng hỏi lại từ đầu.
        if ($plain !== '' && ! str_contains($plain, '{')) {
            return ['content' => $plain];
        }

        throw new RuntimeException('Phản hồi AI không đúng định dạng JSON yêu cầu.');
    }

    /** @return array<string, mixed>|null */
    private function tryDecodeDocument(string $content): ?array
    {
        $body = $this->stripJsonFence($content);
        $start = strpos($body, '{');
        $end = strrpos($body, '}');

        if ($start === false || $end === false || $end <= $start) {
            return null;
        }

        // Cắt đúng phần object, bỏ mọi lời dẫn model viết thêm trước hoặc sau.
        $body = substr($body, $start, $end - $start + 1);
        $decoded = json_decode($body, true);

        if (! is_array($decoded)) {
            // Khối ```chart nhiều dòng nằm trong chuỗi `content` hay làm model
            // quên escape xuống dòng. Escape lại rồi thử tiếp, thay vì vứt bỏ
            // cả câu trả lời chỉ vì một ký tự xuống dòng.
            $decoded = json_decode($this->escapeRawNewlinesInStrings($body), true);
        }

        return is_array($decoded) ? $decoded : null;
    }

    private function stripJsonFence(string $content): string
    {
        $content = trim($content);

        // Chỉ bóc khi TOÀN BỘ phản hồi nằm trong một khối ```. Regex không neo
        // hai đầu sẽ cắt nhầm khối ```chart nằm bên trong câu trả lời.
        if (preg_match('/^```(?:json)?\s*([\s\S]*?)```$/i', $content, $matches) === 1) {
            return trim($matches[1]);
        }

        return $content;
    }

    /**
     * Escape xuống dòng thô nằm bên trong chuỗi JSON, giữ nguyên phần ngoài chuỗi.
     */
    private function escapeRawNewlinesInStrings(string $input): string
    {
        $out = '';
        $inString = false;
        $escaped = false;

        // Duyệt theo byte là an toàn: ký tự UTF-8 nhiều byte không bao giờ chứa
        // byte trùng với `"`, `\` hay ký tự điều khiển ASCII.
        foreach (str_split($input) as $char) {
            if ($escaped) {
                $out .= $char;
                $escaped = false;

                continue;
            }

            if ($char === '\\') {
                $out .= $char;
                $escaped = $inString;

                continue;
            }

            if ($char === '"') {
                $inString = ! $inString;
                $out .= $char;

                continue;
            }

            $out .= $inString
                ? match ($char) {
                    "\n" => '\\n',
                    "\r" => '\\r',
                    "\t" => '\\t',
                    default => $char,
                }
            : $char;
        }

        return $out;
    }

    /** @param array<string, mixed> $document */
    private function plainContent(array $document): string
    {
        $content = Arr::get($document, 'content');

        if (is_string($content) && trim($content) !== '') {
            return trim($content);
        }

        // Model thỉnh thoảng bỏ `content` mà dồn hết lời giải thích vào block
        // đầu tiên. Lấy tạm chỗ đó còn hơn báo lỗi khi câu trả lời vẫn có.
        $fallback = $this->firstTextBlockContent(Arr::get($document, 'blocks'));

        if ($fallback !== null) {
            return $fallback;
        }

        throw new RuntimeException('Phản hồi AI thiếu phần nội dung giải thích.');
    }

    private function firstTextBlockContent(mixed $blocks): ?string
    {
        if (! is_array($blocks)) {
            return null;
        }

        foreach ($blocks as $block) {
            if (! is_array($block) || ($block['type'] ?? null) !== 'text') {
                continue;
            }

            $text = $block['content'] ?? null;

            if (is_string($text) && trim($text) !== '') {
                return trim($text);
            }
        }

        return null;
    }

    /** @return array<int, array<string, mixed>> */
    private function sanitizeBlocks(mixed $blocks): array
    {
        if (! is_array($blocks)) {
            return [];
        }

        $safe = [];

        foreach (array_slice($blocks, 0, 12) as $block) {
            if (! is_array($block) || ! is_string($block['type'] ?? null)) {
                continue;
            }

            if ($block['type'] === 'text' && is_string($block['content'] ?? null)) {
                $safe[] = ['type' => 'text', 'content' => trim($block['content'])];
            }

            if ($block['type'] === 'table') {
                $headers = $this->stringList($block['headers'] ?? [], 12);
                $rows = is_array($block['rows'] ?? null)
                    ? array_slice(array_values(array_filter(array_map(
                        fn (mixed $row): ?array => is_array($row) ? $this->stringList($row, 12) : null,
                        $block['rows'],
                    ))), 0, 100)
                    : [];

                if ($headers !== [] && $rows !== []) {
                    $safe[] = ['type' => 'table', 'headers' => $headers, 'rows' => $rows];
                }
            }

            if ($block['type'] === 'chart') {
                $chart = $this->sanitizeChart($block);

                if ($chart !== null) {
                    $safe[] = $chart;
                }
            }
        }

        return $safe;
    }

    /** @return array<string, mixed>|null */
    private function sanitizeChart(array $block): ?array
    {
        $chartType = $block['chart_type'] ?? null;
        $labels = $this->stringList($block['labels'] ?? [], 50);
        $datasets = [];

        if (! in_array($chartType, ['bar', 'line', 'doughnut'], true) || $labels === []) {
            return null;
        }

        foreach (array_slice(is_array($block['datasets'] ?? null) ? $block['datasets'] : [], 0, 8) as $dataset) {
            if (! is_array($dataset) || ! is_string($dataset['label'] ?? null) || ! is_array($dataset['data'] ?? null)) {
                continue;
            }

            $data = array_map(
                fn (mixed $value): int|float => is_numeric($value) ? $value + 0 : 0,
                array_slice($dataset['data'], 0, count($labels)),
            );
            $datasets[] = ['label' => trim($dataset['label']), 'data' => $data];
        }

        return $datasets === [] ? null : [
            'type' => 'chart',
            'chart_type' => $chartType,
            'title' => is_string($block['title'] ?? null) ? trim($block['title']) : '',
            'labels' => $labels,
            'datasets' => $datasets,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function sanitizeActions(mixed $actions): array
    {
        if (! is_array($actions)) {
            return [];
        }

        $allowed = ($this->actions ?? new AiActionCatalog)->allowedTypes();
        $safe = [];

        foreach (array_slice($actions, 0, 3) as $action) {
            if (! is_array($action)
                || ! in_array($action['type'] ?? null, $allowed, true)
                || ! is_string($action['summary'] ?? null)
                || ! is_array($action['payload'] ?? null)) {
                continue;
            }

            $safe[] = [
                'type' => $action['type'],
                'summary' => mb_substr(trim($action['summary']), 0, 500),
                'payload' => $action['payload'],
            ];
        }

        return $safe;
    }

    /** @return array<int, string> */
    private function stringList(mixed $values, int $limit): array
    {
        if (! is_array($values)) {
            return [];
        }

        return array_map(
            fn (mixed $value): string => mb_substr((string) $value, 0, 500),
            array_slice(array_values($values), 0, $limit),
        );
    }

    /**
     * Sắp xếp khóa để hai lời gọi giống nhau cho ra cùng một chữ ký.
     *
     * Model không đảm bảo thứ tự khóa trong JSON tham số, nên không sắp lại thì
     * cùng một lời gọi lại trông như hai lời gọi khác nhau.
     *
     * @param  array<string, mixed>  $value
     * @return array<string, mixed>
     */
    private function sortRecursively(array $value): array
    {
        ksort($value);

        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->sortRecursively($item);
            }
        }

        return $value;
    }

    /**
     * Đính `temperature` vào payload, và chỉ khi nó thật sự được cấu hình.
     *
     * Gửi tham số này tới một model không nhận nó làm hỏng cả request chứ không
     * phải bị bỏ qua: claude-sonnet-5 trả về 400 kèm "`temperature` is
     * deprecated for this model". Để trống trong cấu hình là cách an toàn cho
     * mọi provider.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function withTemperature(array $payload): array
    {
        $temperature = config('ai.temperature');

        if ($temperature !== null && $temperature !== '') {
            $payload['temperature'] = (float) $temperature;
        }

        return $payload;
    }

    /**
     * Thời gian đã trôi qua, tính bằng mili giây.
     *
     * Sàn ở 1ms để một lời gọi nhanh dưới nửa mili giây không bị làm tròn thành
     * 0 — số 0 trong nhật ký đọc như "chưa đo được" chứ không như "rất nhanh".
     */
    private function elapsedMs(float|int $startedAt): int
    {
        return max(1, (int) round((hrtime(true) - $startedAt) / 1_000_000));
    }

    private function nullableInt(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
