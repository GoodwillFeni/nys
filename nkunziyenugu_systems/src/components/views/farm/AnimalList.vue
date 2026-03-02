<template>
  <div>

        <div class="card p-3 mb-3">
      <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
        <h4 class="m-0">Animal List</h4>
        <div class="d-flex align-items-center gap-2">
          <input
            class="form-control form-control-sm"
            style="width: 220px"
            type="text"
            placeholder="Search animal"
            v-model="filters.search"
          />
        <div class="form-group">
            <div class="form-group">
              <select v-model="filters.status" id="status" name="status" class="form-control-sm" @change="loadAnimals()">
                <option value="">All</option>
                <option value="active">Active</option>
                <option value="sold">Sold</option>
                <option value="dead">Dead</option>
              </select>
            </div>
          </div>
            <button type="button" class="button-info" @click="addAnimal()">Add Animal</button>
        </div>
      </div>
    </div>

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
.filter-form {
  margin: 10px 0 10px 0;
  
}
.shop-page {
  padding: 10px;
}

.card {
  background: linear-gradient(135deg, #27253f, #605a6d);
  color: #fff;
  border-radius: 8px;
}

.thumb {
  width: 100px;
  height: 100px;
  border-radius: 10px;
  overflow: hidden;
  background: rgba(255, 255, 255, 0.08);
  flex: 0 0 auto;
}

.thumb-inner {
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 12px;
  color: rgba(255, 255, 255, 0.7);
}

.thumb-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}
</style>