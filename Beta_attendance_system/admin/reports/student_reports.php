<?php
session_start();
require_once '../../app/config/auth_check.php';
require_once '../../app/config/database.php';
checkAuth('admin');

$conn = connectDB();

// Get filter values
$course_id = isset($_GET['course_id']) ? $_GET['course_id'] : null;
$year_level = isset($_GET['year_level']) ? $_GET['year_level'] : null;
$block_id = isset($_GET['block_id']) ? $_GET['block_id'] : null;
$month = isset($_GET['month']) ? $_GET['month'] : date('m');
$year = isset($_GET['year']) ? $_GET['year'] : date('Y');

// Fetch all courses for filter
$courses = $conn->query("SELECT course_id, course_name FROM courses ORDER BY course_name");

// Fetch blocks based on filters
$blockQuery = "SELECT b.*, c.course_name 
              FROM blocks b 
              INNER JOIN courses c ON b.course_id = c.course_id 
              WHERE 1=1";
if ($course_id) {
    $blockQuery .= " AND b.course_id = " . intval($course_id);
}
if ($year_level) {
    $blockQuery .= " AND b.year_level = " . intval($year_level);
}
$blocks = $conn->query($blockQuery);

// Build student performance query
$query = "SELECT 
            s.Student_Id,
            s.first_name,
            s.Last_name,
            s.year_level,
            s.Email,
            b.block_name,
            c.course_name,
            COUNT(DISTINCT cl.Class_id) as total_subjects,
            COUNT(DISTINCT att.date) as total_attendance_days,
            SUM(CASE WHEN att.status = 'present' THEN 1 ELSE 0 END) as present_days,
            SUM(CASE WHEN att.status = 'late' THEN 1 ELSE 0 END) as late_days,
            SUM(CASE WHEN att.status = 'absent' THEN 1 ELSE 0 END) as absent_days,
            ROUND(
                (SUM(CASE 
                    WHEN att.status = 'present' THEN 1 
                    WHEN att.status = 'late' THEN 0.5 
                    ELSE 0 
                END) / NULLIF(COUNT(DISTINCT att.date), 0)) * 100, 
                1
            ) as attendance_rate
          FROM student s
          INNER JOIN blocks b ON s.block_id = b.block_id
          INNER JOIN courses c ON b.course_id = c.course_id
          LEFT JOIN classes cl ON b.block_id = cl.block_id
          LEFT JOIN attendance att ON (
              att.student_id = s.Student_Id
              AND att.class_id = cl.Class_id
              AND MONTH(att.date) = ?
              AND YEAR(att.date) = ?
          )
          WHERE 1=1";

$params = ["ii", intval($month), intval($year)];

// Add search condition
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search = '%' . $conn->real_escape_string($_GET['search']) . '%';
    $query .= " AND (s.Student_Id LIKE ? OR s.first_name LIKE ? OR s.Last_name LIKE ? OR s.Email LIKE ?)";
    $params[0] .= "ssss";
    $params[] = $search;
    $params[] = $search;
    $params[] = $search;
    $params[] = $search;
}

if ($course_id) {
    $query .= " AND c.course_id = ?";
    $params[0] .= "i";
    $params[] = $course_id;
}
if ($year_level) {
    $query .= " AND s.year_level = ?";
    $params[0] .= "i";
    $params[] = $year_level;
}
if ($block_id) {
    $query .= " AND b.block_id = ?";
    $params[0] .= "i";
    $params[] = $block_id;
}

$query .= " GROUP BY s.Student_Id, s.first_name, s.Last_name, s.year_level, s.Email, b.block_name, c.course_name
            ORDER BY 
                CASE WHEN (
                    SUM(CASE 
                        WHEN att.status = 'present' THEN 1 
                        WHEN att.status = 'late' THEN 0.5 
                        ELSE 0 
                    END) / NULLIF(COUNT(DISTINCT att.date), 0)
                ) IS NULL THEN 0 ELSE 1 END DESC,
                (SUM(CASE 
                    WHEN att.status = 'present' THEN 1 
                    WHEN att.status = 'late' THEN 0.5 
                    ELSE 0 
                END) / NULLIF(COUNT(DISTINCT att.date), 0)) * 100 DESC,
                c.course_name,
                s.year_level,
                b.block_name,
                s.Last_name,
                s.first_name";

$stmt = $conn->prepare($query);
if ($stmt === false) {
    die('Error preparing query: ' . $conn->error);
}

$types = $params[0];
$bindParams = array($types);
for ($i = 1; $i < count($params); $i++) {
    $bindParams[] = &$params[$i];
}
call_user_func_array([$stmt, 'bind_param'], $bindParams);

$stmt->execute();
$student_records = $stmt->get_result();

// Calculate overall statistics
$total_students = 0;
$high_attendance = 0; // >= 90%
$medium_attendance = 0; // >= 75% and < 90%
$low_attendance = 0; // < 75%
$total_attendance_rate = 0;

