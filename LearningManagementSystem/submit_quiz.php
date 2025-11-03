<?php
include("config.php");
session_start();

// Logging for debugging
file_put_contents('quiz_submission_log.txt', date('Y-m-d H:i:s') . " - Submission started\n", FILE_APPEND);

// Validate student access
if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'student' || !isset($_POST['quiz_id'])) {
    file_put_contents('quiz_submission_log.txt', date('Y-m-d H:i:s') . " - Access validation failed\n", FILE_APPEND);
    header("Location: my_course.php");
    exit();
}

$user_id = $_SESSION['user'];
$quiz_id = $_POST['quiz_id'];
$start_time = $_POST['start_time'];
$end_time = time();
$total_time = $end_time - $start_time;

// Process the question order if provided
$question_order = [];
if (isset($_POST['question_order']) && !empty($_POST['question_order'])) {
    $question_order = json_decode($_POST['question_order'], true);
    if (!is_array($question_order)) {
        $question_order = [];
    }
}
$question_order_json = json_encode($question_order);

file_put_contents('quiz_submission_log.txt', date('Y-m-d H:i:s') . " - User $user_id submitted quiz $quiz_id with question order: $question_order_json\n", FILE_APPEND);

// Check if the quiz exists and student is enrolled in the course
$quiz_query = $conn->prepare("
    SELECT q.id, q.duration, q.course_id
    FROM quizzes q
    JOIN courses c ON q.course_id = c.id
    JOIN enrollments e ON c.id = e.course_id AND e.student_id = ?
    WHERE q.id = ?
");
$quiz_query->bind_param("ii", $user_id, $quiz_id);

if (!$quiz_query->execute()) {
    file_put_contents('quiz_submission_log.txt', date('Y-m-d H:i:s') . " - Quiz query failed: " . $conn->error . "\n", FILE_APPEND);
    exit("Database error: " . $conn->error);
}

$quiz_result = $quiz_query->get_result();

if ($quiz_result->num_rows === 0) {
    file_put_contents('quiz_submission_log.txt', date('Y-m-d H:i:s') . " - Quiz not found or not enrolled\n", FILE_APPEND);
    header("Location: my_course.php");
    exit();
}

$quiz = $quiz_result->fetch_assoc();

// Get quiz questions
$questions_query = $conn->prepare("
    SELECT id, question, question_type, option1, option2, option3, option4, correct_option
    FROM quiz_questions
    WHERE quiz_id = ?
");

$questions_query->bind_param("i", $quiz_id);

if (!$questions_query->execute()) {
    file_put_contents('quiz_submission_log.txt', date('Y-m-d H:i:s') . " - Questions query failed: " . $conn->error . "\n", FILE_APPEND);
    exit("Database error: " . $conn->error);
}

$questions_result = $questions_query->get_result();

$questions = [];
$total_score = 0;
$max_score = 0;

while ($row = $questions_result->fetch_assoc()) {
    $questions[$row['id']] = $row;
    $max_score += 1;
}

file_put_contents('quiz_submission_log.txt', date('Y-m-d H:i:s') . " - Found " . count($questions) . " questions\n", FILE_APPEND);

// Get current attempt number
$attempt_count_query = $conn->prepare("
    SELECT COUNT(*) as count
    FROM quiz_attempts
    WHERE quiz_id = ? AND student_id = ?
");
$attempt_count_query->bind_param("ii", $quiz_id, $user_id);

if (!$attempt_count_query->execute()) {
    file_put_contents('quiz_submission_log.txt', date('Y-m-d H:i:s') . " - Attempt count query failed: " . $conn->error . "\n", FILE_APPEND);
    exit("Database error: " . $conn->error);
}

$attempt_count_result = $attempt_count_query->get_result();
$attempt_count_row = $attempt_count_result->fetch_assoc();
$attempt_number = $attempt_count_row['count'] + 1;

file_put_contents('quiz_submission_log.txt', date('Y-m-d H:i:s') . " - This is attempt #$attempt_number\n", FILE_APPEND);

// Create an attempt record
$conn->begin_transaction();

try {
    // Insert the attempt - now including question_order column
    $attempt_stmt = $conn->prepare("
        INSERT INTO quiz_attempts (quiz_id, student_id, start_time, end_time, time_taken, attempt_number, question_order)
        VALUES (?, ?, FROM_UNIXTIME(?), FROM_UNIXTIME(?), ?, ?, ?)
    ");
    $attempt_stmt->bind_param("iiiiiss", $quiz_id, $user_id, $start_time, $end_time, $total_time, $attempt_number, $question_order_json);
    
    if (!$attempt_stmt->execute()) {
        throw new Exception("Failed to create attempt record: " . $conn->error);
    }
    
    $attempt_id = $conn->insert_id;
    file_put_contents('quiz_submission_log.txt', date('Y-m-d H:i:s') . " - Created attempt ID: $attempt_id\n", FILE_APPEND);
    
    // Process each question and answer
    foreach ($questions as $question_id => $question) {
        $answer_key = "answer_" . $question_id;
        $student_answer = isset($_POST[$answer_key]) ? $_POST[$answer_key] : "";
        
        file_put_contents('quiz_submission_log.txt', date('Y-m-d H:i:s') . " - Processing Q$question_id ($question[question_type]): '$student_answer'\n", FILE_APPEND);
        
        $points = 0;
        $is_correct = 0;
        
        if ($question['question_type'] === 'mcq') {
            $is_correct = ((string)$student_answer === (string)$question['correct_option']) ? 1 : 0;
            $points = $is_correct;
        } 
        else if ($question['question_type'] === 'true_false') {
            // Normalize the student's answer
            $student_answer_normalized = 0;
            if (!empty($student_answer)) {
                $student_answer = strtolower(trim($student_answer));
                if ($student_answer === 'true' || $student_answer === '1' || $student_answer === 'yes') {
                    $student_answer_normalized = 1;
                } elseif ($student_answer === 'false' || $student_answer === '0' || $student_answer === 'no') {
                    $student_answer_normalized = 2;
                }
            }
            
            // Compare with correct option (1=true, 2=false)
            $is_correct = ($student_answer_normalized == $question['correct_option']) ? 1 : 0;
            $points = $is_correct;
            
            // Store the original text answer for display
            $student_answer = ($student_answer_normalized == 1) ? 'true' : 'false';
            
            file_put_contents('quiz_submission_log.txt', 
                "True/False Q$question_id: Student=$student_answer, Correct=" . 
                ($question['correct_option'] == 1 ? 'true' : 'false') . 
                ", IsCorrect=$is_correct\n", FILE_APPEND);
        }
        else if ($question['question_type'] === 'text') {
            $is_correct = NULL;
            $points = 0;
        }

        if ($is_correct === 1) {
            $total_score += 1;
        }

        file_put_contents('quiz_submission_log.txt', date('Y-m-d H:i:s') . " - Q$question_id is_correct: " . 
            (is_null($is_correct) ? "NULL (pending)" : $is_correct) . ", points: $points\n", FILE_APPEND);
        
        if (empty($student_answer)) {
            $student_answer = "";
        }   
        
        // Store the answer
        $answer_stmt = $conn->prepare("
            INSERT INTO quiz_answers (attempt_id, question_id, student_answer, is_correct, points)
            VALUES (?, ?, ?, ?, ?)
        ");
        
        if (is_null($is_correct)) {
            $answer_stmt->bind_param("iisdi", $attempt_id, $question_id, $student_answer, $is_correct, $points);
        } else {
            $answer_stmt->bind_param("iisii", $attempt_id, $question_id, $student_answer, $is_correct, $points);
        }
        
        if (!$answer_stmt->execute()) {
            throw new Exception("Failed to store answer for question $question_id: " . $conn->error);
        }
    }
    
    // Calculate percentage score
    $percentage_score = ($max_score > 0) ? ($total_score / $max_score) * 100 : 0;
    
    file_put_contents('quiz_submission_log.txt', date('Y-m-d H:i:s') . " - Total score: $total_score/$max_score ($percentage_score%)\n", FILE_APPEND);
    
    // Update the attempt with the score
    $update_attempt = $conn->prepare("
        UPDATE quiz_attempts 
        SET score = ?, max_score = ?, percentage_score = ?
        WHERE id = ?
    ");
    $update_attempt->bind_param("iidd", $total_score, $max_score, $percentage_score, $attempt_id);
    
    if (!$update_attempt->execute()) {
        throw new Exception("Failed to update attempt with scores: " . $conn->error);
    }
    
    $conn->commit();
    file_put_contents('quiz_submission_log.txt', date('Y-m-d H:i:s') . " - Quiz submission successful\n", FILE_APPEND);
    
    // Redirect to results page
    header("Location: quiz_results.php?attempt_id=" . $attempt_id);
    exit();
    
} catch (Exception $e) {
    $conn->rollback();
    file_put_contents('quiz_submission_log.txt', date('Y-m-d H:i:s') . " - ERROR: " . $e->getMessage() . "\n", FILE_APPEND);
    echo "An error occurred: " . $e->getMessage();
    exit();
}