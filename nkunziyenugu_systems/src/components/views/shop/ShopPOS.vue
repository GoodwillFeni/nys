<template>
  <div class="shop-page">
    <div class="card p-3 mb-3">
      <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
        <h4 class="m-0">POS (Cashier)</h4>
        <div class="d-flex align-items-center gap-2">
          <button class="button-info" @click="refresh">Refresh</button>
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
                    <button class="button-success" @click="addItem(p)">Add</button>
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
                    <button class="button-danger" @click="remove(it)">X</button>
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
                <label class="form-label">Amount given (cash)</label>
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
              <div class="col-12 col-md-5 d-flex align-items-end justify-content-end">
                <div class="fw-bold">Change: R {{ formatMoney(changeAmount) }}</div>
              </div>
            </div>

            <div class="mt-3">
              <label class="form-label">Customer name (optional)</label>
              <input class="form-control form-control-sm" v-model="customerName" placeholder="Walk-in customer" />
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
      checkingOut: false,
      amountGiven: null,
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
      if (!this.amountGiven && this.amountGiven !== 0) return 0
      if (Number.isNaN(given)) return 0
      if (given < this.cartTotal) return 0
      return given - this.cartTotal
    },
    amountGivenWarning() {
      const given = Number(this.amountGiven)
      if (!this.amountGiven && this.amountGiven !== 0) return null
      if (Number.isNaN(given)) return 'Enter a valid amount.'
      if (given < this.cartTotal) return `Amount given is less than total (R ${this.formatMoney(this.cartTotal)}).`
      return null
    },
    checkoutDisabled() {
      if (this.checkingOut || this.cartItems.length === 0) return true
      const given = Number(this.amountGiven)
      if (!this.amountGiven && this.amountGiven !== 0) return false
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

      if (this.amountGivenWarning) {
        this.error = this.amountGivenWarning
        return
      }

      this.checkingOut = true
      try {
        const res = await api.post('/shop/pos/checkout', { customer_name: this.customerName || null })
        const saleId = res.data?.data?.id
        this.customerName = ''
        this.amountGiven = null
        this.success = `POS checkout completed${saleId ? ` (Sale #${saleId})` : ''}.`
        await this.fetchCart()
        await this.fetchProducts()
      } catch (e) {
        this.error = e?.response?.data?.message || e.message || 'Checkout failed'
      } finally {
        this.checkingOut = false
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
</style>
