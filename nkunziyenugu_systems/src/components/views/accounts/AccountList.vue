<template>
  <div class="p-6">
    <div class="mb-4 flex justify-end">
      <button @click="$router.push('/AddAccount')" class="button-info">
        Add Account
      </button>
    </div>

    <!-- User List Table -->
    <table class="min-w-full border border-gray-200">
      <thead class="bg-gray-100">
        <tr>
          <th>#</th>
          <th>Name</th>
          <th>Type</th>
          <th>Created At</th>
          <th>Updated At</th>
          <th>Action</th>
        </tr>
      </thead>

      <tbody>
        <tr v-for="account in accounts" :key="account.id">
          <td>{{ (accounts.indexOf(account) + 1) }}</td>
          <td>{{ account.name }}</td>
          <td>{{ account.type }}</td>
          <td>{{ formatDate(account.created_at) }}</td>
          <td>{{ formatDate(account.updated_at) }}</td>
          <td>
            <button @click="editAccount(account)" class="button-info">
              <i class="bi bi-pencil-square"></i>
            </button>
            <button @click="deleteAccount(account)" class="button-danger" v-if="isImpersonating === true">
              <i class="bi bi-trash"></i>
            </button>
          </td>
        </tr>

        <tr v-if="accounts.length === 0">
          <td colspan="9" class="text-center py-4">
            No accounts found.
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
      accounts: [],
    }
  },

  mounted() {
    this.getAccounts();
  },

  watch: {
    // Watch for active account changes in Vuex store
    '$store.state.auth.activeAccount': {
      handler() {
        this.getAccounts();
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

    editAccount(account) {
      this.$router.push({
        path: `/EditAccount/${account.id}`,
      })
    },

    async getAccounts() {
      try {
        const token = localStorage.getItem("token");
        const response = await api.get("/accounts/available", {
          headers: { Authorization: `Bearer ${token}` },
        });

        if (response.data && response.data.accounts) {
          this.accounts = response.data.accounts;

          console.log("Accounts loaded:", this.accounts);// Debug log
        } else {
          this.accounts = [];
        }
      } catch (error) {
        toast.error(error.response?.data?.message || "Failed to load accounts.");
        this.accounts = [];
      }
    },

    async deleteAccount(account) {
      let confirmation = confirm("Are you sure you want to delete this account?");
      if (!confirmation) {
        return;
      }
      try {
        const token = localStorage.getItem("token");
        const response = await api.delete(`/accounts/${account.id}`, {
          headers: { Authorization: `Bearer ${token}` },
        });
        toast.success(response.data.message);
        this.getAccounts(); // reload accounts list
      } catch (error) {
        toast.error(error.response?.data?.message || "Failed to delete account.");
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
