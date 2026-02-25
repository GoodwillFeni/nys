<template>
  <aside class="sidebar">
    <div class="app-loading-bar"></div>

    <AccountSelector v-if="isAuthenticated" />
    <nav>
      <RouterLink to="/" v-if="isAuthenticated && !isCustomer"><i class="bi bi-house"></i>Main Dashboard</RouterLink>
      <RouterLink v-if="isAuthenticated && !isCustomer" to="/DeviceList"><i class="bi bi-laptop"></i>Devices</RouterLink>
      <RouterLink v-if="isAuthenticated && !isCustomer" to="/AccountList"><i class="bi bi-bank"></i>Accounts</RouterLink>
      <RouterLink v-if="isAuthenticated && !isCustomer" to="/UserList"><i class="bi bi-people"></i>Users</RouterLink>

      <!-- Shop navigations  -->
      <div v-if="isAuthenticated && !isCustomer" class="nav-group">
        <button
          type="button"
          class="nav-group__toggle"
          :class="{ 'nav-group__toggle--active': isShopRoute }"
          @click="toggleShop"
        >
          <i class="bi bi-cart"></i>
          Shop
        </button>
        <div v-show="shopOpen" class="nav-group__items">
          <RouterLink v-if="isAuthenticated || isSuperAdmin || isOwner || isAdmin" to="/Shop/Products" ><i class="bi bi-boxes"></i>Products</RouterLink>
          <!-- <RouterLink v-if="isAuthenticated" to="/Shop/Cart">Cart</RouterLink> -->
          <RouterLink v-if="isAuthenticated" to="/Shop/MyOrders"><i class="bi bi-cart"></i>My Orders</RouterLink>
          <RouterLink v-if="isSuperAdmin || isOwner || isAdmin" to="/Shop/POS"><i class="bi bi-signpost"></i>POS</RouterLink>
          <RouterLink v-if="isSuperAdmin || isOwner || isAdmin" to="/Shop/SalesSummary"><i class="bi bi-bar-chart"></i>Sales Summary</RouterLink>
          <RouterLink v-if="isSuperAdmin || isOwner || isAdmin" to="/Shop/CashFlow"><i class="bi bi-cash"></i>Cash Flow</RouterLink>
        </div>
      </div>
       <!-- End shop navigations -->

        <!-- Farm navigations -->
        <div v-if="isAuthenticated && !isCustomer" class="nav-group">
          <button
            type="button"
            class="nav-group__toggle"
            :class="{ 'nav-group__toggle--active': isFarmRoute }"
            @click="toggleFarm">
            <i class="bi bi-tree"></i>
            Farm
          </button>

          <div v-show="farmOpen" class="nav-group__items">
            <RouterLink v-if="isAuthenticated" to="/Farm/FarmDashboard"><i class="bi bi-speedometer2"></i>Farm Dashboard</RouterLink>
            <RouterLink v-if="isAuthenticated" to="/Farm/Farms"><i class="bi bi-house-door"></i>Farms</RouterLink>
            <RouterLink v-if="isAuthenticated" to="/Farm/AnimalList"><i class="bi bi-github"></i>Animals</RouterLink>
            <RouterLink v-if="isAuthenticated" to="/Farm/InventoryView"><i class="bi bi-box-seam"></i>Inventory</RouterLink>
            <RouterLink v-if="isAuthenticated" to="/Farm/PnlReport"><i class="bi bi-clipboard-data"></i>Reports</RouterLink>
          </div>
        </div>
        <!-- End Farm navigations -->
      <RouterLink v-if="isAuthenticated && !isCustomer" to="/AuditLogs" ><i class="bi bi-book"></i>Audit Logs</RouterLink>
      <RouterLink v-if="isAuthenticated && isCustomer" to="/Customer/Credit"><i class="bi bi-cart"></i>My Credit</RouterLink>
      <RouterLink v-if="isAuthenticated && isCustomer" to="/Customer/CreditRequests"><i class="bi bi-cart"></i>Credit Requests</RouterLink>
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
      shopOpen: false,
      farmOpen: false
    }
  },

  computed: {
    isShopRoute() {
      return (this.$route?.path || '').startsWith('/Shop')
    },
    isFarmRoute() {
      return (this.$route?.path || '').startsWith('/Farm')
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
    if (!(newPath || '').startsWith('/Farm')) this.farmOpen = false
  }
},

  methods: {
    toggleShop() {
      const willOpen = !this.shopOpen
      if (willOpen && !this.isShopRoute) {
        this.$router.push('/Shop/Products')
      }
      this.shopOpen = willOpen
    },
    toggleFarm() {
      const willOpen = !this.farmOpen
      if (willOpen && !this.isFarmRoute) {
        this.$router.push('/Farm/FarmDashboard')
      }
      this.farmOpen = willOpen
    }
  }
}
</script>

<style scoped>
.sidebar {
  width: 210px;
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

i {
  margin-right: 10px;
}
</style>
