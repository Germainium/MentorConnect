<?php
// Initialize the session
session_start();

// Check if the user is logged in and is an admin
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["user_type"] !== "admin"){
    header("location: login.php");
    exit;
}

// Include database connection
require_once "config/db_connect.php";

// Get admin information
$admin_id = $_SESSION["user_id"];
$first_name = $_SESSION["first_name"];
$last_name = $_SESSION["last_name"];
$email = $_SESSION["email"];

// Get dashboard statistics
$stats = array();

// Total users
$sql = "SELECT 
        (SELECT COUNT(*) FROM users WHERE user_type = 'student') as student_count,
        (SELECT COUNT(*) FROM users WHERE user_type = 'mentor') as mentor_count,
        (SELECT COUNT(*) FROM sessions) as total_sessions,
        (SELECT COUNT(*) FROM sessions WHERE status = 'completed') as completed_sessions";
$result = $conn->query($sql);
if($result && $result->num_rows > 0){
    $stats = $result->fetch_assoc();
}

// Process user management actions
if($_SERVER["REQUEST_METHOD"] == "POST"){
    // Delete user
    if(isset($_POST["delete_user"])){
        $user_id = $_POST["user_id"];
        
        $sql = "DELETE FROM users WHERE user_id = ?";
        if($stmt = $conn->prepare($sql)){
            $stmt->bind_param("i", $user_id);
            
            if($stmt->execute()){
                $user_deleted = true;
            } else {
                $delete_error = "Something went wrong. Please try again.";
            }
            $stmt->close();
        }
    }
    
    // Add new user
    if(isset($_POST["add_user"])){
        $new_email = trim($_POST["email"]);
        $new_password = trim($_POST["password"]);
        $new_first_name = trim($_POST["first_name"]);
        $new_last_name = trim($_POST["last_name"]);
        $new_user_type = trim($_POST["user_type"]);
        
        // Validate input
        $input_error = false;
        
        if(empty($new_email)){
            $email_error = "Please enter an email.";
            $input_error = true;
        } elseif(!filter_var($new_email, FILTER_VALIDATE_EMAIL)){
            $email_error = "Please enter a valid email.";
            $input_error = true;
        }
        
        if(empty($new_password)){
            $password_error = "Please enter a password.";
            $input_error = true;
        } elseif(strlen($new_password) < 8){
            $password_error = "Password must have at least 8 characters.";
            $input_error = true;
        }
        
        if(empty($new_first_name)){
            $first_name_error = "Please enter a first name.";
            $input_error = true;
        }
        
        if(empty($new_last_name)){
            $last_name_error = "Please enter a last name.";
            $input_error = true;
        }
        
        // Check if email already exists
        $sql = "SELECT user_id FROM users WHERE email = ?";
        if($stmt = $conn->prepare($sql)){
            $stmt->bind_param("s", $new_email);
            $stmt->execute();
            $stmt->store_result();
            
            if($stmt->num_rows > 0){
                $email_error = "This email is already taken.";
                $input_error = true;
            }
            
            $stmt->close();
        }
        
        // Add user if no errors
        if(!$input_error){
            $sql = "INSERT INTO users (email, password, first_name, last_name, user_type) VALUES (?, ?, ?, ?, ?)";
            
            if($stmt = $conn->prepare($sql)){
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt->bind_param("sssss", $new_email, $hashed_password, $new_first_name, $new_last_name, $new_user_type);
                
                if($stmt->execute()){
                    $new_user_id = $stmt->insert_id;
                    
                    // Create profile based on user type
                    if($new_user_type == "mentor"){
                        $sql = "INSERT INTO mentor_profiles (mentor_id, expertise, experience_years, bio) VALUES (?, '', 0, '')";
                        $profile_stmt = $conn->prepare($sql);
                        $profile_stmt->bind_param("i", $new_user_id);
                        $profile_stmt->execute();
                        $profile_stmt->close();
                    } elseif($new_user_type == "student"){
                        $sql = "INSERT INTO student_profiles (student_id) VALUES (?)";
                        $profile_stmt = $conn->prepare($sql);
                        $profile_stmt->bind_param("i", $new_user_id);
                        $profile_stmt->execute();
                        $profile_stmt->close();
                    }
                    
                    $user_added = true;
                } else {
                    $add_error = "Something went wrong. Please try again.";
                }
                
                $stmt->close();
            }
        }
    }
    
    // Update session status
    if(isset($_POST["update_session"])){
        $session_id = $_POST["session_id"];
        $new_status = $_POST["new_status"];
        
        $sql = "UPDATE sessions SET status = ? WHERE session_id = ?";
        if($stmt = $conn->prepare($sql)){
            $stmt->bind_param("si", $new_status, $session_id);
            
            if($stmt->execute()){
                $session_updated = true;
            } else {
                $update_error = "Something went wrong. Please try again.";
            }
            $stmt->close();
        }
    }
    
    // Add resource
    if(isset($_POST["add_resource"])){
        $resource_title = trim($_POST["resource_title"]);
        $resource_description = trim($_POST["resource_description"]);
        $resource_type = trim($_POST["resource_type"]);
        $resource_url = trim($_POST["resource_url"]);
        
        $sql = "INSERT INTO resources (title, description, resource_type, url, created_by) VALUES (?, ?, ?, ?, ?)";
        if($stmt = $conn->prepare($sql)){
            $stmt->bind_param("ssssi", $resource_title, $resource_description, $resource_type, $resource_url, $admin_id);
            
            if($stmt->execute()){
                $resource_added = true;
            } else {
                $resource_error = "Something went wrong. Please try again.";
            }
            $stmt->close();
        }
    }
}

