<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class WorkingDay extends Model
{
    protected $fillable = [
        'day',
        'is_working',
        'morning_from',
        'morning_to',
        'evening_from',
        'evening_to',
    ];

    protected $casts = [
        'is_working' => 'boolean',
    ];

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderByRaw("FIELD(day, 'sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday')");
    }
}
