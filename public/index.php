<?php
// public/index.php - Bman SOAP proxy (Render)
declare(strict_types=1);

// Environment variables (configure in Render)
$BMAN_HOST   = getenv('BMAN_HOST')   ?: 'emporiodeanna.bman.it';
$BMAN_PORT   = getenv('BMAN_PORT')   ?: '3555';
$BMAN_TOKEN  = getenv('BMAN_TOKEN')  ?: '';                 // <-- set in Render
$DEFAULT_M   = getenv('BMAN_METHOD') ?: 'GetArticoli';      // default method
$PROXY_KEY   = getenv('PROXY_KEY')   ?: '';                 // simple protection key (optional)

// Simple auth
if ($PROXY_KEY !== '') {
  $k = $_GET['key'] ?? '';
  if (!hash_equals($PROXY_KEY, $k)) {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok'=>false,'error'=>'Unauthorized']);
    exit;
  }
}

// Params
$method   = $_GET['m'] ?? $DEFAULT_M;
$page     = max(1, (int)($_GET['page'] ?? 1));
$perPage  = min(200, max(1, (int)($_GET['per_page'] ?? 100)));

header('Content-Type: application/json; charset=utf-8');

if ($BMAN_TOKEN === '') {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'Missing BMAN_TOKEN env var']); exit;
}

$endpoint  = "https://{$BMAN_HOST}:{$BMAN_PORT}/bmanapi.asmx";
$soapAction = "http://tempuri.org/{$method}";

$xml = '<?xml version="1.0" encoding="utf-8"?>'
     . '<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" '
     . 'xmlns:xsd="http://www.w3.org/2001/XMLSchema" '
     . 'xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">'
     . '<soap:Body>'
     . "<{$method} xmlns=\"http://tempuri.org/\">"
     . "<token>{$BMAN_TOKEN}</token>"
     . "<Page>{$page}</Page>"
     . "<PageSize>{$perPage}</PageSize>"
     . "</{$method}>"
     . '</soap:Body>'
     . '</soap:Envelope>';

$ch = curl_init($endpoint);
curl_setopt_array($ch, [
  CURLOPT_POST            => true,
  CURLOPT_HTTPHEADER      => [
    'Content-Type: text/xml; charset=utf-8',
    "SOAPAction: \"$soapAction\""
  ],
  CURLOPT_POSTFIELDS      => $xml,
  CURLOPT_RETURNTRANSFER  => true,
  CURLOPT_CONNECTTIMEOUT  => 10,
  CURLOPT_TIMEOUT         => 25,
  CURLOPT_SSL_VERIFYPEER  => false,
  CURLOPT_SSL_VERIFYHOST  => false,
]);
$resp = curl_exec($ch);
$err  = curl_error($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($resp === false) {
  http_response_code(502);
  echo json_encode(['ok'=>false,'error'=>"cURL error: $err"]); exit;
}
if ($code >= 400) {
  http_response_code($code);
  echo json_encode(['ok'=>false,'error'=>"HTTP $code", 'raw'=>substr($resp,0,1000)]); exit;
}

libxml_use_internal_errors(true);
$xmlObj = simplexml_load_string($resp);
if ($xmlObj === false) {
  echo json_encode(['ok'=>false,'error'=>'Invalid XML', 'raw'=>substr($resp,0,1000)]); exit;
}
$ns = $xmlObj->getNamespaces(true);
$body = $xmlObj->children($ns['soap'])->Body ?? null;
if (!$body) { echo json_encode(['ok'=>false,'error'=>'No SOAP Body', 'raw'=>substr($resp,0,1000)]); exit; }

$respNode = $body->children('http://tempuri.org/')->{$method.'Response'} ?? null;
$result   = $respNode ? $respNode->{$method.'Result'} : null;
$json = json_decode(json_encode($result), true);

$items = null;
if (is_array($json)) {
  foreach (['Products','ProductList','Articoli','Items','Catalog','Result','Data','data'] as $k) {
    if (isset($json[$k]) && is_array($json[$k])) { $items = $json[$k]; break; }
  }
  if (!$items) {
    foreach ($json as $v) { if (is_array($v) && isset($v[0])) { $items = $v; break; } }
  }
}

echo json_encode([
  'ok'       => true,
  'method'   => $method,
  'page'     => $page,
  'per_page' => $perPage,
  'items'    => $items ?: $json,
], JSON_UNESCAPED_UNICODE);
