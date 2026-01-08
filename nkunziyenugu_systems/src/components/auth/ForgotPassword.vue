<template>
  <div class="login-wrapper">
    <div class="login-container">

      <!-- LEFT PANEL -->
      <div class="login-left">
        <h1>Password Reset Link</h1>
        <p>
          Forgot your password?<br />
          Enter your email address and we’ll help you reset it securely.
        </p>
      </div>

      <!-- RIGHT PANEL -->
      <div class="login-right">
        <h2>Reset Password</h2>

        <form @submit.prevent="resetPassword">
          <div class="input-group">
            <input type="email" v-model="form.email" placeholder="Email address" required/>
          </div>

          <button type="submit" :disabled="loading">
            {{ loading ? 'Sending link...' : 'Send Reset Link' }}
          </button>

          <div class="switch-link">
            Remembered your password?
            <router-link to="/login">Login</router-link>
          </div>
        </form>

      </div>
    </div>
  </div>
</template>

<script>
import api from '../../store/services/api';
import { useToast } from "vue-toastification";
const toast = useToast();

export default {
  name: "ForgotPassword",

  data() {
    return {
      loading: false,
      form: {
        email: ""
      }
    };
  },

  methods: {
   async resetPassword() {
      this.loading = true;
      try {
        const res = await api.post('/forgot-password', {
          email: this.form.email
        });
        toast.success(res.data.message);
      } catch (err) {
        toast.error(err.response.data.message);
        console.error(err);
        this.loading = false;
      }
    }
  }
};
</script>

<style scoped>
/* SAME BASE STYLING AS LOGIN & SIGNUP */

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
  height: 480px;
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
  padding: 60px 50px;
  display: flex;
  flex-direction: column;
  justify-content: center;
}

.login-right h2 {
  text-align: center;
  margin-bottom: 30px;
  color: #6a5cff;
}

/* INPUTS */
.input-group {
  margin-bottom: 20px;
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
}

button:disabled {
  opacity: 0.7;
  cursor: not-allowed;
}

/* LINK */
.switch-link {
  margin-top: 20px;
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
