<?php

namespace App\Domain\Gis;

/**
 * Maps distance along a drawn road line (measure, m) to official chainage (m) and back.
 *
 * Without calibration markers the mapping is linear: chainage = start + measure.
 * Km-stone markers become anchors; between anchors chainage is interpolated, beyond
 * the last anchor it continues 1:1. This absorbs drawing inaccuracies so that
 * "km 14.250" on the ground matches km 14.250 in the system.
 */
final class ChainageScale
{
    /** @var list<array{0: float, 1: float}> [measure, chainage] sorted by measure */
    private array $anchors;

    /** @param list<array{0: float, 1: float}> $anchors */
    private function __construct(array $anchors)
    {
        $this->anchors = $anchors;
    }

    /**
     * @param  iterable<array{chainage_m: int, latitude: float, longitude: float}>  $markers
     */
    public static function build(LineString $line, int $startChainageM, iterable $markers = []): self
    {
        $points = [];
        foreach ($markers as $marker) {
            $hit = $line->locate((float) $marker['latitude'], (float) $marker['longitude']);
            $points[] = [$hit['measure_m'], (float) $marker['chainage_m']];
        }
        usort($points, fn ($a, $b) => $a[0] <=> $b[0]);

        // Keep only markers that increase in both measure and chainage (ignore inconsistent ones).
        $anchors = [[0.0, (float) $startChainageM]];
        foreach ($points as [$m, $c]) {
            [$lastM, $lastC] = end($anchors);
            if ($m > $lastM + 1 && $c > $lastC) {
                $anchors[] = [$m, $c];
            }
        }

        return new self($anchors);
    }

    public function chainageAt(float $measureM): int
    {
        return (int) round($this->interpolate($measureM, 0, 1));
    }

    public function measureAt(int $chainageM): float
    {
        return max(0.0, $this->interpolate((float) $chainageM, 1, 0));
    }

    public function isCalibrated(): bool
    {
        return count($this->anchors) > 1;
    }

    private function interpolate(float $value, int $from, int $to): float
    {
        $a = $this->anchors;
        $n = count($a);

        if ($n === 1 || $value <= $a[0][$from]) {
            return $a[0][$to] + ($value - $a[0][$from]);
        }

        for ($i = 1; $i < $n; $i++) {
            if ($value <= $a[$i][$from]) {
                $t = ($value - $a[$i - 1][$from]) / ($a[$i][$from] - $a[$i - 1][$from]);

                return $a[$i - 1][$to] + $t * ($a[$i][$to] - $a[$i - 1][$to]);
            }
        }

        return $a[$n - 1][$to] + ($value - $a[$n - 1][$from]);
    }
}
