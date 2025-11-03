<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include("config.php");
session_start();

// Ensure student is logged in
if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user'];

// Fetch student info
$stmt = $conn->prepare("SELECT name, profile_pic FROM students WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Handle filter parameters
$course_id = isset($_GET['course_id']) ? intval($_GET['course_id']) : 0;
$activity_type = isset($_GET['activity_type']) ? $_GET['activity_type'] : '';
$date_range = isset($_GET['date_range']) ? $_GET['date_range'] : '7days'; // Default to last 7 days

// Build the date condition based on selected range
$date_condition = '';
switch ($date_range) {
    case '7days':
        $date_condition = "AND a.start_time >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        break;
    case '30days':
        $date_condition = "AND a.start_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        break;
    case '90days':
        $date_condition = "AND a.start_time >= DATE_SUB(NOW(), INTERVAL 90 DAY)";
        break;
    case 'all':
        $date_condition = "";
        break;
    default:
        $date_condition = "AND a.start_time >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
}

// Build the course filter condition
$course_condition = ($course_id > 0) ? "AND a.course_id = $course_id" : "";

// Build the activity type filter condition
$activity_condition = '';
if ($activity_type == 'video_watch') {
    $activity_condition = "AND a.activity_type = 'video_watch'";
} elseif ($activity_type == 'quiz_attempt') {
    $activity_condition = "AND a.activity_type = 'quiz_attempt'";
}

// Fetch student's courses for filter dropdown
$courses_query = "
    SELECT c.id, c.title 
    FROM courses c
    JOIN enrollments e ON c.id = e.course_id
    WHERE e.student_id = ?
    ORDER BY c.title
";
$courses_stmt = $conn->prepare($courses_query);
$courses_stmt->bind_param("i", $user_id);
$courses_stmt->execute();
$courses_result = $courses_stmt->get_result();
$courses_stmt->close();

// Fetch summary stats for the student
$stats_query = "
    SELECT 
        COUNT(DISTINCT CASE WHEN a.activity_type = 'video_watch' THEN a.video_id END) as videos_watched,
        COUNT(DISTINCT CASE WHEN a.activity_type = 'quiz_attempt' THEN a.video_id END) as quizzes_attempted,
        SUM(CASE WHEN a.activity_type = 'video_watch' THEN a.watched_time ELSE 0 END) as total_watch_time,
        COUNT(DISTINCT a.course_id) as courses_accessed
    FROM attendance a
    WHERE a.student_id = ? $date_condition
";
$stats_stmt = $conn->prepare($stats_query);
$stats_stmt->bind_param("i", $user_id);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();
$stats_stmt->close();

// Fetch detailed attendance records
$attendance_query = "
    SELECT 
        a.id,
        a.course_id,
        c.title as course_title,
        a.video_id,
        a.activity_type,
        CASE 
            WHEN a.activity_type = 'video_watch' THEN v.video_title
            WHEN a.activity_type = 'quiz_attempt' THEN q.title
            ELSE 'Unknown'
        END as content_title,
        a.start_time,
        a.end_time,
        a.watched_time,
        a.status
    FROM attendance a
    LEFT JOIN courses c ON a.course_id = c.id
    LEFT JOIN course_videos v ON a.video_id = v.id AND a.activity_type = 'video_watch'
    LEFT JOIN quizzes q ON a.video_id = q.id AND a.activity_type = 'quiz_attempt'
    WHERE a.student_id = ? $course_condition $activity_condition $date_condition
    ORDER BY a.start_time DESC
    LIMIT 100
";

$attendance_stmt = $conn->prepare($attendance_query);
$attendance_stmt->bind_param("i", $user_id);
$attendance_stmt->execute();
$attendance_result = $attendance_stmt->get_result();
$attendance_stmt->close();

// Function to format seconds into readable time
function formatWatchTime($seconds) {
    if (!$seconds) return "0 min";
    
    $hours = floor($seconds / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    
    if ($hours > 0) {
        return "$hours hr " . ($minutes > 0 ? "$minutes min" : "");
    } else {
        return "$minutes min";
    }
}

// Helper function to get appropriate icon based on activity type
function getActivityIcon($type) {
    switch ($type) {
        case 'video_watch':
            return '<i class="fas fa-video"></i>';
        case 'quiz_attempt':
            return '<i class="fas fa-tasks"></i>';
        default:
            return '<i class="fas fa-circle"></i>';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Activity | Learning Portal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            display: flex;
            height: 100vh;
            overflow-x: hidden;
            background-color: #f5f7fa;
            color: #333;
        }

        /* Sidebar Styles */
        .sidebar {
            width: 60px; /* Initial width when collapsed */
            background: #1e293b;
            color: white;
            height: 100vh;
            padding: 20px 0;
            position: fixed;
            left: 0;
            top: 0;
            transition: width 0.3s ease-in-out;
            overflow: hidden;
            z-index: 1000;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
        }

        .sidebar.expanded {
            width: 250px; /* Width when expanded */
        }

        .profile-section {
            display: none;
            text-align: center;
            padding: 30px 20px;
            background-color: #172130;
            margin-bottom: 30px;
            position: relative;
            top: 0;
        }

        .student-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            margin: 0 auto 15px;
            overflow: hidden;
            background-color: #f5f7fa;
            border: 3px solid rgba(255, 255, 255, 0.2);
        }

        .profile-section h3 {
            margin-top: 15px;
            font-size: 20px;
            font-weight: 500;
            letter-spacing: 0.5px;
        }

        .sidebar.expanded .profile-section {
            display: block;
        }

        .student-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .sidebar ul {
            list-style: none;
            padding: 0;
            margin-top: 40px;
        }

        .sidebar ul li {
            padding: 12px 20px;
            cursor: pointer;
            transition: all 0.2s ease;
            white-space: nowrap;
        }

        .sidebar ul li:hover, .sidebar ul li.active {
            background: #2c3e50;
        }
        
        .sidebar ul li a {
            color: #ffffff;
            text-decoration: none;
            font-size: 16px;
            display: flex;
            align-items: center;
            width: 100%;
        }

        .sidebar ul li i {
            margin-right: 15px;
            width: 20px;
            text-align: center;
            font-size: 18px;
        }

        .sidebar ul li span {
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .sidebar.expanded ul li span {
            opacity: 1;
        }

        /* Toggle Button */
        .toggle-btn {
            background: transparent;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #3b82f6;
            padding: 10px 20px;
            margin: 0;
            display: block;
            position: absolute;
            top: 10px;
            left: 0;
            z-index: 10;
        }

        /* Main Content */
        .dashboard-content {
            flex-grow: 1;
            padding: 40px;
            margin-left: 60px; /* Initial margin */
            transition: margin-left 0.3s ease-in-out;
            width: calc(100% - 60px);
            overflow-y: auto;
        }

        .sidebar.expanded + .dashboard-content {
            margin-left: 250px;
            width: calc(100% - 250px);
        }

        .dashboard-header {
            margin-bottom: 40px;
        }
        
        .dashboard-header h2 {
            color: #333;
            margin-bottom: 10px;
            font-size: 32px;
            font-weight: 600;
        }
        
        .dashboard-header p {
            color: #6b7280;
            font-size: 16px;
            margin-top: 5px;
        }

        /* Stats Cards */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: #ffffff;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            display: flex;
            flex-direction: column;
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1);
        }

        .stat-card .stat-icon {
            margin-bottom: 15px;
            font-size: 28px;
            color: #3b82f6;
        }

        .stat-card h3 {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 5px;
            color: #1e293b;
        }

        .stat-card p {
            color: #6b7280;
            font-size: 14px;
        }

        /* Filter Controls */
        .filter-controls {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 30px;
            background: #ffffff;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            flex: 1;
            min-width: 200px;
        }

        .filter-group label {
            margin-bottom: 8px;
            font-weight: 500;
            color: #4b5563;
        }

        .filter-group select, .filter-group input {
            padding: 10px 15px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            font-size: 15px;
            color: #1f2937;
        }

        .filter-group select:focus, .filter-group input:focus {
            outline: none;
            border-color: #3b82f6;
        }

        .filter-controls button {
            align-self: flex-end;
            background: #3b82f6;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.2s;
            margin-top: 24px;
        }

        .filter-controls button:hover {
            background: #2563eb;
        }

        /* Activity Table */
        .section-title {
            font-size: 22px;
            font-weight: 600;
            margin-bottom: 20px;
            color: #1e293b;
        }

        .activity-table {
            width: 100%;
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            overflow: hidden;
            border-collapse: collapse;
        }

        .activity-table th {
            background: #f8fafc;
            padding: 15px 20px;
            text-align: left;
            font-weight: 600;
            color: #4b5563;
            border-bottom: 1px solid #e5e7eb;
        }

        .activity-table td {
            padding: 15px 20px;
            border-bottom: 1px solid #e5e7eb;
            color: #1f2937;
        }

        .activity-table tr:last-child td {
            border-bottom: none;
        }

        .activity-table tr:hover {
            background: #f9fafb;
        }

        .activity-type {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .activity-icon {
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #edf2f7;
            border-radius: 50%;
            color: #3b82f6;
        }

        .badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 500;
        }

        .badge-blue {
            background: #e6f2ff;
            color: #1e88e5;
        }

        .badge-green {
            background: #e6fff2;
            color: #06b6d4;
        }

        .logout-button {
            display: flex;
            padding: 12px 20px;
            align-items: center;
            color: #ffffff;
            text-decoration: none;
            transition: all 0.2s ease;
            position: absolute;
            bottom: 20px;
            width: 100%;
        }

        .logout-button:hover {
            background: #2c3e50;
        }

        .logout-button i {
            margin-right: 15px;
            width: 20px;
            text-align: center;
            font-size: 18px;
        }

        .logout-button span {
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .sidebar.expanded .logout-button span {
            opacity: 1;
        }

        .no-records {
            text-align: center;
            padding: 40px 20px;
            color: #6b7280;
            font-size: 16px;
        }

        @media (max-width: 768px) {
            .stats-container {
                grid-template-columns: 1fr;
            }

            .filter-controls {
                flex-direction: column;
            }

            .dashboard-content {
                padding: 20px;
            }

            .activity-table {
                display: block;
                overflow-x: auto;
            }
        }
    </style>
</head>
<body>

<div class="sidebar" id="sidebar">
    <button class="toggle-btn" onclick="toggleSidebar()">☰</button>
    <div class="profile-section">
        <div class="student-avatar">
            <img src="uploads/<?php echo $user['profile_pic'] ?: 'default.png'; ?>" alt="Profile Picture">
        </div>
        <h3><?php echo htmlspecialchars($user['name']); ?></h3>
    </div>
    <ul>
        <li><a href="index.php"><i class="fas fa-home"></i> <span>Dashboard</span></a></li>
        <li><a href="course.php"><i class="fas fa-book"></i> <span>Courses</span></a></li>
        <li class="active"><a href="view_attendance.php"><i class="fas fa-chart-line"></i> <span>My Activity</span></a></li>
        <li><a href="my_course.php"><i class="fas fa-graduation-cap"></i> <span>My Courses</span></a></li>
        <li><a href="update_profile.php"><i class="fas fa-user-edit"></i> <span>Update Profile</span></a></li>
    </ul>
    <a href="logout.php" class="logout-button"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a>
</div>

<div class="dashboard-content" id="dashboardContent">
    <div class="dashboard-header">
        <h2>My Learning Activity</h2>
        <p>Track your progress across all courses</p>
    </div>

    <!-- Stats Summary Cards -->
    <div class="stats-container">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-video"></i></div>
            <h3><?php echo $stats['videos_watched'] ?: '0'; ?></h3>
            <p>Videos Watched</p>
        </div>
    
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-clock"></i></div>
            <h3><?php echo formatWatchTime($stats['total_watch_time'] ?: 0); ?></h3>
            <p>Total Watch Time</p>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-book-open"></i></div>
            <h3><?php echo $stats['courses_accessed'] ?: '0'; ?></h3>
            <p>Courses Accessed</p>
        </div>
    </div>

    <!-- Filter Controls -->
    <form class="filter-controls" action="" method="GET">
        <div class="filter-group">
            <label for="course_id">Course</label>
            <select name="course_id" id="course_id">
                <option value="0">All </option>
                <?php while ($course = $courses_result->fetch_assoc()): ?>
                    <option value="<?php echo $course['id']; ?>" <?php echo ($course_id == $course['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($course['title']); ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
        <!--
        <div class="filter-group">
            
            <label for="activity_type">Activity Type</label>
            <select name="activity_type" id="activity_type">
                <option value="" <?php echo ($activity_type == '') ? 'selected' : ''; ?>>All Activities</option>
                <option value="video_watch" <?php echo ($activity_type == 'video_watch') ? 'selected' : ''; ?>>Videos Only</option>
                <option value="quiz_attempt" <?php echo ($activity_type == 'quiz_attempt') ? 'selected' : ''; ?>>Quizzes Only</option>
            </select>
                
        </div>
        -->
        <div class="filter-group">
            <label for="date_range">Time Period</label>
            <select name="date_range" id="date_range">
                <option value="7days" <?php echo ($date_range == '7days') ? 'selected' : ''; ?>>Last 7 Days</option>
                <option value="30days" <?php echo ($date_range == '30days') ? 'selected' : ''; ?>>Last 30 Days</option>
                <option value="90days" <?php echo ($date_range == '90days') ? 'selected' : ''; ?>>Last 90 Days</option>
                <option value="all" <?php echo ($date_range == 'all') ? 'selected' : ''; ?>>All Time</option>
            </select>
        </div>
        <button type="submit">Apply Filters</button>
    </form>

    <!-- Activity Records Table -->
    <h3 class="section-title">Activity History</h3>
    <?php if ($attendance_result->num_rows > 0): ?>
        <table class="activity-table">
            <thead>
                <tr>
                    <th>Activity</th>
                    <th>Course</th>
                    <th>Started</th>
                    <th>Duration</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($record = $attendance_result->fetch_assoc()): ?>
                    <tr>
                        <td>
                            <div class="activity-type">
                                <div class="activity-icon">
                                    <?php echo getActivityIcon($record['activity_type']); ?>
                                </div>
                                <div>
                                    <?php echo htmlspecialchars($record['content_title']); ?>
                                    <div style="font-size: 13px; color: #6b7280;">
                                        <?php echo ($record['activity_type'] == 'video_watch') ? 'Video' : 'Quiz'; ?>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td><?php echo htmlspecialchars($record['course_title']); ?></td>
                        <td>
                            <?php 
                                $start_time = new DateTime($record['start_time']);
                                echo $start_time->format('M j, Y • g:i A'); 
                            ?>
                        </td>
                        <td>
                            <?php 
                                if ($record['end_time']) {
                                    $start = new DateTime($record['start_time']);
                                    $end = new DateTime($record['end_time']);
                                    $interval = $start->diff($end);
                                    
                                    if ($interval->h > 0) {
                                        echo $interval->format('%h hr %i min');
                                    } else {
                                        echo $interval->format('%i min %s sec');
                                    }
                                } else {
                                    echo "In progress";
                                }
                            ?>
                        </td>
                        <td>
                            <?php if ($record['activity_type'] == 'video_watch'): ?>
                                <span class="badge badge-blue">
                                    <?php echo formatWatchTime($record['watched_time']); ?> watched
                                </span>
                            <?php else: ?>
                                <span class="badge badge-green">
                                    <?php echo $record['status']; ?>
                                </span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    <?php else: ?>
        <div class="no-records">
            <i class="fas fa-search" style="font-size: 48px; color: #d1d5db; margin-bottom: 20px;"></i>
            <h3>No activity records found</h3>
            <p>We couldn't find any activity matching your filters. Try changing your filter settings or start engaging with course content.</p>
        </div>
    <?php endif; ?>
</div>

<script>
    function toggleSidebar() {
        let sidebar = document.getElementById("sidebar");
        sidebar.classList.toggle("expanded");
    }
</script>

</body>
</html>