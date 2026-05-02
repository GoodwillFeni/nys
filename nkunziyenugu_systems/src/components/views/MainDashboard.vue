<template>
  <div class="dashboard">

    <!-- Top summary cards -->
    <div class="summary-row">
      <div class="summary-card" v-if="d.has_devices">
        <i class="bi bi-laptop icon blue"></i>
        <div>
          <span class="value">{{ d.devices?.online || 0 }} / {{ d.devices?.total || 0 }}</span>
          <span class="label">Devices Online</span>
        </div>
      </div>

      <div class="summary-card" :class="{ 'warning-card': d.alerts > 0 }">
        <i class="bi bi-exclamation-triangle icon orange"></i>
        <div>
          <span class="value">{{ d.alerts || 0 }}</span>
          <span class="label">Alerts</span>
        </div>
      </div>

      <div class="summary-card">
        <i class="bi bi-activity icon green"></i>
        <div>
          <span class="value">{{ d.activity_today || 0 }}</span>
          <span class="label">Activity Today</span>
        </div>
      </div>
    </div>

    <!-- Conditional sections -->
    <div class="grid-2">

      <!-- Farm section -->
      <div class="section-card" v-if="d.has_farm">
        <div class="section-header">
          <h5><i class="bi bi-tree"></i> Farm Overview</h5>
          <button class="button-info button-sm" @click="$router.push({ name: 'FarmDashboard' })">View</button>
        </div>

        <div class="mini-stats">
          <div class="mini-stat">
            <span class="mini-value">{{ d.farm?.animals || 0 }}</span>
            <span class="mini-label">Active Animals</span>
          </div>
          <div class="mini-stat">
            <span class="mini-value">{{ d.farm?.events_this_month || 0 }}</span>
            <span class="mini-label">Events This Month</span>
          </div>
        </div>

        <div class="pnl-row">
          <div class="pnl-item">
            <span class="pnl-label">Income</span>
            <span class="green">R{{ fmt(d.farm?.income) }}</span>
          </div>
          <div class="pnl-item">
            <span class="pnl-label">Expense</span>
            <span class="red">R{{ fmt(d.farm?.expense) }}</span>
          </div>
          <div class="pnl-item">
            <span class="pnl-label">Op. Profit</span>
            <span :class="(d.farm?.profit || 0) >= 0 ? 'green' : 'red'">R{{ fmt(d.farm?.profit) }}</span>
          </div>
          <div class="pnl-item">
            <span class="pnl-label">Investment</span>
            <span class="purple">R{{ fmt(d.farm?.investment) }}</span>
          </div>
        </div>
      </div>

      <!-- Shop section -->
      <div class="section-card" v-if="d.has_shop">
        <div class="section-header">
          <h5><i class="bi bi-cart3"></i> Shop Overview</h5>
          <button class="button-info button-sm" @click="$router.push({ name: 'ShopDashboard' })">View</button>
        </div>

        <div class="mini-stats">
          <div class="mini-stat">
            <span class="mini-value">{{ d.shop?.sales_count || 0 }}</span>
            <span class="mini-label">Sales This Month</span>
          </div>
          <div class="mini-stat" :class="{ 'mini-warn': d.shop?.unpaid > 0 }">
            <span class="mini-value">{{ d.shop?.unpaid || 0 }}</span>
            <span class="mini-label">Unpaid</span>
          </div>
        </div>

        <div class="pnl-row">
          <div class="pnl-item">
            <span class="pnl-label">Revenue</span>
            <span class="blue">R{{ fmt(d.shop?.revenue) }}</span>
          </div>
          <div class="pnl-item">
            <span class="pnl-label">Profit</span>
            <span :class="(d.shop?.profit || 0) >= 0 ? 'green' : 'red'">R{{ fmt(d.shop?.profit) }}</span>
          </div>
        </div>
      </div>

    </div>

    <!-- No data message -->
    <div class="section-card" v-if="!d.has_farm && !d.has_shop && !d.has_devices && loaded">
      <div class="empty-state">
        <i class="bi bi-rocket-takeoff empty-icon"></i>
        <h5>Get Started</h5>
        <p>Start by adding a farm, products, or registering a device.</p>
        <div class="d-flex gap-2 justify-content-center">
          <button class="button-info" @click="$router.push({ name: 'AddFarm' })">Add Farm</button>
          <button class="button-info" @click="$router.push({ name: 'AddProduct' })">Add Product</button>
          <button class="button-info" @click="$router.push({ name: 'AddDevice' })">Add Device</button>
        </div>
      </div>
    </div>

    <!-- Recent Activity -->
    <div class="section-card mt-3" v-if="d.recent_activity?.length">
      <div class="section-header">
        <h5><i class="bi bi-clock-history"></i> Recent Activity</h5>
      </div>
      <table>
        <thead>
          <tr>
            <th>Time</th>
            <th>Action</th>
            <th>Description</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="a in d.recent_activity" :key="a.id">
            <td>{{ formatDate(a.created_at) }}</td>
            <td><span class="badge badge-action">{{ a.action }}</span></td>
            <td>{{ a.description }}</td>
          </tr>
        </tbody>
      </table>
    </div>

  </div>
