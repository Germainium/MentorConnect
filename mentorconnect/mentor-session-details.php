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

// Check if session ID is provided
if(!isset($_GET["id"]) || empty($_GET["id"])){
    header("location: mentor.php?section=upcoming-sessions&error=invalid_session");
    exit;
}

$session_id = $_GET["id"];

// Get session details
$sql = "SELECT s.*, u.first_name, u.last_name, u.email, sp.profile_photo as student_photo
        FROM sessions s 
        JOIN users u ON s.student_id = u.user_id 
        LEFT JOIN student_profiles sp ON u.user_id = sp.student_id
        WHERE s.session_id = ? AND s.mentor_id = ?";
if($stmt = $conn->prepare($sql)){
    $stmt->bind_param("ii", $session_id, $mentor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if($result->num_rows != 1){
        // Session not found
        header("location: mentor.php?section=upcoming-sessions&error=invalid_session");
        exit;
    }
    
    $session = $result->fetch_assoc();
    $stmt->close();
}

// Get student profile details
$student_id = $session['student_id'];
$student_profile = array();
$sql = "SELECT * FROM student_profiles WHERE student_id = ?";
if($stmt = $conn->prepare($sql)){
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if($result->num_rows == 1){
        $student_profile = $result->fetch_assoc();
    }
    $stmt->close();
}

// Get previous sessions with this student
$previous_sessions = array();
$sql = "SELECT * FROM sessions 
        WHERE mentor_id = ? AND student_id = ? AND session_id != ? AND status = 'completed'
        ORDER BY session_date DESC";
if($stmt = $conn->prepare($sql)){
    $stmt->bind_param("iii", $mentor_id, $student_id, $session_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while($row = $result->fetch_assoc()){
        $previous_sessions[] = $row;
    }
    $stmt->close();
}

// Process session completion
if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["complete_session"])){
    // Update session status
    $sql = "UPDATE sessions SET status = 'completed' WHERE session_id = ? AND mentor_id = ?";
    if($stmt = $conn->prepare($sql)){
        $stmt->bind_param("ii", $session_id, $mentor_id);
        
        if($stmt->execute()){
            // Create notification for student
            $notification_sql = "INSERT INTO notifications (user_id, notification_type, message, related_id) 
                                VALUES (?, 'session_completed', ?, ?)";
            $notification_stmt = $conn->prepare($notification_sql);
            $notification_message = $_SESSION["first_name"] . " " . $_SESSION["last_name"] . " has marked your session as completed. Please provide feedback.";
            $notification_stmt->bind_param("isi", $student_id, $notification_message, $session_id);
            $notification_stmt->execute();
            $notification_stmt->close();
            
            // Redirect to upcoming sessions
            header("location: mentor.php?section=upcoming-sessions&success=completed");
            exit;
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
    <title>Session Details - MentorConnect</title>
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
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .header {
            background-color: #2c3e50;
            color: white;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header-title {
            font-size: 20px;
            font-weight: bold;
        }

        .header-actions a {
            color: white;
            text-decoration: none;
            margin-left: 15px;
        }

        .container {
            max-width: 1000px;
            margin: 20px auto;
            padding: 0 20px;
            flex: 1;
        }

        .card {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            padding: 20px;
            margin-bottom: 20px;
        }

        .session-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }

        .session-title {
            font-size: 24px;
            font-weight: bold;
        }

        .session-status {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
        }

        .status-accepted {
            background-color: #d4edda;
            color: #155724;
        }

        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }

        .status-completed {
            background-color: #cce5ff;
            color: #004085;
        }

        .session-details {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .detail-item {
            margin-bottom: 10px;
        }

        .detail-label {
            font-weight: bold;
            color: #7f8c8d;
            margin-bottom: 5px;
            font-size: 14px;
        }

        .detail-value {
            font-size: 16px;
        }

        .student-info {
            display: flex;
            margin-bottom: 20px;
        }

        .student-avatar {
            width: 80px;
            height: 80px;
            background-color: #3498db;
            border-radius: 50%;
            margin-right: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 30px;
            overflow: hidden;
        }

        .student-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .student-details {
            flex: 1;
        }

        .student-name {
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .student-email {
            color: #7f8c8d;
            margin-bottom: 10px;
        }

        .student-profile {
            margin-top: 10px;
        }

        .profile-item {
            margin-bottom: 10px;
        }

        .profile-label {
            font-weight: bold;
            margin-bottom: 5px;
        }

        .section-title {
            font-size: 18px;
            margin-bottom: 15px;
            color: #2c3e50;
            font-weight: bold;
        }

        .session-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        .btn {
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
        }

        .btn-primary {
            background-color: #3498db;
            color: white;
        }

        .btn-success {
            background-color: #2ecc71;
            color: white;
        }

        .btn-danger {
            background-color: #e74c3c;
            color: white;
        }

        .previous-sessions {
            margin-top: 20px;
        }

        .session-item {
            padding: 15px;
            border-bottom: 1px solid #eee;
        }

        .session-item:last-child {
            border-bottom: none;
        }

        .session-date {
            font-weight: bold;
            margin-bottom: 5px;
        }

        .session-topic {
            color: #3498db;
            margin-bottom: 5px;
        }

        .session-notes {
            font-size: 14px;
            color: #7f8c8d;
        }

        @media (max-width: 768px) {
            .session-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .session-status {
                margin-top: 10px;
            }

            .session-details {
                grid-template-columns: 1fr;
            }

            .student-info {
                flex-direction: column;
            }

            .student-avatar {
                margin-bottom: 15px;
                margin-right: 0;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-title">Session Details</div>
        <div class="header-actions">
            <a href="mentor.php?section=upcoming-sessions"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
        </div>
    </div>

    <div class="container">
        <div class="card">
            <div class="session-header">
                <h1 class="session-title"><?php echo htmlspecialchars($session['topic']); ?></h1>
                <div class="session-status status-<?php echo $session['status']; ?>">
                    <?php echo ucfirst($session['status']); ?>
                </div>
            </div>

            <div class="session-details">
                <div>
                    <div class="detail-item">
                        <div class="detail-label">Date</div>
                        <div class="detail-value"><?php echo date('F j, Y', strtotime($session['session_date'])); ?></div>
                    </div>
                </div>
                <div>
                    <div class="detail-item">
                        <div class="detail-label">Time</div>
                        <div class="detail-value"><?php echo date('g:i A', strtotime($session['start_time'])); ?> - <?php echo date('g:i A', strtotime($session['end_time'])); ?></div>
                    </div>
                </div>
                <div>
                    <div class="detail-item">
                        <div class="detail-label">Duration</div>
                        <div class="detail-value">1 hour</div>
                    </div>
                </div>
                <div>
                    <div class="detail-item">
                        <div class="detail-label">Created</div>
                        <div class="detail-value"><?php echo date('F j, Y', strtotime($session['created_at'])); ?></div>
                    </div>
                </div>
            </div>

            <?php if(!empty($session['notes'])): ?>
                <div class="detail-item">
                    <div class="detail-label">Session Notes</div>
                    <div class="detail-value"><?php echo htmlspecialchars($session['notes']); ?></div>
                </div>
            <?php endif; ?>

            <div class="session-actions">
                <?php if($session['status'] == 'accepted' && date('Y-m-d') == $session['session_date'] && (strtotime($session['start_time']) - time() < 3600) && (strtotime($session['start_time']) - time() > 0)): ?>
                    <a href="mentor-start-session.php?id=<?php echo $session_id; ?>" class="btn btn-primary">Start Session</a>
                <?php endif; ?>
                
                <?php if($session['status'] == 'accepted'): ?>
                    <form method="post">
                        <input type="hidden" name="session_id" value="<?php echo $session_id; ?>">
                        <button type="submit" name="complete_session" class="btn btn-success" onclick="return confirm('Are you sure you want to mark this session as completed?')">Mark as Completed</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <h2 class="section-title">Student Information</h2>
            <div class="student-info">
                <div class="student-avatar">
                    <?php if(!empty($session['student_photo']) && file_exists($session['student_photo'])): ?>
                        <img src="<?php echo htmlspecialchars($session['student_photo']); ?>" alt="Student Photo">
                    <?php else: ?>
                        <?php echo substr($session['first_name'], 0, 1) . substr($session['last_name'], 0, 1); ?>
                    <?php endif; ?>
                </div>
                <div class="student-details">
                    <div class="student-name"><?php echo htmlspecialchars($session['first_name'] . ' ' . $session['last_name']); ?></div>
                    <div class="student-email"><?php echo htmlspecialchars($session['email']); ?></div>
                    
                    <div class="student-profile">
                        <?php if(!empty($student_profile)): ?>
                            <?php if(!empty($student_profile['career_goals'])): ?>
                                <div class="profile-item">
                                    <div class="profile-label">Career Goals</div>
                                    <div><?php echo htmlspecialchars($student_profile['career_goals']); ?></div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if(!empty($student_profile['skills'])): ?>
                                <div class="profile-item">
                                    <div class="profile-label">Skills</div>
                                    <div><?php echo htmlspecialchars($student_profile['skills']); ?></div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if(!empty($student_profile['education'])): ?>
                                <div class="profile-item">
                                    <div class="profile-label">Education</div>
                                    <div><?php echo htmlspecialchars($student_profile['education']); ?></div>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <p>No additional profile information available.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <?php if(!empty($previous_sessions)): ?>
            <div class="card">
                <h2 class="section-title">Previous Sessions with this Student</h2>
                <div class="previous-sessions">
                    <?php foreach($previous_sessions as $prev_session): ?>
                        <div class="session-item">
                            <div class="session-date"><?php echo date('F j, Y', strtotime($prev_session['session_date'])); ?></div>
                            <div class="session-topic"><?php echo htmlspecialchars($prev_session['topic']); ?></div>
                            <?php if(!empty($prev_session['notes'])): ?>
                                <div class="session-notes"><?php echo htmlspecialchars($prev_session['notes']); ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>

