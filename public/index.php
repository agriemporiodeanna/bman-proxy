<?php
// public/index.php - Bman SOAP proxy (Render) v3.1 – robust JSON-in-XML
declare(strict_types=1);

// ===== Env =====
$BMAN_HOST   = getenv('BMAN_HOST')   ?: 'emporiodeanna.bman.it';
$BMAN_PORT   = getenv('BMAN_PORT')   ?: '3555';
$BMAN_TOKEN  = getenv('BMAN_TOKEN')  ?: '';
$DEFAULT_M   = getenv('BMAN_METHOD') ?: 'getAnagrafiche';
$PROXY_KEY   = getenv('PROXY_KEY')   ?: '';
$DEFAULT_NS  = getenv('BMAN_NS')     ?: 'http://tempuri.org/';

// ===== Simple auth =====
if ($PROXY_KEY !== '') {
  $k = $_GET['key'] ?? '';
  if (!hash_equals($PROXY_KEY, $k)) {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok'=>false,'error'=>'Unauthorized']); exit;
  }
}

$endpoint = "https://{$BMAN_HOST}:{$BMAN_PORT}/bmanapi.asmx";
$wsdlUrl  = $endpoint.'?WSDL';

function out_json($arr, int $code=200){
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($arr, JSON_UNESCAPED_UNICODE);
  exit;
}

// ===== Tools
if (isset($_GET['wsdl'])) {
  $ch = curl_init($wsdlUrl);
  curl_setopt_array($ch,[
    CURLOPT_RETURNTRANSFER=>true, CURLOPT_CONNECTTIMEOUT=>8, CURLOPT_TIMEOUT=>20,
    CURLOPT_SSL_VERIFYPEER=>false, CURLOPT_SSL_VERIFYHOST=>false
  ]);
  $resp = curl_exec($ch); $err=curl_error($ch);
  if($resp===false) out_json(['ok'=>false,'error'=>"cURL error: $err"], 502);
  header('Content-Type: text/xml; charset=utf-8'); echo $resp; exit;
}
if (isset($_GET['ops'])) {
  $ch = curl_init($wsdlUrl);
  curl_setopt_array($ch,[
    CURLOPT_RETURNTRANSFER=>true, CURLOPT_CONNECTTIMEOUT=>8, CURLOPT_TIMEOUT=>20,
    CURLOPT_SSL_VERIFYPEER=>false, CURLOPT_SSL_VERIFYHOST=>false
  ]);
  $resp = curl_exec($ch); $err=curl_error($ch);
  if($resp===false) out_json(['ok'=>false,'error'=>"cURL error: $err"], 502);
  $xml = @simplexml_load_string($resp);
  if(!$xml) out_json(['ok'=>false,'error'=>'Invalid WSDL']);
  $xml->registerXPathNamespace('wsdl','http://schemas.xmlsoap.org/wsdl/');
  $ops = [];
  foreach($xml->xpath('//wsdl:operation') as $op){ $ops[] = (string)$op['name']; }
  out_json(['ok'=>true,'operations'=>$ops]);
}

// ===== Normal proxy call =====
$method  = $_GET['m'] ?? $DEFAULT_M;
$ns      = $_GET['ns'] ?? $DEFAULT_NS;

$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = min(200, max(1, (int)($_GET['per_page'] ?? 100)));

// getAnagrafiche specific
$filtriParam = $_GET['filtri'] ?? '';
$ordCampo    = $_GET['ordCampo'] ?? '';
$ordDir      = (int)($_GET['ordDir'] ?? 1);
$depositi    = $_GET['depositi'] ?? '';
$dettVar     = isset($_GET['dettVarianti']) ? (($_GET['dettVarianti']=='1'||$_GET['dettVarianti']=='true')?'true':'false') : 'false';

if ($BMAN_TOKEN === '') out_json(['ok'=>false,'error'=>'Missing BMAN_TOKEN env var'], 500);

$soapAction = rtrim($ns,'/').'/'.$method;

