<?php

namespace App\Services;

use App\Models\DeliveryPeriod;
use App\Models\Order;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Computes the admin daily delivery & loading report.
 *
 * Business rules:
 * - Base set: orders assigned for the selected date (orders.assigned_date).
 * - Delivered: status delivered/تم التوصيل.
 * - In progress: any other non-terminal status (new, confirmed, out-for-delivery, ...).
 * - Postponed: scheduled orders whose schedule_date is after the report date.
 * - Returned: cancelled orders (status cancelled/ملغي). Shown in gray and counted
 *   in the total loading figure only.
 * - total_loading  = SUM(order_items.quantity) over the whole set (delivered,
 *   in-progress, postponed and returned orders all carry their cartons).
 * - actual_loading = SUM(order_items.delivered_quantity) (zero for postponed /
 *   returned orders).
 * - Cancelled/returned orders are excluded from order counts, amounts, payment
 *   split and charts.
 */
class DailyReportService
{
    private const STATUS_DELIVERED = ['delivered', 'تم التوصيل'];
    private const STATUS_CANCELLED = ['cancelled', 'ملغي'];

    /** COD payment method id (cash on delivery). */
    private const COD_PAYMENT_METHOD_ID = 5;

    /**
     * Build the filter options (delivery periods and delivery drivers) that the
     * report page needs to render its filter bar.
     *
     * @return array{delivery_periods: Collection, deliveries: Collection, default_date: string}
     */
    public function getFilterOptions(): array
    {
        return [
            'delivery_periods' => DeliveryPeriod::orderBy('id')->get(['id', 'name', 'description']),
            'deliveries'       => User::where('type', 'delivery')->orderBy('name')->get(['id', 'name']),
            'default_date'     => Carbon::today()->toDateString(),
        ];
    }

    /**
     * Compute the full report payload for the given filters.
     *
     * @param string|null $date             Report date (Y-m-d). Defaults to today.
     * @param int|null    $deliveryPeriodId Filter by exact delivery_period_id.
     * @param int|null    $deliveryId       Filter by delivery driver (orders.delivery_id).
     * @return array
     */
    public function getData(?string $date, ?int $deliveryPeriodId = null, ?int $deliveryId = null): array
    {
        $reportDate = $date ? Carbon::parse($date)->startOfDay() : Carbon::today();

        $orders = $this->loadOrders($reportDate, $deliveryPeriodId, $deliveryId);

        $classified = $orders->map(fn (Order $order) => [
            'order'     => $order,
            'category'  => $this->classify($order, $reportDate),
            'items'     => $order->items->map(fn ($item) => [
                'product_id'        => (int) $item->product_id,
                'name'              => $item->product?->name ?? '',
                'quantity'          => (int) $item->quantity,
                'delivered_quantity'=> (int) ($item->delivered_quantity ?? 0),
            ]),
            'neighborhood' => $this->neighborhoodOf($order),
            'is_cod'       => (int) $order->payment_method_id === self::COD_PAYMENT_METHOD_ID,
        ]);

        return [
            'filters' => [
                'date'          => $reportDate->toDateString(),
                'period_id'     => $deliveryPeriodId,
                'delivery_id'   => $deliveryId,
                'period_name'   => $deliveryPeriodId ? DeliveryPeriod::find($deliveryPeriodId)?->name : null,
                'delivery_name' => $deliveryId ? User::find($deliveryId)?->name : null,
            ],
            'summary' => $this->buildSummary($classified),
            'charts'  => $this->buildCharts($classified),
            'drivers' => $this->buildDrivers($classified),
            'orders'  => $this->buildOrders($classified),
        ];
    }

    // ------------------------------------------------------------------
    //  Data loading
    // ------------------------------------------------------------------

