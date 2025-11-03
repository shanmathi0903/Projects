<?php
session_start();
include 'config.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require 'vendor/autoload.php';

// Ensure the admin is logged in
if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $_POST['name'];
    $email = $_POST['email'];
    
    // First check if the email already exists
    $check_stmt = $conn->prepare("SELECT COUNT(*) as count FROM teachers WHERE email = ?");
    $check_stmt->bind_param("s", $email);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    $row = $result->fetch_assoc();
    $check_stmt->close();
    
    if ($row['count'] > 0) {
        // Email already exists
        echo "<script>alert('Error: A teacher with this email already exists!'); window.location='add_teacher.php';</script>";
    } else {
        // Email is unique, proceed with adding the teacher
        $password = generateRandomPassword(); // Auto-generate password for security
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $creation_time = time(); // Current timestamp
        
        // Insert teacher details into the database with creation timestamp
        $stmt = $conn->prepare("INSERT INTO teachers (name, email, password, password_changed, creation_time) VALUES (?, ?, ?, 0, ?)");
        $stmt->bind_param("sssi", $name, $email, $hashedPassword, $creation_time);
        
        if ($stmt->execute()) {
            sendEmail($email, $password, $name); // Send login credentials via email
            echo "<script>alert('Teacher added successfully! Login details sent to their email.'); window.location='admin_dashboard.php';</script>";
        } else {
            echo "<script>alert('Error adding teacher: " . $stmt->error . "'); window.location='add_teacher.php';</script>";
        }
        $stmt->close();
    }
}

// Function to generate a random password
function generateRandomPassword($length = 8) {
    return substr(str_shuffle("abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%&*"), 0, $length);
}

// Function to send email
function sendEmail($email, $password, $name = '') {
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'rasiramalingam32@gmail.com'; // Change this
        $mail->Password = 'ivlf rzwd anqx ogjx'; // Change this
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->setFrom('your-email@gmail.com', 'LMS System');
        $mail->addAddress($email);
        $mail->isHTML(true);
        $mail->Subject = 'LMS - Your Teacher Account Details';
        
        // Personalize greeting if name is provided
        $greeting = !empty($name) ? "Welcome to LMS, $name!" : "Welcome to LMS!";
        
        $mail->Body = "
            <h3>$greeting</h3>
            <p>Your account has been created. Below are your login details:</p>
            <p><b>Email:</b> $email</p>
            <p><b>Temporary Password:</b> $password</p>
            <p><b>Note:</b> You must log in within 24 hours to activate your account!</p>
            <p>Please log in and change your password immediately.</p>
            <br>
        ";

        if ($mail->send()) {
            error_log("Teacher account details sent to: " . $email);
        }
    } catch (Exception $e) {
        echo "<script>alert('Email sending failed: " . $mail->ErrorInfo . "');</script>";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Add Teacher</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body style="background: linear-gradient(to right, #0d0d2b, #3b1f57); margin: 0; padding: 0; height: 100vh; display: flex; justify-content: center; align-items: center;">

    <div class="login-container">
        <div class="login-box">
            <h2>Add Teacher</h2>
            <form method="post" action="add_teacher.php">
                <div class="input-container">
                    <input type="text" name="name" placeholder="Teacher Name" required>
                    <div class="input-icon user"></div>
                </div>
                <div class="input-container">
                    <input type="email" name="email" placeholder="Email" required>
                    <div class="input-icon email"></div>
                </div>
                <button type="submit">Add Teacher</button>
            </form>
            <div class="register-link">
                <a href="admin_dashboard.php">Back to Dashboard</a>
            </div>
        </div>
    </div>
</body>
</html>
