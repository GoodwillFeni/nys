<template>
  <div class="login-wrapper">
    <div class="login-container">
      <div class="login-right">
        <h2>Edit Animal Type</h2>
        <form @submit.prevent="submit">
          <div class="input-group">
            <input type="text" v-model="form.name" placeholder="Name of the animal type" required />
          </div>

          <div class="input-group">
            <input type="text" v-model="form.description" placeholder="Description" />
          </div>

          <div class="input-group">
            <input
              type="number"
              step="0.01"
              min="0"
              v-model.number="form.default_birth_value"
              placeholder="Default birth value (R)"
            />
            <small class="hint">
              Used to value newborns of this type when logging a Birth event.
              E.g. 1400 for cattle, 400 for sheep. Set 0 to enter cost manually each time.
            </small>
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
  name: 'EditAnimalType',
  props: { id: { type: [String, Number], required: true } },
  data() {
    return {
      loading: false,
      form: { name: '', description: '', default_birth_value: 0 },
    };
  },
  mounted() { this.load(); },
  methods: {
    async load() {
      try {
        const res = await api.get(`/farm/animals/types/${this.id}`);
        const t = res.data;
        this.form.name = t.name;
        this.form.description = t.description || '';
        this.form.default_birth_value = parseFloat(t.default_birth_value || 0);
      } catch (e) {
        toast.error(e.response?.data?.message || 'Failed to load animal type');
      }
    },
    async submit() {
      this.loading = true;
      try {
        await api.put(`/farm/animals/types/${this.id}`, this.form);
        toast.success('Animal type updated');
        this.$router.push({ name: 'AnimalTypeList' });
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
.input-group textarea {
  width: 100%;
  padding: 12px 15px;
  border-radius: 25px;
  border: 1px solid #ddd;
  outline: none;
  font-size: 14px;
  color: #444;
}
.input-group input:focus { border-color: #6a5cff; }
.hint {
  display: block;
  margin: 6px 14px 0;
  font-size: 12px;
  color: #666;
  line-height: 1.4;
}
</style>
