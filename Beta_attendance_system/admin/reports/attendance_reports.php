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
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-d', strtotime('-30 days'));
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');

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

// Build attendance report query
$query = "SELECT 
            s.Student_Id,
            s.first_name,
            s.Last_name,
            s.year_level,
            b.block_name,
            c.course_name,
            COUNT(DISTINCT att.date) as total_days,
            SUM(CASE WHEN att.status = 'present' THEN 1 ELSE 0 END) as present_days,
            SUM(CASE WHEN att.status = 'late' THEN 1 ELSE 0 END) as late_days,
            SUM(CASE WHEN att.status = 'absent' THEN 1 ELSE 0 END) as absent_days
          FROM student s
          INNER JOIN blocks b ON s.block_id = b.block_id
          INNER JOIN courses c ON b.course_id = c.course_id
          LEFT JOIN classes cl ON b.block_id = cl.block_id
          LEFT JOIN attendance att ON (
              att.student_id = s.Student_Id
              AND att.class_id = cl.Class_id
              AND att.date BETWEEN ? AND ?
          )
          WHERE 1=1";

$params = ["ss", $date_from, $date_to];

// Add search condition
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search = '%' . $conn->real_escape_string($_GET['search']) . '%';
    $query .= " AND (s.Student_Id LIKE ? OR s.first_name LIKE ? OR s.Last_name LIKE ?)";
    $params[0] .= "sss";
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

$query .= " GROUP BY s.Student_Id
            ORDER BY c.course_name, s.year_level, b.block_name, s.Last_name, s.first_name";

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
$attendance_records = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Reports</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <?php include '../../app/includes/admin_navbar.php'; ?>

    <div class="container mt-4">
        <h2>Attendance Reports</h2>

        <!-- Filters -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
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
                                    <?php echo (isset($_GET['block_id']) && $_GET['block_id'] == $block_id_found) ? 'selected' : ''; ?>>
                                    Block <?php echo $block_name; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">From Date</label>
                        <input type="date" class="form-control" name="date_from" value="<?php echo $date_from; ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">To Date</label>
                        <input type="date" class="form-control" name="date_to" value="<?php echo $date_to; ?>">
                    </div>
                    <div class="col-md-1">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-primary w-100">Filter</button>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Search</label>
                        <input type="text" class="form-control" name="search" value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                    </div>
                </form>
            </div>
        </div>

        <!-- Attendance Report Table -->
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
                                <th>Total Days</th>
                                <th>Present</th>
                                <th>Late</th>
                                <th>Absent</th>
                                <th>Attendance Rate</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($record = $attendance_records->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($record['Student_Id']); ?></td>
                                    <td><?php echo htmlspecialchars($record['first_name'] . ' ' . $record['Last_name']); ?></td>
                                    <td><?php echo htmlspecialchars($record['course_name']); ?></td>
                                    <td><?php echo $record['year_level']; ?></td>
                                    <td><?php echo htmlspecialchars($record['block_name']); ?></td>
                                    <td><?php echo $record['total_days']; ?></td>
                                    <td class="text-success"><?php echo $record['present_days']; ?></td>
                                    <td class="text-warning"><?php echo $record['late_days']; ?></td>
                                    <td class="text-danger"><?php echo $record['absent_days']; ?></td>
                                    <td>
                                        <?php
                                        if ($record['total_days'] > 0) {
                                            $rate = (($record['present_days'] + ($record['late_days'] * 0.5)) / $record['total_days']) * 100;
                                            $color = $rate >= 75 ? 'text-success' : 'text-danger';
                                            echo "<span class='$color'>" . number_format($rate, 1) . '%</span>';
                                        } else {
                                            echo 'N/A';
                                        }
                                        ?>
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