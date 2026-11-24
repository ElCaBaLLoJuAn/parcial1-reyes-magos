<?php

namespace App\Domain;

enum Rey: string
{
    case MELCHOR = 'MELCHOR';
    case GASPAR = 'GASPAR';
    case BALTASAR = 'BALTASAR';

    public static function valores(): array
    {
        return array_column(self::cases(), 'value');
    }
}
