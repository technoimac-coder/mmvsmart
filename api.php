<?php
// api.php - Backend API for Makudmuang Smart Class System

// Set response headers
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');

// Use a server-side session for privileged administration actions.  The
// existing UI session in localStorage is only a convenience and is never
// trusted by the academic-year endpoints below.
session_set_cookie_params([
    'httponly' => true,
    'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    'samesite' => 'Strict',
    'path' => '/'
]);
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/config.php';

// Read JSON input body
$inputRaw = file_get_contents('php://input');
$body = json_decode($inputRaw, true) ?: [];

// Detect Face Scan API Event (doPost)
if (isset($body['event']) && isset($body['secret'])) {
    handleFaceApi($body, $pdo);
    exit;
}

// Normal AJAX request from google-mock.js
$action = $body['action'] ?? null;
$args = $body['args'] ?? [];

if (!$action) {
    echo json_encode(['success' => false, 'message' => 'ไม่พบแอคชันที่ต้องการ'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Route AJAX requests
try {
    if (empty($_SESSION['academic_year_schema_v2_ready'])) {
        ensureAcademicYearSchema($pdo);
        $_SESSION['academic_year_schema_v2_ready'] = true;
    }
    // Release the session file lock before database/report work so parallel
    // AJAX requests from the same browser do not block one another.
    if (!in_array($action, ['processLogin', 'logoutSession'], true)) {
        session_write_close();
    }
    $result = routeAction($action, $args, $pdo);
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}

// Route to corresponding PHP helper function
function routeAction($action, $args, $pdo) {
    switch ($action) {
        case 'getSignatures':
            return getSignatures();
            
        case 'saveSignatures':
            return saveSignatures($args[0]);
            
        case 'setupPermissions':
            return ['success' => true];
            
        case 'canTeacherAccess':
            return canTeacherAccess($args[0], $args[1], $args[2], $pdo);
            
        case 'processLogin':
            return processLogin($args[0], $args[1], $pdo);

        case 'logoutSession':
            return logoutSession();
            
        case 'updateTeacherAvatar':
            return updateTeacherAvatar($args[0], $args[1], $args[2] ?? '', $pdo);
            
        case 'adminUpdateSingleTeacherAvatar':
            return adminUpdateSingleTeacherAvatar($args[0], $args[1], $pdo);
            
        case 'adminRemoveTeacherAvatar':
            return adminRemoveTeacherAvatar($args[0], $pdo);
            
        case 'bulkUploadTeacherAvatars':
            return bulkUploadTeacherAvatars($args[0], $pdo);
            
        case 'getAdminTeacherListWithAvatars':
            return getAdminTeacherListWithAvatars($pdo);

        case 'adminUpdateTeacherAssignment':
            return adminUpdateTeacherAssignment($args[0], $args[1] ?? '', $args[2] ?? '', $pdo);

        case 'adminCreateActivity':
            return adminCreateActivity($args[0] ?? [], $pdo);

        case 'adminCreateActivitiesBulk':
            return adminCreateActivitiesBulk($args[0] ?? [], $pdo);

        case 'adminListActivities':
            return adminListActivities($args[0] ?? '', $args[1] ?? '', $pdo);

        case 'adminSetActivityStatus':
            return adminSetActivityStatus($args[0], $args[1], $pdo);

        case 'adminDeleteActivity':
            return adminDeleteActivity($args[0], $pdo);

        case 'adminGetActivityReport':
            return adminGetActivityReport($args[0], $args[1], $args[2] ?? '', $args[3] ?? '', $pdo);

        case 'getActivityReportForTeacher':
            return getActivityReportForTeacher($args[0], $args[1], $args[2] ?? '', $args[3] ?? '', $args[4] ?? '', $pdo);

        case 'getActivitiesForTeacher':
            return getActivitiesForTeacher($args[0] ?? '', $args[1] ?? '', $args[2] ?? '', $pdo);

        case 'getActivityStudents':
            return getActivityStudents($args[0], $args[1], $args[2], $args[3] ?? '', $pdo);

        case 'getActivityMatrix':
            return getActivityMatrix($args[0], $args[1], $args[2] ?? '', $pdo);

        case 'saveActivityMatrix':
            return saveActivityMatrix($args[0], $args[1], $args[2] ?? [], $args[3] ?? '', $pdo);

        case 'saveActivityAttendance':
            return saveActivityAttendance($args[0], $args[1], $args[2], $args[3] ?? [], $args[4] ?? '', $pdo);
            
        case 'adminUpdateSingleStudentAvatar':
            return adminUpdateSingleStudentAvatar($args[0], $args[1], $pdo);
            
        case 'bulkUploadStudentAvatars':
            return bulkUploadStudentAvatars($args[0], $pdo);
            
        case 'adminAddUser':
            return adminAddUser($args[0], $args[1], $args[2], $args[3] ?? '', $args[4] ?? '', $args[5] ?? null, $pdo);
            
        case 'adminAddStudent':
            return adminAddStudent($args[0], $args[1], $args[2], $args[3], $args[4], $pdo);

        case 'getAdminStudentsByRoom':
            return getAdminStudentsByRoom($args[0], $args[1], $args[2] ?? 'active', $pdo);

        case 'adminUpdateStudentRecord':
            return adminUpdateStudentRecord($args[0], $args[1], $pdo);

        case 'adminBulkMoveStudents':
            return adminBulkMoveStudents($args[0] ?? [], $pdo);

        case 'adminSetStudentActive':
            return adminSetStudentActive($args[0], $args[1], $args[2] ?? '', $pdo);

        case 'getRecentStudentRosterChanges':
            return getRecentStudentRosterChanges($pdo);

        case 'getAcademicYearSetupData':
            return getAcademicYearSetupData($pdo);

        case 'getCurrentAcademicPeriod':
            return getCurrentAcademicPeriod($pdo);

        case 'adminSetAcademicPeriod':
            return adminSetAcademicPeriod($args[0] ?? '', $args[1] ?? '', $pdo);

        case 'applyAcademicYearImport':
            return applyAcademicYearImport($args[0], $args[1], $args[2] ?? [], $pdo);

        case 'listAcademicYearBackups':
            return listAcademicYearBackups($pdo);

        case 'restoreAcademicYearBackup':
            return restoreAcademicYearBackup($args[0], $pdo);
            
        case 'getAdminLogs':
            return getAdminLogs($args[0] ?? '', $args[1] ?? '', $pdo);
            
        case 'resetNewTermData':
            return resetNewTermData($args[0], $args[1] ?? '', $pdo);
            
        case 'uploadGroupVolunteerPhoto':
            return uploadGroupVolunteerPhoto($args[0], $args[1], $args[2], $args[3], $args[4], $args[5], $pdo);
            
        case 'getVolunteerGallery':
            return getVolunteerGallery($args[0], $args[1], $args[2], $args[3], $args[4] ?? '', $args[5] ?? '', $args[6] ?? '', $args[7] ?? '', $pdo);
            
        case 'getFaceArrivalMap':
            return getFaceArrivalMap($args[0], $args[1], $args[2], $pdo);
            
        case 'getStudentsWithAttendance':
            return getStudentsWithAttendance($args[0], $args[1], $args[2], $args[3], $pdo);
            
        case 'saveAttendance':
            return saveAttendance($args[0], $args[1], $args[2], $args[3], $args[4], $pdo);
            
        case 'getReportData':
            return getReportData($args[0], $pdo);

        case 'getAttendanceCalendar':
            return getAttendanceCalendar($args[0] ?? '', $args[1] ?? '', $args[2] ?? '', $args[3] ?? '', (bool)($args[4] ?? false), $pdo);
            
        case 'getIndividualSummary':
            return getIndividualSummary($args[0], $args[1], $args[2], $args[3], $args[4], $pdo);
            
        case 'exportIndividualExcel':
            return exportIndividualExcel($args[0], $args[1], $args[2], $args[3], $args[4], $pdo);
            
        case 'getAllStudentsForDeduct':
            return getAllStudentsForDeduct($pdo);
            
        case 'saveMultipleDeductions':
            return saveMultipleDeductions($args[0], $args[1], $args[2], $args[3], $args[4], $pdo);
            
        case 'getRoomDeductionReport':
            return getRoomDeductionReport($args[0], $args[1], $args[2], $args[3] ?? '', $args[4] ?? '', $pdo);
            
        case 'generatePDFFromHtml':
            return ['success' => true, 'html' => $args[0], 'filename' => $args[1] . '.pdf'];
            
        case 'generateAdvancedPDF':
            return generateAdvancedPDF($args[0], $args[1], $args[2] ?? null, $pdo);
            
        case 'exportOverallExcel':
            return exportOverallExcel($args[0], $args[1], $args[2] ?? null, $pdo);
            
        case 'generateRoomPDF':
            return generateRoomPDF($args[0], $args[1], $args[2], $args[3], $pdo);
            
        case 'generateIndividualPDF':
            return generateIndividualPDF($args[0], $args[1], $args[2], $args[3], $args[4], $pdo);
            
        case 'generateDeductPDF':
            return generateDeductPDF($args[0], $args[1], $args[2], $args[3] ?? '', $args[4] ?? '', $pdo);
            
        case 'getMyLogs':
            return getMyLogs($args[0], $args[1], $args[2] ?? '', $args[3] ?? '', $pdo);
            
        case 'generateRewardPDF':
            return generateRewardPDF($args[0], $args[1], $args[2], $args[3] ?? '', $args[4] ?? '', $pdo);
            
        case 'saveReward':
            return saveReward($args[0], $args[1], $args[2], $args[3], $args[4], $args[5], $args[6], $args[7], $pdo);
            
        case 'getRoomRewardReport':
            return getRoomRewardReport($args[0], $args[1], $args[2], $args[3] ?? '', $args[4] ?? '', $pdo);
            
        case 'getAllRewardsRanked':
            return getAllRewardsRanked($args[0] ?? '', $args[1] ?? '', $pdo);
            
        case 'getStudentHistoryForTeacher':
            return getStudentHistoryForTeacher($args[0], $args[1] ?? '', $args[2] ?? '', $pdo);
            
        case 'getTeachersForClub':
            return getTeachersForClub($pdo);
            
        case 'addClub':
            return addClub($args[0], $args[1], $args[2], $args[3] ?? 'รับทุกห้อง', $pdo);
            
        case 'getAllClubsInfo':
            return getAllClubsInfo($args[0], $args[1], $pdo);
            
        case 'getMyClubs':
            return getMyClubs($args[0], $pdo);
            
        case 'getClubMembers':
            return getClubMembers($args[0], $pdo);
            
        case 'getStudentById':
            return getStudentById($args[0], $pdo);
            
        case 'getStudentClubData':
            return getStudentClubData($args[0], $pdo);
            
        case 'studentSelectClub':
            return studentSelectClub($args[0], $args[1], $pdo);
            
        case 'submitClubApplication':
            return submitClubApplication($args[0], $args[1], $args[2], $args[3], $args[4], $args[5], $pdo);
            
        case 'getStudentsByClub':
            return getStudentsByClub($args[0], $pdo);
            
        case 'saveClubAttendance':
            return saveClubAttendance($args[0], $args[1], $args[2], $pdo);
            
        case 'getClubAttendanceReport':
            return getClubAttendanceReport($args[0], $args[1] ?? '', $args[2] ?? '', $pdo);
            
        case 'getStudentsWithoutClub':
            return getStudentsWithoutClub($pdo);
            
        case 'getAdminMembersByClub':
            return getAdminMembersByClub($args[0], $pdo);
            
        case 'getAdminMembersByRoom':
            return getAdminMembersByRoom($args[0], $args[1], $pdo);
            
        case 'addClubAdminRole':
            return addClubAdminRole($args[0], $pdo);

        case 'addActivityAdminRole':
            return addActivityAdminRole($args[0], $pdo);
            
        case 'getClubsListAdmin':
            return getClubsListAdmin($pdo);
            
        case 'updateClubImage':
            return updateClubImage($args[0], $args[1], $pdo);
            
        case 'deleteClubByAdmin':
            return deleteClubByAdmin($args[0], $pdo);
            
        case 'checkIfClubAdmin':
            return checkIfClubAdmin($args[0], $pdo);

        case 'checkIfActivityAdmin':
            return checkIfActivityAdmin($args[0], $pdo);
            
        case 'getAllClubsInfoWithStatus':
            return getAllClubsInfoWithStatus($pdo);
            
        case 'toggleClubStatus':
            // GAS passed index. In PHP we toggle by Club name.
            return toggleClubStatus($args[0], $args[1], $pdo);
            
        case 'saveClubAttendanceWithDate':
            return saveClubAttendanceWithDate($args[0], $args[1], $args[2], $pdo);
            
        case 'getStudentsByClubWithAttendance':
            return getStudentsByClubWithAttendance($args[0], $args[1], $pdo);
            
        case 'getAllTeachersData':
            return getAllTeachersData($pdo);
            
        case 'addStudentToClubManual':
            return addStudentToClubManual($args[0], $args[1], $args[2], $pdo);
            
        case 'removeStudentFromClub':
            return removeStudentFromClub($args[0], $args[1], $args[2], $pdo);
            
        case 'addMultipleStudentsToClub':
            return addMultipleStudentsToClub($args[0], $args[1], $pdo);
            
        case 'generateClubReportPDF':
            return generateClubReportPDF($args[0], $args[1] ?? '', $args[2] ?? '', $pdo);
            
        case 'generateNoClubReportPDF':
            return generateNoClubReportPDF($pdo);
            
        case 'generateAdminClubMembersPDF':
            return generateAdminClubMembersPDF($args[0], $pdo);
            
        case 'generateAdminRoomClubsPDF':
            return generateAdminRoomClubsPDF($args[0], $args[1], $pdo);
            
        case 'getDashboardData':
            return getDashboardData($args[0] ?? '', $args[1] ?? '', $pdo);
            
        case 'getFaceArrivalMap':
            return getFaceArrivalMap($args[0], $args[1], $args[2], $pdo);
            
        case 'addMultipleClubs':
            return addMultipleClubs($args[0], $pdo);
            
        case 'getClubCreationStatus':
            return getClubCreationStatus($pdo);
            
        case 'toggleClubCreationSystemStatus':
            return toggleClubCreationSystemStatus($args[0], $pdo);
            
        default:
            throw new Exception("ไม่พบฟังก์ชันที่เรียก: " . $action);
    }
}

// ----------------------------------------------------
// DATABASE HELPER FUNCTIONS
// ----------------------------------------------------

function canTeacherAccess($teacherName, $level, $room, $pdo) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE name = ?");
    $stmt->execute([$teacherName]);
    $user = $stmt->fetch();
    
    if (!$user) return false;
    
    $username = $user['username'];
    $isAdmin = ($username === '1240800191192' || $username === '1229900316190');
    if ($isAdmin) return true;
    
    if (!empty($user['head_level']) && trim($user['head_level']) === trim($level)) {
        return true;
    }
    
    $targetClass = trim($level) . '/' . trim($room);
    if (!empty($user['advisory_room']) && trim($user['advisory_room']) === $targetClass) {
        return true;
    }
    
    return false;
}

function processLogin($user, $pass, $pdo) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND password = ?");
    $stmt->execute([trim($user), trim($pass)]);
    $foundUser = $stmt->fetch();
    
    if (!$foundUser) {
        return ['success' => false, 'message' => 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง!'];
    }
    
    $username = $foundUser['username'];
    $isAdmin = ($username === '1240800191192' || $username === '1229900316190');

    session_regenerate_id(true);
    $_SESSION['username'] = trim($username);
    $_SESSION['teacher_name'] = trim($foundUser['name']);
    $_SESSION['is_admin'] = $isAdmin;
    
    ensureActivityAdminSchema($pdo);

    // Check if club admin
    $stmtAdmin = $pdo->prepare("SELECT COUNT(*) FROM club_admins WHERE username = ?");
    $stmtAdmin->execute([$username]);
    $isClubAdmin = ($stmtAdmin->fetchColumn() > 0);
    $stmtActivityAdmin = $pdo->prepare("SELECT COUNT(*) FROM activity_admins WHERE username = ?");
    $stmtActivityAdmin->execute([$username]);
    $isActivityAdmin = ($stmtActivityAdmin->fetchColumn() > 0);
    $_SESSION['is_activity_admin'] = $isActivityAdmin;
    
    // Get all teachers for datalist
    $teachersList = [];
    $stmtTeachers = $pdo->query("SELECT name, username FROM users ORDER BY name ASC");
    while ($row = $stmtTeachers->fetch()) {
        $teachersList[] = trim($row['name']) . " (" . trim($row['username']) . ")";
    }
    
    return [
        'success' => true,
        'name' => trim($foundUser['name']),
        'username' => trim($foundUser['username']),
        'avatar' => !empty($foundUser['avatar']) ? trim($foundUser['avatar']) : null,
        'isAdmin' => $isAdmin,
        'isClubAdmin' => $isClubAdmin,
        'isActivityAdmin' => $isActivityAdmin,
        'advisoryRoom' => $foundUser['advisory_room'] ?? '',
        'headLevel' => $foundUser['head_level'] ?? '',
        'allTeachers' => $teachersList
    ];
}

function updateTeacherAvatar($teacherName, $base64Data, $filename, $pdo) {
    try {
        $avatarDir = UPLOAD_DIR . '/avatars';
        if (!file_exists($avatarDir)) {
            mkdir($avatarDir, 0777, true);
            chmod($avatarDir, 0777);
        }

        // Decode base64
        $pos = strpos($base64Data, 'base64,');
        if ($pos !== false) {
            $dataStr = substr($base64Data, $pos + 7);
        } else {
            $dataStr = $base64Data;
        }
        $imgBytes = base64_decode($dataStr);

        $safeName = md5($teacherName);
        $uniqueFilename = 'avatar_' . $safeName . '_' . time() . '.jpg';
        $filePath = $avatarDir . '/' . $uniqueFilename;
        file_put_contents($filePath, $imgBytes);

        $imgUrl = 'uploads/avatars/' . $uniqueFilename;

        $stmt = $pdo->prepare("UPDATE users SET avatar = ? WHERE name = ?");
        $stmt->execute([$imgUrl, trim($teacherName)]);

        return [
            'success' => true,
            'avatar' => $imgUrl,
            'message' => 'อัปโหลดรูปประจำตัวครูสำเร็จแล้ว!'
        ];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()];
    }
}

function adminUpdateSingleTeacherAvatar($userId, $base64Data, $pdo) {
    try {
        $avatarDir = UPLOAD_DIR . '/avatars';
        if (!file_exists($avatarDir)) {
            mkdir($avatarDir, 0777, true);
            chmod($avatarDir, 0777);
        }

        $stmtU = $pdo->prepare("SELECT id, username, name FROM users WHERE id = ? OR username = ?");
        $stmtU->execute([$userId, $userId]);
        $user = $stmtU->fetch();
        if (!$user) {
            return ['success' => false, 'message' => 'ไม่พบข้อมูลครู'];
        }

        $pos = strpos($base64Data, 'base64,');
        $dataStr = ($pos !== false) ? substr($base64Data, $pos + 7) : $base64Data;
        $imgBytes = base64_decode($dataStr);

        $safeName = md5($user['username']);
        $uniqueFilename = 'avatar_' . $safeName . '_' . time() . '.jpg';
        $filePath = $avatarDir . '/' . $uniqueFilename;
        file_put_contents($filePath, $imgBytes);

        $imgUrl = 'uploads/avatars/' . $uniqueFilename;

        $stmt = $pdo->prepare("UPDATE users SET avatar = ? WHERE id = ?");
        $stmt->execute([$imgUrl, $user['id']]);

        return [
            'success' => true,
            'avatar' => $imgUrl,
            'teacherId' => $user['id'],
            'message' => "อัปเดตรูปประจำตัวของครู{$user['name']} สำเร็จแล้ว!"
        ];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()];
    }
}

function adminRemoveTeacherAvatar($userId, $pdo) {
    try {
        $stmt = $pdo->prepare("UPDATE users SET avatar = NULL WHERE id = ? OR username = ?");
        $stmt->execute([$userId, $userId]);
        return [
            'success' => true,
            'message' => 'ลบรูปประจำตัวเรียบร้อยแล้ว'
        ];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()];
    }
}

function getAdminTeacherListWithAvatars($pdo) {
    try {
        requireAdminSession();
        $stmt = $pdo->query("SELECT id, username, name, advisory_room, head_level, avatar FROM users ORDER BY id ASC");
        return ['success' => true, 'teachers' => $stmt->fetchAll(PDO::FETCH_ASSOC)];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function bulkUploadTeacherAvatars($filesArray, $pdo) {
    try {
        $avatarDir = UPLOAD_DIR . '/avatars';
        if (!file_exists($avatarDir)) {
            mkdir($avatarDir, 0777, true);
            chmod($avatarDir, 0777);
        }

        // Fetch all users
        $stmtUsers = $pdo->query("SELECT id, username, name, code, avatar FROM users");
        $users = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);

        $userMap = [];
        foreach ($users as $u) {
            $uNameClean = preg_replace('/^(นาย|นางสาว|นาง|ว่าที่ร้อยตรี|ครู)\s*/u', '', trim($u['name']));
            $parts = preg_split('/\s+/u', $uNameClean);
            $firstName = $parts[0] ?? '';
            
            if (!empty($u['username'])) {
                $userMap[trim($u['username'])] = $u;
            }
            if (!empty($u['name'])) {
                $userMap[trim($u['name'])] = $u;
                $userMap[$uNameClean] = $u;
            }
            if (!empty($firstName)) {
                $userMap[$firstName] = $u;
            }
        }

        $matchedTeachers = [];
        $unmatchedFiles = [];

        $stmtUpdate = $pdo->prepare("UPDATE users SET avatar = ? WHERE id = ?");

        foreach ($filesArray as $item) {
            $filename = trim($item['name'] ?? '');
            $base64Data = $item['data'] ?? '';
            
            if (empty($filename) || empty($base64Data)) continue;

            // Strip extension
            $identifier = pathinfo($filename, PATHINFO_FILENAME);
            $identifierClean = trim($identifier);
            $identifierUpper = strtoupper($identifierClean);
            $identifierNoPrefix = preg_replace('/^(นาย|นางสาว|นาง|ว่าที่ร้อยตรี|ครู)\s*/u', '', $identifierClean);

            $targetUser = null;
            if (isset($userMap[$identifierClean])) {
                $targetUser = $userMap[$identifierClean];
            } elseif (isset($userMap[$identifierUpper])) {
                $targetUser = $userMap[$identifierUpper];
            } elseif (isset($userMap[$identifierNoPrefix])) {
                $targetUser = $userMap[$identifierNoPrefix];
            } else {
                // Try partial / first name match across all users
                foreach ($users as $u) {
                    $uName = trim($u['name']);
                    $uClean = preg_replace('/^(นาย|นางสาว|นาง|ว่าที่ร้อยตรี|ครู)\s*/u', '', $uName);
                    $uParts = preg_split('/\s+/u', $uClean);
                    $uFirst = $uParts[0] ?? '';

                    if ($identifierClean === $uFirst || $identifierNoPrefix === $uFirst) {
                        $targetUser = $u;
                        break;
                    }

                    if (mb_strpos($uClean, $identifierClean) !== false || mb_strpos($identifierClean, $uFirst) !== false) {
                        $targetUser = $u;
                        break;
                    }
                }
            }

            if ($targetUser) {
                // Decode & save
                $pos = strpos($base64Data, 'base64,');
                $dataStr = ($pos !== false) ? substr($base64Data, $pos + 7) : $base64Data;
                $imgBytes = base64_decode($dataStr);

                $safeName = md5($targetUser['username']);
                $uniqueFilename = 'avatar_' . $safeName . '_' . time() . '.jpg';
                $filePath = $avatarDir . '/' . $uniqueFilename;
                file_put_contents($filePath, $imgBytes);

                $imgUrl = 'uploads/avatars/' . $uniqueFilename;
                $stmtUpdate->execute([$imgUrl, $targetUser['id']]);

                $matchedTeachers[] = [
                    'name' => $targetUser['name'],
                    'username' => $targetUser['username'],
                    'avatar' => $imgUrl,
                    'file' => $filename
                ];
            } else {
                $unmatchedFiles[] = $filename;
            }
        }

        $matchedCount = count($matchedTeachers);
        $totalFiles = count($filesArray);

        return [
            'success' => true,
            'matchedCount' => $matchedCount,
            'totalFiles' => $totalFiles,
            'matchedTeachers' => $matchedTeachers,
            'unmatchedFiles' => $unmatchedFiles,
            'message' => "อัปโหลดและจับคู่รูปโปรไฟล์ครูสำเร็จ {$matchedCount} จากทั้งหมด {$totalFiles} ไฟล์"
        ];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()];
    }
}

