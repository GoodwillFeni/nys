<template>
  <div class="shop-page">
    <div class="card p-3 mb-3">
      <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
        <h4 class="m-0">My Credit</h4>
        <button class="button-info" @click="fetch">Load</button>
      </div>

      <div v-if="error" class="alert alert-danger mt-3 mb-0">{{ error }}</div>
    </div>

    <div v-if="loading" class="card p-3">Loading...</div>

    <div v-else class="card p-3 mb-3">
      <div class="row g-2">
        <div class="col-12 col-md-4">
          <div class="fw-bold">Total Credit</div>
          <div>R {{ formatMoney(summary.total_credit) }}</div>
        </div>
        <div class="col-12 col-md-4">
          <div class="fw-bold">Total Paid</div>
          <div>R {{ formatMoney(summary.total_paid) }}</div>
        </div>
        <div class="col-12 col-md-4">
          <div class="fw-bold">Outstanding</div>
          <div>R {{ formatMoney(summary.total_outstanding) }}</div>
        </div>
      </div>
    </div>

    <div v-if="!loading" class="card p-3">
      <table class="table table-borderless mb-0">
        <thead>
          <tr>
            <th>#</th>
            <th>Date</th>
            <th>Total</th>
            <th>Paid</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(s, idx) in sales" :key="s.id">
            <td>{{ idx + 1 }}</td>
            <td>{{ formatDateTime(s.sale_datetime) }}</td>
            <td>R {{ formatMoney(s.total_amount) }}</td>
            <td>{{ s.is_paid ? 'Yes' : 'No' }}</td>
          </tr>
          <tr v-if="sales.length === 0">
            <td colspan="4">No credit sales.</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script>
import api from '@/store/services/api'

export default {
  name: 'CustomerCredit',
  data() {
    return {
      loading: false,
      error: null,
      sales: [],
      summary: {
        total_credit: 0,
        total_paid: 0,
        total_outstanding: 0,
      },
    }
  },
  mounted() {
    this.fetch()
  },
  methods: {
    formatMoney(v) {
      const n = Number(v)
      if (Number.isNaN(n)) return '0.00'
      return n.toFixed(2)
    },
    formatDateTime(v) {
      if (!v) return '-'
      const d = new Date(v)
      if (Number.isNaN(d.getTime())) return String(v)
      const yyyy = d.getFullYear()
      const mm = String(d.getMonth() + 1).padStart(2, '0')
      const dd = String(d.getDate()).padStart(2, '0')
      const hh = String(d.getHours()).padStart(2, '0')
      const mi = String(d.getMinutes()).padStart(2, '0')
      const ss = String(d.getSeconds()).padStart(2, '0')
      return `${yyyy}-${mm}-${dd} ${hh}:${mi}:${ss}`
    },
    async fetch() {
      this.loading = true
      this.error = null
      try {
        const res = await api.get('/shop/customer/credit')
        this.sales = res.data?.data?.sales || []
        this.summary = res.data?.data?.summary || this.summary
      } catch (e) {
        this.error = e?.response?.data?.message || e.message || 'Failed to load credit'
      } finally {
        this.loading = false
      }
    },
  },
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

</style>
