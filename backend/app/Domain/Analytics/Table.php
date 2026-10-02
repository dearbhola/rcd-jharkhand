<?php

namespace App\Domain\Analytics;

/**
 * A titled, column-defined result set: rendered on screen and exported (CSV / Excel / PDF)
 * from the same data, so the file always matches what was on screen.
 */
final class Table
{
    /**
     * @param  array<string, string>  $columns  key => heading
     * @param  list<array<string, mixed>>  $rows
     * @param  array<string, string>  $filters  human-readable filter summary
     * @param  list<string>  $notes
     */
    public function __construct(
        public readonly string $title,
        public readonly array $columns,
        public readonly array $rows,
        public readonly array $filters = [],
        public readonly bool $includesTestData = false,
        public readonly array $notes = [],
        public readonly ?string $subtitle = null,
    ) {}

    /** @return list<list<mixed>> rows as plain values in column order */
    public function matrix(): array
    {
        return array_map(fn ($row) => array_map(fn ($key) => self::plain($row[$key] ?? null), array_keys($this->columns)), $this->rows);
    }

    private static function plain(mixed $value): mixed
    {
        return match (true) {
            $value instanceof \DateTimeInterface => $value->format('d-M-Y H:i'),
            is_bool($value) => $value ? 'Yes' : 'No',
            is_array($value) => implode(', ', $value),
            default => $value,
        };
    }
}
