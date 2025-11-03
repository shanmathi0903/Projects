<?php
session_start();
include 'config.php';

if (!isset($_SESSION['email'])) {
    echo "<script>alert('Session expired! Request a new OTP.'); window.location.href='forgot_password.php';</script>";
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_SESSION['email'];
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);

    if ($password !== $confirm_password) {
        echo "<script>alert('Passwords do not match! Please try again.'); window.location.href='reset_password.php';</script>";
        exit();
    }

    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Determine if the user is a teacher or student
    $stmt = $conn->prepare("SELECT 'teacher' AS role FROM teachers WHERE email = ? 
                            UNION 
                            SELECT 'student' AS role FROM students WHERE email = ?");
    $stmt->bind_param("ss", $email, $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $role = $row['role'];

        if ($role === 'teacher') {
            $update_stmt = $conn->prepare("UPDATE teachers SET password=? WHERE email=?");
        } else {
            $update_stmt = $conn->prepare("UPDATE students SET password=? WHERE email=?");
        }

        $update_stmt->bind_param("ss", $hashed_password, $email);
        $update_stmt->execute();

        // Clear session after reset
        session_destroy();

        echo "<script>alert('Password reset successfully! Please log in.'); window.location.href='login.php';</script>";
        exit();
    } else {
        echo "<script>alert('User not found! Try again.'); window.location.href='forgot_password.php';</script>";
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reset Password</title>
    <link rel="stylesheet" href="assets/style.css">
    <script>
        function validatePasswords() {
            let password = document.getElementById("password").value;
            let confirmPassword = document.getElementById("confirm_password").value;
            
            if (password !== confirmPassword) {
                alert("Passwords do not match!");
                return false;
            }
            return true;
        }
    </script>
</head>
<body style="background: linear-gradient(to right, #0d0d2b, #3b1f57); margin: 0; padding: 0; height: 100vh; display: flex; justify-content: center; align-items: center;">

    <div class="login-container">
        <div class="login-box">
            <h2>Reset Password</h2>
            <form method="post" onsubmit="return validatePasswords()">
                <div class="input-container">
                    <input type="password" id="password" name="password" placeholder="Enter New Password" required>
                    <div class="input-icon password"></div>
                </div>
                <div class="input-container">
                    <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm New Password" required>
                    <div class="input-icon password"></div>
                </div>
                <button type="submit">Reset Password</button>
            </form>
            <div class="register-link">
                <a href="login.php">Back to Login</a>
            </div>
        </div>
    </div>
</body>
</html>
