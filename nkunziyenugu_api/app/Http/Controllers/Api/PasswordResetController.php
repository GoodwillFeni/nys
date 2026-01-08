<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class PasswordResetController extends Controller
{
    /**
     * Send password reset link
     */
    public function sendResetLink(Request $request)
    {
        $request->validate([
            'email' => 'required|email'
        ]);

        // Ensure user exists and is not deleted
        $user = User::where('email', $request->email)
            ->where('deleted_flag', 0)
            ->first();

        if (!$user) {
            return response()->json([
                'message' => 'User not found or has been deleted'
            ], 404);
        }

        $status = Password::broker('users')->sendResetLink([
            'email' => $request->email
        ]);

        if ($status === Password::RESET_LINK_SENT) {
            return response()->json([
                'message' => 'Reset link sent successfully'
            ]);
        }

        return response()->json([
            'message' => 'Unable to send reset link, please confirm the email address is correct'
        ], 500);
    }

    /**
     * Reset password
     */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'token'    => 'required',
            'email'    => 'required|email',
            'password' => 'required|min:6|confirmed',
        ]);

        $status = Password::broker('users')->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->password_hash = Hash::make($password);
                $user->save();
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return response()->json([
                'message' => 'Password reset successful'
            ]);
        }

        return response()->json([
            'message' => 'Invalid or expired token'
        ], 400);
    }
}
