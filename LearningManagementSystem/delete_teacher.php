
<?php
session_start();
include 'config.php';

// Check if the user is logged in and is an admin
if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Check if ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "No teacher ID provided.";
    header("Location: manage_users.php");
    exit();
}

$teacher_id = intval($_GET['id']);

// Verify this teacher exists
$check_query = "SELECT id, name, email  FROM teachers WHERE id = ?";
$check_stmt = $conn->prepare($check_query);
$check_stmt->bind_param("i", $teacher_id);
$check_stmt->execute();
$result = $check_stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error'] = "Teacher not found.";
    header("Location: manage_users.php");
    $check_stmt->close();
    exit();
}

$teacher_data = $result->fetch_assoc();
$teacher_name = $teacher_data['name'];
$check_stmt->close();

// Get all other teachers for reassignment
$teachers_query = "SELECT id, name FROM teachers WHERE id != ? AND status = 'active' ORDER BY name";
$teachers_stmt = $conn->prepare($teachers_query);
$teachers_stmt->bind_param("i", $teacher_id);
$teachers_stmt->execute();
$other_teachers = $teachers_stmt->get_result();
$teachers_stmt->close();

// Get courses by this teacher
$courses_query = "SELECT id, title FROM courses WHERE teacher_id = ? ORDER BY title";
$courses_stmt = $conn->prepare($courses_query);
$courses_stmt->bind_param("i", $teacher_id);
$courses_stmt->execute();
$courses = $courses_stmt->get_result();
$courses_array = [];
while ($course = $courses->fetch_assoc()) {
    $courses_array[] = $course;
}
$courses_stmt->close();

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if it's a reassignment or final deletion
    if (isset($_POST['reassign_courses'])) {
        // Process course reassignment
        $conn->begin_transaction();
        try {
            foreach ($_POST['course_assignments'] as $course_id => $new_teacher_id) {
                if (!empty($new_teacher_id)) {
                    // Update the course with the new teacher
                    $update_query = "UPDATE courses SET teacher_id = ? WHERE id = ?";
                    $update_stmt = $conn->prepare($update_query);
                    $update_stmt->bind_param("ii", $new_teacher_id, $course_id);
                    $update_stmt->execute();
                    $update_stmt->close();

                    // Update the teacher_id in all quizzes associated with this course
                    $update_quizzes_query = "UPDATE quizzes SET teacher_id = ? WHERE course_id = ?";
                    $update_quizzes_stmt = $conn->prepare($update_quizzes_query);
                    $update_quizzes_stmt->bind_param("ii", $new_teacher_id, $course_id);
                    $update_quizzes_stmt->execute();
                    $update_quizzes_stmt->close();
                }
            }
            
            $conn->commit();
            $_SESSION['success'] = "Courses have been reassigned successfully.";
            
            // Redirect to confirm deletion page 
            header("Location: delete_teacher.php?id={$teacher_id}&reassigned=1");
            exit();
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['error'] = "Error reassigning courses: " . $e->getMessage();
        }
    } elseif (isset($_POST['confirm_delete']) && $_POST['confirm_delete'] === 'yes') {
        // Final deletion after reassignment (actually archiving)
        $conn->begin_transaction();
        
        try {
            // IMPORTANT: We're no longer deleting courses - keeping them associated with the archived teacher
            // Get statistics for archiving
            $courses_count = $conn->query("SELECT COUNT(*) as count FROM courses WHERE teacher_id = $teacher_id")->fetch_assoc()['count'];
            $students_count = $conn->query("SELECT COUNT(DISTINCT student_id) as count FROM enrollments e JOIN courses c ON e.course_id = c.id WHERE c.teacher_id = $teacher_id")->fetch_assoc()['count'];
            
            // Check if we need to create the archived_teachers table
            $check_table_query = "SHOW TABLES LIKE 'archived_teachers'";
            $table_exists = $conn->query($check_table_query)->num_rows > 0;
            
            if (!$table_exists) {
                // Create archived_teachers table if it doesn't exist
                $create_table_query = "CREATE TABLE archived_teachers (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    original_id INT NOT NULL,
                    name VARCHAR(255) NOT NULL,
                    email VARCHAR(255) NOT NULL,
                    department VARCHAR(100),
                    bio TEXT,
                    profile_pic VARCHAR(255),
                    courses_count INT DEFAULT 0,
                    students_count INT DEFAULT 0,
                    archive_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    archived_by INT,
                    archive_reason TEXT
                )";
                $conn->query($create_table_query);
            }
            
            // Archive the teacher
            $archive_query = "INSERT INTO archived_teachers 
                             (original_id, name, email, department, bio, profile_pic, courses_count, students_count, archived_by, archive_reason) 
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $archive_stmt = $conn->prepare($archive_query);
            
            $admin_id = $_SESSION['user_id']; // Assuming user_id is stored in session
            $reason = isset($_POST['archive_reason']) ? $_POST['archive_reason'] : "Teacher account removed from system";
            
            $archive_stmt->bind_param("isssssiiis", 
                $teacher_id, 
                $teacher_data['name'], 
                $teacher_data['email'], 
                $teacher_data['department'], 
                $teacher_data['bio'], 
                $teacher_data['profile_pic'], 
                $courses_count, 
                $students_count, 
                $admin_id, 
                $reason
            );
            $archive_stmt->execute();
            $archive_stmt->close();
            
            // Update the original teacher record to mark as archived
            $update_teacher_query = "UPDATE teachers SET is_archived = 1, status = 'archived' WHERE id = ?";
            $update_teacher_stmt = $conn->prepare($update_teacher_query);
            $update_teacher_stmt->bind_param("i", $teacher_id);
            $update_teacher_stmt->execute();
            $update_teacher_stmt->close();
            
            // Commit the transaction
            $conn->commit();
            
            $_SESSION['success'] = "Teacher '$teacher_name' has been archived successfully. Their courses remain visible but they cannot modify them.";
            header("Location: manage_users.php");
            exit();
            
        } catch (Exception $e) {
            // Rollback the transaction if any query fails
            $conn->rollback();
            $_SESSION['error'] = "Error archiving teacher: " . $e->getMessage();
            header("Location: manage_users.php");
            exit();
        }
    } else {
        // If not confirmed, redirect back
        header("Location: manage_users.php");
        exit();
    }
}

