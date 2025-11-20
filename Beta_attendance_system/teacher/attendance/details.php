<?php
session_start();
require_once '../../app/config/auth_check.php';
require_once '../../app/config/database.php';
checkAuth('teacher');

header('Content-Type: application/json');

$conn = connectDB();
$teacher_id = $_SESSION['teacher_id'];

// Get and validate parameters
$student_id = $_GET['student_id'] ?? '';
$class_id = $_GET['class_id'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

if (!$student_id || !$class_id || !$date_from || !$date_to) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required parameters']);
    exit;
}

// Verify teacher owns this class
$stmt = $conn->prepare("SELECT 1 FROM classes WHERE Class_id = ? AND Teacher_id = ?");
$stmt->bind_param("ii", $class_id, $teacher_id);
$stmt->execute();
if (!$stmt->get_result()->fetch_assoc()) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

try {
    // Get attendance statistics
    $statsQuery = "SELECT 
        COUNT(*) as total_days,
        SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as total_present,
        SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as total_late
        FROM attendance 
        WHERE student_id = ? 
        AND class_id = ?
        AND date BETWEEN ? AND ?";

    $stmt = $conn->prepare($statsQuery);
    $stmt->bind_param("siss", $student_id, $class_id, $date_from, $date_to);
    $stmt->execute();
    $stats = $stmt->get_result()->fetch_assoc();

    if (!$stats) {
        throw new Exception('Failed to fetch attendance statistics');
    }

    // Get detailed attendance records
    $query = "SELECT 
        date,
        DATE_FORMAT(date, '%W, %M %d, %Y') as formatted_date,
        DAYNAME(date) as day_name,
        status
        FROM attendance 
        WHERE student_id = ? 
        AND class_id = ? 
        AND date BETWEEN ? AND ?
        ORDER BY date ASC";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("siss", $student_id, $class_id, $date_from, $date_to);
    $stmt->execute();
    $result = $stmt->get_result();

    $attendance_records = [];
    while ($row = $result->fetch_assoc()) {
        $attendance_records[] = [
            'date' => $row['date'],
            'formatted_date' => $row['formatted_date'],
            'day_name' => $row['day_name'],
            'status' => $row['status']
        ];
    }

    // Calculate attendance rate
    $attendance_rate = $stats['total_days'] > 0 
        ? round((($stats['total_present'] + $stats['total_late'] * 0.5) / $stats['total_days']) * 100, 1)
        : 0;

    echo json_encode([
        'success' => true,
        'stats' => [
            'total_present' => (int)$stats['total_present'],
            'total_days' => (int)$stats['total_days'],
            'total_late' => (int)$stats['total_late'],
            'attendance_rate' => $attendance_rate
        ],
        'attendance' => $attendance_records
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
