<?php
session_start();
require_once '../../app/config/auth_check.php';
require_once '../../app/config/database.php';
checkAuth('teacher');

$conn = connectDB();
$teacher_id = $_SESSION['teacher_id'];
$message = '';

// Get teacher details
$stmt = $conn->prepare("SELECT * FROM teachers WHERE Teacher_id = ?");
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$teacher = $stmt->get_result()->fetch_assoc();

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];

        // Verify current password
        if (password_verify($current_password, $teacher['password'])) {
            if ($new_password === $confirm_password) {
                if (strlen($new_password) >= 8) {
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $update = $conn->prepare("UPDATE teachers SET password = ? WHERE Teacher_id = ?");
                    $update->bind_param("si", $hashed_password, $teacher_id);
                    
                    if ($update->execute()) {
                        $message = '<div class="alert alert-success">Password updated successfully!</div>';
                    } else {
                        $message = '<div class="alert alert-danger">Error updating password.</div>';
                    }
                } else {
                    $message = '<div class="alert alert-danger">New password must be at least 8 characters long.</div>';
                }
            } else {
                $message = '<div class="alert alert-danger">New passwords do not match.</div>';
            }
        } else {
            $message = '<div class="alert alert-danger">Current password is incorrect.</div>';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Teacher Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="/Beta_attendance_system/assets/css/main.css" rel="stylesheet">
    <link href="/Beta_attendance_system/assets/css/teacher-pages.css" rel="stylesheet">
</head>
<body>
    <?php include '../../app/includes/teacher_navbar.php'; ?>

    <div class="container mt-4">
        <h2 class="page-header">Account Settings</h2>
        <?php echo $message; ?>

        <div class="row g-4">
            <div class="col-md-6">
                <div class="content-card">
                    <div class="card-body">
                        <h5 class="card-title mb-4">Profile Information</h5>
                        <div class="profile-info">
                            <div class="info-item">
                                <strong>Name</strong>
                                <span><?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?></span>
                            </div>
                            <div class="info-item">
                                <strong>Email</strong>
                                <span><?php echo htmlspecialchars($teacher['email']); ?></span>
                            </div>
                            <div class="info-item">
                                <strong>Teacher ID</strong>
                                <span><?php echo htmlspecialchars($teacher['Teacher_id']); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="content-card">
                    <div class="card-body">
                        <h5 class="card-title mb-4">Change Password</h5>
                        <form method="POST" onsubmit="return validatePassword()">
                            <div class="mb-4">
                                <label for="current_password" class="form-label">Current Password</label>
                                <input type="password" class="form-control" id="current_password" name="current_password" required>
                            </div>
                            <div class="mb-4">
                                <label for="new_password" class="form-label">New Password</label>
                                <input type="password" class="form-control" id="new_password" name="new_password" required>
                                <div class="form-text text-muted">Password must be at least 8 characters long.</div>
                            </div>
                            <div class="mb-4">
                                <label for="confirm_password" class="form-label">Confirm New Password</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                            </div>
                            <button type="submit" name="change_password" class="btn btn-primary px-4">Change Password</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function validatePassword() {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;

            if (newPassword.length < 8) {
                alert('New password must be at least 8 characters long.');
                return false;
            }

            if (newPassword !== confirmPassword) {
                alert('New passwords do not match.');
                return false;
            }

            return true;
        }
    </script>
</body>
</html>
