<?php
// Initialize the session
session_start();

// Check if the user is logged in, if not then redirect to login page
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["user_type"] !== "mentor"){
    header("location: login.php");
    exit;
}

// Include database connection
require_once "db_connect.php";

// Process session actions (accept/decline)
if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["action"]) && isset($_POST["session_id"])){
    $session_id = $_POST["session_id"];
    $action = $_POST["action"];
    
    if($action == "accept" || $action == "decline"){
        $status = ($action == "accept") ? "accepted" : "declined";
        
        // Update session status
        $sql = "UPDATE sessions SET status = ? WHERE session_id = ? AND mentor_id = ?";
        if($stmt = $conn->prepare($sql)){
            $stmt->bind_param("sii", $status, $session_id, $_SESSION["user_id"]);
            
            if($stmt->execute()){
                // Get student ID for notification
                $student_sql = "SELECT student_id, topic FROM sessions WHERE session_id = ?";
                $student_stmt = $conn->prepare($student_sql);
                $student_stmt->bind_param("i", $session_id);
                $student_stmt->execute();
                $student_stmt->bind_result($student_id, $topic);
                $student_stmt->fetch();
                $student_stmt->close();
                
                // Create notification for student
                $notification_type = "session_" . $action . "ed";
                $notification_message = "Your session request on '" . $topic . "' has been " . $action . "ed by " . $_SESSION["first_name"] . " " . $_SESSION["last_name"];
                
                $notification_sql = "INSERT INTO notifications (user_id, notification_type, message, related_id) VALUES (?, ?, ?, ?)";
                $notification_stmt = $conn->prepare($notification_sql);
                $notification_stmt->bind_param("issi", $student_id, $notification_type, $notification_message, $session_id);
                $notification_stmt->execute();
                $notification_stmt->close();
                
                // Redirect to refresh the page
                header("location: manage_sessions.php");
                exit;
            } else{
                echo "Oops! Something went wrong. Please try again later.";
            }
            
            $stmt->close();
        }
    }
}

// Get pending session requests
$pending_sessions = array();
$sql = "SELECT s.session_id, s.topic, s.session_date, s.start_time, s.end_time, 
               u.first_name, u.last_name, u.email 
        FROM sessions s 
        JOIN users u ON s.student_id = u.user_id 
        WHERE s.mentor_id = ? AND s.status = 'pending' 
        ORDER BY s.session_date, s.start_time";

$sql = "SELECT s.session_id, s.topic, s.session_date, s.start_time, s.end_time, 
               u.first_name, u.last_name, u.email 
        FROM sessions s 
        JOIN users u ON s.student_id = u.user_id 
        WHERE s.mentor_id = ? AND s.status = 'pending'
        ORDER BY s.session_date, s.start_time";

if($stmt = $conn->prepare($sql)){
    $stmt->bind_param("i", $_SESSION["user_id"]);
    $stmt->execute();
    $result = $stmt->get_result();

    while($row = $result->fetch_assoc()){
        $pending_sessions[] = $row;
    }

    $stmt->close();
}


// Get upcoming sessions
$upcoming_sessions = array();
$sql = "SELECT s.session_id, s.topic, s.session_date, s.start_time, s.end_time, 
               u.first_name, u.last_name, u.email 
        FROM sessions s 
        JOIN users u ON s.student_id = u.user_id 
        WHERE s.mentor_id = ? AND s.status = 'accepted' AND s.session_date >= CURDATE() 
        ORDER BY s.session_date, s.start_time";

if($stmt = $conn->prepare($sql)){
    $stmt->bind_param("i", $_SESSION["user_id"]);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while($row = $result->fetch_assoc()){
        $upcoming_sessions[] = $row;
    }
    
    $stmt->close();
}

// Get past sessions
$past_sessions = array();
$sql = "SELECT s.session_id, s.topic, s.session_date, s.start_time, s.end_time, 
               u.first_name, u.last_name, u.email, s.status
        FROM sessions s 
        JOIN users u ON s.student_id = u.user_id 
        WHERE s.mentor_id = ? AND (s.status = 'completed' OR (s.status = 'accepted' AND s.session_date < CURDATE())) 
        ORDER BY s.session_date DESC, s.start_time DESC 
        LIMIT 10";

if($stmt = $conn->prepare($sql)){
    $stmt->bind_param("i", $_SESSION["user_id"]);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while($row = $result->fetch_assoc()){
        $past_sessions[] = $row;
    }
    
    $stmt->close();
}

// Close connection
closeConnection($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Sessions - MentorConnect</title>
    <!-- Add your CSS here -->
</head>
<body>
    <div class="container">
        <h2>Manage Sessions</h2>
        
        <h3>Pending Session Requests</h3>
        <?php if(empty($pending_sessions)): ?>
            <p>No pending session requests.</p>
        <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Topic</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($pending_sessions as $session): ?>
                    <tr>
                        <td><?php echo $session['first_name'] . ' ' . $session['last_name']; ?></td>
                        <td><?php echo $session['topic']; ?></td>
                        <td><?php echo date('F j, Y', strtotime($session['session_date'])); ?></td>
                        <td><?php echo date('g:i A', strtotime($session['start_time'])) . ' - ' . date('g:i A', strtotime($session['end_time'])); ?></td>
                        <td>
                            <form method="post" style="display:inline;">
                                <input type="hidden" name="session_id" value="<?php echo $session['session_id']; ?>">
                                <input type="hidden" name="action" value="accept">
                                <button type="submit">Accept</button>
                            </form>
                            <form method="post" style="display:inline;">
                                <input type="hidden" name="session_id" value="<?php echo $session['session_id']; ?>">
                                <input type="hidden" name="action" value="decline">
                                <button type="submit">Decline</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        
        <h3>Upcoming Sessions</h3>
        <?php if(empty($upcoming_sessions)): ?>
            <p>No upcoming sessions.</p>
        <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Topic</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($upcoming_sessions as $session): ?>
                    <tr>
                        <td><?php echo $session['first_name'] . ' ' . $session['last_name']; ?></td>
                        <td><?php echo $session['topic']; ?></td>
                        <td><?php echo date('F j, Y', strtotime($session['session_date'])); ?></td>
                        <td><?php echo date('g:i A', strtotime($session['start_time'])) . ' - ' . date('g:i A', strtotime($session['end_time'])); ?></td>
                        <td>
                            <a href="session_details.php?id=<?php echo $session['session_id']; ?>">View Details</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        
        <h3>Past Sessions</h3>
        <?php if(empty($past_sessions)): ?>
            <p>No past sessions.</p>
        <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Topic</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($past_sessions as $session): ?>
                    <tr>
                        <td><?php echo $session['first_name'] . ' ' . $session['last_name']; ?></td>
                        <td><?php echo $session['topic']; ?></td>
                        <td><?php echo date('F j, Y', strtotime($session['session_date'])); ?></td>
                        <td><?php echo ucfirst($session['status']); ?></td>
                        <td>
                            <a href="provide_feedback.php?session_id=<?php echo $session['session_id']; ?>">Provide Feedback</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        
        <p><a href="mentor.php">Back to Dashboard</a></p>
    </div>
</body>
</html>