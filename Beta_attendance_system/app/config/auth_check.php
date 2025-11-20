<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/database.php';

function checkAuth($required_role) {
    // Check if session exists and hasn't expired
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || !isset($_SESSION['last_activity'])) {
        session_destroy();
        header("Location: /Beta_attendance_system/app/auth/login.php");
        exit();
    }

    // Check for session timeout (30 minutes)
    $timeout = 30 * 60; // 30 minutes in seconds
    if (time() - $_SESSION['last_activity'] > $timeout) {
        session_destroy();
        header("Location: /Beta_attendance_system/app/auth/login.php?timeout=1");
        exit();
    }

    // Update last activity time
    $_SESSION['last_activity'] = time();

    // Check if user has required role
    if ($_SESSION['role'] !== $required_role) {
        if ($required_role === 'admin') {
            header("Location: /Beta_attendance_system/app/auth/login.php?unauthorized=1");
        } else {
            header("Location: /Beta_attendance_system/admin/dashboard/admin_dashboard.php");
        }
        exit();
    }
}
