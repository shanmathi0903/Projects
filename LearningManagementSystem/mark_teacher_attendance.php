<?php
include("config.php");
session_start();

// Set timezone to Indian Standard Time
date_default_timezone_set('Asia/Kolkata');

// Ensure only admin or teachers can access this page
if (!isset($_SESSION['user']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'teacher')) {
    header("Location: login.php");
    exit();
}

// Get current user's ID and role
$current_user_id = $_SESSION['user'];
$current_user_role = $_SESSION['role'];

// Get filter parameters
$filter_student = isset($_GET['student_id']) ? intval($_GET['student_id']) : 0;
$filter_course = isset($_GET['course_id']) ? intval($_GET['course_id']) : 0;
$filter_date_start = isset($_GET['date_start']) ? $_GET['date_start'] : '';
$filter_date_end = isset($_GET['date_end']) ? $_GET['date_end'] : '';

// Base query
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
";

// If user is a teacher, restrict to only their students through courses they teach
$params = [];
$param_types = "";

if ($current_user_role === 'teacher') {
    // Show only students enrolled in courses that this teacher teaches
    $query .= " WHERE c.teacher_id = ?";
    $params[] = $current_user_id;
    $param_types .= "i";
} else {
    $query .= " WHERE 1=1";
}

// Apply filters
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

// Order by
$query .= " ORDER BY a.start_time DESC";

// Prepare and execute the query
$stmt = $conn->prepare($query);

if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

// Get students for filter dropdown - restricted to students enrolled in teacher's courses
if ($current_user_role === 'teacher') {
    $students_query = "
        SELECT DISTINCT s.id, s.name 
        FROM students s
        JOIN enrollments e ON s.id = e.student_id
        JOIN courses c ON e.course_id = c.id
        WHERE c.teacher_id = ?
        ORDER BY s.name
    ";
    $stmt_students = $conn->prepare($students_query);
    $stmt_students->bind_param("i", $current_user_id);
    $stmt_students->execute();
    $students_result = $stmt_students->get_result();
} else {
    $students_query = "SELECT id, name FROM students ORDER BY name";
    $students_result = $conn->query($students_query);
}

// Get courses for filter dropdown - limited to courses taught by teacher
if ($current_user_role === 'teacher') {
    $courses_query = "
        SELECT id, title 
        FROM courses
        WHERE teacher_id = ?
        ORDER BY title
    ";
    $stmt_courses = $conn->prepare($courses_query);
    $stmt_courses->bind_param("i", $current_user_id);
    $stmt_courses->execute();
    $courses_result = $stmt_courses->get_result();
} else {
    $courses_query = "SELECT id, title FROM courses ORDER BY title";
    $courses_result = $conn->query($courses_query);
}

// Calculate summary statistics
$total_records = $result->num_rows;

// Reset result pointer
$result->data_seek(0);

// Initialize counters
$total_video_time = 0;
$total_quiz_time = 0;
$video_count = 0;
$quiz_count = 0;
$unique_students = [];
$unique_courses = [];

while ($row = $result->fetch_assoc()) {
    $unique_students[$row['student_id']] = true;
    $unique_courses[$row['course_id']] = true;
    
    if ($row['activity_type'] == 'video_watch') {
        if (isset($row['watched_time']) && is_numeric($row['watched_time'])) {
            $total_video_time += $row['watched_time'];
        }
        $video_count++;
    } else if ($row['activity_type'] == 'quiz_attempt') {
        if (isset($row['watched_time']) && is_numeric($row['watched_time'])) {
            $total_quiz_time += $row['watched_time'];
        }
        $quiz_count++;
    }
}

// Reset result pointer again
$result->data_seek(0);

