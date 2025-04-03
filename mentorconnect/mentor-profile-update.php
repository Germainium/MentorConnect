<?php
// Initialize the session
session_start();

// Check if the user is logged in and is a mentor
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["user_type"] !== "mentor"){
  header("location: login.php");
  exit;
}

// Include database connection
require_once "config/db_connect.php";

// Define variables
$first_name = $_SESSION["first_name"];
$last_name = $_SESSION["last_name"];
$email = $_SESSION["email"];
$mentor_id = $_SESSION["user_id"];
$expertise = $experience_years = $bio = $hourly_rate = $profile_photo = $phone = "";
$success_message = $error_message = "";

// Create uploads directory if it doesn't exist
$upload_dir = "uploads/profile_photos/";
if (!file_exists($upload_dir)) {
  mkdir($upload_dir, 0777, true);
}

// Get mentor profile details
$sql = "SELECT * FROM mentor_profiles WHERE mentor_id = ?";
if($stmt = $conn->prepare($sql)){
  $stmt->bind_param("i", $mentor_id);
  $stmt->execute();
  $result = $stmt->get_result();
  
  if($result->num_rows == 1){
      $row = $result->fetch_assoc();
      $expertise = $row["expertise"];
      $experience_years = $row["experience_years"];
      $bio = $row["bio"];
      $hourly_rate = isset($row["hourly_rate"]) ? $row["hourly_rate"] : "";
      $profile_photo = isset($row["profile_photo"]) ? $row["profile_photo"] : "";
  }
  $stmt->close();
}

// Get phone from users table
$sql = "SELECT phone FROM users WHERE user_id = ?";
if($stmt = $conn->prepare($sql)){
  $stmt->bind_param("i", $mentor_id);
  $stmt->execute();
  $result = $stmt->get_result();
  if($result->num_rows == 1){
      $row = $result->fetch_assoc();
      $phone = $row["phone"];
  }
  $stmt->close();
}

// Process form submission
if($_SERVER["REQUEST_METHOD"] == "POST"){
  // Get form data
  $new_first_name = trim($_POST["first_name"]);
  $new_last_name = trim($_POST["last_name"]);
  $new_email = trim($_POST["email"]);
  $new_phone = trim($_POST["phone"]);
  $new_expertise = trim($_POST["expertise"]);
  $new_experience_years = trim($_POST["experience_years"]);
  $new_bio = trim($_POST["bio"]);
  $new_hourly_rate = trim($_POST["hourly_rate"]);
  
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
          $new_filename = "mentor_" . $mentor_id . "_" . time() . "." . $file_extension;
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
          $stmt->bind_param("ssssi", $new_first_name, $new_last_name, $new_email, $new_phone, $mentor_id);
          $stmt->execute();
          $stmt->close();
          
          // Check if mentor profile exists
          $sql = "SELECT mentor_id FROM mentor_profiles WHERE mentor_id = ?";
          $stmt = $conn->prepare($sql);
          $stmt->bind_param("i", $mentor_id);
          $stmt->execute();
          $result = $stmt->get_result();
          
          if($result->num_rows > 0) {
              // Update existing profile
              $sql = "UPDATE mentor_profiles SET expertise = ?, experience_years = ?, bio = ?, hourly_rate = ?, profile_photo = ? WHERE mentor_id = ?";
              $stmt = $conn->prepare($sql);
              $stmt->bind_param("sisssi", $new_expertise, $new_experience_years, $new_bio, $new_hourly_rate, $new_profile_photo, $mentor_id);
          } else {
              // Insert new profile
              $sql = "INSERT INTO mentor_profiles (mentor_id, expertise, experience_years, bio, hourly_rate, profile_photo) VALUES (?, ?, ?, ?, ?, ?)";
              $stmt = $conn->prepare($sql);
              $stmt->bind_param("isisss", $mentor_id, $new_expertise, $new_experience_years, $new_bio, $new_hourly_rate, $new_profile_photo);
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
          $expertise = $new_expertise;
          $experience_years = $new_experience_years;
          $bio = $new_bio;
          $hourly_rate = $new_hourly_rate;
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
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
      .form-group textarea,
      .form-group select {
          width: 100%;
          padding: 12px 15px;
          border: 1px solid #ddd;
          border-radius: 5px;
          font-size: 16px;
          transition: border-color 0.3s;
      }

      .form-group input:focus,
      .form-group textarea:focus,
      .form-group select:focus {
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
          <a href="mentor.php" class="back-link">
              <span class="back-icon"><i class="fas fa-arrow-left"></i></span> Back to Dashboard
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
                      <label for="profile_photo" class="photo-upload-label"><i class="fas fa-camera"></i></label>
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
                  <label for="expertise">Areas of Expertise</label>
                  <textarea id="expertise" name="expertise" placeholder="List your areas of expertise and specialization" required><?php echo htmlspecialchars($expertise); ?></textarea>
              </div>

              <div class="form-row">
                  <div class="form-group">
                      <label for="experience_years">Years of Experience</label>
                      <input type="number" id="experience_years" name="experience_years" value="<?php echo htmlspecialchars($experience_years); ?>" min="0" required>
                  </div>
                  <div class="form-group">
                      <label for="hourly_rate">Hourly Rate ($)</label>
                      <input type="number" id="hourly_rate" name="hourly_rate" value="<?php echo htmlspecialchars($hourly_rate); ?>" min="0" step="0.01">
                  </div>
              </div>

              <div class="form-group">
                  <label for="bio">Professional Bio</label>
                  <textarea id="bio" name="bio" placeholder="Tell us about your professional background and experience"><?php echo htmlspecialchars($bio); ?></textarea>
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

