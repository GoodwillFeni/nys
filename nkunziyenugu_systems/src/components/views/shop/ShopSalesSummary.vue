<template>
  <div class="shop-page">
    <div class="card p-3 mb-3">
      <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
        <h4 class="m-0">Sales Summary</h4>
        <!-- <button class="button-info" @click="fetch">Load</button> -->
      </div>

      <div class="row g-2 mt-2">
        <div class="col-12 col-md-4">
          <label class="form-label">From</label>
          <input class="form-control form-control-sm" type="date" v-model="from" @change="fetch"/>
        </div>
        <div class="col-12 col-md-4">
          <label class="form-label">To</label>
          <input class="form-control form-control-sm" type="date" v-model="to" @change="fetch" />
        </div>
        <div class="col-12 col-md-4 d-flex align-items-end">
          <div class="fw-bold">Total: R {{ formatMoney(total) }}</div>
        </div>
      </div>

      <div v-if="error" class="alert alert-danger mt-3 mb-0">{{ error }}</div>
    </div>

    <div v-if="loading" class="card p-3">Loading...</div>

    <div v-else class="card p-3">
      <table class="table table-borderless mb-0">
        <thead>
          <tr>
            <th>#</th>
            <th>Date</th>
            <th>Cashier</th>
            <th>Customer</th>
            <th>Payment</th>
            <th>Paid</th>
            <th>Total</th>
            <th>Profit</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <template v-for="(s, idx) in sales" :key="s.id">
            <tr>
              <td>{{ idx + 1 }}</td>
              <td>{{ formatDateTime(s.sale_datetime) }}</td>
              <td>{{ formatUser(s.cashier) }}</td>
              <td>
                <span>{{ s.customer_name || '-' }}</span>
                <button
                  v-if="!s.customer_name"
                  class="button-info ms-2"
                  @click="setCustomerName(s)"
                >
                  <i class="bi bi-person-plus"></i>
                </button>
              </td>
              <td>{{ s.payment_method || '-' }}</td>
              <td>
                <span v-if="s.is_paid">Yes</span>
                <span v-else>No</span>
                <button
                  v-if="!s.is_paid && s.payment_method === 'Credit' && markPaidSaleId !== s.id"
                  class="button-success ms-2"
                  @click="startMarkPaid(s)"
                >
                  <i class="bi bi-check"></i>
                </button>

                <span v-if="!s.is_paid && s.payment_method === 'Credit' && markPaidSaleId === s.id" class="ms-2">
                  <select class="form-control form-control-sm d-inline-block" style="width: 160px" v-model="markPaidMethod">
                    <option value="Cash">Cash</option>
                    <option value="Cash Deposit">Cash Deposit</option>
                  </select>
                  <button class="button-success ms-2" :disabled="markPaidSaving" @click="confirmMarkPaid(s)">
                    {{ markPaidSaving ? 'Saving...' : 'Confirm' }}
                  </button>
                  <button class="button-danger ms-2" :disabled="markPaidSaving" @click="cancelMarkPaid">
                    Cancel
                  </button>
                </span>
              </td>
              <td>R {{ formatMoney(s.total_amount) }}</td>
              <td>R {{ formatMoney(s.total_profit) }}</td>
              <td style="width: 1%; white-space: nowrap">
                <button class="button-info" @click="toggleSale(s.id)">
                  <i v-if="expandedSaleId === s.id" class="bi bi-eye-slash"></i>
                  <i v-else class="bi bi-eye"></i>
                </button>
              </td>
            </tr>

            <tr v-if="expandedSaleId === s.id">
              <td colspan="9">
                <div class="p-2" style="background: rgba(255,255,255,0.06); border-radius: 8px;">
                  <div class="fw-bold mb-2">Items</div>
                  <div v-if="!s.items || s.items.length === 0" class="small">No items</div>

                  <table v-else class="table table-borderless mb-0">
                    <thead>
                      <tr>
                        <th>Product</th>
                        <th>Qty</th>
                        <th>Unit</th>
                        <th>Total</th>
                        <th>Profit/Item</th>
                        <th>Actions</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="it in s.items" :key="it.id">
                        <td>{{ it.product_name }}</td>
                        <td>{{ it.qty_sold }}</td>
                        <td>R {{ formatMoney(it.actual_price) }}</td>
                        <td>R {{ formatMoney(it.total_price) }}</td>
                        <td style="max-width: 160px">
                          <input
                            class="form-control form-control-sm"
                            type="number"
                            step="0.01"
                            v-model.number="profitEditByItemId[it.id]"
                          />
                        </td>
                        <td style="width: 1%; white-space: nowrap">
                          <button class="button-success" 
                          :disabled="savingItemId === it.id" 
                          @click="saveItemProfit(it)">
                            <i class="bi bi-check"></i>
                          </button>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </td>
            </tr>
          </template>
        </tbody>
      </table>
      <div v-if="sales.length === 0">No sales in this period.</div>
    </div>
  </div>
