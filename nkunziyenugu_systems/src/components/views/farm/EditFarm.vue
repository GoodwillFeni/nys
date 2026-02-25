<template>
  <div class="page">
    <h2>Edit Farm</h2>

    <form v-if="loaded" @submit.prevent="update">
      <div class="mb-3">
        <label>Name</label>
        <input v-model="form.name" class="form-control" required />
      </div>

      <div class="mb-3">
        <label>Location</label>
        <input v-model="form.location" class="form-control" />
      </div>

      <div class="mb-3">
        <label>Description</label>
        <textarea v-model="form.description" class="form-control"></textarea>
      </div>

      <div class="mb-3">
        <label>Status</label>
        <select v-model="form.is_active" class="form-control">
          <option :value="1">Active</option>
          <option :value="0">Inactive</option>
        </select>
      </div>

      <button class="btn btn-success">Update</button>
      <router-link class="btn btn-secondary ms-2" to="/Farm/Farms">
        Cancel
      </router-link>
    </form>
  </div>
</template>

<script>
import axios from 'axios'

export default {
  name: "EditFarm",

  data() {
    return {
      accountId: localStorage.getItem('account_id'),
      farmId: this.$route.params.id,
      loaded: false,
      form: {}
    }
  },

  mounted() {
    this.loadFarm()
  },

  methods: {
    async loadFarm() {
      const res = await axios.get('/api/farms', {
        params: { account_id: this.accountId }
      })

      const farm = res.data.find(f => f.id == this.farmId)
      this.form = farm
      this.loaded = true
    },

    async update() {
      await axios.put(`/api/farms/${this.farmId}`, {
        ...this.form,
        account_id: this.accountId
      })

      this.$router.push('/Farm/Farms')
    }
  }
}
</script>