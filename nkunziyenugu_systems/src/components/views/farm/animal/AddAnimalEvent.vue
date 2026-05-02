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

          <!-- BIRTH OFFSPRING (cost_type === 'birth') -->
          <div v-if="isBirth" class="offspring-section">
            <div class="offspring-header-row">
              <strong>Offspring</strong>
              <div class="num-born">
                <label>Number born</label>
                <input type="number" min="1" max="20" v-model.number="offspringCount" @change="syncOffspringCount" />
              </div>
            </div>
            <span v-if="motherSex && motherSex.toLowerCase() !== 'female'" class="warning-text">
              Selected animal is sex='{{ motherSex }}'. Birth events require a Female animal.
            </span>
            <div v-for="(o, i) in form.offspring" :key="i" class="offspring-row">
              <span class="calf-num">#{{ i + 1 }}</span>
              <input class="calf-tag" v-model="o.animal_tag" placeholder="Tag (blank = auto)" />
              <select class="calf-sex" v-model="o.sex" required>
                <option value="" disabled>Sex</option>
                <option value="Female">Female</option>
                <option value="Male">Male</option>
                <option value="Unknown">Unknown</option>
              </select>
              <input class="calf-name" v-model="o.animal_name" placeholder="Name (optional)" />
            </div>
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

      offspringCount: 1,

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
        offspring: [],
        meta: {
          notes: ''
        }
      }
    }
  },

  computed: {
    // A birth = cost_type set to 'birth'. Whatever event_type they typed
    // ("New calf", "Twin lambs", etc.) is just description.
    isBirth() {
      return this.form.cost_type === 'birth';
    },
    selectedAnimal() {
      return this.animals.find(a => String(a.id) === String(this.form.animal_id));
    },
    motherSex() {
      return this.selectedAnimal?.sex ?? '';
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

    syncOffspringCount() {
      const target = Math.max(1, Math.min(20, Number(this.offspringCount) || 1));
      const list = this.form.offspring;
      while (list.length < target) list.push({ animal_tag: '', sex: '', animal_name: '' });
      while (list.length > target) list.pop();
    },

    async submit() {
      try {
        this.loading = true;

        const url = this.form.mode === 'single'
          ? '/animal-events/single'
          : '/animal-events/bulk';

        // Strip offspring from non-birth payloads.
        // Strip cost when it's 0 on a Birth so the backend auto-fills from default_birth_value.
        const payload = { ...this.form };
        if (!this.isBirth) {
          delete payload.offspring;
        } else if (!payload.cost || Number(payload.cost) === 0) {
          delete payload.cost;
        }

        await api.post(url, payload);

        toast.success(this.isBirth
          ? `Birth recorded — ${this.form.offspring.length} offspring created`
          : "Event saved successfully");

        this.$router.back();

      } catch (e) {
        // Surface Laravel validation errors (422) and generic server errors clearly.
        const data = e.response?.data;
        let msg = data?.message || "Failed to save event";
        if (data?.errors) {
          const lines = Object.entries(data.errors).map(([k, v]) => `${k}: ${(Array.isArray(v) ? v[0] : v)}`);
          msg = `${msg} — ${lines.join('; ')}`;
        }
        toast.error(msg);
        console.error('Save event failed:', data);
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
    },
    isBirth(val) {
      // When user picks cost_type=Birth, ensure at least one offspring row exists
      if (val && this.form.offspring.length === 0) {
        this.syncOffspringCount();
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
  align-items: flex-start;
  justify-content: center;
  padding: 20px 0;
  border-radius: 10px;
  background: linear-gradient(135deg, #27253f, #605a6d);
}

.login-container {
  width: 900px;
  max-width: 95%;
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

/* OFFSPRING (birth event) */
.offspring-section {
  background: #f6f4ff;
  border: 1px solid #d9d3ff;
  border-radius: 12px;
  padding: 12px;
  margin-bottom: 15px;
}
.offspring-header-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 10px;
  color: #6a5cff;
}
.num-born {
  display: flex;
  align-items: center;
  gap: 8px;
}
.num-born label {
  margin: 0;
  font-size: 13px;
  white-space: nowrap;
}
.num-born input {
  width: 70px;
  padding: 6px 10px;
  border-radius: 16px;
  border: 1px solid #ddd;
  font-size: 13px;
  text-align: center;
}
.offspring-row {
  display: flex;
  align-items: center;
  gap: 8px;
  border-top: 1px dashed #d9d3ff;
  padding-top: 8px;
  margin-top: 8px;
}
.offspring-row .calf-num {
  flex: 0 0 32px;
  font-weight: 700;
  color: #6a5cff;
  font-size: 13px;
}
.offspring-row .calf-tag,
.offspring-row .calf-sex,
.offspring-row .calf-name {
  flex: 1;
  min-width: 0;
  padding: 8px 12px;
  border-radius: 16px;
  border: 1px solid #ddd;
  outline: none;
  font-size: 13px;
  color: #444;
  background: #fff;
}
.offspring-row .calf-sex { flex: 0 0 110px; }
.offspring-row .calf-tag:focus,
.offspring-row .calf-sex:focus,
.offspring-row .calf-name:focus {
  border-color: #6a5cff;
}
.warning-text {
  display: block;
  color: #c0392b;
  font-size: 12px;
  margin-bottom: 8px;
}
</style>