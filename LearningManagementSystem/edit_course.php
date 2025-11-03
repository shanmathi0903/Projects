<?php
include('config.php');
session_start();

// Check if the teacher is logged in
if (!isset($_SESSION['teacher_id'])) {
    die("Access denied. Please log in as a teacher.");
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid course ID.");
}

$course_id = $_GET['id'];
$teacher_id = $_SESSION['teacher_id'];

// Check teacher's archive status first
$teacher_status_query = $conn->prepare("SELECT status, is_archived FROM teachers WHERE id = ?");
$teacher_status_query->bind_param("i", $teacher_id);
$teacher_status_query->execute();
$teacher_status_result = $teacher_status_query->get_result();
$teacher_status = $teacher_status_result->fetch_assoc();

// Determine if teacher is archived
$is_archived = ($teacher_status['status'] === 'archived' || $teacher_status['is_archived'] == 1 || $teacher_status['status'] === 'suspended');

// Fetch course details - allow access if user is either current teacher OR original author
$stmt = $conn->prepare("SELECT * FROM courses WHERE id = ? AND (teacher_id = ? OR original_author_id = ?)");
$stmt->bind_param("iii", $course_id, $teacher_id, $teacher_id);
$stmt->execute();
$result = $stmt->get_result();
$course = $result->fetch_assoc();

if (!$course) {
    die("Course not found or you don't have permission to view it.");
}

// Fetch all videos for the course
$video_stmt = $conn->prepare("SELECT * FROM course_videos WHERE course_id = ?");
$video_stmt->bind_param("i", $course_id);
$video_stmt->execute();
$videos = $video_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Check if this teacher is the current assigned teacher (not just original author)
$is_current_teacher = ($course['teacher_id'] == $teacher_id);

