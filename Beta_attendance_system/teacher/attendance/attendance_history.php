<?php
session_start();
require_once '../../app/config/auth_check.php';
require_once '../../app/config/database.php';
checkAuth('teacher');

$conn = connectDB();
$teacher_id = $_SESSION['teacher_id'];

// Get filter values
$class_id = isset($_GET['class_id']) ? $_GET['class_id'] : null;
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-d', strtotime('-30 days'));
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Fetch teacher's classes for filter
$classQuery = "SELECT c.Class_id, 
               CONCAT(s.Subject_Name, ' - ', 
                     DATE_FORMAT(c.start_time, '%h:%i %p'), ' - ',
                     DATE_FORMAT(c.end_time, '%h:%i %p'), ' ',
                     GROUP_CONCAT(cd.day_of_week)) as class_name 
               FROM classes c 
               INNER JOIN subject s ON c.Subject_id = s.Subject_id 
               LEFT JOIN class_days cd ON c.Class_id = cd.class_id
               WHERE c.Teacher_id = ?
               GROUP BY c.Class_id";

$stmt = $conn->prepare($classQuery);
if ($stmt === false) {
    die('Error preparing class query: ' . $conn->error);
}
$stmt->bind_param("i", $teacher_id);
if (!$stmt->execute()) {
    die('Error executing class query: ' . $stmt->error);
}
$classes = $stmt->get_result();

// Fetch attendance records
$query = "SELECT 
            s.Student_Id,
            s.first_name,
            s.Last_name,
            s.year_level,
            c.Class_id,
            sub.Subject_Name,
            b.block_name,
            co.course_name,
            COUNT(a.date) as total_days,
            SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present_count
          FROM classes c
          INNER JOIN blocks b ON c.block_id = b.block_id
          INNER JOIN student s ON s.block_id = b.block_id 
          INNER JOIN subject sub ON c.Subject_id = sub.Subject_id
          INNER JOIN courses co ON b.course_id = co.course_id
          LEFT JOIN attendance a ON (
              a.class_id = c.Class_id 
              AND a.student_id = s.Student_Id
              AND a.date BETWEEN ? AND ?
          )
          WHERE c.Teacher_id = ?";

$params = ["ssi", $date_from, $date_to, $teacher_id];

if ($class_id) {
    $query .= " AND c.Class_id = ?";
    $params[0] .= "i";
    $params[] = $class_id;
}

