<?php

namespace App\Enums;

enum AddressType: string
{
    case HOME = 'home';
    case WORK = 'work';

    /**
     * Get all values
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get label for UI
     */
    public function label(): string
    {
        return match($this) {
            self::HOME => 'المنزل',
            self::WORK => 'العمل',
        };
    }
}
