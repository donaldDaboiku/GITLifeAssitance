<?php

namespace App\Http\Controllers;

use App\Http\Requests\SnoozeOccurrenceRequest;
use App\Http\Requests\VisitFollowUpRequest;
use App\Http\Resources\ActivityResource;
use App\Http\Resources\OccurrenceResource;
use App\Models\Activity;
use App\Models\ActivityOccurrence;
use App\Services\OccurrenceService;
use App\Services\VisitFollowUpService;
use Illuminate\Http\JsonResponse;

class OccurrenceController extends Controller
{
    public function pay(ActivityOccurrence $occurrence, OccurrenceService $occurrences): ActivityResource
    {
        $this->authorize('update', $occurrence);

        return new ActivityResource($occurrences->markPaid($occurrence));
    }

    public function complete(ActivityOccurrence $occurrence, OccurrenceService $occurrences): JsonResponse
    {
        $this->authorize('update', $occurrence);
        $result = $occurrences->complete($occurrence);

        return response()->json([
            'data' => (new ActivityResource($result['activity']))->resolve(),
            'follow_up_offers' => $result['follow_up_offers'],
        ]);
    }

    public function skip(ActivityOccurrence $occurrence, OccurrenceService $occurrences): ActivityResource
    {
        $this->authorize('update', $occurrence);

        return new ActivityResource($occurrences->skip($occurrence));
    }

    public function snooze(SnoozeOccurrenceRequest $request, ActivityOccurrence $occurrence, OccurrenceService $occurrences): JsonResponse
    {
        $this->authorize('update', $occurrence);
        $updated = $occurrences->snooze($occurrence, $request->input('preset'), $request->input('until'));

        return (new OccurrenceResource($updated))->response();
    }

    public function visitFollowUps(
        VisitFollowUpRequest $request,
        Activity $activity,
        VisitFollowUpService $followUps,
    ): JsonResponse {
        $this->authorize('update', $activity);
        abort_unless($activity->type === 'visit', 422, 'Follow-ups are only for visits.');

        $created = $followUps->apply(
            $activity,
            $request->user(),
            $request->validated('choices'),
            $request->validated('next_visit_on'),
        );

        return response()->json([
            'data' => ActivityResource::collection(collect($created))->resolve(),
        ], 201);
    }
}