// Format time for display
function formatTimeFromSeconds($seconds) {
    $hours = floor($seconds / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    $remainingSeconds = $seconds % 60;
    
    $timeString = '';
    if ($hours > 0) {
        $timeString .= $hours . ' hour' . ($hours != 1 ? 's' : '') . ' ';
    }
    if ($minutes > 0 || $hours > 0) {
        $timeString .= $minutes . ' minute' . ($minutes != 1 ? 's' : '') . ' ';
    }
    $timeString .= $remainingSeconds . ' second' . ($remainingSeconds != 1 ? 's' : '');
    
    return $timeString;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Attendance Report</title>
    <link rel="stylesheet" href="assets/admin.css">
    <style>
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        .filter-form {
            background-color: #f5f5f5;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        .filter-form select, .filter-form input {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .filter-form button {
            padding: 8px 16px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .filter-form button.reset {
            background-color: #f44336;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 12px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
            position: sticky;
            top: 0;
        }
        tr:hover {
            background-color: #f5f5f5;
        }
        .activity-video {
            color: #2196F3;
        }
        .activity-quiz {
            color: #FF9800;
        }
        .status-present {
            color: #4CAF50;
            font-weight: bold;
        }
        .export-btn {
            display: inline-block;
            margin-top: 20px;
            padding: 10px 20px;
            background-color: #3f51b5;
            color: white;
            text-decoration: none;
            border-radius: 4px;
        }
        .back-btn {
    display: inline-block;
    margin-bottom: 15px;
    color: #333;
    text-decoration: none;
    font-weight: bold;
    padding: 5px 10px;
    border-radius: 4px;
    background-color: #f1f1f1;
    transition: background-color 0.3s;
}

.back-btn:hover {
    background-color: #ddd;
}

.navigation-bar {
    margin-bottom: 20px;
}
        </style>
</head>
<body>
<div class="navigation-bar">
    <a href="teacher_dashboard.php" class="back-btn">← Back to Dashboard</a>   
</div>
    <div class="container">
        <h1>Student Attendance Report
            <?php if ($current_user_role === 'teacher'): ?>
            <?php endif; ?>
        </h1>
        
        <!-- Filter Form -->
        <form class="filter-form" method="GET">
            <div>
                <label for="student_id">Student:</label>
                <select name="student_id" id="student_id">
                    <option value="0">All Students</option>
                    <?php while ($student = $students_result->fetch_assoc()): ?>
                        <option value="<?= $student['id'] ?>" <?= $filter_student == $student['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($student['name']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            
            <div>
                <label for="course_id">Course:</label>
                <select name="course_id" id="course_id">
                    <option value="0">All Courses</option>
                    <?php while ($course = $courses_result->fetch_assoc()): ?>
                        <option value="<?= $course['id'] ?>" <?= $filter_course == $course['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($course['title']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            
            <div>
                <label for="date_start">From:</label>
                <input type="date" name="date_start" id="date_start" value="<?= htmlspecialchars($filter_date_start) ?>">
            </div>
            
            <div>
                <label for="date_end">To:</label>
                <input type="date" name="date_end" id="date_end" value="<?= htmlspecialchars($filter_date_end) ?>">
            </div>
            
            <div>
                <button type="submit">Apply Filters</button>
                <button type="button" class="reset" onclick="window.location.href='mark_teacher_attendance.php'">Reset</button>
            </div>
        </form>
        
        <!-- Export Button -->
        <a href="export_attendance.php<?= 
            (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '') 
        ?>" class="export-btn">Export to CSV</a>
        
        <!-- Results Table -->
        <table>
            <thead>
                <tr>
                    <th>Student</th>
                    <th>Course</th>
                    <th>Activity</th>
                    <th>Title</th>
                    <th>Started </th>
                    <th>Ended </th>
                    <th>Duration</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['student_name']) ?></td>
                            <td><?= htmlspecialchars($row['course_title']) ?></td>
                            <td class="<?= $row['activity_type'] == 'video_watch' ? 'activity-video' : 'activity-quiz' ?>">
                                <?= ucfirst(str_replace('_', ' ', $row['activity_type'])) ?>
                            </td>
                            <td><?= htmlspecialchars($row['activity_name']) ?></td>
                            <td><?= date('Y-m-d h:i:s A', strtotime($row['start_time'])) ?></td>
                            <td>
                                <?= $row['end_time'] ? date('Y-m-d h:i:s A', strtotime($row['end_time'])) : 'In Progress' ?>
                            </td>
                            <td>
                                <?php if ($row['watched_time'] && $row['end_time']): ?>
                                    <?= formatTimeFromSeconds($row['watched_time']) ?>
                                <?php elseif ($row['end_time']): ?>
                                    <?php
                                        $start = new DateTime($row['start_time']);
                                        $end = new DateTime($row['end_time']);
                                        $diff = $start->diff($end);
                                        echo $diff->format('%H:%I:%S');
                                    ?>
                                <?php else: ?>
                                    In Progress
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" style="text-align: center;">No attendance records found</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>