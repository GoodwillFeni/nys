<template>
  <div class="page">
    <div class="header">
      <button class="button-info" @click="addFarm()">+ Add Farm</button>
    </div>

    <table class="">
      <thead>
        <tr>
          <th>#</th>
          <th>Farm Name</th>
          <th>Location</th>
          <th>Description</th>
          <th>Status</th>
          <th width="150">Actions</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="farm in farms" :key="farm.id">
          <td>{{ (farms.indexOf(farm) + 1) }}</td>
          <td>{{ farm.name }}</td>
          <td>{{ farm.location }}</td>
          <td>{{ farm.description }}</td>
          <td>
            <span :class="farm.is_active ? 'badge bg-success' : 'badge bg-danger'">
              {{ farm.is_active ? 'Active' : 'Inactive' }}
            </span>
          </td>
          <td>
            <router-link
              :to="`/Farm/Edit/${farm.id}`"
              class="btn btn-sm btn-warning"
            >
            <i class="bi bi-pencil-square"></i>
            </router-link>

            <button
              class="btn btn-sm btn-danger"
              @click="deleteFarm(farm.id)"
            >
            <i class="bi bi-trash"></i>
            </button>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<script>
import api from '@/store/services/api';

export default {
  name: "FarmList",

  data() {
    return {
      farms: [],
      accountId: localStorage.getItem('account_id')   // ← this is probably `null`
    }
  },

  computed: {
    accounts() {
      return this.$store.state.auth.accounts;        // same array
    }
  },

  mounted() {
    this.loadFarms()
  },

  methods: {
    async loadFarms() {
      api.get(`/farm/farms`, {
        params: { account_id: this.accountId }           // query string
      })
      .then(res => {
        this.farms = res.data
      })
        .catch(err => {
            console.error(err)
        })
    },
    addFarm() {
      this.$router.push({ name: "AddFarm" });
    }, 

    async deleteFarm(id) {
      if (!confirm("Delete this farm?")) return

      await api.delete(`/farm/farms/${id}`, {
        data: { 
          account_id: this.accountId 
        }
      })

      this.loadFarms()
    },
    choose(account) {
      this.$store.dispatch('auth/switchAccount', account);
      // axios interceptor will then send X-Account-ID with every request
    }
  }
}
</script>

<style scoped>
.btn {
  margin-left: 10px;
}
</style>