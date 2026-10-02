<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Support\TestDataMode;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(Request $request, TestDataMode $testData): View
    {
        $filters = $request->validate([
            'action' => ['nullable', 'string', 'max:60'],
            'user' => ['nullable', 'string', 'max:100'],
            'entity' => ['nullable', 'string', 'max:60'],
            'entity_id' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $logs = AuditLog::query()
            ->when(! $testData->includesTestData(), fn ($q) => $q->where('is_test', false))
            ->when($filters['action'] ?? null, fn ($q, $v) => $q->where('action', 'like', "{$v}%"))
            ->when($filters['user'] ?? null, fn ($q, $v) => $q->where('user_name', 'like', "%{$v}%"))
            ->when($filters['entity'] ?? null, fn ($q, $v) => $q->where('auditable_type', $v))
            ->when($filters['entity_id'] ?? null, fn ($q, $v) => $q->where('auditable_id', $v))
            ->when($filters['from'] ?? null, fn ($q, $v) => $q->where('created_at', '>=', $v))
            ->when($filters['to'] ?? null, fn ($q, $v) => $q->where('created_at', '<', Carbon::parse($v)->addDay()))
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        $entities = AuditLog::query()->whereNotNull('auditable_type')->distinct()->orderBy('auditable_type')->pluck('auditable_type');

        return view('admin.audit.index', compact('logs', 'filters', 'entities'));
    }

    public function show(AuditLog $auditLog): View
    {
        return view('admin.audit.show', ['log' => $auditLog->load('user')]);
    }
}
