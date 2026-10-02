<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class NumberSequence
{
    /** Next value for a key. Must run inside the caller's transaction for gap-free numbering. */
    public function next(string $key): int
    {
        DB::statement('INSERT INTO number_sequences (`key`, value, created_at, updated_at) VALUES (?, 0, NOW(), NOW())
            ON DUPLICATE KEY UPDATE `key` = `key`', [$key]);

        $value = (int) DB::table('number_sequences')->where('key', $key)->lockForUpdate()->value('value') + 1;
        DB::table('number_sequences')->where('key', $key)->update(['value' => $value, 'updated_at' => now()]);

        return $value;
    }

    /** Indian financial year label (April–March), e.g. 2026-27. */
    public static function financialYear(\DateTimeInterface $date): string
    {
        $year = (int) $date->format('Y');
        $start = (int) $date->format('n') >= 4 ? $year : $year - 1;

        return sprintf('%d-%02d', $start, ($start + 1) % 100);
    }
}