function xmlSafe($s){ return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

$body = "";
if (strcasecmp($method,'getAnagrafiche')===0) {
  $filtriJson   = $filtriParam !== '' ? $filtriParam : '[]';
  $depositiJson = $depositi !== '' ? $depositi : '""';
  $body =
    "<chiave>".xmlSafe($BMAN_TOKEN)."</chiave>".
    "<filtri>".xmlSafe($filtriJson)."</filtri>".
    "<ordinamentoCampo>".xmlSafe($ordCampo)."</ordinamentoCampo>".
    "<ordinamentoDirezione>".xmlSafe((string)$ordDir)."</ordinamentoDirezione>".
    "<numeroDiPagina>".xmlSafe((string)$page)."</numeroDiPagina>".
    "<listaDepositi>".xmlSafe($depositiJson)."</listaDepositi>".
    "<dettaglioVarianti>{$dettVar}</dettaglioVarianti>";
} else {
  $body =
    "<token>".xmlSafe($BMAN_TOKEN)."</token>".
    "<Page>".xmlSafe((string)$page)."</Page>".
    "<PageSize>".xmlSafe((string)$perPage)."</PageSize>";
}

$xml = '<?xml version="1.0" encoding="utf-8"?>'
     . '<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" '
     . 'xmlns:xsd="http://www.w3.org/2001/XMLSchema" '
     . 'xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">'
     . '<soap:Body>'
     . "<{$method} xmlns=\"".xmlSafe($ns)."\">"
     . $body
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

if ($resp === false) out_json(['ok'=>false,'error'=>"cURL error: $err"], 502);
if ($code >= 400)    out_json(['ok'=>false,'error'=>"HTTP $code",'raw'=>substr($resp,0,1200)], $code);

// Try normal XML parsing first
libxml_use_internal_errors(true);
$xmlObj = simplexml_load_string($resp);

// Some servers return JSON string inside <getAnagraficheResult> – handle both cases
if($xmlObj === false){
  if (preg_match('~<([a-zA-Z0-9_:]+)Result>(.*?)</\1Result>~s', $resp, $m)) {
    $payload = html_entity_decode($m[2], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $payload = trim($payload);
    if ($payload !== '' && ($payload[0] === '{' || $payload[0] === '[')) {
      $decoded = json_decode($payload, true);
      if (json_last_error() === JSON_ERROR_NONE) {
        out_json(['ok'=>true,'method'=>$method,'ns'=>$ns,'items'=>$decoded]);
      } else {
        out_json(['ok'=>false,'error'=>'JSON decode error','raw'=>substr($payload,0,1000)], 502);
      }
    }
  }
  out_json(['ok'=>false,'error'=>'Invalid XML','raw'=>substr($resp,0,1000)], 502);
}

// XML parsed – attempt to locate *Result node ignoring namespace
$namespaces = $xmlObj->getNamespaces(true);
$bodyNode = $xmlObj->children($namespaces['soap'])->Body ?? null;
if(!$bodyNode) out_json(['ok'=>false,'error'=>'No SOAP Body','raw'=>substr($resp,0,800)], 502);

// Get first element under Body (Response)
$responseNode = null;
foreach($bodyNode->children() as $c){ $responseNode = $c; break; }
if(!$responseNode) out_json(['ok'=>false,'error'=>'No Response node','raw'=>substr($resp,0,800)], 502);

// Try to get <...Result> as text
$resultNode = null;
foreach($responseNode->children() as $c){ $resultNode = $c; break; }
if($resultNode){
  $payload = (string)$resultNode;
  $payload = html_entity_decode($payload, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
  $payload = trim($payload);
  if ($payload !== '' && ($payload[0] === '{' || $payload[0] === '[')) {
    $decoded = json_decode($payload, true);
    if (json_last_error() === JSON_ERROR_NONE) {
      out_json(['ok'=>true,'method'=>$method,'ns'=>$ns,'items'=>$decoded]);
    } else {
      out_json(['ok'=>false,'error'=>'JSON decode error','raw'=>substr($payload,0,1000)], 502);
    }
  }
}

// Fallback to previous generic behavior
$respNode = $bodyNode->children($ns)->{$method.'Response'} ?? null;
if(!$respNode){
  $respNode = $bodyNode->children('http://tempuri.org/')->{$method.'Response'} ?? null;
}
$result   = $respNode ? $respNode->{$method.'Result'} : null;
$json     = json_decode(json_encode($result), true);
$items    = null;
if (is_array($json)) {
  foreach (['Products','ProductList','Articoli','Items','Catalog','Result','Data','data'] as $k) {
    if (isset($json[$k]) && is_array($json[$k])) { $items = $json[$k]; break; }
  }
  if (!$items) {
    foreach ($json as $v) { if (is_array($v) && isset($v[0])) { $items = $v; break; } }
  }
}
out_json(['ok'=>true,'method'=>$method,'ns'=>$ns,'items'=>$items ?: $json]);
