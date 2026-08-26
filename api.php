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
    if (empty($_SESSION['academic_year_schema_ready'])) {
        ensureAcademicYearSchema($pdo);
        $_SESSION['academic_year_schema_ready'] = true;
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
            
        case 'adminUpdateSingleStudentAvatar':
            return adminUpdateSingleStudentAvatar($args[0], $args[1], $pdo);
            
        case 'bulkUploadStudentAvatars':
            return bulkUploadStudentAvatars($args[0], $pdo);
            
        case 'adminAddUser':
            return adminAddUser($args[0], $args[1], $args[2], $args[3] ?? '', $args[4] ?? '', $args[5] ?? null, $pdo);
            
        case 'adminAddStudent':
            return adminAddStudent($args[0], $args[1], $args[2], $args[3], $args[4], $pdo);

        case 'getAcademicYearSetupData':
            return getAcademicYearSetupData($pdo);

        case 'applyAcademicYearImport':
            return applyAcademicYearImport($args[0], $args[1], $args[2] ?? [], $pdo);

        case 'listAcademicYearBackups':
            return listAcademicYearBackups($pdo);

        case 'restoreAcademicYearBackup':
            return restoreAcademicYearBackup($args[0], $pdo);
            
        case 'getAdminLogs':
            return getAdminLogs($pdo);
            
        case 'resetNewTermData':
            return resetNewTermData($args[0], $args[1] ?? '', $pdo);
            
        case 'uploadGroupVolunteerPhoto':
            return uploadGroupVolunteerPhoto($args[0], $args[1], $args[2], $args[3], $args[4], $args[5], $pdo);
            
        case 'getVolunteerGallery':
            return getVolunteerGallery($args[0], $args[1], $args[2], $args[3], $args[4] ?? '', $args[5] ?? '', $pdo);
            
        case 'getFaceArrivalMap':
            return getFaceArrivalMap($args[0], $args[1], $args[2], $pdo);
            
        case 'getStudentsWithAttendance':
            return getStudentsWithAttendance($args[0], $args[1], $args[2], $args[3], $pdo);
            
        case 'saveAttendance':
            return saveAttendance($args[0], $args[1], $args[2], $args[3], $args[4], $pdo);
            
        case 'getReportData':
            return getReportData($args[0], $pdo);
            
        case 'getIndividualSummary':
            return getIndividualSummary($args[0], $args[1], $args[2], $args[3], $args[4], $pdo);
            
        case 'exportIndividualExcel':
            return exportIndividualExcel($args[0], $args[1], $args[2], $args[3], $args[4], $pdo);
            
        case 'getAllStudentsForDeduct':
            return getAllStudentsForDeduct($pdo);
            
        case 'saveMultipleDeductions':
            return saveMultipleDeductions($args[0], $args[1], $args[2], $args[3], $args[4], $pdo);
            
        case 'getRoomDeductionReport':
            return getRoomDeductionReport($args[0], $args[1], $args[2], $pdo);
            
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
            return generateDeductPDF($args[0], $args[1], $args[2], $pdo);
            
        case 'getMyLogs':
            return getMyLogs($args[0], $args[1], $pdo);
            
        case 'generateRewardPDF':
            return generateRewardPDF($args[0], $args[1], $args[2], $pdo);
            
        case 'saveReward':
            return saveReward($args[0], $args[1], $args[2], $args[3], $args[4], $args[5], $args[6], $args[7], $pdo);
            
        case 'getRoomRewardReport':
            return getRoomRewardReport($args[0], $args[1], $args[2], $pdo);
            
        case 'getAllRewardsRanked':
            return getAllRewardsRanked($pdo);
            
        case 'getStudentHistoryForTeacher':
            return getStudentHistoryForTeacher($args[0], $pdo);
            
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
            return getClubAttendanceReport($args[0], $pdo);
            
        case 'getStudentsWithoutClub':
            return getStudentsWithoutClub($pdo);
            
        case 'getAdminMembersByClub':
            return getAdminMembersByClub($args[0], $pdo);
            
        case 'getAdminMembersByRoom':
            return getAdminMembersByRoom($args[0], $args[1], $pdo);
            
        case 'addClubAdminRole':
            return addClubAdminRole($args[0], $pdo);
            
        case 'getClubsListAdmin':
            return getClubsListAdmin($pdo);
            
        case 'updateClubImage':
            return updateClubImage($args[0], $args[1], $pdo);
            
        case 'deleteClubByAdmin':
            return deleteClubByAdmin($args[0], $pdo);
            
        case 'checkIfClubAdmin':
            return checkIfClubAdmin($args[0], $pdo);
            
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
            return generateClubReportPDF($args[0], $pdo);
            
        case 'generateNoClubReportPDF':
            return generateNoClubReportPDF($pdo);
            
        case 'generateAdminClubMembersPDF':
            return generateAdminClubMembersPDF($args[0], $pdo);
            
        case 'generateAdminRoomClubsPDF':
            return generateAdminRoomClubsPDF($args[0], $args[1], $pdo);
            
        case 'getDashboardData':
            return getDashboardData($pdo);
            
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
    
    // Check if club admin
    $stmtAdmin = $pdo->prepare("SELECT COUNT(*) FROM club_admins WHERE username = ?");
    $stmtAdmin->execute([$username]);
    $isClubAdmin = ($stmtAdmin->fetchColumn() > 0);
    
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

function adminAddUser($user, $pass, $name, $advisoryRoom, $headLevel, $avatarBase64 = null, $pdo) {
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
        updated_at DATETIME NOT NULL,
        updated_by VARCHAR(255) NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

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

    $indexStmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'students' AND INDEX_NAME = 'idx_students_active_class'");
    $indexStmt->execute([$dbName]);
    if ((int)$indexStmt->fetchColumn() === 0) {
        $pdo->exec("CREATE INDEX idx_students_active_class ON students (is_active, level, room, no)");
    }
}

function requireAdminSession() {
    if (empty($_SESSION['is_admin']) || empty($_SESSION['username'])) {
        throw new RuntimeException('เซสชันผู้ดูแลระบบหมดอายุ กรุณาออกจากระบบแล้วเข้าสู่ระบบใหม่');
    }
    return trim($_SESSION['teacher_name'] ?? $_SESSION['username']);
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
        if (!preg_match('/^(?:[1-9]|1[0-2])$/', $room)) throw new InvalidArgumentException("ห้องในแถว {$line} ต้องเป็น 1 ถึง 12");
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

function getAdminLogs($pdo) {
    $deductions = [];
    $rewards = [];
    
    $stmtD = $pdo->query("SELECT datetime, level, room, name, reason, points, teacher_name FROM deductions ORDER BY datetime DESC");
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
    
    $stmtR = $pdo->query("SELECT datetime, level, room, name, reason, points, teacher_name FROM rewards ORDER BY datetime DESC");
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
    
    return ['deductions' => $deductions, 'rewards' => $rewards];
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

function getVolunteerGallery($teacherName, $isAdmin, $advisoryRoom, $viewType, $filterLevel = '', $filterRoom = '', $pdo) {
    $sql = "SELECT datetime, student_id, name, level, room, image_url, teacher_name, hours, activity FROM volunteer";
    $params = [];
    $where = [];
    
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

function getRoomDeductionReport($level, $room, $teacherName, $pdo) {
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
    $stmtD = $pdo->prepare("SELECT student_id, SUM(points) as pts FROM deductions WHERE level = ? AND room = ? GROUP BY student_id");
    $stmtD->execute([$level, $room]);
    
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

function generateDeductPDF($level, $room, $teacherName, $pdo) {
    if (!canTeacherAccess($teacherName, $level, $room, $pdo)) return ['error' => 'คุณไม่มีสิทธิ์เข้าถึงรายงานห้องเรียนนี้'];
    
    $data = getRoomDeductionReport($level, $room, $teacherName, $pdo);
    if (empty($data)) return ['error' => 'ไม่พบข้อมูลคะแนนในห้องที่เลือก'];
    
    $tableRows = "";
    foreach ($data as $s) {
        $tableRows .= "<tr><td>{$s['no']}</td><td class='text-left' style='text-align:left; padding-left:8px;'>{$s['name']}</td><td style='color:red;'>-{$s['deducted']}</td><td>{$s['remaining']}</td></tr>";
    }
    
    $html = "<html><head>" . getPdfHeader("โรงเรียนมกุฎเมืองราชวิทยาลัย") . "</head><body><h3>รายงานคะแนนความประพฤตินักเรียน</h3><p><b>ระดับชั้น:</b> {$level}/{$room}</p><table><thead><tr><th style='width:15%;'>เลขที่</th><th style='width:45%;'>ชื่อ-สกุล</th><th style='width:20%;'>หักรวม</th><th style='width:20%;'>คะแนนคงเหลือ</th></tr></thead><tbody>{$tableRows}</tbody></table></body></html>";
    
    return ['html' => $html, 'filename' => "Deduct_Report_{$level}_{$room}.pdf"];
}

function getMyLogs($tableName, $teacherName, $pdo) {
    // $tableName comes in as "Deductions" or "Rewards". Map to local SQL table name.
    $table = (strtolower($tableName) === 'rewards') ? 'rewards' : 'deductions';
    
    $stmt = $pdo->prepare("SELECT datetime, level, room, name, reason, points FROM {$table} WHERE teacher_name = ? ORDER BY datetime DESC LIMIT 30");
    $stmt->execute([$teacherName]);
    
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

function generateRewardPDF($level, $room, $teacherName, $pdo) {
    if (!canTeacherAccess($teacherName, $level, $room, $pdo)) return ['error' => 'คุณไม่มีสิทธิ์เข้าถึงรายงานห้องเรียนนี้'];
    
    $data = getRoomRewardReport($level, $room, $teacherName, $pdo);
    if (empty($data)) return ['error' => 'ไม่พบข้อมูลคะแนนในห้องที่เลือก'];
    
    $tableRows = "";
    foreach ($data as $s) {
        $tableRows .= "<tr><td>{$s['no']}</td><td class='text-left' style='text-align:left; padding-left:8px;'>{$s['name']}</td><td style='color:green;'>+{$s['total']}</td></tr>";
    }
    
    $html = "<html><head>" . getPdfHeader("โรงเรียนมกุฎเมืองราชวิทยาลัย") . "</head><body><h3>รายงานคะแนนความดีนักเรียนสะสม</h3><p><b>ระดับชั้น:</b> {$level}/{$room}</p><table><thead><tr><th style='width:15%;'>เลขที่</th><th style='width:55%;'>ชื่อ-สกุล</th><th style='width:30%;'>คะแนนความดีสะสม</th></tr></thead><tbody>{$tableRows}</tbody></table></body></html>";
    
    return ['html' => $html, 'filename' => "Reward_Report_{$level}_{$room}.pdf"];
}

function saveReward($dateTime, $level, $room, $stuId, $stuName, $reason, $points, $teacherName, $pdo) {
    if (!canTeacherAccess($teacherName, $level, $room, $pdo)) {
        throw new Exception("คุณไม่มีสิทธิ์บันทึกการบวกคะแนนความดีของห้องเรียนนี้");
    }
    
    $stmt = $pdo->prepare("INSERT INTO rewards (datetime, level, room, student_id, name, reason, points, teacher_name) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$dateTime, trim($level), trim($room), trim($stuId), trim($stuName), trim($reason), $points, trim($teacherName)]);
    
    return "บันทึกการบวกคะแนนเรียบร้อย โดยครู " . $teacherName . "!";
}

function getRoomRewardReport($level, $room, $teacherName, $pdo) {
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
    $stmtR = $pdo->prepare("SELECT student_id, SUM(points) as pts FROM rewards WHERE level = ? AND room = ? GROUP BY student_id");
    $stmtR->execute([$level, $room]);
    
    while ($row = $stmtR->fetch()) {
        $sid = trim($row['student_id']);
        $pts = (int)$row['pts'];
        if (isset($rewardMap[$sid])) {
            $rewardMap[$sid]['total'] = $pts;
        }
    }
    
    return array_values($rewardMap);
}

function getAllRewardsRanked($pdo) {
    $stmt = $pdo->query("SELECT student_id, name, level, room, SUM(points) as total FROM rewards GROUP BY student_id, name, level, room ORDER BY total DESC");
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

function getStudentHistoryForTeacher($stuId, $pdo) {
    $history = ['deductions' => [], 'rewards' => []];
    
    $stmtD = $pdo->prepare("SELECT datetime, reason, points FROM deductions WHERE student_id = ? ORDER BY datetime DESC");
    $stmtD->execute([$stuId]);
    while ($row = $stmtD->fetch()) {
        $history['deductions'][] = [
            'date' => $row['datetime'],
            'reason' => $row['reason'],
            'points' => $row['points']
        ];
    }
    
    $stmtR = $pdo->prepare("SELECT datetime, reason, points FROM rewards WHERE student_id = ? ORDER BY datetime DESC");
    $stmtR->execute([$stuId]);
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

function getClubAttendanceReport($clubName, $pdo) {
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
        $stmtAtt = $pdo->prepare("SELECT student_id, name, status FROM club_attendance WHERE club_name = ?");
        $stmtAtt->execute([$clubName]);
        
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
        $stmt = $pdo->prepare("INSERT INTO club_admins (username) VALUES (?) ON DUPLICATE KEY UPDATE username = username");
        $stmt->execute([trim($username)]);
        return ['success' => true, 'message' => "เพิ่มสิทธิ์ให้ครูท่านนี้เป็นผู้ดูแลระบบชุมนุมเรียบร้อยแล้ว!"];
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

function generateClubReportPDF($clubName, $pdo) {
    $reportRes = getClubAttendanceReport($clubName, $pdo);
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
    
    return ['html' => $html, 'filename' => "ClubReport_{$clubName}.pdf"];
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

function getDashboardData($pdo) {
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
        'totalStudents' => $totalStudents,
        'enrolledStudents' => $enrolledStudents,
        'pendingStudents' => $pendingStudents,
        'clubsList' => $clubsList
    ];
}

function getFaceArrivalMap($level, $room, $date, $pdo) {
    try {
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
        if (empty($_SESSION['academic_year_schema_ready'])) {
            ensureAcademicYearSchema($pdo);
            $_SESSION['academic_year_schema_ready'] = true;
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
