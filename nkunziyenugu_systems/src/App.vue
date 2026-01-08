<template>
  <div id="app" class="app-layout">
    <SideNav v-if="isAuthenticated"/> <!-- Side Navigation Component only if user is authenticated -->

    <main class="main-content">  <!-- Main Content -->
      <RouterView />
    </main>
  </div>
</template>

<script>
import SideNav from '@/components/SideNav.vue'

export default {
  name: 'App',
  components: {
    SideNav
  },

  computed: {
    isAuthenticated() {
      return !!localStorage.getItem('token'); // Check if token exists in localStorage
    }
  }
}
</script>

<style>
#app {
  font-family: Avenir, Helvetica, Arial, sans-serif;
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
  color: #2c3e50;
  background: linear-gradient(135deg, #27253f, #605a6d) !important;
}
.app-layout {
  display: flex;
  min-height: 100vh;
  background: linear-gradient(135deg, #27253f, #605a6d) !important;
}

.main-content {
  flex: 1;
  background: linear-gradient(135deg, #27253f, #605a6d) !important;
}
</style>
