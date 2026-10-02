<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * Builds the sidebar for a user from config/navigation.php.
 */
class Navigation
{
    /** @return list<array{section: ?string, items: list<array<string, mixed>>}> */
    public static function for(?User $user): array
    {
        if (! $user) {
            return [];
        }

        $sections = [];
        foreach (config('navigation') as $section) {
            $items = array_values(array_filter(
                $section['items'],
                fn ($item) => Route::has($item['route']) && collect($item['can'])->contains(fn ($p) => $user->can($p)),
            ));

            if ($items !== []) {
                $sections[] = ['section' => $section['section'], 'items' => $items];
            }
        }

        return $sections;
    }

    public static function isActive(string $pattern): bool
    {
        return request()->routeIs(...explode('|', $pattern));
    }
}
