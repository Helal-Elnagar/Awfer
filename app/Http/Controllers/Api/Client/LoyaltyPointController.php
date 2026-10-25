<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Http\Resources\LoyaltyPointResource;
use App\Http\Resources\TransactionResource;
use App\Models\Transaction;
use App\Traits\ResponseTrait;

class LoyaltyPointController extends Controller
{
    use ResponseTrait;

    public function loyalityPoints()
    {
        $user = auth()->user();

        $type = request()->type;
        $loyaltyTypes = ['loyalty_reward', 'loyalty_spent'];

        $transactions = Transaction::where('user_id', $user->id)
            ->when($type, function ($query) use ($type) {
                $query->where('type', $type);
            }, function ($query) use ($loyaltyTypes) {
                $query->whereIn('type', $loyaltyTypes);
            })
            ->latest()
            ->get();
        $appInfo = \App\Models\SiteSetting::pluck('value', 'key')->toArray();
        $settings = \App\Services\SettingService::appInformations($appInfo);

        // Calculate total points and their SAR value
        $totalPoints = auth()->user()->loyalty_points;
        $pointValue = $settings['loyalty_points_redeem_rate'] ?? 0.1; // Default 0.1 SAR per point
        $pointsValueInSAR = $totalPoints * $pointValue;
        return $this->successOtherData([
            'points' => $user->loyalty_points,
                        'points_value_sar' => round($pointsValueInSAR, 2),

            'transactions' => LoyaltyPointResource::collection($transactions),
        ]);
    }
}
