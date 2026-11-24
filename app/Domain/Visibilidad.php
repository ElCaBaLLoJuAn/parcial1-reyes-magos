<?php

namespace App\Domain;

enum Visibilidad: string
{
    case VISIBLE = 'VISIBLE';
    case NUBLADA = 'NUBLADA';

    public static function valores(): array
    {
        return array_column(self::cases(), 'value');
    }
}
