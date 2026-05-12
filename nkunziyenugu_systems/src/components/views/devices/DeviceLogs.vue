<template>
      <div class="">
        <div class="app-loading-bar"></div>
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
          <label for="perPage">Rows</label>
            <select v-model.number="pagination.per_page" class="form-control" id="perPage">
              <option :value="10">10</option>
              <option :value="20">20</option>
              <option :value="50">50</option>
              <option :value="100">100</option>
              <option :value="200">200</option>
              <option :value="500">500</option>
              <option :value="1000">1000</option>
              <option :value="2000">2000</option>
              <option :value="5000">5000</option>
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
          <th>Sensor</th>
          <!-- Heartbeat-only columns. Populated only when log.type === 'heartbeat'. -->
          <th>Firmware</th>
          <th>Balance</th>
          <th class="col-narrow">Bal. checked</th>
          <th class="col-narrow">Uptime</th>
          <th class="col-narrow">Seq</th>
          <th>Received At</th>
          <th>Actions</th>
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
            <!-- Sensor data -->
            <td v-if="log.type === 'sensor' && log.payload.inputs?.input1">
              <span v-if="log.payload.inputs?.input1.state == 0"> <!-- Fix this on the FW side make 1 = on and 0 = off -->
                {{ log.payload.inputs?.input1.description ??'-' }} : <i class="bi bi-toggle-on"></i>
              </span>

              <span v-else>
                {{ log.payload.inputs?.input1.description ??'-' }} : <i class="bi bi-toggle-off"></i>
              </span>
            </td>
            <td v-else>-</td>
            <!-- End sensor data -->

            <!-- Heartbeat fields — only populated for heartbeat rows -->
            <td>{{ log.type === 'heartbeat' ? (log.payload?.firmware_version ?? '-') : '-' }}</td>
            <td>{{ log.type === 'heartbeat' ? (log.payload?.balance ?? '-') : '-' }}</td>
            <td class="col-narrow">{{ log.type === 'heartbeat' && log.payload?.balance_ts ? relativeTime(log.payload.balance_ts) : '-' }}</td>
            <td class="col-narrow">{{ log.type === 'heartbeat' && log.payload?.uptime_s != null ? humanizeUptime(log.payload.uptime_s) : '-' }}</td>
            <td class="col-narrow">{{ log.type === 'heartbeat' && log.payload?.message_seq != null ? log.payload.message_seq : '-' }}</td>

            <td>{{ formatDate(log.created_at) }}</td>
            <td>
              <button class="button-info" @click="togglePayload(log.id)">
                <i v-if="expandedPayloadId === log.id" class="bi bi-eye-slash"></i>
                <i v-else class="bi bi-eye"></i>
              </button>
            </td>
          </tr>
          <tr v-show="expandedPayloadId === log.id">
            <td colspan="13">
              <pre class="payload-pre">{{ prettyJson(log.payload) }}</pre>
            </td>
          </tr>
        </template>

        <tr v-if="logs.length === 0">
          <td colspan="13">No logs found.</td>
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
import { markRaw } from "vue";
import L from "@/utils/leaflet-setup";
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
      mapMarkers: [],
      mapLine: null,
      filters: {
        type: "",
        from: "",
        to: "",
      },
      pagination: {
        page: 1,
        per_page: 10,
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
      // map.remove() detaches every Leaflet event listener and tears down the
      // tile DOM. Skipping it is the #1 source of memory leaks on SPA route
      // changes — listeners pile up on window resize/zoom and the tab freezes.
      if (this.map) {
        try { this.map.remove(); } catch (e) { /* ignore */ }
      }
      this.map = null;
      this.mapMarkers = [];
      this.mapLine = null;
      this.mapTileLayers = null;
    },

    toDateTimeLocal(date) { // Converts a Date object to a string in the format YYYY-MM-DDTHH:MM:SS
      const year = date.getFullYear();
      const month = String(date.getMonth() + 1).padStart(2, "0");
      const day = String(date.getDate()).padStart(2, "0");
      const hours = String(date.getHours()).padStart(2, "0");
      const minutes = String(date.getMinutes()).padStart(2, "0");
      const seconds = String(date.getSeconds()).padStart(2, "0");
      return `${year}-${month}-${day}T${hours}:${minutes}:${seconds}`;
    },

    setDefaultDateRange() { // Sets the default date range
      const start = new Date();
      start.setHours(0, 0, 0, 0);
      const end = new Date();
      end.setHours(23, 59, 59, 0);
      this.filters.from = this.toDateTimeLocal(start);
      this.filters.to = this.toDateTimeLocal(end);
    },

    ensureMap() {
      if (this.map) return;
      const el = document.getElementById("deviceLogsMap");
      if (!el) return;

      // Build the four base layers up-front. Lazy factories aren't worth it
      // here — the layer switcher needs them all available to swap.
      const osmAttr =
        '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors';

      const streets = L.tileLayer(
        "https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png",
        { maxZoom: 19, attribution: osmAttr }
      );
      const satellite = L.tileLayer(
        "https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}",
        {
          maxZoom: 19,
          attribution:
            "Tiles &copy; Esri &mdash; Source: Esri, Maxar, Earthstar Geographics",
        }
      );
      const terrain = L.tileLayer(
        "https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png",
        {
          maxZoom: 17,
          attribution:
            osmAttr +
            ' | Map style: &copy; <a href="https://opentopomap.org">OpenTopoMap</a> (CC-BY-SA)',
        }
      );
      const dark = L.tileLayer(
        "https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}.png",
        {
          maxZoom: 19,
          subdomains: "abcd",
          attribution:
            osmAttr +
            ' &copy; <a href="https://carto.com/attributions">CARTO</a>',
        }
      );

      this.mapTileLayers = { Streets: streets, Satellite: satellite, Terrain: terrain, Dark: dark };

      // markRaw: keep Leaflet's internal circular refs out of Vue's reactivity
      // proxy — otherwise you get random "Maximum call stack" errors from
      // Vue trying to deep-track _leaflet_id chains.
      this.map = markRaw(
        L.map(el, {
          center: [0, 0],
          zoom: 2,
          layers: [streets],
          zoomControl: true,
          worldCopyJump: true,
        })
      );

      L.control
        .layers(this.mapTileLayers, {}, { position: "topright", collapsed: true })
        .addTo(this.map);

      // After v-if/v-show toggles, the container's measured size is often 0
      // until the next paint frame. invalidateSize fixes the grey-tile bug.
      this.$nextTick(() => this.map?.invalidateSize());

      this.refreshMap();
    },

    refreshMap() {
      if (this.filters.type !== "location") return;
      if (!this.map) {
        this.ensureMap();
        return; // ensureMap calls refreshMap once the map is up
      }

      // Tear down old overlays — keep the base tile layer + control intact
      for (const m of this.mapMarkers) m.remove();
      this.mapMarkers = [];
      if (this.mapLine) {
        this.mapLine.remove();
        this.mapLine = null;
      }

      const points = (this.logs || [])
        .filter((l) => l && l.lat != null && l.lng != null)
        .map((l) => {
          const ts = l.message_timestamp || l.device_timestamp || l.created_at;
          return {
            log: l,
            time: ts ? new Date(ts).getTime() : 0,
            lat: Number(l.lat),
            lng: Number(l.lng),
          };
        })
        .filter((p) => Number.isFinite(p.lat) && Number.isFinite(p.lng))
        .sort((a, b) => a.time - b.time);

      if (points.length === 0) {
        this.map.setView([0, 0], 2);
        this.$nextTick(() => this.map?.invalidateSize());
        return;
      }

      const latlngs = points.map((p) => [p.lat, p.lng]);

      for (const p of points) {
        const ts = p.log.message_timestamp || p.log.device_timestamp || p.log.created_at;
        const gps = p.log.payload?.gps;
        const popup = `<div style="min-width:220px">
          <div><strong>Time:</strong> ${this.formatDate(ts)}</div>
          <div><strong>Lat:</strong> ${p.lat}</div>
          <div><strong>Lng:</strong> ${p.lng}</div>
          ${gps ? `<div><strong>Fix:</strong> ${gps.fix}</div>` : ""}
          ${gps ? `<div><strong>Satellites:</strong> ${gps.satellites}</div>` : ""}
          ${gps ? `<div><strong>Fix Quality:</strong> ${gps.fix_quality}</div>` : ""}
        </div>`;
        const marker = L.marker([p.lat, p.lng], {
          title: `Time: ${this.formatDate(ts)}`,
        })
          .bindPopup(popup)
          .addTo(this.map);
        this.mapMarkers.push(marker);
      }

      this.mapLine = L.polyline(latlngs, {
        color: "#2c7be5",
        weight: 3,
        opacity: 0.9,
      }).addTo(this.map);

      this.$nextTick(() => {
        if (!this.map) return;
        if (latlngs.length === 1) {
          this.map.setView(latlngs[0], 15);
        } else {
          this.map.fitBounds(L.latLngBounds(latlngs), { padding: [30, 30] });
        }
        this.map.invalidateSize();
      });
    },

    formatDate(dateStr) { // Format a date
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

    prettyJson(payload) { // Format a JSON payload for display in the UI
      try {
        return JSON.stringify(payload, null, 2);
      } catch (e) {
        return String(payload);
      }
    },

    togglePayload(id) {
      this.expandedPayloadId = this.expandedPayloadId === id ? null : id;
    },

    // "2d 4h", "13m", "47s" — keep it human and compact for table cells.
    humanizeUptime(seconds) {
      const s = Number(seconds);
      if (!Number.isFinite(s) || s < 0) return "-";
      const d = Math.floor(s / 86400);
      const h = Math.floor((s % 86400) / 3600);
      const m = Math.floor((s % 3600) / 60);
      if (d > 0) return `${d}d ${h}h`;
      if (h > 0) return `${h}h ${m}m`;
      if (m > 0) return `${m}m`;
      return `${Math.floor(s)}s`;
    },

    // "5h ago", "3d ago", "just now" — relative to current wall clock.
    // Accepts either epoch seconds (integer) or an ISO-8601 string.
    relativeTime(value) {
      if (value == null) return "-";
      const epoch = typeof value === "number" ? value * 1000 : new Date(value).getTime();
      if (!Number.isFinite(epoch)) return "-";
      const diff = Math.max(0, Math.floor((Date.now() - epoch) / 1000));
      if (diff < 60)    return "just now";
      if (diff < 3600)  return `${Math.floor(diff / 60)}m ago`;
      if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`;
      return `${Math.floor(diff / 86400)}d ago`;
    },

    applyFilters() {
      this.pagination.page = 1;
      this.fetchLogs();
    },

    resetFilters() {
      this.filters.type = "";
      this.setDefaultDateRange();
      this.pagination.page = 1;
      this.pagination.per_page = 20;
      this.fetchLogs();
    },
    goToPage(page) {
      this.pagination.page = page;
      this.fetchLogs();
    },
    fetchLogs() { // Fetch device logs
      const loadingBarEl = document.querySelector(".app-loading-bar");
      if (loadingBarEl) {
        loadingBarEl.style.display = "block";
      }
      this.loading = true;
      this.expandedPayloadId = null;
      const requestedPerPage = this.pagination.per_page;

      api.get(`/devices/${this.id}/logs`, {
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
          console.log(this.logs);
          this.pagination.page = data?.pagination?.page ?? 1;
          this.pagination.per_page = requestedPerPage;
          this.pagination.total = data?.pagination?.total ?? 0;
          this.pagination.last_page = data?.pagination?.last_page ?? 1;
        })
        .catch((error) => {
          console.error(error);
          toast.error(error.response?.data?.message || "Failed to load device logs.");
        })
        .finally(() => {
          this.loading = false;
          if (loadingBarEl) {
            loadingBarEl.style.display = "none";
          }
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

/* Hide the high-detail heartbeat columns on narrow screens. Firmware +
 * Balance stay visible because they're the most operationally useful at a
 * glance; Bal. checked / Uptime / Seq are diagnostic and can wait until
 * the user expands the payload pane via the eye icon. */
@media (max-width: 1000px) {
  .col-narrow {
    display: none;
  }
}
</style>