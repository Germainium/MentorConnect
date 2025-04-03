<?php
// Initialize the session
session_start();

// Check if the user is logged in and is a student
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["user_type"] !== "student"){
  header("location: logpage.php");
  exit;
}

// Include database connection
require_once "config/db_connect.php";

// Get student information
$student_id = $_SESSION["user_id"];
$first_name = $_SESSION["first_name"];
$last_name = $_SESSION["last_name"];
$email = $_SESSION["email"];

// Get student profile details
$career_goals = $skills = $education = $interests = $phone = "";
$sql = "SELECT * FROM student_profiles WHERE student_id = ?";
if($stmt = $conn->prepare($sql)){
  $stmt->bind_param("i", $student_id);
  $stmt->execute();
  $result = $stmt->get_result();
  
  if($result->num_rows == 1){
      $row = $result->fetch_assoc();
      $career_goals = $row["career_goals"];
      $skills = $row["skills"];
      // Check if these columns exist in your table
      $education = isset($row["education"]) ? $row["education"] : "";
      $interests = isset($row["interests"]) ? $row["interests"] : "";
      $phone = isset($row["phone"]) ? $row["phone"] : "";
  }
  $stmt->close();
}

// Get dashboard statistics
$stats = array();

// Completed sessions
$sql = "SELECT COUNT(*) as count FROM sessions WHERE student_id = ? AND status = 'completed'";
if($stmt = $conn->prepare($sql)){
  $stmt->bind_param("i", $student_id);
  $stmt->execute();
  $result = $stmt->get_result();
  if($result->num_rows > 0){
      $row = $result->fetch_assoc();
      $stats['completed_sessions'] = $row['count'];
  }
  $stmt->close();
}

// Upcoming sessions
$sql = "SELECT COUNT(*) as count FROM sessions WHERE student_id = ? AND status = 'accepted' AND session_date >= CURDATE()";
if($stmt = $conn->prepare($sql)){
  $stmt->bind_param("i", $student_id);
  $stmt->execute();
  $result = $stmt->get_result();
  if($result->num_rows > 0){
      $row = $result->fetch_assoc();
      $stats['upcoming_sessions'] = $row['count'];
  }
  $stmt->close();
}

// Get upcoming sessions
$upcoming_sessions = array();
$sql = "SELECT s.*, u.first_name, u.last_name 
      FROM sessions s 
      JOIN users u ON s.mentor_id = u.user_id 
      WHERE s.student_id = ? AND s.status = 'accepted' AND s.session_date >= CURDATE() 
      ORDER BY s.session_date, s.start_time";
if($stmt = $conn->prepare($sql)){
  $stmt->bind_param("i", $student_id);
  $stmt->execute();
  $result = $stmt->get_result();
  while($row = $result->fetch_assoc()){
      $upcoming_sessions[] = $row;
  }
  $stmt->close();
}

// Get recent activity (completed sessions that need feedback)
$recent_activity = array();
$sql = "SELECT s.*, u.first_name, u.last_name, 
      (SELECT COUNT(*) FROM feedback WHERE session_id = s.session_id AND from_user_id = ?) as has_feedback
      FROM sessions s 
      JOIN users u ON s.mentor_id = u.user_id 
      WHERE s.student_id = ? AND s.status = 'completed' 
      ORDER BY s.session_date DESC 
      LIMIT 5";
if($stmt = $conn->prepare($sql)){
  $stmt->bind_param("ii", $student_id, $student_id);
  $stmt->execute();
  $result = $stmt->get_result();
  while($row = $result->fetch_assoc()){
      $recent_activity[] = $row;
  }
  $stmt->close();
}

// Get all mentors for the "Find Mentors" section
$mentors = array();
$sql = "SELECT u.user_id, u.first_name, u.last_name, mp.expertise, mp.experience_years, mp.bio, mp.average_rating 
      FROM users u 
      JOIN mentor_profiles mp ON u.user_id = mp.mentor_id 
      WHERE u.user_type = 'mentor' 
      ORDER BY mp.average_rating DESC";
if($stmt = $conn->prepare($sql)){
  $stmt->execute();
  $result = $stmt->get_result();
  while($row = $result->fetch_assoc()){
      $mentors[] = $row;
  }
  $stmt->close();
}

// Get session history
$session_history = array();
$sql = "SELECT s.*, u.first_name, u.last_name, 
      (SELECT COUNT(*) FROM feedback WHERE session_id = s.session_id AND from_user_id = ?) as has_feedback
      FROM sessions s 
      JOIN users u ON s.mentor_id = u.user_id 
      WHERE s.student_id = ? AND s.status = 'completed' 
      ORDER BY s.session_date DESC";
if($stmt = $conn->prepare($sql)){
  $stmt->bind_param("ii", $student_id, $student_id);
  $stmt->execute();
  $result = $stmt->get_result();
  while($row = $result->fetch_assoc()){
      $session_history[] = $row;
  }
  $stmt->close();
}

// Process profile update
if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["update_profile"])){
  // Validate and sanitize input
  $new_first_name = trim($_POST["fullName"]);
  $new_email = trim($_POST["email"]);
  $new_phone = trim($_POST["phone"]);
  $new_career_goals = trim($_POST["career_goals"]);
  $new_skills = trim($_POST["skills"]);
  $new_education = trim($_POST["education"]);
  $new_interests = trim($_POST["interests"]);
  
  // Update user table
  $sql = "UPDATE users SET first_name = ?, last_name = ?, email = ? WHERE user_id = ?";
  if($stmt = $conn->prepare($sql)){
      // Extract first and last name
      $name_parts = explode(" ", $new_first_name, 2);
      $first_name = $name_parts[0];
      $last_name = isset($name_parts[1]) ? $name_parts[1] : "";
      
      $stmt->bind_param("sssi", $first_name, $last_name, $new_email, $student_id);
      $stmt->execute();
      $stmt->close();
      
      // Update session variables
      $_SESSION["first_name"] = $first_name;
      $_SESSION["last_name"] = $last_name;
      $_SESSION["email"] = $new_email;
  }
  
  // Update student profile
  $sql = "UPDATE student_profiles SET career_goals = ?, skills = ?, education = ?, interests = ?, phone = ? WHERE student_id = ?";
  if($stmt = $conn->prepare($sql)){
      $stmt->bind_param("sssssi", $new_career_goals, $new_skills, $new_education, $new_interests, $new_phone, $student_id);
      $stmt->execute();
      $stmt->close();
      
      // Update local variables
      $career_goals = $new_career_goals;
      $skills = $new_skills;
      $education = $new_education;
      $interests = $new_interests;
      $phone = $new_phone;
      
      $profile_updated = true;
  }
}

