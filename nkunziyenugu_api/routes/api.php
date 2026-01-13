<?php
use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\Api\PasswordResetController;
use App\Http\Controllers\Api\AccountsController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\ImpersonationController;

Route::post('/register', [AuthController::class, 'register']); //Route for registering a new user
Route::post('/login', [AuthController::class, 'login']); //Route for logging in a user
Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLink']); // Route for sending password reset link
Route::post('/reset-password', [PasswordResetController::class, 'resetPassword']); // Route for resetting the password

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', function (Request $request) {
       // return $request->user();
       return response()->json([
            'user' => $request->user(),
            'is_impersonating' => session()->has('impersonator_id')
        ]);
    });
    // Protected APIs
    Route::get('/dashboard', fn () => ['status' => 'ok']);
});

Route::middleware('auth:sanctum')->get('/accounts/available', [AccountsController::class, 'availableAccounts']); // Get available accounts for the authenticated user

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/users', [UserController::class, 'getUsers']); // List users
    Route::post('/addUser', [UserController::class, 'addUser']); // Add new user
    Route::get('/users/{id}', [UserController::class, 'getUserDetails']); // Get user details
    Route::put('/users/{id}', [UserController::class, 'updateUser']); // Update user details
    Route::delete('/users/{id}', [UserController::class, 'deleteUser']); // Delete user
    
    // Account CRUD operations
    Route::post('/accounts', [AccountsController::class, 'createAccount']); // Create new account
    Route::get('/accounts/{id}', [AccountsController::class, 'getAccountDetails']); // Get account details
    Route::put('/accounts/{id}', [AccountsController::class, 'updateAccount']); // Update account
    Route::delete('/accounts/{id}', [AccountsController::class, 'deleteAccount']); // Delete account
    
    // Audit Logs (admin/owner only)
    Route::get('/audit-logs', [AuditLogController::class, 'getLogs']); // Get audit logs with filtering
    Route::get('/audit-logs/statistics', [AuditLogController::class, 'getStatistics']); // Get audit log statistics
});


Route::middleware(['auth:sanctum', 'super_admin'])->group(function () {
    Route::post('/impersonate/{userId}', [ImpersonationController::class, 'impersonate']);
    Route::post('/impersonate/stop', [ImpersonationController::class, 'stop']);
});