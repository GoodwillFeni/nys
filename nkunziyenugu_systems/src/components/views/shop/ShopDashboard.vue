<template>
  <div class="shop-dashboard">

    <!-- Header -->
    <div class="card p-3 mb-3">
      <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
        <h4 class="m-0">Shop Dashboard</h4>
        <div class="d-flex align-items-center gap-2 flex-wrap">
          <button class="button-info" @click="$router.push({ name: 'ShopProducts' })">Products</button>
          <button class="button-info" @click="$router.push({ name: 'ShopPOS' })">POS</button>
          <button class="button-info" @click="$router.push({ name: 'ShopCashFlow' })">Cash Flow</button>
          <button class="button-info" @click="$router.push({ name: 'ShopSalesSummary' })">Sales Report</button>
        </div>
      </div>
    </div>

    <!-- Summary Cards -->
    <div class="summary-row">
      <div class="summary-card">
        <i class="bi bi-cart3 icon green"></i>
        <div>
          <span class="value">{{ d.month_sales?.count || 0 }}</span>
          <span class="label">Sales This Month</span>
        </div>
      </div>

      <div class="summary-card">
        <i class="bi bi-cash-stack icon blue"></i>
        <div>
          <span class="value">R{{ fmt(d.month_sales?.revenue) }}</span>
          <span class="label">Revenue</span>
        </div>
      </div>

      <div class="summary-card" :class="{ 'profit-card': (d.month_sales?.profit || 0) >= 0, 'loss-card': (d.month_sales?.profit || 0) < 0 }">
        <i class="bi bi-graph-up-arrow icon"></i>
        <div>
          <span class="value">R{{ fmt(d.month_sales?.profit) }}</span>
          <span class="label">Profit</span>
        </div>
      </div>

      <div class="summary-card" :class="{ 'warning-card': d.month_sales?.unpaid > 0 }">
        <i class="bi bi-clock-history icon orange"></i>
        <div>
          <span class="value">{{ d.month_sales?.unpaid || 0 }}</span>
          <span class="label">Unpaid Sales</span>
        </div>
      </div>
    </div>

    <!-- Second row cards -->
    <div class="summary-row">
      <div class="summary-card">
        <i class="bi bi-box-seam icon blue"></i>
        <div>
          <span class="value">{{ d.total_products }}</span>
          <span class="label">Products</span>
        </div>
      </div>

      <div class="summary-card" :class="{ 'warning-card': d.low_stock_products > 0 }">
        <i class="bi bi-exclamation-triangle icon orange"></i>
        <div>
          <span class="value">{{ d.low_stock_products }}</span>
          <span class="label">Low Stock</span>
        </div>
      </div>

      <div class="summary-card">
        <i class="bi bi-people icon green"></i>
        <div>
          <span class="value">{{ d.total_customers }}</span>
          <span class="label">Customers</span>
        </div>
      </div>

      <div class="summary-card" :class="{ 'warning-card': d.pending_credits > 0 }">
        <i class="bi bi-credit-card icon orange"></i>
        <div>
          <span class="value">{{ d.pending_credits }}</span>
          <span class="label">Pending Credits</span>
        </div>
      </div>
    </div>

    <!-- Charts row -->
    <div class="grid-3">
      <!-- Monthly Trend -->
      <div class="section-card">
        <h5>Monthly Revenue</h5>
        <canvas ref="trendChart" style="height: 200px; width: 100%"></canvas>
      </div>

      <!-- Payment Methods -->
      <div class="section-card">
        <h5>Payment Methods</h5>
        <canvas ref="paymentChart" style="height: 200px; width: 100%"></canvas>
      </div>

      <!-- Cashflow -->
      <div class="section-card">
        <h5>Cashflow This Month</h5>
        <div class="cashflow-grid">
          <div class="cf-item">
            <span class="cf-label">Cash In</span>
            <span class="green">R{{ fmt(d.cashflow?.cash_in) }}</span>
          </div>
          <div class="cf-item">
            <span class="cf-label">Cash Out</span>
            <span class="red">R{{ fmt(d.cashflow?.cash_out) }}</span>
          </div>
          <div class="cf-item cf-net">
            <span class="cf-label">Net</span>
            <span :class="(d.cashflow?.net || 0) >= 0 ? 'green' : 'red'">R{{ fmt(d.cashflow?.net) }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Bottom row -->
    <div class="grid-2 mt-3">
      <!-- Top Products -->
      <div class="section-card">
        <h5>Top Selling Products</h5>
        <div class="stat-list" v-if="d.top_products?.length">
          <div class="stat-row" v-for="(p, i) in d.top_products" :key="i">
            <span>{{ p.product_name }}</span>
            <span><span class="stat-count">{{ p.total_qty }}</span> sold &middot; R{{ fmt(p.total_revenue) }}</span>
          </div>
        </div>
        <p class="empty" v-else>No sales data</p>
      </div>

      <!-- Recent Sales -->
      <div class="section-card">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <h5 class="m-0">Recent Sales</h5>
          <button class="button-info btn-sm" @click="$router.push({ name: 'ShopSalesSummary' })">View All</button>
        </div>
        <table>
          <thead>
            <tr>
              <th>Date</th>
              <th>Customer</th>
              <th>Amount</th>
              <th>Profit</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="s in d.recent_sales" :key="s.id">
              <td>{{ formatDate(s.sale_datetime) }}</td>
              <td>{{ s.customer_name || '-' }}</td>
              <td>R{{ fmt(s.total_amount) }}</td>
              <td>R{{ fmt(s.total_profit) }}</td>
              <td>
                <span :class="['badge', s.is_paid ? 'badge-paid' : 'badge-unpaid']">
                  {{ s.is_paid ? 'Paid' : 'Unpaid' }}
                </span>
              </td>
            </tr>
            <tr v-if="!d.recent_sales?.length">
              <td colspan="5" class="text-center">No recent sales</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

  </div>
</template>

<script>
import api from '@/store/services/api';
import { useToast } from "vue-toastification";
import {
  Chart, LineController, BarController, CategoryScale, LinearScale,
  PointElement, LineElement, BarElement, Tooltip, Legend
} from 'chart.js';

Chart.register(
  LineController, BarController, CategoryScale, LinearScale,
  PointElement, LineElement, BarElement, Tooltip, Legend
);

Chart.defaults.animation = false;

const toast = useToast();

const COLORS = ['#66bb6a', '#42a5f5', '#ffa726', '#ef5350', '#ab47bc', '#26c6da', '#8d6e63', '#78909c'];

export default {
  name: "ShopDashboard",

  data() {
    return {
      d: {
        total_products: 0,
        low_stock_products: 0,
        total_customers: 0,
        pending_credits: 0,
        month_sales: {},
        cashflow: {},
        sales_by_payment: [],
        monthly_trend: [],
        top_products: [],
        recent_sales: [],
      },
      charts: []
    }
  },

  mounted() {
    this.loadDashboard();
  },

  beforeUnmount() {
    this.charts.forEach(c => c.destroy());
  },

  methods: {
    async loadDashboard() {
      try {
        const res = await api.get('/shop/dashboard');
        this.d = res.data;
        this.$nextTick(() => this.drawCharts());
      } catch (e) {
        toast.error('Failed to load shop dashboard');
      }
    },

    drawCharts() {
      // Destroy any existing charts first
      this.charts.forEach(c => c.destroy());
      this.charts = [];

      // Monthly trend
      if (this.$refs.trendChart?.getContext && this.d.monthly_trend?.length) {
        this.charts.push(new Chart(this.$refs.trendChart, {
          type: 'bar',
          data: {
            labels: this.d.monthly_trend.map(m => m.month),
            datasets: [
              {
                label: 'Revenue',
                data: this.d.monthly_trend.map(m => m.revenue),
                backgroundColor: 'rgba(66,165,245,0.6)',
                borderRadius: 4,
              },
              {
                label: 'Profit',
                data: this.d.monthly_trend.map(m => m.profit),
                backgroundColor: 'rgba(102,187,106,0.6)',
                borderRadius: 4,
              }
            ]
          },
          options: {
            responsive: true,
            plugins: { legend: { labels: { color: '#ccc' } } },
            scales: {
              x: { ticks: { color: '#999' }, grid: { color: 'rgba(255,255,255,0.05)' } },
              y: { ticks: { color: '#999' }, grid: { color: 'rgba(255,255,255,0.05)' } }
            }
          }
        }));
      }

      // Payment methods
      if (this.$refs.paymentChart?.getContext && this.d.sales_by_payment?.length) {
        this.charts.push(new Chart(this.$refs.paymentChart, {
          type: 'bar',
          data: {
            labels: this.d.sales_by_payment.map(p => p.payment_method || 'Unknown'),
            datasets: [{
              label: 'Sales',
              data: this.d.sales_by_payment.map(p => p.total),
              backgroundColor: COLORS.slice(0, this.d.sales_by_payment.length),
              borderRadius: 4,
            }]
          },
          options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: {
              x: { ticks: { color: '#999' }, grid: { color: 'rgba(255,255,255,0.05)' } },
              y: { ticks: { color: '#999' }, grid: { color: 'rgba(255,255,255,0.05)' } }
            }
          }
        }));
      }
    },

    fmt(val) { return parseFloat(val || 0).toFixed(2); },
    formatDate(d) { return d ? new Date(d).toLocaleDateString('en-ZA') : '-'; },
  }
}
</script>

