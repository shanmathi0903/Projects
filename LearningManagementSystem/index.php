<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LMS - Learning Management System</title>
    <link rel="stylesheet" href="assets/styles.css">
</head>
<body>

<!-- Navigation Bar -->
<nav>
    <div class="logo">LMS</div>
    <ul class="nav-links">
        <li><a href="index.php">Home</a></li>
        <li><a href="login.php">Login</a></li>
        
    </ul>
</nav>

<!-- Hero Section -->
<section class="hero">
    <div class="hero-content">
        <span class="badge">Education</span>
        <h1 class="fade-in">LMS Learning Management System</h1>
        <p class="fade-in">
            A platform where knowledge meets innovation. Experience top-tier online learning today!
        </p>
        <a href="register.php" class="btn bounce">Start Today <span>→</span></a>
    </div>
    <div class="hero-image">
        <img src="indexImage.jpg" alt="LMS Banner">
    </div>
</section>



<!-- Course Section -->
<section class="courses">
    <h2>Explore Our Courses</h2>
    <p>Choose from a variety of courses and enhance your learning experience.</p>
    <div class="course-list">
        <div class="course-card">
            <h3>Web Development</h3>
            <p>Learn HTML, CSS, JavaScript, and PHP.</p>
        </div>
        <div class="course-card">
            <h3>Python for Beginners</h3>
            <p>Master Python from scratch with hands-on projects.</p>
        </div>
        <div class="course-card">
            <h3>Machine Learning</h3>
            <p>Understand AI & Machine Learning concepts.</p>
        </div>
    </div>
</section>

<!-- Footer -->
<footer>
    <p>&copy; 2025 Learning Management System. All Rights Reserved.</p>
</footer>

</body>
</html>
