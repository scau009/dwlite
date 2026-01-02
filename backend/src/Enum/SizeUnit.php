<?php

namespace App\Enum;

enum SizeUnit: string
{
    case EU = 'EU';
    case US = 'US';
    case UK = 'UK';
    case CM = 'CM';

    public function label(): string
    {
        return match ($this) {
            self::EU => 'EU (欧码)',
            self::US => 'US (美码)',
            self::UK => 'UK (英码)',
            self::CM => 'CM (厘米)',
        };
    }

    public static function values(): array
    {
        return array_map(fn ($case) => $case->value, self::cases());
    }
}
