<?php

namespace App\Adapters;

use App\Domain\RutaEstelar;
use App\Domain\Visibilidad;
use App\Exceptions\ServicioExternoNoDisponibleException;
use DOMDocument;
use DOMXPath;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Adaptador del servicio SOAP heredado EstrellaDeBelenService (operación ConsultarRuta).
 *
 * Es la ÚNICA clase que conoce XML: arma el sobre SOAP, lo envía y traduce la respuesta
 * al objeto de dominio RutaEstelar. Si el servicio responde con soap:Fault, no responde
 * en N segundos o devuelve algo ilegible, lanza ServicioExternoNoDisponibleException.
 * Extra: reintenta con backoff exponencial (máximo 2 intentos) antes de declararlo caído.
 */
class EstrellaAdapter
{
    public const SERVICIO = 'EstrellaDeBelenService';

    private const NS_ESTRELLA = 'http://belen.beatlech/estrella';

    public function __construct(
        private readonly string $url,
        private readonly int $timeoutSegundos = 3,
        private readonly int $intentos = 2,
        private readonly int $backoffMs = 300,
    ) {}

    public function consultarRuta(string $origen, string $destino = 'Belen'): RutaEstelar
    {
        $sobre = $this->construirSobre($origen, $destino);
        $motivo = 'sin respuesta';

        for ($intento = 1; $intento <= $this->intentos; $intento++) {
            try {
                $respuesta = Http::withHeaders(['SOAPAction' => '"ConsultarRuta"'])
                    ->connectTimeout($this->timeoutSegundos)
                    ->timeout($this->timeoutSegundos)
                    ->withBody($sobre, 'text/xml; charset=utf-8')
                    ->post($this->url);

                return $this->interpretar($respuesta->body(), $respuesta->status());
            } catch (ConnectionException) {
                $motivo = "sin respuesta en {$this->timeoutSegundos} s (timeout)";
            } catch (SoapFaultException $fault) {
                $motivo = $fault->getMessage();
            }

            Log::warning(self::SERVICIO." falló (intento {$intento}/{$this->intentos}): {$motivo}");
            if ($intento < $this->intentos) {
                usleep($this->backoffMs * 1000 * (2 ** ($intento - 1))); // backoff: 300 ms, 600 ms...
            }
        }

        throw new ServicioExternoNoDisponibleException(self::SERVICIO, $motivo, $this->intentos);
    }

    private function construirSobre(string $origen, string $destino): string
    {
        $origen = htmlspecialchars($origen, ENT_XML1);
        $destino = htmlspecialchars($destino, ENT_XML1);

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"
                  xmlns:eb="http://belen.beatlech/estrella">
  <soapenv:Body>
    <eb:ConsultarRutaRequest>
      <eb:origen>{$origen}</eb:origen>
      <eb:destino>{$destino}</eb:destino>
    </eb:ConsultarRutaRequest>
  </soapenv:Body>
</soapenv:Envelope>
XML;
    }

    /** XML -> objeto de dominio. */
    private function interpretar(string $xml, int $status): RutaEstelar
    {
        $dom = new DOMDocument;
        if (trim($xml) === '' || ! @$dom->loadXML($xml, LIBXML_NONET)) {
            throw new SoapFaultException("respuesta no es XML válido (HTTP {$status})");
        }
        $xpath = new DOMXPath($dom);

        $fault = $xpath->query('//*[local-name()="Fault"]')->item(0);
        if ($fault) {
            $faultcode = trim($xpath->evaluate('string(.//*[local-name()="faultcode"])', $fault));
            $faultstring = trim($xpath->evaluate('string(.//*[local-name()="faultstring"])', $fault));

            throw new SoapFaultException(trim("SOAP Fault {$faultcode}: {$faultstring}"));
        }

        $xpath->registerNamespace('eb', self::NS_ESTRELLA);
        $visibilidad = trim($xpath->evaluate('string(//eb:visibilidad)'))
            ?: trim($xpath->evaluate('string(//*[local-name()="visibilidad"])'));
        $dias = trim($xpath->evaluate('string(//eb:diasRestantes)'))
            ?: trim($xpath->evaluate('string(//*[local-name()="diasRestantes"])'));

        $enum = Visibilidad::tryFrom($visibilidad)
            ?? throw new SoapFaultException("visibilidad desconocida '{$visibilidad}' (HTTP {$status})");

        return new RutaEstelar($enum, is_numeric($dias) ? (int) $dias : null);
    }
}
