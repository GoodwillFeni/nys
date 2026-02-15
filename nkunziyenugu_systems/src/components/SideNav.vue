<template>
  <aside class="sidebar">
    <AccountSelector v-if="isAuthenticated" />
    <nav>
      <RouterLink to="/" v-if="isAuthenticated && !isCustomer">Main Dashboard</RouterLink>
      <RouterLink v-if="isAuthenticated && !isCustomer" to="/DeviceList">Devices</RouterLink>
      <RouterLink v-if="isAuthenticated && !isCustomer" to="/AccountList">Accounts</RouterLink>
      <RouterLink v-if="isAuthenticated && !isCustomer" to="/UserList">Users</RouterLink>
      <!-- Shop navigations  -->
             <div v-if="isAuthenticated && !isCustomer" class="nav-group">
        <button
          type="button"
          class="nav-group__toggle"
          :class="{ 'nav-group__toggle--active': isShopRoute }"
          @click="toggleShop"
        >
          Shop
        </button>
        <div v-show="shopOpen" class="nav-group__items">
          <RouterLink v-if="isAuthenticated || isSuperAdmin || isOwner || isAdmin" to="/Shop/Products">Products</RouterLink>
          <!-- <RouterLink v-if="isAuthenticated" to="/Shop/Cart">Cart</RouterLink> -->
          <RouterLink v-if="isAuthenticated" to="/Shop/MyOrders">My Orders</RouterLink>
          <RouterLink v-if="isSuperAdmin || isOwner || isAdmin" to="/Shop/POS">POS</RouterLink>
          <RouterLink v-if="isSuperAdmin || isOwner || isAdmin" to="/Shop/SalesSummary">Sales Summary</RouterLink>
          <RouterLink v-if="isSuperAdmin || isOwner || isAdmin" to="/Shop/CashFlow">Cash Flow</RouterLink>
        </div>
      </div>
       <!-- End shop navigations -->
      <RouterLink v-if="isAuthenticated && !isCustomer" to="/AuditLogs">Audit Logs</RouterLink>

      <RouterLink v-if="isAuthenticated && isCustomer" to="/Customer/Credit">My Credit</RouterLink>
      <RouterLink v-if="isAuthenticated && isCustomer" to="/Customer/CreditRequests">Credit Requests</RouterLink>
      <LogOut />
    </nav>
  </aside>
</template>

<script>
import LogOut from '../components/auth/LogOut.vue';
import AccountSelector from './AccountSelector.vue';

export default {
  name: 'SideNav',

  components: {
    LogOut,
    AccountSelector
  },

  data() {
    return {
      shopOpen: false
    }
  },

  computed: {
    isShopRoute() {
      return (this.$route?.path || '').startsWith('/Shop')
    },
    isAuthenticated() {
      return this.$store.getters.isAuthenticated
    },
    isSuperAdmin() {
      return this.$store.getters.isSuperAdmin
    },
    isOwner() {
      return this.$store.getters.isOwner
    },
    isAdmin() {
      return this.$store.getters.isAdmin
    },
    isViewer() {
      return this.$store.getters.isViewer
    },
    isCustomer() {
      return this.$store.getters.isCustomer
    }
  },

  watch: {
    '$route.path'(newPath) {
      if (!(newPath || '').startsWith('/Shop')) this.shopOpen = false
    }
  },

  methods: {
    toggleShop() {
      const willOpen = !this.shopOpen
      if (willOpen && !this.isShopRoute) {
        this.$router.push('/Shop/Products')
      }
      this.shopOpen = willOpen
    }
  }
}
</script>

<style scoped>
.sidebar {
  width: 150px;
  background: linear-gradient(135deg, #27253f, #605a6d);
  color: #fff;
  padding: 20px;
}

.sidebar h2 {
  margin-bottom: 10px;
}

nav {
  display: flex;
  flex-direction: column;
}

nav a {
  color: #fff;
  text-decoration: none;
  padding: 5px 0;
}

.nav-group {
  display: flex;
  flex-direction: column;
}

.nav-group__toggle {
  appearance: none;
  border: 0;
  background: transparent;
  color: #fff;
  text-align: left;
  padding: 5px 0;
  cursor: pointer;
  font: inherit;
}

.nav-group__toggle--active {
  font-weight: bold;
}

.nav-group__items {
  display: flex;
  flex-direction: column;
  padding-left: 10px;
}

nav a.router-link-active {
  font-weight: bold;
}
</style>