if ($temp_records = $student_records->fetch_all(MYSQLI_ASSOC)) {
    $total_students = count($temp_records);
    foreach ($temp_records as $record) {
        if ($record['attendance_rate'] !== null) {
            if ($record['attendance_rate'] >= 90) $high_attendance++;
            elseif ($record['attendance_rate'] >= 75) $medium_attendance++;
            else $low_attendance++;
            $total_attendance_rate += $record['attendance_rate'];
        }
    }
}
$average_attendance = $total_students > 0 ? round($total_attendance_rate / $total_students, 1) : 0;

// Reset result set pointer
$student_records->data_seek(0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Reports</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <?php include '../../app/includes/admin_navbar.php'; ?>

    <div class="container mt-4">
        <h2>Student Reports</h2>

        <!-- Statistics Overview -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <h5 class="card-title">Total Students</h5>
                        <p class="card-text display-6"><?php echo $total_students; ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <h5 class="card-title">High Attendance</h5>
                        <p class="card-text">
                            <?php echo $high_attendance; ?> students
                            <small>(≥90%)</small>
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-warning text-white">
                    <div class="card-body">
                        <h5 class="card-title">Medium Attendance</h5>
                        <p class="card-text">
                            <?php echo $medium_attendance; ?> students
                            <small>(75-89%)</small>
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-danger text-white">
                    <div class="card-body">
                        <h5 class="card-title">Low Attendance</h5>
                        <p class="card-text">
                            <?php echo $low_attendance; ?> students
                            <small>(<75%)</small>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-2">
                        <label class="form-label">Course</label>
                        <select class="form-select" name="course_id">
                            <option value="">All Courses</option>
                            <?php while ($course = $courses->fetch_assoc()): ?>
                                <option value="<?php echo $course['course_id']; ?>"
                                    <?php echo ($course_id == $course['course_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($course['course_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Year Level</label>
                        <select class="form-select" name="year_level">
                            <option value="">All Years</option>
                            <?php for($i = 1; $i <= 4; $i++): ?>
                                <option value="<?php echo $i; ?>"
                                    <?php echo ($year_level == $i) ? 'selected' : ''; ?>>
                                    <?php echo $i; ?>st Year
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Block</label>
                        <select class="form-select" name="block_id">
                            <option value="">All Blocks</option>
                            <?php foreach(['A','B','C','D','E'] as $block_name): ?>
                                <option value="<?php 
                                    // Find the block_id for this block name if it exists
                                    $blocks->data_seek(0);
                                    $block_id_found = '';
                                    while ($block = $blocks->fetch_assoc()) {
                                        if ($block['block_name'] === $block_name) {
                                            $block_id_found = $block['block_id'];
                                            break;
                                        }
                                    }
                                    echo $block_id_found;
                                ?>"
                                    <?php echo ($block_id == $block_id_found) ? 'selected' : ''; ?>>
                                    Block <?php echo $block_name; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Month</label>
                        <select class="form-select" name="month">
                            <?php for($i = 1; $i <= 12; $i++): ?>
                                <option value="<?php echo $i; ?>"
                                    <?php echo ($month == $i) ? 'selected' : ''; ?>>
                                    <?php echo date('F', mktime(0, 0, 0, $i, 1)); ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Year</label>
                        <select class="form-select" name="year">
                            <?php for($i = date('Y'); $i >= date('Y')-1; $i--): ?>
                                <option value="<?php echo $i; ?>"
                                    <?php echo ($year == $i) ? 'selected' : ''; ?>>
                                    <?php echo $i; ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Search</label>
                        <input type="text" class="form-control" name="search" value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-primary w-100">Filter</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Student Performance Table -->
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Student ID</th>
                                <th>Name</th>
                                <th>Course</th>
                                <th>Year</th>
                                <th>Block</th>
                                <th>Email</th>
                                <th>Subjects</th>
                                <th>Present</th>
                                <th>Late</th>
                                <th>Absent</th>
                                <th>Attendance Rate</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($record = $student_records->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($record['Student_Id']); ?></td>
                                    <td><?php echo htmlspecialchars($record['first_name'] . ' ' . $record['Last_name']); ?></td>
                                    <td><?php echo htmlspecialchars($record['course_name']); ?></td>
                                    <td><?php echo $record['year_level']; ?></td>
                                    <td><?php echo htmlspecialchars($record['block_name']); ?></td>
                                    <td><?php echo htmlspecialchars($record['Email']); ?></td>
                                    <td><?php echo $record['total_subjects']; ?></td>
                                    <td class="text-success"><?php echo $record['present_days']; ?></td>
                                    <td class="text-warning"><?php echo $record['late_days']; ?></td>
                                    <td class="text-danger"><?php echo $record['absent_days']; ?></td>
                                    <td>
                                        <?php if ($record['attendance_rate'] !== null): ?>
                                            <span class="badge <?php echo $record['attendance_rate'] >= 90 
                                                ? 'bg-success' 
                                                : ($record['attendance_rate'] >= 75 ? 'bg-warning' : 'bg-danger'); ?>">
                                                <?php echo $record['attendance_rate']; ?>%
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">N/A</span>
                                        <?php endif; ?>
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
</body>
</html>