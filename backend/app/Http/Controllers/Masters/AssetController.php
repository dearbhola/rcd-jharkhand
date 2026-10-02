<?php

namespace App\Http\Controllers\Masters;

use App\Domain\Masters\AssetService;
use App\Domain\Masters\ResponsibilityService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Masters\AssetRequest;
use App\Models\Asset;
use App\Models\AssetType;
use App\Models\Road;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AssetController extends Controller
{
    public function __construct(private readonly AssetService $assets) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'asset_type_id' => ['nullable', 'integer'],
            'road_id' => ['nullable', 'integer'],
            'status' => ['nullable', 'string', 'max:20'],
        ]);

        $assets = Asset::query()
            ->with(['type:id,name', 'road:id,code', 'section:id,code'])
            ->when($filters['q'] ?? null, fn ($q, $t) => $q->where(fn ($w) => $w->where('code', 'like', "%{$t}%")->orWhere('name', 'like', "%{$t}%")))
            ->when($filters['asset_type_id'] ?? null, fn ($q, $v) => $q->where('asset_type_id', $v))
            ->when($filters['road_id'] ?? null, fn ($q, $v) => $q->where('road_id', $v))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->orderBy('code')
            ->paginate(25)
            ->withQueryString();

        return view('masters.assets.index', ['assets' => $assets, 'filters' => $filters, ...$this->lookups()]);
    }

    public function create(Request $request): View
    {
        return view('masters.assets.form', [
            'asset' => new Asset(['status' => 'active', 'road_id' => $request->integer('road_id') ?: null]),
            ...$this->lookups(),
        ]);
    }

    public function store(AssetRequest $request): RedirectResponse
    {
        $asset = $this->assets->save(new Asset, $request->payload());

        return redirect()->route('assets.show', $asset)->with('success', 'Asset created.');
    }

    public function show(Asset $asset, ResponsibilityService $responsibility): View
    {
        $asset->load(['type', 'road', 'section', 'creator:id,name']);

        return view('masters.assets.show', [
            'asset' => $asset,
            'people' => $responsibility->forAsset($asset),
            'hasOverride' => $asset->responsibilityAssignments()->exists(),
        ]);
    }

    public function edit(Asset $asset): View
    {
        return view('masters.assets.form', ['asset' => $asset, ...$this->lookups()]);
    }

    public function update(AssetRequest $request, Asset $asset): RedirectResponse
    {
        $this->assets->save($asset, $request->payload());

        return redirect()->route('assets.show', $asset)->with('success', 'Asset updated.');
    }

    /** @return array<string, mixed> */
    private function lookups(): array
    {
        return [
            'types' => AssetType::where('is_active', true)->where('code', '!=', AssetType::ROAD)->orderBy('sort_order')->pluck('name', 'id'),
            'roads' => Road::orderBy('code')->get(['id', 'code', 'name'])->mapWithKeys(fn ($r) => [$r->id => "{$r->code} — {$r->name}"]),
        ];
    }
}
