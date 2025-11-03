<?php
include("config.php");
session_start();

if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'student') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['user'];
$video_id = $_POST['video_id'];
$course_id = $_POST['course_id'];

// Start a transaction for atomicity
$conn->begin_transaction();

try {
    // First, verify the video belongs to the course
    $verify_stmt = $conn->prepare("SELECT video_sequence FROM course_videos WHERE id = ? AND course_id = ?");
    $verify_stmt->bind_param("ii", $video_id, $course_id);
    $verify_stmt->execute();
    $verify_result = $verify_stmt->get_result();

    if ($verify_result->num_rows === 0) {
        throw new Exception("Invalid video or course");
    }

    $video_info = $verify_result->fetch_assoc();
    $current_sequence = $video_info['video_sequence'];

    // Mark current video as completed
    $complete_stmt = $conn->prepare("UPDATE video_progress SET is_completed = TRUE WHERE student_id = ? AND video_id = ? AND course_id = ?");
    $complete_stmt->bind_param("iii", $user_id, $video_id, $course_id);
    $complete_stmt->execute();

    // Find and unlock the next video in sequence
    $next_sequence = $current_sequence + 1;
    $next_video_stmt = $conn->prepare("
        SELECT id FROM course_videos 
        WHERE course_id = ? AND video_sequence = ?
    ");
    $next_video_stmt->bind_param("ii", $course_id, $next_sequence);
    $next_video_stmt->execute();
    $next_video_result = $next_video_stmt->get_result();

    if ($next_video_result->num_rows > 0) {
        $next_video = $next_video_result->fetch_assoc();
        $next_video_id = $next_video['id'];

        // Unlock the next video
        $unlock_stmt = $conn->prepare("
            UPDATE video_progress 
            SET is_unlocked = TRUE 
            WHERE student_id = ? AND course_id = ? AND video_id = ?
        ");
        $unlock_stmt->bind_param("iii", $user_id, $course_id, $next_video_id);
        $unlock_stmt->execute();
    }

    // Commit the transaction
    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Video completed and next video unlocked',
        'next_sequence' => $next_sequence
    ]);

} catch (Exception $e) {
    // Rollback the transaction
    $conn->rollback();

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

$conn->close();
?>