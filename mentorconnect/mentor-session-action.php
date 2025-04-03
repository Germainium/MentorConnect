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
                
                // Redirect back to mentor page
                header("location: mentor.php?section=session-requests&success=accepted");
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
                
                // Redirect back to mentor page
                header("location: mentor.php?section=session-requests&success=declined");
                exit;
            } else {
                $error_message = "Something went wrong. Please try again.";
            }
            $stmt->close();
        }
    }
}

// If we get here, something went wrong
header("location: mentor.php?section=session-requests&error=1");
exit;
?>

