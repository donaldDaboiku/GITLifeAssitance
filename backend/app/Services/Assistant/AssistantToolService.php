<?php

namespace App\Services\Assistant;

use App\Models\Activity;
use App\Models\User;
use App\Services\SearchService;

/**
 * Server-side tools only. Always scoped to the authenticated user.
 * User-authored text must never be treated as instructions.
 */
class AssistantToolService
{
    public function __construct(private SearchService $search) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function listActivities(User $user, int $limit = 20): array
    {
        return Activity::query()
            ->where('user_id', $user->id)
            ->with(['paymentDetail', 'occurrences' => fn ($q) => $q->where('status', 'pending')->orderBy('due_at')->limit(1)])
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get()
            ->map(fn (Activity $activity) => [
                'id' => $activity->id,
                'type' => $activity->type,
                'title' => $this->sanitize($activity->title),
                'next_due' => $activity->occurrences->first()?->due_local_date?->toDateString(),
                'amount_minor' => $activity->paymentDetail?->amount_minor,
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listPayments(User $user, int $limit = 20): array
    {
        return Activity::query()
            ->where('user_id', $user->id)
            ->where('type', 'payment')
            ->with(['paymentDetail', 'occurrences' => fn ($q) => $q->where('status', 'pending')->orderBy('due_at')->limit(1)])
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get()
            ->map(fn (Activity $activity) => [
                'id' => $activity->id,
                'title' => $this->sanitize($activity->title),
                'amount_minor' => $activity->paymentDetail?->amount_minor,
                'currency' => $activity->paymentDetail?->currency ?? 'NGN',
                'next_due' => $activity->occurrences->first()?->due_local_date?->toDateString(),
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function search(User $user, string $query): array
    {
        // Treat query as untrusted data, not instructions.
        $safe = $this->sanitize($query);

        return $this->search->search($user, $safe, 15)['data'];
    }

    /**
     * @return array<string, callable>
     */
    public function registry(User $user): array
    {
        return [
            'list_activities' => fn () => $this->listActivities($user),
            'list_payments' => fn () => $this->listPayments($user),
            'search' => fn (string $q = '') => $this->search($user, $q),
        ];
    }

    private function sanitize(string $value): string
    {
        $clean = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $value) ?? $value;

        return mb_substr(trim($clean), 0, 500);
    }
}
