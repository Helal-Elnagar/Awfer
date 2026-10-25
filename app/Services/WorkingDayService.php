<?php

namespace App\Services;

use App\Models\WorkingDay;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class WorkingDayService
{
    private const DAYS = [
        'sunday',
        'monday',
        'tuesday',
        'wednesday',
        'thursday',
        'friday',
        'saturday',
    ];

    public function getWorkingDays(): Collection
    {
        $this->ensureSevenDays();

        return WorkingDay::query()->ordered()->get();
    }

    public function updateWorkingDays(array $days): Collection
    {
        $this->ensureSevenDays();

        foreach ($days as $dayData) {
            $day = $dayData['day'] ?? null;

            if (!in_array($day, self::DAYS, true)) {
                continue;
            }

            $isWorking = (bool) ($dayData['is_working'] ?? false);
            $morningFrom = $dayData['morning_from'] ?? ($dayData['morning_begin'] ?? null);
            $morningTo = $dayData['morning_to'] ?? ($dayData['morning_end'] ?? null);
            $eveningFrom = $dayData['evening_from'] ?? ($dayData['evening_begin'] ?? null);
            $eveningTo = $dayData['evening_to'] ?? ($dayData['evening_end'] ?? null);

            WorkingDay::query()
                ->where('day', $day)
                ->update([
                    'is_working' => $isWorking,
                    'morning_from' => $isWorking ? $morningFrom : null,
                    'morning_to' => $isWorking ? $morningTo : null,
                    'evening_from' => $isWorking ? $eveningFrom : null,
                    'evening_to' => $isWorking ? $eveningTo : null,
                ]);
        }

        return $this->getWorkingDays();
    }

    public function isOrderingOpenAt(?Carbon $dateTime = null): bool
    {
        $targetDateTime = $dateTime ?? now();
        $day = Str::lower($targetDateTime->englishDayOfWeek);
        $currentTime = $targetDateTime->format('H:i:s');

        $workingDay = WorkingDay::query()->where('day', $day)->first();

        if (!$workingDay || !$workingDay->is_working) {
            return false;
        }

        $morningOpen = $this->isWithinPeriod($currentTime, $workingDay->morning_from, $workingDay->morning_to);
        $eveningOpen = $this->isWithinPeriod($currentTime, $workingDay->evening_from, $workingDay->evening_to);

        return $morningOpen || $eveningOpen;
    }

    private function isWithinPeriod(string $currentTime, ?string $from, ?string $to): bool
    {
        if (is_null($from) || is_null($to)) {
            return false;
        }

        return $currentTime >= $from && $currentTime <= $to;
    }

    private function ensureSevenDays(): void
    {
        foreach (self::DAYS as $day) {
            WorkingDay::query()->firstOrCreate(
                ['day' => $day],
                [
                    'is_working' => false,
                    'morning_from' => null,
                    'morning_to' => null,
                    'evening_from' => null,
                    'evening_to' => null,
                ]
            );
        }
    }
}
