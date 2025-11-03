<?php 
session_start(); 
include 'config.php';  

// Check if the user is logged in and is an admin
if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit(); 
}

$admin_id = $_SESSION['user'];

// Check if teacher ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: manage_users.php");
    exit();
}

$teacher_id = $_GET['id'];

// Get teacher information
$teacher_query = "
    SELECT 
        id, 
        name, 
        email,
        (SELECT COUNT(*) FROM courses WHERE teacher_id = teachers.id) AS course_count,
        (SELECT COUNT(*) FROM enrollments e JOIN courses c ON e.course_id = c.id WHERE c.teacher_id = teachers.id) AS student_count,
        (SELECT COUNT(*) FROM certificates cert JOIN courses c ON cert.course_id = c.id WHERE c.teacher_id = teachers.id) AS completion_count
    FROM 
        teachers 
    WHERE 
        id = ?
";

$teacher_stmt = $conn->prepare($teacher_query);
$teacher_stmt->bind_param("i", $teacher_id);
$teacher_stmt->execute();
$teacher_result = $teacher_stmt->get_result();

if ($teacher_result->num_rows === 0) {
    header("Location: manage_users.php");
    exit();
}

$teacher = $teacher_result->fetch_assoc();

// Get all courses by this teacher (remove created_at since it doesn't exist)
$courses_query = "
    SELECT 
        c.id,
        c.title,
        c.description,
        (SELECT COUNT(*) FROM enrollments WHERE course_id = c.id) AS enrolled_count,
        (SELECT COUNT(*) FROM certificates WHERE course_id = c.id) AS completion_count,
        (SELECT COUNT(*) FROM course_videos WHERE course_id = c.id) AS video_count
    FROM 
        courses c
    WHERE 
        c.teacher_id = ?
    ORDER BY 
        c.title
";

$courses_stmt = $conn->prepare($courses_query);
$courses_stmt->bind_param("i", $teacher_id);
$courses_stmt->execute();
$courses_result = $courses_stmt->get_result();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Teacher Details - Admin Dashboard</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 0;
            background-color: #f5f7fa;
            color: #333;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        header {
            background-color: #2c3e50;
            color: white;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        h1, h2, h3 {
            color: #2c3e50;
            margin-bottom: 15px;
        }
        header h1 {
            color: white;
            margin: 0;
        }
        .back-btn {
            padding: 8px 15px;
            background-color: #555;
            color: white;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
            display: inline-block;
        }
        .back-btn:hover {
            background-color: #333;
        }
        .info-card {
            background-color: white;
            border-radius: 5px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 20px;
        }
        .stats-container {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 20px;
        }
        .stat-card {
            flex: 1;
            min-width: 200px;
            background-color: white;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            padding: 15px;
            text-align: center;
        }
        .stat-number {
            font-size: 32px;
            font-weight: bold;
            margin: 10px 0;
            color: #3498db;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 12px 8px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        tr:hover {
            background-color: #f1f1f1;
        }
        .progress-bar {
            width: 100%;
            background-color: #f0f0f0;
            border-radius: 5px;
            margin-top: 3px;
        }
        .progress {
            height: 20px;
            background-color: #4CAF50;
            border-radius: 5px;
            text-align: center;
            line-height: 20px;
            color: white;
            min-width: 30px;
        }
        .empty-message {
            padding: 20px;
            background-color: #f8f8f8;
            border-radius: 5px;
            text-align: center;
            color: #666;
        }
        .action-btn {
            padding: 6px 12px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
            display: inline-block;
            margin: 2px 0;
        }
        .action-btn:hover {
            background-color: #45a049;
        }
        .action-btn.secondary {
            background-color: #3498db;
        }
        .action-btn.secondary:hover {
            background-color: #2980b9;
        }
        .action-btn.danger {
            background-color: #e74c3c;
        }
        .action-btn.danger:hover {
            background-color: #c0392b;
        }
        .completion-rate {
            font-weight: bold;
        }
        .high-completion {
            color: green;
        }
        .medium-completion {
            color: orange;
        }
        .low-completion {
            color: red;
        }
    </style>
</head>
<body>
    <header>
        <h1>Teacher Details</h1>
        <a href="manage_users.php" class="back-btn">← Back to Manage Users</a>
    </header>
    
    <div class="container">
        <div class="info-card">
            <h2><?php echo htmlspecialchars($teacher['name']); ?></h2>
            <p><strong>Email:</strong> <?php echo htmlspecialchars($teacher['email']); ?></p>
            <div class="stats-container">
                <div class="stat-card">
                    <h3>Courses</h3>
                    <div class="stat-number"><?php echo $teacher['course_count']; ?></div>
                    <p>Total courses created</p>
                </div>
                <div class="stat-card">
                    <h3>Students</h3>
                    <div class="stat-number"><?php echo $teacher['student_count']; ?></div>
                    <p>Total enrollments</p>
                </div>
                <div class="stat-card">
                    <h3>Completions</h3>
                    <div class="stat-number"><?php echo $teacher['completion_count']; ?></div>
                    <p>Course certifications</p>
                </div>
                <div class="stat-card">
                    <h3>Completion Rate</h3>
                    <?php 
                    $completion_rate = ($teacher['student_count'] > 0) ? round(($teacher['completion_count'] / $teacher['student_count']) * 100, 1) : 0;
                    $completion_class = ($completion_rate >= 75) ? 'high-completion' : (($completion_rate >= 40) ? 'medium-completion' : 'low-completion');
                    ?>
                    <div class="stat-number <?php echo $completion_class; ?>"><?php echo $completion_rate; ?>%</div>
                    <p>Of total enrollments</p>
                </div>
            </div>
            
            <div style="text-align: right;">
                <a href="delete_teacher.php?id=<?php echo $teacher_id; ?>" class="action-btn danger" onclick="return confirm('Are you sure you want to delete this teacher? This will remove all associated courses and enrollments.')">Delete Instructor</a>
            </div>
        </div>
        
        <div class="info-card">
            <h2>Courses (<?php echo $courses_result->num_rows; ?>)</h2>
            <?php if ($courses_result->num_rows > 0) { ?>
                <table>
                    <tr>
                        <th>Title</th>
                        <th>Videos</th>
                        <th>Enrolled Students</th>
                        <th>Completions</th>
                        <th>Completion Rate</th>
                    </tr>
                    <?php while ($course = $courses_result->fetch_assoc()) { 
                        $course_completion_rate = ($course['enrolled_count'] > 0) ? round(($course['completion_count'] / $course['enrolled_count']) * 100, 1) : 0;
                        $rate_class = ($course_completion_rate >= 75) ? 'high-completion' : (($course_completion_rate >= 40) ? 'medium-completion' : 'low-completion');
                    ?>
                        <tr>
                            <td><?php echo htmlspecialchars($course['title']); ?></td>
                            <td><?php echo $course['video_count']; ?></td>
                            <td><?php echo $course['enrolled_count']; ?></td>
                            <td><?php echo $course['completion_count']; ?></td>
                            <td class="completion-rate <?php echo $rate_class; ?>"><?php echo $course_completion_rate; ?>%</td>
                        </tr>
                    <?php } ?>
                </table>
            <?php } else { ?>
                <div class="empty-message">
                    <p>This instructor hasn't created any courses yet.</p>
                </div>
            <?php } ?>
        </div>
    </div>
    
    <script>
        // JavaScript can be added for any interactive elements
    </script>
</body>
</html>

<?php
// Close all prepared statements
$teacher_stmt->close();
$courses_stmt->close();
$conn->close();
?>