    /**
     * @return Collection<int, Order>
     */
    private function loadOrders(Carbon $reportDate, ?int $deliveryPeriodId, ?int $deliveryId): Collection
    {
        return Order::with(['items.product', 'address.district', 'deliveryPeriod', 'deliveryUser'])
            ->whereDate('assigned_date', $reportDate->toDateString())
            ->when($deliveryPeriodId, fn ($q) => $q->where('delivery_period_id', $deliveryPeriodId))
            ->when($deliveryId, fn ($q) => $q->where('delivery_id', $deliveryId))
            ->orderBy('delivery_period_id')
            ->orderBy('order_number')
            ->get();
    }

    /**
     * Classify an order into one of: delivered | in_progress | postponed | returned.
     */
    private function classify(Order $order, Carbon $reportDate): string
    {
        if (in_array($order->status, self::STATUS_CANCELLED, true)) {
            return 'returned';
        }

        if (in_array($order->status, self::STATUS_DELIVERED, true)) {
            return 'delivered';
        }

        $scheduleDate = $order->schedule_date ?? $order->scheduled_at?->toDateString();
        if ($order->delivery_type === 'scheduled' && $scheduleDate && Carbon::parse($scheduleDate)->greaterThan($reportDate)) {
            return 'postponed';
        }

        return 'in_progress';
    }

    /**
     * Resolve the neighborhood (الحي) label of an order.
     */
    private function neighborhoodOf(Order $order): string
    {
        $address = $order->address;

        if ($address?->district) {
            return (string) $address->district->name;
        }

        if ($address && $address->district_name) {
            return (string) $address->district_name;
        }

        return '-';
    }

    // ------------------------------------------------------------------
    //  Summary
    // ------------------------------------------------------------------

    private function buildSummary(Collection $classified): array
    {
        $counted = $classified->reject(fn ($row) => $row['category'] === 'returned');

        $ordersCount     = $counted->count();
        $totalAmount     = $counted->sum(fn ($row) => (float) $row['order']->total);
        $averageAmount   = $ordersCount > 0 ? $totalAmount / $ordersCount : 0.0;

        $prepaid = $counted->reject(fn ($row) => $row['is_cod']);
        $cod     = $counted->filter(fn ($row) => $row['is_cod']);

        $totalLoading  = $classified->sum(fn ($row) => $row['items']->sum('quantity'));
        $actualLoading = $classified->sum(fn ($row) => $row['items']->sum('delivered_quantity'));

        return [
            'orders_count'        => $ordersCount,
            'postponed_count'     => $classified->where('category', 'postponed')->count(),
            'returned_count'      => $classified->where('category', 'returned')->count(),
            'total_amount'        => round($totalAmount, 2),
            'average_amount'      => round($averageAmount, 2),
            'prepaid_count'       => $prepaid->count(),
            'prepaid_total'       => round((float) $prepaid->sum(fn ($row) => $row['order']->total), 2),
            'cod_count'           => $cod->count(),
            'cod_total'           => round((float) $cod->sum(fn ($row) => $row['order']->total), 2),
            'total_loading'       => (int) $totalLoading,
            'actual_loading'      => (int) $actualLoading,
            'loading_percentage'  => $totalLoading > 0 ? round((($actualLoading / $totalLoading) * 100), 1) : 0.0,
        ];
    }

    // ------------------------------------------------------------------
    //  Charts
    // ------------------------------------------------------------------

    private function buildCharts(Collection $classified): array
    {
        $counted = $classified->reject(fn ($row) => $row['category'] === 'returned');

        $byNeighborhood = $counted
            ->groupBy('neighborhood')
            ->map(fn ($rows) => [
                'name'  => $rows->first()['neighborhood'],
                'total' => round($rows->sum(fn ($row) => (float) $row['order']->total), 2),
            ])
            ->sortByDesc('total')
            ->values()
            ->all();

        $prepaid = $counted->reject(fn ($row) => $row['is_cod'])->count();
        $cod     = $counted->filter(fn ($row) => $row['is_cod'])->count();

        $products = $this->aggregateProducts($classified);

        $byPeriod = $counted
            ->groupBy(fn ($row) => $row['order']->delivery_period_id ?? 0)
            ->map(fn ($rows) => [
                'name'  => $rows->first()['order']->deliveryPeriod?->name ?: __('admin.without_period'),
                'count' => $rows->count(),
            ])
            ->values()
            ->all();

        return [
            'by_neighborhood' => $byNeighborhood,
            'payment'         => [
                ['label' => 'prepaid', 'value' => $prepaid],
                ['label' => 'cod',     'value' => $cod],
            ],
            'products' => $products,
            'by_period' => $byPeriod,
        ];
    }

