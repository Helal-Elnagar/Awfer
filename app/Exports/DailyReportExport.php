<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class DailyReportExport implements WithMultipleSheets
{
    protected array $payload;
    protected string $currency;

    public function __construct(array $payload, string $currency)
    {
        $this->payload  = $payload;
        $this->currency = $currency;
    }

    public function sheets(): array
    {
        return [
            new DailyReportOrdersSheet($this->payload['orders'], $this->payload['summary'], $this->currency),
            new DailyReportDriversSheet($this->payload['drivers'], $this->payload['summary']),
        ];
    }
}
