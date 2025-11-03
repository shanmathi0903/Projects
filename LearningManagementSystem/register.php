<?php
session_start();
include 'config.php';
require 'vendor/autoload.php'; // Include PHPMailer

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $password = $_POST['password'];

    // Hash the password before storing
    $hashed_password = password_hash($password, PASSWORD_BCRYPT);

    // ✅ Check if email exists in students table
    $stmt = $conn->prepare("SELECT email FROM students WHERE email = ?");
    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        echo "<script>alert('This email is already registered! Please use a different email.'); window.location.href='register.php';</script>";
        $stmt->close();
        exit();
    }

    $stmt->close();

    // ✅ Insert student into `students` table (No Enrollment Number)
    $stmt = $conn->prepare("INSERT INTO students (name, email, password) VALUES (?, ?, ?)");
    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param("sss", $name, $email, $hashed_password);

    if ($stmt->execute()) {
        // ✅ Send welcome email after successful registration
        sendWelcomeEmail($email, $name);
        echo "<script>alert('Registration successful! Please check your email.'); window.location.href='login.php';</script>";
    } else {
        echo "Error: " . $stmt->error;
    }

    $stmt->close();
    $conn->close();
}

// ✅ Function to send welcome email
function sendWelcomeEmail($email, $fullname) {
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'rasiramalingam32@gmail.com'; // ⚠️ Change this to your email
        $mail->Password = 'ivlf rzwd anqx ogjx'; // ⚠️ Use App Password, NOT your actual password!
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->setFrom('your_email@gmail.com', 'LMS System');
        $mail->addAddress($email);

        $mail->Subject = 'Welcome to LMS!';
        $mail->Body = "Hello $fullname,\n\nCongratulations! You have successfully registered on our Learning Management System.\n\nBest Regards,\nLMS Team";

        $mail->send();
    } catch (Exception $e) {
        error_log("Failed to send welcome email: " . $mail->ErrorInfo);
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Student Registration</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body style="background: linear-gradient(to right, #0d0d2b, #3b1f57); margin: 0; padding: 0; height: 100vh; display: flex; justify-content: center; align-items: center;">

    <div class="login-container">
        <div class="login-box">
            <h2>Student Registration</h2>
            <form method="post">
                <div class="input-container">
                    <input type="text" name="name" placeholder="Name" required>
                    <div class="input-icon user"></div>
                </div>
                <div class="input-container">
                    <input type="email" name="email" placeholder="Email" required>
                    <div class="input-icon email"></div>
                </div>
                <div class="input-container">
                    <input type="password" name="password" placeholder="Password" required>
                    <div class="input-icon password"></div>
                </div>
                <button type="submit">Register</button>
            </form>
            <div class="register-link">
                Already have an account? <a href="login.php">Login</a>
            </div>
        </div>
    </div>
</body>
</html>
