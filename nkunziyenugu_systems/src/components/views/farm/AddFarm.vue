<template>
  <div class="page">
    <h2>Add Farm</h2>

    <form @submit.prevent="submit">
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

      <button class="btn btn-success">Save</button>
      <router-link class="btn btn-secondary ms-2" to="/Farm/Farms">
        Cancel
      </router-link>
    </form>
  </div>
</template>

<script>
import axios from 'axios'

export default {
  name: "AddFarm",

  data() {
    return {
      accountId: localStorage.getItem('account_id'),
      form: {
        name: '',
        location: '',
        description: '',
        is_active: 1
      }
    }
  },

  methods: {
    async submit() {
      await axios.post('/api/farms', {
        ...this.form,
        account_id: this.accountId
      })

      this.$router.push('/Farm/Farms')
    }
  }
}
</script>