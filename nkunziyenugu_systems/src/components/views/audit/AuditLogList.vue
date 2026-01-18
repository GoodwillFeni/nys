<template>
  <div class="p-6">
    <!-- <h2 class="mb-4">Audit Logs</h2> -->
    <!-- Filters -->
    <div class="filters mb-4">
      <div class="filter-row">
        <div class="filter-group">
          <label>Action:</label>
          <select v-model="filters.action" @change="applyFilters">
            <option value="">All Actions</option>
            <option value="created">Created</option>
            <option value="updated">Updated</option>
            <option value="deleted">Deleted</option>
          </select>
        </div>

        <div class="filter-group">
          <label>Model Type:</label>
          <select v-model="filters.model_type" @change="applyFilters">
            <option value="">All Models</option>
            <option value="App\Models\User">User</option>
            <option value="App\Models\Account">Account</option>
          </select>
        </div>

        <div class="filter-group">
          <label>Date From:</label>
          <input type="date" v-model="filters.date_from" @change="applyFilters" />
        </div>

        <div class="filter-group">
          <label>Date To:</label>
          <input type="date" v-model="filters.date_to" @change="applyFilters" />
        </div>

        <div class="filter-group">
          <button @click="clearFilters" class="button-warning">Clear Filters</button>
        </div>
      </div>
    </div>

    <!-- Audit Logs Table -->
    <div class="table-container">
      <table class="audit-table">
        <thead>
          <tr>
            <th>#</th>
            <th>Date & Time</th>
            <th>User</th>
            <th>Action</th>
            <th>Model</th>
            <th>Description</th>
            <th>IP Address</th>
            <th>Details</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(log, index) in logs" :key="log.id">
            <td>{{ (currentPage - 1) * perPage + index + 1 }}</td>
            <td>{{ formatDateTime(log.created_at) }}</td>
            <td>{{ log.user ? `${log.user.name} ${log.user.surname}` : 'System' }}</td>
            <td>
              <span :class="['action-badge', log.action]">
                {{ log.action.toUpperCase() }}
              </span>
            </td>
            <td>{{ getModelName(log.model_type) }}</td>
            <td>{{ log.description }}</td>
            <td>{{ log.ip_address || 'N/A' }}</td>
            <td>
              <button @click="showDetails(log)" class="button-info">
                <i class="bi bi-eye"></i>
              </button>
            </td>
          </tr>

          <tr v-if="logs.length === 0">
            <td colspan="8" class="text-center py-4">
              No audit logs found.
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div class="pagination" v-if="totalPages > 1">
      <button 
        @click="changePage(currentPage - 1)" 
        :disabled="currentPage === 1"
        class="button-info"
      >
        Previous
      </button>
      <span class="page-info">
        Page {{ currentPage }} of {{ totalPages }} (Total: {{ total }})
      </span>
      <button 
        @click="changePage(currentPage + 1)" 
        :disabled="currentPage === totalPages"
        class="button-info"
      >
        Next
      </button>
    </div>

    <!-- Details Modal -->
    <div v-if="selectedLog" class="modal-overlay" @click="closeModal">
      <div class="modal-content" @click.stop>
        <div class="modal-header">
          <h3>Audit Log Details</h3>
          <button @click="closeModal" class="close-btn">&times;</button>
        </div>
        <div class="modal-body">
          <div class="detail-row">
            <strong>Date:</strong> {{ formatDateTime(selectedLog.created_at) }}
          </div>
          <div class="detail-row">
            <strong>User:</strong> {{ selectedLog.user ? `${selectedLog.user.name} ${selectedLog.user.surname}` : 'System' }}
          </div>
          <div class="detail-row">
            <strong>Action:</strong> 
            <span :class="['action-badge', selectedLog.action]">
              {{ selectedLog.action.toUpperCase() }}
            </span>
          </div>
          <div class="detail-row">
            <strong>Model:</strong> {{ getModelName(selectedLog.model_type) }} (ID: {{ selectedLog.model_id }})
          </div>
          <div class="detail-row">
            <strong>Description:</strong> {{ selectedLog.description }}
          </div>
          <div class="detail-row" v-if="selectedLog.old_values">
            <strong>Old Values:</strong>
            <pre class="values-box">{{ JSON.stringify(selectedLog.old_values, null, 2) }}</pre>
          </div>
          <div class="detail-row" v-if="selectedLog.new_values">
            <strong>New Values:</strong>
            <pre class="values-box">{{ JSON.stringify(selectedLog.new_values, null, 2) }}</pre>
          </div>
          <div class="detail-row">
            <strong>IP Address:</strong> {{ selectedLog.ip_address || 'N/A' }}
          </div>
          <div class="detail-row" v-if="selectedLog.user_agent">
            <strong>User Agent:</strong> {{ selectedLog.user_agent }}
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import api from "@/store/services/api";
import { useToast } from "vue-toastification";
const toast = useToast();

