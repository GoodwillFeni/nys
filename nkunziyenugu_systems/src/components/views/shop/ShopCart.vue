<template>
  <div class="shop-page">
    <div class="card p-3 mb-3">
      <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
        <h4 class="m-0">Cart</h4>
        <RouterLink class="button-info" to="/Shop/Products">Continue Shopping</RouterLink>
      </div>
      <div v-if="error"   class="alert alert-danger  mt-3 mb-0">{{ error }}</div>
      <div v-if="success" class="alert alert-success mt-3 mb-0">{{ success }}</div>
    </div>

    <div class="card p-3" v-if="items.length === 0">
      Your cart is empty.
    </div>

    <div v-else>
      <div class="card p-3 mb-3">
        <table class="table table-borderless mb-0">
          <thead>
            <tr>
              <th>Product</th>
              <th style="width:120px">Qty</th>
              <th style="width:140px">Unit</th>
              <th style="width:140px">Total</th>
              <th style="width:50px"></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(i, idx) in items" :key="i.product_id">
              <td>{{ i.product_name }}</td>
              <td>
                <input class="form-control form-control-sm" type="number" min="1"
                  v-model.number="items[idx].qty" @change="persist" />
              </td>
              <td>R {{ fmt(i.unit_price) }}</td>
              <td>R {{ fmt(lineTotal(i)) }}</td>
              <td>
                <button class="button-danger" @click="removeItem(i.product_id)">
                  <i class="bi bi-x"></i>
                </button>
              </td>
            </tr>
          </tbody>
        </table>
        <div class="d-flex justify-content-end mt-3">
          <div class="fw-bold fs-5">Total: R {{ fmt(cartTotal) }}</div>
        </div>
      </div>

      <div class="d-flex justify-content-end">
        <button class="button-success" @click="handleCheckout">
          <i class="bi bi-credit-card me-1"></i> Proceed to Checkout
        </button>
      </div>
    </div>

    <!-- ── Auth modal (login / register) ──────────────────────────────────── -->
    <div v-if="showAuthModal" class="modal-backdrop" @click.self="showAuthModal = false">
      <div class="modal-box">
        <div class="modal-tabs">
          <button :class="['tab-btn', { active: authTab === 'login' }]" @click="authTab = 'login'">Log In</button>
          <button :class="['tab-btn', { active: authTab === 'register' }]" @click="authTab = 'register'">Register</button>
        </div>

        <!-- Login tab -->
        <div v-if="authTab === 'login'" class="mt-3">
          <div class="mb-3">
            <label>Email or Phone</label>
            <input class="form-control form-control-sm" v-model="loginForm.email" placeholder="Email or phone" />
          </div>
          <div class="mb-3">
            <label>Password</label>
            <input class="form-control form-control-sm" type="password" v-model="loginForm.password" />
          </div>
          <div v-if="authError" class="text-danger small mb-2">{{ authError }}</div>
          <button class="button-success w-100" :disabled="authLoading" @click="doLogin">
            {{ authLoading ? 'Logging in...' : 'Log In' }}
          </button>
        </div>

        <!-- Register tab -->
        <div v-if="authTab === 'register'" class="mt-3">
          <div class="mb-2">
            <label>First Name</label>
            <input class="form-control form-control-sm" v-model="regForm.name" />
          </div>
          <div class="mb-2">
            <label>Last Name</label>
            <input class="form-control form-control-sm" v-model="regForm.surname" />
          </div>
          <div class="mb-2">
            <label>Email</label>
            <input class="form-control form-control-sm" type="email" v-model="regForm.email" />
          </div>
          <div class="mb-2">
            <label>Phone</label>
            <input class="form-control form-control-sm" v-model="regForm.phone" />
          </div>
          <div class="mb-3">
            <label>Password</label>
            <input class="form-control form-control-sm" type="password" v-model="regForm.password" />
          </div>
          <div v-if="authError" class="text-danger small mb-2">{{ authError }}</div>
          <button class="button-success w-100" :disabled="authLoading" @click="doRegister">
            {{ authLoading ? 'Registering...' : 'Create Account' }}
          </button>
        </div>
      </div>
    </div>

    <!-- ── Payment modal ──────────────────────────────────────────────────── -->
    <div v-if="showPaymentModal" class="modal-backdrop" @click.self="showPaymentModal = false">
      <div class="modal-box">
        <h5 class="mb-3">Select Payment Method</h5>

        <div class="payment-options">
          <label class="payment-option" :class="{ selected: paymentMethod === 'pay_in_store' }">
            <input type="radio" v-model="paymentMethod" value="pay_in_store" @change="proofFile = null" />
            <i class="bi bi-shop"></i>
            <div>
              <strong>Pay in Store</strong>
              <div class="small text-muted">Bring payment when collecting your order</div>
            </div>
          </label>

          <label class="payment-option" :class="{ selected: paymentMethod === 'deposit' }">
            <input type="radio" v-model="paymentMethod" value="deposit" />
            <i class="bi bi-upload"></i>
            <div>
              <strong>Deposit Slip</strong>
              <div class="small text-muted">Upload proof of bank deposit</div>
            </div>
          </label>

          <label class="payment-option" :class="{ selected: paymentMethod === 'credit' }">
            <input type="radio" v-model="paymentMethod" value="credit" @change="proofFile = null" />
            <i class="bi bi-wallet2"></i>
            <div>
              <strong>Credit</strong>
              <div class="small text-muted">Use your store credit account</div>
            </div>
          </label>
        </div>

        <!-- Deposit slip upload -->
        <div v-if="paymentMethod === 'deposit'" class="mt-3">
          <label>Upload Deposit Slip <span class="text-muted small">(JPG, PNG or PDF, max 5MB)</span></label>
          <input class="form-control form-control-sm mt-1" type="file" accept=".jpg,.jpeg,.png,.pdf"
            @change="onFileChange" />
          <div v-if="proofFile" class="small mt-1 text-success">
            <i class="bi bi-check-circle"></i> {{ proofFile.name }}
          </div>
        </div>

        <div class="mb-3 mt-3">
          <label>Notes <span class="text-muted small">(optional)</span></label>
          <textarea class="form-control form-control-sm" rows="2" v-model="orderNotes"></textarea>
        </div>

        <div v-if="error" class="text-danger small mb-2">{{ error }}</div>

        <div class="d-flex gap-2 justify-content-end">
          <button class="button-danger" @click="showPaymentModal = false">Cancel</button>
          <button class="button-success" :disabled="submitting || !paymentMethod" @click="submitOrder">
            {{ submitting ? 'Placing Order...' : `Place Order — R ${fmt(cartTotal)}` }}
          </button>
        </div>
      </div>
    </div>

  </div>
