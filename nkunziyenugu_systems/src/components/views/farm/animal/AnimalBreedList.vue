<template>
  <div>
    <!-- Header -->
    <div class="card p-3 mb-3">
      <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
        <h4 class="m-0">Animal Breeds</h4>
        <div class="d-flex align-items-center gap-2 flex-wrap">
          <input
            class="form-control form-control-sm"
            style="width: 220px"
            type="text"
            placeholder="Search by name"
            v-model="search"
          />
          <select v-model="typeFilter" class="form-control-sm" style="width: 180px">
            <option value="">All Types</option>
            <option v-for="t in types" :key="t.id" :value="t.id">{{ t.name }}</option>
          </select>
          <button type="button" class="button-info" @click="$router.push({ name: 'AddAnimalBreed' })">
            Add Breed
          </button>
          <button type="button" class="button-warning" @click="$router.back()">Back</button>
        </div>
      </div>
    </div>

    <!-- Table -->
    <table v-if="filteredBreeds.length > 0">
      <thead>
        <tr>
          <th>#</th>
          <th>Breed Name</th>
          <th>Type</th>
          <th>Description</th>
          <th class="action-col">Actions</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="(b, index) in filteredBreeds" :key="b.id">
          <td>{{ index + 1 }}</td>
          <td>{{ b.breed_name }}</td>
          <td>{{ typeName(b.animal_type_id) }}</td>
          <td>{{ b.description || '—' }}</td>
          <td class="action-cell">
            <button class="button-info button-sm" @click="$router.push({ name: 'EditAnimalBreed', params: { id: b.id } })">
              <i class="bi bi-pencil-square"></i>
            </button>
            <button class="button-danger button-sm" @click="confirmDelete(b)">
              <i class="bi bi-trash"></i>
            </button>
          </td>
        </tr>
      </tbody>
    </table>
    <p v-else-if="!loading" class="empty">No breeds yet — click "Add Breed" to create one.</p>
    <p v-else class="empty">Loading…</p>
  </div>
</template>

<script>
import api from '@/store/services/api';
import { useToast } from "vue-toastification";
const toast = useToast();

export default {
  name: 'AnimalBreedList',

  data() {
    return {
      breeds: [],
      types: [],
      loading: false,
      search: '',
      typeFilter: '',
    };
  },

  computed: {
    filteredBreeds() {
      const q = this.search.trim().toLowerCase();
      return this.breeds.filter(b => {
        if (this.typeFilter && Number(b.animal_type_id) !== Number(this.typeFilter)) return false;
        if (q && !(b.breed_name || '').toLowerCase().includes(q)) return false;
        return true;
      });
    },
  },

  mounted() {
    this.load();
    this.loadTypes();
  },

  methods: {
    async load() {
      this.loading = true;
      try {
        const res = await api.get('/farm/animals/breeds');
        this.breeds = res.data || [];
      } catch (e) {
        toast.error(e.response?.data?.message || 'Failed to load breeds');
      } finally {
        this.loading = false;
      }
    },

    async loadTypes() {
      try {
        const res = await api.get('/farm/animals/types');
        this.types = res.data?.data || [];
      } catch (e) {
        // non-fatal
      }
    },

    typeName(id) {
      return this.types.find(t => Number(t.id) === Number(id))?.name ?? '—';
    },

    async confirmDelete(b) {
      if (!confirm(`Delete breed "${b.breed_name}"? This cannot be undone.`)) return;
      try {
        await api.delete(`/farm/animals/breeds/${b.id}`);
        toast.success('Breed deleted');
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
.action-col { width: 120px; }
.action-cell { white-space: nowrap; }
</style>
