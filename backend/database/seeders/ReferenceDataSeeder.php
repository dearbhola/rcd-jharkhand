<?php

namespace Database\Seeders;

use App\Models\AssetType;
use App\Models\IssueCategory;
use App\Models\RoadCategory;
use App\Models\Severity;
use Illuminate\Database\Seeder;

/**
 * Initial configurable lookups. Admins can add/edit/deactivate all of these.
 */
class ReferenceDataSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([['NH', 'National Highway'], ['SH', 'State Highway'], ['MDR', 'Major District Road'], ['ODR', 'Other District Road'], ['VR', 'Village Road']] as $i => [$code, $name]) {
            RoadCategory::firstOrCreate(['code' => $code], ['name' => $name, 'sort_order' => $i]);
        }

        foreach ([['LOW', 'Low', 1, '#198754'], ['MEDIUM', 'Medium', 2, '#ffc107'], ['HIGH', 'High', 3, '#fd7e14'], ['CRITICAL', 'Critical', 4, '#dc3545']] as [$code, $name, $rank, $color]) {
            Severity::firstOrCreate(['code' => $code], ['name' => $name, 'rank' => $rank, 'color' => $color]);
        }

        $types = [
            'ROAD' => ['Road', 'line', 'sign-turn-right', [
                'POTHOLE' => 'Pothole', 'CRACK' => 'Crack', 'RUTTING' => 'Rutting', 'SURFACE_DAMAGE' => 'Surface Damage',
                'SHOULDER_DAMAGE' => 'Shoulder Damage', 'DRAINAGE' => 'Drainage Problem', 'ENCROACHMENT' => 'Encroachment', 'OTHER' => 'Other',
            ]],
            'BRIDGE' => ['Bridge', 'point', 'bricks', [
                'EXPANSION_JOINT' => 'Expansion Joint', 'DECK_DAMAGE' => 'Deck Damage', 'RAILING' => 'Railing',
                'BEARING' => 'Bearing', 'APPROACH_ROAD' => 'Approach Road', 'OTHER' => 'Other',
            ]],
            'CULVERT' => ['Culvert', 'point', 'circle', ['BLOCKAGE' => 'Blockage', 'STRUCTURAL' => 'Structural Damage', 'HEADWALL' => 'Headwall Damage', 'OTHER' => 'Other']],
            'GUARD_WALL' => ['Guard Wall', 'line', 'bounding-box', ['COLLAPSE' => 'Collapse', 'CRACK' => 'Crack', 'TILT' => 'Tilt', 'DRAINAGE' => 'Drainage', 'OTHER' => 'Other']],
            'RETAINING_WALL' => ['Retaining Wall', 'line', 'layers', ['COLLAPSE' => 'Collapse', 'CRACK' => 'Crack', 'BULGING' => 'Bulging', 'DRAINAGE' => 'Weep-hole / Drainage', 'OTHER' => 'Other']],
            'DRAIN' => ['Drain', 'line', 'water', ['BLOCKAGE' => 'Blockage', 'BROKEN' => 'Broken / Damaged', 'OVERFLOW' => 'Overflow', 'OTHER' => 'Other']],
            'CAUSEWAY' => ['Causeway', 'point', 'tsunami', ['WASHOUT' => 'Washout', 'SURFACE_DAMAGE' => 'Surface Damage', 'OTHER' => 'Other']],
            'ROAD_FURNITURE' => ['Road Furniture', 'point', 'sign-stop', ['SIGNBOARD' => 'Signboard', 'KM_STONE' => 'KM Stone', 'CRASH_BARRIER' => 'Crash Barrier', 'MARKING' => 'Road Marking', 'OTHER' => 'Other']],
            'OTHER' => ['Other', 'point', 'question-circle', ['OTHER' => 'Other']],
        ];

        $order = 0;
        foreach ($types as $code => [$name, $kind, $icon, $categories]) {
            $type = AssetType::firstOrCreate(['code' => $code], ['name' => $name, 'geometry_kind' => $kind, 'icon' => $icon, 'sort_order' => $order++]);

            $i = 0;
            foreach ($categories as $catCode => $catName) {
                IssueCategory::firstOrCreate(
                    ['asset_type_id' => $type->id, 'code' => $catCode],
                    ['name' => $catName, 'sort_order' => $i++],
                );
            }
        }
    }
}
