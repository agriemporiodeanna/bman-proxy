import express from "express";
import bodyParser from "body-parser";
import fetch from "node-fetch";

const app = express();
app.use(bodyParser.json());

const BMAN_URL = "https://cloud.bman.it:3555/bmanapi.asmx";
const BMAN_KEY = process.env.BMAN_KEY; // <-- CHIAVE PROTETTA DA ENV

if (!BMAN_KEY) {
  console.error("❌ ERRORE: Variabile BMAN_KEY non impostata su Render!");
}

function createSoapEnvelope(filters, page, detail) {
  return `
  <soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
                 xmlns:xsd="http://www.w3.org/2001/XMLSchema"
                 xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
    <soap:Body>
      <getAnagraficheV3 xmlns="http://cloud.bman.it/">
        <chiave>${BMAN_KEY}</chiave>
        <filtri>${filters}</filtri>
        <ordinamentoCampo></ordinamentoCampo>
        <ordinamentoDirezione>1</ordinamentoDirezione>
        <numeroPagina>${page}</numeroPagina>
        <listaDepositi>[]</listaDepositi>
        <dettaglioVarianti>${detail}</dettaglioVarianti>
      </getAnagraficheV3>
    </soap:Body>
  </soap:Envelope>`;
}

app.post("/get-anagrafiche", async (req, res) => {
  try {
    if (!BMAN_KEY) return res.status(500).json({ errore: "BMAN_KEY non configurata" });

    const filtersJSON = req.body.filtri ?? [];
    const page = req.body.page ?? 1;
    const dettaglioVarianti = req.body.varianti ?? false;

    const soapBody = createSoapEnvelope(
      JSON.stringify(filtersJSON),
      page,
      dettaglioVarianti
    );

    const response = await fetch(`${BMAN_URL}?op=getAnagraficheV3`, {
      method: "POST",
      headers: {
        "Content-Type": "text/xml; charset=utf-8",
        "SOAPAction": "http://cloud.bman.it/getAnagraficheV3",
      },
      body: soapBody,
    });

    const xml = await response.text();
    const match = xml.match(/<getAnagraficheV3Result>([\s\S]*?)<\/getAnagraficheV3Result>/);

    if (!match) return res.status(500).json({ errore: "Nessun risultato da Bman", xml });

    if (match[1].startsWith("ERRORE")) return res.status(403).json({ errore: match[1] });

    return res.json(JSON.parse(match[1]));

  } catch (e) {
    res.status(500).json({ errore: e.message });
  }
});

app.get("/", (req, res) => {
  res.send("Bman Proxy Attivo 🚀");
});

const PORT = process.env.PORT || 10000;
app.listen(PORT, () => console.log("Proxy operativo su porta: " + PORT));