</template>

<script>
import api from '@/store/services/api'

export default {
  name: 'ShopCart',

  data() {
    return {
      items: [],
      error: null,
      success: null,
      submitting: false,

      // Auth modal
      showAuthModal: false,
      authTab: 'login',
      authLoading: false,
      authError: null,
      loginForm: { email: '', password: '' },
      regForm: { name: '', surname: '', email: '', phone: '', password: '' },

      // Payment modal
      showPaymentModal: false,
      paymentMethod: 'pay_in_store',
      proofFile: null,
      orderNotes: '',
    }
  },

  computed: {
    isAuthenticated() {
      return !!this.$store.getters.isAuthenticated
    },
    cartTotal() {
      return this.items.reduce((sum, i) => sum + this.lineTotal(i), 0)
    }
  },

  mounted() {
    this.loadCart()
  },

  methods: {
    fmt(v) {
      const n = Number(v)
      return Number.isNaN(n) ? '0.00' : n.toFixed(2)
    },

    lineTotal(i) {
      return (Number(i.unit_price) || 0) * (Number(i.qty) || 0)
    },

    loadCart() {
      try {
        const cart = JSON.parse(localStorage.getItem('shop_cart') || '{"items":[]}')
        this.items = (cart.items || []).map(x => ({ ...x, qty: Number(x.qty) || 1 }))
      } catch {
        this.items = []
      }
    },

    persist() {
      localStorage.setItem('shop_cart', JSON.stringify({ items: this.items }))
      this.$store.dispatch('updateCartCount')
    },

    removeItem(productId) {
      this.items = this.items.filter(i => i.product_id !== productId)
      this.persist()
    },

    handleCheckout() {
      this.error = null
      if (!this.isAuthenticated) {
        this.showAuthModal = true
        return
      }
      this.showPaymentModal = true
    },

    onFileChange(e) {
      this.proofFile = e.target.files[0] || null
    },

    // ── Login ──────────────────────────────────────────────────────────────
    async doLogin() {
      this.authError = null
      this.authLoading = true
      try {
        const res = await api.post('/login', this.loginForm)
        this.$store.dispatch('login', res.data)
        this.showAuthModal = false
        this.showPaymentModal = true
      } catch (e) {
        this.authError = e?.response?.data?.message || 'Login failed'
      } finally {
        this.authLoading = false
      }
    },

    // ── Register ───────────────────────────────────────────────────────────
    async doRegister() {
      this.authError = null
      this.authLoading = true
      try {
        const res = await api.post('/register', this.regForm)
        this.$store.dispatch('login', res.data)
        this.showAuthModal = false
        this.showPaymentModal = true
      } catch (e) {
        const errors = e?.response?.data?.errors
        this.authError = errors
          ? Object.values(errors).flat().join(' ')
          : (e?.response?.data?.message || 'Registration failed')
      } finally {
        this.authLoading = false
      }
    },

    // ── Submit order ───────────────────────────────────────────────────────
    async submitOrder() {
      this.error = null
      if (!this.paymentMethod) return
      if (this.paymentMethod === 'deposit' && !this.proofFile) {
        this.error = 'Please upload your deposit slip.'
        return
      }

      this.submitting = true
      try {
        const formData = new FormData()
        formData.append('payment_method', this.paymentMethod)
        if (this.orderNotes) formData.append('notes', this.orderNotes)
        if (this.proofFile)  formData.append('payment_proof', this.proofFile)
        this.items.forEach((item, idx) => {
          formData.append(`items[${idx}][product_id]`, item.product_id)
          formData.append(`items[${idx}][qty]`,        item.qty)
        })

        const res = await api.post('/shop/orders', formData)

        // Clear cart
        this.items = []
        this.persist()
        this.showPaymentModal = false
        this.success = `Order #${res.data?.data?.id || ''} placed successfully! You will receive a confirmation email.`
      } catch (e) {
        this.error = e?.response?.data?.message || 'Failed to place order'
      } finally {
        this.submitting = false
      }
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

.table { color: #fff; background: transparent; }
.table th, .table td { color: #fff; }
.table > :not(caption) > * > * { background-color: transparent !important; color: #fff !important; }

.form-control {
  background: rgba(255,255,255,0.08) !important;
  border: 1px solid rgba(255,255,255,0.18) !important;
  color: #fff !important;
}
.form-control::placeholder { color: rgba(255,255,255,0.5) !important; }

/* ── Modals ─────────────────────────────────────────────── */
.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,0.6);
  z-index: 2000;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 16px;
}

.modal-box {
  background: linear-gradient(135deg, #27253f, #3d3650);
  border-radius: 12px;
  padding: 24px;
  width: 100%;
  max-width: 440px;
  color: #fff;
  max-height: 90vh;
  overflow-y: auto;
}

/* Auth tabs */
.modal-tabs { display: flex; gap: 8px; }
.tab-btn {
  flex: 1;
  padding: 8px;
  border: 1px solid rgba(255,255,255,0.2);
  background: transparent;
  color: rgba(255,255,255,0.6);
  border-radius: 6px;
  cursor: pointer;
  font-size: 14px;
}
.tab-btn.active {
  background: rgba(255,255,255,0.15);
  color: #fff;
  font-weight: 600;
}

/* Payment options */
.payment-options { display: flex; flex-direction: column; gap: 10px; }
.payment-option {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 14px;
  border: 1px solid rgba(255,255,255,0.15);
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.15s;
}
.payment-option input[type="radio"] { display: none; }
.payment-option i { font-size: 22px; opacity: 0.7; min-width: 24px; }
.payment-option.selected {
  border-color: #42a5f5;
  background: rgba(66,165,245,0.12);
}
.payment-option.selected i { opacity: 1; color: #42a5f5; }

.w-100 { width: 100%; }
.text-muted { color: rgba(255,255,255,0.5) !important; }
.text-success { color: #66bb6a !important; }
.text-danger { color: #ef5350 !important; }
.fs-5 { font-size: 18px; }
</style>
