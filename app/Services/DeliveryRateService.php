<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\DeliveryRating;
use App\Models\Order;
use App\Models\User;

class DeliveryRateService
{
    public function create(User $user, array $data): DeliveryRating
    {
        $order = Order::query()->findOrFail($data['order_id']);

        if ($order->user_id !== $user->id) {
            throw new \Exception(__('apis.order_not_belongs_to_user'));
        }

        if ($order->status !== OrderStatus::DELIVERED->value) {
            throw new \Exception(__('apis.order_must_be_delivered_to_rate'));
        }

        if (is_null($order->delivery_id)) {
            throw new \Exception(__('apis.delivery_not_assigned_to_order'));
        }

        if ((int) $data['delivery_id'] !== (int) $order->delivery_id) {
            throw new \Exception(__('apis.delivery_not_assigned_to_this_order'));
        }

        $alreadyRated = DeliveryRating::query()
            ->where('user_id', $user->id)
            ->where('order_id', $order->id)
            ->exists();

        if ($alreadyRated) {
            throw new \Exception(__('apis.delivery_already_rated'));
        }

        return DeliveryRating::query()->create([
            'user_id' => $user->id,
            'order_id' => $order->id,
            'delivery_id' => (int) $data['delivery_id'],
            'rate' => $data['rate'],
            'comment' => $data['comment'] ?? null,
        ]);
    }
}
