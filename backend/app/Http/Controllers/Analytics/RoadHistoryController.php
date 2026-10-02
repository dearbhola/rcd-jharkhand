<?php

namespace App\Http\Controllers\Analytics;

use App\Domain\Performance\RoadHistoryService;
use App\Http\Controllers\Controller;
use App\Models\AssetType;
use App\Models\Contractor;
use App\Models\IssueCategory;
use App\Models\Road;
use App\Models\Severity;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RoadHistoryController extends Controller
{
    public function show(Request $request, Road $road, RoadHistoryService $history): View
    {
        $filters = $request->validate([
            'year' => ['nullable', 'integer', 'between:2000,2100'],
            'issue_category_id' => ['nullable', 'integer'],
            'severity_id' => ['nullable', 'integer'],
            'contractor_id' => ['nullable', 'integer'],
            'road_section_id' => ['nullable', 'integer'],
        ]);

        return view('performance.road-history', $history->build($road, $filters) + [
            'filters' => $filters,
            'sections' => $road->sections()->pluck('code', 'id'),
            'categories' => IssueCategory::where('asset_type_id', AssetType::where('code', AssetType::ROAD)->value('id'))->orderBy('sort_order')->pluck('name', 'id'),
            'severities' => Severity::orderBy('rank')->pluck('name', 'id'),
            'contractors' => Contractor::orderBy('name')->pluck('name', 'id'),
        ]);
    }
}
