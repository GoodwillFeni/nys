<template>
  <div>
    <!-- Header -->
    <div class="card p-3 mb-3">
      <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
        <h4 class="m-0">Animal Events</h4>
        <div class="d-flex align-items-center gap-2 flex-wrap">
          <input
            class="form-control form-control-sm"
            style="width: 180px"
            type="text"
            placeholder="Search tag or name"
            v-model="filters.search"
            @input="loadEvents"
          />

          <select v-model="filters.farm_id" class="form-control-sm" @change="onFarmChange">
            <option value="">All Farms</option>
            <option v-for="f in farms" :key="f.id" :value="f.id">{{ f.name }}</option>
          </select>

          <select v-model="filters.animal_type" class="form-control-sm" @change="loadEvents">
            <option value="">All Animal Types</option>
            <option v-for="t in animalTypes" :key="t.id" :value="t.id">{{ t.name }}</option>
          </select>

          <select v-model="filters.cost_type" class="form-control-sm" @change="loadEvents">
            <option value="">All Cost Types</option>
            <option value="expense">Expense</option>
            <option value="running">Running</option>
            <option value="income">Income</option>
            <option value="loss">Loss</option>
            <option value="birth">Birth</option>
            <option value="investment">Investment</option>
          </select>

          <button type="button" class="button-info" @click="$router.push({ name: 'AddAnimalEvent', params: filters.animal_id ? { id: filters.animal_id } : {} })">
            Add Event
          </button>
          <button type="button" class="button-warning" @click="$router.back()">Back</button>
        </div>
      </div>
    </div>

    <!-- Summary Cards -->
    <div class="summary-row" v-if="summary">
      <div class="summary-card income">
        <span class="label">Income</span>
        <span class="value">R{{ formatCost(summary.income) }}</span>
      </div>
      <div class="summary-card birth">
        <span class="label">Birth Value</span>
        <span class="value">R{{ formatCost(summary.birth) }}</span>
      </div>
      <div class="summary-card expense">
        <span class="label">Expense</span>
        <span class="value">R{{ formatCost(summary.expense) }}</span>
      </div>
      <div class="summary-card running">
        <span class="label">Running</span>
        <span class="value">R{{ formatCost(summary.running) }}</span>
      </div>
      <div class="summary-card loss">
        <span class="label">Loss</span>
        <span class="value">R{{ formatCost(summary.loss) }}</span>
      </div>
      <div class="summary-card investment">
        <span class="label">Investment</span>
        <span class="value">R{{ formatCost(summary.investment) }}</span>
      </div>
      <div class="summary-card total">
        <span class="label">Events</span>
        <span class="value">{{ summary.total_events }}</span>
      </div>
    </div>

    <!-- Events Table -->
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Date</th>
          <th>Farm</th>
          <th>Animal Tag</th>
          <th>Event Type</th>
          <th>Cost</th>
          <th>Cost Type</th>
          <th>Notes</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="(event, index) in events" :key="event.id">
          <td>{{ index + 1 }}</td>
          <td>{{ formatDate(event.event_date) }}</td>
          <td>{{ event.farm?.name || '-' }}</td>
          <td>{{ event.animal?.animal_tag || '-' }}</td>
          <td>{{ event.event_type }}</td>
          <td>R{{ formatCost(event.cost) }}</td>
          <td>
            <span :class="'badge badge-' + event.cost_type">{{ event.cost_type }}</span>
          </td>
          <td>{{ event.meta?.notes || '-' }}</td>
          <td>
            <button class="button-warning btn-sm" @click="editEvent(event)">
              <i class="bi bi-pencil-square"></i>
            </button>
            <button class="button-danger btn-sm" @click="deleteEvent(event)">
              <i class="bi bi-trash"></i>
            </button>
          </td>
        </tr>
        <tr v-if="events.length === 0">
          <td colspan="9" class="text-center">No events found</td>
        </tr>
      </tbody>
    </table>

    <!-- Edit Modal -->
    <div class="modal-overlay" v-if="showEditModal" @click.self="showEditModal = false">
      <div class="modal-box">
        <h3>Edit Event</h3>
        <form @submit.prevent="saveEdit">
          <div class="input-group">
            <input v-model="editForm.event_type" placeholder="Event Type" required />
          </div>
          <div class="row">
            <div class="input-group col">
              <input type="date" v-model="editForm.event_date" required />
            </div>
            <div class="input-group col">
              <input type="number" v-model.number="editForm.cost" placeholder="Cost" />
            </div>
          </div>
          <div class="input-group">
            <select v-model="editForm.cost_type">
              <option value="expense">Expense</option>
              <option value="running">Running</option>
              <option value="income">Income</option>
              <option value="loss">Loss</option>
              <option value="birth">Birth</option>
              <option value="investment">Investment</option>
            </select>
          </div>
          <div class="input-group">
            <textarea v-model="editForm.notes" placeholder="Notes"></textarea>
          </div>
          <div class="d-flex gap-2">
            <button type="submit" class="button-info" :disabled="saving">
              {{ saving ? 'Saving...' : 'Save' }}
            </button>
            <button type="button" class="button-warning" @click="showEditModal = false">Cancel</button>
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
  name: "AnimalEventList",

  data() {
    return {
      events: [],
      farms: [],
      animalTypes: [],
      summary: null,
      showEditModal: false,
      saving: false,
      editForm: {
        id: null,
        event_type: '',
        event_date: '',
        cost: 0,
        cost_type: 'expense',
        notes: '',
      },
      filters: {
        search: '',
        farm_id: '',
        animal_id: '',
        animal_type: '',
        cost_type: '',
      }
    }
  },

  mounted() {
    if (this.$route.query.farm_id) {
      this.filters.farm_id = this.$route.query.farm_id;
    }

    this.loadFarms();
    this.loadAnimalTypes();
    this.loadEvents();
  },

  methods: {
    async loadFarms() {
      try {
        const res = await api.get('/farm/farms');
        this.farms = res.data || [];
      } catch (e) {
        toast.error('Failed to load farms');
      }
    },

    async loadAnimalTypes() {
      try {
        const res = await api.get('/farm/animals/types');
        this.animalTypes = res.data.data || [];
      } catch (e) {
        toast.error('Failed to load animal types');
      }
    },

    onFarmChange() {
      this.loadEvents();
    },

    async loadEvents() {
      try {
        const params = {};
        if (this.filters.farm_id) params.farm_id = this.filters.farm_id;
        if (this.filters.animal_type) params.animal_type_id = this.filters.animal_type;
        if (this.filters.cost_type) params.cost_type = this.filters.cost_type;
        if (this.filters.search) params.search = this.filters.search;

        const res = await api.get('/animal-events/list', { params });
        this.events = res.data.events?.data || [];
        this.summary = res.data.summary || null;
      } catch (e) {
        toast.error('Failed to load events');
      }
    },

    editEvent(event) {
      this.editForm.id = event.id;
      this.editForm.event_type = event.event_type;
      this.editForm.event_date = event.event_date?.split('T')[0] || '';
      this.editForm.cost = event.cost;
      this.editForm.cost_type = event.cost_type;
      this.editForm.notes = event.meta?.notes || '';
      this.showEditModal = true;
    },

    async saveEdit() {
      try {
        this.saving = true;
        await api.put(`/animal-events/${this.editForm.id}`, {
          event_type: this.editForm.event_type,
          event_date: this.editForm.event_date,
          cost: this.editForm.cost,
          cost_type: this.editForm.cost_type,
          meta: { notes: this.editForm.notes },
        });
        toast.success('Event updated');
        this.showEditModal = false;
        this.loadEvents();
      } catch (e) {
        toast.error(e.response?.data?.message || 'Failed to update event');
      } finally {
        this.saving = false;
      }
    },

    async deleteEvent(event) {
      if (!confirm(`Delete "${event.event_type}" event?`)) return;
      try {
        await api.delete(`/animal-events/${event.id}`);
        toast.success('Event deleted');
        this.loadEvents();
      } catch (e) {
        toast.error(e.response?.data?.message || 'Failed to delete event');
      }
    },

    formatDate(date) {
      if (!date) return '-';
      return new Date(date).toLocaleDateString('en-ZA');
    },

    formatCost(val) {
      return parseFloat(val || 0).toFixed(2);
    }
  },

  watch: {
    '$route.query'() {
      if (this.$route.query.farm_id) {
        this.filters.farm_id = this.$route.query.farm_id;
      }
      if (this.$route.query.search) {
        this.filters.search = this.$route.query.search;
      }
      this.loadEvents();
    }
  }
}
</script>

