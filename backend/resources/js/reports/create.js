import { request } from '../ui';
import { L, createMap, escapeHtml as e } from '../gis/base';

const cfg = JSON.parse(document.getElementById('reportConfig').textContent);
const $ = (id) => document.getElementById(id);
const state = { pos: null, preview: null, typeId: cfg.roadTypeId, photos: [], videos: [], override: false, lastPreviewAt: null };

// Keep the same client_uuid across retries of this form (idempotent submission).
const uuidKey = 'rcd-report-uuid';
const clientUuid = sessionStorage.getItem(uuidKey) || cfg.clientUuid;
sessionStorage.setItem(uuidKey, clientUuid);

/* ---------- Location ---------- */
function accuracyBadge(acc) {
    const ok = acc <= cfg.maxAccuracy;
    $('gpsAccuracy').className = `badge ${ok ? 'text-bg-success' : 'text-bg-warning'}`;
    $('gpsAccuracy').textContent = `±${Math.round(acc)} m${ok ? '' : ` (need ≤ ${cfg.maxAccuracy} m)`}`;
}

function distance(a, b) {
    const r = 6371000, toRad = (d) => (d * Math.PI) / 180;
    const dLat = toRad(b.lat - a.lat), dLng = toRad(b.lng - a.lng);
    const h = Math.sin(dLat / 2) ** 2 + Math.cos(toRad(a.lat)) * Math.cos(toRad(b.lat)) * Math.sin(dLng / 2) ** 2;
    return 2 * r * Math.asin(Math.sqrt(h));
}

async function runPreview() {
    const p = state.pos;
    $('locationStatus').innerHTML = '<span class="text-muted">Checking your location against the road network…</span>';
    const q = new URLSearchParams({ lat: p.lat, lng: p.lng, accuracy: p.accuracy, override: state.override ? 1 : 0 });
    try {
        const d = await request(`${cfg.urls.preview}?${q}`);
        state.preview = d.ok ? d : null;
        state.lastPreviewAt = { ...p };
        if (!d.ok) {
            $('locationStatus').innerHTML = `<div class="alert alert-warning mb-0 py-2"><i class="bi bi-exclamation-triangle"></i> ${e(d.message)}</div>`;
        } else {
            $('locationStatus').innerHTML = `<div class="alert alert-success mb-0 py-2">
                <div class="fw-semibold"><i class="bi bi-signpost-2"></i> ${e(d.road.code)} · ${e(d.road.name)}</div>
                <div class="small">Section ${e(d.section)} · <strong>km ${e(d.chainage_km)}</strong> · ${d.distance_m} m from the road centre line
                ${d.asset ? `<br><i class="bi bi-bricks"></i> Near ${e(d.asset.type)}: ${e(d.asset.name)}` : ''}
                ${d.override ? '<br><span class="badge text-bg-dark">TEST MODE · location override</span>' : ''}</div></div>`;
        }
    } catch (err) {
        state.preview = null;
        $('locationStatus').innerHTML = `<div class="alert alert-danger mb-0 py-2">Could not check location: ${e(err.message)}</div>`;
    }
    renderTypes();
    refreshSubmit();
}

function onPosition(position) {
    if (state.override) return;
    const c = position.coords;
    state.pos = { lat: c.latitude, lng: c.longitude, accuracy: c.accuracy, fixAt: new Date(position.timestamp).toISOString() };
    $('gpsCoords').textContent = `${c.latitude.toFixed(6)}, ${c.longitude.toFixed(6)}`;
    accuracyBadge(c.accuracy);
    if (c.accuracy > cfg.maxAccuracy) {
        $('locationStatus').innerHTML = '<span class="text-muted">Waiting for a more accurate GPS fix… move to open sky.</span>';
        return;
    }
    // Re-check only when we moved noticeably (saves requests while walking).
    if (!state.lastPreviewAt || distance(state.lastPreviewAt, state.pos) > 15) runPreview();
}

function startGps() {
    if (!navigator.geolocation) {
        $('locationStatus').innerHTML = '<div class="alert alert-danger mb-0">This browser cannot provide GPS. Use a phone with location enabled.</div>';
        return;
    }
    navigator.geolocation.watchPosition(onPosition, (err) => {
        $('locationStatus').innerHTML = `<div class="alert alert-danger mb-0 py-2">Location unavailable: ${e(err.message)}. Allow location access and enable GPS.</div>`;
    }, { enableHighAccuracy: true, maximumAge: 0, timeout: 30000 });
}