export default {
  name: "AuditLogList",
  data() {
    return {
      logs: [],
      filters: {
        action: "",
        model_type: "",
        date_from: "",
        date_to: "",
      },
      currentPage: 1,
      perPage: 20,
      total: 0,
      totalPages: 1,
      selectedLog: null,
      loading: false
    };
  },

  mounted() {
    this.getLogs();
  },

  watch: {
    // Watch for active account changes
    '$store.state.auth.activeAccount': {
      handler() {
        this.getLogs();
      },
      deep: true
    }
  },

  methods: {
    async getLogs() {
      this.loading = true;
      try {
        const params = {
          page: this.currentPage,
          per_page: this.perPage,
        };

        // Add filters
        if (this.filters.action) params.action = this.filters.action;
        if (this.filters.model_type) params.model_type = this.filters.model_type;
        if (this.filters.date_from) params.date_from = this.filters.date_from;
        if (this.filters.date_to) params.date_to = this.filters.date_to;

        const response = await api.get("/audit-logs", { params });

        if (response.data.status === "success") {
          this.logs = response.data.data || [];
          this.total = response.data.pagination?.total || 0;
          this.totalPages = response.data.pagination?.last_page || 1;
        }
      } catch (error) {
        console.error("Error fetching audit logs:", error);
        toast.error(error.response?.data?.message || "Failed to load audit logs.");
        this.logs = [];
      } finally {
        this.loading = false;
      }
    },

    applyFilters() {
      this.currentPage = 1;
      this.getLogs();
    },

    clearFilters() {
      this.filters = {
        action: "",
        model_type: "",
        date_from: "",
        date_to: "",
      };
      this.currentPage = 1;
      this.getLogs();
    },

    changePage(page) {
      if (page >= 1 && page <= this.totalPages) {
        this.currentPage = page;
        this.getLogs();
      }
    },

    showDetails(log) {
      this.selectedLog = log;
    },

    closeModal() {
      this.selectedLog = null;
    },

    formatDateTime(dateStr) {
      if (!dateStr) return "N/A";
      const date = new Date(dateStr);
      const year = date.getFullYear();
      const month = String(date.getMonth() + 1).padStart(2, "0");
      const day = String(date.getDate()).padStart(2, "0");
      const hours = String(date.getHours()).padStart(2, "0");
      const minutes = String(date.getMinutes()).padStart(2, "0");
      const seconds = String(date.getSeconds()).padStart(2, "0");
      return `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`;
    },

    getModelName(modelType) {
      if (!modelType) return "N/A";
      const parts = modelType.split("\\");
      return parts[parts.length - 1];
    }
  }
};
</script>

<style scoped>
.p-6 {
  padding: 24px;
}

h2 {
  color: #fff;
  margin-bottom: 20px;
}

.filters {
  background: rgba(255, 255, 255, 0.1);
  padding: 15px;
  border-radius: 8px;
  margin-bottom: 20px;
}

.filter-row {
  display: flex;
  gap: 15px;
  flex-wrap: wrap;
  align-items: flex-end;
}

.filter-group {
  display: flex;
  flex-direction: column;
  gap: 5px;
}

.filter-group label {
  color: #fff;
  font-size: 12px;
  font-weight: 500;
}

.filter-group select,
.filter-group input {
  padding: 8px 12px;
  border-radius: 5px;
  border: 1px solid rgba(255, 255, 255, 0.3);
  background: rgba(255, 255, 255, 0.1);
  color: #fff;
  font-size: 14px;
}

.filter-group select option {
  background: #27253f;
  color: #fff;
}

.table-container {
  overflow-x: auto;
}

.audit-table {
  width: 100%;
  border-collapse: collapse;
  background: linear-gradient(135deg, #27253f, #605a6d);
  color: #fff;
}

.audit-table th,
.audit-table td {
  padding: 12px;
  text-align: left;
  border-bottom: 1px solid rgba(255, 255, 255, 0.2);
}

.audit-table th {
  background: rgba(0, 0, 0, 0.2);
  font-weight: 600;
}

.action-badge {
  padding: 4px 10px;
  border-radius: 12px;
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
}

.action-badge.created {
  background: #4CAF50;
  color: #fff;
}

.action-badge.updated {
  background: #FF9800;
  color: #fff;
}

.action-badge.deleted {
  background: #F44336;
  color: #fff;
}

.btn-sm {
  padding: 4px 8px;
  font-size: 12px;
}

.pagination {
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 15px;
  margin-top: 20px;
  color: #fff;
}

.page-info {
  font-size: 14px;
}

.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.7);
  display: flex;
  justify-content: center;
  align-items: center;
  z-index: 1000;
}

.modal-content {
  background: #fff;
  border-radius: 8px;
  width: 90%;
  max-width: 700px;
  max-height: 90vh;
  overflow-y: auto;
  color: #333;
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 20px;
  border-bottom: 1px solid #ddd;
}

.modal-header h3 {
  margin: 0;
  color: #333;
}

.close-btn {
  background: none;
  border: none;
  font-size: 24px;
  cursor: pointer;
  color: #666;
  padding: 0;
  width: 30px;
  height: 30px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.close-btn:hover {
  color: #000;
}

.modal-body {
  padding: 20px;
}

.detail-row {
  margin-bottom: 15px;
}

.detail-row strong {
  display: inline-block;
  min-width: 120px;
  color: #666;
}

.values-box {
  background: #f5f5f5;
  padding: 10px;
  border-radius: 4px;
  overflow-x: auto;
  font-size: 12px;
  margin-top: 5px;
}

.text-center {
  text-align: center;
}
</style>
