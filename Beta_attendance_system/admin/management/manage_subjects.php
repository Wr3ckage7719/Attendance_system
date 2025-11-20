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
                $check = $conn->prepare("SELECT COUNT(*) as count FROM subject WHERE Subject_code = ? AND course_id = ?");
                $check->bind_param("si", $_POST['subject_code'], $_POST['course_id']);
                $check->execute();
                if ($check->get_result()->fetch_assoc()['count'] > 0) {
                    throw new Exception("Subject code already exists for this course");
                }

                $subject_name = $_POST['subject_name'];
                $subject_code = $_POST['subject_code'];
                $course_id = $_POST['course_id'];
                $year_level = $_POST['year_level'];

                $stmt = $conn->prepare("INSERT INTO subject (Subject_Name, Subject_code, course_id, year_level) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("ssii", $subject_name, $subject_code, $course_id, $year_level);
                
                if (!$stmt->execute()) {
                    throw new Exception("Error adding subject: " . $conn->error);
                }
                $message = "Subject added successfully";
                $messageType = "success";
            } elseif ($_POST['action'] === 'edit') {
                $subject_id = $_POST['subject_id'];
                $subject_name = $_POST['subject_name'];
                $subject_code = $_POST['subject_code'];
                $course_id = $_POST['course_id'];
                $year_level = $_POST['year_level'];

                $check = $conn->prepare("SELECT COUNT(*) as count FROM subject WHERE Subject_code = ? AND course_id = ? AND Subject_id != ?");
                $check->bind_param("sii", $subject_code, $course_id, $subject_id);
                $check->execute();
                if ($check->get_result()->fetch_assoc()['count'] > 0) {
                    throw new Exception("Subject code already exists for this course");
                }

                $stmt = $conn->prepare("UPDATE subject SET Subject_Name = ?, Subject_code = ?, course_id = ?, year_level = ? WHERE Subject_id = ?");
                $stmt->bind_param("ssiii", $subject_name, $subject_code, $course_id, $year_level, $subject_id);
                
                if (!$stmt->execute()) {
                    throw new Exception("Error updating subject: " . $conn->error);
                }
                $message = "Subject updated successfully";
                $messageType = "success";
            } elseif ($_POST['action'] === 'delete') {
                $subject_id = $_POST['subject_id'];
                
                $check = $conn->prepare("SELECT COUNT(*) as count FROM classes WHERE Subject_id = ?");
                $check->bind_param("i", $subject_id);
                $check->execute();
                if ($check->get_result()->fetch_assoc()['count'] > 0) {
                    throw new Exception("Cannot delete subject that is used in classes");
                }
                
                $stmt = $conn->prepare("DELETE FROM subject WHERE Subject_id = ?");
                $stmt->bind_param("i", $subject_id);
                
                if (!$stmt->execute()) {
                    throw new Exception("Error deleting subject: " . $conn->error);
                }
                $message = "Subject deleted successfully";
                $messageType = "success";
            }
        } catch (Exception $e) {
            $message = $e->getMessage();
            $messageType = "danger";
        }
    }
}

// Fetch all courses for the dropdown
$courses = $conn->query("SELECT course_id, course_name FROM courses ORDER BY course_name");

// Modify subjects query to include course info and search
$query = "SELECT s.*, c.course_name 
          FROM subject s 
          LEFT JOIN courses c ON s.course_id = c.course_id
          WHERE 1=1";

if (isset($_GET['course_filter']) && !empty($_GET['course_filter'])) {
    $query .= " AND s.course_id = " . intval($_GET['course_filter']);
}

if (isset($_GET['year_filter']) && !empty($_GET['year_filter'])) {
    $query .= " AND s.year_level = " . intval($_GET['year_filter']);
}

if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search = '%' . $conn->real_escape_string($_GET['search']) . '%';
    $query .= " AND (s.Subject_Name LIKE '$search' OR s.Subject_code LIKE '$search')";
}

