# Bman Proxy (Render)

Proxy leggero che chiama il servizio SOAP `.asmx` di Bman sulla porta 3555 e restituisce JSON via HTTPS (443).
Perfetto per aggirare i limiti di Aruba (porte in uscita bloccate).

## Deploy su Render
1. Crea un nuovo repo su GitHub e carica questa cartella.
2. Su Render: **New Web Service** → collega il repo.
3. Render userà automaticamente il `Dockerfile`.
4. In **Settings → Environment Variables**, aggiungi:
   - `BMAN_HOST = emporiodeanna.bman.it`
   - `BMAN_PORT = 3555`
   - `BMAN_TOKEN = <il_tuo_token_Bman>`
   - `BMAN_METHOD = GetArticoli`  (o quello corretto)
   - `PROXY_KEY = <una_chiave_segreta>` (opzionale ma consigliata)
5. Deploy. Otterrai un URL tipo `https://bman-proxy.onrender.com/`.

## Test rapido
```
https://<NOME-SERVIZIO>.onrender.com/?m=GetArticoli&page=1&per_page=5&key=<PROXY_KEY>
```

## Nota
Se il servizio SOAP usa metodi/parametri diversi (es. `GetProducts`), cambia `BMAN_METHOD` o usa `?m=GetProducts` nella query.


## Novità v2
- `?wsdl=1` per recuperare il WSDL originale.
- `?ops=1` per elencare le operazioni SOAP disponibili.
- `ns=` parametro per impostare il namespace della SOAPAction (default `http://tempuri.org/`).
