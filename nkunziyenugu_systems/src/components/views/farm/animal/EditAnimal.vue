<template>
    <div class="login-wrapper">
    <div class="login-container">
      <div class="login-right">
        <h2>Update Animal</h2>
        <form @submit.prevent="submit">
          <div class="row">
            <div class="input-group col"> 
              <select v-model="form.account_id" required placeholder="Select account" @change="getFarms()">
                <option value="" disabled selected>Select Account Name </option>
                <option :value="account.id" v-for="account in accounts" :key="account.id">
                  {{ account.name }}
                </option>
              </select>
            </div>

            <div class="input-group col"> 
              <select v-model="form.farm_id" required placeholder="Select farm" @change="getAnimalTypes()">
                <option value="" disabled selected>Select Farm to add animal</option>
                <option :value="farm.id" v-for="farm in farms" :key="farm.id">
                  {{ farm.name }}
                </option>
              </select>
            </div>
          </div>

          <div class="row">
            <div class="input-group col" 
                @mouseenter="showTooltip = true" 
                @mouseleave="showTooltip = false">
                <input type="number" v-model.number="form.animal_tag" min="1" max="100000" placeholder="Enter ear tag number" required />   
                <!-- Tooltip -->
                  <span class="tooltip-text">Ear tag number is a unique identifier for each animal.</span>
            </div>

            <div class="input-group col"> 
              <select v-model="form.animal_type_id" required placeholder="Select animal type" @change="getAnimalBreeds()">
                <option value="" disabled selected>Select Animal Type</option>
                <option :value="animalType.id" v-for="animalType in animalTypes" :key="animalType.id">
                  {{ animalType.name }}
                </option>
              </select>
            </div>
          </div>

          <div class="row">
            <div class="input-group col">
              <select v-model="form.sex" required>
                  <option value="" disabled selected>Select Animal Sex</option>
                  <option :value="sex" v-for="sex in animalSexes" :key="sex">
                    {{ sex }}
                  </option>
              </select>
            </div>

            <div class="input-group col">
              <select v-model="form.breed_id" required>
                  <option value="" disabled selected>Select Animal Breed</option>
                  <option :value="breed.id" v-for="breed in animalBreeds" :key="breed">
                    {{ breed.breed_name }}
                  </option>
              </select>
            </div>
          </div>

          <div class="input-group col">
            <label for="date_of_birth">Date of Birth:</label>
            <input type="date" v-model="form.date_of_birth"  required />
            <span style="font-size: 12px; color: #888;">Date of birth is required for accurate age tracking, if not sure use estimated data.</span>
          </div>
          <div class="input-group">
            <input type="text" v-model="form.description" placeholder="Enter animal description for easy identification" required />
          </div>
          
          <div class="row">
            <div class="col-2" >
                <button type="submit" :disabled="loading" class="button-info">
                    {{ loading ? 'Updating ...' : 'Update' }}
                </button>
            </div>
            <div class="col-2">
                <button @click="$router.push('/Farm/AnimalList')" class="button-warning">
                    Back
                </button>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script>
import api from '@/store/services/api';
import { useToast } from "vue-toastification";
const toast = useToast();

