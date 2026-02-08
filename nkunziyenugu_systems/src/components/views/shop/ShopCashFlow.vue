<template>
  <div class="shop-page">
    <div class="card p-3 mb-3">
      <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
        <h4 class="m-0">Cash Flow</h4>
        <div class="d-flex gap-2">
          <button class="button-info" @click="fetch">Load</button>
          <button v-if="isPrivileged" class="button-success" @click="openCreate">Add</button>
        </div>
      </div>

      <div class="row g-2 mt-2">
        <div class="col-12 col-md-4">
          <label class="form-label">From</label>
          <input class="form-control form-control-sm" type="date" v-model="from" />
        </div>
        <div class="col-12 col-md-4">
          <label class="form-label">To</label>
          <input class="form-control form-control-sm" type="date" v-model="to" />
        </div>
        <div class="col-12 col-md-4 d-flex align-items-end">
          <div class="fw-bold">Turnover: R {{ formatMoney(turnover) }}</div>
        </div>
      </div>

      <div v-if="error" class="alert alert-danger mt-3 mb-0">{{ error }}</div>
    </div>

    <div v-if="isPrivileged && showForm" class="card p-3 mb-3">
      <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div class="fw-bold">{{ form.id ? 'Edit Cashflow' : 'Add Cashflow' }}</div>
        <button class="button-danger" @click="closeForm">Close</button>
      </div>

      <div class="row g-2 mt-2">
        <div class="col-12 col-md-4">
          <label class="form-label">Transaction Type</label>
          <select class="form-control form-control-sm" v-model="form.transaction_type">
            <option disabled value="">Choose</option>
            <option value="Income">Income</option>
            <option value="Expense">Expense</option>
            <option value="Cashup">Cashup</option>
            <option value="Other">Other</option>
          </select>
        </div>
        <div class="col-12 col-md-4">
          <label class="form-label">Payment Type</label>
          <select class="form-control form-control-sm" v-model="form.payment_type">
            <option value="">-</option>
            <option value="Cash">Cash</option>
            <option value="Card">Card</option>
            <option value="EFT">EFT</option>
            <option value="Other">Other</option>
          </select>
        </div>
        <div class="col-12 col-md-4">
          <label class="form-label">Date</label>
          <input class="form-control form-control-sm" v-model="form.date" type="date" />
        </div>
        <div class="col-12 col-md-4">
          <label class="form-label">Amount</label>
          <input class="form-control form-control-sm" v-model.number="form.amount" type="number" step="0.01" />
        </div>
        <div class="col-12 col-md-8">
          <label class="form-label">Notes</label>
          <input class="form-control form-control-sm" v-model="form.notes" type="text" />
        </div>
        <div class="col-12 d-flex gap-2 mt-1">
          <button class="button-success" :disabled="saving" @click="save">{{ saving ? 'Saving...' : 'Save' }}</button>
          <button v-if="form.id" class="button-danger" :disabled="saving" @click="remove(form.id)">Delete</button>
        </div>
      </div>
    </div>

    <div v-if="loading" class="card p-3">Loading...</div>

    <div v-else class="card p-3">
      <table class="table table-borderless mb-0">
        <thead>
          <tr>
            <th>#</th>
            <th>Type</th>
            <th>Payment</th>
            <th>Amount</th>
            <th>Date</th>
            <th v-if="isPrivileged"></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(r, idx) in rows" :key="r.id">
            <td>{{ idx + 1 }}</td>
            <td>{{ r.transaction_type }}</td>
            <td>{{ r.payment_type || '-' }}</td>
            <td>R {{ formatMoney(r.amount) }}</td>
            <td>{{ r.date }}</td>
            <td v-if="isPrivileged" style="width: 1%; white-space: nowrap">
              <button class="button-info" @click="openEdit(r)">Edit</button>
            </td>
          </tr>
        </tbody>
      </table>
      <div v-if="rows.length === 0">No cashflow rows in this period.</div>
    </div>
  </div>
</template>

<script>
import api from '@/store/services/api'