<style scoped>
.card {
  background: linear-gradient(135deg, #27253f, #605a6d);
  color: #fff;
  border-radius: 8px;
}

.summary-row {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
  gap: 10px;
  margin-bottom: 16px;
}

.summary-card {
  background: #fff;
  border-radius: 8px;
  padding: 14px;
  box-shadow: 0 2px 6px rgba(0,0,0,0.1);
  display: flex;
  flex-direction: column;
  align-items: center;
}

.summary-card .label {
  font-size: 12px;
  color: #888;
  text-transform: uppercase;
}

.summary-card .value {
  font-size: 18px;
  font-weight: bold;
}

.summary-card.income .value { color: #2e7d32; }
.summary-card.birth .value { color: #1565c0; }
.summary-card.expense .value { color: #c62828; }
.summary-card.running .value { color: #e65100; }
.summary-card.loss .value { color: #b71c1c; }
.summary-card.investment .value { color: #6a1b9a; }
.summary-card.total .value { color: #333; }

.badge {
  padding: 3px 8px;
  border-radius: 12px;
  font-size: 12px;
  color: #fff;
}

.badge-income { background: #2e7d32; }
.badge-birth { background: #1565c0; }
.badge-expense { background: #c62828; }
.badge-running { background: #e65100; }
.badge-loss { background: #b71c1c; }
.badge-investment { background: #6a1b9a; }

.text-center { text-align: center; }

.btn-sm { padding: 4px 8px; font-size: 12px; }

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

.modal-box h3 {
  margin-bottom: 20px;
  color: #6a5cff;
  text-align: center;
}

.modal-box .input-group {
  margin-bottom: 12px;
}

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
.modal-box select:focus {
  border-color: #6a5cff;
}
</style>
