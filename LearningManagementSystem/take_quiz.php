<?php
include("config.php");
session_start();

// Validate student access
if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'student' || !isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: my_course.php");
    exit();
}

$user_id = $_SESSION['user'];
$quiz_id = $_GET['id'];

// Check if the quiz exists and student is enrolled in the course
$quiz_query = $conn->prepare("
    SELECT q.id, q.title, q.duration, q.course_id, c.title as course_title, q.max_attempts
    FROM quizzes q
    JOIN courses c ON q.course_id = c.id
    JOIN enrollments e ON c.id = e.course_id AND e.student_id = ?
    WHERE q.id = ?
");
$quiz_query->bind_param("ii", $user_id, $quiz_id);
$quiz_query->execute();
$quiz_result = $quiz_query->get_result();

if ($quiz_result->num_rows === 0) {
    header("Location: my_course.php");
    exit();
}

$quiz = $quiz_result->fetch_assoc();

// Check if student has reached maximum attempts
$attempt_query = $conn->prepare("
    SELECT COUNT(*) as attempt_count FROM quiz_attempts 
    WHERE quiz_id = ? AND student_id = ?
");
$attempt_query->bind_param("ii", $quiz_id, $user_id);
$attempt_query->execute();
$attempt_result = $attempt_query->get_result();
$attempt_data = $attempt_result->fetch_assoc();
$current_attempts = $attempt_data['attempt_count'];

$max_attempts = $quiz['max_attempts'] ?? 0;
if ($max_attempts > 0 && $current_attempts > $max_attempts) {
    $current_attempts = $max_attempts;
}

// Get quiz questions
$query = "SELECT id, question, question_type, option1, option2, option3, option4, correct_option FROM quiz_questions WHERE quiz_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $quiz_id);
$stmt->execute();
$questions_result = $stmt->get_result();

$questions = [];
while ($row = $questions_result->fetch_assoc()) {
    if (empty($row['question_type'])) {
        $row['question_type'] = 'mcq';
    }
    $questions[] = $row;
}

$total_questions = count($questions);

if ($total_questions === 0) {
    header("Location: view_course.php?id=" . $quiz['course_id']);
    exit();
}

// Generate a seed for shuffling based on user ID and attempt number
// This ensures different shuffle order for each attempt but consistent within the same attempt
$shuffle_seed = $user_id * 1000 + ($current_attempts + 1);
srand($shuffle_seed);

// Shuffle the questions
$original_question_order = $questions;
shuffle($questions);

// Store the question order mapping for submission handling
$question_order_mapping = [];
foreach ($questions as $index => $question) {
    $question_order_mapping[] = $question['id'];
}
$question_order_json = json_encode($question_order_mapping);

// Reset PHP's random seed to avoid affecting other random operations
mt_srand();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Taking Quiz: <?= htmlspecialchars($quiz['title']) ?></title>
    <style>
        .quiz-container { max-width: 800px; margin: 0 auto; padding: 20px; }
        .quiz-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #ddd; }
        .quiz-info { background-color: #f9f9f9; padding: 15px; border-radius: 8px; margin-bottom: 20px; }
        .quiz-timer { font-size: 18px; font-weight: bold; color: #333; text-align: center; padding: 10px; background-color: #f0f0f0; border-radius: 5px; margin-bottom: 20px; }
        .quiz-timer.warning { background-color: #fff3cd; color: #856404; }
        .quiz-timer.danger { background-color: #f8d7da; color: #721c24; }
        .question-section { margin-bottom: 25px; padding: 20px; border: 1px solid #ddd; border-radius: 8px; }
        .question-text { font-size: 18px; margin-bottom: 15px; }
        .options-list { list-style-type: none; padding: 0; }
        .option-item { margin-bottom: 10px; padding: 10px; border: 1px solid #ddd; border-radius: 5px; cursor: pointer; transition: background-color 0.2s; }
        .option-item:hover { background-color: #f5f5f5; }
        .option-item input { margin-right: 10px; }
        .option-item label { cursor: pointer; display: inline-block; width: calc(100% - 30px); }
        .text-answer { width: 100%; min-height: 100px; padding: 10px; border: 1px solid #ddd; border-radius: 5px; }
        .navigation-buttons { display: flex; justify-content: space-between; margin-top: 30px; }
        .btn { padding: 10px 20px; background-color: #4CAF50; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; }
        .btn-secondary { background-color: #6c757d; }
        .btn:hover { opacity: 0.9; }
        .btn:disabled { background-color: #cccccc; cursor: not-allowed; }
        .progress-indicator { display: flex; justify-content: center; margin-bottom: 20px; }
        .progress-dot { width: 12px; height: 12px; border-radius: 50%; background-color: #ddd; margin: 0 5px; cursor: pointer; }
        .progress-dot.active { background-color: #4CAF50; }
        .progress-dot.answered { background-color: #2196F3; }
        .back-link { display: inline-block; margin-bottom: 20px; color: #333; text-decoration: none; }
        .back-link:hover { text-decoration: underline; }
        .attempt-info { margin-top: 10px; padding: 10px; background-color: #e3f2fd; border-radius: 5px; font-size: 14px; }
        .status-message { text-align: center; margin-top: 10px; padding: 8px; border-radius: 5px; }
        .status-message.incomplete { background-color: #ffe0e0; color: #8b0000; }
        .status-message.complete { background-color: #d4edda; color: #155724; }
    </style>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
<div class="quiz-container">
    <a href="view_course.php?id=<?= $quiz['course_id'] ?>" class="back-link">← Back to Course</a>
    
    <div class="quiz-header">
        <h1><?= htmlspecialchars($quiz['title']) ?></h1>
    </div>
    
    <div class="quiz-info">
        <p><strong>Course:</strong> <?= htmlspecialchars($quiz['course_title']) ?></p>
        <p><strong>Duration:</strong> <?= $quiz['duration'] ?> minutes</p>
        <p><strong>Questions:</strong> <?= $total_questions ?></p>
        <div class="attempt-info">
            <p><strong>Attempts:</strong> <?= $current_attempts ?> of <?= $max_attempts > 0 ? $max_attempts : 'unlimited' ?></p>
            <p><small>Questions are shuffled for each attempt</small></p>
        </div>
    </div>
    
    <div class="quiz-timer" id="quiz-timer">
        Time remaining: <span id="timer-value"><?= $quiz['duration'] ?>:00</span>
    </div>
    
    <div id="status-message" class="status-message incomplete">
        Please answer all questions to enable the submit button
    </div>
    
    <form id="quiz-form" action="submit_quiz.php" method="post">
        <input type="hidden" name="quiz_id" value="<?= $quiz_id ?>">
        <input type="hidden" name="start_time" value="<?= time() ?>">
        <input type="hidden" name="question_order" value="<?= htmlspecialchars($question_order_json) ?>">
        
        <div class="progress-indicator">
            <?php for($i = 0; $i < $total_questions; $i++): ?>
                <div class="progress-dot <?= $i === 0 ? 'active' : '' ?>" data-question="<?= $i ?>"></div>
            <?php endfor; ?>
        </div>
        
        <div id="questions-container">
            <?php foreach($questions as $index => $question): ?>
                <div class="question-section" id="question-<?= $index ?>" style="display: <?= $index === 0 ? 'block' : 'none' ?>">
                    <div class="question-text">
                        <strong>Question <?= $index + 1 ?> of <?= count($questions) ?>:</strong> 
                        <?= htmlspecialchars($question['question'] ?? "Question not available.") ?>
                    </div>

                    <?php if ($question['question_type'] === 'mcq'): ?>
                        <ul class="options-list">
                            <?php for($opt = 1; $opt <= 4; $opt++): ?>
                                <?php if (!empty($question['option'.$opt])): ?>
                                    <li class="option-item">
                                        <input type="radio" name="answer_<?= $question['id'] ?>" 
                                            id="option_<?= $question['id'] ?>_<?= $opt ?>" 
                                            value="<?= $opt ?>" class="question-input">
                                        <label for="option_<?= $question['id'] ?>_<?= $opt ?>">
                                            <?= htmlspecialchars($question['option'.$opt]) ?>
                                        </label>
                                    </li>
                                <?php endif; ?>
                            <?php endfor; ?>
                        </ul>
                    <?php elseif ($question['question_type'] === 'text'): ?>
                        <textarea class="text-answer question-input" name="answer_<?= $question['id'] ?>" 
                                placeholder="Enter your answer here"></textarea>
                    <?php elseif ($question['question_type'] === 'true_false'): ?>
                        <ul class="options-list">
                            <li class="option-item">
                                <input type="radio" name="answer_<?= $question['id'] ?>" 
                                    id="option_<?= $question['id'] ?>_true" 
                                    value="true" class="question-input">
                                <label for="option_<?= $question['id'] ?>_true">True</label>
                            </li>
                            <li class="option-item">
                                <input type="radio" name="answer_<?= $question['id'] ?>" 
                                    id="option_<?= $question['id'] ?>_false" 
                                    value="false" class="question-input">
                                <label for="option_<?= $question['id'] ?>_false">False</label>
                            </li>
                        </ul>
                    <?php endif; ?>

                    <div class="navigation-buttons">
                        <button type="button" class="btn btn-secondary prev-btn" <?= $index === 0 ? 'disabled' : '' ?>>Previous</button>
                        <?php if($index < count($questions) - 1): ?>
                            <button type="button" class="btn next-btn">Next</button>
                        <?php else: ?>
                            <button type="submit" class="btn submit-btn" disabled>Submit Quiz</button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </form>
</div>

<script>
    $(document).ready(function() {
        let currentQuestion = 0;
        const totalQuestions = <?= $total_questions ?>;
        let answeredQuestions = [];
        
        let duration = <?= $quiz['duration'] ?>;
        let totalSeconds = duration * 60;
        
        function updateTimer() {
            const minutes = Math.floor(totalSeconds / 60);
            const seconds = totalSeconds % 60;
            
            $('#timer-value').text(minutes + ':' + (seconds < 10 ? '0' : '') + seconds);
            
            if (totalSeconds < 60) {
                $('#quiz-timer').removeClass('warning').addClass('danger');
            } else if (totalSeconds < 300) {
                $('#quiz-timer').addClass('warning');
            }
            
            totalSeconds--;
            
            if (totalSeconds < 0) {
                clearInterval(timerInterval);
                alert("Time's up! Your quiz will be submitted now.");
                $('#quiz-form').submit();
                return;
            }
        }
        
        let timerInterval = setInterval(updateTimer, 1000);
        
        // Check if all questions are answered and update submit button state
        function checkAllQuestionsAnswered() {
            const allQuestionIds = <?= json_encode(array_column($questions, 'id')) ?>;
            let allAnswered = true;
            
            for (let i = 0; i < allQuestionIds.length; i++) {
                const questionId = allQuestionIds[i];
                const inputName = 'answer_' + questionId;
                const isAnswered = 
                    ($('[name="' + inputName + '"]:checked').length > 0) || // For radio buttons
                    ($('[name="' + inputName + '"]').is('textarea') && $('[name="' + inputName + '"]').val().trim() !== ''); // For textarea
                
                if (!isAnswered) {
                    allAnswered = false;
                    break;
                }
            }
            
            if (allAnswered) {
                $('.submit-btn').prop('disabled', false);
                $('#status-message').removeClass('incomplete').addClass('complete').text('All questions answered. You can now submit your quiz.');
            } else {
                $('.submit-btn').prop('disabled', true);
                $('#status-message').removeClass('complete').addClass('incomplete').text('Please answer all questions to enable the submit button');
            }
            
            return allAnswered;
        }
        
        $('.next-btn').click(function() {
            if (currentQuestion < totalQuestions - 1) {
                const questionId = $('#question-' + currentQuestion).find('input, textarea').attr('name');
                if ($('[name="' + questionId + '"]:checked').length > 0 || $('[name="' + questionId + '"]').val()) {
                    if (!answeredQuestions.includes(currentQuestion)) {
                        answeredQuestions.push(currentQuestion);
                        $('.progress-dot[data-question="' + currentQuestion + '"]').addClass('answered');
                    }
                }
                
                $('#question-' + currentQuestion).hide();
                currentQuestion++;
                $('#question-' + currentQuestion).show();
                
                $('.progress-dot').removeClass('active');
                $('.progress-dot[data-question="' + currentQuestion + '"]').addClass('active');
                
                updateButtonStates();
            }
        });
        
        $('.prev-btn').click(function() {
            if (currentQuestion > 0) {
                $('#question-' + currentQuestion).hide();
                currentQuestion--;
                $('#question-' + currentQuestion).show();
                
                $('.progress-dot').removeClass('active');
                $('.progress-dot[data-question="' + currentQuestion + '"]').addClass('active');
                
                updateButtonStates();
            }
        });
        
        $('.progress-dot').click(function() {
            const questionIndex = $(this).data('question');
            
            $('#question-' + currentQuestion).hide();
            currentQuestion = questionIndex;
            $('#question-' + currentQuestion).show();
            
            $('.progress-dot').removeClass('active');
            $(this).addClass('active');
            
            updateButtonStates();
        });
        
        function updateButtonStates() {
            if (currentQuestion === 0) {
                $('.prev-btn').prop('disabled', true);
            } else {
                $('.prev-btn').prop('disabled', false);
            }
        }
        
        // Check answer status whenever an input changes
        $(document).on('change', '.question-input', function() {
            const questionSection = $(this).closest('.question-section');
            const questionIndex = questionSection.attr('id').replace('question-', '');
            
            if (!answeredQuestions.includes(parseInt(questionIndex))) {
                answeredQuestions.push(parseInt(questionIndex));
                $('.progress-dot[data-question="' + questionIndex + '"]').addClass('answered');
            }
            
            // Check if all questions are answered
            checkAllQuestionsAnswered();
        });
        
        // Initial check for answered questions
        $('.question-input').each(function() {
            const input = $(this);
            if ((input.is(':radio') && input.is(':checked')) || 
                (input.is('textarea') && input.val().trim() !== '')) {
                const questionSection = input.closest('.question-section');
                const questionIndex = questionSection.attr('id').replace('question-', '');
                
                if (!answeredQuestions.includes(parseInt(questionIndex))) {
                    answeredQuestions.push(parseInt(questionIndex));
                    $('.progress-dot[data-question="' + questionIndex + '"]').addClass('answered');
                }
            }
        });
        
        // Run initial check to see if all questions are already answered
        checkAllQuestionsAnswered();
    });
</script>
</body>
</html>