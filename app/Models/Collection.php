<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Collection extends Model
{
    protected $fillable = [
        'order_id',
        'delivery_man_id',
        'amount',
        'payment_method',
        'collection_date',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'collection_date' => 'date',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function deliveryMan(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delivery_man_id');
    }
}
