@if ($reassignCandidates->isNotEmpty())
    <div class="card mb-3">
        <div class="card-header"><i class="bi bi-arrow-left-right me-1"></i>Reassign task</div>
        <form method="POST" action="{{ route('reports.reassign', $report) }}" class="card-body row g-2" data-confirm="Reassign this task?">
            @csrf
            <div class="col-12">
                <select name="assignment_id" class="form-select form-select-sm" aria-label="Current holder" required>
                    @foreach ($holders as $h)<option value="{{ $h->id }}">From: {{ $h->user->name }}</option>@endforeach
                </select>
            </div>
            <div class="col-12">
                <select name="user_id" class="form-select form-select-sm" aria-label="New holder" required>
                    <option value="">To…</option>
                    @foreach ($reassignCandidates as $c)
                        @continue($holders->pluck('user_id')->contains($c->id))
                        <option value="{{ $c->id }}">{{ $c->name }}{{ $c->employee_code ? ' ('.$c->employee_code.')' : '' }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12"><input name="reason" class="form-control form-control-sm" placeholder="Reason (mandatory)" required minlength="5"></div>
            <div class="col-12"><button class="btn btn-sm btn-outline-primary w-100">Reassign</button></div>
            <div class="col-12 form-text mt-0">The road's permanent JE/AE/EE mapping is not changed. The move is audited.</div>
        </form>
    </div>
@endif
