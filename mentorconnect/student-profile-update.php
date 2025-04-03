<?php
// Initialize the session
session_start();

// Check if the user is logged in and is a student
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["user_type"] !== "student"){
    header("location: login.php");
    exit;
}

// Include database connection
require_once "config/db_connect.php";

// Define variables
$first_name = $_SESSION["first_name"];
$last_name = $_SESSION["last_name"];
$email = $_SESSION["email"];
$student_id = $_SESSION["user_id"];
$career_goals = $skills = $education = $interests = $phone = $profile_photo = "";
$success_message = $error_message = "";

// Create uploads directory if it doesn't exist
$upload_dir = "uploads/profile_photos/";
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Get student profile details
$sql = "SELECT * FROM student_profiles WHERE student_id = ?";
if($stmt = $conn->prepare($sql)){
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if($result->num_rows == 1){
        $row = $result->fetch_assoc();
        $career_goals = $row["career_goals"];
        $skills = $row["skills"];
        $education = isset($row["education"]) ? $row["education"] : "";
        $interests = isset($row["interests"]) ? $row["interests"] : "";
        $phone = isset($row["phone"]) ? $row["phone"] : "";
        $profile_photo = isset($row["profile_photo"]) ? $row["profile_photo"] : "";
    }
    $stmt->close();
}

// Get phone from users table if not in profile
if(empty($phone)) {
    $sql = "SELECT phone FROM users WHERE user_id = ?";
    if($stmt = $conn->prepare($sql)){
        $stmt->bind_param("i", $student_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if($result->num_rows == 1){
            $row = $result->fetch_assoc();
            $phone = $row["phone"];
        }
        $stmt->close();
    }
}

// Process form submission
if($_SERVER["REQUEST_METHOD"] == "POST"){
    // Get form data
    $new_first_name = trim($_POST["first_name"]);
    $new_last_name = trim($_POST["last_name"]);
    $new_email = trim($_POST["email"]);
    $new_phone = trim($_POST["phone"]);
    $new_career_goals = trim($_POST["career_goals"]);
    $new_skills = trim($_POST["skills"]);
    $new_education = trim($_POST["education"]);
    $new_interests = trim($_POST["interests"]);
    
    // Validate inputs (basic validation)
    $input_error = false;
    
    if(empty($new_first_name) || empty($new_last_name)) {
        $error_message = "Name fields cannot be empty.";
        $input_error = true;
    }
    
    if(empty($new_email) || !filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Please enter a valid email address.";
        $input_error = true;
    }
    
    // Process file upload if a file was selected
    $new_profile_photo = $profile_photo; // Default to current photo
    
    if(isset($_FILES["profile_photo"]) && $_FILES["profile_photo"]["error"] == 0) {
        $allowed_types = ["image/jpeg", "image/jpg", "image/png", "image/gif"];
        $max_size = 5 * 1024 * 1024; // 5MB
        
        $file_temp = $_FILES["profile_photo"]["tmp_name"];
        $file_type = $_FILES["profile_photo"]["type"];
        $file_size = $_FILES["profile_photo"]["size"];
        
        // Validate file type and size
        if(!in_array($file_type, $allowed_types)) {
            $error_message = "Only JPG, PNG, and GIF files are allowed.";
            $input_error = true;
        } elseif($file_size > $max_size) {
            $error_message = "File size must be less than 5MB.";
            $input_error = true;
        } else {
            // Generate unique filename
            $file_extension = pathinfo($_FILES["profile_photo"]["name"], PATHINFO_EXTENSION);
            $new_filename = "student_" . $student_id . "_" . time() . "." . $file_extension;
            $upload_path = $upload_dir . $new_filename;
            
            // Move uploaded file
            if(move_uploaded_file($file_temp, $upload_path)) {
                $new_profile_photo = $upload_path;
                
                // Delete old photo if it exists and is not the default
                if(!empty($profile_photo) && file_exists($profile_photo) && $profile_photo != "uploads/profile_photos/default.jpg") {
                    unlink($profile_photo);
                }
            } else {
                $error_message = "Failed to upload file. Please try again.";
                $input_error = true;
            }
        }
    }
    
    // If no errors, update the database
    if(!$input_error) {
        // Begin transaction
        $conn->begin_transaction();
        
        try {
            // Update users table
            $sql = "UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ? WHERE user_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssssi", $new_first_name, $new_last_name, $new_email, $new_phone, $student_id);
            $stmt->execute();
            $stmt->close();
            
            // Check if student profile exists
            $sql = "SELECT student_id FROM student_profiles WHERE student_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $student_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if($result->num_rows > 0) {
                // Update existing profile
                $sql = "UPDATE student_profiles SET career_goals = ?, skills = ?, education = ?, interests = ?, profile_photo = ? WHERE student_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sssssi", $new_career_goals, $new_skills, $new_education, $new_interests, $new_profile_photo, $student_id);
            } else {
                // Insert new profile
                $sql = "INSERT INTO student_profiles (student_id, career_goals, skills, education, interests, profile_photo) VALUES (?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("isssss", $student_id, $new_career_goals, $new_skills, $new_education, $new_interests, $new_profile_photo);
            }
            
            $stmt->execute();
            $stmt->close();
            
            // Commit transaction
            $conn->commit();
            
            // Update session variables
            $_SESSION["first_name"] = $new_first_name;
            $_SESSION["last_name"] = $new_last_name;
            $_SESSION["email"] = $new_email;
            
            // Update local variables
            $first_name = $new_first_name;
            $last_name = $new_last_name;
            $email = $new_email;
            $phone = $new_phone;
            $career_goals = $new_career_goals;
            $skills = $new_skills;
            $education = $new_education;
            $interests = $new_interests;
            $profile_photo = $new_profile_photo;
            
            $success_message = "Profile updated successfully!";
            
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            $error_message = "Error updating profile: " . $e->getMessage();
        }
    }
}

