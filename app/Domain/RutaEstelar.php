<?php

namespace App\Domain;

/** Objeto de dominio que devuelve EstrellaAdapter (el resto del sistema no ve XML). */
final class RutaEstelar
{
    public function __construct(
        public readonly Visibilidad $visibilidad,
        public readonly ?int $diasRestantes,
    ) {}
}
