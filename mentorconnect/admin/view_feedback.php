<?php
// Initialize the session
session_start();

// Check if the user is logged in as a mentor
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["user_type"] !== "mentor"){
    header("location: login.php");
    exit;
}

// Include database connection
require_once "db_connect.php";

// Get mentor's average rating
$average_rating = 0;
$sql = "SELECT average_rating FROM mentor_profiles WHERE mentor_id = ?";
if($stmt = $conn->prepare($sql)){
    $stmt->bind_param("i", $_SESSION["user_id"]);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if($result->num_rows == 1){
        $row = $result->fetch_assoc();
        $average_rating = $row["average_rating"];
    }
    
    $stmt->close();
}

// Get feedback received by the mentor
$feedback_list = array();
$sql = "SELECT f.*, s.topic, s.session_date, u.first_name, u.last_name 
        FROM feedback f 
        JOIN sessions s ON f.session_id = s.session_id 
        JOIN users u ON f.from_user_id = u.user_id 
        WHERE f.to_user_id = ? 
        ORDER BY f.created_at DESC";

if($stmt = $conn->prepare($sql)){
    $stmt->bind_param("i", $_SESSION["user_id"]);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while($row = $result->fetch_assoc()){
        $feedback_list[] = $row;
    }
    
    $stmt->close();
}

// Get feedback statistics
$stats = array();

// Count by rating
for($i = 1; $i <= 5; $i++){
    $sql = "SELECT COUNT(*) as count FROM feedback WHERE to_user_id = ? AND rating = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $_SESSION["user_id"], $i);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stats['rating_' . $i] = $row['count'];
    $stmt->close();
}

// Total feedback count
$stats['total'] = array_sum([$stats['rating_1'], $stats['rating_2'], $stats['rating_3'], $stats['rating_4'], $stats['rating_5']]);

// Calculate percentages for each rating
for($i = 1; $i <= 5; $i++){
    $stats['percent_' . $i] = $stats['total'] > 0 ? round(($stats['rating_' . $i] / $stats['total']) * 100) : 0;
}

// Close connection
closeConnection($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Feedback - MentorConnect</title>
    <style>
        /* Basic styling for the page */
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        h2, h3 {
            color: #333;
        }
        .stats-container {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            flex: 1;
            min-width: 200px;
            background-color: #f9f9f9;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            text-align: center;
        }
        .rating-value {
            font-size: 36px;
            font-weight: bold;
            color: #3498db;
            margin: 10px 0;
        }
        .rating-stars {
            color: #f1c40f;
            font-size: 24px;
            margin-bottom: 10px;
        }
        .rating-label {
            color: #7f8c8d;
            font-size: 14px;
        }
        .rating-bars {
            margin-bottom: 30px;
        }
        .rating-bar {
            margin-bottom: 10px;
        }
        .rating-bar-label {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
        }
        .rating-bar-track {
            height: 10px;
            background-color: #ecf0f1;
            border-radius: 5px;
            overflow: hidden;
        }
        .rating-bar-fill {
            height: 100%;
            background-color: #3498db;
            border-radius: 5px;
        }
        .feedback-list {
            margin-top: 20px;
        }
        .feedback-item {
            background-color: #f9f9f9;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        .feedback-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        .student-name {
            font-weight: bold;
        }
        .session-info {
            color: #7f8c8d;
            font-size: 14px;
        }
        .feedback-rating {
            color: #f1c40f;
            margin-bottom: 10px;
        }
        .feedback-section {
            margin-bottom: 15px;
        }
        .feedback-section h4 {
            margin-bottom: 5px;
            color: #555;
        }
        .feedback-section p {
            margin: 0;
            color: #333;
        }
        .feedback-date {
            color: #95a5a6;
            font-size: 12px;
            text-align: right;
            margin-top: 10px;
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
        .no-feedback {
            text-align: center;
            padding: 30px;
            color: #7f8c8d;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Your Feedback</h2>
        
        <div class="stats-container">
            <div class="stat-card">
                <div class="rating-label">Average Rating</div>
                <div class="rating-value"><?php echo number_format($average_rating, 1); ?></div>
                <div class="rating-stars">
                    <?php
                    $full_stars = floor($average_rating);
                    $half_star = $average_rating - $full_stars >= 0.5;
                    
                    for($i = 1; $i <= 5; $i++){
                        if($i <= $full_stars){
                            echo "★"; // Full star
                        } elseif($i == $full_stars + 1 && $half_star){
                            echo "★"; // Half star (using full star for simplicity)
                        } else {
                            echo "☆"; // Empty star
                        }
                    }
                    ?>
                </div>
                <div class="rating-label">Based on <?php echo $stats['total']; ?> reviews</div>
            </div>
            
            <div class="stat-card">
                <div class="rating-bars">
                    <?php for($i = 5; $i >= 1; $i--): ?>
                    <div class="rating-bar">
                        <div class="rating-bar-label">
                            <span><?php echo $i; ?> stars</span>
                            <span><?php echo $stats['rating_' . $i]; ?> reviews</span>
                        </div>
                        <div class="rating-bar-track">
                            <div class="rating-bar-fill" style="width: <?php echo $stats['percent_' . $i]; ?>%"></div>
                        </div>
                    </div>
                    <?php endfor; ?>
                </div>
            </div>
        </div>
        
        <h3>Recent Feedback</h3>
        
        <div class="feedback-list">
            <?php if(empty($feedback_list)): ?>
                <div class="no-feedback">
                    <p>You haven't received any feedback yet.</p>
                </div>
            <?php else: ?>
                <?php foreach($feedback_list as $feedback): ?>
                <div class="feedback-item">
                    <div class="feedback-header">
                        <div class="student-name"><?php echo $feedback['first_name'] . ' ' . $feedback['last_name']; ?></div>
                        <div class="session-info"><?php echo $feedback['topic']; ?> • <?php echo date('F j, Y', strtotime($feedback['session_date'])); ?></div>
                    </div>
                    
                    <div class="feedback-rating">
                        <?php
                        for($i = 1; $i <= 5; $i++){
                            if($i <= $feedback['rating']){
                                echo "★"; // Full star
                            } else {
                                echo "☆"; // Empty star
                            }
                        }
                        ?>
                    </div>
                    
                    <div class="feedback-section">
                        <h4>Comments</h4>
                        <p><?php echo nl2br(htmlspecialchars($feedback['comments'])); ?></p>
                    </div>
                    
                    <?php if(!empty($feedback['strengths'])): ?>
                    <div class="feedback-section">
                        <h4>What Was Most Valuable</h4>
                        <p><?php echo nl2br(htmlspecialchars($feedback['strengths'])); ?></p>
                    </div>
                    <?php endif; ?>
                    
                    <?php if(!empty($feedback['areas_for_improvement'])): ?>
                    <div class="feedback-section">
                        <h4>Areas for Improvement</h4>
                        <p><?php echo nl2br(htmlspecialchars($feedback['areas_for_improvement'])); ?></p>
                    </div>
                    <?php endif; ?>
                    
                    <div class="feedback-date">
                        Received on <?php echo date('F j, Y, g:i a', strtotime($feedback['created_at'])); ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <p><a href="mentor.php" class="btn">Back to Dashboard</a></p>
    </div>
</body>
</html>