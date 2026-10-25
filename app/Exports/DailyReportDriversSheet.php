<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class DailyReportDriversSheet implements FromArray, WithTitle, WithHeadings, WithStyles
{
    protected array $drivers;
    protected array $summary;

    public function __construct(array $drivers, array $summary)
    {
        $this->drivers  = $drivers;
        $this->summary  = $summary;
    }

    public function title(): string
    {
        return __('admin.report_deliveries_sheet');
    }

    public function headings(): array
    {
        return [
            __('admin.delivery_driver'),
            __('admin.actual_loading'),
            __('admin.total_loading'),
            __('admin.loading_percentage'),
            __('admin.products'),
        ];
    }

    public function array(): array
    {
        $rows = [];

        foreach ($this->drivers as $driver) {
            $products = collect($driver['products'] ?? [])
                ->map(fn ($product) => $product['name'] . ': ' . $product['delivered_quantity'] . ' / ' . $product['total_quantity'])
                ->implode(PHP_EOL);

            $rows[] = [
                $driver['name'],
                $driver['actual_loading'],
                $driver['total_loading'],
                $driver['loading_percentage'],
                $products,
            ];
        }

        $rows[] = [
            __('admin.total'),
            $this->summary['actual_loading'],
            $this->summary['total_loading'],
            $this->summary['loading_percentage'],
            '',
        ];

        return $rows;
    }

    public function styles(Worksheet $sheet)
    {
        $lastRow = count($this->drivers) + 2;

        return [
            1 => ['font' => ['bold' => true]],
            $lastRow => ['font' => ['bold' => true]],
        ];
    }
}
