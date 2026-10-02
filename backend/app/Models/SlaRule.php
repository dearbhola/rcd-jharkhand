<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\TracksAuthor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SlaRule extends Model
{
    use Auditable, TracksAuthor;

    public const STAGE_VALIDATION = 'VALIDATION';

    public const STAGE_RESPONSE = 'RESPONSE';

    public const STAGE_REPAIR = 'REPAIR';

    public const STAGE_JE_REVIEW = 'JE_REVIEW';

    public const STAGE_AE_REVIEW = 'AE_REVIEW';

    public const STAGE_EE_APPROVAL = 'EE_APPROVAL';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['hours' => 'float', 'is_active' => 'boolean'];
    }

    public function assetType(): BelongsTo
    {
        return $this->belongsTo(AssetType::class);
    }

    public function issueCategory(): BelongsTo
    {
        return $this->belongsTo(IssueCategory::class);
    }

    public function severity(): BelongsTo
    {
        return $this->belongsTo(Severity::class);
    }

    /** Higher = more specific; the most specific matching rule wins. */
    public function specificity(): int
    {
        return ($this->issue_category_id ? 4 : 0) + ($this->asset_type_id ? 2 : 0) + ($this->severity_id ? 1 : 0);
    }
}
