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

// Public
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLink']);
Route::post('/reset-password', [PasswordResetController::class, 'resetPassword']);
Route::post('/devices/ingest', [DeviceIngestController::class, 'store'])->middleware('device_auth');
Route::post('/device/message', [DeviceMessageController::class, 'store']);

// Authenticated-only (no permission gate — these are session/infrastructure)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', function (Request $request) {
        return response()->json([
            'user' => $request->user(),
            'is_impersonating' => session()->has('impersonator_id'),
        ]);
    });

    // Account selector after login
    Route::get('/accounts/available', [AccountsController::class, 'availableAccounts']);
    // Creating an account is self-service (makes the caller its Owner)
    Route::post('/accounts', [AccountsController::class, 'createAccount']);

    // Dashboard
    Route::middleware(['account.access', 'permission:MainDashboard,view'])
        ->get('/dashboard', [DashboardController::class, 'index']);

    // Accounts
    Route::middleware('permission:Accounts,view')->get('/accounts/{id}', [AccountsController::class, 'getAccountDetails']);
    Route::middleware('permission:EditAccount,edit')->put('/accounts/{id}', [AccountsController::class, 'updateAccount']);
    Route::middleware('permission:Accounts,delete')->delete('/accounts/{id}', [AccountsController::class, 'deleteAccount']);

    // Users
    Route::middleware('permission:UserList,view')->get('/users', [UserController::class, 'getUsers']);
    Route::middleware('permission:AddUser,add')->post('/users', [UserController::class, 'addUser']);
    Route::middleware('permission:EditUser,view')->get('/users/{id}', [UserController::class, 'getUserDetails']);
    Route::middleware('permission:EditUser,edit')->put('/users/{id}', [UserController::class, 'updateUser']);
    Route::middleware('permission:UserList,delete')->delete('/users/{id}', [UserController::class, 'deleteUser']);

    // Audit logs
    Route::middleware('permission:AuditLogs,view')->get('/audit-logs', [AuditLogController::class, 'getLogs']);
    Route::middleware('permission:AuditLogs,view')->get('/audit-logs/statistics', [AuditLogController::class, 'getStatistics']);

    // Account-scoped routes
    Route::middleware('account.access')->group(function () {

        // Shop
        Route::middleware('permission:ShopDashboard,view')->get('/shop/dashboard', [ShopDashboardController::class, 'dashboard']);
        Route::middleware('permission:ShopProducts,view')->get('/shop/products', [ShopProductController::class, 'index']);
        Route::middleware('permission:ShopProducts,view')->get('/shop/products/{product}', [ShopProductController::class, 'show']);
        Route::middleware('permission:AddProduct,add')->post('/shop/products', [ShopProductController::class, 'store']);
        Route::middleware('permission:AddProduct,edit')->put('/shop/products/{product}', [ShopProductController::class, 'update']);
        Route::middleware('permission:ShopProducts,delete')->delete('/shop/products/{product}', [ShopProductController::class, 'destroy']);

        Route::middleware('permission:ShopMyOrders,view')->get('/shop/orders/my', [ShopOrderController::class, 'myOrders']);
        Route::middleware('permission:ShopCart,add')->post('/shop/orders', [ShopOrderController::class, 'createOrder']);
        Route::middleware('permission:ShopMyOrders,edit')->post('/shop/orders/{order}/proof', [ShopOrderController::class, 'uploadProof']);
        Route::middleware('permission:AdminOrders,edit')->post('/shop/orders/{order}/ask', [ShopOrderController::class, 'askCustomer']);
        Route::middleware('permission:AdminOrders,view')->get('/shop/orders', [ShopOrderController::class, 'adminIndex']);
        Route::middleware('permission:AdminOrders,approve')->put('/shop/orders/{order}', [ShopOrderController::class, 'adminUpdate']);

        Route::middleware('permission:ShopPOS,view')->get('/shop/pos/cart', [ShopPosController::class, 'getOpenCart']);
        Route::middleware('permission:ShopPOS,add')->post('/shop/pos/cart/items', [ShopPosController::class, 'addItem']);
        Route::middleware('permission:ShopPOS,edit')->put('/shop/pos/cart/items/{item}', [ShopPosController::class, 'updateItem']);
        Route::middleware('permission:ShopPOS,delete')->delete('/shop/pos/cart/items/{item}', [ShopPosController::class, 'removeItem']);
        Route::middleware('permission:ShopPOS,complete')->post('/shop/pos/checkout', [ShopPosController::class, 'checkout']);
        Route::middleware('permission:ShopPOS,edit')->put('/shop/pos/sales/{sale}', [ShopPosController::class, 'updateSale']);
        Route::middleware('permission:ShopPOS,complete')->post('/shop/pos/sales/{sale}/mark-paid', [ShopPosController::class, 'markSalePaid']);
        Route::middleware('permission:ShopSalesSummary,view')->get('/shop/pos/sales-report', [ShopPosController::class, 'salesReport']);
        Route::middleware('permission:ShopPOS,edit')->put('/shop/pos/sale-items/{item}', [ShopPosController::class, 'updateSaleItem']);

        Route::middleware('permission:ShopProducts,view')->get('/shop/customers', [ShopCustomerController::class, 'index']);
        Route::middleware('permission:ShopProducts,add')->post('/shop/customers', [ShopCustomerController::class, 'store']);

        // Customer portal — authenticated customer self-service
        Route::get('/shop/customer/me', [ShopCustomerPortalController::class, 'me']);
        Route::middleware('permission:CustomerCredit,view')->get('/shop/customer/credit', [ShopCustomerPortalController::class, 'credit']);
        Route::middleware('permission:CustomerCreditRequests,view')->get('/shop/customer/credit-requests', [ShopCustomerPortalController::class, 'myCreditRequests']);
        Route::middleware('permission:CustomerCreditRequests,add')->post('/shop/customer/credit-requests', [ShopCustomerPortalController::class, 'requestCredit']);

        Route::middleware('permission:AdminOrders,view')->get('/shop/credit-requests', [ShopCreditRequestController::class, 'index']);
        Route::middleware('permission:AdminOrders,approve')->post('/shop/credit-requests/{creditRequest}/approve', [ShopCreditRequestController::class, 'approve']);
        Route::middleware('permission:AdminOrders,approve')->post('/shop/credit-requests/{creditRequest}/decline', [ShopCreditRequestController::class, 'decline']);

        Route::middleware('permission:ShopCashFlow,view')->get('/shop/cashflow', [ShopCashflowController::class, 'index']);
        Route::middleware('permission:ShopCashFlow,view')->get('/shop/cashflow/{cashflow}', [ShopCashflowController::class, 'show']);
        Route::middleware('permission:ShopCashFlow,add')->post('/shop/cashflow', [ShopCashflowController::class, 'store']);
        Route::middleware('permission:ShopCashFlow,edit')->put('/shop/cashflow/{cashflow}', [ShopCashflowController::class, 'update']);
        Route::middleware('permission:ShopCashFlow,delete')->delete('/shop/cashflow/{cashflow}', [ShopCashflowController::class, 'destroy']);

        // Farm
        Route::middleware('permission:FarmDashboard,view')->get('farm/dashboard', [FarmController::class, 'dashboard']);
        Route::middleware('permission:FarmList,view')->get('farm/farms', [FarmController::class, 'index']);
        Route::middleware('permission:AddFarm,add')->post('farm/farms', [FarmController::class, 'store']);
        Route::middleware('permission:FarmList,view')->get('farm/farms/{farm}', [FarmController::class, 'show']);
        Route::middleware('permission:EditFarm,edit')->put('farm/farms/{farm}', [FarmController::class, 'update']);
        Route::middleware('permission:FarmList,delete')->delete('farm/farms/{farm}', [FarmController::class, 'destroy']);

        Route::middleware('permission:AnimalList,view')->get('farm/animals/types', [AnimalController::class, 'types']);
        Route::middleware('permission:AnimalList,view')->get('farm/animals/types/{id}', [AnimalController::class, 'showType']);
        Route::middleware('permission:AddAnimalType,add')->post('farm/animals/types', [AnimalController::class, 'storeType']);
        Route::middleware('permission:AddAnimalType,edit')->put('farm/animals/types/{id}', [AnimalController::class, 'updateType']);
        Route::middleware('permission:AddAnimalType,delete')->delete('farm/animals/types/{id}', [AnimalController::class, 'destroyType']);
        Route::middleware('permission:AnimalList,view')->get('farm/animals/breeds', [AnimalController::class, 'breeds']);
        Route::middleware('permission:AnimalList,view')->get('farm/animals/breeds/{id}', [AnimalController::class, 'showBreed']);
        Route::middleware('permission:AddAnimalBreed,add')->post('farm/animals/breeds', [AnimalController::class, 'storeBreed']);
        Route::middleware('permission:AddAnimalBreed,edit')->put('farm/animals/breeds/{id}', [AnimalController::class, 'updateBreed']);
        Route::middleware('permission:AddAnimalBreed,delete')->delete('farm/animals/breeds/{id}', [AnimalController::class, 'destroyBreed']);
        Route::middleware('permission:AnimalList,view')->get('farm/animals', [AnimalController::class, 'index']);
        Route::middleware('permission:AddAnimal,add')->post('farm/animals', [AnimalController::class, 'store']);
        Route::middleware('permission:EditAnimal,view')->get('farm/animals/{animal}', [AnimalController::class, 'show']);
        Route::middleware('permission:EditAnimal,edit')->put('farm/animals/{animal}', [AnimalController::class, 'update']);
        Route::middleware('permission:EditAnimal,edit')->post('farm/animals/{animal}/sell', [AnimalController::class, 'sell']);
        Route::middleware('permission:AnimalList,delete')->delete('farm/animals/{animal}', [AnimalController::class, 'destroy']);

        Route::middleware('permission:AddAnimalEvent,add')->post('animal-events/single', [AnimalEventController::class, 'storeSingle']);
        Route::middleware('permission:AddAnimalEvent,add')->post('animal-events/bulk', [AnimalEventController::class, 'storeBulk']);
        Route::middleware('permission:AnimalEventList,view')->get('animal-events/list', [AnimalEventController::class, 'list']);
        Route::middleware('permission:AnimalEventList,view')->get('animal-events/dashboard', [AnimalEventController::class, 'dashboard']);
        Route::middleware('permission:AnimalEventList,edit')->put('animal-events/{id}', [AnimalEventController::class, 'update']);
        Route::middleware('permission:AnimalEventList,delete')->delete('animal-events/{id}', [AnimalEventController::class, 'destroy']);

        Route::middleware('permission:AnimalDeviceLink,assign')->post('farm/animals/devices/link', [AnimalDeviceLinkController::class, 'linkDevice']);
        Route::middleware('permission:AnimalDeviceLink,assign')->post('farm/animals/devices/transfer', [AnimalDeviceLinkController::class, 'transferDevice']);
        Route::middleware('permission:AnimalDeviceLink,edit')->put('farm/animals/devices/link/{linkId}', [AnimalDeviceLinkController::class, 'unlinkDevice']);
        Route::middleware('permission:AnimalDeviceLink,view')->get('farm/animals/{animalId}/devices', [AnimalDeviceLinkController::class, 'getAnimalDevices']);
        Route::middleware('permission:AnimalDeviceLink,view')->get('farm/devices/{deviceId}/animals', [AnimalDeviceLinkController::class, 'getDeviceAnimals']);

        Route::middleware('permission:InventoryView,view')->get('farm/inventory/items', [InventoryController::class, 'items']);
        Route::middleware('permission:InventoryView,add')->post('farm/inventory/items', [InventoryController::class, 'storeItem']);
        Route::middleware('permission:InventoryView,edit')->put('farm/inventory/items/{id}', [InventoryController::class, 'updateItem']);
        Route::middleware('permission:InventoryView,delete')->delete('farm/inventory/items/{id}', [InventoryController::class, 'destroyItem']);
        Route::middleware('permission:InventoryView,view')->get('farm/inventory/movements', [InventoryController::class, 'movements']);
        Route::middleware('permission:InventoryView,add')->post('farm/inventory/movements', [InventoryController::class, 'movement']);

        Route::middleware('permission:PnlReport,view')->get('farm/reports/pnl', [FarmReportController::class, 'pnl']);
    });
});

// Device management
Route::middleware(['auth:sanctum', 'account.access'])->group(function () {
    Route::middleware('permission:DevicesList,view')->get('/devices', [DeviceController::class, 'index']);
    Route::middleware('permission:AddDevice,add')->post('/devices', [DeviceController::class, 'store']);
    Route::middleware('permission:DeviceLogs,view')->get('/devices/{device}/logs', [DeviceController::class, 'logs']);
    Route::middleware('permission:DevicesList,view')->get('/devices/{device}', [DeviceController::class, 'show']);
});

// Impersonation — super admin only
Route::middleware(['auth:sanctum', 'super_admin'])->group(function () {
    Route::post('/impersonate/{userId}', [ImpersonationController::class, 'impersonate']);
    Route::post('/impersonate/stop', [ImpersonationController::class, 'stop']);
});
