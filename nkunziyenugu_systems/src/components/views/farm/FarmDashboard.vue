<template>
  <div class="farm-dashboard">

    <!-- Page Header -->
    <div class="card p-3 mb-3">
      <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
        <h4 class="m-0">Farm Dashboard</h4>
        <div class="d-flex align-items-center gap-2 flex-wrap">
          <button class="button-info" @click="$router.push({ name: 'AddAnimal' })">Add Animal</button>
          <button class="button-info" @click="$router.push({ name: 'AnimalEventList' })">Events</button>
          <button class="button-info" @click="$router.push({ name: 'InventoryView' })">Inventory</button>
          <button class="button-info" @click="$router.push({ name: 'PnlReport' })">P&L Report</button>
        </div>
      </div>
    </div>

    <!-- Summary Cards -->
    <div class="summary-row">
      <div class="summary-card">
        <i class="bi bi-house-door icon green"></i>
        <div>
          <span class="value">{{ data.total_farms }}</span>
          <span class="label">Farms</span>
        </div>
      </div>

      <div class="summary-card">
        <i class="bi bi-github icon blue"></i>
        <div>
          <span class="value">{{ data.total_animals }}</span>
          <span class="label">Animals</span>
        </div>
      </div>

      <div class="summary-card" :class="{ 'warning-card': data.low_stock_count > 0 }">
        <i class="bi bi-exclamation-triangle icon orange"></i>
        <div>
          <span class="value">{{ data.low_stock_count }}</span>
          <span class="label">Low Stock</span>
        </div>
      </div>

      <div class="summary-card" :class="data.pnl?.profit >= 0 ? 'profit-card' : 'loss-card'">
        <i class="bi bi-graph-up-arrow icon"></i>
        <div>
          <span class="value">R{{ fmt(data.pnl?.profit) }}</span>
          <span class="label">Month Profit</span>
        </div>
      </div>
    </div>

    <!-- Two column section -->
    <div class="grid-2">

      <!-- Animals by Type -->
      <div class="section-card">
        <h5>Animals by Type</h5>
        <div class="stat-list" v-if="data.animals_by_type">
          <div class="stat-row" v-for="(count, type) in data.animals_by_type" :key="type">
            <span>{{ type }}</span>
            <span class="stat-count">{{ count }}</span>
          </div>
        </div>
        <p class="empty" v-else>No animals</p>
      </div>

      <!-- Animals per Farm -->
      <div class="section-card">
        <h5>Animals per Farm</h5>
        <div class="stat-list" v-if="data.animals_per_farm">
          <div class="stat-row" v-for="(count, farm) in data.animals_per_farm" :key="farm">
            <span>{{ farm }}</span>
            <span class="stat-count">{{ count }}</span>
          </div>
        </div>
        <p class="empty" v-else>No farms</p>
      </div>

      <!-- Animal Status -->
      <div class="section-card">
        <h5>Animals by Status</h5>
        <div class="stat-list" v-if="data.animals_by_status">
          <div class="stat-row" v-for="(count, status) in data.animals_by_status" :key="status">
            <span>{{ status }}</span>
            <span class="stat-count">{{ count }}</span>
          </div>
        </div>
        <p class="empty" v-else>No data</p>
      </div>

      <!-- Month P&L -->
      <div class="section-card">
        <h5>This Month's P&L</h5>
        <div class="pnl-grid" v-if="data.pnl">
          <div class="pnl-item">
            <span class="pnl-label">Income</span>
            <span class="green">R{{ fmt(data.pnl.income) }}</span>
          </div>
          <div class="pnl-item">
            <span class="pnl-label">Op. Expense</span>
            <span class="red">R{{ fmt(data.pnl.expense) }}</span>
          </div>
          <div class="pnl-item">
            <span class="pnl-label">Op. Profit</span>
            <span :class="data.pnl.profit >= 0 ? 'green' : 'red'">R{{ fmt(data.pnl.profit) }}</span>
          </div>
          <div class="pnl-item">
            <span class="pnl-label">Investment</span>
            <span class="purple">R{{ fmt(data.pnl.investment) }}</span>
          </div>
        </div>
        <p class="pnl-period" v-if="data.pnl">{{ data.pnl.period }}</p>
      </div>
    </div>

    <!-- Recent Events -->
    <div class="section-card mt-3">
      <div class="d-flex align-items-center justify-content-between mb-2">
        <h5 class="m-0">Recent Events</h5>
        <button class="button-info btn-sm" @click="$router.push({ name: 'AnimalEventList' })">View All</button>
      </div>
      <table>
        <thead>
          <tr>
            <th>Date</th>
            <th>Farm</th>
            <th>Animal</th>
            <th>Event</th>
            <th>Cost</th>
            <th>Type</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="e in data.recent_events" :key="e.id">
            <td>{{ formatDate(e.event_date) }}</td>
            <td>{{ e.farm?.name || '-' }}</td>
            <td>{{ e.animal?.animal_tag || '-' }}</td>
            <td>{{ e.event_type }}</td>
            <td>R{{ fmt(e.cost) }}</td>
            <td><span :class="'badge badge-' + e.cost_type">{{ e.cost_type }}</span></td>
          </tr>
          <tr v-if="!data.recent_events || data.recent_events.length === 0">
            <td colspan="6" class="text-center">No recent events</td>
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
  name: "FarmDashboard",

  data() {
    return {
      data: {
        total_farms: 0,
        total_animals: 0,
        low_stock_count: 0,
        animals_by_type: {},
        animals_per_farm: {},
        animals_by_status: {},
        pnl: null,
        recent_events: [],
      }
    }
  },

  mounted() {
    this.loadDashboard();
  },

  methods: {
    async loadDashboard() {
      try {
        const res = await api.get('/farm/dashboard');
        this.data = res.data;
      } catch (e) {
        toast.error('Failed to load dashboard');
      }
    },

    fmt(val) {
      return parseFloat(val || 0).toFixed(2);
    },

    formatDate(d) {
      return d ? new Date(d).toLocaleDateString('en-ZA') : '-';
    }
  }
}
</script>

