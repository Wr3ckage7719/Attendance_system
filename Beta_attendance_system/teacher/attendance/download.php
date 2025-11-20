<?php
session_start();
require_once '../../app/config/auth_check.php';
require_once '../../app/config/database.php';

// Add error handling for FPDF
if (!file_exists('../../fpdf/fpdf.php')) {
    die('FPDF not installed. Please install FPDF first.');
}
require('../../fpdf/fpdf.php');
checkAuth('teacher');

class AttendancePDF extends FPDF {
    private $rightTableEdge;

    function setTableRightEdge($edge) {
        $this->rightTableEdge = $edge;
    }

    function Header() {
        $this->SetFont('Arial', 'B', 15);
        $this->Cell(0, 10, 'Attendance Report', 0, 1, 'C');
        $this->Ln(5);
    }

    function Footer() {
        $this->SetY(-40);
        $this->SetFont('Arial', '', 10);
        
        // Align "Verified by:" with table's right edge
        $this->SetX($this->rightTableEdge - $this->GetStringWidth('Verified by:'));
        $this->Cell($this->GetStringWidth('Verified by:'), 10, 'Verified by:', 0, 1, 'L');
        
        // Draw signature line aligned with table
        $lineWidth = 60;
        $this->Line($this->rightTableEdge - $lineWidth, $this->GetY()+5, $this->rightTableEdge, $this->GetY()+5);
        
        $this->SetY($this->GetY()+10);
        // Align signature text with line
        $this->SetX($this->rightTableEdge - $lineWidth);
        $this->Cell($lineWidth, 10, "Teacher's Signature", 0, 0, 'C');
    }
}

