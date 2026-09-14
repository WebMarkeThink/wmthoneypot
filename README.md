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

*Sistema → Installa estensioni* → caricare lo zip dalla
[pagina delle release](https://github.com/WebMarkeThink/wmthoneypot/releases), poi abilitare
**Sistema - WMT Honeypot anti-spam**.

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

Per rilasciare: aggiornare versione nel manifest, `CHANGELOG.md` e `changelog.xml`, eseguire la build,
committare `updates.xml`, poi creare il tag `vX.Y.Z`. La CI ricostruisce il pacchetto, verifica che
tag e manifest coincidano e pubblica la release.

## Licenza

GPL-2.0-or-later. Vedi [LICENSE.txt](LICENSE.txt).
