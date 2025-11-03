<?php
session_start();
include 'config.php';

// Check if the user is logged in and is an admin
if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Handle restore action if requested
if (isset($_GET['action']) && $_GET['action'] == 'restore' && isset($_GET['id'])) {
    $archived_id = intval($_GET['id']);
    
    // Check if this archived teacher exists
    $check_query = "SELECT original_id, name FROM archived_teachers WHERE id = ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param("i", $archived_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $archived_data = $check_result->fetch_assoc();
        $original_id = $archived_data['original_id'];
        $teacher_name = $archived_data['name'];
        
        // Check if the original ID still exists but is marked as archived
        $check_original_query = "SELECT id FROM teachers WHERE id = ? AND status = 'archived'";
        $check_original_stmt = $conn->prepare($check_original_query);
        $check_original_stmt->bind_param("i", $original_id);
        $check_original_stmt->execute();
        $original_exists = $check_original_stmt->get_result()->num_rows > 0;
        $check_original_stmt->close();
        
        if ($original_exists) {
            // Restore the teacher by updating status
            $restore_query = "UPDATE teachers SET status = 'active', last_updated = NOW() WHERE id = ?";
            $restore_stmt = $conn->prepare($restore_query);
            $restore_stmt->bind_param("i", $original_id);
            
            if ($restore_stmt->execute()) {
                // Delete from archived_teachers
                $delete_archive = "DELETE FROM archived_teachers WHERE id = ?";
                $delete_stmt = $conn->prepare($delete_archive);
                $delete_stmt->bind_param("i", $archived_id);
                $delete_stmt->execute();
                $delete_stmt->close();
                
                $_SESSION['success'] = "Teacher '$teacher_name' has been successfully restored.";
            } else {
                $_SESSION['error'] = "Error restoring teacher: " . $conn->error;
            }
            $restore_stmt->close();
        } else {
            $_SESSION['error'] = "Cannot restore teacher. Original record no longer exists or is already active.";
        }
    } else {
        $_SESSION['error'] = "Archived teacher not found.";
    }
    $check_stmt->close();
    
    // Redirect to refresh page
    header("Location: view_archived_teachers.php");
    exit();
}

// Get all archived teachers
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

$count_query = "SELECT COUNT(*) as total FROM archived_teachers";
$count_result = $conn->query($count_query);
$total_records = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_records / $per_page);

$query = "SELECT * FROM archived_teachers ORDER BY archive_date DESC LIMIT ?, ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $offset, $per_page);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Archived Teachers - Admin Dashboard</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 0;
            background-color: #f5f7fa;
            color: #333;
        }
        .container {
            max-width: 1000px;
            margin: 50px auto;
            padding: 20px;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        header {
            background-color: #2c3e50;
            color: white;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-radius: 8px 8px 0 0;
            margin: -20px -20px 20px;
        }
        h1, h2 {
            color: #2c3e50;
        }
        header h1 {
            color: white;
            margin: 0;
            font-size: 24px;
        }
        .back-btn {
            display: inline-block;
            padding: 8px 15px;
            background-color: #34495e;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-size: 14px;
        }
        .back-btn:hover {
            background-color: #46637f;
        }
        .success-message {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
            border-radius: 4px;
            padding: 10px 15px;
            margin-bottom: 20px;
        }
        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
            border-radius: 4px;
            padding: 10px 15px;
            margin-bottom: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        table, th, td {
            border: 1px solid #ddd;
        }
        th, td {
            padding: 12px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .badge {
            display: inline-block;
            padding: 3px 8px;
            font-size: 12px;
            border-radius: 3px;
            color: white;
            font-weight: bold;
        }
        .badge-primary {
            background-color: #0d6efd;
        }
        .badge-warning {
            background-color: #ffc107;
            color: #212529;
        }
        .action-btn {
            padding: 5px 10px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 14px;
            display: inline-block;
            text-align: center;
            margin: 2px;
        }
        .btn-success {
            background-color: #28a745;
            color: white;
        }
        .btn-success:hover {
            background-color: #218838;
        }
        .btn-info {
            background-color: #17a2b8;
            color: white;
        }
        .btn-info:hover {
            background-color: #138496;
        }
        .pagination {
            display: flex;
            justify-content: center;
            margin-top: 20px;
        }
        .pagination a, .pagination span {
            display: inline-block;
            padding: 8px 16px;
            text-decoration: none;
            border: 1px solid #ddd;
            margin: 0 4px;
            color: #0d6efd;
        }
        .pagination a:hover {
            background-color: #f1f1f1;
        }
        .pagination .active {
            background-color: #0d6efd;
            color: white;
            border: 1px solid #0d6efd;
        }
        .pagination .disabled {
            color: #6c757d;
            pointer-events: none;
        }
        .archive-reason {
            max-width: 250px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            cursor: pointer;
        }
        .archive-reason:hover {
            white-space: normal;
            overflow: visible;
        }
        .empty-state {
            text-align: center;
            padding: 40px 0;
            color: #6c757d;
        }
        .empty-state svg {
            width: 64px;
            height: 64px;
            margin-bottom: 20px;
            fill: #adb5bd;
        }
        .empty-state h3 {
            margin-bottom: 10px;
            color: #495057;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>Archived Teachers</h1>
            <a href="manage_users.php" class="back-btn">Back to Manage Users</a>
        </header>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="success-message">
                <?php 
                    echo $_SESSION['success']; 
                    unset($_SESSION['success']);
                ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="error-message">
                <?php 
                    echo $_SESSION['error']; 
                    unset($_SESSION['error']);
                ?>
            </div>
        <?php endif; ?>
        
        <?php if ($result->num_rows > 0): ?>
            <table>
            <thead>
                    <tr>
                        <th>Name</th>
                        <th>Original ID</th>
                        <th>Reason for Archiving</th>
                        <th>Archived Date</th>
                        
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['name']); ?></td>
                            <td><?php echo $row['original_id']; ?></td>
                            <td class="archive-reason"><?php echo htmlspecialchars($row['archive_reason']); ?></td>
                            <td><?php echo date('M d, Y', strtotime($row['archive_date'])); ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            
            <!-- Pagination -->
            <div class="pagination">
                <?php if ($total_pages > 1): ?>
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo ($page - 1); ?>">&laquo; Previous</a>
                    <?php else: ?>
                        <span class="disabled">&laquo; Previous</span>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <?php if ($i == $page): ?>
                            <span class="active"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo ($page + 1); ?>">Next &raquo;</a>
                    <?php else: ?>
                        <span class="disabled">Next &raquo;</span>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                    <path d="M20 6h-8l-2-2H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2zm0 12H4V8h16v10z"/>
                    <path d="M12 17l4-4h-3V9h-2v4H8l4 4z" fill-opacity=".3"/>
                </svg>
                <h3>No Archived Teachers</h3>
                <p>There are currently no archived teachers in the system.</p>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Enable tooltip-like behavior for long archive reasons
        document.addEventListener('DOMContentLoaded', function() {
            const archiveReasons = document.querySelectorAll('.archive-reason');
            archiveReasons.forEach(function(element) {
                // Store the original text as a title attribute for hover effect
                if (element.textContent.length > 40) {
                    element.setAttribute('title', element.textContent);
                }
            });
        });
    </script>
</body>
</html>

<?php
// Close database connection
$stmt->close();
$conn->close();
?>