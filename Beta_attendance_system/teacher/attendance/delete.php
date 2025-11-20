<?php
session_start();
require_once 'config/auth_check.php';
require_once 'config/database.php';
checkAuth('teacher');

header('Content-Type: application/json');

try {
    $conn = connectDB();
    
    // Validate input parameters
    if (!isset($_POST['student_id'], $_POST['class_id'], $_POST['date_from'], $_POST['date_to'])) {
        throw new Exception('Missing required parameters');
    }

    // Validate that the teacher owns this class
    $stmt = $conn->prepare("SELECT 1 FROM classes WHERE Class_id = ? AND Teacher_id = ?");
    $stmt->bind_param("ii", $_POST['class_id'], $_SESSION['teacher_id']);
    $stmt->execute();
    if (!$stmt->get_result()->fetch_row()) {
        throw new Exception('Unauthorized access');
    }

    // Delete attendance records
    $stmt = $conn->prepare("DELETE FROM attendance 
                           WHERE student_id = ? 
                           AND class_id = ? 
                           AND date BETWEEN ? AND ?");
    $stmt->bind_param("iiss", 
        $_POST['student_id'], 
        $_POST['class_id'],
        $_POST['date_from'],
        $_POST['date_to']
    );
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Attendance records deleted successfully']);
    } else {
        throw new Exception('Failed to delete attendance records');
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
