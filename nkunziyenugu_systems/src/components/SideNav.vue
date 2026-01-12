<template>
  <aside class="sidebar">
    <nav>
      <RouterLink to="/" v-if="isAuthenticated || isSuperAdmin || isOwner || isAdmin">Main Dashboard</RouterLink>
      <RouterLink v-if="isAuthenticated || isSuperAdmin || isOwner || isAdmin" to="/AccountList">Accounts</RouterLink>
      <RouterLink v-if="isAuthenticated || isSuperAdmin || isOwner || isAdmin" to="/UserList">Users</RouterLink>
      <LogOut />
    </nav>
  </aside>
</template>

<script>
import LogOut from '../components/auth/LogOut.vue';
export default {
  name: 'SideNav',

  components: {
    LogOut
  },

  computed: {
    isAuthenticated() {
      return this.$store.getters.isAuthenticated
    },
    isSuperAdmin() {
      return this.$store.getters.isSuperAdmin
    },
    isOwner() {
      return this.$store.getters.isOwner
    },
    isAdmin() {
      return this.$store.getters.isAdmin
    },
    isViewer() {
      return this.$store.getters.isViewer
    }
  }
}
</script>

<style scoped>
.sidebar {
  width: 150px;
  background: linear-gradient(135deg, #27253f, #605a6d);
  color: #fff;
  padding: 20px;
}

.sidebar h2 {
  margin-bottom: 10px;
}

nav {
  display: flex;
  flex-direction: column;
}

nav a {
  color: #fff;
  text-decoration: none;
  padding: 5px 0;
}

nav a.router-link-active {
  font-weight: bold;
}
</style>
