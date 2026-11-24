<?php

namespace App\Domain;

enum Ofrenda: string
{
    case ORO = 'ORO';
    case INCIENSO = 'INCIENSO';
    case MIRRA = 'MIRRA';

    public static function valores(): array
    {
        return array_column(self::cases(), 'value');
    }
}
