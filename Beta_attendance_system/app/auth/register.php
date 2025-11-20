<?php
session_start();
require_once '../config/database.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $conn = connectDB();
    
    // Get and validate form data
    $email = $conn->real_escape_string($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Better name handling
    $fullname = trim($conn->real_escape_string($_POST['name']));
    $name_parts = explode(" ", $fullname);
    
    // Ensure we have at least a first name
    if (empty($name_parts[0])) {
        $error = "First name is required";
    } else {
        $firstName = $name_parts[0];
        // Join the rest as last name if exists
        $lastName = count($name_parts) > 1 ? implode(" ", array_slice($name_parts, 1)) : "";
        
        // Continue with validation
        if ($password !== $confirm_password) {
            $error = "Passwords do not match";
        } elseif (strlen($password) < 6) {
            $error = "Password must be at least 6 characters long";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Invalid email format";
        } else {
            // Check if email exists
            $check_email = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
            $check_email->bind_param("s", $email);
            $check_email->execute();
            
            if ($check_email->get_result()->num_rows > 0) {
                $error = "Email already registered";
            } else {
                $conn->begin_transaction();
                try {
                    // Insert into users table
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("INSERT INTO users (email, password, role) VALUES (?, ?, 'teacher')");
                    $stmt->bind_param("ss", $email, $hashed_password);
                    $stmt->execute();
                    
                    $user_id = $conn->insert_id;

                    // Get the next available Teacher_id
                    $result = $conn->query("SELECT MAX(Teacher_id) as max_id FROM teachers");
                    $row = $result->fetch_assoc();
                    $next_teacher_id = ($row['max_id'] !== null) ? $row['max_id'] + 1 : 1;

                    // Insert into teachers table with proper name, email and Teacher_id
                    $stmt = $conn->prepare("INSERT INTO teachers (Teacher_id, first_name, last_name, user_id, email) VALUES (?, ?, ?, ?, ?)");
                    $stmt->bind_param("issis", $next_teacher_id, $firstName, $lastName, $user_id, $email);
                    $stmt->execute();

                    $conn->commit();
                    $_SESSION['success'] = "Account created successfully! Welcome, $firstName!";
                    header("Location: login.php");
                    exit();
                } catch (Exception $e) {
                    $conn->rollback();
                    $error = "Registration failed: " . $e->getMessage();
                }
            }
        }
    }
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Attendance Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/utilities.css">
    <link rel="stylesheet" href="../../assets/css/auth.css">
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <h3 class="auth-title">Create Account</h3>
                <p class="auth-subtitle">Register as a new teacher</p>
            </div>
            <?php if (isset($error)): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            <form method="POST" action="" onsubmit="return validateForm()">
                <div class="form-group">
                    <label for="name" class="form-label">Full Name</label>
                    <input type="text" class="form-control" id="name" name="name" required>
                </div>
                <div class="form-group">
                    <label for="email" class="form-label">Email address</label>
                    <input type="email" class="form-control" id="email" name="email" required>
                </div>
                <div class="form-group">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>
                <div class="form-group">
                    <label for="confirm_password" class="form-label">Confirm Password</label>
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                </div>
                <button type="submit" class="auth-button">Register</button>
            </form>
            <div class="auth-links">
                <a href="login.php">Already have an account? Login here</a>
            </div>
        </div>
    </div>
    <script>
        function validateForm() {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (password !== confirmPassword) {
                alert('Passwords do not match!');
                return false;
            }
            
            if (password.length < 6) {
                alert('Password must be at least 6 characters long!');
                return false;
            }
            
            return true;
        }
    </script>
</body>
</html>
