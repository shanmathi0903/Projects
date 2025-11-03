<?php
session_start();
include 'config.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_SESSION['email'] ?? '';
    $role = $_SESSION['role'] ?? '';
    $entered_otp = $_POST['otp'] ?? '';

    if (empty($email) || empty($entered_otp)) {
        echo "<script>alert('Invalid request. Please try again.'); window.location='login.php';</script>";
        exit();
    }

    // Fetch user details from the database
    if ($role === 'teacher') {
        $stmt = $conn->prepare("SELECT password_changed FROM teachers WHERE email = ?");
    } else {
        $stmt = $conn->prepare("SELECT otp, otp_expiry FROM students WHERE email = ?");
    }
    
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        if ($role === 'teacher') {
            $stmt->bind_result($password_changed);
            $stmt->fetch();
            
            if ($password_changed == 0) {
                // Redirect to change password for first-time login
                header("Location: change_password.php");
            } else {
                // Redirect to reset password for forgot password case
                header("Location: reset_password.php");
            }
        } else {
            $stmt->bind_result($stored_otp, $otp_expiry);
            $stmt->fetch();
            
            if ($entered_otp == $stored_otp && strtotime($otp_expiry) > time()) {
                // Redirect student to reset password page
                header("Location: reset_password.php");
            } else {
                echo "<script>alert('Invalid or expired OTP.'); window.location='verify_otp.php';</script>";
            }
        }
        exit();
    } else {
        echo "<script>alert('OTP not found. Please try again.'); window.location='verify_otp.php';</script>";
    }
    
    $stmt->close();
}
$conn->close();
?>





<!DOCTYPE html>
<html>
<head>
    <title>Verify OTP</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body style="background: linear-gradient(to right, #0d0d2b, #3b1f57); margin: 0; padding: 0; height: 100vh; display: flex; justify-content: center; align-items: center;">

    <div class="login-container">
        <div class="login-box">
            <h2>Enter OTP</h2>
            <form method="post" action="verify_otp.php">
                <div class="input-container">
                    <input type="text" name="otp" placeholder="Enter OTP" required>
                </div>
                <button type="submit">Verify</button>
            </form>
            <div class="register-link">
                <a href="login.php">Back to Login</a>
            </div>
        </div>
    </div>
</body>
</html>