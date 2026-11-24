<?php

namespace App\Domain;

enum EstadoCaravana: string
{
    case DETENIDA = 'DETENIDA';
    case EN_CAMINO = 'EN_CAMINO';
    case EN_BELEN = 'EN_BELEN';
    case OFRENDA_ENTREGADA = 'OFRENDA_ENTREGADA';

    public static function valores(): array
    {
        return array_column(self::cases(), 'value');
    }

    /** Regla 1: DETENIDA -> EN_CAMINO, EN_CAMINO -> DETENIDA | EN_BELEN, EN_BELEN -> OFRENDA_ENTREGADA. */
    public function transicionesPermitidas(): array
    {
        return match ($this) {
            self::DETENIDA => [self::EN_CAMINO],
            self::EN_CAMINO => [self::DETENIDA, self::EN_BELEN],
            self::EN_BELEN => [self::OFRENDA_ENTREGADA],
            self::OFRENDA_ENTREGADA => [],
        };
    }

    public function puedePasarA(self $destino): bool
    {
        return in_array($destino, $this->transicionesPermitidas(), true);
    }

    /** Regla 5: en estos estados ya no se agregan paradas. */
    public function aceptaParadas(): bool
    {
        return ! in_array($this, [self::EN_BELEN, self::OFRENDA_ENTREGADA], true);
    }
}
