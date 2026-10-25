<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GiftCode extends Model
{
    use HasFactory;

    protected $table = 'gift_codes';

    protected $fillable = [
        'gift_id',
        'user_id',
        'gift_code',
        'expires_at',
        'used_at',
        'is_finished',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at'    => 'datetime',
        'is_finished' => 'boolean',
    ];

    public function gift()
    {
        return $this->belongsTo(Gift::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function isExpired()
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function isUsed()
    {
        return !is_null($this->used_at);
    }
}
