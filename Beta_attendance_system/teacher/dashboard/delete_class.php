<?php
session_start();
require_once 'config/auth_check.php';
require_once 'config/database.php';
checkAuth('teacher');

$response = ['success' => false, 'message' => ''];

try {
    if (!isset($_POST['class_id'])) {
        throw new Exception('Class ID is required');
    }

    $conn = connectDB();
    $class_id = $_POST['class_id'];
    $teacher_id = $_SESSION['teacher_id'];

    // Verify class belongs to teacher and has no attendance
    $stmt = $conn->prepare(
        "SELECT COUNT(*) as count FROM classes c 
         LEFT JOIN attendance a ON c.Class_id = a.class_id
         WHERE c.Class_id = ? AND c.Teacher_id = ? AND a.class_id IS NULL"
    );
    $stmt->bind_param("ii", $class_id, $teacher_id);
    $stmt->execute();
    
    if ($stmt->get_result()->fetch_assoc()['count'] === 0) {
        throw new Exception('Cannot delete this class');
    }

    // Delete the class
    $stmt = $conn->prepare("DELETE FROM classes WHERE Class_id = ? AND Teacher_id = ?");
    $stmt->bind_param("ii", $class_id, $teacher_id);
    
    if ($stmt->execute()) {
        $response['success'] = true;
        $response['message'] = 'Class deleted successfully';
    }
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

header('Content-Type: application/json');
echo json_encode($response);
