<template>
  <div class="gps-map-wrapper">
    <div ref="mapEl" class="gps-map" :class="{ 'gps-map-fullscreen': isFullscreen }"></div>
  </div>
</template>

<script>
import { markRaw } from 'vue'
import L from '@/utils/leaflet-setup'
import 'leaflet.markercluster/dist/MarkerCluster.css'
import 'leaflet.markercluster/dist/MarkerCluster.Default.css'
import 'leaflet.markercluster'

const TILE_LAYERS = {
  Streets: () => L.tileLayer(
    'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
    {
      maxZoom: 19,
      attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
    }
  ),
  Satellite: () => L.tileLayer(
    'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
    {
      maxZoom: 19,
      attribution: 'Tiles &copy; Esri &mdash; Source: Esri, Maxar, Earthstar Geographics',
    }
  ),
  Terrain: () => L.tileLayer(
    'https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png',
    {
      maxZoom: 17,
      attribution:
        '&copy; OpenStreetMap contributors | Map style: &copy; <a href="https://opentopomap.org">OpenTopoMap</a> (CC-BY-SA)',
    }
  ),
  Dark: () => L.tileLayer(
    'https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}.png',
    {
      maxZoom: 19,
      attribution:
        '&copy; OpenStreetMap contributors &copy; <a href="https://carto.com/attributions">CARTO</a>',
      subdomains: 'abcd',
    }
  ),
}

