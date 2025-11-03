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

// Handle search query if present
$search_query = isset($_GET['search']) ? $_GET['search'] : '';
$search_condition = '';

if (!empty($search_query)) {
    $search_condition = "WHERE courses.title LIKE ? OR teachers.name LIKE ?";
}

// Fetch courses with teacher information and enrollment count
$courses_query = "
    SELECT 
        courses.id, 
        courses.title, 
        courses.description, 
        teachers.name as teacher_name,
        (SELECT COUNT(*) FROM enrollments WHERE enrollments.course_id = courses.id) as enrollment_count
    FROM courses 
    LEFT JOIN teachers ON courses.teacher_id = teachers.id 
    $search_condition
";

$courses_stmt = $conn->prepare($courses_query);

// Bind search parameters if searching
if (!empty($search_query)) {
    $search_param = "%$search_query%";
    $courses_stmt->bind_param("ss", $search_param, $search_param);
}

$courses_stmt->execute();
$courses = $courses_stmt->get_result();

// Get courses the student is already enrolled in
$enrolled_stmt = $conn->prepare("SELECT course_id FROM enrollments WHERE student_id = ?");
$enrolled_stmt->bind_param("i", $user_id);
$enrolled_stmt->execute();
$enrolled_result = $enrolled_stmt->get_result();

