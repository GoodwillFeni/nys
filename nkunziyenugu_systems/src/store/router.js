
//CREATE ROUTES LIST
const routes = [
    // Protected Routes
    {
        path: '/',
        name: 'MainDashboard',
        component: () => import('../components/views/MainDashboard.vue'),
        meta: { requiresAuth: true, roles: ['Admin', 'Owner', 'SuperAdmin', 'User'] }
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