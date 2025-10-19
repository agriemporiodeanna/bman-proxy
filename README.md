# Bman Proxy v3 (getAnagrafiche)

Supporta il metodo SOAP **getAnagrafiche** con filtri/sort/paginazione.

## Deploy su Render
- Dockerfile incluso
- Start: `php -S 0.0.0.0:$PORT -t public`
- Env:
  - `BMAN_HOST = emporiodeanna.bman.it`
  - `BMAN_PORT = 3555`
  - `BMAN_TOKEN = <chiave>` (parametro "chiave" delle API Bman)
  - `BMAN_METHOD = getAnagrafiche`
  - `BMAN_NS = http://tempuri.org/` (cambialo se il WSDL indica un targetNamespace diverso)
  - `PROXY_KEY = <chiave_proxy>` (opzionale)

## Endpoints utili
- `/?wsdl=1&key=...` → scarica WSDL
- `/?ops=1&key=...` → elenca le operazioni
- Chiamata prodotti:
  ```
  /?m=getAnagrafiche&
    filtri=[{"chiave":"ecommerce","operatore":"=","valore":"True"}]&
    ordCampo=ID&ordDir=1&
    page=1&
    depositi=&
    dettVarianti=false&
    key=...
  ```
- Puoi passare `ns=` per impostare il namespace della SOAPAction se diverso da `http://tempuri.org/`.
