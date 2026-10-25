<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Order Auto-Cancellation Configuration
    |--------------------------------------------------------------------------
    |
    | Configure how long an unpaid order can remain in "pending" status before
    | being automatically cancelled. The orders:auto-cancel command checks
    | for expired orders that have not been paid.
    |
    | Orders using Cash on Delivery (payment_method_id = 5) are excluded from
    | auto-cancellation since payment happens on delivery.
    |
    */

    'auto_cancel' => [
        'enabled' => env('ORDER_AUTO_CANCEL_ENABLED', true),
        'pending_minutes' => env('ORDER_AUTO_CANCEL_PENDING_MINUTES', 1),
        'cancel_reason' => env('ORDER_AUTO_CANCEL_REASON', 'Auto-cancelled due to payment timeout'),
        'notify_user' => env('ORDER_AUTO_CANCEL_NOTIFY', false),
        'log_level' => env('ORDER_AUTO_CANCEL_LOG_LEVEL', 'info'),
        'batch_size' => env('ORDER_AUTO_CANCEL_BATCH_SIZE', 50),
    ],

];