</template>

<script>
import api from '@/store/services/api';
import { useToast } from "vue-toastification";
const toast = useToast();

export default {
  name: 'MainDashboard',

  data() {
    return {
      loaded: false,
      d: {
        has_farm: false,
        has_shop: false,
        has_devices: false,
        devices: null,
        alerts: 0,
        activity_today: 0,
        farm: null,
        shop: null,
        recent_activity: [],
      }
    }
  },

  mounted() {
    this.loadDashboard();
  },

  methods: {
    async loadDashboard() {
      try {
        const res = await api.get('/dashboard');
        this.d = res.data;
      } catch (e) {
        toast.error('Failed to load dashboard');
      } finally {
        this.loaded = true;
      }
    },

    fmt(val) {
      return parseFloat(val || 0).toFixed(2);
    },

    formatDate(d) {
      if (!d) return '-';
      const dt = new Date(d);
      return dt.toLocaleDateString('en-ZA') + ' ' + dt.toLocaleTimeString('en-ZA', { hour: '2-digit', minute: '2-digit' });
    }
  }
}
</script>

<style scoped>
.dashboard {
  padding: 10px;
  color: #e0e0e0;
}

/* Summary row */
.summary-row {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 14px;
  margin-bottom: 20px;
}

.summary-card {
  background: rgba(255,255,255,0.08);
  border-radius: 10px;
  padding: 18px 20px;
  display: flex;
  align-items: center;
  gap: 14px;
  border: 1px solid rgba(255,255,255,0.08);
}

.summary-card .icon { font-size: 28px; }
.summary-card .value { font-size: 22px; font-weight: bold; display: block; color: #fff; }
.summary-card .label { font-size: 12px; color: rgba(255,255,255,0.5); text-transform: uppercase; }

.green { color: #66bb6a; }
.blue { color: #42a5f5; }
.orange { color: #ffa726; }
.red { color: #ef5350; }
.purple { color: #ce93d8; }

.warning-card { border-left: 3px solid #ffa726; }

/* Grid */
.grid-2 {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
}

@media (max-width: 768px) {
  .grid-2 { grid-template-columns: 1fr; }
}

/* Section card */
.section-card {
  background: rgba(255,255,255,0.06);
  border-radius: 10px;
  padding: 20px;
  border: 1px solid rgba(255,255,255,0.08);
}

.section-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 14px;
}

.section-header h5 {
  margin: 0;
  color: #fff;
  font-size: 15px;
  display: flex;
  align-items: center;
  gap: 8px;
}

/* Mini stats */
.mini-stats {
  display: flex;
  gap: 12px;
  margin-bottom: 14px;
}

.mini-stat {
  background: rgba(255,255,255,0.05);
  border-radius: 8px;
  padding: 10px 16px;
  display: flex;
  flex-direction: column;
  align-items: center;
  flex: 1;
}

.mini-value { font-size: 20px; font-weight: bold; color: #fff; }
.mini-label { font-size: 11px; color: rgba(255,255,255,0.5); text-transform: uppercase; }
.mini-warn .mini-value { color: #ffa726; }

/* P&L row */
.pnl-row {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
  gap: 8px;
}

.pnl-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  background: rgba(255,255,255,0.05);
  border-radius: 8px;
  padding: 10px;
}

.pnl-label { font-size: 10px; color: rgba(255,255,255,0.5); text-transform: uppercase; }
.pnl-item span:last-child { font-size: 15px; font-weight: bold; }

.badge { padding: 3px 8px; border-radius: 12px; font-size: 11px; color: #fff; }
.badge-action { background: #546e7a; }

/* Empty state */
.empty-state {
  text-align: center;
  padding: 40px 20px;
}

.empty-icon { font-size: 48px; color: rgba(255,255,255,0.3); display: block; margin-bottom: 12px; }
.empty-state h5 { color: #fff; margin-bottom: 8px; }
.empty-state p { color: rgba(255,255,255,0.5); margin-bottom: 16px; }

.mt-3 { margin-top: 16px; }
.justify-content-center { justify-content: center; }
</style>
