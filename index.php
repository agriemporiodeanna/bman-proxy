<?php
// =========================================
// BMAN PROXY API CONNECTOR - AGRI EMPORIO DEANNA
// =========================================
// Namespace e chiave WS corretti per la tua istanza
$NS        = 'http://cloud.bman.it/';
$KEY       = 'UC54Q19JJS4ZATDLEG0OFW07A884AV';
$BMAN_URL  = 'https://emporiodeanna.bman.it:3555/bmanapi.asmx';

// --- Opzioni utility ---
$DEBUG     = isset($_GET['debug']);        // ?debug=1 → stampa envelope e risposta
$RAW_XML   = isset($_GET['raw']);          // ?raw=1    → forza output XML grezzo (default)
$TIMEOUT   = 25;

// --- Lettura parametri query comuni ---
$m            = $_GET['m']            ?? null;
$filtri       = $_GET['filtri']       ?? '';      // JSON urlencoded per getAnagrafiche
$ordCampo     = $_GET['ordCampo']     ?? 'ID';
$ordDir       = $_GET['ordDir']       ?? '1';     // 1 ASC, 2 DESC
$page         = $_GET['page']         ?? '1';
$per_page     = $_GET['per_page']     ?? '50';
$dettVarianti = $_GET['dettVarianti'] ?? 'false';

// getDisponibilitaArticolo (singolo deposito)
$IDAnagrafica = $_GET['IDAnagrafica'] ?? '';
$IDDeposito   = $_GET['IDDeposito']   ?? '';
$taglia       = $_GET['taglia']       ?? '';
$colore       = $_GET['colore']       ?? '';
$varianti     = $_GET['varianti']     ?? '';

// getDisponibilitaArticoloDepositi: params={"codice":"EAN"}
$paramsJson   = $_GET['params'] ?? null;

// --- Funzione chiamata SOAP generica ---
function soap_call($endpoint, $namespace, $method, $bodyXml, $timeout, $debug, $rawOut = true) {
    $envelope =
        '<?xml version="1.0" encoding="utf-8"?>' .
        '<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" ' .
        'xmlns:xsd="http://www.w3.org/2001/XMLSchema" ' .
        'xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">' .
        '<soap:Body>' . $bodyXml . '</soap:Body>' .
        '</soap:Envelope>';

    $headers = [
        'Content-Type: text/xml; charset=utf-8',
        'SOAPAction: ' . $namespace . $method,
        'Content-Length: ' . strlen($envelope)
    ];

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $endpoint,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $envelope,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 12,
        CURLOPT_TIMEOUT        => $timeout,
    ]);

    $resp = curl_exec($ch);
    $err  = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($debug) {
        header('Content-Type: text/plain; charset=utf-8');
        echo "SOAPAction: {$namespace}{$method}\nHTTP: {$code}\n\n== REQUEST ==\n{$envelope}\n\n== RESPONSE ==\n{$resp}\n";
        exit;
    }

    if ($err) {
        header('Content-Type: application/json');
        echo json_encode(["ok"=>false,"error"=>"HTTP cURL error: $err","http"=>$code]);
        exit;
    }

    if ($rawOut) {
        header('Content-Type: application/xml; charset=utf-8');
        echo $resp;
        exit;
    }

    // In caso volessi implementare un parser XML→JSON, potresti farlo qui.
    header('Content-Type: application/xml; charset=utf-8');
    echo $resp;
    exit;
}

// --- Router metodi ---
if (!$m) {
    header('Content-Type: application/json');
    echo json_encode([
        "ok"=>false,
        "error"=>"Nessun metodo specificato",
        "supported"=>["getAnagrafiche","getDepositi","getDisponibilitaArticoloDepositi","getDisponibilitaArticolo"],
        "examples"=>[
            "?m=getDepositi",
            "?m=getAnagrafiche&filtri=%5B%7B%22chiave%22%3A%22ecommerce%22%2C%22operatore%22%3A%22%3D%22%2C%22valore%22%3A%22True%22%7D%5D&ordCampo=ID&ordDir=1&page=1&per_page=50&dettVarianti=false",
            "?m=getDisponibilitaArticoloDepositi&params=%7B%22codice%22%3A%228710439158594%22%7D",
            "?m=getDisponibilitaArticolo&IDAnagrafica=151911&IDDeposito=1&taglia=&colore=&varianti="
        ]
    ]);
    exit;
}

switch ($m) {
    // =========================
    // 1) getAnagrafiche
    // =========================
    case 'getAnagrafiche': {
        // Campi compatibili con la tua istanza: chiave, filtri, ordCampo, ordDir, page, per_page, dettVarianti
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

        soap_call($BMAN_URL, $NS, 'getAnagrafiche', $body, $TIMEOUT, $DEBUG, true);
        break;
    }

    // =========================
    // 2) getDepositi
    // =========================
    case 'getDepositi': {
        $body =
            '<getDepositi xmlns="'.$NS.'">'.
                '<chiave>'.$KEY.'</chiave>'.
            '</getDepositi>';

        soap_call($BMAN_URL, $NS, 'getDepositi', $body, $TIMEOUT, $DEBUG, true);
        break;
    }

    // =========================
    // 3) getDisponibilitaArticoloDepositi (params={"codice":"EAN"})
    // =========================
    case 'getDisponibilitaArticoloDepositi': {
        $p = $paramsJson ? json_decode($paramsJson, true) : [];
        $codice = $p['codice'] ?? '';

        $body =
            '<getDisponibilitaArticoloDepositi xmlns="'.$NS.'">'.
                '<chiave>'.$KEY.'</chiave>'.
                '<codice>'.htmlspecialchars($codice, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8').'</codice>'.
            '</getDisponibilitaArticoloDepositi>';

        soap_call($BMAN_URL, $NS, 'getDisponibilitaArticoloDepositi', $body, $TIMEOUT, $DEBUG, true);
        break;
    }

    // =========================
    // 4) getDisponibilitaArticolo (per uno specifico deposito)
    // =========================
    case 'getDisponibilitaArticolo': {
        if ($IDAnagrafica === '' || $IDDeposito === '') {
            header('Content-Type: application/json');
            echo json_encode(["ok"=>false,"error"=>"Parametri mancanti: servono IDAnagrafica e IDDeposito"]);
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

        soap_call($BMAN_URL, $NS, 'getDisponibilitaArticolo', $body, $TIMEOUT, $DEBUG, true);
        break;
    }

    // =========================
    // Default
    // =========================
    default: {
        header('Content-Type: application/json');
        echo json_encode(["ok"=>false,"error"=>"Metodo non supportato: $m"]);
        exit;
    }
}

