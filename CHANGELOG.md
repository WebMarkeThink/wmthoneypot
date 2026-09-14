# Changelog

Formato secondo [Keep a Changelog](https://keepachangelog.com/it/1.1.0/); versionamento [SemVer](https://semver.org/lang/it/).

Per questa estensione: **major** = cambia il requisito minimo di Joomla o PHP; **minor** = nuove funzionalità,
nuovi parametri o nuove stringhe di lingua; **patch** = correzioni e traduzioni.

## 1.0.0 — 2026-09-14

### Aggiunto
- Campo esca fuori schermo sui moduli di contatto, con etichetta che invita a lasciarlo vuoto:
  resta comprensibile per chi usa uno screen reader.
- Istante di apertura firmato con il segreto del sito: blocca gli invii più rapidi del tempo minimo
  e quelli provenienti da moduli raccolti in precedenza.
- Parametri: nome del campo esca, tempo minimo di compilazione, validità del modulo,
  registrazione dei blocchi, elenco dei moduli protetti, messaggio di rifiuto personalizzabile.
- Traduzioni italiano e inglese.

### Note
- Richiede **Joomla 6**: usa `SubscriberInterface` con le classi evento `PrepareFormEvent`
  e `ValidateContactEvent`. Su Joomla 5 e precedenti non funziona.
