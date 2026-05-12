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
    // Accepts arbitrary query params. The api client already attaches the
    // Authorization header and X-Account-ID via its interceptor, so passing
    // them again here would duplicate. Callers commonly pass {filter: 'outdated_firmware'}
    // when the Devices Dashboard navigates here with a card click.
    async getDeviceList({ commit }, params = {}) {
        try {
          const response = await api.get("/devices", { params });
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