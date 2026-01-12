<template>
  <div class="login-wrapper">
    <div class="login-container">
      <div class="login-right">
        <h2>Edit User</h2>

        <form @submit.prevent="editUser">
          <div class="input-group">
            <input 
              type="text" 
              v-model="form.name" 
              placeholder="Name" 
              required />
          </div>

          <div class="input-group">
            <input 
              type="text" 
              v-model="form.surname" 
              placeholder="Surname" 
              required />
          </div>

          <div class="input-group">
            <input 
              type="email"
              v-model="form.email"
              placeholder="Email address"
              required
            />
          </div>

          <div class="input-group">
            <input
              type="tel"
              v-model="form.phone"
              placeholder="Phone number"
            />
          </div>

          <div class="input-group">
            <input
              type="password"
              v-model="form.password"
              placeholder="New Password (leave blank to keep current)"
            />
          </div>

          <div class="input-group">
            <select v-model="selectedAccount" required>
              <option value="" disabled>Select Account</option>
              <option v-for="account in accounts" :key="account.id" :value="account.id">
                {{ account.name }}
              </option>
            </select>
          </div>

          <div class="input-group">
            <select v-model="form.role" required>
              <option value="" disabled>Select Role</option>
              <option value="super_admin">SuperAdmin</option>
              <option value="owner">Owner</option>
              <option value="admin">Admin</option>
              <option value="viewer">Viewer</option>
            </select>
          </div>

          <div class="row">
            <div class="col-2">
              <button type="submit" :disabled="loading" class="button-info">
                {{ loading ? 'Updating user...' : 'Update' }}
              </button>
            </div>
            <div class="col-2">
                <button @click="goBack()" type="button" class="button-warning">
                Go Back
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
  name: "EditUser",
  data() {
    return {
      loading: false,
      accounts: [],
      selectedAccount: null,
      form: {
        id: null,
        name: "",
        surname: "",
        email: "",
        phone: "",
        password: "",
        role: ""
      }
    };
  },

  mounted() {
    this.getAccounts();
    this.getUserDetails();
  },

  methods: {
    async getAccounts() {
      try {
        const token = localStorage.getItem("token");
        const response = await api.get("/accounts/available", {
          headers: { Authorization: `Bearer ${token}` },
        });
        this.accounts = response.data.accounts || [];
      } catch (error) {
        toast.error("Failed to load accounts");
        this.accounts = [];
      }
    },

    async getUserDetails() {
      try {
        const token = localStorage.getItem("token");
        const response = await api.get(`/users/${this.$route.params.id}`, {
          headers: { Authorization: `Bearer ${token}` }
        });

        const user = response.data.data;
        this.form.id = user.id;
        this.form.name = user.name;
        this.form.surname = user.surname;
        this.form.email = user.email;
        this.form.phone = user.phone || "";
        // Password left blank
        if (user.accounts && user.accounts.length > 0) {
          this.selectedAccount = user.accounts[0].id;
          this.form.role = user.accounts[0].role;
        }

      } catch (error) {
        console.error("Error fetching user details:", error.response?.data || error.message);
        toast.error("Failed to load user details");
      }
    },

    async editUser() {
      this.loading = true;
      try {
        const payload = {
          name: this.form.name,
          surname: this.form.surname,
          email: this.form.email,
          phone: this.form.phone,
          password: this.form.password, // optional
          accounts: [
            {
              id: this.selectedAccount,
              role: this.form.role
            }
          ]
        };
        const token = localStorage.getItem("token");
        const response = await api.put(`/users/${this.form.id}`, payload, {
          headers: { Authorization: `Bearer ${token}` }
        });

        toast.success(response.data.message);

      } catch (error) {
        const msg = error.response?.data?.message || "An error occurred while updating the user.";
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