// Process password change
if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["change_password"])){
  $current_password = trim($_POST["current_password"]);
  $new_password = trim($_POST["new_password"]);
  $confirm_password = trim($_POST["confirm_password"]);
  
  // Validate input
  $password_error = false;
  
  // Check if current password is correct
  $sql = "SELECT password FROM users WHERE user_id = ?";
  if($stmt = $conn->prepare($sql)){
      $stmt->bind_param("i", $student_id);
      $stmt->execute();
      $result = $stmt->get_result();
      
      if($result->num_rows == 1){
          $row = $result->fetch_assoc();
          if(!password_verify($current_password, $row["password"])){
              $current_password_error = "Current password is incorrect.";
              $password_error = true;
          }
      }
      $stmt->close();
  }
  
  // Check if new password is valid
  if(empty($new_password)){
      $new_password_error = "Please enter a new password.";
      $password_error = true;
  } elseif(strlen($new_password) < 8){
      $new_password_error = "Password must have at least 8 characters.";
      $password_error = true;
  }
  
  // Check if passwords match
  if($new_password != $confirm_password){
      $confirm_password_error = "Passwords do not match.";
      $password_error = true;
  }
  
  // Update password if no errors
  if(!$password_error){
      $sql = "UPDATE users SET password = ? WHERE user_id = ?";
      if($stmt = $conn->prepare($sql)){
          $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
          $stmt->bind_param("si", $hashed_password, $student_id);
          
          if($stmt->execute()){
              $password_updated = true;
          } else {
              $password_update_error = "Something went wrong. Please try again.";
          }
          $stmt->close();
      }
  }
}

// Process session booking
if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["book_session"])){
  $mentor_id = $_POST["mentor_id"];
  $topic = $_POST["topic"];
  $session_date = $_POST["session_date"];
  $session_time = $_POST["session_time"];
  $notes = $_POST["notes"];
  
  // Calculate end time (1 hour after start time)
  $start_time = $session_time;
  $end_time = date('H:i:s', strtotime($start_time . ' + 1 hour'));
  
  // Insert session request
  $sql = "INSERT INTO sessions (student_id, mentor_id, topic, session_date, start_time, end_time, notes, status, created_at) 
          VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW())";
  if($stmt = $conn->prepare($sql)){
      $stmt->bind_param("iisssss", $student_id, $mentor_id, $topic, $session_date, $start_time, $end_time, $notes);
      
      if($stmt->execute()){
          $session_booked = true;
      } else {
          $booking_error = "Something went wrong. Please try again.";
      }
      $stmt->close();
  }
}

// Process session cancellation
if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["cancel_session"])){
  $session_id = $_POST["session_id"];
  
  // Update session status
  $sql = "UPDATE sessions SET status = 'cancelled' WHERE session_id = ? AND student_id = ?";
  if($stmt = $conn->prepare($sql)){
      $stmt->bind_param("ii", $session_id, $student_id);
      
      if($stmt->execute()){
          $session_cancelled = true;
      } else {
          $cancellation_error = "Something went wrong. Please try again.";
      }
      $stmt->close();
  }
}

// Process feedback submission
if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["submit_feedback"])){
  $session_id = $_POST["session_id"];
  $rating = $_POST["rating"];
  $comments = $_POST["comments"];
  $most_valuable = $_POST["most_valuable"];
  $improvement = $_POST["improvement"];
  $future_topics = $_POST["future_topics"];
  
  // Get mentor ID from session
  $sql = "SELECT mentor_id FROM sessions WHERE session_id = ?";
  if($stmt = $conn->prepare($sql)){
      $stmt->bind_param("i", $session_id);
      $stmt->execute();
      $result = $stmt->get_result();
      
      if($result->num_rows == 1){
          $row = $result->fetch_assoc();
          $mentor_id = $row["mentor_id"];
          
          // Insert feedback
          $feedback_sql = "INSERT INTO feedback (session_id, from_user_id, to_user_id, rating, comments, strengths, areas_for_improvement, future_topics) 
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
          $feedback_stmt = $conn->prepare($feedback_sql);
          $feedback_stmt->bind_param("iiisssss", $session_id, $student_id, $mentor_id, $rating, $comments, $most_valuable, $improvement, $future_topics);
          
          if($feedback_stmt->execute()){
              $feedback_submitted = true;
              
              // Update mentor's average rating
              $update_rating_sql = "UPDATE mentor_profiles 
                                  SET average_rating = (
                                      SELECT AVG(rating) 
                                      FROM feedback 
                                      WHERE to_user_id = ?
                                  ) 
                                  WHERE mentor_id = ?";
              $update_rating_stmt = $conn->prepare($update_rating_sql);
              $update_rating_stmt->bind_param("ii", $mentor_id, $mentor_id);
              $update_rating_stmt->execute();
              $update_rating_stmt->close();
          } else {
              $feedback_error = "Something went wrong. Please try again.";
          }
          $feedback_stmt->close();
      }
      $stmt->close();
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
<title>Student Dashboard - MentorConnect</title>
<style>
/* Basic Reset */
* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
  font-family: Arial, sans-serif;
}

body {
  background-color: #f5f5f5;
}

/* Main Layout */
.container {
  display: flex;
  min-height: 100vh;
}

/* Sidebar */
.sidebar {
  width: 250px;
  background-color: #2c3e50;
  color: white;
  height: 100vh;
  position: fixed;
  z-index: 100;
}

.sidebar-header {
  padding: 20px;
  text-align: center;
  border-bottom: 1px solid #34495e;
}

.student-avatar {
  width: 80px;
  height: 80px;
  background-color: #3498db;
  border-radius: 50%;
  margin: 0 auto 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 36px;
  color: white;
}

.student-name {
  font-size: 18px;
  margin-bottom: 5px;
}

.student-title {
  font-size: 14px;
  color: #bdc3c7;
}

.sidebar-menu {
  padding: 20px 0;
  max-height: calc(100vh - 200px);
  overflow-y: auto;
}

.menu-item {
  padding: 12px 20px;
  cursor: pointer;
  transition: background-color 0.3s;
  display: flex;
  align-items: center;
}

.menu-item:hover {
  background-color: #34495e;
}

.menu-item.active {
  background-color: #3498db;
}

.menu-icon {
  margin-right: 10px;
  font-size: 18px;
  width: 20px;
  text-align: center;
}

/* Main Content */
.main-content {
  flex: 1;
  margin-left: 250px;
  padding: 20px;
}

.header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
  padding-bottom: 20px;
  border-bottom: 1px solid #ddd;
}

