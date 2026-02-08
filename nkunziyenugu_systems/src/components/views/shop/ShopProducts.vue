<template>
  <div class="shop-page">
    <div class="card p-3 mb-3">
      <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
        <h4 class="m-0">Shop Products</h4>
        <div class="d-flex align-items-center gap-2">
          <button v-if="isPrivileged" class="button-success" @click="openCreate">Add Product</button>
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

    <div v-if="isPrivileged && showForm" class="card p-3 mb-3">
      <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div class="fw-bold">{{ form.id ? 'Edit Product' : 'Add Product' }}</div>
        <button class="button-danger" @click="closeForm">Close</button>
      </div>

      <div class="row g-2 mt-2">
        <div class="col-12 col-md-6">
          <label class="form-label">Product Name</label>
          <input class="form-control form-control-sm" v-model="form.product_name" type="text" />
        </div>
        <div class="col-12 col-md-6">
          <label class="form-label">Product Type</label>
          <input class="form-control form-control-sm" v-model="form.product_type" type="text" />
        </div>
        <div class="col-12">
          <label class="form-label">Description</label>
          <textarea class="form-control form-control-sm" v-model="form.description" rows="2"></textarea>
        </div>

        <div class="col-12 col-md-3">
          <label class="form-label">Selling Price</label>
          <input class="form-control form-control-sm" v-model.number="form.actual_price" type="number" min="0" step="0.01" @change="calPrice" />
        </div>
        <div class="col-12 col-md-3">
          <label class="form-label">Stock Price</label>
          <input class="form-control form-control-sm" v-model.number="form.stock_price" type="number" min="0" step="0.01" @change="calPrice" />
        </div>
        <div class="col-12 col-md-3">
          <label class="form-label">Quantity</label>
          <input class="form-control form-control-sm" v-model.number="form.qty" type="number" min="0" step="1" @change="calPrice" />
        </div>
        <div class="col-12 col-md-3">
          <label class="form-label">Image</label>
          <input class="form-control form-control-sm" type="file" accept="image/*" @change="onImgChange" />
        </div>

        <div class="col-12 col-md-3">
          <label class="form-label">Price Percentage</label>
          <input class="form-control form-control-sm" v-model.number="form.price_percentage" type="number" step="0.01" @change="calPrice" />
        </div>
        <div class="col-12 col-md-3">
          <label class="form-label">Transport Percentage</label>
          <input class="form-control form-control-sm" v-model.number="form.transport_percentage" type="number" step="0.01" @change="calPrice" />
        </div>
        <div class="col-12 col-md-3">
          <label class="form-label">Calculated Price No Profit</label>
          <input class="form-control form-control-sm" v-model.number="form.cal_price_no_prof" type="number" step="0.01" readonly />
        </div>
        <div class="col-12 col-md-3">
          <label class="form-label">Calculated Price</label>
          <input class="form-control form-control-sm" v-model.number="form.cal_price" type="number" step="0.01" readonly />
        </div>
        <div class="col-12 col-md-3">
          <label class="form-label">Profit/Item</label>
          <input class="form-control form-control-sm" v-model.number="form.prof_per_product" type="number" step="0.01" readonly />
        </div>

        <div class="col-12 d-flex gap-2 mt-1">
          <button class="button-success" :disabled="saving" @click="saveProduct">{{ saving ? 'Saving...' : 'Save' }}</button>
          <button v-if="form.id" class="button-danger" :disabled="saving" @click="deleteProduct(form.id)">Delete</button>
        </div>
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
                <button class="button-success" @click="addToCart(p)">Add</button>
                <button v-if="isPrivileged" class="button-info" @click="openEdit(p)">Edit</button>
              </div>

              <div class="small mt-2" v-if="p.stock_level <= 0" style="color:#ffb3b3; font-weight:600">
                Out of stock
              </div>
              <div class="small mt-2" v-else>
                Stock: {{ p.stock_level }}
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
    openCreate() {
      this.resetForm()
      this.showForm = true
      this.error = null
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
    onImgChange(e) {
      const f = e?.target?.files?.[0]
      this.form.img = f || null
    },
    buildProductFormData() {
      const fd = new FormData()
      fd.append('product_name', this.form.product_name)
      fd.append('product_type', this.form.product_type || '')
      fd.append('description', this.form.description || '')
      fd.append('actual_price', String(Number(this.form.actual_price) || 0))
      fd.append('stock_price', String(Number(this.form.stock_price) || 0))
      fd.append('cal_price_no_prof', String(Number(this.form.cal_price_no_prof) || 0))
      fd.append('cal_price', String(Number(this.form.cal_price) || 0))
      fd.append('prof_per_product', String(Number(this.form.prof_per_product) || 0))
      fd.append('qty', String(Number(this.form.qty) || 0))
      if (this.form.img) fd.append('img', this.form.img)
      return fd
    },
    percentage(num, per) {
      const n = Number(num)
      const p = Number(per)
      if (!n || Number.isNaN(n) || Number.isNaN(p)) return 0
      return (n / 100) * p
    },
    calPrice() {
      const stockPrice = Number(this.form.stock_price) || 0
      const qty = Number(this.form.qty) || 0
      const pricePerc = Number(this.form.price_percentage) || 0
      const transportPerc = Number(this.form.transport_percentage) || 0

      if (!qty || qty <= 0) {
        this.form.cal_price = 0
        this.form.cal_price_no_prof = 0
        this.form.prof_per_product = 0
        return
      }

      const percent = this.percentage(stockPrice, pricePerc)
      const calPriceNoProfExtra = this.percentage(stockPrice, transportPerc)

      const percentPrice = stockPrice + percent
      const calPrice = percentPrice / qty
      this.form.cal_price = Number(calPrice.toFixed(2))

      const calPriceNo = stockPrice + calPriceNoProfExtra
      const calNoProf = calPriceNo / qty
      this.form.cal_price_no_prof = Number(calNoProf.toFixed(2))

      const actualPrice = Number(this.form.actual_price) || 0
      this.form.prof_per_product = Number((actualPrice - this.form.cal_price_no_prof).toFixed(2))
    },
    async saveProduct() {
      if (!this.isPrivileged) return

      this.saving = true
      this.error = null
      try {
        this.calPrice()
        const fd = this.buildProductFormData()
        if (this.form.id) {
          fd.append('_method', 'PUT')
          await api.post(`/shop/products/${this.form.id}`, fd, {
            headers: { 'Content-Type': 'multipart/form-data' }
          })
        } else {
          await api.post('/shop/products', fd, {
            headers: { 'Content-Type': 'multipart/form-data' }
          })
        }

        this.closeForm()
        await this.fetchProducts()
      } catch (e) {
        this.error = e?.response?.data?.message || e.message || 'Failed to save product'
      } finally {
        this.saving = false
      }
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