// Only process form submissions if: 1) teacher is not archived AND 2) they are the current assigned teacher
if (!$is_archived && $is_current_teacher) {
    // Handle course update
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_course'])) {
        $title = isset($_POST['title']) ? trim($_POST['title']) : $course['title'];
        $description = isset($_POST['description']) ? trim($_POST['description']) : $course['description'];

        // Only update if values are provided
        if (!empty($title) && !empty($description)) {
            // Update course details
            $update_stmt = $conn->prepare("UPDATE courses SET title = ?, description = ? WHERE id = ? AND teacher_id = ?");
            $update_stmt->bind_param("ssii", $title, $description, $course_id, $teacher_id);

            if ($update_stmt->execute()) {
                echo "<script>alert('Course updated successfully!'); window.location.href='teacher_dashboard.php';</script>";
            } else {
                echo "Error updating course: " . $conn->error;
            }
        } else {
            // Skip update if fields are empty
            echo "<script>alert('No changes made to course details.'); window.location.href='teacher_dashboard.php';</script>";
        }
    }

    // Handle video upload
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['upload_video'])) {
        if (!empty($_FILES['video']['name'])) {
            $target_dir = "uploads/videos/";
            $target_file = $target_dir . basename($_FILES["video"]["name"]);
            $videoFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

            // Check if file is a valid video format
            $allowed_types = ["mp4", "avi", "mov", "wmv"];
            if (!in_array($videoFileType, $allowed_types)) {
                die("Invalid file format. Allowed formats: MP4, AVI, MOV, WMV.");
            }

            if (move_uploaded_file($_FILES["video"]["tmp_name"], $target_file)) {
                $video_title = basename($_FILES["video"]["name"]);
                $insert_video_stmt = $conn->prepare("INSERT INTO course_videos (course_id, video_title, video_path) VALUES (?, ?, ?)");
                $insert_video_stmt->bind_param("iss", $course_id, $video_title, $target_file);

                if ($insert_video_stmt->execute()) {
                    echo "<script>alert('Video uploaded successfully!'); window.location.href='edit_course.php?id=$course_id';</script>";
                } else {
                    die("Error inserting video: " . $conn->error);
                }
            } else {
                die("Error uploading video.");
            }
        }
    }

    // Handle video deletion
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_video'])) {
        if (isset($_POST['video_id']) && is_numeric($_POST['video_id'])) {
            $video_id = $_POST['video_id'];

            // Fetch the video path
            $video_stmt = $conn->prepare("SELECT video_path FROM course_videos WHERE id = ?");
            $video_stmt->bind_param("i", $video_id);
            $video_stmt->execute();
            $video_result = $video_stmt->get_result()->fetch_assoc();

            if ($video_result) {
                $video_path = $video_result['video_path'];

                // Delete video file from storage
                if (file_exists($video_path)) {
                    unlink($video_path);
                }

                // Remove video from database
                $delete_stmt = $conn->prepare("DELETE FROM course_videos WHERE id = ?");
                $delete_stmt->bind_param("i", $video_id);
                $delete_stmt->execute();

                echo "<script>alert('Video deleted successfully!'); window.location.href='edit_course.php?id=$course_id';</script>";
            } else {
                die("Video not found.");
            }
        } else {
            die("Invalid video ID.");
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $is_archived ? "View Course" : "Edit Course"; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <style>
        /* Material Design Style */
        body {
            font-family: 'Roboto', sans-serif;
            background-color: #f5f5f5;
            color: #333;
            margin: 0;
            padding: 0;
            line-height: 1.6;
        }

        .container {
            background: #ffffff;
            width: 80%;
            max-width: 1100px;
            margin: 30px auto;
            border-radius: 4px;
            box-shadow: 0 3px 6px rgba(0,0,0,0.16), 0 3px 6px rgba(0,0,0,0.23);
            overflow: hidden;
        }

        h2 {
            background: #6200ee;
            color: white;
            margin: 0;
            padding: 24px;
            font-size: 24px;
            font-weight: 400;
        }

        h3 {
            color: #6200ee;
            font-size: 20px;
            margin: 24px 0 16px;
            padding: 0 24px;
            font-weight: 500;
        }

        form {
            padding: 24px;
        }

        label {
            font-size: 14px;
            color: #666;
            margin-bottom: 8px;
            display: block;
        }

        input[type="text"], textarea {
            width: 100%;
            padding: 12px;
            border: none;
            border-bottom: 1px solid #ddd;
            background-color: #f7f7f7;
            border-radius: 4px 4px 0 0;
            font-size: 16px;
            margin-bottom: 24px;
            transition: 0.3s;
        }

        input[type="text"]:focus, textarea:focus {
            outline: none;
            border-bottom: 2px solid #6200ee;
            margin-bottom: 23px;
        }

        input[type="file"] {
            margin-bottom: 24px;
        }

        button {
            background-color: #6200ee;
            color: white;
            border: none;
            padding: 10px 24px;
            border-radius: 4px;
            text-transform: uppercase;
            font-weight: 500;
            font-size: 14px;
            cursor: pointer;
            transition: 0.3s;
            letter-spacing: 0.5px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }

        button:hover {
            background-color: #5100c3;
            box-shadow: 0 4px 8px rgba(0,0,0,0.3);
        }

        .delete-btn {
            background-color: #f44336;
        }

        .delete-btn:hover {
            background-color: #d32f2f;
        }

        .video-container {
            background: #fff;
            margin: 16px 24px;
            padding: 16px;
            border-radius: 4px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.12), 0 1px 2px rgba(0,0,0,0.24);
        }

        video {
            width: 100%;
            border-radius: 4px;
        }

        .section {
            margin-bottom: 30px;
            padding: 20px;
            background: #fff;
            border-radius: 4px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.12), 0 1px 2px rgba(0,0,0,0.24);
        }

        .back-btn {
            display: inline-block;
            margin: 20px;
            color: #6200ee;
            text-decoration: none;
            font-weight: 500;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            background-color:rgb(221, 228, 227);
        }

        .back-btn:hover {
            color:rgb(103, 100, 106);
        }

        .read-only {
            background-color: #f7f7f7;
            cursor: not-allowed;
            color: #666;
        }

        .archived-notice {
            margin: 20px;
            padding: 16px;
            border-radius: 4px;
            font-weight: 500;
            text-align: center;
        }

        .archived-notice.restricted {
            background-color: #ffebee;
            color: #d32f2f;
        }

        .archived-notice.view-only {
            background-color: #e3f2fd;
            color: #1976d2;
        }

        @media screen and (max-width: 768px) {
            .container {
                width: 95%;
            }
            
            h2, h3, form {
                padding: 16px;
            }
            
            .video-container {
                margin: 16px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h2><?php echo $is_archived ? "View Course" : "Edit Course"; ?></h2>
        
        <?php if ($is_archived): ?>
        <div class="archived-notice restricted">
            <strong>Account Restricted:</strong> Your account is currently archived. You can view existing content but cannot make changes.
        </div>
        <?php elseif (!$is_current_teacher): ?>
        <div class="archived-notice view-only">
            <strong>View Only Mode:</strong> You are viewing this course as its original author. Only the current assigned teacher can make changes.
        </div>
        <?php endif; ?>
        
        <a href="teacher_dashboard.php" class="back-btn">Back to Dashboard</a>
        
        <div class="section">
            <!-- Course Details Section -->
            <h3>Course Details</h3>
            <?php if (!$is_archived && $is_current_teacher): ?>
                <!-- Editable Form for Active Current Teachers -->
                <form method="post">
                    <label>Title:</label>
                    <input type="text" name="title" value="<?= htmlspecialchars($course['title']) ?>" required>

                    <label>Description:</label>
                    <textarea name="description" required><?= htmlspecialchars($course['description']) ?></textarea>
                    
                    <button type="submit" name="update_course">Update Course</button>
                </form>
            <?php else: ?>
                <!-- Read-only View for Archived Teachers or Original Authors -->
                <div>
                    <label>Title:</label>
                    <input type="text" value="<?= htmlspecialchars($course['title']) ?>" readonly class="read-only">

                    <label>Description:</label>
                    <textarea readonly class="read-only"><?= htmlspecialchars($course['description']) ?></textarea>
                </div>
            <?php endif; ?>
        </div>

        <?php if (!$is_archived && $is_current_teacher): ?>
        <div class="section">
            <h3>Upload New Video</h3>
            
            <!-- Video Upload Form -->
            <form method="post" enctype="multipart/form-data">
                <label>Select Video File:</label>
                <input type="file" name="video" accept="video/*" required>
                <button type="submit" name="upload_video">Upload Video</button>
            </form>
        </div>
        <?php endif; ?>

        <div class="section">
            <h3>Course Videos</h3>
            <?php if (!empty($videos)): ?>
                <?php foreach ($videos as $video): ?>
                    <div class="video-container">
                        <video controls>
                            <source src="<?= htmlspecialchars($video['video_path']) ?>" type="video/mp4">
                            Your browser does not support the video tag.
                        </video>
                        <p><strong><?= htmlspecialchars($video['video_title']) ?></strong></p>
                        <?php if (!$is_archived && $is_current_teacher): ?>
                            <form method="post" style="display:inline; padding: 0;">
                                <input type="hidden" name="video_id" value="<?= $video['id'] ?>">
                                <button type="submit" name="delete_video" class="delete-btn" onclick="return confirm('Are you sure you want to delete this video?');">Delete Video</button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p style="text-align: center; color: #666;">No videos uploaded for this course.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>