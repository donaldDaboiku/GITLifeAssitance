<?php

namespace App\Http\Controllers;

use App\Http\Requests\AskAssistantRequest;
use App\Http\Requests\ConfirmAssistantRequest;
use App\Http\Requests\ParseAssistantRequest;
use App\Http\Requests\TranscribeAssistantRequest;
use App\Http\Resources\ActivityResource;
use App\Services\Assistant\AssistantService;
use Illuminate\Http\JsonResponse;

class AssistantController extends Controller
{
    public function parse(ParseAssistantRequest $request, AssistantService $assistant): JsonResponse
    {
        $proposal = $assistant->parse($request->user(), $request->string('text')->toString());

        return response()->json(['proposal' => $proposal]);
    }

    public function confirm(ConfirmAssistantRequest $request, AssistantService $assistant): JsonResponse
    {
        $activity = $assistant->confirm($request->user(), $request->validated('proposal'));

        return response()->json([
            'data' => (new ActivityResource($activity))->resolve(),
        ], 201);
    }

    public function ask(AskAssistantRequest $request, AssistantService $assistant): JsonResponse
    {
        return response()->json($assistant->ask($request->user(), $request->string('question')->toString()));
    }

    public function transcribe(TranscribeAssistantRequest $request, AssistantService $assistant): JsonResponse
    {
        return response()->json(
            $assistant->transcribe($request->user(), $request->file('audio'))
        );
    }
}
