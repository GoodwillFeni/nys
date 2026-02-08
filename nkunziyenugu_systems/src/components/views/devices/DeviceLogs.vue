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
          <input v-model="filters.from" type="datetime-local" step="1" class="form-control" placeholder="From" />
         </div>

         <div class="form-group filter-field">
          <label for="to">To</label>
          <input v-model="filters.to" type="datetime-local" step="1" class="form-control" placeholder="To" />
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

    <div v-if="filters.type === 'location'" v-show="!loading" class="device-logs-map" id="deviceLogsMap"></div>

    <table v-if="!loading" class="min-w-full border border-gray-200">
      <thead class="bg-gray-100">
        <tr>
          <th>#</th>
          <th>Type</th>
          <th>Message Time</th>
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
            <td>{{ formatDate(log.message_timestamp || log.device_timestamp || log.created_at) }}</td>
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
import * as L from "leaflet";
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
      map: null,
      mapMarkersLayer: null,
      mapLine: null,
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
    this.setDefaultDateRange();
    this.fetchLogs();
  },
  watch: {
    "filters.type": function (next, prev) {
      if (prev === "location" && next !== "location") {
        this.destroyMap();
      }
      if (next === "location") {
        this.$nextTick(() => {
          this.refreshMap();
        });
      }
    },
  },
  beforeUnmount() {
    this.destroyMap();
  },
  methods: {
    destroyMap() {
      if (this.map) {
        try {
          this.map.stop();
        } catch (e) {
          // ignore
        }
        try {
          this.map.off();
        } catch (e) {
          // ignore
        }
        this.map.remove();
        this.map = null;
        this.mapMarkersLayer = null;
        this.mapLine = null;
      }
    },
    fixLeafletIcons() {
      const iconRetinaUrl = require("leaflet/dist/images/marker-icon-2x.png");
      const iconUrl = require("leaflet/dist/images/marker-icon.png");
      const shadowUrl = require("leaflet/dist/images/marker-shadow.png");
      delete L.Icon.Default.prototype._getIconUrl;
      L.Icon.Default.mergeOptions({
        iconRetinaUrl,
        iconUrl,
        shadowUrl,
      });
    },
    toDateTimeLocal(date) {
      const year = date.getFullYear();
      const month = String(date.getMonth() + 1).padStart(2, "0");
      const day = String(date.getDate()).padStart(2, "0");
      const hours = String(date.getHours()).padStart(2, "0");
      const minutes = String(date.getMinutes()).padStart(2, "0");
      const seconds = String(date.getSeconds()).padStart(2, "0");
      return `${year}-${month}-${day}T${hours}:${minutes}:${seconds}`;
    },
    setDefaultDateRange() {
      const start = new Date();
      start.setHours(0, 0, 0, 0);
      const end = new Date();
      end.setHours(23, 59, 59, 0);
      this.filters.from = this.toDateTimeLocal(start);
      this.filters.to = this.toDateTimeLocal(end);
    },
    ensureMap() {
      if (this.map) {
        const container = this.map.getContainer ? this.map.getContainer() : null;
        if (!container || !document.body.contains(container)) {
          this.destroyMap();
        } else {
          this.$nextTick(() => {
            this.map.invalidateSize();
          });
          return;
        }
      }
      const el = document.getElementById("deviceLogsMap");
      if (!el) {
        return;
      }
      this.fixLeafletIcons();
      this.map = L.map(el, {
        zoomControl: true,
        attributionControl: true,
        scrollWheelZoom: false,
        zoomAnimation: false,
        markerZoomAnimation: false,
        fadeAnimation: false,
      });

      L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
        maxZoom: 19,
        attribution: "&copy; OpenStreetMap contributors",
      }).addTo(this.map);
      this.mapMarkersLayer = L.layerGroup().addTo(this.map);
      this.map.setView([0, 0], 2);
    },
    refreshMap() {
      if (this.filters.type !== "location") {
        return;
      }
      this.ensureMap();
      if (!this.map || !this.mapMarkersLayer) {
        return;
      }

      try {
        this.map.stop();
      } catch (e) {
        // ignore
      }

      this.mapMarkersLayer.clearLayers();
      if (this.mapLine) {
        this.map.removeLayer(this.mapLine);
        this.mapLine = null;
      }

      const points = (this.logs || [])
        .filter((l) => l && l.lat != null && l.lng != null)
        .map((l) => {
          const ts = l.message_timestamp || l.device_timestamp || l.created_at;
          const time = ts ? new Date(ts).getTime() : 0;
          return {
            log: l,
            time,
            lat: Number(l.lat),
            lng: Number(l.lng),
          };
        })
        .filter((p) => Number.isFinite(p.lat) && Number.isFinite(p.lng));

      points.sort((a, b) => a.time - b.time);

      if (points.length === 0) {
        this.map.setView([0, 0], 2);
        return;
      }

      const latlngs = [];
      for (const p of points) {
        const ll = [p.lat, p.lng];
        latlngs.push(ll);
        const gps = p.log.payload?.gps;
        const tooltip = `<div style="min-width:220px">
          <div><strong>Time:</strong> ${this.formatDate(p.log.message_timestamp || p.log.device_timestamp || p.log.created_at)}</div>
          <div><strong>Lat:</strong> ${p.lat}</div>
          <div><strong>Lng:</strong> ${p.lng}</div>
          ${gps ? `<div><strong>Fix:</strong> ${gps.fix}</div>` : ""}
          ${gps ? `<div><strong>Satellites:</strong> ${gps.satellites}</div>` : ""}
          ${gps ? `<div><strong>Fix Quality:</strong> ${gps.fix_quality}</div>` : ""}
        </div>`;
        const marker = L.marker(ll).bindTooltip(tooltip, { sticky: true });
        marker.addTo(this.mapMarkersLayer);
      }

      this.mapLine = L.polyline(latlngs, { color: "#2c7be5", weight: 3, opacity: 0.9 }).addTo(this.map);
      this.$nextTick(() => {
        if (latlngs.length === 1) {
          this.map.setView(latlngs[0], 15);
        } else {
          this.map.fitBounds(this.mapLine.getBounds(), { padding: [20, 20] });
        }
        this.map.invalidateSize();
      });
    },
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
      this.setDefaultDateRange();
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
          this.$nextTick(() => {
            this.refreshMap();
          });
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

.device-logs-map {
  width: 100%;
  height: 40vh;
  min-height: 140px;
  border: 1px solid rgba(255, 255, 255, 0.15);
  border-radius: 6px;
  margin-bottom: 10px;
}
</style>