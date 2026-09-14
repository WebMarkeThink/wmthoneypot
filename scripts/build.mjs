// Costruisce il pacchetto installabile e allinea l'update server.
// La versione vive in un solo posto: il manifest wmthoneypot.xml.
// Uso: node scripts/build.mjs
import { mkdirSync, rmSync, readFileSync, writeFileSync, cpSync } from 'node:fs';
import { createHash } from 'node:crypto';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import { zipDirectory } from './zip.mjs';

const ROOT = join(dirname(fileURLToPath(import.meta.url)), '..');
const ELEMENT = 'wmthoneypot';
const PACKAGE = `plg_system_${ELEMENT}`;

// File che finiscono nel pacchetto: solo ciò che serve al sito di destinazione.
const CONTENTS = [
  `${ELEMENT}.xml`,
  'LICENSE.txt',
  'services',
  'src',
  'language',
];

const manifest = readFileSync(join(ROOT, `${ELEMENT}.xml`), 'utf8');
const version = (manifest.match(/<version>([^<]+)<\/version>/) || [])[1];

if (!version) {
  throw new Error('Versione non trovata nel manifest');
}

const staging = join(ROOT, 'dist', PACKAGE);
rmSync(join(ROOT, 'dist'), { recursive: true, force: true });
mkdirSync(staging, { recursive: true });

for (const entry of CONTENTS) {
  cpSync(join(ROOT, entry), join(staging, entry), { recursive: true });
}

const zipName = `${PACKAGE}-${version}.zip`;
const zipPath = join(ROOT, 'dist', zipName);
const files = zipDirectory(staging, zipPath);
rmSync(staging, { recursive: true, force: true });

const bytes = readFileSync(zipPath);
const sha256 = createHash('sha256').update(bytes).digest('hex');
const sha384 = createHash('sha384').update(bytes).digest('hex');

// L'update server punta sempre al download della release corrispondente al tag.
const downloadUrl = `https://github.com/WebMarkeThink/${ELEMENT}/releases/download/v${version}/${zipName}`;
const updatesPath = join(ROOT, 'updates.xml');
const updates = readFileSync(updatesPath, 'utf8')
  .replace(/(<update>[\s\S]*?<version>)[^<]+(<\/version>)/, `$1${version}$2`)
  .replace(/(<downloadurl[^>]*>)[^<]+(<\/downloadurl>)/, `$1${downloadUrl}$2`)
  .replace(/(<sha256>)[^<]+(<\/sha256>)/, `$1${sha256}$2`)
  .replace(/(<sha384>)[^<]+(<\/sha384>)/, `$1${sha384}$2`);

writeFileSync(updatesPath, updates);

writeFileSync(join(ROOT, 'dist', `${zipName}.sha256`), `${sha256}  ${zipName}\n`);

console.log(`${zipName} — ${files.length} file, ${(bytes.length / 1024).toFixed(1)} kB`);
console.log(`sha256 ${sha256}`);
console.log('updates.xml allineato');
