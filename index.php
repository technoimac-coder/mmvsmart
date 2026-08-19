<?php
// index.php - Entry point router that matches Google Apps Script URL parameters

if (isset($_GET['mode']) && $_GET['mode'] === 'student') {
    include __DIR__ . '/student.html';
} else {
    include __DIR__ . '/teacher.html';
}