<style scoped>
.shop-dashboard { padding: 10px; color: #e0e0e0; }

.card {
  background: rgba(255,255,255,0.06);
  color: #fff;
  border-radius: 10px;
  backdrop-filter: blur(4px);
}

/* Summary */
.summary-row {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 14px;
  margin-bottom: 14px;
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

.warning-card { border-left: 3px solid #ffa726; }
.profit-card .icon { color: #66bb6a; }
.profit-card .value { color: #66bb6a; }
.loss-card .icon { color: #ef5350; }
.loss-card .value { color: #ef5350; }

/* Grids */
.grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px; }
.grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }

@media (max-width: 900px) { .grid-3 { grid-template-columns: 1fr; } }
@media (max-width: 768px) { .grid-2 { grid-template-columns: 1fr; } }

.section-card {
  background: rgba(255,255,255,0.06);
  border-radius: 10px;
  padding: 20px;
  border: 1px solid rgba(255,255,255,0.08);
}

.section-card h5 { margin-bottom: 12px; color: #fff; font-size: 15px; }

/* Cashflow */
.cashflow-grid { display: flex; flex-direction: column; gap: 12px; margin-top: 10px; }
.cf-item {
  display: flex; justify-content: space-between; align-items: center;
  padding: 12px 16px; background: rgba(255,255,255,0.05); border-radius: 8px;
}
.cf-item span:last-child { font-size: 18px; font-weight: bold; }
.cf-label { font-size: 13px; color: rgba(255,255,255,0.5); }
.cf-net { border-top: 1px solid rgba(255,255,255,0.1); padding-top: 14px; }

/* Stat list */
.stat-list { display: flex; flex-direction: column; gap: 6px; }
.stat-row {
  display: flex; justify-content: space-between; padding: 8px 12px;
  background: rgba(255,255,255,0.05); border-radius: 6px; font-size: 13px; color: #ccc;
}
.stat-count { font-weight: bold; color: #fff; }

/* Table */
table { color: #e0e0e0; }
thead { background: rgba(255,255,255,0.08); }
th { color: rgba(255,255,255,0.7); }

.badge { padding: 3px 8px; border-radius: 12px; font-size: 11px; color: #fff; }
.badge-paid { background: #2e7d32; }
.badge-unpaid { background: #e65100; }

.btn-sm { padding: 4px 10px; font-size: 12px; }
.text-center { text-align: center; }
.empty { color: rgba(255,255,255,0.4); font-style: italic; font-size: 13px; }
.mt-3 { margin-top: 16px; }
</style>