export default {
  name: 'GpsMap',

  props: {
    // [{ id, name, lat, lng, speed, lastUpdate }, ...]
    devices:    { type: Array, default: () => [] },
    // [{ id, color, points: [[lat,lng], ...] }, ...]
    tracks:     { type: Array, default: () => [] },
    // [{ center: [lat,lng], radius: meters, label?, color? }, ...]
    geofences:  { type: Array, default: () => [] },

    center:        { type: Array,   default: () => [-1.95, 30.06] }, // Kigali fallback
    zoom:          { type: Number,  default: 8 },
    initialLayer:  { type: String,  default: 'Streets' },
    cluster:       { type: Boolean, default: true },
    autoFit:       { type: Boolean, default: true },
  },

  emits: ['marker-click', 'ready'],

  data() {
    return {
      map: null,
      markerLayer: null,        // L.MarkerClusterGroup or L.FeatureGroup
      markersById: new Map(),   // id -> L.Marker
      geofenceLayer: null,
      tracksLayer: null,
      resizeObserver: null,
      isFullscreen: false,
      _fullscreenHandler: null,
    }
  },

  mounted() {
    this.initMap()
  },

  beforeUnmount() {
    this.cleanup()
  },

  watch: {
    devices() { this.syncDevices() },
    tracks()    { this.drawTracks() },
    geofences() { this.drawGeofences() },
  },

  methods: {
    initMap() {
      const baseLayers = Object.fromEntries(
        Object.entries(TILE_LAYERS).map(([k, factory]) => [k, factory()])
      )
      const startLayer = baseLayers[this.initialLayer] || baseLayers.Streets

      // markRaw: see DeviceLogs.vue — prevents Vue 3's Proxy from wrapping
      // Leaflet's internal circular refs.
      this.map = markRaw(L.map(this.$refs.mapEl, {
        center: this.center,
        zoom: this.zoom,
        layers: [startLayer],
        worldCopyJump: true,
        preferCanvas: true,           // canvas renderer = much faster with many markers
      }))

      L.control.layers(baseLayers, {}, { position: 'topright', collapsed: true })
        .addTo(this.map)

      this.addFullscreenControl()

      this.markerLayer = this.cluster
        ? L.markerClusterGroup({
            disableClusteringAtZoom: 17,
            maxClusterRadius: 60,
            chunkedLoading: true,     // avoids long main-thread blocks on big lists
          })
        : L.featureGroup()
      this.markerLayer.addTo(this.map)

      this.geofenceLayer = L.layerGroup().addTo(this.map)
      this.tracksLayer   = L.layerGroup().addTo(this.map)

      this.syncDevices()
      this.drawGeofences()
      this.drawTracks()

      // Re-measure after the parent layout settles. Without this you get the
      // classic grey-tile / half-rendered map when the container is hidden
      // at first paint (v-if / v-show / tab switch / modal open).
      this.$nextTick(() => this.map?.invalidateSize())

      // Keep size correct when the container resizes (responsive layouts,
      // sidebars opening/closing, fullscreen changes).
      if (typeof ResizeObserver !== 'undefined') {
        this.resizeObserver = new ResizeObserver(() => this.map?.invalidateSize())
        this.resizeObserver.observe(this.$refs.mapEl)
      }

      // Sync our local fullscreen flag if the user exits via Esc
      this._fullscreenHandler = () => {
        this.isFullscreen = !!document.fullscreenElement
        this.$nextTick(() => this.map?.invalidateSize())
      }
      document.addEventListener('fullscreenchange', this._fullscreenHandler)

      this.$emit('ready', this.map)
    },

    addFullscreenControl() {
      const self = this
      const Fs = L.Control.extend({
        options: { position: 'topleft' },
        onAdd() {
          const a = L.DomUtil.create('a', 'leaflet-bar leaflet-control gps-fs-btn')
          a.href = '#'
          a.title = 'Toggle fullscreen'
          a.setAttribute('role', 'button')
          a.innerHTML = '⤢' // diagonal arrows
          L.DomEvent.on(a, 'click', (e) => {
            L.DomEvent.preventDefault(e)
            L.DomEvent.stopPropagation(e)
            self.toggleFullscreen()
          })
          return a
        },
      })
      new Fs().addTo(this.map)
    },

    toggleFullscreen() {
      const el = this.$refs.mapEl
      if (!document.fullscreenElement) {
        el.requestFullscreen?.().catch(() => {})
      } else {
        document.exitFullscreen?.()
      }
    },

    syncDevices() {
      if (!this.map || !this.markerLayer) return

      const seen = new Set()
      const newMarkers = []

      for (const d of this.devices) {
        if (d == null || d.lat == null || d.lng == null) continue
        const lat = Number(d.lat)
        const lng = Number(d.lng)
        if (!Number.isFinite(lat) || !Number.isFinite(lng)) continue
        seen.add(d.id)

        const popup = this.popupHtml(d)
        let marker = this.markersById.get(d.id)

        if (marker) {
          // Update in place — no churn on the cluster index
          marker.setLatLng([lat, lng])
          if (marker.getPopup()) marker.setPopupContent(popup)
          else marker.bindPopup(popup)
        } else {
          marker = L.marker([lat, lng], { title: d.name || `Device ${d.id}` })
            .bindPopup(popup)
          marker.on('click', () => this.$emit('marker-click', d))
          newMarkers.push(marker)
          this.markersById.set(d.id, marker)
        }
      }

      // Add new markers in one batch — much faster than addLayer per marker
      if (newMarkers.length) {
        if (typeof this.markerLayer.addLayers === 'function') {
          this.markerLayer.addLayers(newMarkers)         // cluster group
        } else {
          newMarkers.forEach((m) => this.markerLayer.addLayer(m))
        }
      }

      // Remove markers for devices no longer in the list
      for (const [id, marker] of this.markersById) {
        if (!seen.has(id)) {
          this.markerLayer.removeLayer(marker)
          this.markersById.delete(id)
        }
      }

      if (this.autoFit && this.markersById.size > 0) {
        this.fitToDevices()
      }
    },

    popupHtml(d) {
      const last = d.lastUpdate ? new Date(d.lastUpdate).toLocaleString() : '-'
      const speed = d.speed != null ? `${d.speed} km/h` : '-'
      const name = this.escapeHtml(d.name ?? `Device ${d.id}`)
      return `<div style="min-width:200px">
        <div><strong>${name}</strong></div>
        <div>Speed: ${speed}</div>
        <div>Last update: ${last}</div>
      </div>`
    },

    escapeHtml(s) {
      return String(s).replace(/[&<>"']/g, (c) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
      }[c]))
    },

    drawTracks() {
      if (!this.tracksLayer) return
      this.tracksLayer.clearLayers()
      for (const t of this.tracks) {
        if (!t?.points || t.points.length < 2) continue
        L.polyline(t.points, {
          color: t.color || '#2c7be5',
          weight: 3,
          opacity: 0.85,
        }).addTo(this.tracksLayer)
      }
    },

    drawGeofences() {
      if (!this.geofenceLayer) return
      this.geofenceLayer.clearLayers()
      for (const f of this.geofences) {
        if (!f?.center || !Number.isFinite(f.radius)) continue
        const c = L.circle(f.center, {
          radius: f.radius,
          color: f.color || '#e74c3c',
          weight: 2,
          fillOpacity: 0.08,
        })
        if (f.label) c.bindPopup(this.escapeHtml(f.label))
        c.addTo(this.geofenceLayer)
      }
    },

    fitToDevices() {
      if (!this.map || this.markersById.size === 0) return
      const layers = Array.from(this.markersById.values())
      const group = L.featureGroup(layers)
      const bounds = group.getBounds()
      if (bounds.isValid()) {
        this.map.fitBounds(bounds, { padding: [40, 40], maxZoom: 16 })
      }
    },

    // Useful helper for callers that want to check "did this device leave the fence"
    isInsideGeofence(latlng, fence) {
      if (!fence?.center) return false
      return L.latLng(latlng).distanceTo(L.latLng(fence.center)) <= fence.radius
    },

    cleanup() {
      if (this._fullscreenHandler) {
        document.removeEventListener('fullscreenchange', this._fullscreenHandler)
        this._fullscreenHandler = null
      }
      this.resizeObserver?.disconnect()
      this.resizeObserver = null

      // map.remove() is the *only* correct teardown — it detaches every event
      // listener and frees the DOM. Without it you leak listeners on every
      // route change and eventually freeze the tab.
      if (this.map) {
        try { this.map.remove() } catch (e) { /* ignore */ }
        this.map = null
      }
      this.markersById.clear()
      this.markerLayer = null
      this.geofenceLayer = null
      this.tracksLayer = null
    },
  },
}
</script>

<style scoped>
.gps-map-wrapper {
  width: 100%;
  height: 100%;
  position: relative;
}
.gps-map {
  width: 100%;
  height: 100%;
  min-height: 320px;
  border-radius: 8px;
  background: #1a1a1a; /* avoids white flash before tiles load */
}
.gps-map-fullscreen { border-radius: 0; }

/* Make Leaflet popups readable on the dark gradient theme */
:deep(.leaflet-popup-content-wrapper) {
  background: #fff;
  color: #222;
  border-radius: 6px;
}
:deep(.leaflet-popup-content) { margin: 10px 12px; font-size: 13px; }
:deep(.gps-fs-btn) {
  width: 30px; height: 30px;
  line-height: 30px;
  text-align: center;
  font-size: 18px;
  background: #fff;
  color: #222;
  text-decoration: none;
}
:deep(.leaflet-container) {
  font-family: inherit;
}
</style>
