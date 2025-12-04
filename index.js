import express from "express";
import bodyParser from "body-parser";
import fetch from "node-fetch";

const app = express();
app.use(bodyParser.urlencoded({ extended: true }));

// Creazione SOAP Envelope
function buildSOAPEnvelope(method, params) {
  return `
<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
               xmlns:xsd="http://www.w3.org/2001/XMLSchema"
               xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
  <soap:Body>
    <${method} xmlns="http://tempuri.org/">
      <chiave>${params.chiave}</chiave>
      <filtri>${params.filtri}</filtri>
      <ordinamentoCampo>${params.ordinamentoCampo}</ordinamentoCampo>
      <ordinamentoDirezione>${params.ordinamentoDirezione}</ordinamentoDirezione>
      <numeroPagina>${params.numeroPagina}</numeroPagina>
      <listaDepositi>${params.listaDepositi}</listaDepositi>
      <dettaglioVarianti>${params.dettaglioVarianti}</dettaglioVarianti>
    </${method}>
  </soap:Body>
</soap:Envelope>
  `.trim();
}

app.post("/bman", async (req, res) => {

  const method = "getAnagrafiche"; // Metodo SOAP
  const bmanUrl = "https://emporiodeanna.bman.it:3555/bmanapi.asmx";

  const soapEnvelope = buildSOAPEnvelope(method, req.body);

  try {
    const response = await fetch(bmanUrl, {
      method: "POST",
      headers: {
        "Content-Type": "text/xml; charset=utf-8",
        "SOAPAction": `http://tempuri.org/${method}`
      },
      body: soapEnvelope
    });

    const xml = await response.text();

    // Estrai JSON dal SOAP
    const match = xml.match(/>(\{.*\})</s);
    if (!match) {
      return res.status(500).send("Errore: impossibile estrarre JSON da Bman.\n\n" + xml);
    }

    return res.json(JSON.parse(match[1]));

  } catch (err) {
    return res.status(500).send("Errore proxy SOAP: " + err.toString());
  }
});

// Avvio server
app.listen(process.env.PORT || 3000, () => {
  console.log("Bman SOAP proxy attivo!");
});
