<?php

namespace App\Services\Ai;

final readonly class AiProviderResult
{
    /**
     * @param  array<int, array<string, mixed>>  $blocks
     * @param  array<int, array<string, mixed>>  $actions
     * @param  array<int, array<string, mixed>>  $toolsUsed
     */
    public function __construct(
        public string $content,
        public array $blocks,
        public array $actions,
        public string $provider,
        public string $model,
        public ?int $promptTokens = null,
        public ?int $completionTokens = null,
        public ?int $totalTokens = null,
        public ?int $latencyMs = null,
        public ?string $providerReference = null,
        public array $toolsUsed = [],
    ) {}
}
