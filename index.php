<?php
// index.php - Smart router for MMVSmart and MMV Club Space (club.mmvschool.ac.th)

$host = strtolower($_SERVER['HTTP_HOST'] ?? '');
$mode = $_GET['mode'] ?? '';

// If accessed via club. subdomain or ?mode=student, serve student club registration portal
if (strpos($host, 'club.') !== false || $mode === 'student') {
    include __DIR__ . '/student.html';
} else {
    include __DIR__ . '/teacher.html';
}
