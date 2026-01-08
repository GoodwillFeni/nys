// src/store/index.js
import { createStore } from 'vuex'
import auth from './services/auth'

export default createStore({
  modules: {
    auth
  }
})

