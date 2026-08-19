<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/config.php';

try {
    $stmtS = $pdo->query("SELECT COUNT(*) FROM students");
    $stmtA = $pdo->query("SELECT COUNT(*) FROM attendance");
    $stmtD = $pdo->query("SELECT COUNT(*) FROM deductions");
    $stmtR = $pdo->query("SELECT COUNT(*) FROM rewards");
    
    echo json_encode([
        'status' => 'SUCCESS',
        'current_database' => DB_NAME,
        'students_count' => $stmtS->fetchColumn(),
        'attendance_count' => $stmtA->fetchColumn(),
        'deductions_count' => $stmtD->fetchColumn(),
        'rewards_count' => $stmtR->fetchColumn()
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo json_encode([
        'status' => 'ERROR',
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}
