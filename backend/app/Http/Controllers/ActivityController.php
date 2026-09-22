<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreActivityRequest;
use App\Http\Requests\UpdateActivityRequest;
use App\Http\Resources\ActivityResource;
use App\Models\Activity;
use App\Services\ActivityService;
use App\Services\WorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ActivityController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $activities = Activity::query()
            ->where('user_id', $request->user()->id)
            ->with(Activity::RELATIONS)
            ->orderByDesc('updated_at')
            ->get();

        return ActivityResource::collection($activities);
    }

    public function store(StoreActivityRequest $request, ActivityService $activities, WorkflowService $workflows): JsonResponse
    {
        $data = $request->validated();
        $gift = $data['gift'] ?? null;
        unset($data['gift']);

        $activity = $activities->create($request->user(), $data);
        $payload = ['data' => (new ActivityResource($activity))->resolve()];

        if ($gift && in_array($activity->type, ['birthday', 'anniversary'], true)) {
            $planned = $workflows->planBirthdayGift($activity, $request->user(), $gift);
            $payload['gift_plan'] = [
                'shopping_list_id' => $planned['shopping_list']->id,
                'shopping_item_id' => $planned['shopping_item']->id,
                'task_id' => $planned['task']->id,
            ];
            $payload['data'] = (new ActivityResource($activity->fresh()->load(Activity::RELATIONS)))->resolve();
        }

        return response()->json($payload, 201);
    }

    public function show(Request $request, Activity $activity): ActivityResource
    {
        $this->authorize('view', $activity);
        $activity->load(Activity::RELATIONS);

        return new ActivityResource($activity);
    }

    public function update(UpdateActivityRequest $request, Activity $activity, ActivityService $activities): ActivityResource
    {
        $this->authorize('update', $activity);
        $data = $request->validated();
        unset($data['gift']);

        return new ActivityResource($activities->update($activity, $data));
    }

    public function destroy(Request $request, Activity $activity): Response
    {
        $this->authorize('delete', $activity);
        $activity->occurrences()->delete();
        $activity->reminders()->delete();
        $activity->recurrence()?->delete();
        $activity->paymentDetail()?->delete();
        $activity->task()?->delete();
        $activity->childLinks()->delete();
        $activity->parentLinks()->delete();
        $activity->delete();

        return response()->noContent();
    }
}
