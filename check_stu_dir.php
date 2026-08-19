<?php
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');

$dir = __DIR__ . '/uploads/student_avatars';
if (!file_exists($dir)) {
    mkdir($dir, 0777, true);
    chmod($dir, 0777);
}

echo json_encode([
    'success' => true,
    'dirExists' => file_exists($dir),
    'isWritable' => is_writable($dir)
]);
