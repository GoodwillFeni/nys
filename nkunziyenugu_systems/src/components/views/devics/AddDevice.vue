<template>
  <div class="login-wrapper">
    <div class="login-container">
      <div class="login-right">
        <h2>Add new Device</h2>
        <form @submit.prevent="addDevice">
          <div class="input-group">
            <input 
              type="text" 
              v-model="form.name" 
              placeholder="Device Name" 
              required />
          </div>

          <div class="input-group">
            <input 
              type="text" 
              v-model="form.device_id" 
              placeholder="Device ID" 
              required />
          </div>

          <div class="input-group">
            <input 
              type="text"
              v-model="form.assert_details"
              placeholder="Assert Details (Serial Number, Model, etc)"
              required
            />
            <span class="text-muted">Optional: Provide additional details about the device. </span>
          </div>

          <div class="input-group">
            <select v-model="selectedAccount" required>
              <option value="" disabled>Select Account</option>
              <option v-for="account in accounts" :key="account.id" :value="account.id">
                {{ account.name }}
              </option>
            </select>
          </div>

          <div class="row">
            <div class="col-2" >
                <button type="submit" :disabled="loading" class="button-info">
                    {{ loading ? 'Adding device...' : 'Add Device' }}
                </button>
            </div>
            <div class="col-2">
                <button @click="goBack()" class="button-warning">
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
import api from "@/store/services/api";
import { useToast } from "vue-toastification";
const toast = useToast();

export default {
  name: "AddUser",

  data() {
    return {
      loading: false,
      accounts: [],
      selectedAccount: null,
      form: {
        device_name: "",
        device_id: "",
        assert_details: "",
        account_id: "",
        role: ""
      }
    };
  },

  mounted() {
    this.getAccounts();
  },

  methods: {
    async getAccounts() {
      try {
        const token = localStorage.getItem("token");
        const response = await api.get("/accounts/available", {
          headers: { Authorization: `Bearer ${token}` },
        });

        if (response.data && response.data.accounts) {
          this.accounts = response.data.accounts;
        } else {
          this.accounts = [];
        }
      } catch (error) {
        toast.error(error.response?.data?.message || "Failed to load accounts.");
        this.accounts = [];
      }
    },

    async addDevice() {
      this.loading = true;
      try {
        const payload = {
            device_name: this.form.device_name,
            device_id: this.form.device_id,
            assert_details: this.form.assert_details,
            accounts: [
                {
                    id: this.selectedAccount,
                    role: this.form.role
                }
            ]
        };
        const response = await api.post("/addDevice", payload);
        toast.success(response.data.message);
        // Reset
        this.form = {
          name: "",
          surname: "",
          email: "",
          phone: "",
          password: "",
          role: ""
        };
        this.selectedAccount = null;

      } catch (error) {
        const msg =
          error.response?.data?.message ||
          "An error occurred while adding the user.";

        toast.error(msg);
      } finally {
        this.loading = false;
      }
    },
    goBack() {
      this.$router.go(-1);
    }
  }
};
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
</style>