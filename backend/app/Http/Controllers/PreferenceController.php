<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdatePreferenceRequest;
use App\Http\Resources\UserResource;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PreferenceController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $preference = $request->user()->preference;

        return response()->json([
            'data' => $this->payload($preference),
        ]);
    }

    public function update(UpdatePreferenceRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();

        if (isset($data['reminder_time']) && strlen($data['reminder_time']) === 5) {
            $data['reminder_time'] .= ':00';
        }
        if (isset($data['morning_summary_time']) && strlen($data['morning_summary_time']) === 5) {
            $data['morning_summary_time'] .= ':00';
        }

        $preference = $user->preference()->updateOrCreate(['user_id' => $user->id], $data);
        AuditLog::write($user->id, 'preferences_updated', array_keys($data));

        return response()->json([
            'data' => $this->payload($preference->fresh()),
            'user' => (new UserResource($user->fresh()->load('preference')))->resolve(),
        ]);
    }

    private function payload(mixed $preference): array
    {
        return [
            'timezone' => $preference?->timezone ?? 'Africa/Lagos',
            'reminder_time' => substr((string) ($preference?->reminder_time ?? '09:00:00'), 0, 5),
            'due_soon_days' => $preference?->due_soon_days ?? 3,
            'currency' => $preference?->currency ?? 'NGN',
            'theme' => $preference?->theme ?? 'system',
            'morning_summary_enabled' => (bool) ($preference?->morning_summary_enabled ?? false),
            'morning_summary_time' => substr((string) ($preference?->morning_summary_time ?? '07:00:00'), 0, 5),
            'privacy_notice_accepted_at' => $preference?->privacy_notice_accepted_at?->toIso8601String(),
        ];
    }
}
