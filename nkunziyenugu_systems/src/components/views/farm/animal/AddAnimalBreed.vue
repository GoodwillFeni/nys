<template>
    <div class="login-wrapper">
    <div class="login-container">
      <div class="login-right">
        <h2>Add New Animal Breed</h2>
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
            <div class="input-group col"> 
              <select v-model="form.animal_type_id" required placeholder="Select animal type" @change="getAnimalBreeds()">
                <option value="" disabled selected>Select Animal Type</option>
                <option :value="animalType.id" v-for="animalType in animalTypes" :key="animalType.id">
                  {{ animalType.name }}
                </option>
              </select>
            </div>
            <div class="input-group col">
              <input type="text" v-model="form.animal_breed_name" placeholder="Name of the animal breed" required />
            </div>
          </div>

          <div class="input-group">
            <input type="text" v-model="form.description" placeholder="Enter animal description for easy identification" required />
          </div>
          
          <div class="row">
            <div class="col-3" >
                <button type="submit" :disabled="loading" class="button-info">
                    {{ loading ? 'Adding animal breed...' : 'Add Animal Breed' }}
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
  name: "AddAnimalBreed",
  data() {
    return {
        loading: false,
        animalTypes: [],
        accounts: [],
        farms: [],
        accountIds: [localStorage.getItem('account_id')],
        form: {
            animal_breed_name: '',
            account_id: '',
            animal_type_id: '',
            farm_id: '',
            description: '',
        }
    }
  },

  mounted() {
    this.getAccounts()
  },
  methods: {
    async getAccounts() {
      try {
            const token = localStorage.getItem("token");
            const response = await api.get("/accounts/available", {
            headers: { Authorization: `Bearer ${token}` },
        });

        if (response.data && response.data.accounts) {
            this.accounts = response.data.accounts;
            this.accountIds = this.accounts.map(account => account.id);
        } else {
            this.accounts = [];
        }
      } catch (error) {
            toast.error(error.response?.data?.message || "Failed to load accounts.");
            this.accounts = [];
      }
    },

    getFarms() {
        api.get("/farm/farms", {
            headers: { Authorization: `Bearer ${localStorage.getItem("token")}` },
            params: {
                account_id: this.form.account_id
            }
        }).then(response => {
            if (response.data) {
                this.farms = response.data;
                console.log(this.farms)
            } else {
                this.farms = [];
            }
        }).catch(error => {
            toast.error(error.response?.data?.message || "Failed to load farms.");
            this.farms = [];
        });
    },

    getAnimalTypes() {
        api.get("/farm/animals/types", {
            headers: { Authorization: `Bearer ${localStorage.getItem("token")}` },
        })
        
        .then(response => {
            if (response.data) {
                this.animalTypes = response.data.data
                console.log(this.animalTypes)
            } else {
                this.animalTypes = [];
            }
        })
        
        .catch(error => {
            toast.error(error.response?.data?.message || "Failed to load animal types.");
            this.animalTypes = [];
        })
    },

    getAnimalBreeds() {
        api.get("/farm/animals/breeds", {
            headers: { Authorization: `Bearer ${localStorage.getItem("token")}` },
            params: {
                animal_type_id: this.form.animal_type_id
            }
        })
        
        .then(response => {
            if (response.data) {
                this.animalBreeds = response.data
                console.log(this.animalBreeds)
            } else {
                this.animalBreeds = [];
            }
        })
        
        .catch(error => {
            toast.error(error.response?.data?.message || "Failed to load animal breeds.");
            this.animalBreeds = [];
        })
    },

    async submit() {
      try {
        this.loading = true;

        await api.post('farm/animals/breeds', {
          account_id: this.form.account_id,
          animal_type_id: this.form.animal_type_id,
          breed_name: this.form.animal_breed_name,
          description: this.form.description,
        });

        toast.success('Animal breed added successfully!');
        this.$router.back();
      } catch (error) {
        console.log(error.response?.data?.message)
        toast.error(error.response?.data?.message || 'Failed to add animal breed');
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