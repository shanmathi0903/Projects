<?php 
session_start(); 
include 'config.php';  

// Check if the user is logged in and is an admin
if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit(); 
}

$admin_id = $_SESSION['user'];

// Get filter parameters
$teacher_filter = isset($_GET['teacher_filter']) ? $_GET['teacher_filter'] : '';
$course_filter = isset($_GET['course_filter']) ? $_GET['course_filter'] : '';
$status_filter = isset($_GET['status_filter']) ? $_GET['status_filter'] : '';

// Get all teachers for the dropdown (always excluding archived)
$teachers_query = "SELECT id, name, email FROM teachers WHERE status != 'archived' ORDER BY id";
$teachers_result = $conn->query($teachers_query);

// Get all courses for the dropdown (always excluding archived teachers' courses)
$courses_query = "
    SELECT c.id, c.title, t.name AS teacher_name 
    FROM courses c
    JOIN teachers t ON c.teacher_id = t.id
    WHERE t.status != 'archived'
    ORDER BY c.title
";

$courses_result = $conn->query($courses_query);

// TEACHER LIST QUERY - ALWAYS exclude archived teachers
// TEACHER LIST QUERY - ALWAYS exclude archived teachers
// Change this query in manage_users.php
$teachers_list_query = "
    SELECT 
        t.id,
        t.name,
        t.email,
        (SELECT COUNT(*) FROM courses WHERE teacher_id = t.id) AS course_count,
        (SELECT COUNT(*) FROM enrollments e JOIN courses c ON e.course_id = c.id WHERE c.teacher_id = t.id) AS student_count,
        (SELECT COUNT(*) FROM certificates cert JOIN courses c ON cert.course_id = c.id WHERE c.teacher_id = t.id) AS completion_count,
        t.status
    FROM 
        teachers t
    WHERE 
        t.status != 'archived'
    ORDER BY t.id;
";



// Execute the teachers list query
$teachers_list_result = $conn->query($teachers_list_query);

// STUDENT LIST QUERY
$students_query = "
    SELECT 
        s.id,
        s.name,
        s.email,
        (SELECT COUNT(*) FROM enrollments WHERE student_id = s.id) AS enrolled_count,
        (SELECT COUNT(*) FROM certificates WHERE student_id = s.id) AS completed_count
    FROM 
        students s
    ORDER BY s.id
";

$students_result = $conn->query($students_query);

// ENROLLMENTS QUERY
// ENROLLMENTS QUERY
$enrollments_query = "
    SELECT 
        students.id AS student_id,
        students.name AS student_name,
        students.email AS student_email,
        courses.id AS course_id,
        courses.title AS course_title,
        teachers.name AS teacher_name,
        (
            SELECT COUNT(*) 
            FROM course_videos cv 
            WHERE cv.course_id = courses.id
        ) AS total_videos,
        (
            SELECT COUNT(DISTINCT cv.id) 
            FROM video_progress vp
            JOIN course_videos cv ON vp.video_id = cv.id
            WHERE vp.student_id = students.id 
            AND cv.course_id = courses.id 
            AND vp.is_completed = TRUE
        ) AS completed_videos,
        (
            SELECT MAX(percentage_score)
            FROM quiz_attempts qa
            JOIN quizzes q ON qa.quiz_id = q.id
            WHERE qa.student_id = students.id
            AND q.course_id = courses.id
        ) AS quiz_score,
        (
            SELECT passing_score
            FROM quizzes
            WHERE course_id = courses.id
            LIMIT 1
        ) AS passing_score,
        (
            SELECT certificate_number
            FROM certificates
            WHERE student_id = students.id
            AND course_id = courses.id
            LIMIT 1
        ) AS certificate_number,
        (
            SELECT issue_date
            FROM certificates
            WHERE student_id = students.id
            AND course_id = courses.id
            LIMIT 1
        ) AS certificate_date
    FROM enrollments
    JOIN students ON enrollments.student_id = students.id
    JOIN courses ON enrollments.course_id = courses.id
    JOIN teachers ON courses.teacher_id = teachers.id
    WHERE teachers.status != 'archived'
";

// Apply filters to enrollments query
$params = [];
$types = "";

// Apply teacher filter
if (!empty($teacher_filter)) {
    $enrollments_query .= " AND courses.teacher_id = ?";
    $params[] = $teacher_filter;
    $types .= "i";
}

