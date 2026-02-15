<template>
  <div class="login-wrapper">
    <div class="login-container">
      <!-- LEFT PANEL -->
      <div class="login-left">
        <h1>Welcome to NYS</h1>
        <p>
          Smart Home & Business System.<br />
          Monitor devices, manage stock, livestock and finances in one platform.
        </p>
      </div>
      <!-- RIGHT PANEL -->
      <div class="login-right">
        <h2>User Login</h2>

        <form @submit.prevent="login">
          <div class="input-group">
            <input
              type="text"
              v-model="form.login"
              placeholder="Email or phone"
              required
            />
          </div>

          <div class="input-group">
            <input
              :type="showPassword ? 'text' : 'password'"
              v-model="form.password"
              placeholder="Password"
              class="password-input"
              required
            />
            <button
              type="button"
              class="toggle-password"
              :aria-label="showPassword ? 'Hide password' : 'Show password'"
              @click="showPassword = !showPassword"
            >
              <svg v-if="!showPassword" class="eye-icon" viewBox="0 0 24 24" aria-hidden="true">
                <path
                  fill="currentColor"
                  d="M12 5c-7 0-10 7-10 7s3 7 10 7 10-7 10-7-3-7-10-7Zm0 12a5 5 0 1 1 0-10 5 5 0 0 1 0 10Zm0-8a3 3 0 1 0 0 6 3 3 0 0 0 0-6Z"
                />
              </svg>
              <svg v-else class="eye-icon" viewBox="0 0 24 24" aria-hidden="true">
                <path
                  fill="currentColor"
                  d="M2.1 3.51 3.51 2.1l18.38 18.38-1.41 1.41-3.04-3.04A11.5 11.5 0 0 1 12 20C5 20 2 13 2 13c.74-1.73 1.87-3.27 3.3-4.55L2.1 3.51Zm6.06 6.06a4 4 0 0 0 5.27 5.27l-1.06-1.06a2.5 2.5 0 0 1-3.15-3.15l-1.06-1.06ZM12 6c3.5 0 6.3 1.77 8.28 3.77A14.4 14.4 0 0 1 22 13s-.46 1.06-1.37 2.33l-1.5-1.5c.56-.78.88-1.33.88-1.33s-3-6-8-6c-.62 0-1.22.09-1.79.25L8.85 5.38C9.86 5.14 10.91 5 12 5v1Z"
                />
              </svg>
            </button>
          </div>

          <div class="options">
            <label>
              <input type="checkbox" v-model="form.remember" />
              Remember me
            </label>

            <a href="#" @click.prevent="forgotPassword">
              Forgot password?
            </a>
          </div>

          <button type="submit" :disabled="loading">
            {{ loading ? 'Logging in...' : 'Login' }}
          </button>
            <div class="switch-link">
            Do not have an account?
            <router-link to="/SignUp">Sign Up</router-link>
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
  name: "LogIn",
  data() {
    return {
      loading: false,
      showPassword: false,
      form: {
        login: "",
        password: "",
        remember: false
      }
    };
  },

  methods: {
    async login() {
        this.loading = true
        try {
          const res = await api.post('/login', {
            login: this.form.login,
            password: this.form.password,
          })
          console.log(res)

          if (res.data.status === 'success') {
             this.$store.dispatch('login', {
                  user: res.data.user,
                  token: res.data.token,
                  accounts: res.data.accounts,
                  expires_at: res.data.expires_at
            })
            toast.success(
              'Welcome, ' + res.data.user.name + ' to NYS System!'
            )

            location.reload()
            this.$router.push('/')
            // setTimeout(() => {
            //   location.reload()
            // }, 2000)
          } else {
            toast.error(res.data.message)
          }
        } catch (err) {
          console.error(err)
          toast.error(
            err.response?.data?.message || 'Login failed. Please try again.'
          )
        } finally {
          this.loading = false
        }
    },

    forgotPassword() {
      this.$router.push('/ForgotPassword');
    }
  }
};
</script>

<style scoped>
.login-wrapper {
  min-height: 100vh;
  display: flex;
  align-items: center;
  border-radius: 10px;
  justify-content: center;
  background: linear-gradient(135deg, #27253f, #605a6d);
}

/* MAIN CARD */
.login-container {
  width: 900px;
  max-width: 95%;
  height: 500px;
  background: #ffffff;
  display: flex;
  border-radius: 12px;
  overflow: hidden;
  box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
}

/* LEFT SIDE */
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

/* RIGHT SIDE */
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
  position: relative;
}

.input-group input {
  width: 100%;
  padding: 12px 15px;
  border-radius: 25px;
  border: 1px solid #ddd;
  outline: none;
  font-size: 14px;
}

.password-input {
  padding-right: 42px;
}

.toggle-password {
  position: absolute;
  right: 12px;
  top: 50%;
  transform: translateY(-50%);
  border: none;
  background: transparent;
  padding: 0;
  width: auto;
  cursor: pointer;
  color: #6a5cff;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  z-index: 2;
}

.toggle-password:focus {
  outline: none;
}

.eye-icon {
  width: 18px;
  height: 18px;
}

.input-group input:focus {
  border-color: #6a5cff;
}

/* OPTIONS */
.options {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 13px;
  margin-bottom: 25px;
}

.options a {
  color: #6a5cff;
  text-decoration: none;
}

.options a:hover {
  text-decoration: underline;
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
