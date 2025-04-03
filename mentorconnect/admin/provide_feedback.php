<?php
// Initialize the session
session_start();

// Check if the user is logged in as a student
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["user_type"] !== "student"){
    header("location: login.php");
    exit;
}

// Include database connection
require_once "db_connect.php";

// Define variables and initialize with empty values
$session_id = $rating = $comments = $strengths = $areas_for_improvement = "";
$session_id_err = $rating_err = $comments_err = "";
$success_message = $error_message = "";

// Check if session_id is provided in URL
if(isset($_GET["session_id"]) && !empty(trim($_GET["session_id"]))){
    // Get session ID from URL
    $session_id = trim($_GET["session_id"]);
    
    // Check if feedback already exists for this session
    $sql = "SELECT * FROM feedback WHERE session_id = ? AND from_user_id = ?";
    if($stmt = $conn->prepare($sql)){
        $stmt->bind_param("ii", $session_id, $_SESSION["user_id"]);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if($result->num_rows > 0){
            // Feedback already exists
            $error_message = "You have already provided feedback for this session.";
        } else {
            // Verify that the session belongs to the current student and is completed
            $sql = "SELECT s.*, u.first_name, u.last_name 
                    FROM sessions s 
                    JOIN users u ON s.mentor_id = u.user_id 
                    WHERE s.session_id = ? AND s.student_id = ? AND s.status = 'completed'";
            
            if($stmt = $conn->prepare($sql)){
                $stmt->bind_param("ii", $session_id, $_SESSION["user_id"]);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if($result->num_rows == 0){
                    // Session not found or not eligible for feedback
                    $error_message = "Invalid session or session not eligible for feedback.";
                } else {
                    // Get session details
                    $session = $result->fetch_assoc();
                }
                
                $stmt->close();
            }
        }
        
        $stmt->close();
    }
} else {
    // No session ID provided
    $error_message = "No session specified.";
}

