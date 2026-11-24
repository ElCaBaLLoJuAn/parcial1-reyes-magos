<?php

namespace Tests\Feature;

use App\Models\Caravana;
use App\Models\Parada;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CaravanaApiTest extends TestCase
{
    use RefreshDatabase;

    private function jefe(): array
    {
        return $this->tokenDe('JEFE_CARAVANA');
    }

    private function ruta(string $visibilidad, int $dias = 3): string
    {
        return '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:eb="http://belen.beatlech/estrella"><soapenv:Body>'
            ."<eb:ConsultarRutaResponse><eb:visibilidad>{$visibilidad}</eb:visibilidad><eb:diasRestantes>{$dias}</eb:diasRestantes></eb:ConsultarRutaResponse>"
            .'</soapenv:Body></soapenv:Envelope>';
    }

    private function caravana(array $cambios = []): Caravana
    {
        return Caravana::create(array_merge(
            ['rey' => 'MELCHOR', 'ofrenda' => 'ORO', 'origen' => 'Persia', 'camellos' => 5, 'estado' => 'DETENIDA'],
            $cambios,
        ));
    }

    public function test_registra_caravana_detenida_y_la_consulta(): void
    {
        $r = $this->postJson('/api/caravanas', ['rey' => 'MELCHOR', 'ofrenda' => 'ORO', 'origen' => 'Persia', 'camellos' => 5], $this->jefe());
        $r->assertCreated()->assertJsonPath('data.estado', 'DETENIDA');

        $this->getJson('/api/caravanas/'.$r->json('data.id'))->assertOk()->assertJsonPath('data.paradas', []);
    }

    public function test_seguridad_401_y_403(): void
    {
        $this->assertErrorEstandar($this->postJson('/api/caravanas', []), 401, 'TOKEN_AUSENTE');
        $this->assertErrorEstandar($this->postJson('/api/caravanas', [], $this->tokenDe('PAJE')), 403, 'ROL_INSUFICIENTE');
    }

    public function test_entrada_invalida_400_y_no_existe_404(): void
    {
        $r = $this->postJson('/api/caravanas', ['rey' => 'PEDRO', 'ofrenda' => 'ORO', 'origen' => 'Persia', 'camellos' => 13], $this->jefe());
        $this->assertErrorEstandar($r, 400, 'ENTRADA_INVALIDA');
        $this->assertErrorEstandar($this->getJson('/api/caravanas/99'), 404, 'RECURSO_NO_ENCONTRADO');
    }

    public function test_rey_y_ofrenda_unicos_409(): void
    {
        $this->caravana();
        $this->assertErrorEstandar($this->postJson('/api/caravanas', ['rey' => 'MELCHOR', 'ofrenda' => 'MIRRA', 'origen' => 'Saba', 'camellos' => 2], $this->jefe()), 409, 'REY_DUPLICADO');
        $this->assertErrorEstandar($this->postJson('/api/caravanas', ['rey' => 'GASPAR', 'ofrenda' => 'ORO', 'origen' => 'Saba', 'camellos' => 2], $this->jefe()), 409, 'OFRENDA_DUPLICADA');
    }

    public function test_regla_1_transicion_invalida_409(): void
    {
        $c = $this->caravana();
        $this->assertErrorEstandar($this->patchJson("/api/caravanas/{$c->id}/estado", ['estado' => 'OFRENDA_ENTREGADA'], $this->jefe()), 409, 'TRANSICION_INVALIDA');
    }

    public function test_regla_2_estrella_visible_pone_en_camino(): void
    {
        Http::fake(['*' => Http::response($this->ruta('VISIBLE', 4))]);
        $c = $this->caravana();

        $this->patchJson("/api/caravanas/{$c->id}/estado", ['estado' => 'EN_CAMINO'], $this->jefe())
            ->assertOk()->assertJsonPath('data.estado', 'EN_CAMINO')->assertJsonPath('data.diasRestantes', 4);
        Http::assertSent(fn ($req) => str_contains($req->body(), '<eb:origen>Persia</eb:origen>'));
    }

    public function test_regla_2_estrella_nublada_422(): void
    {
        Http::fake(['*' => Http::response($this->ruta('NUBLADA'))]);
        $c = $this->caravana();

        $this->assertErrorEstandar($this->patchJson("/api/caravanas/{$c->id}/estado", ['estado' => 'EN_CAMINO'], $this->jefe()), 422, 'ESTRELLA_NUBLADA');
        $this->assertSame('DETENIDA', $c->refresh()->estado->value);
    }

    public function test_fault_soap_503_y_sigue_detenida(): void
    {
        Http::fake(['*' => Http::response('<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"><soap:Body><soap:Fault><faultcode>soap:Server</faultcode><faultstring>Cielo cubierto</faultstring></soap:Fault></soap:Body></soap:Envelope>', 500)]);
        $c = $this->caravana(['origen' => 'Herodes']);

        $this->assertErrorEstandar($this->patchJson("/api/caravanas/{$c->id}/estado", ['estado' => 'EN_CAMINO'], $this->jefe()), 503, 'SERVICIO_EXTERNO_NO_DISPONIBLE');
        $this->assertSame('DETENIDA', $c->refresh()->estado->value);
        Http::assertSentCount(2);
    }

    public function test_timeout_503(): void
    {
        Http::fake(fn () => throw new ConnectionException('timeout'));
        $c = $this->caravana();

        $this->assertErrorEstandar($this->patchJson("/api/caravanas/{$c->id}/estado", ['estado' => 'EN_CAMINO'], $this->jefe()), 503, 'SERVICIO_EXTERNO_NO_DISPONIBLE');
        $this->assertSame('DETENIDA', $c->refresh()->estado->value);
    }

    public function test_regla_3_en_belen_requiere_paradas_cerradas(): void
    {
        $c = $this->caravana(['estado' => 'EN_CAMINO']);
        $p = Parada::create(['caravana_id' => $c->id, 'ciudad' => 'Jerusalen', 'orden' => 1, 'dias_estancia' => 2, 'estado' => 'PLANIFICADA']);

        $this->assertErrorEstandar($this->patchJson("/api/caravanas/{$c->id}/estado", ['estado' => 'EN_BELEN'], $this->jefe()), 422, 'PARADAS_PENDIENTES');
        $p->update(['estado' => 'CANCELADA']);
        $this->patchJson("/api/caravanas/{$c->id}/estado", ['estado' => 'EN_BELEN'], $this->jefe())->assertOk();
    }

    public function test_put_solo_en_detenida(): void
    {
        $c = $this->caravana(['estado' => 'EN_CAMINO']);
        $r = $this->putJson("/api/caravanas/{$c->id}", ['rey' => 'MELCHOR', 'ofrenda' => 'ORO', 'origen' => 'Persia', 'camellos' => 8], $this->jefe());
        $this->assertErrorEstandar($r, 409, 'CARAVANA_NO_EDITABLE');
    }
}
