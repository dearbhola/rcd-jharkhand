import { L, createMap } from '../gis/base';

const el = document.getElementById('reportMap');
if (el) {
    const d = JSON.parse(el.dataset.report);
    const map = createMap(el);
    if (d.section) L.geoJSON(d.section, { style: { color: '#0d4f8b', weight: 5, opacity: 0.6 } }).addTo(map);
    const point = L.circleMarker([d.lat, d.lng], { radius: 8, color: '#fff', weight: 2, fillColor: d.color, fillOpacity: 1 }).addTo(map);
    if (d.accuracy) L.circle([d.lat, d.lng], { radius: d.accuracy, color: d.color, weight: 1, fillOpacity: 0.08 }).addTo(map);
    point.bindTooltip(d.label, { permanent: false });
    map.setView([d.lat, d.lng], 17);
}
