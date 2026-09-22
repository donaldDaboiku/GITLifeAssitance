<?php

namespace App\Http\Controllers;

use App\Http\Requests\SnoozeOccurrenceRequest;
use App\Http\Resources\ActivityResource;
use App\Http\Resources\OccurrenceResource;
use App\Models\ActivityOccurrence;
use App\Services\OccurrenceService;
use Illuminate\Http\JsonResponse;

class OccurrenceController extends Controller
{
    public function pay(ActivityOccurrence $occurrence, OccurrenceService $occurrences): ActivityResource
    {
        $this->authorize('update', $occurrence);

        return new ActivityResource($occurrences->markPaid($occurrence));
    }

    public function complete(ActivityOccurrence $occurrence, OccurrenceService $occurrences): ActivityResource
    {
        $this->authorize('update', $occurrence);

        return new ActivityResource($occurrences->complete($occurrence));
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
}
