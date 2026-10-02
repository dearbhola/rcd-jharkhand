import { request } from '../ui';

const cfgEl = document.getElementById('actionConfig');
const cfg = cfgEl ? JSON.parse(cfgEl.textContent) : null;
const esc = (v) => String(v ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);

function distance(a, b) {
    const r = 6371000, rad = (d) => (d * Math.PI) / 180;
    const h = Math.sin(rad(b.lat - a.lat) / 2) ** 2 + Math.cos(rad(a.lat)) * Math.cos(rad(b.lat)) * Math.sin(rad(b.lng - a.lng) / 2) ** 2;
    return 2 * r * Math.asin(Math.sqrt(h));
}

function setupForm(form) {
    const state = { pos: null, photos: [], videos: [], watch: null, capturedAt: null };
    const status = form.querySelector('[data-gps-status]');
    const errors = form.querySelector('[data-errors]');
    const list = form.querySelector('[data-evidence-list]');
    const help = form.querySelector('[data-evidence-help]');

    function startGps() {
        if (form.dataset.needsLocation !== '1' || state.watch !== null) return;
        if (!navigator.geolocation) {
            status.innerHTML = '<span class="text-danger">This browser cannot provide GPS.</span>';
            return;
        }
        state.watch = navigator.geolocation.watchPosition((p) => {
            state.pos = { lat: p.coords.latitude, lng: p.coords.longitude, accuracy: p.coords.accuracy };
            const d = Math.round(distance(state.pos, cfg.site));
            const goodFix = p.coords.accuracy <= cfg.maxAccuracy;
            status.innerHTML = `<i class="bi bi-geo-alt"></i> ±${Math.round(p.coords.accuracy)} m ·
                <strong class="${goodFix ? '' : 'text-warning'}">${d} m from the reported location</strong>${goodFix ? '' : ' · waiting for better accuracy'}`;
        }, (err) => {
            status.innerHTML = `<span class="text-danger">Location unavailable: ${esc(err.message)}</span>`;
        }, { enableHighAccuracy: true, maximumAge: 0, timeout: 30000 });
    }

    function renderEvidence() {
        if (!list) return;
        list.innerHTML = [...state.photos.map((f, i) => ['photo', f, i]), ...state.videos.map((f, i) => ['video', f, i])].map(([kind, f, i]) => `
            <div class="position-relative border rounded bg-white" style="width:84px;height:64px;overflow:hidden">
                ${kind === 'photo' ? `<img src="${URL.createObjectURL(f)}" style="width:100%;height:100%;object-fit:cover" alt="">` : '<i class="bi bi-camera-video fs-3 d-block text-center pt-2"></i>'}
                <button type="button" class="btn btn-sm btn-danger position-absolute top-0 end-0 py-0 px-1" data-remove="${kind}:${i}" aria-label="Remove">&times;</button>
            </div>`).join('');
        list.querySelectorAll('[data-remove]').forEach((b) => b.addEventListener('click', () => {
            const [kind, i] = b.dataset.remove.split(':');
            (kind === 'photo' ? state.photos : state.videos).splice(Number(i), 1);
            renderEvidence();
        }));
        help.textContent = `${state.photos.length} photo(s) (min ${help.dataset.min}, max ${help.dataset.max}) · ${state.videos.length} video(s)`;
    }

    form.querySelector('[data-photo-input]')?.addEventListener('change', (ev) => {
        state.photos.push(...ev.target.files);
        state.capturedAt ??= new Date().toISOString();
        ev.target.value = '';
        renderEvidence();
    });
    form.querySelector('[data-video-input]')?.addEventListener('change', (ev) => {
        state.videos.push(...ev.target.files);
        state.capturedAt ??= new Date().toISOString();
        ev.target.value = '';
        renderEvidence();
    });

    form.closest('.collapse')?.addEventListener('shown.bs.collapse', startGps);

    form.addEventListener('submit', async (ev) => {
        ev.preventDefault();
        errors.innerHTML = '';
        const override = form.querySelector('[name=location_override]')?.checked;
        if (form.dataset.needsLocation === '1' && !state.pos && !override) {
            errors.innerHTML = '<div class="alert alert-warning small py-1">Waiting for your GPS position…</div>';
            return;
        }
        const fd = new FormData(form);
        if (state.pos) {
            fd.set('latitude', state.pos.lat);
            fd.set('longitude', state.pos.lng);
            fd.set('accuracy', state.pos.accuracy);
        }
        if (state.capturedAt) fd.set('captured_at', state.capturedAt);
        [...state.photos, ...state.videos].forEach((f) => fd.append('evidence[]', f, f.name));

        const btn = form.querySelector('button:not([type=button])');
        const label = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Saving…';
        try {
            const res = await request(form.action, { method: 'POST', body: fd });
            if (state.watch !== null) navigator.geolocation.clearWatch(state.watch);
            window.location.href = res.url;
        } catch (err) {
            const messages = err.status === 409
                ? [`${err.message} <a href="" class="alert-link">Reload</a>`]
                : (err.data?.errors ? Object.values(err.data.errors).flat().map(esc) : [esc(err.message)]);
            errors.innerHTML = `<div class="alert alert-danger small py-2">${messages.join('<br>')}</div>`;
            btn.disabled = false;
            btn.innerHTML = label;
        }
    });

    renderEvidence();
}

if (cfg) document.querySelectorAll('form[data-action-form]').forEach(setupForm);
