<?php
session_start();
include 'config.php';

if (!isset($_SESSION['teacher_id'])) {
    die("Error: Teacher ID is not set in session.");
}

$teacher_id = $_SESSION['teacher_id'];
$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $title = $_POST['title'];
    $description = $_POST['description'];

    // Check if this teacher already has a course with the same title
    $check_stmt = $conn->prepare("SELECT id FROM courses WHERE teacher_id = ? AND title = ?");
    $check_stmt->bind_param("is", $teacher_id, $title);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    
    if ($result->num_rows > 0) {
        $error = "You have already uploaded a course with this title. Please use a different title.";
    } else {
        if (isset($_FILES["videos"]) && count($_FILES["videos"]["name"]) > 0) {
            $uploaded_files = [];
            $original_filenames = []; // Add array to store original filenames

            for ($i = 0; $i < count($_FILES["videos"]["name"]); $i++) {
                if ($_FILES["videos"]["error"][$i] == 0) {
                    $video_name = basename($_FILES["videos"]["name"][$i]);
                    $video_tmp = $_FILES["videos"]["tmp_name"][$i];
                    $video_path = "uploads/" . $video_name;

                    if (move_uploaded_file($video_tmp, $video_path)) {
                        $uploaded_files[] = $video_path;
                        $original_filenames[] = pathinfo($video_name, PATHINFO_FILENAME); // Store filename without extension
                    } else {
                        $error = "Error uploading file: " . $_FILES["videos"]["name"][$i];
                    }
                }
            }

            if (!empty($uploaded_files)) {
                // Insert course details into 'courses' table with both teacher_id and original_author_id
                $stmt = $conn->prepare("INSERT INTO courses (teacher_id, original_author_id, title, description) VALUES (?, ?, ?, ?)");
                // When a teacher uploads a new course, they are both the teacher and the original author
                $stmt->bind_param("iiss", $teacher_id, $teacher_id, $title, $description);
                if ($stmt->execute()) {
                    $course_id = $stmt->insert_id; // Get the last inserted course ID
                    
                    // Insert each video into 'course_videos' table
                    $stmt = $conn->prepare("INSERT INTO course_videos (course_id, video_title, video_path, video_sequence) VALUES (?, ?, ?, ?)");
                    foreach ($uploaded_files as $index => $file_path) {
                        // Use the original filename as the video title instead of course title
                        $video_title = $original_filenames[$index];
                        $stmt->bind_param("issi", $course_id, $video_title, $file_path, $index);
                        $stmt->execute();
                    }
                    
                    $success = "Course and videos uploaded successfully!";
                    echo "<script>alert('Course and videos uploaded successfully!'); window.location.href='upload_course.php';</script>";
                } else {
                    $error = "Error: " . $stmt->error;
                }
            } else {
                $error = "No valid video files uploaded.";
            }
        } else {
            $error = "No video file selected.";
        }
    }
}

// Get courses by this teacher for display
$courses_stmt = $conn->prepare("SELECT title FROM courses WHERE teacher_id = ? ORDER BY id DESC LIMIT 5");
$courses_stmt->bind_param("i", $teacher_id);
$courses_stmt->execute();
$courses_result = $courses_stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Course</title>
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background: #f4f4f4;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px 0;
        }

        .container {
            background: #fff;
            padding: 30px;
            border-radius: 8px;
            width: 400px;
            box-shadow: 0px 4px 10px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        h2 {
            color: #333;
            margin-bottom: 20px;
        }

        label {
            display: block;
            font-weight: 600;
            text-align: left;
            margin: 10px 0 5px;
            color: #555;
        }

        input, textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 14px;
            outline: none;
            transition: 0.3s;
        }

        input:focus, textarea:focus {
            border-color: #007bff;
            box-shadow: 0px 0px 5px rgba(0, 123, 255, 0.5);
        }

        textarea {
            height: 80px;
            resize: none;
        }

        input[type="file"] {
            padding: 5px;
            border: none;
        }

        button {
            width: 100%;
            padding: 12px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            margin-top: 15px;
            transition: 0.3s;
        }

        button:hover {
            background: #0056b3;
        }

        .message {
            margin-bottom: 10px;
        }

        .success {
            color: #28a745;
            padding: 10px;
            background-color: #d4edda;
            border-radius: 5px;
            margin-bottom: 15px;
        }

        .error {
            color: #dc3545;
            padding: 10px;
            background-color: #f8d7da;
            border-radius: 5px;
            margin-bottom: 15px;
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
<body style="background: linear-gradient(to right, #0d0d2b, #3b1f57); margin: 0; padding: 0; min-height: 100vh; display: flex; justify-content: center; align-items: center;">

    <div class="container">
        <h2>Upload Course</h2>

        <div class="message">
            <?php if (!empty($success)) echo "<p class='success'>$success</p>"; ?>
            <?php if (!empty($error)) echo "<p class='error'>$error</p>"; ?>
        </div>

        <form action="" method="post" enctype="multipart/form-data">
            <label for="title">Course Title:</label>
            <input type="text" name="title" required>

            <label for="description">Course Description:</label>
            <textarea name="description" required></textarea>

            <label for="video">Upload Video:</label>
            <input type="file" name="videos[]" accept="video/*" multiple required>

            <button type="submit">Upload Course</button>
        </form>
        <br>

        <a href="teacher_dashboard.php" class="back-btn"> Back to Dashboard</a>
        
        

</body>
</html>