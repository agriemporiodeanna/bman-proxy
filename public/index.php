<?php
// =========================================
// BMAN PROXY API CONNECTOR - AGRI EMPORIO DEANNA
// =========================================

// CONFIG
$NS  = 'http://cloud.bman.it/';
$KEY = 'c80054646e1ace03d152da42fefd46c1';

// Endpoint SOAP (Bman Cloud)
$BMAN_URL = 'https://emporiodeanna.bman.it:3555/bmanapi.asmx';

// --- util ---
function soap_call($endpoint, $namespace, $method, $xmlBody) {
    $envelope =
        '<?xml version="1.0" encoding="utf-8"?>' .
        '<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" ' .
        'xmlns:xsd="http://www.w3.org/2001/XMLSchema" ' .
        'xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">' .
        '<soap:Body>' . $xmlBody . '</soap:Body>' .
        '</soap:Envelope>';

    $headers = [
        "Content-Type: text/xml; charset=utf-8",
        "SOAPAction: {$namespace}{$method}",
        "Content-Length: " . strlen($envelope)
    ];

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $endpoint,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $envelope,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 12,
        CURLOPT_TIMEOUT        => 25,
    ]);
    $resp = curl_exec($ch);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($err) {
        header('Content-Type: application/json');
        echo json_encode(["ok"=>false,"error"=>"HTTP cURL error: $err"]);
        exit;
    }
    // ritorno XML nativo (il proxy lato FE lo trasformerà in JSON)
    header('Content-Type: application/xml; charset=utf-8');
    echo $resp;
    exit;
}

// --- input ---
$m            = $_GET['m']            ?? null;
$filtri       = $_GET['filtri']       ?? '';   // JSON urlencoded (opzionale)
$ordCampo     = $_GET['ordCampo']     ?? 'ID';
$ordDir       = $_GET['ordDir']       ?? '1';  // 1 ASC, 2 DESC
$page         = $_GET['page']         ?? '1';
$per_page     = $_GET['per_page']     ?? '50';
$dettVarianti = $_GET['dettVarianti'] ?? 'false';

// getDisponibilitaArticolo (singolo deposito)
$IDAnagrafica = $_GET['IDAnagrafica'] ?? '';
$IDDeposito   = $_GET['IDDeposito']   ?? '';
$taglia       = $_GET['taglia']       ?? '';
$colore       = $_GET['colore']       ?? '';
$varianti     = $_GET['varianti']     ?? '';

// getDisponibilitaArticoloDepositi params in JSON: {"codice":"EAN"}
$params = $_GET['params'] ?? null;

if (!$m) {
    header('Content-Type: application/json');
    echo json_encode([
        "ok"=>false,
        "error"=>"Nessun metodo specificato. Usa ?m=getAnagrafiche|getDepositi|getDisponibilitaArticolo|getDisponibilitaArticoloDepositi"
    ]);
    exit;
}

// --- router ---
switch ($m) {

    // Elenco anagrafiche con filtri (paginato)
    case 'getAnagrafiche': {
        // NB: Bman accetta i parametri con questi nomi (compatibili con la tua istanza)
        $body =
            '<getAnagrafiche xmlns="'.$NS.'">'.
                '<chiave>'.$KEY.'</chiave>'.
                '<filtri>'.htmlspecialchars($filtri, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8').'</filtri>'.
                '<ordCampo>'.$ordCampo.'</ordCampo>'.
                '<ordDir>'.$ordDir.'</ordDir>'.
                '<page>'.$page.'</page>'.
                '<per_page>'.$per_page.'</per_page>'.
                '<dettVarianti>'.($dettVarianti ? 'true' : 'false').'</dettVarianti>'.
            '</getAnagrafiche>';

        soap_call($BMAN_URL, $NS, 'getAnagrafiche', $body);
    }

    // Depositi esposti al Web Service
    case 'getDepositi': {
        $body =
            '<getDepositi xmlns="'.$NS.'">'.
                '<chiave>'.$KEY.'</chiave>'.
            '</getDepositi>';

        soap_call($BMAN_URL, $NS, 'getDepositi', $body);
    }

    // Disponibilità per TUTTI i depositi (input: params={"codice":"EAN"})
    case 'getDisponibilitaArticoloDepositi': {
        $p = $params ? json_decode($params, true) : [];
        $codice = $p['codice'] ?? '';
        $body =
            '<getDisponibilitaArticoloDepositi xmlns="'.$NS.'">'.
                '<chiave>'.$KEY.'</chiave>'.
                '<codice>'.htmlspecialchars($codice, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8').'</codice>'.
            '</getDisponibilitaArticoloDepositi>';

        soap_call($BMAN_URL, $NS, 'getDisponibilitaArticoloDepositi', $body);
    }

    // ⚠️ NUOVO: Disponibilità di un articolo per UNO specifico deposito
    // Parametri richiesti: IDAnagrafica, IDDeposito
    // Opzionali: taglia, colore (tipoArt 4), varianti (tipoArt 5 -> "IDVar|IDVal$IDVar2|IDVal2")
    case 'getDisponibilitaArticolo': {
        if ($IDAnagrafica === '' || $IDDeposito === '') {
            header('Content-Type: application/json');
            echo json_encode(["ok"=>false,"error"=>"Parametri mancanti: serve IDAnagrafica e IDDeposito"]);
            exit;
        }
        $body =
            '<getDisponibilitaArticolo xmlns="'.$NS.'">'.
                '<chiave>'.$KEY.'</chiave>'.
                '<IDAnagrafica>'.intval($IDAnagrafica).'</IDAnagrafica>'.
                '<IDDeposito>'.intval($IDDeposito).'</IDDeposito>'.
                '<taglia>'.htmlspecialchars($taglia, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8').'</taglia>'.
                '<colore>'.htmlspecialchars($colore, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8').'</colore>'.
                '<varianti>'.htmlspecialchars($varianti, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8').'</varianti>'.
            '</getDisponibilitaArticolo>';

        soap_call($BMAN_URL, $NS, 'getDisponibilitaArticolo', $body);
    }

    default: {
        header('Content-Type: application/json');
        echo json_encode(["ok"=>false,"error"=>"Metodo non supportato: $m"]);
        exit;
    }
}
