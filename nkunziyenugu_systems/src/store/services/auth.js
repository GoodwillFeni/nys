

export default {
    state: {
    user: JSON.parse(localStorage.getItem('user')),
    token: localStorage.getItem('token'),
    accounts: JSON.parse(localStorage.getItem('accounts')),
    activeAccount: JSON.parse(localStorage.getItem('activeAccount'))
  },

  mutations: {
    SET_AUTH(state, payload) {
      state.user = payload.user
      state.token = payload.token
      state.accounts = payload.accounts
      state.activeAccount = payload.accounts?.[0] || null

      localStorage.setItem('user', JSON.stringify(payload.user))
      localStorage.setItem('token', payload.token)
      localStorage.setItem('accounts', JSON.stringify(payload.accounts))
      localStorage.setItem('activeAccount', JSON.stringify(state.activeAccount))
    },

    SET_ACTIVE_ACCOUNT(state, account) {
      state.activeAccount = account
      localStorage.setItem('activeAccount', JSON.stringify(account))
    },

    LOGOUT(state) {
      state.user = null
      state.token = null
      state.accounts = null
      state.activeAccount = null
      localStorage.clear()
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

    role: state => state.activeAccount?.pivot?.role || null,

    isSuperAdmin: (state, getters) => getters.role === 'superadmin',
    isOwner: (state, getters) => getters.role === 'owner',
    isAdmin: (state, getters) => getters.role === 'admin',
    isViewer: (state, getters) => getters.role === 'viewer'
  }
}