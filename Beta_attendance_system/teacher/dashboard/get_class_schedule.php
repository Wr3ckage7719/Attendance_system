<?php
session_start();
require_once '../../app/config/auth_check.php';
require_once '../../app/config/database.php';
checkAuth('teacher');

header('Content-Type: application/json');

$conn = connectDB();
$teacher_id = $_SESSION['teacher_id'];
$start = $_GET['start'] ?? date('Y-m-d');
$end = $_GET['end'] ?? date('Y-m-d', strtotime('+30 days'));

// Get teacher's classes and their schedules
$query = "SELECT c.Class_id, 
         s.Subject_Name,
         s.Subject_code,
         b.block_name,
         b.year_level,
         co.course_name,
         c.start_time,
         c.end_time,
         cd.day_of_week
         FROM classes c 
         INNER JOIN subject s ON c.Subject_id = s.Subject_id
         INNER JOIN courses co ON c.course_id = co.course_id
         LEFT JOIN blocks b ON c.block_id = b.block_id
         INNER JOIN class_days cd ON c.Class_id = cd.class_id
         WHERE c.Teacher_id = ?
         ORDER BY b.year_level, b.block_name";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$result = $stmt->get_result();

$events = [];
$classes = [];

// Process each class schedule
while ($row = $result->fetch_assoc()) {
    $day_num = date('w', strtotime($row['day_of_week']));
    $class_key = $row['Class_id'];
    
    if (!isset($classes[$class_key])) {
        $classes[$class_key] = [
            'title' => $row['Subject_code'] . ' - ' . $row['block_name'],
            'description' => $row['Subject_Name'] . "\n" . $row['course_name'],
            'start_time' => $row['start_time'],
            'end_time' => $row['end_time'],
            'days' => []
        ];
    }
    $classes[$class_key]['days'][] = $day_num;
}

// Generate events for each class within the date range
$current = new DateTime($start);
$end_date = new DateTime($end);

while ($current <= $end_date) {
    $current_day = $current->format('w');
    
    foreach ($classes as $class_id => $class) {
        if (in_array($current_day, $class['days'])) {
            $event_start = $current->format('Y-m-d') . ' ' . $class['start_time'];
            $event_end = $current->format('Y-m-d') . ' ' . $class['end_time'];
            
            $events[] = [
                'id' => $class_id . '-' . $current->format('Y-m-d'),
                'title' => $class['title'],
                'description' => $class['description'],
                'start' => $event_start,
                'end' => $event_end,
                'classId' => $class_id,
                'backgroundColor' => '#4f46e5',
                'borderColor' => '#4338ca',
                'textColor' => '#ffffff'
            ];
        }
    }
    
    $current->modify('+1 day');
}

echo json_encode($events);