<template>
  <div class="shop-page">
    <div class="card p-3 mb-3">
      <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
        <h4 class="m-0">My Orders</h4>
        <RouterLink class="button-info" to="/Shop/Products">Shop</RouterLink>
      </div>
      <div v-if="error" class="alert alert-danger mt-3 mb-0">{{ error }}</div>
    </div>

    <div v-if="loading" class="card p-3">Loading...</div>

    <div v-else class="card p-3">
      <table class="table table-borderless mb-0">
        <thead>
          <tr>
            <th>#</th>
            <th>Status</th>
            <th>Total</th>
            <th>Created</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="o in orders" :key="o.id">
            <td>{{ o.id }}</td>
            <td>{{ o.status }}</td>
            <td>R {{ formatMoney(o.total_amount) }}</td>
            <td>{{ o.created_at }}</td>
          </tr>
        </tbody>
      </table>

      <div v-if="orders.length === 0">No orders yet.</div>
    </div>
  </div>
</template>

<script>
import api from '@/store/services/api'

export default {
  name: 'ShopMyOrders',
  data() {
    return {
      loading: false,
      error: null,
      orders: [],
    }
  },
  mounted() {
    this.fetchOrders()
  },
  methods: {
    formatMoney(v) {
      const n = Number(v)
      if (Number.isNaN(n)) return '0.00'
      return n.toFixed(2)
    },
    async fetchOrders() {
      this.loading = true
      this.error = null
      try {
        const res = await api.get('/shop/orders/my')
        this.orders = res.data?.data || []
      } catch (e) {
        this.error = e?.response?.data?.message || e.message || 'Failed to load orders'
      } finally {
        this.loading = false
      }
    }
  }
}
</script>

<style scoped>
.shop-page {
  padding: 10px;
}

.card {
  background: linear-gradient(135deg, #27253f, #605a6d);
  color: #fff;
  border-radius: 8px;
}

.table {
  color: #fff;
}

.table th,
.table td {
  color: #fff;
}
</style>
