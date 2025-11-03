<?php
include 'config.php';

// Add profile_pic column to teachers table
$alter_query = "ALTER TABLE teachers ADD COLUMN profile_pic VARCHAR(255) DEFAULT NULL";

try {
    if ($conn->query($alter_query)) {
        echo "Successfully added profile_pic column to teachers table.";
    } else {
        echo "Error adding column: " . $conn->error;
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?> 