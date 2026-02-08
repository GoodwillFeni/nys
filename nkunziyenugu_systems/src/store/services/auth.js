

export default {
    state: {
    user: JSON.parse(localStorage.getItem('user')),
    token: localStorage.getItem('token'),
    accounts: JSON.parse(localStorage.getItem('accounts')),
    activeAccount: JSON.parse(localStorage.getItem('activeAccount')),
    expires_at: JSON.parse(localStorage.getItem('expires_at'))
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

    LOGOUT(state) { // Clear all authentication data
      state.user = null
      state.token = null
      state.accounts = null
      state.activeAccount = null,
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
    }
  },

  getters: {
    isAuthenticated: state => !!state.token,

    role: state => (state.activeAccount?.pivot?.role || null),
    normalizedRole: (state, getters) => (getters.role ? String(getters.role).toLowerCase() : null),

    isSuperAdmin: (state, getters) => getters.normalizedRole === 'superadmin' || getters.normalizedRole === 'super_admin',
    isOwner: (state, getters) => getters.normalizedRole === 'owner',
    isAdmin: (state, getters) => getters.normalizedRole === 'admin',
    isViewer: (state, getters) => getters.normalizedRole === 'viewer'
  }
}