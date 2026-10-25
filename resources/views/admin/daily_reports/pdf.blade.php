<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>{{ __('admin.daily_reports') }}</title>
    <style>
        body {
            font-family: dejavusans;
            direction: rtl;
            font-size: 11px;
        }
        .report-header {
            text-align: center;
            margin-bottom: 16px;
        }
        .report-header h1 {
            font-size: 18px;
            margin: 0 0 4px 0;
        }
        .filters {
            margin-bottom: 18px;
        }
        .filters table {
            width: 100%;
            border-collapse: collapse;
        }
        .filters th,
        .filters td {
            border: 1px solid #cccccc;
            padding: 5px 8px;
            text-align: right;
        }
        .filters th {
            background-color: #f0f0f0;
            width: 25%;
        }
        .section-title {
            background-color: #eeeeee;
            padding: 7px 10px;
            font-size: 14px;
            margin: 0 0 8px 0;
            border: 1px solid #cccccc;
        }
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            font-size: 10px;
        }
        .table th,
        .table td {
            border: 1px solid #999999;
            padding: 5px 7px;
            text-align: right;
            vertical-align: top;
            word-wrap: break-word;
        }
        .table th {
            background-color: #f0f0f0;
            font-weight: bold;
        }
        .table .total-row td {
            font-weight: bold;
            background-color: #f7f7f7;
        }
        .table .returned td {
            color: #888888;
        }
    </style>
</head>
<body>
    <div class="report-header">
        <h1>{{ __('admin.daily_reports') }}</h1>
    </div>

    <div class="filters">
        <table>
            <tr>
                <th>{{ __('admin.daily_report_date') }}</th>
                <td>{{ $filters['date'] }}</td>
                <th>{{ __('admin.delivery_period') }}</th>
                <td>{{ $filters['period_name'] ?? __('admin.delivery_period_all') }}</td>
            </tr>
            <tr>
                <th>{{ __('admin.delivery_driver') }}</th>
                <td colspan="3">{{ $filters['delivery_name'] ?? __('admin.delivery_driver_all') }}</td>
            </tr>
        </table>
    </div>

    <h2 class="section-title">{{ __('admin.report_orders_sheet') }}</h2>
    <table class="table">
        <thead>
            <tr>
                <th>#</th>
                <th>{{ __('admin.order_number') }}</th>
                <th>{{ __('admin.neighborhood') }}</th>
                <th>{{ __('admin.period') }}</th>
                <th>{{ __('admin.details') }}</th>
                <th>{{ __('admin.payment_status') }}</th>
                <th>{{ __('admin.amount') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($orders as $order)
                <tr class="{{ $order['category'] === 'returned' ? 'returned' : '' }}">
                    <td>{{ $order['seq'] }}</td>
                    <td>{{ $order['order_number'] }}</td>
                    <td>{{ $order['neighborhood'] }}</td>
                    <td>{{ $order['period'] }}</td>
                    <td>{{ collect($order['details'] ?? [])->map(fn ($item) => $item['name'] . ' × ' . $item['quantity'])->implode(' + ') }}</td>
                    <td>{{ $order['is_cod'] ? __('admin.cash_on_delivery') : __('admin.prepaid') }}</td>
                    <td>{{ number_format($order['amount'], 2) }} {{ $currency }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7">{{ __('admin.no_orders_for_selected_filters') }}</td>
                </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr class="total-row">
                <td colspan="6">{{ __('admin.total') }}</td>
                <td>{{ number_format($summary['total_amount'], 2) }} {{ $currency }}</td>
            </tr>
        </tfoot>
    </table>

    <h2 class="section-title">{{ __('admin.report_deliveries_sheet') }}</h2>
    <table class="table">
        <thead>
            <tr>
                <th>{{ __('admin.delivery_driver') }}</th>
                <th>{{ __('admin.actual_loading') }}</th>
                <th>{{ __('admin.total_loading') }}</th>
                <th>{{ __('admin.loading_percentage') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($drivers as $driver)
                <tr>
                    <td>{{ $driver['name'] }}</td>
                    <td>{{ $driver['actual_loading'] }}</td>
                    <td>{{ $driver['total_loading'] }}</td>
                    <td>{{ $driver['loading_percentage'] }}%</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4">{{ __('admin.no_data_available') }}</td>
                </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr class="total-row">
                <td>{{ __('admin.total') }}</td>
                <td>{{ $summary['actual_loading'] }}</td>
                <td>{{ $summary['total_loading'] }}</td>
                <td>{{ $summary['loading_percentage'] }}%</td>
            </tr>
        </tfoot>
    </table>
</body>
</html>
