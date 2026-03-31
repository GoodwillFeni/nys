<template>
  <div class="shop-page">
    <div class="card p-3 mb-3">
      <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
        <h4 class="m-0">Shop Products</h4>
        <div class="d-flex align-items-center gap-2">
          <input
            class="form-control form-control-sm"
            style="width: 220px"
            type="text"
            placeholder="Search product"
            v-model="search"
          />
          <RouterLink class="button-info" to="/Shop/Cart">Go to Cart ({{ cartCount }})</RouterLink>
        </div>
      </div>

      <div v-if="error" class="alert alert-danger mt-3 mb-0">
        {{ error }}
      </div>
    </div>

    <div v-if="loading" class="card p-3">Loading...</div>

    <div v-else class="row g-3">
      <div class="col-12 col-md-6 col-lg-4" v-for="p in filteredProducts" :key="p.id">
        <div class="card p-3 h-100">
          <div class="d-flex gap-3">
            <div class="thumb">
              <div class="thumb-inner" v-if="!p.img_path">No Image</div>
              <img v-else class="thumb-img" :src="productImageUrl(p.img_path)" alt="" />
            </div>

            <div class="flex-grow-1">
              <div class="d-flex justify-content-between align-items-start gap-2">
                <div>
                  <div class="fw-bold">{{ p.product_name }}</div>
                  <div class="text-muted small" v-if="p.product_type">{{ p.product_type }}</div>
                </div>
                <div class="fw-bold">R {{ formatMoney(p.actual_price) }}</div>
              </div>

              <div class="small mt-2" v-if="p.description">{{ p.description }}</div>

              <div class="d-flex align-items-center gap-2 mt-3">
                <input
                  class="form-control form-control-sm"
                  style="width: 90px"
                  type="number"
                  min="1"
                  v-model.number="qtyById[p.id]"
                />
                <button class="button-success" @click="addToCart(p)">
                  <i class="bi bi-cart-plus"></i>
                </button>

              </div>

              <div class="small mt-2" v-if="p.stock_level <= 0" style="color:#ffb3b3; font-weight:600">
                Out of stock
              </div>
              <div class="small mt-2" v-else>
                Avilable: {{ p.stock_level }}
              </div>
            </div>
          </div>
        </div>
      </div>

      <div v-if="filteredProducts.length === 0" class="col-12">
        <div class="card p-3">No products found.</div>
      </div>
    </div>
  </div>
</template>

<script>
import api from '@/store/services/api'

export default {
  name: 'ShopProducts',
  data() {
    return {
      loading: false,
      error: null,
      search: '',
      products: [],
      qtyById: {},
      showForm: false,
      saving: false,
      form: {
        id: null,
        product_name: '',
        product_type: '',
        description: '',
        actual_price: 0,
        stock_price: 0,
        qty: 0,
        price_percentage: 0,
        transport_percentage: 0,
        cal_price_no_prof: 0,
        cal_price: 0,
        prof_per_product: 0,
        img: null,
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
    filteredProducts() {
      const s = String(this.search || '').toLowerCase()
      if (!s) return this.products
      return this.products.filter(p => String(p.product_name || '').toLowerCase().includes(s))
    },
    cartCount() {
      const cart = this.getCart()
      return cart.items.reduce((sum, i) => sum + (Number(i.qty) || 0), 0)
    }
  },
  mounted() {
    this.fetchProducts()
  },

  methods: {
    formatMoney(v) {
      const n = Number(v)
      if (Number.isNaN(n)) return '0.00'
      return n.toFixed(2)
    },

    productImageUrl(imgPath) {
      const base = api.defaults.baseURL.replace(/\/api\/?$/, '')
      return `${base}/storage/${imgPath}`
    },
    getCart() {
      try {
        return JSON.parse(localStorage.getItem('shop_cart') || '{"items":[]}')
      } catch {
        return { items: [] }
      }
    },
    setCart(cart) {
      localStorage.setItem('shop_cart', JSON.stringify(cart))
    },
    addToCart(product) {
      if (Number(product.stock_level) <= 0) {
        this.error = `No stock available for ${product.product_name}`
        return
      }

      const qty = Number(this.qtyById[product.id] || 1)
      if (!qty || qty < 1) {
        this.error = 'Please enter a valid quantity.'
        return
      }

      const cart = this.getCart()
      const existing = cart.items.find(i => i.product_id === product.id)
      if (existing) {
        existing.qty = (Number(existing.qty) || 0) + qty
      } else {
        cart.items.push({
          product_id: product.id,
          product_name: product.product_name,
          unit_price: Number(product.actual_price) || 0,
          qty,
        })
      }
      this.setCart(cart)

      this.qtyById[product.id] = 1
      this.error = null
    },


    resetForm() {
      this.form = {
        id: null,
        product_name: '',
        product_type: '',
        description: '',
        actual_price: 0,
        stock_price: 0,
        qty: 0,
        price_percentage: 0,
        transport_percentage: 0,
        cal_price_no_prof: 0,
        cal_price: 0,
        prof_per_product: 0,
        img: null,
      }
    },

    openEdit(p) {
      this.form = {
        id: p.id,
        product_name: p.product_name || '',
        product_type: p.product_type || '',
        description: p.description || '',
        actual_price: Number(p.actual_price) || 0,
        stock_price: Number(p.stock_price) || 0,
        qty: Number(p.stock_level) || 0,
        price_percentage: Number(p.price_percentage) || 0,
        transport_percentage: Number(p.transport_percentage) || 0,
        cal_price_no_prof: Number(p.cal_price_no_prof) || 0,
        cal_price: Number(p.cal_price) || 0,
        prof_per_product: Number(p.prof_per_product) || 0,
        img: null,
      }
      this.showForm = true
      this.error = null
    },
    closeForm() {
      this.showForm = false
      this.resetForm()
    },

    async deleteProduct(id) {
      if (!this.isPrivileged) return

      const ok = window.confirm('Delete this product?')
      if (!ok) return

      this.saving = true
      this.error = null
      try {
        await api.delete(`/shop/products/${id}`)
        this.closeForm()
        await this.fetchProducts()
      } catch (e) {
        this.error = e?.response?.data?.message || e.message || 'Failed to delete product'
      } finally {
        this.saving = false
      }
    },
    async fetchProducts() {
      this.loading = true
      this.error = null
      try {
        const res = await api.get('/shop/products')
        this.products = res.data?.data || []
        for (const p of this.products) {
          if (this.qtyById[p.id] == null) this.qtyById[p.id] = 1
        }
      } catch (e) {
        this.error = e?.response?.data?.message || e.message || 'Failed to load products'
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

.thumb {
  width: 100px;
  height: 100px;
  border-radius: 10px;
  overflow: hidden;
  background: rgba(255, 255, 255, 0.08);
  flex: 0 0 auto;
}

.thumb-inner {
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 12px;
  color: rgba(255, 255, 255, 0.7);
}

.thumb-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}
</style>
