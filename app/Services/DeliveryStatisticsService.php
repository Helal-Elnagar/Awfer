<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Service responsible for computing the delivery dashboard statistics.
 *
 * Business rules:
 * - daily_orders: All orders assigned to this delivery person today (any status).
 * - today_new_orders: Orders assigned today currently in "New" status.
 * - today_processing_orders: Orders assigned today currently in "Out for Delivery" status.
 * - today_delivered: Orders delivered today (regardless of assignment date).
 * - today_cancelled: Orders cancelled today by this delivery person.
 * - today_remaining: daily_orders - today_delivered - today_cancelled (never negative).
 * - total_required: All orders assigned this month (any status).
 * - total_delivered: Delivered orders assigned this month.
 */
class DeliveryStatisticsService
{
    private int $userId;
    private Carbon $today;
    private Carbon $monthStart;

    private const CACHE_TTL = 300;

    /** Status values as stored in the database (canonical keys + legacy Arabic labels). */
    private const STATUS_NEW          = ['new', 'جديد'];
    private const STATUS_OUT_DELIVERY = ['out-for-delivery', 'جاري التوصيل'];
    private const STATUS_DELIVERED    = ['delivered', 'تم التوصيل'];
    private const STATUS_CANCELLED    = ['cancelled', 'ملغي'];

    /**
     * @param int $userId The authenticated delivery person's ID.
     *
     * @throws \InvalidArgumentException When the user ID is invalid.
     */
    public function __construct(int $userId)
    {
        if ($userId <= 0) {
            throw new \InvalidArgumentException('Invalid user ID provided.');
        }

        $this->userId = $userId;
        $this->today = Carbon::today();
        $this->monthStart = Carbon::now()->startOfMonth();
    }

    /**
     * Build the full dashboard payload.
     *
     * @return array{summary: array, delivery_progress: array, products: array}
     */
    public function getDashboardData(): array
    {
        $cacheKey = $this->cacheKey();

        return Cache::remember($cacheKey, self::CACHE_TTL, function () {
            return $this->buildDashboardData();
        });
    }

    /**
     * Clear cached dashboard data for this user (instance method).
     */
    public function clearCache(): void
    {
        Cache::forget($this->cacheKey());
    }

    /**
     * Clear cached dashboard data for any delivery user (static).
     * Call this from any code path that changes order state.
     */
    public static function clearCacheForUser(int $userId): void
    {
        $dateString = Carbon::today()->toDateString();
        Cache::forget("delivery_dashboard_{$userId}_{$dateString}");
    }

    /**
     * Build the cache key for today's dashboard.
     */
    private function cacheKey(): string
    {
        return "delivery_dashboard_{$this->userId}_" . $this->today->toDateString();
    }

    /**
     * Build the dashboard data directly from the database (no cache).
     *
     * Each section is computed independently so one failure does not break the rest.
     */
    private function buildDashboardData(): array
    {
        $this->validateUserExists();

        // All orders ever assigned to this delivery person, including those
        // where delivery_id was cleared (cancelled orders use cancelled_by_delivery_id).
        $baseQuery = Order::where(function ($q) {
            $q->where('delivery_id', $this->userId)
              ->orWhere('cancelled_by_delivery_id', $this->userId);
        });

        return [
            'summary'           => $this->getSummary($baseQuery),
            'delivery_progress' => $this->getDeliveryProgress($baseQuery),
            'products'          => $this->getProducts(),
        ];
    }

    /**
     * Verify the delivery person exists and is of correct type.
     *
     * @throws \RuntimeException
     */
    private function validateUserExists(): void
    {
        $user = User::find($this->userId);

        if (!$user) {
            throw new \RuntimeException("User #{$this->userId} not found.");
        }

        if ($user->type !== 'delivery') {
            throw new \RuntimeException("User #{$this->userId} is not a delivery person.");
        }
    }

    // ------------------------------------------------------------------
    //  Summary
    // ------------------------------------------------------------------

