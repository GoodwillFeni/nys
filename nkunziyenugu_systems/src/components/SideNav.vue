<template>
  <aside class="sidebar" :class="{ 'sidebar--open': open }">
    <div class="app-loading-bar"></div>

    <AccountSelector v-if="isAuthenticated" />
    <nav>
      <RouterLink to="/" v-if="canRoute('MainDashboard')"><i class="bi bi-house"></i>Main Dashboard</RouterLink>

      <!-- Devices group -->
      <div v-if="anyDeviceRoute" class="nav-group">
        <button
          type="button"
          class="nav-group__toggle"
          :class="{ 'nav-group__toggle--active': isDeviceRoute }"
          @click="toggleDevices">
          <i class="bi bi-laptop"></i>
          Devices
        </button>
        <div v-show="devicesOpen" class="nav-group__items">
          <RouterLink v-if="canRoute('DeviceDashboard')" to="/Devices/Dashboard"><i class="bi bi-speedometer2"></i>Dashboard</RouterLink>
          <RouterLink v-if="canRoute('DevicesList')" to="/DeviceList"><i class="bi bi-list"></i>All Devices</RouterLink>
          <RouterLink v-if="canRoute('AddDevice')" to="/AddDevice"><i class="bi bi-plus-circle"></i>Add Device</RouterLink>
        </div>
      </div>

      <RouterLink v-if="canRoute('Accounts')" to="/AccountList"><i class="bi bi-bank"></i>Accounts</RouterLink>
      <RouterLink v-if="canRoute('UserList')" to="/UserList"><i class="bi bi-people"></i>Users</RouterLink>

      <!-- Shop -->
      <div v-if="anyShopRoute" class="nav-group">
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
          <RouterLink v-if="canRoute('ShopDashboard')" to="/Shop/Dashboard"><i class="bi bi-speedometer2"></i>Dashboard</RouterLink>
          <RouterLink v-if="canRoute('ShopProducts')" to="/Shop/Products"><i class="bi bi-boxes"></i>Products</RouterLink>
          <RouterLink v-if="canRoute('ShopMyOrders')" to="/Shop/MyOrders"><i class="bi bi-cart"></i>My Orders</RouterLink>
          <RouterLink v-if="canRoute('AdminOrders')" to="/Shop/Orders"><i class="bi bi-bag-check"></i>Orders</RouterLink>
          <RouterLink v-if="canRoute('ShopPOS')" to="/Shop/POS"><i class="bi bi-signpost"></i>POS</RouterLink>
          <RouterLink v-if="canRoute('ShopSalesSummary')" to="/Shop/SalesSummary"><i class="bi bi-bar-chart"></i>Sales Summary</RouterLink>
          <RouterLink v-if="canRoute('ShopCashFlow')" to="/Shop/CashFlow"><i class="bi bi-cash"></i>Cash Flow</RouterLink>
          <RouterLink v-if="canRoute('CustomerCredit')" to="/Customer/Credit"><i class="bi bi-wallet2"></i>My Credit</RouterLink>
          <RouterLink v-if="canRoute('CustomerCreditRequests')" to="/Customer/CreditRequests"><i class="bi bi-receipt"></i>Credit Requests</RouterLink>
        </div>
      </div>

      <!-- Farm -->
      <div v-if="anyFarmRoute" class="nav-group">
        <button
          type="button"
          class="nav-group__toggle"
          :class="{ 'nav-group__toggle--active': isFarmRoute }"
          @click="toggleFarm">
          <i class="bi bi-tree"></i>
          Farm
        </button>

        <div v-show="farmOpen" class="nav-group__items">
          <RouterLink v-if="canRoute('FarmDashboard')" to="/Farm/FarmDashboard"><i class="bi bi-speedometer2"></i>Farm Dashboard</RouterLink>
          <RouterLink v-if="canRoute('FarmList')" to="/Farm/Farms"><i class="bi bi-house-door"></i>Farms</RouterLink>
          <RouterLink v-if="canRoute('AnimalList')" to="/Farm/AnimalList"><i class="bi bi-github"></i>Animals</RouterLink>
          <RouterLink v-if="canRoute('InventoryView')" to="/Farm/InventoryView"><i class="bi bi-box-seam"></i>Inventory</RouterLink>
          <RouterLink v-if="canRoute('PnlReport')" to="/Farm/PnlReport"><i class="bi bi-clipboard-data"></i>Reports</RouterLink>
        </div>
      </div>

      <RouterLink v-if="canRoute('AuditLogs')" to="/AuditLogs"><i class="bi bi-book"></i>Audit Logs</RouterLink>
      <LogOut />
    </nav>
  </aside>
</template>

<script>
import LogOut from '../components/auth/LogOut.vue';
import AccountSelector from './AccountSelector.vue';

