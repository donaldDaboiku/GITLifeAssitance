<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDeviceRequest;
use App\Models\AuditLog;
use App\Models\Device;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class DeviceController extends Controller
{
    public function store(StoreDeviceRequest $request): JsonResponse
    {
        $token = $request->user()->createToken($request->string('name')->toString());

        $device = $request->user()->devices()->create([
            'name' => $request->string('name')->toString(),
            'type' => $request->string('type')->toString(),
            'app_version' => $request->input('app_version'),
            'access_token_id' => $token->accessToken->id,
            'last_sync_at' => now(),
            'active' => true,
        ]);

        AuditLog::write($request->user()->id, 'device_created', ['device_id' => $device->id]);

        return response()->json([
            'device' => $this->payload($device),
            'token' => $token->plainTextToken,
        ], 201);
    }

    public function destroy(Request $request, Device $device): Response
    {
        abort_unless($device->user_id === $request->user()->id, 403);

        $device->accessToken?->delete();
        $device->delete();
        AuditLog::write($request->user()->id, 'device_revoked', ['device_id' => $device->id]);

        return response()->noContent();
    }

    private function payload(Device $device): array
    {
        return [
            'id' => $device->id,
            'name' => $device->name,
            'type' => $device->type,
            'app_version' => $device->app_version,
            'active' => $device->active,
        ];
    }
}
