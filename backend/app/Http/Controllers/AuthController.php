<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\TransientToken;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::query()->create($request->safe()->only(['name', 'email', 'password']));
        Auth::guard('web')->login($user);
        $request->session()->regenerate();
        AuditLog::write($user->id, 'register');

        return (new UserResource($user->load('preference')))->response()->setStatusCode(201);
    }

    public function login(LoginRequest $request): UserResource
    {
        if (! Auth::guard('web')->attempt($request->only('email', 'password'), true)) {
            throw ValidationException::withMessages([
                'email' => 'These credentials do not match our records.',
            ]);
        }

        $request->session()->regenerate();
        AuditLog::write(Auth::guard('web')->id(), 'login');

        return new UserResource(Auth::guard('web')->user()->load('preference'));
    }

    /**
     * Token login for Tauri/Capacitor (and other non-cookie clients).
     */
    public function tokenLogin(LoginRequest $request): JsonResponse
    {
        if (! Auth::guard('web')->attempt($request->only('email', 'password'))) {
            throw ValidationException::withMessages([
                'email' => 'These credentials do not match our records.',
            ]);
        }

        /** @var User $user */
        $user = Auth::guard('web')->user();
        $deviceName = $request->string('device_name')->toString() ?: 'Device';
        $deviceType = $request->string('device_type')->toString() ?: 'web';
        if (! in_array($deviceType, ['web', 'android', 'windows'], true)) {
            $deviceType = 'web';
        }

        $token = $user->createToken($deviceName);
        $device = $user->devices()->create([
            'name' => $deviceName,
            'type' => $deviceType,
            'app_version' => $request->input('app_version'),
            'access_token_id' => $token->accessToken->id,
            'last_sync_at' => now(),
            'active' => true,
        ]);

        AuditLog::write($user->id, 'token_login', ['device_id' => $device->id]);

        return response()->json([
            'data' => (new UserResource($user->load('preference')))->resolve(),
            'token' => $token->plainTextToken,
            'device' => [
                'id' => $device->id,
                'name' => $device->name,
                'type' => $device->type,
                'app_version' => $device->app_version,
                'active' => $device->active,
            ],
        ]);
    }

    public function logout(Request $request): Response
    {
        $user = $request->user();
        $token = $user?->currentAccessToken();

        if ($token && ! $token instanceof TransientToken) {
            $token->delete();
        }

        if ($request->hasSession()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        AuditLog::write($user?->id, 'logout');

        return response()->noContent();
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user()->load('preference'));
    }
}
