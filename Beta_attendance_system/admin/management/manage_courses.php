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
        try {
            if ($_POST['action'] === 'add') {
                // Check if course code already exists
                $check = $conn->prepare("SELECT COUNT(*) as count FROM courses WHERE course_code = ?");
                $check->bind_param("s", $_POST['course_code']);
                $check->execute();
                $result = $check->get_result()->fetch_assoc();
                
                if ($result['count'] > 0) {
                    throw new Exception("Course code already exists");
                }
                
                $course_name = $_POST['course_name'];
                $course_code = $_POST['course_code'];
                $description = $_POST['description'];

                $stmt = $conn->prepare("INSERT INTO courses (course_name, course_code, description) VALUES (?, ?, ?)");
                $stmt->bind_param("sss", $course_name, $course_code, $description);
                
                if ($stmt->execute()) {
                    $message = "Course added successfully";
                    $messageType = "success";
                } else {
                    throw new Exception("Error adding course: " . $conn->error);
                }
            } elseif ($_POST['action'] === 'edit') {
                $course_id = $_POST['course_id'];
                $course_name = $_POST['course_name'];
                $course_code = $_POST['course_code'];
                $description = $_POST['description'];

                $stmt = $conn->prepare("UPDATE courses SET course_name = ?, course_code = ?, description = ? WHERE course_id = ?");
                $stmt->bind_param("sssi", $course_name, $course_code, $description, $course_id);
                
                if ($stmt->execute()) {
                    $message = "Course updated successfully";
                    $messageType = "success";
                }
            } elseif ($_POST['action'] === 'delete') {
                $course_id = $_POST['course_id'];
                
                // First check if course has any classes
                $check = $conn->prepare("SELECT COUNT(*) as count FROM classes WHERE course_id = ?");
                $check->bind_param("i", $course_id);
                $check->execute();
                $result = $check->get_result()->fetch_assoc();
                
                if ($result['count'] > 0) {
                    throw new Exception("Cannot delete course that has active classes");
                }
                
                $stmt = $conn->prepare("DELETE FROM courses WHERE course_id = ?");
                $stmt->bind_param("i", $course_id);
                
                if ($stmt->execute()) {
                    $message = "Course deleted successfully";
                    $messageType = "success";
                }
            }
        } catch (Exception $e) {
            $message = "Error: " . $e->getMessage();
            $messageType = "danger";
        }
    }
}

// Fetch all courses
$courses = $conn->query("SELECT * FROM courses ORDER BY course_name");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Courses</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <?php include '../../app/includes/admin_navbar.php'; ?>

    <div class="container mt-4">
        <h2>Manage Courses</h2>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show">
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Add Course Button -->
        <button class="btn btn-primary mb-4" data-bs-toggle="modal" data-bs-target="#addCourseModal">
            Add New Course
        </button>

        <!-- Courses List -->
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Course Code</th>
                                <th>Course Name</th>
                                <th>Description</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($course = $courses->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($course['course_code']); ?></td>
                                    <td><?php echo htmlspecialchars($course['course_name']); ?></td>
                                    <td><?php echo htmlspecialchars($course['description']); ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-primary" 
                                                onclick="editCourse(<?php echo $course['course_id']; ?>, 
                                                '<?php echo addslashes($course['course_name']); ?>', 
                                                '<?php echo addslashes($course['course_code']); ?>', 
                                                '<?php echo addslashes($course['description']); ?>')">
                                            Edit
                                        </button>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="course_id" value="<?php echo $course['course_id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger" 
                                                    onclick="return confirm('Are you sure? This will affect all related classes.')">
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

    <!-- Add Course Modal -->
    <div class="modal fade" id="addCourseModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Course</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add">
                        <div class="mb-3">
                            <label class="form-label">Course Code</label>
                            <input type="text" class="form-control" name="course_code" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Course Name</label>
                            <input type="text" class="form-control" name="course_name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">Add Course</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Course Modal -->
    <div class="modal fade" id="editCourseModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Course</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="course_id" id="edit_course_id">
                        <div class="mb-3">
                            <label class="form-label">Course Code</label>
                            <input type="text" class="form-control" name="course_code" id="edit_course_code" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Course Name</label>
                            <input type="text" class="form-control" name="course_name" id="edit_course_name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" id="edit_description" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editCourse(courseId, courseName, courseCode, description) {
            document.getElementById('edit_course_id').value = courseId;
            document.getElementById('edit_course_name').value = courseName;
            document.getElementById('edit_course_code').value = courseCode;
            document.getElementById('edit_description').value = description;
            new bootstrap.Modal(document.getElementById('editCourseModal')).show();
        }
    </script>
</body>
</html>
