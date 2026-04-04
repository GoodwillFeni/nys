<template>
  <div>
    <!-- Header -->
    <div class="card p-3 mb-3">
      <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
        <h4 class="m-0">Farm Inventory</h4>
        <div class="d-flex align-items-center gap-2 flex-wrap">
          <select v-model="filters.farm_id" class="form-control-sm" @change="loadAll">
            <option value="">All Farms</option>
            <option v-for="f in farms" :key="f.id" :value="f.id">{{ f.name }}</option>
          </select>
          <select v-model="filters.category" class="form-control-sm" @change="loadItems">
            <option value="">All Categories</option>
            <option value="feed">Feed</option>
            <option value="vaccine">Vaccine</option>
            <option value="medicine">Medicine</option>
            <option value="supplement">Supplement</option>
            <option value="equipment">Equipment</option>
            <option value="other">Other</option>
          </select>
          <button class="button-info" @click="openAddItem">Add Item</button>
          <button class="button-info" @click="openPurchase">Record Purchase</button>
          <button class="button-warning" @click="openIssue">Record Issue</button>
        </div>
      </div>

      <!-- Tabs -->
      <div class="tabs mt-3">
        <button :class="['tab', activeTab === 'items' && 'active']" @click="activeTab = 'items'">Items</button>
        <button :class="['tab', activeTab === 'movements' && 'active']" @click="activeTab = 'movements'; loadMovements()">Movements</button>
      </div>
    </div>

    <!-- ITEMS TAB -->
    <div v-if="activeTab === 'items'">
      <table>
        <thead>
          <tr>
            <th>#</th>
            <th>Name</th>
            <th>Category</th>
            <th>Farm</th>
            <th>Unit</th>
            <th>Stock</th>
            <th>Reorder Level</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(item, i) in items" :key="item.id" :class="{ 'low-stock-row': item.low_stock }">
            <td>{{ i + 1 }}</td>
            <td>{{ item.name }}</td>
            <td><span class="badge badge-cat">{{ item.category }}</span></td>
            <td>{{ item.farm?.name || '-' }}</td>
            <td>{{ item.unit }}</td>
            <td>{{ item.current_stock }}</td>
            <td>{{ item.reorder_level || '-' }}</td>
            <td>
              <span v-if="item.low_stock" class="badge badge-loss">Low</span>
              <span v-else class="badge badge-income">OK</span>
            </td>
            <td>
              <button class="button-warning btn-sm" @click="openEditItem(item)">
                <i class="bi bi-pencil-square"></i>
              </button>
              <button class="button-danger btn-sm" @click="deleteItem(item)">
                <i class="bi bi-trash"></i>
              </button>
            </td>
          </tr>
          <tr v-if="items.length === 0">
            <td colspan="9" class="text-center">No inventory items</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- MOVEMENTS TAB -->
    <div v-if="activeTab === 'movements'">
      <div class="d-flex gap-2 mb-3 flex-wrap">
        <select v-model="movFilters.item_id" class="form-control-sm" @change="loadMovements">
          <option value="">All Items</option>
          <option v-for="it in items" :key="it.id" :value="it.id">{{ it.name }}</option>
        </select>
        <select v-model="movFilters.type" class="form-control-sm" @change="loadMovements">
          <option value="">All Types</option>
          <option value="purchase">Purchase</option>
          <option value="issue">Issue</option>
          <option value="adjustment">Adjustment</option>
        </select>
      </div>

      <table>
        <thead>
          <tr>
            <th>#</th>
            <th>Date</th>
            <th>Item</th>
            <th>Type</th>
            <th>Qty</th>
            <th>Unit Cost</th>
            <th>Total</th>
            <th>Animal</th>
            <th>Notes</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(m, i) in movements" :key="m.id">
            <td>{{ i + 1 }}</td>
            <td>{{ formatDate(m.movement_date) }}</td>
            <td>{{ m.item?.name || '-' }}</td>
            <td><span :class="'badge badge-' + m.movement_type">{{ m.movement_type }}</span></td>
            <td>{{ m.qty }} {{ m.item?.unit || '' }}</td>
            <td>R{{ formatCost(m.unit_cost) }}</td>
            <td>R{{ formatCost(m.total_cost) }}</td>
            <td>{{ m.animal?.animal_tag || '-' }}</td>
            <td>{{ m.notes || '-' }}</td>
          </tr>
          <tr v-if="movements.length === 0">
            <td colspan="9" class="text-center">No movements</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- ADD/EDIT ITEM MODAL -->
    <div class="modal-overlay" v-if="showItemModal" @click.self="showItemModal = false">
      <div class="modal-box">
        <h3>{{ itemForm.id ? 'Edit' : 'Add' }} Inventory Item</h3>
        <form @submit.prevent="saveItem">
          <div class="input-group">
            <select v-model="itemForm.farm_id" required>
              <option value="" disabled>Select Farm</option>
              <option v-for="f in farms" :key="f.id" :value="f.id">{{ f.name }}</option>
            </select>
          </div>
          <div class="input-group">
            <input v-model="itemForm.name" placeholder="Item name (e.g. Cattle Feed)" required />
          </div>
          <div class="row">
            <div class="input-group col">
              <select v-model="itemForm.category" required>
                <option value="" disabled>Category</option>
                <option value="feed">Feed</option>
                <option value="vaccine">Vaccine</option>
                <option value="medicine">Medicine</option>
                <option value="supplement">Supplement</option>
                <option value="equipment">Equipment</option>
                <option value="other">Other</option>
              </select>
            </div>
            <div class="input-group col">
              <input v-model="itemForm.unit" placeholder="Unit (kg, ml, dose, bag)" required />
            </div>
          </div>
          <div class="input-group">
            <input type="number" v-model.number="itemForm.reorder_level" placeholder="Reorder level (optional)" />
          </div>
          <div class="d-flex gap-2">
            <button type="submit" class="button-info" :disabled="saving">{{ saving ? 'Saving...' : 'Save' }}</button>
            <button type="button" class="button-warning" @click="showItemModal = false">Cancel</button>
          </div>
        </form>
      </div>
    </div>

    <!-- PURCHASE / ISSUE MODAL -->
    <div class="modal-overlay" v-if="showMovModal" @click.self="showMovModal = false">
      <div class="modal-box">
        <h3>{{ movForm.movement_type === 'purchase' ? 'Record Purchase' : 'Record Issue' }}</h3>
        <form @submit.prevent="saveMovement">
          <div class="input-group">
            <select v-model="movForm.farm_id" required @change="loadAnimals">
              <option value="" disabled>Select Farm</option>
              <option v-for="f in farms" :key="f.id" :value="f.id">{{ f.name }}</option>
            </select>
          </div>
          <div class="input-group">
            <select v-model="movForm.inventory_item_id" required>
              <option value="" disabled>Select Item</option>
              <option v-for="it in items" :key="it.id" :value="it.id">
                {{ it.name }} ({{ it.current_stock }} {{ it.unit }})
              </option>
            </select>
          </div>
          <div class="row">
            <div class="input-group col">
              <input type="number" v-model.number="movForm.qty" placeholder="Quantity" required min="0.01" step="0.01" />
            </div>
            <div class="input-group col" v-if="movForm.movement_type === 'purchase'">
              <input type="number" v-model.number="movForm.unit_cost" placeholder="Unit cost (R)" required min="0" step="0.01" />
            </div>
          </div>
          <div class="input-group" v-if="movForm.movement_type === 'issue'">
            <select v-model="movForm.animal_id">
              <option value="">No specific animal</option>
              <option v-for="a in animals" :key="a.id" :value="a.id">
                {{ a.animal_tag }} - {{ a.animal_name || '' }}
              </option>
            </select>
          </div>
          <div class="input-group">
            <textarea v-model="movForm.notes" placeholder="Notes (optional)"></textarea>
          </div>
          <div class="d-flex gap-2">
            <button type="submit" class="button-info" :disabled="saving">{{ saving ? 'Saving...' : 'Save' }}</button>
            <button type="button" class="button-warning" @click="showMovModal = false">Cancel</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script>
