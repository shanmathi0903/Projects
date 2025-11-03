<?php
include("config.php");
session_start();

// Set timezone to Indian Standard Time
date_default_timezone_set('Asia/Kolkata');

// Ensure it's an AJAX request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

// Validate that the user is logged in and is a student
if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'student') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

$student_id = $_SESSION['user'];

// Validate that we have the required parameters
if (!isset($_POST['activity_type']) || empty($_POST['activity_type']) ||
    !isset($_POST['course_id']) || !is_numeric($_POST['course_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit();
}

// Get common parameters
$activity_type = $_POST['activity_type']; // Either 'video_watch' or 'quiz_attempt'
$course_id = intval($_POST['course_id']);
$action = $_POST['action'] ?? ''; // 'start' or 'end'
$timestamp = date('Y-m-d H:i:s');

// Handle video watching
if ($activity_type === 'video_watch') {
    if (!isset($_POST['video_id']) || !is_numeric($_POST['video_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing video_id parameter']);
        exit();
    }
    
    $video_id = intval($_POST['video_id']);
    
    if ($action === 'start') {
        // Check if there's already an entry for this video (active or not)
        $check_stmt = $conn->prepare("
            SELECT id, end_time FROM attendance 
            WHERE student_id = ? AND course_id = ? AND video_id = ? AND activity_type = 'video_watch'
            ORDER BY id DESC LIMIT 1
        ");
        $check_stmt->bind_param("iii", $student_id, $course_id, $video_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            // An entry exists for this video
            $existing_entry = $check_result->fetch_assoc();
            
            if ($existing_entry['end_time'] === null) {
                // Session is still active, don't create a new one
                echo json_encode(['success' => true, 'message' => 'Session already active']);
            } else {
                // Update existing entry to mark new start
                $update_stmt = $conn->prepare("
                    UPDATE attendance 
                    SET start_time = ?, end_time = NULL, watched_time = NULL
                    WHERE id = ?
                ");
                $update_stmt->bind_param("si", $timestamp, $existing_entry['id']);
                
                if ($update_stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Attendance record updated']);
                } else {
                    http_response_code(500);
                    echo json_encode(['success' => false, 'message' => 'Database error: ' . $update_stmt->error]);
                }
                $update_stmt->close();
            }
        } else {
            // No existing entry, create a new one
            $stmt = $conn->prepare("
                INSERT INTO attendance 
                (student_id, course_id, video_id, status, activity_type, start_time) 
                VALUES (?, ?, ?, 'Present', 'video_watch', ?)
            ");
            $stmt->bind_param("iiis", $student_id, $course_id, $video_id, $timestamp);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Attendance record started']);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Database error: ' . $stmt->error]);
            }
            $stmt->close();
        }
        $check_stmt->close();
        exit();
    } 
    elseif ($action === 'end') {
        if (!isset($_POST['watched_time']) || !is_numeric($_POST['watched_time'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Missing watched_time parameter']);
            exit();
        }
        
        $watched_time = intval($_POST['watched_time']); // Time in seconds
        
        // Find the most recent attendance record for this video
        $find_stmt = $conn->prepare("
            SELECT id FROM attendance 
            WHERE student_id = ? AND course_id = ? AND video_id = ? AND activity_type = 'video_watch'
            ORDER BY id DESC LIMIT 1
        ");
        $find_stmt->bind_param("iii", $student_id, $course_id, $video_id);
        $find_stmt->execute();
        $result = $find_stmt->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $attendance_id = $row['id'];
            
            // Update the existing record with end time and watched time
            $stmt = $conn->prepare("
                UPDATE attendance 
                SET end_time = ?, watched_time = ? 
                WHERE id = ?
            ");
            $stmt->bind_param("sii", $timestamp, $watched_time, $attendance_id);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Attendance record updated']);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Database error: ' . $stmt->error]);
            }
            $stmt->close();
        } else {
            // Should not happen with the modified logic, but just in case
            $stmt = $conn->prepare("
                INSERT INTO attendance 
                (student_id, course_id, video_id, status, watched_time, activity_type, start_time, end_time) 
                VALUES (?, ?, ?, 'Present', ?, 'video_watch', ?, ?)
            ");
            $earlier_time = date('Y-m-d H:i:s', strtotime($timestamp) - $watched_time);
            $stmt->bind_param("iiiiss", $student_id, $course_id, $video_id, $watched_time, $earlier_time, $timestamp);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Attendance record created']);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Database error: ' . $stmt->error]);
            }
            $stmt->close();
        }
        $find_stmt->close();
        exit();
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action parameter']);
        exit();
    }
}
// Handle quiz attempts
elseif ($activity_type === 'quiz_attempt') {
    if (!isset($_POST['quiz_id']) || !is_numeric($_POST['quiz_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing quiz_id parameter']);
        exit();
    }
    
    $quiz_id = intval($_POST['quiz_id']);
    
    if ($action === 'start') {
        // Check if there's already an entry for this quiz (active or not)
        $check_stmt = $conn->prepare("
            SELECT id, end_time FROM attendance 
            WHERE student_id = ? AND course_id = ? AND video_id = ? AND activity_type = 'quiz_attempt'
            ORDER BY id DESC LIMIT 1
        ");
        $check_stmt->bind_param("iii", $student_id, $course_id, $quiz_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            // An entry exists for this quiz
            $existing_entry = $check_result->fetch_assoc();
            
            if ($existing_entry['end_time'] === null) {
                // Session is still active, don't create a new one
                echo json_encode(['success' => true, 'message' => 'Quiz session already active']);
            } else {
                // Update existing entry to mark new start
                $update_stmt = $conn->prepare("
                    UPDATE attendance 
                    SET start_time = ?, end_time = NULL
                    WHERE id = ?
                ");
                $update_stmt->bind_param("si", $timestamp, $existing_entry['id']);
                
                if ($update_stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Quiz attendance record updated']);
                } else {
                    http_response_code(500);
                    echo json_encode(['success' => false, 'message' => 'Database error: ' . $update_stmt->error]);
                }
                $update_stmt->close();
            }
        } else {
            // No existing entry, create a new one
            $stmt = $conn->prepare("
                INSERT INTO attendance 
                (student_id, course_id, video_id, status, activity_type, start_time) 
                VALUES (?, ?, ?, 'Present', 'quiz_attempt', ?)
            ");
            $stmt->bind_param("iiis", $student_id, $course_id, $quiz_id, $timestamp);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Quiz attendance started']);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Database error: ' . $stmt->error]);
            }
            $stmt->close();
        }
        $check_stmt->close();
        exit();
    } 
    elseif ($action === 'end') {
        // Find the most recent attendance record for this quiz
        $find_stmt = $conn->prepare("
            SELECT id FROM attendance 
            WHERE student_id = ? AND course_id = ? AND video_id = ? AND activity_type = 'quiz_attempt'
            ORDER BY id DESC LIMIT 1
        ");
        $find_stmt->bind_param("iii", $student_id, $course_id, $quiz_id);
        $find_stmt->execute();
        $result = $find_stmt->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $attendance_id = $row['id'];
            
            // Update the existing record with end time
            $stmt = $conn->prepare("
                UPDATE attendance 
                SET end_time = ? 
                WHERE id = ?
            ");
            $stmt->bind_param("si", $timestamp, $attendance_id);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Quiz attendance record updated']);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Database error: ' . $stmt->error]);
            }
            $stmt->close();
        } else {
            // Should not happen with the modified logic, but just in case
            echo json_encode(['success' => true, 'message' => 'No matching quiz record found, skipping']);
        }
        $find_stmt->close();
        exit();
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action parameter']);
        exit();
    }
} else {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid activity_type parameter']);
    exit();
}

$conn->close();
?>