<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\Order\OrderStatusService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AutoCancelOrders extends Command
{
    protected $signature = 'orders:cancel-unpaid
                            {--dry-run : Show what would be cancelled without actually cancelling}
                            {--force : Bypass config enabled check and run anyway}
                            {--minutes= : Override the pending minutes threshold}
                            {--batch= : Override the batch size}';

    protected $description = 'Automatically cancel unpaid pending orders that have exceeded their payment timeout';

    public function handle(OrderStatusService $orderStatusService): int
    {
        if (!$this->option('force') && !config('orders.auto_cancel.enabled', true)) {
            $this->info('Order auto-cancellation is disabled in configuration.');
            $this->info('Use --force to run anyway, or enable via ORDER_AUTO_CANCEL_ENABLED env var.');
            return self::SUCCESS;
        }

        $dryRun = $this->option('dry-run');
        $minutes = (int) ($this->option('minutes') ?? config('orders.auto_cancel.pending_minutes', 1));
        $batchSize = (int) ($this->option('batch') ?? config('orders.auto_cancel.batch_size', 50));
        $cancelReason = config('orders.auto_cancel.cancel_reason', 'Auto-cancelled due to payment timeout');

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No orders will be cancelled');
        }

        $this->info("Scanning for unpaid pending orders past their {$minutes}-minute deadline...");

        $expiredOrders = Order::where('status', 'pending')
            ->where('payment_status', 'pending')
            ->where('payment_method_id', '!=', 5)
            ->where('created_at', '<', now()->subMinutes($minutes))
            ->orderBy('created_at', 'asc')
            ->limit($batchSize)
            ->get();

        $count = $expiredOrders->count();
        $this->line("Found {$count} expired order(s) to process.");

        if ($count === 0) {
            $this->info('No expired orders found. Exiting.');
            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Order #', 'Payment', 'Created', 'Branch'],
            $expiredOrders->map(fn(Order $o) => [
                $o->id,
                $o->order_number,
                $o->payment_status,
                optional($o->created_at)->format('Y-m-d H:i'),
                $o->branch_id,
            ])
        );

        if ($dryRun) {
            $this->warn("Dry run complete. {$count} order(s) would have been cancelled.");
            Log::info('Auto-cancel dry run completed', [
                'orders_found' => $count,
                'batch_size' => $batchSize,
            ]);
            return self::SUCCESS;
        }

        $cancelled = 0;
        $failed = 0;

        foreach ($expiredOrders as $order) {
            try {
                DB::transaction(function () use ($order, $orderStatusService, $cancelReason) {
                    $orderStatusService->cancelOrder($order, $cancelReason, null);
                });

                $cancelled++;
                $this->line("Cancelled order #{$order->order_number} (ID: {$order->id})");
            } catch (\Exception $e) {
                $failed++;
                Log::error('Failed to auto-cancel order', [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'error' => $e->getMessage(),
                ]);
                $this->error("Failed to cancel order #{$order->order_number}: {$e->getMessage()}");
            }
        }

        $this->info("Auto-cancellation completed: {$cancelled} cancelled, {$failed} failed.");

        $logLevel = config('orders.auto_cancel.log_level', 'info');
        Log::log($logLevel, 'Order auto-cancellation completed', [
            'cancelled' => $cancelled,
            'failed' => $failed,
            'batch_size' => $batchSize,
        ]);

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
