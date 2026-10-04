<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Support\IndianPhone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    private const TOKEN_TTL_DAYS = 30;

    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            ...$request->safe()->only(['name', 'email', 'phone', 'password']),
            'role' => 'customer', // never taken from input
        ]);

        return $this->tokenResponse($user, 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::query()->where('email', $request->validated('email'))->first();

        // Same message for unknown email and wrong password — no account enumeration.
        if (! $user || ! $user->password || ! Hash::check($request->validated('password'), $user->password)) {
            throw ValidationException::withMessages([
                'email' => 'These credentials do not match our records.',
            ]);
        }

        return $this->tokenResponse($user);
    }

    public function user(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    /** PUT /auth/user — name and phone. Email changes are not self-service (it's the login + order contact). */
    public function updateProfile(Request $request): UserResource
    {
        $request->merge(['phone' => $request->filled('phone') ? IndianPhone::normalize($request->input('phone')) : null]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'regex:'.IndianPhone::PATTERN],
        ], ['phone.regex' => 'Enter a valid 10-digit Indian mobile number.']);

        $request->user()->update($data);

        return new UserResource($request->user()->refresh());
    }

    /** PUT /auth/password — signs out every other device once the password changes. */
    public function updatePassword(Request $request): Response
    {
        $request->validate([
            'current_password' => ['required', 'string', 'current_password:sanctum'],
            'password' => ['required', 'confirmed', Password::min(8), 'different:current_password'],
        ], ['current_password.current_password' => 'Your current password is incorrect.']);

        $user = $request->user();
        $user->update(['password' => $request->input('password')]);

        $currentTokenId = $user->currentAccessToken()?->getKey();
        $user->tokens()->when($currentTokenId, fn ($q) => $q->whereKeyNot($currentTokenId))->delete();

        return response()->noContent();
    }

    public function logout(Request $request): Response
    {
        $token = $request->user()->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        return response()->noContent();
    }

    private function tokenResponse(User $user, int $status = 200): JsonResponse
    {
        $token = $user->createToken('storefront', ['*'], now()->addDays(self::TOKEN_TTL_DAYS));

        return response()->json([
            'token' => $token->plainTextToken,
            'user' => new UserResource($user),
        ], $status);
    }
}
