<?php
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);

$WSDL     = 'https://emporiodeanna.bman.it:3555/bmanapi.asmx?WSDL';
$LOCATION = 'https://emporiodeanna.bman.it:3555/bmanapi.asmx';
$URI      = 'http://tempuri.org/';
$TOKEN    = 'UC54Q19JJS4ZATDLEG0OFW07A884AV'; // per test (poi in variabile ENV)

$method   = $_GET['m'] ?? 'GetArticoli';
$page     = max(1, (int)($_GET['page'] ?? 1));
$perPage  = min(200, max(1, (int)($_GET['per_page'] ?? 50)));

$ctx = stream_context_create(['ssl'=>[
  'verify_peer'=>false,'verify_peer_name'=>false,'allow_self_signed'=>true
]]);
try {
  $cli = new SoapClient(null, [
    'location'=>$LOCATION,
    'uri'=>$URI,
    'trace'=>0,'exceptions'=>1,
    'stream_context'=>$ctx,
    'connection_timeout'=>15
  ]);

  $params = [
    'token'    => $TOKEN,
    'Page'     => $page,
    'PageSize' => $perPage
  ];
  $res = $cli->__soapCall($method, [$params]);
  $arr = json_decode(json_encode($res), true);

  $items = null;
  foreach (['Products','ProductList','Articoli','Items','Catalog','Result','Data','data'] as $k)
    if (isset($arr[$k]) && is_array($arr[$k])) { $items = $arr[$k]; break; }

  echo json_encode(['ok'=>true,'items'=>$items ?: $arr], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  http_response_code(502);
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
