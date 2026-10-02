/**
 * Small progressive-enhancement helpers shared by all pages. No framework.
 */

/** fetch() wrapper with CSRF + JSON defaults. Throws on non-2xx with the parsed body attached. */
export async function request(url, { method = 'GET', body = null, headers = {} } = {}) {
    const token = document.querySelector('meta[name="csrf-token"]')?.content;
    const isForm = body instanceof FormData;
    const response = await fetch(url, {
        method,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...(token ? { 'X-CSRF-TOKEN': token } : {}),
            ...(body && !isForm ? { 'Content-Type': 'application/json' } : {}),
            ...headers,
        },
        body: body ? (isForm ? body : JSON.stringify(body)) : null,
    });
    const data = response.headers.get('content-type')?.includes('json') ? await response.json() : null;
    if (!response.ok) {
        const error = new Error(data?.message || `Request failed (${response.status})`);
        error.status = response.status;
        error.data = data;
        throw error;
    }
    return data;
}

function initSidebar() {
    const sidebar = document.getElementById('rcdSidebar');
    document.querySelectorAll('[data-rcd-toggle="sidebar"]').forEach((btn) =>
        btn.addEventListener('click', () => sidebar?.classList.toggle('show')),
    );
}

/** <form data-confirm="Are you sure?"> asks before submitting. */
function initConfirmForms() {
    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (form instanceof HTMLFormElement && form.dataset.confirm && !window.confirm(form.dataset.confirm)) {
            event.preventDefault();
        }
    });
}

/** Auto-submit filter forms when a select changes: <form data-autosubmit>. */
function initAutoSubmit() {
    document.querySelectorAll('form[data-autosubmit] select').forEach((select) =>
        select.addEventListener('change', () => select.form.requestSubmit()),
    );
}

/** Prevent double submission: disable submit buttons once a form is sent. */
function initSubmitOnce() {
    document.addEventListener('submit', (event) => {
        if (event.defaultPrevented) return;
        event.target.querySelectorAll?.('button[type="submit"]').forEach((b) => {
            b.disabled = true;
        });
    });
}

/** Toggle every checkbox in a group: <input type="checkbox" data-check-all="group-name">. */
function initCheckAll() {
    document.querySelectorAll('[data-check-all]').forEach((master) =>
        master.addEventListener('change', () => {
            document
                .querySelectorAll(`input[type="checkbox"][data-group="${master.dataset.checkAll}"]`)
                .forEach((cb) => {
                    cb.checked = master.checked;
                });
        }),
    );
}

/**
 * Dependent selects: <select data-depends-on="#f_division_id"> whose <option data-parent="id">
 * entries are shown only when they match the parent select's value.
 */
function initDependentSelects() {
    document.querySelectorAll('select[data-depends-on]').forEach((child) => {
        const parent = document.querySelector(child.dataset.dependsOn);
        if (!parent) return;
        const apply = () => {
            child.querySelectorAll('option[data-parent]').forEach((opt) => {
                const visible = !parent.value || opt.dataset.parent === parent.value;
                opt.hidden = !visible;
                if (!visible && opt.selected) child.value = '';
            });
        };
        parent.addEventListener('change', apply);
        apply();
    });
}

/**
 * Road → section loader: <select data-sections-for="#roadSelect" data-url="/roads/:id/sections.json">.
 * Fetches only the chosen road's sections instead of shipping every section to the page.
 */
function initSectionLoaders() {
    document.querySelectorAll('select[data-sections-for]').forEach((target) => {
        const road = document.querySelector(target.dataset.sectionsFor);
        if (!road) return;
        const placeholder = target.dataset.placeholder || 'Whole road';
        const load = async () => {
            const selected = target.dataset.selected || '';
            target.innerHTML = `<option value="">${placeholder}</option>`;
            if (!road.value) return;
            try {
                const data = await request(target.dataset.url.replace(':id', road.value));
                data.sections.forEach((s) => target.add(new Option(s.label, s.id, false, String(s.id) === selected)));
            } catch {
                target.add(new Option('Could not load sections', '', false, false));
            }
        };
        road.addEventListener('change', () => {
            target.dataset.selected = '';
            load();
        });
        load();
    });
}

/**
 * Section checkbox list for a road: <div data-section-checks-for="#roadSelect" data-url=".../:id/sections.json"
 * data-name="sections[]" data-selected="1,2">. Includes a "select all" toggle.
 */
function initSectionChecklists() {
    document.querySelectorAll('[data-section-checks-for]').forEach((box) => {
        const road = document.querySelector(box.dataset.sectionChecksFor);
        if (!road) return;
        const render = async () => {
            const selected = (box.dataset.selected || '').split(',').filter(Boolean);
            box.innerHTML = road.value ? '<div class="text-muted small">Loading sections…</div>' : '<div class="text-muted small">Choose a road first.</div>';
            if (!road.value) return;
            const data = await request(box.dataset.url.replace(':id', road.value));
            if (!data.sections.length) {
                box.innerHTML = '<div class="text-warning small">This road has no sections.</div>';
                return;
            }
            const all = `<div class="form-check mb-1"><input class="form-check-input" type="checkbox" id="secAll" data-check-all="sections">
                <label class="form-check-label fw-semibold small" for="secAll">All sections</label></div>`;
            box.innerHTML = all + data.sections.map((s) => `
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="${box.dataset.name}" value="${s.id}" id="sec${s.id}" data-group="sections"
                        ${selected.includes(String(s.id)) ? 'checked' : ''}>
                    <label class="form-check-label small" for="sec${s.id}">${s.label}${s.status !== 'active' ? ' <span class="badge text-bg-secondary">inactive</span>' : ''}</label>
                </div>`).join('');
            initCheckAll();
        };
        road.addEventListener('change', () => {
            box.dataset.selected = '';
            render();
        });
        render();
    });
}

export function initUi() {
    initDependentSelects();
    initSectionLoaders();
    initSectionChecklists();
    initSidebar();
    initConfirmForms();
    initAutoSubmit();
    initSubmitOnce();
    initCheckAll();
}
