<?php

namespace App\Http\Controllers;

use App\Http\Requests\PullSyncRequest;
use App\Http\Requests\PushSyncRequest;
use App\Models\Device;
use App\Services\SyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SyncController extends Controller
{
    public function push(PushSyncRequest $request, SyncService $sync): JsonResponse
    {
        $device = $this->resolveDevice($request, $request->input('device_id'));
        $results = $sync->push($request->user(), $device, $request->validated('mutations'));

        return response()->json(['results' => $results]);
    }

    public function pull(PullSyncRequest $request, SyncService $sync): JsonResponse
    {
        $device = $this->resolveDevice($request, $request->input('device_id'));
        $payload = $sync->pull(
            $request->user(),
            $request->input('cursor'),
            (int) $request->input('limit', 100),
            $device,
        );

        return response()->json($payload);
    }

    private function resolveDevice(Request $request, ?string $deviceId): ?Device
    {
        if (! $deviceId) {
            return null;
        }

        return Device::query()
            ->where('user_id', $request->user()->id)
            ->whereKey($deviceId)
            ->first();
    }
}
