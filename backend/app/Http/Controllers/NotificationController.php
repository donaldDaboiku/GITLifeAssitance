<?php

namespace App\Http\Controllers;

use App\Models\ReminderNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $notifications = ReminderNotification::query()
            ->where('user_id', $request->user()->id)
            ->latest()
            ->limit(50)
            ->get(['id', 'title', 'body', 'read_at', 'occurrence_id', 'created_at']);

        return response()->json(['data' => $notifications]);
    }

    public function read(Request $request, ReminderNotification $reminderNotification): Response
    {
        abort_unless($reminderNotification->user_id === $request->user()->id, 403);
        $reminderNotification->read_at = now();
        $reminderNotification->save();

        return response()->noContent();
    }
}
