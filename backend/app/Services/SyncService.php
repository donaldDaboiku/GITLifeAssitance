<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\ActivityOccurrence;
use App\Models\Contact;
use App\Models\Device;
use App\Models\ShoppingItem;
use App\Models\ShoppingList;
use App\Models\SyncMutation;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class SyncService
{
    public const ENTITIES = [
        'activity' => Activity::class,
        'occurrence' => ActivityOccurrence::class,
        'contact' => Contact::class,
        'shopping_list' => ShoppingList::class,
        'shopping_item' => ShoppingItem::class,
    ];

    private const FIELDS = [
        'activity' => [
            'title', 'description', 'category', 'priority', 'timezone',
            'location', 'contact_id', 'notes', 'type',
        ],
        'occurrence' => [
            'status', 'completed_at', 'snoozed_until', 'due_at', 'due_local_date', 'activity_id',
        ],
        'contact' => [
            'name', 'phone', 'email', 'relationship', 'birthday', 'anniversary', 'notes',
        ],
        'shopping_list' => [
            'name', 'notes', 'activity_id',
        ],
        'shopping_item' => [
            'shopping_list_id', 'activity_id', 'name', 'quantity', 'unit',
            'estimated_price_minor', 'actual_price_minor', 'priority', 'purchased', 'store', 'notes',
        ],
    ];

    public function __construct(private OccurrenceService $occurrences) {}

    /**
     * @param  list<array<string, mixed>>  $mutations
     * @return list<array<string, mixed>>
     */
    public function push(User $user, ?Device $device, array $mutations): array
    {
        $results = [];

        foreach ($mutations as $mutation) {
            $existing = SyncMutation::query()
                ->where('user_id', $user->id)
                ->where('client_mutation_id', $mutation['client_mutation_id'])
                ->first();

            if ($existing) {
                $results[] = $existing->result ?? [
                    'client_mutation_id' => $existing->client_mutation_id,
                    'status' => 'duplicate',
                ];

                continue;
            }

            $results[] = DB::transaction(function () use ($user, $device, $mutation) {
                $result = $this->applyMutation($user, $device, $mutation);

                SyncMutation::query()->create([
                    'user_id' => $user->id,
                    'device_id' => $device?->id,
                    'client_mutation_id' => $mutation['client_mutation_id'],
                    'entity' => $mutation['entity'],
                    'entity_id' => $mutation['id'],
                    'operation' => $mutation['op'],
                    'payload' => $mutation,
                    'result' => $result,
                    'applied_at' => now(),
                    'origin_device_id' => $device?->id,
                ]);

                return $result;
            });
        }

        if ($device) {
            $device->forceFill(['last_sync_at' => now()])->save();
        }

        return $results;
    }

    /**
     * @return array{changes: list<array<string, mixed>>, next_cursor: string|null, has_more: bool}
     */
    public function pull(User $user, ?string $cursor, int $limit = 100, ?Device $device = null): array
    {
        $limit = max(1, min($limit, 500));
        $decoded = $this->decodeCursor($cursor);
        $rows = [];

        foreach (self::ENTITIES as $entity => $class) {
            /** @var Model $class */
            $query = $class::query()
                ->withTrashed()
                ->where('user_id', $user->id);

            if ($decoded) {
                $query->where(function ($builder) use ($decoded) {
                    $builder->where('updated_at', '>', $decoded['t'])
                        ->orWhere(function ($inner) use ($decoded) {
                            $inner->where('updated_at', $decoded['t'])
                                ->where('id', '>', $decoded['i']);
                        });
                });
            }

            foreach ($query->orderBy('updated_at')->orderBy('id')->limit($limit + 1)->get() as $model) {
                $rows[] = $this->serializeChange($entity, $model);
            }
        }

        usort($rows, function (array $a, array $b) {
            $cmp = strcmp($a['updated_at'], $b['updated_at']);

            return $cmp !== 0 ? $cmp : strcmp($a['id'], $b['id']);
        });

        $hasMore = count($rows) > $limit;
        $slice = array_slice($rows, 0, $limit);
        $next = null;
        if ($slice !== []) {
            $last = $slice[array_key_last($slice)];
            $next = $this->encodeCursor($last['updated_at'], $last['id']);
        }

        if ($device) {
            $device->forceFill(['last_sync_at' => now()])->save();
        }

        return [
            'changes' => $slice,
            'next_cursor' => $next,
            'has_more' => $hasMore,
        ];
    }

    /**
     * @param  array<string, mixed>  $mutation
     * @return array<string, mixed>
     */
    private function applyMutation(User $user, ?Device $device, array $mutation): array
    {
        $entity = $mutation['entity'];
        $op = $mutation['op'];
        $id = $mutation['id'];

        if (! isset(self::ENTITIES[$entity])) {
            return $this->result($mutation, 'rejected', message: 'Unknown entity.');
        }

        /** @var class-string<Model> $class */
        $class = self::ENTITIES[$entity];
        $model = $class::query()->withTrashed()->where('user_id', $user->id)->find($id);

        if ($op === 'delete') {
            if (! $model) {
                return $this->result($mutation, 'applied', extra: ['missing' => true]);
            }
            $model->origin_device_id = $device?->id;
            $model->delete();
            $model->refresh();

            return $this->result($mutation, 'applied', $this->serializeChange($entity, $model));
        }

        if ($op !== 'upsert') {
            return $this->result($mutation, 'rejected', message: 'Unknown operation.');
        }

        $data = $this->filterFields($entity, $mutation['data'] ?? []);

        if ($entity === 'occurrence' && $model instanceof ActivityOccurrence) {
            $data = $this->protectCompletedStatus($model, $data, (bool) ($mutation['reopen'] ?? false));
        }

        if (! $model) {
            $model = new $class;
            $model->id = $id;
            $model->user_id = $user->id;
            if ($entity === 'occurrence' && empty($data['activity_id'])) {
                return $this->result($mutation, 'rejected', message: 'occurrence requires activity_id.');
            }
            if ($entity === 'shopping_item' && empty($data['shopping_list_id'])) {
                return $this->result($mutation, 'rejected', message: 'shopping_item requires shopping_list_id.');
            }
        } elseif ($model->trashed()) {
            $model->restore();
        }

        if ($entity === 'occurrence' && $model instanceof ActivityOccurrence && isset($data['status'])) {
            return $this->applyOccurrenceStatus($user, $device, $mutation, $model, $data);
        }

        foreach ($data as $key => $value) {
            $model->{$key} = $value;
        }
        $model->origin_device_id = $device?->id;
        $model->save();

        return $this->result($mutation, 'applied', $this->serializeChange($entity, $model->fresh()));
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $mutation
     * @return array<string, mixed>
     */
    private function applyOccurrenceStatus(
        User $user,
        ?Device $device,
        array $mutation,
        ActivityOccurrence $occurrence,
        array $data,
    ): array {
        $status = $data['status'];

        if (in_array($status, ['completed', 'paid'], true)) {
            if (! in_array($occurrence->status, ['pending', 'in_progress'], true)) {
                return $this->result($mutation, 'applied', $this->serializeChange('occurrence', $occurrence));
            }

            if ($occurrence->activity->type === 'payment' || $status === 'paid') {
                $this->occurrences->markPaid($occurrence);
            } else {
                $this->occurrences->complete($occurrence);
            }
            $occurrence->refresh();
            $occurrence->origin_device_id = $device?->id;
            $occurrence->save();

            return $this->result($mutation, 'applied', $this->serializeChange('occurrence', $occurrence));
        }

        if ($status === 'skipped') {
            $this->occurrences->skip($occurrence);
            $occurrence->refresh();
            $occurrence->origin_device_id = $device?->id;
            $occurrence->save();

            return $this->result($mutation, 'applied', $this->serializeChange('occurrence', $occurrence));
        }

        foreach ($data as $key => $value) {
            $occurrence->{$key} = $value;
        }
        $occurrence->origin_device_id = $device?->id;
        $occurrence->save();

        return $this->result($mutation, 'applied', $this->serializeChange('occurrence', $occurrence->fresh()));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function protectCompletedStatus(ActivityOccurrence $occurrence, array $data, bool $reopen): array
    {
        if (! in_array($occurrence->status, ['completed', 'paid'], true)) {
            return $data;
        }

        if (isset($data['status']) && $data['status'] === 'pending' && ! $reopen) {
            unset($data['status'], $data['completed_at']);
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function filterFields(string $entity, array $data): array
    {
        $allowed = self::FIELDS[$entity] ?? [];

        return array_intersect_key($data, array_flip($allowed));
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeChange(string $entity, Model $model): array
    {
        $attrs = $model->attributesToArray();
        unset($attrs['user_id']);

        return [
            'entity' => $entity,
            'id' => $model->getKey(),
            'version' => (int) ($model->version ?? 1),
            'updated_at' => optional($model->updated_at)->toIso8601String() ?? now()->toIso8601String(),
            'deleted_at' => optional($model->deleted_at)->toIso8601String(),
            'data' => $model->trashed() ? null : $attrs,
        ];
    }

    /**
     * @param  array<string, mixed>  $mutation
     * @param  array<string, mixed>|null  $change
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function result(
        array $mutation,
        string $status,
        ?array $change = null,
        array $extra = [],
        ?string $message = null,
    ): array {
        return array_merge([
            'client_mutation_id' => $mutation['client_mutation_id'],
            'status' => $status,
            'entity' => $mutation['entity'],
            'id' => $mutation['id'],
            'change' => $change,
            'message' => $message,
        ], $extra);
    }

    /**
     * @return array{t: string, i: string}|null
     */
    private function decodeCursor(?string $cursor): ?array
    {
        if (! $cursor) {
            return null;
        }

        $raw = base64_decode($cursor, true);
        if ($raw === false) {
            return null;
        }

        $data = json_decode($raw, true);
        if (! is_array($data) || empty($data['t']) || empty($data['i'])) {
            return null;
        }

        return [
            't' => CarbonImmutable::parse((string) $data['t'])->toDateTimeString(),
            'i' => (string) $data['i'],
        ];
    }

    private function encodeCursor(string $updatedAt, string $id): string
    {
        return base64_encode(json_encode([
            't' => $updatedAt,
            'i' => $id,
        ], JSON_THROW_ON_ERROR));
    }
}
