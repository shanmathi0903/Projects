<?php
include("config.php");
session_start();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Create a log function
function logError($message) {
    error_log(date('Y-m-d H:i:s') . " - " . $message . "\n", 3, "progress_errors.log");
}

// Validate the request
if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'student' || !isset($_POST['video_id'])) {
    logError("Invalid request: " . json_encode($_POST));
    echo json_encode(['success' => false, 'message' => 'Invalid request parameters']);
    exit();
}

$user_id = $_SESSION['user'];
$video_id = intval($_POST['video_id']);
$course_id = isset($_POST['course_id']) ? intval($_POST['course_id']) : 0;

try {
    // Verify the video exists and belongs to a course the student is enrolled in
    $verify_query = $conn->prepare("
        SELECT cv.id, cv.course_id, cv.video_sequence
        FROM course_videos cv
        JOIN enrollments e ON cv.course_id = e.course_id
        WHERE cv.id = ? AND e.student_id = ?
    ");
    
    if (!$verify_query) {
        throw new Exception("Database prepare error: " . $conn->error);
    }
    
    $verify_query->bind_param("ii", $video_id, $user_id);
    
    if (!$verify_query->execute()) {
        throw new Exception("Execute error on verify query: " . $verify_query->error);
    }
    
    $result = $verify_query->get_result();
    
    if ($result->num_rows === 0) {
        logError("Video not found or not enrolled: video_id=$video_id, user_id=$user_id");
        echo json_encode(['success' => false, 'message' => 'Video not found or not enrolled']);
        exit();
    }
    
    $video_data = $result->fetch_assoc();
    $course_id = $video_data['course_id']; // Use the actual course_id from the database
    $video_sequence = $video_data['video_sequence'];
    
    // Begin transaction to ensure database consistency
    $conn->begin_transaction();
    
    // Check if a progress record already exists
    $check_query = $conn->prepare("
        SELECT id FROM video_progress 
        WHERE student_id = ? AND video_id = ?
    ");
    
    if (!$check_query) {
        throw new Exception("Database prepare error: " . $conn->error);
    }
    
    $check_query->bind_param("ii", $user_id, $video_id);
    
    if (!$check_query->execute()) {
        throw new Exception("Execute error on check query: " . $check_query->error);
    }
    
    $progress_result = $check_query->get_result();
    $success = false;
    
    if ($progress_result->num_rows > 0) {
        // Update existing record
        $update_query = $conn->prepare("
            UPDATE video_progress 
            SET is_completed = 1 
            WHERE student_id = ? AND video_id = ?
        ");
        
        if (!$update_query) {
            throw new Exception("Database prepare error: " . $conn->error);
        }
        
        $update_query->bind_param("ii", $user_id, $video_id);
        $success = $update_query->execute();
        
        if (!$success) {
            throw new Exception("Execute error on update query: " . $update_query->error);
        }
    } else {
        // Create new record
        $is_completed = 1;
        
        $insert_query = $conn->prepare("
            INSERT INTO video_progress 
            (student_id, course_id, video_id, video_sequence, is_completed, is_unlocked) 
            VALUES (?, ?, ?, ?, ?, 0)
        ");
        
        if (!$insert_query) {
            throw new Exception("Database prepare error: " . $conn->error);
        }
        
        $insert_query->bind_param("iiiii", $user_id, $course_id, $video_id, $video_sequence, $is_completed);
        $success = $insert_query->execute();
        
        if (!$success) {
            throw new Exception("Execute error on insert query: " . $insert_query->error);
        }
    }
    
    // If current video marked as completed successfully, unlock the next video
    if ($success) {
        $next_sequence = $video_sequence + 1;
        
        // Get the next video
        $next_video_query = $conn->prepare("
            SELECT id FROM course_videos 
            WHERE course_id = ? AND video_sequence = ?
        ");
        
        if (!$next_video_query) {
            throw new Exception("Database prepare error: " . $conn->error);
        }
        
        $next_video_query->bind_param("ii", $course_id, $next_sequence);
        
        if (!$next_video_query->execute()) {
            throw new Exception("Execute error on next video query: " . $next_video_query->error);
        }
        
        $next_video_result = $next_video_query->get_result();
        
        if ($next_video_result->num_rows > 0) {
            $next_video = $next_video_result->fetch_assoc();
            $next_video_id = $next_video['id'];
            
            // Check if this is a rewatch after reset
            $reset_check = $conn->prepare("
                SELECT 1 FROM quiz_resets 
                WHERE student_id = ? AND quiz_id IN (
                    SELECT id FROM quizzes WHERE course_id = ?
                )
                ORDER BY reset_date DESC LIMIT 1
            ");
            $reset_check->bind_param("ii", $user_id, $course_id);
            $reset_check->execute();
            $is_rewatch = $reset_check->get_result()->num_rows > 0;
            
            // Only unlock next video if current video is completed and either:
            // 1. This is not a rewatch, or
            // 2. All previous videos are completed in the rewatch
            if (!$is_rewatch || (
                $is_rewatch && isAllPreviousVideosCompleted($conn, $user_id, $course_id, $video_sequence)
            )) {
                // Create or update next video progress
                $next_progress_query = $conn->prepare("
                    INSERT INTO video_progress 
                    (student_id, course_id, video_id, video_sequence, is_completed, is_unlocked)
                    VALUES (?, ?, ?, ?, 0, 1)
                    ON DUPLICATE KEY UPDATE is_unlocked = 1
                ");
                $next_progress_query->bind_param("iiii", $user_id, $course_id, $next_video_id, $next_sequence);
                $next_progress_query->execute();
            }
        }
        
        // Check if all videos are completed for this course
        $all_completed_query = $conn->prepare("
            SELECT 
                (SELECT COUNT(*) FROM course_videos WHERE course_id = ?) AS total_videos,
                (SELECT COUNT(*) FROM video_progress WHERE course_id = ? AND student_id = ? AND is_completed = 1) AS completed_videos
        ");
        
        if (!$all_completed_query) {
            throw new Exception("Database prepare error: " . $conn->error);
        }
        
        $all_completed_query->bind_param("iii", $course_id, $course_id, $user_id);
        
        if (!$all_completed_query->execute()) {
            throw new Exception("Execute error on all completed query: " . $all_completed_query->error);
        }
        
        $completion_result = $all_completed_query->get_result();
        $completion_data = $completion_result->fetch_assoc();
        
        $all_completed = ($completion_data['total_videos'] > 0 && 
                         $completion_data['total_videos'] == $completion_data['completed_videos']);
        
        // Commit the transaction
        $conn->commit();
        
        echo json_encode([
            'success' => true, 
            'all_completed' => $all_completed,
            'message' => 'Progress updated successfully'
        ]);
    } else {
        throw new Exception("Failed to update progress");
    }
    
} catch (Exception $e) {
    // Rollback the transaction in case of error
    $conn->rollback();
    
    logError("Error in update_progress.php: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}

// Helper function to check if all previous videos are completed
function isAllPreviousVideosCompleted($conn, $user_id, $course_id, $current_sequence) {
    $check_query = $conn->prepare("
        SELECT COUNT(*) as total,
               SUM(CASE WHEN vp.is_completed = 1 THEN 1 ELSE 0 END) as completed
        FROM course_videos cv
        LEFT JOIN video_progress vp ON cv.id = vp.video_id AND vp.student_id = ?
        WHERE cv.course_id = ? AND cv.video_sequence < ?
    ");
    $check_query->bind_param("iii", $user_id, $course_id, $current_sequence);
    $check_query->execute();
    $result = $check_query->get_result()->fetch_assoc();
    
    return $result['total'] > 0 && $result['total'] == $result['completed'];
}
?>