<?php
session_start();
require_once '../../app/config/auth_check.php';
require_once '../../app/config/database.php';
checkAuth('teacher');

$conn = connectDB();

// Check if user is properly authenticated
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Fetch teacher data from database
$user_id = $_SESSION['user_id'];
$query = "SELECT Teacher_id, first_name FROM teachers WHERE user_id = ?";
$stmt = $conn->prepare($query);
if ($stmt === false) {
    die("Error preparing query: " . $conn->error);
}
$stmt->bind_param("i", $user_id);
if (!$stmt->execute()) {
    die("Error executing query: " . $stmt->error);
}
$result = $stmt->get_result();

if ($teacher = $result->fetch_assoc()) {
    $_SESSION['teacher_id'] = $teacher['Teacher_id'];
    $_SESSION['teacher_name'] = $teacher['first_name'];
} else {
    // If no teacher record found, redirect to login
    session_destroy();
    header("Location: login.php");
    exit();
}
$stmt->close();

$teacher_id = $_SESSION['teacher_id'];

// Helper function for prepared statements
function prepareAndExecute($conn, $query, $types, $params) {
    $stmt = $conn->prepare($query);
    if ($stmt === false) {
        die("Error preparing query: " . $conn->error);
    }
    
    if (!$stmt->bind_param($types, ...$params)) {
        die("Error binding parameters: " . $stmt->error);
    }
    
    if (!$stmt->execute()) {
        die("Error executing query: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    $stmt->close();
    return $result;
}

// Get class count
$classCountQuery = "SELECT COUNT(DISTINCT c.Class_id) as count 
                   FROM classes c 
                   WHERE c.Teacher_id = ?";
$result = prepareAndExecute($conn, $classCountQuery, "i", [$teacher_id]);
$classCount = $result->fetch_assoc()['count'];

// Get today's sessions
$today = date('Y-m-d');
$dayOfWeek = date('l');
$todaySessionsQuery = "SELECT COUNT(*) as count 
                      FROM classes c 
                      INNER JOIN class_days cd ON c.Class_id = cd.class_id 
                      WHERE c.Teacher_id = ? 
                      AND cd.day_of_week = ?";
$result = prepareAndExecute($conn, $todaySessionsQuery, "is", [$teacher_id, $dayOfWeek]);
$todaySessions = $result->fetch_assoc()['count'];

// Get pending attendance
$pendingQuery = "SELECT COUNT(DISTINCT c.Class_id) as count 
                FROM classes c 
                INNER JOIN class_days cd ON c.Class_id = cd.class_id 
                LEFT JOIN attendance a ON (
                    c.Class_id = a.class_id 
                    AND a.date = CURRENT_DATE
                )
                WHERE c.Teacher_id = ? 
                AND cd.day_of_week = ? 
                AND a.attendance_id IS NULL";
$result = prepareAndExecute($conn, $pendingQuery, "is", [$teacher_id, $dayOfWeek]);
$pendingAttendance = $result->fetch_assoc()['count'];

// Get student count - Updated query to remove block_classes dependency
$studentCountQuery = "SELECT COUNT(DISTINCT s.Student_Id) as count 
                     FROM student s 
                     INNER JOIN blocks b ON s.block_id = b.block_id
                     INNER JOIN classes c ON c.block_id = b.block_id 
                     WHERE c.Teacher_id = ?";
$result = prepareAndExecute($conn, $studentCountQuery, "i", [$teacher_id]);
$studentCount = $result->fetch_assoc()['count'];

// Get class schedule for calendar
$scheduleQuery = "SELECT 
    c.Class_id,
    s.Subject_Name,
    c.start_time,
    c.end_time,
    GROUP_CONCAT(cd.day_of_week) as days,
    COALESCE(b.block_name, 'Unassigned') as block_name
    FROM classes c
    INNER JOIN subject s ON c.Subject_id = s.Subject_id
    INNER JOIN class_days cd ON c.Class_id = cd.class_id
    LEFT JOIN blocks b ON c.block_id = b.block_id
    WHERE c.Teacher_id = ?
    GROUP BY c.Class_id";
$result = prepareAndExecute($conn, $scheduleQuery, "i", [$teacher_id]);
$schedule = $result;

$subjectColors = [
    '#4f46e5', // Indigo
    '#dc2626', // Red
    '#059669', // Green
    '#d97706', // Orange
    '#7c3aed', // Purple
    '#2563eb', // Blue
    '#db2777', // Pink
    '#0891b2', // Cyan
];

$usedSubjectColors = [];
$classEvents = [];
while ($class = $schedule->fetch_assoc()) {
    // Assign consistent color for each subject
    if (!isset($usedSubjectColors[$class['Subject_Name']])) {
        $usedSubjectColors[$class['Subject_Name']] = $subjectColors[count($usedSubjectColors) % count($subjectColors)];
    }
    
    $days = explode(',', $class['days']);
    foreach ($days as $day) {
        $classEvents[] = [
            'title' => $class['Subject_Name'],  // Simplified title, removed block name
            'daysOfWeek' => [['Sunday'=>0,'Monday'=>1,'Tuesday'=>2,'Wednesday'=>3,
                             'Thursday'=>4,'Friday'=>5,'Saturday'=>6][$day]],
            'startTime' => $class['start_time'],
            'endTime' => $class['end_time'],
            'color' => $usedSubjectColors[$class['Subject_Name']],
            'borderColor' => $usedSubjectColors[$class['Subject_Name']],
            'textColor' => '#ffffff',
            'classNames' => ['subject-' . strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $class['Subject_Name']))],
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css" rel="stylesheet">
    <link href="/Beta_attendance_system/assets/css/main.css" rel="stylesheet">
    <link href="/Beta_attendance_system/assets/css/calendar.css" rel="stylesheet">
</head>
<body>
    <?php include '../../app/includes/teacher_navbar.php'; ?>

    <div class="container mt-4">
        <h2 class="teacher-welcome">Welcome, <?php echo htmlspecialchars($_SESSION['teacher_name']); ?></h2>
        
        <!-- Stats Grid -->
        <div class="dashboard-grid">
            <div class="dashboard-card">
                <div class="card-body">
                    <h5 class="card-title">My Classes</h5>
                    <p class="display-4"><?php echo $classCount; ?></p>
                </div>
            </div>
            <div class="dashboard-card">
                <div class="card-body">
                    <h5 class="card-title">Today's Sessions</h5>
                    <p class="display-4"><?php echo $todaySessions; ?></p>
                </div>
            </div>
            <div class="dashboard-card">
                <div class="card-body">
                    <h5 class="card-title">Pending Attendance</h5>
                    <p class="display-4"><?php echo $pendingAttendance; ?></p>
                </div>
            </div>
            <div class="dashboard-card">
                <div class="card-body">
                    <h5 class="card-title">Total Students</h5>
                    <p class="display-4"><?php echo $studentCount; ?></p>
                </div>
            </div>
        </div>

        <!-- Calendar Section -->
        <div class="dashboard-calendar-wrapper">
            <div id="calendar"></div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var calendarEl = document.getElementById('calendar');
            var calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                height: 'auto',
                contentHeight: 'auto',
                aspectRatio: 1.8,
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek'
                },
                events: <?php echo json_encode($classEvents); ?>,
                dayMaxEvents: 2,
                moreLinkClick: 'popover',
                eventDisplay: 'block',
                displayEventTime: false,
                eventContent: function(arg) {
                    if (arg.view.type === 'dayGridMonth') {
                        return {
                            html: `<div class="fc-event-title" style="font-size: 0.688rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">${arg.event.title}</div>`
                        };
                    }
                    return;
                },
                eventDidMount: function(info) {
                    info.el.title = `${info.event.title}\n${info.event.extendedProps.startTime} - ${info.event.extendedProps.endTime}`;
                },
                slotMinTime: '07:00:00',
                slotMaxTime: '19:00:00',
                allDaySlot: false,
                nowIndicator: true,
                eventTimeFormat: {
                    hour: '2-digit',
                    minute: '2-digit',
                    meridiem: true
                },
                slotLabelFormat: {
                    hour: '2-digit',
                    minute: '2-digit',
                    meridiem: true
                }
            });
            calendar.render();
        });
    </script>
</body>
</html>
