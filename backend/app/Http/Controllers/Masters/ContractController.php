<?php

namespace App\Http\Controllers\Masters;

use App\Domain\Audit\AuditLogger;
use App\Domain\Masters\ContractMappingService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Masters\ContractMappingRequest;
use App\Http\Requests\Masters\ContractRequest;
use App\Models\Contract;
use App\Models\ContractDocument;
use App\Models\Contractor;
use App\Models\ContractRoadSection;
use App\Models\Division;
use App\Models\Road;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ContractController extends Controller
{
    private const DOCUMENT_DISK = 'local';

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'contractor_id' => ['nullable', 'integer'],
            'division_id' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::in(['draft', 'active', 'completed', 'terminated'])],
            'maintenance' => ['nullable', Rule::in(['active', 'expired', 'upcoming'])],
        ]);
        $today = now()->toDateString();

        $contracts = Contract::query()
            ->with(['contractor:id,name', 'division:id,code'])
            ->withCount('roadSections')
            ->when($filters['q'] ?? null, fn ($q, $t) => $q->where(fn ($w) => $w->where('contract_no', 'like', "%{$t}%")
                ->orWhere('name', 'like', "%{$t}%")->orWhere('agreement_no', 'like', "%{$t}%")))
            ->when($filters['contractor_id'] ?? null, fn ($q, $v) => $q->where('contractor_id', $v))
            ->when($filters['division_id'] ?? null, fn ($q, $v) => $q->where('division_id', $v))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when(($filters['maintenance'] ?? null) === 'active', fn ($q) => $q->maintenanceActiveOn(now()))
            ->when(($filters['maintenance'] ?? null) === 'expired', fn ($q) => $q->whereDate('maintenance_end_date', '<', $today))
            ->when(($filters['maintenance'] ?? null) === 'upcoming', fn ($q) => $q->whereDate('maintenance_start_date', '>', $today))
            ->orderByDesc('maintenance_end_date')
            ->paginate(25)
            ->withQueryString();

        return view('masters.contracts.index', ['contracts' => $contracts, 'filters' => $filters, ...$this->lookups()]);
    }

    public function create(Request $request): View
    {
        return view('masters.contracts.form', [
            'contract' => new Contract(['status' => 'active', 'contractor_id' => $request->integer('contractor_id') ?: null]),
            ...$this->lookups(),
        ]);
    }

    public function store(ContractRequest $request): RedirectResponse
    {
        $contract = Contract::create($request->payload());

        return redirect()->route('contracts.show', $contract)->with('success', 'Contract created. Map it to road sections next.');
    }

    public function show(Contract $contract): View
    {
        $contract->load(['contractor', 'division', 'documents', 'creator:id,name']);

        return view('masters.contracts.show', [
            'contract' => $contract,
            'mappings' => ContractRoadSection::with(['road:id,code,name', 'section:id,code'])->where('contract_id', $contract->id)->orderBy('road_id')->orderBy('start_chainage_m')->get(),
            'roads' => Road::orderBy('code')->get(['id', 'code', 'name'])->mapWithKeys(fn ($r) => [$r->id => "{$r->code} — {$r->name}"]),
        ]);
    }

    public function edit(Contract $contract): View
    {
        return view('masters.contracts.form', ['contract' => $contract, ...$this->lookups()]);
    }

    public function update(ContractRequest $request, Contract $contract): RedirectResponse
    {
        $contract->update($request->payload());

        return redirect()->route('contracts.show', $contract)->with('success', 'Contract updated.');
    }

    public function storeMapping(ContractMappingRequest $request, Contract $contract, ContractMappingService $mappings): RedirectResponse
    {
        $mappings->add($contract, $request->payload());

        return redirect()->route('contracts.show', $contract)->with('success', 'Road coverage added.');
    }

    public function endMapping(Request $request, ContractRoadSection $mapping, ContractMappingService $mappings): RedirectResponse
    {
        $data = $request->validate([
            'effective_to' => ['required', 'date'],
            'reason' => ['required', 'string', 'min:5', 'max:255'],
        ]);
        $mappings->end($mapping, $data['effective_to'], $data['reason']);

        return redirect()->route('contracts.show', $mapping->contract_id)->with('success', 'Coverage ended. History is preserved.');
    }

    public function storeDocument(Request $request, Contract $contract, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'file' => ['required', 'file', 'max:20480', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx'],
        ]);

        $file = $data['file'];
        $path = $file->storeAs("contracts/{$contract->id}", Str::uuid().'.'.$file->extension(), self::DOCUMENT_DISK);

        $document = $contract->documents()->create([
            'title' => $data['title'],
            'disk' => self::DOCUMENT_DISK,
            'path' => $path,
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'sha256' => hash_file('sha256', $file->getRealPath()),
            'uploaded_by' => $request->user()->id,
        ]);
        $audit->log('contract.document_uploaded', $contract, null, ['document_id' => $document->id, 'title' => $document->title]);

        return back()->with('success', 'Document uploaded.');
    }

    public function downloadDocument(ContractDocument $document): StreamedResponse
    {
        abort_unless(Contract::whereKey($document->contract_id)->exists(), 404); // respects test-data visibility

        return Storage::disk($document->disk)->download($document->path, Str::slug($document->title).'.'.pathinfo($document->path, PATHINFO_EXTENSION));
    }

    /** @return array<string, mixed> */
    private function lookups(): array
    {
        return [
            'contractors' => Contractor::orderBy('name')->pluck('name', 'id'),
            'divisions' => Division::orderBy('code')->pluck('name', 'id'),
        ];
    }
}
