<template>
  <div class="login-wrapper">
    <div class="login-container">

      <div class="login-right">
        <h2>Add Event Type</h2>

        <form @submit.prevent="submit">

          <!-- ACCOUNT + FARM -->
          <div class="row">
            <div class="input-group col">
              <select v-model="form.account_id" required @change="getFarms()">
                <option value="" disabled>Select Account</option>
                <option v-for="account in accounts" :key="account.id" :value="account.id">
                  {{ account.name }}
                </option>
              </select>
            </div>

            <div class="input-group col">
              <select v-model="form.farm_id">
                <option value="">All Farms (Optional)</option>
                <option v-for="farm in farms" :key="farm.id" :value="farm.id">
                  {{ farm.name }}
                </option>
              </select>
            </div>
          </div>

          <!-- EVENT NAME -->
          <div class="input-group">
            <input
              type="text"
              v-model="form.event_type"
              placeholder="Event Type (e.g Vaccination, Feeding)"
              required
            />
          </div>

          <!-- DEFAULT COST -->
          <div class="row">
            <div class="input-group col">
              <input
                type="number"
                v-model="form.default_cost"
                placeholder="Default Cost"
              />
            </div>

            <div class="input-group col">
              <select v-model="form.cost_type">
                <option value="expense">Expense</option>
                <option value="running">Running</option>
                <option value="income">Income</option>
                <option value="loss">Loss</option>
              </select>
            </div>
          </div>

          <!-- DESCRIPTION -->
          <div class="input-group">
            <textarea
              v-model="form.description"
              placeholder="Description (optional)"
            ></textarea>
          </div>

          <!-- BUTTONS -->
          <div class="row">
            <div class="col-4">
              <button type="submit" :disabled="loading" class="button-info">
                {{ loading ? 'Saving...' : 'Add Event Type' }}
              </button>
            </div>

            <div class="col-3">
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
  name: "AddEventType",

  data() {
    return {
      loading: false,
      accounts: [],
      farms: [],

      form: {
        account_id: '',
        farm_id: '',
        event_type: '',
        default_cost: '',
        cost_type: 'expense',
        description: ''
      }
    }
  },

  mounted() {
    this.getAccounts();
  },

  methods: {

    async getAccounts() {
      try {
        const res = await api.get("/accounts/available");
        this.accounts = res.data.accounts || [];
      } catch (error) {
        toast.error("Failed to load accounts");
      }
    },

    async getFarms() {
      try {
        const res = await api.get("/farm/farms", {
          params: { account_id: this.form.account_id }
        });

        this.farms = res.data || [];
      } catch (error) {
        toast.error("Failed to load farms");
      }
    },

    async submit() {
      try {
        this.loading = true;

        await api.post('/event-types', this.form);

        toast.success('Event type added successfully');

        this.$router.push('/Farm/EventTypes');

      } catch (error) {
        toast.error(error.response?.data?.message || 'Failed to add event type');
      } finally {
        this.loading = false;
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