<?php

namespace Tests\Unit\Gis;

use App\Domain\Gis\ChainageScale;
use App\Domain\Gis\LineString;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ChainageScaleTest extends TestCase
{
    private const M_PER_DEG = 111195; // metres per degree of latitude (sphere)

    private function line(): LineString
    {
        return new LineString([[85.0, 23.0], [85.0, 23.0 + 10000 / self::M_PER_DEG]]); // ~10 km north
    }

    private function marker(int $chainage, float $measure): array
    {
        return ['chainage_m' => $chainage, 'latitude' => 23.0 + $measure / self::M_PER_DEG, 'longitude' => 85.0];
    }

    #[Test]
    public function without_markers_chainage_is_start_plus_distance(): void
    {
        $scale = ChainageScale::build($this->line(), 2000);

        $this->assertFalse($scale->isCalibrated());
        $this->assertSame(2000, $scale->chainageAt(0));
        $this->assertSame(5500, $scale->chainageAt(3500));
        $this->assertEqualsWithDelta(3500, $scale->measureAt(5500), 0.01);
    }

    #[Test]
    public function markers_stretch_chainage_between_anchors_and_continue_one_to_one_after(): void
    {
        // The km-5.200 stone sits 5.000 km along the drawn line.
        $scale = ChainageScale::build($this->line(), 0, [$this->marker(5200, 5000)]);

        $this->assertTrue($scale->isCalibrated());
        $this->assertEqualsWithDelta(2600, $scale->chainageAt(2500), 2);
        $this->assertEqualsWithDelta(5200, $scale->chainageAt(5000), 2);
        $this->assertEqualsWithDelta(7200, $scale->chainageAt(7000), 2);
        $this->assertEqualsWithDelta(5000, $scale->measureAt(5200), 2);
        $this->assertEqualsWithDelta(2500, $scale->measureAt(2600), 2);
    }

    #[Test]
    public function inconsistent_markers_are_ignored(): void
    {
        // Second marker claims a lower chainage further along the line.
        $scale = ChainageScale::build($this->line(), 0, [$this->marker(5000, 5000), $this->marker(4000, 8000)]);

        $this->assertEqualsWithDelta(8000, $scale->chainageAt(8000), 2);
    }
}
