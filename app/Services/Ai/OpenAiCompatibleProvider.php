<?php

namespace App\Services\Ai;

use App\Services\Ai\Contracts\AiProvider;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class OpenAiCompatibleProvider implements AiProvider
{
    public function __construct(private ?AiActionCatalog $actions = null) {}

    /** @param array<int, array<string, string>> $messages */
    public function chat(array $messages): AiProviderResult
    {
        $this->guardConfigured();
        $startedAt = hrtime(true);

        $response = $this->client()
            ->post(config('ai.base_url').'/chat/completions', [
                'model' => config('ai.model'),
                'messages' => $messages,
                'max_tokens' => config('ai.max_output_tokens'),
                'response_format' => ['type' => 'json_object'],
            ])
            ->throw();

        $latencyMs = (int) round((hrtime(true) - $startedAt) / 1_000_000);
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

    private function client(): PendingRequest
    {
        return Http::acceptJson()
            ->asJson()
            ->withToken((string) config('ai.api_key'))
            ->connectTimeout((int) config('ai.connect_timeout'))
            ->timeout((int) config('ai.timeout'))
            ->retry(2, 300, function (Throwable $exception): bool {
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
        $content = trim($content);
        $content = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', $content) ?? $content;
        $decoded = json_decode($content, true);

        if (! is_array($decoded)) {
            throw new RuntimeException('Phản hồi AI không đúng định dạng JSON yêu cầu.');
        }

        return $decoded;
    }

    /** @param array<string, mixed> $document */
    private function plainContent(array $document): string
    {
        $content = Arr::get($document, 'content');

        if (! is_string($content) || trim($content) === '') {
            throw new RuntimeException('Phản hồi AI thiếu phần nội dung giải thích.');
        }

        return trim($content);
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

    private function nullableInt(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
