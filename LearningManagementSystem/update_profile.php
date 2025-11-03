<?php
session_start();
include 'config.php';

if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'student') {
    exit("Unauthorized access");
}

$user_id = $_SESSION['user'];

// Fetch user details
$stmt = $conn->prepare("SELECT name, gender, location, bio, phone, college_name, profile_pic FROM students WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Handle profile update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_profile'])) {
    $name = $_POST['name'];
    $gender = $_POST['gender'];
    $location = $_POST['location'];
    $bio = $_POST['bio'];
    $phone = $_POST['phone'];
    $college_name = $_POST['college_name'];

   
    $sql = "UPDATE students SET name=?, gender=?, location=?, bio=?, phone=?, college_name=? WHERE id=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssssi", $name, $gender, $location, $bio, $phone, $college_name, $user_id);

    if ($stmt->execute()) {
        echo "<script>alert('Profile updated successfully!');</script>";
    } else {
        echo "<script>alert('Error updating profile: " . $stmt->error . "');</script>";
    }

    
    if (!empty($_FILES['profile_pic']['name'])) {
        $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
        $upload_dir = "uploads/";

       
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
            chmod($upload_dir, 0777);
        }

        $file_ext = strtolower(pathinfo($_FILES["profile_pic"]["name"], PATHINFO_EXTENSION));

        if (in_array($file_ext, $allowed_types)) {
            $new_filename = "user_" . $user_id . "_" . time() . "." . $file_ext;
            $target_file = $upload_dir . $new_filename;

            if (move_uploaded_file($_FILES["profile_pic"]["tmp_name"], $target_file)) {
                // ✅ Update the database with the new profile picture filename
                $update_pic_sql = "UPDATE students SET profile_pic=? WHERE id=?";
                $update_pic_stmt = $conn->prepare($update_pic_sql);
                $update_pic_stmt->bind_param("si", $new_filename, $user_id);
                $update_pic_stmt->execute();

                echo "<script>alert('Profile picture updated successfully!');</script>";
            } else {
                echo "<script>alert('File upload failed. Please check folder permissions or try again.');</script>";
            }
        } else {
            echo "<script>alert('Invalid file type! Only JPG, PNG, and GIF allowed.');</script>";
        }
    }

    // Redirect after update
    echo "<script>window.location.href = 'Student_dashboard.php';</script>";
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Profile</title>
    <link rel="stylesheet" href="assets/uppro.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background-color: #f5f7fa;
        margin: 0;
        padding: 20px;
        color: #333;
        line-height: 1.6;
    }

    .update-profile-container {
        background: white;
        padding: 30px;
        border-radius: 15px;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08);
        width: 70%;
        max-width: 800px;
        margin: 40px auto;
    }

    .update-profile-container h2 {
        text-align: center;
        color: #2C3E50;
        font-size: 28px;
        margin-bottom: 30px;
        position: relative;
        padding-bottom: 10px;
    }

    .update-profile-container h2:after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 50%;
        transform: translateX(-50%);
        width: 80px;
        height: 3px;
        background: #3498db;
    }

    .update-profile-container label {
        display: block;
        font-weight: 600;
        margin: 15px 0 5px 0;
        color: #2C3E50;
        font-size: 14px;
    }

    .update-profile-container input,
    .update-profile-container select,
    .update-profile-container textarea {
        width: 100%;
        padding: 12px 15px;
        margin-top: 5px;
        border-radius: 8px;
        border: 1px solid #e1e1e1;
        background-color: #f9f9f9;
        font-size: 14px;
        transition: all 0.3s ease;
        box-sizing: border-box;
    }

    .update-profile-container input:focus,
    .update-profile-container select:focus,
    .update-profile-container textarea:focus {
        border-color: #3498db;
        box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.2);
        outline: none;
        background-color: #fff;
    }

    .update-profile-container textarea {
        resize: vertical;
        min-height: 100px;
    }

    .update-profile-container button {
        width: 100%;
        padding: 14px;
        margin-top: 25px;
        background: linear-gradient(135deg, #3498db, #2C3E50);
        color: white;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        font-size: 16px;
        font-weight: 600;
        transition: all 0.3s ease;
        letter-spacing: 0.5px;
    }

    .update-profile-container button:hover {
        background: linear-gradient(135deg, #2980b9, #1a252f);
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
    }

    .profile-pic-preview {
        display: flex;
        justify-content: center;
        margin: 20px 0;
    }

    .profile-pic-preview img {
        width: 150px;
        height: 150px;
        border-radius: 50%;
        object-fit: cover;
        border: 4px solid #fff;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        transition: transform 0.3s ease;
    }

    .profile-pic-preview img:hover {
        transform: scale(1.05);
    }

    /* File input styling */
    input[type="file"] {
        border: 1px dashed #ccc;
        padding: 10px;
        border-radius: 8px;
        background: #f9f9f9;
        cursor: pointer;
    }

    input[type="file"]::file-selector-button {
        background: #3498db;
        color: white;
        border: none;
        border-radius: 4px;
        padding: 8px 16px;
        margin-right: 15px;
        cursor: pointer;
        transition: background 0.3s ease;
    }

    input[type="file"]::file-selector-button:hover {
        background: #2980b9;
    }

    #updateMessage {
        text-align: center;
        margin-top: 15px;
        font-weight: 500;
        color: #2ecc71;
    }

    /* Responsive adjustments */
    @media (max-width: 768px) {
        .update-profile-container {
            width: 90%;
            padding: 20px;
        }

        .profile-pic-preview img {
            width: 120px;
            height: 120px;
        }
    }
</style>
</head>
<body>

<div class="update-profile-container">
    <h2>Update Profile</h2>
    <form id="updateProfileForm" method="POST" enctype="multipart/form-data">
        <label for="name">Full Name:</label>
        <input type="text" name="name" value="<?php echo htmlspecialchars($user['name'] ?? ''); ?>" required>

        <label for="gender">Gender:</label>
        <select name="gender">
            <option value="Male" <?php echo ($user['gender'] == "Male") ? "selected" : ""; ?>>Male</option>
            <option value="Female" <?php echo ($user['gender'] == "Female") ? "selected" : ""; ?>>Female</option>
            <option value="Other" <?php echo ($user['gender'] == "Other") ? "selected" : ""; ?>>Other</option>
        </select>

        <label for="location">Location:</label>
        <input type="text" name="location" value="<?php echo htmlspecialchars($user['location'] ?? ''); ?>">

        <label for="phone">Phone Number:</label>
        <input type="text" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" required>

        <label for="college_name">College Name:</label>
        <input type="text" name="college_name" value="<?php echo htmlspecialchars($user['college_name'] ?? ''); ?>" required>

        <label for="bio">Bio:</label>
        <textarea name="bio"><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>

        <!-- Profile Picture Preview -->
        <label>Profile Picture:</label>
        <div class="profile-pic-preview">
            <img src="uploads/<?php echo htmlspecialchars($user['profile_pic'] ?? 'default-user.png'); ?>" alt="Profile Pic" id="profilePreview">
        </div>
        <input type="file" name="profile_pic" id="profilePicInput">

        <button type="submit" name="update_profile">Update</button>
        <p id="updateMessage"></p>
    </form>
</div>

<script>
$(document).ready(function () {
    // Show profile picture preview
    $("#profilePicInput").on("change", function () {
        var reader = new FileReader();
        reader.onload = function (e) {
            $("#profilePreview").attr("src", e.target.result);
        };
        reader.readAsDataURL(this.files[0]);
    });
});
</script>

</body>
</html>