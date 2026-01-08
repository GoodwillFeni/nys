<template>
  <div class="login-wrapper">
    <div class="login-container">
      <div class="login-right">
        <h2>Add User</h2>

        <form @submit.prevent="addUser">
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
              placeholder="Password"
              required
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
            <div class="col-2" >
                <button type="submit" :disabled="loading" class="button-info">
                    {{ loading ? 'Adding user...' : 'Add User' }}
                </button>
            </div>
            <div class="col-2">
                <button @click="goBack()" class="button-warning">
                    Go Back?
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
  },

  methods: {
    async getAccounts() {
      try {
        const token = localStorage.getItem("token");
        const response = await api.get("/accounts/available", {
          headers: { Authorization: `Bearer ${token}` },
        });
        this.accounts = response.data.accounts;
        console.log("Available accounts:", this.accounts);
      } catch (error) {
        console.error("Error fetching accounts:", error);
      }
    },
    async addUser() {
      this.loading = true;
      this.error = null;
      this.success = null;
      try {
        const response = await api.post("/admin/add-user", this.form);
        console.log("Add user response:", response.data); // Debug log
        toast.success("User added successfully!");
        this.success = "User account created successfully!";
        // Reset form or redirect as needed
        this.form = { name: "", surname: "", email: "", phone: "", password: "", role: "" };
      } catch (error) {
        this.error =
          error.response && error.response.data ? error.response.data.message : "An error occurred while adding the user.";
        toast.error(this.error);
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