$('refreshGps').addEventListener('click', () => {
    state.lastPreviewAt = null;
    if (state.pos) runPreview();
});

/* Test-mode override: authorised users pick the point on a map. */
if (cfg.canOverride) {
    let map, marker;
    $('overrideToggle').addEventListener('change', (ev) => {
        state.override = ev.target.checked;
        $('overrideMapWrap').classList.toggle('d-none', !state.override);
        state.preview = null;
        state.lastPreviewAt = null;
        if (state.override && !map) {
            map = createMap($('overrideMap'), cfg.map);
            map.on('click', (m) => {
                marker ? marker.setLatLng(m.latlng) : (marker = L.marker(m.latlng).addTo(map));
                state.pos = { lat: m.latlng.lat, lng: m.latlng.lng, accuracy: 1, fixAt: new Date().toISOString() };
                $('gpsCoords').textContent = `${m.latlng.lat.toFixed(6)}, ${m.latlng.lng.toFixed(6)} (picked)`;
                accuracyBadge(1);
                runPreview();
            });
        }
        if (!state.override) startGps();
        refreshSubmit();
    });
}

/* ---------- What / category / severity ---------- */
function renderTypes() {
    const types = cfg.types.filter((t) => t.id === cfg.roadTypeId || (state.preview?.asset && t.id === state.preview.asset.type_id));
    if (!types.some((t) => t.id === state.typeId)) state.typeId = cfg.roadTypeId;
    $('typeChoices').innerHTML = types.map((t) => `
        <input type="radio" class="btn-check" name="asset_type_id" id="type${t.id}" value="${t.id}" ${t.id === state.typeId ? 'checked' : ''}>
        <label class="btn btn-outline-primary btn-lg" for="type${t.id}">${e(t.name)}</label>`).join('');
    $('typeChoices').querySelectorAll('input').forEach((i) => i.addEventListener('change', () => {
        state.typeId = Number(i.value);
        renderCategories();
    }));
    renderCategories();
}

function renderCategories() {
    const type = cfg.types.find((t) => t.id === state.typeId);
    const cats = type ? type.categories : [];
    $('categoryChoices').innerHTML = cats.map((c) => `
        <input type="radio" class="btn-check" name="issue_category_id" id="cat${c.id}" value="${c.id}">
        <label class="btn btn-outline-secondary" for="cat${c.id}">${e(c.name)}</label>`).join('') || '<span class="text-muted small">No categories configured.</span>';
    $('categoryChoices').querySelectorAll('input').forEach((i) => i.addEventListener('change', () => { checkDuplicates(); refreshSubmit(); }));
    $('duplicateWarning').innerHTML = '';
    refreshSubmit();
}

$('severityChoices').innerHTML = cfg.severities.map((s) => `
    <input type="radio" class="btn-check" name="severity_id" id="sev${s.id}" value="${s.id}">
    <label class="btn btn-outline-dark" for="sev${s.id}" style="--bs-btn-active-bg:${s.color};--bs-btn-active-border-color:${s.color}">${e(s.name)}</label>`).join('');
$('severityChoices').querySelectorAll('input').forEach((i) => i.addEventListener('change', refreshSubmit));

async function checkDuplicates() {
    const cat = document.querySelector('input[name=issue_category_id]:checked');
    if (!cat || !state.pos || !state.preview) return;
    const q = new URLSearchParams({ lat: state.pos.lat, lng: state.pos.lng, category_id: cat.value });
    const d = await request(`${cfg.urls.duplicates}?${q}`).catch(() => ({ count: 0 }));
    $('duplicateWarning').innerHTML = d.count
        ? `<div class="alert alert-warning py-2 small mb-0"><i class="bi bi-files"></i> <strong>Possible duplicate:</strong>
            ${d.count} similar report(s) already filed within a short distance recently.
            ${d.items.map((i) => `<br><a href="${i.url}" target="_blank">${e(i.report_no)}</a> · ${i.distance_m} m · ${e(i.reported)}`).join('')}
            <br>You can still submit if this is a different problem.</div>`
        : '';
}

