<template>
  <div class="shop-page">
    <div class="card p-3 mb-3">
      <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
        <h4 class="m-0">Sales Summary</h4>
        <button class="button-info" @click="fetch">Load</button>
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
            <th>Customer</th>
            <th>Total</th>
            <th>Profit</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <template v-for="(s, idx) in sales" :key="s.id">
            <tr>
              <td>{{ idx + 1 }}</td>
              <td>{{ s.sale_datetime }}</td>
              <td>{{ s.customer_name || '-' }}</td>
              <td>R {{ formatMoney(s.total_amount) }}</td>
              <td>R {{ formatMoney(s.total_profit) }}</td>
              <td style="width: 1%; white-space: nowrap">
                <button class="button-info" @click="toggleSale(s.id)">
                  {{ expandedSaleId === s.id ? 'Hide' : 'View' }}
                </button>
              </td>
            </tr>

            <tr v-if="expandedSaleId === s.id">
              <td colspan="6">
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
                        <th></th>
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
                          <button class="button-success" :disabled="savingItemId === it.id" @click="saveItemProfit(it)">
                            {{ savingItemId === it.id ? 'Saving...' : 'Save' }}
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
    formatMoney(v) {
      const n = Number(v)
      if (Number.isNaN(n)) return '0.00'
      return n.toFixed(2)
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
