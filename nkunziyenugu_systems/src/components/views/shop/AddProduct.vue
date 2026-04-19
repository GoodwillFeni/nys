 <template>
   <div v-if="isPrivileged" class="login-wrapper">
    <div class="login-container">
      <div class="login-right">
            <h2>{{ form.id ? 'Edit Product' : 'Add Product' }}</h2>
            <form @submit.prevent="saveProduct()">
            <div class="row">
                <div class="col-12 col-md-6">
                    <label class="form-label">Product Name</label>
                    <input class="form-control " v-model="form.product_name" type="text" placeholder="Enter product name" />
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label">Product Type</label>
                    <input class="form-control " v-model="form.product_type" type="text" />
                </div>
                <div class="col-12">
                    <label class="form-label">Description</label>
                    <textarea class="form-control " v-model="form.description" rows="2"></textarea>
                </div>

                <div class="col-12 col-md-3" v-if="this.form.id">
                    <label class="form-label">Stock level</label>
                    <input class="form-control " v-model.number="form.stock_level" type="number" min="0" step="1" @change="calPrice" />
                </div>

                <div class="col-12 col-md-3">
                    <label class="form-label">Quantity</label>
                    <input class="form-control " v-model.number="form.qty" type="number" min="0" step="1" @change="calPrice" />
                </div>

                <div class="col-12 col-md-3">
                    <label class="form-label">Stock Price</label>
                    <input class="form-control " v-model.number="form.stock_price" type="number" min="0" step="0.01" @change="calPrice" />
                </div>

                <div class="col-12 col-md-3">
                    <label class="form-label">Transport Percentage</label>
                    <input class="form-control " v-model.number="form.transport_percentage" type="number" step="0.01" @change="calPrice" />
                </div>

                <div class="col-12 col-md-3">
                    <label class="form-label">Price Percentage</label>
                    <input class="form-control " v-model.number="form.price_percentage" type="number" step="0.01" @change="calPrice" />
                </div>

                <div class="col-12 col-md-4">
                    <label class="form-label">Calculated Price No Profit</label>
                    <input class="form-control " v-model.number="form.cal_price_no_prof" type="number" step="0.01" readonly />
                </div>

                <div class="col-12 col-md-3">
                    <label class="form-label">Calculated Price</label>
                    <input class="form-control " v-model.number="form.cal_price" type="number" step="0.01" readonly />
                </div>
                                <div class="col-12 col-md-3">
                    <label class="form-label">Selling Price</label>
                    <input class="form-control " v-model.number="form.actual_price" type="number" min="0" step="0.01" @change="calPrice" />
                </div>

                <div class="col-12 col-md-3">
                    <label class="form-label">Profit/Item</label>
                    <input class="form-control " v-model.number="form.prof_per_product" type="number" step="0.01" readonly />
                </div>

                <div class="col-12 col-md-5">
                    <label class="form-label">Image</label>
                    <input class="form-control " type="file" accept="image/*" @change="onImgChange" />
                </div>

                <div class="col-12 d-flex gap-2">
                    <button type="button" class="button-success" :disabled="saving" @click="saveProduct">{{ saving ? 'Adding...' : 'Add Product' }}</button>
                    <button type="button" class="button-danger" @click="back()">Back</button>
                </div>
            </div>
        </form>
      </div>
    </div>
    </div>
</template>

<script>
import api from '../../../store/services/api'
import { useToast } from "vue-toastification";
const toast = useToast();
export default {
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
            stock_level: 0
        },
        }
    },

    computed: {
        isPrivileged() {
            return !!this.$store?.getters?.isPrivileged
        },
    },

    mounted(){
        const id = this.$route.params.id
        if(id){
            this.form.id = id
            this.fetchProduct(id)
        }
    },

    methods: {
        back() {
            this.$router.go(-1)
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

        //if edit product
        async fetchProduct(id) {
            try {
                this.loading = true
                const res = await api.get(`/shop/products/${id}`)
                console.log(res.data)
                const p = res.data.data

                this.form = {
                    id: p.id,
                    product_name: p.product_name || '',
                    product_type: p.product_type || '',
                    description: p.description || '',
                    actual_price: Number(p.actual_price) || 0,
                    stock_price: Number(p.stock_price) || 0,
                    stock_level: Number(p.stock_level) || 0,
                    qty: Number(p.qty) || 0,
                    price_percentage: Number(p.price_percentage) || 0,
                    transport_percentage: Number(p.transport_percentage) || 0,
                    cal_price_no_prof: Number(p.cal_price_no_prof) || 0,
                    cal_price: Number(p.cal_price) || 0,
                    prof_per_product: Number(p.prof_per_product) || 0,
                    img: null // don't preload file
                }

            } catch (e) {
                this.error = 'Failed to load product'
            } finally {
                this.loading = false
            }
        },

        async saveProduct() {
            if (!this.isPrivileged) return

            this.saving = true
            this.error = null

            //If edit add new qty to old qty
            if (this.form.id) {
                this.form.qty += this.form.stock_level
            }
            this.form.stock_level = this.form.qty
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
                this.$router.go(-1)
                toast.success('Product added / updated successfully!')
                await this.fetchProducts()
            } catch (e) {
                this.error = e?.response?.data?.message || e.message || 'Failed to save product'
            } finally {
                this.saving = false
            }
        },
    }
}
</script>
<style scoped>
/* SAME STYLING AS SIGNUP */
.login-wrapper {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 10px;
  background: linear-gradient(135deg, #27253f, #605a6d);
}

.login-container {
  width: 900px;
  max-width: 95%;
  height: 540px;
  background: #ffffff;
  display: flex;
  border-radius: 12px;
  overflow: hidden;
  box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
}

/* LEFT */
.login-left {
  flex: 1;
  background: linear-gradient(135deg, #27253f, #605a6d);
  color: #ffffff;
  padding: 50px;
  display: flex;
  flex-direction: column;
  justify-content: center;
}

.login-left h1 {
  font-size: 32px;
  margin-bottom: 15px;
}

.login-left p {
  line-height: 1.6;
  opacity: 0.9;
}

/* RIGHT */
.login-right {
  flex: 1;
  padding: 50px;
  display: flex;
  flex-direction: column;
  justify-content: center;
}

.login-right h2 {
  text-align: center;
  margin-bottom: 25px;
  color: #6a5cff;
}

/* INPUTS */
.input-group {
  margin-bottom: 15px;
}

.input-group input,
.input-group select {
  width: 100%;
  padding: 12px 15px;
  border-radius: 25px;
  border: 1px solid #ddd;
  outline: none;
  font-size: 14px;
}

.input-group input:focus,
.input-group select:focus {
  border-color: #6a5cff;
}

/* RESPONSIVE */
@media (max-width: 768px) {
  .login-container {
    flex-direction: column;
    height: auto;
  }

  .login-left {
    padding: 30px;
    text-align: center;
  }
}

/* BUTTON */
.col-6 {
  display: flex;
  justify-content: center;
}

label {
    color: #6a5cff;
}
</style>