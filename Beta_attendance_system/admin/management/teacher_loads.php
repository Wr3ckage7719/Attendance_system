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
            if ($_POST['action'] === 'assign') {
                $teacher_id = $_POST['teacher_id'];
                $subject_id = $_POST['subject_id'];
                $course_id = $_POST['course_id'];
                $days = $_POST['days']; // Array of selected days
                $start_time = $_POST['start_time'];
                $end_time = $_POST['end_time'];
                $block_id = $_POST['block_id'];
                $year_level = $_POST['year_level']; // Add this line

                // Check for schedule conflicts
                $conflict_check = $conn->prepare(
                    "SELECT COUNT(*) as count FROM classes c
                     INNER JOIN class_days cd ON c.Class_id = cd.class_id
                     WHERE c.Teacher_id = ? 
                     AND cd.day_of_week = ?
                     AND ((TIME(c.start_time) BETWEEN ? AND ?) 
                     OR (TIME(c.end_time) BETWEEN ? AND ?))
                     AND c.Class_id != IFNULL(?, 0)"
                );

                // Check conflicts for each selected day
                foreach ($days as $day) {
                    $conflict_check->bind_param("ssssssi", $teacher_id, $day, $start_time, $end_time, $start_time, $end_time, $class_id);
                    $conflict_check->execute();
                    if ($conflict_check->get_result()->fetch_assoc()['count'] > 0) {
                        throw new Exception("Schedule conflict detected for {$day}");
                    }
                }

                // Begin transaction
                $conn->begin_transaction();

                try {
                    // Insert class first
                    $stmt = $conn->prepare(
                        "INSERT INTO classes (Teacher_id, Subject_id, course_id, start_time, end_time, block_id, year_level) 
                         VALUES (?, ?, ?, ?, ?, ?, ?)"
                    );
                    $stmt->bind_param("iiissii", $teacher_id, $subject_id, $course_id, $start_time, $end_time, $block_id, $year_level);
                    $stmt->execute();
                    $class_id = $conn->insert_id;

                    // Insert days
                    $day_stmt = $conn->prepare("INSERT INTO class_days (class_id, day_of_week) VALUES (?, ?)");
                    foreach ($days as $day) {
                        $day_stmt->bind_param("is", $class_id, $day);
                        $day_stmt->execute();
                    }

                    $conn->commit();
                    $message = "Teaching load assigned successfully";
                    $messageType = "success";
                } catch (Exception $e) {
                    $conn->rollback();
                    throw $e;
                }
            } elseif ($_POST['action'] === 'delete') {
                $class_id = $_POST['class_id'];

                // Check if class has attendance records
                $check = $conn->prepare("SELECT COUNT(*) as count FROM attendance WHERE class_id = ?");
                $check->bind_param("i", $class_id);
                $check->execute();
                if ($check->get_result()->fetch_assoc()['count'] > 0) {
                    throw new Exception("Cannot delete class with existing attendance records");
                }

                // Begin transaction
                $conn->begin_transaction();
                try {
                    // Delete class_days first
                    $stmt = $conn->prepare("DELETE FROM class_days WHERE class_id = ?");
                    $stmt->bind_param("i", $class_id);
                    $stmt->execute();

                    // Then delete the class
                    $stmt = $conn->prepare("DELETE FROM classes WHERE Class_id = ?");
                    $stmt->bind_param("i", $class_id);
                    $stmt->execute();

                    $conn->commit();
                    $message = "Teaching load removed successfully";
                    $messageType = "success";
                } catch (Exception $e) {
                    $conn->rollback();
                    throw $e;
                }
            }
        } catch (Exception $e) {
            $message = "Error: " . $e->getMessage();
            $messageType = "danger";
        }
    }
}

// Fetch all teachers
$teachers = $conn->query("SELECT Teacher_id, CONCAT(first_name, ' ', last_name) as teacher_name FROM teachers ORDER BY first_name");

// Modify query to include year_level
$subjects = $conn->query("SELECT Subject_id, Subject_Name, Subject_code, course_id, year_level FROM subject ORDER BY Subject_Name");

// Fetch all courses
$courses = $conn->query("SELECT course_id, course_name FROM courses ORDER BY course_name");

// Update blocks query to include year level filtering
$blocks = $conn->query("SELECT b.block_id, b.block_name, b.course_id, b.year_level,
                       (SELECT COUNT(*) FROM student 
                        WHERE block_id = b.block_id 
                        AND year_level = b.year_level) as student_count
                       FROM blocks b
                       ORDER BY b.block_name");

// Modify loads query to filter student count by year level
$loads_query = "SELECT c.*, 
                GROUP_CONCAT(cd.day_of_week ORDER BY FIELD(cd.day_of_week, 
                    'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'
                ) SEPARATOR ', ') as days,
                t.first_name, t.last_name,
                s.Subject_Name,
                co.course_name,
                b.block_name,
                c.year_level,
                (SELECT COUNT(*) FROM student 
                 WHERE block_id = b.block_id 
                 AND year_level = c.year_level) as student_count
                FROM classes c
                INNER JOIN teachers t ON c.Teacher_id = t.Teacher_id
                INNER JOIN subject s ON c.Subject_id = s.Subject_id
                INNER JOIN courses co ON c.course_id = co.course_id
                LEFT JOIN blocks b ON c.block_id = b.block_id
                LEFT JOIN class_days cd ON c.Class_id = cd.class_id
                WHERE 1=1";

if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search = $conn->real_escape_string($_GET['search']);
    $loads_query .= " AND (t.first_name LIKE '%$search%' OR t.last_name LIKE '%$search%' 
                     OR s.Subject_Name LIKE '%$search%')";
}

