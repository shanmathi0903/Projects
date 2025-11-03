<?php
include("config.php");
session_start();

// Validate student access
if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'student' || !isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: my_course.php");
    exit();
}

$user_id = $_SESSION['user'];
$certificate_id = $_GET['id'];

// Get certificate details
$cert_query = $conn->prepare("
    SELECT 
        cert.id,
        cert.certificate_number,
        cert.issue_date,
        c.title AS course_title,
        s.name AS student_name
    FROM certificates cert
    JOIN courses c ON cert.course_id = c.id
    JOIN students s ON cert.student_id = s.id
    WHERE cert.id = ? AND cert.student_id = ?
");
$cert_query->bind_param("ii", $certificate_id, $user_id);
$cert_query->execute();
$cert_result = $cert_query->get_result();

if ($cert_result->num_rows === 0) {
    header("Location: my_course.php");
    exit();
}

$certificate = $cert_result->fetch_assoc();

// Format date
$issue_date = new DateTime($certificate['issue_date']);
$formatted_date = $issue_date->format('F d, Y');

// Generate verification URL using the exact same host as the current page
// This ensures port numbers and protocols match what's working for the user
$verification_url = "http://" . $_SERVER['HTTP_HOST'] . "/verify_certificate.php?code=" . urlencode($certificate['certificate_number']);

// Output HTML certificate
header('Content-Type: text/html');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificate of Completion - <?php echo htmlspecialchars($certificate['student_name']); ?></title>
    <!-- Include QR code library -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <style>
        @page {
            size: 950px 650px; /* Increased to accommodate margins */
            margin: 0;
        }
        
        @media print {
            body {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
                margin: 0;
                padding: 0;
                width: 950px;
                height: 650px;
                overflow: hidden;
            }
            .no-print {
                display: none;
            }
            .container {
                margin: 0 !important;
                box-shadow: none !important;
            }
            .certificate-wrapper {
                padding: 25px;
                background-color: white;
            }
        }
        
        html, body {
            margin: 0;
            padding: 0;
            background-color: #f5f5f5;
            height: 100%;
            width: 100%;
        }
        
        body {
            font-family: Arial, sans-serif;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        
        .container {
            width: 900px;
            background-color: white;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            display: flex;
            justify-content: center;
            align-items: center;
        }
        
        .certificate-wrapper {
            padding: 25px;
            background-color: white;
            box-sizing: border-box;
            width: 900px;
        }
        
        .certificate {
            padding: 20px;
            border: 10px solid rgb(2, 41, 196);
            position: relative;
            background-color: white;
            color: #333;
            width: 100%;
            height: 550px;
            box-sizing: border-box;
        }
        
        .certificate h1 {
            color:rgb(2, 41, 196);
            font-size: 38px;
            text-align: center;
            margin-bottom: 20px;
            text-transform: uppercase;
            white-space: nowrap;
        }
        
        h2 {
            color: #666;
            font-size: 24px;
            text-align: center;
            font-weight: normal;
            margin-bottom: 30px;
        }
        
        .student-name {
            color: #333;
            font-size: 32px;
            text-align: center;
            font-weight: bold;
            margin-bottom: 30px;
        }
        
        .course-title {
            color:rgb(2, 41, 196);
            font-size: 24px;
            text-align: center;
            font-weight: bold;
            margin: 20px 0 40px 0;
        }
        
        .description {
            color: #333;
            font-size: 18px;
            text-align: center;
            margin-bottom: 10px;
        }
        
        .footer {
            margin-top: 40px;
            display: flex;
            justify-content: space-between;
            padding: 0 10%;
        }
        
        .date, .signature {
            text-align: center;
            font-size: 16px;
            color: #666;
        }
        
        .signature-line {
            width: 200px;
            border-top: 1px solid #333;
            margin: 10px auto;
        }
        
        .cert-number {
            color: #666;
            font-size: 14px;
            text-align: center;
            margin-top: 20px;
        }
        
        .print-button {
            display: block;
            margin: 20px auto;
            padding: 10px 20px;
            background-color: rgb(2, 41, 196);
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
        }
        
        
        .back-button {
            display: inline-block;
            margin: 20px;
            padding: 10px 20px;
            background-color: #666;
            color: white;
            text-decoration: none;
            border-radius: 5px;
        }
        

        .signature-text {
            font-family: 'Brush Script MT', cursive;
            font-size: 40px;
            margin-bottom: 5px;
        }
        
        .qr-code-container {
            position: absolute;
            top: 20px;
            right: 20px;
            text-align: center;
            z-index: 100;
            margin-right: 10px;
            margin-left: 20px;
        }
        
        .qr-code-container p {
            margin-top: 5px;
            font-size: 12px;
            color: #666;
        }
        
        #qrcode {
            margin: 0 auto;
        }
        
        .verification-link {
            text-align: center;
            margin-top: 10px;
            font-size: 12px;
        }
        
        .verification-link a {
            color: rgb(2, 41, 196);
            text-decoration: none;
        }
        
        .certificate-header {
            position: relative;
            padding-top: 10px;
            width: 100%;
            display: flex;
            justify-content: center;
        }
        
        .buttons-container {
            display: flex;
            justify-content: center;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="no-print">
        <div class="buttons-container">
            <a href="view_course.php?id=<?php echo $certificate['id']; ?>" class="back-button">← Back to Course</a>
            <button class="print-button" onclick="downloadAsPDF()">Print Certificate</button>
            
        </div>
    </div>
    
    <div class="container">
        <div class="certificate-wrapper">
            <div class="certificate">
                <div class="certificate-header">
                    <h1>Certificate of Completion</h1>
                </div>
            
            
            <h2>This is to certify that</h2>
            
            <div class="student-name"><?php echo htmlspecialchars($certificate['student_name']); ?></div>
            <div class="description">has successfully completed all requirements of the course</div>
            <div class="course-title"><?php echo htmlspecialchars($certificate['course_title']); ?></div>
            
            <div class="footer">
                <div class="date">
                    <div>Date Issued:</div>
                    <div><?php echo $formatted_date; ?></div>
                </div>
                
                <div class="signature">
                    <div class="signature-text">Admin</div>
                    <div class="signature-line"></div>
                    <div>Chief Educator</div>
                </div>
            </div>
            
            <div class="cert-number">Certificate Number: <?php echo htmlspecialchars($certificate['certificate_number']); ?></div>
            
        </div>
        </div>
    </div>
    
    <script>
        
        
        // Function to download as PDF
        function downloadAsPDF() {
            // First hide the buttons
            const buttons = document.querySelector('.no-print');
            buttons.style.display = 'none';
            
            // Use window.print() to trigger the browser's print dialog
            // Since we've set up @page size correctly, this will generate a properly sized PDF
            window.print();
            
            // Show the buttons again
            setTimeout(function() {
                buttons.style.display = 'block';
            }, 1000);
        }
    </script>
</body>
</html>
<?php exit; ?>