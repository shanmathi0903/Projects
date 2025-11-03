<?php 
session_start(); 
include 'config.php';  

// Check if the user is logged in and has the 'teacher' role 
if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'teacher') {     
    header("Location: login.php");     
    exit(); 
}  

// Get teacher ID 
$teacher_id = $_SESSION['user'];  

// Get user information including status and archived status
$stmt = $conn->prepare("SELECT id, name, email, status, is_archived FROM teachers WHERE id = ?"); 
$stmt->bind_param("i", $teacher_id); 
$stmt->execute(); 
$user = $stmt->get_result()->fetch_assoc();

// Check if teacher account is archived or suspended
$is_archived = ($user['status'] === 'archived' || $user['is_archived'] == 1 || $user['status'] === 'suspended');

// If teacher is archived or suspended, they can view but not modify
$can_modify = !$is_archived;

// Add debugging
error_log("User data: " . print_r($user, true));

// Fetch courses where this teacher is either the current teacher or the original author
$course_stmt = $conn->prepare("
    SELECT id, title, teacher_id, original_author_id 
    FROM courses 
    WHERE teacher_id = ? OR original_author_id = ?
"); 
$course_stmt->bind_param("ii", $teacher_id, $teacher_id); 
$course_stmt->execute(); 
$courses = $course_stmt->get_result(); 

// Clone the result set for later use (for the quiz dropdown)
$courses_data = [];
while ($row = $courses->fetch_assoc()) {
    $courses_data[] = $row;
}
// Reset the result pointer
$courses->data_seek(0);
?>  

<!DOCTYPE html> 
<html lang="en"> 
<head>     
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instructor Dashboard</title>     
    <link rel="stylesheet" href="assets/style.css">     
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    margin: 0;
    padding: 0;
    background-color: #f5f7fa;
    display: flex;
    color: #333;
    /* Remove fixed height on body to allow for natural page flow */
    min-height: 100vh;
    overflow-x: hidden;
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
    overflow-x: hidden;
    overflow-y: auto; /* Add vertical scrolling */
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
    /* Added to ensure proper vertical positioning */
    position: relative;
    top: 0;
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

.teacher-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.sidebar ul {
    list-style: none;
    padding: 0;
    margin-top: 40px; /* Add some space after the profile section */
}

.sidebar ul li {
    padding: 12px 20px;
    cursor: pointer;
    transition: all 0.2s ease;
    white-space: nowrap;
    border-bottom: none;
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
    padding: 0;
}

.sidebar ul li a:before {
    content: none; /* Remove the default content */
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

/* Dropdown styling */
.dropdown-content {
    display: none;
    background-color: #1a2433;
    padding-left: 20px;
}

.sidebar.expanded .dropdown-content {
    width: 100%;
}

.dropdown-content a {
    padding: 10px 15px 10px 45px !important;
    font-size: 14px;
}

.dropdown > a:after {
    content: "▼";
    font-size: 10px;
    margin-left: 10px;
}

/* Fixed logout button style */
.logout-button {
    display: flex;
    padding: 12px 20px;
    align-items: center;
    color: #ffffff;
    text-decoration: none;
    transition: all 0.2s ease;
    position: relative; /* Changed from absolute to relative */
    bottom: 0;
    width: 100%;
    margin-bottom: 10px; /* Add some space above logout button */
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

/* Content area adjustment */
.content {
    flex-grow: 1;
    padding: 40px;
    margin-left: 60px; /* Initial margin */
    transition: margin-left 0.3s ease-in-out;
    width: calc(100% - 60px);
    overflow-y: auto; /* Add vertical scrolling */
    height: 100vh; /* Full viewport height */
    box-sizing: border-box; /* Include padding in width/height calculations */
}

.sidebar.expanded + .content {
    margin-left: 250px;
    width: calc(100% - 250px);
}

table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    margin-top: 20px;
    background: white;
    border-radius: 5px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

table, th, td {
    border: none;
}

th {
    background: #f8f9fa;
    color: #333;
    font-weight: 600;
    text-transform: uppercase;
    font-size: 12px;
    padding: 15px 20px;
    border-bottom: 1px solid #e0e0e0;
}

td {
    padding: 15px 20px;
    border-bottom: 1px solid #e0e0e0;
    color: #333;
}

td a {
    color: #3498db;
    text-decoration: none;
    margin-right: 15px;
    transition: color 0.3s;
}

td a:hover {
    color: #2980b9;
    text-decoration: underline;
}

td a[href*="delete"] {
    color: #e74c3c;
}

td a[href*="delete"]:hover {
    color: #c0392b;
}

td a[href*="assessment"] {
    color: #9b59b6;
}

td a[href*="assessment"]:hover {
    color: #8e44ad;
}

tr:last-child td {
    border-bottom: none;
}

tr:hover td {
    background-color: #f9f9f9;
}

/* Status indicator for course ownership */
.ownership-badge {
    display: inline-block;
    font-size: 11px;
    padding: 3px 8px;
    border-radius: 10px;
    margin-left: 10px;
    font-weight: normal;
    vertical-align: middle;
}

.original-author {
    background-color: #e9f7ef;
    color: #27ae60;
    border: 1px solid #27ae60;
}

.current-teacher {
    background-color: #eaf2f8;
    color: #2980b9;
    border: 1px solid #2980b9;
}

.both-roles {
    background-color: #fef9e7;
    color: #f39c12;
    border: 1px solid #f39c12;
}

/* Add responsiveness for smaller screens */
@media (max-width: 768px) {
    .content {
        padding: 20px;
    }
    
    table {
        font-size: 14px;
    }
    
    th, td {
        padding: 10px 15px;
    }
}

/* Styles for archived teacher notification */
.archived-notice {
    background-color: #ffe1e1;
    border-left: 4px solid #e74c3c;
    padding: 15px;
    margin-bottom: 20px;
    border-radius: 4px;
    color: #c0392b;
}

.disabled-link {
    color: #999 !important;
    text-decoration: none !important;
    cursor: not-allowed;
    pointer-events: none;
}

.disabled-icon {
    opacity: 0.5;
}
    </style>
</head> 
<body>     
    <div class="sidebar" id="sidebar">
        <button class="toggle-btn" onclick="toggleSidebar()">☰</button>
        
        <div class="profile-section">
            <div class="teacher-avatar">
                <img src="teacher-default.png" 
                alt="Teacher Profile" 
                style="width: 100px; height: 100px; border-radius: 50%; object-fit: cover;">
            </div>
            <h3><?php echo htmlspecialchars($user['name']); ?></h3>
            <?php if ($is_archived): ?>
                <span class="badge">
                    <?php echo ucfirst(htmlspecialchars($user['status'])); ?>
                </span>
            <?php endif; ?>
        </div>
        
        <ul>
            <li class="active"><a href="teacher_dashboard.php"><i class="fas fa-home"></i> <span>Dashboard</span></a></li>
            
            <?php if ($can_modify): ?>
                <li><a href="upload_course.php"><i class="fas fa-file-upload"></i> <span>Upload Course</span></a></li>
                <li><a href="manage_students.php"><i class="fas fa-users"></i> <span>Manage Students</span></a></li>
                <li class="dropdown">
                    <a href="#" onclick="toggleDropdown()">
                        <i class="fas fa-clipboard-check"></i> <span>Manage Assessment</span>
                    </a>
                    <div class="dropdown-content" id="assessmentDropdown">
                        <?php if (count($courses_data) > 0): ?>
                            <?php foreach ($courses_data as $course): ?>
                                <a href="quiz_management.php?course_id=<?php echo $course['id']; ?>">
                                    <i class="fas fa-book"></i> <span><?php echo htmlspecialchars($course['title']); ?></span>
                                </a>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <a href="#"><i class="fas fa-exclamation-circle"></i> <span>No courses available</span></a>
                        <?php endif; ?>
                    </div>
                </li>
                <li><a href="mark_teacher_attendance.php"><i class="fas fa-chart-line"></i> <span>Access Record</span></a></li>
            <?php else: ?>
                <li><a class="disabled-link"><i class="fas fa-file-upload disabled-icon"></i> <span>Upload Course</span></a></li>
                <li><a class="disabled-link"><i class="fas fa-users disabled-icon"></i> <span>Manage Students</span></a></li>
                <li><a class="disabled-link"><i class="fas fa-clipboard-check disabled-icon"></i> <span>Manage Assessment</span></a></li>
                <li><a class="disabled-link"><i class="fas fa-chart-line disabled-icon"></i> <span>Access Record</span></a></li>
            <?php endif; ?>
        </ul>
        
        <a href="logout.php" class="logout-button"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a>
    </div>      
    
    <div class="content" id="dashboardContent">
        <div class="dashboard-header">
            <h2>Welcome, <?php echo htmlspecialchars($user['name']); ?>!</h2>
            <p>Manage your courses and student assessments</p>
        </div>
        
        <?php if ($is_archived): ?>
        <div class="archived-notice">
            <strong><i class="fas fa-exclamation-triangle"></i> Account Status: <?php echo ucfirst(htmlspecialchars($user['status'])); ?></strong>
            <p>Your account is currently <?php echo htmlspecialchars($user['status']); ?>. You can view existing content but cannot upload new courses or make changes. Please contact the administrator for more information.</p>
        </div>
        <?php endif; ?>
        
        <h3 class="section-title">Your Courses</h3>
        <table>         
            <tr>             
                <th>Course Title</th>
                <th>Status</th>             
                <th>Actions</th>         
            </tr>         
            <?php if ($courses->num_rows > 0): ?>
                <?php while ($course = $courses->fetch_assoc()): 
                    // Determine ownership status
                    $is_current_teacher = ($course['teacher_id'] == $teacher_id);
                    $is_original_author = ($course['original_author_id'] == $teacher_id);
                    $ownership_status = '';
                    $badge_class = '';
                    
                    if ($is_current_teacher && $is_original_author) {
                        $ownership_status = 'Original Author & Current instructor';
                        $badge_class = 'both-roles';
                    } elseif ($is_current_teacher) {
                        $ownership_status = 'Current Instructor';
                        $badge_class = 'current-teacher';
                    } elseif ($is_original_author) {
                        $ownership_status = 'Original Author';
                        $badge_class = 'original-author';
                    }
                    
                    // Determine if this teacher can edit this specific course
                    // Only current teachers who are not archived can edit
                    $can_edit_this_course = $can_modify && $is_current_teacher;
                ?>
                <tr>             
                    <td><?php echo htmlspecialchars($course['title']); ?></td>
                    <td>
                        <span class="ownership-badge <?php echo $badge_class; ?>">
                            <?php echo $ownership_status; ?>
                        </span>
                    </td>             
                    <td>
                        <?php if ($can_edit_this_course): ?>                 
                            <a href="edit_course.php?id=<?php echo $course['id']; ?>">Edit</a> |                 
                            <a href="delete_course.php?id=<?php echo $course['id']; ?>" onclick="return confirm('Are you sure you want to delete this course?');">Delete</a> |
                            <a href="quiz_management.php?course_id=<?php echo $course['id']; ?>">Manage Assessment</a>
                        <?php else: ?>
                            <a href="edit_course.php?id=<?php echo $course['id']; ?>">View</a> 
                        <!--    <a href="view_assessment.php?course_id=<?php echo $course['id']; ?>">View Assessment</a>  -->
                        <?php endif; ?>
                    </td>         
                </tr>         
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="3" style="text-align: center;">No courses found</td>
                </tr>
            <?php endif; ?>     
        </table>
        
        <?php if ($can_modify): ?>
            <div style="margin-top: 20px;">
                <a href="upload_course.php" class="btn">
                    <i class="fas fa-plus"></i> Add New Course
                </a>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function toggleSidebar() {
            let sidebar = document.getElementById("sidebar");
            sidebar.classList.toggle("expanded");
            
            // Update dropdown visibility if sidebar is collapsed
            if (!sidebar.classList.contains("expanded")) {
                document.getElementById("assessmentDropdown").style.display = "none";
            }
        }
        
        function toggleDropdown() {
            let dropdown = document.getElementById("assessmentDropdown");
            if (dropdown.style.display === "block") {
                dropdown.style.display = "none";
            } else {
                dropdown.style.display = "block";
            }
            
            // Prevent event from bubbling up to parent elements
            event.stopPropagation();
        }
    </script>
</body> 
</html>