$conn = connectDB();
$student_id = $_GET['student_id'] ?? '';
$class_id = $_GET['class_id'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// Get class and student details
$detailsQuery = "SELECT 
    CONCAT(s.first_name, ' ', s.Last_name) as student_name,
    s.Student_Id,
    s.year_level,
    sub.Subject_Name,
    CONCAT(t.first_name, ' ', t.last_name) as teacher_name,
    b.block_name,
    co.course_name
    FROM student s
    INNER JOIN blocks b ON s.block_id = b.block_id
    INNER JOIN courses co ON b.course_id = co.course_id
    INNER JOIN classes c ON b.block_id = c.block_id
    INNER JOIN subject sub ON c.Subject_id = sub.Subject_id
    INNER JOIN teachers t ON c.Teacher_id = t.Teacher_id
    WHERE s.Student_Id = ? AND c.Class_id = ?";

$stmt = $conn->prepare($detailsQuery);
$stmt->bind_param("si", $student_id, $class_id);
$stmt->execute();
$details = $stmt->get_result()->fetch_assoc();

// Get attendance records
$query = "SELECT 
    DATE_FORMAT(date, '%M %d, %Y') as formatted_date,
    DAYNAME(date) as day_name,
    status
    FROM attendance 
    WHERE student_id = ? 
    AND class_id = ? 
    AND date BETWEEN ? AND ?
    ORDER BY date ASC";

$stmt = $conn->prepare($query);
$stmt->bind_param("siss", $student_id, $class_id, $date_from, $date_to);
$stmt->execute();
$result = $stmt->get_result();

// Create PDF with Portrait orientation (changed from 'L' to 'P')
$pdf = new AttendancePDF('P');
$pdf->AddPage();

// Calculate table position first (adjusted for portrait)
$pageWidth = $pdf->GetPageWidth();
$tableWidth = $pageWidth * 0.9; // Increased from 0.8 to 0.9 for better use of space
$leftMargin = ($pageWidth - $tableWidth) / 2;
$dateWidth = $tableWidth * 0.4;
$dayWidth = $tableWidth * 0.3;
$statusWidth = $tableWidth * 0.3;

// After calculating table dimensions, set the right edge position
$rightTableEdge = $leftMargin + $tableWidth;
$pdf->setTableRightEdge($rightTableEdge);

// Header Information aligned with table (adjusted font size)
$pdf->SetX($leftMargin);
$pdf->SetFont('Arial', 'B', 11); // Reduced from 12 to 11
$pdf->Cell(0, 8, 'Subject: ' . $details['Subject_Name'], 0, 1);
$pdf->SetX($leftMargin);
$pdf->Cell(0, 8, 'Teacher: ' . $details['teacher_name'], 0, 1);
$pdf->Ln(5);

// Student Information aligned with table
$pdf->SetX($leftMargin);
$pdf->SetFont('Arial', '', 11);
$pdf->Cell(0, 6, 'Student: ' . $details['student_name'] . ' (' . $details['Student_Id'] . ')', 0, 1);
$pdf->SetX($leftMargin);
$pdf->Cell(0, 6, 'Year Level: ' . $details['year_level'], 0, 1);
$pdf->SetX($leftMargin);
$pdf->Cell(0, 6, 'Course: ' . $details['course_name'] . ' - ' . $details['block_name'], 0, 1);
$pdf->SetX($leftMargin);
$pdf->Cell(0, 6, 'Period: ' . $date_from . ' to ' . $date_to, 0, 1);
$pdf->Ln(5);

// Move to centered position
$pdf->SetX($leftMargin);

// Table Header
$pdf->SetFillColor(230, 230, 230);
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell($dateWidth, 7, 'Date', 1, 0, 'C', true);
$pdf->Cell($dayWidth, 7, 'Day', 1, 0, 'C', true);
$pdf->Cell($statusWidth, 7, 'Status', 1, 1, 'C', true);

// Table Content
$pdf->SetFont('Arial', '', 10);
while ($row = $result->fetch_assoc()) {
    $pdf->SetX($leftMargin); // Reset X position for each row
    $status_color = match($row['status']) {
        'present' => array(0, 150, 0),    // Green
        'absent' => array(200, 0, 0),     // Red
        'late' => array(255, 140, 0),     // Orange
        default => array(0, 0, 0)         // Black
    };
    
    $pdf->Cell($dateWidth, 7, $row['formatted_date'], 1, 0, 'C');
    $pdf->Cell($dayWidth, 7, $row['day_name'], 1, 0, 'C');
    $pdf->SetTextColor($status_color[0], $status_color[1], $status_color[2]);
    $pdf->Cell($statusWidth, 7, ucfirst($row['status']), 1, 1, 'C');
    $pdf->SetTextColor(0, 0, 0);
}

// Reset position for statistics
$pdf->SetX(0);

// Calculate statistics
$pdf->Ln(5);
$pdf->SetX($leftMargin);
$pdf->SetFont('Arial', 'B', 10);
$stats = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT 
        COUNT(*) as total_days,
        SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_days,
        SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late_days,
        ROUND(((SUM(CASE WHEN status = 'present' THEN 1 
                        WHEN status = 'late' THEN 0.5 
                        ELSE 0 END) / COUNT(*)) * 100), 1) as attendance_rate
    FROM attendance 
    WHERE student_id = '$student_id' 
    AND class_id = $class_id 
    AND date BETWEEN '$date_from' AND '$date_to'
"));

$pdf->SetX($leftMargin);
$pdf->Cell(0, 7, "Total Days: {$stats['total_days']}", 0, 1);
$pdf->SetX($leftMargin);
$pdf->Cell(0, 7, "Present Days: {$stats['present_days']}", 0, 1);
$pdf->SetX($leftMargin);
$pdf->Cell(0, 7, "Late Days: {$stats['late_days']}", 0, 1);
$pdf->SetX($leftMargin);
$pdf->Cell(0, 7, "Attendance Rate: {$stats['attendance_rate']}%", 0, 1);

// Sanitize the filename to remove invalid characters
$student_name = preg_replace('/[^a-zA-Z0-9]/', '_', $details['student_name']);
$filename = sprintf('%s_Attendance_%s_to_%s.pdf', 
    $student_name,
    date('Y-m-d', strtotime($date_from)),
    date('Y-m-d', strtotime($date_to))
);

// Add error handling for PDF generation
try {
    $pdf->Output('D', $filename);
} catch (Exception $e) {
    die('Error generating PDF: ' . $e->getMessage());
}
exit;