    /**
     * Aggregate loading per product across the whole (classified) set:
     * total_quantity = SUM(quantity), delivered_quantity = SUM(delivered_quantity).
     *
     * @return array<int, array{name: string, total_quantity: int, delivered_quantity: int}>
     */
    private function aggregateProducts(Collection $classified): array
    {
        return $classified
            ->flatMap(fn ($row) => $row['items'])
            ->groupBy('product_id')
            ->map(fn ($items) => [
                'name'              => $items->first()['name'] ?: __('admin.without_period'),
                'total_quantity'    => (int) $items->sum('quantity'),
                'delivered_quantity'=> (int) $items->sum('delivered_quantity'),
            ])
            ->sortByDesc('total_quantity')
            ->values()
            ->all();
    }

    // ------------------------------------------------------------------
    //  Driver cards
    // ------------------------------------------------------------------

    /**
     * Per-driver loading breakdown, including per-product total vs actual.
     *
     * @return array<int, array{id: int|null, name: string, total_loading: int, actual_loading: int, loading_percentage: float, products: array}>
     */
    private function buildDrivers(Collection $classified): array
    {
        return $classified
            ->groupBy(fn ($row) => $row['order']->delivery_id ?: 'unassigned')
            ->map(function ($rows, $key) {
                $first = $rows->first();
                $driver = $first['order']->deliveryUser;
                $total  = $rows->sum(fn ($row) => $row['items']->sum('quantity'));
                $actual = $rows->sum(fn ($row) => $row['items']->sum('delivered_quantity'));

                return [
                    'id'                 => $first['order']->delivery_id,
                    'name'               => $driver?->name ?: __('admin.not_assigned'),
                    'total_loading'      => (int) $total,
                    'actual_loading'     => (int) $actual,
                    'loading_percentage' => $total > 0 ? round((($actual / $total) * 100), 1) : 0.0,
                    'products'           => $this->aggregateProducts($rows),
                ];
            })
            ->values()
            ->all();
    }

    // ------------------------------------------------------------------
    //  Orders table
    // ------------------------------------------------------------------

    /**
     * @return array<int, array{id: int, order_number: string, neighborhood: string, period: string, period_id: int|null, details: array, is_cod: bool, amount: float, status: string, category: string, driver_id: int|null, driver_name: string}>
     */
    private function buildOrders(Collection $classified): array
    {
        return $classified->map(fn ($row, $index) => [
            'seq'           => $index + 1,
            'id'            => (int) $row['order']->id,
            'order_number'  => (string) ($row['order']->order_number ?: $row['order']->id),
            'neighborhood'  => $row['neighborhood'],
            'period'        => $row['order']->deliveryPeriod?->name ?: '-',
            'period_id'     => $row['order']->delivery_period_id,
            'details'       => $row['items']->map(fn ($item) => [
                'name'     => $item['name'],
                'quantity' => $item['quantity'],
            ])->values()->all(),
            'is_cod'        => $row['is_cod'],
            'amount'        => round((float) $row['order']->total, 2),
            'status'        => (string) ($row['order']->status ?? ''),
            'category'      => $row['category'],
            'driver_id'     => $row['order']->delivery_id,
            'driver_name'   => $row['order']->deliveryUser?->name ?: __('admin.not_assigned'),
        ])->values()->all();
    }
}
