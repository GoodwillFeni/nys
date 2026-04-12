<template>
  <div class="shop-page">
    <div class="card p-3 mb-3">
      <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
        <h4 class="m-0">Orders</h4>
        <div class="d-flex gap-2 flex-wrap">
          <select class="form-control form-control-sm" style="width:160px" v-model="filterStatus" @change="loadOrders">
            <option value="">All Statuses</option>
            <option value="pending_approval">Awaiting Approval</option>
            <option value="approved">Approved</option>
            <option value="rejected">Not Approved</option>
            <option value="completed">Completed</option>
          </select>
        </div>
      </div>
    </div>

    <div v-if="loading" class="card p-3">Loading orders...</div>
    <div v-else-if="error" class="alert alert-danger">{{ error }}</div>
    <div v-else-if="orders.length === 0" class="card p-3">No orders found.</div>

    <div v-else class="orders-list">
      <div v-for="o in orders" :key="o.id" class="order-card">
        <div class="order-header" @click="toggle(o.id)">
          <div class="d-flex align-items-center gap-2 flex-wrap">
            <span class="order-id">#{{ o.id }}</span>
            <span :class="['status-badge', statusClass(o.status)]">{{ statusLabel(o.status) }}</span>
            <span class="payment-badge">{{ paymentLabel(o.payment_method) }}</span>
            <span class="customer-name">{{ o.user?.name }} {{ o.user?.surname }}</span>
          </div>
          <div class="d-flex align-items-center gap-3">
            <span class="order-total">R {{ fmt(o.total_amount) }}</span>
            <span class="order-date">{{ fmtDate(o.created_at) }}</span>
            <i :class="['bi', expanded === o.id ? 'bi-chevron-up' : 'bi-chevron-down']"></i>
          </div>
        </div>

        <div v-if="expanded === o.id" class="order-body">
          <!-- Items table -->
          <table class="table table-borderless mb-0">
            <thead>
              <tr><th>Product</th><th>Qty</th><th>Unit</th><th>Total</th></tr>
            </thead>
            <tbody>
              <tr v-for="item in o.items" :key="item.id">
                <td>{{ item.product?.product_name || 'Product' }}</td>
                <td>
                  <!-- Allow qty edit if pending_approval -->
                  <input v-if="o.status === 'pending_approval'" class="form-control form-control-sm"
                    style="width:70px" type="number" min="1"
                    v-model.number="editQty[item.id]" />
                  <span v-else>{{ item.qty }}</span>
                </td>
                <td>R {{ fmt(item.unit_price) }}</td>
                <td>R {{ fmt(item.unit_price * (editQty[item.id] || item.qty)) }}</td>
              </tr>
            </tbody>
          </table>

          <div class="order-footer">

            <!-- Customer details card -->
            <div class="customer-card">
              <div class="customer-title"><i class="bi bi-person-circle"></i> Customer</div>
              <div class="customer-row">
                <span class="customer-name">{{ o.user?.name }} {{ o.user?.surname }}</span>
              </div>
              <div class="customer-contacts">
                <a v-if="o.user?.phone" :href="'tel:' + o.user.phone" class="contact-btn">
                  <i class="bi bi-telephone"></i> {{ o.user.phone }}
                </a>
                <a v-if="o.user?.email" :href="'mailto:' + o.user.email" class="contact-btn">
                  <i class="bi bi-envelope"></i> {{ o.user.email }}
                </a>
              </div>
            </div>

            <div v-if="o.notes" class="small mb-2"><strong>Notes:</strong> {{ o.notes }}</div>

            <!-- Deposit slip link -->
            <div v-if="o.payment_proof_path" class="mb-2">
              <a :href="proofUrl(o.payment_proof_path)" target="_blank" class="proof-link">
                <i class="bi bi-file-earmark"></i> View Deposit Slip
              </a>
            </div>

            <!-- Rejection reason display -->
            <div v-if="o.rejection_reason" class="rejection-reason mb-2">
              <i class="bi bi-exclamation-triangle"></i> {{ o.rejection_reason }}
            </div>

            <!-- Credit paid status -->
            <div v-if="o.payment_method === 'credit'" class="credit-status mb-2">
              <i class="bi bi-wallet2"></i>
              {{ o.paid_at ? 'Paid on ' + fmtDate(o.paid_at) : 'Payment pending' }}
            </div>

            <!-- Action buttons -->
            <div class="action-row">
              <template v-if="o.status === 'pending_approval'">
                <button class="button-success" @click="openAction(o, 'approve')">
                  <i class="bi bi-check-lg"></i> Approve
                </button>
                <!-- Deposit: offer single-step approve + complete since payment is already received -->
                <button v-if="o.payment_method === 'deposit'" class="button-info" @click="openAction(o, 'approve_complete')">
                  <i class="bi bi-bag-check"></i> Approve & Complete
                </button>
                <button class="button-danger" @click="openAction(o, 'reject')">
                  <i class="bi bi-x-lg"></i> Reject
                </button>
              </template>

              <template v-if="o.status === 'approved'">
                <button class="button-info" @click="openAction(o, 'complete')">
                  <i class="bi bi-bag-check"></i> Mark Completed
                </button>
              </template>

              <!-- Mark Paid: credit orders that haven't been paid yet (approved or completed) -->
              <button v-if="o.payment_method === 'credit' && !o.paid_at &&
                            (o.status === 'approved' || o.status === 'completed')"
                class="button-success" @click="markPaid(o)">
                <i class="bi bi-cash-coin"></i> Mark Paid
              </button>

              <!-- Ask customer a question -->
              <button class="button-warning" @click="openAsk(o)">
                <i class="bi bi-chat-dots"></i> Ask Customer
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Pagination -->
    <div v-if="pagination && pagination.last_page > 1" class="d-flex justify-content-center gap-2 mt-3">
      <button class="button-info" :disabled="pagination.current_page === 1"
        @click="loadOrders(pagination.current_page - 1)">Prev</button>
      <span class="page-info">{{ pagination.current_page }} / {{ pagination.last_page }}</span>
      <button class="button-info" :disabled="pagination.current_page === pagination.last_page"
        @click="loadOrders(pagination.current_page + 1)">Next</button>
    </div>

    <!-- Action modal (approve / reject / complete) -->
    <div v-if="actionModal.show" class="modal-backdrop" @click.self="actionModal.show = false">
      <div class="modal-box">
        <h5 class="mb-3">
          {{ actionModal.type === 'approve'          ? 'Approve Order' :
             actionModal.type === 'reject'           ? 'Reject Order'  :
             actionModal.type === 'approve_complete' ? 'Approve & Complete Order' : 'Mark as Completed' }}
          #{{ actionModal.order?.id }}
        </h5>

        <div v-if="actionModal.type === 'reject'" class="mb-3">
          <label>Reason <span class="text-muted small">(sent to customer)</span></label>
          <textarea class="form-control form-control-sm" rows="3" v-model="actionModal.reason"></textarea>
        </div>

        <div v-if="actionModal.type === 'approve'" class="mb-3">
          <label>Notes <span class="text-muted small">(optional)</span></label>
          <textarea class="form-control form-control-sm" rows="2" v-model="actionModal.notes"></textarea>
        </div>

        <div v-if="actionError" class="text-danger small mb-2">{{ actionError }}</div>

        <div class="d-flex gap-2 justify-content-end">
          <button class="button-danger" @click="actionModal.show = false">Cancel</button>
          <button :class="actionModal.type === 'reject' ? 'button-danger' : 'button-success'"
            :disabled="actionLoading" @click="confirmAction">
            {{ actionLoading ? 'Saving...' : 'Confirm' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Ask customer modal -->
    <div v-if="askModal.show" class="modal-backdrop" @click.self="askModal.show = false">
      <div class="modal-box">
        <h5 class="mb-1">Ask Customer a Question</h5>
        <p class="text-muted small mb-3">
          Message will be sent to
          <strong>{{ askModal.order?.user?.name }}</strong>
          at <strong>{{ askModal.order?.user?.email }}</strong>
          regarding Order #{{ askModal.order?.id }}
        </p>

        <div class="mb-3">
          <label>Your Question / Message</label>
          <textarea class="form-control form-control-sm" rows="4"
            v-model="askModal.message"
            placeholder="e.g. Can you confirm your delivery address?"></textarea>
        </div>

        <div v-if="askModal.error" class="text-danger small mb-2">{{ askModal.error }}</div>
        <div v-if="askModal.sent" class="text-success small mb-2">
          <i class="bi bi-check-circle"></i> Message sent successfully.
        </div>

        <div class="d-flex gap-2 justify-content-end">
          <button class="button-danger" @click="askModal.show = false">Cancel</button>
          <button class="button-warning" :disabled="askModal.loading || !askModal.message"
            @click="sendAsk">
            <i class="bi bi-send"></i>
            {{ askModal.loading ? 'Sending...' : 'Send Message' }}
          </button>
        </div>
      </div>
    </div>

  </div>
</template>

<script>
import api from '@/store/services/api'

export default {
  name: 'AdminOrders',

  data() {
    return {
      loading: false,
      error: null,
      orders: [],
      pagination: null,
      filterStatus: '',
      expanded: null,
      editQty: {},

      // Action modal
      actionModal: { show: false, type: '', order: null, reason: '', notes: '' },
      actionLoading: false,
      actionError: null,

      // Ask customer modal
      askModal: { show: false, order: null, message: '', loading: false, error: null, sent: false },
    }
  },

  mounted() {
    this.loadOrders()
  },

  methods: {
    async loadOrders(page = 1) {
      this.loading = true
      this.error = null
      try {
        const params = { page }
        if (this.filterStatus) params.status = this.filterStatus
        const res = await api.get('/shop/orders', { params })
        const data = res.data?.data || {}
        this.orders     = data.data || []
        this.pagination = { current_page: data.current_page, last_page: data.last_page }

        // Init edit qty map
        this.editQty = {}
        this.orders.forEach(o => o.items?.forEach(i => { this.editQty[i.id] = i.qty }))
      } catch (e) {
        this.error = e?.response?.data?.message || 'Failed to load orders'
      } finally {
        this.loading = false
      }
    },

    toggle(id) {
      this.expanded = this.expanded === id ? null : id
    },

    openAction(order, type) {
      this.actionModal = { show: true, type, order, reason: '', notes: order.notes || '' }
      this.actionError = null
    },

    async confirmAction() {
      this.actionError = null
      this.actionLoading = true
      const { type, order, reason, notes } = this.actionModal

      const payload = {}
      if (type === 'approve')          { payload.status = 'approved'; payload.notes = notes }
      if (type === 'reject')           { payload.status = 'rejected'; payload.rejection_reason = reason }
      if (type === 'complete')         { payload.status = 'completed' }
      if (type === 'approve_complete') { payload.status = 'completed'; payload.notes = notes }

      // Include edited quantities if approving (or approve+complete from pending)
      if ((type === 'approve' || type === 'approve_complete') && order.status === 'pending_approval') {
        payload.items = order.items.map(i => ({ id: i.id, qty: this.editQty[i.id] || i.qty }))
      }

      try {
        await api.put(`/shop/orders/${order.id}`, payload)
        this.actionModal.show = false
        await this.loadOrders()
      } catch (e) {
        this.actionError = e?.response?.data?.message || 'Action failed'
      } finally {
        this.actionLoading = false
      }
    },

    async markPaid(order) {
      try {
        await api.put(`/shop/orders/${order.id}`, { mark_paid: true })
        await this.loadOrders()
      } catch (e) {
        this.error = e?.response?.data?.message || 'Failed to mark paid'
      }
    },

    openAsk(order) {
      this.askModal = { show: true, order, message: '', loading: false, error: null, sent: false }
    },

    async sendAsk() {
      if (!this.askModal.message.trim()) return
      this.askModal.loading = true
      this.askModal.error = null
      this.askModal.sent = false
      try {
        await api.post(`/shop/orders/${this.askModal.order.id}/ask`, {
          message: this.askModal.message
        })
        this.askModal.sent = true
        this.askModal.message = ''
        // Auto-close after 2 seconds
        setTimeout(() => { this.askModal.show = false }, 2000)
      } catch (e) {
        this.askModal.error = e?.response?.data?.message || 'Failed to send message'
      } finally {
        this.askModal.loading = false
      }
    },

    fmt(v) {
      const n = Number(v)
      return Number.isNaN(n) ? '0.00' : n.toFixed(2)
    },

    fmtDate(d) {
      if (!d) return '—'
      return new Date(d).toLocaleDateString('en-ZA', { day: '2-digit', month: 'short', year: 'numeric' })
    },

    proofUrl(path) {
      const base = api.defaults.baseURL.replace(/\/api\/?$/, '')
      return `${base}/storage/${path}`
    },

    statusClass(s) {
      return { pending_approval: 'status-orange', approved: 'status-green', rejected: 'status-red', completed: 'status-blue' }[s] || 'status-grey'
    },
    statusLabel(s) {
      return { pending_approval: 'Awaiting Approval', approved: 'Approved', rejected: 'Not Approved', completed: 'Completed' }[s] || s
    },
    paymentLabel(m) {
      return { pay_in_store: 'Pay in Store', deposit: 'Deposit', credit: 'Credit' }[m] || (m || '—')
    }
  }
}
</script>

<style scoped>
.shop-page { padding: 10px; }
.card { background: linear-gradient(135deg, #27253f, #605a6d); color: #fff; border-radius: 8px; }
.orders-list { display: flex; flex-direction: column; gap: 10px; }

.order-card { background: linear-gradient(135deg, #27253f, #3d3650); border-radius: 10px; overflow: hidden; border: 1px solid rgba(255,255,255,0.08); }
.order-header { display: flex; align-items: center; justify-content: space-between; padding: 14px 16px; cursor: pointer; gap: 12px; flex-wrap: wrap; }
.order-header:hover { background: rgba(255,255,255,0.05); }
.order-id { font-weight: 700; font-size: 15px; color: #fff; }
.order-total { font-weight: 600; color: #fff; }
.order-date { font-size: 12px; color: rgba(255,255,255,0.5); }
.customer-name { font-size: 13px; color: rgba(255,255,255,0.7); }

.status-badge { padding: 3px 10px; border-radius: 12px; font-size: 12px; font-weight: 600; }
.status-orange { background: rgba(255,152,0,0.2);  color: #ffb74d; border: 1px solid #ff9800; }
.status-green  { background: rgba(76,175,80,0.2);  color: #81c784; border: 1px solid #4caf50; }
.status-red    { background: rgba(244,67,54,0.2);  color: #ef9a9a; border: 1px solid #f44336; }
.status-blue   { background: rgba(66,165,245,0.2); color: #90caf9; border: 1px solid #42a5f5; }
.status-grey   { background: rgba(255,255,255,0.1); color: rgba(255,255,255,0.6); }

.payment-badge { font-size: 11px; padding: 2px 8px; border-radius: 10px; background: rgba(255,255,255,0.08); color: rgba(255,255,255,0.6); }

.order-body { border-top: 1px solid rgba(255,255,255,0.08); padding: 0 16px 16px; }
.order-footer { margin-top: 12px; padding-top: 10px; border-top: 1px solid rgba(255,255,255,0.08); }

.action-row { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 12px; }

.proof-link { color: #42a5f5; font-size: 13px; text-decoration: none; }
.proof-link:hover { text-decoration: underline; }

.rejection-reason { background: rgba(244,67,54,0.12); border-left: 3px solid #f44336; padding: 8px 12px; border-radius: 4px; color: #ef9a9a; font-size: 13px; }
.credit-status { background: rgba(255,255,255,0.06); padding: 6px 10px; border-radius: 6px; font-size: 13px; color: rgba(255,255,255,0.7); }

/* Customer details card */
.customer-card { background: rgba(255,255,255,0.05); border-radius: 8px; padding: 10px 14px; margin-bottom: 12px; border: 1px solid rgba(255,255,255,0.1); }
.customer-title { font-size: 11px; text-transform: uppercase; color: rgba(255,255,255,0.5); margin-bottom: 6px; letter-spacing: 0.5px; }
.customer-name { font-weight: 600; color: #fff; font-size: 14px; }
.customer-contacts { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 8px; }
.contact-btn { display: inline-flex; align-items: center; gap: 6px; padding: 5px 12px; border-radius: 20px; font-size: 12px; text-decoration: none; background: rgba(255,255,255,0.08); color: rgba(255,255,255,0.85); border: 1px solid rgba(255,255,255,0.15); transition: all 0.15s; }
.contact-btn:hover { background: rgba(255,255,255,0.15); color: #fff; }

.page-info { color: rgba(255,255,255,0.7); display: flex; align-items: center; font-size: 13px; }

.table { color: #fff; background: transparent; }
.table th, .table td { color: #fff; padding: 8px 4px; font-size: 13px; }
.table > :not(caption) > * > * { background-color: transparent !important; color: #fff !important; }

.form-control { background: rgba(255,255,255,0.08) !important; border: 1px solid rgba(255,255,255,0.18) !important; color: #fff !important; }
.form-control option { background: #27253f; color: #fff; }

/* Modal */
.modal-backdrop { position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 2000; display: flex; align-items: center; justify-content: center; padding: 16px; }
.modal-box { background: linear-gradient(135deg, #27253f, #3d3650); border-radius: 12px; padding: 24px; width: 100%; max-width: 440px; color: #fff; }
.text-muted { color: rgba(255,255,255,0.5) !important; }
.text-danger { color: #ef5350 !important; }
</style>
