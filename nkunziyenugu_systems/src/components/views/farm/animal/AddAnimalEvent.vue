<template>
  <div class="login-wrapper">
    <div class="login-container">

      <div class="login-right">
        <h2>Add Animal Event</h2>

        <form @submit.prevent="submit">

          <!-- ACCOUNT AND FARM -->
          <div class="row">
            <div class="input-group col">
              <select v-model="form.account_id" required @change="getFarms()">
                <option value="" disabled>Select Account</option>
                <option v-for="a in accounts" :key="a.id" :value="a.id">
                  {{ a.name }}
                </option>
              </select>
            </div>

            <div class="input-group col">
              <select v-model="form.farm_id" required>
                <option value="" disabled>Select Farm</option>
                <option v-for="f in farms" :key="f.id" :value="f.id">
                  {{ f.name }}
                </option>
              </select>
            </div>
          </div>

          <!-- MODE (single/bulk) -->
          <div class="input-group">
            <select v-model="form.mode">
              <option value="single">Single Animal</option>
              <option value="bulk">Bulk (Farm / Type)</option>
            </select>
          </div>

          <!-- EVENT INFO -->
          <div class="row">
            <div class="input-group col">
              <input v-model="form.event_type" placeholder="Event Type (e.g Vaccination)" required />
            </div>

            <div class="input-group col">
              <input type="date" v-model="form.event_date" required />
            </div>
          </div>

          <!-- COST -->
          <div class="row">
            <div class="input-group col">
              <input type="number" v-model.number="form.cost" placeholder="Cost" required />
            </div>

            <div class="input-group col">
              <select v-model="form.cost_type">
                <option value="expense">Expense</option>
                <option value="running">Running</option>
                <option value="income">Income</option>
                <option value="loss">Loss</option>
                <option value="birth">Birth</option>
                <option value="investment">Investment (Buy Animal)</option>
              </select>
            </div>
          </div>

          <!-- SINGLE -->
          <div v-if="form.mode === 'single'" class="input-group">
            <select v-model="form.animal_id">
              <option value="" disabled>Select Animal</option>
              <option v-for="a in animals" :key="a.id" :value="a.id">
                {{ a.animal_tag }} - {{ a.animal_name || a.animal_type?.name || '' }}
              </option>
            </select>
          </div>

          <!-- BULK -->
          <div v-if="form.mode === 'bulk'" class="input-group">
            <select v-model="form.animal_type">
              <option value="">All Animal Types</option>
              <option v-for="t in animalTypes" :key="t.id" :value="t.name">
                {{ t.name }}
              </option>
            </select>
          </div>

          <!-- META -->
          <div class="input-group">
            <textarea v-model="form.meta.notes" placeholder="Notes (optional)"></textarea>
          </div>

          <!-- BUTTONS -->
          <div class="row">
            <div class="col-2">
              <button type="submit" :disabled="loading" class="button-info">
                {{ loading ? 'Saving...' : 'Save Event' }}
              </button>
            </div>

            <div class="col-2">
              <button type="button" @click="$router.back()" class="button-warning">
                Back
              </button>
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
  name: "AddAnimalEvent",

  props: {
    id: { type: [String, Number], default: null }
  },

  data() {
    return {
      loading: false,
      prefilling: false,

      accounts: [],
      farms: [],
      animals: [],
      animalTypes: [],

      form: {
        account_id: '',
        farm_id: '',
        mode: 'single',
        event_type: '',
        event_date: new Date().toISOString().slice(0, 10),
        cost: 0,
        cost_type: 'expense',
        animal_id: '',
        animal_type: '',
        meta: {
          notes: ''
        }
      }
    }
  },

  async mounted() {
    await this.getAccounts();

    if (this.id) {
      await this.prefillFromAnimal(this.id);
    }
  },

  methods: {
    async getAccounts() {
      try {
        const res = await api.get("/accounts/available");
        this.accounts = res.data.accounts || [];
      } catch (e) {
        toast.error("Failed to load accounts");
      }
    },

    async getFarms() {
      try {
        const res = await api.get("/farm/farms", {
          params: { account_id: this.form.account_id }
        });

        this.farms = res.data || [];
      } catch (e) {
        toast.error("Failed to load farms");
      }
    },

    async loadAnimals() {
      try {
        const res = await api.get("/farm/animals", {
          params: { farm_id: this.form.farm_id }
        });

        this.animals = res.data.data || [];
      } catch (e) {
        toast.error("Failed to load animals");
      }
    },

    async loadAnimalTypes() {
      try {
        const res = await api.get("/farm/animals/types");
        this.animalTypes = res.data.data || [];
      } catch (e) {
        toast.error("Failed to load animal types");
      }
    },

    async prefillFromAnimal(animalId) {
      try {
        this.prefilling = true;
        const res = await api.get(`/farm/animals/${animalId}`);
        const animal = res.data;

        // Set account and load farms
        this.form.account_id = animal.account_id;
        await this.getFarms();

        // Set farm and load animals + types
        this.form.farm_id = animal.farm_id;
        await Promise.all([this.loadAnimals(), this.loadAnimalTypes()]);

        // Set animal (single mode) and animal type (bulk mode)
        this.form.animal_id = animal.id;
        if (animal.animal_type) {
          this.form.animal_type = animal.animal_type.name;
        }
      } catch (e) {
        toast.error("Failed to load animal details");
      } finally {
        this.prefilling = false;
      }
    },

    async submit() {
      try {
        this.loading = true;

        const url = this.form.mode === 'single'
          ? '/animal-events/single'
          : '/animal-events/bulk';

        await api.post(url, this.form);

        toast.success("Event saved successfully");

        this.$router.back();

      } catch (e) {
        toast.error(e.response?.data?.message || "Failed to save event");
      } finally {
        this.loading = false;
      }
    }

  },

  watch: {
    'form.farm_id'(val) {
      if (val && !this.prefilling) {
        this.loadAnimals();
        this.loadAnimalTypes();
      }
    }
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
  height: 650px;
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

label {
  display: block;
  margin-bottom: 5px;
  font-weight: 16;
  color: #333;
}

.input-group input,
.input-group select,
.input-group textarea {
  width: 100%;
  padding: 12px 15px;
  border-radius: 25px;
  border: 1px solid #ddd;
  outline: none;
  font-size: 14px;
  color: #444;
}

.input-group input:focus,
.input-group select:focus {
  border-color: #6a5cff;
}

/* DATE INPUT */
input[type="date"] {
  width: 100%;
  padding: 12px 15px;
  border-radius: 25px;
  border: 1px solid #ddd;
  outline: none;
  font-size: 14px;
  color: #444;
}

input[type="date"]:focus {
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
</style>