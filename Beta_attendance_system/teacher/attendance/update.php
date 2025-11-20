<?php
session_start();
require_once '../../app/config/auth_check.php';
require_once '../../app/config/database.php';
checkAuth('teacher');

header('Content-Type: application/json');

$conn = connectDB();
$teacher_id = $_SESSION['teacher_id'];

// Get and validate JSON input
$json = file_get_contents('php://input');
if (!$json) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No data received']);
    exit;
}

$data = json_decode($json, true);
if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON data']);
    exit;
}

// Validate required fields
if (!isset($data['studentId'], $data['classId'], $data['updates']) || !is_array($data['updates'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

$student_id = $data['studentId'];
$class_id = $data['classId'];
$updates = $data['updates'];

// Verify teacher owns this class
$stmt = $conn->prepare("SELECT 1 FROM classes WHERE Class_id = ? AND Teacher_id = ?");
$stmt->bind_param("ii", $class_id, $teacher_id);
$stmt->execute();
if (!$stmt->get_result()->fetch_assoc()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

try {
    $conn->begin_transaction();

    $stmt = $conn->prepare("UPDATE attendance 
                           SET status = ?
                           WHERE student_id = ? 
                           AND class_id = ? 
                           AND date = ?");

    foreach ($updates as $update) {
        if (!isset($update['date'], $update['status'])) {
            throw new Exception('Invalid update data format');
        }

        if (!in_array($update['status'], ['present', 'late', 'absent'])) {
            throw new Exception('Invalid attendance status');
        }

        $stmt->bind_param("ssss", 
            $update['status'],
            $student_id,
            $class_id,
            $update['date']
        );

        if (!$stmt->execute()) {
            throw new Exception('Failed to update attendance record');
        }
    }

    $conn->commit();
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
