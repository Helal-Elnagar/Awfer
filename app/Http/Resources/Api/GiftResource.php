<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class GiftResource extends JsonResource
{
    public function toArray($request)
    {
        $userPoints   = $this->user_points ?? 0; 
        $progress     = $this->progress ?? 0; 
        $giftCount    = $this->gift_count ?? 0;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'min_points' => $this->min_points,
            'product_count' => $userPoints,
            'progress' => round($progress, 2),
            'gift_count' => $giftCount,

            'expires_after_days' => $this->expires_after_days,

            
        ];
    }
}
