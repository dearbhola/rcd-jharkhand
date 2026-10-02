<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Spatial index row owned by the GIS engine. Do not write directly;
 * go through App\Domain\Gis\GisEngine.
 */
class GisFeature extends Model
{
    public const TYPE_ROAD = 'road';

    public const TYPE_SECTION = 'road_section';

    public const TYPE_ASSET = 'asset';

    protected $guarded = ['id'];

    protected $hidden = ['geom'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
