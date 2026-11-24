<?php

namespace App\Domain;

enum EstadoParada: string
{
    case PLANIFICADA = 'PLANIFICADA';
    case CUMPLIDA = 'CUMPLIDA';
    case CANCELADA = 'CANCELADA';

    public static function valores(): array
    {
        return array_column(self::cases(), 'value');
    }
}
