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
['adminUpdateTeacherAssignment', 'requireAdminSession()', 'advisory_room = ?, head_level = ?'].forEach(required => {
  if (!apiSource.includes(required)) throw new Error(`Missing secure teacher assignment API: ${required}`);
});
['teacherAssignmentModal', 'openTeacherAssignmentEditor', 'saveTeacherAssignment'].forEach(required => {
  if (!html.includes(required)) throw new Error(`Missing teacher assignment UI: ${required}`);
});
[
  'ensureActivitySchema',
  'adminCreateActivity',
  'adminCreateActivitiesBulk',
  'adminGetActivityReport',
  'getActivityReportForTeacher',
  'adminListActivities',
  'adminSetActivityStatus',
  'getActivitiesForTeacher',
  'getActivityStudents',
  'saveActivityAttendance',
  'activity_attendance',
  'requireTeacherSession',
  'getCurrentTeacherPermissions',
  'canCurrentTeacherAccess',
  'canCurrentTeacherAccessActivityClass',
  'idx_activity_period',
  'academic_year, semester',
  'กรุณาบันทึกสถานะนักเรียนให้ครบทั้งห้อง',
  "['เข้าร่วม', 'ลา (มีใบรับรองแพทย์)', 'ไม่เข้าร่วมกิจกรรม']"
].forEach(required => {
  if (!apiSource.includes(required)) throw new Error(`Missing activity API: ${required}`);
});
[
  'nav-activity',
  'activitySection',
  'adminActivityTable',
  'loadTeacherActivities',
  'loadActivityStudents',
  'saveActivityCheck',
  'loadAdminActivities'
].forEach(required => {
  if (!html.includes(required)) throw new Error(`Missing activity UI: ${required}`);
});
['adminActivityBulkRows', 'addAdminActivityBulkRow', 'saveAdminActivitiesBulk', 'บันทึกกิจกรรมทั้งหมด'].forEach(required => {
  if (!html.includes(required)) throw new Error(`Missing bulk activity UI: ${required}`);
});
['adminActivityReportTable', 'loadAdminActivityReport', 'รายชื่อกิจกรรมทั้งหมด', 'เกณฑ์ผ่านต้องเข้าร่วมอย่างน้อย 80%'].forEach(required => {
  if (!html.includes(required)) throw new Error(`Missing activity report UI: ${required}`);
});
['advisorActivityReportTable', 'loadAdvisorActivityReport', 'รายงานกิจกรรมห้องที่ปรึกษา', 'เช็กและดูรายงานได้เฉพาะห้องนี้'].forEach(required => {
  if (!html.includes(required)) throw new Error(`Missing advisor activity report UI: ${required}`);
});
['quickActivityCheck', 'quickActivityReport', 'openAdvisorActivityReport', 'advisorActivityReportCard'].forEach(required => {
  if (!html.includes(required)) throw new Error(`Missing activity quick menu: ${required}`);
});
['activityAcademicYear', 'activitySemester', 'adminActivityAcademicYear', 'adminActivityReportYear', 'adminActivityReportSemester'].forEach(required => {
  if (!html.includes(required)) throw new Error(`Missing activity period filter: ${required}`);
});
['resolveAcademicPeriod', 'academicPeriodBounds', 'datetime BETWEEN ? AND ?'].forEach(required => {
  if (!apiSource.includes(required)) throw new Error(`Missing global academic period API support: ${required}`);
});
['getCurrentAcademicPeriod', 'adminSetAcademicPeriod', 'current_semester', 'requireAdminSession()'].forEach(required => {
  if (!apiSource.includes(required)) throw new Error(`Missing admin-controlled academic period API: ${required}`);
});
['globalAcademicYear', 'globalSemester', 'changeGlobalAcademicPeriod', 'makudmuang_academic_period'].forEach(required => {
  if (!html.includes(required)) throw new Error(`Missing global academic period UI support: ${required}`);
});
['academicPeriodSaveButton', 'applyAcademicPeriodToUi', 'เฉพาะผู้ดูแลระบบเท่านั้นที่กำหนดปีการศึกษาและภาคเรียนได้'].forEach(required => {
  if (!html.includes(required)) throw new Error(`Missing admin-only academic period UI: ${required}`);
});
const activityApiBlock = apiSource.slice(
  apiSource.indexOf('function saveActivityAttendance'),
  apiSource.indexOf('function adminUpdateTeacherAssignment')
);
const activityUiBlock = html.slice(
  html.indexOf('function renderActivityStudents'),
  html.indexOf('function checkAndLoadAtt')
);
['เข้าร่วม', 'ลา (มีใบรับรองแพทย์)', 'ไม่เข้าร่วมกิจกรรม'].forEach(status => {
  if (!activityApiBlock.includes(status)) throw new Error(`Activity API status is missing: ${status}`);
  if (!activityUiBlock.includes(status)) throw new Error(`Activity UI status is missing: ${status}`);
});
if (activityApiBlock.includes("'มาสาย'") || activityApiBlock.includes("'ไม่เข้าร่วม', 'ลา'")) {
  throw new Error('Legacy activity statuses must not be accepted by the API');
}
if (activityUiBlock.includes("'มาสาย'") || activityUiBlock.includes("'ไม่เข้าร่วม':")) {
  throw new Error('Legacy activity statuses must not appear in the activity UI');
}
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
['getLocalDateValue', 'refreshDateAfterMidnight', 'setInterval(refreshDateAfterMidnight, 60000)'].forEach(required => {
  if (!html.includes(required)) throw new Error(`Missing automatic date rollover support: ${required}`);
});
['ensureDateInSelectedAcademicPeriod', 'return currentAcademicPeriod($pdo)', 'กิจกรรมนี้ไม่ได้อยู่ในภาคเรียนที่เลือก'].forEach(required => {
  if (!apiSource.includes(required)) throw new Error(`Missing selected-term API enforcement: ${required}`);
});
['applyAcademicPeriodDateLimits', "input.min = range.start", "input.max = range.end"].forEach(required => {
  if (!html.includes(required)) throw new Error(`Missing selected-term date controls: ${required}`);
});

console.log(`Syntax OK + Excel round-trip: api.php, google-mock.js, teacher.html (${scripts.length} inline scripts)`);