$query .= " ORDER BY c.course_name, s.Subject_Name";
$subjects = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Subjects</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <?php include '../../app/includes/admin_navbar.php'; ?>

    <div class="container mt-4">
        <h2>Manage Subjects</h2>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show">
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Add course filter -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Search</label>
                        <input type="text" class="form-control" name="search" 
                               value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>" 
                               placeholder="Search by name or code">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Filter by Course</label>
                        <select class="form-select" name="course_filter">
                            <option value="">All Courses</option>
                            <?php 
                            $courses->data_seek(0);
                            while ($course = $courses->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $course['course_id']; ?>"
                                    <?php echo (isset($_GET['course_filter']) && $_GET['course_filter'] == $course['course_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($course['course_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Filter by Year</label>
                        <select class="form-select" name="year_filter">
                            <option value="">All Years</option>
                            <?php for($i = 1; $i <= 4; $i++): ?>
                                <option value="<?php echo $i; ?>"
                                    <?php echo (isset($_GET['year_filter']) && $_GET['year_filter'] == $i) ? 'selected' : ''; ?>>
                                    <?php echo $i; ?>st Year
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">Filter</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Add Subject Button -->
        <button class="btn btn-primary mb-4" data-bs-toggle="modal" data-bs-target="#addSubjectModal">
            Add New Subject
        </button>

        <!-- Subjects List -->
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Course</th>
                                <th>Subject Code</th>
                                <th>Subject Name</th>
                                <th>Year Level</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($subject = $subjects->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($subject['course_name'] ?? 'No Course'); ?></td>
                                    <td><?php echo htmlspecialchars($subject['Subject_code']); ?></td>
                                    <td><?php echo htmlspecialchars($subject['Subject_Name']); ?></td>
                                    <td><?php echo htmlspecialchars($subject['year_level'] ? $subject['year_level'] . ' Year' : 'N/A'); ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-primary" onclick="editSubject(<?php echo $subject['Subject_id']; ?>)">
                                            Edit
                                        </button>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="subject_id" value="<?php echo $subject['Subject_id']; ?>">
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

    <!-- Add Subject Modal -->
    <div class="modal fade" id="addSubjectModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Subject</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add">
                        <div class="mb-3">
                            <label class="form-label">Course</label>
                            <select class="form-select" name="course_id" required>
                                <?php 
                                $courses->data_seek(0);
                                while ($course = $courses->fetch_assoc()): 
                                ?>
                                    <option value="<?php echo $course['course_id']; ?>">
                                        <?php echo htmlspecialchars($course['course_name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Subject Code</label>
                            <input type="text" class="form-control" name="subject_code" required 
                                   pattern="[A-Za-z0-9-]+" title="Alphanumeric characters only">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Subject Name</label>
                            <input type="text" class="form-control" name="subject_name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Year Level</label>
                            <select class="form-select" name="year_level" required>
                                <option value="1">1st Year</option>
                                <option value="2">2nd Year</option>
                                <option value="3">3rd Year</option>
                                <option value="4">4th Year</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">Add Subject</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Subject Modal -->
    <div class="modal fade" id="editSubjectModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Subject</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="subject_id" id="edit_subject_id">
                        <div class="mb-3">
                            <label class="form-label">Course</label>
                            <select class="form-select" name="course_id" id="edit_course_id" required>
                                <?php 
                                $courses->data_seek(0);
                                while ($course = $courses->fetch_assoc()): 
                                ?>
                                    <option value="<?php echo $course['course_id']; ?>">
                                        <?php echo htmlspecialchars($course['course_name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Subject Code</label>
                            <input type="text" class="form-control" name="subject_code" id="edit_subject_code" required 
                                   pattern="[A-Za-z0-9-]+" title="Alphanumeric characters only">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Subject Name</label>
                            <input type="text" class="form-control" name="subject_name" id="edit_subject_name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Year Level</label>
                            <select class="form-select" name="year_level" id="edit_year_level" required>
                                <option value="1">1st Year</option>
                                <option value="2">2nd Year</option>
                                <option value="3">3rd Year</option>
                                <option value="4">4th Year</option>
                            </select>
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
        function editSubject(subjectId) {
            const row = event.target.closest('tr');
            const course = row.cells[0].textContent;
            const code = row.cells[1].textContent;
            const name = row.cells[2].textContent;
            const yearLevel = row.cells[3].textContent.split(' ')[0];
            
            document.getElementById('edit_subject_id').value = subjectId;
            document.getElementById('edit_subject_code').value = code;
            document.getElementById('edit_subject_name').value = name;
            document.getElementById('edit_year_level').value = yearLevel;
            
            const courseSelect = document.getElementById('edit_course_id');
            Array.from(courseSelect.options).forEach(option => {
                if (option.text === course) {
                    option.selected = true;
                }
            });
            
            new bootstrap.Modal(document.getElementById('editSubjectModal')).show();
        }
    </script>
</body>
</html>
