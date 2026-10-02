<?php

namespace App\Http\Requests\Reports;

use App\Domain\Reporting\ReportSubmission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

class StoreReportRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'client_uuid' => ['required', 'uuid'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['required', 'numeric', 'min:0', 'max:100000'],
            'captured_at' => ['required', 'date'],
            'gps_fix_at' => ['nullable', 'date'],
            'asset_type_id' => ['required', 'integer'],
            'issue_category_id' => ['required', 'integer'],
            'severity_id' => ['required', 'integer'],
            'description' => ['nullable', 'string', 'max:1000'],
            'location_override' => ['nullable', 'boolean'],
            // Detailed type/size/count checks are done by EvidenceService against configurable limits.
            'evidence' => ['required', 'array', 'max:20'],
            'evidence.*' => ['file'],
        ];
    }

    public function messages(): array
    {
        return ['evidence.required' => 'Add at least one photo.', 'accuracy.required' => 'GPS location is required.'];
    }

    public function submission(): ReportSubmission
    {
        return new ReportSubmission(
            clientUuid: strtolower($this->validated('client_uuid')),
            latitude: (float) $this->validated('latitude'),
            longitude: (float) $this->validated('longitude'),
            accuracyM: (float) $this->validated('accuracy'),
            capturedAt: Carbon::parse($this->validated('captured_at'))->setTimezone(config('app.timezone')),
            gpsFixAt: $this->validated('gps_fix_at') ? Carbon::parse($this->validated('gps_fix_at'))->setTimezone(config('app.timezone')) : null,
            assetTypeId: (int) $this->validated('asset_type_id'),
            issueCategoryId: (int) $this->validated('issue_category_id'),
            severityId: (int) $this->validated('severity_id'),
            description: $this->validated('description'),
            locationOverride: $this->boolean('location_override'),
            source: 'web',
        );
    }
}
