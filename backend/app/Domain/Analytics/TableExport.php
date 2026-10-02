<?php

namespace App\Domain\Analytics;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** Excel sheet for a Table: title block, filters, test-data warning, then the data. */
class TableExport implements FromArray, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
{
    private int $headerRows;

    public function __construct(private readonly Table $table)
    {
        $this->headerRows = count($this->preamble()) + 1;
    }

    /** @return list<list<string>> */
    private function preamble(): array
    {
        $lines = [[$this->table->title]];
        if ($this->table->subtitle) {
            $lines[] = [$this->table->subtitle];
        }
        foreach ($this->table->filters as $k => $v) {
            $lines[] = ["{$k}: {$v}"];
        }
        $lines[] = ['Generated: '.now()->format('d-M-Y H:i T')];
        if ($this->table->includesTestData) {
            $lines[] = ['TEST DATA INCLUDED — NOT AN OFFICIAL REPORT'];
        }
        foreach ($this->table->notes as $note) {
            $lines[] = [$note];
        }
        $lines[] = [''];

        return $lines;
    }

    public function headings(): array
    {
        return [...$this->preamble(), array_values($this->table->columns)];
    }

    public function array(): array
    {
        return $this->table->matrix();
    }

    public function title(): string
    {
        return mb_substr(preg_replace('/[\\\\\/\?\*\[\]:]/', ' ', $this->table->title), 0, 31);
    }

    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true, 'size' => 13]], $this->headerRows => ['font' => ['bold' => true]]];
    }
}
