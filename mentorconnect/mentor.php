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

// Get mentor information
$mentor_id = $_SESSION["user_id"];
$first_name = $_SESSION["first_name"];
$last_name = $_SESSION["last_name"];
$email = $_SESSION["email"];

// Get mentor profile details
$expertise = $experience_years = $bio = $phone = "";
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
        // Check if these columns exist in your table
        $phone = isset($row["phone"]) ? $row["phone"] : "";
    }
    $stmt->close();
}

// Get dashboard statistics
$stats = array();

// Completed sessions
$sql = "SELECT COUNT(*) as count FROM sessions WHERE mentor_id = ? AND status = 'completed'";
if($stmt = $conn->prepare($sql)){
    $stmt->bind_param("i", $mentor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if($result->num_rows > 0){
        $row = $result->fetch_assoc();
        $stats['completed_sessions'] = $row['count'];
    }
    $stmt->close();
}

// Upcoming sessions
$sql = "SELECT COUNT(*) as count FROM sessions WHERE mentor_id = ? AND status = 'accepted' AND session_date >= CURDATE()";
if($stmt = $conn->prepare($sql)){
    $stmt->bind_param("i", $mentor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if($result->num_rows > 0){
        $row = $result->fetch_assoc();
        $stats['upcoming_sessions'] = $row['count'];
    }
    $stmt->close();
}

// Get pending session requests
$pending_requests = array();
$sql = "SELECT s.*, u.first_name, u.last_name 
        FROM sessions s 
        JOIN users u ON s.student_id = u.user_id 
        WHERE s.mentor_id = ? AND s.status = 'pending' 
        ORDER BY s.created_at DESC";
if($stmt = $conn->prepare($sql)){
    $stmt->bind_param("i", $mentor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while($row = $result->fetch_assoc()){
        $pending_requests[] = $row;
    }
    $stmt->close();
}

// Get upcoming sessions
$upcoming_sessions = array();
$sql = "SELECT s.*, u.first_name, u.last_name 
        FROM sessions s 
        JOIN users u ON s.student_id = u.user_id 
        WHERE s.mentor_id = ? AND s.status = 'accepted' AND s.session_date >= CURDATE() 
        ORDER BY s.session_date, s.start_time";
if($stmt = $conn->prepare($sql)){
    $stmt->bind_param("i", $mentor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while($row = $result->fetch_assoc()){
        $upcoming_sessions[] = $row;
    }
    $stmt->close();
}

// Get session history
$session_history = array();
$sql = "SELECT s.*, u.first_name, u.last_name, 
        (SELECT COUNT(*) FROM feedback WHERE session_id = s.session_id AND to_user_id = ?) as has_feedback
        FROM sessions s 
        JOIN users u ON s.student_id = u.user_id 
        WHERE s.mentor_id = ? AND s.status = 'completed' 
        ORDER BY s.session_date DESC";
if($stmt = $conn->prepare($sql)){
    $stmt->bind_param("ii", $mentor_id, $mentor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while($row = $result->fetch_assoc()){
        $session_history[] = $row;
    }
    $stmt->close();
}

// Get all students
$students = array();
$sql = "SELECT DISTINCT u.user_id, u.first_name, u.last_name, sp.skills 
        FROM users u 
        JOIN student_profiles sp ON u.user_id = sp.student_id 
        JOIN sessions s ON u.user_id = s.student_id 
        WHERE s.mentor_id = ? AND u.user_type = 'student'";
if($stmt = $conn->prepare($sql)){
    $stmt->bind_param("i", $mentor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while($row = $result->fetch_assoc()){
        $students[] = $row;
    }
    $stmt->close();
}

// Process profile update
if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["update_profile"])){
    // Validate and sanitize input
    $new_first_name = trim($_POST["fullName"]);
    $new_email = trim($_POST["email"]);
    $new_phone = trim($_POST["phone"]);
    $new_expertise = trim($_POST["expertise"]);
    $new_experience_years = trim($_POST["experience_years"]);
    $new_bio = trim($_POST["bio"]);
    
    // Update user table
    $sql = "UPDATE users SET first_name = ?, last_name = ?, email = ? WHERE user_id = ?";
    if($stmt = $conn->prepare($sql)){
        // Extract first and last name
        $name_parts = explode(" ", $new_first_name, 2);
        $first_name = $name_parts[0];
        $last_name = isset($name_parts[1]) ? $name_parts[1] : "";
        
        $stmt->bind_param("sssi", $first_name, $last_name, $new_email, $mentor_id);
        $stmt->execute();
        $stmt->close();
        
        // Update session variables
        $_SESSION["first_name"] = $first_name;
        $_SESSION["last_name"] = $last_name;
        $_SESSION["email"] = $new_email;
    }
    
    // Update mentor profile - removed availability field
    $sql = "UPDATE mentor_profiles SET expertise = ?, experience_years = ?, bio = ?, phone = ? WHERE mentor_id = ?";
    if($stmt = $conn->prepare($sql)){
        $stmt->bind_param("sissi", $new_expertise, $new_experience_years, $new_bio, $new_phone, $mentor_id);
        $stmt->execute();
        $stmt->close();
        
        // Update local variables
        $expertise = $new_expertise;
        $experience_years = $new_experience_years;
        $bio = $new_bio;
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
        $stmt->bind_param("i", $mentor_id);
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
            $stmt->bind_param("si", $hashed_password, $mentor_id);
            
            if($stmt->execute()){
                $password_updated = true;
            } else {
                $password_update_error = "Something went wrong. Please try again.";
            }
            $stmt->close();
        }
    }
}

// Process session request response
if($_SERVER["REQUEST_METHOD"] == "POST" && (isset($_POST["accept_session"]) || isset($_POST["decline_session"]))){
    $session_id = $_POST["session_id"];
    $status = isset($_POST["accept_session"]) ? "accepted" : "declined";
    
    // Update session status
    $sql = "UPDATE sessions SET status = ? WHERE session_id = ? AND mentor_id = ?";
    if($stmt = $conn->prepare($sql)){
        $stmt->bind_param("sii", $status, $session_id, $mentor_id);
        
        if($stmt->execute()){
            $session_updated = true;
        } else {
            $update_error = "Something went wrong. Please try again.";
        }
        $stmt->close();
    }
}

// Process session cancellation
if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["cancel_session"])){
    $session_id = $_POST["session_id"];
    
    // Update session status
    $sql = "UPDATE sessions SET status = 'cancelled' WHERE session_id = ? AND mentor_id = ?";
    if($stmt = $conn->prepare($sql)){
        $stmt->bind_param("ii", $session_id, $mentor_id);
        
        if($stmt->execute()){
            $session_cancelled = true;
        } else {
            $cancellation_error = "Something went wrong. Please try again.";
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
<title>Mentor Dashboard - MentorConnect</title>
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
}

:root {
    --primary-color: #3498db;
    --primary-dark: #2980b9;
    --secondary-color: #2c3e50;
    --success-color: #2ecc71;
    --warning-color: #f39c12;
    --danger-color: #e74c3c;
    --light-color: #ecf0f1;
    --dark-color: #34495e;
    --text-color: #333;
    --text-muted: #7f8c8d;
    --border-color: #ddd;
    --border-radius: 8px;
    --box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
}

/* Main Layout */
.container {
    display: flex;
    min-height: 100vh;
}

/* Sidebar */
.sidebar {
    width: 250px;
    background-color: var(--secondary-color);
    color: white;
    height: 100vh;
    position: fixed;
    z-index: 100;
}

.sidebar-header {
    padding: 20px;
    text-align: center;
    border-bottom: 1px solid var(--dark-color);
}

.mentor-avatar {
    width: 80px;
    height: 80px;
    background-color: var(--primary-color);
    border-radius: 50%;
    margin: 0 auto 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 36px;
    color: white;
}

.mentor-name {
    font-size: 18px;
    margin-bottom: 5px;
}

.mentor-title {
    font-size: 14px;
    color: var(--light-color);
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
    background-color: rgba(255, 255, 255, 0.1);
    border-left-color: var(--primary-color);
}

.menu-item.active {
    background-color: rgba(255, 255, 255, 0.1);
    border-left-color: var(--primary-color);
    color: white;
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

.student-profile {
    display: flex;
    align-items: center;
}

.student-mini-avatar {
    width: 40px;
    height: 40px;
    background-color: var(--primary-color);
    border-radius: 50%;
    margin-right: 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: bold;
}

.student-details h4 {
    font-size: 16px;
    margin-bottom: 5px;
}

.student-details p {
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

.accept-btn {
    background-color: var(--success-color);
    color: white;
}

.decline-btn {
    background-color: var(--danger-color);
    color: white;
}

.join-btn {
    background-color: var(--primary-color);
    color: white;
}

.cancel-btn {
    background-color: var(--danger-color);
    color: white;
}

.view-feedback-btn {
    background-color: var(--warning-color);
    color: white;
}

/* Student List */
.student-list {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 20px;
}

.student-card {
    background-color: white;
    border-radius: 8px;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
    overflow: hidden;
}

.student-header {
    display: flex;
    padding: 15px;
    border-bottom: 1px solid #eee;
}

.student-avatar {
    width: 60px;
    height: 60px;
    background-color: var(--primary-color);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: bold;
    font-size: 24px;
    margin-right: 15px;
}

.student-info h3 {
    font-size: 18px;
    margin-bottom: 5px;
}

.student-info p {
    font-size: 14px;
    color: #7f8c8d;
}

.student-body {
    padding: 15px;
}

.student-body p {
    margin-bottom: 10px;
    font-size: 14px;
}

.student-footer {
    padding: 15px;
    border-top: 1px solid #eee;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.message-btn {
    background-color: var(--primary-color);
    color: white;
    border: none;
    border-radius: 4px;
    padding: 8px 15px;
    cursor: pointer;
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
    background-color: var(--primary-color);
    color: white;
    border: none;
    border-radius: 4px;
    padding: 10px 20px;
    cursor: pointer;
    font-size: 16px;
}

/* Feedback Modal */
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
    margin: 10% auto;
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

.feedback-details {
    margin-bottom: 20px;
}

.feedback-details h3 {
    font-size: 16px;
    margin-bottom: 5px;
}

.feedback-details p {
    margin-bottom: 10px;
}

.rating {
    color: var(--warning-color);
    font-size: 20px;
    margin-bottom: 10px;
}

/* Mobile Menu Button */
.mobile-menu-btn {
    display: none;
    position: fixed;
    top: 10px;
    left: 10px;
    z-index: 200;
    background-color: var(--primary-color);
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

    .student-list {
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
            <div class="mentor-avatar"><?php echo substr($first_name, 0, 1); ?></div>
            <h3 class="mentor-name"><?php echo $first_name . " " . $last_name; ?></h3>
            <p class="mentor-title"><?php echo !empty($expertise) ? explode(",", $expertise)[0] . " Mentor" : "Mentor"; ?></p>
            <form action="logout.php" method="post">
                <button type="submit" class="logout-btn" style="margin-top: 10px; background-color: #e74c3c; color: white; border: none; padding: 5px 10px; border-radius: 4px; cursor: pointer;">Logout</button>
            </form>
        </div>
        <div class="sidebar-menu">
            <div class="menu-item active" data-section="dashboard">
                <span class="menu-icon">📊</span> Dashboard
            </div>
            <div class="menu-item" data-section="session-requests">
                <span class="menu-icon">📩</span> Session Requests
                <?php if(count($pending_requests) > 0): ?>
                    <span style="margin-left: auto; background-color: #e74c3c; color: white; border-radius: 50%; width: 20px; height: 20px; display: flex; align-items: center; justify-content: center; font-size: 12px;"><?php echo count($pending_requests); ?></span>
                <?php endif; ?>
            </div>
            <div class="menu-item" data-section="upcoming-sessions">
                <span class="menu-icon">📅</span> Upcoming Sessions
            </div>
            <div class="menu-item" data-section="session-history">
                <span class="menu-icon">📚</span> Session History
            </div>
            <div class="menu-item" data-section="students">
                <span class="menu-icon">👨‍🎓</span> My Students
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

        <?php if(isset($session_updated) && $session_updated): ?>
            <div class="alert alert-success">Session request has been updated successfully.</div>
        <?php endif; ?>

        <?php if(isset($session_cancelled) && $session_cancelled): ?>
            <div class="alert alert-success">Session has been cancelled successfully.</div>
        <?php endif; ?>

        <?php if(isset($update_error)): ?>
            <div class="alert alert-danger"><?php echo $update_error; ?></div>
        <?php endif; ?>

        <?php if(isset($password_update_error)): ?>
            <div class="alert alert-danger"><?php echo $password_update_error; ?></div>
        <?php endif; ?>

        <?php if(isset($cancellation_error)): ?>
            <div class="alert alert-danger"><?php echo $cancellation_error; ?></div>
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
                    <div class="stat-title">Students</div>
                    <div class="stat-value"><?php echo count($students); ?></div>
                    <div class="stat-description">Mentored students</div>
                </div>
                <div class="stat-card">
                    <div class="stat-title">Pending Requests</div>
                    <div class="stat-value"><?php echo count($pending_requests); ?></div>
                    <div class="stat-description">Session requests</div>
                </div>
            </div>

            <h2 class="section-title">Pending Session Requests</h2>
            <div class="session-list">
                <?php if(empty($pending_requests)): ?>
                    <div class="session-item">
                        <p>You have no pending session requests.</p>
                    </div>
                <?php else: ?>
                    <?php foreach($pending_requests as $request): ?>
                        <div class="session-item">
                            <div class="student-profile">
                                <div class="student-mini-avatar"><?php echo substr($request['first_name'], 0, 1) . substr($request['last_name'], 0, 1); ?></div>
                                <div class="student-details">
                                    <h4><?php echo $request['first_name'] . " " . $request['last_name']; ?></h4>
                                    <p><?php echo $request['first_name'] . " " . $request['last_name']; ?></p>
                                    <p><?php echo $request['topic']; ?> • <?php echo date('F j, Y', strtotime($request['session_date'])); ?>, <?php echo date('g:i A', strtotime($request['start_time'])); ?></p>
                                </div>
                            </div>
                            <div class="session-actions">
                                <form method="post" style="display: inline;">
                                    <input type="hidden" name="session_id" value="<?php echo $request['session_id']; ?>">
                                    <button type="submit" name="accept_session" class="accept-btn">Accept</button>
                                    <button type="submit" name="decline_session" class="decline-btn">Decline</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <h2 class="section-title" style="margin-top: 30px;">Upcoming Sessions</h2>
            <div class="session-list">
                <?php if(empty($upcoming_sessions)): ?>
                    <div class="session-item">
                        <p>You have no upcoming sessions.</p>
                    </div>
                <?php else: ?>
                    <?php foreach($upcoming_sessions as $session): ?>
                        <div class="session-item">
                            <div class="student-profile">
                                <div class="student-mini-avatar"><?php echo substr($session['first_name'], 0, 1) . substr($session['last_name'], 0, 1); ?></div>
                                <div class="student-details">
                                    <h4><?php echo $session['first_name'] . " " . $session['last_name']; ?></h4>
                                    <p><?php echo $session['topic']; ?> • <?php echo date('F j, Y', strtotime($session['session_date'])); ?>, <?php echo date('g:i A', strtotime($session['start_time'])); ?></p>
                                </div>
                            </div>
                            <div class="session-actions">
                                <?php if(date('Y-m-d') == $session['session_date'] && (strtotime($session['start_time']) - time() < 3600) && (strtotime($session['start_time']) - time() > 0)): ?>
                                    <a href="session-chat.php?session_id=<?php echo $session['session_id']; ?>" class="join-btn" style="text-decoration: none; display: inline-block; text-align: center; background-color: #2ecc71; color: white; border: none; border-radius: 4px; padding: 8px 15px; cursor: pointer; font-size: 14px;">Join Session</a>
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

        <!-- Session Requests Section -->
        <div id="session-requests-section" class="content-section">
            <h2 class="section-title">Pending Session Requests</h2>
            <div class="session-list">
                <?php if(empty($pending_requests)): ?>
                    <div class="session-item">
                        <p>You have no pending session requests.</p>
                    </div>
                <?php else: ?>
                    <?php foreach($pending_requests as $request): ?>
                        <div class="session-item">
                            <div class="student-profile">
                                <div class="student-mini-avatar"><?php echo substr($request['first_name'], 0, 1) . substr($request['last_name'], 0, 1); ?></div>
                                <div class="student-details">
                                    <h4><?php echo $request['first_name'] . " " . $request['last_name']; ?></h4>
                                    <p><?php echo $request['topic']; ?> • <?php echo date('F j, Y', strtotime($request['session_date'])); ?>, <?php echo date('g:i A', strtotime($request['start_time'])); ?></p>
                                    <?php if(!empty($request['notes'])): ?>
                                        <p><strong>Notes:</strong> <?php echo $request['notes']; ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="session-actions">
                                <form method="post" style="display: inline;">
                                    <input type="hidden" name="session_id" value="<?php echo $request['session_id']; ?>">
                                    <button type="submit" name="accept_session" class="accept-btn">Accept</button>
                                    <button type="submit" name="decline_session" class="decline-btn">Decline</button>
                                </form>
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
                        <p>You have no upcoming sessions.</p>
                    </div>
                <?php else: ?>
                    <?php foreach($upcoming_sessions as $session): ?>
                        <div class="session-item">
                            <div class="student-profile">
                                <div class="student-mini-avatar"><?php echo substr($session['first_name'], 0, 1) . substr($session['last_name'], 0, 1); ?></div>
                                <div class="student-details">
                                    <h4><?php echo $session['first_name'] . " " . $session['last_name']; ?></h4>
                                    <p><?php echo $session['topic']; ?> • <?php echo date('F j, Y', strtotime($session['session_date'])); ?>, <?php echo date('g:i A', strtotime($session['start_time'])); ?></p>
                                    <?php if(!empty($session['notes'])): ?>
                                        <p><strong>Notes:</strong> <?php echo $session['notes']; ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="session-actions">
                                <?php if(date('Y-m-d') == $session['session_date'] && (strtotime($session['start_time']) - time() < 3600) && (strtotime($session['start_time']) - time() > 0)): ?>
                                    <a href="session-chat.php?session_id=<?php echo $session['session_id']; ?>" class="join-btn" style="text-decoration: none; display: inline-block; text-align: center; background-color: #2ecc71; color: white; border: none; border-radius: 4px; padding: 8px 15px; cursor: pointer; font-size: 14px;">Join Session</a>
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
                            <div class="student-profile">
                                <div class="student-mini-avatar"><?php echo substr($session['first_name'], 0, 1) . substr($session['last_name'], 0, 1); ?></div>
                                <div class="student-details">
                                    <h4><?php echo $session['first_name'] . " " . $session['last_name']; ?></h4>
                                    <p><?php echo $session['topic']; ?> • <?php echo date('F j, Y', strtotime($session['session_date'])); ?></p>
                                </div>
                            </div>
                            <div class="session-actions">
                                <?php if($session['has_feedback'] > 0): ?>
                                    <button class="view-feedback-btn" data-session-id="<?php echo $session['session_id']; ?>" data-student-name="<?php echo $session['first_name'] . ' ' . $session['last_name']; ?>">View Feedback</button>
                                <?php else: ?>
                                    <button class="view-feedback-btn" disabled>No Feedback</button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Students Section -->
        <div id="students-section" class="content-section">
            <h2 class="section-title">My Students</h2>
            <div class="student-list">
                <?php if(empty($students)): ?>
                    <p>You have no students yet.</p>
                <?php else: ?>
                    <?php foreach($students as $student): ?>
                        <div class="student-card">
                            <div class="student-header">
                                <div class="student-avatar"><?php echo substr($student['first_name'], 0, 1) . substr($student['last_name'], 0, 1); ?></div>
                                <div class="student-info">
                                    <h3><?php echo $student['first_name'] . " " . $student['last_name']; ?></h3>
                                    <p><?php echo !empty($student['skills']) ? explode(",", $student['skills'])[0] . " Student" : "Student"; ?></p>
                                </div>
                            </div>
                            <div class="student-body">
                                <p><strong>Skills:</strong> <?php echo $student['skills']; ?></p>
                            </div>
                            <div class="student-footer">
                                <button class="message-btn">Message</button>
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
                        <label for="expertise">Areas of Expertise</label>
                        <textarea id="expertise" name="expertise"><?php echo $expertise; ?></textarea>
                    </div>
                    <div class="form-group">
                        <label for="experience_years">Years of Experience</label>
                        <input type="number" id="experience_years" name="experience_years" value="<?php echo $experience_years; ?>">
                    </div>
                    <div class="form-group">
                        <label for="bio">Professional Bio</label>
                        <textarea id="bio" name="bio"><?php echo $bio; ?></textarea>
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

<!-- Feedback Modal -->
<div id="feedbackModal" class="modal">
    <div class="modal-content">
        <span class="close-btn" id="closeFeedbackBtn">&times;</span>
        <h2 class="modal-title">Feedback from <span id="feedbackStudentName">Student</span></h2>
        
        <div class="feedback-details">
            <div class="rating" id="feedbackRating">★★★★★</div>
            <h3>Comments</h3>
            <p id="feedbackComments">Loading feedback...</p>
            <h3>Most Valuable Aspects</h3>
            <p id="feedbackStrengths">Loading feedback...</p>
            <h3>Areas for Improvement</h3>
            <p id="feedbackImprovements">Loading feedback...</p>
            <h3>Future Topics</h3>
            <p id="feedbackTopics">Loading feedback...</p>
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

// Feedback Modal
const viewFeedbackBtns = document.querySelectorAll('.view-feedback-btn');
const feedbackModal = document.getElementById('feedbackModal');
const closeFeedbackBtn = document.getElementById('closeFeedbackBtn');
const feedbackStudentNameSpan = document.getElementById('feedbackStudentName');
const feedbackRatingDiv = document.getElementById('feedbackRating');

// Feedback modal functionality
viewFeedbackBtns.forEach(btn => {
    if (!btn.disabled) {
        btn.addEventListener('click', function() {
            const sessionId = this.getAttribute('data-session-id');
            const studentName = this.getAttribute('data-student-name');
            
            // TODO: Fetch feedback data from server
            // For now, just show mock data
            feedbackRatingDiv.innerHTML = '★★★★★';
            document.getElementById('feedbackComments').textContent = 'Great session! Very helpful.';
            document.getElementById('feedbackStrengths').textContent = 'Clear explanations and practical examples.';
            document.getElementById('feedbackImprovements').textContent = 'Could go a bit slower on complex topics.';
            document.getElementById('feedbackTopics').textContent = 'Advanced JavaScript, React Hooks';
            
            feedbackStudentNameSpan.textContent = studentName;
            feedbackModal.style.display = 'block';
        });
    }
});

closeFeedbackBtn.addEventListener('click', function() {
    feedbackModal.style.display = 'none';
});

// Close modals when clicking outside
window.addEventListener('click', function(event) {
    if (event.target === feedbackModal) {
        feedbackModal.style.display = 'none';
    }
});
</script>
</body>
</html>

