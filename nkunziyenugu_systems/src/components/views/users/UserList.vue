<template>
  <div class="p-6">
    <h2 class="text-2xl font-bold mb-4">Users</h2>

    <div class="mb-4 flex justify-end">
      <button @click="$router.push('/AddUser')" class="button-info">
        Add New User
      </button>
    </div>

    <!-- User List Table -->
    <table class="min-w-full border border-gray-200">
      <thead class="bg-gray-100">
        <tr>
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
          <td>{{ user.name }}</td>
          <td>{{ user.surname }}</td>
          <td>{{ user.email }}</td>
          <td>{{ user.phone }}</td>
          <td>{{ user.account }}</td>
          <td>{{ user.role }}</td>
          <td>{{ formatDate(user.created_at) }}</td>
          <td>{{ formatDate(user.updated_at) }}</td>
          <td>
            <button @click="editUser(user)" class="button-info">
              Edit
            </button>
            <button @click="deleteUser(user)" class="button-danger">
              Delete
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
export default {
  name: 'UserList',
  data() {
    return {
      users: [
        {
          id: 1,
          name: 'John',
          surname: 'Doe',
          email: 'john@example.com',
          phone: '+27831234567',
          account: 'Home',
          role: 'Admin',
          created_at: '2026-01-01T08:00:00Z',
          updated_at: '2026-01-01T12:00:00Z',
        },
        {
          id: 2,
          name: 'Jane',
          surname: 'Smith',
          email: 'jane@example.com',
          phone: '+27839876543',
          account: 'Work',
          role: 'User',
          created_at: '2026-01-02T09:30:00Z',
          updated_at: '2026-01-02T10:00:00Z',
        },
      ],
    }
  },

  methods: {
    formatDate(dateStr) {
      const options = {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
      }
      return new Date(dateStr).toLocaleString(undefined, options)
    },

    editUser(user) {
      this.$router.push({
        path: `/EditUser/${user.id}`,
      })
    },

    deleteUser(user) {
      if (confirm(`Are you sure you want to delete ${user.name}?`)) {
        this.users = this.users.filter(u => u.id !== user.id)
      }
    },
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
