<?php
session_start();
include 'config.php';

// Check if the user is logged in and is a teacher
if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'teacher') {
    header("Location: login.php");
    exit();
}

$teacher_id = $_SESSION['user'];
$course_id = isset($_GET['course_id']) ? intval($_GET['course_id']) : 0;

// Fetch course information
$course_query = $conn->prepare("SELECT title FROM courses WHERE id = ? AND teacher_id = ?");
$course_query->bind_param("ii", $course_id, $teacher_id);
$course_query->execute();
$course_result = $course_query->get_result();

if ($course_result->num_rows === 0) {
    header("Location: teacher_dashboard.php");
    exit();
}

$course = $course_result->fetch_assoc();

// Fetch existing quizzes for this course
$quizzes_query = $conn->prepare("SELECT * FROM quizzes WHERE course_id = ? AND teacher_id = ? ORDER BY created_at DESC");
$quizzes_query->bind_param("ii", $course_id, $teacher_id);
$quizzes_query->execute();
$quizzes_result = $quizzes_query->get_result();

// Create a new quiz
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_quiz'])) {
    $title = trim($_POST['title']);
    $duration = intval($_POST['duration']);
    
    if (empty($title) || $duration <= 0) {
        $error = "Please provide a valid title and duration.";
    } else {
        $stmt = $conn->prepare("INSERT INTO quizzes (title, course_id, teacher_id, duration) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("siii", $title, $course_id, $teacher_id, $duration);
        
        if ($stmt->execute()) {
            $new_quiz_id = $conn->insert_id;
            header("Location: edit_quiz.php?id=$new_quiz_id");
            exit();
        } else {
            $error = "Failed to create quiz. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Assessment Management - <?php echo htmlspecialchars($course['title']); ?></title>
    <style>
        /* General Styles */
body {
    font-family: Arial, sans-serif;
    background-color: #f4f7f6;
    margin: 0;
    padding: 0;
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 100vh;
}

/* Dashboard Container */
.dashboard-container {
    background: #fff;
    width: 80%;
    max-width: 900px;
    padding: 20px;
    box-shadow: 0px 4px 10px rgba(0, 0, 0, 0.1);
    border-radius: 8px;
}

h2 {
    text-align: center;
    color: #333;
}

/* Alert Message */
.alert {
    padding: 10px;
    border-radius: 5px;
    margin-bottom: 10px;
}

.alert-danger {
    background-color: #ffdddd;
    color: #d9534f;
    border-left: 5px solid #d9534f;
}

/* Form Styling */
form {
    background: #fff;
    padding: 15px;
    border-radius: 5px;
    box-shadow: 0 0 5px rgba(0, 0, 0, 0.1);
}

.form-group {
    margin-bottom: 15px;
}

label {
    display: block;
    font-weight: bold;
    margin-bottom: 5px;
    color: #444;
}

input, select {
    width: 100%;
    padding: 8px;
    border: 1px solid #ccc;
    border-radius: 5px;
    font-size: 14px;
}

button {
    background: #28a745;
    color: #fff;
    padding: 10px 15px;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    font-size: 16px;
}

button:hover {
    background: #218838;
}

/* Table Styling */
.data-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
}

.data-table th, .data-table td {
    padding: 10px;
    text-align: left;
    border-bottom: 1px solid #ddd;
}

.data-table th {
    background-color: #28a745;
    color: white;
}

.data-table tr:hover {
    background-color: #f1f1f1;
}

/* Buttons */
.btn {
    text-decoration: none;
    padding: 8px 12px;
    border-radius: 5px;
    color: white;
    background-color: #007bff;
    display: inline-block;
    font-size: 14px;
}

.btn:hover {
    background-color: #0056b3;
}

.btn-danger {
    background-color: #dc3545;
}

.btn-danger:hover {
    background-color: #c82333;
}

/* Responsive Design */
@media (max-width: 768px) {
    .dashboard-container {
        width: 95%;
    }

    .data-table th, .data-table td {
        padding: 8px;
    }
}

    </style>
</head>
<body>
    <div class="dashboard-container">
        <h2>Assessment Management for <?php echo htmlspecialchars($course['title']); ?></h2>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <div class="action-panel">
            <h3>Create New Assessment</h3>
            <form method="post" action="">
                <div class="form-group">
                    <label for="title">Assessment Title:</label>
                    <input type="text" id="title" name="title" required class="form-control">
                </div>
                
                <div class="form-group">
                    <label for="duration">Duration (minutes):</label>
                    <input type="number" id="duration" name="duration" min="1" required class="form-control">
                </div>
                
                <button type="submit" name="create_quiz" class="btn">Create Assessment</button>
            </form>
        </div>
        
        <div class="content-panel">
            <h3>Existing Assessments</h3>
            
            <?php if ($quizzes_result->num_rows > 0): ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Duration</th>
                            <th>Created</th>
                            <th>Questions</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($quiz = $quizzes_result->fetch_assoc()): ?>
                            <?php
                            // Count questions
                            $question_count_query = $conn->prepare("SELECT COUNT(*) as count FROM quiz_questions WHERE quiz_id = ?");
                            $question_count_query->bind_param("i", $quiz['id']);
                            $question_count_query->execute();
                            $question_count = $question_count_query->get_result()->fetch_assoc()['count'];
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($quiz['title']); ?></td>
                                <td><?php echo $quiz['duration']; ?> minutes</td>
                                <td><?php echo date('M j, Y', strtotime($quiz['created_at'])); ?></td>
                                <td><?php echo $question_count; ?></td>
                                <td>
                                    <a href="edit_quiz.php?id=<?php echo $quiz['id']; ?>" class="btn btn-sm">Edit</a>
                                    
                                    <a href="delete_quiz.php?id=<?php echo $quiz['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this quiz?')">Delete</a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No quizzes created yet.</p>
            <?php endif; ?>
        </div>
        
        <div class="action-buttons">
            <a href="teacher_dashboard.php?id=<?php echo $course_id; ?>" class="btn">Back to Course</a>
        </div>
    </div>
</body>
</html>