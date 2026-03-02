<template>
    <div class="login-wrapper">
    <div class="login-container">
      <div class="login-right">
        <h2>Add New Animal</h2>
        <form @submit.prevent="submit">
          <div class="input-group"> 
            <select v-model="form.account_id" required placeholder="Select account" @change="getFarms()">
              <option value="" disabled selected>Select Account</option>
              <option :value="account.id" v-for="account in accounts" :key="account.id">
                {{ account.name }}
              </option>
            </select>
          </div>

          <div class="input-group"> 
            <select v-model="form.farm_id" required placeholder="Select farm" @change="getAnimalTypes()">
              <option value="" disabled selected>Select Farm</option>
              <option :value="farm.id" v-for="farm in farms" :key="farm.id">
                {{ farm.name }}
              </option>
            </select>
          </div>

          <div class="input-group"> 
            <select v-model="form.animal_type_id" required placeholder="Select animal type">
              <option value="" disabled selected>Select Animal Type</option>
              <option :value="animalType.id" v-for="animalType in animalTypes" :key="animalType.id">
                {{ animalType.name }}
              </option>
            </select>
          </div>

          <div class="input-group">
            <input type="text"  v-model="form.name"  placeholder="Animal name" required />
            <span class="optional">Animal is optional for easy identification.</span>
          </div>

          <div class="input-group">
            <label for="date_of_birth">Estimated Date of Birth:</label>
            <input type="date" v-model="form.date_of_birth" placeholder="Animal date of birth" required />
          </div>

          <div class="input-group">
            <select v-model="form.sex" required>
                <option value="" disabled selected>Select Sex</option>
                <option :value="sex" v-for="sex in animalSexes" :key="sex">
                  {{ sex }}
                </option>
            </select>
          </div>

          <div class="input-group">
            <select v-model="form.breed" required>
                <option value="" disabled selected>Select Breed</option>
                <option :value="breed" v-for="breed in animalBreeds" :key="breed">
                  {{ breed }}
                </option>
            </select>
          </div>

          <div class="input-group">
            <input type="text" v-model="form.description" placeholder="Animal description" required />
          </div>
          
          <div class="row">
            <div class="col-2" >
                <button type="submit" :disabled="loading" class="button-info">
                    {{ loading ? 'Adding animal...' : 'Add Animal' }}
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
  name: "AddFarm",
  data() {
    return {
        loading: false,
        animalTypes: [],
        accounts: [],
        farms: [],
        animalSexes: ['Male', 'Female'],
        animalBreeds: ['Breed 1', 'Breed 2', 'Breed 3'],
        accountIds: [localStorage.getItem('account_id')],  // ← this is probably `[null]`
        form: {
            name: '',
            location: '',
            account_id: null,
            farm_id: null,
            description: '',
            is_active: 1
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

    async submit() {
      await api.post('farm/animals', {
        ...this.form,
      })

      this.$router.push('/Farm/AnimalList')
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
.input-group select {
  width: 100%;
  padding: 12px 15px;
  border-radius: 25px;
  border: 1px solid #ddd;
  outline: none;
  font-size: 14px;
  color: #444;
}
.input-group input[type="date"],
.input-group select {
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