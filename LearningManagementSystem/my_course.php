<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include("config.php");
session_start();

if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user'];

// Get enrolled courses
$enrolled_courses_stmt = $conn->prepare("
    SELECT c.id, c.title, c.description 
    FROM courses c
    JOIN enrollments e ON c.id = e.course_id
    WHERE e.student_id = ?
");
$enrolled_courses_stmt->bind_param("i", $user_id);
$enrolled_courses_stmt->execute();
$enrolled_courses = $enrolled_courses_stmt->get_result();

// Arrays to store completed and in-progress courses
$completed_courses = [];
$in_progress_courses = [];

// Process all courses and separate them into completed and in-progress
while ($course = $enrolled_courses->fetch_assoc()) {
    // Get videos for this course
    $videos_stmt = $conn->prepare("
        SELECT id FROM course_videos 
        WHERE course_id = ?
    ");
    $videos_stmt->bind_param("i", $course['id']);
    $videos_stmt->execute();
    $videos_result = $videos_stmt->get_result();
    
    $total_videos = $videos_result->num_rows;
    
    // Count completed videos
    $completed_stmt = $conn->prepare("
        SELECT COUNT(DISTINCT video_id) as completed
        FROM video_progress
        WHERE student_id = ? 
        AND video_id IN (SELECT id FROM course_videos WHERE course_id = ?)
        AND is_completed = 1
    ");
    $completed_stmt->bind_param("ii", $user_id, $course['id']);
    $completed_stmt->execute();
    $completed_result = $completed_stmt->get_result();
    $completed_row = $completed_result->fetch_assoc();
    $completed_videos = $completed_row['completed'];
    
    // Calculate percentage
    $progress_percentage = $total_videos > 0 ? min(100, round(($completed_videos / $total_videos) * 100)) : 0;
    
    // Update the progress in the enrollments table
    $update_progress_stmt = $conn->prepare("
        UPDATE enrollments 
        SET progress = ? 
        WHERE student_id = ? AND course_id = ?
    ");
    $update_progress_stmt->bind_param("iii", $progress_percentage, $user_id, $course['id']);
    $update_progress_stmt->execute();
    
    // Add course information with progress data
    $course_with_progress = $course;
    $course_with_progress['total_videos'] = $total_videos;
    $course_with_progress['completed_videos'] = $completed_videos;
    $course_with_progress['progress_percentage'] = $progress_percentage;
    
    // Check if certificate exists for this course
    $cert_check = $conn->prepare("
        SELECT id FROM certificates 
        WHERE student_id = ? AND course_id = ?
    ");
    $cert_check->bind_param("ii", $user_id, $course['id']);
    $cert_check->execute();
    $cert_result = $cert_check->get_result();
    $has_certificate = ($cert_result->num_rows > 0);
    
    // Separate courses based on certificate existence
    if ($has_certificate) {
        $completed_courses[] = $course_with_progress;
    } else {
        $in_progress_courses[] = $course_with_progress;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Courses</title>
    <link rel="stylesheet" href="assets/student.css">
    <style>
        /* Variables for consistent color palette and styling */
        :root {
          --primary-gradient: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
          --card-shadow: 0 10px 30px rgba(0, 0, 0, 0.05), 0 1px 3px rgba(0, 0, 0, 0.03);
          --card-shadow-hover: 0 20px 40px rgba(0, 0, 0, 0.1);
          --border-radius-lg: 20px;
          --border-radius-md: 16px;
          --border-radius-sm: 8px;
          --accent-purple: #5e60ce;
          --accent-blue: #64dfdf;
          --accent-pink: #f72585;
          --text-dark: #1f1f1f;
          --text-medium: #666;
          --text-light: #777;
          --transition-standard: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
          --completed-green: #06d6a0;
          --progress-yellow:rgb(85, 120, 202);
        }
        
        /* Base styling */
        body {
          background: var(--primary-gradient);
          min-height: 100vh;
          font-family: 'Segoe UI', Roboto, 'Helvetica Neue', sans-serif;
          margin: 0;
          padding: 0;
        }
        
        /* Page header */
        h2 {
          text-align: center;
          margin: 40px auto 30px;
          color: var(--text-dark);
          font-size: 32px;
          font-weight: 800;
          position: relative;
          display: inline-block;
          left: 50%;
          transform: translateX(-50%);
        }
        
        h2::after {
          content: '';
          position: absolute;
          bottom: -10px;
          left: 0;
          width: 100%;
          height: 5px;
          background: linear-gradient(90deg, var(--accent-purple), var(--accent-blue), var(--accent-pink));
          border-radius: 20px;
        }
        
        /* Section headers */
        h3.section-header {
          text-align: left;
          margin: 30px 20px 10px;
          color: var(--text-dark);
          font-size: 24px;
          font-weight: 700;
          border-left: 5px solid var(--accent-purple);
          padding-left: 15px;
        }
        
        /* Main container with glass morphism effect */
        .course-container {
          display: grid;
          grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
          gap: 30px;
          padding: 35px;
          background-color: rgba(255, 255, 255, 0.6);
          backdrop-filter: blur(10px);
          border-radius: var(--border-radius-lg);
          margin: 20px;
          border: 1px solid rgba(255, 255, 255, 0.8);
        }
        
        /* Card styling */
        .course-card {
          position: relative;
          border: none;
          border-radius: var(--border-radius-md);
          padding: 25px;
          background-color: white;
          box-shadow: var(--card-shadow);
          transition: var(--transition-standard);
          cursor: pointer;
          overflow: hidden;
        }
        
        /* Card accent decoration */
        .course-card::after {
          content: '';
          position: absolute;
          top: -50px;
          right: -50px;
          width: 100px;
          height: 100px;
          border-radius: 50%;
          opacity: 0.7;
          transition: var(--transition-standard);
        }
        
        /* In Progress Course Card Styling */
        .in-progress-course::after {
          background: linear-gradient(45deg, var(--accent-purple), #6930c3);
        }
        
        /* Completed Course Card Styling */
        .completed-course::after {
          background: linear-gradient(45deg, var(--completed-green), #06a77d);
        }
        
        /* Hover effects */
        .course-card:hover {
          transform: translateY(-10px);
          box-shadow: var(--card-shadow-hover);
        }
        
        .course-card:hover::after {
          transform: scale(1.2);
        }
        
        /* Course content styling */
        .course-card h3 {
          color: var(--text-dark);
          margin: 10px 0 16px 0;
          font-size: 20px;
          font-weight: 700;
          position: relative;
        }
        
        .course-card p {
          color: var(--text-medium);
          font-size: 15px;
          line-height: 1.6;
          margin-bottom: 25px;
          height: 70px;
          overflow: hidden;
          text-overflow: ellipsis;
          display: -webkit-box;
          -webkit-line-clamp: 3;
          -webkit-box-orient: vertical;
        }
        
        /* Course info section */
        .course-info {
          display: flex;
          justify-content: space-between;
          align-items: center;
          margin-top: 20px;
          padding-top: 15px;
          border-top: 1px solid #f0f0f0;
        }
        
        .course-progress {
          font-size: 14px;
          color: var(--text-light);
        }
        
        .course-progress-percentage {
          font-size: 14px;
          font-weight: 600;
          color: white;
          padding: 5px 15px;
          border-radius: 30px;
          background-color: var(--accent-purple);
        }
        
        /* Progress status indicators */
        .completed-course .course-progress-percentage {
          background-color: var(--completed-green);
        }
        
        .in-progress-course .course-progress-percentage {
          background-color: var(--progress-yellow);
        }
        
        /* Empty state */
        .no-courses {
          text-align: center;
          color: var(--text-medium);
          margin: 50px auto;
          max-width: 500px;
          padding: 40px;
          background-color: white;
          border-radius: var(--border-radius-md);
          box-shadow: var(--card-shadow);
        }
        
        /* Navigation */
        .back-button {
          position: absolute;
          left: 15px;
          top: 15px;
          padding: 8px 16px;
          background-color: rgb(121, 67, 203);
          color: white;
          border: none;
          border-radius: 6px;
          font-size: 14px;
          cursor: pointer;
          box-shadow: 0 2px 6px rgba(0, 0, 0, 0.2);
          transition: background-color 0.3s ease;
        }
        
        .back-button::before {
          content: "←";
          font-size: 20px;
          font-weight: bold;
        }
        
        .back-button:hover {
          transform: translateY(-3px);
          box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
          background-color: var(--accent-purple);
          color: white;
        }
        
        /* Media queries for responsiveness */
        @media (max-width: 768px) {
          .course-container {
            padding: 20px;
            margin: 15px;
          }
          
          h2 {
            font-size: 28px;
          }
          
          .back-button {
            left: 15px;
            top: 15px;
          }
        }
        
        @media (max-width: 480px) {
          .course-container {
            grid-template-columns: 1fr;
          }
        }
    </style>
</head>
<body>

<h2>My Enrolled Courses</h2>

<?php if (count($in_progress_courses) > 0 || count($completed_courses) > 0): ?>
    <a href="student_dashboard.php" class="back-button">  Back </a>
    
    <!-- In Progress Courses Section -->
    <?php if (count($in_progress_courses) > 0): ?>
        <h3 class="section-header">In Progress Courses</h3>
        <div class="course-container">
            <?php foreach ($in_progress_courses as $course): ?>
                <div class="course-card in-progress-course" onclick="window.location.href='view_course.php?id=<?php echo $course['id']; ?>'">
                    <h3><?php echo htmlspecialchars($course['title']); ?></h3>
                    <p><?php echo htmlspecialchars($course['description']); ?></p>
                    <div class="course-info">
                        <div class="course-progress">
                            <?php echo $course['completed_videos']; ?> of <?php echo $course['total_videos']; ?> videos completed
                        </div>
                        <div class="course-progress-percentage">
                            <?php echo $course['progress_percentage']; ?>% complete
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    
    <!-- Completed Courses Section -->
    <?php if (count($completed_courses) > 0): ?>
        <h3 class="section-header">Completed Courses</h3>
        <div class="course-container">
            <?php foreach ($completed_courses as $course): ?>
                <div class="course-card completed-course" onclick="window.location.href='view_course.php?id=<?php echo $course['id']; ?>'">
                    <h3><?php echo htmlspecialchars($course['title']); ?></h3>
                    <p><?php echo htmlspecialchars($course['description']); ?></p>
                    <div class="course-info">
                        <div class="course-progress">
                            <?php echo $course['completed_videos']; ?> of <?php echo $course['total_videos']; ?> videos completed
                        </div>
                        <div class="course-progress-percentage">
                            <?php echo $course['progress_percentage']; ?>% complete
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    
<?php else: ?>
    <p class="no-courses">You are not enrolled in any courses yet.</p>
<?php endif; ?>

</body>
</html>