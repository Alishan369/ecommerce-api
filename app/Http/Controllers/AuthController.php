<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Repositories\Interfaces\UserRepositoryInterface;
use App\Support\India;
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

    /**
     * Bcrypt hash (cost 12) of random bytes nobody knows. Unknown emails are checked against it,
     * so a failed login takes as long as a wrong password and doesn't reveal which emails exist.
     */
    private const DUMMY_HASH = '$2y$12$WQwvxshsWMDnJdAQo54/dOo4CZEdZFhvr5ChLbThWzrLzsedj6/8C';

    public function __construct(private readonly UserRepositoryInterface $users) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $user = $this->users->create([
            ...$request->safe()->only(['name', 'email', 'phone', 'password']),
            'role' => 'customer', // never taken from input
        ]);

        return $this->tokenResponse($user, 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $user = $this->users->findByEmail($request->validated('email'));
        $valid = Hash::check($request->validated('password'), $user?->password ?? self::DUMMY_HASH);

        // Same message for unknown email and wrong password — no account enumeration.
        if (! $user || ! $valid) {
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
        if (is_string($phone = $request->input('phone'))) {
            $request->merge(['phone' => India::phone($phone)]);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'regex:'.India::PHONE_PATTERN],
        ], ['phone.regex' => India::PHONE_MESSAGE]);

        $request->user()->update($data);

        return new UserResource($request->user());
    }

    /** PUT /auth/password — signs out every other device once the password changes. */
    public function updatePassword(Request $request): Response
    {
        $request->validate([
            'current_password' => ['required', 'string', 'current_password:sanctum'],
            'password' => ['required', 'confirmed', Password::defaults(), 'different:current_password'],
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
