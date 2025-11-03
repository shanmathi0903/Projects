<?php
// Basic error handling
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Try to include configuration
$config_loaded = false;
try {
    if (file_exists("config.php")) {
        include("config.php");
        $config_loaded = true;
    }
} catch (Exception $e) {
    $error_message = "Configuration error: " . $e->getMessage();
}

session_start();

$verification_message = "";
$verification_status = "";
$certificate_data = null;

// Check if we have a working database connection without using ping()
$db_connected = false;
if ($config_loaded && isset($conn)) {
    try {
        // Simple query to test connection instead of using ping()
        $test_query = $conn->query("SELECT 1");
        if ($test_query) {
            $db_connected = true;
        } else {
            $error_message = "Database connection failed: " . $conn->error;
        }
    } catch (Exception $e) {
        $error_message = "Database error: " . $e->getMessage();
    }
}

// Check if certificate code is provided
if ($db_connected && isset($_GET['code']) && !empty($_GET['code'])) {
    $certificate_code = $_GET['code'];
    
    try {
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
            WHERE cert.certificate_number = ?
        ");
        
        if ($cert_query) {
            $cert_query->bind_param("s", $certificate_code);
            $cert_query->execute();
            $cert_result = $cert_query->get_result();
            
            if ($cert_result->num_rows > 0) {
                $certificate_data = $cert_result->fetch_assoc();
                $verification_status = "success";
                $verification_message = "Certificate verified successfully!";
                
                // Format date
                $issue_date = new DateTime($certificate_data['issue_date']);
                $formatted_date = $issue_date->format('F d, Y');
            } else {
                $verification_status = "error";
                $verification_message = "Certificate not found or invalid!";
            }
        } else {
            $verification_status = "error";
            $verification_message = "Database query error: " . $conn->error;
        }
    } catch (Exception $e) {
        $verification_status = "error";
        $verification_message = "Error processing verification: " . $e->getMessage();
    }
}

// Determine if we need to show the certificate view
$show_certificate = ($verification_status === "success" && $certificate_data);

