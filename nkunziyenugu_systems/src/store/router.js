
//CREATE ROUTES LIST
const routes = [
    // Protected Routes
    {
        path: '/',
        name: 'MainDashboard',
        component: () => import('../components/views/MainDashboard.vue'),
        meta: { requiresAuth: true, roles: ['admin', 'owner', 'superAdmin', 'user'] }
    },
    {
        path: '/UserList',
        name: 'UserList',
        component: () => import('../components/views/users/UserList.vue'),
        meta: { requiresAuth: true, roles: ['admin', 'owner', 'superAdmin'] }
    },
    {
        path: '/AddUser',
        name: 'AddUser',
        component: () => import('../components/views/users/AddUser.vue'),
        meta: { requiresAuth: true, roles: ['admin', 'owner', 'superAdmin'] }
    },
    {
        path: '/EditUser/:id',
        name: 'EditUser',
        component: () => import('../components/views/users/EditUser.vue'),
        props: true,
        meta: { requiresAuth: true, roles: ['admin', 'owner', 'superAdmin'] }
    },
    {
        path: '/AccountList',
        name: 'Accounts',
        component: () => import('../components/views/accounts/AccountList.vue'),
        meta: { requiresAuth: true, roles: ['admin', 'owner', 'superAdmin'] }
    },
    {
        path: '/AddAccount',
        name: 'AddAccount',
        component: () => import('../components/views/accounts/AddAccount.vue'),
        meta: { requiresAuth: true, roles: ['admin', 'owner', 'superAdmin'] }
    },
    {
        path: '/EditAccount/:id',
        name: 'EditAccount',
        component: () => import('../components/views/accounts/EditAccount.vue'),
        props: true,
        meta: { requiresAuth: true, roles: ['admin', 'owner', 'superAdmin'] }
    },
    {
        path: '/AuditLogs',
        name: 'AuditLogs',
        component: () => import('../components/views/audit/AuditLogList.vue'),
        meta: { requiresAuth: true, roles: ['admin', 'owner', 'superAdmin'] }
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