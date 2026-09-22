<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreShoppingItemRequest;
use App\Http\Requests\StoreShoppingListRequest;
use App\Http\Requests\UpdateShoppingItemRequest;
use App\Http\Resources\ShoppingListResource;
use App\Models\ShoppingItem;
use App\Models\ShoppingList;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ShoppingController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $lists = ShoppingList::query()
            ->where('user_id', $request->user()->id)
            ->with('items')
            ->orderByDesc('updated_at')
            ->get();

        return ShoppingListResource::collection($lists);
    }

    public function store(StoreShoppingListRequest $request): JsonResponse
    {
        $list = $request->user()->shoppingLists()->create($request->safe()->only(['name', 'notes']));
        foreach ($request->validated('items', []) as $item) {
            $list->items()->create([
                ...$item,
                'user_id' => $request->user()->id,
            ]);
        }

        return (new ShoppingListResource($list->load('items')))->response()->setStatusCode(201);
    }

    public function show(Request $request, ShoppingList $shoppingList): ShoppingListResource
    {
        abort_unless($shoppingList->user_id === $request->user()->id, 403);

        return new ShoppingListResource($shoppingList->load('items'));
    }

    public function addItem(StoreShoppingItemRequest $request, ShoppingList $shoppingList): JsonResponse
    {
        abort_unless($shoppingList->user_id === $request->user()->id, 403);
        $item = $shoppingList->items()->create([
            ...$request->validated(),
            'user_id' => $request->user()->id,
        ]);

        return response()->json(['data' => $item], 201);
    }

    public function updateItem(UpdateShoppingItemRequest $request, ShoppingItem $shoppingItem): JsonResponse
    {
        abort_unless($shoppingItem->user_id === $request->user()->id, 403);
        $shoppingItem->update($request->validated());

        return response()->json(['data' => $shoppingItem->fresh()]);
    }

    public function destroy(Request $request, ShoppingList $shoppingList): Response
    {
        abort_unless($shoppingList->user_id === $request->user()->id, 403);
        $shoppingList->items()->delete();
        $shoppingList->delete();

        return response()->noContent();
    }
}
