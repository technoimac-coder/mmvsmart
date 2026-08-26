const fs = require('fs');
const path = require('path');
const vm = require('vm');
const PhpParser = require(path.resolve(__dirname, '../../php-check/node_modules/php-parser'));

const root = path.resolve(__dirname, '..');
const parser = new PhpParser.Engine({
  parser: { php7: true, suppressErrors: false },
  ast: { withPositions: true }
});

parser.parseCode(fs.readFileSync(path.join(root, 'api.php'), 'utf8'));

const html = fs.readFileSync(path.join(root, 'teacher.html'), 'utf8');
const scripts = [...html.matchAll(/<script(?:\s[^>]*)?>([\s\S]*?)<\/script>/gi)]
  .map(match => match[1])
  .filter(source => source.trim());

scripts.forEach((source, index) => {
  new vm.Script(source, { filename: `teacher.html:inline-script-${index + 1}` });
});

new vm.Script(fs.readFileSync(path.join(root, 'google-mock.js'), 'utf8'), {
  filename: 'google-mock.js'
});

const XLSX = require(path.join(root, 'assets/vendor/sheetjs/xlsx.full.min.js'));
const workbook = XLSX.utils.book_new();
XLSX.utils.book_append_sheet(workbook, XLSX.utils.aoa_to_sheet([
  ['เลขที่', 'รหัสนักเรียน', 'ชื่อ-นามสกุล', 'ชั้น', 'ห้อง'],
  ['1', '74001', 'นักเรียน ทดสอบ', 'ม.1', '1']
]), 'รายชื่อนักเรียน');
const roundTrip = XLSX.read(XLSX.write(workbook, { type: 'buffer', bookType: 'xlsx' }), { type: 'buffer' });
const roster = XLSX.utils.sheet_to_json(roundTrip.Sheets[roundTrip.SheetNames[0]], { header: 1, raw: false });
if (roster[1][1] !== '74001') throw new Error('SheetJS roster round-trip failed');

console.log(`Syntax OK + Excel round-trip: api.php, google-mock.js, teacher.html (${scripts.length} inline scripts)`);
