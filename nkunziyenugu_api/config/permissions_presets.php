<?php
/**
 * Permission presets — UI-only shortcuts used by AddUser / EditPermissions.
 * Picking a preset in the admin UI auto-ticks the checkboxes that match.
 *
 * Presets are NEVER stored. What gets saved to account_users.route_access
 * + action_access is the final checkbox state.
 *
 * 'routes' => '*'   means "all routes from the registry"
 * 'actions' => '*'  means "all actions from the registry"
 */

return [
    'Owner' => [
        'routes'  => '*',
        'actions' => '*',
    ],

    'Admin' => [
        'routes'  => '*',
        'actions' => ['view', 'add', 'edit', 'approve', 'complete', 'assign'],
    ],

    'FarmWorker' => [
        'routes' => [
            'FarmDashboard',
            'FarmList',
            'AnimalList',
            'AnimalEventList',
            'AddAnimal',
            'EditAnimal',
            'AddAnimalEvent',
            'InventoryView',
        ],
        'actions' => ['view', 'add', 'edit'],
    ],

    'ShopKeeper' => [
        'routes' => [
            'ShopDashboard',
            'ShopProducts',
            'ShopPOS',
            'AdminOrders',
            'ShopCashFlow',
            'ShopSalesSummary',
            'AddProduct',
        ],
        'actions' => ['view', 'add', 'edit', 'complete'],
    ],

    'Customer' => [
        'routes' => [
            'ShopProducts',
            'ShopCart',
            'ShopMyOrders',
            'CustomerCredit',
            'CustomerCreditRequests',
        ],
        'actions' => ['view', 'add'],
    ],

    'Viewer' => [
        'routes' => [
            'MainDashboard',
            'FarmDashboard',
            'FarmList',
            'AnimalList',
            'AnimalEventList',
            'InventoryView',
            'ShopDashboard',
            'ShopProducts',
            'DevicesList',
        ],
        'actions' => ['view'],
    ],

    // -------------------------------------------------------------------
    // Self-signup presets, keyed 1:1 with the `account_type` the user
    // picks at signup. AuthController::register and AccountsController::
    // createAccount look these up by name. Common base across all four:
    // Dashboard + Users & Admin + Devices — every signup user owns their
    // account and needs to manage their own team and trackers.
    // Super admin can grant additional routes later via EditPermissions.
    // -------------------------------------------------------------------

    'Home' => [
        'routes' => [
            // Common base
            'MainDashboard',
            'UserList', 'AddUser', 'EditUser',
            'Accounts', 'AddAccount', 'EditAccount',
            'AuditLogs', 'EditPermissions',
            'DeviceDashboard', 'DevicesList', 'AddDevice', 'DeviceLogs',
        ],
        'actions' => ['view', 'add', 'edit', 'delete', 'assign', 'configure'],
    ],

    'Farm' => [
        'routes' => [
            // Common base
            'MainDashboard',
            'UserList', 'AddUser', 'EditUser',
            'Accounts', 'AddAccount', 'EditAccount',
            'AuditLogs', 'EditPermissions',
            'DeviceDashboard', 'DevicesList', 'AddDevice', 'DeviceLogs',
            // Farm domain
            'FarmDashboard', 'FarmList', 'AddFarm', 'EditFarm',
            'AnimalList', 'InventoryView', 'PnlReport',
            'AddAnimal', 'EditAnimal',
            'AddAnimalEvent', 'AnimalEventList', 'AnimalDeviceLink',
            'AnimalTypeList', 'AddAnimalType', 'EditAnimalType',
            'AnimalBreedList', 'AddAnimalBreed', 'EditAnimalBreed',
        ],
        'actions' => ['view', 'add', 'edit', 'delete', 'assign', 'configure', 'approve', 'complete'],
    ],

    'Shop' => [
        'routes' => [
            // Common base
            'MainDashboard',
            'UserList', 'AddUser', 'EditUser',
            'Accounts', 'AddAccount', 'EditAccount',
            'AuditLogs', 'EditPermissions',
            'DeviceDashboard', 'DevicesList', 'AddDevice', 'DeviceLogs',
            // Shop + Customer-facing
            'ShopDashboard', 'ShopProducts', 'ShopCart', 'ShopMyOrders',
            'AdminOrders', 'ShopPOS', 'ShopSalesSummary', 'ShopCashFlow', 'AddProduct',
            'CustomerCredit', 'CustomerCreditRequests',
        ],
        'actions' => ['view', 'add', 'edit', 'delete', 'assign', 'configure', 'approve', 'complete'],
    ],

    'Other' => [
        'routes' => [
            // Common base only — same as Home for v1.
            // Differentiate later if "Other" gets a domain.
            'MainDashboard',
            'UserList', 'AddUser', 'EditUser',
            'Accounts', 'AddAccount', 'EditAccount',
            'AuditLogs', 'EditPermissions',
            'DeviceDashboard', 'DevicesList', 'AddDevice', 'DeviceLogs',
        ],
        'actions' => ['view', 'add', 'edit', 'delete', 'assign', 'configure'],
    ],
];
