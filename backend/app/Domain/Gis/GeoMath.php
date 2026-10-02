<?php

namespace App\Domain\Gis;

/**
 * Geodesic helpers (WGS84 sphere approximation). Accurate to well under a metre
 * at road-segment scale, which is far below consumer GPS accuracy.
 */
final class GeoMath
{
    public const EARTH_RADIUS_M = 6371008.8;

    public static function distance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 2 * self::EARTH_RADIUS_M * asin(min(1.0, sqrt($a)));
    }

    /**
     * Project point P onto segment AB in a local equirectangular plane.
     *
     * @return array{t: float, lat: float, lng: float, distance: float} t in [0,1] along AB
     */
    public static function projectOnSegment(float $pLat, float $pLng, float $aLat, float $aLng, float $bLat, float $bLng): array
    {
        $k = cos(deg2rad(($aLat + $bLat) / 2));
        $ax = $aLng * $k;
        $bx = $bLng * $k;
        $px = $pLng * $k;

        $dx = $bx - $ax;
        $dy = $bLat - $aLat;
        $len2 = $dx * $dx + $dy * $dy;

        $t = $len2 > 0 ? (($px - $ax) * $dx + ($pLat - $aLat) * $dy) / $len2 : 0.0;
        $t = max(0.0, min(1.0, $t));

        $lat = $aLat + $t * ($bLat - $aLat);
        $lng = $aLng + $t * ($bLng - $aLng);

        return ['t' => $t, 'lat' => $lat, 'lng' => $lng, 'distance' => self::distance($pLat, $pLng, $lat, $lng)];
    }

    /**
     * Bounding box around a point, in degrees.
     *
     * @return array{min_lat: float, max_lat: float, min_lng: float, max_lng: float}
     */
    public static function bboxAround(float $lat, float $lng, float $radiusM): array
    {
        $dLat = rad2deg($radiusM / self::EARTH_RADIUS_M);
        $dLng = rad2deg($radiusM / (self::EARTH_RADIUS_M * max(cos(deg2rad($lat)), 1e-6)));

        return [
            'min_lat' => $lat - $dLat,
            'max_lat' => $lat + $dLat,
            'min_lng' => $lng - $dLng,
            'max_lng' => $lng + $dLng,
        ];
    }
}
