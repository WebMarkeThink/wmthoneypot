# WmtHoneypot

Plugin Joomla che blocca gli invii automatici dei moduli di contatto **senza captcha e senza servizi esterni**.
Nato per un sito di un'associazione di pazienti, dove reCAPTCHA era escluso sia per il trattamento dei dati
verso Google sia perché i captcha sono un ostacolo per chi ha difficoltà motorie o usa tecnologie assistive.

- Nessuna chiamata di rete, nessun cookie, nessun dato personale in più.
- Nessuna dipendenza: due soli eventi, nessuna query al database.
- Accessibile: il campo esca è fuori schermo ma leggibile dagli screen reader, e la sua etichetta
  dice esplicitamente di lasciarlo vuoto.

## Requisiti

**Joomla 6.0 o superiore.** Il plugin usa `SubscriberInterface` con le classi evento concrete
(`PrepareFormEvent`, `ValidateContactEvent`): su Joomla 5 e precedenti, dove i listener legacy erano
ancora ammessi, non funziona. PHP 8.1+.

## Come funziona

1. Quando Joomla prepara il modulo di contatto, il plugin aggiunge due campi:
   un **campo esca**, nascosto via CSS, e un **istante di apertura** firmato in HMAC-SHA256
   con il segreto del sito.
2. All'invio, il plugin rifiuta il messaggio se:
   - il campo esca è stato compilato (lo fanno i bot che riempiono tutto);
   - la firma dell'istante non è valida (campo manomesso o assente);
   - sono passati meno del *tempo minimo di compilazione* (4 secondi di default);
   - il modulo era stato aperto da più della *validità* impostata (24 ore di default), il che
     indica un invio ripetuto da moduli raccolti in precedenza.
3. Se il modulo arriva senza i due campi — per esempio da una pagina messa in cache prima
   dell'installazione — **non blocca nulla**: meglio una segnalazione di spam in più che un
   messaggio vero perso.

## Installazione

*Sistema → Installa estensioni* → scheda **Installa da URL**, incollando il link dell'allegato
dell'ultima [release](https://github.com/WebMarkeThink/wmthoneypot/releases), per esempio:

```
https://github.com/WebMarkeThink/wmthoneypot/releases/download/v1.0.0/plg_system_wmthoneypot-1.0.0.zip
```

Poi abilitare **Sistema - WMT Honeypot anti-spam** in *Sistema → Plugin*.

> **Il file giusto è `plg_system_wmthoneypot-<versione>.zip`**, una decina di KB.
> Non sono installabili né i due *Source code (zip/tar.gz)* che GitHub allega in automatico a ogni
> release, né lo zip del pulsante *Code → Download ZIP*: contengono il repository, non il pacchetto,
> e Joomla risponde che manca il file manifest.

Gli aggiornamenti arrivano dal pannello di Joomla: il manifest dichiara un update server.

## Parametri

| Parametro | Default | A cosa serve |
|---|---|---|
| Nome del campo esca | `wmt_conferma` | Cambiarlo su ogni sito rende inefficaci i bot tarati sul nome predefinito |
| Tempo minimo di compilazione | 4 secondi | Sotto questa soglia l'invio è considerato automatico |
| Validità del modulo | 24 ore | Alzarla se il sito usa una cache delle pagine molto lunga |
| Registra i blocchi | sì | Scrive in `administrator/logs/wmthoneypot.log.php`, senza dati personali |
| Moduli da proteggere | `com_contact.contact` | Un contesto per riga |
| Messaggio di rifiuto | vuoto | Lascia vuoto per il testo predefinito; utile per indicare un contatto alternativo |

## Prova su staging

Prima della messa in produzione, tre verifiche:

1. **Invio normale** compilando il modulo a mano: deve arrivare l'email.
2. **Campo esca compilato** da console del browser
   (`document.querySelector('[name="jform[wmt_conferma]"]').value = 'x'`): deve essere rifiutato.
3. **Invio immediato**, entro pochi secondi dal caricamento: deve essere rifiutato.

Con la registrazione attiva, ogni blocco lascia il motivo nel log.

## Sviluppo

La versione vive **solo** nel manifest `wmthoneypot.xml`: changelog, update server e tag git derivano da lì.

```bash
node scripts/validate.mjs   # XML, file dichiarati, coerenza delle versioni
node scripts/build.mjs      # crea dist/plg_system_wmthoneypot-<versione>.zip e allinea updates.xml
```

### Rilasciare una versione

1. Aggiornare la versione nel manifest `wmthoneypot.xml`, poi `CHANGELOG.md` e `changelog.xml`.
2. `node scripts/build.mjs` — riallinea `hpupdates.xml` con versione, link di download e hash.
3. Committare, poi creare il tag `vX.Y.Z`: la CI verifica che tag e manifest coincidano,
   ricostruisce il pacchetto e pubblica la release.
4. **Caricare `hpupdates.xml` su <https://updates.webmarkethink.it/hpupdates.xml>** (via FTP).
   È l'unico passaggio manuale: l'update server sta su un nostro dominio, non in CI, per non tenere
   credenziali FTP nei secret del repository. Finché non lo si carica, i siti installati non vedono
   il nuovo aggiornamento.

Attenzione a non confondere i due XML del repository:

| File | Cos'è | Dove va |
|---|---|---|
| `wmthoneypot.xml` | **manifest**: descrive l'estensione a Joomla durante l'installazione | dentro il pacchetto |
| `hpupdates.xml` | **update server**: versione disponibile, link di download e hash | su `updates.webmarkethink.it` |

Il sottodominio `updates.webmarkethink.it` ospita gli update server di più estensioni: da qui il prefisso
nel nome del file (`hp` = honeypot). Ogni nuova estensione userà un nome proprio sullo stesso sottodominio.

## Licenza

GPL-2.0-or-later. Vedi [LICENSE.txt](LICENSE.txt).
