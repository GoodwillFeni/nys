<template>
  <div class="shop-page">
    <div class="card p-3 mb-3">
      <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
        <h4 class="m-0">POS (Cashier)</h4>
        <div class="d-flex align-items-center gap-2">
          <button class="button-info" @click="refresh">
            <i class="bi bi-arrow-clockwise"></i>
          </button>
          <button class="button-success" :disabled="checkoutDisabled" @click="checkout">
            Checkout (R {{ formatMoney(cartTotal) }})
          </button>
        </div>
      </div>
      <div v-if="error" class="alert alert-danger mt-3 mb-0">{{ error }}</div>
      <div v-if="success" class="alert alert-success mt-3 mb-0">{{ success }}</div>
    </div>

    <div class="row g-3">
      <div class="col-12 col-lg-7">
        <div class="card p-3">
          <div class="d-flex align-items-center justify-content-between">
            <div class="fw-bold">Products</div>
            <input
              class="form-control form-control-sm"
              style="width: 220px"
              type="text"
              placeholder="Search"
              v-model="search"
            />
          </div>

          <div v-if="loadingProducts" class="mt-3">Loading...</div>

          <table v-else class="table table-borderless mt-3 mb-0">
            <thead>
              <tr>
                <th>Name</th>
                <th>Stock</th>
                <th>Price</th>
                <th style="width:220px">Action</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="p in filteredProducts" :key="p.id">
                <td>{{ p.product_name }}</td>
                <td>{{ p.stock_level }}</td>
                <td>R {{ formatMoney(p.actual_price) }}</td>
                <td>
                  <div class="d-flex gap-2 align-items-center">
                    <input
                      class="form-control form-control-sm"
                      style="width: 90px"
                      type="number"
                      min="1"
                      v-model.number="qtyById[p.id]"
                    />
                    <button class="button-success" @click="addItem(p)"><i class="bi bi-cart-plus"></i></button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>

          <div v-if="filteredProducts.length === 0" class="mt-3">No products.</div>
        </div>
      </div>

      <div class="col-12 col-lg-5">
        <div class="card p-3">
          <div class="fw-bold mb-2">Current POS Cart</div>

          <div v-if="loadingCart">Loading cart...</div>

          <div v-else>
            <table class="table table-borderless mb-0" v-if="cartItems.length > 0">
              <thead>
                <tr>
                  <th>Item</th>
                  <th style="width:110px">Qty</th>
                  <th style="width:120px">Total</th>
                  <th style="width:70px"></th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="it in cartItems" :key="it.id">
                  <td>{{ it.product?.product_name || it.product_id }}</td>
                  <td>
                    <input
                      class="form-control form-control-sm"
                      type="number"
                      min="1"
                      :value="it.qty"
                      @change="e => updateQty(it, e.target.value)"
                    />
                  </td>
                  <td>R {{ formatMoney(it.total_price) }}</td>
                  <td>
                    <button class="button-danger" @click="remove(it)"><i class="bi bi-cart-x"></i></button>
                  </td>
                </tr>
              </tbody>
            </table>

            <div v-else class="text-muted">No items in cart.</div>

            <div class="d-flex justify-content-end mt-3">
              <div class="fw-bold">Total: R {{ formatMoney(cartTotal) }}</div>
            </div>

            <div class="row g-2 mt-2" v-if="cartItems.length > 0">
              <div class="col-12 col-md-7">
                <label class="form-label">Payment method</label>
                <select class="form-control form-control-sm" v-model="paymentMethod">
                  <option value="Cash">Cash</option>
                  <option value="Cash Deposit">Cash Deposit</option>
                  <option value="Credit">Credit</option>
                </select>
              </div>

              <div class="col-12 col-md-7" v-if="requiresCashAmount">
                <label class="form-label">Amount given</label>
                <input
                  class="form-control form-control-sm"
                  type="number"
                  step="0.01"
                  min="0"
                  v-model.number="amountGiven"
                  placeholder="Enter amount"
                />
                <div v-if="amountGivenWarning" class="small mt-1" style="color:#ffb3b3; font-weight:600">
                  {{ amountGivenWarning }}
                </div>
              </div>

              <div class="col-12 col-md-5 d-flex align-items-end justify-content-end" v-if="requiresCashAmount">
                <div class="fw-bold">Change: R {{ formatMoney(changeAmount) }}</div>
              </div>

              <div class="col-12" v-if="creditWarning">
                <div class="small mt-1" style="color:#ffb3b3; font-weight:600">
                  {{ creditWarning }}
                </div>
              </div>
            </div>

            <div class="mt-3" v-if="paymentMethod !== 'Credit'">
              <label class="form-label">Customer name (optional)</label>
              <input class="form-control form-control-sm" v-model="customerName" placeholder="Walk-in customer" />
            </div>

            <div class="mt-3" v-else>
              <div class="fw-bold mb-2">Credit Customer</div>

              <div class="row g-2">
                <div class="col-12">
                  <label class="form-label">Search customer</label>
                  <input
                    class="form-control form-control-sm"
                    v-model="customerSearch"
                    placeholder="Type name or phone"
                    @input="onCustomerSearch"
                  />
                </div>
                <div class="col-12">
                  <label class="form-label">Select customer</label>
                  <select class="form-control form-control-sm" v-model="selectedCustomerId">
                    <option value="" disabled>Choose</option>
                    <option v-for="c in customerResults" :key="c.id" :value="c.id">
                      {{ c.name }} - {{ c.phone }}
                    </option>
                  </select>
                </div>
              </div>

              <div class="mt-3" v-if="!selectedCustomerId">
                <div class="fw-bold mb-2">Or create new customer</div>
                <div class="row g-2">
                  <div class="col-12 col-md-6">
                    <label class="form-label">Name</label>
                    <input class="form-control form-control-sm" v-model="newCustomer.name" />
                  </div>
                  <div class="col-12 col-md-6">
                    <label class="form-label">Phone</label>
                    <input class="form-control form-control-sm" v-model="newCustomer.phone" />
                  </div>
                  <div class="col-12 col-md-6">
                    <label class="form-label">Email (optional)</label>
                    <input class="form-control form-control-sm" v-model="newCustomer.email" />
                  </div>
                  <div class="col-12 col-md-6">
                    <label class="form-label">Password</label>
                    <input class="form-control form-control-sm" type="password" v-model="newCustomer.password" />
                  </div>
                  <div class="col-12">
                    <button class="button-success" :disabled="creatingCustomer" @click="createCustomer">
                      {{ creatingCustomer ? 'Creating...' : 'Create Customer' }}
                    </button>
                  </div>
                </div>
              </div>
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
  name: 'ShopPOS',
  data() {
    return {
      error: null,
      success: null,

      loadingProducts: false,
      products: [],
      search: '',
      qtyById: {},

      loadingCart: false,
      cart: null,
      customerName: '',
      paymentMethod: 'Cash',
      checkingOut: false,
      amountGiven: null,

      customerSearch: '',
      customerResults: [],
      selectedCustomerId: null,
      creatingCustomer: false,
      newCustomer: {
        name: '',
        phone: '',
        email: '',
        password: '',
      },
    }
  },
  computed: {
    filteredProducts() {
      const s = String(this.search || '').toLowerCase()
      if (!s) return this.products
      return this.products.filter(p => String(p.product_name || '').toLowerCase().includes(s))
    },
    cartItems() {
      return this.cart?.items || []
    },
    cartTotal() {
      return this.cartItems.reduce((sum, it) => sum + (Number(it.total_price) || 0), 0)
    },
    changeAmount() {
      const given = Number(this.amountGiven)
      if (!this.requiresCashAmount) return 0
      if (!this.amountGiven && this.amountGiven !== 0) return 0
      if (Number.isNaN(given)) return 0
      if (given < this.cartTotal) return 0
      return given - this.cartTotal
    },
    requiresCashAmount() {
      return this.paymentMethod !== 'Credit'
    },
    creditWarning() {
      if (this.paymentMethod !== 'Credit') return null
      if (!this.selectedCustomerId) return 'Customer is required for Credit sales.'
      return null
    },
    amountGivenWarning() {
      if (!this.requiresCashAmount) return null
      const given = Number(this.amountGiven)
      if (!this.amountGiven && this.amountGiven !== 0) return null
      if (Number.isNaN(given)) return 'Enter a valid amount.'
      if (given < this.cartTotal) return `Amount given is less than total (R ${this.formatMoney(this.cartTotal)}).`
      return null
    },
    checkoutDisabled() {
      if (this.checkingOut || this.cartItems.length === 0) return true

      if (this.paymentMethod === 'Credit') return !this.selectedCustomerId

      const given = Number(this.amountGiven)
      if (!this.amountGiven && this.amountGiven !== 0) return true
      if (Number.isNaN(given)) return true
      return given < this.cartTotal
    }
  },
  mounted() {
    this.refresh()
  },
  methods: {
    formatMoney(v) {
      const n = Number(v)
      if (Number.isNaN(n)) return '0.00'
      return n.toFixed(2)
    },
    async refresh() {
      this.success = null
      this.error = null
      await Promise.all([this.fetchProducts(), this.fetchCart()])
    },
    async fetchProducts() {
      this.loadingProducts = true
      try {
        const res = await api.get('/shop/products')
        this.products = res.data?.data || []
        for (const p of this.products) {
          if (this.qtyById[p.id] == null) this.qtyById[p.id] = 1
        }
      } catch (e) {
        this.error = e?.response?.data?.message || e.message || 'Failed to load products'
      } finally {
        this.loadingProducts = false
      }
    },
    async fetchCart() {
      this.loadingCart = true
      try {
        const res = await api.get('/shop/pos/cart')
        this.cart = res.data?.data || null
        if (!this.cart || !this.cart.items || this.cart.items.length === 0) {
          this.amountGiven = null
        }
      } catch (e) {
        this.error = e?.response?.data?.message || e.message || 'Failed to load POS cart'
      } finally {
        this.loadingCart = false
      }
    },
    async addItem(p) {
      this.error = null
      this.success = null
      const qty = Number(this.qtyById[p.id] || 1)
      if (!qty || qty < 1) {
        this.error = 'Please enter a valid quantity.'
        return
      }
      if (Number(p.stock_level) < qty) {
        this.error = `Insufficient stock for ${p.product_name}.`
        return
      }
      try {
        const res = await api.post('/shop/pos/cart/items', { product_id: p.id, qty })
        this.cart = res.data?.data || null
        this.qtyById[p.id] = 1
      } catch (e) {
        this.error = e?.response?.data?.message || e.message || 'Failed to add item'
      }
    },
    async updateQty(item, qtyValue) {
      this.error = null
      const qty = Number(qtyValue)
      if (!qty || qty < 1) return
      const stock = Number(item.product?.stock_level)
      if (!Number.isNaN(stock) && stock >= 0 && qty > stock) {
        this.error = `Insufficient stock for ${item.product?.product_name || 'item'}.`
        return
      }
      try {
        const res = await api.put(`/shop/pos/cart/items/${item.id}`, { qty })
        this.cart = res.data?.data || null
      } catch (e) {
        this.error = e?.response?.data?.message || e.message || 'Failed to update'
      }
    },
    async remove(item) {
      this.error = null
      try {
        const res = await api.delete(`/shop/pos/cart/items/${item.id}`)
        this.cart = res.data?.data || null
      } catch (e) {
        this.error = e?.response?.data?.message || e.message || 'Failed to remove'
      }
    },
    async checkout() {
      this.error = null
      this.success = null

      if (this.cartItems.length <= 0) {
        this.error = 'Your order is empty.'
        return
      }

      if (this.paymentMethod === 'Credit') {
        if (!this.selectedCustomerId) {
          this.error = 'Please select or create a customer for Credit sales.'
          return
        }
      } else {
        if (this.amountGivenWarning) {
          this.error = this.amountGivenWarning
          return
        }
        if (!this.amountGiven && this.amountGiven !== 0) {
          this.error = 'Please enter the amount given.'
          return
        }
      }

      this.checkingOut = true
      try {
        const selectedCustomerId = this.selectedCustomerId ? Number(this.selectedCustomerId) : null
        const res = await api.post('/shop/pos/checkout', {
          customer_name: this.customerName || null,
          customer_id: this.paymentMethod === 'Credit' ? selectedCustomerId : null,
          payment_method: this.paymentMethod,
          amount_received: this.requiresCashAmount ? (this.amountGiven == null ? null : Number(this.amountGiven)) : null,
        })
        const saleId = res.data?.data?.id
        const isPaid = !!res.data?.data?.is_paid
        this.customerName = ''
        this.customerSearch = ''
        this.customerResults = []
        this.selectedCustomerId = ''
        this.amountGiven = null
        this.success = isPaid
          ? `POS checkout completed${saleId ? ` (Sale #${saleId})` : ''}.`
          : `Credit sale created${saleId ? ` (Sale #${saleId})` : ''}. Payment pending.`
        await this.fetchCart()
        await this.fetchProducts()
      } catch (e) {
        this.error = e?.response?.data?.message || e.message || 'Checkout failed'
      } finally {
        this.checkingOut = false
      }
    }
    ,
    async onCustomerSearch() {
      if (this.paymentMethod !== 'Credit') return
      const s = String(this.customerSearch || '').trim()
      if (!s) {
        this.customerResults = []
        return
      }

      try {
        const res = await api.get('/shop/customers', { params: { search: s } })
        this.customerResults = res.data?.data || []
      } catch (e) {
        this.error = e?.response?.data?.message || e.message || 'Failed to search customers'
      }
    },
    async createCustomer() {
      if (this.paymentMethod !== 'Credit') return

      const name = String(this.newCustomer.name || '').trim()
      const phone = String(this.newCustomer.phone || '').trim()
      const email = String(this.newCustomer.email || '').trim() || null
      const password = String(this.newCustomer.password || '')

      if (!name || !phone || !password) {
        this.error = 'Name, phone and password are required.'
        return
      }

      this.creatingCustomer = true
      this.error = null
      try {
        const res = await api.post('/shop/customers', { name, phone, email, password })
        const c = res.data?.data
        if (c?.id) {
          this.selectedCustomerId = c.id
          this.customerResults = [c, ...this.customerResults.filter(x => x.id !== c.id)]
          this.newCustomer = { name: '', phone: '', email: '', password: '' }
        }
      } catch (e) {
        this.error = e?.response?.data?.message || e.message || 'Failed to create customer'
      } finally {
        this.creatingCustomer = false
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
