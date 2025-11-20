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
                $block_name = $_POST['block_name'];
                $course_id = $_POST['course_id'];
                $year_level = $_POST['year_level'];
                $classes = $_POST['classes'] ?? [];

                // Check if block already exists for this course and year
                $check = $conn->prepare("SELECT COUNT(*) as count FROM blocks WHERE block_name = ? AND course_id = ? AND year_level = ?");
                $check->bind_param("sii", $block_name, $course_id, $year_level);
                $check->execute();
                if ($check->get_result()->fetch_assoc()['count'] > 0) {
                    throw new Exception("Block already exists for this course and year level");
                }

                $conn->begin_transaction();

                // Insert block with year level
                $stmt = $conn->prepare("INSERT INTO blocks (block_name, course_id, year_level) VALUES (?, ?, ?)");
                $stmt->bind_param("sii", $block_name, $course_id, $year_level);
                $stmt->execute();
                $block_id = $conn->insert_id;

                // Assign classes to block
                if (!empty($classes)) {
                    $stmt = $conn->prepare("INSERT INTO block_classes (block_id, class_id) VALUES (?, ?)");
                    foreach ($classes as $class_id) {
                        $stmt->bind_param("ii", $block_id, $class_id);
                        $stmt->execute();
                    }
                }

                $conn->commit();
                $message = "Block created successfully";
                $messageType = "success";
            } elseif ($_POST['action'] === 'edit') {
                $block_id = $_POST['block_id'];
                $course_id = $_POST['edit_course_id'];
                $year_level = $_POST['edit_year_level'];
                $block_name = $_POST['edit_block_name'];
                $classes = $_POST['edit_classes'] ?? [];

                // Check if the block update would create a duplicate
                $check = $conn->prepare("SELECT COUNT(*) as count FROM blocks 
                                       WHERE block_name = ? AND course_id = ? AND year_level = ? 
                                       AND block_id != ?");
                $check->bind_param("siii", $block_name, $course_id, $year_level, $block_id);
                $check->execute();
                if ($check->get_result()->fetch_assoc()['count'] > 0) {
                    throw new Exception("Block already exists for this course and year level");
                }

                $conn->begin_transaction();

                // Update block
                $stmt = $conn->prepare("UPDATE blocks SET block_name = ?, course_id = ?, year_level = ? WHERE block_id = ?");
                $stmt->bind_param("siii", $block_name, $course_id, $year_level, $block_id);
                $stmt->execute();

                // Update class assignments
                $stmt = $conn->prepare("DELETE FROM block_classes WHERE block_id = ?");
                $stmt->bind_param("i", $block_id);
                $stmt->execute();

                if (!empty($classes)) {
                    $stmt = $conn->prepare("INSERT INTO block_classes (block_id, class_id) VALUES (?, ?)");
                    foreach ($classes as $class_id) {
                        $stmt->bind_param("ii", $block_id, $class_id);
                        $stmt->execute();
                    }
                }

                $conn->commit();
                $message = "Block updated successfully";
                $messageType = "success";
            } elseif ($_POST['action'] === 'delete') {
                $block_id = $_POST['block_id'];

                // Check if block has students
                $check = $conn->prepare("SELECT COUNT(*) as count FROM student WHERE block_id = ?");
                $check->bind_param("i", $block_id);
                $check->execute();
                if ($check->get_result()->fetch_assoc()['count'] > 0) {
                    throw new Exception("Cannot delete block that has students assigned to it");
                }

                $conn->begin_transaction();

                // Delete class assignments first
                $stmt = $conn->prepare("DELETE FROM block_classes WHERE block_id = ?");
                $stmt->bind_param("i", $block_id);
                $stmt->execute();

                // Delete block
                $stmt = $conn->prepare("DELETE FROM blocks WHERE block_id = ?");
                $stmt->bind_param("i", $block_id);
                $stmt->execute();

                $conn->commit();
                $message = "Block deleted successfully";
                $messageType = "success";
            }
        } catch (Exception $e) {
            if (isset($conn) && $conn->connect_errno === 0) {
                $conn->rollback();
            }
            $message = "Error: " . $e->getMessage();
            $messageType = "danger";
        }
    }
}

