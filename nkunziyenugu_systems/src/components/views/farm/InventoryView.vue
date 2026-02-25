<template>
  <div>
    <h2 class="text-xl font-bold mb-4">Inventory</h2>

    <table class="w-full">
      <thead>
        <tr>
          <th>Name</th>
          <th>Unit</th>
          <th>Stock</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="item in items" :key="item.id">
          <td>{{ item.name }}</td>
          <td>{{ item.unit }}</td>
          <td>{{ item.stock }}</td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<script>
import api from "../../../store/services/api"

export default {
  name: "InventoryView",
  data() {
    return {
      items: []
    }
  },

  mounted() {
    this.loadInventory()
  },

  methods: {
    async loadInventory() {
      try {
        const res = await api.get("/farm/inventory/items")
        this.items = res.data.data
      } catch (err) {
        console.error("failed loading inventory", err)
      }
    }
  }
}
</script>
<style scoped></style>