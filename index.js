import express from "express";
import bodyParser from "body-parser";
import fetch from "node-fetch";

const app = express();
app.use(bodyParser.urlencoded({ extended: true }));
app.use(bodyParser.json());

// SOAP Envelope corretto per getAnagraficheV3
function buildSOAPEnvelope(params) {
  return `
<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
               xmlns:xsd="http://www.w3.org/2001/XMLSchema"
               xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
  <soap:Body>
    <getAnagraficheV3 xmlns="http://cloud.bman.it/">
      <chiave>${params.chiave}</chiave>
      <filtri/>
      <ordinamentoCampo>${params.ordinamentoCampo}</ordinamentoCampo>
      <ordinamentoDirezione>1</ordinamentoDirezione>
      <numeroPagina>1</numeroPagina>
      <listaDepositi/>
      <dettaglioVarianti>false</dettaglioVarianti>
    </getAnagraficheV3>
  </soap:Body>
</soap:Envelope>
  `.trim();
}

// Endpoint pubblico per PHP
app.post("/bman", async (req, res) => {
  const bmanUrl = "https://emporiodeanna.bman.it:3555/bmanapi.asmx";
  const soapEnvelope = buildSOAPEnvelope(req.body);

  try {
    const response = await fetch(bmanUrl, {
      method: "POST",
      headers: {
        "Content-Type": "text/xml; charset=utf-8",
        "SOAPAction": "http://cloud.bman.it/getAnagraficheV3"
      },
      body: soapEnvelope
    });

    const xml = await response.text();

    // Prova ad estrarre JSON dentro al SOAP
    const match = xml.match(/>(\{.*\})</s);
    if (!match) {
      return res.status(500).send(
        "Errore: impossibile estrarre JSON da Bman.\n\n" + xml
      );
    }

    return res.json(JSON.parse(match[1]));

  } catch (err) {
    return res.status(500).send("Errore proxy SOAP: " + err.toString());
  }
});

// Resta vivo su Render
app.get("/", (req, res) => {
  res.send("Bman SOAP Proxy attivo (V3) 🚀");
});

const PORT = process.env.PORT || 3000;
app.listen(PORT, () => {
  console.log("Bman SOAP Proxy attivo su porta " + PORT);
});
