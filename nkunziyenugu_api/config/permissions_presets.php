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
];
