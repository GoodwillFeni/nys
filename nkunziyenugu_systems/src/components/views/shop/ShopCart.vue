<template>
  <div class="shop-page">
    <div class="card p-3 mb-3">
      <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
        <h4 class="m-0">Cart</h4>
        <div class="d-flex align-items-center gap-2">
          <RouterLink class="button-info" to="/Shop/Products">Continue Shopping</RouterLink>
          <button class="button-success" :disabled="submitting || items.length === 0" @click="checkout">
            Checkout (R {{ formatMoney(cartTotal) }})
          </button>
        </div>
      </div>
      <div v-if="error" class="alert alert-danger mt-3 mb-0">{{ error }}</div>
      <div v-if="success" class="alert alert-success mt-3 mb-0">{{ success }}</div>
    </div>

    <div class="card p-3" v-if="items.length === 0">
      Your cart is empty.
    </div>

    <div v-else class="card p-3">
      <table class="table table-borderless mb-0">
        <thead>
          <tr>
            <th>Product</th>
            <th style="width:120px">Qty</th>
            <th style="width:140px">Unit</th>
            <th style="width:140px">Total</th>
            <th style="width:90px">Action</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(i, idx) in items" :key="i.product_id">
            <td>{{ i.product_name }}</td>
            <td>
              <input
                class="form-control form-control-sm"
                type="number"
                min="1"
                v-model.number="items[idx].qty"
                @change="persist"
              />
            </td>
            <td>R {{ formatMoney(i.unit_price) }}</td>
            <td>R {{ formatMoney(lineTotal(i)) }}</td>
            <td>
              <button class="button-danger" @click="removeItem(i.product_id)">Remove</button>
            </td>
          </tr>
        </tbody>
      </table>

      <div class="d-flex justify-content-end mt-3">
        <div class="fw-bold">Grand Total: R {{ formatMoney(cartTotal) }}</div>
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
    }
  },
  computed: {
    cartTotal() {
      return this.items.reduce((sum, i) => sum + this.lineTotal(i), 0)
    }
  },
  mounted() {
    this.loadCart()
  },
  methods: {
    formatMoney(v) {
      const n = Number(v)
      if (Number.isNaN(n)) return '0.00'
      return n.toFixed(2)
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
    },
    removeItem(productId) {
      this.items = this.items.filter(i => i.product_id !== productId)
      this.persist()
    },
    async checkout() {
      this.error = null
      this.success = null
      if (this.items.length === 0) return

      this.submitting = true
      try {
        const payload = {
          items: this.items.map(i => ({ product_id: i.product_id, qty: Number(i.qty) || 1 }))
        }
        const res = await api.post('/shop/orders', payload)
        this.items = []
        this.persist()
        this.success = `Order placed successfully. Order #${res.data?.data?.id || ''}`
      } catch (e) {
        this.error = e?.response?.data?.message || e.message || 'Checkout failed'
      } finally {
        this.submitting = false
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
