
//CREATE ROUTES LIST
const routes = [
    // Protected Routes
    {
        path: '/',
        name: 'MainDashboard',
        component: () => import('../components/views/MainDashboard.vue'),
        meta: { requiresAuth: true, roles: ['Admin', 'Owner', 'SuperAdmin', 'Viewer'] }
    },
    {
        path: '/UserList',
        name: 'UserList',
        component: () => import('../components/views/users/UserList.vue'),
        meta: { requiresAuth: true, roles: ['Admin', 'Owner', 'SuperAdmin'] }
    },
    {
        path: '/AddUser',
        name: 'AddUser',
        component: () => import('../components/views/users/AddUser.vue'),
        meta: { requiresAuth: true, roles: ['Admin', 'Owner', 'SuperAdmin'] }
    },
    {
        path: '/EditUser/:id',
        name: 'EditUser',
        component: () => import('../components/views/users/EditUser.vue'),
        props: true,
        meta: { requiresAuth: true, roles: ['Admin', 'Owner', 'SuperAdmin'] }
    },
    {
        path: '/AccountList',
        name: 'Accounts',
        component: () => import('../components/views/accounts/AccountList.vue'),
        meta: { requiresAuth: true, roles: ['Admin', 'Owner', 'SuperAdmin'] }
    },
    {
        path: '/AddAccount',
        name: 'AddAccount',
        component: () => import('../components/views/accounts/AddAccount.vue'),
        meta: { requiresAuth: true, roles: ['Admin', 'Owner', 'SuperAdmin'] }
    },
    {
        path: '/EditAccount/:id',
        name: 'EditAccount',
        component: () => import('../components/views/accounts/EditAccount.vue'),
        props: true,
        meta: { requiresAuth: true, roles: ['Admin', 'Owner', 'SuperAdmin'] }
    },
    {
        path: '/AuditLogs',
        name: 'AuditLogs',
        component: () => import('../components/views/audit/AuditLogList.vue'),
        meta: { requiresAuth: true, roles: ['Admin', 'Owner', 'SuperAdmin'] }
    },
    {
        path: '/DeviceList',
        name: 'DevicesList',
        component: () => import('../components/views/devices/DeviceList.vue'),
        meta: { requiresAuth: true, roles: ['Admin', 'Owner', 'SuperAdmin', 'Viewer'] }
    },
    {
        path: '/AddDevice',
        name: 'AddDevice',
        component: () => import('../components/views/devices/AddDevice.vue'),
        meta: { requiresAuth: true, roles: ['Admin', 'Owner', 'SuperAdmin'] }
    },
    {
        path: '/DeviceLogs/:id',
        name: 'DeviceLogs',
        component: () => import('../components/views/devices/DeviceLogs.vue'),
        props: true,
        meta: { requiresAuth: true, roles: ['Admin', 'Owner', 'SuperAdmin'] }
    },
    {
        path: '/Shop/Products',
        name: 'ShopProducts',
        component: () => import('../components/views/shop/ShopProducts.vue'),
        meta: { requiresAuth: true, roles: ['Admin', 'Owner', 'SuperAdmin', 'Viewer'] }
    },
    {
        path: '/Shop/Cart',
        name: 'ShopCart',
        component: () => import('../components/views/shop/ShopCart.vue'),
        meta: { requiresAuth: true, roles: ['Admin', 'Owner', 'SuperAdmin', 'Viewer'] }
    },
    {
        path: '/Shop/MyOrders',
        name: 'ShopMyOrders',
        component: () => import('../components/views/shop/ShopMyOrders.vue'),
        meta: { requiresAuth: true, roles: ['Admin', 'Owner', 'SuperAdmin', 'Viewer'] }
    },
    {
        path: '/Shop/POS',
        name: 'ShopPOS',
        component: () => import('../components/views/shop/ShopPOS.vue'),
        meta: { requiresAuth: true, roles: ['Admin', 'Owner', 'SuperAdmin'] }
    },
    {
        path: '/Shop/SalesSummary',
        name: 'ShopSalesSummary',
        component: () => import('../components/views/shop/ShopSalesSummary.vue'),
        meta: { requiresAuth: true, roles: ['Admin', 'Owner', 'SuperAdmin'] }
    },
    {
        path: '/Shop/CashFlow',
        name: 'ShopCashFlow',
        component: () => import('../components/views/shop/ShopCashFlow.vue'),
        meta: { requiresAuth: true, roles: ['Admin', 'Owner', 'SuperAdmin'] }
    },
    
    // End Protected Routes

    // Public Routes 
    {
        path: '/SignUp',
        name: 'SignUp',
        component: () => import('../components/auth/SignUp.vue'),
        meta: { guestOnly: true }
    },
    {
        path: '/LogIn',
        name: 'LogIn',
        component: () => import('../components/auth/LogIn.vue'),
        meta: { guestOnly: true }
    },
    {
        path: '/ResetPassword',
        name: 'ResetPassword',
        component: () => import('../components/auth/ResetPassword.vue'),
        meta: { guestOnly: true }
    },
    {
        path: '/ForgotPassword',
        name: 'ForgotPassword',
        component: () => import('../components/auth/ForgotPassword.vue'),
        meta: { guestOnly: true }
    }
    // End Public Routes
]

export default routes;