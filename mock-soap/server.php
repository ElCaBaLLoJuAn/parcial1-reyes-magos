<?php
/**
 * Mock local de EstrellaDeBelenService (SOAP 1.1) para desarrollo y demo.
 * Levantar:  php -S 127.0.0.1:8088 mock-soap/server.php
 * WSDL:      http://127.0.0.1:8088/estrella?wsdl
 *
 * Comportamiento (según el origen de la caravana):
 *   "Herodes"   -> soap:Fault  faultcode soap:Server  faultstring "Cielo cubierto"  (definido por el docente)
 *   "Desierto"  -> tarda 5 s en responder (para probar el timeout de 3 s)
 *   "Niebla"    -> NUBLADA
 *   otro origen -> VISIBLE con diasRestantes calculado
 */

$ruta = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($ruta !== '/estrella') {
    http_response_code(404);
    echo 'Usa /estrella';
    return;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('Content-Type: text/xml; charset=utf-8');
    readfile(__DIR__.'/estrella.wsdl');
    return;
}

$xml = file_get_contents('php://input');
$dom = new DOMDocument();
if (! @$dom->loadXML($xml)) {
    responderFault('soap:Client', 'XML mal formado');
    return;
}
$xp = new DOMXPath($dom);
$origen = trim($xp->evaluate('string(//*[local-name()="origen"])'));
$destino = trim($xp->evaluate('string(//*[local-name()="destino"])'));
error_log("[mock estrella] ConsultarRuta origen={$origen} destino={$destino}");

if (strcasecmp($origen, 'Herodes') === 0) {
    responderFault('soap:Server', 'Cielo cubierto');
    return;
}
if (strcasecmp($origen, 'Desierto') === 0) {
    sleep(5);
}

$visibilidad = strcasecmp($origen, 'Niebla') === 0 ? 'NUBLADA' : 'VISIBLE';
$dias = (crc32(strtolower($origen)) % 10) + 1;

header('Content-Type: text/xml; charset=utf-8');
echo <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"
                  xmlns:eb="http://belen.beatlech/estrella">
  <soapenv:Body>
    <eb:ConsultarRutaResponse>
      <eb:visibilidad>{$visibilidad}</eb:visibilidad>
      <eb:diasRestantes>{$dias}</eb:diasRestantes>
    </eb:ConsultarRutaResponse>
  </soapenv:Body>
</soapenv:Envelope>
XML;

function responderFault(string $code, string $mensaje): void
{
    http_response_code(500);
    header('Content-Type: text/xml; charset=utf-8');
    echo <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
  <soap:Body>
    <soap:Fault>
      <faultcode>{$code}</faultcode>
      <faultstring>{$mensaje}</faultstring>
    </soap:Fault>
  </soap:Body>
</soap:Envelope>
XML;
}
