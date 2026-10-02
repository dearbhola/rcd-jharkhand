<x-app-layout title="Audit entry #{{ $log->id }}" :breadcrumbs="['Audit log' => route('admin.audit.index'), '#'.$log->id]">
    <x-page-header title="Audit entry #{{ $log->id }}" :subtitle="$log->action" />
    <div class="row g-3">
        <div class="col-lg-5">
            <div class="card">
                <div class="card-body">
                    <dl class="row mb-0 small">
                        <dt class="col-4">When</dt><dd class="col-8">{{ $log->created_at->format('d-M-Y H:i:s T') }}</dd>
                        <dt class="col-4">User</dt><dd class="col-8">{{ $log->user_name }} {{ $log->user_id ? '#'.$log->user_id : '' }}</dd>
                        <dt class="col-4">Role</dt><dd class="col-8">{{ $log->role_code ?? '—' }}</dd>
                        <dt class="col-4">Action</dt><dd class="col-8"><code>{{ $log->action }}</code></dd>
                        <dt class="col-4">Entity</dt><dd class="col-8">{{ $log->auditable_type ? $log->auditable_type.' #'.$log->auditable_id : '—' }}</dd>
                        <dt class="col-4">Comment</dt><dd class="col-8">{{ $log->comment ?? '—' }}</dd>
                        <dt class="col-4">IP</dt><dd class="col-8">{{ $log->ip_address ?? '—' }}</dd>
                        <dt class="col-4">Device</dt><dd class="col-8 text-break">{{ $log->user_agent ?? '—' }}</dd>
                        <dt class="col-4">Request</dt><dd class="col-8 text-break"><code>{{ $log->request_id }}</code></dd>
                        <dt class="col-4">Data</dt><dd class="col-8">{{ $log->is_test ? 'TEST' : 'Production' }}</dd>
                    </dl>
                </div>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header">Changes</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0 small">
                        <thead><tr><th>Field</th><th>Previous</th><th>New</th></tr></thead>
                        <tbody>
                        @php($fields = array_unique(array_merge(array_keys($log->old_values ?? []), array_keys($log->new_values ?? []))))
                        @forelse ($fields as $field)
                            <tr>
                                <td><code>{{ $field }}</code></td>
                                <td class="text-danger text-break">{{ json_encode($log->old_values[$field] ?? null, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</td>
                                <td class="text-success text-break">{{ json_encode($log->new_values[$field] ?? null, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-muted">No field changes recorded.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
