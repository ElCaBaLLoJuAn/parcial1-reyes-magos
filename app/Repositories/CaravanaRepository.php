<?php

namespace App\Repositories;

use App\Models\Caravana;
use Illuminate\Support\Collection;

/** Capa de datos: único lugar que habla con la tabla caravanas. */
class CaravanaRepository
{
    public function listar(?string $estado = null): Collection
    {
        return Caravana::query()
            ->when($estado, fn ($q) => $q->where('estado', $estado))
            ->orderBy('id')
            ->get();
    }

    public function buscar(int $id): ?Caravana
    {
        return Caravana::find($id);
    }

    public function buscarConParadas(int $id): ?Caravana
    {
        return Caravana::with('paradas')->find($id);
    }

    public function existeCon(string $campo, string $valor, ?int $excluirId = null): bool
    {
        return Caravana::where($campo, $valor)
            ->when($excluirId, fn ($q) => $q->where('id', '!=', $excluirId))
            ->exists();
    }

    public function crear(array $datos): Caravana
    {
        return Caravana::create($datos);
    }

    public function actualizar(Caravana $caravana, array $datos): Caravana
    {
        $caravana->fill($datos)->save();

        return $caravana->refresh();
    }
}