if ($search) {
    $query .= " AND (s.first_name LIKE ? OR s.Last_name LIKE ? OR s.Student_Id LIKE ?)";
    $params[0] .= "sss";
    $searchParam = '%' . $search . '%';
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

$query .= " GROUP BY s.Student_Id, c.Class_id
            ORDER BY s.year_level, co.course_name, b.block_name, s.Last_name, s.first_name";

// Prepare attendance query with better error handling
$stmt = $conn->prepare($query);
if ($stmt === false) {
    die('Error preparing attendance query: ' . $conn->error);
}

// Fix bind_param reference issue with error handling
try {
    $types = $params[0];
    $bindParams = array($types);
    for ($i = 1; $i < count($params); $i++) {
        $bindParams[] = &$params[$i];
    }
    call_user_func_array([$stmt, 'bind_param'], $bindParams);
    
    if (!$stmt->execute()) {
        throw new Exception('Error executing attendance query: ' . $stmt->error);
    }
    $attendance_records = $stmt->get_result();
} catch (Exception $e) {
    die($e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance History</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="/Beta_attendance_system/assets/css/main.css" rel="stylesheet">
    <link href="/Beta_attendance_system/assets/css/teacher-pages.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</head>
<body>
    <?php include '../../app/includes/teacher_navbar.php'; ?>

    <div class="container mt-4">
        <h2 class="page-header">Attendance History</h2>
        
        <!-- Filters -->
        <div class="filter-card">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label for="search" class="form-label">Search</label>
                        <input type="text" class="form-control" id="search" name="search" 
                               placeholder="Search by name or ID"
                               value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="class_id" class="form-label">Class</label>
                        <select class="form-select" name="class_id">
                            <option value="">All Classes</option>
                            <?php while ($class = $classes->fetch_assoc()): ?>
                                <option value="<?php echo $class['Class_id']; ?>" 
                                    <?php echo ($class_id == $class['Class_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($class['class_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="date_from" class="form-label">From Date</label>
                        <input type="date" class="form-control" name="date_from" 
                               value="<?php echo $date_from; ?>">
                    </div>
                    <div class="col-md-2">
                        <label for="date_to" class="form-label">To Date</label>
                        <input type="date" class="form-control" name="date_to" 
                               value="<?php echo $date_to; ?>">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">Filter</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Attendance Records -->
        <div class="content-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div class="small text-muted">
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th style="min-width: 180px">Student Name</th>
                                <th style="min-width: 150px">Subject</th>
                                <th style="width: 80px">Block</th>
                                <th style="min-width: 120px">Course</th>
                                <th style="width: 100px" class="text-center">Present/Total</th>
                                <th style="width: 100px" class="text-center">Rate</th>
                                <th style="width: 160px" class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($record = $attendance_records->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex flex-column">
                                            <span class="fw-medium"><?php echo htmlspecialchars($record['first_name'] . ' ' . $record['Last_name']); ?></span>
                                            <small class="text-muted"><?php echo htmlspecialchars($record['year_level']); ?>th Year</small>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="d-flex flex-column">
                                            <span class="fw-medium"><?php echo htmlspecialchars($record['Subject_Name']); ?></span>
                                            <small class="text-muted"><?php echo htmlspecialchars($record['block_name']); ?></small>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($record['block_name']); ?></td>
                                    <td><?php echo htmlspecialchars($record['course_name']); ?></td>
                                    <td class="text-center">
                                        <span class="badge bg-light text-dark">
                                            <?php echo $record['present_count']; ?><small class="text-muted">/<?php echo $record['total_days']; ?></small>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <?php 
                                        if ($record['total_days'] > 0) {
                                            $rate = ($record['present_count'] / $record['total_days']) * 100;
                                            $colorClass = $rate >= 90 ? 'success' : 
                                                        ($rate >= 75 ? 'warning' : 'danger');
                                            echo '<div class="d-flex flex-column align-items-center">';
                                            echo '<span class="badge bg-' . $colorClass . '">' . number_format($rate, 1) . '%</span>';
                                            if ($rate < 75) {
                                                echo '<small class="text-danger mt-1">At Risk</small>';
                                            }
                                            echo '</div>';
                                        } else {
                                            echo '<span class="badge bg-secondary">N/A</span>';
                                        }
                                        ?>
                                    </td>
                                    <td class="text-end">
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" class="btn btn-info" data-student-id="<?php echo $record['Student_Id']; ?>" data-class-id="<?php echo $record['Class_id']; ?>">
                                                <i class="fas fa-eye"></i> View
                                            </button>
                                            <button type="button" class="btn btn-warning" data-student-id="<?php echo $record['Student_Id']; ?>" data-class-id="<?php echo $record['Class_id']; ?>">
                                                <i class="fas fa-edit"></i> Edit
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- View Details Modal -->
    <div class="modal fade" id="viewDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-light">
                    <h5 class="modal-title">
                        <i class="fas fa-clipboard-list me-2"></i>
                        Attendance Details
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="attendanceDetails">
                    <div class="d-flex justify-content-center align-items-center py-5">
                        <div class="spinner-border text-primary me-3" role="status"></div>
                        <span>Loading attendance details...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Modal -->
    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-light">
                    <h5 class="modal-title">
                        <i class="fas fa-edit me-2"></i>
                        Edit Attendance History
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="studentInfo" class="mb-3"></div>
                    <div class="table-responsive">
                        <table class="table" id="editAttendanceTable">
                            <thead class="table-light">
                                <tr>
                                    <th>Date</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td colspan="2" class="text-center py-4">
                                        <div class="spinner-border text-primary mb-2" role="status"></div>
                                        <p class="text-muted mb-0">Loading records...</p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>
                        Cancel
                    </button>
                    <button type="button" class="btn btn-primary" onclick="saveAttendance()">
                        <i class="fas fa-save me-1"></i>
                        Save Changes
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize Bootstrap modals
            const viewModal = new bootstrap.Modal(document.getElementById('viewDetailsModal'));
            const editModal = new bootstrap.Modal(document.getElementById('editModal'));
            window.viewModal = viewModal;
            window.editModal = editModal;

            // Initialize tooltips
            const tooltips = document.querySelectorAll('[data-bs-toggle="tooltip"]');
            tooltips.forEach(tooltip => new bootstrap.Tooltip(tooltip));

            // Add click event listeners to buttons
            document.querySelectorAll('.btn-info').forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    const studentId = this.getAttribute('data-student-id');
                    const classId = this.getAttribute('data-class-id');
                    viewDetails(studentId, classId);
                });
            });

            document.querySelectorAll('.btn-warning').forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    const studentId = this.getAttribute('data-student-id');
                    const classId = this.getAttribute('data-class-id');
                    const dateFrom = document.querySelector('input[name="date_from"]').value;
                    const dateTo = document.querySelector('input[name="date_to"]').value;
                    editHistory(studentId, classId, dateFrom, dateTo);
                });
            });

            // Initialize date range validation
            const dateFrom = document.querySelector('input[name="date_from"]');
            const dateTo = document.querySelector('input[name="date_to"]');
            
            dateFrom.addEventListener('change', validateDateRange);
            dateTo.addEventListener('change', validateDateRange);
        });

        let currentEditData = {};
        const STATUS_COLORS = {
            present: 'success',
            absent: 'danger',
            late: 'warning'
        };

        async function showLoadingSpinner(elementId) {
            document.getElementById(elementId).innerHTML = `
                <div class="d-flex justify-content-center align-items-center py-4">
                    <div class="spinner-border text-primary me-3" role="status"></div>
                    <span>Loading...</span>
                </div>`;
        }

        async function showErrorMessage(elementId, error, retryFunction, ...params) {
            document.getElementById(elementId).innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    ${error.message}
                    <button type="button" class="btn btn-outline-danger btn-sm mt-2" 
                            onclick="${retryFunction}(${params.map(p => `'${p}'`).join(',')})">
                        <i class="fas fa-sync-alt me-1"></i> Try Again
                    </button>
                </div>`;
        }

        async function viewDetails(studentId, classId) {
            try {
                const row = event.target.closest('tr');
                const studentName = row.cells[0].textContent;
                const subject = row.cells[1].textContent;
                const dateFrom = document.querySelector('input[name="date_from"]').value;
                const dateTo = document.querySelector('input[name="date_to"]').value;
                
                showLoadingSpinner('attendanceDetails');
                window.viewModal.show();
                
                const response = await fetch(`details.php?student_id=${studentId}&class_id=${classId}&date_from=${dateFrom}&date_to=${dateTo}`);
                if (!response.ok) throw new Error(`Failed to fetch attendance details (${response.status})`);
                
                const data = await response.json();
                if (data.error) throw new Error(data.error);

                const rateColor = data.stats.attendance_rate >= 90 ? 'success' : 
                                (data.stats.attendance_rate >= 75 ? 'warning' : 'danger');
                
                document.getElementById('attendanceDetails').innerHTML = generateDetailsView(
                    studentName, subject, data, rateColor, studentId, classId, dateFrom, dateTo
                );
            } catch (error) {
                console.error('Error:', error);
                showErrorMessage('attendanceDetails', error, 'viewDetails', studentId, classId);
            }
        }

        function generateDetailsView(studentName, subject, data, rateColor, studentId, classId, dateFrom, dateTo) {
            return `
                <div class="alert alert-info">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h5 class="mb-1">${studentName}</h5>
                            <div>${subject}</div>
                        </div>
                        <span class="badge bg-${rateColor} fs-6">
                            ${data.stats.attendance_rate}%
                        </span>
                    </div>
                    <div class="mt-3">
                        <span class="badge bg-success">Present: ${data.stats.total_present}</span>
                        <span class="badge bg-danger ms-2">Absent: ${data.stats.total_days - data.stats.total_present}</span>
                        <span class="badge bg-secondary ms-2">Total: ${data.stats.total_days}</span>
                    </div>
                </div>
                <div class="mb-3">
                    <a href="download.php?student_id=${studentId}&class_id=${classId}&date_from=${dateFrom}&date_to=${dateTo}" 
                       class="btn btn-success btn-sm">
                        <i class="fas fa-download me-1"></i> Download Report
                    </a>
                </div>
                ${generateAttendanceTable(data.attendance)}`;
        }

        function generateAttendanceTable(attendance) {
            if (!attendance || attendance.length === 0) {
                return `
                    <div class="alert alert-warning">
                        <i class="fas fa-info-circle me-2"></i>
                        No attendance records found for the selected period
                    </div>`;
            }

            return `
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Date</th>
                                <th>Day</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${attendance.map(record => `
                                <tr>
                                    <td>${record.formatted_date}</td>
                                    <td>${record.day_name}</td>
                                    <td>
                                        <span class="badge bg-${STATUS_COLORS[record.status]}">
                                            ${record.status.charAt(0).toUpperCase() + record.status.slice(1)}
                                        </span>
                                    </td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>`;
        }

        async function editHistory(studentId, classId, dateFrom, dateTo) {
            try {
                const row = event.target.closest('tr');
                const studentName = row.cells[0].textContent;
                const subject = row.cells[1].textContent;
                
                currentEditData = { studentId, classId, dateFrom, dateTo };
                
                document.getElementById('studentInfo').innerHTML = `
                    <div class="alert alert-info">
                        <h5 class="mb-1">${studentName}</h5>
                        <div>${subject}</div>
                        <small class="text-muted mt-2 d-block">
                            Editing period: ${dateFrom} to ${dateTo}
                        </small>
                    </div>`;

                const tbody = document.querySelector('#editAttendanceTable tbody');
                tbody.innerHTML = `
                    <tr>
                        <td colspan="2" class="text-center py-4">
                            <div class="spinner-border text-primary mb-2"></div>
                            <p class="text-muted mb-0">Loading attendance records...</p>
                        </td>
                    </tr>`;
                
                window.editModal.show();
                
                const response = await fetch(`details.php?student_id=${studentId}&class_id=${classId}&date_from=${dateFrom}&date_to=${dateTo}`);
                if (!response.ok) throw new Error(`Failed to fetch attendance details (${response.status})`);
                
                const data = await response.json();
                if (data.error) throw new Error(data.error);

                tbody.innerHTML = generateEditTableContent(data.attendance);

                // Add change event listeners to track modifications
                document.querySelectorAll('#editAttendanceTable select').forEach(select => {
                    select.addEventListener('change', () => {
                        select.classList.add('border-primary');
                    });
                });
            } catch (error) {
                console.error('Error:', error);
                document.querySelector('#editAttendanceTable tbody').innerHTML = `
                    <tr>
                        <td colspan="2">
                            <div class="alert alert-danger mb-0">
                                <i class="fas fa-exclamation-circle me-2"></i>
                                ${error.message}
                                <button type="button" class="btn btn-outline-danger btn-sm mt-2" 
                                        onclick="editHistory('${studentId}', '${classId}', '${dateFrom}', '${dateTo}')">
                                    <i class="fas fa-sync-alt me-1"></i> Try Again
                                </button>
                            </div>
                        </td>
                    </tr>`;
            }
        }

        function generateEditTableContent(attendance) {
            if (!attendance || attendance.length === 0) {
                return `
                    <tr>
                        <td colspan="2">
                            <div class="alert alert-warning mb-0">
                                <i class="fas fa-info-circle me-2"></i>
                                No attendance records found for the selected period
                            </div>
                        </td>
                    </tr>`;
            }

            return attendance.map(record => `
                <tr>
                    <td>${record.formatted_date}</td>
                    <td>
                        <select class="form-select form-select-sm" data-date="${record.date}"
                                data-original="${record.status}">
                            <option value="present" ${record.status === 'present' ? 'selected' : ''}>Present</option>
                            <option value="late" ${record.status === 'late' ? 'selected' : ''}>Late</option>
                            <option value="absent" ${record.status === 'absent' ? 'selected' : ''}>Absent</option>
                        </select>
                    </td>
                </tr>`).join('');
        }

        async function saveAttendance() {
            const saveButton = document.querySelector('#editModal .btn-primary');
            const originalText = saveButton.innerHTML;
            
            try {
                const rows = document.querySelectorAll('#editAttendanceTable tbody tr select');
                const updates = [];
                
                rows.forEach(select => {
                    if (select?.dataset.date && select.value !== select.dataset.original) {
                        updates.push({
                            date: select.dataset.date,
                            status: select.value
                        });
                    }
                });

                if (updates.length === 0) {
                    throw new Error('No changes were made to the attendance records');
                }

                // Disable save button and show loading state
                saveButton.disabled = true;
                saveButton.innerHTML = `
                    <span class="spinner-border spinner-border-sm me-2"></span>
                    Saving Changes...`;

                const response = await fetch('update.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        ...currentEditData,
                        updates: updates
                    })
                });

                const data = await response.json();
                if (!data.success) {
                    throw new Error(data.message || 'Failed to update attendance');
                }

                // Show success message
                document.querySelector('#editModal .modal-body').innerHTML = `
                    <div class="alert alert-success mb-0">
                        <i class="fas fa-check-circle me-2"></i>
                        Attendance records updated successfully!
                    </div>`;
                
                // Reload after a short delay
                setTimeout(() => location.reload(), 1500);
            } catch (error) {
                console.error('Error:', error);
                saveButton.disabled = false;
                saveButton.innerHTML = originalText;
                
                alert(error.message || 'Error updating attendance');
            }
        }
    </script>
</body>
</html>
