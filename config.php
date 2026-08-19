<?php
// Configuration settings for Makudmuang Smart Class System

// Database Credentials
define('DB_HOST', 'localhost');
define('DB_USER', 'mmvsc_school_db');         // Verified working MySQL user from Plesk
define('DB_PASS', 'Password@123');            // Verified working MySQL password
define('DB_NAME', 'mmvsc_smartclass');        // Dedicated database for Smart Class System

// Face API Security Secret
define('FACE_API_SECRET', 'YOUR_SECRET_KEY'); // Matches Property FACE_API_SECRET in GAS

// Signature settings for printed PDF reports (can be edited when positions/names change)
define('SIGNATURES', [
    'officer' => [
        'name' => 'นางสาวกนกนาถ สุทธิสถิตย์',
        'position' => 'เจ้าหน้าที่ระบบดูแลช่วยเหลือนักเรียน'
    ],
    'deputy' => [
        'name' => 'นายไชยวัฒน์ บุญมี',
        'position' => 'รองผู้อำนวยการกลุ่มบริหารงานทั่วไป'
    ],
    'director' => [
        'name' => 'นางสาวมณฑาทิพย์ เสาวคนธ์',
        'position' => 'ผู้อำนวยการโรงเรียนมกุฎเมืองราชวิทยาลัย'
    ]
]);

// Timezone Settings
date_default_timezone_set('Asia/Bangkok');

// Upload directory for Volunteer activity photos
define('UPLOAD_DIR', __DIR__ . '/uploads');

// Create database connection
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    // Return connection error in JSON if it is an API request
    if (strpos($_SERVER['REQUEST_URI'], 'api.php') !== false || $_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'message' => 'เชื่อมต่อฐานข้อมูลล้มเหลว: ' . $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
        exit;
    } else {
        die('Database Connection Failed: ' . $e->getMessage());
    }
}