import api from '@/store/services/api';
import { useToast } from "vue-toastification";
const toast = useToast();

export default {
  name: "InventoryView",

  data() {
    return {
      activeTab: 'items',
      saving: false,
      items: [],
      movements: [],
      farms: [],
      animals: [],
      filters: { farm_id: '', category: '' },
      movFilters: { item_id: '', type: '' },

      showItemModal: false,
      itemForm: { id: null, farm_id: '', name: '', category: '', unit: '', reorder_level: null },

      showMovModal: false,
      movForm: { farm_id: '', inventory_item_id: '', movement_type: 'purchase', qty: null, unit_cost: null, animal_id: '', notes: '' },
    }
  },

  mounted() {
    this.loadFarms();
    this.loadItems();
  },

  methods: {
    async loadFarms() {
      try {
        const res = await api.get('/farm/farms');
        this.farms = res.data || [];
      } catch (e) { toast.error('Failed to load farms'); }
    },

    async loadItems() {
      try {
        const params = {};
        if (this.filters.farm_id) params.farm_id = this.filters.farm_id;
        if (this.filters.category) params.category = this.filters.category;
        const res = await api.get('/farm/inventory/items', { params });
        this.items = res.data || [];
      } catch (e) { toast.error('Failed to load items'); }
    },

    async loadMovements() {
      try {
        const params = {};
        if (this.filters.farm_id) params.farm_id = this.filters.farm_id;
        if (this.movFilters.item_id) params.inventory_item_id = this.movFilters.item_id;
        if (this.movFilters.type) params.movement_type = this.movFilters.type;
        const res = await api.get('/farm/inventory/movements', { params });
        this.movements = res.data.data || [];
      } catch (e) { toast.error('Failed to load movements'); }
    },

    async loadAnimals() {
      try {
        const params = {};
        if (this.movForm.farm_id) params.farm_id = this.movForm.farm_id;
        const res = await api.get('/farm/animals', { params });
        this.animals = res.data.data || [];
      } catch (e) { toast.error('Failed to load animals'); }
    },

    loadAll() {
      this.loadItems();
      if (this.activeTab === 'movements') this.loadMovements();
    },

    // ── Item CRUD ──
    openAddItem() {
      this.itemForm = { id: null, farm_id: '', name: '', category: '', unit: '', reorder_level: null };
      this.showItemModal = true;
    },

    openEditItem(item) {
      this.itemForm = {
        id: item.id,
        farm_id: item.farm_id,
        name: item.name,
        category: item.category,
        unit: item.unit,
        reorder_level: item.reorder_level,
      };
      this.showItemModal = true;
    },

    async saveItem() {
      try {
        this.saving = true;
        if (this.itemForm.id) {
          await api.put(`/farm/inventory/items/${this.itemForm.id}`, this.itemForm);
          toast.success('Item updated');
        } else {
          await api.post('/farm/inventory/items', this.itemForm);
          toast.success('Item added');
        }
        this.showItemModal = false;
        this.loadItems();
      } catch (e) {
        toast.error(e.response?.data?.message || 'Failed to save item');
      } finally { this.saving = false; }
    },

    async deleteItem(item) {
      if (!confirm(`Delete "${item.name}"?`)) return;
      try {
        await api.delete(`/farm/inventory/items/${item.id}`);
        toast.success('Item deleted');
        this.loadItems();
      } catch (e) { toast.error('Failed to delete item'); }
    },

    // ── Movements ──
    openPurchase() {
      this.movForm = { farm_id: this.filters.farm_id || '', inventory_item_id: '', movement_type: 'purchase', qty: null, unit_cost: null, animal_id: '', notes: '' };
      this.showMovModal = true;
    },

    openIssue() {
      this.movForm = { farm_id: this.filters.farm_id || '', inventory_item_id: '', movement_type: 'issue', qty: null, unit_cost: null, animal_id: '', notes: '' };
      if (this.movForm.farm_id) this.loadAnimals();
      this.showMovModal = true;
    },

    async saveMovement() {
      try {
        this.saving = true;
        await api.post('/farm/inventory/movements', this.movForm);
        toast.success(this.movForm.movement_type === 'purchase' ? 'Purchase recorded' : 'Issue recorded');
        this.showMovModal = false;
        this.loadItems();
        if (this.activeTab === 'movements') this.loadMovements();
      } catch (e) {
        toast.error(e.response?.data?.message || 'Failed to save movement');
      } finally { this.saving = false; }
    },

    formatDate(d) { return d ? new Date(d).toLocaleDateString('en-ZA') : '-'; },
    formatCost(v) { return parseFloat(v || 0).toFixed(2); },
  }
}
</script>

