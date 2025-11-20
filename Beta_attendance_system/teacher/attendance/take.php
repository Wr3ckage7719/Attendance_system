<?php
session_start();
require_once '../../app/config/auth_check.php';
require_once '../../app/config/database.php';
checkAuth('teacher');

$conn = connectDB();
$teacher_id = $_SESSION['teacher_id'];
$message = '';

// Handle attendance submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_attendance'])) {
    try {
        if (!isset($_POST['attendance']) || !is_array($_POST['attendance'])) {
            throw new Exception("No attendance data submitted");
        }

        $class_id = $_POST['class_id'];
        $date = $_POST['date'];
        $attendance = $_POST['attendance'];

        // Start transaction
        $conn->begin_transaction();

        try {
            foreach ($attendance as $student_id => $status) {
                if (!in_array($status, ['present', 'absent', 'late'])) {
                    throw new Exception("Invalid attendance status");
                }

                // Use REPLACE INTO to handle duplicates
                $stmt = $conn->prepare("REPLACE INTO attendance (class_id, student_id, date, status) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("isss", $class_id, $student_id, $date, $status);
                
                if (!$stmt->execute()) {
                    error_log("Failed to save attendance for student $student_id: " . $conn->error);
                    throw new Exception("Error saving attendance: " . $conn->error);
                }
            }

            $conn->commit();
            $message = '<div class="alert alert-success">Attendance has been recorded successfully.</div>';
        } catch (Exception $e) {
            $conn->rollback();
            throw $e;
        }
    } catch (Exception $e) {
        $message = '<div class="alert alert-danger">Error: ' . $e->getMessage() . '</div>';
    }
}

// Add error panel at the top of the page
$error_panel = '';
if ($conn->error) {
    $error_panel = '<div class="alert alert-danger">Database Error: ' . $conn->error . '</div>';
}

// Update class query to get proper schedule info
$classQuery = "SELECT c.Class_id, 
               GROUP_CONCAT(DISTINCT cd.day_of_week) as scheduled_days,
               CONCAT(s.Subject_Name, ' (', b.block_name, ') - ', 
                     TIME_FORMAT(c.start_time, '%h:%i %p'), ' - ',
                     TIME_FORMAT(c.end_time, '%h:%i %p')) as class_name,
               c.start_time,
               c.end_time 
               FROM classes c 
               INNER JOIN subject s ON c.Subject_id = s.Subject_id
               LEFT JOIN blocks b ON c.block_id = b.block_id
               INNER JOIN class_days cd ON c.Class_id = cd.class_id
               WHERE c.Teacher_id = ?
               GROUP BY c.Class_id";
$stmt = $conn->prepare($classQuery);
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$classes = $stmt->get_result();

// Get students if class is selected
$students = null;
if (isset($_GET['class_id'])) {
    $class_id = $_GET['class_id'];
    $date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
    
    // Direct student query without date verification
    $studentQuery = "SELECT s.*, 
                           a.status as attendance_status,
                           b.block_name,
                           co.course_name,
                           s.year_level
                    FROM classes c
                    INNER JOIN blocks b ON c.block_id = b.block_id 
                    INNER JOIN student s ON s.block_id = b.block_id
                    INNER JOIN courses co ON b.course_id = co.course_id
                    LEFT JOIN attendance a ON (
                        a.student_id = s.Student_Id 
                        AND a.class_id = ? 
                        AND a.date = ?
                    )
                    WHERE c.Class_id = ?
                    ORDER BY s.year_level, s.Last_name, s.first_name";
    $stmt = $conn->prepare($studentQuery);
    $stmt->bind_param("isi", $class_id, $date, $class_id);
    $stmt->execute();
    $students = $stmt->get_result();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Take Attendance</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="/Beta_attendance_system/assets/css/main.css" rel="stylesheet">
    <link href="/Beta_attendance_system/assets/css/teacher-pages.css" rel="stylesheet">
</head>
<body>
    <?php include '../../app/includes/teacher_navbar.php'; ?>

    <div class="container mt-4">
        <h2 class="page-header">Take Attendance</h2>
        <?php 
        echo $error_panel; 
        echo $message; 
        ?>

        <!-- Class Selection Form -->
        <div class="filter-card">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-6">
                        <label for="class_id" class="form-label">Select Class</label>
                        <select class="form-select" name="class_id" required onchange="this.form.submit()">
                            <option value="">Choose a class...</option>
                            <?php while ($class = $classes->fetch_assoc()): ?>
                                <option value="<?php echo $class['Class_id']; ?>"
                                    <?php echo (isset($_GET['class_id']) && $_GET['class_id'] == $class['Class_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($class['class_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="date" class="form-label">Date</label>
                        <input type="date" class="form-control" name="date" 
                               value="<?php echo isset($_GET['date']) ? $_GET['date'] : date('Y-m-d'); ?>" 
                               required>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">Load Class</button>
                    </div>
                </form>
            </div>
        </div>

        <?php if ($students && $students->num_rows > 0): ?>
        <!-- Attendance Form -->
        <form method="POST">
            <input type="hidden" name="class_id" value="<?php echo $_GET['class_id']; ?>">
            <input type="hidden" name="date" value="<?php echo isset($_GET['date']) ? $_GET['date'] : date('Y-m-d'); ?>">
            
            <div class="content-card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table attendance-table">
                            <thead>
                                <tr>
                                    <th>Student ID</th>
                                    <th>Name</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($student = $students->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($student['Student_Id']); ?></td>
                                    <td>
                                        <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['Last_name']); ?>
                                        <small class="text-muted">(<?php echo htmlspecialchars($student['year_level']); ?>th Year)</small>
                                    </td>
                                    <td>
                                        <div class="btn-group attendance-buttons" role="group">
                                            <input type="radio" class="btn-check" 
                                                   name="attendance[<?php echo $student['Student_Id']; ?>]" 
                                                   value="present" id="present_<?php echo $student['Student_Id']; ?>"
                                                   <?php echo ($student['attendance_status'] === 'present') ? 'checked' : ''; ?> required>
                                            <label class="btn btn-outline-success" 
                                                   for="present_<?php echo $student['Student_Id']; ?>">Present</label>

                                            <input type="radio" class="btn-check" 
                                                   name="attendance[<?php echo $student['Student_Id']; ?>]" 
                                                   value="late" id="late_<?php echo $student['Student_Id']; ?>"
                                                   <?php echo ($student['attendance_status'] === 'late') ? 'checked' : ''; ?> required>
                                            <label class="btn btn-outline-warning" 
                                                   for="late_<?php echo $student['Student_Id']; ?>">Late</label>

                                            <input type="radio" class="btn-check" 
                                                   name="attendance[<?php echo $student['Student_Id']; ?>]" 
                                                   value="absent" id="absent_<?php echo $student['Student_Id']; ?>"
                                                   <?php echo ($student['attendance_status'] === 'absent') ? 'checked' : ''; ?> required>
                                            <label class="btn btn-outline-danger" 
                                                   for="absent_<?php echo $student['Student_Id']; ?>">Absent</label>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="text-end mt-3">
                        <button type="submit" name="submit_attendance" class="btn btn-primary">Save Attendance</button>
                    </div>
                </div>
            </div>
        </form>
        <?php elseif (isset($_GET['class_id'])): ?>
            <div class="alert alert-info">No students found in this class.</div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
