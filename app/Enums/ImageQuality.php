<?php

namespace App\Enums;

enum ImageQuality: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function description(): string
    {
        return match ($this) {
            self::Low => 'Fastest and cheapest',
            self::Medium => 'Balanced detail and cost',
            self::High => 'Most detail, highest cost',
        };
    }
}
