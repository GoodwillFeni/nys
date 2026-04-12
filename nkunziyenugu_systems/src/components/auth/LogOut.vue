<template>
  <a @click.prevent="logout" class="logout-link">
    <i class="bi bi-box-arrow-right"></i>Log Out
  </a>
</template>

<script>
import api from '@/store/services/api'

export default {
  methods: {
    async logout() {
      try {
        await api.post('/logout')
      } catch (e) {
        console.warn('Backend logout failed, forcing client logout')
      }

      this.$store.dispatch('logout')
      location.reload()
      this.$router.replace('/LogIn')
    }
  }
}
</script>

<style scoped>
.logout-link {
  color: rgba(255,255,255,0.82);
  text-decoration: none;
  padding: 9px 10px;
  border-radius: 7px;
  display: flex;
  align-items: center;
  font-size: 14px;
  cursor: pointer;
  transition: background 0.15s;
  margin-top: auto;
}

.logout-link:hover {
  background: rgba(255,255,255,0.1);
  color: #fff;
}

i {
  margin-right: 10px;
  font-size: 15px;
  opacity: 0.85;
}
</style>
