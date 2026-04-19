import { createApp } from 'vue'
import 'bootstrap/dist/css/bootstrap.min.css'
import 'bootstrap/dist/js/bootstrap.bundle.min.js'
import 'bootstrap-icons/font/bootstrap-icons.css';
import 'leaflet/dist/leaflet.css'

import App from './App.vue'
import { createWebHistory, createRouter } from 'vue-router'
import './assets/global.css'

import store from './store'
import routes from './store/router'

// Vue-toastification
import Toast, { POSITION } from "vue-toastification"
import "vue-toastification/dist/index.css"

const router = createRouter({
  history: createWebHistory(),
  routes
})

router.beforeEach((to, from, next) => {
  const token = localStorage.getItem('token')
  const user = JSON.parse(localStorage.getItem('user') || 'null')
  const isSuper = !!user?.is_super_admin

  // Guest routes
  if (to.meta.guestOnly) {
    if (!token) return next()
    // Authenticated + hitting a guest page: redirect to their first reachable screen
    return next('/')
  }

  // Protected routes need a token
  if (!token && to.meta.requiresAuth) {
    return next('/LogIn')
  }

  // Super admin bypasses permission checks
  if (token && isSuper) return next()

  // Permission check — route name must appear in activeAccount.pivot.route_access
  if (token) {
    const account = JSON.parse(localStorage.getItem('activeAccount') || 'null')
    const list = account?.pivot?.route_access ?? account?.route_access ?? []
    if (to.name && Array.isArray(list) && list.includes(to.name)) return next()
    // No permission — stay where we are
    return next(false)
  }

  next()
})


const app = createApp(App)

app.use(router)
app.use(store)
app.use(Toast, {
  position: POSITION.TOP_RIGHT,
  timeout: 3000,
  closeOnClick: true,
  pauseOnHover: true,
  draggable: true,
  draggablePercent: 0.6,
  showCloseButtonOnHover: false,
  hideProgressBar: false,
  closeButton: "button",
  icon: true,
  rtl: false
})

app.mount('#app')
