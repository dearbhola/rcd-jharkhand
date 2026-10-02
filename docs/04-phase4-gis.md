# Phase 4 — GIS (web) — completed 02-Oct-2026

## Verified

| Check | Result |
|---|---|
| Automated tests | **111 passed, 613 assertions** (Phases 1–4) |
| Real-browser run (Chrome via puppeteer-core) | Map loads layers for the viewport; filters reload; clicking a road fills the side panel. Locate tool resolves a typed point. New road → import GPX draft → save → length warning. **No JavaScript errors.** |
| Code style | clean |

## Screens

| Screen | Who | What |
|---|---|---|
| **Map** (`/map`) | `road.view` | Road sections coloured by route: green = in maintenance (contractor), orange = no maintenance (department); dashed = test data. Clustered asset markers. Filters: division → sub-division, contractor, maintenance status, JE, AE, EE, asset type. **Clicking a road** shows road → section → contract (maintenance dates, active?) → contractor → JE → AE → EE with phone numbers; open issues and repair history follow in Phases 5–6. A "Damage reports" layer toggle is in place for Phase 5. |
| **Locate a point** (`/gis/locate`) | `road.view` | Click the map, type coordinates, or use the device's GPS. Shows exactly what the system resolves: road, section, **chainage**, distance from road, nearest asset, contract, contractor, JE/AE/EE, **workflow route**, missing mappings, and other roads within the radius. An "as of date" field shows historical responsibility. |
| **Geometry editor** (road page → "Draw/Edit geometry") | `gis.manage` | Draw the centre line, edit vertices (with snapping), reverse direction. **Import GeoJSON, KML or GPX** (e.g. a track recorded by driving the road) as a draft, then save. Each save is a **new version**; old versions are kept. Sections are re-cut automatically. Warns if the drawn length differs from the declared chainage by more than 5%. **Km-stone markers** are added by clicking the map. |
| **Export** | `gis.manage` | GeoJSON (EPSG:4326) per road (road line + sections, with codes and chainage) or for all roads. The export re-imports cleanly. |

## How location resolution works

```text
GPS point
  → spatial index (gis_features, MySQL SRID 4326) → candidate sections by bounding box   [fast]
  → exact point-to-line distance per candidate; keep those within the radius            [PHP]
  → nearest road; chainage = calibrated distance along the road line
  → nearest asset within radius (optional)
  → contract mapping covering that chainage on the date → maintenance dates → route
  → JE/AE/EE in force on the date (asset override, otherwise section)
```

- Code: `LocationResolver` → `LocationMatch`, then `ResponsibilityResolver` → `Resolution`. Phase 5 reports use exactly this; IDs sent by the client are never trusted.
- **Calibration** (`ChainageScale`): without markers, chainage = road start + distance along the drawn line. Km-stone markers become anchors, interpolated between and continuing 1:1 after the last. Inconsistent markers are ignored. Markers further than 100 m from the line, or out of order, are refused.
- **Department vs contractor:** the contractor route applies only when a coverage mapping exists on the date **and** the date is inside the contract's maintenance period. Otherwise the department route applies, even if the contract is still mapped (tested).
- **Performance:** layers are requested per viewport (`bbox`), capped at 3,000 features. Lines are simplified to about one pixel at the current zoom (Douglas–Peucker); at zoom 8 the payload is under half that of zoom 17 (tested). Assets are clustered. Road lines and scales are cached per request.
- **PostGIS later:** only `MysqlGisEngine` (implements `GisEngine`) touches spatial SQL.

## Security notes

- KML/GPX are parsed with network access and external entities disabled; an XXE test file is refused (tested).
- Uploads are limited to 10 MB and `.geojson/.json/.kml/.gpx`. At most 20,000 vertices are accepted; imports above that are simplified to 1 m.
- The resolve endpoint is rate-limited to 120 requests/min.

## Notes

- **Base map:** OpenStreetMap's public tiles are fine for development. Their usage policy forbids heavy production use, so set `map.tile_url` to a licensed or self-hosted provider before go-live.
- The **demo road geometries are synthetic** and don't follow real streets. Redraw or import real alignments (GPX from a drive works well) in the geometry editor.
- Maps use canvas rendering for performance; the map element exposes `leafletMap` for debugging.
