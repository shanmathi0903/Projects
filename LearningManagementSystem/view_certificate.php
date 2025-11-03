<?php
include("config.php");
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['user']) || !isset($_SESSION['role'])) {
    die("Session variables missing! Please log in again.");
}

if ($_SESSION['role'] !== 'student') {
    die("Unauthorized access! Only students can view certificates.");
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid certificate ID!");
}

$user_id = $_SESSION['user'];
$certificate_id = (int)$_GET['id'];

// ✅ Ensure certificate belongs to the logged-in student
$cert_query = $conn->prepare("
    SELECT s.name, c.title AS course_title, cert.certificate_number, cert.issue_date
    FROM certificates cert
    JOIN students s ON cert.student_id = s.id
    JOIN courses c ON cert.course_id = c.id
    WHERE cert.student_id = ? AND cert.course_id = ?
");

$cert_query->bind_param("ii", $user_id, $certificate_id);
$cert_query->execute();
$cert_result = $cert_query->get_result();

if ($cert_result->num_rows === 0) {
    die("<h3 style='color:red;'>❌ No certificate found for you in this course.</h3>");
}

// Fetch data
$certificate = $cert_result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificate</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            text-align: center;
            margin: 50px;
        }
        .certificate-container {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            display: inline-block;
        }
        h1 {
            color: #4CAF50;
        }
        .details {
            font-size: 18px;
            margin-top: 10px;
        }
        .download-btn {
            display: inline-block;
            padding: 10px 20px;
            margin-top: 20px;
            color: white;
            background: #4CAF50;
            text-decoration: none;
            border-radius: 5px;
            font-size: 16px;
        }
        .download-btn:hover {
            background: #45a049;
        }
    </style>
</head>
<body>

    <div class="certificate-container">
        <h1>Certificate of Completion</h1>
        <p class="details"><strong>Student Name:</strong> <?php echo htmlspecialchars($certificate['name']); ?></p>
        <p class="details"><strong>Course Title:</strong> <?php echo htmlspecialchars($certificate['course_title']); ?></p>
        <p class="details"><strong>Certificate Number:</strong> <?php echo htmlspecialchars($certificate['certificate_number']); ?></p>
        <p class="details"><strong>Issue Date:</strong> <?php echo htmlspecialchars($certificate['issue_date']); ?></p>

        <a href="download_certificate.php?id=<?php echo $certificate_id; ?>" class="download-btn">Download Certificate</a>
    </div>

</body>
</html>
