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
$profile_photo = "";

// Get mentor profile photo
$sql = "SELECT profile_photo FROM mentor_profiles WHERE mentor_id = ?";
if($stmt = $conn->prepare($sql)){
    $stmt->bind_param("i", $mentor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if($result->num_rows == 1){
        $row = $result->fetch_assoc();
        $profile_photo = $row["profile_photo"];
    }
    $stmt->close();
}

// Get session requests
$session_requests = array();
$sql = "SELECT s.*, u.first_name, u.last_name, u.email, sp.profile_photo as student_photo
        FROM sessions s 
        JOIN users u ON s.student_id = u.user_id 
        LEFT JOIN student_profiles sp ON u.user_id = sp.student_id
        WHERE s.mentor_id = ? AND s.status = 'pending' 
        ORDER BY s.created_at DESC";
if($stmt = $conn->prepare($sql)){
    $stmt->bind_param("i", $mentor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while($row = $result->fetch_assoc()){
        $session_requests[] = $row;
    }
    $stmt->close();
}

// Process session acceptance/rejection
if($_SERVER["REQUEST_METHOD"] == "POST"){
    if(isset($_POST["accept_session"]) && isset($_POST["session_id"])){
        $session_id = $_POST["session_id"];
        
        // Update session status
        $sql = "UPDATE sessions SET status = 'accepted' WHERE session_id = ? AND mentor_id = ?";
        if($stmt = $conn->prepare($sql)){
            $stmt->bind_param("ii", $session_id, $mentor_id);
            
            if($stmt->execute()){
                // Get student ID for notification
                $sql = "SELECT student_id FROM sessions WHERE session_id = ?";
                $student_stmt = $conn->prepare($sql);
                $student_stmt->bind_param("i", $session_id);
                $student_stmt->execute();
                $student_result = $student_stmt->get_result();
                $student_row = $student_result->fetch_assoc();
                $student_id = $student_row["student_id"];
                $student_stmt->close();
                
                // Create notification for student
                $sql = "INSERT INTO notifications (user_id, notification_type, message, related_id) 
                        VALUES (?, 'session_accepted', ?, ?)";
                $notification_stmt = $conn->prepare($sql);
                $notification_message = $first_name . " " . $last_name . " has accepted your session request";
                $notification_stmt->bind_param("isi", $student_id, $notification_message, $session_id);
                $notification_stmt->execute();
                $notification_stmt->close();
                
                // Redirect to refresh the page
                header("location: mentor-session-requests.php?success=accepted");
                exit;
            } else {
                $error_message = "Something went wrong. Please try again.";
            }
            $stmt->close();
        }
    } elseif(isset($_POST["decline_session"]) && isset($_POST["session_id"])){
        $session_id = $_POST["session_id"];
        
        // Update session status
        $sql = "UPDATE sessions SET status = 'declined' WHERE session_id = ? AND mentor_id = ?";
        if($stmt = $conn->prepare($sql)){
            $stmt->bind_param("ii", $session_id, $mentor_id);
            
            if($stmt->execute()){
                // Get student ID for notification
                $sql = "SELECT student_id FROM sessions WHERE session_id = ?";
                $student_stmt = $conn->prepare($sql);
                $student_stmt->bind_param("i", $session_id);
                $student_stmt->execute();
                $student_result = $student_stmt->get_result();
                $student_row = $student_result->fetch_assoc();
                $student_id = $student_row["student_id"];
                $student_stmt->close();
                
                // Create notification for student
                $sql = "INSERT INTO notifications (user_id, notification_type, message, related_id) 
                        VALUES (?, 'session_declined', ?, ?)";
                $notification_stmt = $conn->prepare($sql);
                $notification_message = $first_name . " " . $last_name . " has declined your session request";
                $notification_stmt->bind_param("isi", $student_id, $notification_message, $session_id);
                $notification_stmt->execute();
                $notification_stmt->close();
                
                // Redirect to refresh the page
                header("location: mentor-session-requests.php?success=declined");
                exit;
            } else {
                $error_message = "Something went wrong. Please try again.";
            }
            $stmt->close();
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
    <title>Session Requests - MentorConnect</title>
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
            left: 0;
            top: 0;
            overflow-y: auto;
        }

        .profile-section {
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid #34495e;
        }

        .profile-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background-color: #3498db;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            margin: 0 auto 15px;
            overflow: hidden;
        }

        .profile-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .profile-name {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .profile-title {
            font-size: 14px;
            color: #bdc3c7;
            margin-bottom: 15px;
        }

        .logout-btn {
            background-color: #e74c3c;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            transition: background-color 0.3s;
        }

        .logout-btn:hover {
            background-color: #c0392b;
        }

        .nav-menu {
            padding: 20px 0;
        }

        .nav-item {
            padding: 12px 20px;
            display: flex;
            align-items: center;
            color: #ecf0f1;
            text-decoration: none;
            transition: background-color 0.3s;
        }

        .nav-item:hover {
            background-color: #34495e;
        }

        .nav-item.active {
            background-color: #3498db;
        }

        .nav-icon {
            margin-right: 10px;
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
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #ddd;
        }

        .page-title {
            font-size: 24px;
            font-weight: bold;
        }

        /* Alert Messages */
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

        /* Session Requests */
        .requests-container {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .request-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            border-bottom: 1px solid #eee;
        }

        .request-item:last-child {
            border-bottom: none;
        }

        .student-info {
            display: flex;
            align-items: center;
            flex: 1;
        }

        .student-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background-color: #3498db;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 15px;
            overflow: hidden;
        }

        .student-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .student-details h3 {
            font-size: 18px;
            margin-bottom: 5px;
        }

        .student-details p {
            font-size: 14px;
            color: #7f8c8d;
            margin-bottom: 3px;
        }

        .request-details {
            flex: 2;
            padding: 0 20px;
        }

        .request-details h4 {
            font-size: 16px;
            margin-bottom: 10px;
            color: #3498db;
        }

        .detail-item {
            display: flex;
            margin-bottom: 5px;
        }

        .detail-label {
            font-weight: 500;
            width: 100px;
            color: #555;
        }

        .detail-value {
            color: #333;
        }

        .request-actions {
            display: flex;
            gap: 10px;
        }

        .btn {
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            transition: background-color 0.3s;
        }

        .btn-accept {
            background-color: #2ecc71;
            color: white;
        }

        .btn-accept:hover {
            background-color: #27ae60;
        }

        .btn-decline {
            background-color: #e74c3c;
            color: white;
        }

        .btn-decline:hover {
            background-color: #c0392b;
        }

        .empty-state {
            padding: 40px 20px;
            text-align: center;
            color: #7f8c8d;
        }

        .empty-state i {
            font-size: 48px;
            margin-bottom: 15px;
            color: #bdc3c7;
        }

        .empty-state p {
            font-size: 18px;
            margin-bottom: 5px;
        }

        .empty-state small {
            font-size: 14px;
            color: #95a5a6;
        }

        /* Responsive Styles */
        @media (max-width: 992px) {
            .request-item {
                flex-direction: column;
                align-items: flex-start;
            }

            .student-info {
                margin-bottom: 15px;
            }

            .request-details {
                padding: 0;
                margin-bottom: 15px;
                width: 100%;
            }

            .request-actions {
                width: 100%;
                justify-content: flex-end;
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
            }

            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="profile-section">
            <div class="profile-avatar">
                <?php if(!empty($profile_photo) && file_exists($profile_photo)): ?>
                    <img src="<?php echo htmlspecialchars($profile_photo); ?>" alt="Profile Photo">
                <?php else: ?>
                    <?php echo substr($first_name, 0, 1); ?>
                <?php endif; ?>
            </div>
            <h3 class="profile-name"><?php echo htmlspecialchars($first_name . " " . $last_name); ?></h3>
            <p class="profile-title">Mentor</p>
            <form action="logout.php" method="post">
                <button type="submit" class="logout-btn">Logout</button>
            </form>
        </div>
        <div class="nav-menu">
            <a href="mentor-dashboard.php" class="nav-item">
                <span class="nav-icon"><i class="fas fa-tachometer-alt"></i></span> Dashboard
            </a>
            <a href="mentor-session-requests.php" class="nav-item active">
                <span class="nav-icon"><i class="fas fa-envelope"></i></span> Session Requests
            </a>
            <a href="mentor-upcoming-sessions.php" class="nav-item">
                <span class="nav-icon"><i class="fas fa-calendar-alt"></i></span> Upcoming Sessions
            </a>
            <a href="mentor-session-history.php" class="nav-item">
                <span class="nav-icon"><i class="fas fa-history"></i></span> Session History
            </a>
            <a href="mentor-profile-update.php" class="nav-item">
                <span class="nav-icon"><i class="fas fa-user"></i></span> Profile
            </a>
            <a href="mentor-availability.php" class="nav-item">
                <span class="nav-icon"><i class="fas fa-clock"></i></span> Availability
            </a>
            <a href="mentor-student-profiles.php" class="nav-item">
                <span class="nav-icon"><i class="fas fa-user-graduate"></i></span> Student Profiles
            </a>
            <a href="mentor-provide-feedback.php" class="nav-item">
                <span class="nav-icon"><i class="fas fa-comment"></i></span> Provide Feedback
            </a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="header">
            <h1 class="page-title">Session Requests</h1>
        </div>

        <?php if(isset($_GET['success']) && $_GET['success'] == 'accepted'): ?>
            <div class="alert alert-success">Session request has been accepted successfully.</div>
        <?php elseif(isset($_GET['success']) && $_GET['success'] == 'declined'): ?>
            <div class="alert alert-success">Session request has been declined.</div>
        <?php endif; ?>

        <?php if(isset($error_message)): ?>
            <div class="alert alert-danger"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <?php if(empty($session_requests)): ?>
            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <p>You have no pending session requests</p>
                <small>When students request mentoring sessions, they will appear here</small>
            </div>
        <?php else: ?>
            <div class="requests-container">
                <?php foreach($session_requests as $request): ?>
                    <div class="request-item">
                        <div class="student-info">
                            <div class="student-avatar">
                                <?php if(!empty($request['student_photo']) && file_exists($request['student_photo'])): ?>
                                    <img src="<?php echo htmlspecialchars($request['student_photo']); ?>" alt="Student Photo">
                                <?php else: ?>
                                    <?php echo substr($request['first_name'], 0, 1) . substr($request['last_name'], 0, 1); ?>
                                <?php endif; ?>
                            </div>
                            <div class="student-details">
                                <h3><?php echo htmlspecialchars($request['first_name'] . " " . $request['last_name']); ?></h3>
                                <p><?php echo htmlspecialchars($request['email']); ?></p>
                                <p>Requested: <?php echo date('F j, Y, g:i A', strtotime($request['created_at'])); ?></p>
                            </div>
                        </div>
                        <div class="request-details">
                            <h4><?php echo htmlspecialchars($request['topic']); ?></h4>
                            <div class="detail-item">
                                <div class="detail-label">Date:</div>
                                <div class="detail-value"><?php echo date('F j, Y', strtotime($request['session_date'])); ?></div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Time:</div>
                                <div class="detail-value"><?php echo date('g:i A', strtotime($request['start_time'])); ?> - <?php echo date('g:i A', strtotime($request['end_time'])); ?></div>
                            </div>
                            <?php if(!empty($request['notes'])): ?>
                                <div class="detail-item">
                                    <div class="detail-label">Notes:</div>
                                    <div class="detail-value"><?php echo htmlspecialchars($request['notes']); ?></div>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="request-actions">
                            <form method="post">
                                <input type="hidden" name="session_id" value="<?php echo $request['session_id']; ?>">
                                <button type="submit" name="accept_session" class="btn btn-accept">Accept</button>
                            </form>
                            <form method="post">
                                <input type="hidden" name="session_id" value="<?php echo $request['session_id']; ?>">
                                <button type="submit" name="decline_session" class="btn btn-decline">Decline</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>

