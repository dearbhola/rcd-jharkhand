<?php

namespace App\Models;

use App\Models\Concerns\AppendOnly;
use App\Models\Concerns\HasTestFlag;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Photo/video evidence. The original file is never modified; only derived
 * copies and processing metadata are filled in after upload.
 */
class Evidence extends Model
{
    use AppendOnly, HasTestFlag;

    public const KIND_PHOTO = 'photo';

    public const KIND_VIDEO = 'video';

    protected $table = 'evidences';

    protected $guarded = ['id'];

    protected array $mutableAttributes = [
        'display_path', 'thumbnail_path', 'processing_status', 'width', 'height', 'duration_s', 'exif', 'flags', 'workflow_action_id',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'gps_accuracy_m' => 'float',
            'captured_at' => 'datetime',
            'exif' => 'array',
            'flags' => 'array',
            'file_size' => 'integer',
        ];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    public function evidenceable(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}