.page-title {
  font-size: 24px;
  font-weight: bold;
}

/* Dashboard Cards */
.stats-container {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
  gap: 20px;
  margin-bottom: 30px;
}

.stat-card {
  background-color: white;
  border-radius: 8px;
  box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
  padding: 20px;
}

.stat-title {
  font-size: 14px;
  color: #7f8c8d;
  margin-bottom: 10px;
}

.stat-value {
  font-size: 28px;
  font-weight: bold;
  margin-bottom: 5px;
}

.stat-description {
  font-size: 12px;
  color: #7f8c8d;
}

/* Section Common Styles */
.section-title {
  font-size: 18px;
  margin-bottom: 15px;
}

.content-section {
  display: none;
}

.content-section.active {
  display: block;
}

/* Mentor List */
.mentor-list {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
  gap: 20px;
}

.mentor-card {
  background-color: white;
  border-radius: 8px;
  box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
  overflow: hidden;
}

.mentor-header {
  display: flex;
  padding: 15px;
  border-bottom: 1px solid #eee;
}

.mentor-avatar {
  width: 60px;
  height: 60px;
  background-color: #3498db;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-weight: bold;
  font-size: 24px;
  margin-right: 15px;
}

.mentor-info h3 {
  font-size: 18px;
  margin-bottom: 5px;
}

.mentor-info p {
  font-size: 14px;
  color: #7f8c8d;
}

.mentor-body {
  padding: 15px;
}

.mentor-body p {
  margin-bottom: 10px;
  font-size: 14px;
}

.mentor-footer {
  padding: 15px;
  border-top: 1px solid #eee;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.mentor-rating {
  color: #f1c40f;
}

.book-btn {
  background-color: #3498db;
  color: white;
  border: none;
  border-radius: 4px;
  padding: 8px 15px;
  cursor: pointer;
}

/* Session List */
.session-list {
  background-color: white;
  border-radius: 8px;
  box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
  overflow: hidden;
  margin-bottom: 30px;
}

.session-item {
  padding: 15px 20px;
  border-bottom: 1px solid #eee;
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.session-item:last-child {
  border-bottom: none;
}

.mentor-profile {
  display: flex;
  align-items: center;
}

.mentor-mini-avatar {
  width: 40px;
  height: 40px;
  background-color: #3498db;
  border-radius: 50%;
  margin-right: 15px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-weight: bold;
}

.mentor-details h4 {
  font-size: 16px;
  margin-bottom: 5px;
}

.mentor-details p {
  font-size: 14px;
  color: #7f8c8d;
}

.session-actions button {
  padding: 8px 15px;
  border: none;
  border-radius: 4px;
  cursor: pointer;
  margin-left: 10px;
  font-size: 14px;
}

.join-btn {
  background-color: #2ecc71;
  color: white;
}

.cancel-btn {
  background-color: #e74c3c;
  color: white;
}

.feedback-btn {
  background-color: #f39c12;
  color: white;
}

/* Profile Form */
.profile-form {
  background-color: white;
  border-radius: 8px;
  box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
  padding: 20px;
  margin-bottom: 20px;
}

.form-group {
  margin-bottom: 20px;
}

.form-group label {
  display: block;
  margin-bottom: 5px;
  font-weight: bold;
}

.form-group input,
.form-group textarea,
.form-group select {
  width: 100%;
  padding: 10px;
  border: 1px solid #ddd;
  border-radius: 4px;
  font-size: 14px;
}

.form-group textarea {
  height: 100px;
  resize: vertical;
}

.submit-btn {
  background-color: #3498db;
  color: white;
  border: none;
  border-radius: 4px;
  padding: 10px 20px;
  cursor: pointer;
  font-size: 16px;
}

/* Feedback Form */
.feedback-form {
  background-color: white;
  border-radius: 8px;
  box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
  padding: 20px;
  margin-top: 20px;
}

.rating {
  display: flex;
  margin-bottom: 15px;
}

.star {
  font-size: 24px;
  color: #ddd;
  cursor: pointer;
  margin-right: 5px;
}

.star.selected {
  color: #f1c40f;
}

/* Search Form */
.search-form {
  background-color: white;
  border-radius: 8px;
  box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
  padding: 20px;
  margin-bottom: 20px;
}

.search-row {
  display: flex;
  gap: 20px;
  margin-bottom: 20px;
}

.search-group {
  flex: 1;
}

.search-btn {
  background-color: #3498db;
  color: white;
  border: none;
  border-radius: 4px;
  padding: 10px 20px;
  cursor: pointer;
  font-size: 16px;
}

.filter-options {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
}

.filter-tag {
  background-color: #ecf0f1;
  border-radius: 20px;
  padding: 5px 15px;
  font-size: 14px;
  cursor: pointer;
}

.filter-tag.active {
  background-color: #3498db;
  color: white;
}

/* Resources Section */
.resource-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
  gap: 20px;
}

.resource-card {
  background-color: white;
  border-radius: 8px;
  box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
  overflow: hidden;
}

.resource-image {
  height: 150px;
  background-color: #3498db;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-size: 36px;
}

.resource-content {
  padding: 15px;
}

.resource-content h3 {
  margin-bottom: 10px;
  font-size: 18px;
}

.resource-content p {
  color: #7f8c8d;
  margin-bottom: 15px;
  font-size: 14px;
}

.resource-link {
  display: inline-block;
  color: #3498db;
  text-decoration: none;
}

/* Booking Modal */
.modal {
  display: none;
  position: fixed;
  z-index: 1000;
  left: 0;
  top: 0;
  width: 100%;
  height: 100%;
  background-color: rgba(0, 0, 0, 0.5);
}

.modal-content {
  background-color: white;
  margin: 5% auto;
  padding: 20px;
  border-radius: 8px;
  max-width: 500px;
  max-height: 80vh;
  overflow-y: auto;
  box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
}

.close-btn {
  float: right;
  font-size: 24px;
  font-weight: bold;
  cursor: pointer;
}

.modal-title {
  margin-bottom: 20px;
  font-size: 20px;
}

/* Calendar */
.calendar {
  margin-bottom: 20px;
}

.calendar-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 10px;
}

.calendar-title {
  font-size: 18px;
}

.calendar-nav button {
  background: none;
  border: none;
  cursor: pointer;
  font-size: 18px;
}

