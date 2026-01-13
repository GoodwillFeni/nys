<template>
  <div class="login-wrapper">
    <div class="login-container">
      <div class="login-right">
        <h2>Add Account</h2>

        <form @submit.prevent="addAccount">
          <div class="input-group">
            <input 
              type="text" 
              v-model="form.name" 
              placeholder="Account Name" 
              required />
          </div>

          <div class="input-group">
            <select v-model="form.type" required>
              <option value="" disabled>Select Account Type</option>
              <option value="Home">Home</option>
              <option value="Business">Business</option>
              <option value="Farm">Farm</option>
              <option value="Other">Other</option>
            </select>
          </div>

          <div class="row">
            <div class="col-2">
              <button type="submit" :disabled="loading" class="button-info">
                {{ loading ? 'Adding account...' : 'Add Account' }}
              </button>
            </div>
            <div class="col-2">
              <button @click="goBack()" type="button" class="button-warning">
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
  name: "AddAccount",
  data() {
    return {
      loading: false,
      form: {
        name: "",
        type: ""
      }
    };
  },

  methods: {
    async addAccount() {
      this.loading = true;
      try {
        const payload = {
          name: this.form.name,
          type: this.form.type
        };
        
        const response = await api.post("/accounts", payload);
        
        toast.success(response.data.message || "Account created successfully");
        
        // Reset form
        this.form = {
          name: "",
          type: ""
        };
        
        // Navigate back to account list
        this.$router.push("/AccountList");

      } catch (error) {
        const msg =
          error.response?.data?.message ||
          "An error occurred while adding the account.";

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
/* SAME STYLING AS ADD USER */
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
.col-2 {
  display: flex;
  justify-content: flex-start;
}

.row {
  display: flex;
  gap: 10px;
  justify-content: flex-start;
}

.button-info,
.button-warning {
  white-space: nowrap;
}
</style>
