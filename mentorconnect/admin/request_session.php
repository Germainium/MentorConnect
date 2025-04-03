<?php
// Initialize the session
session_start();

// Check if the user is logged in, if not then redirect to login page
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["user_type"] !== "student"){
    header("location: login.php");
    exit;
}

// Include database connection
require_once "db_connect.php";

// Define variables and initialize with empty values
$mentor_id = $topic = $session_date = $start_time = "";
$mentor_id_err = $topic_err = $session_date_err = $start_time_err = "";
$success_message = "";

// Processing form data when form is submitted
if($_SERVER["REQUEST_METHOD"] == "POST"){
    
    // Validate mentor
    if(empty($_POST["mentor_id"])){
        $mentor_id_err = "Please select a mentor.";
    } else{
        $mentor_id = $_POST["mentor_id"];
    }
    
    // Validate topic
    if(empty(trim($_POST["topic"]))){
        $topic_err = "Please enter a topic.";
    } else{
        $topic = trim($_POST["topic"]);
    }
    
    // Validate session date
    if(empty($_POST["session_date"])){
        $session_date_err = "Please select a date.";
    } else{
        $session_date = $_POST["session_date"];
        
        // Check if date is in the future
        if(strtotime($session_date) < strtotime(date("Y-m-d"))){
            $session_date_err = "Please select a future date.";
        }
    }
    
    // Validate start time
    if(empty($_POST["start_time"])){
        $start_time_err = "Please select a time.";
    } else{
        $start_time = $_POST["start_time"];
    }
    
    // Calculate end time (1 hour after start time)
    $end_time = date('H:i:s', strtotime($start_time . ' + 1 hour'));
    
    // Check input errors before inserting in database
    if(empty($mentor_id_err) && empty($topic_err) && empty($session_date_err) && empty($start_time_err)){
        
        // Prepare an insert statement
        $sql = "INSERT INTO sessions (mentor_id, student_id, topic, session_date, start_time, end_time, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')";
         
        if($stmt = $conn->prepare($sql)){
            // Bind variables to the prepared statement as parameters
            $stmt->bind_param("iissss", $param_mentor_id, $param_student_id, $param_topic, $param_session_date, $param_start_time, $param_end_time);
            
            // Set parameters
            $param_mentor_id = $mentor_id;
            $param_student_id = $_SESSION["user_id"];
            $param_topic = $topic;
            $param_session_date = $session_date;
            $param_start_time = $start_time;
            $param_end_time = $end_time;
            
            // Attempt to execute the prepared statement
            if($stmt->execute()){
                // Create notification for mentor
                $notification_sql = "INSERT INTO notifications (user_id, notification_type, message, related_id) VALUES (?, 'session_request', ?, ?)";
                $notification_stmt = $conn->prepare($notification_sql);
                $notification_stmt->bind_param("isi", $mentor_id, $notification_message, $conn->insert_id);
                $notification_message = "You have a new session request from " . $_SESSION["first_name"] . " " . $_SESSION["last_name"] . " on " . $topic;
                $notification_stmt->execute();
                $notification_stmt->close();
                
                // Set success message
                $success_message = "Session request submitted successfully!";
                
                // Clear form fields
                $mentor_id = $topic = $session_date = $start_time = "";
            } else{
                echo "Oops! Something went wrong. Please try again later.";
            }

            // Close statement
            $stmt->close();
        }
    }
    
    // Close connection
    closeConnection($conn);
}

// Get list of mentors
$mentors = array();
$sql = "SELECT u.user_id, u.first_name, u.last_name, mp.expertise, mp.average_rating 
        FROM users u 
        JOIN mentor_profiles mp ON u.user_id = mp.mentor_id 
        WHERE u.user_type = 'mentor'";
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $mentors[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Session - MentorConnect</title>
    <!-- Add your CSS here -->
</head>
<body>
    <div class="container">
        <h2>Request a Mentoring Session</h2>
        
        <?php 
        if(!empty($success_message)){
            echo '<div class="alert alert-success">' . $success_message . '</div>';
        }        
        ?>
        
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <div class="form-group">
                <label>Select Mentor</label>
                <select name="mentor_id">
                    <option value="">Choose a mentor...</option>
                    <?php foreach($mentors as $mentor): ?>
                    <option value="<?php echo $mentor['user_id']; ?>" <?php if($mentor_id == $mentor['user_id']) echo "selected"; ?>>
                        <?php echo $mentor['first_name'] . ' ' . $mentor['last_name']; ?> - <?php echo $mentor['expertise']; ?> (Rating: <?php echo $mentor['average_rating']; ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
                <span class="error"><?php echo $mentor_id_err; ?></span>
            </div>
            <div class="form-group">
                <label>Topic</label>
                <input type="text" name="topic" value="<?php echo $topic; ?>">
                <span class="error"><?php echo $topic_err; ?></span>
            </div>
            <div class="form-group">
                <label>Session Date</label>
                <input type="date" name="session_date" value="<?php echo $session_date; ?>">
                <span class="error"><?php echo $session_date_err; ?></span>
            </div>
            <div class="form-group">
                <label>Start Time</label>
                <input type="time" name="start_time" value="<?php echo $start_time; ?>">
                <span class="error"><?php echo $start_time_err; ?></span>
            </div>
            <div class="form-group">
                <input type="submit" value="Request Session">
                <a href="student.php">Cancel</a>
            </div>
        </form>
    </div>
</body>
</html>