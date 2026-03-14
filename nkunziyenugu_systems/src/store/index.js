// src/store/index.js
import { createStore } from 'vuex'
import auth from './services/auth'
import farmService from './services/farmService'
import device from './services/device'

export default createStore({
  modules: {
    auth,
    farmService,
    device
  }
})

