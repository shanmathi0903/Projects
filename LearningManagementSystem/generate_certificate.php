<?php
include("config.php");
session_start();

// Validate student access
if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'student' || !isset($_POST['course_id']) || !is_numeric($_POST['course_id'])) {
    $response = [
        'success' => false,
        'message' => 'Invalid access or missing course ID'
    ];
    echo json_encode($response);
    exit();
}

$student_id = $_SESSION['user'];
$course_id = $_POST['course_id'];

// Verify the student is enrolled and completed the course
$check_query = $conn->prepare("
    SELECT 
        cv.id AS video_id,
        vp.is_completed
    FROM course_videos cv
    LEFT JOIN video_progress vp ON cv.id = vp.video_id AND vp.student_id = ?
    WHERE cv.course_id = ?
");
$check_query->bind_param("ii", $student_id, $course_id);
$check_query->execute();
$videos_result = $check_query->get_result();

$total_videos = 0;
$completed_videos = 0;

while ($video = $videos_result->fetch_assoc()) {
    $total_videos++;
    if ($video['is_completed']) {
        $completed_videos++;
    }
}

$all_completed = ($total_videos > 0 && $completed_videos == $total_videos);

if (!$all_completed) {
    $response = [
        'success' => false,
        'message' => 'You need to complete all videos before generating a certificate.'
    ];
    echo json_encode($response);
    exit();
}

// NEW CODE: Check if the course has a quiz and if the student passed it
$quiz_query = $conn->prepare("
    SELECT id, passing_score 
    FROM quizzes 
    WHERE course_id = ?
");
$quiz_query->bind_param("i", $course_id);
$quiz_query->execute();
$quiz_result = $quiz_query->get_result();

// If course has a quiz, check if student passed it
if ($quiz_result->num_rows > 0) {
    $quiz = $quiz_result->fetch_assoc();
    $quiz_id = $quiz['id'];
    $passing_score = $quiz['passing_score'];
    
    // Check if student has any attempts for this quiz
    $attempt_query = $conn->prepare("
        SELECT MAX(percentage_score) as highest_score
        FROM quiz_attempts 
        WHERE quiz_id = ? AND student_id = ?
    ");
    $attempt_query->bind_param("ii", $quiz_id, $student_id);
    $attempt_query->execute();
    $attempt_result = $attempt_query->get_result();
    $attempt_data = $attempt_result->fetch_assoc();
    
    // If no attempts or highest score below passing score, reject certificate generation
    if ($attempt_result->num_rows === 0 || $attempt_data['highest_score'] < $passing_score) {
        $response = [
            'success' => false,
            'message' => 'You must pass the quiz with at least ' . $passing_score . '% to earn a certificate.'
        ];
        echo json_encode($response);
        exit();
    }
}
// END NEW CODE

// Check if certificate already exists
$cert_check = $conn->prepare("
    SELECT id FROM certificates 
    WHERE student_id = ? AND course_id = ?
");
$cert_check->bind_param("ii", $student_id, $course_id);
$cert_check->execute();
$cert_result = $cert_check->get_result();

if ($cert_result->num_rows > 0) {
    // Certificate already exists
    $certificate_id = $cert_result->fetch_assoc()['id'];
    $response = [
        'success' => true,
        'message' => 'Certificate already exists',
        'certificate_id' => $certificate_id
    ];
    echo json_encode($response);
    exit();
}

// Start transaction
$conn->begin_transaction();

try {
    // Get student and course info for the certificate
    $info_stmt = $conn->prepare("
        SELECT 
            s.name AS student_name,
            c.title AS course_title
        FROM students s
        JOIN courses c ON c.id = ?
        WHERE s.id = ?
    ");
    $info_stmt->bind_param("ii", $course_id, $student_id);
    $info_stmt->execute();
    $info_result = $info_stmt->get_result();
    $info_data = $info_result->fetch_assoc();
    
    // Generate unique certificate number
    $certificate_number = 'CERT-' . date('Ymd') . '-' . $student_id . '-' . $course_id;
    
    // Insert certificate record
    $cert_stmt = $conn->prepare("
        INSERT INTO certificates 
        (student_id, course_id, certificate_number, issue_date) 
        VALUES (?, ?, ?, NOW())
    ");
    $cert_stmt->bind_param("iis", $student_id, $course_id, $certificate_number);
    $cert_stmt->execute();
    $certificate_id = $conn->insert_id;
    
    // Commit the transaction
    $conn->commit();
    
    // Return success response with certificate ID
    $response = [
        'success' => true,
        'message' => 'Certificate generated successfully',
        'certificate_id' => $certificate_id
    ];
    echo json_encode($response);
    exit();
    
} catch (Exception $e) {
    // Rollback the transaction
    $conn->rollback();
    
    // Return error response
    $response = [
        'success' => false,
        'message' => 'Failed to generate certificate: ' . $e->getMessage()
    ];
    echo json_encode($response);
    exit();
}
?>