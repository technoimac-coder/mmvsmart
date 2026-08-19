<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/config.php';

// Clear avatars in DB
$stmt = $pdo->query("UPDATE users SET avatar = NULL");
$affected = $stmt->rowCount();

// Clean uploads/avatars directory
$avatarDir = __DIR__ . '/uploads/avatars';
$deletedFiles = 0;
if (file_exists($avatarDir) && is_dir($avatarDir)) {
    $files = glob($avatarDir . '/*');
    foreach ($files as $f) {
        if (is_file($f)) {
            unlink($f);
            $deletedFiles++;
        }
    }
}

// Fetch sorted teachers list
$stmtList = $pdo->query("SELECT id, username, name, code, avatar FROM users");
$teachers = $stmtList->fetchAll(PDO::FETCH_ASSOC);

usort($teachers, function($a, $b) {
    return strnatcasecmp($a['code'] ?? '', $b['code'] ?? '');
});

echo json_encode([
    'success' => true,
    'users_cleared' => $affected,
    'files_deleted' => $deletedFiles,
    'sample_sorted_teachers' => array_slice($teachers, 0, 10)
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
