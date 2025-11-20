<?php
$base_path = "/Beta_attendance_system/admin/";
$current_page = basename($_SERVER['PHP_SELF']);
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="/Beta_attendance_system/assets/css/navbar.css">
<nav class="navbar navbar-expand-lg modern-navbar admin-navbar">
    <div class="container-fluid">
        <a class="navbar-brand" href="<?php echo $base_path; ?>dashboard/admin_dashboard.php">
            <i class="bi bi-shield-lock me-2"></i>Admin Panel
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page == 'admin_dashboard.php' ? 'active' : ''; ?>" 
                       href="<?php echo $base_path; ?>dashboard/admin_dashboard.php">
                       <i class="bi bi-grid me-1"></i>Dashboard
                    </a>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="bi bi-people me-1"></i>User Management
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="<?php echo $base_path; ?>management/manage_teachers.php">
                            <i class="bi bi-person-badge me-2"></i>Manage Teachers</a></li>
                        <li><a class="dropdown-item" href="<?php echo $base_path; ?>management/manage_students.php">
                            <i class="bi bi-mortarboard me-2"></i>Manage Students</a></li>
                        <li><a class="dropdown-item" href="<?php echo $base_path; ?>management/reset_passwords.php">
                            <i class="bi bi-key me-2"></i>Reset Passwords</a></li>
                    </ul>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="bi bi-book me-1"></i>Academic Management
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="<?php echo $base_path; ?>management/manage_courses.php">
                            <i class="bi bi-journal-text me-2"></i>Manage Courses</a></li>
                        <li><a class="dropdown-item" href="<?php echo $base_path; ?>management/manage_subjects.php">
                            <i class="bi bi-journal-check me-2"></i>Manage Subjects</a></li>
                        <li><a class="dropdown-item" href="<?php echo $base_path; ?>management/teacher_loads.php">
                            <i class="bi bi-person-workspace me-2"></i>Teacher Load Assignment</a></li>
                    </ul>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="bi bi-graph-up me-1"></i>Reports
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="<?php echo $base_path; ?>reports/attendance_reports.php">
                            <i class="bi bi-calendar-check me-2"></i>Attendance Reports</a></li>
                        <li><a class="dropdown-item" href="<?php echo $base_path; ?>reports/student_reports.php">
                            <i class="bi bi-file-earmark-text me-2"></i>Student Reports</a></li>
                    </ul>
                </li>
            </ul>
            <ul class="navbar-nav">
                <li class="nav-item">
                    <a class="nav-link" href="/Beta_attendance_system/app/auth/logout.php">
                        <i class="bi bi-box-arrow-right me-1"></i>Logout
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>
