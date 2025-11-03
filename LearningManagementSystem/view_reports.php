<?php 
session_start(); 
include 'config.php';  

// Check if user is logged in and is admin
if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Fetch admin name
$stmt = $conn->prepare("SELECT name FROM admins WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user']);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$admin_name = $user ? htmlspecialchars($user['name']) : "Admin User";

// Get date range for filtering
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-6 months'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Add a day to end_date to include the full day in the range (until 23:59:59)
$end_date_inclusive = date('Y-m-d', strtotime($end_date . ' +1 day'));

// Get system overview statistics with date filtering and using prepared statements
$stats = [
    'teachers' => $conn->query("SELECT COUNT(*) FROM teachers WHERE status = 'active'")->fetch_row()[0],
    'students' => $conn->query("SELECT COUNT(*) FROM students")->fetch_row()[0],
    'courses' => $conn->query("SELECT COUNT(*) FROM courses")->fetch_row()[0],
    'videos' => $conn->query("SELECT COUNT(*) FROM course_videos")->fetch_row()[0],
];

// Use prepared statements for certificates count
$cert_stmt = $conn->prepare("SELECT COUNT(*) FROM certificates WHERE issue_date BETWEEN ? AND ?");
$cert_stmt->bind_param("ss", $start_date, $end_date_inclusive);
$cert_stmt->execute();
$cert_result = $cert_stmt->get_result();
$stats['certificates'] = $cert_result->fetch_row()[0];
$cert_stmt->close();

// Use prepared statements for enrollments count
$enroll_stmt = $conn->prepare("SELECT COUNT(*) FROM enrollments WHERE enroll_date BETWEEN ? AND ?");
$enroll_stmt->bind_param("ss", $start_date, $end_date_inclusive);
$enroll_stmt->execute();
$enroll_result = $enroll_stmt->get_result();
$stats['enrollments'] = $enroll_result->fetch_row()[0];
$enroll_stmt->close();

// Get recent certificates with date filtering using prepared statements
$cert_list_stmt = $conn->prepare("SELECT 
                                c.certificate_number,
                                c.issue_date,
                                s.name AS student_name,
                                co.title AS course_title
                                FROM certificates c
                                JOIN students s ON c.student_id = s.id
                                JOIN courses co ON c.course_id = co.id
                                WHERE c.issue_date BETWEEN ? AND ?
                                ORDER BY c.issue_date DESC
                                LIMIT 10");
$cert_list_stmt->bind_param("ss", $start_date, $end_date_inclusive);
$cert_list_stmt->execute();
$recent_certificates = $cert_list_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$cert_list_stmt->close();

// Close connections
$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', sans-serif; }
        body { background-color: #f0f5ff; color: #333; }
        
        /* Header styles - similar to Manage Users */
        .page-header {
            background-color: #1e293b;
            color: white;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .page-header h1 {
            font-size: 28px;
            font-weight: 600;
        }
        .back-btn {
            padding: 8px 15px;
            background-color: #555;
            color: white;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
            display: inline-block;
        }
        .back-btn:hover {
            background-color: #333;
        }
        
        /* Main container */
        .container {
            width: 100%;
            max-width: 1300px;
            margin: 0 auto;
            padding: 20px;
        }
        
        /* Date filter styles */
        .filter-container {
            background: white;
            padding: 15px 20px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            margin-bottom: 25px;
        }
        .filter-row {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 15px;
        }
        .filter-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .filter-group label {
            font-weight: 500;
            color: #4b5563;
        }
        .filter-group input[type="date"] {
            padding: 8px 12px;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            font-size: 14px;
        }
        .filter-button {
            background: #3b82f6;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
            transition: background-color 0.2s;
        }
        .filter-button:hover {
            background: #2563eb;
        }
        .clear-filter-button {
            background: #f3f4f6;
            color: #4b5563;
            border: 1px solid #d1d5db;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
            transition: background-color 0.2s;
        }
        .clear-filter-button:hover {
            background: #e5e7eb;
        }
        
        /* Action buttons */
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-left: auto;
        }
        .action-btn {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
        }
        .action-btn.secondary {
            background: #f3f4f6;
            color: #4b5563;
            border: 1px solid #d1d5db;
        }
        .action-btn.secondary:hover {
            background: #e5e7eb;
        }
        
        /* Stats cards */
        .section-title {
            font-size: 18px;
            color: #1e293b;
            margin-bottom: 15px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .section-title i {
            color: #3b82f6;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            display: flex;
            flex-direction: column;
            align-items: center;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            margin-bottom: 15px;
            font-size: 20px;
        }
        .stat-icon.blue { background-color: #dbeafe; color: #3b82f6; }
        .stat-icon.green { background-color: #dcfce7; color: #22c55e; }
        .stat-icon.purple { background-color: #f3e8ff; color: #a855f7; }
        .stat-icon.red { background-color: #fee2e2; color: #ef4444; }
        .stat-icon.yellow { background-color: #fef3c7; color: #f59e0b; }
        .stat-icon.pink { background-color: #fce7f3; color: #ec4899; }
        .stat-number {
            font-size: 28px;
            font-weight: bold;
            color: #1e293b;
            margin: 5px 0;
        }
        .stat-label {
            font-size: 14px;
            color: #64748b;
            text-align: center;
        }
        
        /* Certificate table */
        .data-container {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            margin-bottom: 30px;
            overflow: hidden;
        }
        .data-header {
            padding: 15px 20px;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .data-header h3 {
            font-size: 16px;
            font-weight: 600;
            color: #1e293b;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        .data-table th {
            background-color: #f8fafc;
            padding: 12px 15px;
            text-align: left;
            font-weight: 600;
            color: #475569;
            font-size: 14px;
            border-bottom: 1px solid #e5e7eb;
        }
        .data-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #e5e7eb;
            font-size: 14px;
        }
        .data-table tr:last-child td {
            border-bottom: none;
        }
        .data-table tr:hover {
            background-color: #f8fafc;
        }
        .certificate-id {
            color: #3b82f6;
            font-weight: 500;
        }
        
        /* Date filter info */
        .filter-info {
            background-color: #f8fafc;
            border: 1px solid #e5e7eb;
            border-radius: 4px;
            padding: 10px 15px;
            margin-bottom: 20px;
            font-size: 14px;
            color: #4b5563;
        }
        
        /* Empty state */
        .empty-state {
            padding: 40px 20px;
            text-align: center;
            color: #64748b;
        }
        .empty-state i {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.5;
        }
        .empty-state p {
            font-size: 16px;
        }
        
        /* Print styles */
        @media print {
            /* Hide elements not needed in print */
            .page-header, .filter-container, .back-btn, .action-buttons, .filter-info {
                display: none !important;
            }
            
            /* Ensure content fits well on printed page */
            body {
                padding: 15px;
                background-color: white !important;
            }
            
            .container {
                width: 100% !important;
                max-width: none !important;
                padding: 0 !important;
            }
            
            /* Make sure tables don't break across pages */
            .data-table {
                page-break-inside: auto;
            }
            
            .data-table tr {
                page-break-inside: avoid;
                page-break-after: auto;
            }
            
            /* Print with clean background */
            .stat-card, .data-container {
                box-shadow: none !important;
                border: 1px solid #ddd;
            }
            
            /* Preserve icon colors in print */
            .stat-icon.blue { 
                background-color: #dbeafe !important; 
                color: #3b82f6 !important; 
            }
            .stat-icon.green { 
                background-color: #dcfce7 !important; 
                color: #22c55e !important; 
            }
            .stat-icon.purple { 
                background-color: #f3e8ff !important; 
                color: #a855f7 !important; 
            }
            .stat-icon.red { 
                background-color: #fee2e2 !important; 
                color: #ef4444 !important; 
            }
            .stat-icon.yellow { 
                background-color: #fef3c7 !important; 
                color: #f59e0b !important; 
            }
            .stat-icon.pink { 
                background-color: #fce7f3 !important; 
                color: #ec4899 !important; 
            }
            
            /* Certificate ID color preservation */
            .certificate-id {
                color: #3b82f6 !important;
                font-weight: 500 !important;
            }
            
            /* Add a header to each printed page */
            @page {
                margin: 2cm;
            }
            
            /* Hide everything else by default */
            body > *:not(.print-content) {
                display: none !important;
            }
            
            /* Show only what we want to print */
            .print-content {
                display: block !important;
            }
            
            /* Print header styling */
            .print-header {
                display: block !important;
                text-align: center;
                margin-bottom: 30px;
                border-bottom: 1px solid #ddd;
                padding-bottom: 15px;
            }
            
            .print-header h1 {
                font-size: 24px;
                margin-bottom: 10px;
            }
            
            .print-header p {
                font-size: 14px;
                color: #555;
                margin: 5px 0;
            }
            
            /* Print section styling */
            .print-section {
                margin-bottom: 30px;
            }
            
            .print-section h2 {
                font-size: 18px;
                margin-bottom: 15px;
                color: #333;
            }
            
            /* Apply correct icon colors to print section specifically */
            .print-content .stat-icon.blue { 
                background-color: #dbeafe !important; 
                color: #3b82f6 !important; 
            }
            .print-content .stat-icon.green { 
                background-color: #dcfce7 !important; 
                color: #22c55e !important; 
            }
            .print-content .stat-icon.purple { 
                background-color: #f3e8ff !important; 
                color: #a855f7 !important; 
            }
            .print-content .stat-icon.red { 
                background-color: #fee2e2 !important; 
                color: #ef4444 !important; 
            }
            .print-content .stat-icon.yellow { 
                background-color: #fef3c7 !important; 
                color: #f59e0b !important; 
            }
            .print-content .stat-icon.pink { 
                background-color: #fce7f3 !important; 
                color: #ec4899 !important; 
            }
            
            /* Force color printing */
            * {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
        }
        
        /* Hide the print-specific elements when not printing */
        .print-header, .print-content {
            display: none;
        }
        
        /* Responsive design */
        @media (max-width: 768px) {
            .filter-row {
                flex-direction: column;
                align-items: flex-start;
            }
            .filter-group {
                width: 100%;
            }
            .action-buttons {
                margin-left: 0;
                margin-top: 15px;
                width: 100%;
                justify-content: flex-end;
            }
            .stats-grid {
                grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            }
        }
    </style>
</head>
<body>
    <!-- Header with Back button - matching Manage Users style -->
    <div class="page-header">
        <h1>Analytics Dashboard</h1>
        <a href="admin_dashboard.php" class="back-btn">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
    </div>
    
    <div class="container">
        <!-- Date Filter Section -->
        <div class="filter-container">
            <form method="GET" action="" id="dateFilterForm">
                <div class="filter-row">
                    <div class="filter-group">
                        <label for="start_date"><i class="fas fa-calendar-alt"></i> Date Range:</label>
                        <input type="date" id="start_date" name="start_date" value="<?php echo $start_date; ?>">
                    </div>
                    <div class="filter-group">
                        <label for="end_date">to</label>
                        <input type="date" id="end_date" name="end_date" value="<?php echo $end_date; ?>">
                    </div>
                    <button type="submit" class="filter-button">Apply Filter</button>
                    <button type="button" class="clear-filter-button" onclick="clearFilters()">Clear Filters</button>
                    
                    <div class="action-buttons">
                        <button type="button" class="action-btn secondary" onclick="printReport()">
                            <i class="fas fa-print"></i> Print Report
                        </button>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- Active Filter Indicator -->
        <div class="filter-info">
            <i class="fas fa-filter"></i> Showing data from <strong><?php echo date('M d, Y', strtotime($start_date)); ?></strong> to <strong><?php echo date('M d, Y', strtotime($end_date)); ?></strong>
        </div>
        
        <!-- System Overview Section -->
        <h2 class="section-title"><i class="fas fa-tachometer-alt"></i> System Overview</h2>
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon blue"><i class="fas fa-chalkboard-teacher"></i></div>
                <div class="stat-number"><?php echo number_format($stats['teachers']); ?></div>
                <div class="stat-label">Active Instructors</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon green"><i class="fas fa-user-graduate"></i></div>
                <div class="stat-number"><?php echo number_format($stats['students']); ?></div>
                <div class="stat-label">Total Students</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon purple"><i class="fas fa-book"></i></div>
                <div class="stat-number"><?php echo number_format($stats['courses']); ?></div>
                <div class="stat-label">Available Courses</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon red"><i class="fas fa-play-circle"></i></div>
                <div class="stat-number"><?php echo number_format($stats['videos']); ?></div>
                <div class="stat-label">Learning Videos</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon yellow"><i class="fas fa-user-plus"></i></div>
                <div class="stat-number"><?php echo number_format($stats['enrollments']); ?></div>
                <div class="stat-label">Total Enrollments </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon pink"><i class="fas fa-certificate"></i></div>
                <div class="stat-number"><?php echo number_format($stats['certificates']); ?></div>
                <div class="stat-label">Certificates (Period)</div>
            </div>
        </div>
        
        <!-- Recently Issued Certificates Section -->
        <h2 class="section-title"><i class="fas fa-certificate"></i> Certificates Within Selected Period</h2>
        <div class="data-container">
            <div class="data-header">
                <h3>Course Completions</h3>
            </div>
            
            <?php if (!empty($recent_certificates)): ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Certificate ID</th>
                            <th>Student Name</th>
                            <th>Course</th>
                            <th>Issue Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_certificates as $cert): ?>
                        <tr>
                            <td class="certificate-id"><?php echo htmlspecialchars($cert['certificate_number']); ?></td>
                            <td><?php echo htmlspecialchars($cert['student_name']); ?></td>
                            <td><?php echo htmlspecialchars($cert['course_title']); ?></td>
                            <td><?php echo date('M d, Y', strtotime($cert['issue_date'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-award"></i>
                    <p>No certificates have been issued within the selected date range.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Print-specific content -->
    <div class="print-content">
        <div class="print-header">
            <h1>Analytics Report</h1>
            <p>Period: <?php echo date('M d, Y', strtotime($start_date)); ?> to <?php echo date('M d, Y', strtotime($end_date)); ?></p>
            <p>Generated: <?php echo date('M d, Y'); ?></p>
        </div>
        
        <!-- Print System Overview Section -->
        <div class="print-section">
            <h2>System Overview</h2>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon blue"><i class="fas fa-chalkboard-teacher"></i></div>
                    <div class="stat-number"><?php echo number_format($stats['teachers']); ?></div>
                    <div class="stat-label">Active Instructors</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon green"><i class="fas fa-user-graduate"></i></div>
                    <div class="stat-number"><?php echo number_format($stats['students']); ?></div>
                    <div class="stat-label">Total Students</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon purple"><i class="fas fa-book"></i></div>
                    <div class="stat-number"><?php echo number_format($stats['courses']); ?></div>
                    <div class="stat-label">Available Courses</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon red"><i class="fas fa-play-circle"></i></div>
                    <div class="stat-number"><?php echo number_format($stats['videos']); ?></div>
                    <div class="stat-label">Learning Videos</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon yellow"><i class="fas fa-user-plus"></i></div>
                    <div class="stat-number"><?php echo number_format($stats['enrollments']); ?></div>
                    <div class="stat-label">Total Enrollments </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon pink"><i class="fas fa-certificate"></i></div>
                    <div class="stat-number"><?php echo number_format($stats['certificates']); ?></div>
                    <div class="stat-label">Certificates (Period)</div>
                </div>
            </div>
        </div>
        
        <!-- Print Certificates Section -->
        <div class="print-section">
            <h2>Certificates Within Selected Period</h2>
            <?php if (!empty($recent_certificates)): ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Certificate ID</th>
                            <th>Student Name</th>
                            <th>Course</th>
                            <th>Issue Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_certificates as $cert): ?>
                        <tr>
                            <td class="certificate-id"><?php echo htmlspecialchars($cert['certificate_number']); ?></td>
                            <td><?php echo htmlspecialchars($cert['student_name']); ?></td>
                            <td><?php echo htmlspecialchars($cert['course_title']); ?></td>
                            <td><?php echo date('M d, Y', strtotime($cert['issue_date'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <p>No certificates have been issued within the selected date range.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Function to clear date filters
        function clearFilters() {
            document.getElementById('start_date').value = '<?php echo date('Y-m-d', strtotime('-6 months')); ?>';
            document.getElementById('end_date').value = '<?php echo date('Y-m-d'); ?>';
            document.getElementById('dateFilterForm').submit();
        }
        
        // Form validation for date range
        document.getElementById('dateFilterForm').addEventListener('submit', function(e) {
            const startDate = new Date(document.getElementById('start_date').value);
            const endDate = new Date(document.getElementById('end_date').value);
            
            if (endDate < startDate) {
                e.preventDefault();
                alert('End date cannot be earlier than start date.');
            }
        });
        
        // Custom print report function
        function printReport() {
            window.print();
        }
    </script>
</body>
</html>