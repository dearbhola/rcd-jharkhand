<?php

namespace App\Http\Controllers\Masters;

use App\Http\Controllers\Controller;
use App\Models\AssetType;
use App\Models\IssueCategory;
use App\Models\RoadCategory;
use App\Models\Severity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Configurable lookups: asset types + issue category tree, severities, road categories.
 * Entries are deactivated, never deleted (reports reference them).
 */
class CategoryController extends Controller
{
    public function index(Request $request): View
    {
        return view('masters.categories.index', [
            'assetTypes' => AssetType::with(['issueCategories' => fn ($q) => $q->whereNull('parent_id')->with('children')])->orderBy('sort_order')->get(),
            'severities' => Severity::orderBy('rank')->get(),
            'roadCategories' => RoadCategory::orderBy('sort_order')->get(),
            'tab' => $request->query('tab', 'assets'),
        ]);
    }

    public function storeAssetType(Request $request): RedirectResponse
    {
        AssetType::create($this->validateAssetType($request));

        return $this->back('assets', 'Asset type added.');
    }

    public function updateAssetType(Request $request, AssetType $assetType): RedirectResponse
    {
        $assetType->update($this->validateAssetType($request, $assetType));

        return $this->back('assets', 'Asset type updated.');
    }

    public function storeIssueCategory(Request $request, AssetType $assetType): RedirectResponse
    {
        $data = $this->validateIssueCategory($request, $assetType);
        $assetType->issueCategories()->create($data);

        return $this->back('assets', 'Category added.');
    }

    public function updateIssueCategory(Request $request, IssueCategory $issueCategory): RedirectResponse
    {
        $issueCategory->update($this->validateIssueCategory($request, $issueCategory->assetType, $issueCategory));

        return $this->back('assets', 'Category updated.');
    }

    public function storeSeverity(Request $request): RedirectResponse
    {
        Severity::create($this->validateSeverity($request));

        return $this->back('severities', 'Severity added.');
    }

    public function updateSeverity(Request $request, Severity $severity): RedirectResponse
    {
        $severity->update($this->validateSeverity($request, $severity));

        return $this->back('severities', 'Severity updated.');
    }

    public function storeRoadCategory(Request $request): RedirectResponse
    {
        RoadCategory::create($this->validateRoadCategory($request));

        return $this->back('roads', 'Road category added.');
    }

    public function updateRoadCategory(Request $request, RoadCategory $roadCategory): RedirectResponse
    {
        $roadCategory->update($this->validateRoadCategory($request, $roadCategory));

        return $this->back('roads', 'Road category updated.');
    }

    private function back(string $tab, string $message): RedirectResponse
    {
        return redirect()->route('categories.index', ['tab' => $tab])->with('success', $message);
    }

    private function codeFrom(Request $request): void
    {
        $request->merge(['code' => Str::upper(Str::slug((string) ($request->input('code') ?: $request->input('name')), '_'))]);
    }

    private function validateAssetType(Request $request, ?AssetType $type = null): array
    {
        $this->codeFrom($request);

        return $request->validate([
            'code' => ['required', 'max:30', Rule::unique('asset_types', 'code')->ignore($type)],
            'name' => ['required', 'string', 'max:100'],
            'geometry_kind' => ['required', Rule::in(['point', 'line'])],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
            'is_active' => ['required', 'boolean'],
        ]);
    }

    private function validateIssueCategory(Request $request, AssetType $type, ?IssueCategory $category = null): array
    {
        $this->codeFrom($request);

        return $request->validate([
            'code' => ['required', 'max:40', Rule::unique('issue_categories', 'code')->where('asset_type_id', $type->id)->ignore($category)],
            'name' => ['required', 'string', 'max:100'],
            'parent_id' => ['nullable', Rule::exists('issue_categories', 'id')->where('asset_type_id', $type->id)->whereNull('parent_id'),
                Rule::notIn(array_filter([$category?->id]))],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
            'is_active' => ['required', 'boolean'],
        ]);
    }

    private function validateSeverity(Request $request, ?Severity $severity = null): array
    {
        $this->codeFrom($request);

        return $request->validate([
            'code' => ['required', 'max:20', Rule::unique('severities', 'code')->ignore($severity)],
            'name' => ['required', 'string', 'max:50'],
            'rank' => ['required', 'integer', 'min:1', 'max:20', Rule::unique('severities', 'rank')->ignore($severity)],
            'color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'is_active' => ['required', 'boolean'],
        ]);
    }

    private function validateRoadCategory(Request $request, ?RoadCategory $category = null): array
    {
        $this->codeFrom($request);

        return $request->validate([
            'code' => ['required', 'max:20', Rule::unique('road_categories', 'code')->ignore($category)],
            'name' => ['required', 'string', 'max:100'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
            'is_active' => ['required', 'boolean'],
        ]);
    }
}
