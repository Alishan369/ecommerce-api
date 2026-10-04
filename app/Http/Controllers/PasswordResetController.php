<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password as PasswordBroker;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/**
 * Forgot / reset password. The email links to the storefront's /reset-password
 * page (see AppServiceProvider), which posts the token back here.
 */
class PasswordResetController extends Controller
{
    /** POST /auth/forgot-password — same answer whether or not the account exists. */
    public function sendLink(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:255']]);

        // INVALID_USER and RESET_THROTTLED are deliberately not revealed (no account enumeration).
        PasswordBroker::sendResetLink(['email' => strtolower(trim($data['email']))]);

        return response()->json([
            'message' => 'If an account exists for that email, we\'ve sent a link to reset the password. It expires in 60 minutes.',
        ]);
    }

    /** POST /auth/reset-password — sets the new password and signs out every device. */
    public function reset(Request $request): JsonResponse
    {
        $request->merge(['email' => strtolower(trim((string) $request->input('email')))]);

        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $status = PasswordBroker::reset($data, function (User $user, string $password) {
            $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
            $user->tokens()->delete(); // anyone holding an old session is signed out

            event(new PasswordReset($user));
        });

        if ($status !== PasswordBroker::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => $status === PasswordBroker::RESET_THROTTLED
                    ? 'Please wait a minute before trying again.'
                    : 'This reset link is invalid or has expired. Request a new one.',
            ]);
        }

        return response()->json(['message' => 'Your password has been reset. You can sign in with it now.']);
    }
}
