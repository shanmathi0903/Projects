<?php
session_start();
$_SESSION['test'] = "Session is working!";
echo "Session started. Check 'C:\\php_sessions' for session files.";
?>