// Apply course filter
if (!empty($course_filter)) {
    $enrollments_query .= " AND courses.id = ?";
    $params[] = $course_filter;
    $types .= "i";
}

// Add status filter
if (!empty($status_filter)) {
    if ($status_filter == 'completed') {
        $enrollments_query .= " AND EXISTS (SELECT 1 FROM certificates WHERE certificates.student_id = students.id AND certificates.course_id = courses.id)";
    } else if ($status_filter == 'in_progress') {
        $enrollments_query .= " AND NOT EXISTS (SELECT 1 FROM certificates WHERE certificates.student_id = students.id AND certificates.course_id = courses.id)";
    }
}

$enrollments_query .= " ORDER BY students.name, courses.title";

// Prepare and execute the enrollments query
if (!empty($params)) {
    $enrollments_stmt = $conn->prepare($enrollments_query);
    $enrollments_stmt->bind_param($types, ...$params);
    $enrollments_stmt->execute();
    $enrollments_result = $enrollments_stmt->get_result();
} else {
    $enrollments_result = $conn->query($enrollments_query);
}

// Check if we need to display a message (e.g., after archiving/unarchiving)
$message = "";
$message_type = "";
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $message_type = $_SESSION['message_type'];
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Manage Users - Admin Dashboard</title>
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
        .filters {
            margin-bottom: 20px;
            padding: 15px;
            background-color: #f9f9f9;
            border-radius: 5px;
            border: 1px solid #ddd;
        }
        .filter-group {
            display: inline-block;
            margin-right: 20px;
            margin-bottom: 10px;
        }
        .filter-group label {
            display: inline-block;
            width: auto;
            margin-right: 8px;
            font-weight: bold;
        }
        select, input[type="text"] {
            padding: 6px;
            border: 1px solid #ddd;
            border-radius: 4px;
            width: 200px;
        }
        .filter-buttons {
            display: inline-block;
            margin-left: 10px;
        }
        .action-btn {
            padding: 8px 15px;
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
        .tab-container {
            margin-top: 20px;
        }
        .tabs {
            display: flex;
            border-bottom: 1px solid #ddd;
            margin-bottom: 20px;
        }
        .tab {
            padding: 10px 20px;
            cursor: pointer;
            background-color: #f1f1f1;
            border: 1px solid #ddd;
            border-bottom: none;
            border-radius: 5px 5px 0 0;
            margin-right: 5px;
        }
        .tab.active {
            background-color: white;
            border-bottom: 1px solid white;
            margin-bottom: -1px;
        }
        .tab-content {
            display: none;
            background: white;
            padding: 20px;
            border-radius: 0 0 5px 5px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .tab-content.active {
            display: block;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 12px 8px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
            position: sticky;
            top: 0;
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
        .certificate-status {
            font-weight: bold;
        }
        .certificate-issued {
            color: green;
        }
        .certificate-eligible {
            color: blue;
        }
        .certificate-pending {
            color: orange;
        }
        .no-results {
            padding: 20px;
            background-color: #f8f8f8;
            border-radius: 5px;
            text-align: center;
            color: #666;
        }
    </style>
    
</head>
<body>
    <header>
        <h1>Manage Users</h1>
        <a href="admin_dashboard.php" class="back-btn">← Back to Dashboard</a>
    </header>
    
    <div class="container">
        <?php if (!empty($message)): ?>
        <div class="message <?php echo $message_type; ?>">
            <?php echo $message; ?>
        </div>
        <?php endif; ?>
        
        <!-- Filters -->
        <div class="filters">
            <form method="GET" action="">
                <div class="filter-group">
                    <label for="teacher_filter">Instructor:</label>
                    <select name="teacher_filter" id="teacher_filter">
                        <option value="">All </option>
                        <?php while ($teacher = $teachers_result->fetch_assoc()) { ?>
                            <option value="<?php echo $teacher['id']; ?>" <?php echo (isset($_GET['teacher_filter']) && $_GET['teacher_filter'] == $teacher['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($teacher['name']); ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="course_filter">Course:</label>
                    <select name="course_filter" id="course_filter">
                        <option value="">All </option>
                        <?php while ($course = $courses_result->fetch_assoc()) { ?>
                            <option value="<?php echo $course['id']; ?>" <?php echo (isset($_GET['course_filter']) && $_GET['course_filter'] == $course['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($course['title']) . ' (' . htmlspecialchars($course['teacher_name']) . ')'; ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="status_filter">Status:</label>
                    <select name="status_filter" id="status_filter">
                        <option value="">All </option>
                        <option value="completed" <?php echo (isset($_GET['status_filter']) && $_GET['status_filter'] == 'completed') ? 'selected' : ''; ?>>Completed</option>
                        <option value="in_progress" <?php echo (isset($_GET['status_filter']) && $_GET['status_filter'] == 'in_progress') ? 'selected' : ''; ?>>In Progress</option>
                    </select>
                </div>
                
                <div class="filter-buttons">
                    <button type="submit" class="action-btn">Apply Filters</button>
                    <a href="manage_users.php" class="action-btn" style="background-color: #f44336;">Clear Filters</a>
                </div>
            </form>
        </div>
        
        <!-- Tabs -->
        <div class="tab-container">
            <div class="tabs">
                <div class="tab active" onclick="openTab(event, 'teachers-tab')">Instructors</div>
                <div class="tab" onclick="openTab(event, 'students-tab')">Students</div>
                <div class="tab" onclick="openTab(event, 'enrollments-tab')">Course Enrollments</div>
            </div>
            
            <!-- Teachers Tab -->
            <div id="teachers-tab" class="tab-content active">
                <?php if ($teachers_list_result->num_rows > 0) { ?>
                    <div style="text-align: right; margin-bottom: 10px;">
                        <a href="add_teacher.php" class="action-btn">+ Add New Teacher</a>
                        <a href="view_archieved_teachers.php" class="action-btn">View Archived Teachers</a>
                    </div>
                    <table>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Courses</th>
                            <th>Students</th>
                            <th>Course Completions</th>
                            <th>Actions</th>
                        </tr>
                        <?php while ($teacher = $teachers_list_result->fetch_assoc()) { ?>
                            <tr>
                                <td><?php echo $teacher['id']; ?></td>
                                <td><?php echo htmlspecialchars($teacher['name']); ?></td>
                                <td><?php echo htmlspecialchars($teacher['email']); ?></td>
                                <td><?php echo $teacher['course_count']; ?></td>
                                <td><?php echo $teacher['student_count']; ?></td>
                                <td><?php echo $teacher['completion_count']; ?></td>
                                <td>
                                    <a href="view_teacher.php?id=<?php echo $teacher['id']; ?>" class="action-btn secondary">View Details</a>
                                    <a href="delete_teacher.php?id=<?php echo $teacher['id']; ?>" class="action-btn danger" onclick="return confirm('Are you sure you want to delete this teacher? This will remove all associated courses and enrollments.')">Delete</a>
                                </td>
                            </tr>
                        <?php } ?>
                    </table>
                <?php } else { ?>
                    <div class="no-results">
                        <p>No teachers found.</p>
                    </div>
                <?php } ?>
            </div>
            
            <!-- Students Tab -->
            <div id="students-tab" class="tab-content">
                <?php if ($students_result->num_rows > 0) { ?>
                    <table>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Enrolled Courses</th>
                            <th>Completed Courses</th>
                        </tr>
                        <?php while ($student = $students_result->fetch_assoc()) { ?>
                            <tr>
                                <td><?php echo $student['id']; ?></td>
                                <td><?php echo htmlspecialchars($student['name']); ?></td>
                                <td><?php echo htmlspecialchars($student['email']); ?></td>
                                <td><?php echo $student['enrolled_count']; ?></td>
                                <td><?php echo $student['completed_count']; ?></td>
                            </tr>
                        <?php } ?>
                    </table>
                <?php } else { ?>
                    <div class="no-results">
                        <p>No students found.</p>
                    </div>
                <?php } ?>
            </div>
            
            <!-- Enrollments Tab -->
            <div id="enrollments-tab" class="tab-content">
                <?php if ($enrollments_result->num_rows > 0) { ?>
                    <table>
                        <tr>
                            <th>Student</th>
                            <th>Email</th>
                            <th>Course</th>
                            <th>Teacher</th>
                            <th>Course Progress</th>
                            <th>Assessment Status</th>
                            <th>Certificate Status</th>
                        </tr>
                        <?php while ($enrollment = $enrollments_result->fetch_assoc()) { 
                            // Calculate progress percentage
                            // Calculate progress percentage
$progress_percentage = $enrollment['total_videos'] > 0
? min(100, round(($enrollment['completed_videos'] / $enrollment['total_videos']) * 100, 2))
: 0;
                                
                            // Determine certificate status
                            $certificate_status = '';
                            $status_class = '';
                            if (!empty($enrollment['certificate_number'])) {
                                $certificate_status = ' Issued on ' . date('M d, Y', strtotime($enrollment['certificate_date']));
                                $status_class = 'certificate-issued';
                            } else {
                                // Check if requirements are met
                                $videos_completed = ($enrollment['total_videos'] > 0 && $enrollment['completed_videos'] == $enrollment['total_videos']);
                                $quiz_passed = empty($enrollment['passing_score']) || (!empty($enrollment['quiz_score']) && $enrollment['quiz_score'] >= $enrollment['passing_score']);
                                
                                if (!$videos_completed) {
                                    $certificate_status = '⚠ Videos incomplete';
                                    $status_class = 'certificate-pending';
                                } elseif (!$quiz_passed && !empty($enrollment['passing_score'])) {
                                    $certificate_status = '⚠ Quiz not passed';
                                    $status_class = 'certificate-pending';
                                } else {
                                    $certificate_status = ' Eligible (not claimed)';
                                    $status_class = 'certificate-eligible';
                                }
                            }
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($enrollment['student_name']); ?></td>
                            <td><?php echo htmlspecialchars($enrollment['student_email']); ?></td>
                            <td><?php echo htmlspecialchars($enrollment['course_title']); ?></td>
                            <td><?php echo htmlspecialchars($enrollment['teacher_name']); ?></td>
                            <td>
                                <div><?php echo $enrollment['completed_videos'] . '/' . $enrollment['total_videos']; ?></div>
                                <div class="progress-bar">
                                    <div class="progress" style="width: <?php echo max(5, $progress_percentage); ?>%">
                                        <?php echo $progress_percentage . '%'; ?>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <?php 
                                if (!empty($enrollment['quiz_score'])) {
                                    echo $enrollment['quiz_score'] . '%';
                                    if (!empty($enrollment['passing_score'])) {
                                        $pass_status = $enrollment['quiz_score'] >= $enrollment['passing_score'] ? 
                                            '<span style="color:green"> Passed</span>' : 
                                            '<span style="color:red"> Failed</span>';
                                        echo ' ' . $pass_status;
                                        echo '<br>(Required: ' . $enrollment['passing_score'] . '%)';
                                    }
                                } else {
                                    echo empty($enrollment['passing_score']) ? 'N/A' : 'Not attempted';
                                }
                                ?>
                            </td>
                            <td class="certificate-status <?php echo $status_class; ?>">
                                <?php echo $certificate_status; ?>
                                <?php if (!empty($enrollment['certificate_number'])) { ?>
                                    <br><small>Cert #: <?php echo htmlspecialchars($enrollment['certificate_number']); ?></small>
                                <?php } ?>
                            </td>
                        </tr>
                        <?php } ?>
                    </table>
                <?php } else { ?>
                    <div class="no-results">
                        <p>No enrollments match your filter criteria.</p>
                        <p><a href="manage_users.php" class="action-btn">Show All Enrollments</a></p>
                    </div>
                <?php } ?>
            </div>
        </div>
    </div>
    
    <script>
        function openTab(evt, tabName) {
            // Declare all variables
            var i, tabcontent, tablinks;

            // Get all elements with class="tab-content" and hide them
            tabcontent = document.getElementsByClassName("tab-content");
            for (i = 0; i < tabcontent.length; i++) {
                tabcontent[i].className = tabcontent[i].className.replace(" active", "");
            }

            // Get all elements with class="tab" and remove the class "active"
            tablinks = document.getElementsByClassName("tab");
            for (i = 0; i < tablinks.length; i++) {
                tablinks[i].className = tablinks[i].className.replace(" active", "");
            }

            // Show the current tab, and add an "active" class to the button that opened the tab
            document.getElementById(tabName).className += " active";
            evt.currentTarget.className += " active";
        }
        
        // Change course dropdown options based on teacher selection
        document.addEventListener('DOMContentLoaded', function() {
            // This is where AJAX functionality can be added to dynamically update course options
            // when a teacher is selected, without requiring page reload
        });
    </script>
</body>
</html>

<?php
// Close all prepared statements
if (isset($enrollments_stmt)) $enrollments_stmt->close();
$conn->close();
?>