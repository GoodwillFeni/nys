<template>
  <div>
    <!-- Header -->
    <div class="card p-3 mb-3">
      <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
        <h4 class="m-0">Farm P&L Report</h4>
        <div class="d-flex align-items-center gap-2 flex-wrap">
          <select v-model="filters.farm_id" class="form-control-sm" style="width: 180px" @change="loadReport">
            <option value="">All Farms</option>
            <option v-for="f in farms" :key="f.id" :value="f.id">{{ f.name }}</option>
          </select>
          <input type="date" v-model="filters.from" class="form-control-sm" style="width: 160px" @change="loadReport" />
          <input type="date" v-model="filters.to" class="form-control-sm" style="width: 160px" @change="loadReport" />
          <button type="button" class="button-info button-sm" @click="resetToLifetime">Lifetime</button>
          <button type="button" class="button-warning" @click="$router.back()">Back</button>
        </div>
      </div>
      <p class="period-hint" v-if="report">
        Showing
        <strong v-if="report.period.lifetime">lifetime totals</strong>
        <strong v-else>{{ report.period.from || 'start' }} to {{ report.period.to || 'today' }}</strong>
        — clear the dates to see lifetime.
      </p>
    </div>

    <!-- Operating P&L -->
    <div class="summary-row" v-if="report">
      <div class="summary-card income-card">
        <span class="label">Operating Income</span>
        <span class="value">R{{ fmt(report.operating.income) }}</span>
      </div>
      <div class="summary-card expense-card">
        <span class="label">Operating Expense</span>
        <span class="value">R{{ fmt(report.operating.expense) }}</span>
      </div>
      <div class="summary-card loss-card">
        <span class="label">Loss</span>
        <span class="value">R{{ fmt(report.operating.loss) }}</span>
      </div>
      <div class="summary-card" :class="report.operating.profit >= 0 ? 'profit-card' : 'loss-card'">
        <span class="label">Operating Profit</span>
        <span class="value">R{{ fmt(report.operating.profit) }}</span>
      </div>
    </div>

    <!-- Natural increase + Capital -->
    <div class="summary-row" v-if="report">
      <div class="summary-card birth-card">
        <span class="label">Natural Increase (Birth Value)</span>
        <span class="value">R{{ fmt(report.natural_increase ? report.natural_increase.birth_value : report.capital.birth_value) }}</span>
      </div>
      <div class="summary-card investment-card">
        <span class="label">Capital Investment</span>
        <span class="value">R{{ fmt(report.capital.investment) }}</span>
      </div>
      <div class="summary-card equity-card">
        <span class="label">Total Equity Change</span>
        <span class="value">R{{ fmt(report.total_equity_change) }}</span>
      </div>
    </div>

    <!-- Two-column breakdown — always shown -->
    <div class="breakdown-grid" v-if="report">

      <!-- Animal Events -->
      <div class="breakdown-section">
        <h5>Animal Events</h5>
        <div class="mini-cards">
          <div class="mini-card"><span>Income</span><span class="green">R{{ fmt(report.animal_events.income) }}</span></div>
          <div class="mini-card"><span>Birth</span><span class="blue">R{{ fmt(report.animal_events.birth) }}</span></div>
          <div class="mini-card"><span>Expense</span><span class="red">R{{ fmt(report.animal_events.expense) }}</span></div>
          <div class="mini-card"><span>Running</span><span class="orange">R{{ fmt(report.animal_events.running) }}</span></div>
          <div class="mini-card"><span>Loss</span><span class="red">R{{ fmt(report.animal_events.loss) }}</span></div>
          <div class="mini-card"><span>Investment</span><span class="purple">R{{ fmt(report.animal_events.investment) }}</span></div>
        </div>

        <table v-if="report.animal_events.breakdown && report.animal_events.breakdown.length > 0">
          <thead>
            <tr><th>Event Type</th><th>Cost Type</th><th>Count</th><th>Total</th></tr>
          </thead>
          <tbody>
            <tr v-for="(row, i) in report.animal_events.breakdown" :key="'ae' + i">
              <td>{{ row.event_type }}</td>
              <td><span :class="'badge badge-' + row.cost_type">{{ row.cost_type }}</span></td>
              <td>{{ row.count }}</td>
              <td>R{{ fmt(row.total) }}</td>
            </tr>
          </tbody>
        </table>
        <p v-else class="empty">No animal events in this period</p>
      </div>

      <!-- Inventory -->
      <div class="breakdown-section">
        <h5>Inventory Transactions</h5>
        <div class="mini-cards">
          <div class="mini-card"><span>Income</span><span class="green">R{{ fmt(report.inventory.income) }}</span></div>
          <div class="mini-card"><span>Expense</span><span class="red">R{{ fmt(report.inventory.expense) }}</span></div>
          <div class="mini-card"><span>Loss</span><span class="red">R{{ fmt(report.inventory.loss) }}</span></div>
        </div>

        <table v-if="report.inventory.breakdown && report.inventory.breakdown.length > 0">
          <thead>
            <tr><th>Category</th><th>Type</th><th>Count</th><th>Total</th></tr>
          </thead>
          <tbody>
            <tr v-for="(row, i) in report.inventory.breakdown" :key="'inv' + i">
              <td>{{ row.category }}</td>
              <td><span :class="'badge badge-' + row.type">{{ row.type }}</span></td>
              <td>{{ row.count }}</td>
              <td>R{{ fmt(row.total) }}</td>
            </tr>
          </tbody>
        </table>
        <p v-else class="empty">No inventory transactions in this period</p>
      </div>

    </div>
  </div>
