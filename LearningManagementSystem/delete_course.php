<?php
session_start();
include 'config.php';

// Check if teacher is logged in
if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'teacher') {
    header("Location: login.php");
    exit();
}

$teacher_id = $_SESSION['user'];

if (!isset($_GET['id'])) {
    die("Invalid request.");
}

$course_id = $_GET['id'];

// Check if the course belongs to the logged-in teacher
$check_stmt = $conn->prepare("SELECT * FROM courses WHERE id = ? AND teacher_id = ?");
$check_stmt->bind_param("ii", $course_id, $teacher_id);
$check_stmt->execute();
$course = $check_stmt->get_result()->fetch_assoc();

if (!$course) {
    die("Course not found or you don't have permission to delete it.");
}

// Start transaction
$conn->begin_transaction();

try {
    // First, get all quiz IDs related to this course
    $quiz_ids = [];
    $quiz_stmt = $conn->prepare("SELECT id FROM quizzes WHERE course_id = ?");
    $quiz_stmt->bind_param("i", $course_id);
    $quiz_stmt->execute();
    $quiz_result = $quiz_stmt->get_result();
    while ($row = $quiz_result->fetch_assoc()) {
        $quiz_ids[] = $row['id'];
    }
    
    // If there are quizzes, delete related quiz attempts and answers
    if (!empty($quiz_ids)) {
        foreach ($quiz_ids as $quiz_id) {
            // Get all attempt IDs for this quiz
            $attempt_ids = [];
            $attempt_stmt = $conn->prepare("SELECT id FROM quiz_attempts WHERE quiz_id = ?");
            $attempt_stmt->bind_param("i", $quiz_id);
            $attempt_stmt->execute();
            $attempt_result = $attempt_stmt->get_result();
            while ($row = $attempt_result->fetch_assoc()) {
                $attempt_ids[] = $row['id'];
            }
            
            // Delete quiz answers for each attempt
            if (!empty($attempt_ids)) {
                foreach ($attempt_ids as $attempt_id) {
                    $delete_answers = $conn->prepare("DELETE FROM quiz_answers WHERE attempt_id = ?");
                    $delete_answers->bind_param("i", $attempt_id);
                    $delete_answers->execute();
                }
            }
            
            // Delete quiz attempts
            $delete_attempts = $conn->prepare("DELETE FROM quiz_attempts WHERE quiz_id = ?");
            $delete_attempts->bind_param("i", $quiz_id);
            $delete_attempts->execute();
            
            // Delete quiz questions
            $delete_questions = $conn->prepare("DELETE FROM quiz_questions WHERE quiz_id = ?");
            $delete_questions->bind_param("i", $quiz_id);
            $delete_questions->execute();
        }
    }
    
    // Now delete quizzes
    $delete_quizzes = $conn->prepare("DELETE FROM quizzes WHERE course_id = ?");
    $delete_quizzes->bind_param("i", $course_id);
    $delete_quizzes->execute();
    
    // Delete video progress records
    $delete_video_progress = $conn->prepare("
        DELETE FROM video_progress 
        WHERE video_id IN (SELECT id FROM course_videos WHERE course_id = ?)
    ");
    $delete_video_progress->bind_param("i", $course_id);
    $delete_video_progress->execute();
    
    // Delete course videos
    $delete_videos = $conn->prepare("DELETE FROM course_videos WHERE course_id = ?");
    $delete_videos->bind_param("i", $course_id);
    $delete_videos->execute();
    
    // Delete student activity
    $delete_activity = $conn->prepare("DELETE FROM student_activity WHERE course_id = ?");
    $delete_activity->bind_param("i", $course_id);
    $delete_activity->execute();
    
    // Delete enrollments
    $delete_enrollments = $conn->prepare("DELETE FROM enrollments WHERE course_id = ?");
    $delete_enrollments->bind_param("i", $course_id);
    $delete_enrollments->execute();
    
    // Delete attendance records
    $delete_attendance = $conn->prepare("DELETE FROM attendance WHERE course_id = ?");
    $delete_attendance->bind_param("i", $course_id);
    $delete_attendance->execute();
    
    // Delete certificates
    $delete_certificates = $conn->prepare("DELETE FROM certificates WHERE course_id = ?");
    $delete_certificates->bind_param("i", $course_id);
    $delete_certificates->execute();
    
    // Delete course materials
    $delete_materials = $conn->prepare("DELETE FROM course_materials WHERE course_id = ?");
    $delete_materials->bind_param("i", $course_id);
    $delete_materials->execute();
    
    // Delete course content completion records
    $delete_completion = $conn->prepare("DELETE FROM course_content_completion WHERE course_id = ?");
    $delete_completion->bind_param("i", $course_id);
    $delete_completion->execute();
    
    
    
    // Finally delete the course
    $delete_course = $conn->prepare("DELETE FROM courses WHERE id = ?");
    $delete_course->bind_param("i", $course_id);
    $delete_course->execute();
    
    $conn->commit();
    
    $_SESSION['message'] = "Course deleted successfully!";
    header("Location: teacher_dashboard.php");
    exit();
} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['error'] = "Error deleting course: " . $e->getMessage();
    header("Location: teacher_dashboard.php");
    exit();
}

// Close connection
$conn->close();
?>