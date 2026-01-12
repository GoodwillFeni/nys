import { createApp } from 'vue'
import 'bootstrap/dist/css/bootstrap.min.css'
import 'bootstrap/dist/js/bootstrap.bundle.min.js'
import 'bootstrap-icons/font/bootstrap-icons.css';

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
  const account = JSON.parse(localStorage.getItem('activeAccount'))
  const role = account?.pivot?.role || null
  // console.log('Navigating to:', to.name, 'with role:', role)

  // Guest blocked from protected routes
  if (!token && to.meta.requiresAuth) {
    return next('/LogIn')
  }

  // Logged-in users blocked from guest pages
  if (token && to.meta.guestOnly) {
    return next('/')
  }

  // Role-based protection
  if (token && to.meta.roles) {
    if (!role || !to.meta.roles.includes(role)) {
      return next(false) 
    }
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
