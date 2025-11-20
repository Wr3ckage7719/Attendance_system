<?php
session_start();
require_once '../../app/config/auth_check.php';
require_once '../../app/config/database.php';
checkAuth('admin');

$conn = connectDB();
// Get counts for dashboard
$teacherCount = $conn->query("SELECT COUNT(*) as count FROM teachers")->fetch_assoc()['count'];
$courseCount = $conn->query("SELECT COUNT(*) as count FROM courses")->fetch_assoc()['count'];
$subjectCount = $conn->query("SELECT COUNT(*) as count FROM subject")->fetch_assoc()['count'];
$studentCount = $conn->query("SELECT COUNT(*) as count FROM student")->fetch_assoc()['count'];
$classCount = $conn->query("SELECT COUNT(*) as count FROM classes")->fetch_assoc()['count'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <?php include '../../app/includes/admin_navbar.php'; ?>

    <div class="container mt-4">
        <h2>Welcome, Admin</h2>
        <div class="row mt-4">
            <div class="col-md-4 mb-3">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <h5 class="card-title">Total Teachers</h5>
                        <p class="card-text display-4"><?php echo $teacherCount; ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <h5 class="card-title">Total Students</h5>
                        <p class="card-text display-4"><?php echo $studentCount; ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card bg-info text-white">
                    <div class="card-body">
                        <h5 class="card-title">Total Classes</h5>
                        <p class="card-text display-4"><?php echo $classCount; ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card bg-warning text-white">
                    <div class="card-body">
                        <h5 class="card-title">Total Courses</h5>
                        <p class="card-text display-4"><?php echo $courseCount; ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card bg-secondary text-white">
                    <div class="card-body">
                        <h5 class="card-title">Total Subjects</h5>
                        <p class="card-text display-4"><?php echo $subjectCount; ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions Section -->
        <div class="mt-4">
            <h3>Quick Actions</h3>
            <div class="row">
                <div class="col-md-4 mb-3">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">User Management</h5>
                            <div class="list-group">
                                <a href="../management/manage_teachers.php" class="list-group-item list-group-item-action">Manage Teachers</a>
                                <a href="../management/manage_students.php" class="list-group-item list-group-item-action">Manage Students</a>
                                <a href="../management/reset_passwords.php" class="list-group-item list-group-item-action">Reset User Passwords</a>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Academic Management</h5>
                            <div class="list-group">
                                <a href="../management/manage_courses.php" class="list-group-item list-group-item-action">Manage Courses</a>
                                <a href="../management/manage_subjects.php" class="list-group-item list-group-item-action">Manage Subjects</a>
                                <a href="../management/teacher_loads.php" class="list-group-item list-group-item-action">Assign Teaching Loads</a>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Reports & Analytics</h5>
                            <div class="list-group">
                                <a href="../management/teacher_loads.php" class="list-group-item list-group-item-action">View Teaching Loads</a>
                                <a href="../reports/attendance_reports.php" class="list-group-item list-group-item-action">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span>Attendance Reports</span>
                                        <span class="badge bg-primary rounded-pill">View</span>
                                    </div>
                                </a>
                                <a href="../reports/student_reports.php" class="list-group-item list-group-item-action">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span>Student Reports</span>
                                        <span class="badge bg-primary rounded-pill">View</span>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
