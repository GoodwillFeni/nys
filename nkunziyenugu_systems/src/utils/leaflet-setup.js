// Single-source Leaflet bootstrap. Import this module wherever you use Leaflet
// instead of importing 'leaflet' directly — it guarantees the marker-icon fix
// runs exactly once.
//
// WHY THIS FILE EXISTS
// --------------------
// Leaflet's default Icon resolves marker images via require()-style relative
// paths baked into its source. Webpack 5 (Vue CLI 5) hashes/inlines images,
// so those paths break and you get the classic "marker icon missing" or
// "icon URL is undefined" runtime error.
//
// We override Icon.Default so it uses the bundler-resolved URLs from
// `leaflet/dist/images/*.png`. This works for Webpack, Vite, and Rollup.
import L from 'leaflet'
import iconUrl from 'leaflet/dist/images/marker-icon.png'
import iconRetinaUrl from 'leaflet/dist/images/marker-icon-2x.png'
import shadowUrl from 'leaflet/dist/images/marker-shadow.png'

delete L.Icon.Default.prototype._getIconUrl
L.Icon.Default.mergeOptions({ iconUrl, iconRetinaUrl, shadowUrl })

// ---------------------------------------------------------------------------
// PATCH: zoom-animation race safety
// ---------------------------------------------------------------------------
// Leaflet 1.9.4 has an unresolved bug where a layer whose `_map` was nulled
// (e.g. removed mid-animation, or torn down while a poll/refresh was running)
// still fires its queued `zoomanim` handler. The handler then dereferences
// `this._map` and crashes with:
//   TypeError: Cannot read properties of null (reading '_latLngToNewLayerPoint')
// Reproducible by mouse-wheel-zooming the map while markers are being
// rebuilt by a setInterval poll, or while the route is unmounting.
// Upstream tracking: https://github.com/Leaflet/Leaflet/issues/7331
//
// We guard `_animateZoom` on every prototype that defines one — if the layer
// has already been detached, the handler simply no-ops instead of throwing.
// Idempotent: safe to import this module multiple times (the original
// reference is captured once per prototype).
const patchAnimateZoom = (Cls) => {
  if (!Cls || !Cls.prototype || !Cls.prototype._animateZoom) return
  if (Cls.prototype._animateZoom.__nysPatched) return
  const orig = Cls.prototype._animateZoom
  const patched = function (opt) {
    if (!this._map) return
    return orig.call(this, opt)
  }
  patched.__nysPatched = true
  Cls.prototype._animateZoom = patched
}

// Marker is the one in the stack trace, but Path/GridLayer/SVG/Canvas can
// hit the same race on Polyline/Polygon/Circle/tile fades — patch them all.
patchAnimateZoom(L.Marker)
patchAnimateZoom(L.Path)
patchAnimateZoom(L.GridLayer)
patchAnimateZoom(L.SVG)
patchAnimateZoom(L.Canvas)

export default L
