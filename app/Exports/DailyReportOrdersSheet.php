<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class DailyReportOrdersSheet implements FromArray, WithTitle, WithHeadings, WithStyles
{
    protected array $orders;
    protected array $summary;
    protected string $currency;

    public function __construct(array $orders, array $summary, string $currency)
    {
        $this->orders   = $orders;
        $this->summary  = $summary;
        $this->currency = $currency;
    }

    public function title(): string
    {
        return __('admin.report_orders_sheet');
    }

    public function headings(): array
    {
        return [
            '#',
            __('admin.order_number'),
            __('admin.neighborhood'),
            __('admin.period'),
            __('admin.details'),
            __('admin.payment_status'),
            __('admin.amount'),
        ];
    }

    public function array(): array
    {
        $rows = [];

        foreach ($this->orders as $order) {
            $details = collect($order['details'] ?? [])
                ->map(fn ($item) => $item['name'] . ' × ' . $item['quantity'])
                ->implode(' + ');

            $rows[] = [
                $order['seq'],
                $order['order_number'],
                $order['neighborhood'],
                $order['period'],
                $details,
                $order['is_cod'] ? __('admin.cash_on_delivery') : __('admin.prepaid'),
                $order['amount'],
            ];
        }

        $rows[] = [
            __('admin.total'),
            '',
            '',
            '',
            '',
            '',
            $this->summary['total_amount'],
        ];

        return $rows;
    }

    public function styles(Worksheet $sheet)
    {
        $lastRow = count($this->orders) + 2;

        return [
            1 => ['font' => ['bold' => true]],
            $lastRow => ['font' => ['bold' => true]],
        ];
    }
}
