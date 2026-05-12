  <template>
  <div class="p-6">
    <div class="mb-4 flex justify-end" style="display:flex; gap:8px; justify-content:flex-end; align-items:center;">
      <button @click="$router.push({ name: 'DeviceDashboard' })" class="button-info">
        <i class="bi bi-speedometer2"></i> Dashboard
      </button>
      <button @click="$router.push('/AddDevice')" class="button-info">
        Add Device
      </button>
    </div>

    <!-- Filter banner: shown when DeviceList was reached via a Dashboard card click. -->
    <div v-if="activeFilter" class="filter-banner">
      <span>
        Showing devices matching: <strong>{{ filterLabel(activeFilter) }}</strong>
      </span>
      <button class="button-warning button-sm" @click="clearFilter">Clear filter</button>
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
          <th>Firmware</th>
          <th>Balance</th>
          <th>Bal. checked</th>
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

          <!-- Heartbeat-driven columns. latest_heartbeat is null until the
               device has reported at least once. Warning chip when this row
               is in scope for the active dashboard filter. -->
          <td>
            {{ device.latest_heartbeat?.firmware_version ?? '-' }}
            <span v-if="activeFilter === 'outdated_firmware' && device.latest_heartbeat?.firmware_version"
                  class="chip chip-warn" title="Not the current release">
              outdated
            </span>
          </td>
          <td>
            {{ device.latest_heartbeat?.balance ?? '-' }}
            <span v-if="activeFilter === 'low_balance' && device.latest_heartbeat?.balance"
                  class="chip chip-danger" title="Below threshold">
              low
            </span>
          </td>
          <td>{{ device.latest_heartbeat?.balance_ts ? relativeTime(device.latest_heartbeat.balance_ts) : '-' }}</td>

          <td>{{ formatDate(device.created_at) }}</td>
          <td>
            {{ formatDate(device.last_seen_at) }}
            <span v-if="activeFilter === 'not_reporting'" class="chip chip-muted" title="Stale">
              quiet
            </span>
          </td>
          <td>
            <button @click="DeviceLogs(device)" class="button-info">
              <i class="bi bi-eye"></i>
            </button>
            <button @click="deleteDevice(device)" class="button-danger">
              <i class="bi bi-trash"></i>
            </button>
          </td>
        </tr>

        <tr v-if="!devices.length">
          <td colspan="12" style="text-align:center; color:rgba(255,255,255,0.5);">No devices match the current filter.</td>
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
  computed: {
    // Read directly from the route so changes (e.g. dashboard card click
    // navigating with a new ?filter=) re-render the banner and re-fetch.
    activeFilter() {
      return this.$route.query.filter || null;
    },
  },
  watch: {
    activeFilter() {
      this.fetchDevices();
    },
  },
  mounted() {
    this.$store.subscribe((mutation) => {
      if (mutation.type === "SET_DEVICE_LIST") {
        this.devices = mutation.payload;
      }
    });
    this.fetchDevices();
  },
  methods: {
    fetchDevices() {
      const params = {};
      if (this.activeFilter) params.filter = this.activeFilter;
      this.$store.dispatch("getDeviceList", params);
    },

    clearFilter() {
      // Pop the query string off the URL; the watcher re-fetches with no filter.
      this.$router.replace({ name: 'DevicesList' });
    },

    filterLabel(key) {
      switch (key) {
        case 'outdated_firmware': return 'Outdated firmware';
        case 'low_balance':       return 'Low balance';
        case 'not_reporting':     return 'Not reporting';
        default:                  return key;
      }
    },

    formatDate(dateStr) {
      if (!dateStr) return '-';
      const date = new Date(dateStr);
      if (Number.isNaN(date.getTime())) return '-';
      const year = date.getFullYear();
      const month = String(date.getMonth() + 1).padStart(2, '0');
      const day = String(date.getDate()).padStart(2, '0');
      const hours = String(date.getHours()).padStart(2, '0');
      const minutes = String(date.getMinutes()).padStart(2, '0');
      const seconds = String(date.getSeconds()).padStart(2, '0');
      return `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`;
    },

    // "5h ago" — accepts epoch seconds (number) or ISO 8601 string.
    relativeTime(value) {
      if (value == null) return '-';
      const epoch = typeof value === 'number' ? value * 1000 : new Date(value).getTime();
      if (!Number.isFinite(epoch)) return '-';
      const diff = Math.max(0, Math.floor((Date.now() - epoch) / 1000));
      if (diff < 60)    return 'just now';
      if (diff < 3600)  return `${Math.floor(diff / 60)}m ago`;
      if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`;
      return `${Math.floor(diff / 86400)}d ago`;
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
.filter-banner {
  display: flex;
  justify-content: space-between;
  align-items: center;
  background: rgba(255, 167, 38, 0.15);
  border-left: 3px solid #ffa726;
  color: #fff;
  padding: 10px 14px;
  border-radius: 6px;
  margin-bottom: 12px;
  font-size: 13px;
}

.chip {
  display: inline-block;
  margin-left: 6px;
  padding: 1px 7px;
  border-radius: 10px;
  font-size: 10px;
  text-transform: uppercase;
  font-weight: 600;
}
.chip-warn   { background: rgba(255, 167, 38, 0.2); color: #ffb74d; }
.chip-danger { background: rgba(239, 83, 80, 0.2); color: #ef9a9a; }
.chip-muted  { background: rgba(158, 158, 158, 0.2); color: #cfcfcf; }
</style>
