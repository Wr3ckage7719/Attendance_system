<?php
session_start();
require_once '../../app/config/auth_check.php';
require_once '../../app/config/database.php';
checkAuth('admin');

$conn = connectDB();

// Function to get block student count
function getBlockStudentCount($conn, $block_id) {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM student WHERE block_id = ?");
    $stmt->bind_param("i", $block_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc()['count'];
}

// Handle student addition, editing, and deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        try {
            if ($_POST['action'] === 'add') {
                // Check if student ID already exists
                $check = $conn->prepare("SELECT COUNT(*) as count FROM student WHERE Student_Id = ?");
                $check->bind_param("s", $_POST['student_id']);
                $check->execute();
                if ($check->get_result()->fetch_assoc()['count'] > 0) {
                    throw new Exception("Student ID already exists");
                }

                // Verify block capacity before adding student
                $block_id = $_POST['block_id'];
                $check = $conn->prepare("SELECT COUNT(*) as count FROM student WHERE block_id = ?");
                $check->bind_param("i", $block_id);
                $check->execute();
                if ($check->get_result()->fetch_assoc()['count'] >= 40) {
                    throw new Exception("Selected block is already full (maximum 40 students)");
                }

                $student_id = $_POST['student_id'];
                $first_name = $_POST['first_name'];
                $last_name = $_POST['last_name'];
                $email = $_POST['email'];
                $phone = $_POST['phone'];
                $year_level = $_POST['year_level'];

                $stmt = $conn->prepare("INSERT INTO student (Student_Id, first_name, Last_name, Email, phone_number, block_id, year_level) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("sssssis", $student_id, $first_name, $last_name, $email, $phone, $block_id, $year_level);
                
                if (!$stmt->execute()) {
                    throw new Exception("Error adding student: " . $conn->error);
                }
                $message = "Student added successfully";
                $messageType = "success";
            } elseif ($_POST['action'] === 'edit') {
                $student_id = $_POST['student_id'];
                $first_name = $_POST['first_name'];
                $last_name = $_POST['last_name'];
                $email = $_POST['email'];
                $phone = $_POST['phone'];
                $block_id = $_POST['block_id'];
                $year_level = $_POST['year_level'];

                // If block is changing, verify capacity of new block
                $check = $conn->prepare("SELECT block_id FROM student WHERE Student_Id = ?");
                $check->bind_param("s", $student_id);
                $check->execute();
                $current_block = $check->get_result()->fetch_assoc()['block_id'];

                if ($current_block != $block_id) {
                    $check = $conn->prepare("SELECT COUNT(*) as count FROM student WHERE block_id = ?");
                    $check->bind_param("i", $block_id);
                    $check->execute();
                    if ($check->get_result()->fetch_assoc()['count'] >= 40) {
                        throw new Exception("Selected block is already full (maximum 40 students)");
                    }
                }

                $stmt = $conn->prepare("UPDATE student SET first_name=?, Last_name=?, Email=?, phone_number=?, block_id=?, year_level=? WHERE Student_Id=?");
                $stmt->bind_param("ssssiss", $first_name, $last_name, $email, $phone, $block_id, $year_level, $student_id);
                if (!$stmt->execute()) {
                    throw new Exception("Error updating student: " . $conn->error);
                }
                $message = "Student updated successfully";
                $messageType = "success";
            } elseif ($_POST['action'] === 'delete') {
                $student_id = $_POST['student_id'];

                // Check if student has attendance records
                $check = $conn->prepare("SELECT COUNT(*) as count FROM attendance WHERE student_id = ?");
                $check->bind_param("s", $student_id);
                $check->execute();
                if ($check->get_result()->fetch_assoc()['count'] > 0) {
                    throw new Exception("Cannot delete student with attendance records");
                }

                $stmt = $conn->prepare("DELETE FROM student WHERE Student_Id = ?");
                $stmt->bind_param("s", $student_id);
                if (!$stmt->execute()) {
                    throw new Exception("Error deleting student: " . $conn->error);
                }
                $message = "Student deleted successfully";
                $messageType = "success";
            }
        } catch (Exception $e) {
            $message = "Error: " . $e->getMessage();
            $messageType = "danger";
        }
    }
}

// Updated student query with search and filters
$query = "SELECT s.*, 
          b.block_id,
          b.block_name,
          b.year_level as block_year,
          CONCAT(c.course_code, ' - ', s.year_level, ' Year - Block ', b.block_name) as block_section,
          c.course_name,
          c.course_id,
          (SELECT COUNT(*) FROM student WHERE block_id = b.block_id AND year_level = s.year_level) as block_count
          FROM student s 
          LEFT JOIN blocks b ON s.block_id = b.block_id
          LEFT JOIN courses c ON b.course_id = c.course_id
          WHERE 1=1";

if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search = $conn->real_escape_string($_GET['search']);
    $query .= " AND (s.Student_Id LIKE '%$search%' OR s.first_name LIKE '%$search%' 
                OR s.Last_name LIKE '%$search%' OR s.Email LIKE '%$search%')";
}

if (isset($_GET['course_filter']) && !empty($_GET['course_filter'])) {
    $query .= " AND c.course_id = " . intval($_GET['course_filter']);
}

