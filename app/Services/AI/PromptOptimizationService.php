<?php

namespace App\Services\AI;

class PromptOptimizationService
{
    public function optimize(string $userPrompt, string $contentType = 'social_media'): string
    {
        return "Optimized prompt for {$contentType}: " . trim($userPrompt) . " [High Quality, Professional MSME Marketing, Indonesian Context]";
    }
}
