<?php

namespace App\Http\Controllers;

use App\Http\Requests\CambiarEstadoCaravanaRequest;
use App\Http\Requests\GuardarCaravanaRequest;
use App\Http\Requests\ListarCaravanasRequest;
use App\Http\Resources\CaravanaResource;
use App\Services\CaravanaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** Controlador delgado: valida formato, delega en CaravanaService y arma la respuesta HTTP. */
class CaravanaController extends Controller
{
    public function __construct(private readonly CaravanaService $servicio) {}

    public function index(ListarCaravanasRequest $request): AnonymousResourceCollection
    {
        return CaravanaResource::collection($this->servicio->listar($request->validated('estado')));
    }

    public function store(GuardarCaravanaRequest $request): JsonResponse
    {
        $caravana = $this->servicio->registrar($request->validated());

        return (new CaravanaResource($caravana))->response()->setStatusCode(201)
            ->header('Location', url("/api/caravanas/{$caravana->id}"));
    }

    public function show(int $id): CaravanaResource
    {
        return new CaravanaResource($this->servicio->obtenerConParadas($id));
    }

    public function update(GuardarCaravanaRequest $request, int $id): CaravanaResource
    {
        return new CaravanaResource($this->servicio->actualizar($id, $request->validated()));
    }

    public function cambiarEstado(CambiarEstadoCaravanaRequest $request, int $id): CaravanaResource
    {
        return new CaravanaResource($this->servicio->cambiarEstado($id, $request->validated('estado')));
    }
}
