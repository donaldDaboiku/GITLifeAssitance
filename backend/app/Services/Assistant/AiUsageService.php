<?php

namespace App\Services\Assistant;

use App\Models\AiUsageEvent;
use App\Models\User;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AiUsageService
{
    public function assertWithinLimits(User $user): void
    {
        $perMinute = (int) config('ai.rate_per_minute', 10);
        $recent = AiUsageEvent::query()
            ->where('user_id', $user->id)
            ->where('created_at', '>=', now()->subMinute())
            ->count();

        if ($recent >= $perMinute) {
            throw new HttpException(429, 'AI rate limit reached. Try again in a minute.');
        }

        $cap = (int) config('ai.monthly_request_cap', 200);
        $monthCount = AiUsageEvent::query()
            ->where('user_id', $user->id)
            ->where('created_at', '>=', now()->startOfMonth())
            ->count();

        if ($monthCount >= $cap) {
            throw new HttpException(429, 'Monthly AI usage cap reached.');
        }
    }

    public function record(User $user, string $kind, array $metadata = [], int $promptTokens = 0, int $completionTokens = 0): void
    {
        AiUsageEvent::query()->create([
            'user_id' => $user->id,
            'kind' => $kind,
            'provider' => config('ai.provider'),
            'model' => config('ai.model'),
            'prompt_tokens' => $promptTokens,
            'completion_tokens' => $completionTokens,
            'metadata' => $metadata,
        ]);
    }

    public function openaiClient(?string $apiKey = null, ?string $baseUrl = null): PendingRequest
    {
        $key = $apiKey ?: config('ai.api_key');
        abort_unless($key, 503, 'AI provider is not configured.');

        return Http::baseUrl(rtrim((string) ($baseUrl ?: config('ai.base_url')), '/'))
            ->withToken((string) $key)
            ->acceptJson()
            ->timeout(60);
    }
}