.weekdays {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: 5px;
  margin-bottom: 10px;
}

.weekday {
  text-align: center;
  font-weight: bold;
  padding: 10px;
}

.calendar-days {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: 5px;
}

.calendar-day {
  height: 40px;
  border: 1px solid #ddd;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
}

.calendar-day:hover {
  background-color: #f5f5f5;
}

.calendar-day.selected {
  background-color: #3498db;
  color: white;
}

.calendar-day.unavailable {
  background-color: #f5f5f5;
  color: #bbb;
  cursor: not-allowed;
}

/* Time Slots */
.time-slots {
  margin-bottom: 20px;
}

.time-slot {
  display: inline-block;
  padding: 8px 15px;
  border: 1px solid #ddd;
  border-radius: 4px;
  margin-right: 10px;
  margin-bottom: 10px;
  cursor: pointer;
}

.time-slot.selected {
  background-color: #3498db;
  color: white;
  border-color: #3498db;
}

.time-slot.unavailable {
  background-color: #f5f5f5;
  color: #bbb;
  cursor: not-allowed;
}

/* Mobile Menu Button */
.mobile-menu-btn {
  display: none;
  position: fixed;
  top: 10px;
  left: 10px;
  z-index: 200;
  background-color: #3498db;
  color: white;
  border: none;
  border-radius: 4px;
  width: 40px;
  height: 40px;
  font-size: 24px;
  cursor: pointer;
}

