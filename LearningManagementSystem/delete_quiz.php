<?php
session_start();
include 'config.php';

// Initialize debug info variable
$debug_info = "";

// Check if the user is logged in and is a teacher
if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'teacher') {
    header("Location: login.php");
    exit();
}

$teacher_id = $_SESSION['user'];

// Check if quiz ID is provided and is a number
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "Invalid quiz ID.";
    header("Location: teacher_dashboard.php");
    exit();
}

$quiz_id = intval($_GET['id']);

// First, verify that the quiz belongs to this teacher
$verify_query = $conn->prepare("SELECT q.*, c.title as course_title, c.id as course_id 
                               FROM quizzes q 
                               JOIN courses c ON q.course_id = c.id 
                               WHERE q.id = ? AND q.teacher_id = ?");
$verify_query->bind_param("ii", $quiz_id, $teacher_id);
$verify_query->execute();
$result = $verify_query->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error'] = "Quiz not found or you don't have permission to delete it.";
    header("Location: teacher_dashboard.php");
    exit();
}

$quiz = $result->fetch_assoc();
$course_id = $quiz['course_id'];
$quiz_title = $quiz['title'];
$course_title = $quiz['course_title'];

// Debug information
$debug_info = "";

// If a confirmation is submitted, delete the quiz
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {
    // Debug information
    $debug_info .= "Delete confirmation received for quiz ID: $quiz_id<br>";
    
    // Begin transaction to ensure data integrity
    $conn->begin_transaction();
    
    try {
        // Check if each table exists before trying to delete from it
        // First check quiz_attempts table
        $table_check = $conn->query("SHOW TABLES LIKE 'quiz_attempts'");
        if ($table_check->num_rows > 0) {
            // Table exists, proceed with deletion
            $delete_attempts = $conn->prepare("DELETE FROM quiz_attempts WHERE quiz_id = ?");
            $delete_attempts->bind_param("i", $quiz_id);
            $delete_attempts->execute();
            $debug_info .= "Deleted " . $delete_attempts->affected_rows . " quiz attempts<br>";
        } else {
            $debug_info .= "Table 'quiz_attempts' doesn't exist, skipping this step<br>";
        }
        
        // Check quiz_answers table
        $table_check = $conn->query("SHOW TABLES LIKE 'quiz_answers'");
        if ($table_check->num_rows > 0) {
            // Check if we need to use a subquery or if we can delete directly
            $question_check = $conn->query("SHOW TABLES LIKE 'quiz_questions'");
            if ($question_check->num_rows > 0) {
                // Both tables exist, use the original query
                $delete_answers = $conn->prepare("DELETE FROM quiz_answers 
                                              WHERE question_id IN (SELECT id FROM quiz_questions WHERE quiz_id = ?)");
                $delete_answers->bind_param("i", $quiz_id);
                $delete_answers->execute();
                $debug_info .= "Deleted " . $delete_answers->affected_rows . " quiz answers<br>";
            } else {
                $debug_info .= "Table 'quiz_questions' doesn't exist, skipping subquery deletion<br>";
            }
        } else {
            $debug_info .= "Table 'quiz_answers' doesn't exist, skipping this step<br>";
        }
        
        // Check quiz_questions table
        $table_check = $conn->query("SHOW TABLES LIKE 'quiz_questions'");
        if ($table_check->num_rows > 0) {
            $delete_questions = $conn->prepare("DELETE FROM quiz_questions WHERE quiz_id = ?");
            $delete_questions->bind_param("i", $quiz_id);
            $delete_questions->execute();
            $debug_info .= "Deleted " . $delete_questions->affected_rows . " quiz questions<br>";
        } else {
            $debug_info .= "Table 'quiz_questions' doesn't exist, skipping this step<br>";
        }
        
        // Finally delete the quiz itself
        $delete_quiz = $conn->prepare("DELETE FROM quizzes WHERE id = ? AND teacher_id = ?");
        $delete_quiz->bind_param("ii", $quiz_id, $teacher_id);
        $delete_quiz->execute();
        $debug_info .= "Deleted " . $delete_quiz->affected_rows . " quiz record<br>";
        
        // Check if the quiz was actually deleted
        if ($delete_quiz->affected_rows === 0) {
            throw new Exception("Failed to delete the quiz. No rows affected.");
        }
        
        // Commit the transaction
        $conn->commit();
        $debug_info .= "Transaction committed successfully<br>";
        
        $_SESSION['success'] = "Quiz '$quiz_title' has been successfully deleted.";
        
        // Store debug info in session for development purposes
        $_SESSION['debug_info'] = $debug_info;
        
        header("Location: quiz_management.php?course_id=$course_id");
        exit();
    } catch (Exception $e) {
        // Rollback if any query fails
        $conn->rollback();
        $debug_info .= "Error: " . $e->getMessage() . "<br>";
        $_SESSION['error'] = "An error occurred while deleting the quiz: " . $e->getMessage();
        
        // Store debug info in session for development purposes
        $_SESSION['debug_info'] = $debug_info;
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Delete Quiz</title>
    <link rel="stylesheet" href="assets/style.css">
    <style>
        .delete-container {
            max-width: 600px;
            margin: 50px auto;
            padding: 20px;
            background-color: #fff;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .warning {
            background-color: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .error {
            background-color: #f8d7da;
            color: #721c24;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .success {
            background-color: #d4edda;
            color: #155724;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .btn-container {
            display: flex;
            justify-content: space-between;
            margin-top: 20px;
        }
        .btn-danger {
            background-color: #dc3545;
            color: white;
            cursor: pointer;
        }
        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }
        .debug-info {
            margin-top: 20px;
            padding: 10px;
            background-color: #f8f9fa;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-family: monospace;
            font-size: 12px;
            white-space: pre-wrap;
            word-break: break-all;
        }
    </style>
</head>
<body>
    <div class="delete-container">
        <h2>Delete Quiz</h2>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="error">
                <?php 
                    echo htmlspecialchars($_SESSION['error']); 
                    unset($_SESSION['error']);
                ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="success">
                <?php 
                    echo htmlspecialchars($_SESSION['success']); 
                    unset($_SESSION['success']);
                ?>
            </div>
        <?php endif; ?>
        
        <div class="warning">
            <p><strong>Warning:</strong> You are about to delete the quiz "<strong><?php echo htmlspecialchars($quiz_title); ?></strong>" 
            from the course "<strong><?php echo htmlspecialchars($course_title); ?></strong>".</p>
            <p>This action cannot be undone. All questions, student attempts, and results associated with this quiz will be permanently deleted.</p>
        </div>
        
        <form method="post" action="delete_quiz.php?id=<?php echo $quiz_id; ?>">
            <p>Are you sure you want to proceed with deletion?</p>
            
            <div class="btn-container">
                <a href="quiz_management.php?course_id=<?php echo $course_id; ?>" class="btn btn-secondary">Cancel</a>
                <button type="submit" name="confirm_delete" value="1" class="btn btn-danger">Yes, Delete Quiz</button>
            </div>
        </form>
        
        <?php if (!empty($debug_info)): ?>
        <div class="debug-info">
            <h3>Debug Information</h3>
            <pre><?php echo $debug_info; ?></pre>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>