<style scoped>
.card {
  background: linear-gradient(135deg, #27253f, #605a6d);
  color: #fff;
  border-radius: 8px;
}

.tabs { display: flex; gap: 8px; }
.tab {
  padding: 6px 16px;
  border-radius: 20px;
  border: 1px solid rgba(255,255,255,0.3);
  background: transparent;
  color: #fff;
  cursor: pointer;
}
.tab.active {
  background: #6a5cff;
  border-color: #6a5cff;
}

.low-stock-row { background: #fff3e0; }

.badge { padding: 3px 8px; border-radius: 12px; font-size: 12px; color: #fff; }
.badge-cat { background: #546e7a; }
.badge-income { background: #2e7d32; }
.badge-loss { background: #c62828; }
.badge-purchase { background: #2e7d32; }
.badge-issue { background: #e65100; }
.badge-adjustment { background: #1565c0; }

.btn-sm { padding: 4px 8px; font-size: 12px; }
.text-center { text-align: center; }

.modal-overlay {
  position: fixed;
  top: 0; left: 0; right: 0; bottom: 0;
  background: rgba(0,0,0,0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
}

.modal-box {
  background: #fff;
  border-radius: 12px;
  padding: 30px;
  width: 500px;
  max-width: 95%;
  box-shadow: 0 20px 40px rgba(0,0,0,0.3);
}

.modal-box h3 { margin-bottom: 20px; color: #6a5cff; text-align: center; }
.modal-box .input-group { margin-bottom: 12px; }

.modal-box input,
.modal-box select,
.modal-box textarea {
  width: 100%;
  padding: 10px 14px;
  border-radius: 20px;
  border: 1px solid #ddd;
  outline: none;
  font-size: 14px;
  color: #444;
}

.modal-box input:focus,
.modal-box select:focus { border-color: #6a5cff; }
</style>