    /**
     * Compute all summary statistics for today and the current month.
     *
     * @param \Illuminate\Database\Eloquent\Builder $baseQuery Orders assigned to this delivery person.
     * @return array
     */
    private function getSummary($baseQuery): array
    {
        $today = $this->today;
        $monthStart = $this->monthStart;

        // daily_orders: All orders assigned today — including cancelled ones.
        // cancelDeliveryOrder clears delivery_id, so we must also look at
        // cancelled_by_delivery_id to capture orders that were cancelled today
        // but originally assigned to this driver.
        $dailyOrders = Order::where('assigned_date', $today)
            ->where(function ($q) {
                $q->where('delivery_id', $this->userId)
                  ->orWhere('cancelled_by_delivery_id', $this->userId);
            })
            ->count();

        // today_new_orders: Orders assigned today still in "New" status.
        // (cancelled orders have status 'cancelled'/'ملغي', so they are excluded naturally)
        $todayNewOrders = (clone $baseQuery)
            ->whereIn('status', self::STATUS_NEW)
            ->whereDate('assigned_date', $today)
            ->count();

        // today_processing_orders: Orders assigned today currently "Out for Delivery".
        $todayProcessingOrders = (clone $baseQuery)
            ->whereIn('status', self::STATUS_OUT_DELIVERY)
            ->whereDate('assigned_date', $today)
            ->count();

        // today_delivered: Orders delivered today (regardless of assignment date).
        $todayDelivered = Order::where('delivery_id', $this->userId)
            ->whereIn('status', self::STATUS_DELIVERED)
            ->whereDate('delivery_date', $today)
            ->count();

        // today_cancelled: Orders cancelled today by this delivery person.
        // Only count orders that were also assigned today (to keep daily math consistent).
        $todayCancelled = Order::where('cancelled_by_delivery_id', $this->userId)
            ->whereIn('status', self::STATUS_CANCELLED)
            ->whereDate('cancel_date', $today)
            ->whereDate('assigned_date', $today)
            ->count();

        // today_remaining: Orders from today's intake still in the pipeline.
        $todayRemaining = max(0, $dailyOrders - $todayDelivered - $todayCancelled);

        // Collection aggregates: cash collected today, total collected today,
        // and monthly cash collected.
        $collectionAgg = $this->getCollectionAggregates($today, $monthStart);

        // monthly_cartons: Sum of cartons for delivered orders this month.
        $monthlyCartons = (int) Order::where('delivery_id', $this->userId)
            ->whereIn('status', self::STATUS_DELIVERED)
            ->whereDate('delivery_date', '>=', $monthStart)
            ->whereDate('delivery_date', '<=', $today)
            ->sum('cartons_count');

        return [
            'daily_orders'            => (int) $dailyOrders,
            'today_new_orders'        => (int) $todayNewOrders,
            'today_processing_orders' => (int) $todayProcessingOrders,
            'today_delivered'         => (int) $todayDelivered,
            'today_cancelled'         => (int) $todayCancelled,
            'today_remaining'         => (int) $todayRemaining,
            'today_cash_collected'    => money($collectionAgg['today_cash']),
            'today_total_collected'   => money($collectionAgg['today_total']),
            'monthly_cash_collected'  => money($collectionAgg['monthly_cash']),
            'monthly_cartons'         => $monthlyCartons,
        ];
    }

    /**
     * Aggregate collected amounts from delivered orders for today and this month.
     *
     * Reads directly from the orders table — only orders with status "delivered"/"تم التوصيل"
     * (Delivered) are counted. This ensures money is never counted before delivery.
     *
     * @return array{today_cash: float, today_total: float, monthly_cash: float}
     */
    private function getCollectionAggregates(Carbon $today, Carbon $monthStart): array
    {
        $todayStr = $today->toDateString();
        $monthStartStr = $monthStart->toDateString();
        $codPaymentMethodId = 5; // Cash on Delivery

        $result = Order::where('delivery_id', $this->userId)
            ->whereIn('status', self::STATUS_DELIVERED)
            ->selectRaw(
                "SUM(CASE WHEN payment_method_id = ? AND delivery_date = ? THEN total ELSE 0 END) as today_cash",
                [$codPaymentMethodId, $todayStr]
            )
            ->selectRaw(
                "SUM(CASE WHEN delivery_date = ? THEN total ELSE 0 END) as today_total",
                [$todayStr]
            )
            ->selectRaw(
                "SUM(CASE WHEN payment_method_id = ? AND delivery_date >= ? AND delivery_date <= ? THEN total ELSE 0 END) as monthly_cash",
                [$codPaymentMethodId, $monthStartStr, $todayStr]
            )
            ->first();

        return [
            'today_cash'   => (float) ($result->today_cash ?? 0),
            'today_total'  => (float) ($result->today_total ?? 0),
            'monthly_cash' => (float) ($result->monthly_cash ?? 0),
        ];
    }

    // ------------------------------------------------------------------
    //  Delivery Progress
    // ------------------------------------------------------------------

