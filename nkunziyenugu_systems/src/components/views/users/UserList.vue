<template>
  <div class="p-6">
    <div class="mb-4 flex justify-end">
      <button @click="$router.push('/AddUser')" class="button-info">
        Add New User
      </button>
    </div>

    <!-- User List Table -->
    <table class="min-w-full border border-gray-200">
      <thead class="bg-gray-100">
        <tr>
          <th>#</th>
          <th>Name</th>
          <th>Surname</th>
          <th>Email</th>
          <th>Phone</th>
          <th>Account</th>
          <th>Role</th>
          <th>Created At</th>
          <th>Updated At</th>
          <th>Action</th>
        </tr>
      </thead>

      <tbody>
        <tr v-for="user in users" :key="user.id">
          <td>{{ (users.indexOf(user) + 1) }}</td>
          <td>{{ user.name }}</td>
          <td>{{ user.surname }}</td>
          <td>{{ user.email }}</td>
          <td>{{ user.phone }}</td>
          <td>{{ user.account_name }}</td>
          <td>{{ user.account_role }}</td>
          <td>{{ formatDate(user.user_created_at) }}</td>
          <td>{{ formatDate(user.user_updated_at) }}</td>
          <td>
            <button @click="editUser(user)" class="button-info">
              <i class="bi bi-pencil-square"></i>
            </button>
            <button @click="deleteUser(user)" class="button-danger" v-if="isImpersonating === true">
              <i class="bi bi-trash"></i>
            </button>
          </td>
        </tr>

        <tr v-if="users.length === 0">
          <td colspan="9" class="text-center py-4">
            No users found.
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<script>
import api from "@/store/services/api";
import { useToast } from "vue-toastification";
const toast = useToast();

export default {
  name: 'UserList',
  data() {
    return {
      users: [],
    }
  },

  mounted() {
    this.getUsers();
  },

  watch: {
    // Watch for active account changes in Vuex store
    '$store.state.auth.activeAccount': {
      handler() {
        this.getUsers();
      },
      deep: true
    }
  },

  computed: {
    currentUser() {
      return this.$store.state.auth.user
    },

    isImpersonating() {
      return this.$store.state.auth.user?.is_impersonating === true
    }
  },

  methods: {
    async impersonate(userId) {
      await api.post(`/impersonate/${userId}`)
      location.reload()
    },
    async stopImpersonation() {
      await api.post('/impersonate/stop')
      location.reload()
    },
    formatDate(dateStr) {
        const date = new Date(dateStr);
        const year   = date.getFullYear();
        const month  = String(date.getMonth() + 1).padStart(2, '0');
        const day    = String(date.getDate()).padStart(2, '0');
        const hours  = String(date.getHours()).padStart(2, '0');
        const minutes= String(date.getMinutes()).padStart(2, '0');
        const seconds= String(date.getSeconds()).padStart(2, '0');
        return `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`;
    },

    editUser(user) {
      this.$router.push({
        path: `/EditUser/${user.user_id}`,
      })
    },

    async getUsers() {
      try {
        // X-Account-ID is now automatically sent by API interceptor
        const response = await api.get('/users');
        this.users = response.data.data || [];
      } catch (error) {
        console.error('Error fetching users:', error);
        toast.error(error.response?.data?.message || "Failed to load users.");
        this.users = [];
      }
    },

    async deleteUser(user) {
      let confirmation = confirm("Are you sure you want to delete this user?");
      if (!confirmation) {
        return;
      }
      try {
        const token = localStorage.getItem("token");
        const response = await api.delete(`/users/${user.user_id}`, {
          headers: { Authorization: `Bearer ${token}` },
        });
        toast.success(response.data.message);
        this.getUsers(); // reload users list
      } catch (error) {
        toast.error(error.response?.data?.message || "Failed to delete user.");
      }
    }

  },
}
</script>

<style scoped>
table {
  background: linear-gradient(135deg, #27253f, #605a6d);
  color: #fff;
  width: 100%;
  border-collapse: collapse;
}

th,
td {
  padding: 10px;
  border-bottom: 1px solid #fff;
  text-align: left;
}
</style>