/* ---------- Evidence ---------- */
const kb = (n) => Math.round(n / 1024);
function renderEvidence() {
    const items = [...state.photos.map((f, i) => ({ f, i, kind: 'photo' })), ...state.videos.map((f, i) => ({ f, i, kind: 'video' }))];
    $('evidenceList').innerHTML = items.map(({ f, i, kind }) => `
        <div class="col-4 col-md-3">
            <div class="border rounded p-1 position-relative text-center small">
                ${kind === 'photo' ? `<img src="${URL.createObjectURL(f)}" class="img-fluid rounded" style="height:90px;object-fit:cover;width:100%" alt="">`
                    : '<div class="py-4"><i class="bi bi-camera-video fs-3"></i></div>'}
                <div class="text-truncate">${kb(f.size)} KB</div>
                <button type="button" class="btn btn-sm btn-danger position-absolute top-0 end-0 py-0 px-1" data-remove="${kind}:${i}" aria-label="Remove">&times;</button>
            </div>
        </div>`).join('');
    $('evidenceList').querySelectorAll('[data-remove]').forEach((b) => b.addEventListener('click', () => {
        const [kind, i] = b.dataset.remove.split(':');
        (kind === 'photo' ? state.photos : state.videos).splice(Number(i), 1);
        renderEvidence();
    }));
    const ev = cfg.evidence;
    $('evidenceHelp').textContent = `${state.photos.length}/${ev.maxPhotos} photos (min ${ev.minPhotos}) · ${state.videos.length}/${ev.maxVideos} video${ev.videoRequired ? ' (required)' : ''}`;
    refreshSubmit();
}

function addFiles(input, list, max, maxKb, label) {
    for (const f of input.files) {
        if (list.length >= max) { alert(`At most ${max} ${label}.`); break; }
        if (kb(f.size) > maxKb) { alert(`${f.name} is larger than ${maxKb} KB.`); continue; }
        list.push(f);
    }
    if (!state.capturedAt) state.capturedAt = new Date().toISOString();
    input.value = '';
    renderEvidence();
}
$('photoInput').addEventListener('change', (ev) => addFiles(ev.target, state.photos, cfg.evidence.maxPhotos, cfg.evidence.maxImageKb, 'photos'));
$('videoInput')?.addEventListener('change', (ev) => {
    const f = ev.target.files[0];
    if (f) {
        const v = document.createElement('video');
        v.preload = 'metadata';
        v.onloadedmetadata = () => {
            URL.revokeObjectURL(v.src);
            if (v.duration > cfg.evidence.maxVideoSeconds + 0.5) { alert(`Video must be at most ${cfg.evidence.maxVideoSeconds} seconds.`); ev.target.value = ''; return; }
            addFiles(ev.target, state.videos, cfg.evidence.maxVideos, cfg.evidence.maxVideoKb, 'videos');
        };
        v.onerror = () => addFiles(ev.target, state.videos, cfg.evidence.maxVideos, cfg.evidence.maxVideoKb, 'videos');
        v.src = URL.createObjectURL(f);
    }
});

/* ---------- Submit ---------- */
function ready() {
    const ev = cfg.evidence;
    return state.preview && document.querySelector('input[name=issue_category_id]:checked') && document.querySelector('input[name=severity_id]:checked')
        && state.photos.length >= ev.minPhotos && (!ev.videoRequired || state.videos.length > 0);
}
function refreshSubmit() {
    $('submitBtn').disabled = !ready();
}

$('reportForm').addEventListener('submit', async (ev) => {
    ev.preventDefault();
    if (!ready()) return;
    const btn = $('submitBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Uploading…';
    const fd = new FormData();
    fd.append('client_uuid', clientUuid);
    fd.append('latitude', state.pos.lat);
    fd.append('longitude', state.pos.lng);
    fd.append('accuracy', state.pos.accuracy);
    fd.append('gps_fix_at', state.pos.fixAt);
    fd.append('captured_at', state.capturedAt || new Date().toISOString());
    fd.append('asset_type_id', state.typeId);
    fd.append('issue_category_id', document.querySelector('input[name=issue_category_id]:checked').value);
    fd.append('severity_id', document.querySelector('input[name=severity_id]:checked').value);
    fd.append('description', $('description').value);
    fd.append('location_override', state.override ? 1 : 0);
    [...state.photos, ...state.videos].forEach((f) => fd.append('evidence[]', f, f.name));
    try {
        const res = await request(cfg.urls.store, { method: 'POST', body: fd });
        sessionStorage.removeItem(uuidKey);
        window.location.href = res.url;
    } catch (err) {
        const errors = err.data?.errors ? Object.values(err.data.errors).flat() : [err.status ? err.message : 'Network error — your report was not lost; tap Submit again when you have signal.'];
        $('submitErrors').innerHTML = `<div class="alert alert-danger small">${errors.map(e).join('<br>')}</div>`;
        btn.innerHTML = '<i class="bi bi-send"></i> Submit report';
        refreshSubmit();
    }
});

startGps();
renderTypes();
renderEvidence();
