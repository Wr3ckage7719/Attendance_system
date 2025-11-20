<?php
session_start();
require_once '../../app/config/auth_check.php';
require_once '../../app/config/database.php';
checkAuth('admin');

$conn = connectDB();
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add') {
            $email = $_POST['email'];
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $first_name = $_POST['first_name'];
            $last_name = $_POST['last_name'];

            // Check if email already exists in users table
            $check_email = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
            $check_email->bind_param("s", $email);
            $check_email->execute();
            if ($check_email->get_result()->num_rows > 0) {
                $message = "Email already exists";
                $messageType = "danger";
            } else {
                $conn->begin_transaction();
                try {
                    // Create user account
                    $stmt = $conn->prepare("INSERT INTO users (email, password, role) VALUES (?, ?, 'teacher')");
                    $stmt->bind_param("ss", $email, $password);
                    $stmt->execute();
                    $user_id = $conn->insert_id;

                    // Get next Teacher_id
                    $result = $conn->query("SELECT MAX(Teacher_id) as max_id FROM teachers");
                    $row = $result->fetch_assoc();
                    $next_teacher_id = ($row['max_id'] !== null) ? $row['max_id'] + 1 : 1;

                    // Create teacher record with auto-incremented Teacher_id
                    $stmt = $conn->prepare("INSERT INTO teachers (Teacher_id, first_name, last_name, user_id, email) VALUES (?, ?, ?, ?, ?)");
                    $stmt->bind_param("issis", $next_teacher_id, $first_name, $last_name, $user_id, $email);
                    $stmt->execute();

                    $conn->commit();
                    $message = "Teacher added successfully";
                    $messageType = "success";
                } catch (Exception $e) {
                    $conn->rollback();
                    $message = "Error: " . $e->getMessage();
                    $messageType = "danger";
                }
            }
        } elseif ($_POST['action'] === 'delete') {
            $teacher_id = $_POST['teacher_id'];
            
            // Check if teacher has attendance records
            $check = $conn->prepare("SELECT COUNT(*) as count FROM attendance a 
                                   INNER JOIN classes c ON a.class_id = c.Class_id 
                                   WHERE c.Teacher_id = ?");
            $check->bind_param("i", $teacher_id);
            $check->execute();
            if ($check->get_result()->fetch_assoc()['count'] > 0) {
                $message = "Cannot delete teacher with existing attendance records";
                $messageType = "danger";
                goto skip_delete;
            }
            
            $conn->begin_transaction();
            try {
                // Delete associated classes first
                $stmt = $conn->prepare("DELETE FROM class_days WHERE class_id IN (SELECT Class_id FROM classes WHERE Teacher_id = ?)");
                $stmt->bind_param("i", $teacher_id);
                $stmt->execute();

                $stmt = $conn->prepare("DELETE FROM classes WHERE Teacher_id = ?");
                $stmt->bind_param("i", $teacher_id);
                $stmt->execute();
                
                // Get user_id before deleting teacher
                $stmt = $conn->prepare("SELECT user_id FROM teachers WHERE Teacher_id = ?");
                $stmt->bind_param("i", $teacher_id);
                $stmt->execute();
                $teacher = $stmt->get_result()->fetch_assoc();
                
                if ($teacher) {
                    // Delete teacher record
                    $stmt = $conn->prepare("DELETE FROM teachers WHERE Teacher_id = ?");
                    $stmt->bind_param("i", $teacher_id);
                    $stmt->execute();
                    
                    // Delete user record
                    $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
                    $stmt->bind_param("i", $teacher['user_id']);
                    $stmt->execute();
                }
                
                $conn->commit();
                $message = "Teacher and associated data deleted successfully";
                $messageType = "success";
            } catch (Exception $e) {
                $conn->rollback();
                $message = "Error: " . $e->getMessage();
                $messageType = "danger";
            }
        }
    }
}
skip_delete:

// Updated query to fetch all teachers with proper join
$query = "SELECT t.*, u.email as user_email, 
          (SELECT COUNT(*) FROM classes WHERE Teacher_id = t.Teacher_id) as class_count,
          (SELECT COUNT(*) FROM attendance a 
           INNER JOIN classes c ON a.class_id = c.Class_id 
           WHERE c.Teacher_id = t.Teacher_id) as attendance_count
          FROM teachers t
          INNER JOIN users u ON t.user_id = u.user_id
          WHERE u.role = 'teacher'
          ORDER BY t.first_name";

error_log("Teachers query: " . $query); // Debug log
$teachers = $conn->query($query);
if (!$teachers) {
    error_log("Query error: " . $conn->error);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Teachers</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <?php include '../../app/includes/admin_navbar.php'; ?>

    <div class="container mt-4">
        <h2>Manage Teachers</h2>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show">
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Add Teacher Button -->
        <button class="btn btn-primary mb-4" data-bs-toggle="modal" data-bs-target="#addTeacherModal">
            Add New Teacher
        </button>

        <!-- Teachers List -->
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Classes</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($teacher = $teachers->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $teacher['Teacher_id']; ?></td>
                                    <td><?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?></td>
                                    <td><?php echo htmlspecialchars($teacher['user_email']); ?></td>
                                    <td><?php echo $teacher['class_count']; ?></td>
                                    <td>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="teacher_id" value="<?php echo $teacher['Teacher_id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger" 
                                                    onclick="return confirm('Warning!\n\nThis will permanently delete:\n- The teacher account\n- All their assigned classes (<?php echo $teacher['class_count']; ?> classes)\n- All related attendance records\n\nAre you sure you want to continue?')">
                                                Delete
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Teacher Modal -->
    <div class="modal fade" id="addTeacherModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Teacher</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add">
                        <div class="mb-3">
                            <label class="form-label">First Name</label>
                            <input type="text" class="form-control" name="first_name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Last Name</label>
                            <input type="text" class="form-control" name="last_name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" class="form-control" name="password" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">Add Teacher</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
