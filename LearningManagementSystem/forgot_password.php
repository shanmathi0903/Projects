<?php
session_start();
include 'config.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php'; // Include PHPMailer

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);

    // Check if the email exists in teachers or students table
    $stmt = $conn->prepare("SELECT 'teacher' AS role FROM teachers WHERE email = ? 
                            UNION 
                            SELECT 'student' AS role FROM students WHERE email = ?");
    $stmt->bind_param("ss", $email, $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $role = $row['role'];

        // Generate OTP and expiry time (valid for 10 minutes)
        $otp = rand(100000, 999999);
        $otp_expiry = date('Y-m-d H:i:s', time() + 600);

        if ($role === 'teacher') {
            $update_stmt = $conn->prepare("UPDATE teachers SET otp=?, otp_expiry=? WHERE email=?");
        } else {
            $update_stmt = $conn->prepare("UPDATE students SET otp=?, otp_expiry=? WHERE email=?");
        }

        $update_stmt->bind_param("sss", $otp, $otp_expiry, $email);
        $update_stmt->execute();

        // Store session variables
        $_SESSION['email'] = $email;
        $_SESSION['role'] = $role;

        // Send OTP via email using PHPMailer
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com'; // Use your SMTP server
            $mail->SMTPAuth = true;
            $mail->Username = 'rasiramalingam32@gmail.com'; // Replace with your Gmail
            $mail->Password = 'ivlf rzwd anqx ogjx'; // Use App Password (if 2FA enabled)
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;

            $mail->setFrom('rasiramalingam32@gmail.com', 'Admin');
            $mail->addAddress($email);

            $mail->isHTML(true);
            $mail->Subject = 'Your OTP for Password Reset';
            $mail->Body = "Hello,<br><br>Your OTP for password reset is <b>$otp</b>. It is valid for 10 minutes.<br><br>Thanks!";
            $mail->send();

            echo "<script>alert('OTP sent successfully! Check your email.'); window.location.href='verify_otp.php';</script>";
        } catch (Exception $e) {
            echo "<script>alert('Failed to send OTP. Please try again.');</script>";
        }
    } else {
        echo "<script>alert('Email not found.'); window.location.href='forgot_password.php';</script>";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Forgot Password</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body style="background: linear-gradient(to right, #0d0d2b, #3b1f57); margin: 0; padding: 0; height: 100vh; display: flex; justify-content: center; align-items: center;">

    <div class="login-container">
        <div class="login-box">
            <h2>Forgot Password</h2>
            <form method="post" action="forgot_password.php">
                <div class="input-container">
                    <input type="email" name="email" placeholder="Enter Registered Email" required>
                    <div class="input-icon email"></div>
                </div>
                <button type="submit">Send OTP</button>
            </form>
            <div class="register-link">
                <a href="login.php">Back to Login</a>
            </div>
        </div>
    </div>
</body>
</html>