if (isset($_GET['year_filter']) && !empty($_GET['year_filter'])) {
    $query .= " AND s.year_level = " . intval($_GET['year_filter']);
}

if (isset($_GET['block_filter']) && !empty($_GET['block_filter'])) {
    $query .= " AND b.block_name = '" . $conn->real_escape_string($_GET['block_filter']) . "'";
}

$query .= " ORDER BY c.course_name, s.year_level, b.block_name, s.first_name";
$students = $conn->query($query);

// Updated block query for AJAX
$blockQuery = "SELECT b.*, c.course_name, c.course_code,
               (SELECT COUNT(*) FROM student WHERE block_id = b.block_id AND year_level = b.year_level) as student_count,
               CONCAT(c.course_code, ' - ', b.year_level, 'st Year - Block ', b.block_name) as block_section
               FROM blocks b 
               INNER JOIN courses c ON b.course_id = c.course_id";

if (isset($_GET['course_id']) && !empty($_GET['course_id'])) {
    $blockQuery .= " WHERE b.course_id = " . intval($_GET['course_id']);
    if (isset($_GET['year_level']) && !empty($_GET['year_level'])) {
        $blockQuery .= " AND b.year_level = " . intval($_GET['year_level']);
    }
}
$blockQuery .= " ORDER BY b.year_level, student_count ASC, b.block_name";
$blocks = $conn->query($blockQuery);