<style scoped>
.farm-dashboard {
  padding: 10px;
  color: #e0e0e0;
}

.card {
  background: rgba(255,255,255,0.06);
  color: #fff;
  border-radius: 10px;
  backdrop-filter: blur(4px);
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
.profit-card .icon { color: #66bb6a; }
.profit-card .value { color: #66bb6a; }
.loss-card .icon { color: #ef5350; }
.loss-card .value { color: #ef5350; }

/* Grid */
.grid-2 {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
}

@media (max-width: 768px) {
  .grid-2 { grid-template-columns: 1fr; }
}

.section-card {
  background: rgba(255,255,255,0.06);
  border-radius: 10px;
  padding: 20px;
  border: 1px solid rgba(255,255,255,0.08);
}

.section-card h5 {
  margin-bottom: 12px;
  color: #fff;
  font-size: 15px;
}

/* Stat list */
.stat-list { display: flex; flex-direction: column; gap: 6px; }

.stat-row {
  display: flex;
  justify-content: space-between;
  padding: 8px 12px;
  background: rgba(255,255,255,0.05);
  border-radius: 6px;
  font-size: 14px;
  color: #ccc;
}

.stat-count {
  font-weight: bold;
  color: #fff;
}

/* P&L grid */
.pnl-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
}

.pnl-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  background: rgba(255,255,255,0.05);
  border-radius: 8px;
  padding: 12px;
}

.pnl-label { font-size: 11px; color: rgba(255,255,255,0.5); text-transform: uppercase; }
.pnl-item span:last-child { font-size: 16px; font-weight: bold; }
.pnl-period { font-size: 11px; color: rgba(255,255,255,0.3); margin-top: 8px; text-align: right; }

.badge { padding: 3px 8px; border-radius: 12px; font-size: 11px; color: #fff; }
.badge-income { background: #2e7d32; }
.badge-expense { background: #c62828; }
.badge-running { background: #e65100; }
.badge-loss { background: #b71c1c; }
.badge-birth { background: #1565c0; }

.btn-sm { padding: 4px 10px; font-size: 12px; }
.text-center { text-align: center; }
.empty { color: rgba(255,255,255,0.4); font-style: italic; font-size: 13px; }
.mt-3 { margin-top: 16px; }
</style>
