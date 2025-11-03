<?php 
session_start();
include 'config.php';

// Ensure the user is logged in and is a teacher
if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'teacher') {
    header("Location: login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if ($new_password === $confirm_password) {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE teachers SET password=?, password_changed=1 WHERE id=?");
        $stmt->bind_param("si", $hashed_password, $_SESSION['user']);
        $stmt->execute();
        
        echo "<script>alert('Password changed successfully!'); window.location='login.php';</script>";
    } else {
        echo "<script>alert('Passwords do not match!');</script>";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Change Password</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body style="background: linear-gradient(to right, #0d0d2b, #3b1f57); margin: 0; padding: 0; height: 100vh; display: flex; justify-content: center; align-items: center;">
    
    <div class="login-container">
        <div class="login-box">
            <h2>Change Password</h2>
            <form method="post" action="change_password.php">
                <div class="input-container">
                    <input type="password" name="new_password" placeholder="New Password" required>
                    <div class="input-icon password"></div>
                </div>
                <div class="input-container">
                    <input type="password" name="confirm_password" placeholder="Confirm Password" required>
                    <div class="input-icon password"></div>
                </div>
                <button type="submit">Change Password</button>
            </form>
            <div class="register-link">
                <a href="login.php">Back to Login</a>
            </div>
        </div>
    </div>
</body>
</html>