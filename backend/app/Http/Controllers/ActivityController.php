<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreActivityRequest;
use App\Http\Requests\UpdateActivityRequest;
use App\Http\Resources\ActivityResource;
use App\Models\Activity;
use App\Services\ActivityService;
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

    public function store(StoreActivityRequest $request, ActivityService $activities): JsonResponse
    {
        $activity = $activities->create($request->user(), $request->validated());

        return (new ActivityResource($activity))->response()->setStatusCode(201);
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

        return new ActivityResource($activities->update($activity, $request->validated()));
    }

    public function destroy(Request $request, Activity $activity): Response
    {
        $this->authorize('delete', $activity);
        $activity->occurrences()->delete();
        $activity->reminders()->delete();
        $activity->recurrence()?->delete();
        $activity->paymentDetail()?->delete();
        $activity->task()?->delete();
        $activity->delete();

        return response()->noContent();
    }
}
