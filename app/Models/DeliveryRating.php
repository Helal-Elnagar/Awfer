<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryRating extends BaseModel
{
    protected $fillable = [
        'user_id',
        'order_id',
        'delivery_id',
        'rate',
        'comment',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delivery_id');
    }
}
