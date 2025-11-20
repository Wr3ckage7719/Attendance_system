<?php
require_once '../config/database.php';  // Correct relative path
header('Content-Type: application/json');

function createNextBlock($conn, $course_id, $year_level) {
    // Get the last block name for this course and year
    $query = "SELECT block_name 
              FROM blocks 
              WHERE course_id = ? AND year_level = ?
              ORDER BY block_name DESC 
              LIMIT 1";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $course_id, $year_level);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $last_block = $result->fetch_assoc()['block_name'];
        $next_block = chr(ord($last_block) + 1);
        
        // Check if we haven't exceeded 'E'
        if ($next_block <= 'E') {
            $stmt = $conn->prepare("INSERT INTO blocks (block_name, course_id, year_level) VALUES (?, ?, ?)");
            $stmt->bind_param("sii", $next_block, $course_id, $year_level);
            $stmt->execute();
            return $conn->insert_id;
        }
    } else {
        // No blocks exist yet, create block 'A'
        $stmt = $conn->prepare("INSERT INTO blocks (block_name, course_id, year_level) VALUES ('A', ?, ?)");
        $stmt->bind_param("ii", $course_id, $year_level);
        $stmt->execute();
        return $conn->insert_id;
    }
    return null;
}

$conn = connectDB();

if (isset($_GET['course_id']) && isset($_GET['year_level'])) {
    $course_id = intval($_GET['course_id']);
    $year_level = intval($_GET['year_level']);
    
    // Get all blocks for this course and year with proper student count by year level
    $query = "SELECT b.*, c.course_name, c.course_code,
              (SELECT COUNT(*) FROM student 
               WHERE block_id = b.block_id 
               AND year_level = b.year_level) as student_count
              FROM blocks b 
              INNER JOIN courses c ON b.course_id = c.course_id
              WHERE b.course_id = ? AND b.year_level = ?
              ORDER BY b.block_name";
              
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $course_id, $year_level);
    $stmt->execute();
    $blocks = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Check if we need a new block
    $need_new_block = true;
    foreach ($blocks as $block) {
        if ($block['student_count'] < 40) {
            $need_new_block = false;
            break;
        }
    }
    
    if ($need_new_block && count($blocks) < 5) { // Max 5 blocks (A-E)
        $new_block_id = createNextBlock($conn, $course_id, $year_level);
        if ($new_block_id) {
            // Add the new block to the results with proper student count
            $query = "SELECT b.*, c.course_name, c.course_code,
                     (SELECT COUNT(*) FROM student 
                      WHERE block_id = b.block_id 
                      AND year_level = b.year_level) as student_count
                     FROM blocks b 
                     INNER JOIN courses c ON b.course_id = c.course_id
                     WHERE b.block_id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $new_block_id);
            $stmt->execute();
            $new_block = $stmt->get_result()->fetch_assoc();
            if ($new_block) {
                $blocks[] = $new_block;
            }
        }
    }
    
    echo json_encode($blocks);
} else {
    echo json_encode(['error' => 'Missing required parameters']);
}