// Update blocks query to include year level and proper student count
$blocks = $conn->query("
    SELECT b.block_id, b.block_name, c.course_name, c.course_code, b.year_level,
    CONCAT(c.course_code, ' - Year ', b.year_level, ' - Block ', b.block_name) as block_section,
    (SELECT COUNT(*) FROM student WHERE block_id = b.block_id AND year_level = b.year_level) as student_count
    FROM blocks b
    INNER JOIN courses c ON b.course_id = c.course_id
    ORDER BY c.course_name, b.year_level, b.block_name
");

// Fetch courses for the form
$courses = $conn->query("SELECT course_id, course_name FROM courses ORDER BY course_name");

// Fetch classes for the form
$classes = $conn->query("
    SELECT c.Class_id, CONCAT(s.Subject_Name, ' - ', 
           TIME_FORMAT(c.start_time, '%h:%i %p'), ' - ', 
           TIME_FORMAT(c.end_time, '%h:%i %p')) as class_name
    FROM classes c
    INNER JOIN subject s ON c.Subject_id = s.Subject_id
    ORDER BY s.Subject_Name
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Blocks</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <?php include '../../app/includes/admin_navbar.php'; ?>

    <div class="container mt-4">
        <h2>Manage Blocks</h2>
        
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?>"><?php echo $message; ?></div>
        <?php endif; ?>

        <!-- Add Block Form -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Add New Block</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="add">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Block Name</label>
                                <select class="form-select" name="block_name" required>
                                    <option value="">Select Block</option>
                                    <option value="A">Block A</option>
                                    <option value="B">Block B</option>
                                    <option value="C">Block C</option>
                                    <option value="D">Block D</option>
                                    <option value="E">Block E</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Course</label>
                                <select class="form-select" name="course_id" required>
                                    <option value="">Select Course</option>
                                    <?php 
                                    $courses->data_seek(0);
                                    while ($course = $courses->fetch_assoc()): ?>
                                        <option value="<?php echo $course['course_id']; ?>">
                                            <?php echo htmlspecialchars($course['course_name']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Year Level</label>
                                <select class="form-select" name="year_level" required>
                                    <option value="">Select Year Level</option>
                                    <option value="1">Year 1</option>
                                    <option value="2">Year 2</option>
                                    <option value="3">Year 3</option>
                                    <option value="4">Year 4</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Assign Classes</label>
                        <div class="row">
                            <?php while ($class = $classes->fetch_assoc()): ?>
                                <div class="col-md-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="classes[]" 
                                               value="<?php echo $class['Class_id']; ?>">
                                        <label class="form-check-label">
                                            <?php echo htmlspecialchars($class['class_name']); ?>
                                        </label>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">Create Block</button>
                </form>
            </div>
        </div>

        <!-- Blocks List -->
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Block Name</th>
                                <th>Course</th>
                                <th>Year Level</th>
                                <th>Students</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($block = $blocks->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($block['block_name']); ?></td>
                                    <td><?php echo htmlspecialchars($block['course_name']); ?></td>
                                    <td>Year <?php echo htmlspecialchars($block['year_level']); ?></td>
                                    <td><?php echo $block['student_count']; ?>/40</td>
                                    <td>
                                        <button class="btn btn-sm btn-primary" onclick="editBlock(<?php echo $block['block_id']; ?>)">
                                            Edit
                                        </button>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="block_id" value="<?php echo $block['block_id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger" 
                                                    onclick="return confirm('Are you sure? This will remove all class assignments from this block.')">
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

    <!-- Edit Block Modal -->
    <div class="modal fade" id="editBlockModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Block</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="block_id" id="edit_block_id">
                        
                        <div class="mb-3">
                            <label class="form-label">Block Name</label>
                            <select class="form-select" name="edit_block_name" required>
                                <option value="A">Block A</option>
                                <option value="B">Block B</option>
                                <option value="C">Block C</option>
                                <option value="D">Block D</option>
                                <option value="E">Block E</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Course</label>
                            <select class="form-select" name="edit_course_id" required>
                                <?php 
                                $courses->data_seek(0);
                                while ($course = $courses->fetch_assoc()): ?>
                                    <option value="<?php echo $course['course_id']; ?>">
                                        <?php echo htmlspecialchars($course['course_name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Year Level</label>
                            <select class="form-select" name="edit_year_level" required>
                                <option value="1">Year 1</option>
                                <option value="2">Year 2</option>
                                <option value="3">Year 3</option>
                                <option value="4">Year 4</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Assign Classes</label>
                            <div class="row">
                                <?php 
                                $classes->data_seek(0);
                                while ($class = $classes->fetch_assoc()): ?>
                                    <div class="col-md-12">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="edit_classes[]" 
                                                   value="<?php echo $class['Class_id']; ?>">
                                            <label class="form-check-label">
                                                <?php echo htmlspecialchars($class['class_name']); ?>
                                            </label>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            </div>
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
        function editBlock(blockId) {
            // Get block data from the table row
            const row = event.target.closest('tr');
            const blockName = row.cells[0].textContent.replace('Block ', '');
            const courseName = row.cells[1].textContent;
            const yearLevel = row.cells[2].textContent.replace('Year ', '');
            
            // Set values in the edit form
            document.getElementById('edit_block_id').value = blockId;
            document.querySelector('select[name="edit_block_name"]').value = blockName;
            document.querySelector('select[name="edit_year_level"]').value = yearLevel;
            
            // Find and select the correct course
            const courseSelect = document.querySelector('select[name="edit_course_id"]');
            Array.from(courseSelect.options).forEach(option => {
                if (option.text === courseName) {
                    option.selected = true;
                }
            });
            
            // Show the modal
            new bootstrap.Modal(document.getElementById('editBlockModal')).show();
        }
    </script>
</body>
</html>
