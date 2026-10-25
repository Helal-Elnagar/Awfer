<?php

namespace App\Services;

use App\Models\Gift;
use App\Models\GiftCode;
use Carbon\Carbon;
use App\Notifications\NotifyUser;

class GiftService
{
    public function processUserOrder($user)
    {
        $now = now();

        $levels = \App\Models\LevelGift::orderBy('points_required')->get();

        $windows = [];
        foreach ($levels as $level) {
            $gift = Gift::where('level_id', $level->id)->first();
            if (! $gift) {
                continue;
            }

            $windowStart = $gift->created_at ?? $now;
            $windowEnd   = (clone $windowStart)->addMonth();

            $itemsInWindow = \App\Models\OrderItem::whereHas('order', function ($q) use ($user, $windowStart, $windowEnd) {
                    $q->where('user_id', $user->id)->where('status' , 'delivered')
                      ->whereBetween('created_at', [$windowStart, $windowEnd]);
                })
                ->sum('quantity');

            $alreadyIssued = GiftCode::where('user_id', $user->id)
                ->where('gift_id', $gift->id)
                ->exists();

            // Award only within window
            if (!$alreadyIssued && $now->lte($windowEnd) && $itemsInWindow >= (int) $level->points_required) {
                $code = strtoupper(bin2hex(random_bytes(4)));

                $giftCode = GiftCode::create([
                    'user_id'    => $user->id,
                    'gift_id'    => $gift->id,
                    'gift_code'  => $code,
                    'expires_at' => Carbon::now()->addMonth(),
                    'is_finished'=> false,
                ]);

                $user->notify(new NotifyUser([
                    'title' => [
                        'ar' => 'هدية جديدة',
                        'en' => 'New Gift Unlocked',
                    ],
                    'body' => [
                        'ar' => 'لقد حصلت على هدية: ' . ($gift->name ?? '') . '، رمز الاستخدام: ' . $code,
                        'en' => 'You have a new gift: ' . ($gift->name ?? '') . ', code: ' . $code,
                    ],
                    'type' => 'gift',
                    'gift_id' => $gift->id,
                    'gift_code' => $code,
                    'expires_at' => $giftCode->expires_at?->toDateString(),
                ]));
            }

            $windows[] = [
                'level' => $level,
                'gift' => $gift,
                'start' => $windowStart,
                'end' => $windowEnd,
                'items' => (int) $itemsInWindow,
                'expired' => $now->gt($windowEnd) && !$alreadyIssued && ($itemsInWindow < (int) $level->points_required),
                'issued' => (bool) $alreadyIssued,
            ];
        }

        $codes = GiftCode::with('gift')
            ->where('user_id', $user->id)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>=', now());
            })
            ->get();

        $gifts = $codes->map(function ($g) {
            return [
                'gift_id'     => $g->gift_id,
                'gift_name'   => $g->gift->name ?? null,
                'gift_code'   => $g->gift_code,
                'start_date'  => $g->created_at?->toDateString(),
                'expires_at'  => $g->expires_at?->toDateString(),
                'used_at'     => $g->used_at?->toDateTimeString(),
                'is_used'     => (bool) $g->used_at,
                'is_finished' => (bool) $g->is_finished,
                'percentage'  => (float) ($g->gift?->level?->percentage ?? 0),
            ];
        });

        // Progress for the current active window
        $currentWindow = collect($windows)
            ->filter(fn($w) => $now->lte($w['end']))
            ->sortBy(fn($w) => $w['level']->points_required)
            ->first();

        if (!$currentWindow) {
            $itemsCount = 0;
            $remain_items = 0;
            $total_items = 0;
            $progress_percentage = 0;
        } else {
            $itemsCount = (int) $currentWindow['items'];
            $target = (int) $currentWindow['level']->points_required;
            $total_items = $target;
            while ($itemsCount >= $total_items) {
                $next = $levels->first(function ($lvl) use ($total_items) {
                    return (int) $lvl->points_required > $total_items;
                });
                if (!$next) {
                    break;
                }
                $total_items = (int) $next->points_required;
            }
            $remain_items = max(0, $total_items - $itemsCount);
            $progress_percentage = $total_items > 0
                ? min(100, round(($itemsCount / $total_items) * 100, 2))
                : 0;
        }

        return [
            'items_count'         => (int)$itemsCount,
            'remain_items'        => max(0, $remain_items),
            'total_items'         => $total_items,
            'progress_percentage' => $progress_percentage,
            'gifts'               => $gifts,
        ];
    }
}
