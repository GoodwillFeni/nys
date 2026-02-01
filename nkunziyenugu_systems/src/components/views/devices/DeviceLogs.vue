<template>
      <div class="">
      <form @submit.prevent="applyFilters">
        <!-- Center: Filters -->
         <div class="filters-row">
         <div class="form-group filter-field">
          <label for="type">Type</label>
            <select v-model="filters.type" class="form-control" id="type">
              <option value="">All Types</option>
              <option value="heartbeat">Heartbeat</option>
              <option value="location">Location</option>
              <option value="sensor">Sensor</option>
            </select>
         </div>

         <div class="form-group filter-field">
          <label for="from">From</label>
          <input v-model="filters.from" type="datetime-local" class="form-control" placeholder="From" />
         </div>

         <div class="form-group filter-field">
          <label for="to">To</label>
          <input v-model="filters.to" type="datetime-local" class="form-control" placeholder="To" />
         </div>
         
         <div id="action-buttons"> 
            <div class="form-group">
              <button type="submit" class="button-info">Apply</button>
            </div>
            <div class="form-group">
              <button type="button" class="button-warning" @click="resetFilters">Reset</button>
            </div>
            <div class="form-group">
              <button type="button" class="button-danger" @click="$router.back()">Back</button>
            </div>
        </div>
        </div>
      </form>


    <div v-if="loading" class="text-white">Loading logs...</div>

    <table v-else class="min-w-full border border-gray-200">
      <thead class="bg-gray-100">
        <tr>
          <th>#</th>
          <th>Type</th>
          <th>Device Time</th>
          <th>Lat</th>
          <th>Lng</th>
          <th>Received At</th>
          <th>Payload</th>
        </tr>
      </thead>
      <tbody>
        <template v-for="(log, idx) in logs" :key="log.id">
          <tr>
            <td>{{ (pagination.page - 1) * pagination.per_page + idx + 1 }}</td>
            <td>{{ log.type }}</td>
            <td>{{ formatDate(log.device_timestamp) }}</td>
            <td>{{ log.lat ?? '-' }}</td>
            <td>{{ log.lng ?? '-' }}</td>
            <td>{{ formatDate(log.created_at) }}</td>
            <td>
              <button class="button-info" @click="togglePayload(log.id)">
                {{ expandedPayloadId === log.id ? 'Hide' : 'View' }}
              </button>
            </td>
          </tr>
          <tr v-show="expandedPayloadId === log.id">
            <td colspan="7">
              <pre class="payload-pre">{{ prettyJson(log.payload) }}</pre>
            </td>
          </tr>
        </template>

        <tr v-if="logs.length === 0">
          <td colspan="7">No logs found.</td>
        </tr>
      </tbody>
    </table>

    <div class="mt-4 flex items-center justify-between">
      <div class="text-white">
        Page {{ pagination.page }} of {{ pagination.last_page }} ({{ pagination.total }} total)
      </div>
      <div class="flex gap-2">
        <button class="button-info" :disabled="pagination.page <= 1" @click="goToPage(pagination.page - 1)">
          Prev
        </button>
        <button class="button-info" :disabled="pagination.page >= pagination.last_page" @click="goToPage(pagination.page + 1)">
          Next
        </button>
      </div>
    </div>
  </div>
</template>

<script>
import api from "@/store/services/api";
import { useToast } from "vue-toastification";
const toast = useToast();

export default {
  name: "DeviceLogs",
  props: {
    id: {
      type: [String, Number],
      required: true,
    },
  },
  data() {
    return {
      loading: false,
      logs: [],
      expandedPayloadId: null,
      filters: {
        type: "",
        from: "",
        to: "",
      },
      pagination: {
        page: 1,
        per_page: 20,
        total: 0,
        last_page: 1,
      },
    };
  },
  mounted() {
    this.fetchLogs();
  },
  methods: {
    formatDate(dateStr) {
      if (!dateStr) return "-";
      const date = new Date(dateStr);
      if (Number.isNaN(date.getTime())) return "-";
      const year = date.getFullYear();
      const month = String(date.getMonth() + 1).padStart(2, "0");
      const day = String(date.getDate()).padStart(2, "0");
      const hours = String(date.getHours()).padStart(2, "0");
      const minutes = String(date.getMinutes()).padStart(2, "0");
      const seconds = String(date.getSeconds()).padStart(2, "0");
      return `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`;
    },
    prettyJson(payload) {
      try {
        return JSON.stringify(payload, null, 2);
      } catch (e) {
        return String(payload);
      }
    },
    togglePayload(id) {
      this.expandedPayloadId = this.expandedPayloadId === id ? null : id;
    },
    applyFilters() {
      this.pagination.page = 1;
      this.fetchLogs();
    },
    resetFilters() {
      this.filters.type = "";
      this.filters.from = "";
      this.filters.to = "";
      this.pagination.page = 1;
      this.fetchLogs();
    },
    goToPage(page) {
      this.pagination.page = page;
      this.fetchLogs();
    },
    fetchLogs() {
      this.loading = true;
      this.expandedPayloadId = null;

      api
        .get(`/devices/${this.id}/logs`, {
          params: {
            type: this.filters.type || undefined,
            from: this.filters.from || undefined,
            to: this.filters.to || undefined,
            page: this.pagination.page,
            per_page: this.pagination.per_page,
          },
        })
        .then((response) => {
          const data = response.data?.data;
          this.logs = data?.items || [];
          this.pagination.page = data?.pagination?.page ?? 1;
          this.pagination.per_page = data?.pagination?.per_page ?? this.pagination.per_page;
          this.pagination.total = data?.pagination?.total ?? 0;
          this.pagination.last_page = data?.pagination?.last_page ?? 1;
        })
        .catch((error) => {
          console.error(error);
          toast.error(error.response?.data?.message || "Failed to load device logs.");
        })
        .finally(() => {
          this.loading = false;
        });
    },
  },
};
</script>

<style scoped>
.payload-pre {
  background: rgba(0, 0, 0, 0.2);
  color: #fff;
  padding: 10px;
  border-radius: 6px;
  overflow: auto;
  max-height: 300px;
}
form {
  margin-bottom: 20px;
  margin-top: 10px;
  float: right;
  width: auto;
}

.filters-row {
  display: grid;
  grid-template-columns: repeat(3, 220px) auto;
  align-items: end;
  gap: 5px;
  width: max-content;
}

@media (max-width: 1000px) {
  .filters-row {
    grid-template-columns: 1fr;
    width: 100%;
  }
}

.filters-row .form-control {
  width: 100%;
}

#action-buttons {
  display: flex;
  gap: 1px;
  align-items: flex-end;
  flex: 0 0 auto;
}

#action-buttons .form-group {
  margin-bottom: 0;
}
</style>