<template>
  <div class="farm-dashboard">

    <!-- Page Header -->
    <div class="page-header">
      <h2>Farm Dashboard</h2>
    </div>

    <!-- Summary Cards -->
    <div class="summary-cards">
      <div class="card">
        <i class="bi bi-house-door"></i>
        <div>
          <h3>{{ totalFarms }}</h3>
          <p>Total Farms</p>
        </div>
      </div>

      <div class="card">
        <i class="bi bi-github"></i>
        <div>
          <h3>{{ totalAnimals }}</h3>
          <p>Total Animals</p>
        </div>
      </div>

      <div class="card warning">
        <i class="bi bi-exclamation-triangle"></i>
        <div>
          <h3>{{ lowStockItems }}</h3>
          <p>Low Stock Items</p>
        </div>
      </div>

      <div class="card info">
        <i class="bi bi-clipboard-data"></i>
        <div>
          <h3>{{ reportsThisMonth }}</h3>
          <p>Reports This Month</p>
        </div>
      </div>
    </div>

    <!-- Charts Section -->
    <div class="charts-section">
      <div class="chart-card">
        <h4>Animals by Type</h4>
        <ul>
          <li v-for="(count, type) in animalsByType" :key="type">
            {{ type }} — {{ count }}
          </li>
        </ul>
      </div>

      <div class="chart-card">
        <h4>Animals Per Farm</h4>
        <ul>
          <li v-for="farm in animalsPerFarm" :key="farm.name">
            {{ farm.name }} — {{ farm.count }}
          </li>
        </ul>
      </div>
    </div>

    <!-- Recent Activity -->
    <div class="recent-section">
      <h4>Recent Activity</h4>
      <table>
        <thead>
          <tr>
            <th>Date</th>
            <th>Activity</th>
            <th>Farm</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="activity in recentActivities" :key="activity.id">
            <td>{{ activity.date }}</td>
            <td>{{ activity.description }}</td>
            <td>{{ activity.farm }}</td>
          </tr>
        </tbody>
      </table>
    </div>

  </div>
</template>

<script>
export default {
  name: "FarmDashboard",

  data() {
    return {
      // Summary stats (replace with API data later)
      totalFarms: 5,
      totalAnimals: 320,
      lowStockItems: 4,
      reportsThisMonth: 12,

      animalsByType: {
        Cattle: 120,
        Goats: 80,
        Chickens: 100,
        Sheep: 20
      },

      animalsPerFarm: [
        { name: "Green Valley", count: 120 },
        { name: "Sunrise Farm", count: 95 },
        { name: "Riverdale", count: 105 }
      ],

      recentActivities: [
        { id: 1, date: "2026-02-01", description: "Added 5 cattle", farm: "Green Valley" },
        { id: 2, date: "2026-02-03", description: "Inventory updated", farm: "Sunrise Farm" },
        { id: 3, date: "2026-02-05", description: "Monthly report submitted", farm: "Riverdale" }
      ]
    }
  }
}
</script>

<style scoped>
.farm-dashboard {
  padding: 20px;
  color: #333;
}

.page-header {
  margin-bottom: 20px;
}

/* Summary Cards */
.summary-cards {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 15px;
  margin-bottom: 30px;
}

.card {
  display: flex;
  align-items: center;
  gap: 15px;
  background: #ffffff;
  padding: 20px;
  border-radius: 8px;
  box-shadow: 0 3px 8px rgba(0,0,0,0.1);
}

.card i {
  font-size: 28px;
  color: #4caf50;
}

.card.warning i {
  color: #ff9800;
}

.card.info i {
  color: #2196f3;
}

/* Charts Section */
.charts-section {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 20px;
  margin-bottom: 30px;
}

.chart-card {
  background: #ffffff;
  padding: 20px;
  border-radius: 8px;
  box-shadow: 0 3px 8px rgba(0,0,0,0.1);
}

/* Recent Activity */
.recent-section {
  background: #ffffff;
  padding: 20px;
  border-radius: 8px;
  box-shadow: 0 3px 8px rgba(0,0,0,0.1);
}

table {
  width: 100%;
  border-collapse: collapse;
}

th, td {
  padding: 8px;
  text-align: left;
}

thead {
  background: #f5f5f5;
}

tbody tr:hover {
  background: #f9f9f9;
}
</style>