// Get all users
$users = array();
$sql = "SELECT u.*, 
        CASE 
            WHEN u.user_type = 'mentor' THEN (SELECT expertise FROM mentor_profiles WHERE mentor_id = u.user_id)
            WHEN u.user_type = 'student' THEN (SELECT skills FROM student_profiles WHERE student_id = u.user_id)
            ELSE ''
        END as specialization
        FROM users u
        ORDER BY u.user_type, u.first_name";
$result = $conn->query($sql);
if($result && $result->num_rows > 0){
    while($row = $result->fetch_assoc()){
        $users[] = $row;
    }
}

// Get all sessions
$sessions = array();
$sql = "SELECT s.*, 
        u1.first_name as student_first_name, u1.last_name as student_last_name,
        u2.first_name as mentor_first_name, u2.last_name as mentor_last_name
        FROM sessions s
        JOIN users u1 ON s.student_id = u1.user_id
        JOIN users u2 ON s.mentor_id = u2.user_id
        ORDER BY s.session_date DESC, s.start_time DESC";
$result = $conn->query($sql);
if($result && $result->num_rows > 0){
    while($row = $result->fetch_assoc()){
        $sessions[] = $row;
    }
}

// Get all resources
$resources = array();
$sql = "SELECT r.*, u.first_name, u.last_name
        FROM resources r
        JOIN users u ON r.created_by = u.user_id
        ORDER BY r.created_at DESC";
