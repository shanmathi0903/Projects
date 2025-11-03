<?php
session_start();
include 'config.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    $password = $_POST['password'];
    $role = $_POST['role'];

    // Validate role selection
    $valid_roles = ['admin', 'teacher', 'student'];
    if (!in_array($role, $valid_roles)) {
        echo "<script>alert('Invalid role selected!'); window.location='login.php';</script>";
        exit();
    }

    // Role-to-table mapping (avoiding direct table name injection)
    $table_mapping = [
        'admin' => 'admins',
        'teacher' => 'teachers',
        'student' => 'students'
    ];
    $table = $table_mapping[$role];

    // Prepare statement to prevent SQL injection
    $stmt = $conn->prepare("SELECT * FROM $table WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();

    if ($result && password_verify($password, $result['password'])) {


        if ($role === 'teacher' && $result['password_changed'] == 0) {
            $now = time();
            $account_creation = $result['creation_time']; // Make sure this column exists
            $time_diff = $now - $account_creation;
            $hours_diff = $time_diff / 3600; // Convert seconds to hours
            
            // If account is older than 24 hours and password hasn't been changed
            if ($hours_diff > 24) {
                // Account expired
                echo "<script>alert('Your account activation period has expired. Please contact the administrator.'); window.location='login.php';</script>";
                exit();
            }
        }


        $_SESSION['user'] = $result['id'];
        $_SESSION['role'] = $role;
        $_SESSION['name'] = $result['name'];

        // Redirect based on role
        if ($role === 'teacher') {
            $_SESSION['teacher_id'] = $result['id']; // Ensure teacher_id is stored
            
            // Redirect teacher to change password page if needed
            if ($result['password_changed'] == 0) {
                header("Location: change_password.php");
            } else {
                header("Location: teacher_dashboard.php");
            }
            exit();
        }
        elseif ($role === 'student') {
            $_SESSION['student_id'] = $result['id']; // Store student ID
            header("Location: student_dashboard.php");
        
            // ✅ Check if it's the first login
            if ($result['first_login'] == 1) {
                sendWelcomeEmail($email, $result['name'], $result['id']);
        
                // ✅ Update first_login to 0 (so email is not sent again)
                $updateStmt = $conn->prepare("UPDATE students SET first_login = 0 WHERE id = ?");
                $updateStmt->bind_param("i", $result['id']);
                $updateStmt->execute();
                $updateStmt->close();
            }
        
            exit();
        }
         elseif ($role === 'admin') {
            $_SESSION['admin_id'] = $result['id']; // Store admin ID
            header("Location: admin_dashboard.php");
            exit();
        }
    } else {
        echo "<script>alert('Invalid email, password, or role!'); window.location='login.php';</script>";
        exit();
    }
}
?>




<!DOCTYPE html>
<html>
<head>
    <title>Login</title>
    <link rel="stylesheet" href="assets/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500&display=swap" rel="stylesheet">

</head>
<body style="background: linear-gradient(to right, #0d0d2b, #3b1f57); margin: 0; padding: 0; height: 100vh; display: flex; justify-content: center; align-items: center;">

    <div class="login-container">
        <div class="login-box">
            <h2>Login</h2>
            <form method="post" action="login.php">
                <div class="input-container">
                    <input type="text" name="email" placeholder="Email" required>
                    <div class="input-icon email"></div>
                </div>
                <div class="input-container">
                    <input type="password" name="password" placeholder="Password / OTP" required>
                    <div class="input-icon password"></div>
                </div>
                <div class="input-container">
                    <select name="role" required>
                        <option value="" disabled selected>Select Role</option> <option value="student">Student</option>
                        <option value="teacher">Instructor</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                <div class="forgot-password-link">
                    <a href="forgot_password.php">Forgot Password?</a>
                </div>
                <button type="submit">Login</button>
            </form>
            <div class="register-link">
                Don't have an account? <a href="register.php">Register</a>
            </div>
        </div>
    </div>
</body>
</html>