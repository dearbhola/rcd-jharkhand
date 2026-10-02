<?php

/*
 * Infrastructure-level configuration. Business tunables (radii, evidence
 * limits, SLA values, ...) live in the `system_settings` table instead.
 */
return [

    'evidence_disk' => env('RCD_EVIDENCE_DISK', 'evidence'),

    'watermark_font' => resource_path('fonts/DejaVuSans-Bold.ttf'),

    'ffmpeg' => [
        'ffmpeg' => env('FFMPEG_BINARY', 'ffmpeg'),
        'ffprobe' => env('FFPROBE_BINARY', 'ffprobe'),
    ],

    'test_data' => [
        // Never include test data by default in production.
        'include_by_default' => env('RCD_INCLUDE_TEST_DATA', env('APP_ENV') !== 'production'),
    ],

    'otp' => [
        'driver' => env('RCD_OTP_DRIVER', 'log'), // log | (sms gateway, D5)
    ],

    'report_no_prefix' => env('RCD_REPORT_PREFIX', 'RCD'),

    'gis' => [
        'engine' => env('RCD_GIS_ENGINE', 'mysql'), // mysql | postgis (future)
        'srid' => 4326,
    ],

];
