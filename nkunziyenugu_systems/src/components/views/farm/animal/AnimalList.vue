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
            <button type="button" class="button-info" @click="addType()">Add Type</button>
            <button type="button" class="button-info" @click="addBreed()">Add Breed</button>
            <button type="button" class="button-info" @click="$router.push({ name: 'AnimalEventList' })">Events</button>
        </div>
      </div>
    </div>

    <table class="">
      <thead>
        <tr>
          <th>#</th>
          <th>Account</th>
          <th>Farm</th>
          <th>Breed</th>
          <th>Animal Tag</th>
          <th>Type</th>
          <th>Sex</th>
          <th>Status</th>
          <th>Device ID</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="animal in animals" :key="animal.id">
          <td>{{ (animals.indexOf(animal) + 1) }}</td>
          <td>{{ animal.account.name }}</td>
          <td>{{ animal.farm.name }}</td>
          <td>{{ animal.breed.breed_name }}</td>
          <td>{{ animal.animal_tag }}</td>
          <td>{{ animal.animal_type.name }}</td>
          <td>{{ animal.sex }}</td>
          <td>{{ animal.status }}</td>
          <td 
            class="cursor-pointer"
            v-if="animal.device_links?.[0]?.device?.device_uid" 
            @click="DeviceLogs(animal)">
            {{ animal.device_links?.[0]?.device?.device_uid }}
          </td>
          <td v-else>
            No linked device
          </td>          
          <td>
            <!-- <button class="button-info" @click="$router.push({ name: 'AnimalDetails', params: { id: animal.id } })">
              <i class="bi bi-eye"></i>
            </button> -->
            <button 
              @mouseenter="showTooltip = true" 
              @mouseleave="showTooltip = false"
              class="button-warning" 
              @click="$router.push({ name: 'EditAnimal',
              params: { id: animal.id } })">
                <i class="bi bi-pencil-square"></i>
                <span class="tooltip-text">Ear tag number is a unique identifier for each animal.</span>
            </button>

            <button class="button-info" @click="$router.push({ name: 'AddAnimalEvent', params: { id: animal.id } })">
              <i class="bi bi-calendar2-event"></i>
            </button>

             <button 
             class="button-info"
             v-if="animal.device_links.length != 1"
             @click="$router.push({ name: 'AnimalDeviceLink', params: { id: animal.id } })">
              <i class="bi bi-link"></i>
            </button>
            <!-- UnLink device button -->
             <button 
             class="button-warning"
             v-else
             @click="unlinkDevice(animal)">
              <i class="bi bi-link-45deg"></i>
            </button>
            
            <button class="button-success" v-if="animal.status === 'Active'" @click="openSell(animal)">
              <i class="bi bi-cash-coin"></i>
            </button>

            <button class="button-danger" @click="deleteAnimal(animal)">
              <i class="bi bi-trash"></i>
            </button>
          </td>
        </tr>
      </tbody>
    </table>

    <!-- Sell Modal -->
    <div class="modal-overlay" v-if="showSellModal" @click.self="showSellModal = false">
      <div class="modal-box">
        <h3>Sell Animal</h3>
        <p class="sell-info">Tag: <b>{{ sellForm.animal_tag }}</b> | {{ sellForm.animal_name || '' }}</p>
        <form @submit.prevent="submitSell">
          <div class="input-group">
            <input type="number" v-model.number="sellForm.sale_price" placeholder="Sale price (R)" required min="0" step="0.01" />
          </div>
          <div class="input-group">
            <input type="date" v-model="sellForm.sale_date" required />
          </div>
          <div class="input-group">
            <textarea v-model="sellForm.notes" placeholder="Notes (optional)"></textarea>
          </div>
          <div class="d-flex gap-2">
            <button type="submit" class="button-info" :disabled="selling">
              {{ selling ? 'Selling...' : 'Confirm Sale' }}
            </button>
            <button type="button" class="button-warning" @click="showSellModal = false">Cancel</button>
          </div>
        </form>
      </div>
    </div>
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
      },
      showSellModal: false,
      selling: false,
      sellForm: {
        animal_id: null,
        animal_tag: '',
        animal_name: '',
        sale_price: null,
        sale_date: new Date().toISOString().slice(0, 10),
        notes: '',
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
    addType() {
      this.$router.push({ name: "AddAnimalType" });
    },
    addBreed() {
      this.$router.push({ name: "AddAnimalBreed" });
    },

    DeviceLogs(animal) {
      this.$router.push({ name: 'DeviceLogs', params: { id: animal.device_links?.[0]?.device?.id } });
    },

    async loadAnimals() { //Fetch all animals
      this.$store.dispatch("fetchAnimalList", this.filters)
      this.$store.subscribe((mutation) => {
        if (mutation.type === "SET_ANIMAL_LIST") {
          this.animals = mutation.payload
        }
      })
    },

    async unlinkDevice(animal) { //Unlink device
      let confirmation = confirm("Are you sure you want to unlink this device from this animal?");
      if (!confirmation) {
        return;
      }
      try {
       const response = await api.put(`farm/animals/devices/link/${animal.device_links[0].id}`, {
        animal_id: animal.id
       });
        toast.success(response.data.message);
        this.loadAnimals();
      } catch (error) {
        console.log(error.response?.data?.message)
      }
    },

    openSell(animal) {
      this.sellForm = {
        animal_id: animal.id,
        animal_tag: animal.animal_tag,
        animal_name: animal.animal_name || '',
        sale_price: null,
        sale_date: new Date().toISOString().slice(0, 10),
        notes: '',
      };
      this.showSellModal = true;
    },

    async submitSell() {
      try {
        this.selling = true;
        const res = await api.post(`farm/animals/${this.sellForm.animal_id}/sell`, {
          sale_price: this.sellForm.sale_price,
          sale_date: this.sellForm.sale_date,
          notes: this.sellForm.notes,
        });
        toast.success(res.data.message);
        this.showSellModal = false;
        this.loadAnimals();
      } catch (error) {
        toast.error(error.response?.data?.message || 'Failed to sell animal');
      } finally {
        this.selling = false;
      }
    },

    async deleteAnimal(animal) { // Delete animal
      let confirmation = confirm("Are you sure you want to delete this animal?");
      if (!confirmation) {
        return;
      }
      try {
       const response = await api.delete(`farm/animals/${animal.id}`);
        toast.success(response.data.message);
        this.loadAnimals();
      } catch (error) {
        console.log(error.response?.data?.message)
      }
    },
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

.cursor-pointer:hover,
.cursor-pointer:active {
  cursor: pointer;
  color: #6a5cff;
}

.modal-overlay {
  position: fixed;
  top: 0; left: 0; right: 0; bottom: 0;
  background: rgba(0,0,0,0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
}

.modal-box {
  background: #fff;
  border-radius: 12px;
  padding: 30px;
  width: 450px;
  max-width: 95%;
  box-shadow: 0 20px 40px rgba(0,0,0,0.3);
}

.modal-box h3 { margin-bottom: 10px; color: #6a5cff; text-align: center; }
.sell-info { text-align: center; color: #666; margin-bottom: 16px; font-size: 14px; }
.modal-box .input-group { margin-bottom: 12px; }

.modal-box input,
.modal-box textarea {
  width: 100%;
  padding: 10px 14px;
  border-radius: 20px;
  border: 1px solid #ddd;
  outline: none;
  font-size: 14px;
  color: #444;
}

.modal-box input:focus { border-color: #6a5cff; }

.button-success {
  background: #2e7d32;
  color: #fff;
  border: none;
  padding: 6px 12px;
  border-radius: 6px;
  cursor: pointer;
}
</style>