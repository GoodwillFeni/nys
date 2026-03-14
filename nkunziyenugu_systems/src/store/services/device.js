import api from "../services/api";
import { useToast } from "vue-toastification";
const toast = useToast();

const state = {
    deviceList: [],
    device: {},
};

const mutations = {
    SET_DEVICE_LIST(state, payload) {
      state.deviceList = payload;
    },
    SET_DEVICE(state, payload) {
      state.device = payload;
    },
  };

  const actions = {
    async getDeviceList({ commit }, accountId) {
        try {
        const response = await api.get("/devices", {
           headers: { Authorization: `Bearer ${localStorage.getItem("token")}` },
           params: {
             account_id: accountId,
           },
         })
        commit("SET_DEVICE_LIST", response.data.data);
        }
        catch (error) {
          toast.error(error.response?.data?.message || "Failed to load devices.");
          commit("SET_DEVICE_LIST", []);
        }
    },

    
    async getDevice({ commit }, id) {
      const response = await api.get(`/devices/${id}`);
      commit("SET_DEVICE", response.data);
    },
  };

  const getters = {
    deviceList: state => state.deviceList,
    device: state => state.device,
  };

  export default { state, mutations, actions, getters }