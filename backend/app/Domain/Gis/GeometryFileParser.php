<?php

namespace App\Domain\Gis;

use InvalidArgumentException;
use SimpleXMLElement;

/**
 * Reads a road centre-line from GeoJSON, KML or GPX (e.g. a track recorded while driving the road).
 * Multiple lines/segments are joined in file order.
 */
class GeometryFileParser
{
    public function parse(string $contents, string $extension): LineString
    {
        return match (strtolower($extension)) {
            'geojson', 'json' => $this->geoJson($contents),
            'kml' => $this->kml($contents),
            'gpx' => $this->gpx($contents),
            default => throw new InvalidArgumentException('Unsupported file type. Use GeoJSON, KML or GPX.'),
        };
    }

    private function geoJson(string $contents): LineString
    {
        $data = json_decode($contents, true);
        if (! is_array($data)) {
            throw new InvalidArgumentException('The file is not valid JSON.');
        }

        // A FeatureCollection with several line features (e.g. an export with sections): take the road feature or join lines.
        if (($data['type'] ?? null) === 'FeatureCollection' && count($data['features'] ?? []) > 1) {
            $road = collect($data['features'])->first(fn ($f) => ($f['properties']['feature'] ?? null) === 'road');
            if ($road) {
                return LineString::fromGeoJson($road);
            }
            $coords = [];
            foreach ($data['features'] as $feature) {
                $coords = array_merge($coords, LineString::fromGeoJson($feature)->coordinates());
            }

            return new LineString($coords);
        }

        return LineString::fromGeoJson($data);
    }

    private function kml(string $contents): LineString
    {
        $xml = $this->xml($contents);
        $xml->registerXPathNamespace('k', 'http://www.opengis.net/kml/2.2');
        $nodes = $xml->xpath('//k:LineString/k:coordinates') ?: $xml->xpath('//LineString/coordinates') ?: [];

        $coords = [];
        foreach ($nodes as $node) {
            foreach (preg_split('/\s+/', trim((string) $node)) as $tuple) {
                $parts = explode(',', $tuple);
                if (count($parts) >= 2) {
                    $coords[] = [(float) $parts[0], (float) $parts[1]];
                }
            }
        }
        if (count($coords) < 2) {
            throw new InvalidArgumentException('No LineString found in the KML file.');
        }

        return new LineString($coords);
    }

    private function gpx(string $contents): LineString
    {
        $xml = $this->xml($contents);
        $xml->registerXPathNamespace('g', 'http://www.topografix.com/GPX/1/1');
        $points = $xml->xpath('//g:trkpt') ?: $xml->xpath('//g:rtept') ?: $xml->xpath('//trkpt') ?: $xml->xpath('//rtept') ?: [];

        $coords = array_map(fn ($p) => [(float) $p['lon'], (float) $p['lat']], $points);
        if (count($coords) < 2) {
            throw new InvalidArgumentException('No track or route points found in the GPX file.');
        }

        return new LineString($coords);
    }

    private function xml(string $contents): SimpleXMLElement
    {
        // Never resolve external entities (XXE).
        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($contents, SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
        libxml_use_internal_errors($previous);

        if ($xml === false) {
            throw new InvalidArgumentException('The file is not valid XML.');
        }

        return $xml;
    }
}
