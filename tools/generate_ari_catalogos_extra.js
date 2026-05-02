const fs = require('fs');
const xlsx = require('xlsx');

const specs = {
  pais: '.tmp_sessions/ari_xlsx_extract/xl/embeddings/Hoja_de_c_lculo_de_Microsoft_Excel3.xlsx',
  actividad_economica: '.tmp_sessions/ari_xlsx_extract/xl/embeddings/Hoja_de_c_lculo_de_Microsoft_Excel9.xlsx',
  giro_mercantil: '.tmp_sessions/ari_xlsx_extract/xl/embeddings/Hoja_de_c_lculo_de_Microsoft_Excel10.xlsx',
};

function phpString(value) {
  return `'${String(value).replace(/\\/g, '\\\\').replace(/'/g, "\\'")}'`;
}

const out = [
  '<?php',
  '/**',
  ' * Catalogos oficiales ARI extraidos de instructivo_ari.xlsx.',
  ' */',
  '',
  'if (!isset($ARI_CATALOGOS_EXTRA) || !is_array($ARI_CATALOGOS_EXTRA)) {',
  '    $ARI_CATALOGOS_EXTRA = [];',
  '}',
];

for (const [name, file] of Object.entries(specs)) {
  const wb = xlsx.readFile(file);
  const rows = xlsx.utils.sheet_to_json(wb.Sheets[wb.SheetNames[0]], { header: 1, defval: '' });
  const items = [];
  for (const row of rows) {
    const key = String(row[0] ?? '').trim();
    const value = String(row[1] ?? '').trim().replace(/\s+/g, ' ');
    const validKey = name === 'pais' ? /^[A-Z]{2}$/.test(key) : /^\d+$/.test(key);
    if (validKey && value) items.push([key, value]);
  }
  out.push('', `$ARI_CATALOGOS_EXTRA[${phpString(name)}] = [`);
  for (const [key, value] of items) out.push(`    ${phpString(key)} => ${phpString(value)},`);
  out.push('];');
}

fs.writeFileSync('config/ari_catalogos_extra.php', `${out.join('\n')}\n`, 'utf8');