function adminAddUser($user, $pass, $name, $advisoryRoom, $headLevel, $avatarBase64, $pdo) {
    try {
        $avatarUrl = null;
        if (!empty($avatarBase64)) {
            $avatarDir = UPLOAD_DIR . '/avatars';
            if (!file_exists($avatarDir)) {
                mkdir($avatarDir, 0777, true);
                chmod($avatarDir, 0777);
            }
            $pos = strpos($avatarBase64, 'base64,');
            $dataStr = ($pos !== false) ? substr($avatarBase64, $pos + 7) : $avatarBase64;
            $imgBytes = base64_decode($dataStr);

            $safeName = md5(trim($user));
            $uniqueFilename = 'avatar_' . $safeName . '_' . time() . '.jpg';
            $filePath = $avatarDir . '/' . $uniqueFilename;
            file_put_contents($filePath, $imgBytes);
            $avatarUrl = 'uploads/avatars/' . $uniqueFilename;
        }

        if ($avatarUrl) {
            $stmt = $pdo->prepare("INSERT INTO users (username, password, name, advisory_room, head_level, avatar) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE password = VALUES(password), name = VALUES(name), advisory_room = VALUES(advisory_room), head_level = VALUES(head_level), avatar = VALUES(avatar)");
            $stmt->execute([trim($user), trim($pass), trim($name), $advisoryRoom ?: null, $headLevel ?: null, $avatarUrl]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO users (username, password, name, advisory_room, head_level) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE password = VALUES(password), name = VALUES(name), advisory_room = VALUES(advisory_room), head_level = VALUES(head_level)");
            $stmt->execute([trim($user), trim($pass), trim($name), $advisoryRoom ?: null, $headLevel ?: null]);
        }

        return ['success' => true, 'message' => 'บันทึกข้อมูลครูและรูปภาพเรียบร้อยแล้ว!'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function adminAddStudent($no, $id, $name, $level, $room, $pdo) {
    try {
        requireAdminSession();
        $year = currentAcademicYear($pdo);
        $stmt = $pdo->prepare("INSERT INTO students (no, student_id, name, level, room, is_active, academic_year) VALUES (?, ?, ?, ?, ?, 1, ?) ON DUPLICATE KEY UPDATE no = VALUES(no), name = VALUES(name), level = VALUES(level), room = VALUES(room), is_active = 1, academic_year = VALUES(academic_year)");
        $stmt->execute([$no, trim($id), trim($name), trim($level), trim($room), $year]);
        return ['success' => true, 'message' => 'เพิ่มข้อมูลนักเรียนสำเร็จ!'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function logoutSession() {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', $params['secure'], $params['httponly']);
    }
    session_destroy();
    return ['success' => true];
}

/**
 * Add the roster-management columns/tables without changing historical
 * attendance, behaviour, reward or volunteer records.
 */
function ensureAcademicYearSchema($pdo) {
    $dbName = $pdo->query('SELECT DATABASE()')->fetchColumn();
    $columnStmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'students' AND COLUMN_NAME = ?");

    $columnStmt->execute([$dbName, 'is_active']);
    if ((int)$columnStmt->fetchColumn() === 0) {
        $pdo->exec("ALTER TABLE students ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1");
    }
    $columnStmt->execute([$dbName, 'academic_year']);
    if ((int)$columnStmt->fetchColumn() === 0) {
        $pdo->exec("ALTER TABLE students ADD COLUMN academic_year VARCHAR(9) NULL");
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS academic_year_settings (
        id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
        current_year VARCHAR(9) NOT NULL,
        current_semester TINYINT UNSIGNED NOT NULL DEFAULT 1,
        updated_at DATETIME NOT NULL,
        updated_by VARCHAR(255) NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $settingColumn = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'academic_year_settings' AND COLUMN_NAME = ?");
    $settingColumn->execute([$dbName, 'current_semester']);
    if ((int)$settingColumn->fetchColumn() === 0) {
        $pdo->exec("ALTER TABLE academic_year_settings ADD COLUMN current_semester TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER current_year");
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS student_roster_archives (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        batch_id VARCHAR(40) NOT NULL,
        academic_year VARCHAR(9) NOT NULL,
        archived_at DATETIME NOT NULL,
        archived_by VARCHAR(255) NOT NULL,
        student_id VARCHAR(100) NOT NULL,
        student_no VARCHAR(20) NOT NULL,
        student_name VARCHAR(255) NOT NULL,
        level VARCHAR(30) NOT NULL,
        room VARCHAR(30) NOT NULL,
        avatar TEXT NULL,
        was_active TINYINT(1) NOT NULL DEFAULT 1,
        UNIQUE KEY uq_roster_batch_student (batch_id, student_id),
        KEY idx_roster_batch (batch_id),
        KEY idx_roster_year (academic_year)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS student_roster_imports (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        batch_id VARCHAR(40) NOT NULL UNIQUE,
        from_year VARCHAR(9) NOT NULL,
        to_year VARCHAR(9) NOT NULL,
        imported_count INT UNSIGNED NOT NULL,
        deactivated_count INT UNSIGNED NOT NULL,
        club_members_reset TINYINT(1) NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL,
        created_by VARCHAR(255) NOT NULL,
        KEY idx_import_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS student_roster_changes (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        student_id VARCHAR(100) NOT NULL,
        action VARCHAR(30) NOT NULL,
        old_data LONGTEXT NULL,
        new_data LONGTEXT NULL,
        reason VARCHAR(500) NULL,
        changed_by VARCHAR(255) NOT NULL,
        changed_at DATETIME NOT NULL,
        KEY idx_roster_change_student (student_id),
        KEY idx_roster_change_date (changed_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $indexStmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'students' AND INDEX_NAME = 'idx_students_active_class'");
    $indexStmt->execute([$dbName]);
    if ((int)$indexStmt->fetchColumn() === 0) {
        $pdo->exec("CREATE INDEX idx_students_active_class ON students (is_active, level, room, no)");
    }
}

function ensureActivitySchema($pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS activities (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(150) NOT NULL,
        description VARCHAR(500) NULL,
        activity_date DATE NOT NULL,
        start_time TIME NULL,
        target_level VARCHAR(10) NULL,
        target_room VARCHAR(10) NULL,
        status ENUM('open','closed') NOT NULL DEFAULT 'open',
        created_by VARCHAR(255) NOT NULL,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        KEY idx_activity_date_status (activity_date, status),
        KEY idx_activity_target (target_level, target_room)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $dbName = $pdo->query("SELECT DATABASE()")->fetchColumn();
    $columnStmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'activities' AND COLUMN_NAME = ?");
    $columnStmt->execute([$dbName, 'academic_year']);
    if ((int)$columnStmt->fetchColumn() === 0) $pdo->exec("ALTER TABLE activities ADD COLUMN academic_year VARCHAR(4) NULL AFTER activity_date");
    $columnStmt->execute([$dbName, 'semester']);
    if ((int)$columnStmt->fetchColumn() === 0) $pdo->exec("ALTER TABLE activities ADD COLUMN semester TINYINT UNSIGNED NULL AFTER academic_year");

    $defaultYear = currentAcademicYear($pdo);
    $backfill = $pdo->prepare("UPDATE activities SET academic_year = ?, semester = CASE WHEN MONTH(activity_date) BETWEEN 5 AND 10 THEN 1 ELSE 2 END WHERE academic_year IS NULL OR academic_year = '' OR semester IS NULL");
    $backfill->execute([$defaultYear]);

    $indexStmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'activities' AND INDEX_NAME = 'idx_activity_period'");
    $indexStmt->execute([$dbName]);
    if ((int)$indexStmt->fetchColumn() === 0) $pdo->exec("CREATE INDEX idx_activity_period ON activities (academic_year, semester, activity_date)");

    $pdo->exec("CREATE TABLE IF NOT EXISTS activity_attendance (
        activity_id BIGINT UNSIGNED NOT NULL,
        student_id VARCHAR(100) NOT NULL,
        student_name VARCHAR(255) NOT NULL,
        level VARCHAR(30) NOT NULL,
        room VARCHAR(30) NOT NULL,
        status VARCHAR(30) NOT NULL,
        note VARCHAR(255) NULL,
        teacher_name VARCHAR(255) NOT NULL,
        checked_at DATETIME NOT NULL,
        PRIMARY KEY (activity_id, student_id),
        KEY idx_activity_attendance_class (activity_id, level, room),
        KEY idx_activity_attendance_student (student_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    ensureActivityAdminSchema($pdo);
}

function ensureActivityAdminSchema($pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS activity_admins (
        username VARCHAR(100) NOT NULL PRIMARY KEY,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function requireTeacherSession($requestedTeacherName = '') {
    if (empty($_SESSION['username']) || empty($_SESSION['teacher_name'])) {
        throw new RuntimeException('เซสชันหมดอายุ กรุณาออกจากระบบแล้วเข้าสู่ระบบใหม่');
    }
    $sessionTeacher = trim((string)$_SESSION['teacher_name']);
    $requestedTeacherName = trim((string)$requestedTeacherName);
    if ($requestedTeacherName !== '' && $requestedTeacherName !== $sessionTeacher) {
        throw new RuntimeException('ข้อมูลผู้ใช้งานไม่ตรงกับเซสชัน กรุณาเข้าสู่ระบบใหม่');
    }
    return $sessionTeacher;
}

function getCurrentTeacherPermissions($pdo) {
    if (empty($_SESSION['username'])) throw new RuntimeException('เซสชันหมดอายุ กรุณาเข้าสู่ระบบใหม่');
    $stmt = $pdo->prepare("SELECT username, advisory_room, head_level FROM users WHERE username = ? LIMIT 1");
    $stmt->execute([trim((string)$_SESSION['username'])]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) throw new RuntimeException('ไม่พบข้อมูลสิทธิ์ของครู');
    $user['is_admin'] = !empty($_SESSION['is_admin']);
    return $user;
}

function canCurrentTeacherAccess($level, $room, $pdo) {
    $user = getCurrentTeacherPermissions($pdo);
    if (!empty($user['is_admin'])) return true;
    if (trim((string)($user['head_level'] ?? '')) === $level) return true;
    return trim((string)($user['advisory_room'] ?? '')) === $level . '/' . $room;
}

function canCurrentTeacherAccessActivityClass($level, $room, $pdo) {
    $user = getCurrentTeacherPermissions($pdo);
    if (!empty($user['is_admin'])) return true;

    $advisoryRoom = trim((string)($user['advisory_room'] ?? ''));
    if ($advisoryRoom !== '') {
        return $advisoryRoom === $level . '/' . $room;
    }

    return trim((string)($user['head_level'] ?? '')) === $level;
}

function validateActivityTarget($level, $room) {
    $level = trim((string)$level);
    $room = trim((string)$room);
    if ($level !== '' && !preg_match('/^ม\.[1-6]$/u', $level)) {
        throw new InvalidArgumentException('ระดับชั้นกิจกรรมต้องเป็น ม.1 ถึง ม.6');
    }
    if ($room !== '' && !preg_match('/^[1-6]$/', $room)) {
        throw new InvalidArgumentException('ห้องกิจกรรมต้องเป็น 1 ถึง 6');
    }
    if ($room !== '' && $level === '') {
        throw new InvalidArgumentException('กรุณาเลือกระดับชั้นก่อนเลือกห้อง');
    }
    return [$level, $room];
}

function validateActivityId($activityId) {
    $activityId = filter_var($activityId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($activityId === false) throw new InvalidArgumentException('รหัสกิจกรรมไม่ถูกต้อง');
    return (int)$activityId;
}

function findActivity($activityId, $pdo) {
    ensureActivitySchema($pdo);
    $activityId = validateActivityId($activityId);
    $stmt = $pdo->prepare("SELECT * FROM activities WHERE id = ?");
    $stmt->execute([$activityId]);
    $activity = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$activity) throw new RuntimeException('ไม่พบกิจกรรมที่เลือก');
    return $activity;
}

function activityAllowsClass($activity, $level, $room) {
    $targetLevel = trim((string)($activity['target_level'] ?? ''));
    $targetRoom = trim((string)($activity['target_room'] ?? ''));
    if ($targetLevel !== '' && $targetLevel !== $level) return false;
    if ($targetRoom !== '' && $targetRoom !== $room) return false;
    return true;
}

function normalizeActivityData($data, $pdo) {
    if (!is_array($data)) throw new InvalidArgumentException('ข้อมูลกิจกรรมไม่ถูกต้อง');

    $name = trim((string)($data['name'] ?? ''));
    $description = trim((string)($data['description'] ?? ''));
    $date = trim((string)($data['date'] ?? ''));
    $time = trim((string)($data['time'] ?? ''));
    $academicYear = trim((string)($data['academicYear'] ?? ''));
    $semester = trim((string)($data['semester'] ?? ''));
    [$level, $room] = validateActivityTarget($data['level'] ?? '', $data['room'] ?? '');

    if (mb_strlen($name, 'UTF-8') < 3 || mb_strlen($name, 'UTF-8') > 150 || preg_match('/[<>{}\x00-\x1F]/u', $name)) {
        throw new InvalidArgumentException('ชื่อกิจกรรมต้องมี 3-150 ตัวอักษรและไม่มีอักขระพิเศษที่ไม่ปลอดภัย');
    }
    if (mb_strlen($description, 'UTF-8') > 500 || preg_match('/[<>{}\x00-\x1F]/u', $description)) {
        throw new InvalidArgumentException('รายละเอียดกิจกรรมไม่ถูกต้องหรือยาวเกิน 500 ตัวอักษร');
    }
    $dateParts = explode('-', $date);
    if (count($dateParts) !== 3 || !checkdate((int)$dateParts[1], (int)$dateParts[2], (int)$dateParts[0])) {
        throw new InvalidArgumentException('วันที่กิจกรรมไม่ถูกต้อง');
    }
    if ($time !== '' && !preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/', $time)) {
        throw new InvalidArgumentException('เวลากิจกรรมไม่ถูกต้อง');
    }
    // The administrator-selected academic period is authoritative. Do not
    // accept stale browser values that could create data in another term.
    [$academicYear, $semester, $periodStart, $periodEnd] = academicPeriodBounds('', '', $pdo);
    if ($date < substr($periodStart, 0, 10) || $date > substr($periodEnd, 0, 10)) {
        throw new InvalidArgumentException("วันที่กิจกรรมต้องอยู่ในปีการศึกษา {$academicYear} ภาคเรียนที่ {$semester}");
    }

    return [
        'name' => $name,
        'description' => $description !== '' ? $description : null,
        'date' => $date,
        'academicYear' => $academicYear,
        'semester' => (int)$semester,
        'time' => $time !== '' ? $time . ':00' : null,
        'level' => $level !== '' ? $level : null,
        'room' => $room !== '' ? $room : null
    ];
}

function adminCreateActivity($data, $pdo) {
    $adminName = requireActivityAdminSession();
    ensureActivitySchema($pdo);
    $activity = normalizeActivityData($data, $pdo);

    $stmt = $pdo->prepare("INSERT INTO activities
        (name, description, activity_date, academic_year, semester, start_time, target_level, target_room, status, created_by, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'open', ?, NOW(), NOW())");
    $stmt->execute([
        $activity['name'], $activity['description'], $activity['date'], $activity['academicYear'], $activity['semester'], $activity['time'],
        $activity['level'], $activity['room'],
        $adminName
    ]);
    return ['success' => true, 'message' => 'เพิ่มกิจกรรมเรียบร้อยแล้ว', 'activityId' => (int)$pdo->lastInsertId()];
}

function adminCreateActivitiesBulk($rows, $pdo) {
    $adminName = requireActivityAdminSession();
    ensureActivitySchema($pdo);
    if (!is_array($rows) || count($rows) < 1 || count($rows) > 100) {
        throw new InvalidArgumentException('กรุณาระบุกิจกรรม 1-100 รายการต่อครั้ง');
    }

    $activities = [];
    $batchKeys = [];
    foreach ($rows as $index => $row) {
        try {
            $activity = normalizeActivityData($row, $pdo);
        } catch (Exception $e) {
            throw new InvalidArgumentException('รายการที่ ' . ($index + 1) . ': ' . $e->getMessage());
        }
        $key = mb_strtolower($activity['name'], 'UTF-8') . '|' . $activity['date'] . '|' . ($activity['time'] ?? '');
        if (isset($batchKeys[$key])) {
            throw new InvalidArgumentException('รายการที่ ' . ($index + 1) . ' ซ้ำกับรายการก่อนหน้า');
        }
        $batchKeys[$key] = true;
        $activities[] = $activity;
    }

    $check = $pdo->prepare("SELECT id FROM activities
        WHERE name = ? AND activity_date = ? AND academic_year = ? AND semester = ? AND start_time <=> ? AND target_level IS NULL AND target_room IS NULL LIMIT 1");
    $insert = $pdo->prepare("INSERT INTO activities
        (name, description, activity_date, academic_year, semester, start_time, target_level, target_room, status, created_by, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, NULL, NULL, 'open', ?, NOW(), NOW())");
    $inserted = 0;
    $skipped = 0;
    try {
        $pdo->beginTransaction();
        foreach ($activities as $activity) {
            $check->execute([$activity['name'], $activity['date'], $activity['academicYear'], $activity['semester'], $activity['time']]);
            if ($check->fetchColumn()) {
                $skipped++;
                continue;
            }
            $insert->execute([$activity['name'], $activity['description'], $activity['date'], $activity['academicYear'], $activity['semester'], $activity['time'], $adminName]);
            $inserted++;
        }
        $pdo->commit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
    return [
        'success' => true,
        'inserted' => $inserted,
        'skipped' => $skipped,
        'message' => 'เพิ่มกิจกรรม ' . $inserted . ' รายการเรียบร้อยแล้ว' . ($skipped ? ' (ข้ามรายการซ้ำ ' . $skipped . ' รายการ)' : '')
    ];
}

function adminListActivities($academicYear, $semester, $pdo) {
    requireActivityAdminSession();
    ensureActivitySchema($pdo);
    [$academicYear, $semester] = resolveAcademicPeriod($academicYear, $semester, $pdo);
    $stmt = $pdo->prepare("SELECT a.id, a.name, a.description, a.activity_date activityDate, a.academic_year academicYear, a.semester,
        TIME_FORMAT(a.start_time, '%H:%i') startTime, a.target_level targetLevel,
        a.target_room targetRoom, a.status, a.created_by createdBy, a.created_at createdAt,
        COUNT(aa.student_id) checkedCount
        FROM activities a LEFT JOIN activity_attendance aa ON aa.activity_id = a.id
        WHERE a.academic_year = ? AND a.semester = ?
        GROUP BY a.id ORDER BY a.activity_date DESC, a.id DESC LIMIT 100");
    $stmt->execute([$academicYear, $semester]);
    return ['success' => true, 'academicYear' => $academicYear, 'semester' => $semester, 'activities' => $stmt->fetchAll(PDO::FETCH_ASSOC)];
}

function adminSetActivityStatus($activityId, $status, $pdo) {
    requireActivityAdminSession();
    ensureActivitySchema($pdo);
    $activityId = validateActivityId($activityId);
    $status = trim((string)$status);
    if (!in_array($status, ['open', 'closed'], true)) throw new InvalidArgumentException('สถานะกิจกรรมไม่ถูกต้อง');
    [$academicYear, $semester] = currentAcademicPeriod($pdo);
    $stmt = $pdo->prepare("UPDATE activities SET status = ?, updated_at = NOW() WHERE id = ? AND academic_year = ? AND semester = ?");
    $stmt->execute([$status, $activityId, $academicYear, $semester]);
    if ($stmt->rowCount() < 1) {
        $exists = $pdo->prepare("SELECT COUNT(*) FROM activities WHERE id = ? AND academic_year = ? AND semester = ?");
        $exists->execute([$activityId, $academicYear, $semester]);
        if (!(int)$exists->fetchColumn()) throw new RuntimeException('ไม่พบกิจกรรมในภาคเรียนที่เลือก');
    }
    return ['success' => true, 'message' => $status === 'open' ? 'เปิดกิจกรรมเรียบร้อยแล้ว' : 'ปิดกิจกรรมเรียบร้อยแล้ว'];
}

function adminDeleteActivity($activityId, $pdo) {
    requireActivityAdminSession();
    ensureActivitySchema($pdo);
    $activityId = validateActivityId($activityId);
    $stmt = $pdo->prepare("SELECT name FROM activities WHERE id = ? LIMIT 1");
    $stmt->execute([$activityId]);
    $activity = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$activity) throw new RuntimeException('ไม่พบกิจกรรมที่ต้องการลบ');

    $pdo->beginTransaction();
    try {
        // ลบผลเช็กชื่อที่อ้างอิงกิจกรรมก่อน เพื่อรองรับฐานข้อมูลเดิมที่ไม่มี FK cascade
        $attendance = $pdo->prepare("DELETE FROM activity_attendance WHERE activity_id = ?");
        $attendance->execute([$activityId]);
        $delete = $pdo->prepare("DELETE FROM activities WHERE id = ?");
        $delete->execute([$activityId]);
        if ($delete->rowCount() < 1) throw new RuntimeException('ไม่สามารถลบกิจกรรมได้');
        $pdo->commit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
    return ['success' => true, 'message' => 'ลบกิจกรรม “' . $activity['name'] . '” และผลเช็กชื่อที่เกี่ยวข้องแล้ว'];
}

function adminGetActivityReport($level, $room, $academicYear, $semester, $pdo) {
    requireActivityAdminSession();
    if (trim((string)$room) === 'all') {
        [$level, $normalizedRoom] = validateActivityTarget($level, '1');
        if ($level === '') throw new InvalidArgumentException('กรุณาเลือกระดับชั้น');
        [$academicYear, $semester] = resolveAcademicPeriod($academicYear, $semester, $pdo);
        $combined = null;
        for ($roomNo = 1; $roomNo <= 6; $roomNo++) {
            $part = buildActivityReport($level, (string)$roomNo, $academicYear, $semester, $pdo);
            if ($combined === null) {
                $combined = $part;
                $combined['room'] = 'all';
                $combined['students'] = [];
                $combined['passedCount'] = 0;
                $combined['failedCount'] = 0;
            }
            foreach ($part['students'] as $student) {
                $student['room'] = (string)$roomNo;
                $combined['students'][] = $student;
                if (!empty($student['passed'])) $combined['passedCount']++;
                else $combined['failedCount']++;
            }
        }
        $combined['studentCount'] = count($combined['students']);
        return $combined;
    }
    return buildActivityReport($level, $room, $academicYear, $semester, $pdo);
}

function getActivityReportForTeacher($level, $room, $academicYear, $semester, $teacherName, $pdo) {
    requireTeacherSession($teacherName);
    [$level, $room] = validateActivityTarget($level, $room);
    if ($level === '' || $room === '') throw new InvalidArgumentException('กรุณาเลือกชั้นและห้อง');
    if (!canCurrentTeacherAccessActivityClass($level, $room, $pdo)) {
        throw new RuntimeException('คุณไม่มีสิทธิ์ดูรายงานกิจกรรมของห้องนี้');
    }
    return buildActivityReport($level, $room, $academicYear, $semester, $pdo);
}

function buildActivityReport($level, $room, $academicYear, $semester, $pdo) {
    ensureActivitySchema($pdo);
    [$level, $room] = validateActivityTarget($level, $room);
    if ($level === '' || $room === '') throw new InvalidArgumentException('กรุณาเลือกชั้นและห้อง');
    [$academicYear, $semester] = resolveAcademicPeriod($academicYear, $semester, $pdo);

    $activityStmt = $pdo->prepare("SELECT id, name, activity_date activityDate,
            target_level targetLevel, target_room targetRoom
        FROM activities
        WHERE academic_year = ? AND semester = ? AND ((target_level IS NULL OR target_level = '') OR target_level = ?)
        ORDER BY activity_date ASC, id ASC");
    $activityStmt->execute([$academicYear, $semester, $level]);
    $activities = array_values(array_filter($activityStmt->fetchAll(PDO::FETCH_ASSOC), function ($activity) use ($level, $room) {
        return activityAllowsClass([
            'target_level' => $activity['targetLevel'] ?? '',
            'target_room' => $activity['targetRoom'] ?? ''
        ], $level, $room);
    }));

    $studentStmt = $pdo->prepare("SELECT no, student_id id, name
        FROM students WHERE is_active = 1 AND level = ? AND room = ?
        ORDER BY CAST(no AS UNSIGNED), name");
    $studentStmt->execute([$level, $room]);
    $students = $studentStmt->fetchAll(PDO::FETCH_ASSOC);
    $totals = [];
    foreach ($students as $student) {
        $totals[(string)$student['id']] = ['joined' => 0, 'leave' => 0, 'absent' => 0, 'checked' => 0];
    }

    if ($activities && $students) {
        $activityIds = array_map(function ($activity) { return (int)$activity['id']; }, $activities);
        $placeholders = implode(',', array_fill(0, count($activityIds), '?'));
        $attendanceStmt = $pdo->prepare("SELECT student_id, status, COUNT(*) total
            FROM activity_attendance
            WHERE level = ? AND room = ? AND activity_id IN ($placeholders)
            GROUP BY student_id, status");
        $attendanceStmt->execute(array_merge([$level, $room], $activityIds));
        foreach ($attendanceStmt->fetchAll(PDO::FETCH_ASSOC) as $record) {
            $studentId = (string)$record['student_id'];
            if (!isset($totals[$studentId])) continue;
            $count = (int)$record['total'];
            $totals[$studentId]['checked'] += $count;
            if ($record['status'] === 'เข้าร่วม') $totals[$studentId]['joined'] += $count;
            elseif ($record['status'] === 'ลา (มีใบรับรองแพทย์)') $totals[$studentId]['leave'] += $count;
            elseif ($record['status'] === 'ไม่เข้าร่วมกิจกรรม') $totals[$studentId]['absent'] += $count;
        }
    }

    $activityCount = count($activities);
    $passedCount = 0;
    $reportStudents = [];
    foreach ($students as $student) {
        $counts = $totals[(string)$student['id']];
        $percent = $activityCount > 0 ? round(($counts['joined'] * 100) / $activityCount, 2) : 0;
        $passed = $activityCount > 0 && $percent >= 80;
        if ($passed) $passedCount++;
        $reportStudents[] = [
            'no' => $student['no'], 'id' => $student['id'], 'name' => $student['name'],
            'joined' => $counts['joined'], 'leave' => $counts['leave'], 'absent' => $counts['absent'],
            'unchecked' => max(0, $activityCount - $counts['checked']),
            'percent' => $percent, 'passed' => $passed
        ];
    }

    return [
        'success' => true,
        'level' => $level,
        'room' => $room,
        'academicYear' => $academicYear,
        'semester' => $semester,
        'passThreshold' => 80,
        'activityCount' => $activityCount,
        'studentCount' => count($reportStudents),
        'passedCount' => $passedCount,
        'failedCount' => count($reportStudents) - $passedCount,
        'activities' => $activities,
        'students' => $reportStudents
    ];
}

function getActivitiesForTeacher($teacherName, $academicYear, $semester, $pdo) {
    $teacherName = requireTeacherSession($teacherName);
    ensureActivitySchema($pdo);
    $user = getCurrentTeacherPermissions($pdo);

    $isAdmin = !empty($user['is_admin']);
    $advisoryRoom = trim((string)($user['advisory_room'] ?? ''));
    $headLevel = trim((string)($user['head_level'] ?? ''));
    [$academicYear, $semester] = resolveAcademicPeriod($academicYear, $semester, $pdo);
    $stmt = $pdo->prepare("SELECT id, name, description, activity_date activityDate, academic_year academicYear, semester,
        TIME_FORMAT(start_time, '%H:%i') startTime, target_level targetLevel,
        target_room targetRoom, status FROM activities WHERE academic_year = ? AND semester = ? ORDER BY activity_date DESC, id DESC LIMIT 100");
    $stmt->execute([$academicYear, $semester]);
    $activities = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $activity) {
        $targetLevel = trim((string)($activity['targetLevel'] ?? ''));
        $targetRoom = trim((string)($activity['targetRoom'] ?? ''));
        $allowed = $isAdmin;
        if (!$allowed && $advisoryRoom !== '') {
            [$advLevel, $advRoom] = array_pad(explode('/', $advisoryRoom, 2), 2, '');
            $allowed = ($targetLevel === '' || $targetLevel === $advLevel)
                && ($targetRoom === '' || $targetRoom === $advRoom);
        } elseif (!$allowed && $headLevel !== '') {
            $allowed = ($targetLevel === '' || $targetLevel === $headLevel);
        }
        if ($allowed) $activities[] = $activity;
    }
    return [
        'success' => true,
        'academicYear' => $academicYear,
        'semester' => $semester,
        'activities' => $activities,
        'permissions' => ['isAdmin' => $isAdmin, 'advisoryRoom' => $advisoryRoom, 'headLevel' => $headLevel]
    ];
}

function getActivityStudents($activityId, $level, $room, $teacherName, $pdo) {
    $teacherName = requireTeacherSession($teacherName);
    [$level, $room] = validateActivityTarget($level, $room);
    if ($level === '' || $room === '') throw new InvalidArgumentException('กรุณาเลือกชั้นและห้อง');
    $activity = findActivity($activityId, $pdo);
    [$academicYear, $semester] = currentAcademicPeriod($pdo);
    if ((string)($activity['academic_year'] ?? '') !== $academicYear || (int)($activity['semester'] ?? 0) !== $semester) {
        throw new RuntimeException('กิจกรรมนี้ไม่ได้อยู่ในภาคเรียนที่เลือก');
    }
    if (!activityAllowsClass($activity, $level, $room)) throw new RuntimeException('กิจกรรมนี้ไม่ได้กำหนดให้ห้องเรียนที่เลือก');
    if (!canCurrentTeacherAccessActivityClass($level, $room, $pdo)) throw new RuntimeException('คุณไม่มีสิทธิ์เข้าถึงห้องเรียนนี้');

    $stmt = $pdo->prepare("SELECT s.no, s.student_id id, s.name, s.avatar,
        COALESCE(aa.status, '') savedStatus, COALESCE(aa.note, '') note
        FROM students s LEFT JOIN activity_attendance aa
          ON aa.activity_id = ? AND aa.student_id = s.student_id
        WHERE s.is_active = 1 AND s.level = ? AND s.room = ?
        ORDER BY CAST(s.no AS UNSIGNED), s.name");
    $stmt->execute([(int)$activity['id'], $level, $room]);
    return [
        'success' => true,
        'activity' => [
            'id' => (int)$activity['id'], 'name' => $activity['name'], 'description' => $activity['description'],
            'activityDate' => $activity['activity_date'], 'startTime' => $activity['start_time'], 'status' => $activity['status']
        ],
        'students' => $stmt->fetchAll(PDO::FETCH_ASSOC)
    ];
}

function saveActivityAttendance($activityId, $level, $room, $records, $teacherName, $pdo) {
    $teacherName = requireTeacherSession($teacherName);
    [$level, $room] = validateActivityTarget($level, $room);
    if ($level === '' || $room === '') throw new InvalidArgumentException('กรุณาเลือกชั้นและห้อง');
    $activity = findActivity($activityId, $pdo);
    [$academicYear, $semester] = currentAcademicPeriod($pdo);
    if ((string)($activity['academic_year'] ?? '') !== $academicYear || (int)($activity['semester'] ?? 0) !== $semester) {
        throw new RuntimeException('กิจกรรมนี้ไม่ได้อยู่ในภาคเรียนที่เลือก');
    }
    if (($activity['status'] ?? '') !== 'open') throw new RuntimeException('กิจกรรมนี้ปิดการเช็กชื่อแล้ว');
    if (!activityAllowsClass($activity, $level, $room)) throw new RuntimeException('กิจกรรมนี้ไม่ได้กำหนดให้ห้องเรียนที่เลือก');
    if (!canCurrentTeacherAccessActivityClass($level, $room, $pdo)) throw new RuntimeException('คุณไม่มีสิทธิ์บันทึกข้อมูลห้องเรียนนี้');
    if (!is_array($records) || count($records) < 1 || count($records) > 500) {
        throw new InvalidArgumentException('รายการเช็กกิจกรรมไม่ถูกต้อง');
    }

    $studentStmt = $pdo->prepare("SELECT student_id, name FROM students WHERE is_active = 1 AND level = ? AND room = ?");
    $studentStmt->execute([$level, $room]);
    $students = [];
    foreach ($studentStmt->fetchAll(PDO::FETCH_ASSOC) as $student) {
        $students[trim($student['student_id'])] = trim($student['name']);
    }
    $validStatuses = ['เข้าร่วม', 'ลา (มีใบรับรองแพทย์)', 'ไม่เข้าร่วมกิจกรรม'];
    $normalized = [];
    foreach ($records as $record) {
        if (!is_array($record)) throw new InvalidArgumentException('รูปแบบรายการนักเรียนไม่ถูกต้อง');
        $studentId = trim((string)($record['id'] ?? ''));
        $status = trim((string)($record['status'] ?? ''));
        $note = trim((string)($record['note'] ?? ''));
        if (!isset($students[$studentId])) throw new RuntimeException('พบรายชื่อนักเรียนที่ไม่อยู่ในห้องที่เลือก');
        if (!in_array($status, $validStatuses, true)) throw new InvalidArgumentException('กรุณาเลือกสถานะกิจกรรมให้นักเรียนทุกคน');
        if (mb_strlen($note, 'UTF-8') > 255 || preg_match('/[<>{}\x00-\x1F]/u', $note)) {
            throw new InvalidArgumentException('หมายเหตุไม่ถูกต้องหรือยาวเกิน 255 ตัวอักษร');
        }
        $normalized[$studentId] = ['status' => $status, 'note' => $note];
    }
    if (count($normalized) !== count($students)) {
        throw new InvalidArgumentException('กรุณาบันทึกสถานะนักเรียนให้ครบทั้งห้อง');
    }

    try {
        $pdo->beginTransaction();
        $upsert = $pdo->prepare("INSERT INTO activity_attendance
            (activity_id, student_id, student_name, level, room, status, note, teacher_name, checked_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE student_name = VALUES(student_name), level = VALUES(level), room = VALUES(room),
              status = VALUES(status), note = VALUES(note), teacher_name = VALUES(teacher_name), checked_at = NOW()");
        foreach ($normalized as $studentId => $item) {
            $upsert->execute([
                (int)$activity['id'], $studentId, $students[$studentId], $level, $room,
                $item['status'], $item['note'] !== '' ? $item['note'] : null, $teacherName
            ]);
        }
        $pdo->commit();
        return ['success' => true, 'message' => 'บันทึกการเช็กกิจกรรม ' . count($normalized) . ' คนเรียบร้อยแล้ว'];
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function getActivityMatrix($level, $room, $teacherName, $pdo) {
    $teacherName = requireTeacherSession($teacherName);
    [$level, $room] = validateActivityTarget($level, $room);
    if ($level === '' || $room === '') throw new InvalidArgumentException('กรุณาเลือกชั้นและห้อง');
    if (!canCurrentTeacherAccessActivityClass($level, $room, $pdo)) {
        throw new RuntimeException('คุณไม่มีสิทธิ์เข้าถึงห้องเรียนนี้');
    }
    $report = buildActivityReport($level, $room, '', '', $pdo);
    $activities = $report['activities'] ?? [];
    $activityIds = array_map(static function ($activity) { return (int)$activity['id']; }, $activities);
    $statuses = [];
    if ($activityIds) {
        $placeholders = implode(',', array_fill(0, count($activityIds), '?'));
        $stmt = $pdo->prepare("SELECT activity_id, student_id, status, note FROM activity_attendance WHERE activity_id IN ($placeholders) AND level = ? AND room = ?");
        $stmt->execute(array_merge($activityIds, [$level, $room]));
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $sid = trim((string)$row['student_id']);
            $statuses[$sid][(string)(int)$row['activity_id']] = [
                'status' => trim((string)$row['status']),
                'note' => trim((string)($row['note'] ?? ''))
            ];
        }
    }
    foreach ($report['students'] as &$student) {
        $studentId = (string)$student['id'];
        $student['statuses'] = $statuses[$studentId] ?? [];
    }
    unset($student);
    return [
        'success' => true,
        'academicYear' => $report['academicYear'],
        'semester' => $report['semester'],
        'level' => $level,
        'room' => $room,
        'activities' => $activities,
        'students' => $report['students'],
        'passThreshold' => 80
    ];
}

function saveActivityMatrix($level, $room, $rows, $teacherName, $pdo) {
    $teacherName = requireTeacherSession($teacherName);
    [$level, $room] = validateActivityTarget($level, $room);
    if ($level === '' || $room === '') throw new InvalidArgumentException('กรุณาเลือกชั้นและห้อง');
    if (!canCurrentTeacherAccessActivityClass($level, $room, $pdo)) {
        throw new RuntimeException('คุณไม่มีสิทธิ์บันทึกข้อมูลห้องเรียนนี้');
    }
    if (!is_array($rows) || count($rows) < 1 || count($rows) > 500) throw new InvalidArgumentException('รายการเช็กกิจกรรมไม่ถูกต้อง');
    $report = buildActivityReport($level, $room, '', '', $pdo);
    $activities = [];
    foreach ($report['activities'] as $activity) $activities[(string)(int)$activity['id']] = $activity;
    $studentStmt = $pdo->prepare("SELECT student_id, name FROM students WHERE is_active = 1 AND level = ? AND room = ?");
    $studentStmt->execute([$level, $room]);
    $students = [];
    foreach ($studentStmt->fetchAll(PDO::FETCH_ASSOC) as $student) $students[trim($student['student_id'])] = trim($student['name']);
    $validStatuses = ['เข้าร่วม', 'ลา (มีใบรับรองแพทย์)', 'ไม่เข้าร่วมกิจกรรม'];
    $normalized = [];
    foreach ($rows as $row) {
        if (!is_array($row)) throw new InvalidArgumentException('รูปแบบรายชื่อนักเรียนไม่ถูกต้อง');
        $studentId = trim((string)($row['id'] ?? ''));
        if (!isset($students[$studentId])) throw new RuntimeException('พบรายชื่อนักเรียนที่ไม่อยู่ในห้องที่เลือก');
        $rowStatuses = $row['statuses'] ?? [];
        if (!is_array($rowStatuses)) throw new InvalidArgumentException('รูปแบบสถานะกิจกรรมไม่ถูกต้อง');
        foreach ($rowStatuses as $activityId => $value) {
            $activityKey = (string)(int)$activityId;
            if (!isset($activities[$activityKey])) throw new RuntimeException('พบกิจกรรมที่ไม่อยู่ในภาคเรียนที่เลือก');
            if (!is_array($value)) continue;
            $status = trim((string)($value['status'] ?? ''));
            $note = trim((string)($value['note'] ?? ''));
            if ($status === '') continue;
            if (!in_array($status, $validStatuses, true)) throw new InvalidArgumentException('สถานะกิจกรรมไม่ถูกต้อง');
            if (($activities[$activityKey]['status'] ?? '') !== 'open') throw new RuntimeException('มีกิจกรรมที่ปิดการเช็กชื่อแล้ว');
            if (mb_strlen($note, 'UTF-8') > 255 || preg_match('/[<>{}\x00-\x1F]/u', $note)) throw new InvalidArgumentException('หมายเหตุไม่ถูกต้อง');
            $normalized[] = [(int)$activityKey, $studentId, $students[$studentId], $status, $note];
        }
    }
    if (!$normalized) throw new InvalidArgumentException('ยังไม่มีสถานะที่เปลี่ยนแปลง');
    try {
        $pdo->beginTransaction();
        $upsert = $pdo->prepare("INSERT INTO activity_attendance (activity_id, student_id, student_name, level, room, status, note, teacher_name, checked_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE student_name = VALUES(student_name), status = VALUES(status), note = VALUES(note), teacher_name = VALUES(teacher_name), checked_at = NOW()");
        foreach ($normalized as $item) $upsert->execute([$item[0], $item[1], $item[2], $level, $room, $item[3], $item[4] !== '' ? $item[4] : null, $teacherName]);
        $pdo->commit();
        return ['success' => true, 'message' => 'บันทึกการเช็กกิจกรรม ' . count($normalized) . ' รายการเรียบร้อยแล้ว'];
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function adminUpdateTeacherAssignment($userId, $advisoryRoom, $headLevel, $pdo) {
    requireAdminSession();
    $userId = filter_var($userId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($userId === false) throw new InvalidArgumentException('รหัสบัญชีครูไม่ถูกต้อง');

    $advisoryRoom = trim((string)$advisoryRoom);
    $headLevel = trim((string)$headLevel);
    if ($advisoryRoom !== '' && !preg_match('/^ม\.[1-6]\/[1-6]$/u', $advisoryRoom)) {
        throw new InvalidArgumentException('ห้องที่ปรึกษาต้องเป็น ม.1/1 ถึง ม.6/6');
    }
    if ($headLevel !== '' && !preg_match('/^ม\.[1-6]$/u', $headLevel)) {
        throw new InvalidArgumentException('ระดับชั้นที่รับผิดชอบต้องเป็น ม.1 ถึง ม.6');
    }

    $stmt = $pdo->prepare("SELECT id, name FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $teacher = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$teacher) throw new RuntimeException('ไม่พบบัญชีครูที่ต้องการแก้ไข');

    $update = $pdo->prepare("UPDATE users SET advisory_room = ?, head_level = ? WHERE id = ?");
    $update->execute([$advisoryRoom !== '' ? $advisoryRoom : null, $headLevel !== '' ? $headLevel : null, $userId]);

    return [
        'success' => true,
        'message' => 'ปรับสิทธิ์ครูที่ปรึกษาเรียบร้อยแล้ว กรุณาให้ครูออกจากระบบและเข้าสู่ระบบใหม่',
        'teacher' => [
            'id' => (int)$userId,
            'name' => $teacher['name'],
            'advisory_room' => $advisoryRoom,
            'head_level' => $headLevel
        ]
    ];
}

function requireAdminSession() {
    if (empty($_SESSION['is_admin']) || empty($_SESSION['username'])) {
        throw new RuntimeException('เซสชันผู้ดูแลระบบหมดอายุ กรุณาออกจากระบบแล้วเข้าสู่ระบบใหม่');
    }
    return trim($_SESSION['teacher_name'] ?? $_SESSION['username']);
}

function requireActivityAdminSession() {
    if (empty($_SESSION['username']) || empty($_SESSION['teacher_name'])) {
        throw new RuntimeException('เซสชันหมดอายุ กรุณาออกจากระบบแล้วเข้าสู่ระบบใหม่');
    }
    if (empty($_SESSION['is_admin']) && empty($_SESSION['is_activity_admin'])) {
        throw new RuntimeException('คุณไม่มีสิทธิ์ผู้ดูแลระบบกิจกรรม');
    }
    return trim($_SESSION['teacher_name'] ?? $_SESSION['username']);
}

function validateStudentMasterData($data) {
    if (!is_array($data)) throw new InvalidArgumentException('รูปแบบข้อมูลนักเรียนไม่ถูกต้อง');
    $no = trim((string)($data['no'] ?? ''));
    $name = trim((string)($data['name'] ?? ''));
    $level = trim((string)($data['level'] ?? ''));
    $room = trim((string)($data['room'] ?? ''));
    $level = preg_replace('/^ม\.?\s*([1-6])$/u', 'ม.$1', $level);
    if (!preg_match('/^[0-9]{1,3}$/', $no) || (int)$no < 1) throw new InvalidArgumentException('เลขที่ต้องเป็นตัวเลข 1-999');
    if ($name === '' || mb_strlen($name, 'UTF-8') > 255 || preg_match('/[<>{}\x00-\x1F]/u', $name)) throw new InvalidArgumentException('ชื่อนักเรียนไม่ถูกต้อง');
    if (!preg_match('/^ม\.[1-6]$/u', $level)) throw new InvalidArgumentException('ชั้นเรียนต้องเป็น ม.1 ถึง ม.6');
    if (!preg_match('/^[1-6]$/', $room)) throw new InvalidArgumentException('ห้องเรียนต้องเป็น 1 ถึง 6');
    return ['no' => (string)(int)$no, 'name' => $name, 'level' => $level, 'room' => $room];
}

function validateStudentIdValue($studentId) {
    $studentId = trim((string)$studentId);
    if (!preg_match('/^[0-9A-Za-z_-]{3,30}$/', $studentId)) throw new InvalidArgumentException('รหัสนักเรียนไม่ถูกต้อง');
    return $studentId;
}

function writeStudentRosterChange($pdo, $studentId, $action, $oldData, $newData, $reason, $adminName) {
    $stmt = $pdo->prepare("INSERT INTO student_roster_changes
        (student_id, action, old_data, new_data, reason, changed_by, changed_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW())");
    $stmt->execute([
        $studentId,
        $action,
        $oldData === null ? null : json_encode($oldData, JSON_UNESCAPED_UNICODE),
        $newData === null ? null : json_encode($newData, JSON_UNESCAPED_UNICODE),
        $reason !== '' ? $reason : null,
        $adminName
    ]);
}

function getAdminStudentsByRoom($level, $room, $status, $pdo) {
    requireAdminSession();
    ensureAcademicYearSchema($pdo);
    $level = trim((string)$level);
    $room = trim((string)$room);
    if (!preg_match('/^ม\.[1-6]$/u', $level) || !preg_match('/^[1-6]$/', $room)) {
        throw new InvalidArgumentException('กรุณาเลือกชั้นและห้องให้ถูกต้อง');
    }
    $status = in_array($status, ['active', 'inactive', 'all'], true) ? $status : 'active';
    $sql = "SELECT no, student_id, name, level, room, avatar, is_active, academic_year FROM students WHERE level = ? AND room = ?";
    if ($status === 'active') $sql .= " AND is_active = 1";
    if ($status === 'inactive') $sql .= " AND is_active = 0";
    $sql .= " ORDER BY is_active DESC, CAST(no AS UNSIGNED), name";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$level, $room]);
    return ['success' => true, 'students' => $stmt->fetchAll(), 'status' => $status];
}

function adminUpdateStudentRecord($studentId, $data, $pdo) {
    $adminName = requireAdminSession();
    ensureAcademicYearSchema($pdo);
    $studentId = validateStudentIdValue($studentId);
    $data = validateStudentMasterData($data);
    try {
        $pdo->beginTransaction();
        $find = $pdo->prepare("SELECT no, student_id, name, level, room, is_active, academic_year FROM students WHERE student_id = ? FOR UPDATE");
        $find->execute([$studentId]);
        $old = $find->fetch();
        if (!$old) throw new RuntimeException('ไม่พบข้อมูลนักเรียน');

        if ((int)$old['is_active'] === 1) {
            $duplicate = $pdo->prepare("SELECT COUNT(*) FROM students WHERE is_active = 1 AND level = ? AND room = ? AND no = ? AND student_id <> ?");
            $duplicate->execute([$data['level'], $data['room'], $data['no'], $studentId]);
            if ((int)$duplicate->fetchColumn() > 0) throw new RuntimeException('เลขที่นี้มีนักเรียนใช้งานอยู่แล้วในห้องปลายทาง');
        }

        $update = $pdo->prepare("UPDATE students SET no = ?, name = ?, level = ?, room = ? WHERE student_id = ?");
        $update->execute([$data['no'], $data['name'], $data['level'], $data['room'], $studentId]);
        $clubUpdate = $pdo->prepare("UPDATE club_members SET name = ?, level = ?, room = ? WHERE student_id = ?");
        $clubUpdate->execute([$data['name'], $data['level'], $data['room'], $studentId]);
        $new = array_merge($old, $data);
        writeStudentRosterChange($pdo, $studentId, 'update', $old, $new, '', $adminName);
        $pdo->commit();
        return ['success' => true, 'message' => 'แก้ไขข้อมูลนักเรียนเรียบร้อยแล้ว', 'student' => $new];
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

/**
 * เปลี่ยนชั้น/ห้องนักเรียนหลายคนในภาคเรียนเดิม โดยไม่เปลี่ยนปีการศึกษา
 * และไม่แตะต้องข้อมูลประวัติการเช็กชื่อ คะแนน หรือกิจกรรม
 */
function adminBulkMoveStudents($rows, $pdo) {
    $adminName = requireAdminSession();
    ensureAcademicYearSchema($pdo);
    if (!is_array($rows) || count($rows) < 1) throw new InvalidArgumentException('ไม่พบรายการสำหรับย้ายห้อง');
    if (count($rows) > 2000) throw new InvalidArgumentException('นำเข้าได้ไม่เกิน 2,000 รายการต่อครั้ง');

    $normalized = [];
    $seen = [];
    foreach ($rows as $index => $row) {
        if (!is_array($row)) throw new InvalidArgumentException('รูปแบบรายการแถวที่ ' . ($index + 1) . ' ไม่ถูกต้อง');
        $studentId = validateStudentIdValue($row['studentId'] ?? ($row['student_id'] ?? ''));
        $level = trim((string)($row['level'] ?? ''));
        $room = trim((string)($row['room'] ?? ''));
        if (!preg_match('/^ม\.[1-6]$/u', $level)) throw new InvalidArgumentException('ชั้นของแถวที่ ' . ($index + 1) . ' ต้องเป็น ม.1 ถึง ม.6');
        if (!preg_match('/^[1-6]$/', $room)) throw new InvalidArgumentException('ห้องของแถวที่ ' . ($index + 1) . ' ต้องเป็น 1 ถึง 6');
        if (isset($seen[$studentId])) throw new InvalidArgumentException('รหัสนักเรียนซ้ำในไฟล์: ' . $studentId);
        $seen[$studentId] = true;
        $normalized[] = ['studentId' => $studentId, 'level' => $level, 'room' => $room];
    }

    try {
        $pdo->beginTransaction();
        $find = $pdo->prepare("SELECT no, student_id, name, level, room, is_active, academic_year FROM students WHERE student_id = ? FOR UPDATE");
        $checkTarget = $pdo->prepare("SELECT COUNT(*) FROM students WHERE is_active = 1 AND level = ? AND room = ? AND no = ? AND student_id <> ?");
        $update = $pdo->prepare("UPDATE students SET level = ?, room = ? WHERE student_id = ?");
        $clubUpdate = $pdo->prepare("UPDATE club_members SET level = ?, room = ? WHERE student_id = ?");
        $changed = 0; $unchanged = 0; $missing = [];
        foreach ($normalized as $item) {
            $find->execute([$item['studentId']]);
            $old = $find->fetch();
            if (!$old) { $missing[] = $item['studentId']; continue; }
            if ((string)$old['level'] === $item['level'] && (string)$old['room'] === $item['room']) { $unchanged++; continue; }
            if ((int)$old['is_active'] === 1) {
                $checkTarget->execute([$item['level'], $item['room'], $old['no'], $item['studentId']]);
                if ((int)$checkTarget->fetchColumn() > 0) {
                    throw new RuntimeException('เลขที่ ' . $old['no'] . ' มีนักเรียนใช้งานอยู่แล้วใน ' . $item['level'] . '/' . $item['room'] . ' (' . $item['studentId'] . ')');
                }
            }
            $update->execute([$item['level'], $item['room'], $item['studentId']]);
            $clubUpdate->execute([$item['level'], $item['room'], $item['studentId']]);
            $new = array_merge($old, ['level' => $item['level'], 'room' => $item['room']]);
            writeStudentRosterChange($pdo, $item['studentId'], 'bulk_room_move', $old, $new, 'ปรับห้องจำนวนมากโดยไม่เปลี่ยนปีการศึกษา', $adminName);
            $changed++;
        }
        $pdo->commit();
        return ['success' => true, 'changedCount' => $changed, 'unchangedCount' => $unchanged, 'missingStudentIds' => $missing, 'message' => 'ปรับชั้น/ห้องเรียบร้อยแล้ว โดยไม่เปลี่ยนปีการศึกษาและไม่ลบประวัติเดิม'];
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function adminSetStudentActive($studentId, $active, $reason, $pdo) {
    $adminName = requireAdminSession();
    ensureAcademicYearSchema($pdo);
    $studentId = validateStudentIdValue($studentId);
    $active = filter_var($active, FILTER_VALIDATE_BOOLEAN);
    $reason = trim((string)$reason);
    if ($reason !== '' && (mb_strlen($reason, 'UTF-8') > 500 || preg_match('/[<>{}\x00-\x1F]/u', $reason))) {
        throw new InvalidArgumentException('เหตุผลไม่ถูกต้องหรือยาวเกิน 500 ตัวอักษร');
    }
    if (!$active && $reason === '') {
        throw new InvalidArgumentException('กรุณาระบุเหตุผลการปิดใช้งาน (ไม่เกิน 500 ตัวอักษร)');
    }
    try {
        $pdo->beginTransaction();
        $find = $pdo->prepare("SELECT no, student_id, name, level, room, is_active, academic_year FROM students WHERE student_id = ? FOR UPDATE");
        $find->execute([$studentId]);
        $old = $find->fetch();
        if (!$old) throw new RuntimeException('ไม่พบข้อมูลนักเรียน');
        if ((bool)$old['is_active'] === $active) {
            $pdo->rollBack();
            return ['success' => true, 'message' => $active ? 'นักเรียนเปิดใช้งานอยู่แล้ว' : 'นักเรียนปิดใช้งานอยู่แล้ว'];
        }
        if ($active) {
            $duplicate = $pdo->prepare("SELECT COUNT(*) FROM students WHERE is_active = 1 AND level = ? AND room = ? AND no = ? AND student_id <> ?");
            $duplicate->execute([$old['level'], $old['room'], $old['no'], $studentId]);
            if ((int)$duplicate->fetchColumn() > 0) throw new RuntimeException('ไม่สามารถเปิดใช้งานได้ เพราะเลขที่ซ้ำกับนักเรียนในห้องนี้ กรุณาแก้เลขที่ก่อน');
        }
        $update = $pdo->prepare("UPDATE students SET is_active = ? WHERE student_id = ?");
        $update->execute([$active ? 1 : 0, $studentId]);
        if (!$active) {
            // Remove only the current club registration. Historical club
            // attendance remains untouched.
            $removeClub = $pdo->prepare("DELETE FROM club_members WHERE student_id = ?");
            $removeClub->execute([$studentId]);
        }
        $new = $old;
        $new['is_active'] = $active ? 1 : 0;
        writeStudentRosterChange($pdo, $studentId, $active ? 'reactivate' : 'deactivate', $old, $new, $reason, $adminName);
        $pdo->commit();
        return ['success' => true, 'message' => $active ? 'เปิดใช้งานนักเรียนเรียบร้อยแล้ว' : 'ปิดใช้งานนักเรียนเรียบร้อยแล้ว โดยยังเก็บประวัติเดิมทั้งหมด'];
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function getRecentStudentRosterChanges($pdo) {
    requireAdminSession();
    ensureAcademicYearSchema($pdo);
    $stmt = $pdo->query("SELECT student_id studentId, action, old_data oldData, new_data newData, reason, changed_by changedBy, changed_at changedAt
        FROM student_roster_changes ORDER BY id DESC LIMIT 50");
    return ['success' => true, 'changes' => $stmt->fetchAll()];
}

function validateAcademicYear($year) {
    $year = trim((string)$year);
    if (!preg_match('/^[0-9]{4}$/', $year)) {
        throw new InvalidArgumentException('ปีการศึกษาต้องเป็นตัวเลข 4 หลัก เช่น 2569');
    }
    return $year;
}

function normalizeRosterRows($rows) {
    if (!is_array($rows) || count($rows) < 1 || count($rows) > 5000) {
        throw new InvalidArgumentException('ไฟล์ต้องมีรายชื่อนักเรียน 1-5,000 คน');
    }

    $normalized = [];
    $seenIds = [];
    foreach ($rows as $index => $row) {
        $line = $index + 2;
        if (!is_array($row)) throw new InvalidArgumentException("ข้อมูลแถว {$line} ไม่ถูกต้อง");
        $no = trim((string)($row['no'] ?? ''));
        $id = trim((string)($row['studentId'] ?? $row['student_id'] ?? ''));
        $name = trim((string)($row['name'] ?? ''));
        $level = trim((string)($row['level'] ?? ''));
        $room = trim((string)($row['room'] ?? ''));
        $level = preg_replace('/^ม\.?\s*([1-6])$/u', 'ม.$1', $level);

        if (!preg_match('/^[0-9]{1,3}$/', $no) || (int)$no < 1) throw new InvalidArgumentException("เลขที่ในแถว {$line} ไม่ถูกต้อง");
        if (!preg_match('/^[0-9A-Za-z_-]{3,30}$/', $id)) throw new InvalidArgumentException("รหัสนักเรียนในแถว {$line} ไม่ถูกต้อง");
        if ($name === '' || mb_strlen($name, 'UTF-8') > 255 || preg_match('/[<>{}\x00-\x1F]/u', $name)) throw new InvalidArgumentException("ชื่อในแถว {$line} ไม่ถูกต้อง");
        if (!preg_match('/^ม\.[1-6]$/u', $level)) throw new InvalidArgumentException("ชั้นเรียนในแถว {$line} ต้องเป็น ม.1 ถึง ม.6");
        if (!preg_match('/^[1-6]$/', $room)) throw new InvalidArgumentException("ห้องในแถว {$line} ต้องเป็น 1 ถึง 6");
        if (isset($seenIds[$id])) throw new InvalidArgumentException("รหัสนักเรียน {$id} ซ้ำในไฟล์ (แถว {$line})");

        $seenIds[$id] = true;
        $normalized[] = ['no' => (string)(int)$no, 'student_id' => $id, 'name' => $name, 'level' => $level, 'room' => $room];
    }
    return $normalized;
}

function currentAcademicYear($pdo) {
    $year = $pdo->query("SELECT current_year FROM academic_year_settings WHERE id = 1")->fetchColumn();
    return $year ?: (string)((int)date('Y') + 543);
}

function currentAcademicPeriod($pdo) {
    $row = $pdo->query("SELECT current_year, current_semester FROM academic_year_settings WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
    $fallbackSemester = ((int)date('n') >= 5 && (int)date('n') <= 10) ? 1 : 2;
    if (!$row) return [(string)((int)date('Y') + 543), $fallbackSemester];
    $year = validateAcademicYear($row['current_year']);
    $semester = in_array((string)($row['current_semester'] ?? ''), ['1', '2'], true) ? (int)$row['current_semester'] : $fallbackSemester;
    return [$year, $semester];
}

function getCurrentAcademicPeriod($pdo) {
    [$academicYear, $semester] = currentAcademicPeriod($pdo);
    return [
        'success' => true,
        'academicYear' => $academicYear,
        'semester' => $semester,
        'canEdit' => !empty($_SESSION['is_admin']) && !empty($_SESSION['username'])
    ];
}

function adminSetAcademicPeriod($academicYear, $semester, $pdo) {
    $adminName = requireAdminSession();
    $academicYear = validateAcademicYear($academicYear);
    if (!in_array((string)$semester, ['1', '2'], true)) throw new InvalidArgumentException('ภาคเรียนต้องเป็น 1 หรือ 2');
    $semester = (int)$semester;
    $stmt = $pdo->prepare("INSERT INTO academic_year_settings (id, current_year, current_semester, updated_at, updated_by)
        VALUES (1, ?, ?, NOW(), ?) ON DUPLICATE KEY UPDATE current_year=VALUES(current_year), current_semester=VALUES(current_semester), updated_at=NOW(), updated_by=VALUES(updated_by)");
    $stmt->execute([$academicYear, $semester, $adminName]);
    return ['success' => true, 'academicYear' => $academicYear, 'semester' => $semester, 'message' => "กำหนดปีการศึกษา {$academicYear} ภาคเรียนที่ {$semester} สำหรับทั้งระบบแล้ว"];
}

function resolveAcademicPeriod($academicYear, $semester, $pdo) {
    // The administrator-selected value is the single source of truth. Client
    // arguments are ignored so cached pages cannot request a different term.
    return currentAcademicPeriod($pdo);
}

function academicPeriodBounds($academicYear, $semester, $pdo) {
    [$academicYear, $semester] = resolveAcademicPeriod($academicYear, $semester, $pdo);
    $gregorianYear = (int)$academicYear - 543;
    if ($semester === 1) {
        return [$academicYear, $semester, sprintf('%04d-05-01 00:00:00', $gregorianYear), sprintf('%04d-10-31 23:59:59', $gregorianYear)];
    }
    return [$academicYear, $semester, sprintf('%04d-11-01 00:00:00', $gregorianYear), sprintf('%04d-04-30 23:59:59', $gregorianYear + 1)];
}

function ensureDateInSelectedAcademicPeriod($date, $pdo, $allowEmpty = false) {
    $date = trim((string)$date);
    if ($allowEmpty && $date === '') return '';
    $parts = explode('-', $date);
    if (count($parts) !== 3 || !checkdate((int)$parts[1], (int)$parts[2], (int)$parts[0])) {
        throw new InvalidArgumentException('วันที่ไม่ถูกต้อง');
    }
    [$academicYear, $semester, $periodStart, $periodEnd] = academicPeriodBounds('', '', $pdo);
    $startDate = substr($periodStart, 0, 10);
    $endDate = substr($periodEnd, 0, 10);
    if ($date < $startDate || $date > $endDate) {
        throw new InvalidArgumentException("กรุณาเลือกวันที่ในปีการศึกษา {$academicYear} ภาคเรียนที่ {$semester} ({$startDate} ถึง {$endDate})");
    }
    return $date;
}

function archiveCurrentRoster($pdo, $batchId, $year, $adminName) {
    $stmt = $pdo->prepare("INSERT INTO student_roster_archives
        (batch_id, academic_year, archived_at, archived_by, student_id, student_no, student_name, level, room, avatar, was_active)
        SELECT ?, ?, NOW(), ?, student_id, no, name, level, room, avatar, is_active FROM students");
    $stmt->execute([$batchId, $year, $adminName]);
    return $stmt->rowCount();
}

function getAcademicYearSetupData($pdo) {
    $adminName = requireAdminSession();
    ensureAcademicYearSchema($pdo);
    $counts = $pdo->query("SELECT SUM(is_active = 1) active_count, SUM(is_active = 0) inactive_count FROM students")->fetch();
    return [
        'success' => true,
        'currentYear' => currentAcademicYear($pdo),
        'activeCount' => (int)($counts['active_count'] ?? 0),
        'inactiveCount' => (int)($counts['inactive_count'] ?? 0),
        'adminName' => $adminName
    ];
}

function applyAcademicYearImport($rows, $targetYear, $options, $pdo) {
    $adminName = requireAdminSession();
    ensureAcademicYearSchema($pdo);
    $targetYear = validateAcademicYear($targetYear);
    $rows = normalizeRosterRows($rows);
    $deactivateMissing = !isset($options['deactivateMissing']) || (bool)$options['deactivateMissing'];
    $resetClubMembers = !empty($options['resetClubMembers']);
    $fromYear = currentAcademicYear($pdo);
    $batchId = bin2hex(random_bytes(16));

    try {
        $pdo->beginTransaction();
        archiveCurrentRoster($pdo, $batchId, $fromYear, $adminName);

        $deactivated = 0;
        if ($deactivateMissing) {
            $pdo->exec("UPDATE students SET is_active = 0 WHERE is_active <> 0");
        }

        $upsert = $pdo->prepare("INSERT INTO students (no, student_id, name, level, room, is_active, academic_year)
            VALUES (?, ?, ?, ?, ?, 1, ?)
            ON DUPLICATE KEY UPDATE no = VALUES(no), name = VALUES(name), level = VALUES(level), room = VALUES(room), is_active = 1, academic_year = VALUES(academic_year)");
        foreach ($rows as $row) {
            $upsert->execute([$row['no'], $row['student_id'], $row['name'], $row['level'], $row['room'], $targetYear]);
        }
        if ($deactivateMissing) {
            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM student_roster_archives a
                WHERE a.batch_id = ? AND a.was_active = 1
                AND NOT EXISTS (SELECT 1 FROM students s WHERE s.student_id = a.student_id AND s.is_active = 1)");
            $countStmt->execute([$batchId]);
            $deactivated = (int)$countStmt->fetchColumn();
        }

        if ($resetClubMembers) {
            $pdo->exec("DELETE FROM club_members");
        }
        $setting = $pdo->prepare("INSERT INTO academic_year_settings (id, current_year, updated_at, updated_by)
            VALUES (1, ?, NOW(), ?) ON DUPLICATE KEY UPDATE current_year = VALUES(current_year), updated_at = NOW(), updated_by = VALUES(updated_by)");
        $setting->execute([$targetYear, $adminName]);
        $audit = $pdo->prepare("INSERT INTO student_roster_imports
            (batch_id, from_year, to_year, imported_count, deactivated_count, club_members_reset, created_at, created_by)
            VALUES (?, ?, ?, ?, ?, ?, NOW(), ?)");
        $audit->execute([$batchId, $fromYear, $targetYear, count($rows), $deactivated, $resetClubMembers ? 1 : 0, $adminName]);
        $pdo->commit();

        return ['success' => true, 'message' => 'ขึ้นปีการศึกษาใหม่เรียบร้อยแล้ว', 'batchId' => $batchId,
            'importedCount' => count($rows), 'deactivatedCount' => $deactivated, 'targetYear' => $targetYear];
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function listAcademicYearBackups($pdo) {
    requireAdminSession();
    ensureAcademicYearSchema($pdo);
    $stmt = $pdo->query("SELECT i.batch_id batchId, i.from_year fromYear, i.to_year toYear, i.imported_count importedCount,
        i.deactivated_count deactivatedCount, i.club_members_reset clubMembersReset, i.created_at createdAt,
        i.created_by createdBy, COUNT(a.id) backupCount
        FROM student_roster_imports i LEFT JOIN student_roster_archives a ON a.batch_id = i.batch_id
        GROUP BY i.id ORDER BY i.created_at DESC LIMIT 20");
    return ['success' => true, 'backups' => $stmt->fetchAll()];
}

function restoreAcademicYearBackup($batchId, $pdo) {
    $adminName = requireAdminSession();
    ensureAcademicYearSchema($pdo);
    $batchId = trim((string)$batchId);
    if (!preg_match('/^[a-f0-9]{32}$/', $batchId)) throw new InvalidArgumentException('รหัสชุดสำรองไม่ถูกต้อง');
    $stmt = $pdo->prepare("SELECT academic_year, student_id, student_no, student_name, level, room, avatar, was_active
        FROM student_roster_archives WHERE batch_id = ? ORDER BY id");
    $stmt->execute([$batchId]);
    $backupRows = $stmt->fetchAll();
    if (!$backupRows) throw new RuntimeException('ไม่พบชุดสำรองที่เลือก');

    $restoreYear = $backupRows[0]['academic_year'];
    $currentYear = currentAcademicYear($pdo);
    $safetyBatch = bin2hex(random_bytes(16));
    try {
        $pdo->beginTransaction();
        archiveCurrentRoster($pdo, $safetyBatch, $currentYear, $adminName);
        $pdo->exec("UPDATE students SET is_active = 0");
        $upsert = $pdo->prepare("INSERT INTO students (no, student_id, name, level, room, avatar, is_active, academic_year)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE no=VALUES(no), name=VALUES(name), level=VALUES(level), room=VALUES(room), avatar=VALUES(avatar), is_active=VALUES(is_active), academic_year=VALUES(academic_year)");
        foreach ($backupRows as $row) {
            $upsert->execute([$row['student_no'], $row['student_id'], $row['student_name'], $row['level'], $row['room'], $row['avatar'], $row['was_active'], $restoreYear]);
        }
        $setting = $pdo->prepare("INSERT INTO academic_year_settings (id, current_year, updated_at, updated_by) VALUES (1, ?, NOW(), ?)
            ON DUPLICATE KEY UPDATE current_year=VALUES(current_year), updated_at=NOW(), updated_by=VALUES(updated_by)");
        $setting->execute([$restoreYear, $adminName]);
        $audit = $pdo->prepare("INSERT INTO student_roster_imports
            (batch_id, from_year, to_year, imported_count, deactivated_count, club_members_reset, created_at, created_by)
            VALUES (?, ?, ?, ?, 0, 0, NOW(), ?)");
        $audit->execute([$safetyBatch, $currentYear, $restoreYear, count($backupRows), $adminName]);
        $pdo->commit();
        return ['success' => true, 'message' => "กู้คืนรายชื่อนักเรียนปี {$restoreYear} จำนวน " . count($backupRows) . ' คนแล้ว', 'safetyBatchId' => $safetyBatch];
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function getAdminLogs($academicYear, $semester, $pdo) {
    $deductions = [];
    $rewards = [];
    [$academicYear, $semester, $periodStart, $periodEnd] = academicPeriodBounds($academicYear, $semester, $pdo);

    $stmtD = $pdo->prepare("SELECT datetime, level, room, name, reason, points, teacher_name FROM deductions WHERE datetime BETWEEN ? AND ? ORDER BY datetime DESC");
    $stmtD->execute([$periodStart, $periodEnd]);
    while ($row = $stmtD->fetch()) {
        $deductions[] = [
            'date' => $row['datetime'],
            'classInfo' => $row['level'] . '/' . $row['room'],
            'stuName' => $row['name'],
            'reason' => $row['reason'],
            'points' => $row['points'],
            'teacher' => $row['teacher_name']
        ];
    }
    
    $stmtR = $pdo->prepare("SELECT datetime, level, room, name, reason, points, teacher_name FROM rewards WHERE datetime BETWEEN ? AND ? ORDER BY datetime DESC");
    $stmtR->execute([$periodStart, $periodEnd]);
    while ($row = $stmtR->fetch()) {
        $rewards[] = [
            'date' => $row['datetime'],
            'classInfo' => $row['level'] . '/' . $row['room'],
            'stuName' => $row['name'],
            'reason' => $row['reason'],
            'points' => $row['points'],
            'teacher' => $row['teacher_name']
        ];
    }
    
    return ['academicYear' => $academicYear, 'semester' => $semester, 'deductions' => $deductions, 'rewards' => $rewards];
}

function uploadGroupVolunteerPhoto($studentsArray, $base64Data, $filename, $teacherName, $hours, $activityDesc, $pdo) {
    try {
        // Create upload dir if not exists
        if (!file_exists(UPLOAD_DIR)) {
            mkdir(UPLOAD_DIR, 0755, true);
        }
        
        // Decode base64
        $pos = strpos($base64Data, 'base64,');
        if ($pos !== false) {
            $dataStr = substr($base64Data, $pos + 7);
        } else {
            $dataStr = $base64Data;
        }
        $imgBytes = base64_decode($dataStr);
        
        // Unique file name to prevent collision
        $uniqueFilename = time() . '_' . $filename;
        $filePath = UPLOAD_DIR . '/' . $uniqueFilename;
        file_put_contents($filePath, $imgBytes);
        
        // Get public URL path
        $imgUrl = 'uploads/' . $uniqueFilename;
        
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("INSERT INTO volunteer (datetime, student_id, name, level, room, image_url, teacher_name, hours, activity) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $now = date('Y-m-d H:i:s');
        foreach ($studentsArray as $stu) {
            $stmt->execute([
                $now,
                trim($stu['id']),
                trim($stu['name']),
                trim($stu['level']),
                trim($stu['room']),
                $imgUrl,
                trim($teacherName),
                $hours,
                trim($activityDesc)
            ]);
        }
        
        $pdo->commit();
        return ['success' => true, 'message' => 'บันทึกข้อมูลจิตอาสาทั้ง ' . count($studentsArray) . ' คน เรียบร้อยแล้ว!'];
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return ['success' => false, 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()];
    }
}

function getVolunteerGallery($teacherName, $isAdmin, $advisoryRoom, $viewType, $filterLevel, $filterRoom, $academicYear, $semester, $pdo) {
    $sql = "SELECT datetime, student_id, name, level, room, image_url, teacher_name, hours, activity FROM volunteer";
    $params = [];
    [$academicYear, $semester, $periodStart, $periodEnd] = academicPeriodBounds($academicYear, $semester, $pdo);
    $where = ["datetime BETWEEN ? AND ?"];
    $params = [$periodStart, $periodEnd];
    
    if ($viewType === 'dashboard') {
        if (!empty($advisoryRoom)) {
            $parts = explode('/', $advisoryRoom);
            if (count($parts) === 2) {
                $where[] = "level = ? AND room = ?";
                $params[] = $parts[0];
                $params[] = $parts[1];
            } else {
                return [];
            }
        } else {
            return [];
        }
    } elseif ($viewType === 'gallery') {
        if (!$isAdmin) {
            $where[] = "teacher_name = ?";
            $params[] = $teacherName;
        }
    } elseif ($viewType === 'admin_gallery') {
        if ($isAdmin) {
            if (!empty($filterLevel)) {
                $where[] = "level = ?";
                $params[] = $filterLevel;
            }
            if (!empty($filterRoom)) {
                $where[] = "room = ?";
                $params[] = $filterRoom;
            }
        } else {
            return [];
        }
    } else {
        if (!$isAdmin) {
            $where[] = "(teacher_name = ? OR (level = ? AND room = ?))";
            $parts = explode('/', $advisoryRoom);
            $params[] = $teacherName;
            $params[] = $parts[0] ?? '';
            $params[] = $parts[1] ?? '';
        }
    }
    
    if (!empty($where)) {
        $sql .= " WHERE " . implode(" AND ", $where);
    }
    
    // Join with students to get student numbers for ordering in admin_gallery
    if ($viewType === 'admin_gallery' && !empty($filterLevel) && !empty($filterRoom)) {
        $sql = "SELECT v.*, s.no FROM volunteer v LEFT JOIN students s ON v.student_id = s.student_id";
        if (!empty($where)) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }
        $sql .= " ORDER BY s.no ASC, v.datetime DESC";
    } else {
        $sql .= " ORDER BY datetime DESC";
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    $results = [];
    while ($row = $stmt->fetch()) {
        $results[] = [
            'datetime' => $row['datetime'],
            'stuId' => $row['student_id'],
            'stuName' => $row['name'],
            'classInfo' => $row['level'] . '/' . $row['room'],
            'imgUrl' => $row['image_url'], // Relative URL to server root
            'uploader' => $row['teacher_name'],
            'hours' => $row['hours'],
            'activity' => $row['activity'],
            'no' => $row['no'] ?? 999
        ];
    }
    
    return $results;
}

function resetNewTermData($tablesArray, $teacherName, $pdo) {
    $allowedTables = [
        'attendance' => 'ประวัติการเช็คชื่อ',
        'deductions' => 'ประวัติการตัดคะแนน',
        'rewards' => 'ประวัติการบวกคะแนนความดี',
        'clubs' => 'รายชื่อชุมนุมทั้งหมด',
        'club_admins' => 'รายชื่อครูผู้ดูแลชุมนุม',
        'club_members' => 'ข้อมูลการเลือกชุมนุม',
        'club_attendance' => 'ประวัติการเช็คชื่อชุมนุม',
        'volunteer' => 'ประวัติกิจกรรมจิตอาสา',
        'face_arrivals' => 'ประวัติการสแกนใบหน้า'
    ];

    if (!is_array($tablesArray) || empty($tablesArray)) {
        return ['success' => false, 'message' => 'ไม่มีรายการที่ถูกเลือก'];
    }

    $cleared = [];
    try {
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
        foreach ($tablesArray as $table) {
            if (isset($allowedTables[$table])) {
                $pdo->exec("TRUNCATE TABLE `{$table}`");
                $cleared[] = $allowedTables[$table];
            }
        }
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

        return [
            'success' => true,
            'message' => 'ล้างข้อมูล ' . count($cleared) . ' รายการ (' . implode(', ', $cleared) . ') เพื่อเริ่มภาคเรียนใหม่เรียบร้อยแล้ว!'
        ];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()];
    }
}

function getStudentsWithAttendance($level, $room, $date, $teacherName, $pdo) {
    if (!canTeacherAccess($teacherName, $level, $room, $pdo)) return [];
    $date = ensureDateInSelectedAcademicPeriod($date, $pdo, true);
    
    // Get all students in this class
    $stmt = $pdo->prepare("SELECT no, student_id, name, avatar FROM students WHERE is_active = 1 AND level = ? AND room = ? ORDER BY no ASC");
    $stmt->execute([$level, $room]);
    $students = $stmt->fetchAll();
    
    // Get attendance for this date
    $stmtAtt = $pdo->prepare("SELECT student_id, status FROM attendance WHERE date = ? AND level = ? AND room = ?");
    $stmtAtt->execute([$date, $level, $room]);
    $attMap = [];
    while ($row = $stmtAtt->fetch()) {
        $attMap[$row['student_id']] = $row['status'];
    }
    
    $results = [];
    foreach ($students as $s) {
        $results[] = [
            'no' => $s['no'],
            'id' => trim($s['student_id']),
            'name' => trim($s['name']),
            'avatar' => $s['avatar'] ?? '',
            'savedStatus' => $attMap[trim($s['student_id'])] ?? ""
        ];
    }
    return $results;
}

function saveAttendance($records, $level, $room, $date, $teacherName, $pdo) {
    if (!canTeacherAccess($teacherName, $level, $room, $pdo)) {
        throw new Exception("คุณไม่มีสิทธิ์บันทึกข้อมูลของห้องเรียนนี้");
    }
    
    if (empty($records)) {
        throw new Exception("ไม่พบข้อมูลนักเรียน กรุณาลองใหม่อีกครั้ง");
    }
    $date = ensureDateInSelectedAcademicPeriod($date, $pdo);
    
    $unselected = [];
    $validStatus = ["มาเรียน", "ลากิจ", "ลาป่วย", "มาสาย", "ขาดเรียน"];
    
    foreach ($records as $r) {
        $status = trim($r['status'] ?? '');
        if ($status === "" || !in_array($status, $validStatus)) {
            $unselected[] = "เลขที่ " . $r['no'];
        }
    }
    
    if (!empty($unselected)) {
        throw new Exception(implode(", ", $unselected));
    }
    
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("INSERT INTO attendance (date, level, room, no, student_id, name, status, teacher_name) VALUES (?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE status = VALUES(status), teacher_name = VALUES(teacher_name)");
        
        foreach ($records as $r) {
            $stmt->execute([
                $date,
                trim($level),
                trim($room),
                $r['no'],
                trim($r['id']),
                trim($r['name']),
                trim($r['status']),
                trim($teacherName)
            ]);
        }
        
        $pdo->commit();
        return "บันทึกข้อมูลสำเร็จ!";
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function getReportData($date, $pdo) {
    $date = ensureDateInSelectedAcademicPeriod($date, $pdo);
    // Totals by grade and room
    $stmtT = $pdo->query("SELECT level, room, COUNT(*) as c FROM students WHERE is_active = 1 GROUP BY level, room");
    $totalStudents = 0;
    $totals = ['overall' => 0, 'grades' => [], 'rooms' => []];
    
    while ($row = $stmtT->fetch()) {
        $g = trim($row['level']);
        $r = $g . '_' . trim($row['room']);
        $count = (int)$row['c'];
        
        $totalStudents += $count;
        $totals['grades'][$g] = ($totals['grades'][$g] ?? 0) + $count;
        $totals['rooms'][$r] = $count;
    }
    $totals['overall'] = $totalStudents;
    
    $report = [
        'overall' => ['มาเรียน' => 0, 'ลากิจ' => 0, 'ลาป่วย' => 0, 'มาสาย' => 0, 'ขาดเรียน' => 0, 'รวม' => 0, 'ทั้งหมด' => $totals['overall']],
        'grades' => [],
        'rooms' => []
    ];
    
    foreach ($totals['grades'] as $g => $count) {
        $report['grades'][$g] = ['มาเรียน' => 0, 'ลากิจ' => 0, 'ลาป่วย' => 0, 'มาสาย' => 0, 'ขาดเรียน' => 0, 'รวม' => 0, 'ทั้งหมด' => $count];
    }
    
    foreach ($totals['rooms'] as $r => $count) {
        $report['rooms'][$r] = ['มาเรียน' => 0, 'ลากิจ' => 0, 'ลาป่วย' => 0, 'มาสาย' => 0, 'ขาดเรียน' => 0, 'รวม' => 0, 'ทั้งหมด' => $count];
    }
    
    // Get actual attendance records for this date
    $stmtAtt = $pdo->prepare("SELECT level, room, status, COUNT(*) as c FROM attendance WHERE date = ? GROUP BY level, room, status");
    $stmtAtt->execute([$date]);
    
    while ($row = $stmtAtt->fetch()) {
        $g = trim($row['level']);
        $roomNum = trim($row['room']);
        $r = $g . '_' . $roomNum;
        $status = trim($row['status']);
        $count = (int)$row['c'];
        
        if (isset($report['overall'][$status])) {
            $report['overall'][$status] += $count;
        }
        $report['overall']['รวม'] += $count;
        
        if (isset($report['grades'][$g])) {
            if (isset($report['grades'][$g][$status])) $report['grades'][$g][$status] += $count;
            $report['grades'][$g]['รวม'] += $count;
        }
        
        if (isset($report['rooms'][$r])) {
            if (isset($report['rooms'][$r][$status])) $report['rooms'][$r][$status] += $count;
            $report['rooms'][$r]['รวม'] += $count;
        }
    }
    
    return $report;
}

function getIndividualSummary($level, $room, $startDate, $endDate, $teacherName, $pdo) {
    if (!canTeacherAccess($teacherName, $level, $room, $pdo)) return [];
    $startDate = ensureDateInSelectedAcademicPeriod($startDate, $pdo);
    $endDate = ensureDateInSelectedAcademicPeriod($endDate, $pdo);
    if ($startDate > $endDate) throw new InvalidArgumentException('วันที่เริ่มต้นต้องไม่เกินวันที่สิ้นสุด');
    
    // Get all students in this class
    $stmt = $pdo->prepare("SELECT no, student_id, name, avatar FROM students WHERE is_active = 1 AND level = ? AND room = ? ORDER BY no ASC");
    $stmt->execute([$level, $room]);
    $students = $stmt->fetchAll();
    
    $statsMap = [];
    foreach ($students as $s) {
        $statsMap[trim($s['student_id'])] = [
            'no' => $s['no'],
            'id' => trim($s['student_id']),
            'name' => trim($s['name']),
            'avatar' => $s['avatar'] ?? '',
            'มาเรียน' => 0,
            'ลากิจ' => 0,
            'ลาป่วย' => 0,
            'มาสาย' => 0,
            'ขาดเรียน' => 0,
            'รวม' => 0
        ];
    }
    
    // Fetch attendance summary
    $stmtAtt = $pdo->prepare("SELECT student_id, status, COUNT(*) as c FROM attendance WHERE level = ? AND room = ? AND date >= ? AND date <= ? GROUP BY student_id, status");
    $stmtAtt->execute([$level, $room, $startDate, $endDate]);
    
    while ($row = $stmtAtt->fetch()) {
        $sid = trim($row['student_id']);
        $status = trim($row['status']);
        $count = (int)$row['c'];
        
        if (isset($statsMap[$sid])) {
            if (isset($statsMap[$sid][$status])) {
                $statsMap[$sid][$status] = $count;
            }
            $statsMap[$sid]['รวม'] += $count;
        }
    }
    
    return array_values($statsMap);
}

function exportIndividualExcel($level, $room, $startDate, $endDate, $teacherName, $pdo) {
    if (!canTeacherAccess($teacherName, $level, $room, $pdo)) return ['error' => 'สิทธิ์ไม่ถูกต้อง'];
    $data = getIndividualSummary($level, $room, $startDate, $endDate, $teacherName, $pdo);
    
    $csvContent = "เลขที่,ชื่อ-สกุล,มาเรียน,ลากิจ,ลาป่วย,มาสาย,ขาดเรียน,รวมครั้งที่เช็ค\n";
    foreach ($data as $s) {
        $csvContent .= "{$s['no']},{$s['name']},{$s['มาเรียน']},{$s['ลากิจ']},{$s['ลาป่วย']},{$s['มาสาย']},{$s['ขาดเรียน']},{$s['รวม']}\n";
    }
    
    return [
        'content' => $csvContent,
        'filename' => "Report_Indv_{$level}_{$room}.csv"
    ];
}

function getAllStudentsForDeduct($pdo) {
    $stmt = $pdo->query("SELECT no, student_id, name, level, room FROM students WHERE is_active = 1 ORDER BY level ASC, room ASC, no ASC");
    $students = [];
    while ($row = $stmt->fetch()) {
        $students[] = [
            'no' => $row['no'],
            'id' => trim($row['student_id']),
            'name' => trim($row['name']),
            'level' => trim($row['level']),
            'room' => trim($row['room'])
        ];
    }
    return $students;
}

function saveMultipleDeductions($dateTime, $studentsArray, $reason, $points, $teacherName, $pdo) {
    if (empty($studentsArray)) return "ไม่มีนักเรียนที่ทำการบันทึก";
    
    if (!canTeacherAccess($teacherName, $studentsArray[0]['level'], $studentsArray[0]['room'], $pdo)) {
        throw new Exception("คุณไม่มีสิทธิ์บันทึกการตัดคะแนนของห้องเรียนนี้");
    }
    
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("INSERT INTO deductions (datetime, level, room, student_id, name, reason, points, teacher_name) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        
        foreach ($studentsArray as $s) {
            $stmt->execute([
                $dateTime,
                trim($s['level']),
                trim($s['room']),
                trim($s['id']),
                trim($s['name']),
                trim($reason),
                $points,
                trim($teacherName)
            ]);
        }
        
        $pdo->commit();
        return "บันทึกการตัดคะแนนนักเรียน " . count($studentsArray) . " คน สำเร็จ!";
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function getRoomDeductionReport($level, $room, $teacherName, $academicYear, $semester, $pdo) {
    if (!canTeacherAccess($teacherName, $level, $room, $pdo)) return [];
    
    // Get all students
    $stmt = $pdo->prepare("SELECT no, student_id, name FROM students WHERE is_active = 1 AND level = ? AND room = ? ORDER BY no ASC");
    $stmt->execute([$level, $room]);
    $students = $stmt->fetchAll();
    
    $deductMap = [];
    foreach ($students as $s) {
        $deductMap[trim($s['student_id'])] = [
            'no' => $s['no'],
            'id' => trim($s['student_id']),
            'name' => trim($s['name']),
            'deducted' => 0,
            'remaining' => 100
        ];
    }
    
    // Query deductions
    [, , $periodStart, $periodEnd] = academicPeriodBounds($academicYear, $semester, $pdo);
    $stmtD = $pdo->prepare("SELECT student_id, SUM(points) as pts FROM deductions WHERE level = ? AND room = ? AND datetime BETWEEN ? AND ? GROUP BY student_id");
    $stmtD->execute([$level, $room, $periodStart, $periodEnd]);
    
    while ($row = $stmtD->fetch()) {
        $sid = trim($row['student_id']);
        $pts = (int)$row['pts'];
        if (isset($deductMap[$sid])) {
            $deductMap[$sid]['deducted'] = $pts;
            $deductMap[$sid]['remaining'] = 100 - $pts;
        }
    }
    
    return array_values($deductMap);
}

// ----------------------------------------------------
// PDF GENERATION WITH print-friendly HTML INTERCEPTION
// ----------------------------------------------------

function getLogoBase64() {
    // Default logo path or external link
    return "logo.png";
}

function getPdfHeader($title, $isCompact = false) {
    $logoUrl = getLogoBase64();
    if ($isCompact) {
        return "
        <style>
        @import url('https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;600;700&display=swap');
        body { font-family: 'Sarabun', Tahoma, sans-serif; font-size: 10.5pt; padding: 0px 5px 0px 5px; color: #000; background-color: #fff; } 
        h2 { font-size: 16pt; font-weight: bold; margin: 0; text-align: center; } 
        h3 { font-size: 11pt; margin: 2px 0 1px 0; text-align: center; font-weight: bold; } 
        h4 { font-size: 10.5pt; font-weight: bold; margin: 3px 0 6px 0; text-align: center; } 
        p { font-size: 10.5pt; margin: 1px 0 2px 0; text-align: center; } 
        .header-table { width: 100%; border-collapse: collapse; margin-top: -3px; margin-bottom: 2px; } 
        .header-table td { border: none !important; padding: 0; } 
        .logo { width: 62px; height: auto; display: block; } 
        table { width: 100%; border-collapse: collapse; margin-top: 3px; margin-bottom: 2px; page-break-inside: auto; } 
        tr { page-break-inside: avoid; }
        .signature-section { page-break-inside: avoid; }
        th, td { border: 0.5px solid #555; text-align: center; font-size: 10pt; line-height: 1.2; font-family: 'Sarabun', sans-serif; vertical-align: middle; } 
        th, th * { background-color: #f2f2f2; font-weight: bold !important; border: 0.5px solid #444; padding: 3px 2px; } 
        td, td * { font-weight: normal !important; padding: 1px 2px; }
        .total-row td, .total-row td * { font-weight: bold !important; padding: 1.5px 2px; }
        </style>
        <table class='header-table'>
          <tr>
            <td style='width: 15%; text-align: left; vertical-align: middle;'><img src='{$logoUrl}' class='logo'></td>
            <td style='width: 70%; text-align: center; vertical-align: middle;'><h2 style='font-size: 16pt; font-weight: bold;'>{$title}</h2></td>
            <td style='width: 15%;'></td>
          </tr>
        </table>";
    } else {
        return "
        <style>
        @import url('https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;600;700&display=swap');
        body { font-family: 'Sarabun', Tahoma, sans-serif; font-size: 10.5pt; padding: 0px 5px 0px 5px; color: #000; background-color: #fff; line-height: 1.25; } 
        h2 { font-size: 16pt; font-weight: bold; margin: 0 0 2px 0; text-align: center; } 
        h3 { font-size: 11.5pt; margin: 3px 0 2px 0; text-align: center; font-weight: bold; } 
        h4 { font-size: 10.5pt; font-weight: bold; margin: 3px 0 4px 0; text-align: center; } 
        p { font-size: 10.5pt; margin: 2px 0 6px 0; text-align: center; } 
        .header-table { width: 100%; border-collapse: collapse; margin-top: -3px; margin-bottom: 2px; } 
        .header-table td { border: none !important; padding: 0; } 
        .logo { width: 62px; height: auto; display: block; } 
        table { width: 100%; border-collapse: collapse; margin-top: 4px; margin-bottom: 3px; page-break-inside: auto; } 
        tr { page-break-inside: avoid; }
        .signature-section { page-break-inside: avoid; }
        th, td { border: 0.5px solid #555; padding: 1.5px 4px; text-align: center; font-size: 10.5pt; line-height: 1.2; vertical-align: middle; } 
        th, th * { background-color: #f2f2f2; font-weight: bold !important; border: 0.5px solid #444; padding: 2.5px 4px; } 
        td, td * { font-weight: normal !important; }
        .total-row td, .total-row td * { font-weight: bold !important; }
        td.text-left, td[style*='text-align:left'] { text-align: left !important; padding-left: 6px !important; }
        </style>
        <table class='header-table'>
          <tr>
            <td style='width: 15%; text-align: left; vertical-align: middle;'><img src='{$logoUrl}' class='logo'></td>
            <td style='width: 70%; text-align: center; vertical-align: middle;'><h2 style='font-size: 16pt; font-weight: bold;'>{$title}</h2></td>
            <td style='width: 15%;'></td>
          </tr>
        </table>";
    }
}

function getSignatures() {
    $filePath = __DIR__ . '/signatures.json';
    if (file_exists($filePath)) {
        $data = json_decode(file_get_contents($filePath), true);
        if ($data) {
            return $data;
        }
    }
    
    // Default fallback from config
    return defined('SIGNATURES') ? SIGNATURES : [
        'officer' => ['name' => 'นางสาวกนกนาถ สุทธิสถิตย์', 'position' => 'เจ้าหน้าที่ระบบดูแลช่วยเหลือนักเรียน'],
        'deputy' => ['name' => 'นายไชยวัฒน์ บุญมี', 'position' => 'รองผู้อำนวยการกลุ่มบริหารงานทั่วไป'],
        'director' => ['name' => 'นางสาวมณฑาทิพย์ เสาวคนธ์', 'position' => 'ผู้อำนวยการโรงเรียนมกุฎเมืองราชวิทยาลัย']
    ];
}

function saveSignatures($data) {
    if (!isset($data['officer']) || !isset($data['deputy']) || !isset($data['director'])) {
        return ['success' => false, 'message' => 'ข้อมูลไม่ครบถ้วน'];
    }
    
    $filePath = __DIR__ . '/signatures.json';
    $result = file_put_contents($filePath, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    if ($result === false) {
        return ['success' => false, 'message' => 'ไม่สามารถเขียนไฟล์ตั้งค่าได้'];
    }
    return ['success' => true, 'message' => 'บันทึกรายชื่อผู้ลงนามเรียบร้อยแล้ว'];
}

function getSignatureHTML($isCompact = false) {
    $sig = getSignatures();

    if ($isCompact) {
        return '
        <div class="signature-section" style="page-break-inside: avoid; margin-top: 12px;">
          <div class="signature-title" style="text-align: center; font-size: 10.5pt; margin-top: 1px; margin-bottom: 25px;">
            เสนอ ผู้อำนวยการโรงเรียนมกุฎเมืองราชวิทยาลัย เพื่อทราบ
          </div>
          <table style="width: 100%; border-collapse: collapse; border: none; margin-top: 1px; table-layout: fixed;">
            <tr>
              <td style="width: 33.33%; text-align: center; border: none; font-size: 10pt; line-height: 1.35; font-family: \'Sarabun\', sans-serif; padding: 0px 2px; white-space: nowrap;">
                <div style="margin-bottom: 5px;">ลงชื่อ........................................</div>
                <div>(' . $sig['officer']['name'] . ')</div>
                <div>' . $sig['officer']['position'] . '</div>
              </td>
              <td style="width: 33.33%; text-align: center; border: none; font-size: 10pt; line-height: 1.35; font-family: \'Sarabun\', sans-serif; padding: 0px 2px; white-space: nowrap;">
                <div style="margin-bottom: 5px;">ลงชื่อ........................................</div>
                <div>(' . $sig['deputy']['name'] . ')</div>
                <div>' . $sig['deputy']['position'] . '</div>
              </td>
              <td style="width: 33.33%; text-align: center; border: none; font-size: 10pt; line-height: 1.35; font-family: \'Sarabun\', sans-serif; padding: 0px 2px; white-space: nowrap;">
                <div style="margin-bottom: 5px;">ลงชื่อ........................................</div>
                <div>(' . $sig['director']['name'] . ')</div>
                <div>' . $sig['director']['position'] . '</div>
              </td>
            </tr>
          </table>
        </div>';
    } else {
        return '
        <div class="signature-section" style="page-break-inside: avoid; margin-top: 25px;">
          <div class="signature-title" style="text-align: center; font-size: 14pt; font-weight: bold; margin-bottom: 20px;">
            เสนอ ผู้อำนวยการโรงเรียนมกุฎเมืองราชวิทยาลัย เพื่อทราบ
          </div>
          <table style="width: 100%; border-collapse: collapse; border: none; margin-top: 15px; table-layout: fixed;">
            <tr>
              <td style="width: 33.33%; text-align: center; border: none; font-size: 13pt; line-height: 1.5; font-family: \'Sarabun\', sans-serif; padding: 5px 10px; white-space: nowrap;">
                <div style="margin-bottom: 20px;">ลงชื่อ........................................</div>
                <div style="margin-bottom: 4px;">(' . $sig['officer']['name'] . ')</div>
                <div>' . $sig['officer']['position'] . '</div>
              </td>
              <td style="width: 33.33%; text-align: center; border: none; font-size: 13pt; line-height: 1.5; font-family: \'Sarabun\', sans-serif; padding: 5px 10px; white-space: nowrap;">
                <div style="margin-bottom: 20px;">ลงชื่อ........................................</div>
                <div style="margin-bottom: 4px;">(' . $sig['deputy']['name'] . ')</div>
                <div>' . $sig['deputy']['position'] . '</div>
              </td>
              <td style="width: 33.33%; text-align: center; border: none; font-size: 13pt; line-height: 1.5; font-family: \'Sarabun\', sans-serif; padding: 5px 10px; white-space: nowrap;">
                <div style="margin-bottom: 20px;">ลงชื่อ........................................</div>
                <div style="margin-bottom: 4px;">(' . $sig['director']['name'] . ')</div>
                <div>' . $sig['director']['position'] . '</div>
              </td>
            </tr>
          </table>
        </div>';
    }
}

function generateAdvancedPDF($date, $options, $preloadedData, $pdo) {
    $reportData = $preloadedData ?: getReportData($date, $pdo);
    if (isset($reportData['error'])) return $reportData;
    if ($reportData['overall']['รวม'] === 0) return ['error' => 'ไม่มีข้อมูลการเช็คชื่อสำหรับสร้างรายงานในวันนี้'];
    
    $ov = $reportData['overall'];
    $html = '<html><head>' . getPdfHeader("โรงเรียนมกุฎเมืองราชวิทยาลัย", true) . '</head><body>';
    $html .= '<h3>รายงานสรุปผลเวลาเรียน (ภาพรวม)</h3><p style="text-align:center;"><b>ประจำวันที่:</b> ' . $date . '</p>';
    
    if ($options['incOverall'] ?? false) {
        $html .= '<h4>สถิติภาพรวมทั้งโรงเรียน</h4><table><thead><tr><th>มาเรียน</th><th>ลากิจ</th><th>ลาป่วย</th><th>มาสาย</th><th>ขาดเรียน</th><th>เช็คชื่อแล้ว</th><th>นักเรียนทั้งหมด</th></tr></thead><tbody><tr><td>' . $ov['มาเรียน'] . '</td><td>' . $ov['ลากิจ'] . '</td><td>' . $ov['ลาป่วย'] . '</td><td>' . $ov['มาสาย'] . '</td><td style="color:red;">' . $ov['ขาดเรียน'] . '</td><td>' . $ov['รวม'] . '</td><td>' . $ov['ทั้งหมด'] . '</td></tr></tbody></table>';
    }
    
    if ($options['incGrades'] ?? false) {
        $html .= "<h4>สรุประดับชั้นเรียน</h4><table><thead><tr><th>ระดับชั้น</th><th>มาเรียน</th><th>ลากิจ</th><th>ลาป่วย</th><th>มาสาย</th><th>ขาดเรียน</th><th>เช็คชื่อแล้ว</th><th>นักเรียนทั้งหมด</th></tr></thead><tbody>";
        uksort($reportData['grades'], 'strnatcmp');
        foreach ($reportData['grades'] as $g => $d) {
            $html .= '<tr><td>' . $g . '</td><td>' . $d['มาเรียน'] . '</td><td>' . $d['ลากิจ'] . '</td><td>' . $d['ลาป่วย'] . '</td><td>' . $d['มาสาย'] . '</td><td style="color:red;">' . $d['ขาดเรียน'] . '</td><td><b>' . $d['รวม'] . '</b></td><td><b>' . $d['ทั้งหมด'] . '</b></td></tr>';
        }
        $html .= '<tr style="background-color: #f2f2f2; font-weight: bold;"><td>รวมทั้งหมด</td><td>' . $ov['มาเรียน'] . '</td><td>' . $ov['ลากิจ'] . '</td><td>' . $ov['ลาป่วย'] . '</td><td>' . $ov['มาสาย'] . '</td><td style="color:red;">' . $ov['ขาดเรียน'] . '</td><td>' . $ov['รวม'] . '</td><td>' . $ov['ทั้งหมด'] . '</td></tr></tbody></table>';
    }
    
    if ($options['incRooms'] ?? false) {
        $html .= '<h4>สรุปรายห้องเรียน</h4><table><thead><tr><th>ห้องเรียน</th><th>มาเรียน</th><th>ลากิจ</th><th>ลาป่วย</th><th>มาสาย</th><th>ขาดเรียน</th><th>เช็คชื่อแล้ว</th><th>นักเรียนทั้งหมด</th></tr></thead><tbody>';
        uksort($reportData['rooms'], 'strnatcmp');
        foreach ($reportData['rooms'] as $r => $d) {
            $displayName = str_replace("_", "/", $r);
            $html .= '<tr><td>' . $displayName . '</td><td>' . $d['มาเรียน'] . '</td><td>' . $d['ลากิจ'] . '</td><td>' . $d['ลาป่วย'] . '</td><td>' . $d['มาสาย'] . '</td><td style="color:red;">' . $d['ขาดเรียน'] . '</td><td><b>' . $d['รวม'] . '</b></td><td><b>' . $d['ทั้งหมด'] . '</b></td></tr>';
        }
        $html .= '<tr style="background-color: #f2f2f2; font-weight: bold;"><td>รวมทั้งหมด</td><td>' . $ov['มาเรียน'] . '</td><td>' . $ov['ลากิจ'] . '</td><td>' . $ov['ลาป่วย'] . '</td><td>' . $ov['มาสาย'] . '</td><td style="color:red;">' . $ov['ขาดเรียน'] . '</td><td>' . $ov['รวม'] . '</td><td>' . $ov['ทั้งหมด'] . '</td></tr></tbody></table>';
    }
    
    $html .= getSignatureHTML(true) . '</body></html>';
    
    return ['html' => $html, 'filename' => "Overall_Report_" . $date . ".pdf"];
}

function exportOverallExcel($date, $options, $preloadedData, $pdo) {
    $reportData = $preloadedData ?: getReportData($date, $pdo);
    if (isset($reportData['error'])) return $reportData;
    if ($reportData['overall']['รวม'] === 0) return ['error' => 'ไม่มีข้อมูลการเช็คชื่อ'];
    
    $ov = $reportData['overall'];
    $csvContent = "โรงเรียนมกุฎเมืองราชวิทยาลัย\nรายงานสรุปผลเวลาเรียน (ภาพรวม)\nประจำวันที่: " . $date . "\n\n";
    
    if ($options['incOverall'] ?? false) {
        $csvContent .= "สถิติภาพรวมทั้งโรงเรียน\nมาเรียน,ลากิจ,ลาป่วย,มาสาย,ขาดเรียน,เช็คชื่อแล้ว,นักเรียนทั้งหมด\n";
        $csvContent .= "{$ov['มาเรียน']},{$ov['ลากิจ']},{$ov['ลาป่วย']},{$ov['มาสาย']},{$ov['ขาดเรียน']},{$ov['รวม']},{$ov['ทั้งหมด']}\n\n";
    }
    
    if ($options['incGrades'] ?? false) {
        $csvContent .= "สรุประดับชั้นเรียน\nระดับชั้น,มาเรียน,ลากิจ,ลาป่วย,มาสาย,ขาดเรียน,เช็คชื่อแล้ว,นักเรียนทั้งหมด\n";
        foreach ($reportData['grades'] as $g => $d) {
            $csvContent .= "{$g},{$d['มาเรียน']},{$d['ลากิจ']},{$d['ลาป่วย']},{$d['มาสาย']},{$d['ขาดเรียน']},{$d['รวม']},{$d['ทั้งหมด']}\n";
        }
        $csvContent .= "รวมทั้งหมด,{$ov['มาเรียน']},{$ov['ลากิจ']},{$ov['ลาป่วย']},{$ov['มาสาย']},{$ov['ขาดเรียน']},{$ov['รวม']},{$ov['ทั้งหมด']}\n\n";
    }
    
    if ($options['incRooms'] ?? false) {
        $csvContent .= "สรุปรายห้องเรียน\nห้องเรียน,มาเรียน,ลากิจ,ลาป่วย,มาสาย,ขาดเรียน,เช็คชื่อแล้ว,นักเรียนทั้งหมด\n";
        uksort($reportData['rooms'], 'strnatcmp');
        foreach ($reportData['rooms'] as $r => $d) {
            $displayName = str_replace("_", "/", $r);
            $csvContent .= "{$displayName},{$d['มาเรียน']},{$d['ลากิจ']},{$d['ลาป่วย']},{$d['มาสาย']},{$d['ขาดเรียน']},{$d['รวม']},{$d['ทั้งหมด']}\n";
        }
        $csvContent .= "รวมทั้งหมด,{$ov['มาเรียน']},{$ov['ลากิจ']},{$ov['ลาป่วย']},{$ov['มาสาย']},{$ov['ขาดเรียน']},{$ov['รวม']},{$ov['ทั้งหมด']}\n\n";
    }
    
    $csvContent .= "\n\n,ลงชื่อ...................................................,ลงชื่อ...................................................,ลงชื่อ...................................................\n";
    $csvContent .= ",(นางสาวกนกนาถ สุทธิสถิตย์),(นายไชยวัฒน์ บุญมี),(นางสาวมณฑาทิพย์ เสาวคนธ์)\n";
    $csvContent .= ",เจ้าหน้าที่ระบบดูแลช่วยเหลือนักเรียน,รองผู้อำนวยการกลุ่มบริหารงานทั่วไป,ผู้อำนวยการโรงเรียนมกุฎเมืองราชวิทยาลัย\n";
    
    return [
        'content' => $csvContent,
        'filename' => "Overall_Report_" . $date . ".csv"
    ];
}

function generateRoomPDF($level, $room, $date, $teacherName, $pdo) {
    if (!canTeacherAccess($teacherName, $level, $room, $pdo)) return ['error' => 'คุณไม่มีสิทธิ์ดาวน์โหลดรายงานห้องเรียนนี้'];
    
    $stmt = $pdo->prepare("SELECT * FROM attendance WHERE level = ? AND room = ? AND date = ? ORDER BY no ASC");
    $stmt->execute([$level, $room, $date]);
    $data = $stmt->fetchAll();
    
    if (empty($data)) return ['error' => 'ไม่มีข้อมูลของห้องนี้ในวันที่ระบุ'];
    
    $stats = ['มาเรียน' => 0, 'ลากิจ' => 0, 'ลาป่วย' => 0, 'มาสาย' => 0, 'ขาดเรียน' => 0, 'รวม' => 0];
    $tableRows = "";
    
    foreach ($data as $row) {
        $status = trim($row['status']);
        if (isset($stats[$status])) $stats[$status]++;
        $stats['รวม']++;
        
        $statusColor = $status === 'ขาดเรียน' ? 'color: #dc3545;' : ($status === 'มาเรียน' ? 'color: #198754;' : '');
        $tableRows .= "<tr><td>{$row['no']}</td><td>{$row['student_id']}</td><td class='text-left' style='text-align:left; padding-left:8px;'>{$row['name']}</td><td style='{$statusColor}'>{$status}</td></tr>";
    }
    
    $html = "<html><head>" . getPdfHeader("โรงเรียนมกุฎเมืองราชวิทยาลัย") . "</head><body><h3>รายงานสรุปเวลาเรียนประจำวัน ({$date})</h3><p><b>ระดับชั้น:</b> {$level}/{$room} &nbsp;&nbsp; <b>รวม:</b> {$stats['รวม']} คน</p><table><thead><tr><th style='width:8%;'>เลขที่</th><th style='width:17%;'>รหัส</th><th style='width:50%;'>ชื่อ-สกุล</th><th style='width:25%;'>สถานะ</th></tr></thead><tbody>{$tableRows}</tbody></table></body></html>";
    
    return ['html' => $html, 'filename' => "Room_Report_{$level}_{$room}_{$date}.pdf"];
}

function generateIndividualPDF($level, $room, $startDate, $endDate, $teacherName, $pdo) {
    if (!canTeacherAccess($teacherName, $level, $room, $pdo)) return ['error' => 'คุณไม่มีสิทธิ์ดาวน์โหลดรายงานห้องเรียนนี้'];
    
    $data = getIndividualSummary($level, $room, $startDate, $endDate, $teacherName, $pdo);
    if (empty($data)) return ['error' => 'ไม่พบข้อมูล'];
    
    $tableRows = "";
    foreach ($data as $s) {
        $tableRows .= "<tr><td>{$s['no']}</td><td class='text-left' style='text-align:left; padding-left:8px;'>{$s['name']}</td><td style='color:green;'>{$s['มาเรียน']}</td><td>{$s['ลากิจ']}</td><td>{$s['ลาป่วย']}</td><td style='color:orange;'>{$s['มาสาย']}</td><td style='color:red;'>{$s['ขาดเรียน']}</td><td>{$s['รวม']}</td></tr>";
    }
    
    $html = "<html><head>" . getPdfHeader("โรงเรียนมกุฎเมืองราชวิทยาลัย") . "</head><body><h3>รายงานสรุปเวลาเรียนรายบุคคล</h3><p><b>ระดับชั้น:</b> {$level}/{$room} &nbsp;&nbsp; <b>ตั้งแต่วันที่:</b> {$startDate} <b>ถึง</b> {$endDate}</p><table><thead><tr><th style='width:7%;'>เลขที่</th><th style='width:37%;'>ชื่อ-สกุล</th><th style='width:9%;'>มาเรียน</th><th style='width:9%;'>ลากิจ</th><th style='width:9%;'>ลาป่วย</th><th style='width:9%;'>สาย</th><th style='width:9%;'>ขาด</th><th style='width:11%;'>รวม</th></tr></thead><tbody>{$tableRows}</tbody></table></body></html>";
    
    return ['html' => $html, 'filename' => "Indv_Report_{$level}_{$room}.pdf"];
}

function generateDeductPDF($level, $room, $teacherName, $academicYear, $semester, $pdo) {
    if (!canTeacherAccess($teacherName, $level, $room, $pdo)) return ['error' => 'คุณไม่มีสิทธิ์เข้าถึงรายงานห้องเรียนนี้'];
    
    [$academicYear, $semester] = resolveAcademicPeriod($academicYear, $semester, $pdo);
    $data = getRoomDeductionReport($level, $room, $teacherName, $academicYear, $semester, $pdo);
    if (empty($data)) return ['error' => 'ไม่พบข้อมูลคะแนนในห้องที่เลือก'];
    
    $tableRows = "";
    foreach ($data as $s) {
        $tableRows .= "<tr><td>{$s['no']}</td><td class='text-left' style='text-align:left; padding-left:8px;'>{$s['name']}</td><td style='color:red;'>-{$s['deducted']}</td><td>{$s['remaining']}</td></tr>";
    }
    
    $html = "<html><head>" . getPdfHeader("โรงเรียนมกุฎเมืองราชวิทยาลัย") . "</head><body><h3>รายงานคะแนนความประพฤตินักเรียน</h3><p><b>ปีการศึกษา:</b> {$academicYear} <b>ภาคเรียน:</b> {$semester} &nbsp; <b>ระดับชั้น:</b> {$level}/{$room}</p><table><thead><tr><th style='width:15%;'>เลขที่</th><th style='width:45%;'>ชื่อ-สกุล</th><th style='width:20%;'>หักรวม</th><th style='width:20%;'>คะแนนคงเหลือ</th></tr></thead><tbody>{$tableRows}</tbody></table></body></html>";
    
    return ['html' => $html, 'filename' => "Deduct_Report_{$academicYear}_T{$semester}_{$level}_{$room}.pdf"];
}

function getMyLogs($tableName, $teacherName, $academicYear, $semester, $pdo) {
    // $tableName comes in as "Deductions" or "Rewards". Map to local SQL table name.
    $table = (strtolower($tableName) === 'rewards') ? 'rewards' : 'deductions';
    
    [, , $periodStart, $periodEnd] = academicPeriodBounds($academicYear, $semester, $pdo);
    $stmt = $pdo->prepare("SELECT datetime, level, room, name, reason, points FROM {$table} WHERE teacher_name = ? AND datetime BETWEEN ? AND ? ORDER BY datetime DESC LIMIT 30");
    $stmt->execute([$teacherName, $periodStart, $periodEnd]);
    
    $results = [];
    while ($row = $stmt->fetch()) {
        $results[] = [
            'datetime' => $row['datetime'],
            'stuName' => $row['name'] . " (" . $row['level'] . "/" . $row['room'] . ")",
            'reason' => $row['reason'],
            'points' => $row['points']
        ];
    }
    return $results;
}

function generateRewardPDF($level, $room, $teacherName, $academicYear, $semester, $pdo) {
    if (!canTeacherAccess($teacherName, $level, $room, $pdo)) return ['error' => 'คุณไม่มีสิทธิ์เข้าถึงรายงานห้องเรียนนี้'];
    
    [$academicYear, $semester] = resolveAcademicPeriod($academicYear, $semester, $pdo);
    $data = getRoomRewardReport($level, $room, $teacherName, $academicYear, $semester, $pdo);
    if (empty($data)) return ['error' => 'ไม่พบข้อมูลคะแนนในห้องที่เลือก'];
    
    $tableRows = "";
    foreach ($data as $s) {
        $tableRows .= "<tr><td>{$s['no']}</td><td class='text-left' style='text-align:left; padding-left:8px;'>{$s['name']}</td><td style='color:green;'>+{$s['total']}</td></tr>";
    }
    
    $html = "<html><head>" . getPdfHeader("โรงเรียนมกุฎเมืองราชวิทยาลัย") . "</head><body><h3>รายงานคะแนนความดีนักเรียนสะสม</h3><p><b>ปีการศึกษา:</b> {$academicYear} <b>ภาคเรียน:</b> {$semester} &nbsp; <b>ระดับชั้น:</b> {$level}/{$room}</p><table><thead><tr><th style='width:15%;'>เลขที่</th><th style='width:55%;'>ชื่อ-สกุล</th><th style='width:30%;'>คะแนนความดีสะสม</th></tr></thead><tbody>{$tableRows}</tbody></table></body></html>";
    
    return ['html' => $html, 'filename' => "Reward_Report_{$academicYear}_T{$semester}_{$level}_{$room}.pdf"];
}

function saveReward($dateTime, $level, $room, $stuId, $stuName, $reason, $points, $teacherName, $pdo) {
    if (!canTeacherAccess($teacherName, $level, $room, $pdo)) {
        throw new Exception("คุณไม่มีสิทธิ์บันทึกการบวกคะแนนความดีของห้องเรียนนี้");
    }
    
    $stmt = $pdo->prepare("INSERT INTO rewards (datetime, level, room, student_id, name, reason, points, teacher_name) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$dateTime, trim($level), trim($room), trim($stuId), trim($stuName), trim($reason), $points, trim($teacherName)]);
    
    return "บันทึกการบวกคะแนนเรียบร้อย โดยครู " . $teacherName . "!";
}

function getRoomRewardReport($level, $room, $teacherName, $academicYear, $semester, $pdo) {
    if (!canTeacherAccess($teacherName, $level, $room, $pdo)) return [];
    
    // Get all students
    $stmt = $pdo->prepare("SELECT no, student_id, name FROM students WHERE is_active = 1 AND level = ? AND room = ? ORDER BY no ASC");
    $stmt->execute([$level, $room]);
    $students = $stmt->fetchAll();
    
    $rewardMap = [];
    foreach ($students as $s) {
        $rewardMap[trim($s['student_id'])] = [
            'no' => $s['no'],
            'id' => trim($s['student_id']),
            'name' => trim($s['name']),
            'total' => 0
        ];
    }
    
    // Get rewards sum
    [, , $periodStart, $periodEnd] = academicPeriodBounds($academicYear, $semester, $pdo);
    $stmtR = $pdo->prepare("SELECT student_id, SUM(points) as pts FROM rewards WHERE level = ? AND room = ? AND datetime BETWEEN ? AND ? GROUP BY student_id");
    $stmtR->execute([$level, $room, $periodStart, $periodEnd]);
    
    while ($row = $stmtR->fetch()) {
        $sid = trim($row['student_id']);
        $pts = (int)$row['pts'];
        if (isset($rewardMap[$sid])) {
            $rewardMap[$sid]['total'] = $pts;
        }
    }
    
    return array_values($rewardMap);
}

function getAllRewardsRanked($academicYear, $semester, $pdo) {
    [, , $periodStart, $periodEnd] = academicPeriodBounds($academicYear, $semester, $pdo);
    $stmt = $pdo->prepare("SELECT student_id, name, level, room, SUM(points) as total FROM rewards WHERE datetime BETWEEN ? AND ? GROUP BY student_id, name, level, room ORDER BY total DESC");
    $stmt->execute([$periodStart, $periodEnd]);
    $results = [];
    while ($row = $stmt->fetch()) {
        $results[] = [
            'name' => $row['name'],
            'class' => $row['level'] . '/' . $row['room'],
            'total' => (int)$row['total']
        ];
    }
    return $results;
}

function getStudentHistoryForTeacher($stuId, $academicYear, $semester, $pdo) {
    $history = ['deductions' => [], 'rewards' => []];
    [$resolvedYear, $resolvedSemester, $periodStart, $periodEnd] = academicPeriodBounds($academicYear, $semester, $pdo);
    $history['academicYear'] = $resolvedYear;
    $history['semester'] = $resolvedSemester;

    $stmtD = $pdo->prepare("SELECT datetime, reason, points FROM deductions WHERE student_id = ? AND datetime BETWEEN ? AND ? ORDER BY datetime DESC");
    $stmtD->execute([$stuId, $periodStart, $periodEnd]);
    while ($row = $stmtD->fetch()) {
        $history['deductions'][] = [
            'date' => $row['datetime'],
            'reason' => $row['reason'],
            'points' => $row['points']
        ];
    }
    
    $stmtR = $pdo->prepare("SELECT datetime, reason, points FROM rewards WHERE student_id = ? AND datetime BETWEEN ? AND ? ORDER BY datetime DESC");
    $stmtR->execute([$stuId, $periodStart, $periodEnd]);
    while ($row = $stmtR->fetch()) {
        $history['rewards'][] = [
            'date' => $row['datetime'],
            'reason' => $row['reason'],
            'points' => $row['points']
        ];
    }
    
    return $history;
}

// ----------------------------------------------------
// CLUB MODULE FUNCTIONS
// ----------------------------------------------------

function getTeachersForClub($pdo) {
    $stmt = $pdo->query("SELECT name FROM users ORDER BY name ASC");
    $teachers = [];
    while ($row = $stmt->fetch()) {
        $teachers[] = trim($row['name']);
    }
    return $teachers;
}

function addClub($clubName, $teacherName, $limit, $targetClasses, $pdo) {
    try {
        $stmt = $pdo->prepare("INSERT INTO clubs (name, teacher, limit_seats, target_classes, status) VALUES (?, ?, ?, ?, 'เปิด')");
        $stmt->execute([trim($clubName), trim($teacherName), $limit, trim($targetClasses)]);
        return ['success' => true, 'message' => 'เปิดรับสมัครชุมนุมเรียบร้อย!'];
    } catch (Exception $e) {
        if ($e->getCode() == 23000) { // unique violation
            return ['success' => false, 'message' => 'ชื่อชุมนุมนี้มีในระบบแล้ว กรุณาใช้ชื่ออื่นครับ'];
        }
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function getAllClubsInfo($studentLevel, $studentRoom, $pdo) {
    // Return all clubs open, check target class filter
    $stmt = $pdo->query("SELECT c.*, (SELECT COUNT(*) FROM club_members m WHERE m.club_name = c.name) as current_members FROM clubs c WHERE c.status = 'เปิด'");
    $results = [];
    
    $checkClass = "ม." . trim($studentLevel) . "/" . trim($studentRoom);
    
    while ($row = $stmt->fetch()) {
        $target = trim($row['target_classes']);
        $cleanTarget = str_replace(' ', '', $target);
        
        if (empty($studentLevel) || $target === "รับทุกห้อง" || strpos($cleanTarget, $checkClass) !== false) {
            $results[] = [
                'name' => trim($row['name']),
                'teacher' => trim($row['teacher']),
                'limit' => (int)$row['limit_seats'],
                'targetClasses' => $target,
                'currentMembers' => (int)$row['current_members']
            ];
        }
    }
    return $results;
}

function getMyClubs($teacherName, $pdo) {
    $stmt = $pdo->prepare("SELECT name FROM clubs WHERE teacher LIKE ?");
    $stmt->execute(['%' . $teacherName . '%']);
    
    $myClubs = [];
    while ($row = $stmt->fetch()) {
        $myClubs[] = trim($row['name']);
    }
    return $myClubs;
}

function getClubMembers($clubName, $pdo) {
    $stmt = $pdo->prepare("SELECT student_id, name, level, room FROM club_members WHERE club_name = ?");
    $stmt->execute([$clubName]);
    
    $members = [];
    while ($row = $stmt->fetch()) {
        $members[] = [
            'id' => trim($row['student_id']),
            'name' => trim($row['name']),
            'level' => trim($row['level']),
            'room' => trim($row['room'])
        ];
    }
    return $members;
}

function getStudentById($id, $pdo) {
    // Get student info
    $stmt = $pdo->prepare("SELECT * FROM students WHERE is_active = 1 AND student_id = ?");
    $stmt->execute([trim($id)]);
    $student = $stmt->fetch();
    
    if (!$student) return null;
    
    $studentInfo = [
        'id' => trim($student['student_id']),
        'name' => trim($student['name']),
        'level' => trim($student['level']),
        'room' => trim($student['room']),
        'currentClub' => null,
        'clubTeacher' => null
    ];
    
    // Check if enrolled in club
    $stmtMem = $pdo->prepare("SELECT m.club_name, c.teacher FROM club_members m LEFT JOIN clubs c ON m.club_name = c.name WHERE m.student_id = ?");
    $stmtMem->execute([trim($id)]);
    $mem = $stmtMem->fetch();
    
    if ($mem) {
        $studentInfo['currentClub'] = trim($mem['club_name']);
        $studentInfo['clubTeacher'] = trim($mem['teacher'] ?? '');
    }
    
    return $studentInfo;
}

function getStudentClubData($id, $pdo) {
    $stmt = $pdo->prepare("SELECT * FROM students WHERE is_active = 1 AND student_id = ?");
    $stmt->execute([trim($id)]);
    $student = $stmt->fetch();
    
    if (!$student) {
        return ['success' => false, 'message' => 'ไม่พบข้อมูลนักเรียน รหัสนี้ในระบบ'];
    }
    
    $stuClass = trim($student['level']) . '/' . trim($student['room']);
    
    // Check if enrolled in club
    $stmtMem = $pdo->prepare("SELECT m.club_name, c.teacher FROM club_members m LEFT JOIN clubs c ON m.club_name = c.name WHERE m.student_id = ?");
    $stmtMem->execute([trim($id)]);
    $mem = $stmtMem->fetch();
    
    $studentData = [
        'id' => trim($student['student_id']),
        'name' => trim($student['name']),
        'level' => trim($student['level']),
        'room' => trim($student['room']),
        'avatar' => $student['avatar'] ?? '',
        'club' => $mem ? trim($mem['club_name']) : null,
        'advisor' => $mem ? trim($mem['teacher'] ?? '') : null
    ];
    
    // Get all open clubs
    $stmtClubs = $pdo->query("SELECT * FROM clubs ORDER BY name ASC");
    $allClubs = $stmtClubs->fetchAll();
    
    // Count members per club
    $stmtCounts = $pdo->query("SELECT club_name, COUNT(*) as cnt FROM club_members GROUP BY club_name");
    $countsMap = [];
    while ($row = $stmtCounts->fetch()) {
        $countsMap[$row['club_name']] = (int)$row['cnt'];
    }
    
    $availableClubs = [];
    foreach ($allClubs as $c) {
        $cStatus = trim($c['status'] ?? 'เปิด');
        if ($cStatus !== 'เปิด') continue;
        
        $target = trim($c['target_classes'] ?? 'รับทุกห้อง');
        
        // Filter by student's class (level/room or level)
        $isEligible = false;
        if ($target === 'รับทุกห้อง' || empty($target)) {
            $isEligible = true;
        } else {
            $allowedList = array_map('trim', explode(',', $target));
            if (in_array($stuClass, $allowedList) || in_array(trim($student['level']), $allowedList)) {
                $isEligible = true;
            }
        }
        
        if ($isEligible) {
            $currentCount = $countsMap[$c['name']] ?? 0;
            $availableClubs[] = [
                'name' => $c['name'],
                'advisor' => $c['teacher'],
                'current' => $currentCount,
                'max' => (int)$c['limit_seats'],
                'target_levels' => $target
            ];
        }
    }
    
    return [
        'success' => true,
        'student' => $studentData,
        'clubs' => $availableClubs
    ];
}

function studentSelectClub($stuId, $clubName, $pdo) {
    try {
        $pdo->beginTransaction();
        
        // Check student
        $stmtS = $pdo->prepare("SELECT * FROM students WHERE is_active = 1 AND student_id = ?");
        $stmtS->execute([trim($stuId)]);
        $student = $stmtS->fetch();
        if (!$student) {
            $pdo->rollBack();
            return ['success' => false, 'message' => 'ไม่พบข้อมูลนักเรียน'];
        }
        
        // Check if already selected
        $stmtCheck = $pdo->prepare("SELECT club_name FROM club_members WHERE student_id = ?");
        $stmtCheck->execute([trim($stuId)]);
        $existingClub = $stmtCheck->fetchColumn();
        if ($existingClub) {
            $pdo->rollBack();
            return ['success' => false, 'message' => "คุณได้ลงทะเบียนชุมนุม '{$existingClub}' ไปแล้ว"];
        }
        
        // Check club
        $stmtC = $pdo->prepare("SELECT * FROM clubs WHERE name = ? FOR UPDATE");
        $stmtC->execute([trim($clubName)]);
        $club = $stmtC->fetch();
        if (!$club) {
            $pdo->rollBack();
            return ['success' => false, 'message' => 'ไม่พบชุมนุมที่เลือก'];
        }
        
        if (trim($club['status']) !== 'เปิด') {
            $pdo->rollBack();
            return ['success' => false, 'message' => 'ชุมนุมนี้ปิดรับสมัครแล้ว'];
        }
        
        // Check count
        $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM club_members WHERE club_name = ?");
        $stmtCount->execute([trim($clubName)]);
        $currentCount = (int)$stmtCount->fetchColumn();
        $maxSeats = (int)$club['limit_seats'];
        
        if ($maxSeats > 0 && $currentCount >= $maxSeats) {
            $pdo->rollBack();
            return ['success' => false, 'message' => 'ขออภัย ชุมนุมนี้เต็มจำนวนแล้วครับ'];
        }
        
        // Insert
        $stmtIns = $pdo->prepare("INSERT INTO club_members (club_name, student_id, name, level, room) VALUES (?, ?, ?, ?, ?)");
        $stmtIns->execute([
            trim($clubName),
            trim($student['student_id']),
            trim($student['name']),
            trim($student['level']),
            trim($student['room'])
        ]);
        
        $pdo->commit();
        return ['success' => true, 'message' => 'ลงทะเบียนเลือกชุมนุมเรียบร้อยแล้ว!'];
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return ['success' => false, 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()];
    }
}

function submitClubApplication($stuId, $stuName, $level, $room, $clubName, $teacherName, $pdo) {
    try {
        $pdo->beginTransaction();
        
        // Lock and check limit
        $stmtLimit = $pdo->prepare("SELECT limit_seats FROM clubs WHERE name = ? FOR UPDATE");
        $stmtLimit->execute([$clubName]);
        $club = $stmtLimit->fetch();
        if (!$club) {
            $pdo->rollBack();
            return ['success' => false, 'message' => 'ไม่พบข้อมูลชุมนุมนี้'];
        }
        $limit = (int)$club['limit_seats'];
        
        // Count members
        $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM club_members WHERE club_name = ?");
        $stmtCount->execute([$clubName]);
        $currentCount = (int)$stmtCount->fetchColumn();
        
        if ($limit > 0 && $currentCount >= $limit) {
            $pdo->rollBack();
            return ['success' => false, 'message' => 'ขออภัย ชุมนุมนี้มีผู้สมัครเต็มจำนวนแล้วครับ!'];
        }
        
        // Check if student has club already
        $stmtCheck = $pdo->prepare("SELECT club_name FROM club_members WHERE student_id = ?");
        $stmtCheck->execute([$stuId]);
        $hasClub = $stmtCheck->fetchColumn();
        if ($hasClub) {
            $pdo->rollBack();
            return ['success' => false, 'message' => 'คุณได้สมัครชุมนุมไปแล้วครับ! (' . $hasClub . ')'];
        }
        
        // Insert member
        $stmtInsert = $pdo->prepare("INSERT INTO club_members (club_name, student_id, name, level, room) VALUES (?, ?, ?, ?, ?)");
        $stmtInsert->execute([$clubName, trim($stuId), trim($stuName), trim($level), trim($room)]);
        
        $pdo->commit();
        return ['success' => true, 'message' => 'สมัครชุมนุมสำเร็จเรียบร้อย!', 'teacher' => $teacherName];
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return ['success' => false, 'message' => 'ระบบกำลังหนาแน่น กรุณาลองใหม่อีกครั้งครับ: ' . $e->getMessage()];
    }
}

function getStudentsByClub($clubName, $pdo) {
    $stmt = $pdo->prepare("SELECT student_id as id, name, level, room FROM club_members WHERE club_name = ? ORDER BY level ASC, room ASC, student_id ASC");
    $stmt->execute([$clubName]);
    return $stmt->fetchAll();
}

function saveClubAttendance($clubName, $date, $records, $pdo) {
    $date = ensureDateInSelectedAcademicPeriod($date, $pdo);
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("INSERT INTO club_attendance (date, club_name, student_id, name, status) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE status = VALUES(status)");
        
        foreach ($records as $r) {
            $stmt->execute([
                $date,
                $clubName,
                trim($r['id']),
                trim($r['name']),
                trim($r['status'])
            ]);
        }
        
        $pdo->commit();
        return "บันทึกการเช็คชื่อชุมนุมสำเร็จ!";
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function getClubAttendanceReport($clubName, $academicYear, $semester, $pdo) {
    try {
        // Map members
        $stmtMem = $pdo->prepare("SELECT student_id, name, level, room FROM club_members WHERE club_name = ?");
        $stmtMem->execute([$clubName]);
        $members = $stmtMem->fetchAll();
        
        $reportMap = [];
        foreach ($members as $m) {
            $sid = trim($m['student_id']);
            $reportMap[$sid] = [
                'id' => $sid,
                'name' => trim($m['name']),
                'level' => trim($m['level']),
                'room' => trim($m['room']),
                'present' => 0,
                'absent' => 0,
                'total' => 0
            ];
        }
        
        // Fetch attendance
        [, , $periodStart, $periodEnd] = academicPeriodBounds($academicYear, $semester, $pdo);
        $stmtAtt = $pdo->prepare("SELECT student_id, name, status FROM club_attendance WHERE club_name = ? AND date BETWEEN ? AND ?");
        $stmtAtt->execute([$clubName, substr($periodStart, 0, 10), substr($periodEnd, 0, 10)]);
        
        while ($row = $stmtAtt->fetch()) {
            $sid = trim($row['student_id']);
            if (isset($reportMap[$sid])) {
                $status = trim($row['status']);
                $reportMap[$sid]['total']++;
                if (strpos($status, 'มา') !== false) {
                    $reportMap[$sid]['present']++;
                } elseif (strpos($status, 'ขาด') !== false) {
                    $reportMap[$sid]['absent']++;
                }
            }
        }
        
        $reportArray = array_values($reportMap);
        
        // Sort: level, room, student_id
        usort($reportArray, function($a, $b) {
            if ($a['level'] !== $b['level']) return strnatcmp($a['level'], $b['level']);
            if ($a['room'] !== $b['room']) return (int)$a['room'] - (int)$b['room'];
            return (int)$a['id'] - (int)$b['id'];
        });
        
        return ['success' => true, 'data' => $reportArray];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function getStudentsWithoutClub($pdo) {
    // Students NOT in club_members
    $stmt = $pdo->query("SELECT no, student_id, name, level, room FROM students WHERE is_active = 1 AND student_id NOT IN (SELECT student_id FROM club_members) ORDER BY level ASC, room ASC, no ASC");
    $result = [];
    while ($row = $stmt->fetch()) {
        $result[] = [
            'no' => $row['no'],
            'id' => trim($row['student_id']),
            'name' => trim($row['name']),
            'level' => trim($row['level']),
            'room' => trim($row['room'])
        ];
    }
    return $result;
}

function getAdminMembersByClub($clubName, $pdo) {
    try {
        $stmtClub = $pdo->prepare("SELECT teacher FROM clubs WHERE name = ?");
        $stmtClub->execute([$clubName]);
        $teacherName = $stmtClub->fetchColumn() ?: "ไม่ระบุ";
        
        $stmtMem = $pdo->prepare("SELECT student_id as id, name, level, room FROM club_members WHERE club_name = ? ORDER BY level ASC, room ASC, student_id ASC");
        $stmtMem->execute([$clubName]);
        $students = $stmtMem->fetchAll();
        
        return ['success' => true, 'teacher' => $teacherName, 'students' => $students];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function getAdminMembersByRoom($level, $room, $pdo) {
    try {
        // Roster
        $stmtStu = $pdo->prepare("SELECT no, student_id, name FROM students WHERE is_active = 1 AND level = ? AND room = ? ORDER BY no ASC");
        $stmtStu->execute([$level, $room]);
        $students = $stmtStu->fetchAll();
        
        // Club mapping
        $stmtClubs = $pdo->prepare("SELECT student_id, club_name FROM club_members WHERE level = ? AND room = ?");
        $stmtClubs->execute([$level, $room]);
        $clubMap = [];
        while ($row = $stmtClubs->fetch()) {
            $clubMap[trim($row['student_id'])] = trim($row['club_name']);
        }
        
        $result = [];
        foreach ($students as $s) {
            $sid = trim($s['student_id']);
            $result[] = [
                'no' => $s['no'],
                'id' => $sid,
                'name' => trim($s['name']),
                'club' => $clubMap[$sid] ?? "ไม่มีชุมนุม"
            ];
        }
        
        return ['success' => true, 'data' => $result];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function addClubAdminRole($username, $pdo) {
    try {
        requireAdminSession();
        $username = trim((string)$username);
        $check = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
        $check->execute([$username]);
        if (!(int)$check->fetchColumn()) throw new InvalidArgumentException('ไม่พบบัญชีครูที่เลือก');
        $stmt = $pdo->prepare("INSERT INTO club_admins (username) VALUES (?) ON DUPLICATE KEY UPDATE username = username");
        $stmt->execute([$username]);
        return ['success' => true, 'message' => "เพิ่มสิทธิ์ให้ครูท่านนี้เป็นผู้ดูแลระบบชุมนุมเรียบร้อยแล้ว!"];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Return a compact attendance calendar and consecutive-absence alerts for the
 * teacher's advisory room.  Dates are scoped to the selected academic period.
 */
function getAttendanceCalendar($teacherName, $advisoryRoom, $academicYear, $semester, $isAdmin, $pdo) {
    [$academicYear, $semester, $periodStart, $periodEnd] = academicPeriodBounds($academicYear, $semester, $pdo);
    $parts = explode('/', (string)$advisoryRoom, 2);
    $params = [$periodStart, $periodEnd];
    $where = 'a.date BETWEEN ? AND ?';
    if (!$isAdmin) {
        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            return ['success' => true, 'academicYear' => $academicYear, 'semester' => $semester, 'days' => [], 'alerts' => []];
        }
        $where .= ' AND a.level = ? AND a.room = ?';
        $params[] = $parts[0];
        $params[] = $parts[1];
    }
    $stmt = $pdo->prepare("SELECT a.date, a.student_id, a.name, a.level, a.room, a.status
        FROM attendance a WHERE {$where} ORDER BY a.date ASC, a.student_id ASC");
    $stmt->execute($params);
    $days = [];
    $absences = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $date = (string)$row['date'];
        if (!isset($days[$date])) $days[$date] = ['date' => $date, 'มาเรียน' => 0, 'มาสาย' => 0, 'ลากิจ' => 0, 'ลาป่วย' => 0, 'ขาดเรียน' => 0, 'เช็คชื่อแล้ว' => 0];
        $status = (string)$row['status'];
        if (array_key_exists($status, $days[$date])) $days[$date][$status]++;
        $days[$date]['เช็คชื่อแล้ว']++;
        if ($status === 'ขาดเรียน') {
            $sid = (string)$row['student_id'];
            if (!isset($absences[$sid])) $absences[$sid] = ['studentId' => $sid, 'name' => (string)$row['name'], 'level' => (string)$row['level'], 'room' => (string)$row['room'], 'dates' => []];
            $absences[$sid]['dates'][] = $date;
        }
    }
    $alerts = [];
    foreach ($absences as $student) {
        $dates = array_values(array_unique($student['dates']));
        $run = [];
        foreach ($dates as $date) {
            $prev = $run ? end($run) : null;
            $gap = $prev ? (int)((strtotime($date) - strtotime($prev)) / 86400) : 1;
            // Friday-to-Monday is still consecutive school-day absence.
            if (!$run || $gap <= 3) $run[] = $date; else $run = [$date];
            if (count($run) >= 3) {
                $alerts[] = ['studentId' => $student['studentId'], 'name' => $student['name'], 'level' => $student['level'], 'room' => $student['room'], 'from' => $run[0], 'to' => $date, 'days' => count($run)];
            }
        }
    }
    return ['success' => true, 'academicYear' => $academicYear, 'semester' => $semester, 'days' => array_values($days), 'alerts' => $alerts];
}

function addActivityAdminRole($username, $pdo) {
    try {
        requireAdminSession();
        ensureActivityAdminSchema($pdo);
        $username = trim((string)$username);
        $check = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
        $check->execute([$username]);
        if (!(int)$check->fetchColumn()) throw new InvalidArgumentException('ไม่พบบัญชีครูที่เลือก');
        $stmt = $pdo->prepare("INSERT INTO activity_admins (username) VALUES (?) ON DUPLICATE KEY UPDATE username = username");
        $stmt->execute([$username]);
        return ['success' => true, 'message' => 'เพิ่มสิทธิ์ให้ครูท่านนี้เป็นผู้ดูแลระบบกิจกรรมเรียบร้อยแล้ว กรุณาให้ครูออกจากระบบและเข้าสู่ระบบใหม่'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function checkIfClubAdmin($username, $pdo) {
    if (!$username) return false;
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM club_admins WHERE username = ?");
    $stmt->execute([trim($username)]);
    return ($stmt->fetchColumn() > 0);
}

function checkIfActivityAdmin($username, $pdo) {
    if (!$username) return false;
    ensureActivityAdminSchema($pdo);
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM activity_admins WHERE username = ?");
    $stmt->execute([trim($username)]);
    return ($stmt->fetchColumn() > 0);
}

function getAllClubsInfoWithStatus($pdo) {
    try {
        $stmt = $pdo->query("SELECT c.*, (SELECT COUNT(*) FROM club_members m WHERE m.club_name = c.name) as current_members FROM clubs c ORDER BY name ASC");
        $result = [];
        while ($row = $stmt->fetch()) {
            $result[] = [
                'name' => trim($row['name']),
                'teacher' => trim($row['teacher']),
                'limit' => (int)$row['limit_seats'],
                'targetClasses' => trim($row['target_classes']),
                'currentMembers' => (int)$row['current_members'],
                'status' => trim($row['status']),
                'rowIndex' => trim($row['name']) // Use club name as key in place of rowIndex
            ];
        }
        return $result;
    } catch (Exception $e) {
        return [];
    }
}

function toggleClubStatus($clubKey, $currentStatus, $pdo) {
    try {
        // Toggle status by club name instead of row index
        $newStatus = ($currentStatus === 'เปิด') ? 'ปิด' : 'เปิด';
        $stmt = $pdo->prepare("UPDATE clubs SET status = ? WHERE name = ?");
        $stmt->execute([$newStatus, $clubKey]);
        
        return ['success' => true, 'message' => "เปลี่ยนสถานะเป็น '" . $newStatus . "' เรียบร้อยแล้ว!"];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function saveClubAttendanceWithDate($clubName, $date, $records, $pdo) {
    $date = ensureDateInSelectedAcademicPeriod($date, $pdo);
    try {
        $pdo->beginTransaction();
        
        // Delete records on this date
        $stmtDelete = $pdo->prepare("DELETE FROM club_attendance WHERE club_name = ? AND date = ?");
        $stmtDelete->execute([$clubName, $date]);
        
        // Insert new records
        $stmtInsert = $pdo->prepare("INSERT INTO club_attendance (date, club_name, student_id, name, status) VALUES (?, ?, ?, ?, ?)");
        foreach ($records as $r) {
            $stmtInsert->execute([
                $date,
                $clubName,
                trim($r['id']),
                trim($r['name']),
                trim($r['status'])
            ]);
        }
        
        $pdo->commit();
        return "บันทึก/อัปเดต การเช็คชื่อเรียบร้อยแล้ว!";
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function getStudentsByClubWithAttendance($clubName, $date, $pdo) {
    $date = ensureDateInSelectedAcademicPeriod($date, $pdo);
    // Get members with roll number joined from students table to avoid missing 'no' column in club_members
    $stmtMem = $pdo->prepare("
        SELECT cm.student_id as id, cm.name, cm.level, cm.room, COALESCE(s.no, 99) as no 
        FROM club_members cm
        LEFT JOIN students s ON cm.student_id = s.student_id
        WHERE cm.club_name = ?
    ");
    $stmtMem->execute([$clubName]);
    $members = $stmtMem->fetchAll();
    
    // Sort
    usort($members, function($a, $b) {
        if ($a['level'] !== $b['level']) return strnatcmp($a['level'], $b['level']);
        if ($a['room'] !== $b['room']) return (int)$a['room'] - (int)$b['room'];
        return (int)$a['no'] - (int)$b['no'];
    });
    
    // Get attendance for this date
    $stmtAtt = $pdo->prepare("SELECT student_id, status FROM club_attendance WHERE club_name = ? AND date = ?");
    $stmtAtt->execute([$clubName, $date]);
    
    $attMap = [];
    while ($row = $stmtAtt->fetch()) {
        $attMap[trim($row['student_id'])] = trim($row['status']);
    }
    
    $results = [];
    foreach ($members as $m) {
        $sid = trim($m['id']);
        $results[] = [
            'id' => $sid,
            'name' => trim($m['name']),
            'level' => trim($m['level']),
            'room' => trim($m['room']),
            'no' => $m['no'],
            'savedStatus' => $attMap[$sid] ?? ""
        ];
    }
    return $results;
}

function getAllTeachersData($pdo) {
    $stmt = $pdo->query("SELECT name, username FROM users ORDER BY name ASC");
    $list = [];
    while ($row = $stmt->fetch()) {
        $list[] = trim($row['name']) . " (" . trim($row['username']) . ")";
    }
    return $list;
}

function addStudentToClubManual($clubName, $stuId, $teacherName, $pdo) {
    try {
        $pdo->beginTransaction();
        
        // Find student details
        $stmtStu = $pdo->prepare("SELECT no, student_id, name, level, room FROM students WHERE is_active = 1 AND student_id = ?");
        $stmtStu->execute([$stuId]);
        $student = $stmtStu->fetch();
        
        if (!$student) {
            $pdo->rollBack();
            return ['success' => false, 'message' => 'ไม่พบรหัสนักเรียนในระบบโรงเรียน'];
        }
        
        // Check if student already in a club
        $stmtCheck = $pdo->prepare("SELECT club_name FROM club_members WHERE student_id = ?");
        $stmtCheck->execute([$stuId]);
        $hasClub = $stmtCheck->fetchColumn();
        if ($hasClub) {
            $pdo->rollBack();
            return ['success' => false, 'message' => "นักเรียนคนนี้มีชุมนุมแล้ว (" . $hasClub . ")"];
        }
        
        // Register manual
        $stmtInsert = $pdo->prepare("INSERT INTO club_members (club_name, student_id, name, level, room) VALUES (?, ?, ?, ?, ?)");
        $stmtInsert->execute([$clubName, trim($student['student_id']), trim($student['name']), trim($student['level']), trim($student['room'])]);
        
        $pdo->commit();
        return ['success' => true, 'message' => 'เพิ่มนักเรียนเข้าชุมนุมสำเร็จ!'];
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return ['success' => false, 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()];
    }
}

function removeStudentFromClub($clubName, $stuId, $teacherName, $pdo) {
    try {
        // Authorize check
        $stmtClub = $pdo->prepare("SELECT teacher FROM clubs WHERE name = ?");
        $stmtClub->execute([$clubName]);
        $teachers = $stmtClub->fetchColumn();
        
        $isAuthorized = ($teachers && strpos($teachers, $teacherName) !== false);
        $isAdmin = checkIfClubAdmin($teacherName, $pdo);
        
        if (!$isAuthorized && !$isAdmin) {
            return ['success' => false, 'message' => 'คุณไม่มีสิทธิ์จัดการสมาชิกในชุมนุมนี้'];
        }
        
        $stmtDelete = $pdo->prepare("DELETE FROM club_members WHERE club_name = ? AND student_id = ?");
        $stmtDelete->execute([$clubName, $stuId]);
        
        if ($stmtDelete->rowCount() > 0) {
            return ['success' => true, 'message' => 'ลบนักเรียนออกจากชุมนุมเรียบร้อยแล้ว'];
        } else {
            return ['success' => false, 'message' => 'ไม่พบรหัสนักเรียนคนนี้ในชุมนุม'];
        }
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function addMultipleStudentsToClub($clubName, $studentsArray, $pdo) {
    try {
        $pdo->beginTransaction();
        
        $stmtInsert = $pdo->prepare("INSERT INTO club_members (club_name, student_id, name, level, room) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE club_name = VALUES(club_name)");
        foreach ($studentsArray as $stu) {
            $stmtInsert->execute([
                $clubName,
                trim($stu['id']),
                trim($stu['name']),
                trim($stu['level']),
                trim($stu['room'])
            ]);
        }
        
        $pdo->commit();
        return ['success' => true, 'message' => 'เพิ่มสมาชิก ' . count($studentsArray) . ' คน สำเร็จ!'];
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function generateClubReportPDF($clubName, $academicYear, $semester, $pdo) {
    [$academicYear, $semester] = resolveAcademicPeriod($academicYear, $semester, $pdo);
    $reportRes = getClubAttendanceReport($clubName, $academicYear, $semester, $pdo);
    if (!$reportRes['success']) return ['error' => $reportRes['message']];
    
    $data = $reportRes['data'];
    if (empty($data)) return ['error' => 'ไม่มีข้อมูลสำหรับออกรายงาน'];
    
    $stmtClub = $pdo->prepare("SELECT teacher FROM clubs WHERE name = ?");
    $stmtClub->execute([$clubName]);
    $teacherStr = $stmtClub->fetchColumn() ?: "ไม่ระบุชื่อครู";
    
    $teachersArray = array_filter(array_map('trim', explode(',', $teacherStr)));
    $teacherNames = [];
    foreach ($teachersArray as $t) {
        $displayName = (strpos($t, 'ครู') === 0) ? $t : 'ครู' . $t;
        $teacherNames[] = $displayName;
    }
    $teacherFormatted = !empty($teacherNames) ? implode(', ', $teacherNames) : "ไม่ระบุชื่อครู";
    
    $tableRows = "";
    foreach ($data as $s) {
        $present = (int)($s['present'] ?? 0);
        $absent = (int)($s['absent'] ?? 0);
        $total = $present + $absent;
        $tableRows .= "<tr>
            <td style='padding: 5px 4px; font-weight: normal !important;'>{$s['id']}</td>
            <td class='text-left' style='text-align:left !important; padding-left:12px !important; padding-top: 5px; padding-bottom: 5px; font-weight: normal !important;'>{$s['name']}</td>
            <td style='padding: 5px 4px; font-weight: normal !important;'>{$s['level']}/{$s['room']}</td>
            <td style='color:#198754; padding: 5px 4px; font-weight: normal !important;'>{$present}</td>
            <td style='color:#dc3545; padding: 5px 4px; font-weight: normal !important;'>{$absent}</td>
            <td style='padding: 5px 4px; font-weight: normal !important;'>{$total}</td>
        </tr>";
    }

    $html = "<html><head>" . getPdfHeader("โรงเรียนมกุฎเมืองราชวิทยาลัย") . "</head><body>
      <h3>รายงานสรุปการเข้าเรียนชุมนุม</h3>
      <h4 style='font-size: 11.5pt; font-weight: bold; margin: 2px 0 3px 0; text-align: center;'>ชื่อชุมนุม: {$clubName}</h4>
      <p style='text-align: center; margin: 2px 0 8px 0; font-size: 10.5pt;'>
        <b>ปีการศึกษา:</b> {$academicYear} &nbsp; <b>ภาคเรียน:</b> {$semester}<br>
        <b>ครูที่ปรึกษา:</b> {$teacherFormatted} &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; <b>จำนวนสมาชิก:</b> " . count($data) . " คน
      </p>
      <table style='width:100%; border-collapse:collapse; margin-top: 6px;'>
        <thead>
          <tr>
            <th style='width:15%; padding: 6px 4px;'>รหัส</th>
            <th style='width:40%; padding: 6px 4px;'>ชื่อนักเรียน</th>
            <th style='width:15%; padding: 6px 4px;'>ระดับชั้น</th>
            <th style='width:10%; padding: 6px 4px;'>มา(ครั้ง)</th>
            <th style='width:10%; padding: 6px 4px;'>ขาด(ครั้ง)</th>
            <th style='width:10%; padding: 6px 4px;'>รวม(ครั้ง)</th>
          </tr>
        </thead>
        <tbody>{$tableRows}</tbody>
      </table>
    </body></html>";
    
    return ['html' => $html, 'filename' => "ClubReport_{$academicYear}_T{$semester}_{$clubName}.pdf"];
}

function generateNoClubReportPDF($pdo) {
    $students = getStudentsWithoutClub($pdo);
    if (empty($students)) return ['error' => 'นักเรียนทุกคนมีชุมนุมครบแล้ว ไม่พบข้อมูล'];
    
    $tableRows = "";
    foreach ($students as $s) {
        $tableRows .= "<tr>
            <td>{$s['level']}/{$s['room']}</td>
            <td>{$s['id']}</td>
            <td class='text-left' style='text-align:left; padding-left:8px;'>{$s['name']}</td>
        </tr>";
    }
    
    $html = "<html><head>" . getPdfHeader("โรงเรียนมกุฎเมืองราชวิทยาลัย") . "</head><body>
      <h3>รายงานรายชื่อนักเรียนที่ยังไม่มีชุมนุม</h3>
      <p style='margin-bottom: 10px;'><b>จำนวนนักเรียนทั้งหมดที่ยังไม่มีชุมนุม:</b> " . count($students) . " คน</p>
      <table>
        <thead>
          <tr>
            <th style='width:18%;'>ชั้น/ห้อง</th>
            <th style='width:22%;'>รหัสนักเรียน</th>
            <th style='width:60%;'>ชื่อ-สกุล</th>
          </tr>
        </thead>
        <tbody>{$tableRows}</tbody>
      </table>
    </body></html>";
    
    return ['html' => $html, 'filename' => "Report_NoClub_Students.pdf"];
}

function generateAdminClubMembersPDF($clubName, $pdo) {
    $reportRes = getAdminMembersByClub($clubName, $pdo);
    if (!$reportRes['success']) return ['error' => $reportRes['message']];
    
    $students = $reportRes['students'];
    if (empty($students)) return ['error' => 'ไม่พบข้อมูลสมาชิกในชุมนุมนี้'];
    
    $tableRows = "";
    foreach ($students as $s) {
        $tableRows .= "<tr>
            <td>{$s['id']}</td>
            <td class='text-left' style='text-align:left; padding-left:8px;'>{$s['name']}</td>
            <td>{$s['level']}/{$s['room']}</td>
        </tr>";
    }
    
    $html = "<html><head>" . getPdfHeader("โรงเรียนมกุฎเมืองราชวิทยาลัย") . "</head><body>
      <h3>รายงานรายชื่อสมาชิกชุมนุม</h3>
      <p style='margin-bottom: 4px;'><b>ชื่อชุมนุม:</b> {$clubName}</p>
      <p style='margin-bottom: 10px;'><b>ครูที่ปรึกษา:</b> {$reportRes['teacher']} &nbsp;&nbsp;|&nbsp;&nbsp; <b>จำนวนสมาชิก:</b> " . count($students) . " คน</p>
      <table>
        <thead>
          <tr>
            <th style='width:20%;'>รหัสนักเรียน</th>
            <th style='width:60%;'>ชื่อ-สกุล</th>
            <th style='width:20%;'>ชั้น/ห้อง</th>
          </tr>
        </thead>
        <tbody>{$tableRows}</tbody>
      </table>
    </body></html>";
    
    return ['html' => $html, 'filename' => "Report_ClubMembers_{$clubName}.pdf"];
}

function generateAdminRoomClubsPDF($level, $room, $pdo) {
    $roomRes = getAdminMembersByRoom($level, $room, $pdo);
    if (!$roomRes['success']) return ['error' => $roomRes['message']];
    
    $data = $roomRes['data'];
    if (empty($data)) return ['error' => 'ไม่พบข้อมูลนักเรียนในห้องนี้'];
    
    $tableRows = "";
    foreach ($data as $s) {
        $tableRows .= "<tr>
            <td>{$s['no']}</td>
            <td>{$s['id']}</td>
            <td class='text-left' style='text-align:left; padding-left:8px;'>{$s['name']}</td>
            <td>{$s['club']}</td>
        </tr>";
    }
    
    $html = "<html><head>" . getPdfHeader("โรงเรียนมกุฎเมืองราชวิทยาลัย") . "</head><body>
      <h3>รายงานชุมนุมแยกตามห้องเรียน</h3>
      <p style='margin-bottom: 10px;'><b>ระดับชั้น:</b> {$level}/{$room} &nbsp;&nbsp;|&nbsp;&nbsp; <b>จำนวนนักเรียนทั้งหมด:</b> " . count($data) . " คน</p>
      <table>
        <thead>
          <tr>
            <th style='width:8%;'>เลขที่</th>
            <th style='width:17%;'>รหัส</th>
            <th style='width:50%;'>ชื่อ-สกุล</th>
            <th style='width:25%;'>ชุมนุม</th>
          </tr>
        </thead>
        <tbody>{$tableRows}</tbody>
      </table>
    </body></html>";
    
    return ['html' => $html, 'filename' => "Report_RoomClubs_{$level}_{$room}.pdf"];
}

function getDashboardData($academicYear, $semester, $pdo) {
    [$academicYear, $semester] = resolveAcademicPeriod($academicYear, $semester, $pdo);
    // Count total, enrolled, pending
    $stmtT = $pdo->query("SELECT COUNT(*) FROM students WHERE is_active = 1");
    $totalStudents = (int)$stmtT->fetchColumn();
    
    $stmtE = $pdo->query("SELECT COUNT(*) FROM club_members");
    $enrolledStudents = (int)$stmtE->fetchColumn();
    $pendingStudents = $totalStudents - $enrolledStudents;
    
    // Club info
    $stmtClubs = $pdo->query("SELECT c.*, (SELECT COUNT(*) FROM club_members m WHERE m.club_name = c.name) as current_members FROM clubs c ORDER BY name ASC");
    $clubsList = [];
    while ($row = $stmtClubs->fetch()) {
        $clubsList[] = [
            'id' => trim($row['name']),
            'name' => trim($row['name']),
            'teacher' => trim($row['teacher'] ?? ''),
            'max' => (int)$row['limit_seats'],
            'grades' => trim($row['target_classes'] ?? 'ทั้งหมด'),
            'current' => (int)$row['current_members']
        ];
    }
    
    return [
        'academicYear' => $academicYear,
        'semester' => $semester,
        'totalStudents' => $totalStudents,
        'enrolledStudents' => $enrolledStudents,
        'pendingStudents' => $pendingStudents,
        'clubsList' => $clubsList
    ];
}

function getFaceArrivalMap($level, $room, $date, $pdo) {
    try {
        $date = ensureDateInSelectedAcademicPeriod($date, $pdo);
        $stmt = $pdo->prepare("SELECT student_id, datetime, status FROM face_arrivals WHERE date = ? AND level = ? AND room = ?");
        $stmt->execute([$date, $level, $room]);
        
        $result = [];
        while ($row = $stmt->fetch()) {
            $result[trim($row['student_id'])] = [
                'time' => date('H:i', strtotime($row['datetime'])),
                'status' => trim($row['status'])
            ];
        }
        return $result;
    } catch (Exception $e) {
        return [];
    }
}

function addMultipleClubs($clubsArray, $pdo) {
    try {
        $pdo->beginTransaction();
        
        $stmtInsert = $pdo->prepare("INSERT INTO clubs (name, teacher, limit_seats, target_classes, location, status, image) VALUES (?, ?, ?, ?, ?, 'เปิด', ?)");
        $messages = [];
        
        foreach ($clubsArray as $club) {
            $clubName = trim($club['name']);
            $teacherName = trim($club['teacher']);
            $limit = $club['limit'];
            $targetClasses = trim($club['targetClasses']);
            $location = trim($club['location'] ?? '');
            $imageBase64 = $club['image'] ?? '';
            
            // Check duplicate
            $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM clubs WHERE name = ?");
            $stmtCheck->execute([$clubName]);
            if ($stmtCheck->fetchColumn() > 0) {
                $messages[] = "❌ '" . $clubName . "' มีชื่อซ้ำในระบบ";
                continue;
            }
            
            $imageUrl = null;
            if (!empty($imageBase64)) {
                $clubUploadDir = __DIR__ . '/uploads/clubs';
                if (!file_exists($clubUploadDir)) {
                    mkdir($clubUploadDir, 0755, true);
                }
                $pos = strpos($imageBase64, 'base64,');
                $dataStr = ($pos !== false) ? substr($imageBase64, $pos + 7) : $imageBase64;
                $imgBytes = base64_decode($dataStr);
                if ($imgBytes) {
                    $filename = 'club_' . md5($clubName . time()) . '.jpg';
                    file_put_contents($clubUploadDir . '/' . $filename, $imgBytes);
                    $imageUrl = 'uploads/clubs/' . $filename;
                }
            }
            
            $stmtInsert->execute([$clubName, $teacherName, $limit, $targetClasses, $location, $imageUrl]);
            $messages[] = "✅ '" . $clubName . "' สร้างสำเร็จ";
        }
        
        $pdo->commit();
        return ['success' => true, 'message' => implode("\n", $messages)];
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return ['success' => false, 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()];
    }
}

function updateClubImage($clubName, $base64Data, $pdo) {
    try {
        if (!file_exists(__DIR__ . '/uploads/clubs')) {
            mkdir(__DIR__ . '/uploads/clubs', 0755, true);
        }
        $pos = strpos($base64Data, 'base64,');
        $dataStr = ($pos !== false) ? substr($base64Data, $pos + 7) : $base64Data;
        $imgBytes = base64_decode($dataStr);
        if (!$imgBytes) return ['success' => false, 'message' => 'ข้อมูลรูปภาพไม่ถูกต้อง'];
        
        $filename = 'club_' . md5($clubName . time()) . '.jpg';
        file_put_contents(__DIR__ . '/uploads/clubs/' . $filename, $imgBytes);
        $imageUrl = 'uploads/clubs/' . $filename;
        
        $stmt = $pdo->prepare("UPDATE clubs SET image = ? WHERE name = ?");
        $stmt->execute([$imageUrl, trim($clubName)]);
        return ['success' => true, 'image' => $imageUrl, 'message' => 'อัปเดตรูปภาพชุมนุมสำเร็จ!'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()];
    }
}

function getClubCreationStatus($pdo) {
    $stmt = $pdo->prepare("SELECT settings_value FROM settings WHERE settings_key = 'AllowClubCreation'");
    $stmt->execute();
    return $stmt->fetchColumn() ?: "เปิด";
}

function toggleClubCreationSystemStatus($currentStatus, $pdo) {
    try {
        $newStatus = ($currentStatus === 'เปิด') ? 'ปิด' : 'เปิด';
        $stmt = $pdo->prepare("INSERT INTO settings (settings_key, settings_value) VALUES ('AllowClubCreation', ?) ON DUPLICATE KEY UPDATE settings_value = VALUES(settings_value)");
        $stmt->execute([$newStatus]);
        
        return [
            'success' => true,
            'newStatus' => $newStatus,
            'message' => "เปลี่ยนสถานะฟอร์มเปิดชุมนุมเป็น '" . $newStatus . "' เรียบร้อยแล้ว!"
        ];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

// ----------------------------------------------------
// FACE API INCOMING POST REQUEST HANDLER (doPost)
// ----------------------------------------------------

function handleFaceApi($body, $pdo) {
    try {
        if (empty($_SESSION['academic_year_schema_v2_ready'])) {
            ensureAcademicYearSchema($pdo);
            $_SESSION['academic_year_schema_v2_ready'] = true;
        }
        session_write_close();
        // Validate secret
        if ($body['secret'] !== FACE_API_SECRET) {
            echo json_encode(['ok' => false, 'error' => 'unauthorized'], JSON_UNESCAPED_UNICODE);
            return;
        }
        
        $event = $body['event'] ?? '';
        
        if ($event === 'get_face_roster') {
            // Get all students
            $stmt = $pdo->query("SELECT student_id, name, level, room FROM students WHERE is_active = 1 ORDER BY student_id ASC");
            $students = [];
            while ($row = $stmt->fetch()) {
                $lvl = trim($row['level']);
                $rm = trim($row['room']);
                $students[] = [
                    'student_id' => trim($row['student_id']),
                    'full_name' => trim($row['name']),
                    'class_room' => (!empty($lvl) && !empty($rm)) ? $lvl . '/' . $rm : ($lvl ?: $rm),
                    'level' => $lvl,
                    'room' => $rm
                ];
            }
            echo json_encode(['ok' => true, 'students' => $students], JSON_UNESCAPED_UNICODE);
            return;
        }
        
        if ($event !== 'face_arrival' || empty($body['student_id'])) {
            echo json_encode(['ok' => false, 'error' => 'invalid_request'], JSON_UNESCAPED_UNICODE);
            return;
        }
        
        // Find student
        $stmtS = $pdo->prepare("SELECT name, level, room FROM students WHERE is_active = 1 AND student_id = ?");
        $stmtS->execute([trim($body['student_id'])]);
        $student = $stmtS->fetch();
        
        if (!$student) {
            echo json_encode(['ok' => false, 'error' => 'student_not_found'], JSON_UNESCAPED_UNICODE);
            return;
        }
        
        $studentId = trim($body['student_id']);
        $today = date('Y-m-d');
        
        // Check duplicate
        $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM face_arrivals WHERE date = ? AND student_id = ?");
        $stmtCheck->execute([$today, $studentId]);
        if ($stmtCheck->fetchColumn() > 0) {
            echo json_encode([
                'ok' => true,
                'duplicate' => true,
                'student' => [
                    'student_id' => $studentId,
                    'full_name' => trim($student['name']),
                    'level' => trim($student['level']),
                    'room' => trim($student['room'])
                ]
            ], JSON_UNESCAPED_UNICODE);
            return;
        }
        
        // Save face arrival
        $now = date('Y-m-d H:i:s');
        $confidence = (double)($body['confidence'] ?? 0);
        
        $stmtInsert = $pdo->prepare("INSERT INTO face_arrivals (date, datetime, student_id, name, level, room, status, confidence) VALUES (?, ?, ?, ?, ?, ?, 'มาถึงโรงเรียนแล้ว', ?)");
        $stmtInsert->execute([$today, $now, $studentId, trim($student['name']), trim($student['level']), trim($student['room']), $confidence]);
        
        echo json_encode([
            'ok' => true,
            'duplicate' => false,
            'student' => [
                'student_id' => $studentId,
                'full_name' => trim($student['name']),
                'level' => trim($student['level']),
                'room' => trim($student['room'])
            ]
        ], JSON_UNESCAPED_UNICODE);
        
    } catch (Exception $e) {
        echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
}

function adminUpdateSingleStudentAvatar($studentId, $base64Data, $pdo) {
    try {
        if (!file_exists(__DIR__ . '/uploads/student_avatars')) {
            mkdir(__DIR__ . '/uploads/student_avatars', 0755, true);
        }
        
        $pos = strpos($base64Data, 'base64,');
        $dataStr = ($pos !== false) ? substr($base64Data, $pos + 7) : $base64Data;
        $imgBytes = base64_decode($dataStr);
        if (!$imgBytes) return ['success' => false, 'message' => 'ข้อมูลรูปภาพไม่ถูกต้อง'];
        
        $filename = 'stu_' . preg_replace('/[^a-zA-Z0-9_\-]/', '', $studentId) . '_' . time() . '.jpg';
        $filePath = __DIR__ . '/uploads/student_avatars/' . $filename;
        file_put_contents($filePath, $imgBytes);
        
        $avatarUrl = 'uploads/student_avatars/' . $filename;
        $stmt = $pdo->prepare("UPDATE students SET avatar = ? WHERE student_id = ?");
        $stmt->execute([$avatarUrl, trim($studentId)]);
        
        return ['success' => true, 'avatar' => $avatarUrl, 'message' => 'อัปเดตรูปถ่ายนักเรียนสำเร็จ!'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()];
    }
}

function bulkUploadStudentAvatars($filesArray, $pdo) {
    try {
        if (!file_exists(__DIR__ . '/uploads/student_avatars')) {
            mkdir(__DIR__ . '/uploads/student_avatars', 0755, true);
        }
        
        $stmt = $pdo->query("SELECT student_id, name FROM students WHERE is_active = 1");
        $students = $stmt->fetchAll();
        $studentIdMap = [];
        foreach ($students as $s) {
            $sid = trim($s['student_id']);
            $studentIdMap[$sid] = $sid;
        }
        
        $updatedCount = 0;
        $unmatched = [];
        $updateStmt = $pdo->prepare("UPDATE students SET avatar = ? WHERE student_id = ?");
        
        foreach ($filesArray as $file) {
            $rawName = $file['name'] ?? '';
            $base64Data = $file['data'] ?? '';
            if (empty($rawName) || empty($base64Data)) continue;
            
            $cleanName = pathinfo($rawName, PATHINFO_FILENAME);
            $cleanName = trim($cleanName);
            
            $targetStuId = null;
            if (isset($studentIdMap[$cleanName])) {
                $targetStuId = $studentIdMap[$cleanName];
            } else {
                foreach ($studentIdMap as $sid => $v) {
                    if (strpos($cleanName, $sid) !== false) {
                        $targetStuId = $sid;
                        break;
                    }
                }
            }
            
            if ($targetStuId) {
                $pos = strpos($base64Data, 'base64,');
                $dataStr = ($pos !== false) ? substr($base64Data, $pos + 7) : $base64Data;
                $imgBytes = base64_decode($dataStr);
                
                if ($imgBytes) {
                    $filename = 'stu_' . $targetStuId . '_' . time() . '.jpg';
                    $filePath = __DIR__ . '/uploads/student_avatars/' . $filename;
                    file_put_contents($filePath, $imgBytes);
                    
                    $avatarUrl = 'uploads/student_avatars/' . $filename;
                    $updateStmt->execute([$avatarUrl, $targetStuId]);
                    $updatedCount++;
                }
            } else {
                $unmatched[] = $rawName;
            }
        }
        
        return [
            'success' => true,
            'updatedCount' => $updatedCount,
            'unmatchedCount' => count($unmatched),
            'unmatchedFiles' => $unmatched,
            'message' => "อัปโหลดและจับคู่รูปถ่ายนักเรียนสำเร็จ {$updatedCount} คน" . (count($unmatched) > 0 ? " (ไม่พบข้อมูล " . count($unmatched) . " ไฟล์)" : "")
        ];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()];
    }
}
