<?php
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');

try {
    // Check if avatar column exists in students table
    $stmt = $pdo->query("SHOW COLUMNS FROM students LIKE 'avatar'");
    $col = $stmt->fetch();
    
    if (!$col) {
        $pdo->exec("ALTER TABLE students ADD COLUMN avatar TEXT DEFAULT NULL AFTER name");
        echo json_encode(['success' => true, 'message' => 'Added avatar column to students table successfully!']);
    } else {
        echo json_encode(['success' => true, 'message' => 'avatar column already exists in students table.']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