export default {
  name: 'ShopCashFlow',
  data() {
    const today = new Date()
    const yyyy = today.getFullYear()
    const mm = String(today.getMonth() + 1).padStart(2, '0')
    const dd = String(today.getDate()).padStart(2, '0')
    return {
      from: `${yyyy}-${mm}-01`,
      to: `${yyyy}-${mm}-${dd}`,
      loading: false,
      error: null,
      rows: [],
      showForm: false,
      saving: false,
      form: {
        id: null,
        transaction_type: '',
        payment_type: '',
        notes: '',
        date: `${yyyy}-${mm}-${dd}`,
        amount: 0,
      },
    }
  },
  computed: {
    isPrivileged() {
      return (
        !!this.$store?.getters?.isAdmin ||
        !!this.$store?.getters?.isOwner ||
        !!this.$store?.getters?.isSuperAdmin
      )
    },
    turnover() {
      return this.rows.reduce((sum, r) => sum + (Number(r.amount) || 0), 0)
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
    resetForm() {
      this.form = {
        id: null,
        transaction_type: '',
        payment_type: '',
        notes: '',
        date: this.to,
        amount: 0,
      }
    },
    openCreate() {
      this.resetForm()
      this.showForm = true
      this.error = null
    },
    openEdit(r) {
      this.form = {
        id: r.id,
        transaction_type: r.transaction_type || '',
        payment_type: r.payment_type || '',
        notes: r.notes || '',
        date: r.date,
        amount: Number(r.amount) || 0,
      }
      this.showForm = true
      this.error = null
    },
    closeForm() {
      this.showForm = false
      this.resetForm()
    },
    async save() {
      if (!this.isPrivileged) return

      this.saving = true
      this.error = null
      try {
        const payload = {
          transaction_type: this.form.transaction_type,
          payment_type: this.form.payment_type || null,
          notes: this.form.notes || null,
          date: this.form.date,
          amount: this.form.amount,
        }

        if (this.form.id) {
          await api.put(`/shop/cashflow/${this.form.id}`, payload)
        } else {
          await api.post('/shop/cashflow', payload)
        }

        this.closeForm()
        await this.fetch()
      } catch (e) {
        this.error = e?.response?.data?.message || e.message || 'Failed to save cashflow'
      } finally {
        this.saving = false
      }
    },
    async remove(id) {
      if (!this.isPrivileged) return

      const ok = window.confirm('Delete this cashflow row?')
      if (!ok) return

      this.saving = true
      this.error = null
      try {
        await api.delete(`/shop/cashflow/${id}`)
        this.closeForm()
        await this.fetch()
      } catch (e) {
        this.error = e?.response?.data?.message || e.message || 'Failed to delete cashflow'
      } finally {
        this.saving = false
      }
    },
    async fetch() {
      this.loading = true
      this.error = null
      try {
        const res = await api.get('/shop/cashflow', { params: { from: this.from, to: this.to } })
        this.rows = res.data?.data || []
      } catch (e) {
        this.error = e?.response?.data?.message || e.message || 'Failed to load cashflow'
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
  background: transparent;
}

.table th,
.table td {
  color: #fff;
}

/* Bootstrap 5 applies cell background via :not(caption) selector; override it */
.table > :not(caption) > * > * {
  background-color: transparent !important;
  color: #fff !important;
}

.table thead th {
  border-bottom: 1px solid rgba(255, 255, 255, 0.15);
}

.table tbody tr {
  border-top: 1px solid rgba(255, 255, 255, 0.08);
}

.form-control {
  background: rgba(255, 255, 255, 0.08) !important;
  border: 1px solid rgba(255, 255, 255, 0.18) !important;
  color: #fff !important;
}

.form-control::placeholder {
  color: rgba(255, 255, 255, 0.65) !important;
}

.form-label {
  color: rgba(255, 255, 255, 0.9);
}

select.form-control {
  appearance: none;
  -webkit-appearance: none;
  -moz-appearance: none;
  background-color: rgba(255, 255, 255, 0.08) !important;
  color: #fff !important;
}

select.form-control option {
  background-color: #27253f;
  color: #fff;
}

.text-muted {
  color: rgba(255, 255, 255, 0.7) !important;
}
</style>
