<?php

namespace App\Domain\Analytics;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\Response;

class Exporter
{
    public const FORMATS = ['csv', 'xlsx', 'pdf'];

    public function download(Table $table, string $format, ?string $filename = null): Response
    {
        $name = ($filename ?? Str::slug($table->title)).'-'.now()->format('Ymd-Hi').'.'.$format;

        return match ($format) {
            'csv' => $this->csv($table, $name),
            'xlsx' => Excel::download(new TableExport($table), $name, \Maatwebsite\Excel\Excel::XLSX),
            'pdf' => Pdf::loadView('exports.table', ['table' => $table])
                ->setPaper('a4', count($table->columns) > 6 ? 'landscape' : 'portrait')
                ->download($name),
            default => abort(400, 'Unknown export format.'),
        };
    }

    private function csv(Table $table, string $name): Response
    {
        return response()->streamDownload(function () use ($table) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel shows ₹ and Hindi text correctly
            fputcsv($out, [$table->title]);
            foreach ($table->filters as $k => $v) {
                fputcsv($out, ["{$k}: {$v}"]);
            }
            fputcsv($out, ['Generated: '.now()->format('d-M-Y H:i T')]);
            if ($table->includesTestData) {
                fputcsv($out, ['TEST DATA INCLUDED — NOT AN OFFICIAL REPORT']);
            }
            fputcsv($out, []);
            fputcsv($out, array_values($table->columns));
            foreach ($table->matrix() as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