</template>

<script>
import api from '@/store/services/api'

export default {
  name: 'ShopSalesSummary',
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
      sales: [],
      expandedSaleId: null,
      profitEditByItemId: {},
      savingItemId: null,
      markPaidSaleId: null,
      markPaidMethod: 'Cash',
      markPaidSaving: false,
    }
  },
  computed: {
    total() {
      return this.sales.reduce((sum, s) => sum + (Number(s.total_amount) || 0), 0)
    }
  },
  mounted() {
    this.fetch()
  },
  methods: {
    formatUser(u) {
      if (!u) return '-'
      const name = String(u.name || '').trim()
      const surname = String(u.surname || '').trim()
      return `${name} ${surname}`.trim() || '-'
    },
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
    toggleSale(id) {
      if (this.expandedSaleId === id) {
        this.expandedSaleId = null
        return
      }
      this.expandedSaleId = id

      const sale = this.sales.find(s => s.id === id)
      const items = sale?.items || []
      for (const it of items) {
        if (this.profitEditByItemId[it.id] == null) {
          this.profitEditByItemId[it.id] = Number(it.prof_per_product) || 0
        }
      }
    },
    async saveItemProfit(item) {
      this.savingItemId = item.id
      this.error = null
      try {
        const prof = Number(this.profitEditByItemId[item.id])
        const res = await api.put(`/shop/pos/sale-items/${item.id}`, {
          prof_per_product: Number.isNaN(prof) ? 0 : prof,
        })

        const updatedSale = res.data?.data
        if (updatedSale?.id) {
          const idx = this.sales.findIndex(s => s.id === updatedSale.id)
          if (idx >= 0) this.sales.splice(idx, 1, updatedSale)

          const items = updatedSale.items || []
          for (const it of items) {
            this.profitEditByItemId[it.id] = Number(it.prof_per_product) || 0
          }
        }
      } catch (e) {
        this.error = e?.response?.data?.message || e.message || 'Failed to update sale item'
      } finally {
        this.savingItemId = null
      }
    },
    async fetch() {
      this.loading = true
      this.error = null
      try {
        const res = await api.get('/shop/pos/sales-report', { params: { from: this.from, to: this.to } })
        this.sales = res.data?.data || []
        this.expandedSaleId = null
        this.profitEditByItemId = {}
      } catch (e) {
        this.error = e?.response?.data?.message || e.message || 'Failed to load sales'
      } finally {
        this.loading = false
      }
    },
    startMarkPaid(sale) {
      this.markPaidSaleId = sale?.id || null
      this.markPaidMethod = 'Cash'
      this.error = null
    },
    cancelMarkPaid() {
      this.markPaidSaleId = null
      this.markPaidMethod = 'Cash'
      this.markPaidSaving = false
    },
    async confirmMarkPaid(sale) {
      if (!sale?.id) return

      this.markPaidSaving = true
      this.error = null

      try {
        if (!sale?.customer_name) {
          const name = window.prompt('Customer name', '')
          if (!name) return

          const resName = await api.put(`/shop/pos/sales/${sale.id}`, { customer_name: name })
          const updatedNameSale = resName.data?.data
          if (updatedNameSale?.id) {
            const idx = this.sales.findIndex(x => x.id === updatedNameSale.id)
            if (idx >= 0) this.sales.splice(idx, 1, updatedNameSale)
            sale = updatedNameSale
          }
        }

        const res = await api.post(`/shop/pos/sales/${sale.id}/mark-paid`, { paid_method: this.markPaidMethod })
        const updated = res.data?.data
        if (updated?.id) {
          const idx = this.sales.findIndex(x => x.id === updated.id)
          if (idx >= 0) this.sales.splice(idx, 1, updated)
        }

        this.cancelMarkPaid()
      } catch (e) {
        this.error = e?.response?.data?.message || e.message || 'Failed to mark sale as paid'
      } finally {
        this.markPaidSaving = false
      }
    },
    async setCustomerName(sale) {
      const name = window.prompt('Customer name', '')
      if (!name) return

      this.error = null
      try {
        const res = await api.put(`/shop/pos/sales/${sale.id}`, { customer_name: name })
        const updated = res.data?.data
        if (updated?.id) {
          const idx = this.sales.findIndex(x => x.id === updated.id)
          if (idx >= 0) this.sales.splice(idx, 1, updated)
        }
      } catch (e) {
        this.error = e?.response?.data?.message || e.message || 'Failed to update customer name'
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

.text-muted {
  color: rgba(255, 255, 255, 0.7) !important;
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
</style>
