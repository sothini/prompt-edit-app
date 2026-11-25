<?php

namespace App\Enums;

enum ImageSize: string
{
    case Square = '1024x1024';
    case Landscape = '1536x1024';
    case Portrait = '1024x1536';

    public function label(): string
    {
        return match ($this) {
            self::Square => 'Square',
            self::Landscape => 'Landscape',
            self::Portrait => 'Portrait',
        };
    }

    public function width(): int
    {
        return match ($this) {
            self::Square, self::Portrait => 1024,
            self::Landscape => 1536,
        };
    }

    public function height(): int
    {
        return match ($this) {
            self::Square, self::Landscape => 1024,
            self::Portrait => 1536,
        };
    }
}