if (isset($_GET['teacher_filter']) && !empty($_GET['teacher_filter'])) {
    $loads_query .= " AND t.Teacher_id = " . intval($_GET['teacher_filter']);
}

if (isset($_GET['course_filter']) && !empty($_GET['course_filter'])) {
    $loads_query .= " AND co.course_id = " . intval($_GET['course_filter']);
}

if (isset($_GET['year_filter']) && !empty($_GET['year_filter'])) {
    $loads_query .= " AND c.year_level = " . intval($_GET['year_filter']);
}

$loads_query .= " GROUP BY c.Class_id ORDER BY t.first_name, c.start_time";
$loads = $conn->query($loads_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Load Assignment</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <?php include '../../app/includes/admin_navbar.php'; ?>

    <div class="container mt-4">
        <h2>Teacher Load Assignment</h2>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show">
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Assign Load Form -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Assign New Teaching Load</h5>
            </div>
            <div class="card-body">
                <form method="POST" class="row g-3">
                    <input type="hidden" name="action" value="assign">
                    
                    <div class="col-md-3">
                        <label class="form-label">Teacher</label>
                        <select class="form-select" name="teacher_id" required>
                            <option value="">Select Teacher</option>
                            <?php while ($teacher = $teachers->fetch_assoc()): ?>
                                <option value="<?php echo $teacher['Teacher_id']; ?>">
                                    <?php echo htmlspecialchars($teacher['teacher_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Course</label>
                        <select class="form-select" name="course_id" id="course_select" required onchange="updateSubjectsAndBlocks()">
                            <option value="">Select Course</option>
                            <?php while ($course = $courses->fetch_assoc()): ?>
                                <option value="<?php echo $course['course_id']; ?>">
                                    <?php echo htmlspecialchars($course['course_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Year Level</label>
                        <select class="form-select" name="year_level" id="year_level" required onchange="updateSubjectsAndBlocks()">
                            <option value="">Select Year</option>
                            <option value="1">1st Year</option>
                            <option value="2">2nd Year</option>
                            <option value="3">3rd Year</option>
                            <option value="4">4th Year</option>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Subject</label>
                        <select class="form-select" name="subject_id" id="subject_select" required>
                            <option value="">Select Subject</option>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Days</label>
                        <div class="dropdown">
                            <button class="btn btn-secondary dropdown-toggle w-100" type="button" id="dayDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                Select Days
                            </button>
                            <div class="dropdown-menu p-2 w-100" aria-labelledby="dayDropdown">
                                <div class="form-check">
                                    <input class="form-check-input day-checkbox" type="checkbox" name="days[]" value="Monday" id="monday">
                                    <label class="form-check-label" for="monday">Monday</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input day-checkbox" type="checkbox" name="days[]" value="Tuesday" id="tuesday">
                                    <label class="form-check-label" for="tuesday">Tuesday</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input day-checkbox" type="checkbox" name="days[]" value="Wednesday" id="wednesday">
                                    <label class="form-check-label" for="wednesday">Wednesday</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input day-checkbox" type="checkbox" name="days[]" value="Thursday" id="thursday">
                                    <label class="form-check-label" for="thursday">Thursday</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input day-checkbox" type="checkbox" name="days[]" value="Friday" id="friday">
                                    <label class="form-check-label" for="friday">Friday</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input day-checkbox" type="checkbox" name="days[]" value="Saturday" id="saturday">
                                    <label class="form-check-label" for="saturday">Saturday</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input day-checkbox" type="checkbox" name="days[]" value="Sunday" id="sunday">
                                    <label class="form-check-label" for="sunday">Sunday</label>
                                </div>
                                <hr class="dropdown-divider">
                                <div class="d-flex justify-content-between">
                                    <button type="button" class="btn btn-sm btn-primary" onclick="selectAllDays(true)">Select All</button>
                                    <button type="button" class="btn btn-sm btn-secondary" onclick="selectAllDays(false)">Clear</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label">Start Time</label>
                        <input type="time" class="form-control" name="start_time" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">End Time</label>
                        <input type="time" class="form-control" name="end_time" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Block</label>
                        <select class="form-select" name="block_id" id="block_select" required>
                            <option value="">Select Block</option>
                        </select>
                    </div>

                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">Assign Load</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Current Loads -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Current Teaching Loads</h5>
            </div>
            <div class="card-body">
                <!-- Add Search and Filter Form -->
                <form method="GET" class="row g-3 mb-4">
                    <div class="col-md-3">
                        <label class="form-label">Search</label>
                        <input type="text" class="form-control" name="search" 
                               value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>" 
                               placeholder="Search teacher or subject">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Teacher</label>
                        <select class="form-select" name="teacher_filter">
                            <option value="">All Teachers</option>
                            <?php 
                            $teachers->data_seek(0);
                            while ($teacher = $teachers->fetch_assoc()): ?>
                                <option value="<?php echo $teacher['Teacher_id']; ?>"
                                    <?php echo (isset($_GET['teacher_filter']) && $_GET['teacher_filter'] == $teacher['Teacher_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($teacher['teacher_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
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
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">Filter</button>
                    </div>
                </form>
                
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Teacher</th>
                                <th>Subject</th>
                                <th>Course</th>
                                <th>Block</th>
                                <th>Year</th>
                                <th>Schedule</th>
                                <th>Students</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($load = $loads->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($load['first_name'] . ' ' . $load['last_name']); ?></td>
                                    <td><?php echo htmlspecialchars($load['Subject_Name']); ?></td>
                                    <td><?php echo htmlspecialchars($load['course_name']); ?></td>
                                    <td><?php echo htmlspecialchars($load['block_name'] ? 'Block ' . $load['block_name'] : 'No Block'); ?></td>
                                    <td><?php echo $load['year_level'] ? htmlspecialchars($load['year_level']) . ' Year' : 'N/A'; ?></td>
                                    <td>
                                        <?php 
                                        echo htmlspecialchars($load['days']) . '<br>' .
                                             date('g:i A', strtotime($load['start_time'])) . ' - ' . 
                                             date('g:i A', strtotime($load['end_time'])); 
                                        ?>
                                    </td>
                                    <td><?php echo $load['student_count']; ?></td>
                                    <td>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="class_id" value="<?php echo $load['Class_id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger" 
                                                    onclick="return confirm('Are you sure? This will remove all students from this class.')">
                                                Remove
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Add this before other scripts
        const allSubjects = <?php echo json_encode($subjects->fetch_all(MYSQLI_ASSOC)); ?>;
        const allBlocks = <?php echo json_encode($blocks->fetch_all(MYSQLI_ASSOC)); ?>;
        
        function updateSubjectsAndBlocks() {
            updateSubjects();
            loadBlocks();
        }

        function updateSubjects() {
            const courseId = document.getElementById('course_select').value;
            const yearLevel = document.getElementById('year_level').value;
            const subjectSelect = document.getElementById('subject_select');
            
            subjectSelect.innerHTML = '<option value="">Select Subject</option>';
            
            if (!courseId || !yearLevel) return;
            
            const filteredSubjects = allSubjects.filter(subject => 
                subject.course_id == courseId && 
                subject.year_level == yearLevel
            );
            
            filteredSubjects.forEach(subject => {
                subjectSelect.innerHTML += `
                    <option value="${subject.Subject_id}">
                        ${subject.Subject_Name} (${subject.Subject_code})
                    </option>`;
            });
        }

        // Update loadBlocks function
        function loadBlocks() {
            const yearLevel = document.getElementById('year_level').value;
            const courseId = document.getElementById('course_select').value;
            const blockSelect = document.getElementById('block_select');
            
            blockSelect.innerHTML = '<option value="">Select Block</option>';
            
            if (!yearLevel || !courseId) return;
            
            const availableBlocks = allBlocks.filter(block => 
                block.course_id == courseId && 
                block.year_level == yearLevel
            );
            
            availableBlocks.forEach(block => {
                blockSelect.innerHTML += `
                    <option value="${block.block_id}">
                        Block ${block.block_name} (${block.student_count} students)
                    </option>`;
            });
        }

        // Add time validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const startTime = this.start_time.value;
            const endTime = this.end_time.value;
            
            if (endTime <= startTime) {
                e.preventDefault();
                alert('End time must be after start time');
            }
        });

        function selectAllDays(checked) {
            document.querySelectorAll('.day-checkbox').forEach(checkbox => {
                checkbox.checked = checked;
            });
            updateDayDropdownText();
        }

        function updateDayDropdownText() {
            const checkedDays = Array.from(document.querySelectorAll('.day-checkbox:checked'))
                .map(cb => cb.value);
            const dropdownButton = document.getElementById('dayDropdown');
            
            if (checkedDays.length === 0) {
                dropdownButton.textContent = 'Select Days';
            } else if (checkedDays.length === 7) {
                dropdownButton.textContent = 'All Days';
            } else {
                dropdownButton.textContent = `${checkedDays.length} day(s) selected`;
            }
        }

        document.querySelectorAll('.day-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', updateDayDropdownText);
        });

        // Add form validation for days
        document.querySelector('form').addEventListener('submit', function(e) {
            const selectedDays = document.querySelectorAll('.day-checkbox:checked').length;
            if (selectedDays === 0) {
                e.preventDefault();
                alert('Please select at least one day');
            }
        });
    </script>
</body>
</html>
