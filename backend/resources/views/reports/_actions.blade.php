@php
    $style = fn ($code) => match ($code) {
        'reject', 'invalidate' => ['danger', 'x-octagon'],
        'approve', 'accept', 'validate' => ['success', 'check2-circle'],
        'merge_duplicate' => ['secondary', 'files'],
        'submit_repair' => ['primary', 'tools'],
        'acknowledge' => ['primary', 'play-circle'],
        default => ['primary', 'arrow-right-circle'],
    };
@endphp
@if ($actions->isNotEmpty())
    <div class="card mb-3 border-primary">
        <div class="card-header bg-primary-subtle d-flex justify-content-between">
            <span><i class="bi bi-lightning-charge me-1"></i>Your action</span>
            @if ($sla)
                <span class="small {{ $sla->due_at->isPast() ? 'text-danger fw-semibold' : 'text-muted' }}">
                    {{ $sla->due_at->isPast() ? 'Overdue by '.$sla->due_at->diffForHumans(null, true) : 'Due '.$sla->due_at->diffForHumans() }}
                </span>
            @endif
        </div>
        <div class="card-body vstack gap-2">
            @foreach ($actions as $t)
                @php
                    [$colour, $icon] = $style($t->action_code);
                    $guards = $t->guards ?? [];
                    $evidenceContext = in_array('evidence.repair_requirements', $guards, true) ? 'repair' : (in_array('evidence.inspection_requirements', $guards, true) ? 'inspection' : '');
                    $fid = 'act-'.$t->action_code;
                @endphp
                <button class="btn btn-{{ $colour }} text-start" type="button" data-bs-toggle="collapse" data-bs-target="#{{ $fid }}" aria-expanded="false">
                    <i class="bi bi-{{ $icon }} me-1"></i>{{ $t->name }}
                </button>
                <div class="collapse" id="{{ $fid }}">
                    <form class="border rounded p-3 bg-light" data-action-form novalidate
                          action="{{ route('reports.actions', [$report, $t->action_code]) }}"
                          data-needs-location="{{ $t->requires_location ? 1 : 0 }}" data-evidence="{{ $evidenceContext }}">
                        <input type="hidden" name="version" value="{{ $instance->version }}">

                        @if ($t->action_code === 'submit_repair')
                            <label class="form-label required small">Repair carried out</label>
                            <textarea name="repair_description" class="form-control mb-2" rows="2" required placeholder="e.g. Pothole cut, cleaned, tack coat and BC laid, compacted"></textarea>
                        @endif

                        @if ($t->action_code === 'merge_duplicate')
                            <label class="form-label required small">Duplicate of</label>
                            <select name="duplicate_of" class="form-select mb-2" required>
                                <option value="">— choose original report —</option>
                                @foreach ($duplicates as $d)
                                    <option value="{{ $d['report']->id }}">{{ $d['report']->report_no }} · {{ round($d['distance_m']) }} m · {{ $d['report']->created_at->diffForHumans() }}</option>
                                @endforeach
                            </select>
                        @endif

                        @if ($t->requires_location)
                            <div class="small mb-2" data-gps-status><i class="bi bi-geo-alt"></i> Getting your GPS position…</div>
                            @if ($actionConfig['canOverride'])
                                <div class="form-check small mb-2">
                                    <input class="form-check-input" type="checkbox" name="location_override" value="1" id="{{ $fid }}-ovr">
                                    <label class="form-check-label" for="{{ $fid }}-ovr">Test mode: skip the on-site check (flagged)</label>
                                </div>
                            @endif
                        @endif

                        @if ($evidenceContext)
                            @php($rules = $actionConfig['evidence'][$evidenceContext])
                            <div class="mb-2">
                                <label class="btn btn-outline-primary btn-sm mb-0"><i class="bi bi-camera"></i> Take photo
                                    <input type="file" accept="image/*" capture="environment" multiple hidden data-photo-input></label>
                                @if ($rules['maxVideos'] > 0)
                                    <label class="btn btn-outline-primary btn-sm mb-0"><i class="bi bi-camera-video"></i> Video
                                        <input type="file" accept="video/*" capture="environment" hidden data-video-input></label>
                                @endif
                                <div class="small text-muted mt-1" data-evidence-help data-min="{{ $rules['minPhotos'] }}" data-max="{{ $rules['maxPhotos'] }}"
                                     data-max-videos="{{ $rules['maxVideos'] }}" data-video-required="{{ $rules['videoRequired'] ? 1 : 0 }}">
                                    Photos required: at least {{ $rules['minPhotos'] }}{{ $rules['videoRequired'] ? ', plus a video' : '' }}.</div>
                                <div class="d-flex flex-wrap gap-2 mt-1" data-evidence-list></div>
                            </div>
                        @endif

                        <label class="form-label small {{ $t->requires_reason ? 'required' : '' }}">{{ $t->requires_reason ? 'Reason (mandatory)' : 'Comment (optional)' }}</label>
                        <textarea name="comment" class="form-control mb-2" rows="2" {{ $t->requires_reason ? 'required minlength=5' : '' }}></textarea>

                        <div data-errors></div>
                        <button class="btn btn-{{ $colour }} w-100"><i class="bi bi-{{ $icon }} me-1"></i>Confirm: {{ $t->name }}</button>
                    </form>
                </div>
            @endforeach
        </div>
    </div>
@endif
