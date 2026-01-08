<template>
  <div class="dashboard">
    <!-- TOP LINE CHART -->
    <div class="card">
      <h3>Orders & Revenue</h3>
      <canvas ref="lineChart" style="height: 200px; width: 100%"></canvas>
    </div>

    <!-- BOTTOM CHARTS -->
    <div class="charts-row">
      <div class="card">
        <h4>Monthly Sales</h4>
        <canvas ref="barChart1" style="height: 200px; width: 100%"></canvas>
      </div>

      <div class="card">
        <h4>Payment Methods</h4>
        <canvas ref="barChart2" style="height: 200px; width: 100%"></canvas>
      </div>

      <div class="card">
        <h4>Order Status</h4>
        <canvas ref="pieChart"></canvas>
      </div>
    </div>

    <!-- RAW DATA TABLE -->
    <div class="card">
      <h3>Recent Orders</h3>
      <table>
        <thead>
          <tr>
            <th>#</th>
            <th>Customer</th>
            <th>Amount</th>
            <th>Status</th>
            <th>Date</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="order in orders" :key="order.id">
            <td>{{ order.id }}</td>
            <td>{{ order.customer }}</td>
            <td>R {{ order.amount }}</td>
            <td>
              <span :class="['status', order.status.toLowerCase()]">
                {{ order.status }}
              </span>
            </td>
            <td>{{ order.date }}</td>
          </tr>
        </tbody>
      </table>
    </div>

  </div>
</template>

<script>
import {
  Chart,
  LineController,
  BarController,
  PieController,
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  BarElement,
  ArcElement,
  Tooltip,
  Legend
} from 'chart.js'

Chart.register(
  LineController,
  BarController,
  PieController,
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  BarElement,
  ArcElement,
  Tooltip,
  Legend
)

/* DYNAMIC COLOR PALETTE */
const COLORS = [
  '#FFC107', // yellow
  '#4CAF50', // light green
  '#2196F3', // blue
  '#03A9F4', // light blue
  '#9C27B0', // purple
  '#FF5722', // orange
  '#00BCD4', // cyan
  '#8BC34A'  // lime
]

const pickColors = count => COLORS.slice(0, count)

export default {
  name: 'MainDashboard',

  data() {
    return {
      orders: [
        { id: 1, customer: 'John', amount: 1200, status: 'Paid', date: '2025-01-10' },
        { id: 2, customer: 'Sarah', amount: 900, status: 'Pending', date: '2025-01-11' },
        { id: 3, customer: 'Mike', amount: 1500, status: 'Paid', date: '2025-01-12' },
        { id: 4, customer: 'Anna', amount: 700, status: 'Cancelled', date: '2025-01-13' }
      ]
    }
  },

  mounted() {
    this.drawCharts()
  },

  methods: {
    drawCharts() {

      /* LINE CHART */
      new Chart(this.$refs.lineChart, {
        type: 'line',
        data: {
          labels: ['Mon','Tue','Wed','Thu','Fri'],
          datasets: [
            {
              label: 'Orders',
              data: [5, 8, 6, 9, 12],
              borderColor: COLORS[2],
              backgroundColor: 'rgba(33,150,243,0.2)',
              tension: 0.4
            },
            {
              label: 'Revenue',
              data: [1000, 1600, 1200, 2000, 2600],
              borderColor: COLORS[1],
              backgroundColor: 'rgba(76,175,80,0.2)',
              tension: 0.4
            }
          ]
        },
        options: {
          plugins: {
            legend: {
              position: 'right'
            }
          }
        }
      })

      /* BAR CHART 1 */
      new Chart(this.$refs.barChart1, {
        type: 'bar',
        data: {
          labels: ['Jan','Feb','Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
          datasets: [{
            label: 'Sales',
            data: [3000, 4200, 3800, 4500, 5000, 4800, 5200, 5500, 4900, 5100, 4700, 5300],
            backgroundColor: pickColors(12)
          }]
        },
        options: {
          plugins: {
            legend: {
              position: 'bottom'
            }
          }
        }
      })

      /* BAR CHART 2 */
      new Chart(this.$refs.barChart2, {
        type: 'bar',
        data: {
          labels: ['Cash','Card','Online', 'Mobile', 'Other'],
          datasets: [{
            label: 'Payments',
            data: [25, 40, 35, 15, 10],
            backgroundColor: pickColors(5),
            borderWidth: 1,
          }]
        },
        options: {
          plugins: {
            legend: {
              position: 'bottom'
            }
          }
        }
      })

      /* PIE CHART */
      new Chart(this.$refs.pieChart, {
        type: 'pie',
        data: {
          labels: ['Paid','Pending','Cancelled', 'Refunded'],
          datasets: [{
            data: [60, 25, 15, 10],
            backgroundColor: pickColors(4)
          }]
        },
        options: {
          plugins: {
            legend: {
              position: 'right'
            }
          }
        }
      })
    }
  }
}
</script>

<style scoped>
.dashboard {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

/* CARD */
.card {
  background: linear-gradient(135deg, #27253f, #605a6d);
  color: #fff;
  padding: 15px;
  border-radius: 8px;
  height: 300px;
}

/* CHART GRID */
.charts-row {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 20px;
}

/* TABLE */
table {
  width: 100%;
  border-collapse: collapse;
}

th, td {
  padding: 10px;
  border-bottom: 1px solid #eee;
  text-align: left;
}


/* STATUS COLORS */
.status {
  padding: 4px 10px;
  border-radius: 12px;
  font-size: 12px;
  color: #fff;
}

.status.paid {
  background: #4CAF50;
}

.status.pending {
  background: #FFC107;
  color: #000;
}

.status.cancelled {
  background: #F44336;
}

/* RESPONSIVE */
@media (max-width: 900px) {
  .charts-row {
    grid-template-columns: 1fr;
  }
}
</style>
