<template>
  <div class="login-wrapper">
    <div class="login-container">
      <div class="login-right">
        <h2>Edit Animal Breed</h2>
        <form @submit.prevent="submit">
          <div class="input-group">
            <select v-model="form.animal_type_id" required>
              <option value="" disabled>Select Animal Type</option>
              <option v-for="t in types" :key="t.id" :value="t.id">{{ t.name }}</option>
            </select>
          </div>

          <div class="input-group">
            <input type="text" v-model="form.breed_name" placeholder="Breed name" required />
          </div>

          <div class="input-group">
            <input type="text" v-model="form.description" placeholder="Description" />
          </div>

          <div class="row">
            <div class="col-3">
              <button type="submit" :disabled="loading" class="button-info">
                {{ loading ? 'Saving…' : 'Save' }}
              </button>
            </div>
            <div class="col-2">
              <button type="button" @click="$router.back()" class="button-warning">Back</button>
            </div>
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
  name: 'EditAnimalBreed',
  props: { id: { type: [String, Number], required: true } },
  data() {
    return {
      loading: false,
      types: [],
      form: { animal_type_id: '', breed_name: '', description: '' },
    };
  },
  async mounted() {
    await this.loadTypes();
    await this.load();
  },
  methods: {
    async loadTypes() {
      try {
        const res = await api.get('/farm/animals/types');
        this.types = res.data?.data || [];
      } catch (e) { /* non-fatal */ }
    },
    async load() {
      try {
        const res = await api.get(`/farm/animals/breeds/${this.id}`);
        const b = res.data;
        this.form.animal_type_id = b.animal_type_id;
        this.form.breed_name = b.breed_name;
        this.form.description = b.description || '';
      } catch (e) {
        toast.error(e.response?.data?.message || 'Failed to load breed');
      }
    },
    async submit() {
      this.loading = true;
      try {
        await api.put(`/farm/animals/breeds/${this.id}`, this.form);
        toast.success('Breed updated');
        this.$router.push({ name: 'AnimalBreedList' });
      } catch (e) {
        toast.error(e.response?.data?.message || 'Failed to update');
      } finally {
        this.loading = false;
      }
    },
  },
};
</script>

<style scoped>
.login-wrapper {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 10px;
  background: linear-gradient(135deg, #27253f, #605a6d);
}
.login-container {
  width: 700px;
  max-width: 95%;
  background: #ffffff;
  display: flex;
  border-radius: 12px;
  overflow: hidden;
  box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
}
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
.input-group { margin-bottom: 15px; }
.input-group input,
.input-group select {
  width: 100%;
  padding: 12px 15px;
  border-radius: 25px;
  border: 1px solid #ddd;
  outline: none;
  font-size: 14px;
  color: #444;
}
.input-group input:focus,
.input-group select:focus { border-color: #6a5cff; }
</style>
