<x-app-layout title="System settings" :breadcrumbs="['Administration', 'System settings']">
    <x-page-header title="System settings" subtitle="Business rules that are configurable without code changes. Every change is audited." />
    <div class="row g-3">
        <div class="col-lg-3 d-none d-lg-block">
            <nav class="card position-sticky" style="top: 70px">
                <div class="list-group list-group-flush small">
                    @foreach ($groups as $key => $label)
                        @if ($settings->has($key))<a href="#g-{{ $key }}" class="list-group-item list-group-item-action">{{ $label }}</a>@endif
                    @endforeach
                </div>
            </nav>
        </div>
        <div class="col-lg-9">
            @foreach ($groups as $group => $label)
                @continue(! $settings->has($group))
                <form method="POST" action="{{ route('admin.settings.update') }}" class="card mb-3" id="g-{{ $group }}" novalidate>
                    @csrf @method('PUT')
                    <div class="card-header d-flex justify-content-between align-items-center">{{ $label }}
                        <button class="btn btn-sm btn-primary"><i class="bi bi-check2" aria-hidden="true"></i> Save {{ strtolower($label) }}</button></div>
                    <div class="list-group list-group-flush">
                        @foreach ($settings[$group] as $s)
                            @php($name = 'settings['.$s->key.']')
                            @php($id = 's_'.str_replace('.', '_', $s->key))
                            @php($error = $errors->first('settings.'.$s->key))
                            <div class="list-group-item">
                                <div class="row g-2 align-items-center">
                                    <div class="col-md-7">
                                        <label for="{{ $id }}" class="fw-semibold small mb-0">{{ $s->description }}</label>
                                        <div class="small text-muted"><code>{{ $s->key }}</code>{{ $s->is_public ? ' · shared with the mobile app' : '' }}
                                            @if ($s->updater) · changed by {{ $s->updater->name }} {{ $s->updated_at->diffForHumans() }}@endif</div>
                                    </div>
                                    <div class="col-md-5">
                                        @if ($s->type === 'bool')
                                            <select id="{{ $id }}" name="{{ $name }}" @class(['form-select form-select-sm', 'is-invalid' => $error])>
                                                <option value="1" @selected($s->value === '1')>Yes</option>
                                                <option value="0" @selected($s->value !== '1')>No</option>
                                            </select>
                                        @elseif ($s->type === 'json')
                                            <textarea id="{{ $id }}" name="{{ $name }}" rows="2" @class(['form-control form-control-sm font-monospace', 'is-invalid' => $error])>{{ old('settings.'.$s->key, $s->value) }}</textarea>
                                        @else
                                            <input id="{{ $id }}" name="{{ $name }}" value="{{ old('settings.'.$s->key, $s->value) }}"
                                                   type="{{ in_array($s->type, ['int', 'float'], true) ? 'number' : 'text' }}" {{ $s->type === 'float' ? 'step=any' : '' }}
                                                   @class(['form-control form-control-sm', 'is-invalid' => $error])>
                                        @endif
                                        @if ($error)<div class="invalid-feedback">{{ $error }}</div>@endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </form>
            @endforeach
        </div>
    </div>
</x-app-layout>
