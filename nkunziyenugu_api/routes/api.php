<?php
use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\Api\PasswordResetController;
use App\Http\Controllers\Api\AccountsController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\ImpersonationController;
use App\Http\Controllers\Api\DeviceIngestController;
use App\Http\Controllers\Api\DeviceController;
use App\Http\Controllers\Api\DeviceMessageController;


Route::post('/register', [AuthController::class, 'register']); //Route for registering a new user
Route::post('/login', [AuthController::class, 'login']); //Route for logging in a user
Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLink']); // Route for sending password reset link
Route::post('/reset-password', [PasswordResetController::class, 'resetPassword']); // Route for resetting the password
Route::post('/devices/ingest', [DeviceIngestController::class, 'store'])
    ->middleware('device_auth');

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', function (Request $request) {
        return response()->json([
            'user' => $request->user(),
            'is_impersonating' => session()->has('impersonator_id')
        ]);
    });
    Route::get('/dashboard', fn () => ['status' => 'ok']);
    // Accounts
    Route::get('/accounts/available', [AccountsController::class, 'availableAccounts']);
    Route::post('/accounts', [AccountsController::class, 'createAccount']);
    Route::get('/accounts/{id}', [AccountsController::class, 'getAccountDetails']);
    Route::put('/accounts/{id}', [AccountsController::class, 'updateAccount']);
    Route::delete('/accounts/{id}', [AccountsController::class, 'deleteAccount']);
    // Users
    Route::get('/users', [UserController::class, 'getUsers']);
    Route::post('/users', [UserController::class, 'addUser']);
    Route::get('/users/{id}', [UserController::class, 'getUserDetails']);
    Route::put('/users/{id}', [UserController::class, 'updateUser']);
    Route::delete('/users/{id}', [UserController::class, 'deleteUser']);
    // Audit logs (owner/admin logic handled in controller)
    Route::get('/audit-logs', [AuditLogController::class, 'getLogs']);
    Route::get('/audit-logs/statistics', [AuditLogController::class, 'getStatistics']);
});
//End of protected routes

//Device management routes
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/devices', [DeviceController::class, 'index']);
    Route::post('/devices', [DeviceController::class, 'store']);
    Route::get('/devices/{device}/logs', [DeviceController::class, 'logs']);
    Route::get('/devices/{device}', [DeviceController::class, 'show']);
});

Route::post('/device/message', [DeviceMessageController::class, 'store']);


// Impersonation routes - only accessible by super admins
Route::middleware(['auth:sanctum', 'super_admin'])->group(function () {
    Route::post('/impersonate/{userId}', [ImpersonationController::class, 'impersonate']);
    Route::post('/impersonate/stop', [ImpersonationController::class, 'stop']);
});