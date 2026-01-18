<template>
  <li @click="logout">
    <a>Log Out</a>
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

</style>
