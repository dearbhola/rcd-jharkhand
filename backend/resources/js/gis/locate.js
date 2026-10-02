import { L, createMap, escapeHtml as e } from './base';
import { request } from '../ui';

const el = document.getElementById('locateMap');
const out = document.getElementById('locateResult');
const form = document.getElementById('locateForm');
const map = createMap(el);
const url = el.dataset.resolveUrl;
let overlay = L.layerGroup().addTo(map);

const person = (p) => (p ? `${e(p.name)}${p.employee_code ? ` (${e(p.employee_code)})` : ''}` : '<span class="badge text-bg-warning">not mapped</span>');

async function resolve(lat, lng) {
    form.lat.value = lat.toFixed(7);
    form.lng.value = lng.toFixed(7);
    const radius = Number(form.radius.value) || 50;
    overlay.clearLayers();
    L.circle([lat, lng], { radius, color: '#0d4f8b', weight: 1, fillOpacity: 0.08 }).addTo(overlay);
    L.circleMarker([lat, lng], { radius: 6, color: '#dc3545', fillOpacity: 1 }).addTo(overlay);
    out.innerHTML = '<div class="text-muted small">Resolving…</div>';

    const p = new URLSearchParams({ lat, lng, radius, date: form.date.value });
    const d = await request(`${url}?${p}`);
    if (!d.found) {
        out.innerHTML = `<div class="alert alert-warning mb-0"><i class="bi bi-geo-alt"></i> ${e(d.message)}<div class="small">No active road section within ${d.radius_m} m.</div></div>`;
        return;
    }
    const m = d.match;
    L.polyline([[lat, lng], [m.snapped.lat, m.snapped.lng]], { color: '#dc3545', dashArray: '4 4' }).addTo(overlay);
    L.circleMarker([m.snapped.lat, m.snapped.lng], { radius: 5, color: '#198754', fillOpacity: 1 }).addTo(overlay);
    const route = m.route === 'contractor'
        ? '<span class="badge text-bg-success">Contractor workflow</span>'
        : '<span class="badge text-bg-warning">Department workflow</span>';
    out.innerHTML = `
        <table class="table table-sm small mb-2">
            <tr><th>Road</th><td>${e(m.road.code)} · ${e(m.road.name)}</td></tr>
            <tr><th>Section</th><td>${e(m.section.code)}</td></tr>
            <tr><th>Chainage</th><td><strong>${e(m.chainage_km)} km</strong></td></tr>
            <tr><th>Distance from road</th><td>${m.distance_m} m</td></tr>
            <tr><th>Asset</th><td>${m.asset ? `${e(m.asset.code)} (${e(m.asset.type)}, ${m.asset.distance_m} m)` : '—'}</td></tr>
            <tr><th>Contract</th><td>${m.contract ? `${e(m.contract.contract_no)}<br><small>Maintenance ${e(m.contract.maintenance_start)} → ${e(m.contract.maintenance_end)}</small>` : '—'}</td></tr>
            <tr><th>Contractor</th><td>${m.contractor ? e(m.contractor.name) : '—'}</td></tr>
            <tr><th>JE</th><td>${person(m.je)}</td></tr>
            <tr><th>AE</th><td>${person(m.ae)}</td></tr>
            <tr><th>EE</th><td>${person(m.ee)}</td></tr>
            <tr><th>Route</th><td>${route}</td></tr>
        </table>
        ${m.gaps.length ? `<div class="alert alert-danger small py-1 mb-2">Missing mapping: ${m.gaps.join(', ')}. Reports here cannot be routed.</div>` : ''}
        ${d.alternatives.length ? `<div class="small text-muted">Also within radius: ${d.alternatives.map((a) => `${e(a.road.code)} (${a.distance_m} m)`).join(', ')}</div>` : ''}`;
}

map.on('click', (ev) => resolve(ev.latlng.lat, ev.latlng.lng));
form.addEventListener('submit', (ev) => {
    ev.preventDefault();
    const lat = parseFloat(form.lat.value);
    const lng = parseFloat(form.lng.value);
    if (!Number.isNaN(lat) && !Number.isNaN(lng)) {
        map.setView([lat, lng], Math.max(map.getZoom(), 15));
        resolve(lat, lng);
    }
});
document.getElementById('myLocation').addEventListener('click', () => {
    if (!navigator.geolocation) return alert('Geolocation is not available in this browser.');
    navigator.geolocation.getCurrentPosition(
        (pos) => {
            map.setView([pos.coords.latitude, pos.coords.longitude], 16);
            out.insertAdjacentHTML('afterbegin', `<div class="small text-muted mb-1">Device accuracy ±${Math.round(pos.coords.accuracy)} m</div>`);
            resolve(pos.coords.latitude, pos.coords.longitude);
        },
        (err) => alert(`Could not get location: ${err.message}`),
        { enableHighAccuracy: true, timeout: 15000 },
    );
});
