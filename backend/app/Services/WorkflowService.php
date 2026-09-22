<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\ActivityLink;
use App\Models\ShoppingItem;
use App\Models\ShoppingList;
use App\Models\User;
use Carbon\CarbonImmutable;

class WorkflowService
{
    public function __construct(private ActivityService $activities) {}

    /**
     * Birthday with a gift idea → shopping item + buy-gift task, linked to the birthday.
     *
     * @return array{shopping_list: ShoppingList, shopping_item: ShoppingItem, task: Activity}
     */
    public function planBirthdayGift(Activity $birthday, User $user, array $gift): array
    {
        $list = ShoppingList::query()->create([
            'user_id' => $user->id,
            'activity_id' => $birthday->id,
            'name' => 'Gift for '.$birthday->title,
        ]);

        $item = ShoppingItem::query()->create([
            'user_id' => $user->id,
            'shopping_list_id' => $list->id,
            'activity_id' => $birthday->id,
            'name' => $gift['idea'],
            'quantity' => 1,
            'estimated_price_minor' => $gift['budget_minor'] ?? null,
            'priority' => 'high',
            'purchased' => false,
            'notes' => $gift['notes'] ?? null,
        ]);

        $dueDate = $birthday->occurrences()->orderBy('due_at')->value('due_local_date');
        $dueOn = $dueDate
            ? CarbonImmutable::parse((string) $dueDate)->toDateString()
            : CarbonImmutable::now($birthday->timezone)->toDateString();

        $task = $this->activities->create($user, [
            'type' => 'task',
            'title' => 'Buy gift: '.$gift['idea'],
            'due_on' => $dueOn,
            'timezone' => $birthday->timezone,
            'contact_id' => $birthday->contact_id,
            'priority' => 'high',
            'reminder_offsets_minutes' => [10080, 1440],
            'notes' => 'Linked to '.$birthday->title,
        ]);

        $this->link($user, $birthday, $task, 'gift_for');

        return [
            'shopping_list' => $list->load('items'),
            'shopping_item' => $item->fresh(),
            'task' => $task,
        ];
    }

    public function link(User $user, Activity $parent, Activity $child, string $relation): ActivityLink
    {
        return ActivityLink::query()->firstOrCreate(
            [
                'parent_activity_id' => $parent->id,
                'child_activity_id' => $child->id,
                'relation' => $relation,
            ],
            ['user_id' => $user->id],
        );
    }
}
