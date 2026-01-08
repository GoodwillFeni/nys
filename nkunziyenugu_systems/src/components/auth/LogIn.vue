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
              type="email"
              v-model="form.email"
              placeholder="Email address"
              required
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
      form: {
        email: "",
        password: "",
        remember: false
      }
    };
  },

  methods: {
    async login() {
        this.loading = true
        try {
          const res = await api.post('/login', this.form)

          if (res.data.status === 'success') {
              // localStorage.setItem('token', res.data.token)
              // localStorage.setItem('user', JSON.stringify(res.data.user))
             this.$store.dispatch('login', {
                  user: res.data.user,
                  token: res.data.token,
                  accounts: res.data.accounts
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
