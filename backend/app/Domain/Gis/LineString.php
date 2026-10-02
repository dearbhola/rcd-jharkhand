<?php

namespace App\Domain\Gis;

use InvalidArgumentException;

/**
 * Immutable WGS84 polyline with linear-referencing operations.
 * Coordinates are stored as [lng, lat] (GeoJSON order).
 */
final class LineString
{
    /** @var list<array{0: float, 1: float}> */
    private array $coords;

    /** @var list<float> cumulative distance (m) at each vertex */
    private array $measures;

    /**
     * @param  list<array{0: float|int, 1: float|int}>  $coords
     */
    public function __construct(array $coords)
    {
        $clean = [];
        foreach ($coords as $c) {
            if (! is_array($c) || count($c) < 2 || ! is_numeric($c[0]) || ! is_numeric($c[1])) {
                throw new InvalidArgumentException('Invalid coordinate in line string.');
            }
            [$lng, $lat] = [(float) $c[0], (float) $c[1]];
            if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
                throw new InvalidArgumentException('Coordinate out of WGS84 range.');
            }
            $last = end($clean);
            if ($last === false || $last[0] !== $lng || $last[1] !== $lat) {
                $clean[] = [$lng, $lat];
            }
        }

        if (count($clean) < 2) {
            throw new InvalidArgumentException('A line string needs at least two distinct points.');
        }

        $this->coords = $clean;
        $this->measures = [0.0];
        for ($i = 1, $n = count($clean); $i < $n; $i++) {
            $this->measures[] = $this->measures[$i - 1]
                + GeoMath::distance($clean[$i - 1][1], $clean[$i - 1][0], $clean[$i][1], $clean[$i][0]);
        }
    }

    /**
     * Accepts a GeoJSON LineString, MultiLineString (parts joined in order),
     * Feature or single-feature FeatureCollection.
     */
    public static function fromGeoJson(array|string $geojson): self
    {
        $g = is_string($geojson) ? json_decode($geojson, true, 512, JSON_THROW_ON_ERROR) : $geojson;

        if (($g['type'] ?? null) === 'FeatureCollection') {
            if (count($g['features'] ?? []) !== 1) {
                throw new InvalidArgumentException('FeatureCollection must contain exactly one feature.');
            }
            $g = $g['features'][0];
        }
        if (($g['type'] ?? null) === 'Feature') {
            $g = $g['geometry'] ?? [];
        }

        return match ($g['type'] ?? null) {
            'LineString' => new self($g['coordinates'] ?? []),
            'MultiLineString' => new self(array_merge(...array_values($g['coordinates'] ?? [[]]))),
            default => throw new InvalidArgumentException('Geometry must be a LineString or MultiLineString.'),
        };
    }

    /** @return list<array{0: float, 1: float}> */
    public function coordinates(): array
    {
        return $this->coords;
    }

    public function length(): float
    {
        return end($this->measures);
    }

    /**
     * Nearest point on the line to (lat, lng).
     *
     * @return array{distance_m: float, measure_m: float, lat: float, lng: float}
     */
    public function locate(float $lat, float $lng): array
    {
        $best = null;

        for ($i = 0, $n = count($this->coords) - 1; $i < $n; $i++) {
            [$aLng, $aLat] = $this->coords[$i];
            [$bLng, $bLat] = $this->coords[$i + 1];
            $p = GeoMath::projectOnSegment($lat, $lng, $aLat, $aLng, $bLat, $bLng);

            if ($best === null || $p['distance'] < $best['distance_m']) {
                $segmentLength = $this->measures[$i + 1] - $this->measures[$i];
                $best = [
                    'distance_m' => $p['distance'],
                    'measure_m' => $this->measures[$i] + $p['t'] * $segmentLength,
                    'lat' => $p['lat'],
                    'lng' => $p['lng'],
                ];
            }
        }

        return $best;
    }

    /** @return array{0: float, 1: float} [lng, lat] at a distance along the line (clamped) */
    public function pointAt(float $measureM): array
    {
        $measureM = max(0.0, min($this->length(), $measureM));

        for ($i = 1, $n = count($this->coords); $i < $n; $i++) {
            if ($this->measures[$i] >= $measureM) {
                $segment = $this->measures[$i] - $this->measures[$i - 1];
                $t = $segment > 0 ? ($measureM - $this->measures[$i - 1]) / $segment : 0;

                return [
                    $this->coords[$i - 1][0] + $t * ($this->coords[$i][0] - $this->coords[$i - 1][0]),
                    $this->coords[$i - 1][1] + $t * ($this->coords[$i][1] - $this->coords[$i - 1][1]),
                ];
            }
        }

        return end($this->coords);
    }

    /** Sub-line between two distances along the line. */
    public function slice(float $fromM, float $toM): self
    {
        $fromM = max(0.0, min($this->length(), $fromM));
        $toM = max(0.0, min($this->length(), $toM));
        if ($toM - $fromM < 0.01) {
            throw new InvalidArgumentException('Slice range is empty.');
        }

        $out = [$this->pointAt($fromM)];
        for ($i = 0, $n = count($this->coords); $i < $n; $i++) {
            if ($this->measures[$i] > $fromM && $this->measures[$i] < $toM) {
                $out[] = $this->coords[$i];
            }
        }
        $out[] = $this->pointAt($toM);

        return new self($out);
    }

    /** Douglas–Peucker simplification with tolerance in metres. */
    public function simplify(float $toleranceM): self
    {
        if ($toleranceM <= 0 || count($this->coords) <= 2) {
            return $this;
        }

        $keep = array_fill(0, count($this->coords), false);
        $keep[0] = $keep[count($this->coords) - 1] = true;
        $stack = [[0, count($this->coords) - 1]];

        while ($stack) {
            [$start, $end] = array_pop($stack);
            $maxDist = 0.0;
            $index = null;
            [$aLng, $aLat] = $this->coords[$start];
            [$bLng, $bLat] = $this->coords[$end];

            for ($i = $start + 1; $i < $end; $i++) {
                $d = GeoMath::projectOnSegment($this->coords[$i][1], $this->coords[$i][0], $aLat, $aLng, $bLat, $bLng)['distance'];
                if ($d > $maxDist) {
                    $maxDist = $d;
                    $index = $i;
                }
            }

            if ($index !== null && $maxDist > $toleranceM) {
                $keep[$index] = true;
                $stack[] = [$start, $index];
                $stack[] = [$index, $end];
            }
        }

        return new self(array_values(array_filter($this->coords, fn ($_, $i) => $keep[$i], ARRAY_FILTER_USE_BOTH)));
    }

    /** @return array{min_lat: float, max_lat: float, min_lng: float, max_lng: float} */
    public function bbox(): array
    {
        $lngs = array_column($this->coords, 0);
        $lats = array_column($this->coords, 1);

        return ['min_lat' => min($lats), 'max_lat' => max($lats), 'min_lng' => min($lngs), 'max_lng' => max($lngs)];
    }

    /** @return array{type: string, coordinates: list<array{0: float, 1: float}>} */
    public function toGeoJson(int $precision = 7): array
    {
        return [
            'type' => 'LineString',
            'coordinates' => array_map(fn ($c) => [round($c[0], $precision), round($c[1], $precision)], $this->coords),
        ];
    }

    /** WKT in lng-lat order (pair with axis-order=long-lat when loading into SRID 4326). */
    public function toWkt(): string
    {
        return 'LINESTRING('.implode(',', array_map(fn ($c) => sprintf('%.7F %.7F', $c[0], $c[1]), $this->coords)).')';
    }
}
