<?php

namespace App\Services;

use App\Domain\EstadoParada;
use App\Exceptions\ApiException;
use App\Models\Parada;
use App\Repositories\ParadaRepository;
use Illuminate\Support\Collection;

/**
 * Reglas de negocio del itinerario (reglas 4 y 5 del escenario D).
 */
class ParadaService
{
    public function __construct(
        private readonly ParadaRepository $paradas,
        private readonly CaravanaService $caravanas,
    ) {}

    /** La caravana debe existir (404) y no estar EN_BELEN ni OFRENDA_ENTREGADA (regla 5 -> 409). */
    public function agregar(int $caravanaId, array $datos): Parada
    {
        $caravana = $this->caravanas->obtener($caravanaId);

        if (! $caravana->estado->aceptaParadas()) {
            throw ApiException::conflicto(
                'CARAVANA_CERRADA',
                "No se pueden agregar paradas a una caravana en estado {$caravana->estado->value}",
            );
        }
        if ($this->paradas->existeOrden($caravana->id, $datos['orden'])) {
            throw ApiException::conflicto('ORDEN_DUPLICADO', "La caravana ya tiene una parada con orden {$datos['orden']}");
        }

        return $this->paradas->crear([
            'caravana_id' => $caravana->id,
            'ciudad' => $datos['ciudad'],
            'orden' => $datos['orden'],
            'dias_estancia' => $datos['diasEstancia'],
            'estado' => EstadoParada::PLANIFICADA->value,
        ]);
    }

    public function listar(int $caravanaId): Collection
    {
        $this->caravanas->obtener($caravanaId); // 404 si la caravana no existe

        return $this->paradas->listarDeCaravana($caravanaId);
    }

    public function obtener(int $id): Parada
    {
        return $this->paradas->buscar($id) ?? throw ApiException::noEncontrado('Parada', $id);
    }

    /**
     * Solo una parada PLANIFICADA puede pasar a CUMPLIDA o CANCELADA (otra cosa -> 409).
     * Regla 4: no se marca CUMPLIDA la parada n si la n − 1 sigue PLANIFICADA (409).
     */
    public function cambiarEstado(int $id, string $estado): Parada
    {
        $parada = $this->obtener($id);
        $destino = EstadoParada::from($estado);

        if ($parada->estado !== EstadoParada::PLANIFICADA) {
            throw ApiException::conflicto(
                'TRANSICION_INVALIDA',
                "No se puede pasar de {$parada->estado->value} a {$destino->value}: la parada ya fue cerrada",
            );
        }

        if ($destino === EstadoParada::CUMPLIDA) {
            $anterior = $this->paradas->anterior($parada);
            if ($anterior && $anterior->estado === EstadoParada::PLANIFICADA) {
                throw ApiException::conflicto(
                    'PARADA_FUERA_DE_ORDEN',
                    "No se puede cumplir la parada {$parada->orden} ({$parada->ciudad}): la parada {$anterior->orden} ({$anterior->ciudad}) sigue PLANIFICADA",
                    [['paradaPendienteId' => $anterior->id, 'orden' => $anterior->orden]],
                );
            }
        }

        return $this->paradas->actualizar($parada, ['estado' => $destino->value]);
    }

    /** Solo se elimina una parada PLANIFICADA. */
    public function eliminar(int $id): void
    {
        $parada = $this->obtener($id);
        if ($parada->estado !== EstadoParada::PLANIFICADA) {
            throw ApiException::conflicto('PARADA_NO_ELIMINABLE', "Solo se elimina una parada PLANIFICADA; esta está {$parada->estado->value}");
        }
        $this->paradas->eliminar($parada);
    }

    /** Extra: itinerario con paradas en orden, días totales y paradas que faltan. */
    public function itinerario(int $caravanaId): array
    {
        $caravana = $this->caravanas->obtener($caravanaId);
        $paradas = $this->paradas->listarDeCaravana($caravanaId);
        $activas = $paradas->where('estado', '!=', EstadoParada::CANCELADA);

        return [
            'caravanaId' => $caravana->id,
            'rey' => $caravana->rey->value,
            'estado' => $caravana->estado->value,
            'paradas' => $paradas,
            'diasTotalesViaje' => (int) $activas->sum('dias_estancia'),
            'paradasFaltantes' => $paradas->where('estado', EstadoParada::PLANIFICADA)->count(),
            'diasRestantesSegunEstrella' => $caravana->dias_restantes,
        ];
    }
}
