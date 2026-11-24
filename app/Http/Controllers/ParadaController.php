<?php

namespace App\Http\Controllers;

use App\Http\Requests\AgregarParadaRequest;
use App\Http\Requests\CambiarEstadoParadaRequest;
use App\Http\Resources\ParadaResource;
use App\Services\ParadaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ParadaController extends Controller
{
    public function __construct(private readonly ParadaService $servicio) {}

    public function index(int $caravanaId): AnonymousResourceCollection
    {
        return ParadaResource::collection($this->servicio->listar($caravanaId));
    }

    public function store(AgregarParadaRequest $request, int $caravanaId): JsonResponse
    {
        $parada = $this->servicio->agregar($caravanaId, $request->validated());

        return (new ParadaResource($parada))->response()->setStatusCode(201);
    }

    public function cambiarEstado(CambiarEstadoParadaRequest $request, int $id): ParadaResource
    {
        return new ParadaResource($this->servicio->cambiarEstado($id, $request->validated('estado')));
    }

    public function destroy(int $id): Response
    {
        $this->servicio->eliminar($id);

        return response()->noContent();
    }

    public function itinerario(int $caravanaId): JsonResponse
    {
        $itinerario = $this->servicio->itinerario($caravanaId);
        $itinerario['paradas'] = ParadaResource::collection($itinerario['paradas']);

        return response()->json(['data' => $itinerario]);
    }
}