// Create an array of enrolled course IDs for easy lookup
$enrolled_courses = array();
while ($row = $enrolled_result->fetch_assoc()) {
    $enrolled_courses[] = $row['course_id'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard</title>
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
            /* Added to ensure proper vertical positioning */
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
            margin-top: 40px; /* Add some space after the profile section */
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

        /* Search bar styles */
        .search-bar {
            margin-bottom: 30px;
            width: 100%;
            max-width: 600px;
            position: relative;
        }

        .search-bar form {
            display: flex;
            width: 100%;
        }

        .search-bar input {
            flex-grow: 1;
            padding: 15px 20px;
            border: 1px solid #e5e7eb;
            border-radius: 8px 0 0 8px;
            font-size: 16px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
            outline: none;
        }

        .search-bar input:focus {
            border-color: #3b82f6;
        }

        .search-bar button {
            background: #3b82f6;
            color: white;
            border: none;
            padding: 0 25px;
            border-radius: 0 8px 8px 0;
            cursor: pointer;
            font-size: 18px;
            transition: background-color 0.2s ease;
        }

        .search-bar button:hover {
            background: #2563eb;
        }

        .section-title {
            color: #333;
            font-size: 22px;
            margin-bottom: 20px;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .section-title a {
            color: #3b82f6;
            font-size: 16px;
            text-decoration: none;
            display: flex;
            align-items: center;
        }

        .section-title a i {
            margin-left: 5px;
        }

        .course-container {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 40px;
        }

        /* Updated course card styles to match the image */
        .course-card {
            flex: 0 0 calc(33.333% - 20px);
            background: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            cursor: pointer;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            position: relative;
            border-left: 4px solid #3b82f6; /* Added blue accent border */
        }

        .course-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1);
        }

        .course-details {
            padding: 20px;
        }

        .course-details h3 {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 15px;
            color: #1e293b;
            line-height: 1.4;
        }

        .instructor {
            font-size: 14px;
            color: #6b7280;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
        }

        .instructor i {
            margin-right: 8px;
            color: #3b82f6;
        }

        .enrolled-count {
            font-size: 14px;
            color: #6b7280;
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }

        .enrolled-count i {
            margin-right: 8px;
            color: #3b82f6;
        }

        .enrolled-badge {
            display: inline-block;
            background-color:rgb(85, 111, 213); /* Updated to match the image's teal color */
            color: white;
            padding: 6px 15px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
        }

        /* Fixed logout button style */
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

        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            justify-content: center;
            align-items: center;
            z-index: 1001;
        }

        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 12px;
            max-width: 500px;
            width: 100%;
            position: relative;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
        }

        .close {
            position: absolute;
            right: 15px;
            top: 10px;
            font-size: 24px;
            cursor: pointer;
            color: #6b7280;
        }

        .btn {
            background-color: #3b82f6;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            cursor: pointer;
            margin-top: 20px;
            font-weight: 500;
            transition: background-color 0.2s ease;
        }

        .btn:hover {
            background-color: #2563eb;
        }

        /* Modal enrolled button styled like the image */
        .modal-enrolled-badge {
            display: inline-block;
            background-color:rgb(88, 212, 6); /* Teal color from image */
            color: white;
            padding: 8px 18px;
            border-radius: 20px;
            font-size: 16px;
            font-weight: 500;
            margin-top: 20px;
        }

        @media (max-width: 1200px) {
            .course-card {
                flex: 0 0 calc(50% - 20px);
            }
        }

        @media (max-width: 768px) {
            .course-container {
                flex-direction: column;
                align-items: stretch;
            }

            .course-card {
                width: 100%;
                flex: none;
            }

            .dashboard-content {
                padding: 20px;
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
        <li class="active"><a href="index.php"><i class="fas fa-home"></i> <span>Dashboard</span></a></li>
        <li><a href="view_attendance.php"><i class="fas fa-chart-line"></i> <span>My Activity</span></a></li>
        <li><a href="my_course.php"><i class="fas fa-graduation-cap"></i> <span>My Courses</span></a></li>
        <li><a href="update_profile.php"><i class="fas fa-user-edit"></i> <span>Update Profile</span></a></li>
    </ul>
    <a href="logout.php" class="logout-button"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a>
</div>

<div class="dashboard-content" id="dashboardContent">
    <div class="dashboard-header">
        <h2>Welcome, <?php echo htmlspecialchars($user['name']); ?>!</h2>
        <p>Access your courses and track your learning progress</p>
    </div>

    <div id="courses" class="content-section">
        <!-- Search bar -->
        <div class="search-bar">
            <form action="" method="GET">
                <input type="text" name="search" placeholder="Search for courses or instructors..." value="<?php echo htmlspecialchars($search_query); ?>">
                <button type="submit"><i class="fas fa-search"></i></button>
            </form>
        </div>

        <h3 class="section-title">
            <?php if (!empty($search_query)): ?>
                Search Results for "<?php echo htmlspecialchars($search_query); ?>"
            <?php else: ?>
                Available Courses
            <?php endif; ?>
        </h3>

        <div class="course-container">
    <?php if ($courses->num_rows > 0): ?>
        <?php while ($course = $courses->fetch_assoc()): ?>
            <?php 
            $isEnrolled = in_array($course['id'], $enrolled_courses);
            
            // Store the course data as data attributes
            $courseData = "data-id='" . $course['id'] . "' " .
                          "data-title='" . addslashes(htmlspecialchars($course['title'])) . "' " .
                          "data-description='" . addslashes(htmlspecialchars($course['description'])) . "' " .
                          "data-enrolled='" . ($isEnrolled ? 'true' : 'false') . "'";
            ?>
            <div class="course-card" <?php echo $courseData; ?> onclick="handleCourseClick(this)">
                <div class="course-details">
                    <h3><?php echo htmlspecialchars($course['title']); ?></h3>
                    <div class="instructor">
                        <i class="fas fa-user"></i> 
                        <?php echo $course['teacher_name'] ? htmlspecialchars($course['teacher_name']) : 'Unknown Instructor'; ?>
                    </div>
                    <div class="enrolled-count">
                        <i class="fas fa-users"></i> 
                        <?php echo $course['enrollment_count']; ?> Learner<?php echo $course['enrollment_count'] != 1 ? 's' : ''; ?>
                    </div>
                    <?php if ($isEnrolled): ?>
                        <span class="enrolled-badge">Already Enrolled</span>
                    <?php endif; ?>
                </div>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <p>No courses found. Try a different search term.</p>
    <?php endif; ?>
</div>
    </div>

    <div id="courseModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <h2 id="modalTitle"></h2>
            <p id="modalDescription"></p>
            <div id="enrollmentAction">
                <!-- This area will be populated dynamically by JavaScript -->
            </div>
        </div>
    </div>
</div>

<script>
    function toggleSidebar() {
        let sidebar = document.getElementById("sidebar");
        sidebar.classList.toggle("expanded");
    }

    function showSection(section) {
        document.querySelectorAll('.content-section').forEach(el => el.style.display = 'none');
        document.getElementById(section).style.display = 'block';
    }

    showSection('courses');

    // Improved course details function to ensure proper modal display
    function showCourseDetails(id, title, description, isEnrolled) {
        console.log("Showing course details for: " + title + ", Enrolled: " + isEnrolled);
        
        // If student is already enrolled, redirect to view_course.php
        if (isEnrolled) {
            window.location.href = 'view_course.php?id=' + id;
            return;
        }
        
        // Otherwise show the modal for non-enrolled courses
        document.getElementById("modalTitle").textContent = title;
        document.getElementById("modalDescription").textContent = description;
        
        // Ensure the enrollment action is always present
        const enrollmentActionDiv = document.getElementById("enrollmentAction");
        enrollmentActionDiv.innerHTML = `
            <form id="enrollForm" method="POST" action="enroll.php">
                <input type="hidden" name="course_id" value="${id}">
                <button type="submit" class="btn">Enroll Now</button>
            </form>
        `;
        
        // Show the modal
        const modal = document.getElementById("courseModal");
        modal.style.display = "flex";
    }
    function handleCourseClick(cardElement) {
        const courseId = cardElement.getAttribute('data-id');
        const courseTitle = cardElement.getAttribute('data-title');
        const courseDescription = cardElement.getAttribute('data-description');
        const isEnrolled = cardElement.getAttribute('data-enrolled') === 'true';
        
        if (isEnrolled) {
            window.location.href = 'view_course.php?id=' + courseId;
        } else {
            showCourseDetails(courseId, courseTitle, courseDescription, isEnrolled);
        }
    }

    function closeModal() {
        document.getElementById("courseModal").style.display = "none";
    }

    // Close modal when clicking outside of it
    window.onclick = function(event) {
        const modal = document.getElementById("courseModal");
        if (event.target === modal) {
            closeModal();
        }
    }
    
    // Add event listeners to all course cards
    document.addEventListener("DOMContentLoaded", function() {
        const courseCards = document.querySelectorAll('.course-card');
        
        courseCards.forEach(card => {
            card.style.cursor = "pointer";
        });
    });
</script>
</body>
</html>