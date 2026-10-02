import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import iconUrl from 'leaflet/dist/images/marker-icon.png';
import iconRetinaUrl from 'leaflet/dist/images/marker-icon-2x.png';
import shadowUrl from 'leaflet/dist/images/marker-shadow.png';

// Vite rewrites asset URLs, so Leaflet's default icon path detection must be bypassed.
delete L.Icon.Default.prototype._getIconUrl;
L.Icon.Default.mergeOptions({ iconUrl, iconRetinaUrl, shadowUrl });

/** Create a map from a container's data-config JSON: {tileUrl, attribution, center, zoom}. */
export function createMap(el, overrides = {}) {
    const config = { ...JSON.parse(el.dataset.config || '{}'), ...overrides };
    const map = L.map(el, { preferCanvas: true }).setView(config.center || [23.6, 85.3], config.zoom || 8);
    L.tileLayer(config.tileUrl, { maxZoom: 19, attribution: config.attribution }).addTo(map);
    L.control.scale({ imperial: false }).addTo(map);
    el.leafletMap = map; // handy for debugging and browser tests
    return map;
}

export function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
}

export function debounce(fn, ms = 300) {
    let t;
    return (...args) => {
        clearTimeout(t);
        t = setTimeout(() => fn(...args), ms);
    };
}

export { L };