// Check if any courses have been reassigned
$reassigned = isset($_GET['reassigned']) && $_GET['reassigned'] == 1;

// Refresh course list if some were already reassigned
if ($reassigned) {
    $courses_stmt = $conn->prepare($courses_query);
    $courses_stmt->bind_param("i", $teacher_id);
    $courses_stmt->execute();
    $courses = $courses_stmt->get_result();
    $courses_array = [];
    while ($course = $courses->fetch_assoc()) {
        $courses_array[] = $course;
    }
    $courses_stmt->close();
}

// Get summary of data that will be deleted
$courses_count = $conn->query("SELECT COUNT(*) as count FROM courses WHERE teacher_id = $teacher_id")->fetch_assoc()['count'];
$students_count = $conn->query("SELECT COUNT(DISTINCT student_id) as count FROM enrollments e JOIN courses c ON e.course_id = c.id WHERE c.teacher_id = $teacher_id")->fetch_assoc()['count'];
$enrollments_count = $conn->query("SELECT COUNT(*) as count FROM enrollments e JOIN courses c ON e.course_id = c.id WHERE c.teacher_id = $teacher_id")->fetch_assoc()['count'];
$certificates_count = $conn->query("SELECT COUNT(*) as count FROM certificates cert JOIN courses c ON cert.course_id = c.id WHERE c.teacher_id = $teacher_id")->fetch_assoc()['count'];
?>

