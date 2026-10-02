<?php

use App\Models\Report;

if (! function_exists('km')) {
    /** Format a chainage in metres as km with 3 decimals, e.g. 14250 → "14.250". */
    function km(?int $metres, bool $suffix = false): string
    {
        if ($metres === null) {
            return '—';
        }

        return number_format($metres / 1000, 3, '.', '').($suffix ? ' km' : '');
    }
}

if (! function_exists('d')) {
    /** Display date in the house style, e.g. 02-Oct-2026. */
    function d(?DateTimeInterface $date, bool $withTime = false): string
    {
        return $date ? $date->format($withTime ? 'd-M-Y H:i' : 'd-M-Y') : '—';
    }
}

if (! function_exists('report_flag')) {
    /** Add a flag to a report's location_flags (idempotent). */
    function report_flag(Report $report, string $flag): void
    {
        $flags = $report->location_flags ?? [];
        if (! in_array($flag, $flags, true)) {
            $report->forceFill(['location_flags' => [...$flags, $flag]])->save();
        }
    }
}
