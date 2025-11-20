<?php
session_start();
require_once '../config/database.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $conn = connectDB();
    $email = $conn->real_escape_string($_POST['email']);
    $password = $_POST['password'];
    $role = $_POST['role'];

    // Enhanced debug logging
    error_log("Login attempt - Email: $email, Role: $role");

    if ($role === 'admin') {
        $sql = "SELECT u.*, a.admin_id, a.first_name, a.last_name 
                FROM users u 
                INNER JOIN admin a ON u.user_id = a.user_id 
                WHERE u.email = ? AND u.role = 'admin'";
    } else {
        $sql = "SELECT u.*, t.Teacher_id, t.first_name, t.last_name 
                FROM users u 
                INNER JOIN teachers t ON u.user_id = t.user_id 
                WHERE u.email = ? AND u.role = 'teacher'";
    }

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Prepare failed: " . $conn->error);
        $error = "Database error";
    } else {
        $stmt->bind_param("s", $email);
        if (!$stmt->execute()) {
            error_log("Execute failed: " . $stmt->error);
            $error = "Database error";
        } else {
            $result = $stmt->get_result();
            error_log("Query results: " . $result->num_rows . " rows found");
            
            if ($user = $result->fetch_assoc()) {
                if (password_verify($password, $user['password'])) {
                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['name'] = $user['first_name'] . ' ' . $user['last_name'];
                    $_SESSION['last_activity'] = time();
                    
                    if (isset($_POST['remember_me'])) {
                        setcookie('email', $email, time() + (86400 * 30), "/");
                    }
                    
                    if ($role === 'admin') {
                        $_SESSION['admin_id'] = $user['admin_id'];
                        $_SESSION['success'] = "Login successful as Admin!";
                        header("Location: /Beta_attendance_system/admin/dashboard/admin_dashboard.php");
                    } else {
                        $_SESSION['teacher_id'] = $user['Teacher_id'];
                        $_SESSION['teacher_name'] = $user['first_name'] . ' ' . $user['last_name'];
                        $_SESSION['success'] = "Login successful as Teacher!";
                        header("Location: /Beta_attendance_system/teacher/dashboard/index.php");
                    }
                    exit();
                } else {
                    error_log("Password verification failed for user: $email");
                    $error = "Invalid password";
                }
            } else {
                error_log("No user found with email: $email and role: $role");
                $error = "Invalid credentials for $role login";
            }
        }
    }
    $stmt->close();
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Attendance Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/utilities.css">
    <link rel="stylesheet" href="../../assets/css/auth.css">
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <h3 class="auth-title">Welcome Back</h3>
                <p class="auth-subtitle">Sign in to your account</p>
            </div>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <?php 
                        echo $_SESSION['success'];
                        unset($_SESSION['success']);
                    ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>

            <ul class="nav nav-pills nav-justified mb-4" id="loginTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="teacher-tab" data-bs-toggle="pill" 
                            data-bs-target="#teacher-login" type="button">Teacher Login</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="admin-tab" data-bs-toggle="pill" 
                            data-bs-target="#admin-login" type="button">Admin Login</button>
                </li>
            </ul>

            <div class="tab-content">
                <!-- Teacher Login Panel -->
                <div class="tab-pane fade show active" id="teacher-login">
                    <form method="POST" action="">
                        <input type="hidden" name="role" value="teacher">
                        <div class="form-group">
                            <label for="teacher-email" class="form-label">Email address</label>
                            <input type="email" class="form-control" id="teacher-email" name="email" required>
                        </div>
                        <div class="form-group">
                            <label for="teacher-password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="teacher-password" name="password" required>
                        </div>
                        <div class="form-group">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="teacher-remember" name="remember_me">
                                <label class="form-check-label" for="teacher-remember">Remember me</label>
                            </div>
                        </div>
                        <button type="submit" class="auth-button">Login as Teacher</button>
                    </form>
                    <div class="auth-links">
                        <a href="register.php">Don't have an account? Register here</a>
                    </div>
                </div>

                <!-- Admin Login Panel -->
                <div class="tab-pane fade" id="admin-login">
                    <form method="POST" action="">
                        <input type="hidden" name="role" value="admin">
                        <div class="form-group">
                            <label for="admin-email" class="form-label">Admin Email</label>
                            <input type="email" class="form-control" id="admin-email" name="email" required>
                        </div>
                        <div class="form-group">
                            <label for="admin-password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="admin-password" name="password" required>
                        </div>
                        <div class="form-group">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="admin-remember" name="remember_me">
                                <label class="form-check-label" for="admin-remember">Remember me</label>
                            </div>
                        </div>
                        <button type="submit" class="auth-button bg-dark">Login as Admin</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
