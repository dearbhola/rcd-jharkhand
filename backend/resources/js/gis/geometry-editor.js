import '@geoman-io/leaflet-geoman-free';
import '@geoman-io/leaflet-geoman-free/dist/leaflet-geoman.css';
import { L, createMap, escapeHtml as e } from './base';
import { request } from '../ui';

const el = document.getElementById('editorMap');
const data = JSON.parse(document.getElementById('editorData').textContent);
const msg = document.getElementById('editorMessage');
const map = createMap(el);

map.pm.addControls({
    position: 'topleft',
    drawMarker: false, drawCircleMarker: false, drawPolygon: false, drawRectangle: false, drawCircle: false, drawText: false,
    cutPolygon: false, rotateMode: false, dragMode: false, removalMode: false,
});
map.pm.setGlobalOptions({ snappable: true, snapDistance: 15 });

const sectionLayer = L.geoJSON(null, {
    style: () => ({ color: '#6c757d', weight: 10, opacity: 0.25 }),
    onEachFeature: (f, l) => l.bindTooltip(`${e(f.properties.code)} · km ${e(f.properties.km)}`, { sticky: true }),
}).addTo(map);
data.sections.forEach((s) => sectionLayer.addData({ type: 'Feature', geometry: s.geometry, properties: s }));

let road = null; // the editable polyline
let dirty = false;
let source = 'drawn';

function setRoad(geojson, fit = true) {
    if (road) map.removeLayer(road);
    road = L.geoJSON(geojson, { style: { color: '#0d4f8b', weight: 5 } }).getLayers()[0];
    road.addTo(map);
    road.on('pm:edit', markDirty);
    if (fit) map.fitBounds(road.getBounds(), { padding: [30, 30] });
    updateStats();
}

function lengthKm() {
    if (!road) return 0;
    const pts = road.getLatLngs();
    let m = 0;
    for (let i = 1; i < pts.length; i++) m += pts[i - 1].distanceTo(pts[i]);
    return m / 1000;
}

function updateStats() {
    const declared = (data.road.end_m - data.road.start_m) / 1000;
    document.getElementById('statLength').textContent = road ? `${lengthKm().toFixed(3)} km` : '—';
    document.getElementById('statVertices').textContent = road ? road.getLatLngs().length : '—';
    document.getElementById('statDeclared').textContent = `${declared.toFixed(3)} km`;
    document.getElementById('saveBtn').disabled = !road || !dirty;
}

function markDirty(src = 'drawn') {
    dirty = true;
    if (typeof src === 'string') source = src;
    msg.className = 'alert alert-warning small py-2';
    msg.textContent = 'Unsaved changes. Saving creates a new geometry version; earlier versions are kept.';
    updateStats();
}

map.on('pm:create', (ev) => {
    if (ev.shape !== 'Line') return;
    const geo = ev.layer.toGeoJSON().geometry;
    map.removeLayer(ev.layer);
    setRoad(geo, false);
    markDirty('drawn');
});

if (data.geometry) setRoad(data.geometry);
else if (data.sections.length) map.fitBounds(sectionLayer.getBounds());

document.getElementById('editBtn').addEventListener('click', () => road && road.pm.toggleEdit({ snappable: true }));
document.getElementById('reverseBtn').addEventListener('click', () => {
    if (!road) return;
    road.setLatLngs(road.getLatLngs().slice().reverse());
    markDirty();
});

document.getElementById('saveBtn').addEventListener('click', async () => {
    if (!road) return;
    road.pm.disable();
    const remarks = document.getElementById('remarks').value;
    try {
        const res = await request(data.urls.save, { method: 'PUT', body: { geometry: road.toGeoJSON().geometry, remarks, source } });
        dirty = false;
        msg.className = `alert ${res.warning ? 'alert-warning' : 'alert-success'} small py-2`;
        msg.textContent = `${res.message} Length ${res.length_km} km. Sections re-cut. ${res.warning ?? ''}`;
        setTimeout(() => window.location.reload(), res.warning ? 4000 : 1200);
    } catch (err) {
        msg.className = 'alert alert-danger small py-2';
        msg.textContent = err.data?.errors ? Object.values(err.data.errors).flat().join(' ') : err.message;
    }
    updateStats();
});

document.getElementById('importFile').addEventListener('change', async (ev) => {
    const file = ev.target.files[0];
    if (!file) return;
    const body = new FormData();
    body.append('file', file);
    try {
        const res = await request(data.urls.import, { method: 'POST', body });
        setRoad(res.geometry);
        markDirty('imported');
        msg.textContent = `Imported ${res.vertices} points (${res.length_km} km) as a draft. Review, then Save.`;
    } catch (err) {
        msg.className = 'alert alert-danger small py-2';
        msg.textContent = err.data?.errors ? Object.values(err.data.errors).flat().join(' ') : err.message;
    }
    ev.target.value = '';
});

// Km-stone markers
const markerLayer = L.layerGroup().addTo(map);
data.markers.forEach((m) => {
    L.marker([m.lat, m.lng], { title: `km ${m.km}` })
        .bindPopup(`<strong>km ${e(m.km)}</strong>${m.label ? `<br>${e(m.label)}` : ''}<br>
            <button class="btn btn-sm btn-outline-danger mt-1" data-delete="${m.delete_url}">Remove marker</button>`)
        .addTo(markerLayer);
});
map.on('popupopen', (ev) => {
    ev.popup.getElement().querySelector('[data-delete]')?.addEventListener('click', async (b) => {
        if (!confirm('Remove this km marker? Sections will be re-cut.')) return;
        await request(b.target.dataset.delete, { method: 'DELETE' });
        window.location.reload();
    });
});

let addingMarker = false;
const markerBtn = document.getElementById('markerBtn');
markerBtn?.addEventListener('click', () => {
    addingMarker = !addingMarker;
    markerBtn.classList.toggle('active', addingMarker);
    el.style.cursor = addingMarker ? 'crosshair' : '';
});
map.on('click', async (ev) => {
    if (!addingMarker) return;
    const km = prompt('Chainage of this km stone (km, e.g. 14.000):');
    if (!km) return;
    try {
        await request(data.urls.markers, { method: 'POST', body: { chainage_km: km, latitude: ev.latlng.lat, longitude: ev.latlng.lng } });
        window.location.reload();
    } catch (err) {
        alert(err.data?.errors ? Object.values(err.data.errors).flat().join('\n') : err.message);
    }
});

window.addEventListener('beforeunload', (ev) => {
    if (dirty) ev.preventDefault();
});
updateStats();
