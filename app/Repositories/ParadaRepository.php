<?php

namespace App\Repositories;

use App\Domain\EstadoParada;
use App\Models\Parada;
use Illuminate\Support\Collection;

/** Capa de datos: único lugar que habla con la tabla paradas. */
class ParadaRepository
{
    public function listarDeCaravana(int $caravanaId): Collection
    {
        return Parada::where('caravana_id', $caravanaId)->orderBy('orden')->get();
    }

    public function buscar(int $id): ?Parada
    {
        return Parada::find($id);
    }

    public function existeOrden(int $caravanaId, int $orden): bool
    {
        return Parada::where('caravana_id', $caravanaId)->where('orden', $orden)->exists();
    }

    public function contarConEstado(int $caravanaId, EstadoParada $estado): int
    {
        return Parada::where('caravana_id', $caravanaId)->where('estado', $estado->value)->count();
    }

    /** Parada inmediatamente anterior (n − 1) dentro de la misma caravana. */
    public function anterior(Parada $parada): ?Parada
    {
        return Parada::where('caravana_id', $parada->caravana_id)
            ->where('orden', '<', $parada->orden)
            ->orderByDesc('orden')
            ->first();
    }

    public function crear(array $datos): Parada
    {
        return Parada::create($datos);
    }

    public function actualizar(Parada $parada, array $datos): Parada
    {
        $parada->fill($datos)->save();

        return $parada->refresh();
    }

    public function eliminar(Parada $parada): void
    {
        $parada->delete();
    }
}
