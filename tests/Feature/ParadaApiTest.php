<?php

namespace Tests\Feature;

use App\Models\Caravana;
use App\Models\Parada;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ParadaApiTest extends TestCase
{
    use RefreshDatabase;

    private Caravana $caravana;

    protected function setUp(): void
    {
        parent::setUp();
        $this->caravana = Caravana::create(['rey' => 'GASPAR', 'ofrenda' => 'INCIENSO', 'origen' => 'India', 'camellos' => 4, 'estado' => 'DETENIDA']);
    }

    private function parada(int $orden, string $estado = 'PLANIFICADA', int $dias = 2): Parada
    {
        return Parada::create(['caravana_id' => $this->caravana->id, 'ciudad' => "Ciudad {$orden}", 'orden' => $orden, 'dias_estancia' => $dias, 'estado' => $estado]);
    }

    public function test_agrega_parada_planificada_201(): void
    {
        $this->postJson("/api/caravanas/{$this->caravana->id}/paradas", ['ciudad' => 'Babilonia', 'orden' => 1, 'diasEstancia' => 3], $this->tokenDe('JEFE_CARAVANA'))
            ->assertCreated()->assertJsonPath('data.estado', 'PLANIFICADA');
    }

    public function test_caravana_inexistente_404_y_orden_duplicado_409(): void
    {
        $jefe = $this->tokenDe('JEFE_CARAVANA');
        $this->assertErrorEstandar($this->postJson('/api/caravanas/999/paradas', ['ciudad' => 'Damasco', 'orden' => 1, 'diasEstancia' => 1], $jefe), 404, 'RECURSO_NO_ENCONTRADO');
        $this->parada(1);
        $this->assertErrorEstandar($this->postJson("/api/caravanas/{$this->caravana->id}/paradas", ['ciudad' => 'Damasco', 'orden' => 1, 'diasEstancia' => 1], $jefe), 409, 'ORDEN_DUPLICADO');
    }

    public function test_validacion_400(): void
    {
        $r = $this->postJson("/api/caravanas/{$this->caravana->id}/paradas", ['ciudad' => '', 'orden' => 0, 'diasEstancia' => 8], $this->tokenDe('JEFE_CARAVANA'));
        $this->assertErrorEstandar($r, 400, 'ENTRADA_INVALIDA');
        $this->assertCount(3, $r->json('detalles'));
    }

    public function test_regla_5_no_agrega_paradas_en_belen(): void
    {
        $this->caravana->update(['estado' => 'EN_BELEN']);
        $r = $this->postJson("/api/caravanas/{$this->caravana->id}/paradas", ['ciudad' => 'Damasco', 'orden' => 1, 'diasEstancia' => 1], $this->tokenDe('JEFE_CARAVANA'));
        $this->assertErrorEstandar($r, 409, 'CARAVANA_CERRADA');
    }

    public function test_lista_paradas_en_orden(): void
    {
        $this->parada(3);
        $this->parada(1);
        $this->parada(2);
        $this->getJson("/api/caravanas/{$this->caravana->id}/paradas")->assertOk()
            ->assertJsonPath('data.0.orden', 1)->assertJsonPath('data.2.orden', 3);
    }

    public function test_regla_4_se_cumplen_en_orden(): void
    {
        $p1 = $this->parada(1);
        $p2 = $this->parada(2);

        $this->assertErrorEstandar($this->patchJson("/api/paradas/{$p2->id}/estado", ['estado' => 'CUMPLIDA']), 409, 'PARADA_FUERA_DE_ORDEN');
        $this->patchJson("/api/paradas/{$p1->id}/estado", ['estado' => 'CUMPLIDA'])->assertOk();
        $this->patchJson("/api/paradas/{$p2->id}/estado", ['estado' => 'CUMPLIDA'])->assertOk();
        $this->assertErrorEstandar($this->patchJson("/api/paradas/{$p2->id}/estado", ['estado' => 'CANCELADA']), 409, 'TRANSICION_INVALIDA');
    }

    public function test_solo_elimina_planificadas(): void
    {
        $jefe = $this->tokenDe('JEFE_CARAVANA');
        $cumplida = $this->parada(1, 'CUMPLIDA');
        $planificada = $this->parada(2);

        $this->assertErrorEstandar($this->deleteJson("/api/paradas/{$cumplida->id}", [], $jefe), 409, 'PARADA_NO_ELIMINABLE');
        $this->deleteJson("/api/paradas/{$planificada->id}", [], $jefe)->assertNoContent();
        $this->assertErrorEstandar($this->deleteJson("/api/paradas/{$planificada->id}", [], $this->tokenDe('PAJE')), 403, 'ROL_INSUFICIENTE');
    }

    public function test_itinerario(): void
    {
        $this->parada(1, 'CUMPLIDA', 3);
        $this->parada(2, 'CANCELADA', 5);
        $this->parada(3, 'PLANIFICADA', 2);

        $this->getJson("/api/caravanas/{$this->caravana->id}/itinerario")->assertOk()
            ->assertJsonPath('data.diasTotalesViaje', 5)
            ->assertJsonPath('data.paradasFaltantes', 1)
            ->assertJsonCount(3, 'data.paradas');
    }
}
