<?php

namespace App\Services;

use App\Adapters\EstrellaAdapter;
use App\Domain\EstadoCaravana;
use App\Domain\EstadoParada;
use App\Domain\Visibilidad;
use App\Exceptions\ApiException;
use App\Exceptions\ServicioExternoNoDisponibleException;
use App\Models\Caravana;
use App\Repositories\CaravanaRepository;
use App\Repositories\ParadaRepository;
use Illuminate\Support\Collection;

/**
 * Reglas de negocio de las caravanas (reglas 1, 2 y 3 del escenario D)
 * y manejo del fallo del servicio de la Estrella de Belén.
 */
class CaravanaService
{
    public function __construct(
        private readonly CaravanaRepository $caravanas,
        private readonly ParadaRepository $paradas,
        private readonly EstrellaAdapter $estrella,
    ) {}

    public function listar(?string $estado): Collection
    {
        return $this->caravanas->listar($estado);
    }

    public function obtener(int $id): Caravana
    {
        return $this->caravanas->buscar($id) ?? throw ApiException::noEncontrado('Caravana', $id);
    }

    public function obtenerConParadas(int $id): Caravana
    {
        return $this->caravanas->buscarConParadas($id) ?? throw ApiException::noEncontrado('Caravana', $id);
    }

    /** Regla 1: toda caravana nace DETENIDA. Rey y ofrenda son únicos (409 si se repiten). */
    public function registrar(array $datos): Caravana
    {
        $this->asegurarUnicos($datos);

        return $this->caravanas->crear([
            'rey' => $datos['rey'],
            'ofrenda' => $datos['ofrenda'],
            'origen' => $datos['origen'],
            'camellos' => $datos['camellos'],
            'estado' => EstadoCaravana::DETENIDA->value,
        ]);
    }

    /** Solo se edita en DETENIDA. */
    public function actualizar(int $id, array $datos): Caravana
    {
        $caravana = $this->obtener($id);
        if ($caravana->estado !== EstadoCaravana::DETENIDA) {
            throw ApiException::conflicto(
                'CARAVANA_NO_EDITABLE',
                "Solo se puede editar una caravana DETENIDA; la de {$caravana->rey->value} está {$caravana->estado->value}",
            );
        }
        $this->asegurarUnicos($datos, $caravana->id);

        return $this->caravanas->actualizar($caravana, [
            'rey' => $datos['rey'],
            'ofrenda' => $datos['ofrenda'],
            'origen' => $datos['origen'],
            'camellos' => $datos['camellos'],
        ]);
    }

    /**
     * Reglas 1, 2 y 3.
     *  1. Transición fuera de la tabla                          -> 409 TRANSICION_INVALIDA
     *  2. A EN_CAMINO: consulta la estrella. NUBLADA            -> 422 ESTRELLA_NUBLADA
     *     Fault o sin respuesta en 3 s                          -> 503 y la caravana sigue DETENIDA
     *  3. A EN_BELEN con alguna parada PLANIFICADA              -> 422 PARADAS_PENDIENTES
     */
    public function cambiarEstado(int $id, string $estado): Caravana
    {
        $caravana = $this->obtener($id);
        $destino = EstadoCaravana::from($estado);

        if (! $caravana->estado->puedePasarA($destino)) {
            throw ApiException::conflicto(
                'TRANSICION_INVALIDA',
                "No se puede pasar de {$caravana->estado->value} a {$destino->value}",
                [[
                    'estadoActual' => $caravana->estado->value,
                    'estadoSolicitado' => $destino->value,
                    'permitidas' => array_map(fn ($e) => $e->value, $caravana->estado->transicionesPermitidas()),
                ]],
            );
        }

        $cambios = ['estado' => $destino->value];

        if ($destino === EstadoCaravana::EN_CAMINO) {
            try {
                $ruta = $this->estrella->consultarRuta($caravana->origen, 'Belen');
            } catch (ServicioExternoNoDisponibleException $e) {
                // Punto de manejo del fallo distribuido: no se toca la caravana (sigue DETENIDA).
                throw new ApiException(503, 'SERVICIO_EXTERNO_NO_DISPONIBLE',
                    "El servicio {$e->servicio} no está disponible; la caravana permanece DETENIDA. Intenta más tarde",
                    [['servicio' => $e->servicio, 'motivo' => $e->motivo, 'intentos' => $e->intentos, 'estadoCaravana' => $caravana->estado->value]],
                );
            }

            if ($ruta->visibilidad === Visibilidad::NUBLADA) {
                throw ApiException::reglaNegocio(
                    'ESTRELLA_NUBLADA',
                    "La estrella está NUBLADA sobre {$caravana->origen}; la caravana no puede avanzar",
                    [['servicio' => EstrellaAdapter::SERVICIO, 'visibilidad' => $ruta->visibilidad->value]],
                );
            }
            $cambios['dias_restantes'] = $ruta->diasRestantes;
        }

        if ($destino === EstadoCaravana::EN_BELEN) {
            $pendientes = $this->paradas->contarConEstado($caravana->id, EstadoParada::PLANIFICADA);
            if ($pendientes > 0) {
                throw ApiException::reglaNegocio(
                    'PARADAS_PENDIENTES',
                    "La caravana aún tiene {$pendientes} parada(s) PLANIFICADA(s); deben estar CUMPLIDA o CANCELADA",
                    [['paradasPlanificadas' => $pendientes]],
                );
            }
            $cambios['dias_restantes'] = 0;
        }

        return $this->caravanas->actualizar($caravana, $cambios);
    }

    private function asegurarUnicos(array $datos, ?int $excluirId = null): void
    {
        if ($this->caravanas->existeCon('rey', $datos['rey'], $excluirId)) {
            throw ApiException::conflicto('REY_DUPLICADO', "{$datos['rey']} ya tiene una caravana registrada");
        }
        if ($this->caravanas->existeCon('ofrenda', $datos['ofrenda'], $excluirId)) {
            throw ApiException::conflicto('OFRENDA_DUPLICADA', "La ofrenda {$datos['ofrenda']} ya la lleva otra caravana");
        }
    }
}
