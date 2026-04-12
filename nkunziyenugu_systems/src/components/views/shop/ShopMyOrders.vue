<template>
  <div class="shop-page">
    <div class="card p-3 mb-3">
      <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
        <h4 class="m-0">My Orders</h4>
        <RouterLink class="button-info" to="/Shop/Products">Shop Products</RouterLink>
      </div>
    </div>

    <div v-if="loading" class="card p-3">Loading orders...</div>
    <div v-else-if="error" class="alert alert-danger">{{ error }}</div>
    <div v-else-if="orders.length === 0" class="card p-3">No orders yet.</div>

    <div v-else class="orders-list">
      <div v-for="o in orders" :key="o.id" class="order-card">
        <div class="order-header" @click="toggle(o.id)">
          <div class="d-flex align-items-center gap-2 flex-wrap">
            <span class="order-id">#{{ o.id }}</span>
            <span :class="['status-badge', statusClass(o.status)]">{{ statusLabel(o.status) }}</span>
            <span class="payment-badge">{{ paymentLabel(o.payment_method) }}</span>
          </div>
          <div class="d-flex align-items-center gap-3">
            <span class="order-total">R {{ fmt(o.total_amount) }}</span>
            <span class="order-date">{{ fmtDate(o.created_at) }}</span>
            <i :class="['bi', expanded === o.id ? 'bi-chevron-up' : 'bi-chevron-down']"></i>
          </div>
        </div>

        <div v-if="expanded === o.id" class="order-body">
          <table class="table table-borderless mb-0">
            <thead>
              <tr>
                <th>Product</th>
                <th style="width:60px">Qty</th>
                <th style="width:100px">Unit</th>
                <th style="width:110px" class="text-end">Total</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in o.items" :key="item.id">
                <td>{{ item.product?.product_name || 'Product' }}</td>
                <td>{{ item.qty }}</td>
                <td>R {{ fmt(item.unit_price) }}</td>
                <td class="text-end">R {{ fmt(item.total_price) }}</td>
              </tr>
            </tbody>
            <tfoot>
              <tr class="total-row">
                <td colspan="3" class="text-end total-label">Grand Total</td>
                <td class="text-end total-amount">R {{ fmt(o.total_amount) }}</td>
              </tr>
            </tfoot>
          </table>

          <div class="order-footer">
            <div v-if="o.notes" class="small mb-1"><strong>Notes:</strong> {{ o.notes }}</div>
            <div v-if="o.payment_method" class="payment-info">
              <i class="bi bi-credit-card"></i>
              Payment: <strong>{{ paymentLabel(o.payment_method) }}</strong>
            </div>
            <div v-if="o.rejection_reason" class="rejection-reason mt-2">
              <i class="bi bi-exclamation-triangle"></i> {{ o.rejection_reason }}
            </div>
          </div>
        </div>
      </div>
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
      expanded: null,
    }
  },

  mounted() {
    this.loadOrders()
  },

  methods: {
    async loadOrders() {
      this.loading = true
      this.error = null
      try {
        const res = await api.get('/shop/orders/my')
        this.orders = res.data?.data || []
      } catch (e) {
        this.error = e?.response?.data?.message || 'Failed to load orders'
      } finally {
        this.loading = false
      }
    },

    toggle(id) {
      this.expanded = this.expanded === id ? null : id
    },

    fmt(v) {
      const n = Number(v)
      return Number.isNaN(n) ? '0.00' : n.toFixed(2)
    },

    fmtDate(d) {
      if (!d) return '—'
      return new Date(d).toLocaleDateString('en-ZA', { day: '2-digit', month: 'short', year: 'numeric' })
    },

    statusClass(status) {
      const map = {
        pending_approval: 'status-orange',
        approved:         'status-green',
        rejected:         'status-red',
        completed:        'status-blue',
      }
      return map[status] || 'status-grey'
    },

    statusLabel(status) {
      const map = {
        pending_approval: 'Awaiting Approval',
        approved:         'Approved',
        rejected:         'Not Approved',
        completed:        'Completed',
      }
      return map[status] || status
    },

    paymentLabel(method) {
      const map = { pay_in_store: 'Pay in Store', deposit: 'Deposit', credit: 'Credit' }
      return map[method] || (method || '—')
    }
  }
}
</script>

<style scoped>
.shop-page { padding: 10px; }

.card {
  background: linear-gradient(135deg, #27253f, #605a6d);
  color: #fff;
  border-radius: 8px;
}

.orders-list { display: flex; flex-direction: column; gap: 10px; }

.order-card {
  background: linear-gradient(135deg, #27253f, #3d3650);
  border-radius: 10px;
  overflow: hidden;
  border: 1px solid rgba(255,255,255,0.08);
}

.order-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 14px 16px;
  cursor: pointer;
  gap: 12px;
  flex-wrap: wrap;
}
.order-header:hover { background: rgba(255,255,255,0.05); }

.order-id    { font-weight: 700; font-size: 15px; color: #fff; }
.order-total { font-weight: 600; color: #fff; }
.order-date  { font-size: 12px; color: rgba(255,255,255,0.5); }

.status-badge {
  padding: 3px 10px;
  border-radius: 12px;
  font-size: 12px;
  font-weight: 600;
}
.status-orange { background: rgba(255,152,0,0.2);  color: #ffb74d; border: 1px solid #ff9800; }
.status-green  { background: rgba(76,175,80,0.2);  color: #81c784; border: 1px solid #4caf50; }
.status-red    { background: rgba(244,67,54,0.2);  color: #ef9a9a; border: 1px solid #f44336; }
.status-blue   { background: rgba(66,165,245,0.2); color: #90caf9; border: 1px solid #42a5f5; }
.status-grey   { background: rgba(255,255,255,0.1); color: rgba(255,255,255,0.6); }

.payment-badge {
  font-size: 11px;
  padding: 2px 8px;
  border-radius: 10px;
  background: rgba(255,255,255,0.08);
  color: rgba(255,255,255,0.6);
}

.order-body {
  border-top: 1px solid rgba(255,255,255,0.08);
  padding: 0 16px 16px;
}

.order-footer {
  margin-top: 12px;
  padding-top: 10px;
  border-top: 1px solid rgba(255,255,255,0.08);
}

.rejection-reason {
  background: rgba(244,67,54,0.12);
  border-left: 3px solid #f44336;
  padding: 8px 12px;
  border-radius: 4px;
  color: #ef9a9a;
  font-size: 13px;
  margin-bottom: 8px;
}

.table { color: #fff; background: transparent; }
.table th, .table td { color: #fff; padding: 8px 4px; font-size: 13px; }
.table > :not(caption) > * > * { background-color: transparent !important; color: #fff !important; }
.text-end { text-align: right; }

.total-row { border-top: 2px solid rgba(255,255,255,0.25); }
.total-label { color: rgba(255,255,255,0.7); font-size: 13px; font-weight: 600; padding-top: 10px; }
.total-amount { font-size: 16px; font-weight: 700; color: #fff; padding-top: 10px; }

.payment-info { font-size: 12px; color: rgba(255,255,255,0.6); margin-top: 8px; }
.payment-info i { margin-right: 6px; }
</style>