$result = $conn->query($sql);
if($result && $result->num_rows > 0){
    while($row = $result->fetch_assoc()){
        $resources[] = $row;
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
<title>Admin Dashboard - MentorConnect</title>
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

.admin-avatar {
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

.admin-name {
    font-size: 18px;
    margin-bottom: 5px;
}

.admin-title {
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
}

.menu-item.active {
    background-color: rgba(255, 255, 255, 0.1);
    border-left: 4px solid var(--primary-color);
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

/* Tables */
.data-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 30px;
    background-color: white;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
}

.data-table th, .data-table td {
    padding: 12px 15px;
    text-align: left;
    border-bottom: 1px solid #eee;
}

.data-table th {
    background-color: #f9f9f9;
    font-weight: bold;
}

.data-table tr:last-child td {
    border-bottom: none;
}

.data-table tr:hover {
    background-color: #f5f5f5;
}

.action-buttons {
    display: flex;
    gap: 5px;
}

.btn {
    padding: 6px 12px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
}

.btn-primary {
    background-color: var(--primary-color);
    color: white;
}

.btn-danger {
    background-color: var(--danger-color);
    color: white;
}

.btn-warning {
    background-color: var(--warning-color);
    color: white;
}

.btn-success {
    background-color: var(--success-color);
    color: white;
}

/* Forms */
.form-container {
    background-color: white;
    border-radius: 8px;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
    padding: 20px;
    margin-bottom: 30px;
}

.form-group {
    margin-bottom: 15px;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: bold;
}

.form-control {
    width: 100%;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
}

.form-row {
    display: flex;
    gap: 15px;
    margin-bottom: 15px;
}

.form-row .form-group {
    flex: 1;
    margin-bottom: 0;
}

/* Alerts */
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

    .form-row {
        flex-direction: column;
        gap: 15px;
    }

    .data-table {
        display: block;
        overflow-x: auto;
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
            <div class="admin-avatar"><?php echo substr($first_name, 0, 1); ?></div>
            <h3 class="admin-name"><?php echo $first_name . " " . $last_name; ?></h3>
            <p class="admin-title">Administrator</p>
            <form action="logout.php" method="post">
                <button type="submit" class="logout-btn" style="margin-top: 10px; background-color: #e74c3c; color: white; border: none; padding: 5px 10px; border-radius: 4px; cursor: pointer;">Logout</button>
            </form>
        </div>
        <div class="sidebar-menu">
            <div class="menu-item active" data-section="dashboard">
                <span class="menu-icon">📊</span> Dashboard
            </div>
            <div class="menu-item" data-section="users">
                <span class="menu-icon">👥</span> User Management
            </div>
            <div class="menu-item" data-section="sessions">
                <span class="menu-icon">📅</span> Session Management
            </div>
            <div class="menu-item" data-section="resources">
                <span class="menu-icon">📚</span> Learning Resources
            </div>
            <div class="menu-item" data-section="reports">
                <span class="menu-icon">📈</span> Reports & Analytics
            </div>
            <div class="menu-item" data-section="settings">
                <span class="menu-icon">⚙️</span> System Settings
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <div class="header">
            <h1 class="page-title" id="page-title">Dashboard</h1>
        </div>

        <?php if(isset($user_deleted) && $user_deleted): ?>
            <div class="alert alert-success">User has been deleted successfully.</div>
        <?php endif; ?>

        <?php if(isset($user_added) && $user_added): ?>
            <div class="alert alert-success">New user has been added successfully.</div>
        <?php endif; ?>

        <?php if(isset($session_updated) && $session_updated): ?>
            <div class="alert alert-success">Session status has been updated successfully.</div>
        <?php endif; ?>

        <?php if(isset($resource_added) && $resource_added): ?>
            <div class="alert alert-success">New resource has been added successfully.</div>
        <?php endif; ?>

        <?php if(isset($delete_error)): ?>
            <div class="alert alert-danger"><?php echo $delete_error; ?></div>
        <?php endif; ?>

        <?php if(isset($add_error)): ?>
            <div class="alert alert-danger"><?php echo $add_error; ?></div>
        <?php endif; ?>

        <?php if(isset($update_error)): ?>
            <div class="alert alert-danger"><?php echo $update_error; ?></div>
        <?php endif; ?>

        <?php if(isset($resource_error)): ?>
            <div class="alert alert-danger"><?php echo $resource_error; ?></div>
        <?php endif; ?>

        <!-- Dashboard Section -->
        <div id="dashboard-section" class="content-section active">
            <div class="stats-container">
                <div class="stat-card">
                    <div class="stat-title">Total Students</div>
                    <div class="stat-value"><?php echo isset($stats['student_count']) ? $stats['student_count'] : 0; ?></div>
                    <div class="stat-description">Registered students</div>
                </div>
                <div class="stat-card">
                    <div class="stat-title">Total Mentors</div>
                    <div class="stat-value"><?php echo isset($stats['mentor_count']) ? $stats['mentor_count'] : 0; ?></div>
                    <div class="stat-description">Active mentors</div>
                </div>
                <div class="stat-card">
                    <div class="stat-title">Total Sessions</div>
                    <div class="stat-value"><?php echo isset($stats['total_sessions']) ? $stats['total_sessions'] : 0; ?></div>
                    <div class="stat-description">All mentoring sessions</div>
                </div>
                <div class="stat-card">
                    <div class="stat-title">Completed Sessions</div>
                    <div class="stat-value"><?php echo isset($stats['completed_sessions']) ? $stats['completed_sessions'] : 0; ?></div>
                    <div class="stat-description">Finished sessions</div>
                </div>
            </div>

            <h2 class="section-title">Recent Sessions</h2>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Student</th>
                        <th>Mentor</th>
                        <th>Topic</th>
                        <th>Date & Time</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $count = 0;
                    foreach($sessions as $session): 
                        if($count >= 5) break; // Show only 5 recent sessions
                        $count++;
                    ?>
                        <tr>
                            <td><?php echo $session['session_id']; ?></td>
                            <td><?php echo $session['student_first_name'] . ' ' . $session['student_last_name']; ?></td>
                            <td><?php echo $session['mentor_first_name'] . ' ' . $session['mentor_last_name']; ?></td>
                            <td><?php echo $session['topic']; ?></td>
                            <td><?php echo date('M j, Y', strtotime($session['session_date'])) . ', ' . date('g:i A', strtotime($session['start_time'])); ?></td>
                            <td>
                                <span class="status-badge" style="
                                    padding: 4px 8px;
                                    border-radius: 4px;
                                    font-size: 12px;
                                    background-color: 
                                        <?php 
                                        switch($session['status']) {
                                            case 'completed': echo '#2ecc71'; break;
                                            case 'accepted': echo '#3498db'; break;
                                            case 'pending': echo '#f39c12'; break;
                                            case 'declined': echo '#e74c3c'; break;
                                            case 'cancelled': echo '#95a5a6'; break;
                                            default: echo '#95a5a6';
                                        }
                                        ?>;
                                    color: white;
                                ">
                                    <?php echo ucfirst($session['status']); ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if($count == 0): ?>
                        <tr>
                            <td colspan="6" style="text-align: center;">No sessions found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <h2 class="section-title">Recent Users</h2>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Type</th>
                        <th>Joined</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $count = 0;
                    foreach($users as $user): 
                        if($count >= 5) break; // Show only 5 recent users
                        $count++;
                    ?>
                        <tr>
                            <td><?php echo $user['user_id']; ?></td>
                            <td><?php echo $user['first_name'] . ' ' . $user['last_name']; ?></td>
                            <td><?php echo $user['email']; ?></td>
                            <td><?php echo ucfirst($user['user_type']); ?></td>
                            <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if($count == 0): ?>
                        <tr>
                            <td colspan="5" style="text-align: center;">No users found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- User Management Section -->
        <div id="users-section" class="content-section">
            <h2 class="section-title">Add New User</h2>
            <div class="form-container">
                <form method="post">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="first_name">First Name</label>
                            <input type="text" id="first_name" name="first_name" class="form-control" required>
                            <?php if(isset($first_name_error)): ?>
                                <div class="error-message" style="color: #e74c3c; font-size: 14px; margin-top: 5px;"><?php echo $first_name_error; ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="form-group">
                            <label for="last_name">Last Name</label>
                            <input type="text" id="last_name" name="last_name" class="form-control" required>
                            <?php if(isset($last_name_error)): ?>
                                <div class="error-message" style="color: #e74c3c; font-size: 14px; margin-top: 5px;"><?php echo $last_name_error; ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email" class="form-control" required>
                            <?php if(isset($email_error)): ?>
                                <div class="error-message" style="color: #e74c3c; font-size: 14px; margin-top: 5px;"><?php echo $email_error; ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="form-group">
                            <label for="password">Password</label>
                            <input type="password" id="password" name="password" class="form-control" required>
                            <?php if(isset($password_error)): ?>
                                <div class="error-message" style="color: #e74c3c; font-size: 14px; margin-top: 5px;"><?php echo $password_error; ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="user_type">User Type</label>
                        <select id="user_type" name="user_type" class="form-control" required>
                            <option value="student">Student</option>
                            <option value="mentor">Mentor</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <button type="submit" name="add_user" class="btn btn-primary">Add User</button>
                </form>
            </div>

            <h2 class="section-title">All Users</h2>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Type</th>
                        <th>Specialization</th>
                        <th>Joined</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($users as $user): ?>
                        <tr>
                            <td><?php echo $user['user_id']; ?></td>
                            <td><?php echo $user['first_name'] . ' ' . $user['last_name']; ?></td>
                            <td><?php echo $user['email']; ?></td>
                            <td><?php echo ucfirst($user['user_type']); ?></td>
                            <td><?php echo $user['specialization']; ?></td>
                            <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                            <td>
                                <div class="action-buttons">
                                    <form method="post" onsubmit="return confirm('Are you sure you want to delete this user?');">
                                        <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                        <button type="submit" name="delete_user" class="btn btn-danger">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if(empty($users)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center;">No users found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Session Management Section -->
        <div id="sessions-section" class="content-section">
            <h2 class="section-title">All Sessions</h2>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Student</th>
                        <th>Mentor</th>
                        <th>Topic</th>
                        <th>Date & Time</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($sessions as $session): ?>
                        <tr>
                            <td><?php echo $session['session_id']; ?></td>
                            <td><?php echo $session['student_first_name'] . ' ' . $session['student_last_name']; ?></td>
                            <td><?php echo $session['mentor_first_name'] . ' ' . $session['mentor_last_name']; ?></td>
                            <td><?php echo $session['topic']; ?></td>
                            <td><?php echo date('M j, Y', strtotime($session['session_date'])) . ', ' . date('g:i A', strtotime($session['start_time'])); ?></td>
                            <td>
                                <span class="status-badge" style="
                                    padding: 4px 8px;
                                    border-radius: 4px;
                                    font-size: 12px;
                                    background-color: 
                                        <?php 
                                        switch($session['status']) {
                                            case 'completed': echo '#2ecc71'; break;
                                            case 'accepted': echo '#3498db'; break;
                                            case 'pending': echo '#f39c12'; break;
                                            case 'declined': echo '#e74c3c'; break;
                                            case 'cancelled': echo '#95a5a6'; break;
                                            default: echo '#95a5a6';
                                        }
                                        ?>;
                                    color: white;
                                ">
                                    <?php echo ucfirst($session['status']); ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <form method="post">
                                        <input type="hidden" name="session_id" value="<?php echo $session['session_id']; ?>">
                                        <select name="new_status" class="form-control" style="width: auto; display: inline-block; margin-right: 5px;">
                                            <option value="pending" <?php echo $session['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                            <option value="accepted" <?php echo $session['status'] == 'accepted' ? 'selected' : ''; ?>>Accepted</option>
                                            <option value="declined" <?php echo $session['status'] == 'declined' ? 'selected' : ''; ?>>Declined</option>
                                            <option value="completed" <?php echo $session['status'] == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                            <option value="cancelled" <?php echo $session['status'] == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                        </select>
                                        <button type="submit" name="update_session" class="btn btn-primary">Update</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if(empty($sessions)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center;">No sessions found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Resources Section -->
        <div id="resources-section" class="content-section">
            <h2 class="section-title">Add New Resource</h2>
            <div class="form-container">
                <form method="post">
                    <div class="form-group">
                        <label for="resource_title">Title</label>
                        <input type="text" id="resource_title" name="resource_title" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="resource_description">Description</label>
                        <textarea id="resource_description" name="resource_description" class="form-control" rows="3" required></textarea>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="resource_type">Type</label>
                            <select id="resource_type" name="resource_type" class="form-control" required>
                                <option value="document">Document</option>
                                <option value="video">Video</option>
                                <option value="link">Link</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="resource_url">URL</label>
                            <input type="url" id="resource_url" name="resource_url" class="form-control" required>
                        </div>
                    </div>
                    <button type="submit" name="add_resource" class="btn btn-primary">Add Resource</button>
                </form>
            </div>

            <h2 class="section-title">All Resources</h2>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Title</th>
                        <th>Type</th>
                        <th>Created By</th>
                        <th>Created At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($resources as $resource): ?>
                        <tr>
                            <td><?php echo $resource['resource_id']; ?></td>
                            <td><?php echo $resource['title']; ?></td>
                            <td><?php echo ucfirst($resource['resource_type']); ?></td>
                            <td><?php echo $resource['first_name'] . ' ' . $resource['last_name']; ?></td>
                            <td><?php echo date('M j, Y', strtotime($resource['created_at'])); ?></td>
                            <td>
                                <div class="action-buttons">
                                    <a href="<?php echo $resource['url']; ?>" target="_blank" class="btn btn-primary">View</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if(empty($resources)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center;">No resources found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Reports Section -->
        <div id="reports-section" class="content-section">
            <h2 class="section-title">System Reports</h2>
            <div class="stats-container">
                <div class="stat-card">
                    <div class="stat-title">Session Completion Rate</div>
                    <div class="stat-value">
                        <?php 
                        $completion_rate = 0;
                        if(isset($stats['total_sessions']) && $stats['total_sessions'] > 0) {
                            $completion_rate = round(($stats['completed_sessions'] / $stats['total_sessions']) * 100);
                        }
                        echo $completion_rate . '%';
                        ?>
                    </div>
                    <div class="stat-description">Percentage of completed sessions</div>
                </div>
                <div class="stat-card">
                    <div class="stat-title">Student-Mentor Ratio</div>
                    <div class="stat-value">
                        <?php 
                        $ratio = 'N/A';
                        if(isset($stats['mentor_count']) && $stats['mentor_count'] > 0) {
                            $ratio = round($stats['student_count'] / $stats['mentor_count'], 1) . ':1';
                        }
                        echo $ratio;
                        ?>
                    </div>
                    <div class="stat-description">Students per mentor</div>
                </div>
                <div class="stat-card">
                    <div class="stat-title">Average Sessions</div>
                    <div class="stat-value">
                        <?php 
                        $avg_sessions = 0;
                        if(isset($stats['student_count']) && $stats['student_count'] > 0) {
                            $avg_sessions = round($stats['total_sessions'] / $stats['student_count'], 1);
                        }
                        echo $avg_sessions;
                        ?>
                    </div>
                    <div class="stat-description">Sessions per student</div>
                </div>
                <div class="stat-card">
                    <div class="stat-title">System Uptime</div>
                    <div class="stat-value">99.9%</div>
                    <div class="stat-description">Platform availability</div>
                </div>
            </div>

            <h2 class="section-title">Monthly Session Statistics</h2>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Month</th>
                        <th>Total Sessions</th>
                        <th>Completed</th>
                        <th>Cancelled</th>
                        <th>Completion Rate</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>April 2025</td>
                        <td>24</td>
                        <td>18</td>
                        <td>2</td>
                        <td>75%</td>
                    </tr>
                    <tr>
                        <td>March 2025</td>
                        <td>32</td>
                        <td>28</td>
                        <td>4</td>
                        <td>87.5%</td>
                    </tr>
                    <tr>
                        <td>February 2025</td>
                        <td>18</td>
                        <td>15</td>
                        <td>3</td>
                        <td>83.3%</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Settings Section -->
        <div id="settings-section" class="content-section">
            <h2 class="section-title">System Settings</h2>
            <div class="form-container">
                <form method="post">
                    <div class="form-group">
                        <label for="site_name">Site Name</label>
                        <input type="text" id="site_name" name="site_name" class="form-control" value="MentorConnect">
                    </div>
                    <div class="form-group">
                        <label for="site_description">Site Description</label>
                        <textarea id="site_description" name="site_description" class="form-control" rows="2">Connect students with mentors for personalized guidance and learning.</textarea>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="session_duration">Default Session Duration (minutes)</label>
                            <input type="number" id="session_duration" name="session_duration" class="form-control" value="60">
                        </div>
                        <div class="form-group">
                            <label for="max_sessions_per_day">Max Sessions Per Day (per mentor)</label>
                            <input type="number" id="max_sessions_per_day" name="max_sessions_per_day" class="form-control" value="5">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="maintenance_mode">Maintenance Mode</label>
                        <select id="maintenance_mode" name="maintenance_mode" class="form-control">
                            <option value="0" selected>Off</option>
                            <option value="1">On</option>
                        </select>
                    </div>
                    <button type="submit" name="save_settings" class="btn btn-primary">Save Settings</button>
                </form>
            </div>

            <h2 class="section-title">Email Settings</h2>
            <div class="form-container">
                <form method="post">
                    <div class="form-group">
                        <label for="smtp_host">SMTP Host</label>
                        <input type="text" id="smtp_host" name="smtp_host" class="form-control" value="smtp.example.com">
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="smtp_port">SMTP Port</label>
                            <input type="number" id="smtp_port" name="smtp_port" class="form-control" value="587">
                        </div>
                        <div class="form-group">
                            <label for="smtp_encryption">Encryption</label>
                            <select id="smtp_encryption" name="smtp_encryption" class="form-control">
                                <option value="tls" selected>TLS</option>
                                <option value="ssl">SSL</option>
                                <option value="none">None</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="smtp_username">Username</label>
                            <input type="text" id="smtp_username" name="smtp_username" class="form-control" value="noreply@mentorconnect.com">
                        </div>
                        <div class="form-group">
                            <label for="smtp_password">Password</label>
                            <input type="password" id="smtp_password" name="smtp_password" class="form-control" value="********">
                        </div>
                    </div>
                    <button type="submit" name="save_email_settings" class="btn btn-primary">Save Email Settings</button>
                </form>
            </div>

            <h2 class="section-title">Backup & Restore</h2>
            <div class="form-container">
                <div style="margin-bottom: 20px;">
                    <button class="btn btn-primary">Create Database Backup</button>
                </div>
                <form method="post" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="restore_file">Restore from Backup</label>
                        <input type="file" id="restore_file" name="restore_file" class="form-control">
                    </div>
                    <button type="submit" name="restore_backup" class="btn btn-warning">Restore Database</button>
                </form>
            </div>
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
</script>
</body>
</html>

