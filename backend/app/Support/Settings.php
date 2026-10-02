<?php

namespace App\Support;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

/**
 * Typed access to `system_settings`, cached. Every tunable business value
 * (radii, evidence limits, SLA defaults, ...) is read through here.
 */
class Settings
{
    private const CACHE_KEY = 'system_settings.all';

    /** @var array<string, array{type: string, value: ?string}>|null */
    private ?array $loaded = null;

    public function get(string $key, mixed $default = null): mixed
    {
        $row = $this->all()[$key] ?? null;

        if ($row === null) {
            if (func_num_args() === 1) {
                throw new InvalidArgumentException("Unknown system setting [{$key}].");
            }

            return $default;
        }

        return self::cast($row['type'], $row['value']);
    }

    public function int(string $key): int
    {
        return (int) $this->get($key);
    }

    public function float(string $key): float
    {
        return (float) $this->get($key);
    }

    public function bool(string $key): bool
    {
        return (bool) $this->get($key);
    }

    public function set(string $key, mixed $value): void
    {
        $setting = SystemSetting::where('key', $key)->firstOrFail();
        $setting->value = is_array($value) ? json_encode($value) : (is_bool($value) ? ($value ? '1' : '0') : (string) $value);
        $setting->save(); // audited via the model

        $this->flush();
    }

    /** @return array<string, mixed> settings flagged public, for the mobile app */
    public function public(): array
    {
        return collect($this->all())
            ->filter(fn ($row) => $row['is_public'])
            ->map(fn ($row) => self::cast($row['type'], $row['value']))
            ->all();
    }

    public function flush(): void
    {
        $this->loaded = null;
        Cache::forget(self::CACHE_KEY);
    }

    /** @return array<string, array{type: string, value: ?string, is_public: bool}> */
    private function all(): array
    {
        return $this->loaded ??= Cache::rememberForever(self::CACHE_KEY, fn () => SystemSetting::query()
            ->get(['key', 'type', 'value', 'is_public'])
            ->mapWithKeys(fn ($s) => [$s->key => ['type' => $s->type, 'value' => $s->value, 'is_public' => (bool) $s->is_public]])
            ->all());
    }

    public static function cast(string $type, ?string $value): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'int' => (int) $value,
            'float' => (float) $value,
            'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'json' => json_decode($value, true),
            default => $value,
        };
    }
}
