const fs = require('fs');
const path = require('path');
const vm = require('vm');
const parserCandidates = [
  path.resolve(__dirname, '../../php-check/node_modules/php-parser'),
  path.resolve(__dirname, '../../../../php-check/node_modules/php-parser')
];
const parserPath = parserCandidates.find(candidate => fs.existsSync(candidate));
if (!parserPath) throw new Error('php-parser dependency was not found');
const PhpParser = require(parserPath);

const root = path.resolve(__dirname, '..');
const parser = new PhpParser.Engine({
  parser: { php7: true, suppressErrors: false },
  ast: { withPositions: true }
});

const apiSource = fs.readFileSync(path.join(root, 'api.php'), 'utf8');
parser.parseCode(apiSource);

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

[
  'getAdminStudentsByRoom',
  'adminUpdateStudentRecord',
  'adminSetStudentActive',
  'student_roster_changes'
].forEach(required => {
  if (!apiSource.includes(required)) throw new Error(`Missing roster API: ${required}`);
});
['rosterManageTable', 'loadManagedRoster', 'toggleManagedStudent'].forEach(required => {
  if (!html.includes(required)) throw new Error(`Missing roster UI: ${required}`);
});
if (!apiSource.includes('name, level, room, avatar, is_active')) {
  throw new Error('Roster API must return the student avatar');
}
['safeRosterAvatarUrl', 'data-roster-avatar', 'loading="lazy"'].forEach(required => {
  if (!html.includes(required)) throw new Error(`Missing roster avatar support: ${required}`);
});
if (!apiSource.includes("preg_match('/^[1-6]$/', $room)")) {
  throw new Error('Server-side room validation must allow rooms 1-6 only');
}
if (!html.includes('Array.from({length:6}') || !html.includes("errors.push('ห้องต้องเป็น 1-6')")) {
  throw new Error('Client-side room controls must allow rooms 1-6 only');
}
if (/id="rosterManageRoom"[\s\S]*?<\/select>/.exec(html)?.[0].includes('value="7"')) {
  throw new Error('Roster room selector must not offer room 7');
}

console.log(`Syntax OK + Excel round-trip: api.php, google-mock.js, teacher.html (${scripts.length} inline scripts)`);
