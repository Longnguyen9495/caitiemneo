<?php

namespace App\Services\Ai;

/**
 * Một mẩu tiến trình phát ra trong lúc model đang trả lời.
 *
 * Nhờ nó mà giao diện hiện chữ dần và nói được "đang tra lịch hẹn…" thay vì để
 * người dùng nhìn ba chấm nhấp nháy suốt mười lăm giây mà không biết chuyện gì
 * đang xảy ra.
 */
final readonly class AiStreamEvent
{
    public const Delta = 'delta';

    public const ToolCall = 'tool_call';

    public const Done = 'done';

    private function __construct(
        public string $type,
        public string $text = '',
        public ?string $tool = null,
        public ?string $toolLabel = null,
    ) {}

    public static function delta(string $text): self
    {
        return new self(self::Delta, text: $text);
    }

    public static function toolCall(string $tool, string $label): self
    {
        return new self(self::ToolCall, tool: $tool, toolLabel: $label);
    }

    public static function done(): self
    {
        return new self(self::Done);
    }
}
