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
