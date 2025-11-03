<?php 
session_start(); 
include 'config.php';  

if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'teacher') {
    header("Location: login.php");
    exit(); 
}

$teacher_id = $_SESSION['user'];

// First get all courses taught by this teacher for the dropdown
$courses_query = "
    SELECT id, title 
    FROM courses 
    WHERE teacher_id = ? 
    ORDER BY title
";
$courses_stmt = $conn->prepare($courses_query);
$courses_stmt->bind_param("i", $teacher_id);
$courses_stmt->execute();
$courses_result = $courses_stmt->get_result();

// Build the main query to fetch student progress AND certificate status
$query = "
    SELECT 
        students.id AS student_id,
        students.name AS student_name,
        students.email AS student_email,
        courses.id AS course_id,
        courses.title AS course_title,
        (
    SELECT COUNT(DISTINCT cv.id) 
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
    WHERE courses.teacher_id = ?
";

// Add course filter if specified
if (isset($_GET['course_filter']) && !empty($_GET['course_filter']) && is_numeric($_GET['course_filter'])) {
    $query .= " AND courses.id = ?";
    $params = [$teacher_id, $_GET['course_filter']];
    $types = "ii";
} else {
    $params = [$teacher_id];
    $types = "i";
}

// Add ordering
$query .= " ORDER BY students.name, courses.title";

// Prepare and execute the main query
$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Student Progress Dashboard</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 20px;
        }
        h2 {
            color: #333;
            border-bottom: 2px solid #4CAF50;
            padding-bottom: 10px;
        }
        /* All your existing styles here */
        
        /* Adding style for back button */
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
            margin-bottom: 20px;
        }
        .back-btn:hover {
            background-color: #333;
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
        .filters {
            margin-bottom: 20px;
            padding: 15px;
            background-color: #f9f9f9;
            border-radius: 5px;
            border: 1px solid #ddd;
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
        .action-btn {
            padding: 5px 10px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            text-decoration: none;
            font-size: 12px;
            display: inline-block;
            margin: 2px 0;
        }
        .action-btn:hover {
            background-color: #45a049;
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
}margin-top: 15px;
        }
        .results-summary {
            margin: 15px 0;
            font-weight: bold;
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
<a href="teacher_dashboard.php" class="back-btn">← Back to Dashboard</a>
    <h2>Student Progress in Your Courses</h2>
    
    <div class="filters">
        <h3>Filters</h3>
        <form method="GET" action="">
            <div class="filter-group">
                <label for="course_filter">Select Course:</label>
                <select name="course_filter" id="course_filter">
                    <option value="">All </option>
                    <?php while ($course = $courses_result->fetch_assoc()) { ?>
                        <option value="<?php echo $course['id']; ?>" <?php echo (isset($_GET['course_filter']) && $_GET['course_filter'] == $course['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($course['title']); ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label for="certificate_status">Certificate Status:</label>
                <select name="certificate_status" id="certificate_status">
                    <option value="">All </option>
                    <option value="issued" <?php echo isset($_GET['certificate_status']) && $_GET['certificate_status'] == 'issued' ? 'selected' : ''; ?>>Certificate Issued</option>
                    <option value="eligible" <?php echo isset($_GET['certificate_status']) && $_GET['certificate_status'] == 'eligible' ? 'selected' : ''; ?>>Eligible (Not Claimed)</option>
                    <option value="pending" <?php echo isset($_GET['certificate_status']) && $_GET['certificate_status'] == 'pending' ? 'selected' : ''; ?>>Requirements Pending</option>
                </select>
            </div>
            
            <div class="filter-group">
                <label for="student_search">Student Search:</label>
                <input type="text" name="student_search" id="student_search" placeholder="Name or email" value="<?php echo isset($_GET['student_search']) ? htmlspecialchars($_GET['student_search']) : ''; ?>">
            </div>
            
            <div class="filter-buttons">
                <button type="submit" class="action-btn">Apply Filters</button>
                <a href="manage_students.php" class="action-btn" style="background-color: #f44336;">Clear Filters</a>
            </div>
        </form>
    </div>

    <?php
    // Count filtered results
    $filtered_count = 0;
    $original_data = [];
    
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $original_data[] = $row;
            
            // Apply client-side filters
            if (isset($_GET['certificate_status']) && !empty($_GET['certificate_status'])) {
                $videos_completed = ($row['total_videos'] > 0 && $row['completed_videos'] == $row['total_videos']);
                $quiz_passed = empty($row['passing_score']) || (!empty($row['quiz_score']) && $row['quiz_score'] >= $row['passing_score']);
                
                if ($_GET['certificate_status'] == 'issued' && empty($row['certificate_number'])) {
                    continue;
                } elseif ($_GET['certificate_status'] == 'eligible' && 
                    (!$videos_completed || !$quiz_passed || !empty($row['certificate_number']))) {
                    continue;
                } elseif ($_GET['certificate_status'] == 'pending' && 
                    ($videos_completed && $quiz_passed)) {
                    continue;
                }
            }
            
            // Apply student search filter
            if (isset($_GET['student_search']) && !empty($_GET['student_search'])) {
                if (stripos($row['student_name'], $_GET['student_search']) === false && 
                    stripos($row['student_email'], $_GET['student_search']) === false) {
                    continue;
                }
            }
            
            $filtered_count++;
        }
    }
    ?>

    <div class="results-summary">
        Found <?php echo $filtered_count; ?> student<?php echo $filtered_count != 1 ? 's' : ''; ?> matching your filters
    </div>

    <?php if ($filtered_count > 0) { ?>
        <table>
            <tr>
                <th>Student Name</th>
                <th>Email</th>
                <th>Course</th>
                <th>Course Progress</th>
                <th>Assessment Status</th>
                <th>Certificate Status</th>
            </tr>
            <?php 
            foreach ($original_data as $row) {
                // Apply client-side filters again
                if (isset($_GET['certificate_status']) && !empty($_GET['certificate_status'])) {
                    $videos_completed = ($row['total_videos'] > 0 && $row['completed_videos'] == $row['total_videos']);
                    $quiz_passed = empty($row['passing_score']) || (!empty($row['quiz_score']) && $row['quiz_score'] >= $row['passing_score']);
                    
                    if ($_GET['certificate_status'] == 'issued' && empty($row['certificate_number'])) {
                        continue;
                    } elseif ($_GET['certificate_status'] == 'eligible' && 
                        (!$videos_completed || !$quiz_passed || !empty($row['certificate_number']))) {
                        continue;
                    } elseif ($_GET['certificate_status'] == 'pending' && 
                        ($videos_completed && $quiz_passed)) {
                        continue;
                    }
                }
                
                // Apply student search filter
                if (isset($_GET['student_search']) && !empty($_GET['student_search'])) {
                    if (stripos($row['student_name'], $_GET['student_search']) === false && 
                        stripos($row['student_email'], $_GET['student_search']) === false) {
                        continue;
                    }
                }
                
                // Calculate progress percentage
                $progress_percentage = $row['total_videos'] > 0
    ? min(100, round(($row['completed_videos'] / $row['total_videos']) * 100, 2))
    : 0;
                    
                // Determine certificate status
                $certificate_status = '';
                $status_class = '';
                if (!empty($row['certificate_number'])) {
                    $certificate_status = '✓ Issued on ' . date('M d, Y', strtotime($row['certificate_date']));
                    $status_class = 'certificate-issued';
                } else {
                    // Check if requirements are met
                    $videos_completed = ($row['total_videos'] > 0 && $row['completed_videos'] == $row['total_videos']);
                    $quiz_passed = empty($row['passing_score']) || (!empty($row['quiz_score']) && $row['quiz_score'] >= $row['passing_score']);
                    
                    if (!$videos_completed) {
                        $certificate_status = '⚠ Videos incomplete';
                        $status_class = 'certificate-pending';
                    } elseif (!$quiz_passed && !empty($row['passing_score'])) {
                        $certificate_status = '⚠ Quiz not passed';
                        $status_class = 'certificate-pending';
                    } else {
                        $certificate_status = '✓ Eligible (not claimed)';
                        $status_class = 'certificate-eligible';
                    }
                }
            ?>
            <tr>
                <td><?php echo htmlspecialchars($row['student_name']); ?></td>
                <td><?php echo htmlspecialchars($row['student_email']); ?></td>
                <td><?php echo htmlspecialchars($row['course_title']); ?></td>
                <td>
                    <div><?php echo $row['completed_videos'] . '/' . $row['total_videos']; ?></div>
                    <div class="progress-bar">
                        <div class="progress" style="width: <?php echo max(5, $progress_percentage); ?>%">
                            <?php echo $progress_percentage . '%'; ?>
                        </div>
                    </div>
                </td>
                <td>
                    <?php 
                    if (!empty($row['quiz_score'])) {
                        echo $row['quiz_score'] . '%';
                        if (!empty($row['passing_score'])) {
                            $pass_status = $row['quiz_score'] >= $row['passing_score'] ? 
                                '<span style="color:green">✓ Passed</span>' : 
                                '<span style="color:red">✗ Failed</span>';
                            echo ' ' . $pass_status;
                            echo '<br>(Required: ' . $row['passing_score'] . '%)';
                        }
                    } else {
                        echo empty($row['passing_score']) ? 'N/A' : 'Not attempted';
                    }
                    ?>
                </td>
                <td class="certificate-status <?php echo $status_class; ?>">
                    <?php echo $certificate_status; ?>
                    <?php if (!empty($row['certificate_number'])) { ?>
                        <br><small>Cert #: <?php echo htmlspecialchars($row['certificate_number']); ?></small>
                    <?php } ?>
                </td>
            </tr>
            <?php } ?>
        </table>
    <?php } else { ?>
        <div class="no-results">
            <p>No students match your filter criteria.</p>
            <p><a href="manage_students.php" class="action-btn">Show All Students</a></p>
        </div>
    <?php } ?>
    
    <script>
        // Add AJAX functionality to update the page when filters change without reloading
        document.addEventListener('DOMContentLoaded', function() {
            // This is a placeholder for potential AJAX implementation
            // For a smoother user experience, you could use JavaScript to filter without page reload
        });
    </script>
</body>
</html>

<?php 
$courses_stmt->close();
$stmt->close(); 
$conn->close(); 
?>