'use strict';
const fs = require('node:fs');
const path = require('node:path');
const { execFileSync } = require('node:child_process');

function walk(directory) {
  return fs.readdirSync(directory, { withFileTypes: true }).flatMap(entry => {
    const file = path.join(directory, entry.name);
    return entry.isDirectory() ? walk(file) : [file];
  });
}

const files = walk(path.join(__dirname, '../public/assets/js')).filter(file => file.endsWith('.js'));
for (const file of files) {
  const source = fs.readFileSync(file, 'utf8');
  if (source.includes('<?')) throw new Error(`PHP dentro de JavaScript: ${file}`);
  execFileSync(process.execPath, ['--check', file], { stdio: 'pipe' });
}
console.log(`OK: sintaxis de ${files.length} archivos JavaScript sin PHP incrustado.`);
