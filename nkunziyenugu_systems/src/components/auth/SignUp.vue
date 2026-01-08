<template>
  <div class="login-wrapper">
    <div class="login-container">
      <!-- LEFT PANEL -->
      <div class="login-left">
        <h1>Create Your Account</h1>
        <p>
          Join NYS Smart Home & Business System.<br />
          Manage devices, livestock, stock and finances in one place.
        </p>
      </div>
      <!-- RIGHT PANEL -->
      <div class="login-right">
        <h2>Sign Up</h2>

        <form @submit.prevent="signup">
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

          <button type="submit" :disabled="loading">
            {{ loading ? 'Creating account...' : 'Sign Up' }}
          </button>

          <div class="switch-link">
            Already have an account?
            <router-link to="/login">Login</router-link>
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
  name: "SignUp",

  data() {
    return {
      loading: false,
      error: null,
      success: null,
      form: {
        name: "",
        surname: "",
        email: "",
        phone: "",
        password: ""
      }
    };
  },

  methods: {
    async signup() {
      this.loading = true;
      this.error = null;
      this.success = null;
      try {
        const response = await api.post("/register", this.form);
        console.log("Signup response:", response.data); // Debug log
        toast.success("Signup successful! Redirecting to login...");
        // this.success = "User account created successfully! Please log in.";
        setTimeout(() => {
          this.$router.push("/login");
        }, 1500);
      } catch (error) {
        this.error =
          error.response && error.response.data? error.response.data.message : toast.error("An error occurred during signup.");
      } finally {
        this.loading = false;
      }
      
    }
  }
};
</script>

<style scoped>
/* SAME STYLING AS LOGIN */
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

.input-group input {
  width: 100%;
  padding: 12px 15px;
  border-radius: 25px;
  border: 1px solid #ddd;
  outline: none;
  font-size: 14px;
}

.input-group input:focus {
  border-color: #6a5cff;
}

/* BUTTON */
button {
  width: 100%;
  padding: 12px;
  border: none;
  border-radius: 25px;
  background: linear-gradient(135deg, #27253f, #605a6d);
  color: #fff;
  font-size: 15px;
  cursor: pointer;
  margin-top: 10px;
}

button:disabled {
  opacity: 0.7;
  cursor: not-allowed;
}

/* SWITCH LINK */
.switch-link {
  margin-top: 15px;
  text-align: center;
  font-size: 13px;
}

.switch-link a {
  color: #6a5cff;
  text-decoration: none;
  font-weight: 500;
}

.switch-link a:hover {
  text-decoration: underline;
}

/* INFO NOTIFICATION */
.error-msg {
  color: #d32f2f;
  font-size: 13px;
  margin-bottom: 10px;
  text-align: center;
}

.success-msg {
  color: #2e7d32;
  font-size: 13px;
  margin-bottom: 10px;
  text-align: center;
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
</style>
