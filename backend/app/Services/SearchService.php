<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Contact;
use App\Models\ShoppingItem;
use App\Models\User;
use Illuminate\Support\Collection;

class SearchService
{
    public function search(User $user, string $query, int $limit = 25): array
    {
        $term = trim($query);
        if ($term === '') {
            return ['data' => []];
        }

        $like = '%'.$term.'%';

        $activities = Activity::query()
            ->where('user_id', $user->id)
            ->where(function ($builder) use ($like) {
                $builder->where('title', 'like', $like)
                    ->orWhere('notes', 'like', $like)
                    ->orWhere('description', 'like', $like)
                    ->orWhere('location', 'like', $like);
            })
            ->limit($limit)
            ->get()
            ->map(fn (Activity $activity) => [
                'kind' => 'activity',
                'id' => $activity->id,
                'type' => $activity->type,
                'title' => $activity->title,
                'subtitle' => $activity->notes,
            ]);

        $contacts = Contact::query()
            ->where('user_id', $user->id)
            ->where(function ($builder) use ($like) {
                $builder->where('name', 'like', $like)
                    ->orWhere('notes', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('phone', 'like', $like);
            })
            ->limit($limit)
            ->get()
            ->map(fn (Contact $contact) => [
                'kind' => 'contact',
                'id' => $contact->id,
                'type' => 'contact',
                'title' => $contact->name,
                'subtitle' => $contact->relationship,
            ]);

        $items = ShoppingItem::query()
            ->where('user_id', $user->id)
            ->where(function ($builder) use ($like) {
                $builder->where('name', 'like', $like)
                    ->orWhere('notes', 'like', $like)
                    ->orWhere('store', 'like', $like);
            })
            ->limit($limit)
            ->get()
            ->map(fn (ShoppingItem $item) => [
                'kind' => 'shopping_item',
                'id' => $item->id,
                'type' => 'shopping',
                'title' => $item->name,
                'subtitle' => $item->store,
                'shopping_list_id' => $item->shopping_list_id,
            ]);

        /** @var Collection<int, array<string, mixed>> $merged */
        $merged = $activities->concat($contacts)->concat($items)->take($limit)->values();

        return ['data' => $merged->all()];
    }
}
