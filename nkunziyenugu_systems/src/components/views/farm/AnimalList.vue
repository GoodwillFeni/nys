<template>
  <div>
    <form class="form-inline">
      <div class="form-group">
        <label for="search" class="block text-sm font-medium text-gray-700">Search</label>
          <input v-model="filters.search" 
          type="text" name="search" 
          id="search" class="form-control" placeholder="Search tag..." @input="loadAnimals()">
      </div>

      <div class="form-group mt-4">
        <label for="status" class="block text-sm font-medium text-gray-700">Status</label>
        <div class="mt-1">
          <select v-model="filters.status" id="status" name="status" class="form-control" @change="loadAnimals()">
            <option value="">All</option>
            <option value="active">Active</option>
            <option value="sold">Sold</option>
            <option value="dead">Dead</option>
          </select>
        </div>
      </div>

      <button type="button" class="button-info" @click="addAnimal()">Add Animal</button>
    </form>

    <table class="table-auto w-full">
      <thead>
        <tr>
          <th>Global Tag</th>
          <th>Farm Tag</th>
          <th>Type</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="animal in animals" :key="animal.id">
          <td>{{ animal.global_tag }}</td>
          <td>{{ animal.farm_tag }}</td>
          <td>{{ animal.animal_type.name }}</td>
          <td>{{ animal.status }}</td>
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
  data() {
    return {
      animals: [],
      filters: {
        search: "",
        status: ""
      }
    }
  },
  mounted() {
    this.loadAnimals()
  },

  methods: {
    addAnimal() {
      this.$router.push({ name: "AddAnimal" });
    },

    async loadAnimals() {
      try {
        const res = await api.get("farm/animals", 
        { 
          params: {
            search: this.filters.search,
            status: this.filters.status
          }
        });
        this.animals = res.data.data;
        console.log(this.animals)
      } catch (err) {
        toast.error(err.response.data.message);
        console.error(err);
      }
    }
  }
}
</script>
<style scoped>
</style>