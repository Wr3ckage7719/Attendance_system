<?php
session_start();
require_once '../../app/config/auth_check.php';
require_once '../../app/config/database.php';
checkAuth('teacher');

$conn = connectDB();
$teacher_id = $_SESSION['teacher_id'];

// Update the query to include year level and class days
$query = "SELECT c.*,
          s.Subject_Name,
          s.Subject_code,
          co.course_name,
          co.course_code,
          b.block_name,
          GROUP_CONCAT(cd.day_of_week) as class_days,
          (SELECT COUNT(*) FROM student WHERE block_id = b.block_id AND year_level = c.year_level) as student_count,
          (SELECT COUNT(*) FROM attendance WHERE class_id = c.Class_id) as attendance_count
          FROM classes c
          INNER JOIN subject s ON c.Subject_id = s.Subject_id
          INNER JOIN courses co ON c.course_id = co.course_id
          LEFT JOIN blocks b ON c.block_id = b.block_id
          LEFT JOIN class_days cd ON c.Class_id = cd.class_id
          WHERE c.Teacher_id = ?
          GROUP BY c.Class_id
          ORDER BY c.year_level, c.day_of_week, c.start_time";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$classes = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Classes</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="/Beta_attendance_system/assets/css/main.css" rel="stylesheet">
    <link href="/Beta_attendance_system/assets/css/teacher-pages.css" rel="stylesheet">
</head>
<body>
    <?php include '../../app/includes/teacher_navbar.php'; ?>

    <div class="container mt-4">
        <h2 class="page-header">My Classes</h2>

        <div class="class-grid">
            <?php while ($class = $classes->fetch_assoc()): ?>
                <div class="class-card">
                    <div class="card-header">
                        <h5><?php echo htmlspecialchars($class['Subject_Name']); ?></h5>
                        <small><?php echo htmlspecialchars($class['Subject_code']); ?></small>
                    </div>
                    <div class="card-body">
                        <div class="class-info">
                            <strong>Course:</strong>
                            <span><?php echo htmlspecialchars($class['course_name']); ?></span>
                            <small class="d-block text-muted ps-4"><?php echo htmlspecialchars($class['course_code']); ?></small>
                        </div>

                        <div class="class-info">
                            <strong>Block:</strong>
                            <span class="badge bg-secondary">
                                <?php echo $class['block_name'] ? 'Block ' . htmlspecialchars($class['block_name']) : 'No Block'; ?>
                            </span>
                        </div>

                        <div class="class-info">
                            <strong>Schedule:</strong>
                            <div class="mt-2">
                                <span class="time-badge">
                                    <?php echo htmlspecialchars($class['class_days']); ?>
                                </span>
                            </div>
                            <div>
                                <span class="time-badge">
                                    <?php echo date('h:i A', strtotime($class['start_time'])); ?> - 
                                    <?php echo date('h:i A', strtotime($class['end_time'])); ?>
                                </span>
                            </div>
                        </div>

                        <div class="class-info mb-0">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <div>
                                    <strong>Students:</strong>
                                    <span class="stats-badge bg-info"><?php echo $class['student_count']; ?></span>
                                </div>
                                <div>
                                    <strong>Records:</strong>
                                    <span class="stats-badge bg-success"><?php echo $class['attendance_count']; ?></span>
                                </div>
                            </div>
                        </div>

                        <div class="class-action-buttons">
                            <a href="../attendance/take.php?class_id=<?php echo $class['Class_id']; ?>" 
                               class="btn btn-success">Take Attendance</a>
                            <a href="../attendance/attendance_history.php?class_id=<?php echo $class['Class_id']; ?>" 
                               class="btn btn-primary">View History</a>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
