<?php

namespace App\Http\Controllers;

use App\Services\SearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function __invoke(Request $request, SearchService $search): JsonResponse
    {
        $request->validate([
            'q' => ['required', 'string', 'min:1', 'max:200'],
        ]);

        return response()->json($search->search($request->user(), $request->string('q')->toString()));
    }
}
