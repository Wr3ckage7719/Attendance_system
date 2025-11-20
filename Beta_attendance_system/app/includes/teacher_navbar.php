<?php
$base_path = "/Beta_attendance_system/teacher/";
$current_page = basename($_SERVER['PHP_SELF']);
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="/Beta_attendance_system/assets/css/navbar.css">
<nav class="navbar navbar-expand-lg modern-navbar teacher-navbar">
    <div class="container-fluid">
        <a class="navbar-brand" href="<?php echo $base_path; ?>dashboard/index.php">
            <i class="bi bi-person-workspace me-2"></i>Teacher Panel
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page == 'index.php' ? 'active' : ''; ?>" 
                       href="<?php echo $base_path; ?>dashboard/index.php">
                       <i class="bi bi-grid me-1"></i>Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page == 'take.php' ? 'active' : ''; ?>" 
                       href="<?php echo $base_path; ?>attendance/take.php">
                       <i class="bi bi-calendar-plus me-1"></i>Take Attendance
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page == 'attendance_history.php' ? 'active' : ''; ?>" 
                       href="<?php echo $base_path; ?>attendance/attendance_history.php">
                       <i class="bi bi-clock-history me-1"></i>Attendance History
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page == 'classes.php' ? 'active' : ''; ?>" 
                       href="<?php echo $base_path; ?>dashboard/classes.php">
                       <i class="bi bi-journal-bookmark me-1"></i>My Classes
                    </a>
                </li>
            </ul>
            <ul class="navbar-nav">
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page == 'index.php' ? 'active' : ''; ?>" 
                       href="<?php echo $base_path; ?>settings/index.php">
                       <i class="bi bi-gear me-1"></i>Settings
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/Beta_attendance_system/app/auth/logout.php">
                        <i class="bi bi-box-arrow-right me-1"></i>Logout
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>
