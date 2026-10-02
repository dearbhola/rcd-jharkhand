<?php

namespace App\Domain\Performance;

use App\Models\Contract;
use App\Support\NumberSequence;
use Illuminate\Support\Carbon;

/**
 * What a performance figure covers: contractor / contract / division / road / section,
 * and a period (financial year, a contract's maintenance period, or explicit dates).
 * Reports are attributed to the period by the date they were reported.
 */
final class PerformanceScope
{
    public function __construct(
        public readonly ?int $contractorId = null,
        public readonly ?int $contractId = null,
        public readonly ?int $divisionId = null,
        public readonly ?int $roadId = null,
        public readonly ?int $sectionId = null,
        public readonly ?Carbon $from = null,
        public readonly ?Carbon $to = null,
        public readonly ?string $periodLabel = null,
    ) {}

    /** @param array<string, mixed> $in validated request input */
    public static function fromInput(array $in): self
    {
        $from = $to = null;
        $label = null;

        if (! empty($in['fy']) && preg_match('/^(\d{4})-(\d{2})$/', $in['fy'], $m)) {
            $from = Carbon::create((int) $m[1], 4, 1)->startOfDay();
            $to = Carbon::create((int) $m[1] + 1, 3, 31)->endOfDay();
            $label = "FY {$in['fy']}";
        } elseif (($in['period'] ?? null) === 'maintenance' && ! empty($in['contract_id']) && ($c = Contract::find($in['contract_id']))?->maintenance_start_date) {
            $from = $c->maintenance_start_date->copy()->startOfDay();
            $to = $c->maintenance_end_date->copy()->endOfDay();
            $label = 'Maintenance period '.$from->format('d-M-Y').' – '.$to->format('d-M-Y');
        } elseif (! empty($in['from']) || ! empty($in['to'])) {
            $from = ! empty($in['from']) ? Carbon::parse($in['from'])->startOfDay() : null;
            $to = ! empty($in['to']) ? Carbon::parse($in['to'])->endOfDay() : null;
            $label = ($from?->format('d-M-Y') ?? 'start').' – '.($to?->format('d-M-Y') ?? 'today');
        }

        return new self(
            contractorId: self::int($in['contractor_id'] ?? null),
            contractId: self::int($in['contract_id'] ?? null),
            divisionId: self::int($in['division_id'] ?? null),
            roadId: self::int($in['road_id'] ?? null),
            sectionId: self::int($in['road_section_id'] ?? null),
            from: $from,
            to: $to,
            periodLabel: $label,
        );
    }

    public function withContractor(int $contractorId): self
    {
        return new self($contractorId, $this->contractId, $this->divisionId, $this->roadId, $this->sectionId, $this->from, $this->to, $this->periodLabel);
    }

    /** @return list<string> financial years offered in filters, newest first */
    public static function financialYears(int $count = 5): array
    {
        $current = (int) substr(NumberSequence::financialYear(now()), 0, 4);

        return array_map(fn ($y) => sprintf('%d-%02d', $y, ($y + 1) % 100), range($current, $current - $count + 1));
    }

    private static function int(mixed $v): ?int
    {
        return is_numeric($v) && (int) $v > 0 ? (int) $v : null;
    }
}
