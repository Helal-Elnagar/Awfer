<?php

namespace App\Repositories;

use App\Models\Gift;
use App\Models\UserGift;
use Carbon\Carbon;

class GiftRepository
{
    public function getAchievedGifts($productsCount)
    {
        return Gift::where('min_points', '<=', $productsCount)->get();
    }

    public function getNextGift($productsCount)
    {
        return Gift::where('min_points', '>', $productsCount)
                   ->orderBy('min_points')
                   ->first();
    }

    public function userHasGift($userId, $giftId)
    {
        return UserGift::where('user_id', $userId)
                       ->where('gift_id', $giftId)
                       ->exists();
    }

    public function addUserGift($userId, $gift)
    {
        $expiresAt = Carbon::now()->addDays($gift->expires_after_days ?? 30);

        return UserGift::create([
            'user_id' => $userId,
            'gift_id' => $gift->id,
            'coupon_id' => $gift->coupon_id,
            'expires_at' => $expiresAt,
        ]);
    }
}
