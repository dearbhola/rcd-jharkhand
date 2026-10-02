<?php

namespace Database\Seeders;

use App\Models\SystemSetting;
use Illuminate\Database\Seeder;

/**
 * Default business tunables. Existing values are never overwritten, so admins'
 * changes survive re-seeding. Values are starting points, not final policy.
 */
class SystemSettingSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->defaults() as [$key, $group, $type, $value, $description, $public]) {
            SystemSetting::firstOrCreate(
                ['key' => $key],
                [
                    'group' => $group,
                    'type' => $type,
                    'value' => is_array($value) ? json_encode($value) : (is_bool($value) ? ($value ? '1' : '0') : (string) $value),
                    'description' => $description,
                    'is_public' => $public,
                ],
            );
        }
    }

    /** @return list<array{0: string, 1: string, 2: string, 3: mixed, 4: string, 5: bool}> */
    private function defaults(): array
    {
        return [
            // Location
            ['report.location_radius_m', 'location', 'int', 50, 'Max distance (m) from a mapped road/asset to create a field report', true],
            ['review.location_radius_m', 'location', 'int', 50, 'Max distance (m) from the report site for JE/AE field review', true],
            ['repair.location_radius_m', 'location', 'int', 50, 'Max distance (m) from the report site to submit a repair', true],
            ['gps.max_accuracy_m', 'location', 'int', 30, 'Reject GPS fixes with worse (larger) accuracy than this', true],
            ['gps.max_capture_age_hours', 'location', 'int', 72, 'Oldest offline capture accepted at sync', true],
            ['location.test_override_enabled', 'location', 'bool', true, 'Allow users with report.location_override to bypass radius checks (test mode)', false],

            // Evidence
            ['evidence.report.min_photos', 'evidence', 'int', 1, 'Minimum photos for a new report', true],
            ['evidence.report.max_photos', 'evidence', 'int', 5, 'Maximum photos for a new report', true],
            ['evidence.report.max_videos', 'evidence', 'int', 1, 'Maximum videos for a new report', true],
            ['evidence.report.video_required', 'evidence', 'bool', false, 'Video mandatory for a new report', true],
            ['evidence.repair.min_photos', 'evidence', 'int', 2, 'Minimum photos when submitting a repair', true],
            ['evidence.repair.max_photos', 'evidence', 'int', 6, 'Maximum photos when submitting a repair', true],
            ['evidence.repair.max_videos', 'evidence', 'int', 1, 'Maximum videos when submitting a repair', true],
            ['evidence.repair.video_required', 'evidence', 'bool', false, 'Video mandatory for a repair submission', true],
            ['evidence.inspection.min_photos', 'evidence', 'int', 1, 'Minimum photos for a field review', true],
            ['evidence.inspection.max_photos', 'evidence', 'int', 5, 'Maximum photos for a field review', true],
            ['evidence.inspection.max_videos', 'evidence', 'int', 1, 'Maximum videos for a field review', true],
            ['evidence.inspection.video_required', 'evidence', 'bool', false, 'Video mandatory for a field review', true],
            ['evidence.max_image_kb', 'evidence', 'int', 10240, 'Maximum image size (KB)', true],
            ['evidence.max_video_kb', 'evidence', 'int', 51200, 'Maximum video size (KB)', true],
            ['evidence.max_video_seconds', 'evidence', 'int', 60, 'Maximum video duration (s)', true],
            ['evidence.image_mimes', 'evidence', 'json', ['image/jpeg', 'image/png', 'image/webp'], 'Allowed image MIME types', true],
            ['evidence.video_mimes', 'evidence', 'json', ['video/mp4', 'video/3gpp', 'video/quicktime'], 'Allowed video MIME types', true],
            ['evidence.image_quality', 'evidence', 'int', 80, 'JPEG quality for compressed/display copies (1-100)', true],
            ['evidence.image_max_dimension', 'evidence', 'int', 1920, 'Longest edge (px) for compressed/display copies', true],

            // Duplicate detection
            ['duplicate.radius_m', 'duplicate', 'int', 30, 'Reports within this distance may be duplicates', false],
            ['duplicate.chainage_window_m', 'duplicate', 'int', 50, 'Chainage window (m) for duplicate detection', false],
            ['duplicate.window_hours', 'duplicate', 'int', 168, 'Time window (h) for duplicate detection', false],

            // Workflow
            ['workflow.auto_validate_roles', 'workflow', 'json', ['JE', 'AE', 'EE'], 'Reports by these roles skip JE validation (mapped JE is still assigned)', false],
            ['workflow.validation_requires_location', 'workflow', 'bool', false, 'JE must be on site to validate a citizen report', false],

            // Delegation
            ['delegation.default_return_on_end', 'delegation', 'bool', true, 'Return open tasks to the primary user when a delegation ends', false],

            // Auth & security
            ['auth.password_min_length', 'security', 'int', 10, 'Minimum password length', false],
            ['auth.password_expiry_days', 'security', 'int', 90, 'Staff password expiry in days (0 = never)', false],
            ['auth.max_login_attempts', 'security', 'int', 5, 'Failed logins before temporary lockout', false],
            ['auth.lockout_minutes', 'security', 'int', 15, 'Lockout duration (min)', false],
            ['auth.mobile_inactivity_logout_minutes', 'security', 'int', 30, 'Mobile app auto-logout after inactivity (min)', true],
            ['auth.token_ttl_days', 'security', 'int', 30, 'Mobile API token lifetime (days)', false],
            ['auth.sms_otp_enabled', 'security', 'bool', false, 'Enable SMS-OTP features (citizen self-registration, forgot password). Requires an SMS provider.', true],
            ['otp.length', 'security', 'int', 6, 'OTP digits', true],
            ['otp.ttl_minutes', 'security', 'int', 10, 'OTP validity (min)', true],
            ['otp.max_attempts', 'security', 'int', 5, 'OTP verification attempts', false],
            ['otp.resend_cooldown_seconds', 'security', 'int', 60, 'Minimum seconds between OTP sends', true],

            // Notifications (in-app is always on)
            ['notifications.mail_enabled', 'notifications', 'bool', false, 'Also send email notifications', false],
            ['notifications.sms_enabled', 'notifications', 'bool', false, 'Also send SMS (requires an SMS provider)', false],
            ['notifications.whatsapp_enabled', 'notifications', 'bool', false, 'Also send WhatsApp (requires a provider)', false],
            ['notifications.push_enabled', 'notifications', 'bool', false, 'Also send mobile push (requires the mobile app)', false],
            ['notifications.external_events', 'notifications', 'json', ['task.assigned', 'task.reassigned', 'sla.breached', 'sla.escalated', 'delegation.started'], 'Events that use external channels (others are in-app only)', false],

            // Sync
            ['sync.local_file_retention_hours', 'sync', 'int', 24, 'Hours the app keeps local media after a confirmed sync', true],
            ['sync.max_retry_attempts', 'sync', 'int', 10, 'Automatic upload retries before manual retry is required', true],

            // Map
            ['map.tile_url', 'map', 'string', 'https://tile.openstreetmap.org/{z}/{x}/{y}.png', 'Base map tile URL template (use a licensed provider in production)', true],
            ['map.tile_attribution', 'map', 'string', '&copy; OpenStreetMap contributors', 'Base map attribution', true],
            ['map.default_center', 'map', 'json', [23.61, 85.28], 'Default map centre [lat, lng]', true],
            ['map.default_zoom', 'map', 'int', 8, 'Default map zoom', true],
        ];
    }
}
