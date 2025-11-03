<?php
include("config.php");
session_start();

// Validate student access
if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user'];

if (!isset($_GET['attempt_id']) || !is_numeric($_GET['attempt_id'])) {
    header("Location: my_course.php?error=invalid_attempt");
    exit();
}

$attempt_id = (int)$_GET['attempt_id'];

// Fetch attempt information
$attempt_query = $conn->prepare("
    SELECT qa.*, q.title as quiz_title, q.course_id, c.title as course_title
    FROM quiz_attempts qa
    JOIN quizzes q ON qa.quiz_id = q.id
    JOIN courses c ON q.course_id = c.id
    JOIN enrollments e ON c.id = e.course_id AND e.student_id = ?
    WHERE qa.id = ? AND qa.student_id = ?
");
$attempt_query->bind_param("iii", $user_id, $attempt_id, $user_id);
$attempt_query->execute();
$attempt_result = $attempt_query->get_result();

if ($attempt_result->num_rows === 0) {
    header("Location: my_course.php");
    exit();
}

$attempt = $attempt_result->fetch_assoc();
$quiz_id = $attempt['quiz_id'];

// Get quiz info
$quiz_query = $conn->prepare("
    SELECT q.*, c.title as course_title
    FROM quizzes q
    JOIN courses c ON q.course_id = c.id
    WHERE q.id = ?
");
$quiz_query->bind_param("i", $quiz_id);
$quiz_query->execute();
$quiz_result = $quiz_query->get_result();
$quiz = $quiz_result->fetch_assoc();

// Get all attempts for this quiz by this student
$all_attempts_query = $conn->prepare("
    SELECT id, attempt_number, start_time, end_time, score, max_score, percentage_score
    FROM quiz_attempts
    WHERE quiz_id = ? AND student_id = ?
    ORDER BY end_time DESC
");
$all_attempts_query->bind_param("ii", $quiz_id, $user_id);
$all_attempts_query->execute();
$all_attempts_result = $all_attempts_query->get_result();

$all_attempts = [];
while ($row = $all_attempts_result->fetch_assoc()) {
    $all_attempts[] = $row;
}

$total_attempts = count($all_attempts);
$max_attempts = isset($quiz['max_attempts']) ? $quiz['max_attempts'] : 0;
$can_attempt_again = ($max_attempts === 0 || $total_attempts < $max_attempts);

$current_attempt_number = 0;
foreach ($all_attempts as $att) {
    if ($att['id'] == $attempt_id) {
        $current_attempt_number = isset($att['attempt_number']) ? $att['attempt_number'] : $total_attempts;
        break;
    }
}

if ($current_attempt_number == 0) {
    for ($i = 0; $i < count($all_attempts); $i++) {
        if ($all_attempts[$i]['id'] == $attempt_id) {
            $current_attempt_number = $total_attempts - $i;
            break;
        }
    }
}

// Get quiz answers for the current attempt
$answers_query = $conn->prepare("
    SELECT qa.*, qq.question, qq.question_type, qq.option1, qq.option2, qq.option3, qq.option4, qq.correct_option
    FROM quiz_answers qa
    JOIN quiz_questions qq ON qa.question_id = qq.id
    WHERE qa.attempt_id = ?
    ORDER BY qq.id
");
$answers_query->bind_param("i", $attempt_id);
$answers_query->execute();
$answers_result = $answers_query->get_result();

$answers = [];
$corrected_score = 0;
$total_questions = 0;
$updates_needed = false;

// Define what we consider as "no answer provided" values
$no_answer_values = ['', '-', 'none', 'no_answer', 'unanswered', null];

while ($row = $answers_result->fetch_assoc()) {
    $total_questions++;
    
    // Check if the student_answer is NULL or equivalent to no answer
    $student_answer = $row['student_answer'];
    $student_answer_text = is_null($student_answer) ? '' : strtolower(trim($student_answer));
    
    // First check if the was_answered column is already set in the database
    $was_answered = isset($row['was_answered']) ? ($row['was_answered'] == 1) : false;
    
    // If was_answered is not set in the database, determine based on the answer value
    if (!isset($row['was_answered'])) {
        $was_answered = !is_null($student_answer) && !in_array($student_answer_text, $no_answer_values);
    }
    
    if ($row['question_type'] === 'true_false') {
        // Properly determine if the question was answered with a valid true/false response
        $student_answer_normalized = 0; // Default to invalid answer
        
        if (!is_null($student_answer) && $student_answer !== '') {
            if ($student_answer_text === 'true' || $student_answer_text === '1' || $student_answer_text === 'yes') {
                $student_answer_normalized = 1;
                $was_answered = true;
            } else if ($student_answer_text === 'false' || $student_answer_text === '0' || $student_answer_text === 'no') {
                $student_answer_normalized = 2;
                $was_answered = true;
            } else {
                // Invalid answer format but not empty
                $student_answer_normalized = 0;
                $was_answered = false;
            }
        } else {
            // Empty answer
            $student_answer_normalized = 0;
            $was_answered = false;
        }
        
        // Check for correct answer only if it was actually answered with a valid response
        $is_really_correct = $was_answered && 
                           ($student_answer_normalized === 1 || $student_answer_normalized === 2) && 
                           ($student_answer_normalized === $row['correct_option']);
        
        $row['is_really_correct'] = $is_really_correct ? 1 : 0;
        $row['student_answered'] = $was_answered ? 1 : 0;
        
        // Update the was_answered column in the database if needed
        if (!isset($row['was_answered']) || $row['was_answered'] != $was_answered) {
            $updates_needed = true;
            $update_was_answered = $conn->prepare("
                UPDATE quiz_answers SET was_answered = ? WHERE id = ?
            ");
            $was_answered_int = $was_answered ? 1 : 0;
            $update_was_answered->bind_param("ii", $was_answered_int, $row['id']);
            $update_was_answered->execute();
        }
        
        if ($is_really_correct != $row['is_correct']) {
            $updates_needed = true;
            
            $update_answer = $conn->prepare("
                UPDATE quiz_answers SET is_correct = ?, points = ? WHERE id = ?
            ");
            $points = $is_really_correct ? 1 : 0;
            $is_really_correct_int = $is_really_correct ? 1 : 0;
            $update_answer->bind_param("iii", $is_really_correct_int, $points, $row['id']);
            $update_answer->execute();
            
            $row['is_correct'] = $is_really_correct;
        }
        
        if ($is_really_correct) {
            $corrected_score++;
        }
    } else if ($row['question_type'] === 'mcq') {
        // For multiple choice questions
        $was_answered = !is_null($student_answer) && !in_array($student_answer_text, $no_answer_values);
        
        if ($was_answered) {
            $is_really_correct = (trim($student_answer) == $row['correct_option']);
        } else {
            $is_really_correct = false;
        }
        
        $row['is_really_correct'] = $is_really_correct ? 1 : 0;
        $row['student_answered'] = $was_answered ? 1 : 0;
        
        // Update the was_answered column in the database if needed
        if (!isset($row['was_answered']) || $row['was_answered'] != $was_answered) {
            $updates_needed = true;
            $was_answered_int = $was_answered ? 1 : 0;
            $update_was_answered = $conn->prepare("
                UPDATE quiz_answers SET was_answered = ? WHERE id = ?
            ");
            $update_was_answered->bind_param("ii", $was_answered_int, $row['id']);
            $update_was_answered->execute();
        }
        
        if ($is_really_correct != $row['is_correct']) {
            $updates_needed = true;
            
            $update_answer = $conn->prepare("
                UPDATE quiz_answers SET is_correct = ?, points = ? WHERE id = ?
            ");
            $points = $is_really_correct ? 1 : 0;
            $is_really_correct_int = $is_really_correct ? 1 : 0;
            $update_answer->bind_param("iii", $is_really_correct_int, $points, $row['id']);
            $update_answer->execute();
            
            $row['is_correct'] = $is_really_correct;
        }
        
        if ($is_really_correct) {
            $corrected_score++;
        }
    } else {
        // For other question types
        $row['is_really_correct'] = $row['is_correct'];
        $row['student_answered'] = $was_answered ? 1 : 0;
        
        if ($row['is_correct']) {
            $corrected_score++;
        }
    }
    
    $answers[] = $row;
}

$corrected_percentage = $total_questions > 0 ? ($corrected_score / $total_questions) * 100 : 0;
$corrected_percentage_score = number_format($corrected_percentage, 1);

// Update attempt score if needed
if ($updates_needed || abs($corrected_percentage - $attempt['percentage_score']) > 0.1) {
    $update_attempt = $conn->prepare("
        UPDATE quiz_attempts 
        SET score = ?, max_score = ?, percentage_score = ? 
        WHERE id = ?
    ");
    $update_attempt->bind_param("iidd", $corrected_score, $total_questions, $corrected_percentage, $attempt_id);
    $update_attempt->execute();
    
    $attempt['score'] = $corrected_score;
    $attempt['max_score'] = $total_questions;
    $attempt['percentage_score'] = $corrected_percentage;
}

$passing_score = 70;
$has_passed = $corrected_percentage_score >= $passing_score;

$highest_score = $corrected_percentage_score;
foreach ($all_attempts as $a) {
    if ($a['id'] != $attempt_id && $a['percentage_score'] > $highest_score) {
        $highest_score = $a['percentage_score'];
    }
}
$highest_score = number_format($highest_score, 1);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiz Result - <?= htmlspecialchars($attempt['quiz_title']) ?></title>
    <style>
        .result-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #333;
            text-decoration: none;
        }
        .result-header {
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 1px solid #ddd;
        }
        .score-section {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 30px;
            padding: 20px;
            background-color: #f9f9f9;
            border-radius: 8px;
        }
        .score-circle {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            font-weight: bold;
            margin: 0 auto 10px auto;
            color: white;
        }
        .passing {
            background-color: #4CAF50;
        }
        .failing {
            background-color: #f44336;
        }
        .answers-section {
            margin-top: 30px;
        }
        .question-item {
            margin-bottom: 25px;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
        }
        .option-correct {
            background-color: rgba(76, 175, 80, 0.2);
            border-color: #4CAF50;
        }
        .option-selected {
            background-color: rgba(33, 150, 243, 0.2);
            border-color: #2196F3;
        }
        .option-incorrect {
            background-color: rgba(244, 67, 54, 0.2);
            border-color: #f44336;
        }
        .answer-status {
            margin-top: 10px;
            padding: 8px;
            border-radius: 4px;
            font-weight: bold;
            text-align: center;
        }
        .correct-answer {
            background-color: rgba(76, 175, 80, 0.2);
            color: #4CAF50;
        }
        .wrong-answer {
            background-color: rgba(244, 67, 54, 0.2);
            color: #f44336;
        }
        .unanswered-answer {
            background-color: rgba(85, 27, 151, 0.2);
            color: #757575;
        }
        .pending-review {
            background-color: rgba(255, 193, 7, 0.2);
            color: #FF9800;
        }
        .attempt-info {
            background-color: #f5f5f5;
            padding: 8px 12px;
            border-radius: 4px;
            display: inline-block;
            margin-bottom: 15px;
            font-weight: bold;
        }
        .unanswered {
            font-style: italic;
            color:rgb(221, 35, 35);
        }
        .correct-answers-locked {
            text-align: center;
            padding: 20px;
            background-color: #f9f9f9;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .locked-icon {
            font-size: 48px;
            margin-bottom: 10px;
            color: #f44336;
        }
    </style>
    
</head>
<body>
<div class="result-container">
    <a href="view_course.php?id=<?= $attempt['course_id'] ?>" class="back-link">← Back to Course</a>
    
    <div class="result-header">
        <h1>Assessment Results</h1>
        <h2><?= htmlspecialchars($attempt['quiz_title']) ?></h2>
        <p><strong>Course:</strong> <?= htmlspecialchars($attempt['course_title']) ?></p>
        <div class="attempt-info">
            Attempt #<?= $current_attempt_number ?> of <?= $total_attempts ?>
        </div>
    </div>
    
    <div class="score-section">
        <div class="score-display">
            <div class="score-circle <?= $has_passed ? 'passing' : 'failing' ?>">
                <?= $corrected_percentage_score ?>%
            </div>
            <p>Your Score</p>
        </div>
        
        <div class="score-details">
            <p><strong>Questions:</strong> <?= count($answers) ?></p>
            <p><strong>Correct Answers:</strong> <?= $corrected_score ?> out of <?= $total_questions ?></p>
            <p><strong>Time Taken:</strong> <?= gmdate("H:i:s", $attempt['time_taken']) ?></p>
            <p><strong>Status:</strong> <?= $has_passed ? 'PASSED' : 'FAILED' ?></p>
            <?php if ($total_attempts > 1): ?>
                <p><strong>Highest Score:</strong> <?= $highest_score ?>%</p>
            <?php endif; ?>
            <?php if ($can_attempt_again): ?>
                <p><a href="take_quiz.php?id=<?= $quiz_id ?>" class="button">Retake Assessment</a></p>
            <?php else: ?>
                <p><em>Maximum attempts reached</em></p>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="answers-section">
        <h3>Question Review</h3>
        
        
        
        <?php foreach ($answers as $index => $answer): ?>
            <div class="question-item">
                <div class="question-text">
                    <strong>Question <?= $index + 1 ?>:</strong> 
                    <?= htmlspecialchars($answer['question']) ?>
                </div>
                
                <?php if ($answer['question_type'] === 'mcq'): ?>
                    <ul class="option-list">
                        <?php for($opt = 1; $opt <= 4; $opt++): ?>
                            <?php if (!empty($answer['option'.$opt])): ?>
                                <?php
                                $optionClass = '';
                                // Only show correct answer highlighting if the student passed
                                if ($has_passed && $opt == $answer['correct_option']) {
                                    $optionClass = 'option-correct';
                                }
                                if ($answer['student_answered'] && $opt == $answer['student_answer']) {
                                    $optionClass .= ' option-selected';
                                    if ($has_passed && $opt != $answer['correct_option']) {
                                        $optionClass .= ' option-incorrect';
                                    }
                                }
                                ?>
                                <li class="option-item <?= $optionClass ?>">
                                    <?= htmlspecialchars($answer['option'.$opt]) ?>
                                    <?php if ($has_passed && $opt == $answer['correct_option']): ?>
                                        <span style="float: right; color: #4CAF50;">✓ Correct Answer</span>
                                    <?php endif; ?>
                                    <?php if ($answer['student_answered'] && $opt == $answer['student_answer'] && $has_passed && $opt != $answer['correct_option']): ?>
                                        <span style="float: right; color: #f44336;">× Your Answer</span>
                                    <?php elseif ($answer['student_answered'] && $opt == $answer['student_answer']): ?>
                                        <span style="float: right; color: #2196F3;">Your Answer</span>
                                    <?php endif; ?>
                                </li>
                            <?php endif; ?>
                        <?php endfor; ?>
                    </ul>
                    
                    <?php if (!$answer['student_answered']): ?>
                        <div class="answer-status unanswered-answer">
                            UNANSWERED
                        </div>
                    <?php else: ?>
                        <div class="answer-status <?= $answer['is_correct'] ? 'correct-answer' : 'wrong-answer' ?>">
                            <?= $answer['is_correct'] ? 'CORRECT' : 'INCORRECT' ?>
                        </div>
                    <?php endif; ?>
                    
                <?php elseif ($answer['question_type'] === 'true_false'): ?>
                    <div style="margin-bottom: 15px;">
                        <?php if ($answer['student_answered']): ?>
                            <p><strong>Your Answer:</strong> <?= htmlspecialchars($answer['student_answer']) ?></p>
                        <?php else: ?>
                            <p><strong>Your Answer:</strong> <span class="unanswered">No answer provided</span></p>
                        <?php endif; ?>
                        <?php if ($has_passed): ?>
                            <p><strong>Correct Answer:</strong> <?= $answer['correct_option'] == 1 ? 'true' : 'false' ?></p>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (!$answer['student_answered']): ?>
                        <div class="answer-status unanswered-answer">
                            UNANSWERED
                        </div>
                    <?php else: ?>
                        <div class="answer-status <?= $answer['is_correct'] ? 'correct-answer' : 'wrong-answer' ?>">
                            <?= $answer['is_correct'] ? 'CORRECT' : 'INCORRECT' ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>
</body>
</html>