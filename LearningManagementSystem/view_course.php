<?php
include("config.php");
session_start();

// Validate student access
if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'student' || !isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: my_course.php");
    exit();
}

$user_id = $_SESSION['user'];
$course_id = $_GET['id'];

// Check if the student is enrolled in the course
$check_query = $conn->prepare("
    SELECT c.id, c.title, c.description 
    FROM courses c 
    JOIN enrollments e ON c.id = e.course_id 
    WHERE e.student_id = ? AND c.id = ?
");
$check_query->bind_param("ii", $user_id, $course_id);
$check_query->execute();
$course_result = $check_query->get_result();

if ($course_result->num_rows === 0) {
    header("Location: my_course.php");
    exit();
}

$course = $course_result->fetch_assoc();

// Fetch quiz for this course
$quiz_query = $conn->prepare("
    SELECT q.id, q.title, q.max_attempts, q.passing_score
    FROM quizzes q 
    WHERE q.course_id = ?
");
$quiz_query->bind_param("i", $course_id);
$quiz_query->execute();
$quiz_result = $quiz_query->get_result();
$quiz = $quiz_result->fetch_assoc();
$has_quiz = $quiz_result->num_rows > 0;

// Check if quiz has questions
$quiz_has_questions = false;
if ($has_quiz) {
    $questions_query = $conn->prepare("
        SELECT COUNT(*) as question_count 
        FROM quiz_questions 
        WHERE quiz_id = ?
    ");
    $questions_query->bind_param("i", $quiz['id']);
    $questions_query->execute();
    $questions_result = $questions_query->get_result();
    $questions_data = $questions_result->fetch_assoc();
    $quiz_has_questions = $questions_data['question_count'] > 0;
}


// Check student's quiz attempts if quiz exists
$attempts_count = 0;
$max_attempts = 0;
$latest_attempt_id = 0;
$latest_score = 0;
$passed_quiz = false;
$need_rewatch = false;
$reset_count = 0;

if ($has_quiz) {
    // Get reset information to calculate current cycle attempts
    $reset_check_query = $conn->prepare("
        SELECT reset_count FROM quiz_resets 
        WHERE student_id = ? AND quiz_id = ?
        ORDER BY reset_date DESC LIMIT 1
    ");
    $reset_check_query->bind_param("ii", $user_id, $quiz['id']);
    $reset_check_query->execute();
    $reset_result = $reset_check_query->get_result();
    
    if ($reset_result->num_rows > 0) {
        $reset_data = $reset_result->fetch_assoc();
        $reset_count = $reset_data['reset_count'];
    }
    
    // Get attempts only after the last reset
    $attempt_query = $conn->prepare("
    SELECT COUNT(*) as attempt_count 
    FROM quiz_attempts 
    WHERE quiz_id = ? AND student_id = ? 
    AND (
        ? = 0 
        OR (
            ? > 0 AND end_time > (
                SELECT reset_date FROM quiz_resets 
                WHERE student_id = ? AND quiz_id = ? 
                ORDER BY reset_date DESC LIMIT 1
            )
        )
    )
");
$attempt_query->bind_param("iiiiii", $quiz['id'], $user_id, $reset_count, $reset_count, $user_id, $quiz['id']);
    $attempt_query->execute();
    $attempt_result = $attempt_query->get_result();
    $attempt_data = $attempt_result->fetch_assoc();
    $attempts_count = $attempt_data['attempt_count'];
    $max_attempts = $quiz['max_attempts'];
    
    // Get latest attempt details
    if ($attempts_count > 0) {
        $latest_attempt_query = $conn->prepare("
            SELECT id, score, percentage_score
            FROM quiz_attempts 
            WHERE quiz_id = ? AND student_id = ? 
            ORDER BY end_time DESC LIMIT 1
        ");
        $latest_attempt_query->bind_param("ii", $quiz['id'], $user_id);
        $latest_attempt_query->execute();
        $latest_attempt_result = $latest_attempt_query->get_result();
        if ($latest_attempt_result->num_rows > 0) {
            $latest_attempt = $latest_attempt_result->fetch_assoc();
            $latest_attempt_id = $latest_attempt['id'];
            $latest_score = number_format($latest_attempt['percentage_score'], 1);
            $passed_quiz = ($latest_score >= $quiz['passing_score']);
            
            // Check if student needs to rewatch course (failed all attempts)
            if (!$passed_quiz && $attempts_count >= 3) {
                $need_rewatch = true;
                
                // Reset video progress if student has failed all attempts
                // We'll check if this has already been reset before
                $reset_check_query = $conn->prepare("
                    SELECT reset_count FROM quiz_resets 
                    WHERE student_id = ? AND quiz_id = ?
                ");
                $reset_check_query->bind_param("ii", $user_id, $quiz['id']);
                $reset_check_query->execute();
                $reset_result = $reset_check_query->get_result();
                
                // Only reset videos if we haven't done so already
                if ($reset_result->num_rows === 0) {
                    // Start transaction to ensure data consistency
                    $conn->begin_transaction();
                    
                    try {
                        // First time failing - delete ALL video progress
                        $reset_video_query = $conn->prepare("
                            DELETE FROM video_progress 
                            WHERE student_id = ? AND video_id IN (
                                SELECT id FROM course_videos WHERE course_id = ?
                            )
                        ");
                        $reset_video_query->bind_param("ii", $user_id, $course_id);
                        $reset_video_query->execute();
                        
                        // Only unlock the first video (sequence = 0)
                        $init_first_video = $conn->prepare("
                            INSERT INTO video_progress 
                            (student_id, course_id, video_id, video_sequence, is_completed, is_unlocked)
                            SELECT ?, ?, id, video_sequence, 0, 1
                            FROM course_videos 
                            WHERE course_id = ? AND video_sequence = 0
                        ");
                        $init_first_video->bind_param("iii", $user_id, $course_id, $course_id);
                        $init_first_video->execute();
                        
                        // Record the reset
                        $insert_reset_query = $conn->prepare("
                            INSERT INTO quiz_resets (student_id, quiz_id, reset_count, reset_date)
                            VALUES (?, ?, 1, NOW())
                        ");
                        $insert_reset_query->bind_param("ii", $user_id, $quiz['id']);
                        $insert_reset_query->execute();
                        
                        $conn->commit();
                    } catch (Exception $e) {
                        $conn->rollback();
                        error_log("Error resetting progress: " . $e->getMessage());
                    }
                }
            }
        }
    }
}


// FIXED: First, fetch all course videos to get actual total count
$all_videos_query = $conn->prepare("
    SELECT id, video_sequence 
    FROM course_videos 
    WHERE course_id = ?
    ORDER BY video_sequence
");
$all_videos_query->bind_param("i", $course_id);
$all_videos_query->execute();
$all_videos_result = $all_videos_query->get_result();
$total_videos = $all_videos_result->num_rows;

// FIXED: Now fetch videos with their progress status and proper unlocking logic
$videos_query = $conn->prepare("
    SELECT DISTINCT
        cv.id AS video_id, 
        cv.video_title, 
        cv.video_path, 
        cv.video_sequence,
        cv.duration,
        COALESCE(vp.is_unlocked, CASE WHEN cv.video_sequence = 0 THEN 1 ELSE 0 END) AS is_unlocked,
        COALESCE(vp.is_completed, 0) AS is_completed,
        (SELECT MAX(vp2.video_sequence) 
         FROM video_progress vp2 
         WHERE vp2.student_id = ? AND vp2.course_id = ? AND vp2.is_completed = 1) AS max_completed_sequence
    FROM course_videos cv
    LEFT JOIN video_progress vp ON cv.id = vp.video_id AND vp.student_id = ?
    WHERE cv.course_id = ?
    ORDER BY cv.video_sequence
");

$videos_query->bind_param("iiii", $user_id, $course_id, $user_id, $course_id);
$videos_query->execute();
$videos_result = $videos_query->get_result();

$videos_data = [];
$completed_videos = 0;
$max_completed_sequence = -1;

while ($video = $videos_result->fetch_assoc()) {
    if ($video['is_completed']) {
        $completed_videos++;
        if ($video['video_sequence'] > $max_completed_sequence) {
            $max_completed_sequence = $video['video_sequence'];
        }
    }
    
    // FIXED: Update unlocking logic - a video is unlocked if it's the first video (sequence=0)
    // or if the previous video in sequence has been completed
    if ($video['video_sequence'] == 0) {
        $video['is_unlocked'] = 1;
    } else if ($video['video_sequence'] == $max_completed_sequence + 1 || 
              ($video['max_completed_sequence'] !== null && $video['video_sequence'] <= $video['max_completed_sequence'] + 1)) {
        $video['is_unlocked'] = 1;
    }
    
    $videos_data[] = $video;
}

// FIXED: Calculate progress based on total_videos from the separate query
$progress_percentage = $total_videos > 0 ? round(($completed_videos / $total_videos) * 100) : 0;

// Check if course is completed for certificate eligibility
$course_completed = ($total_videos > 0 && $completed_videos === $total_videos);

// Check if student can retake the quiz (only if all videos are completed again after reset)
$can_retake_quiz = $course_completed && $need_rewatch;

// Check if certificate already exists
$cert_query = $conn->prepare("
    SELECT id, certificate_number, issue_date 
    FROM certificates 
    WHERE student_id = ? AND course_id = ?
");
$cert_query->bind_param("ii", $user_id, $course_id);
$cert_query->execute();
$cert_result = $cert_query->get_result();
$has_certificate = $cert_result->num_rows > 0;
$certificate = $has_certificate ? $cert_result->fetch_assoc() : null;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($course['title']) ?></title>
    <link rel="stylesheet" href="assets/student.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        <style>
        .course-container { 
            max-width: 1000px; 
            margin: 0 auto; 
            padding: 20px; 
            font-family: Arial, sans-serif;
        }
        .back-button {
            display: inline-block;
            margin-bottom: 20px;
            text-decoration: none;
            color: #333;
            padding: 5px 10px;
            border-radius: 5px;
            background-color: #f2f2f2;
        }
        .progress-container {
            margin: 20px 0;
        }
        .progress-bar-bg {
            background-color: #eee;
            height: 20px;
            border-radius: 10px;
            overflow: hidden;
        }
        .progress-bar {
            height: 20px;
            background-color: #4CAF50;
            color: white;
            text-align: center;
            line-height: 20px;
            font-size: 12px;
            transition: width 0.5s;
        }
        .video-container {
            width: 640px;
            height: 360px;
            margin: 0 auto;
            overflow: hidden;
            position: relative;
        }
        .video-container video {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .video-item { 
            border: 1px solid #ddd; 
            border-radius: 8px; 
            overflow: hidden; 
            position: relative; 
            margin-bottom: 25px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .video-header { 
            padding: 15px; 
            background-color: #f5f5f5; 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
        }
        .video-status {
            font-size: 14px;
            font-weight: bold;
            padding: 4px 8px;
            border-radius: 4px;
        }
        .status-completed {
            background-color: #4CAF50;
            color: white;
        }
        .status-unlocked {
            background-color: #2196F3;
            color: white;
        }
        .status-locked {
            background-color: #f44336;
            color: white;
        }
        .locked-overlay { 
            position: absolute; 
            top: 0; 
            left: 0; 
            width: 100%; 
            height: 100%; 
            background: rgba(0,0,0,0.7); 
            display: flex; 
            justify-content: center; 
            align-items: center; 
            color: white; 
            font-size: 18px; 
        }
        .certificate-section {
            margin-top: 30px;
            padding: 20px;
            background-color: #f9f9f9;
            border-radius: 8px;
            text-align: center;
        }
        .quiz-section {
            margin-top: 30px;
            padding: 20px;
            background-color: #f0f8ff;
            border-radius: 8px;
            text-align: center;
        }
        .reset-notice {
            margin-top: 15px;
            padding: 15px;
            background-color: #ffebee;
            border-radius: 8px;
            text-align: center;
            border-left: 5px solid #f44336;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background-color: #4CAF50;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
            cursor: pointer;
            margin: 5px;
        }
        .btn-disabled {
            background-color: #cccccc;
            cursor: not-allowed;
        }
        .btn-quiz {
            background-color: #2196F3;
        }
        .btn-retake {
            background-color: #FF9800;
        }
        .attempt-info {
            margin: 10px 0;
            padding: 10px;
            background-color: #fff3cd;
            border-radius: 5px;
            font-size: 14px;
        }
        .quiz-results {
            margin: 15px 0;
            padding: 15px;
            background-color: #e8f5e9;
            border-radius: 5px;
            text-align: left;
        }
        .quiz-pass {
            color: #2e7d32;
            font-weight: bold;
        }
        .quiz-fail {
            color: #c62828;
            font-weight: bold;
        }
        </style>
    
</head>
<body>
<div class="course-container">
    <a href="my_course.php" class="back-button">← Back to My Courses</a>
    <h1><?= htmlspecialchars($course['title']) ?></h1>
    
    <?php if ($need_rewatch): ?>
    <div class="reset-notice">
        <h3>⚠️ Your Quiz Progress Has Been Reset</h3>
        <p>You've failed the quiz after 3 attempts. To retake the quiz, you must rewatch all course videos.</p>
        <p>Your video progress has been reset. Please watch all videos again to unlock the quiz.</p>
    </div>
    <?php endif; ?>
    
    <div class="progress-container">
        <h3>Your Progress: <?= $progress_percentage ?>% Complete</h3>
        <div class="progress-bar-bg">
            <div class="progress-bar" style="width: <?= $progress_percentage ?>%;">
                <?= $progress_percentage ?>%
            </div>
        </div>
    </div>

    <div class="videos-container">
        <?php foreach ($videos_data as $index => $video): ?>
            <div class="video-item" data-video-id="<?= $video['video_id'] ?>">
                <div class="video-header">
                    <h3><?= htmlspecialchars($video['video_title']) ?></h3>
                    <span class="video-status <?= 
                        $video['is_completed'] ? 'status-completed' : 
                        ($video['is_unlocked'] ? 'status-unlocked' : 'status-locked') 
                    ?>">
                        <?= $video['is_completed'] ? 'Completed' : 
                            ($video['is_unlocked'] ? 'Unlocked' : 'Locked') ?>
                    </span>
                </div>
                <div class="video-container">
                    <?php if ($video['is_unlocked']): ?>
                        <video controls 
                               id="video-player-<?= $video['video_id'] ?>"
                               data-video-id="<?= $video['video_id'] ?>" 
                               class="video-player">
                            <source src="<?= htmlspecialchars($video['video_path']) ?>" type="video/mp4">
                            Your browser does not support the video tag.
                        </video>
                        <!-- Hidden fields for tracking -->
                        <input type="hidden" class="student_id" value="<?= $user_id ?>">
                        <input type="hidden" class="course_id" value="<?= $course_id ?>">
                        <input type="hidden" class="video_id" value="<?= $video['video_id'] ?>">
                    <?php else: ?>
                        <div class="video-placeholder">
                            <div class="locked-overlay">
                                <div>
                                    <span style="font-size: 32px;">🔒</span><br>
                                    Complete previous video to unlock
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if ($course_completed || $can_retake_quiz): ?>
        <?php if ($has_quiz && $quiz_has_questions): ?>
            <div class="quiz-section">
                <h2>Assessment: <?= htmlspecialchars($quiz['title']) ?></h2>
                <p>You have completed all course videos. Test your knowledge with the assessment.</p>
                
                <?php if ($attempts_count > 0 && !$need_rewatch): ?>
                    <div class="quiz-results">
                        <h3>Your Latest Assessment Results</h3>
                        <p>Score: <strong><?= $latest_score ?>%</strong></p>
                        <p>Status: 
                            <?php if ($passed_quiz): ?>
                                <span class="quiz-pass">PASSED</span> 
                                (Required: <?= $quiz['passing_score'] ?>%)
                            <?php else: ?>
                                <span class="quiz-fail">NOT PASSED</span>
                                (Required: <?= $quiz['passing_score'] ?>%)
                            <?php endif; ?>
                        </p>
                        <a href="quiz_results.php?attempt_id=<?= $latest_attempt_id ?>" class="btn btn-quiz">View Detailed Results</a>
                    </div>
                <?php endif; ?>
                
                <?php if ($need_rewatch && $course_completed): ?>
                    <div class="attempt-info">
                        <p>You have rewatched all course videos and can now retake the quiz.</p>
                        <a href="take_quiz.php?id=<?= $quiz['id'] ?>" class="btn btn-quiz">Take Quiz Again</a>
                    </div>
                <?php elseif (!$need_rewatch): ?>
                    <div class="attempt-info">
    <p>Quiz Attempts: <strong><?= min($attempts_count, $max_attempts) ?> of <?= $max_attempts ?> used</strong>
    <?php if ($reset_count > 0): ?>
        <span class="attempt-cycle">(Attempt cycle: <?= $reset_count + 1 ?>)</span>
    <?php endif; ?>
    </p>
    <?php if ($attempts_count < $max_attempts && !$passed_quiz): ?>
        <a href="take_quiz.php?id=<?= $quiz['id'] ?>" class="btn btn-quiz">
            <?= $attempts_count > 0 ? 'Retake Quiz' : 'Take Quiz' ?>
        </a>
    <?php elseif ($attempts_count >= $max_attempts && !$passed_quiz): ?>
        <p><strong>You have used all allowed attempts but haven't passed.</strong></p>
        <p>You need to rewatch all course videos to unlock the quiz again.</p>
    <?php endif; ?>
</div>
                <?php endif; ?>
            </div>
        <?php elseif ($has_quiz && !$quiz_has_questions): ?>
            <div class="quiz-section">
                <h2>Quiz Not Ready</h2>
                <p>The quiz for this course doesn't have any questions yet.</p>
            </div>
        <?php else: ?>
            <div class="quiz-section">
                <h2>No Quiz Available</h2>
                <p>You've completed all videos, but there's no quiz for this course.</p>
            </div>
        <?php endif; ?>

        <div class="certificate-section">
            <h2>Course Completed! 🎉</h2>
            <?php if ($has_certificate): ?>
                <p>Congratulations! You have completed this course and earned a certificate.</p>
                <p>Certificate Number: <?= htmlspecialchars($certificate['certificate_number']) ?></p>
                <p>Issue Date: <?= date('F j, Y', strtotime($certificate['issue_date'])) ?></p>
                <a href="download_certificate.php?id=<?= $certificate['id'] ?>" class="btn">Download Certificate</a>
            <?php else: ?>
                <p>Congratulations! You have completed all videos in this course.</p>
                <?php if ($has_quiz && $quiz_has_questions && !$passed_quiz): ?>
                    <p>Pass the quiz to generate your certificate.</p>
                    <button class="btn btn-disabled">Certificate Not Available Yet</button>
                <?php else: ?>
                    <button id="generate-cert" class="btn">Generate Certificate</button>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="quiz-section">
            <h2>Quiz Locked</h2>
            <p>Complete all videos to unlock the quiz.</p>
            <button class="btn btn-disabled">Quiz Not Available Yet</button>
        </div>

        <div class="certificate-section">
            <h2>Certificate</h2>
            <p>Complete all videos to earn your course completion certificate.</p>
            <button class="btn btn-disabled">Certificate Not Available Yet</button>
        </div>
    <?php endif; ?>
</div>

<script>
function markVideoComplete(videoId, courseId) {
    console.log("Marking video complete:", videoId, "for course:", courseId);
    
    $(".videos-container").append('<div id="progress-overlay">Updating progress...</div>');
    
    $.ajax({
        url: 'update_progress.php',
        type: 'POST',
        data: { 
            video_id: videoId,
            course_id: courseId
        },
        dataType: 'json',
        success: function(response) {
            console.log("Server response:", response);
            if (response.success) {
                $("#progress-overlay").text('Progress saved! Refreshing...');
                setTimeout(function() {
                    location.reload();
                }, 1000);
            } else {
                console.error('Server error:', response.message);
                $("#progress-overlay").text('Error: ' + response.message);
                setTimeout(function() {
                    $("#progress-overlay").remove();
                }, 3000);
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX Error:', status, error);
            console.error('Response Text:', xhr.responseText);
            
            let errorMessage = 'An error occurred while updating progress. Please try again.';
            try {
                const response = JSON.parse(xhr.responseText);
                if (response.message) {
                    errorMessage = response.message;
                }
            } catch (e) {
                // Use default error message if can't parse JSON
            }
            
            $("#progress-overlay").text('Error: ' + errorMessage);
            setTimeout(function() {
                $("#progress-overlay").remove();
            }, 3000);
        }
    });
}

function trackActivity(activityType, courseId, resourceId, metadata = {}) {
    $.ajax({
        url: 'track_activity.php',
        type: 'POST',
        data: {
            activity_type: activityType,
            course_id: courseId,
            resource_id: resourceId || 0,
            metadata: metadata
        },
        dataType: 'json',
        error: function(xhr, status, error) {
            console.error('Activity tracking error:', error);
        }
    });
}

$(document).ready(function() {
    const courseId = <?= $course_id ?>;

    // Track page view when page loads
    trackActivity('page_view', courseId, 0, {
        page: 'course_view',
        title: '<?= addslashes($course['title']) ?>'
    });
    
    // Track video interactions
    $('.video-player').each(function() {
        const videoPlayer = this;
        const videoId = $(this).data('video-id');
        
        // Track video start
        $(videoPlayer).on('play', function() {
            if (this.currentTime < 1) {
                trackActivity('video_start', courseId, videoId, {
                    time: this.currentTime,
                    title: $(this).closest('.video-item').find('h3').text()
                });
            } else {
                trackActivity('video_resume', courseId, videoId, {
                    time: this.currentTime,
                    title: $(this).closest('.video-item').find('h3').text()
                });
            }
        });
        
        // Track video pause
        $(videoPlayer).on('pause', function() {
            if (this.currentTime < this.duration) {
                trackActivity('video_pause', courseId, videoId, {
                    time: this.currentTime,
                    percentage: Math.round((this.currentTime / this.duration) * 100),
                    title: $(this).closest('.video-item').find('h3').text()
                });
            }
        });
        
        // Track video seeking
        $(videoPlayer).on('seeking', function() {
            trackActivity('video_seek', courseId, videoId, {
                from: videoPlayer.lastTime || 0,
                to: this.currentTime,
                title: $(this).closest('.video-item').find('h3').text()
            });
            videoPlayer.lastTime = this.currentTime;
        });
        
        // Track video end
        $(videoPlayer).on('ended', function() {
            const videoItem = $(this).closest('.video-item');
            
            trackActivity('video_end', courseId, videoId, {
                duration: this.duration,
                title: videoItem.find('h3').text()
            });
            
            markVideoComplete(videoId, courseId);
        });
    });
    
    // Certificate generation
    $('#generate-cert').on('click', function() {
        $(this).prop('disabled', true).text('Generating...');
        
        trackActivity('certificate_view', courseId, 0);
        
        $.ajax({
            url: 'generate_certificate.php',
            type: 'POST',
            data: { course_id: courseId },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    alert('Certificate generated successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + response.message);
                    $('#generate-cert').prop('disabled', false).text('Generate Certificate');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', xhr.responseText);
                alert('An error occurred while generating the certificate.');
                $('#generate-cert').prop('disabled', false).text('Generate Certificate');
            }
        });
    });
    
    // Track resource downloads
    $('.resource-download').on('click', function() {
        const resourceId = $(this).data('resource-id');
        const resourceName = $(this).data('resource-name');
        
        trackActivity('resource_download', courseId, resourceId, {
            name: resourceName
        });
    });
    
    // Track page time
    const pageLoadTime = new Date();
    $(window).on('beforeunload', function() {
        navigator.sendBeacon('track_activity.php', new URLSearchParams({
            activity_type: 'page_view',
            course_id: courseId,
            resource_id: 0,
            metadata: JSON.stringify({
                action: 'leave',
                page: 'course_view',
                title: '<?= addslashes($course['title']) ?>',
                time_spent_seconds: Math.floor((new Date() - pageLoadTime) / 1000)
            })
        }));
    });
});

// Improved attendance tracking system
document.addEventListener('DOMContentLoaded', function() {
    // Track each video separately
    document.querySelectorAll('.video-player').forEach(function(videoElement) {
        if (!videoElement) return;
        
        const videoId = videoElement.dataset.videoId;
        const courseId = videoElement.closest('.video-item').querySelector('.course_id').value;
        
        if (!videoId || !courseId) {
            console.error('Missing required parameters for attendance tracking');
            return;
        }
        
        // Variables for tracking this specific video
        let watchedTime = 0;
        let trackingInterval;
        let attendanceMarked = false;
        let heartbeatInterval;
        let inactivityTimer;
        let lastActivityTime = Date.now();
        let trackingStarted = false;
        let attendanceEnded = false;
        
        // Track user activity
        function resetInactivityTimer() {
            lastActivityTime = Date.now();
            clearTimeout(inactivityTimer);
            
            inactivityTimer = setTimeout(function() {
                if (!videoElement.paused) {
                    trackActivity('video_pause', courseId, videoId, {
                        time: videoElement.currentTime,
                        reason: 'user_inactivity',
                        inactive_time: '5_minutes'
                    });
                    videoElement.pause();
                }
            }, 5 * 60 * 1000);
        }
        
        // User activity detection
        ['mousemove', 'mousedown', 'keypress', 'touchstart', 'scroll'].forEach(function(event) {
            document.addEventListener(event, resetInactivityTimer, true);
        });
        
        // Start tracking when video plays
        videoElement.addEventListener('play', function() {
            resetInactivityTimer();
            
            if (!trackingStarted) {
                trackingStarted = true;
                attendanceEnded = false;
                
                fetch('track_attendance.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        'activity_type': 'video_watch',
                        'course_id': courseId,
                        'video_id': videoId,
                        'watched_time': 0,
                        'action': 'start'
                    })
                })
                .then(response => response.json())
                .catch(error => {
                    console.error('Error starting attendance tracking:', error);
                });
            }
            
            clearInterval(trackingInterval);
            
            trackingInterval = setInterval(function() {
                if (!videoElement.paused) {
                    watchedTime += 1;
                    
                    if (watchedTime >= 60 && !attendanceMarked) {
                        attendanceMarked = true;
                        updateAttendance('update');
                    }
                }
            }, 1000);
            
            clearInterval(heartbeatInterval);
            
            heartbeatInterval = setInterval(function() {
                const idleTime = (Date.now() - lastActivityTime) / 1000;
                
                trackActivity('heartbeat', courseId, videoId, {
                    video_time: videoElement.currentTime,
                    video_playing: !videoElement.paused,
                    idle_time_seconds: Math.floor(idleTime),
                    watched_time: watchedTime
                });
            }, 30 * 1000);
        });
        
        // Stop tracking when video pauses
        videoElement.addEventListener('pause', function() {
            clearInterval(trackingInterval);
            
            if (watchedTime > 0 && !attendanceEnded) {
                updateAttendance('update');
            }
        });
        
        // Final update when video ends
        videoElement.addEventListener('ended', function() {
            clearInterval(trackingInterval);
            clearInterval(heartbeatInterval);
            
            if (!attendanceEnded) {
                if (watchedTime < 1) {
                    watchedTime = 1;
                }
                
                updateAttendance('end');
                attendanceEnded = true;
                
                setTimeout(() => markVideoComplete(videoId, courseId), 500);
            }
        });
        
        // Update when user leaves the page
        window.addEventListener('beforeunload', function() {
            clearInterval(trackingInterval);
            clearInterval(heartbeatInterval);
            
            if (watchedTime > 0 && !attendanceEnded) {
                const formData = new FormData();
                formData.append('activity_type', 'video_watch');
                formData.append('course_id', courseId);
                formData.append('video_id', videoId);
                formData.append('watched_time', Math.floor(watchedTime));
                formData.append('action', 'end');
                
                navigator.sendBeacon('track_attendance.php', formData);
                attendanceEnded = true;
            }
        });
        
        // Function to update attendance
        function updateAttendance(action = 'update') {
            if (watchedTime === 0 && action !== 'start') {
                return;
            }
            
            fetch('track_attendance.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    'activity_type': 'video_watch',
                    'course_id': courseId,
                    'video_id': videoId,
                    'watched_time': Math.floor(watchedTime),
                    'action': action
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (action === 'end') {
                        attendanceEnded = true;
                    }
                } else {
                    console.error('Error updating attendance:', data.message);
                }
            })
            .catch(error => {
                console.error('Error updating attendance:', error);
            });
        }
    });
});
</script>
</body>
</html>