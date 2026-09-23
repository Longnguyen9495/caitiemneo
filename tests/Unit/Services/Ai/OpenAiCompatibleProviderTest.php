<?php

namespace Tests\Unit\Services\Ai;

use App\Services\Ai\OpenAiCompatibleProvider;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class OpenAiCompatibleProviderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('ai.enabled', true);
        Config::set('ai.base_url', 'https://ai.example.com/v1');
        Config::set('ai.api_key', 'test-key');
        Config::set('ai.model', 'test-model');
        Config::set('ai.temperature', 0.2);
        Config::set('ai.max_output_tokens', 3000);
        Config::set('ai.connect_timeout', 10);
        Config::set('ai.timeout', 60);
    }

    public function test_valid_json_with_text_table_chart_and_actions(): void
    {
        Http::fake([
            'https://ai.example.com/v1/chat/completions' => Http::response([
                'id' => 'resp-1',
                'choices' => [[
                    'message' => [
                        'role' => 'assistant',
                        'content' => json_encode([
                            'content' => 'Tóm tắt doanh thu tháng này.',
                            'blocks' => [
                                ['type' => 'text', 'content' => 'Doanh thu tăng 12% so với tháng trước.'],
                                [
                                    'type' => 'table',
                                    'headers' => ['Tháng', 'Doanh thu'],
                                    'rows' => [
                                        ['Tháng 8', '15.2 triệu'],
                                        ['Tháng 9', '17.0 triệu'],
                                    ],
                                ],
                                [
                                    'type' => 'chart',
                                    'chart_type' => 'bar',
                                    'title' => 'Doanh thu',
                                    'labels' => ['Tháng 8', 'Tháng 9'],
                                    'datasets' => [
                                        ['label' => 'Doanh thu', 'data' => [15200000, 17000000]],
                                    ],
                                ],
                            ],
                            'actions' => [[
                                'type' => 'create_cash_entry',
                                'summary' => 'Ghi khoản chi marketing',
                                'payload' => ['branch_id' => 1, 'type' => 'expense', 'category' => 'marketing', 'amount' => 500000],
                            ]],
                        ]),
                    ],
                ]],
                'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 50, 'total_tokens' => 150],
            ]),
        ]);

        $provider = new OpenAiCompatibleProvider;
        $result = $provider->chat([['role' => 'user', 'content' => 'Xem doanh thu']]);

        $this->assertSame('Tóm tắt doanh thu tháng này.', $result->content);
        $this->assertCount(3, $result->blocks);
        $this->assertSame('text', $result->blocks[0]['type']);
        $this->assertSame('table', $result->blocks[1]['type']);
        $this->assertSame('chart', $result->blocks[2]['type']);
        $this->assertSame('bar', $result->blocks[2]['chart_type']);
        $this->assertCount(1, $result->actions);
        $this->assertSame('create_cash_entry', $result->actions[0]['type']);
        $this->assertSame('resp-1', $result->providerReference);
        $this->assertSame(100, $result->promptTokens);
        $this->assertSame(50, $result->completionTokens);
        $this->assertSame(150, $result->totalTokens);
        $this->assertGreaterThan(0, $result->latencyMs);
    }

    public function test_json_inside_markdown_fence_is_decoded(): void
    {
        Http::fake([
            'https://ai.example.com/v1/chat/completions' => Http::response([
                'id' => 'resp-2',
                'choices' => [[
                    'message' => [
                        'role' => 'assistant',
                        'content' => "```json\n".json_encode([
                            'content' => 'Nội dung trong fence.',
                            'blocks' => [],
                            'actions' => [],
                        ])."\n```",
                    ],
                ]],
                'usage' => [],
            ]),
        ]);

        $provider = new OpenAiCompatibleProvider;
        $result = $provider->chat([['role' => 'user', 'content' => 'Hỏi']]);

        $this->assertSame('Nội dung trong fence.', $result->content);
        $this->assertCount(1, $result->blocks);
        $this->assertSame('text', $result->blocks[0]['type']);
    }

    public function test_empty_content_throws_exception(): void
    {
        Http::fake([
            'https://ai.example.com/v1/chat/completions' => Http::response([
                'id' => 'resp-3',
                'choices' => [[
                    'message' => [
                        'role' => 'assistant',
                        'content' => json_encode([
                            'content' => '   ',
                            'blocks' => [],
                            'actions' => [],
                        ]),
                    ],
                ]],
                'usage' => [],
            ]),
        ]);

        $this->expectException(RuntimeException::class);

        $provider = new OpenAiCompatibleProvider;
        $provider->chat([['role' => 'user', 'content' => 'Hỏi']]);
    }

    public function test_malformed_json_throws_exception(): void
    {
        Http::fake([
            'https://ai.example.com/v1/chat/completions' => Http::response([
                'id' => 'resp-4',
                'choices' => [[
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'this is not json {',
                    ],
                ]],
                'usage' => [],
            ]),
        ]);

        $this->expectException(RuntimeException::class);

        $provider = new OpenAiCompatibleProvider;
        $provider->chat([['role' => 'user', 'content' => 'Hỏi']]);
    }

    public function test_missing_content_field_throws_exception(): void
    {
        Http::fake([
            'https://ai.example.com/v1/chat/completions' => Http::response([
                'id' => 'resp-5',
                'choices' => [[
                    'message' => [
                        'role' => 'assistant',
                        'content' => json_encode([
                            'blocks' => [],
                            'actions' => [],
                        ]),
                    ],
                ]],
                'usage' => [],
            ]),
        ]);

        $this->expectException(RuntimeException::class);

        $provider = new OpenAiCompatibleProvider;
        $provider->chat([['role' => 'user', 'content' => 'Hỏi']]);
    }

    public function test_blocks_not_an_array_falls_back_to_text(): void
    {
        Http::fake([
            'https://ai.example.com/v1/chat/completions' => Http::response([
                'id' => 'resp-6',
                'choices' => [[
                    'message' => [
                        'role' => 'assistant',
                        'content' => json_encode([
                            'content' => 'Không có block đúng.',
                            'blocks' => 'not-array',
                            'actions' => [],
                        ]),
                    ],
                ]],
                'usage' => [],
            ]),
        ]);

        $provider = new OpenAiCompatibleProvider;
        $result = $provider->chat([['role' => 'user', 'content' => 'Hỏi']]);

        $this->assertSame('Không có block đúng.', $result->content);
        $this->assertCount(1, $result->blocks);
        $this->assertSame('text', $result->blocks[0]['type']);
    }

    public function test_unknown_block_types_are_filtered_out(): void
    {
        Http::fake([
            'https://ai.example.com/v1/chat/completions' => Http::response([
                'id' => 'resp-7',
                'choices' => [[
                    'message' => [
                        'role' => 'assistant',
                        'content' => json_encode([
                            'content' => 'Chỉ giữ lại text.',
                            'blocks' => [
                                ['type' => 'text', 'content' => 'Hợp lệ'],
                                ['type' => 'unknown_block', 'content' => 'Bị loại'],
                                ['type' => 'chart', 'chart_type' => 'pie', 'labels' => [], 'datasets' => []],
                            ],
                            'actions' => [],
                        ]),
                    ],
                ]],
                'usage' => [],
            ]),
        ]);

        $provider = new OpenAiCompatibleProvider;
        $result = $provider->chat([['role' => 'user', 'content' => 'Hỏi']]);

        $this->assertCount(1, $result->blocks);
        $this->assertSame('text', $result->blocks[0]['type']);
        $this->assertSame('Hợp lệ', $result->blocks[0]['content']);
    }

    public function test_text_block_is_trimmed_and_limited(): void
    {
        Http::fake([
            'https://ai.example.com/v1/chat/completions' => Http::response([
                'id' => 'resp-8',
                'choices' => [[
                    'message' => [
                        'role' => 'assistant',
                        'content' => json_encode([
                            'content' => 'Giải thích.',
                            'blocks' => [
                                ['type' => 'text', 'content' => '  Nhiều khoảng trắng đầu cuối  '],
                            ],
                            'actions' => [],
                        ]),
                    ],
                ]],
                'usage' => [],
            ]),
        ]);

        $provider = new OpenAiCompatibleProvider;
        $result = $provider->chat([['role' => 'user', 'content' => 'Hỏi']]);

        $this->assertSame('Nhiều khoảng trắng đầu cuối', $result->blocks[0]['content']);
    }

    public function test_table_with_empty_rows_is_ignored(): void
    {
        Http::fake([
            'https://ai.example.com/v1/chat/completions' => Http::response([
                'id' => 'resp-9',
                'choices' => [[
                    'message' => [
                        'role' => 'assistant',
                        'content' => json_encode([
                            'content' => 'Không có bảng.',
                            'blocks' => [
                                [
                                    'type' => 'table',
                                    'headers' => ['A'],
                                    'rows' => [],
                                ],
                            ],
                            'actions' => [],
                        ]),
                    ],
                ]],
                'usage' => [],
            ]),
        ]);

        $provider = new OpenAiCompatibleProvider;
        $result = $provider->chat([['role' => 'user', 'content' => 'Hỏi']]);

        $this->assertCount(1, $result->blocks);
        $this->assertSame('text', $result->blocks[0]['type']);
    }

    public function test_chart_with_invalid_type_is_ignored(): void
    {
        Http::fake([
            'https://ai.example.com/v1/chat/completions' => Http::response([
                'id' => 'resp-10',
                'choices' => [[
                    'message' => [
                        'role' => 'assistant',
                        'content' => json_encode([
                            'content' => 'Không có biểu đồ.',
                            'blocks' => [
                                [
                                    'type' => 'chart',
                                    'chart_type' => 'radar',
                                    'labels' => ['A', 'B'],
                                    'datasets' => [
                                        ['label' => 'X', 'data' => [1, 2]],
                                    ],
                                ],
                            ],
                            'actions' => [],
                        ]),
                    ],
                ]],
                'usage' => [],
            ]),
        ]);

        $provider = new OpenAiCompatibleProvider;
        $result = $provider->chat([['role' => 'user', 'content' => 'Hỏi']]);

        $this->assertCount(1, $result->blocks);
        $this->assertSame('text', $result->blocks[0]['type']);
    }

    public function test_plain_text_answer_is_salvaged_instead_of_failing(): void
    {
        Http::fake([
            'https://ai.example.com/v1/chat/completions' => Http::response([
                'id' => 'resp-11',
                'choices' => [[
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'Mình chỉ tra được số liệu của tiệm, chưa xem được thời tiết nhé.',
                    ],
                ]],
                'usage' => [],
            ]),
        ]);

        $provider = new OpenAiCompatibleProvider;
        $result = $provider->chat([['role' => 'user', 'content' => 'Thời tiết hôm nay thế nào?']]);

        $this->assertSame('Mình chỉ tra được số liệu của tiệm, chưa xem được thời tiết nhé.', $result->content);
        $this->assertCount(1, $result->blocks);
        $this->assertSame('text', $result->blocks[0]['type']);
    }

    public function test_raw_newlines_inside_json_string_are_repaired(): void
    {
        Http::fake([
            'https://ai.example.com/v1/chat/completions' => Http::response([
                'id' => 'resp-12',
                'choices' => [[
                    'message' => [
                        'role' => 'assistant',
                        'content' => "{\"content\": \"Doanh thu tháng này:\n```chart\nbar\n```\", \"blocks\": [], \"actions\": []}",
                    ],
                ]],
                'usage' => [],
            ]),
        ]);

        $provider = new OpenAiCompatibleProvider;
        $result = $provider->chat([['role' => 'user', 'content' => 'Biểu đồ doanh thu']]);

        $this->assertStringContainsString('Doanh thu tháng này:', $result->content);
        $this->assertStringContainsString('```chart', $result->content);
    }

    public function test_prose_around_json_object_is_ignored(): void
    {
        Http::fake([
            'https://ai.example.com/v1/chat/completions' => Http::response([
                'id' => 'resp-13',
                'choices' => [[
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'Đây là kết quả bạn cần: '.json_encode([
                            'content' => 'Doanh thu tháng 9 là 17 triệu.',
                            'blocks' => [],
                            'actions' => [],
                        ]).' Hy vọng giúp được bạn.',
                    ],
                ]],
                'usage' => [],
            ]),
        ]);

        $provider = new OpenAiCompatibleProvider;
        $result = $provider->chat([['role' => 'user', 'content' => 'Hỏi']]);

        $this->assertSame('Doanh thu tháng 9 là 17 triệu.', $result->content);
    }

    public function test_missing_content_falls_back_to_first_text_block(): void
    {
        Http::fake([
            'https://ai.example.com/v1/chat/completions' => Http::response([
                'id' => 'resp-14',
                'choices' => [[
                    'message' => [
                        'role' => 'assistant',
                        'content' => json_encode([
                            'blocks' => [
                                ['type' => 'text', 'content' => 'Tháng này tiệm có 42 lượt khách.'],
                            ],
                            'actions' => [],
                        ]),
                    ],
                ]],
                'usage' => [],
            ]),
        ]);

        $provider = new OpenAiCompatibleProvider;
        $result = $provider->chat([['role' => 'user', 'content' => 'Hỏi']]);

        $this->assertSame('Tháng này tiệm có 42 lượt khách.', $result->content);
    }
}
