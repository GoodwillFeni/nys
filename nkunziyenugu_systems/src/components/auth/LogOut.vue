<template>
  <li @click="logout">
    <a>
      <i class="bi bi-box-arrow-right"></i>Log Out</a>
  </li>
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
li {
  cursor: pointer;
  color: #ffffff;
  padding: 0;
  list-style-type: none !important;
  margin: 0;
}

i {
  margin-right: 10px;
}
</style>
