<?php
use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\Api\PasswordResetController;
use App\Http\Controllers\Api\AccountsController;

Route::post('/register', [AuthController::class, 'register']); //Route for registering a new user
Route::post('/login', [AuthController::class, 'login']); //Route for logging in a user
Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLink']); // Route for sending password reset link
Route::post('/reset-password', [PasswordResetController::class, 'resetPassword']); // Route for resetting the password

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', function (Request $request) {
        return $request->user();
    });
    // Protected APIs
    Route::get('/dashboard', fn () => ['status' => 'ok']);
});

Route::middleware('auth:sanctum')->get('/accounts/available', [AccountsController::class, 'availableAccounts']);