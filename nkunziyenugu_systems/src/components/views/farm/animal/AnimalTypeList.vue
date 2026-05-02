<template>
  <div>
    <!-- Header -->
    <div class="card p-3 mb-3">
      <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
        <h4 class="m-0">Animal Types</h4>
        <div class="d-flex align-items-center gap-2 flex-wrap">
          <input
            class="form-control form-control-sm"
            style="width: 220px"
            type="text"
            placeholder="Search by name"
            v-model="search"
          />
          <button type="button" class="button-info" @click="$router.push({ name: 'AddAnimalType' })">
            Add Type
          </button>
          <button type="button" class="button-warning" @click="$router.back()">Back</button>
        </div>
      </div>
    </div>

    <!-- Table -->
    <table v-if="filteredTypes.length > 0">
      <thead>
        <tr>
          <th>#</th>
          <th>Name</th>
          <th>Description</th>
          <th class="num-col">Default Birth Value</th>
          <th class="action-col">Actions</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="(t, index) in filteredTypes" :key="t.id">
          <td>{{ index + 1 }}</td>
          <td>{{ t.name }}</td>
          <td>{{ t.description || '—' }}</td>
          <td class="num-col">R {{ formatMoney(t.default_birth_value) }}</td>
          <td class="action-cell">
            <button class="button-info button-sm" @click="$router.push({ name: 'EditAnimalType', params: { id: t.id } })">
              <i class="bi bi-pencil-square"></i>
            </button>
            <button class="button-danger button-sm" @click="confirmDelete(t)">
              <i class="bi bi-trash"></i>
            </button>
          </td>
        </tr>
      </tbody>
    </table>
    <p v-else-if="!loading" class="empty">No animal types yet — click "Add Type" to create one.</p>
    <p v-else class="empty">Loading…</p>
  </div>
</template>

<script>
import api from '@/store/services/api';
import { useToast } from "vue-toastification";
const toast = useToast();

export default {
  name: 'AnimalTypeList',

  data() {
    return {
      types: [],
      loading: false,
      search: '',
    };
  },

  computed: {
    filteredTypes() {
      const q = this.search.trim().toLowerCase();
      if (!q) return this.types;
      return this.types.filter(t => (t.name || '').toLowerCase().includes(q));
    },
  },

  mounted() {
    this.load();
  },

  methods: {
    async load() {
      this.loading = true;
      try {
        const res = await api.get('/farm/animals/types');
        this.types = res.data?.data || [];
      } catch (e) {
        toast.error(e.response?.data?.message || 'Failed to load animal types');
      } finally {
        this.loading = false;
      }
    },

    formatMoney(v) {
      return parseFloat(v || 0).toFixed(2);
    },

    async confirmDelete(t) {
      if (!confirm(`Delete animal type "${t.name}"? This cannot be undone.`)) return;
      try {
        await api.delete(`/farm/animals/types/${t.id}`);
        toast.success('Animal type deleted');
        this.load();
      } catch (e) {
        toast.error(e.response?.data?.message || 'Failed to delete');
      }
    },
  },
};
</script>

<style scoped>
/* Header card uses the same gradient as the page so it blends in cleanly,
   not the global solid white card. */
.card {
  background: linear-gradient(135deg, #27253f, #605a6d) !important;
  color: #fff;
  border: 1px solid rgba(255, 255, 255, 0.08);
}
.card h4 { color: #fff; }
.empty {
  color: rgba(255,255,255,0.7);
  font-style: italic;
  padding: 16px;
  text-align: center;
}
.num-col { text-align: right; }
.action-col { width: 120px; }
.action-cell { white-space: nowrap; }
</style>
