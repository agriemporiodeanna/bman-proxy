import express from "express";
import bodyParser from "body-parser";
import fetch from "node-fetch";

const app = express();
app.use(bodyParser.urlencoded({ extended: true }));

app.post("/bman", async (req, res) => {

  const bmanUrl = "https://emporiodeanna.bman.it:3555/bmanapi.asmx";

  // Aggiungiamo il nome del metodo richiesto
  const params = new URLSearchParams({
    nomeMetodo: "getAnagrafiche",
    ...req.body
  });

  try {
    const response = await fetch(bmanUrl, {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: params.toString()
    });

    const xml = await response.text();

    // Estrai JSON dal SOAP
    const match = xml.match(/>(\{.*\})</s);
    if (!match) {
      return res.status(500).send("Errore: impossibile estrarre JSON da Bman.\n\n" + xml);
    }

    return res.json(JSON.parse(match[1]));

  } catch (err) {
    return res.status(500).send("Errore proxy: " + err.toString());
  }
});

app.listen(process.env.PORT || 3000, () => {
  console.log("Bman proxy attivo con metodo corretto!");
});