export default {
  name: "EditAnimal",
  data() {
    return {
        loading: false,
        showTooltip: false,
        animalTypes: [],
        accounts: [],
        farms: [],
        animalSexes: ['Male', 'Female'],
        animalBreeds: [],
        accountIds: [localStorage.getItem('account_id')],
        form: {
            animal_tag: null,
            name: '',
            account_id: '',
            animal_type_id: '',
            date_of_birth: '',
            sex: '',
            breed_id: '',
            farm_id: '',
            description: '',
        }
    }
  },

  mounted() {
    this.getAnimal()
  },
  methods: {
    async getAnimal() { //Fetch single animal
         const animalId = this.$route.params.id; // Get the animalId from the route
          this.$store.dispatch('getAnimal', animalId); // Dispatch the action from the store
          this.$store.subscribe((mutation) => {
            if (mutation.type === 'SET_ANIMAL') {
                const animal = mutation.payload;
                this.form = {
                    animal_tag: animal.animal_tag,
                    account_id: animal.account_id,
                    animal_type_id: animal.animal_type_id,
                    date_of_birth: animal.date_of_birth,
                    sex: animal.sex,
                    breed_id: animal.breed_id,
                    farm_id: animal.farm_id,
                    description: animal.notes,
                };

                this.getAccounts();
                this.getFarms();
                this.getAnimalTypes();
                this.getAnimalBreeds();
            } 
          });
        },
    async getAccounts() {
      this.$store.dispatch('getAccounts'); // Fetch accounts from the store
      this.$store.subscribe((mutation) => {
        if (mutation.type === 'SET_ACCOUNTS') {
          this.accounts = mutation.payload;
        }
      });
    },
    async getFarms() {
      this.$store.dispatch('getFarms', this.form.account_id); // Fetch farms from the store
      this.$store.subscribe((mutation) => {
        if (mutation.type === 'SET_FARM_LIST') {
          this.farms = mutation.payload;

        }
      });
    },
    getAnimalTypes() {
      this.$store.dispatch('getAnimalTypes'); // Fetch animal types from the store
      this.$store.subscribe((mutation) => {
        if (mutation.type === 'SET_ANIMAL_TYPES') {
          this.animalTypes = mutation.payload;
        }
      });
    },

    getAnimalBreeds() {
      this.$store.dispatch('getAnimalBreeds', this.form.animal_type_id); // Fetch animal breeds from the store
      this.$store.subscribe((mutation) => {
        if (mutation.type === 'SET_ANIMAL_BREEDS') {
          this.animalBreeds = mutation.payload;
        }
      });
    },

    async submit() {
      try {
        this.loading = true;
        console.log(this.form)
        await api.put('farm/animals/' + this.$route.params.id, {
          ...this.form,
        });

        toast.success('Animal updated successfully!');
        this.$router.push('/Farm/AnimalList');
      } catch (error) {
        console.log(error.response?.data?.message)
        toast.error(error.response?.data?.message || 'Failed to update animal');
      } finally {
        this.loading = false;
      }
    }
  }
}
</script>

<style scoped>
/* SAME STYLING AS SIGNUP */
.login-wrapper {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 10px;
  background: linear-gradient(135deg, #27253f, #605a6d);
}

.login-container {
  width: 900px;
  max-width: 95%;
  height: 650px;
  background: #ffffff;
  display: flex;
  border-radius: 12px;
  overflow: hidden;
  box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
}

/* LEFT */
.login-left {
  flex: 1;
  background: linear-gradient(135deg, #27253f, #605a6d);
  color: #ffffff;
  padding: 50px;
  display: flex;
  flex-direction: column;
  justify-content: center;
}

.login-left h1 {
  font-size: 32px;
  margin-bottom: 15px;
}

.login-left p {
  line-height: 1.6;
  opacity: 0.9;
}

/* RIGHT */
.login-right {
  flex: 1;
  padding: 50px;
  display: flex;
  flex-direction: column;
  justify-content: center;
}

.login-right h2 {
  text-align: center;
  margin-bottom: 25px;
  color: #6a5cff;
}

/* INPUTS */
.input-group {
  margin-bottom: 15px;
}

label {
  display: block;
  margin-bottom: 5px;
  font-weight: 16;
  color: #333;
}

.input-group input,
.input-group select,
.input-group textarea {
  width: 100%;
  padding: 12px 15px;
  border-radius: 25px;
  border: 1px solid #ddd;
  outline: none;
  font-size: 14px;
  color: #444;
}

.input-group input:focus,
.input-group select:focus {
  border-color: #6a5cff;
}

/* DATE INPUT */
input[type="date"] {
  width: 100%;
  padding: 12px 15px;
  border-radius: 25px;
  border: 1px solid #ddd;
  outline: none;
  font-size: 14px;
  color: #444;
}

input[type="date"]:focus {
  border-color: #6a5cff;
}

/* TOOLTIP */
.input-group {
  position: relative;
  display: inline-block;
}

.tooltip-text {
  visibility: hidden;
  width: max-content;
  background-color: #00828b;
  color: #fff;
  text-align: center;
  padding: 5px 8px;
  border-radius: 4px;
  font-size: 14px;

  position: absolute;
  top: -30px; /* adjust as needed */
  left: 0;
  white-space: nowrap;
  z-index: 10;

  /* optional smooth fade */
  opacity: 0;
  transition: opacity 0.2s;
}

.input-group:hover .tooltip-text {
  visibility: visible;
  opacity: 1;
}
/* RESPONSIVE */
@media (max-width: 768px) {
  .login-container {
    flex-direction: column;
    height: auto;
  }

  .login-left {
    padding: 30px;
    text-align: center;
  }
}

/* BUTTON */
.col-6 {
  display: flex;
  justify-content: center;
}
</style>