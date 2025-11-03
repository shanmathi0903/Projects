<?php
include("config.php");
session_start();

if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user'];
$course_id = $_POST['course_id'];

// Check if already enrolled
$check_stmt = $conn->prepare("SELECT * FROM enrollments WHERE student_id = ? AND course_id = ?");
$check_stmt->bind_param("ii", $user_id, $course_id);
$check_stmt->execute();
$result = $check_stmt->get_result();

if ($result->num_rows > 0) {
    echo "<script>alert('Already Enrolled!'); window.location.href='view_course.php';</script>";
    exit();
}

// Count current active enrollments (not completed)
// Assuming a course is completed when progress reaches 100
$count_stmt = $conn->prepare("SELECT COUNT(*) AS active_count FROM enrollments WHERE student_id = ? AND progress < 100");
$count_stmt->bind_param("i", $user_id);
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$active_count = $count_result->fetch_assoc()['active_count'];

if ($active_count >= 3) {
    echo "<script>alert('You can only enroll in 3 courses at a time. Please complete an existing course before enrolling in a new one.'); window.location.href='view_course.php';</script>";
    exit();
}

// Enroll the student
$enroll_stmt = $conn->prepare("INSERT INTO enrollments (student_id, course_id, progress, failed_attempts, requires_rewatch) VALUES (?, ?, 0, 0, 0)");
$enroll_stmt->bind_param("ii", $user_id, $course_id);

if ($enroll_stmt->execute()) {
    echo "<script>alert('Enrollment Successful!'); window.location.href='my_course.php';</script>";
} else {
    echo "<script>alert('Enrollment Failed!'); window.location.href='view_course.php';</script>";
}
?>