// Processing form data when form is submitted
if($_SERVER["REQUEST_METHOD"] == "POST"){
    
    // Validate session ID
    if(empty(trim($_POST["session_id"]))){
        $session_id_err = "Invalid session.";
    } else {
        $session_id = trim($_POST["session_id"]);
    }
    
    // Validate rating
    if(empty(trim($_POST["rating"]))){
        $rating_err = "Please select a rating.";
    } else {
        $rating = trim($_POST["rating"]);
        // Check if rating is between 1 and 5
        if($rating < 1 || $rating > 5){
            $rating_err = "Rating must be between 1 and 5.";
        }
    }
    
    // Validate comments
    if(empty(trim($_POST["comments"]))){
        $comments_err = "Please enter your comments.";
    } else {
        $comments = trim($_POST["comments"]);
    }
    
    // Get optional fields
    $strengths = trim($_POST["strengths"]);
    $areas_for_improvement = trim($_POST["areas_for_improvement"]);
    
    // Check input errors before inserting in database
    if(empty($session_id_err) && empty($rating_err) && empty($comments_err)){
        
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
                $sql = "INSERT INTO feedback (session_id, from_user_id, to_user_id, rating, comments, strengths, areas_for_improvement) 
                        VALUES (?, ?, ?, ?, ?, ?, ?)";
                
                if($stmt = $conn->prepare($sql)){
                    $stmt->bind_param("iiissss", $session_id, $_SESSION["user_id"], $mentor_id, $rating, $comments, $strengths, $areas_for_improvement);
                    
                    if($stmt->execute()){
                        // Update session status to indicate feedback provided
                        $sql = "UPDATE sessions SET status = 'completed' WHERE session_id = ?";
                        $update_stmt = $conn->prepare($sql);
                        $update_stmt->bind_param("i", $session_id);
                        $update_stmt->execute();
                        $update_stmt->close();
                        
                        // Update mentor's average rating
                        $sql = "UPDATE mentor_profiles 
                                SET average_rating = (
                                    SELECT AVG(rating) 
                                    FROM feedback 
                                    WHERE to_user_id = ?
                                ) 
                                WHERE mentor_id = ?";
                        $rating_stmt = $conn->prepare($sql);
                        $rating_stmt->bind_param("ii", $mentor_id, $mentor_id);
                        $rating_stmt->execute();
                        $rating_stmt->close();
                        
                        // Create notification for mentor
                        $notification_sql = "INSERT INTO notifications (user_id, notification_type, message, related_id) 
                                            VALUES (?, 'feedback_received', ?, ?)";
                        $notification_stmt = $conn->prepare($notification_sql);
                        $notification_message = $_SESSION["first_name"] . " " . $_SESSION["last_name"] . " has provided feedback for your session.";
                        $notification_stmt->bind_param("isi", $mentor_id, $notification_message, $session_id);
                        $notification_stmt->execute();
                        $notification_stmt->close();
                        
                        // Set success message
                        $success_message = "Thank you for your feedback!";
                        
                        // Clear form data
                        $rating = $comments = $strengths = $areas_for_improvement = "";
                    } else {
                        $error_message = "Something went wrong. Please try again later.";
                    }
                    
                    $stmt->close();
                }
            } else {
                $error_message = "Invalid session.";
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
    <title>Provide Feedback - MentorConnect</title>
    <style>
        /* Basic styling for the form */
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        h2 {
            color: #333;
            margin-bottom: 20px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        input[type="text"], textarea, select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
        }
        textarea {
            height: 100px;
        }
        .rating {
            display: flex;
            flex-direction: row-reverse;
            justify-content: flex-end;
        }
        .rating input {
            display: none;
        }
        .rating label {
            cursor: pointer;
            width: 40px;
            height: 40px;
            margin: 0;
            padding: 0;
            font-size: 30px;
            text-align: center;
            color: #ddd;
        }
        .rating label:before {
            content: "★";
        }
        .rating input:checked ~ label {
            color: #f1c40f;
        }
        .rating label:hover,
        .rating label:hover ~ label {
            color: #f1c40f;
        }
        .error {
            color: #e74c3c;
            font-size: 14px;
            margin-top: 5px;
        }
        .success {
            color: #2ecc71;
            padding: 10px;
            background-color: #e8f8f5;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .error-message {
            color: #e74c3c;
            padding: 10px;
            background-color: #fdedec;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .btn {
            display: inline-block;
            background-color: #3498db;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            text-decoration: none;
        }
        .btn:hover {
            background-color: #2980b9;
        }
        .btn-secondary {
            background-color: #95a5a6;
        }
        .btn-secondary:hover {
            background-color: #7f8c8d;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Provide Session Feedback</h2>
        
        <?php if(!empty($success_message)): ?>
            <div class="success"><?php echo $success_message; ?></div>
            <p><a href="student.php" class="btn">Return to Dashboard</a></p>
        <?php elseif(!empty($error_message)): ?>
            <div class="error-message"><?php echo $error_message; ?></div>
            <p><a href="student.php" class="btn">Return to Dashboard</a></p>
        <?php else: ?>
            <p>Please provide your feedback for the session with <strong><?php echo $session['first_name'] . ' ' . $session['last_name']; ?></strong> on <strong><?php echo date('F j, Y', strtotime($session['session_date'])); ?></strong>.</p>
            
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                <input type="hidden" name="session_id" value="<?php echo $session_id; ?>">
                
                <div class="form-group">
                    <label>How would you rate this session?</label>
                    <div class="rating">
                        <input type="radio" id="star5" name="rating" value="5" <?php if($rating == "5") echo "checked"; ?>>
                        <label for="star5" title="5 stars"></label>
                        <input type="radio" id="star4" name="rating" value="4" <?php if($rating == "4") echo "checked"; ?>>
                        <label for="star4" title="4 stars"></label>
                        <input type="radio" id="star3" name="rating" value="3" <?php if($rating == "3") echo "checked"; ?>>
                        <label for="star3" title="3 stars"></label>
                        <input type="radio" id="star2" name="rating" value="2" <?php if($rating == "2") echo "checked"; ?>>
                        <label for="star2" title="2 stars"></label>
                        <input type="radio" id="star1" name="rating" value="1" <?php if($rating == "1") echo "checked"; ?>>
                        <label for="star1" title="1 star"></label>
                    </div>
                    <span class="error"><?php echo $rating_err; ?></span>
                </div>
                
                <div class="form-group">
                    <label for="comments">Comments</label>
                    <textarea id="comments" name="comments" placeholder="Share your thoughts about this mentorship session..."><?php echo $comments; ?></textarea>
                    <span class="error"><?php echo $comments_err; ?></span>
                </div>
                
                <div class="form-group">
                    <label for="strengths">What was most valuable about this session?</label>
                    <textarea id="strengths" name="strengths" placeholder="The most useful aspect of this session was..."><?php echo $strengths; ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="areas_for_improvement">What could be improved?</label>
                    <textarea id="areas_for_improvement" name="areas_for_improvement" placeholder="Something that could have been better..."><?php echo $areas_for_improvement; ?></textarea>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn">Submit Feedback</button>
                    <a href="student.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>