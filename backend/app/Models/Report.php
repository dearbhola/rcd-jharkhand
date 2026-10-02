<?php

namespace App\Models;

use App\Domain\Reporting\ReportStatus;
use App\Enums\RoleCode;
use App\Models\Concerns\HasTestFlag;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A damage/issue report. Location, road and responsibility fields are resolved
 * by the server; `status` mirrors the active workflow step for fast filtering.
 * Business events are audited by the services that perform them.
 */
class Report extends Model
{
    use HasFactory, HasTestFlag;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'gps_accuracy_m' => 'float',
            'distance_from_road_m' => 'float',
            'chainage_m' => 'integer',
            'is_mock_location' => 'boolean',
            'location_override' => 'boolean',
            'location_flags' => 'array',
            'captured_at_device' => 'datetime',
            'gps_fix_at' => 'datetime',
            'received_at' => 'datetime',
            'finalized_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    /**
     * Reports a user may see: administrators all; reporters their own; JE/AE/EE those they are
     * currently responsible for; task holders (incl. delegates) the reports they work on;
     * contractors their firm's reports once validated.
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->hasRole(RoleCode::SUPER_ADMIN, RoleCode::ADMIN)) {
            return;
        }

        $query->where(function (Builder $q) use ($user) {
            $q->where('reports.reporter_id', $user->id);

            if ($user->can('report.view_all')) {
                $q->orWhereHas('currentResponsibility', fn ($r) => $r
                    ->where('je_user_id', $user->id)->orWhere('ae_user_id', $user->id)->orWhere('ee_user_id', $user->id));
            }

            // Anyone who holds or held a task on the report (assignees, delegates).
            $q->orWhereHas('workflowInstances.assignments', fn ($a) => $a->where('user_id', $user->id));

            if ($user->hasRole(RoleCode::CONTRACTOR) && $user->contractor_id) {
                $q->orWhere(fn ($c) => $c
                    ->whereNotIn('reports.status', [ReportStatus::PENDING_VALIDATION, ReportStatus::INVALID_CLOSED, ReportStatus::MERGED_DUPLICATE])
                    ->whereHas('currentResponsibility', fn ($r) => $r->where('contractor_id', $user->contractor_id)));
            }
        });
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function road(): BelongsTo
    {
        return $this->belongsTo(Road::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(RoadSection::class, 'road_section_id');
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function assetType(): BelongsTo
    {
        return $this->belongsTo(AssetType::class);
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(IssueCategory::class, 'issue_category_id');
    }

    public function severity(): BelongsTo
    {
        return $this->belongsTo(Severity::class);
    }

    public function responsibilities(): HasMany
    {
        return $this->hasMany(ReportResponsibility::class)->orderByDesc('id');
    }

    public function currentResponsibility(): HasOne
    {
        return $this->hasOne(ReportResponsibility::class)->where('is_current', true);
    }

    public function evidences(): HasMany
    {
        return $this->hasMany(Evidence::class);
    }

    public function workflowInstances(): HasMany
    {
        return $this->hasMany(WorkflowInstance::class);
    }

    public function activeWorkflow(): HasOne
    {
        return $this->hasOne(WorkflowInstance::class)->where('status', WorkflowInstance::STATUS_ACTIVE);
    }

    public function repairAttempts(): HasMany
    {
        return $this->hasMany(RepairAttempt::class)->orderBy('attempt_no');
    }

    public function inspections(): HasMany
    {
        return $this->hasMany(Inspection::class)->orderBy('inspected_at');
    }

    public function links(): HasMany
    {
        return $this->hasMany(ReportLink::class);
    }

    public function slaInstances(): HasMany
    {
        return $this->hasMany(SlaInstance::class);
    }
}
