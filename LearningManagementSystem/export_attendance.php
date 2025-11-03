<?php
include("config.php");
session_start();

// Turn off error display for production
ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_DEPRECATED);

// Ensure only admin or teachers can access this page
if (!isset($_SESSION['user']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'teacher')) {
    header("Location: login.php");
    exit();
}

// Get filter parameters
$filter_student = isset($_GET['student_id']) ? intval($_GET['student_id']) : 0;
$filter_course = isset($_GET['course_id']) ? intval($_GET['course_id']) : 0;
$filter_date_start = isset($_GET['date_start']) ? $_GET['date_start'] : '';
$filter_date_end = isset($_GET['date_end']) ? $_GET['date_end'] : '';

// Get current teacher's ID from session
$teacher_id = $_SESSION['user']; // Make sure this matches your session variable name

// Base query - Different approach based on existing database structure
$query = "
    SELECT 
        a.id,
        a.student_id,
        a.course_id,
        a.video_id,
        a.status,
        a.watched_time,
        a.activity_type,
        a.start_time,
        a.end_time,
        TIMESTAMPDIFF(SECOND, a.start_time, COALESCE(a.end_time, NOW())) as duration_seconds,
        s.name as student_name,
        c.title as course_title,
        CASE
            WHEN a.activity_type = 'video_watch' THEN cv.video_title
            WHEN a.activity_type = 'quiz_attempt' THEN q.title
            ELSE 'Unknown'
        END as activity_name
    FROM 
        attendance a
    JOIN
        students s ON a.student_id = s.id
    JOIN
        courses c ON a.course_id = c.id
    LEFT JOIN
        course_videos cv ON a.video_id = cv.id AND a.activity_type = 'video_watch'
    LEFT JOIN
        quizzes q ON a.video_id = q.id AND a.activity_type = 'quiz_attempt'
    WHERE 1=1 ";

// Apply teacher filter only if role is teacher (admin can see all)
$params = [];
$param_types = "";

if ($_SESSION['role'] === 'teacher') {
    // Modify this according to your database structure - assuming courses has teacher_id
    $query .= " AND c.teacher_id = ?";
    $params[] = $teacher_id;
    $param_types .= "i";
}

// Apply additional filters
if ($filter_student > 0) {
    $query .= " AND a.student_id = ?";
    $params[] = $filter_student;
    $param_types .= "i";
}

if ($filter_course > 0) {
    $query .= " AND a.course_id = ?";
    $params[] = $filter_course;
    $param_types .= "i";
}

if (!empty($filter_date_start)) {
    $query .= " AND DATE(a.start_time) >= ?";
    $params[] = $filter_date_start;
    $param_types .= "s";
}

if (!empty($filter_date_end)) {
    $query .= " AND DATE(a.start_time) <= ?";
    $params[] = $filter_date_end;
    $param_types .= "s";
}

// IMPORTANT CHANGE: Removed the default date filter that was limiting to current day
// Only apply date filters if explicitly provided by user

// Order by
$query .= " ORDER BY a.start_time DESC";

try {
    // Prepare and execute the query
    $stmt = $conn->prepare($query);
    
    if (!empty($params)) {
        // PHP 8.0+ spread operator compatible version
        if (count($params) > 0) {
            $stmt->bind_param($param_types, ...$params);
        }
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Set appropriate headers to avoid caching
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Set headers for CSV download - explicitly set separator
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename=attendance_report_' . date('Y-m-d') . '.csv');
    
    // Create output stream
    $output = fopen('php://output', 'w');
    
    // Add UTF-8 BOM (to ensure Excel opens the CSV with proper encoding)
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // CSV headers as array - IMPORTANT: Match exact column names from your web interface
    $headers = [
        'Student Name',
        'Course Title',
        'Activity Type', // Make sure this header exactly matches what Excel should display
        'Activity Name',
        'Status',
        'Start Time',
        'End Time',
        'Duration (HH:MM:SS)',
        'Watched Time (Seconds)'
    ];
    
    // Write headers using fputcsv to properly escape values
    fputcsv($output, $headers, ',', '"', '\\');
    
    // Add data rows
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            // IMPORTANT: Ensure we properly format the activity_type field
            $activity_type = ucfirst(str_replace('_', ' ', $row['activity_type']));
            
            $data = [
                $row['student_name'],
                $row['course_title'],
                $activity_type, // Properly formatted activity type
                $row['activity_name'],
                $row['status'],
                $row['start_time'],
                $row['end_time'] ? $row['end_time'] : 'In Progress',
                gmdate("H:i:s", $row['duration_seconds']),
                $row['watched_time']
            ];
            
            // Use fputcsv to properly handle CSV formatting
            fputcsv($output, $data, ',', '"', '\\');
        }
    } else {
        // Add a "No records found" message if there's no data
        fputcsv($output, ["No attendance records found matching the specified criteria"], ',', '"', '\\');
    }
    
    // Close the output stream
    fclose($output);
} catch (Exception $e) {
    // Log error (don't display to user)
    error_log("CSV Export Error: " . $e->getMessage());
    
    // Return simple error to browser
    header('Content-Type: text/plain; charset=UTF-8');
    echo "Error generating report. Please try again or contact support.";
}

// End script
exit;
?>