// Generate verification URL for the certificate
$verification_url = "";
if ($show_certificate) {
    $verification_url = "http://" . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'] . "?code=" . urlencode($certificate_data['certificate_number']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificate Verification</title>
    <!-- Include QR code library only if showing certificate -->
    <?php if ($show_certificate): ?>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <?php endif; ?>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f5f5f5;
            color: #333;
        }
        
        .container {
            max-width: 800px;
            margin: 40px auto;
            padding: 20px;
            background-color: white;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            border-radius: 5px;
        }
        
        h1 {
            color: rgb(2, 41, 196);
            text-align: center;
            margin-bottom: 30px;
        }
        
        .verification-form {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .verification-form input {
            padding: 10px;
            width: 60%;
            margin-right: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .verification-form button {
            padding: 10px 20px;
            background-color: rgb(2, 41, 196);
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .verification-result {
            margin-top: 20px;
            padding: 20px;
            border-radius: 4px;
            text-align: center;
        }
        
        .success {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        
        .error {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        
        .certificate-details {
            margin-top: 30px;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .certificate-details h2 {
            color: rgb(2, 41, 196);
            margin-bottom: 20px;
        }
        
        .detail-row {
            display: flex;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        
        .detail-label {
            font-weight: bold;
            width: 40%;
        }
        
        .detail-value {
            width: 60%;
        }
        
        .verification-badge {
            text-align: center;
            margin-top: 30px;
        }
        
        .system-error {
            background-color: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        
        /* Certificate styling */
        @page {
            size: 950px 650px;
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
            .certificate-container {
                margin: 0 !important;
                box-shadow: none !important;
            }
            .certificate-wrapper {
                padding: 25px;
                background-color: white;
            }
        }
        
        .certificate-container {
            width: 800px;
            background-color: white;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 40px auto;
        }
        
        .certificate-wrapper {
            padding: 25px;
            background-color: white;
            box-sizing: border-box;
            width: 100%;
        }
        
        .certificate {
            padding: 20px;
            border: 10px solid rgb(2, 41, 196);
            position: relative;
            background-color: white;
            color: #333;
            width: 100%;
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
        
        .certificate h2 {
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
        
        .certificate-header {
            position: relative;
            padding-top: 10px;
            width: 100%;
            display: flex;
            justify-content: center;
        }
        
        .signature-text {
            font-family: 'Brush Script MT', cursive;
            font-size: 40px;
            margin-bottom: 5px;
        }
        
        .buttons-container {
            display: flex;
            justify-content: center;
            margin: 20px 0;
        }
        
        .print-button {
            padding: 10px 20px;
            background-color: rgb(2, 41, 196);
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin: 0 10px;
        }
        
        .qr-code-container {
            text-align: center;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Certificate Verification</h1>
        
        <?php if (isset($error_message)): ?>
            <div class="system-error">
                <strong>System Error:</strong> <?php echo $error_message; ?>
            </div>
        <?php endif; ?>
        
        <div class="verification-form">
            <form method="GET" action="">
                <input type="text" name="code" placeholder="Enter Certificate Number" 
                       value="<?php echo isset($_GET['code']) ? htmlspecialchars($_GET['code']) : ''; ?>" required>
                <button type="submit">Verify</button>
            </form>
        </div>
        
        <?php if (!empty($verification_message)): ?>
            <div class="verification-result <?php echo $verification_status; ?>">
                <?php echo $verification_message; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($verification_status === "success" && $certificate_data): ?>
            <div class="certificate-details">
                <h2>Certificate Details</h2>
                
                <div class="detail-row">
                    <div class="detail-label">Certificate Number:</div>
                    <div class="detail-value"><?php echo htmlspecialchars($certificate_data['certificate_number']); ?></div>
                </div>
                
                <div class="detail-row">
                    <div class="detail-label">Student Name:</div>
                    <div class="detail-value"><?php echo htmlspecialchars($certificate_data['student_name']); ?></div>
                </div>
                
                <div class="detail-row">
                    <div class="detail-label">Course:</div>
                    <div class="detail-value"><?php echo htmlspecialchars($certificate_data['course_title']); ?></div>
                </div>
                
                <div class="detail-row">
                    <div class="detail-label">Issue Date:</div>
                    <div class="detail-value"><?php echo $formatted_date; ?></div>
                </div>
            </div>
            
            <div class="verification-badge">
                <svg width="100" height="100" viewBox="0 0 100 100">
                    <circle cx="50" cy="50" r="45" fill="#d4edda" stroke="#28a745" stroke-width="2" />
                    <path d="M40 50 L47 57 L60 43" fill="none" stroke="#28a745" stroke-width="5" />
                </svg>
                <p>This certificate has been verified as authentic.</p>
            </div>
            
            <!-- Display the full certificate below the verification details -->
            <div class="certificate-container">
                <div class="certificate-wrapper">
                    <div class="certificate">
                        <div class="certificate-header">
                            <h1>Certificate of Completion</h1>
                        </div>
                        
                        <h2>This is to certify that</h2>
                        
                        <div class="student-name"><?php echo htmlspecialchars($certificate_data['student_name']); ?></div>
                        <div class="description">has successfully completed all requirements of the course</div>
                        <div class="course-title"><?php echo htmlspecialchars($certificate_data['course_title']); ?></div>
                        
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
                        
                        <div class="cert-number">Certificate Number: <?php echo htmlspecialchars($certificate_data['certificate_number']); ?></div>
                    </div>
                </div>
            </div>
            
            
        <?php endif; ?>
    </div>
    
    <!-- Initialize QR code if verification was successful -->
    <?php if ($show_certificate): ?>
    <script>
        window.onload = function() {
            const qrcode = new QRCode(document.getElementById("qrcode"), {
                text: "<?php echo $verification_url; ?>",
                width: 100,
                height: 100,
                colorDark: "#000000",
                colorLight: "#ffffff",
                correctLevel: QRCode.CorrectLevel.H
            });
        };
    </script>
    <?php endif; ?>
</body>
</html>