<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryPeriod extends Model
{
    protected $fillable = [
        'name',
        'description',
    ];

    public function scopeSearch($query, $searchArray = [])
    {
        $searchArray = is_array($searchArray) ? $searchArray : [];

        $query->where(function ($q) use ($searchArray) {
            foreach ($searchArray as $key => $value) {
                if (is_null($value) || $value === '') continue;

                if ($key === 'order') continue;

                $q->orWhere($key, 'like', "%{$value}%");
            }
        });

        $orderDirection = $searchArray['order'] ?? 'DESC';
        return $query->orderBy('id', $orderDirection);
    }

    /**
     * Get orders that use this delivery period
     */
    public function orders()
    {
        return $this->hasMany(Order::class);
    }
}