<!DOCTYPE html>
<html>
<head>
    <title>Archive Teacher - Admin Dashboard</title>
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
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        header {
            background-color: #2c3e50;
            color: white;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-radius: 8px 8px 0 0;
            margin: -20px -20px 20px;
        }
        h1, h2, h3 {
            color: #2c3e50;
        }
        header h1 {
            color: white;
            margin: 0;
            font-size: 24px;
        }
        .warning-box {
            background-color: #fff5f5;
            border: 1px solid #f5c6cb;
            border-left: 5px solid #dc3545;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .success-box {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            border-left: 5px solid #28a745;
            color: #155724;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .info-box {
            background-color: #e2f0fd;
            border: 1px solid #b8daff;
            border-left: 5px solid #0d6efd;
            color: #084298;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .warning-icon {
            font-weight: bold;
            margin-right: 10px;
        }
        .btn-group {
            display: flex;
            justify-content: space-between;
            margin-top: 30px;
        }
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            font-size: 16px;
            font-weight: 500;
            display: inline-block;
            text-align: center;
        }
        .btn-danger {
            background-color: #dc3545;
            color: white;
        }
        .btn-danger:hover {
            background-color: #c82333;
        }
        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }
        .btn-secondary:hover {
            background-color: #5a6268;
        }
        .btn-primary {
            background-color: #0d6efd;
            color: white;
        }
        .btn-primary:hover {
            background-color: #0b5ed7;
        }
        .data-summary {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .data-summary h3 {
            margin-top: 0;
        }
        .data-summary ul {
            margin-bottom: 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        table, th, td {
            border: 1px solid #ddd;
        }
        th, td {
            padding: 12px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        select {
            width: 100%;
            padding: 8px;
            border-radius: 4px;
            border: 1px solid #ddd;
        }
        textarea {
            width: 100%;
            padding: 8px;
            border-radius: 4px;
            border: 1px solid #ddd;
            min-height: 100px;
            resize: vertical;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>Archive Teacher: <?php echo htmlspecialchars($teacher_name); ?></h1>
        </header>
        
        <?php if ($reassigned): ?>
            <div class="success-box">
                <span class="warning-icon">✓</span>
                <strong>Courses have been reassigned successfully.</strong> You can now proceed with archiving the teacher or reassign the remaining courses.
            </div>
        <?php endif; ?>
        
        <?php if (count($courses_array) > 0): ?>
            <div class="info-box">
                <span class="warning-icon">ℹ️</span>
                <strong>Course Reassignment Required:</strong> Before archiving this teacher, please reassign their courses to existing teachers to preserve student progress.
            </div>
            
            <h3>Reassign Courses (<?php echo count($courses_array); ?> total)</h3>
            
            <form method="POST" action="">
                <table>
                    <thead>
                        <tr>
                            <th>Course Title</th>
                            <th>Assign To</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($courses_array as $course): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($course['title']); ?></td>
                                <td>
                                    <select name="course_assignments[<?php echo $course['id']; ?>]" required>
                                        <option value="">-- Select Teacher --</option>
                                        <?php 
                                        $other_teachers->data_seek(0);
                                        while ($teacher = $other_teachers->fetch_assoc()): 
                                        ?>
                                            <option value="<?php echo $teacher['id']; ?>"><?php echo htmlspecialchars($teacher['name']); ?></option>
                                        <?php endwhile; ?>
                                    </select>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <div class="btn-group">
                    <a href="manage_users.php" class="btn btn-secondary">Cancel</a>
                    <button type="submit" name="reassign_courses" class="btn btn-primary">Reassign Courses</button>
                </div>
            </form>
            
        <?php else: ?>
            <!-- If all courses have been reassigned or there were none to begin with -->
            <div class="warning-box">
                <span class="warning-icon">⚠️</span>
                <strong>Warning:</strong> You are about to archive teacher "<?php echo htmlspecialchars($teacher_name); ?>". 
                <?php if ($reassigned): ?>
                    All courses have been reassigned successfully.
                <?php else: ?>
                    This teacher has no courses to reassign.
                <?php endif; ?>
                This teacher will no longer be able to modify their courses but they will still appear in their dashboard.
            </div>
            
            <?php if ($courses_count == 0): ?>
                <p>This teacher has no courses or all courses have been successfully reassigned.</p>
            <?php else: ?>
                <div class="data-summary">
                    <h3>After archiving:</h3>
                    <ul>
                        <li>The teacher will still see <strong><?php echo $courses_count; ?></strong> courses in their dashboard</li>
                        <li>They will not be able to modify any courses or create new ones</li>
                        <li>The <strong><?php echo $enrollments_count; ?></strong> enrollments from <strong><?php echo $students_count; ?></strong> students will remain intact</li>
                        <li>All <strong><?php echo $certificates_count; ?></strong> certificates will remain valid</li>
                    </ul>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="archive_reason">Reason for archiving (optional):</label>
                    <textarea id="archive_reason" name="archive_reason" placeholder="Please provide a reason for archiving this teacher account..."></textarea>
                </div>
                
                <p>Are you absolutely sure you want to proceed with archiving this teacher?</p>
                
                <div class="btn-group">
                    <input type="hidden" name="confirm_delete" value="yes">
                    <a href="manage_users.php" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-danger">Yes, Archive Teacher</button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>

<?php $conn->close(); ?>