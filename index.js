import express from "express";
import bodyParser from "body-parser";
import fetch from "node-fetch";

const app = express();
app.use(bodyParser.urlencoded({ extended: true }));

// PROXY BMAN
app.post("/bman", async (req, res) => {

  const bmanUrl = "https://emporiodeanna.bman.it:3555/bmanapi.asmx/getAnagrafiche";

  try {
    const response = await fetch(bmanUrl, {
      method: "POST",
      body: new URLSearchParams(req.body)
    });

    const xml = await response.text();

    // Estrai JSON dalla risposta SOAP
    const match = xml.match(/>(\{.*\})</s);
    if (!match) {
      return res.status(500).send("Errore: impossibile estrarre JSON da Bman.\n" + xml);
    }

    return res.json(JSON.parse(match[1]));

  } catch (err) {
    return res.status(500).send("Errore proxy: " + err);
  }
});

// Porta Render
app.listen(process.env.PORT || 3000, () => {
  console.log("Bman proxy attivo!");
});
