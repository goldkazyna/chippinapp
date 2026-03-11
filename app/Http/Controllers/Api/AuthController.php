<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

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
            $user = match ($provider) {
                'google' => $this->handleGoogleAuth($request),
                'apple' => $this->handleAppleAuth($request),
                'telegram' => $this->handleTelegramAuth($request),
            };

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

    private function handleGoogleAuth(Request $request): User
    {
        $response = Http::withToken($request->token)
            ->get('https://www.googleapis.com/oauth2/v3/userinfo');

        if (!$response->successful()) {
            throw new \Exception('Invalid Google token');
        }

        $data = $response->json();

        return $this->findOrCreateUser(
            provider: 'google',
            providerId: $data['sub'],
            email: $data['email'] ?? null,
            name: $data['name'] ?? null,
            avatar: $data['picture'] ?? null,
        );
    }

    private function handleAppleAuth(Request $request): User
    {
        $response = Http::get('https://appleid.apple.com/auth/keys');

        if (!$response->successful()) {
            throw new \Exception('Failed to fetch Apple public keys');
        }

        $keys = JWK::parseKeySet($response->json());
        $decoded = JWT::decode($request->token, $keys);

        if ($decoded->iss !== 'https://appleid.apple.com') {
            throw new \Exception('Invalid Apple token issuer');
        }

        if ($decoded->aud !== 'com.chippin.chippin') {
            throw new \Exception('Invalid Apple token audience');
        }

        return $this->findOrCreateUser(
            provider: 'apple',
            providerId: $decoded->sub,
            email: $decoded->email ?? null,
            name: $request->input('name'),
            avatar: null,
        );
    }

    private function findOrCreateUser(string $provider, string $providerId, ?string $email, ?string $name, ?string $avatar): User
    {
        $user = User::where('provider', $provider)
            ->where('provider_id', $providerId)
            ->first();

        if ($user) {
            return $user;
        }

        // Check if user with same email exists
        if ($email) {
            $user = User::where('email', $email)->first();
            if ($user) {
                $user->update([
                    'provider' => $provider,
                    'provider_id' => $providerId,
                    'avatar' => $avatar ?? $user->avatar,
                ]);
                return $user;
            }
        }

        return User::create([
            'name' => $name ?? 'User',
            'email' => $email,
            'provider' => $provider,
            'provider_id' => $providerId,
            'avatar' => $avatar,
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
