<?php

namespace App\Services\Ai\Contracts;

use App\Services\Ai\AiProviderResult;

interface AiProvider
{
    /** @param array<int, array<string, string>> $messages */
    public function chat(array $messages): AiProviderResult;
}
