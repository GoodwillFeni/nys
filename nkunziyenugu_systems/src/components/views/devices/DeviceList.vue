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
          <th>Account</th>
          <th>Is Active</th>
          <th>Has Alarm</th>
          <th>Created At</th>
          <th>Last seen</th>
          <th>Action</th>
        </tr>
      </thead>

      <tbody>
        <tr v-for="device in devices" :key="device.id">
          <td>{{ (devices.indexOf(device) + 1) }}</td>
          <td>{{ device.name }} <i class="bi bi-pencil button-info" @click="EditDeviceName(device)"></i></td>
          <td>{{ device.device_uid }}</td>
          <td>{{ device.account?.name || device.account_name || '-' }}</td> 
          <td>{{ device.is_active ? 'Yes' : 'No' }}</td>
          <td>{{ device.has_alarm ? 'Yes' : 'No' }}</td>
          <td>{{ formatDate(device.created_at) }}</td>
          <td>{{ formatDate(device.last_seen_at) }}</td>
          <td>
            <button @click="DeviceLogs(device)" class="button-info">
              <i class="bi bi-eye"></i>
            </button>
            <button @click="deleteDevice(device)" class="button-danger">
              <i class="bi bi-trash"></i>
            </button>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
<script>
import { useToast } from "vue-toastification";
const toast = useToast();

export default {
  name: "DeviceList",
  data() {
    return {
      devices: [],
    };
  },
  mounted() {
    //Get device list
    this.$store.dispatch("getDeviceList", {
        account_id: localStorage.getItem("account_id"),
        });
        this.$store.subscribe((mutation) => {
          if (mutation.type === "SET_DEVICE_LIST") {
            this.devices = mutation.payload;
          }
        });
    //End get device list
  },
  methods: {
    formatDate(dateStr) {
      const date = new Date(dateStr);
      const year = date.getFullYear();
      const month = String(date.getMonth() + 1).padStart(2, '0');
      const day = String(date.getDate()).padStart(2, '0');
      const hours = String(date.getHours()).padStart(2, '0');
      const minutes = String(date.getMinutes()).padStart(2, '0');
      const seconds = String(date.getSeconds()).padStart(2, '0');
      return `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`;
    },
    
    DeviceLogs(device) {
      this.$router.push({ name: 'DeviceLogs', params: { id: device.id } });
    },

    EditDeviceName(device) {
      toast.info('Edit device name functionality will be implemented soon.', { device });
    },

    deleteDevice(device) {
      toast.info('Delete device functionality will be implemented soon.', { device });
    }
  }
};
</script>
<style scoped>
</style>