/* Success and Error Messages */
.alert {
  padding: 15px;
  margin-bottom: 20px;
  border-radius: 4px;
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

/* Responsive Styles */
@media (max-width: 768px) {
  .sidebar {
    transform: translateX(-100%);
    transition: transform 0.3s;
  }

  .sidebar.active {
    transform: translateX(0);
  }

  .main-content {
    margin-left: 0;
  }

  .mobile-menu-btn {
    display: block;
  }

  .search-row {
    flex-direction: column;
    gap: 10px;
  }

  .session-item {
    flex-direction: column;
    align-items: flex-start;
  }

  .session-actions {
    margin-top: 10px;
    display: flex;
    width: 100%;
    justify-content: space-between;
  }

  .session-actions button {
    margin-left: 0;
  }

  .mentor-list {
    grid-template-columns: 1fr;
  }

  .resource-grid {
    grid-template-columns: 1fr;
  }
}
</style>
</head>
<body>
<!-- Mobile Menu Button -->
<button class="mobile-menu-btn" id="mobileMenuBtn">☰</button>

<div class="container">
<!-- Sidebar -->
<div class="sidebar" id="sidebar">
  <div class="sidebar-header">
    <div class="student-avatar"><?php echo substr($first_name, 0, 1); ?></div>
    <h3 class="student-name"><?php echo $first_name . " " . $last_name; ?></h3>
    <p class="student-title"><?php echo !empty($skills) ? explode(",", $skills)[0] . " Student" : "Student"; ?></p>
    <form action="logout.php" method="post">
      <button type="submit" class="logout-btn" style="margin-top: 10px; background-color: #e74c3c; color: white; border: none; padding: 5px 10px; border-radius: 4px; cursor: pointer;">Logout</button>
    </form>
  </div>
  <div class="sidebar-menu">
    <div class="menu-item active" data-section="dashboard">
      <span class="menu-icon">📊</span> Dashboard
    </div>
    <div class="menu-item" data-section="find-mentors">
      <span class="menu-icon">🔍</span> Find Mentors
    </div>
    <div class="menu-item" data-section="upcoming-sessions">
      <span class="menu-icon">📅</span> Upcoming Sessions
    </div>
    <div class="menu-item" data-section="session-history">
      <span class="menu-icon">📚</span> Session History
    </div>
    <div class="menu-item" data-section="profile">
      <span class="menu-icon">👤</span> My Profile
    </div>
    <div class="menu-item" data-section="change-password">
      <span class="menu-icon">🔒</span> Change Password
    </div>
  </div>
</div>

<!-- Main Content -->
<div class="main-content">
  <!-- Header -->
  <div class="header">
    <h1 class="page-title" id="page-title">Dashboard</h1>
  </div>

  <?php if(isset($profile_updated) && $profile_updated): ?>
  <div class="alert alert-success">Your profile has been updated successfully.</div>
  <?php endif; ?>

  <?php if(isset($password_updated) && $password_updated): ?>
  <div class="alert alert-success">Your password has been updated successfully.</div>
  <?php endif; ?>

  <?php if(isset($session_booked) && $session_booked): ?>
  <div class="alert alert-success">Your session has been booked successfully. The mentor will review your request.</div>
  <?php endif; ?>

  <?php if(isset($session_cancelled) && $session_cancelled): ?>
  <div class="alert alert-success">Your session has been cancelled successfully.</div>
  <?php endif; ?>

  <?php if(isset($feedback_submitted) && $feedback_submitted): ?>
  <div class="alert alert-success">Your feedback has been submitted successfully.</div>
  <?php endif; ?>

  <?php if(isset($booking_error)): ?>
  <div class="alert alert-danger"><?php echo $booking_error; ?></div>
  <?php endif; ?>

  <?php if(isset($password_update_error)): ?>
  <div class="alert alert-danger"><?php echo $password_update_error; ?></div>
  <?php endif; ?>

  <?php if(isset($cancellation_error)): ?>
  <div class="alert alert-danger"><?php echo $cancellation_error; ?></div>
  <?php endif; ?>

  <?php if(isset($feedback_error)): ?>
  <div class="alert alert-danger"><?php echo $feedback_error; ?></div>
  <?php endif; ?>

  <!-- Dashboard Section -->
  <div id="dashboard-section" class="content-section active">
    <div class="stats-container">
      <div class="stat-card">
        <div class="stat-title">Completed Sessions</div>
        <div class="stat-value"><?php echo isset($stats['completed_sessions']) ? $stats['completed_sessions'] : 0; ?></div>
        <div class="stat-description">MentorConnect sessions</div>
      </div>
      <div class="stat-card">
        <div class="stat-title">Upcoming Sessions</div>
        <div class="stat-value"><?php echo isset($stats['upcoming_sessions']) ? $stats['upcoming_sessions'] : 0; ?></div>
        <div class="stat-description">Scheduled sessions</div>
      </div>
      <div class="stat-card">
        <div class="stat-title">Skills Developing</div>
        <div class="stat-value"><?php echo !empty($skills) ? count(explode(",", $skills)) : 0; ?></div>
        <div class="stat-description">Currently in progress</div>
      </div>
      <div class="stat-card">
        <div class="stat-title">Experience</div>
        <div class="stat-value"><?php echo isset($stats['completed_sessions']) ? $stats['completed_sessions'] + 1 : 1; ?></div>
        <div class="stat-description">Mentoring experience</div>
      </div>
    </div>

    <h2 class="section-title">Your Upcoming Sessions</h2>
    <div class="session-list">
      <?php if(empty($upcoming_sessions)): ?>
        <div class="session-item">
          <p>You have no upcoming sessions. <a href="#" class="menu-link" data-section="find-mentors">Find a mentor</a> to book a session.</p>
        </div>
      <?php else: ?>
        <?php foreach($upcoming_sessions as $session): ?>
          <div class="session-item">
            <div class="mentor-profile">
              <div class="mentor-mini-avatar"><?php echo substr($session['first_name'], 0, 1) . substr($session['last_name'], 0, 1); ?></div>
              <div class="mentor-details">
                <h4><?php echo $session['first_name'] . " " . $session['last_name']; ?></h4>
                <p><?php echo $session['topic']; ?> • <?php echo date('F j, Y', strtotime($session['session_date'])); ?>, <?php echo date('g:i A', strtotime($session['start_time'])); ?></p>
              </div>
            </div>
            <div class="session-actions">
              <?php if(date('Y-m-d') == $session['session_date'] && (strtotime($session['start_time']) - time() < 3600) && (strtotime($session['start_time']) - time() > 0)): ?>
                <a href="session-chat-no-ajax.php?session_id=<?php echo $session['session_id']; ?>" class="join-btn" style="text-decoration: none; display: inline-block; text-align: center; background-color: #2ecc71; color: white; border: none; border-radius: 4px; padding: 8px 15px; cursor: pointer; font-size: 14px;">Join Session</a>
              <?php else: ?>
                <button class="join-btn" disabled>Join Session</button>
              <?php endif; ?>
              <form method="post" style="display: inline;">
                <input type="hidden" name="session_id" value="<?php echo $session['session_id']; ?>">
                <button type="submit" name="cancel_session" class="cancel-btn" onclick="return confirm('Are you sure you want to cancel this session?')">Cancel</button>
              </form>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <h2 class="section-title" style="margin-top: 30px;">Recent Activity</h2>
    <div class="session-list">
      <?php if(empty($recent_activity)): ?>
        <div class="session-item">
          <p>You have no recent activity.</p>
        </div>
      <?php else: ?>
        <?php foreach($recent_activity as $activity): ?>
          <div class="session-item">
            <div class="mentor-profile">
              <div class="mentor-mini-avatar"><?php echo substr($activity['first_name'], 0, 1) . substr($activity['last_name'], 0, 1); ?></div>
              <div class="mentor-details">
                <h4><?php echo $activity['first_name'] . " " . $activity['last_name']; ?></h4>
                <p><?php echo $activity['topic']; ?> • <?php echo date('F j, Y', strtotime($activity['session_date'])); ?></p>
              </div>
            </div>
            <div class="session-actions">
              <?php if($activity['has_feedback'] == 0): ?>
                <button class="feedback-btn" data-session-id="<?php echo $activity['session_id']; ?>" data-mentor-name="<?php echo $activity['first_name'] . ' ' . $activity['last_name']; ?>">Give Feedback</button>
              <?php else: ?>
                <button class="feedback-btn" disabled>Feedback Submitted</button>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <!-- Find Mentors Section -->
  <div id="find-mentors-section" class="content-section">
    <h2 class="section-title">Find a Mentor</h2>
    
    <!-- Search Form -->
    <div class="search-form">
      <form id="mentorSearchForm">
        <div class="search-row">
          <div class="search-group">
            <label for="expertise">Search by Expertise or Name</label>
            <input type="text" id="expertise" name="expertise" placeholder="Enter mentor name or expertise">
          </div>
        </div>
        <button type="submit" class="search-btn" style="margin-top: 15px;">Find Mentor</button>
      </form>
    </div>
    
    <!-- Mentor List -->
    <div class="mentor-list">
      <?php if(empty($mentors)): ?>
        <p>No mentors found. Please try a different search.</p>
      <?php else: ?>
        <?php foreach($mentors as $mentor): ?>
          <div class="mentor-card">
            <div class="mentor-header">
              <div class="mentor-avatar"><?php echo substr($mentor['first_name'], 0, 1) . substr($mentor['last_name'], 0, 1); ?></div>
              <div class="mentor-info">
                <h3><?php echo $mentor['first_name'] . " " . $mentor['last_name']; ?></h3>
                <p><?php echo !empty($mentor['expertise']) ? explode(",", $mentor['expertise'])[0] . " Mentor" : "Mentor"; ?></p>
              </div>
            </div>
            <div class="mentor-body">
              <p><strong>Expertise:</strong> <?php echo $mentor['expertise']; ?></p>
              <p><strong>Experience:</strong> <?php echo $mentor['experience_years']; ?> years</p>
              <p><?php echo substr($mentor['bio'], 0, 150) . (strlen($mentor['bio']) > 150 ? '...' : ''); ?></p>
            </div>
            <div class="mentor-footer">
              <div class="mentor-rating">
                <?php 
                  $rating = round($mentor['average_rating']);
                  for($i = 1; $i <= 5; $i++) {
                    echo $i <= $rating ? "★" : "☆";
                  }
                  echo " (" . number_format($mentor['average_rating'], 1) . ")";
                ?>
              </div>
              <button class="book-btn" data-mentor-id="<?php echo $mentor['user_id']; ?>" data-mentor="<?php echo $mentor['first_name'] . ' ' . $mentor['last_name']; ?>" data-expertise="<?php echo explode(",", $mentor['expertise'])[0]; ?>">Book Session</button>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <!-- Upcoming Sessions Section -->
  <div id="upcoming-sessions-section" class="content-section">
    <h2 class="section-title">Upcoming Sessions</h2>
    <div class="session-list">
      <?php if(empty($upcoming_sessions)): ?>
        <div class="session-item">
          <p>You have no upcoming sessions. <a href="#" class="menu-link" data-section="find-mentors">Find a mentor</a> to book a session.</p>
        </div>
      <?php else: ?>
        <?php foreach($upcoming_sessions as $session): ?>
          <div class="session-item">
            <div class="mentor-profile">
              <div class="mentor-mini-avatar"><?php echo substr($session['first_name'], 0, 1) . substr($session['last_name'], 0, 1); ?></div>
              <div class="mentor-details">
                <h4><?php echo $session['first_name'] . " " . $session['last_name']; ?></h4>
                <p><?php echo $session['topic']; ?> • <?php echo date('F j, Y', strtotime($session['session_date'])); ?>, <?php echo date('g:i A', strtotime($session['start_time'])); ?></p>
              </div>
            </div>
            <div class="session-actions">
              <?php if(date('Y-m-d') == $session['session_date'] && (strtotime($session['start_time']) - time() < 3600) && (strtotime($session['start_time']) - time() > 0)): ?>
                <a href="session-chat-no-ajax.php?session_id=<?php echo $session['session_id']; ?>" class="join-btn" style="text-decoration: none; display: inline-block; text-align: center; background-color: #2ecc71; color: white; border: none; border-radius: 4px; padding: 8px 15px; cursor: pointer; font-size: 14px;">Join Session</a>
              <?php else: ?>
                <button class="join-btn" disabled>Join Session</button>
              <?php endif; ?>
              <form method="post" style="display: inline;">
                <input type="hidden" name="session_id" value="<?php echo $session['session_id']; ?>">
                <button type="submit" name="cancel_session" class="cancel-btn" onclick="return confirm('Are you sure you want to cancel this session?')">Cancel</button>
              </form>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <!-- Session History Section -->
  <div id="session-history-section" class="content-section">
    <h2 class="section-title">Session History</h2>
    <div class="session-list">
      <?php if(empty($session_history)): ?>
        <div class="session-item">
          <p>You have no session history yet.</p>
        </div>
      <?php else: ?>
        <?php foreach($session_history as $session): ?>
          <div class="session-item">
            <div class="mentor-profile">
              <div class="mentor-mini-avatar"><?php echo substr($session['first_name'], 0, 1) . substr($session['last_name'], 0, 1); ?></div>
              <div class="mentor-details">
                <h4><?php echo $session['first_name'] . " " . $session['last_name']; ?></h4>
                <p><?php echo $session['topic']; ?> • <?php echo date('F j, Y', strtotime($session['session_date'])); ?></p>
              </div>
            </div>
            <div class="session-actions">
              <?php if($session['has_feedback'] == 0): ?>
                <button class="feedback-btn" data-session-id="<?php echo $session['session_id']; ?>" data-mentor-name="<?php echo $session['first_name'] . ' ' . $session['last_name']; ?>">Give Feedback</button>
              <?php else: ?>
                <button class="feedback-btn" disabled>Feedback Submitted</button>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <!-- Profile Section -->
  <div id="profile-section" class="content-section">
    <h2 class="section-title">My Profile</h2>
    <div class="profile-form">
      <form method="post">
        <div class="form-group">
          <label for="fullName">Full Name</label>
          <input type="text" id="fullName" name="fullName" value="<?php echo $first_name . ' ' . $last_name; ?>">
        </div>
        <div class="form-group">
          <label for="email">Email</label>
          <input type="email" id="email" name="email" value="<?php echo $email; ?>">
        </div>
        <div class="form-group">
          <label for="phone">Phone</label>
          <input type="tel" id="phone" name="phone" value="<?php echo $phone; ?>">
        </div>
        <div class="form-group">
          <label for="career_goals">Career Goals</label>
          <textarea id="career_goals" name="career_goals"><?php echo $career_goals; ?></textarea>
        </div>
        <div class="form-group">
          <label for="skills">Current Skills</label>
          <textarea id="skills" name="skills"><?php echo $skills; ?></textarea>
        </div>
        <div class="form-group">
          <label for="education">Education</label>
          <textarea id="education" name="education"><?php echo $education; ?></textarea>
        </div>
        <div class="form-group">
          <label for="interests">Interests</label>
          <input type="text" id="interests" name="interests" value="<?php echo $interests; ?>">
        </div>
        <button type="submit" name="update_profile" class="submit-btn">Update Profile</button>
      </form>
    </div>
  </div>

  <!-- Change Password Section -->
  <div id="change-password-section" class="content-section">
    <h2 class="section-title">Change Password</h2>
    <div class="profile-form">
      <form method="post">
        <div class="form-group <?php echo isset($current_password_error) ? 'has-error' : ''; ?>">
          <label for="current_password">Current Password</label>
          <input type="password" id="current_password" name="current_password" required>
          <?php if(isset($current_password_error)): ?>
            <div class="error-message" style="color: #e74c3c; font-size: 14px; margin-top: 5px;"><?php echo $current_password_error; ?></div>
          <?php endif; ?>
        </div>
        <div class="form-group <?php echo isset($new_password_error) ? 'has-error' : ''; ?>">
          <label for="new_password">New Password</label>
          <input type="password" id="new_password" name="new_password" required>
          <?php if(isset($new_password_error)): ?>
            <div class="error-message" style="color: #e74c3c; font-size: 14px; margin-top: 5px;"><?php echo $new_password_error; ?></div>
          <?php endif; ?>
        </div>
        <div class="form-group <?php echo isset($confirm_password_error) ? 'has-error' : ''; ?>">
          <label for="confirm_password">Confirm New Password</label>
          <input type="password" id="confirm_password" name="confirm_password" required>
          <?php if(isset($confirm_password_error)): ?>
            <div class="error-message" style="color: #e74c3c; font-size: 14px; margin-top: 5px;"><?php echo $confirm_password_error; ?></div>
          <?php endif; ?>
        </div>
        <button type="submit" name="change_password" class="submit-btn">Update Password</button>
      </form>
    </div>
  </div>
</div>
</div>

<!-- Booking Modal -->
<div id="bookingModal" class="modal">
<div class="modal-content">
  <span class="close-btn" id="closeModalBtn">&times;</span>
  <h2 class="modal-title">Book a Session with <span id="mentorName">Mentor</span></h2>
  <p style="margin-bottom: 15px;">Topic: <span id="sessionTopic">Web Development</span></p>
  
  <form method="post">
    <input type="hidden" id="mentor_id" name="mentor_id">
    <input type="hidden" id="topic" name="topic">
    
    <div class="calendar">
      <div class="calendar-header">
        <div class="calendar-title" id="calendarMonth">March 2025</div>
        <div class="calendar-nav">
          <button type="button" id="prevMonth">&lt;</button>
          <button type="button" id="nextMonth">&gt;</button>
        </div>
      </div>
      <div class="weekdays">
        <div class="weekday">Sun</div>
        <div class="weekday">Mon</div>
        <div class="weekday">Tue</div>
        <div class="weekday">Wed</div>
        <div class="weekday">Thu</div>
        <div class="weekday">Fri</div>
        <div class="weekday">Sat</div>
      </div>
      <div class="calendar-days" id="bookingCalendarDays">
        <!-- Calendar days will be filled by JavaScript -->
      </div>
    </div>
    
    <input type="hidden" id="session_date" name="session_date">
    
    <div class="time-slots">
      <h3>Available Time Slots</h3>
      <div id="bookingTimeSlots">
        <!-- Time slots will be filled by JavaScript -->
      </div>
    </div>
    
    <input type="hidden" id="session_time" name="session_time">
    
    <div class="form-group">
      <label for="notes">Notes for the mentor (optional)</label>
      <textarea id="notes" name="notes" placeholder="Describe what you'd like to discuss in this session..."></textarea>
    </div>
    
    <button type="submit" name="book_session" class="submit-btn">Confirm Booking</button>
  </form>
</div>
</div>

<!-- Feedback Modal -->
<div id="feedbackModal" class="modal">
<div class="modal-content">
  <span class="close-btn" id="closeFeedbackBtn">&times;</span>
  <h2 class="modal-title">Provide Feedback for <span id="feedbackMentorName">Mentor</span></h2>
  
  <div class="feedback-form">
    <form method="post">
      <input type="hidden" id="feedback_session_id" name="session_id">
      
      <div class="form-group">
        <label>Rating</label>
        <div class="rating">
          <span class="star" data-rating="1">★</span>
          <span class="star" data-rating="2">★</span>
          <span class="star" data-rating="3">★</span>
          <span class="star" data-rating="4">★</span>
          <span class="star" data-rating="5">★</span>
        </div>
        <input type="hidden" id="rating" name="rating" value="5">
      </div>
      <div class="form-group">
        <label for="comments">Comments</label>
        <textarea id="comments" name="comments" placeholder="Share your thoughts about this mentorship session..."></textarea>
      </div>
      <div class="form-group">
        <label for="most_valuable">What was most valuable about this session?</label>
        <input type="text" id="most_valuable" name="most_valuable" placeholder="The most useful aspect of this session was...">
      </div>
      <div class="form-group">
        <label for="improvement">What could be improved?</label>
        <input type="text" id="improvement" name="improvement" placeholder="Something that could have been better...">
      </div>
      <div class="form-group">
        <label for="future_topics">Topics for future sessions</label>
        <input type="text" id="future_topics" name="future_topics" placeholder="What would you like to learn next?">
      </div>
      <button type="submit" name="submit_feedback" class="submit-btn">Submit Feedback</button>
    </form>
  </div>
</div>
</div>

<script>
// Mobile Menu Toggle
const mobileMenuBtn = document.getElementById('mobileMenuBtn');
const sidebar = document.getElementById('sidebar');

mobileMenuBtn.addEventListener('click', function() {
  sidebar.classList.toggle('active');
});

// Navigation
const menuItems = document.querySelectorAll('.menu-item');
const menuLinks = document.querySelectorAll('.menu-link');
const contentSections = document.querySelectorAll('.content-section');
const pageTitle = document.getElementById('page-title');

menuItems.forEach(item => {
  item.addEventListener('click', function() {
    // Update active menu item
    menuItems.forEach(menuItem => menuItem.classList.remove('active'));
    this.classList.add('active');
    
    // Show corresponding section
    const sectionId = this.getAttribute('data-section');
    contentSections.forEach(section => section.classList.remove('active'));
    document.getElementById(sectionId + '-section').classList.add('active');
    
    // Update page title
    pageTitle.textContent = this.textContent.trim();
    
    // Close mobile menu
    sidebar.classList.remove('active');
  });
});

menuLinks.forEach(link => {
  link.addEventListener('click', function(e) {
    e.preventDefault();
    
    // Get the section to show
    const sectionId = this.getAttribute('data-section');
    
    // Find the corresponding menu item and click it
    menuItems.forEach(item => {
      if(item.getAttribute('data-section') === sectionId) {
        item.click();
      }
    });
  });
});

// Filter Tags
const filterTags = document.querySelectorAll('.filter-tag');

filterTags.forEach(tag => {
  tag.addEventListener('click', function() {
    this.classList.toggle('active');
  });
});

// Booking Modal
const bookBtns = document.querySelectorAll('.book-btn');
const bookingModal = document.getElementById('bookingModal');
const closeModalBtn = document.getElementById('closeModalBtn');
const mentorNameSpan = document.getElementById('mentorName');
const sessionTopicSpan = document.getElementById('sessionTopic');
const mentorIdInput = document.getElementById('mentor_id');
const topicInput = document.getElementById('topic');

bookBtns.forEach(btn => {
  btn.addEventListener('click', function() {
    const mentorId = this.getAttribute('data-mentor-id');
    const mentorName = this.getAttribute('data-mentor');
    const expertise = this.getAttribute('data-expertise');
    
    mentorNameSpan.textContent = mentorName;
    sessionTopicSpan.textContent = expertise;
    mentorIdInput.value = mentorId;
    topicInput.value = expertise;
    
    bookingModal.style.display = 'block';
    generateBookingCalendar();
  });
});

closeModalBtn.addEventListener('click', function() {
  bookingModal.style.display = 'none';
});

// Feedback Modal
const feedbackBtns = document.querySelectorAll('.feedback-btn');
const feedbackModal = document.getElementById('feedbackModal');
const closeFeedbackBtn = document.getElementById('closeFeedbackBtn');
const feedbackMentorNameSpan = document.getElementById('feedbackMentorName');
const feedbackSessionIdInput = document.getElementById('feedback_session_id');

feedbackBtns.forEach(btn => {
  if (!btn.disabled) {
    btn.addEventListener('click', function() {
      const sessionId = this.getAttribute('data-session-id');
      const mentorName = this.getAttribute('data-mentor-name');
      
      feedbackMentorNameSpan.textContent = mentorName;
      feedbackSessionIdInput.value = sessionId;
      feedbackModal.style.display = 'block';
    });
  }
});

closeFeedbackBtn.addEventListener('click', function() {
  feedbackModal.style.display = 'none';
});

// Star Rating
const stars = document.querySelectorAll('.star');
const ratingInput = document.getElementById('rating');

// Initialize stars (all selected by default for 5-star rating)
stars.forEach(s => s.classList.add('selected'));

stars.forEach((star) => {
  // Add hover effect
  star.addEventListener('mouseover', function() {
    const rating = parseInt(this.getAttribute('data-rating'));
    
    // Reset all stars
    stars.forEach(s => s.classList.remove('selected'));
    
    // Select hovered star and all previous stars
    for (let i = 0; i < rating; i++) {
      stars[i].classList.add('selected');
    }
  });
  
  // Handle click
  star.addEventListener('click', function() {
    const rating = parseInt(this.getAttribute('data-rating'));
    ratingInput.value = rating;
    
    // Reset all stars
    stars.forEach(s => s.classList.remove('selected'));
    
    // Select clicked star and all previous stars
    for (let i = 0; i < rating; i++) {
      stars[i].classList.add('selected');
    }
  });
});

// Add mouseleave handler to the rating container to restore selected rating
document.querySelector('.rating').addEventListener('mouseleave', function() {
  const rating = parseInt(ratingInput.value);
  
  // Reset all stars
  stars.forEach(s => s.classList.remove('selected'));
  
  // Restore selected rating
  for (let i = 0; i < rating; i++) {
    stars[i].classList.add('selected');
  }
});

// Mentor search functionality
document.getElementById('mentorSearchForm').addEventListener('submit', function(e) {
  e.preventDefault();
  const searchTerm = document.getElementById('expertise').value.toLowerCase();
  const mentorCards = document.querySelectorAll('.mentor-card');
  
  mentorCards.forEach(card => {
    const mentorName = card.querySelector('.mentor-info h3').textContent.toLowerCase();
    const expertise = card.querySelector('.mentor-body p:first-child').textContent.toLowerCase();
    
    if (mentorName.includes(searchTerm) || expertise.includes(searchTerm) || searchTerm === '') {
      card.style.display = 'block';
    } else {
      card.style.display = 'none';
    }
  });
});

// Calendar variables
let currentMonth = new Date().getMonth();
let currentYear = new Date().getFullYear();

// Generate Booking Calendar
function generateBookingCalendar() {
  const calendarDays = document.getElementById('bookingCalendarDays');
  const calendarMonth = document.getElementById('calendarMonth');
  const sessionDateInput = document.getElementById('session_date');
  calendarDays.innerHTML = '';
  
  // Set calendar title
  calendarMonth.textContent = new Date(currentYear, currentMonth).toLocaleString('default', { month: 'long', year: 'numeric' });
  
  // Get first day of month and number of days
  const firstDay = new Date(currentYear, currentMonth, 1).getDay();
  const daysInMonth = new Date(currentYear, currentMonth + 1, 0).getDate();
  
  // Add empty cells for days before first day of month
  for (let i = 0; i < firstDay; i++) {
    const emptyDay = document.createElement('div');
    emptyDay.className = 'calendar-day';
    calendarDays.appendChild(emptyDay);
  }
  
  // Get current date for comparison
  const now = new Date();
  const currentDate = new Date(now.getFullYear(), now.getMonth(), now.getDate());
  
  // Add days of month
  for (let i = 1; i <= daysInMonth; i++) {
    const dayElement = document.createElement('div');
    dayElement.className = 'calendar-day';
    dayElement.textContent = i;
    
    // Check if day is in the past
    const dayDate = new Date(currentYear, currentMonth, i);
    if (dayDate < currentDate) {
      dayElement.classList.add('unavailable');
    } else {
      dayElement.addEventListener('click', function() {
        if (!this.classList.contains('unavailable')) {
          // Clear any previously selected day
          document.querySelectorAll('.calendar-day').forEach(day => {
            day.classList.remove('selected');
          });
          
          // Select the clicked day
          this.classList.add('selected');
          
          // Set the selected date
          const selectedDate = new Date(currentYear, currentMonth, i);
          const formattedDate = selectedDate.toISOString().split('T')[0];
          sessionDateInput.value = formattedDate;
          
          // Update time slots based on selected day
          updateTimeSlots(i);
        }
      });
    }
    
    calendarDays.appendChild(dayElement);
  }
}

// Update time slots based on selected day
function updateTimeSlots(day) {
  const timeSlots = document.getElementById('bookingTimeSlots');
  const sessionTimeInput = document.getElementById('session_time');
  timeSlots.innerHTML = '';
  
  const times = [
    '09:00:00', '10:00:00', '11:00:00', '12:00:00', 
    '13:00:00', '14:00:00', '15:00:00', '16:00:00', '17:00:00'
  ];
  
  const displayTimes = [
    '9:00 AM', '10:00 AM', '11:00 AM', '12:00 PM', 
    '1:00 PM', '2:00 PM', '3:00 PM', '4:00 PM', '5:00 PM'
  ];
  
  // Randomly mark some slots as unavailable
  const unavailableSlots = [];
  for (let i = 0; i < 3; i++) {
    const randomIndex = Math.floor(Math.random() * times.length);
    if (!unavailableSlots.includes(randomIndex)) {
      unavailableSlots.push(randomIndex);
    }
  }
  
  times.forEach((time, index) => {
    const timeSlot = document.createElement('div');
    timeSlot.className = 'time-slot';
    timeSlot.textContent = displayTimes[index];
    timeSlot.setAttribute('data-time', time);
    
    if (unavailableSlots.includes(index)) {
      timeSlot.classList.add('unavailable');
    } else {
      timeSlot.addEventListener('click', function() {
        if (!this.classList.contains('unavailable')) {
          document.querySelectorAll('.time-slot').forEach(slot => {
            slot.classList.remove('selected');
          });
          this.classList.add('selected');
          sessionTimeInput.value = this.getAttribute('data-time');
        }
      });
    }
    
    timeSlots.appendChild(timeSlot);
  });
}

// Calendar navigation
const prevMonthBtn = document.getElementById('prevMonth');
const nextMonthBtn = document.getElementById('nextMonth');

prevMonthBtn.addEventListener('click', function() {
  currentMonth--;
  if (currentMonth < 0) {
    currentMonth = 11;
    currentYear--;
  }
  generateBookingCalendar();
});

nextMonthBtn.addEventListener('click', function() {
  currentMonth++;
  if (currentMonth > 11) {
    currentMonth = 0;
    currentYear++;
  }
  generateBookingCalendar();
});

// Close modals when clicking outside
window.addEventListener('click', function(event) {
  if (event.target === bookingModal) {
    bookingModal.style.display = 'none';
  }
  if (event.target === feedbackModal) {
    feedbackModal.style.display = 'none';
  }
});
</script>
</body>
</html>

