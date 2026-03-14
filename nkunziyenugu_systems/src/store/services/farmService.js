import api from "../services/api";
import { useToast } from "vue-toastification";
const toast = useToast();

const state = {
  animalList: [],
  farmList: [],
  farm: {},
  animal: {},
  accountIds: localStorage.getItem('account_id'),
  accounts: [],
  animalTypes: [],
  animalBreeds: []
}

const mutations = {
  SET_ANIMAL_LIST(state, payload) {
    state.animalList = payload
  },
  SET_FARM_LIST(state, payload) {
    state.farmList = payload
  },
  SET_DEVICE_LIST(state, payload) {
    state.deviceList = payload
  },
  SET_FARM(state, payload) {
    state.farm = payload
  },
  SET_ANIMAL(state, payload) {
    state.animal = payload
  },
  SET_DEVICE(state, payload) {
    state.device = payload
  },
  SET_ACCOUNT_IDS(state, payload) {
    state.accountIds = payload
  },
  SET_ACCOUNTS(state, payload) {
    state.account = payload
  },
  SET_ANIMAL_TYPES(state, payload) {
    state.animalTypes = payload
  },
  SET_ANIMAL_BREEDS(state, payload) {
    state.animalBreeds = payload
  }
}

const actions = {
  async fetchAnimalList({ commit }, filters) { //Fetch all animals
      try {
        const res = await api.get("farm/animals", 
        { 
          params: {
            search: filters.search,
            status: filters.status
          }
        });
        commit("SET_ANIMAL_LIST", res.data.data);
      } catch (err) {
        toast.error('Failed to load animals');
        console.error(err);
      }
  },

  async getAnimal({ commit }, animalId) { //Fetch single animal
      try {
          const response = await api.get(`/farm/animals/${animalId}`, {
          headers: { Authorization: `Bearer ${localStorage.getItem("token")}` },
          });
          commit("SET_ANIMAL", response.data);
      } catch (error) {
          toast.error("Failed to load animal");
      }
    },

    async getAccounts({ commit }) { //Fetch all accounts available
      try {
            const token = localStorage.getItem("token");
            const response = await api.get("/accounts/available", {
            headers: { Authorization: `Bearer ${token}` },
        });

        if (response.data && response.data.accounts) {
            const accounts = response.data.accounts;
            commit("SET_ACCOUNTS", accounts); // Update accounts
            commit("SET_ACCOUNT_IDS", accounts.map(account => account.id)); // Update accountIds
        } else {
            commit("SET_ACCOUNT_IDS", []);
        }
      } catch (error) {
            toast.error(error.response?.data?.message || "Failed to load accounts.");
            commit("SET_ACCOUNT_IDS", []);
      }
    },
    async getFarms({ commit}, form) { //Fetch all farms available
      try {
          const response = await api.get("/farm/farms", {
                headers: { Authorization: `Bearer ${localStorage.getItem("token")}` },
                params: {
                    account_id: form.account_id
                }
            })

            if(response.data) {
              commit("SET_FARM_LIST", response.data) //Set farm list
            } else {
              commit("SET_FARM_LIST", [])
            }
          }
      catch(error) {
        toast.error(error.response?.data?.message || "Failed to load farms.");
        commit("SET_FARM_LIST", [])
      }
    },  

    async getAnimalTypes({ commit }) { //Fetch all animal types
        try {
          const response = await api.get("/farm/animals/types", {
              headers: { Authorization: `Bearer ${localStorage.getItem("token")}` },
          })
          
          if (response.data) {
              commit("SET_ANIMAL_TYPES", response.data.data) // Set animal types
          } else {
              commit("SET_ANIMAL_TYPES", [])
          }
        }
      catch(error) {
            toast.error(error.response?.data?.message || "Failed to load animal types.");
            commit("SET_ANIMAL_TYPES", [])
        }
    },

    async getAnimalBreeds({ commit }, form) { //Fetch all animal breeds
      try {
        const response = await api.get("/farm/animals/breeds", {
            headers: { Authorization: `Bearer ${localStorage.getItem("token")}` },
            params: {
                animal_type_id: form.animal_type_id
            }
        })

        if(response.data) {
          commit("SET_ANIMAL_BREEDS", response.data) //Set animal breeds
        } else {
          commit("SET_ANIMAL_BREEDS", [])
        }
      }
      catch(error) {
        toast.error(error.response?.data?.message || "Failed to load animal breeds.");
        commit("SET_ANIMAL_BREEDS", [])
      }
    },
}

const getters = {
  animalList: state => state.animalList,
  farmList: state => state.farmList,
  farm: state => state.farm,
  animal: state => state.animal,
  accountIds: state => state.accountIds
}

export default { state, mutations, actions, getters }