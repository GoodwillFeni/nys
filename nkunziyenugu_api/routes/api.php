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
use App\Http\Controllers\Api\ShopProductController;
use App\Http\Controllers\Api\ShopOrderController;
use App\Http\Controllers\Api\ShopPosController;
use App\Http\Controllers\Api\ShopCashflowController;
use App\Http\Controllers\Api\ShopCustomerController;
use App\Http\Controllers\Api\ShopCustomerPortalController;
use App\Http\Controllers\Api\ShopCreditRequestController;
use App\Http\Controllers\Api\FarmController;
use App\Http\Controllers\Api\AnimalController;
use App\Http\Controllers\Api\AnimalEventController;
use App\Http\Controllers\Api\AnimalDeviceLinkController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\FarmReportController;
use App\Http\Controllers\Api\ShopDashboardController;
use App\Http\Controllers\Api\DashboardController;




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

    // Account-scoped dashboard (X-Account-ID optional for super admin)
    Route::middleware('account.access')->get('/dashboard', [DashboardController::class, 'index']);

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

    // ── Account-scoped routes (X-Account-ID required; super admin bypasses check) ──
    Route::middleware('account.access')->group(function () {

        // Shop
        Route::get('/shop/dashboard', [ShopDashboardController::class, 'dashboard']);
        Route::get('/shop/products', [ShopProductController::class, 'index']);
        Route::get('/shop/products/{product}', [ShopProductController::class, 'show']);
        Route::post('/shop/products', [ShopProductController::class, 'store']);
        Route::put('/shop/products/{product}', [ShopProductController::class, 'update']);
        Route::delete('/shop/products/{product}', [ShopProductController::class, 'destroy']);

        Route::get('/shop/orders/my', [ShopOrderController::class, 'myOrders']);
        Route::post('/shop/orders', [ShopOrderController::class, 'createOrder']);
        Route::post('/shop/orders/{order}/proof', [ShopOrderController::class, 'uploadProof']);
        Route::post('/shop/orders/{order}/ask', [ShopOrderController::class, 'askCustomer']);
        // Admin order management
        Route::get('/shop/orders', [ShopOrderController::class, 'adminIndex']);
        Route::put('/shop/orders/{order}', [ShopOrderController::class, 'adminUpdate']);

        Route::get('/shop/pos/cart', [ShopPosController::class, 'getOpenCart']);
        Route::post('/shop/pos/cart/items', [ShopPosController::class, 'addItem']);
        Route::put('/shop/pos/cart/items/{item}', [ShopPosController::class, 'updateItem']);
        Route::delete('/shop/pos/cart/items/{item}', [ShopPosController::class, 'removeItem']);
        Route::post('/shop/pos/checkout', [ShopPosController::class, 'checkout']);
        Route::put('/shop/pos/sales/{sale}', [ShopPosController::class, 'updateSale']);
        Route::post('/shop/pos/sales/{sale}/mark-paid', [ShopPosController::class, 'markSalePaid']);
        Route::get('/shop/pos/sales-report', [ShopPosController::class, 'salesReport']);
        Route::put('/shop/pos/sale-items/{item}', [ShopPosController::class, 'updateSaleItem']);

        Route::get('/shop/customers', [ShopCustomerController::class, 'index']);
        Route::post('/shop/customers', [ShopCustomerController::class, 'store']);

        Route::get('/shop/customer/me', [ShopCustomerPortalController::class, 'me']);
        Route::get('/shop/customer/credit', [ShopCustomerPortalController::class, 'credit']);
        Route::get('/shop/customer/credit-requests', [ShopCustomerPortalController::class, 'myCreditRequests']);
        Route::post('/shop/customer/credit-requests', [ShopCustomerPortalController::class, 'requestCredit']);

        Route::get('/shop/credit-requests', [ShopCreditRequestController::class, 'index']);
        Route::post('/shop/credit-requests/{creditRequest}/approve', [ShopCreditRequestController::class, 'approve']);
        Route::post('/shop/credit-requests/{creditRequest}/decline', [ShopCreditRequestController::class, 'decline']);

        Route::get('/shop/cashflow', [ShopCashflowController::class, 'index']);
        Route::get('/shop/cashflow/{cashflow}', [ShopCashflowController::class, 'show']);
        Route::post('/shop/cashflow', [ShopCashflowController::class, 'store']);
        Route::put('/shop/cashflow/{cashflow}', [ShopCashflowController::class, 'update']);
        Route::delete('/shop/cashflow/{cashflow}', [ShopCashflowController::class, 'destroy']);

        // Farm
        Route::get('farm/dashboard', [FarmController::class, 'dashboard']);
        Route::get('farm/farms', [FarmController::class, 'index']);
        Route::post('farm/farms', [FarmController::class, 'store']);
        Route::get('farm/farms/{farm}', [FarmController::class, 'show']);
        Route::put('farm/farms/{farm}', [FarmController::class, 'update']);
        Route::delete('farm/farms/{farm}', [FarmController::class, 'destroy']);

        Route::get('farm/animals/types', [AnimalController::class, 'types']);
        Route::post('farm/animals/types', [AnimalController::class, 'storeType']);
        Route::get('farm/animals/breeds', [AnimalController::class, 'breeds']);
        Route::post('farm/animals/breeds', [AnimalController::class, 'storeBreed']);
        Route::get('farm/animals', [AnimalController::class, 'index']);
        Route::post('farm/animals', [AnimalController::class, 'store']);
        Route::get('farm/animals/{animal}', [AnimalController::class, 'show']);
        Route::put('farm/animals/{animal}', [AnimalController::class, 'update']);
        Route::post('farm/animals/{animal}/sell', [AnimalController::class, 'sell']);
        Route::delete('farm/animals/{animal}', [AnimalController::class, 'destroy']);

        Route::post('animal-events/single', [AnimalEventController::class, 'storeSingle']);
        Route::post('animal-events/bulk', [AnimalEventController::class, 'storeBulk']);
        Route::get('animal-events/list', [AnimalEventController::class, 'list']);
        Route::get('animal-events/dashboard', [AnimalEventController::class, 'dashboard']);
        Route::put('animal-events/{id}', [AnimalEventController::class, 'update']);
        Route::delete('animal-events/{id}', [AnimalEventController::class, 'destroy']);

        Route::post('farm/animals/devices/link', [AnimalDeviceLinkController::class, 'linkDevice']);
        Route::post('farm/animals/devices/transfer', [AnimalDeviceLinkController::class, 'transferDevice']);
        Route::put('farm/animals/devices/link/{linkId}', [AnimalDeviceLinkController::class, 'unlinkDevice']);
        Route::get('farm/animals/{animalId}/devices', [AnimalDeviceLinkController::class, 'getAnimalDevices']);
        Route::get('farm/devices/{deviceId}/animals', [AnimalDeviceLinkController::class, 'getDeviceAnimals']);

        Route::get('farm/inventory/items', [InventoryController::class, 'items']);
        Route::post('farm/inventory/items', [InventoryController::class, 'storeItem']);
        Route::put('farm/inventory/items/{id}', [InventoryController::class, 'updateItem']);
        Route::delete('farm/inventory/items/{id}', [InventoryController::class, 'destroyItem']);
        Route::get('farm/inventory/movements', [InventoryController::class, 'movements']);
        Route::post('farm/inventory/movements', [InventoryController::class, 'movement']);

        Route::get('farm/reports/pnl', [FarmReportController::class, 'pnl']);

    }); // end account.access
});

//End of protected routes

//Device management routes
Route::middleware(['auth:sanctum', 'account.access'])->group(function () {
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