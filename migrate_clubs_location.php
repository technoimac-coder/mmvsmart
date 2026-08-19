<?php
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $stmt = $pdo->query("SHOW COLUMNS FROM clubs LIKE 'location'");
    $exists = $stmt->fetch();
    
    if (!$exists) {
        $pdo->exec("ALTER TABLE clubs ADD COLUMN location VARCHAR(255) DEFAULT NULL AFTER target_classes");
        echo json_encode(['success' => true, 'message' => 'Added location column to clubs table successfully!'], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode(['success' => true, 'message' => 'Column location already exists in clubs table.'], JSON_UNESCAPED_UNICODE);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
