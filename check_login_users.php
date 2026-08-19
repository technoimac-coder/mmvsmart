<?php
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $stmt = $pdo->query("SELECT id, username, password, name, advisory_room, head_level FROM users LIMIT 10");
    $users = $stmt->fetchAll();
    echo json_encode([
        'success' => true,
        'userCount' => count($users),
        'users' => $users
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
