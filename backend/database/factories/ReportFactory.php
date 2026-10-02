<?php

namespace Database\Factories;

use App\Models\AssetType;
use App\Models\IssueCategory;
use App\Models\Report;
use App\Models\Severity;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Report>
 * Relies on seeded reference data (asset types, categories, severities).
 */
class ReportFactory extends Factory
{
    protected $model = Report::class;

    public function definition(): array
    {
        $type = AssetType::where('code', AssetType::ROAD)->firstOrFail();

        return [
            'report_no' => 'TEST-'.Str::upper(Str::random(10)),
            'client_uuid' => (string) Str::uuid(),
            'source' => 'mobile',
            'reporter_id' => User::factory(),
            'reporter_role_code' => 'CITIZEN',
            'asset_type_id' => $type->id,
            'latitude' => 23.36,
            'longitude' => 85.33,
            'gps_accuracy_m' => 8,
            'received_at' => now(),
            'issue_category_id' => IssueCategory::where('asset_type_id', $type->id)->value('id'),
            'severity_id' => Severity::where('code', 'MEDIUM')->value('id'),
            'status' => 'PENDING_VALIDATION',
            'is_test' => true,
        ];
    }
}