</template>

<script>
import api from '@/store/services/api';
import { useToast } from "vue-toastification";
const toast = useToast();

export default {
  name: "PnlReport",

  data() {
    return {
      farms: [],
      report: null,
      filters: {
        farm_id: '',
        from: '',
        to: '',
      }
    }
  },

  mounted() {
    // Default: show LIFETIME totals (no date filter). User clicks dates to drill in.
    this.loadFarms();
    this.loadReport();
  },

  methods: {
    async loadFarms() {
      try {
        const res = await api.get('/farm/farms');
        this.farms = res.data || [];
      } catch (e) { toast.error('Failed to load farms'); }
    },

    async loadReport() {
      try {
        const params = { detail: 1 };
        if (this.filters.farm_id) params.farm_id = this.filters.farm_id;
        if (this.filters.from) params.from = this.filters.from;
        if (this.filters.to) params.to = this.filters.to;

        const res = await api.get('/farm/reports/pnl', { params });
        this.report = res.data;
      } catch (e) {
        toast.error('Failed to load report');
      }
    },

    resetToLifetime() {
      this.filters.from = '';
      this.filters.to = '';
      this.loadReport();
    },

    fmt(val) {
      return parseFloat(val || 0).toFixed(2);
    }
  }
}
</script>

<style scoped>
.card {
  background: linear-gradient(135deg, #27253f, #605a6d);
  color: #fff;
  border-radius: 8px;
}

/* Header filter inputs — match the dark gradient card */
.card .form-control-sm,
.card input[type="date"] {
  background: rgba(255, 255, 255, 0.08);
  color: #fff;
  border: 1px solid rgba(255, 255, 255, 0.25);
  border-radius: 6px;
  padding: 6px 10px;
  font-size: 13px;
  height: 34px;
}
.card .form-control-sm:focus,
.card input[type="date"]:focus {
  background: rgba(255, 255, 255, 0.14);
  border-color: #6a5cff;
  outline: none;
  box-shadow: none;
}
.card input[type="date"]::-webkit-calendar-picker-indicator {
  filter: invert(1);
  opacity: 0.7;
  cursor: pointer;
}
.card input[type="date"]::-webkit-datetime-edit-text,
.card input[type="date"]::-webkit-datetime-edit-month-field,
.card input[type="date"]::-webkit-datetime-edit-day-field,
.card input[type="date"]::-webkit-datetime-edit-year-field {
  color: #fff;
}
.card select.form-control-sm option {
  background: #27253f;
  color: #fff;
}


.summary-row {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 12px;
  margin-bottom: 20px;
}

.summary-card {
  background: #fff;
  border-radius: 10px;
  padding: 20px;
  box-shadow: 0 3px 8px rgba(0,0,0,0.1);
  display: flex;
  flex-direction: column;
  align-items: center;
  border-top: 4px solid #ddd;
}

.summary-card .label { font-size: 12px; color: #888; text-transform: uppercase; }
.summary-card .value { font-size: 22px; font-weight: bold; margin-top: 4px; }

.income-card { border-top-color: #2e7d32; }
.income-card .value { color: #2e7d32; }
.expense-card { border-top-color: #c62828; }
.expense-card .value { color: #c62828; }
.loss-card { border-top-color: #b71c1c; }
.loss-card .value { color: #b71c1c; }
.profit-card { border-top-color: #1565c0; }
.profit-card .value { color: #1565c0; }
.investment-card { border-top-color: #6a1b9a; }
.investment-card .value { color: #6a1b9a; }
.birth-card { border-top-color: #1565c0; }
.birth-card .value { color: #1565c0; }
.equity-card { border-top-color: #2e7d32; }
.equity-card .value { color: #2e7d32; }

.period-hint { font-size: 12px; color: rgba(255,255,255,0.7); margin: 8px 0 0; }

.breakdown-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 20px;
  margin-bottom: 20px;
}

@media (max-width: 768px) {
  .breakdown-grid { grid-template-columns: 1fr; }
}

.breakdown-section {
  background: #fff;
  border-radius: 10px;
  padding: 20px;
  box-shadow: 0 3px 8px rgba(0,0,0,0.1);
}

.breakdown-section h5 {
  margin-bottom: 12px;
  color: #333;
  font-size: 16px;
}

.mini-cards {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-bottom: 14px;
}

.mini-card {
  background: #f5f5f5;
  border-radius: 8px;
  padding: 8px 12px;
  display: flex;
  flex-direction: column;
  align-items: center;
  font-size: 12px;
  min-width: 80px;
}

.mini-card span:first-child { color: #888; }
.green { color: #2e7d32; font-weight: bold; }
.blue { color: #1565c0; font-weight: bold; }
.red { color: #c62828; font-weight: bold; }
.orange { color: #e65100; font-weight: bold; }
.purple { color: #6a1b9a; font-weight: bold; }

.badge { padding: 3px 8px; border-radius: 12px; font-size: 11px; color: #fff; }
.badge-income { background: #2e7d32; }
.badge-expense { background: #c62828; }
.badge-running { background: #e65100; }
.badge-loss { background: #b71c1c; }
.badge-birth { background: #1565c0; }
.badge-investment { background: #6a1b9a; }

.empty { color: #999; font-style: italic; font-size: 14px; }
.period-info { color: #999; font-size: 12px; text-align: right; margin-top: 10px; }
</style>
