{{-- Export the current view (same filters). Official exports exclude test data unless explicitly included. --}}
@can('analytics.export')
    <form method="GET" class="d-flex flex-wrap align-items-center gap-2">
        @foreach (request()->except(['export', 'include_test', 'page']) as $k => $v)
            @if (! is_array($v))<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endif
        @endforeach
        @can('testdata.include')
            <div class="form-check small mb-0" title="Exports are official and exclude test data unless you tick this.">
                <input class="form-check-input" type="checkbox" name="include_test" value="1" id="incTest">
                <label class="form-check-label" for="incTest">Include test data</label>
            </div>
        @endcan
        <div class="btn-group btn-group-sm" role="group" aria-label="Export">
            <button name="export" value="csv" class="btn btn-outline-secondary"><i class="bi bi-filetype-csv" aria-hidden="true"></i> CSV</button>
            <button name="export" value="xlsx" class="btn btn-outline-secondary"><i class="bi bi-file-earmark-excel" aria-hidden="true"></i> Excel</button>
            <button name="export" value="pdf" class="btn btn-outline-secondary"><i class="bi bi-file-earmark-pdf" aria-hidden="true"></i> PDF</button>
        </div>
    </form>
@endcan