export default {
  name: 'SideNav',

  components: { LogOut, AccountSelector },

  props: {
    open: { type: Boolean, default: false }
  },

  emits: ['close'],

  data() {
    return {
      shopOpen: false,
      farmOpen: false,
      devicesOpen: false
    }
  },

  computed: {
    isShopRoute() {
      return (this.$route?.path || '').startsWith('/Shop')
    },
    isFarmRoute() {
      return (this.$route?.path || '').startsWith('/Farm')
    },
    // Match every device-related path: /Devices/Dashboard, /DeviceList, /AddDevice, /DeviceLogs/:id
    isDeviceRoute() {
      return (this.$route?.path || '').startsWith('/Device')
    },
    isAuthenticated() {
      return this.$store.getters.isAuthenticated
    },
    cartCount() {
      return this.$store.getters['cartCount'] || 0
    },
    anyShopRoute() {
      return [
        'ShopDashboard','ShopProducts','ShopMyOrders','AdminOrders',
        'ShopPOS','ShopSalesSummary','ShopCashFlow',
        'CustomerCredit','CustomerCreditRequests',
      ].some(n => this.canRoute(n))
    },
    anyFarmRoute() {
      return [
        'FarmDashboard','FarmList','AnimalList','InventoryView','PnlReport',
      ].some(n => this.canRoute(n))
    },
    anyDeviceRoute() {
      return ['DeviceDashboard','DevicesList','AddDevice'].some(n => this.canRoute(n))
    }
  },

  methods: {
    canRoute(name) {
      return this.$store.getters.canRoute(name)
    },
    canAction(name) {
      return this.$store.getters.canAction(name)
    },
    toggleShop() {
      const willOpen = !this.shopOpen
      if (willOpen && !this.isShopRoute) this.$router.push('/Shop/Products')
      this.shopOpen = willOpen
    },
    toggleFarm() {
      const willOpen = !this.farmOpen
      if (willOpen && !this.isFarmRoute) this.$router.push('/Farm/FarmDashboard')
      this.farmOpen = willOpen
    },
    toggleDevices() {
      const willOpen = !this.devicesOpen
      // Open lands on Dashboard if the user has access to it; otherwise fall
      // back to the flat list. Matches the Shop/Farm "land on a useful page"
      // pattern instead of just expanding/collapsing.
      if (willOpen && !this.isDeviceRoute) {
        if (this.canRoute('DeviceDashboard')) this.$router.push('/Devices/Dashboard')
        else if (this.canRoute('DevicesList')) this.$router.push('/DeviceList')
      }
      this.devicesOpen = willOpen
    }
  },

  watch: {
    '$route.path'(newPath) {
      if (!(newPath || '').startsWith('/Shop')) this.shopOpen = false
      if (!(newPath || '').startsWith('/Farm')) this.farmOpen = false
      if (!(newPath || '').startsWith('/Device')) this.devicesOpen = false
    }
  }
}
</script>

<style scoped>
/* ── Sidebar base ──────────────────────────────────── */
.sidebar {
  width: 220px;
  min-width: 220px;
  height: 100vh;
  position: sticky;
  top: 0;
  background: linear-gradient(135deg, #1e1c33, #3d3650);
  color: #fff;
  padding: 20px 16px;
  display: flex;
  flex-direction: column;
  overflow-y: auto;
  overflow-x: hidden;
  border-right: 1px solid rgba(255,255,255,0.08);
  /* Only show scrollbar when needed */
  scrollbar-width: thin;
  scrollbar-color: rgba(255,255,255,0.2) transparent;
}

.sidebar::-webkit-scrollbar {
  width: 4px;
}
.sidebar::-webkit-scrollbar-track {
  background: transparent;
}
.sidebar::-webkit-scrollbar-thumb {
  background: rgba(255,255,255,0.2);
  border-radius: 4px;
}

nav {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

nav a {
  color: rgba(255,255,255,0.82);
  text-decoration: none;
  padding: 9px 10px;
  border-radius: 7px;
  display: flex;
  align-items: center;
  font-size: 14px;
  transition: background 0.15s;
}

nav a:hover {
  background: rgba(255,255,255,0.1);
  color: #fff;
}

nav a.router-link-active {
  background: rgba(255,255,255,0.15);
  color: #fff;
  font-weight: 600;
}

/* ── Nav groups (Shop / Farm) ──────────────────────── */
.nav-group {
  display: flex;
  flex-direction: column;
}

.nav-group__toggle {
  appearance: none;
  border: 0;
  background: transparent;
  color: rgba(255,255,255,0.82);
  text-align: left;
  padding: 9px 10px;
  cursor: pointer;
  font: inherit;
  font-size: 14px;
  border-radius: 7px;
  display: flex;
  align-items: center;
  transition: background 0.15s;
}

.nav-group__toggle:hover {
  background: rgba(255,255,255,0.1);
  color: #fff;
}

.nav-group__toggle--active {
  font-weight: 600;
  color: #fff;
}

.nav-group__items {
  display: flex;
  flex-direction: column;
  padding-left: 12px;
  border-left: 2px solid rgba(255,255,255,0.1);
  margin-left: 18px;
}

.nav-group__items a {
  font-size: 13px;
  padding: 7px 10px;
}

i {
  margin-right: 10px;
  font-size: 15px;
  opacity: 0.85;
}

.cart-badge {
  margin-left: auto;
  background: #e53935;
  color: #fff;
  font-size: 11px;
  font-weight: 700;
  min-width: 20px;
  height: 20px;
  border-radius: 10px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 0 5px;
}

/* ── Mobile: slide-in drawer ───────────────────────── */
@media (max-width: 768px) {
  .sidebar {
    position: fixed;
    top: 52px;   /* below mobile topbar */
    left: 0;
    bottom: 0;
    z-index: 1100;
    transform: translateX(-100%);
    transition: transform 0.25s ease;
    width: 240px;
    min-width: 240px;
    box-shadow: 4px 0 20px rgba(0,0,0,0.4);
  }

  .sidebar--open {
    transform: translateX(0);
  }
}
</style>
