// Permission-aware auth module.
// - `role` / `isOwner` / `isAdmin` etc. are GONE. Replaced by:
//     canRoute(name)   — true if the active account grants that route
//     canAction(name)  — true if the active account grants that action
//     isSuperAdmin     — kept (reads the user's global flag)
// - Permissions come from login response: accounts[].pivot.route_access + action_access

export default {
  state: {
    user: JSON.parse(localStorage.getItem('user') || 'null'),
    token: localStorage.getItem('token'),
    accounts: JSON.parse(localStorage.getItem('accounts') || 'null'),
    activeAccount: JSON.parse(localStorage.getItem('activeAccount') || 'null'),
    expires_at: JSON.parse(localStorage.getItem('expires_at') || 'null'),
    cartCount: (() => {
      try {
        const cart = JSON.parse(localStorage.getItem('shop_cart') || '{"items":[]}')
        return (cart.items || []).reduce((s, i) => s + (Number(i.qty) || 0), 0)
      } catch { return 0 }
    })()
  },

  mutations: {
    SET_AUTH(state, payload) {
      state.user = payload.user
      state.token = payload.token
      state.accounts = payload.accounts
      state.activeAccount = payload.accounts?.[0] || null
      state.expires_at = payload.expires_at

      localStorage.setItem('user', JSON.stringify(payload.user))
      localStorage.setItem('token', payload.token)
      localStorage.setItem('accounts', JSON.stringify(payload.accounts))
      localStorage.setItem('activeAccount', JSON.stringify(state.activeAccount))
      localStorage.setItem('expires_at', JSON.stringify(payload.expires_at))
    },

    SET_ACTIVE_ACCOUNT(state, account) {
      state.activeAccount = account
      localStorage.setItem('activeAccount', JSON.stringify(account))
    },

    SET_CART_COUNT(state, count) {
      state.cartCount = count
    },

    LOGOUT(state) {
      state.user = null
      state.token = null
      state.accounts = null
      state.activeAccount = null
      state.expires_at = null

      localStorage.removeItem('user')
      localStorage.removeItem('token')
      localStorage.removeItem('accounts')
      localStorage.removeItem('activeAccount')
      localStorage.removeItem('expires_at')
    }
  },

  actions: {
    login({ commit }, payload) {
      commit('SET_AUTH', payload)
    },

    logout({ commit }) {
      commit('LOGOUT')
    },

    switchAccount({ commit }, account) {
      commit('SET_ACTIVE_ACCOUNT', account)
    },

    updateCartCount({ commit }) {
      try {
        const cart = JSON.parse(localStorage.getItem('shop_cart') || '{"items":[]}')
        const count = (cart.items || []).reduce((s, i) => s + (Number(i.qty) || 0), 0)
        commit('SET_CART_COUNT', count)
      } catch {
        commit('SET_CART_COUNT', 0)
      }
    }
  },

  getters: {
    isAuthenticated: state => !!state.token,
    isSuperAdmin: state => !!state.user?.is_super_admin,
    cartCount: state => state.cartCount,

    /** Permission: can the user navigate to a given named route in the active account? */
    canRoute: (state, getters) => (routeName) => {
      if (!state.token) return false
      if (getters.isSuperAdmin) return true
      const list = state.activeAccount?.pivot?.route_access ?? state.activeAccount?.route_access ?? []
      return Array.isArray(list) && list.includes(routeName)
    },

    /** Permission: can the user perform a given action in the active account? */
    canAction: (state, getters) => (actionName) => {
      if (!state.token) return false
      if (getters.isSuperAdmin) return true
      const list = state.activeAccount?.pivot?.action_access ?? state.activeAccount?.action_access ?? []
      return Array.isArray(list) && list.includes(actionName)
    },

    /**
     * Legacy compatibility: "is the user privileged to do admin-type things?"
     * True if they have any write-class action. Used by shop components that
     * still have an isPrivileged computed; keeps the old UX working.
     */
    isPrivileged: (state, getters) => {
      if (!state.token) return false
      if (getters.isSuperAdmin) return true
      const list = state.activeAccount?.pivot?.action_access ?? state.activeAccount?.action_access ?? []
      return Array.isArray(list) && ['add','edit','delete','approve','complete'].some(a => list.includes(a))
    }
  }
}
