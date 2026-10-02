import 'leaflet.markercluster';
import 'leaflet.markercluster/dist/MarkerCluster.css';
import 'leaflet.markercluster/dist/MarkerCluster.Default.css';
import { L, createMap, escapeHtml as e, debounce } from './base';
import { request } from '../ui';

const el = document.getElementById('rcdMap');
const form = document.getElementById('mapFilters');
const panel = document.getElementById('mapInfo');
const map = createMap(el);
const urls = JSON.parse(el.dataset.urls);

const COLORS = { contractor: '#198754', department: '#e07b00', inactive: '#6c757d', selected: '#0d6efd' };
let selected = null;

const sectionsLayer = L.geoJSON(null, {
    style: (f) => ({
        color: f.properties.status !== 'active' ? COLORS.inactive : f.properties.maintenance ? COLORS.contractor : COLORS.department,
        weight: 4,
        opacity: 0.9,
        dashArray: f.properties.is_test ? '6 4' : null,
    }),
    onEachFeature: (f, layer) => {
        layer.bindTooltip(`${e(f.properties.road)} / ${e(f.properties.section)}<br><small>km ${e(f.properties.km)}</small>`, { sticky: true });
        layer.on('click', () => showSection(f.properties.section_id, layer));
    },
}).addTo(map);

const assetsLayer = L.markerClusterGroup({ disableClusteringAtZoom: 15, showCoverageOnHover: false });
const issuesLayer = L.layerGroup();
map.addLayer(assetsLayer);
map.addLayer(issuesLayer);

L.control.layers(null, { 'Road sections': sectionsLayer, Assets: assetsLayer, 'Damage reports': issuesLayer }, { collapsed: false }).addTo(map);

const legend = L.control({ position: 'bottomright' });
legend.onAdd = () => {
    const div = L.DomUtil.create('div', 'bg-white p-2 rounded shadow-sm small');
    div.innerHTML = `<div><span style="display:inline-block;width:18px;border-top:4px solid ${COLORS.contractor}"></span> In maintenance (contractor)</div>
        <div><span style="display:inline-block;width:18px;border-top:4px solid ${COLORS.department}"></span> No maintenance (department)</div>
        <div><span style="display:inline-block;width:18px;border-top:4px dashed #555"></span> Test data</div>`;
    return div;
};
legend.addTo(map);

function params(extra = {}) {
    const p = new URLSearchParams(new FormData(form));
    p.set('bbox', map.getBounds().toBBoxString());
    p.set('zoom', map.getZoom());
    Object.entries(extra).forEach(([k, v]) => p.set(k, v));
    [...p.keys()].forEach((k) => !p.get(k) && p.delete(k));
    return p;
}

const status = document.getElementById('mapStatus');
async function load() {
    status.textContent = 'Loading…';
    try {
        const [sections, assets, reports] = await Promise.all([
            request(`${urls.sections}?${params()}`),
            map.hasLayer(assetsLayer) ? request(`${urls.assets}?${params()}`) : Promise.resolve({ features: [] }),
            map.hasLayer(issuesLayer) ? request(`${urls.reports}?${params()}`) : Promise.resolve({ features: [] }),
        ]);
        issuesLayer.clearLayers();
        reports.features.forEach((f) => {
            const [lng, lat] = f.geometry.coordinates;
            const p = f.properties;
            L.circleMarker([lat, lng], { radius: 8, color: '#fff', weight: 2, fillColor: p.color, fillOpacity: 1, dashArray: p.is_test ? '2 2' : null })
                .bindPopup(`<strong><a href="${p.url}">${e(p.report_no)}</a></strong><br>${e(p.category)} · ${e(p.severity)}<br>
                    ${e(p.road)} km ${e(p.km)} · ${e(p.date)}<br><span class="badge text-bg-secondary">${e(p.status_label)}</span>`)
                .addTo(issuesLayer);
        });
        sectionsLayer.clearLayers().addData(sections);
        assetsLayer.clearLayers();
        assets.features.forEach((f) => {
            const [lng, lat] = f.geometry.coordinates;
            L.circleMarker([lat, lng], { radius: 6, color: '#0b2a4a', weight: 2, fillColor: '#ffc107', fillOpacity: 0.95 })
                .bindPopup(`<strong>${e(f.properties.code)}</strong><br>${e(f.properties.type)} · ${e(f.properties.name)}<br>
                    ${e(f.properties.road)} km ${e(f.properties.km)}<br><a href="${urls.assetBase}/${f.properties.asset_id}">Open asset</a>`)
                .addTo(assetsLayer);
        });
        status.textContent = `${sections.features.length} sections · ${assets.features.length} assets · ${reports.features.length} reports in view${sections.truncated ? ' (zoom in for all)' : ''}`;
    } catch (err) {
        status.textContent = `Could not load map data: ${err.message}`;
    }
}

const person = (p) => (p ? `${e(p.name)}${p.mobile ? ` <span class="text-muted">· ${e(p.mobile)}</span>` : ''}` : '<span class="text-warning">not mapped</span>');

async function showSection(id, layer) {
    if (selected) sectionsLayer.resetStyle(selected);
    selected = layer;
    layer.setStyle({ color: COLORS.selected, weight: 7 });
    panel.innerHTML = '<div class="text-muted small">Loading…</div>';
    const d = await request(urls.sectionInfo.replace(':id', id));
    panel.innerHTML = `
        <div class="small text-uppercase text-muted">Road</div>
        <div class="fw-semibold"><a href="${d.road.url}">${e(d.road.code)}</a> · ${e(d.road.name)}</div>
        <div class="small text-uppercase text-muted mt-2">Section</div>
        <div><a href="${d.section.url}">${e(d.section.code)}</a> · km ${e(d.section.km)}</div>
        <div class="small text-uppercase text-muted mt-2">Contract</div>
        <div>${d.contract ? `<a href="${d.contract.url}">${e(d.contract.no)}</a><br><small>Maintenance ${e(d.contract.maintenance)}</small>
            <span class="badge ${d.contract.maintenance_active ? 'text-bg-success' : 'text-bg-secondary'}">${d.contract.maintenance_active ? 'active' : 'not active'}</span>` : '<span class="text-muted">No contract — department</span>'}</div>
        <div class="small text-uppercase text-muted mt-2">Contractor</div><div>${d.contractor ? person(d.contractor) : '—'}</div>
        <div class="small text-uppercase text-muted mt-2">JE</div><div>${person(d.je)}</div>
        <div class="small text-uppercase text-muted mt-2">AE</div><div>${person(d.ae)}</div>
        <div class="small text-uppercase text-muted mt-2">EE</div><div>${person(d.ee)}</div>
        <div class="small text-uppercase text-muted mt-2">Open issues</div><div>${d.open_issues ? `<a href="${d.open_issues_url}">${d.open_issues} open report(s)</a>` : 'None'}</div>
        <div class="small text-uppercase text-muted mt-2">Repair history</div><div class="text-muted">${d.repair_history ?? 'Available once the repair workflow is enabled'}</div>`;
}

const reload = debounce(load, 250);
map.on('moveend', reload);
map.on('overlayadd overlayremove', reload);
form.addEventListener('change', reload);
form.addEventListener('submit', (ev) => {
    ev.preventDefault();
    load();
});
load();
