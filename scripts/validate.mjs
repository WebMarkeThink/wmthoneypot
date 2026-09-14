// Controlli che girano a ogni push (e prima di ogni release):
//  - gli XML sono ben formati (controllo di base, senza dipendenze)
//  - i file dichiarati nel manifest esistono davvero
//  - manifest, updates.xml, changelog.xml e CHANGELOG.md dicono la stessa versione
//  - se esiste un tag git, corrisponde alla versione del manifest
// Uso: node scripts/validate.mjs [versione-del-tag]
import { readFileSync, existsSync } from 'node:fs';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = join(dirname(fileURLToPath(import.meta.url)), '..');
const ELEMENT = 'wmthoneypot';
const errors = [];
const read = (f) => readFileSync(join(ROOT, f), 'utf8');

/** Controllo di base sulla buona formazione: tag bilanciati e annidati correttamente. */
function checkXml(file) {
  const xml = read(file).replace(/<\?[\s\S]*?\?>|<!--[\s\S]*?-->|<!\[CDATA\[[\s\S]*?\]\]>/g, '');
  const stack = [];

  for (const [, closing, name, rest] of xml.matchAll(/<(\/?)([A-Za-z_][\w.-]*)((?:[^>"']|"[^"]*"|'[^']*')*)>/g)) {
    if (closing) {
      const open = stack.pop();
      if (open !== name) errors.push(`${file}: </${name}> non chiude <${open ?? 'nulla'}>`);
    } else if (!rest.trimEnd().endsWith('/')) {
      stack.push(name);
    }
  }

  if (stack.length) errors.push(`${file}: tag non chiusi (${stack.join(', ')})`);
}

for (const file of [`${ELEMENT}.xml`, 'hpupdates.xml', 'changelog.xml']) {
  existsSync(join(ROOT, file)) ? checkXml(file) : errors.push(`${file}: mancante`);
}

const manifest = read(`${ELEMENT}.xml`);
const version = (manifest.match(/<version>([^<]+)<\/version>/) || [])[1] ?? '';

if (!/^\d+\.\d+\.\d+$/.test(version)) {
  errors.push(`manifest: versione "${version}" non è in formato SemVer`);
}

// Ogni file o cartella dichiarata nel manifest deve esistere
for (const [, entry] of manifest.matchAll(/<(?:filename|folder)[^>]*>([^<]+)<\/(?:filename|folder)>/g)) {
  if (!existsSync(join(ROOT, entry))) errors.push(`manifest: dichiarato "${entry}", che non esiste`);
}

for (const [, lang] of manifest.matchAll(/<language[^>]*>([^<]+)<\/language>/g)) {
  if (!existsSync(join(ROOT, lang))) errors.push(`manifest: lingua "${lang}" non trovata`);
}

// Coerenza delle versioni fra i file che il cliente legge
const updatesVersion = (read('hpupdates.xml').match(/<update>[\s\S]*?<version>([^<]+)<\/version>/) || [])[1];
const changelogVersion = (read('changelog.xml').match(/<changelog>[\s\S]*?<version>([^<]+)<\/version>/) || [])[1];
const mdVersion = (read('CHANGELOG.md').match(/^## \[?(\d+\.\d+\.\d+)\]?/m) || [])[1];

if (updatesVersion !== version) errors.push(`hpupdates.xml: versione ${updatesVersion}, manifest ${version}`);
if (changelogVersion !== version) errors.push(`changelog.xml: versione ${changelogVersion}, manifest ${version}`);
if (mdVersion !== version) errors.push(`CHANGELOG.md: versione ${mdVersion}, manifest ${version}`);

// L'update server deve puntare alla release di questa versione
if (!read('hpupdates.xml').includes(`/v${version}/plg_system_${ELEMENT}-${version}.zip`)) {
  errors.push('hpupdates.xml: il link di download non corrisponde alla versione');
}

// In release: il tag deve corrispondere al manifest
const tag = (process.argv[2] || '').replace(/^v/, '');

if (tag && tag !== version) {
  errors.push(`tag ${tag} diverso dalla versione del manifest ${version}`);
}

if (errors.length) {
  console.error('Controlli falliti:');
  for (const e of errors) console.error(` - ${e}`);
  process.exit(1);
}

console.log(`Controlli superati — versione ${version}`);
