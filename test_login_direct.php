<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/api.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $stmt = $pdo->query("SELECT username, password, name FROM users LIMIT 1");
    $u = $stmt->fetch();
    if ($u) {
        $res = processLogin($u['username'], $u['password'], $pdo);
        echo json_encode(['success' => true, 'testUser' => $u['name'], 'loginResult' => $res], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    } else {
        echo json_encode(['success' => false, 'message' => 'No users in database']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