// Close connection
closeConnection($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Profile - MentorConnect</title>
    <style>
        /* Basic Reset */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: #f5f5f5;
            color: #333;
            line-height: 1.6;
        }

        .container {
            max-width: 900px;
            margin: 40px auto;
            padding: 20px;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #ddd;
        }

        .header h1 {
            font-size: 24px;
            color: #333;
        }

        .back-link {
            color: #3498db;
            text-decoration: none;
            display: flex;
            align-items: center;
        }

        .back-link:hover {
            text-decoration: underline;
        }

        .back-icon {
            margin-right: 5px;
        }

        .profile-card {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 30px;
        }

        .profile-header {
            display: flex;
            align-items: center;
            margin-bottom: 30px;
        }

        .profile-photo-container {
            position: relative;
            width: 120px;
            height: 120px;
            margin-right: 30px;
        }

        .profile-photo {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #3498db;
        }

        .photo-upload-label {
            position: absolute;
            bottom: 0;
            right: 0;
            background-color: #3498db;
            color: white;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 18px;
        }

        .photo-upload-input {
            display: none;
        }

        .profile-info h2 {
            font-size: 24px;
            margin-bottom: 5px;
        }

        .profile-info p {
            color: #777;
            font-size: 16px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #555;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
            transition: border-color 0.3s;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #3498db;
        }

        .form-group textarea {
            height: 120px;
            resize: vertical;
        }

        .form-row {
            display: flex;
            gap: 20px;
        }

        .form-row .form-group {
            flex: 1;
        }

        .submit-btn {
            background-color: #3498db;
            color: white;
            border: none;
            border-radius: 5px;
            padding: 12px 20px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .submit-btn:hover {
            background-color: #2980b9;
        }

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        @media (max-width: 768px) {
            .form-row {
                flex-direction: column;
                gap: 0;
            }

            .profile-header {
                flex-direction: column;
                text-align: center;
            }

            .profile-photo-container {
                margin-right: 0;
                margin-bottom: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Update Your Profile</h1>
            <a href="student.php" class="back-link">
                <span class="back-icon">←</span> Back to Dashboard
            </a>
        </div>

        <?php if(!empty($success_message)): ?>
            <div class="alert alert-success"><?php echo $success_message; ?></div>
        <?php endif; ?>

        <?php if(!empty($error_message)): ?>
            <div class="alert alert-danger"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <div class="profile-card">
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" enctype="multipart/form-data">
                <div class="profile-header">
                    <div class="profile-photo-container">
                        <img src="<?php echo !empty($profile_photo) ? htmlspecialchars($profile_photo) : 'uploads/profile_photos/default.jpg'; ?>" alt="Profile Photo" class="profile-photo" id="profilePhotoPreview">
                        <label for="profile_photo" class="photo-upload-label">📷</label>
                        <input type="file" name="profile_photo" id="profile_photo" class="photo-upload-input" accept="image/*">
                    </div>
                    <div class="profile-info">
                        <h2><?php echo htmlspecialchars($first_name . ' ' . $last_name); ?></h2>
                        <p><?php echo htmlspecialchars($email); ?></p>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="first_name">First Name</label>
                        <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($first_name); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="last_name">Last Name</label>
                        <input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($last_name); ?>" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="phone">Phone Number</label>
                        <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($phone); ?>" placeholder="+254 7XX XXX XXX">
                    </div>
                </div>

                <div class="form-group">
                    <label for="career_goals">Career Goals</label>
                    <textarea id="career_goals" name="career_goals" placeholder="Describe your career aspirations and what you hope to achieve"><?php echo htmlspecialchars($career_goals); ?></textarea>
                </div>

                <div class="form-group">
                    <label for="skills">Skills</label>
                    <textarea id="skills" name="skills" placeholder="List your current skills and areas of expertise"><?php echo htmlspecialchars($skills); ?></textarea>
                </div>

                <div class="form-group">
                    <label for="education">Education</label>
                    <textarea id="education" name="education" placeholder="Your educational background"><?php echo htmlspecialchars($education); ?></textarea>
                </div>

                <div class="form-group">
                    <label for="interests">Interests</label>
                    <textarea id="interests" name="interests" placeholder="Your personal and professional interests"><?php echo htmlspecialchars($interests); ?></textarea>
                </div>

                <button type="submit" class="submit-btn">Update Profile</button>
            </form>
        </div>
    </div>

    <script>
        // Preview uploaded image before form submission
        document.getElementById('profile_photo').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(event) {
                    document.getElementById('profilePhotoPreview').src = event.target.result;
                };
                reader.readAsDataURL(file);
            }
        });
    </script>
</body>
</html>

