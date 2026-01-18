  <template>
  <div class="p-6">
    <div class="mb-4 flex justify-end">
      <button @click="$router.push('/AddDevice')" class="button-info">
        Add Device
      </button>
    </div>

    <!-- Device List Table -->
    <table class="min-w-full border border-gray-200">
      <thead class="bg-gray-100">
        <tr>
          <th>#</th>
          <th>Name</th>
          <th>Device ID</th>
          <th>Created At</th>
          <th>Updated At</th>
          <th>Action</th>
        </tr>
      </thead>

      <tbody>
        <tr v-for="device in devices" :key="device.id">
          <td>{{ (devices.indexOf(device) + 1) }}</td>
          <td>{{ device.name }}</td>
            <td>{{ device.device_id }}</td>
          <td>{{ formatDate(device.created_at) }}</td>
          <td>{{ formatDate(device.updated_at) }}</td>
          <td>
            <button @click="DeviceLogs(device)" class="button-info">
              <i class="bi bi-eye"></i>
            </button>
          </td>
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
  name: "DeviceList",
  data() {
    return {
      devices: [
        // Sample device data
        { id: 1, name: "Device 1", device_id: 1, created_at: "2023-01-01", updated_at: "2023-01-02" },
        { id: 2, name: "Device 2", device_id: 2, created_at: "2023-02-01", updated_at: "2023-02-02" },
      ],
    };
  },
  mounted() {
  },
  methods: {
    formatDate(dateStr) {
        const date = new Date(dateStr);
        const year   = date.getFullYear();
        const month  = String(date.getMonth() + 1).padStart(2, '0');
        const day    = String(date.getDate()).padStart(2, '0');
        const hours  = String(date.getHours()).padStart(2, '0');
        const minutes= String(date.getMinutes()).padStart(2, '0');
        const seconds= String(date.getSeconds()).padStart(2, '0');
        return `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`;
    },
    fetchDevices() {
      api
        .get("/devices")
        .then((response) => {
          this.devices = response.data.data;
        })
        .catch((error) => {
          console.error(error);
          toast.error(error.response?.data?.message || "Failed to load devices.");
        });
    },
    DeviceLogs(device) {
      this.$router.push({ name: 'DeviceLogs', params: { id: device.id } });
    },
  }
};
</script>
<style scoped>
table {
  background: linear-gradient(135deg, #27253f, #605a6d);
  color: #fff;
  width: 100%;
  border-collapse: collapse;
}

th,
td {
  padding: 10px;
  border-bottom: 1px solid #fff;
  text-align: left;
}
</style>