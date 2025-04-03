<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Initialize the session
session_start();

// Include database connection
require_once "config/db_connect.php";

// Initialize variables
$email = $password = $confirm_password = $first_name = $last_name = $phone = "";
$user_type = "student"; // Default to student tab
$career_goals = $skills = $expertise = $experience = $availability = $bio = "";
$error = false;
$success_message = "";

// Check database connection
if ($conn->connect_error) {
  $error = true;
}

// Process form data when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
  // Get common form fields
  $first_name = trim($_POST["first_name"]);
  $last_name = trim($_POST["last_name"]);
  $email = trim($_POST["email"]);
  $phone = trim($_POST["phone"]);
  $password = trim($_POST["password"]);
  $confirm_password = trim($_POST["confirm_password"]);
  $user_type = trim($_POST["user_type"]);
  
  // Get user type specific fields
  if ($user_type == "student") {
      $career_goals = isset($_POST["career_goals"]) ? trim($_POST["career_goals"]) : "";
      $skills = isset($_POST["skills"]) ? trim($_POST["skills"]) : "";
  } else if ($user_type == "mentor") {
      $expertise = isset($_POST["expertise"]) ? trim($_POST["expertise"]) : "";
      $experience = isset($_POST["experience"]) ? trim($_POST["experience"]) : 0;
      $bio = isset($_POST["bio"]) ? trim($_POST["bio"]) : "";
  }
  
  // Validate email
  if (empty($email)) {
      $error = true;
      $email_error = "Please enter your email.";
  } else {
      // Check if email already exists
      $sql = "SELECT user_id FROM users WHERE email = ?";
      if ($stmt = $conn->prepare($sql)) {
          $stmt->bind_param("s", $email);
          if ($stmt->execute()) {
              $stmt->store_result();
              
              if ($stmt->num_rows > 0) {
                  $error = true;
                  $email_error = "This email is already taken.";
              }
          } else {
              $error = true;
          }
          $stmt->close();
      } else {
          $error = true;
      }
  }
  
  // Validate password
  if (empty($password)) {
      $error = true;
      $password_error = "Please enter a password.";
  } elseif (strlen($password) < 8) {
      $error = true;
      $password_error = "Password must have at least 8 characters.";
  }
  
  // Validate confirm password
  if ($password != $confirm_password) {
      $error = true;
      $confirm_password_error = "Passwords do not match.";
  }
  
  // Validate first name and last name
  if (empty($first_name) || empty($last_name)) {
      $error = true;
      if (empty($first_name)) {
          $first_name_error = "Please enter your first name.";
      }
      if (empty($last_name)) {
          $last_name_error = "Please enter your last name.";
      }
  }
  
  // Validate phone number (basic validation)
  if (empty($phone) || !preg_match('/^\+254\s?\d{9}$/', $phone)) {
      $error = true;
      $phone_error = "Please enter a valid phone number starting with +254.";
  }
  
  // Validate user type specific fields
  if ($user_type == "mentor") {
      if (empty($expertise)) {
          $error = true;
          $expertise_error = "Please enter your areas of expertise.";
      }
      
      if (empty($experience) || !is_numeric($experience) || $experience < 0) {
          $error = true;
          $experience_error = "Please enter valid years of experience.";
      }
  }
  
  // If no errors, insert into database
  if (!$error) {
      // Begin transaction
      $conn->begin_transaction();
      
      try {
          // Insert into users table
          $sql = "INSERT INTO users (email, password, first_name, last_name, phone, user_type) VALUES (?, ?, ?, ?, ?, ?)";
          
          if ($stmt = $conn->prepare($sql)) {
              $hashed_password = password_hash($password, PASSWORD_DEFAULT);
              $stmt->bind_param("ssssss", $email, $hashed_password, $first_name, $last_name, $phone, $user_type);
              
              if ($stmt->execute()) {
                  $user_id = $conn->insert_id;
              } else {
                  throw new Exception("Error inserting user: " . $stmt->error);
              }
              $stmt->close();
              
              // Insert into profile table based on user type
              if ($user_type == "student") {
                  $sql = "INSERT INTO student_profiles (student_id, career_goals, skills) VALUES (?, ?, ?)";
                  
                  if ($stmt = $conn->prepare($sql)) {
                      $stmt->bind_param("iss", $user_id, $career_goals, $skills);
                      
                      if (!$stmt->execute()) {
                          throw new Exception("Error inserting student profile: " . $stmt->error);
                      }
                      $stmt->close();
                  } else {
                      throw new Exception("Error preparing student profile query: " . $conn->error);
                  }
              } else if ($user_type == "mentor") {
                  $sql = "INSERT INTO mentor_profiles (mentor_id, expertise, experience_years, bio) VALUES (?, ?, ?, ?)";
                  
                  if ($stmt = $conn->prepare($sql)) {
                      $stmt->bind_param("isis", $user_id, $expertise, $experience, $bio);
                      
                      if (!$stmt->execute()) {
                          throw new Exception("Error inserting mentor profile: " . $stmt->error);
                      }
                      $stmt->close();
                      
                      // Now handle availability separately if needed
                      if (isset($_POST["availability"]) && !empty($_POST["availability"])) {
                          $selected_availability = trim($_POST["availability"]);
                          // Store this in a user preference or session for later use
                      }
                  } else {
                      throw new Exception("Error preparing mentor profile query: " . $conn->error);
                  }
              }
              
              // Commit transaction
              $conn->commit();
              
              // Set success message
              $success_message = "Registration successful! You can now log in.";
              
              // Clear form data
              $email = $password = $confirm_password = $first_name = $last_name = $phone = "";
              $career_goals = $skills = $expertise = $experience = $availability = $bio = "";
              
          } else {
              throw new Exception("Error preparing user query: " . $conn->error);
          }
          
      } catch (Exception $e) {
          // Rollback transaction on error
          $conn->rollback();
          $error = true;
          $general_error = "Registration failed: " . $e->getMessage();
          error_log("Registration error: " . $e->getMessage());
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
<title>Register - MentorConnect</title>
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
  min-height: 100vh;
  display: flex;
  flex-direction: column;
}

a {
  text-decoration: none;
  color: #3498db;
  transition: color 0.3s;
}

a:hover {
  color: #2980b9;
}

/* Header */
header {
  background-color: #fff;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
  padding: 15px 0;
}

.container {
  max-width: 1200px;
  margin: 0 auto;
  padding: 0 20px;
}

.header-container {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.logo {
  display: flex;
  align-items: center;
}

.logo-icon {
  width: 40px;
  height: 40px;
  background-color: #3498db;
  color: white;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: bold;
  margin-right: 10px;
}

.logo-text {
  font-size: 20px;
  font-weight: bold;
}

.back-link {
  display: flex;
  align-items: center;
  color: #333;
  font-weight: 500;
}

.back-link:hover {
  color: #3498db;
}

.back-icon {
  margin-right: 5px;
}

/* Main Content */
main {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 40px 20px;
}

.register-container {
  max-width: 900px;
  width: 100%;
  background-color: white;
  border-radius: 10px;
  overflow: hidden;
  box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
}

.register-header {
  text-align: center;
  padding: 30px;
  background-color: #f9f9f9;
  border-bottom: 1px solid #eee;
}

.register-header h1 {
  font-size: 2rem;
  color: #333;
  margin-bottom: 10px;
}

.register-header p {
  color: #777;
}

.register-tabs {
  display: flex;
  border-bottom: 1px solid #eee;
}

.register-tab {
  flex: 1;
  padding: 15px;
  text-align: center;
  cursor: pointer;
  font-weight: 500;
  transition: background-color 0.3s;
}

.register-tab.active {
  background-color: #3498db;
  color: white;
}

.register-tab:hover:not(.active) {
  background-color: #f5f5f5;
}

.register-form-container {
  padding: 30px;
}

.form-section {
  display: none;
}

.form-section.active {
  display: block;
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
  font-size: 1rem;
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

.form-group .error-message {
  color: #e74c3c;
  font-size: 0.9rem;
  margin-top: 5px;
}

.form-group.error input,
.form-group.error textarea,
.form-group.error select {
  border-color: #e74c3c;
}

.form-row {
  display: flex;
  gap: 20px;
}

.form-row .form-group {
  flex: 1;
}

.register-button {
  width: 100%;
  padding: 12px;
  background-color: #3498db;
  color: white;
  border: none;
  border-radius: 5px;
  font-size: 1rem;
  font-weight: bold;
  cursor: pointer;
  transition: background-color 0.3s;
  margin-top: 10px;
}

.register-button:hover {
  background-color: #2980b9;
}

.login-link {
  text-align: center;
  margin-top: 20px;
}

/* Success Message */
.success-message {
  background-color: #d4edda;
  color: #155724;
  padding: 15px;
  margin-bottom: 20px;
  border-radius: 5px;
  text-align: center;
}

/* Error Message */
.error-message {
  color: #e74c3c;
  font-size: 0.9rem;
  margin-top: 5px;
}

.general-error {
  background-color: #f8d7da;
  color: #721c24;
  padding: 15px;
  margin-bottom: 20px;
  border-radius: 5px;
  text-align: center;
}

/* Phone Input Styles */
.phone-input-container {
  display: flex;
  align-items: center;
}

.phone-prefix {
  background-color: #f5f5f5;
  border: 1px solid #ddd;
  border-right: none;
  border-radius: 5px 0 0 5px;
  padding: 12px 15px;
  font-size: 1rem;
  color: #555;
  font-weight: 500;
}

.phone-input {
  border-radius: 0 5px 5px 0 !important;
}

/* Footer */
footer {
  background-color: #2c3e50;
  color: white;
  padding: 20px 0;
  text-align: center;
}

.footer-content {
  max-width: 1200px;
  margin: 0 auto;
  padding: 0 20px;
}

/* Responsive Styles */
@media (max-width: 768px) {
  .form-row {
    flex-direction: column;
    gap: 0;
  }
}
</style>
</head>
<body>
<!-- Header -->
<header>
  <div class="container">
    <div class="header-container">
      <div class="logo">
        <div class="logo-icon">MC</div>
        <div class="logo-text">MentorConnect</div>
      </div>
      <a href="index.php" class="back-link">
        <span class="back-icon">←</span> Back to Home
      </a>
    </div>
  </div>
</header>

<!-- Main Content -->
<main>
  <div class="register-container">
    <div class="register-header">
      <h1>Create an Account</h1>
      <p>Join our community and start your mentorship journey</p>
    </div>
    
    <?php if (!empty($success_message)): ?>
      <div class="success-message"><?php echo $success_message; ?></div>
      <div class="register-form-container">
        <div class="login-link">
          <a href="login.php" class="register-button">Proceed to Login</a>
        </div>
      </div>
    <?php else: ?>
    
    <div class="register-tabs">
      <div class="register-tab <?php echo ($user_type == 'student') ? 'active' : ''; ?>" data-tab="student">Student</div>
      <div class="register-tab <?php echo ($user_type == 'mentor') ? 'active' : ''; ?>" data-tab="mentor">Mentor</div>
    </div>
    
    <div class="register-form-container">
      <!-- Student Registration Form -->
      <form id="studentForm" class="form-section <?php echo ($user_type == 'student') ? 'active' : ''; ?>" method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
        <input type="hidden" name="user_type" value="student">
        
        <div class="form-row">
          <div class="form-group <?php echo (!empty($first_name_error)) ? 'error' : ''; ?>">
            <label for="student-first-name">First Name</label>
            <input type="text" id="student-first-name" name="first_name" value="<?php echo htmlspecialchars($first_name); ?>" required>
            <?php if (!empty($first_name_error)): ?>
              <div class="error-message"><?php echo $first_name_error; ?></div>
            <?php endif; ?>
          </div>
          <div class="form-group <?php echo (!empty($last_name_error)) ? 'error' : ''; ?>">
            <label for="student-last-name">Last Name</label>
            <input type="text" id="student-last-name" name="last_name" value="<?php echo htmlspecialchars($last_name); ?>" required>
            <?php if (!empty($last_name_error)): ?>
              <div class="error-message"><?php echo $last_name_error; ?></div>
            <?php endif; ?>
          </div>
        </div>
        
        <div class="form-group <?php echo (!empty($email_error)) ? 'error' : ''; ?>">
          <label for="student-email">Email Address</label>
          <input type="email" id="student-email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
          <?php if (!empty($email_error)): ?>
            <div class="error-message"><?php echo $email_error; ?></div>
          <?php endif; ?>
        </div>
        
        <div class="form-group <?php echo (!empty($phone_error)) ? 'error' : ''; ?>">
          <label for="student-phone">Phone Number</label>
          <div class="phone-input-container">
            <span class="phone-prefix">+254</span>
            <input type="tel" id="student-phone" name="phone" class="phone-input" placeholder="7XX XXX XXX" value="<?php echo htmlspecialchars(str_replace('+254', '', $phone)); ?>" required>
          </div>
          <?php if (!empty($phone_error)): ?>
            <div class="error-message"><?php echo $phone_error; ?></div>
          <?php endif; ?>
        </div>
        
        <div class="form-row">
          <div class="form-group <?php echo (!empty($password_error)) ? 'error' : ''; ?>">
            <label for="student-password">Password</label>
            <input type="password" id="student-password" name="password" required>
            <?php if (!empty($password_error)): ?>
              <div class="error-message"><?php echo $password_error; ?></div>
            <?php endif; ?>
          </div>
          <div class="form-group <?php echo (!empty($confirm_password_error)) ? 'error' : ''; ?>">
            <label for="student-confirm-password">Confirm Password</label>
            <input type="password" id="student-confirm-password" name="confirm_password" required>
            <?php if (!empty($confirm_password_error)): ?>
              <div class="error-message"><?php echo $confirm_password_error; ?></div>
            <?php endif; ?>
          </div>
        </div>
        
        <div class="form-group">
          <label for="student-career-goals">Career Goals</label>
          <textarea id="student-career-goals" name="career_goals" placeholder="Describe your career aspirations and what you hope to achieve"><?php echo htmlspecialchars($career_goals); ?></textarea>
        </div>
        
        <div class="form-group">
          <label for="student-skills">Current Skills</label>
          <textarea id="student-skills" name="skills" placeholder="List your current skills and areas of expertise"><?php echo htmlspecialchars($skills); ?></textarea>
        </div>
        
        <button type="submit" class="register-button">Create Student Account</button>
        
        <div class="login-link">
          Already have an account? <a href="login.php">Log in</a>
        </div>
      </form>
      
      <!-- Mentor Registration Form -->
      <form id="mentorForm" class="form-section <?php echo ($user_type == 'mentor') ? 'active' : ''; ?>" method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
        <input type="hidden" name="user_type" value="mentor">
        
        <div class="form-row">
          <div class="form-group <?php echo (!empty($first_name_error)) ? 'error' : ''; ?>">
            <label for="mentor-first-name">First Name</label>
            <input type="text" id="mentor-first-name" name="first_name" value="<?php echo htmlspecialchars($first_name); ?>" required>
            <?php if (!empty($first_name_error)): ?>
              <div class="error-message"><?php echo $first_name_error; ?></div>
            <?php endif; ?>
          </div>
          <div class="form-group <?php echo (!empty($last_name_error)) ? 'error' : ''; ?>">
            <label for="mentor-last-name">Last Name</label>
            <input type="text" id="mentor-last-name" name="last_name" value="<?php echo htmlspecialchars($last_name); ?>" required>
            <?php if (!empty($last_name_error)): ?>
              <div class="error-message"><?php echo $last_name_error; ?></div>
            <?php endif; ?>
          </div>
        </div>
        
        <div class="form-group <?php echo (!empty($email_error)) ? 'error' : ''; ?>">
          <label for="mentor-email">Email Address</label>
          <input type="email" id="mentor-email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
          <?php if (!empty($email_error)): ?>
            <div class="error-message"><?php echo $email_error; ?></div>
          <?php endif; ?>
        </div>
        
        <div class="form-group <?php echo (!empty($phone_error)) ? 'error' : ''; ?>">
          <label for="mentor-phone">Phone Number</label>
          <div class="phone-input-container">
            <span class="phone-prefix">+254</span>
            <input type="tel" id="mentor-phone" name="phone" class="phone-input" placeholder="7XX XXX XXX" value="<?php echo htmlspecialchars(str_replace('+254', '', $phone)); ?>" required>
          </div>
          <?php if (!empty($phone_error)): ?>
            <div class="error-message"><?php echo $phone_error; ?></div>
          <?php endif; ?>
        </div>
        
        <div class="form-row">
          <div class="form-group <?php echo (!empty($password_error)) ? 'error' : ''; ?>">
            <label for="mentor-password">Password</label>
            <input type="password" id="mentor-password" name="password" required>
            <?php if (!empty($password_error)): ?>
              <div class="error-message"><?php echo $password_error; ?></div>
            <?php endif; ?>
          </div>
          <div class="form-group <?php echo (!empty($confirm_password_error)) ? 'error' : ''; ?>">
            <label for="mentor-confirm-password">Confirm Password</label>
            <input type="password" id="mentor-confirm-password" name="confirm_password" required>
            <?php if (!empty($confirm_password_error)): ?>
              <div class="error-message"><?php echo $confirm_password_error; ?></div>
            <?php endif; ?>
          </div>
        </div>
        
        <div class="form-group <?php echo (!empty($expertise_error)) ? 'error' : ''; ?>">
          <label for="mentor-expertise">Areas of Expertise</label>
          <textarea id="mentor-expertise" name="expertise" placeholder="List your areas of expertise and specialization" required><?php echo htmlspecialchars($expertise); ?></textarea>
          <?php if (!empty($expertise_error)): ?>
            <div class="error-message"><?php echo $expertise_error; ?></div>
            <?php endif;?>
       
        
        <div class="form-group <?php echo (!empty($experience_error)) ? 'error' : ''; ?>">
          <label for="mentor-experience">Years of Experience</label>
          <input type="number" id="mentor-experience" name="experience" min="0" value="<?php echo htmlspecialchars($experience); ?>" required>
          <?php if (!empty($experience_error)): ?>
            <div class="error-message"><?php echo $experience_error; ?></div>
          <?php endif; ?>
        </div>
        
        <div class="form-group">
          <label for="mentor-availability">Availability</label>
          <select id="mentor-availability" name="availability">
            <option value="">Select your availability</option>
            <option value="weekdays" <?php if($availability == "weekdays") echo "selected"; ?>>Weekdays</option>
            <option value="evenings" <?php if($availability == "evenings") echo "selected"; ?>>Evenings</option>
            <option value="weekends" <?php if($availability == "weekends") echo "selected"; ?>>Weekends</option>
            <option value="flexible" <?php if($availability == "flexible") echo "selected"; ?>>Flexible</option>
          </select>
        </div>
        
        <div class="form-group">
          <label for="mentor-bio">Professional Bio</label>
          <textarea id="mentor-bio" name="bio" placeholder="Tell us about your professional background and why you want to be a mentor"><?php echo htmlspecialchars($bio); ?></textarea>
        </div>
        
        <button type="submit" class="register-button">Create Mentor Account</button>
        
        <div class="login-link">
          Already have an account? <a href="login.php">Log in</a>
        </div>
      </form>
    </div>
    <?php endif; ?>
  </div>
</main>

<!-- Footer -->
<footer>
  <div class="footer-content">
    <p>&copy; 2025 MentorConnect. All rights reserved.</p>
  </div>
</footer>

<script>
  // Tab switching functionality
  const tabs = document.querySelectorAll('.register-tab');
  const forms = document.querySelectorAll('.form-section');
  
  tabs.forEach(tab => {
    tab.addEventListener('click', function() {
      // Update active tab
      tabs.forEach(t => t.classList.remove('active'));
      this.classList.add('active');
      
      // Show corresponding form
      const role = this.getAttribute('data-tab');
      forms.forEach(form => {
        form.classList.remove('active');
        if (form.querySelector('input[name="user_type"]').value === role) {
          form.classList.add('active');
        }
      });
    });
  });
  
  // Clear error on input
  const inputs = document.querySelectorAll('input, textarea, select');
  inputs.forEach(input => {
    input.addEventListener('input', function() {
      const formGroup = this.closest('.form-group');
      if (formGroup && formGroup.classList.contains('error')) {
        formGroup.classList.remove('error');
        const errorMessage = formGroup.querySelector('.error-message');
        if (errorMessage) {
          errorMessage.style.display = 'none';
        }
      }
    });
  });

  // Phone number formatting
  const phoneInputs = document.querySelectorAll('.phone-input');
  phoneInputs.forEach(input => {
    input.addEventListener('input', function(e) {
      // Remove any non-digit characters
      let value = this.value.replace(/\D/g, '');
      
      // Ensure the value starts with the correct format
      if (value.length > 0) {
        // Format the phone number
        if (value.length <= 9) {
          this.value = value;
        } else {
          this.value = value.substring(0, 9);
        }
      }
    });

    // When form is submitted, prepend +254 to the phone number
    input.closest('form').addEventListener('submit', function() {
      const phoneInput = this.querySelector('.phone-input');
      const hiddenPhoneInput = document.createElement('input');
      hiddenPhoneInput.type = 'hidden';
      hiddenPhoneInput.name = 'phone';
      hiddenPhoneInput.value = '+254' + phoneInput.value;
      this.appendChild(hiddenPhoneInput);
    });
  });
</script>
</body>
</html>

