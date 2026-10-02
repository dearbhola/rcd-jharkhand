<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Business tunables (radii, evidence limits, security, notifications, map …).
 * Values are typed and validated; only changed values are written, each audited.
 */
class SettingsController extends Controller
{
    private const GROUP_LABELS = [
        'location' => 'Location & GPS',
        'evidence' => 'Evidence (photos & video)',
        'duplicate' => 'Duplicate detection',
        'workflow' => 'Workflow',
        'delegation' => 'Delegation',
        'security' => 'Security & sign-in',
        'performance' => 'Performance & history',
        'notifications' => 'Notifications',
        'sync' => 'Offline sync (mobile)',
        'map' => 'Map',
    ];

    public function index(): View
    {
        $settings = SystemSetting::with('updater:id,name')->orderBy('id')->get()->groupBy('group');

        return view('admin.settings.index', ['groups' => self::GROUP_LABELS, 'settings' => $settings]);
    }

    public function update(Request $request, Settings $settings): RedirectResponse
    {
        $input = $request->validate(['settings' => ['required', 'array']])['settings'];
        $rows = SystemSetting::whereIn('key', array_keys($input))->get()->keyBy('key');
        $errors = [];
        $changes = [];

        foreach ($input as $key => $raw) {
            $row = $rows[$key] ?? null;
            if (! $row) {
                $errors["settings.{$key}"] = 'Unknown setting.';

                continue;
            }

            try {
                $value = $this->normalise($row->type, $raw);
            } catch (\InvalidArgumentException $e) {
                $errors["settings.{$key}"] = $e->getMessage();

                continue;
            }

            if ($value !== $row->value) {
                $changes[$key] = $value;
            }
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        DB::transaction(function () use ($changes, $rows, $request) {
            foreach ($changes as $key => $value) {
                $rows[$key]->forceFill(['value' => $value, 'updated_by' => $request->user()->id])->save(); // audited
            }
        });
        $settings->flush();

        return back()->with('success', $changes ? count($changes).' setting(s) saved.' : 'No changes.');
    }

    private function normalise(string $type, mixed $raw): string
    {
        $raw = is_string($raw) ? trim($raw) : $raw;

        return match ($type) {
            'bool' => in_array($raw, ['1', 1, true, 'true', 'on'], true) ? '1' : '0',
            'int' => is_numeric($raw) && (int) $raw == $raw && (int) $raw >= 0 ? (string) (int) $raw
                : throw new \InvalidArgumentException('Enter a whole number (0 or more).'),
            'float' => is_numeric($raw) ? (string) (float) $raw : throw new \InvalidArgumentException('Enter a number.'),
            'json' => $this->json((string) $raw),
            default => mb_strlen((string) $raw) <= 1000 ? (string) $raw : throw new \InvalidArgumentException('Too long.'),
        };
    }

    private function json(string $raw): string
    {
        $decoded = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException('Must be valid JSON, e.g. ["a", "b"].');
        }

        return json_encode($decoded, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
