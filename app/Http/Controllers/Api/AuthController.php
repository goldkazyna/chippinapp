<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    public function login(Request $request, string $provider): JsonResponse
    {
        $request->validate([
            'token' => 'required|string',
        ]);

        if (!in_array($provider, ['google', 'apple', 'telegram'])) {
            return response()->json([
                'error' => 'invalid_provider',
                'message' => 'Provider must be one of: google, apple, telegram',
            ], 422);
        }

        try {
            if ($provider === 'telegram') {
                $user = $this->handleTelegramAuth($request);
            } else {
                $socialUser = Socialite::driver($provider)->stateless()->userFromToken($request->token);
                $user = $this->findOrCreateUser($socialUser, $provider);
            }

            $token = $user->createToken('mobile-app')->plainTextToken;

            return response()->json([
                'data' => [
                    'user' => new UserResource($user),
                    'token' => $token,
                ],
                'message' => 'Authenticated successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'auth_failed',
                'message' => 'Authentication failed: ' . $e->getMessage(),
            ], 401);
        }
    }

    public function user(Request $request): JsonResponse
    {
        return response()->json([
            'data' => new UserResource($request->user()),
            'message' => 'User retrieved successfully',
        ]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'sometimes|string|max:255',
            'avatar' => 'sometimes|string|max:500',
        ]);

        $request->user()->update($request->only(['name', 'avatar']));

        return response()->json([
            'data' => new UserResource($request->user()->fresh()),
            'message' => 'Profile updated successfully',
        ]);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $request->validate([
            'default_currency' => 'sometimes|string|max:10',
            'language' => 'sometimes|string|max:5',
        ]);

        $request->user()->update($request->only(['default_currency', 'language']));

        return response()->json([
            'data' => new UserResource($request->user()->fresh()),
            'message' => 'Settings updated successfully',
        ]);
    }

    public function devLogin(Request $request): JsonResponse
    {
        if (app()->environment('production')) {
            return response()->json([
                'error' => 'not_available',
                'message' => 'Dev login is not available in production',
            ], 403);
        }

        $request->validate([
            'email' => 'required|email',
            'name' => 'sometimes|string|max:255',
        ]);

        $user = User::firstOrCreate(
            ['email' => $request->email],
            [
                'name' => $request->name ?? 'Dev User',
                'provider' => 'dev',
                'provider_id' => 'dev_' . md5($request->email),
            ]
        );

        $token = $user->createToken('dev-token')->plainTextToken;

        return response()->json([
            'data' => [
                'user' => new UserResource($user),
                'token' => $token,
            ],
            'message' => 'Dev login successful',
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully',
        ]);
    }

    private function findOrCreateUser($socialUser, string $provider): User
    {
        $user = User::where('provider', $provider)
            ->where('provider_id', $socialUser->getId())
            ->first();

        if ($user) {
            return $user;
        }

        // Check if user with same email exists
        if ($socialUser->getEmail()) {
            $user = User::where('email', $socialUser->getEmail())->first();
            if ($user) {
                $user->update([
                    'provider' => $provider,
                    'provider_id' => $socialUser->getId(),
                    'avatar' => $socialUser->getAvatar() ?? $user->avatar,
                ]);
                return $user;
            }
        }

        return User::create([
            'name' => $socialUser->getName() ?? $socialUser->getNickname() ?? 'User',
            'email' => $socialUser->getEmail(),
            'provider' => $provider,
            'provider_id' => $socialUser->getId(),
            'avatar' => $socialUser->getAvatar(),
        ]);
    }

    private function handleTelegramAuth(Request $request): User
    {
        $request->validate([
            'token' => 'required|string',
            'telegram_id' => 'required|string',
            'name' => 'required|string',
        ]);

        $user = User::where('provider', 'telegram')
            ->where('provider_id', $request->telegram_id)
            ->first();

        if ($user) {
            return $user;
        }

        return User::create([
            'name' => $request->name,
            'provider' => 'telegram',
            'provider_id' => $request->telegram_id,
        ]);
    }
}
