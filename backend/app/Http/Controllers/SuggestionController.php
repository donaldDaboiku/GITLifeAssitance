<?php

namespace App\Http\Controllers;

use App\Http\Requests\ConfirmSuggestionRequest;
use App\Http\Resources\ActivityResource;
use App\Models\AuditLog;
use App\Services\FollowUpSuggestionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SuggestionController extends Controller
{
    public function index(Request $request, FollowUpSuggestionService $suggestions): JsonResponse
    {
        return response()->json([
            'data' => $suggestions->suggest($request->user()),
        ]);
    }

    public function confirm(ConfirmSuggestionRequest $request, FollowUpSuggestionService $suggestions): JsonResponse
    {
        $activity = $suggestions->confirm($request->user(), $request->validated('payload'));
        AuditLog::write($request->user()->id, 'suggestion_confirmed', [
            'activity_id' => $activity->id,
            'suggestion_id' => $request->input('suggestion_id'),
        ]);

        return response()->json([
            'data' => (new ActivityResource($activity))->resolve(),
        ], 201);
    }
}
