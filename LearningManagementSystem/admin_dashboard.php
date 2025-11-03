<?php 
session_start(); 
include 'config.php';  

// Check if the user is logged in and is an admin
if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit(); 
}

$user_id = $_SESSION['user'];

// Fetch admin details
$stmt = $conn->prepare("SELECT name FROM admins WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Ensure $user['name'] exists before using it
$admin_name = $user ? htmlspecialchars($user['name']) : "Admin User";

// Dashboard summary statistics
$total_teachers = $conn->query("SELECT COUNT(*) as count FROM teachers WHERE status = 'active'")->fetch_assoc()['count'];
$total_students = $conn->query("SELECT COUNT(*) as count FROM students")->fetch_assoc()['count'];
$total_courses = $conn->query("SELECT COUNT(*) as count FROM courses")->fetch_assoc()['count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            display: flex;
            height: 100vh;
            overflow-x: hidden;
            background-color: #f5f7fa;
            color: #333;
        }

        /* Sidebar Styles */
        .sidebar {
            width: 60px; /* Initial width when collapsed */
            background: #1e293b;
            color: white;
            height: 100vh;
            padding: 20px 0;
            position: fixed;
            left: 0;
            top: 0;
            transition: width 0.3s ease-in-out;
            overflow: hidden;
            z-index: 1000;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
        }

        .sidebar.expanded {
            width: 250px; /* Width when expanded */
        }

        .profile-section {
            display: none;
            text-align: center;
            padding: 30px 20px;
            background-color: #172130;
            margin-bottom: 20px;
            position: relative;
            top: 0;
        }

        .sidebar.expanded .profile-section {
            display: block;
        }

        .admin-avatar {
            width: 80px;
            height: 80px;
            background-color: #f5f7fa;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 0 auto 15px;
            color: #1e293b;
            font-size: 32px;
            font-weight: bold;
            border: 3px solid rgba(255, 255, 255, 0.2);
        }

        .profile-section h3 {
            margin-top: 10px;
            font-size: 18px;
            font-weight: 500;
        }

        .sidebar ul {
            list-style: none;
            padding: 0;
            margin-top: 60px; /* Space for toggle button */
            display: block; /* Always display the list */
        }

        .sidebar ul li {
            padding: 12px 20px;
            cursor: pointer;
            transition: all 0.2s ease;
            white-space: nowrap;
        }

        .sidebar ul li:hover, .sidebar ul li.active {
            background: #2c3e50;
        }
        
        .sidebar ul li a {
            color: #ffffff;
            text-decoration: none;
            font-size: 16px;
            display: flex;
            align-items: center;
            width: 100%;
            white-space: nowrap; /* Prevent text wrapping */
        }

        .sidebar ul li i {
            margin-right: 15px;
            width: 20px;
            text-align: center;
            font-size: 18px;
        }

        /* Hide text when sidebar is collapsed */
        .sidebar ul li a span {
            opacity: 0; /* Hide by default */
            transition: opacity 0.3s ease;
        }

        .sidebar.expanded ul li a span {
            opacity: 1; /* Show when expanded */
        }

        /* Toggle Button */
        .toggle-btn {
    background: transparent;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color:rgb(33, 93, 190);
    padding: 10px 20px;
    margin: 0;
    display: block;
    position: absolute;
    top: 10px;
    left: 0;
    z-index: 10;
}

        /* Main Content */
        .dashboard-content {
            flex-grow: 1;
            padding: 40px;
            margin-left: 60px; /* Initial margin */
            transition: margin-left 0.3s ease-in-out;
            width: calc(100% - 60px);
        }

        .sidebar.expanded + .dashboard-content {
            margin-left: 250px;
            width: calc(100% - 250px);
        }
        
        .dashboard-header {
            margin-bottom: 40px;
        }
        
        .dashboard-header h2 {
            color: #333;
            margin-bottom: 10px;
            font-size: 32px;
            font-weight: 600;
        }
        
        .dashboard-header p {
            color: #6b7280;
            font-size: 16px;
        }

        .section-title {
            color: #333;
            font-size: 22px;
            margin-bottom: 20px;
            font-weight: 600;
        }

        /* Stats cards container */
        .stats-container {
            display: flex;
            gap: 30px;
            margin-bottom: 40px;
            justify-content: space-between;
        }

        .stat-card {
            flex: 1;
            background: #ffffff;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            text-align: center;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1);
        }

        .stat-card h3 {
            color: #6b7280;
            font-size: 16px;
            font-weight: 500;
            margin-bottom: 15px;
        }

        .stat-number {
            font-size: 48px;
            font-weight: bold;
            color: #3b82f6;
        }
        
        /* Action Buttons in Card format */
        .actions-container {
            display: flex;
            gap: 30px;
            justify-content: space-between;
        }
        
        .action-card {
            flex: 1;
            background: #ffffff;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            text-align: center;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            cursor: pointer;
        }
        
        .action-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1);
        }
        
        .action-card a {
            color: #333;
            text-decoration: none;
            font-weight: 500;
            font-size: 16px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 15px;
        }

        .action-icon {
            font-size: 28px;
            color: #3b82f6;
        }

        /* Logout button */
        .logout-button {
            display: flex;
            padding: 12px 20px;
            align-items: center;
            color: #ffffff;
            text-decoration: none;
            transition: all 0.2s ease;
            position: absolute;
            bottom: 20px;
            width: 100%;
        }

        .logout-button:hover {
            background: #2c3e50;
        }

        .logout-button i {
            margin-right: 15px;
            width: 20px;
            text-align: center;
            font-size: 18px;
        }

        .logout-button span {
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .sidebar.expanded .logout-button span {
            opacity: 1;
        }

        /* Responsive design */
        @media (max-width: 768px) {
            .stats-container, .actions-container {
                flex-direction: column;
                gap: 20px;
            }
            
            .dashboard-content {
                padding: 20px;
            }
        }
    </style>
</head>
<body>

<div class="sidebar" id="sidebar">
    <button class="toggle-btn" onclick="toggleSidebar()">☰</button>
    <div class="profile-section">
        <div class="admin-avatar">
            <?php echo substr($admin_name, 0, 1); ?>
        </div>
        <h3><?php echo $admin_name; ?></h3>
    </div>
    <ul>
        <li><a href="index.php"><i class="fas fa-th-large"></i> <span>Dashboard</span></a></li>
        <li><a href="add_teacher.php"><i class="fas fa-user-plus"></i> <span>Add Instructor</span></a></li>
        <li><a href="manage_users.php"><i class="fas fa-users"></i> <span>Manage Users</span></a></li>
        <li><a href="view_reports.php"><i class="fas fa-chart-bar"></i> <span>View Reports</span></a></li>
    </ul>
    <a href="logout.php" class="logout-button"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a>
</div>

<div class="dashboard-content" id="dashboardContent">
    <div class="dashboard-header">
        <h2>Welcome, <?php echo $admin_name; ?>!</h2>
        <p>This is your admin dashboard. Here you can manage users, courses, and view system analytics.</p>
    </div>
    
    <!-- Stats Cards -->
    <h3 class="section-title">System Overview</h3>
    <div class="stats-container">
        <div class="stat-card">
            <h3>Total Instructors</h3>
            <div class="stat-number"><?php echo $total_teachers; ?></div>
        </div>
        <div class="stat-card">
            <h3>Total Students</h3>
            <div class="stat-number"><?php echo $total_students; ?></div>
        </div>
        <div class="stat-card">
            <h3>Total Courses</h3>
            <div class="stat-number"><?php echo $total_courses; ?></div>
        </div>
    </div>
    
    <!-- Quick Actions -->
    <h3 class="section-title">Quick Actions</h3>
    <div class="actions-container">
        <div class="action-card">
            <a href="add_teacher.php">
                <div class="action-icon"><i class="fas fa-user-plus"></i></div>
                Add Instructor
            </a>
        </div>
        <div class="action-card">
            <a href="manage_users.php">
                <div class="action-icon"><i class="fas fa-users"></i></div>
                Manage Users
            </a>
        </div>
        <div class="action-card">
            <a href="view_reports.php">
                <div class="action-icon"><i class="fas fa-chart-bar"></i></div>
                View Reports
            </a>
        </div>
    </div>
</div>

<script>
    function toggleSidebar() {
        let sidebar = document.getElementById("sidebar");
        sidebar.classList.toggle("expanded");
    }
</script>

</body>
</html>

<?php
$stmt->close();
$conn->close();
?>