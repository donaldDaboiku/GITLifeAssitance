<?php

namespace App\Http\Controllers;

use App\Http\Requests\SubscribePushRequest;
use App\Models\AuditLog;
use App\Models\Device;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PushSubscriptionController extends Controller
{
    public function vapidPublicKey(): JsonResponse
    {
        $key = config('webpush.vapid.public_key');
        abort_unless(filled($key), 503, 'Web Push is not configured. Set WEBPUSH_VAPID_PUBLIC_KEY and WEBPUSH_VAPID_PRIVATE_KEY.');

        return response()->json([
            'public_key' => $key,
        ]);
    }

    public function subscribe(SubscribePushRequest $request): JsonResponse
    {
        $user = $request->user();
        $subscription = $request->validated('subscription');
        $endpoint = $subscription['endpoint'];

        $device = Device::query()
            ->where('user_id', $user->id)
            ->where('type', 'web')
            ->get()
            ->first(function (Device $candidate) use ($endpoint) {
                $payload = json_decode((string) $candidate->push_token, true);

                return ($payload['endpoint'] ?? null) === $endpoint;
            });

        if (! $device) {
            $device = Device::query()
                ->where('user_id', $user->id)
                ->where('type', 'web')
                ->whereNull('push_token')
                ->latest('updated_at')
                ->first();
        }

        if (! $device) {
            $token = $user->createToken('Web Push');
            $device = $user->devices()->create([
                'name' => $request->input('name', 'Web browser'),
                'type' => 'web',
                'app_version' => $request->input('app_version', '0.6.0'),
                'access_token_id' => $token->accessToken->id,
                'active' => true,
                'last_sync_at' => now(),
            ]);
        }

        $device->forceFill([
            'push_token' => json_encode($subscription, JSON_THROW_ON_ERROR),
            'active' => true,
            'last_sync_at' => now(),
        ])->save();

        AuditLog::write($user->id, 'web_push_subscribed', ['device_id' => $device->id]);

        return response()->json([
            'device' => [
                'id' => $device->id,
                'type' => $device->type,
                'name' => $device->name,
            ],
        ]);
    }

    public function unsubscribe(Request $request): Response
    {
        $endpoint = $request->string('endpoint')->toString();
        abort_if($endpoint === '', 422, 'endpoint is required.');

        $devices = Device::query()
            ->where('user_id', $request->user()->id)
            ->where('type', 'web')
            ->whereNotNull('push_token')
            ->get();

        foreach ($devices as $device) {
            $payload = json_decode((string) $device->push_token, true);
            if (($payload['endpoint'] ?? null) === $endpoint) {
                $device->forceFill(['push_token' => null])->save();
                AuditLog::write($request->user()->id, 'web_push_unsubscribed', ['device_id' => $device->id]);
            }
        }

        return response()->noContent();
    }
}