// Fetch all courses for filter
$courseQuery = "SELECT course_id, course_name FROM courses";
$courses = $conn->query($courseQuery);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Students</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <?php include '../../app/includes/admin_navbar.php'; ?>
    <div class="container mt-4">
        <h2>Manage Students</h2>
        
        <?php if (isset($message)): ?>
        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Add Student Form -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Add New Student</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <input type="hidden" name="action" value="add">
                    <div class="row">
                        <div class="col-md-2">
                            <div class="mb-3">
                                <label for="student_id" class="form-label">Student ID</label>
                                <input type="text" class="form-control" id="student_id" name="student_id" required>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label for="first_name" class="form-label">First Name</label>
                                <input type="text" class="form-control" id="first_name" name="first_name" required>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label for="last_name" class="form-label">Last Name</label>
                                <input type="text" class="form-control" id="last_name" name="last_name" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="course_select" class="form-label">Course</label>
                                <select class="form-select" id="course_select" required onchange="loadBlocks(this.value)">
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
                                <label for="block_id" class="form-label">Block Section</label>
                                <select class="form-select" id="block_id" name="block_id" required>
                                    <option value="">Select Course First</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="mb-3">
                                <label for="year_level" class="form-label">Year Level</label>
                                <select class="form-select" id="year_level" name="year_level" required>
                                    <option value="">Select Year</option>
                                    <option value="1">1st Year</option>
                                    <option value="2">2nd Year</option>
                                    <option value="3">3rd Year</option>
                                    <option value="4">4th Year</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="phone" class="form-label">Phone</label>
                                <input type="text" class="form-control" id="phone" name="phone">
                            </div>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">Add Student</button>
                </form>
            </div>
        </div>

        <!-- Search and Filter -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Search and Filter Students</h5>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Search</label>
                        <input type="text" class="form-control" name="search" 
                               value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>" 
                               placeholder="Search ID, name or email">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Course</label>
                        <select class="form-select" name="course_filter">
                            <option value="">All Courses</option>
                            <?php 
                            $courses->data_seek(0);
                            while ($course = $courses->fetch_assoc()): ?>
                                <option value="<?php echo $course['course_id']; ?>"
                                    <?php echo (isset($_GET['course_filter']) && $_GET['course_filter'] == $course['course_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($course['course_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Year Level</label>
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
                    <div class="col-md-2">
                        <label class="form-label">Block</label>
                        <select class="form-select" name="block_filter">
                            <option value="">All Blocks</option>
                            <?php foreach(['A','B','C','D','E'] as $block): ?>
                                <option value="<?php echo $block; ?>"
                                    <?php echo (isset($_GET['block_filter']) && $_GET['block_filter'] == $block) ? 'selected' : ''; ?>>
                                    Block <?php echo $block; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">Filter</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Students List -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Students List</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Block</th>
                                <th>Year Level</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($student = $students->fetch_assoc()): ?>
                                <tr data-block-id="<?php echo htmlspecialchars($student['block_id']); ?>">
                                    <td><?php echo htmlspecialchars($student['Student_Id']); ?></td>
                                    <td><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['Last_name']); ?></td>
                                    <td><?php echo htmlspecialchars($student['Email']); ?></td>
                                    <td><?php echo htmlspecialchars($student['phone_number']); ?></td>
                                    <td><?php echo htmlspecialchars($student['block_section']); ?></td>
                                    <td><?php echo $student['year_level'] ? htmlspecialchars($student['year_level']) . ' Year' : 'N/A'; ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-primary" 
                                                onclick="editStudent('<?php echo $student['Student_Id']; ?>')">Edit</button>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="student_id" value="<?php echo $student['Student_Id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger" 
                                                    onclick="return confirm('Are you sure you want to delete this student?')">
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

    <!-- Edit Student Modal -->
    <div class="modal fade" id="editStudentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Student</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="student_id" id="edit_student_id">
                        <input type="hidden" name="block_id" id="current_block_id">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">First Name</label>
                                    <input type="text" class="form-control" name="first_name" id="edit_first_name" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Last Name</label>
                                    <input type="text" class="form-control" name="last_name" id="edit_last_name" required>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Current Block Section</label>
                                    <input type="text" class="form-control" id="edit_block_display" readonly>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Course</label>
                                    <select class="form-select" id="edit_course_select" required onchange="loadBlocks(this.value, true)">
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
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Block Section</label>
                                    <select class="form-select" name="block_id" id="edit_block_id" required>
                                        <option value="">Select Course First</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" class="form-control" name="email" id="edit_email" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Phone</label>
                                    <input type="text" class="form-control" name="phone" id="edit_phone">
                                </div>
                            </div>
                            <div class="col-md-6">
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
        function editStudent(studentId) {
            const row = event.target.closest('tr');
            const blockId = row.getAttribute('data-block-id');
            const name = row.cells[1].textContent.split(' ');
            const email = row.cells[2].textContent;
            const phone = row.cells[3].textContent;
            const blockInfo = row.cells[4].textContent;
            const yearLevel = parseInt(row.cells[5].textContent);
            const courseId = blockInfo.split(' - ')[0]; // Get course code

            document.getElementById('edit_student_id').value = studentId;
            document.getElementById('edit_first_name').value = name[0];
            document.getElementById('edit_last_name').value = name[1] || '';
            document.getElementById('edit_email').value = email;
            document.getElementById('edit_phone').value = phone;
            document.getElementById('current_block_id').value = blockId;
            document.getElementById('edit_block_display').value = blockInfo;
            document.getElementById('edit_year_level').value = yearLevel;
            
            // Set course and trigger block load
            const courseSelect = document.getElementById('edit_course_select');
            Array.from(courseSelect.options).forEach(option => {
                if (option.text.startsWith(courseId)) {
                    option.selected = true;
                }
            });
            
            if (courseSelect.value) {
                loadBlocks(courseSelect.value, true);
            }

            new bootstrap.Modal(document.getElementById('editStudentModal')).show();
        }

        function loadBlocks(courseId, isEdit = false) {
            const blockSelect = document.getElementById(isEdit ? 'edit_block_id' : 'block_id');
            const yearLevel = document.getElementById(isEdit ? 'edit_year_level' : 'year_level').value;
            
            blockSelect.innerHTML = '<option value="">Loading blocks...</option>';
            
            if (!courseId || !yearLevel) {
                blockSelect.innerHTML = '<option value="">Select Course and Year Level First</option>';
                return;
            }

            // Update the fetch URL to include year_level
            fetch(`../../app/includes/get_blocks.php?course_id=${courseId}&year_level=${yearLevel}`)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        blockSelect.innerHTML = '<option value="">Error: ' + data.error + '</option>';
                        return;
                    }

                    blockSelect.innerHTML = '<option value="">Select Block</option>';
                    if (data.length === 0) {
                        blockSelect.innerHTML = '<option value="">No blocks available for this course/year</option>';
                        return;
                    }

                    let autoSelectedBlock = null;
                    data.forEach(block => {
                        const count = block.student_count || 0;
                        const option = document.createElement('option');
                        option.value = block.block_id;
                        option.text = `${block.course_code} - Year ${block.year_level} - Block ${block.block_name} (${count}/40)`;

                        if (!isEdit) {
                            // For new students, track the first block that's not full
                            if (count < 40 && !autoSelectedBlock) {
                                autoSelectedBlock = block.block_id;
                            }
                            // Disable full blocks
                            option.disabled = count >= 40;
                        }

                        blockSelect.add(option);
                    });

                    // For edit mode, try to select the current block
                    if (isEdit) {
                        const currentBlockId = document.getElementById('current_block_id').value;
                        if (currentBlockId) {
                            blockSelect.value = currentBlockId;
                        }
                    } else if (autoSelectedBlock) {
                        // For new students, auto-select the first available block
                        blockSelect.value = autoSelectedBlock;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    blockSelect.innerHTML = '<option value="">Error loading blocks</option>';
                });
        }

        document.addEventListener('DOMContentLoaded', function() {
            const courseSelect = document.getElementById('course_select');
            const yearSelect = document.getElementById('year_level');
            
            // Listen for changes on both course and year level
            courseSelect.addEventListener('change', function() {
                if (yearSelect.value) {
                    loadBlocks(this.value);
                }
            });
            
            yearSelect.addEventListener('change', function() {
                if (courseSelect.value) {
                    loadBlocks(courseSelect.value);
                }
            });

            // Add listeners for edit form
            const editYearSelect = document.getElementById('edit_year_level');
            editYearSelect.addEventListener('change', function() {
                const courseId = document.getElementById('edit_course_select').value;
                if (courseId) {
                    loadBlocks(courseId, true);
                }
            });
        });
    </script>
</body>
</html>
