<template>
  <div class="shop-page">
    <div class="card p-3 mb-3">
      <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
        <h4 class="m-0">Credit Requests</h4>
        <div class="d-flex gap-2">
          <button class="button-info" @click="fetch">Load</button>
          <button class="button-success" @click="showForm = !showForm">Request Credit</button>
        </div>
      </div>

      <div v-if="error" class="alert alert-danger mt-3 mb-0">{{ error }}</div>
    </div>

    <div v-if="showForm" class="card p-3 mb-3">
      <div class="fw-bold">New Request</div>
      <div class="row g-2 mt-2">
        <div class="col-12 col-md-4">
          <label class="form-label">Amount</label>
          <input class="form-control form-control-sm" type="number" step="0.01" v-model.number="form.amount_requested" />
        </div>
        <div class="col-12 col-md-8">
          <label class="form-label">Reason (optional)</label>
          <input class="form-control form-control-sm" type="text" v-model="form.reason" />
        </div>
        <div class="col-12">
          <button class="button-success" :disabled="saving" @click="submit">
            {{ saving ? 'Submitting...' : 'Submit' }}
          </button>
        </div>
      </div>
    </div>

    <div v-if="loading" class="card p-3">Loading...</div>

    <div v-else class="card p-3">
      <table class="table table-borderless mb-0">
        <thead>
          <tr>
            <th>#</th>
            <th>Date</th>
            <th>Amount</th>
            <th>Status</th>
            <th>Review Notes</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(r, idx) in rows" :key="r.id">
            <td>{{ idx + 1 }}</td>
            <td>{{ formatDateTime(r.created_at) }}</td>
            <td>R {{ formatMoney(r.amount_requested) }}</td>
            <td>{{ r.status }}</td>
            <td>{{ r.review_notes || '-' }}</td>
          </tr>
          <tr v-if="rows.length === 0">
            <td colspan="5">No requests.</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script>
import api from '@/store/services/api'

export default {
  name: 'CustomerCreditRequests',
  data() {
    return {
      loading: false,
      saving: false,
      showForm: false,
      error: null,
      rows: [],
      form: {
        amount_requested: 0,
        reason: '',
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
        const res = await api.get('/shop/customer/credit-requests')
        this.rows = res.data?.data || []
      } catch (e) {
        this.error = e?.response?.data?.message || e.message || 'Failed to load requests'
      } finally {
        this.loading = false
      }
    },
    async submit() {
      const amount = Number(this.form.amount_requested)
      if (Number.isNaN(amount) || amount <= 0) {
        this.error = 'Enter a valid amount.'
        return
      }

      this.saving = true
      this.error = null
      try {
        await api.post('/shop/customer/credit-requests', {
          amount_requested: amount,
          reason: this.form.reason || null,
        })
        this.form = { amount_requested: 0, reason: '' }
        this.showForm = false
        await this.fetch()
      } catch (e) {
        this.error = e?.response?.data?.message || e.message || 'Failed to submit request'
      } finally {
        this.saving = false
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

.form-control {
  background: rgba(255, 255, 255, 0.08) !important;
  border: 1px solid rgba(255, 255, 255, 0.18) !important;
  color: #fff !important;
}

.form-label {
  color: rgba(255, 255, 255, 0.9);
}
</style>
