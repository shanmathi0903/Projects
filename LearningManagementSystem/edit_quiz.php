<?php
session_start();
include 'config.php';

// Check if the user is logged in and is a teacher
if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'teacher') {
    header("Location: login.php");
    exit();
}

$teacher_id = $_SESSION['user'];
$quiz_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Fetch quiz information
$quiz_query = $conn->prepare("SELECT q.*, c.title as course_title 
                              FROM quizzes q 
                              JOIN courses c ON q.course_id = c.id 
                              WHERE q.id = ? AND q.teacher_id = ?");
$quiz_query->bind_param("ii", $quiz_id, $teacher_id);
$quiz_query->execute();
$quiz_result = $quiz_query->get_result();

if ($quiz_result->num_rows === 0) {
    header("Location: teacher_dashboard.php");
    exit();
}

$quiz = $quiz_result->fetch_assoc();

// Fetch existing questions
$questions_query = $conn->prepare("SELECT * FROM quiz_questions WHERE quiz_id = ? ORDER BY id");
$questions_query->bind_param("i", $quiz_id);
$questions_query->execute();
$questions_result = $questions_query->get_result();

// Add a new question
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_question'])) {
    $question_type = $_POST['question_type'];
    $question = trim($_POST['question']);
    
    // Check if the question_type field exists in the database
    // If not, we need to alter the table to add it
    $table_check = $conn->query("SHOW COLUMNS FROM quiz_questions LIKE 'question_type'");
    if ($table_check->num_rows === 0) {
        $conn->query("ALTER TABLE quiz_questions ADD COLUMN question_type VARCHAR(20) DEFAULT 'mcq' AFTER quiz_id");
    }
    
    if ($question_type === 'mcq') {
        $option1 = trim($_POST['option1']);
        $option2 = trim($_POST['option2']);
        $option3 = trim($_POST['option3']);
        $option4 = trim($_POST['option4']);
        $correct_option = intval($_POST['correct_option_mcq']);
        
        if (empty($question) || empty($option1) || empty($option2) || $correct_option < 1 || $correct_option > 4) {
            $error = "Please fill in all required fields correctly.";
        } else {
            $stmt = $conn->prepare("INSERT INTO quiz_questions (quiz_id, question_type, question, option1, option2, option3, option4, correct_option) 
                                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("issssssi", $quiz_id, $question_type, $question, $option1, $option2, $option3, $option4, $correct_option);
        }
    } else if ($question_type === 'true_false') {
        $correct_option = intval($_POST['correct_option_tf']);
        
        if (empty($question) || $correct_option < 1 || $correct_option > 2) {
            $error = "Please fill in all required fields correctly.";
        } else {
            $stmt = $conn->prepare("INSERT INTO quiz_questions (quiz_id, question_type, question, option1, option2, option3, option4, correct_option) 
                                    VALUES (?, ?, ?, 'True', 'False', '', '', ?)");
            $stmt->bind_param("issi", $quiz_id, $question_type, $question, $correct_option);
        }
    } else {
        $error = "Invalid question type.";
    }
    
    if (isset($stmt) && $stmt->execute()) {
        header("Location: edit_quiz.php?id=$quiz_id&success=1");
        exit();
    } else if (!isset($error)) {
        $error = "Failed to add question. Please try again. " . $conn->error;
    }
}

// Delete a question
if (isset($_GET['delete_question']) && is_numeric($_GET['delete_question'])) {
    $question_id = intval($_GET['delete_question']);
    
    $verify_query = $conn->prepare("SELECT id FROM quiz_questions WHERE id = ? AND quiz_id = ?");
    $verify_query->bind_param("ii", $question_id, $quiz_id);
    $verify_query->execute();
    
    if ($verify_query->get_result()->num_rows > 0) {
        $delete_stmt = $conn->prepare("DELETE FROM quiz_questions WHERE id = ?");
        $delete_stmt->bind_param("i", $question_id);
        $delete_stmt->execute();
        
        header("Location: edit_quiz.php?id=$quiz_id&deleted=1");
        exit();
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit Quiz - <?php echo htmlspecialchars($quiz['title']); ?></title>
    <style>
        /* General Styles */
body {
    font-family: Arial, sans-serif;
    background-color: #f4f4f4;
    margin: 0;
    padding: 0;
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 100vh;
}

.dashboard-container {
    background: white;
    padding: 20px;
    border-radius: 10px;
    box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
    width: 80%;
    max-width: 800px;
}

h2, h3 {
    text-align: center;
    color: #333;
}

/* Form Styling */
.form-group {
    margin-bottom: 15px;
}

label {
    font-weight: bold;
}

input, textarea, select {
    width: 100%;
    padding: 8px;
    margin-top: 5px;
    border: 1px solid #ccc;
    border-radius: 5px;
}

button {
    background-color: #28a745;
    color: white;
    border: none;
    padding: 10px 15px;
    border-radius: 5px;
    cursor: pointer;
    width: 100%;
    font-size: 16px;
}

button:hover {
    background-color: #218838;
}

/* Alerts */
.alert {
    padding: 10px;
    margin-bottom: 15px;
    border-radius: 5px;
}

.alert-success {
    background-color: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.alert-danger {
    background-color: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

/* Question List */
.question-list {
    margin-top: 20px;
    padding: 10px;
    border: 1px solid #ccc;
    border-radius: 5px;
    background: #f9f9f9;
}

.question-item {
    background: white;
    padding: 10px;
    border-radius: 5px;
    margin-bottom: 10px;
    border: 1px solid #ddd;
}

.question-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-weight: bold;
}

.question-type-badge {
    font-size: 12px;
    background-color: #007bff;
    color: white;
    padding: 3px 6px;
    border-radius: 3px;
    margin-left: 10px;
}

.correct-option {
    font-weight: bold;
    color: green;
}

/* Buttons */
.btn {
    display: inline-block;
    text-align: center;
    padding: 8px 12px;
    border-radius: 5px;
    text-decoration: none;
    font-size: 14px;
    margin-top: 10px;
}

.btn-danger {
    background-color: #dc3545;
    color: white;
}

.btn-danger:hover {
    background-color: #c82333;
}

.btn-sm {
    font-size: 12px;
    padding: 5px 8px;
}

/* Question Type Selection */
.question-type-selector {
    margin-bottom: 20px;
    display: flex;
    justify-content: space-between;
}

.question-type-selector label {
    display: block;
    background: #f1f1f1;
    padding: 10px;
    border-radius: 5px;
    text-align: center;
    cursor: pointer;
    flex: 1;
    margin: 0 5px;
    position: relative;
}

.question-type-selector input[type="radio"] {
    position: absolute;
    opacity: 0;
    width: 0;
    height: 0;
}

.question-type-selector label.active {
    background: #007bff;
    color: white;
}

.question-options {
    display: none;
}

.question-options.active {
    display: block;
}
    </style>
</head>
<body>
    <div class="dashboard-container">
        <h2>Edit Quiz: <?php echo htmlspecialchars($quiz['title']); ?></h2>
        <p>Course: <?php echo htmlspecialchars($quiz['course_title']); ?> | Duration: <?php echo $quiz['duration']; ?> minutes</p>
        
        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success">Question added successfully!</div>
        <?php endif; ?>
        
        <?php if (isset($_GET['deleted'])): ?>
            <div class="alert alert-success">Question deleted successfully!</div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <div class="action-panel">
            <h3>Add New Question</h3>
            
            <form method="post" action="">
                <div class="question-type-selector">
                    <label for="type_mcq" id="label_mcq" class="active">
                        <input type="radio" id="type_mcq" name="question_type" value="mcq" checked>
                        Multiple Choice
                    </label>
                    <label for="type_true_false" id="label_true_false">
                        <input type="radio" id="type_true_false" name="question_type" value="true_false">
                        True / False
                    </label>
                </div>
                
                <div class="form-group">
                    <label for="question">Question:</label>
                    <textarea id="question" name="question" required class="form-control"></textarea>
                </div>
                
                <!-- Multiple Choice Options -->
                <div id="mcq_options" class="question-options active">
                    <div class="form-group">
                        <label for="option1">Option 1:</label>
                        <input type="text" id="option1" name="option1" class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <label for="option2">Option 2:</label>
                        <input type="text" id="option2" name="option2" class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <label for="option3">Option 3:</label>
                        <input type="text" id="option3" name="option3" class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <label for="option4">Option 4:</label>
                        <input type="text" id="option4" name="option4" class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <label for="correct_option_mcq">Correct Answer:</label>
                        <select id="correct_option_mcq" name="correct_option_mcq" class="form-control">
                            <option value="1">Option 1</option>
                            <option value="2">Option 2</option>
                            <option value="3">Option 3</option>
                            <option value="4">Option 4</option>
                        </select>
                    </div>
                </div>
                
                <!-- True/False Options -->
                <div id="true_false_options" class="question-options">
                    <div class="form-group">
                        <label for="correct_option_tf">Correct Answer:</label>
                        <select id="correct_option_tf" name="correct_option_tf" class="form-control">
                            <option value="1">True</option>
                            <option value="2">False</option>
                        </select>
                    </div>
                </div>
                
                <button type="submit" name="add_question" class="btn">Add Question</button>
            </form>
        </div>
        
        <div class="content-panel">
            <h3>Existing Questions</h3>
            
            <?php if ($questions_result->num_rows > 0): ?>
                <div class="question-list">
                    <?php $question_number = 1; ?>
                    <?php while ($question = $questions_result->fetch_assoc()): ?>
                        <div class="question-item">
                            <div class="question-header">
                                <span class="question-number">
                                    Question <?php echo $question_number++; ?>
                                    <?php 
                                    $question_type = isset($question['question_type']) ? $question['question_type'] : 'mcq';
                                    ?>
                                    <span class="question-type-badge">
                                        <?php echo $question_type === 'mcq' ? 'Multiple Choice' : 'True/False'; ?>
                                    </span>
                                </span>
                                <a href="edit_quiz.php?id=<?php echo $quiz_id; ?>&delete_question=<?php echo $question['id']; ?>" 
                                   class="btn btn-sm btn-danger" 
                                   onclick="return confirm('Are you sure you want to delete this question?')">
                                    Delete
                                </a>
                            </div>
                            <div class="question-content">
                                <p><strong>Q: <?php echo htmlspecialchars($question['question'] ?? "Question not available."); ?></strong></p>
                                
                                <?php if ($question_type === 'mcq'): ?>
                                    <ol>
                                        <li <?php if ($question['correct_option'] == 1) echo 'class="correct-option"'; ?>>
                                            <?php echo htmlspecialchars($question['option1']); ?>
                                        </li>
                                        <li <?php if ($question['correct_option'] == 2) echo 'class="correct-option"'; ?>>
                                            <?php echo htmlspecialchars($question['option2']); ?>
                                        </li>
                                        <?php if (!empty($question['option3'])): ?>
                                            <li <?php if ($question['correct_option'] == 3) echo 'class="correct-option"'; ?>>
                                                <?php echo htmlspecialchars($question['option3']); ?>
                                            </li>
                                        <?php endif; ?>
                                        <?php if (!empty($question['option4'])): ?>
                                            <li <?php if ($question['correct_option'] == 4) echo 'class="correct-option"'; ?>>
                                                <?php echo htmlspecialchars($question['option4']); ?>
                                            </li>
                                        <?php endif; ?>
                                    </ol>
                                <?php elseif ($question_type === 'true_false'): ?>
                                    <ul style="list-style-type: none; padding-left: 20px;">
                                        <li <?php if ($question['correct_option'] == 1) echo 'class="correct-option"'; ?>>
                                            True
                                        </li>
                                        <li <?php if ($question['correct_option'] == 2) echo 'class="correct-option"'; ?>>
                                            False
                                        </li>
                                    </ul>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <p>No questions added yet.</p>
            <?php endif; ?>
        </div>
        
        <div class="action-buttons">
            <a href="quiz_management.php?course_id=<?php echo $quiz['course_id']; ?>" class="btn">Back to Quizzes</a>
        </div>
    </div>
    
    <script>
        // JavaScript to handle question type switching
        document.addEventListener('DOMContentLoaded', function() {
            const mcqRadio = document.getElementById('type_mcq');
            const tfRadio = document.getElementById('type_true_false');
            const mcqLabel = document.getElementById('label_mcq');
            const tfLabel = document.getElementById('label_true_false');
            const mcqOptions = document.getElementById('mcq_options');
            const tfOptions = document.getElementById('true_false_options');
            
            // Initial state setting
            if (mcqRadio.checked) {
                mcqLabel.classList.add('active');
                tfLabel.classList.remove('active');
                mcqOptions.classList.add('active');
                tfOptions.classList.remove('active');
            } else {
                tfLabel.classList.add('active');
                mcqLabel.classList.remove('active');
                tfOptions.classList.add('active');
                mcqOptions.classList.remove('active');
            }
            
            // Event listeners
            mcqRadio.addEventListener('change', function() {
                if (this.checked) {
                    mcqLabel.classList.add('active');
                    tfLabel.classList.remove('active');
                    mcqOptions.classList.add('active');
                    tfOptions.classList.remove('active');
                }
            });
            
            tfRadio.addEventListener('change', function() {
                if (this.checked) {
                    tfLabel.classList.add('active');
                    mcqLabel.classList.remove('active');
                    tfOptions.classList.add('active');
                    mcqOptions.classList.remove('active');
                }
            });
            
            // Make labels clickable
            mcqLabel.addEventListener('click', function() {
                mcqRadio.checked = true;
                mcqRadio.dispatchEvent(new Event('change'));
            });
            
            tfLabel.addEventListener('click', function() {
                tfRadio.checked = true;
                tfRadio.dispatchEvent(new Event('change'));
            });
        });
    </script>
</body>
</html>