    /**
     * Compute monthly delivery progress (required vs delivered).
     *
     * total_required: All orders assigned this month (any status).
     * total_delivered: Orders assigned this month that are delivered.
     * completion_percentage: total_delivered / total_required * 100, rounded.
     *
     * @param \Illuminate\Database\Eloquent\Builder $baseQuery
     * @return array{total_required: int, total_delivered: int, completion_percentage: int}
     */
    private function getDeliveryProgress($baseQuery): array
    {
        $monthStart = $this->monthStart;
        $today = $this->today;

        // Single query counting all assigned orders and delivered orders this month.
        $progress = (clone $baseQuery)
            ->select(
                DB::raw('COUNT(*) as total_required'),
                DB::raw("SUM(CASE WHEN status IN ('" . implode("','", self::STATUS_DELIVERED) . "') THEN 1 ELSE 0 END) as total_delivered")
            )
            ->whereDate('assigned_date', '>=', $monthStart)
            ->whereDate('assigned_date', '<=', $today)
            ->first();

        $totalRequired = (int) ($progress->total_required ?? 0);
        $totalDelivered = (int) ($progress->total_delivered ?? 0);

        $completionPercentage = $totalRequired > 0
            ? (int) round(($totalDelivered / $totalRequired) * 100)
            : 0;

        return [
            'total_required'        => $totalRequired,
            'total_delivered'       => $totalDelivered,
            'completion_percentage' => $completionPercentage,
        ];
    }

    // ------------------------------------------------------------------
    //  Products
    // ------------------------------------------------------------------

    /**
     * Get per-product breakdown for orders assigned this month.
     *
     * required_quantity  = SUM(order_items.quantity) for all assigned orders.
     * delivered_quantity = SUM(order_items.delivered_quantity) for delivered orders only.
     *
     * Product name is resolved via Spatie HasTranslations to the current locale.
     * Product image uses the existing getImageUrlAttribute() accessor.
     *
     * @return array<int, array{id: int, name: string, image_url: string, required_quantity: int, delivered_quantity: int, remaining_quantity: int, completion_percentage: int}>
     */
    private function getProducts(): array
    {
        $monthStart = $this->monthStart;
        $today = $this->today;

        // Aggregate quantities from order items, joined with orders
        // to filter by delivery person and date range.
        // required_quantity: all items from all assigned orders this month.
        // delivered_quantity: only items from delivered orders.
        $aggregated = DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->where(function ($q) {
                $q->where('orders.delivery_id', $this->userId)
                  ->orWhere('orders.cancelled_by_delivery_id', $this->userId);
            })
            ->whereDate('orders.assigned_date', '>=', $monthStart)
            ->whereDate('orders.assigned_date', '<=', $today)
            ->select(
                'order_items.product_id',
                DB::raw('SUM(order_items.quantity) as total_required'),
                DB::raw("SUM(CASE WHEN orders.status IN ('" . implode("','", self::STATUS_DELIVERED) . "') THEN order_items.delivered_quantity ELSE 0 END) as total_delivered")
            )
            ->groupBy('order_items.product_id')
            ->get();

        if ($aggregated->isEmpty()) {
            return [];
        }

        // Batch-load Product models for localized name and image URL.
        $productIds = $aggregated->pluck('product_id')->unique()->toArray();
        $products = Product::whereIn('id', $productIds)->get()->keyBy('id');

        return $aggregated->map(function ($row) use ($products) {
            $product = $products->get($row->product_id);
            $required = (int) $row->total_required;
            $delivered = (int) $row->total_delivered;
            $remaining = max(0, $required - $delivered);
            $completion = $required > 0 ? (int) round(($delivered / $required) * 100) : 0;

            return [
                'id'                    => (int) $row->product_id,
                'name'                  => $product?->name ?? '',
                'image_url'             => $product?->image_url ?? '',
                'required_quantity'     => $required,
                'delivered_quantity'    => $delivered,
                'remaining_quantity'    => $remaining,
                'completion_percentage' => $completion,
            ];
        })->toArray();
    }

    // ------------------------------------------------------------------
    //  Empty / Fallback Responses
    // ------------------------------------------------------------------

    /**
     * @return array
     */
    private function emptySummary(): array
    {
        return [
            'daily_orders'            => 0,
            'today_new_orders'        => 0,
            'today_processing_orders' => 0,
            'today_delivered'         => 0,
            'today_cancelled'         => 0,
            'today_remaining'         => 0,
            'today_cash_collected'    => 0.0,
            'today_total_collected'   => 0.0,
            'monthly_cash_collected'  => 0.0,
            'monthly_cartons'         => 0,
        ];
    }

    /**
     * @return array{total_required: int, total_delivered: int, completion_percentage: int}
     */
    private function emptyDeliveryProgress(): array
    {
        return [
            'total_required'        => 0,
            'total_delivered'       => 0,
            'completion_percentage' => 0,
        ];
    }
}
