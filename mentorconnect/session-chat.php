<?php
// Initialize the session
session_start();

// Set a default timezone to avoid warnings
date_default_timezone_set('UTC');

// Check if the user is logged in
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
  header("location: login.php");
  exit;
}

// Include database connection
require_once "config/db_connect.php";

// Get user information
$user_id = $_SESSION["user_id"];
$user_type = $_SESSION["user_type"];
$first_name = $_SESSION["first_name"];
$last_name = $_SESSION["last_name"];

// Check if session_id is provided
if(!isset($_GET["session_id"]) || empty($_GET["session_id"])){
  header("location: " . $user_type . ".php");
  exit;
}

$session_id = $_GET["session_id"];

// Verify that the user is part of this session
$sql = "SELECT s.*, 
      u1.first_name as student_first_name, u1.last_name as student_last_name, u1.user_id as student_id,
      u2.first_name as mentor_first_name, u2.last_name as mentor_last_name, u2.user_id as mentor_id
      FROM sessions s
      JOIN users u1 ON s.student_id = u1.user_id
      JOIN users u2 ON s.mentor_id = u2.user_id
      WHERE s.session_id = ? AND (s.student_id = ? OR s.mentor_id = ?)";

if($stmt = $conn->prepare($sql)){
  $stmt->bind_param("iii", $session_id, $user_id, $user_id);
  $stmt->execute();
  $result = $stmt->get_result();
  
  if($result->num_rows == 0){
      // User is not part of this session
      header("location: " . $user_type . ".php");
      exit;
  }
  
  $session = $result->fetch_assoc();
  $stmt->close();
}

// Determine the other user in the conversation
if($user_id == $session["student_id"]){
  $other_user_id = $session["mentor_id"];
  $other_user_name = $session["mentor_first_name"] . " " . $session["mentor_last_name"];
  $other_user_type = "mentor";
  $other_user_avatar = substr($session["mentor_first_name"], 0, 1) . substr($session["mentor_last_name"], 0, 1);
} else {
  $other_user_id = $session["student_id"];
  $other_user_name = $session["student_first_name"] . " " . $session["student_last_name"];
  $other_user_type = "student";
  $other_user_avatar = substr($session["student_first_name"], 0, 1) . substr($session["student_last_name"], 0, 1);
}

// Process message sending
if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["send_message"])){
  $message_text = trim($_POST["message_text"]);
  
  if(!empty($message_text)){
      // Insert message into database - updated to match the database schema
      $sql = "INSERT INTO messages (sender_id, receiver_id, message_text, is_read, created_at) 
              VALUES (?, ?, ?, 0, NOW())";
      
      if($stmt = $conn->prepare($sql)){
          $stmt->bind_param("iis", $user_id, $other_user_id, $message_text);
          
          if(!$stmt->execute()){
              $message_error = "Something went wrong. Please try again.";
          }
          $stmt->close();
      }
  }
}

// Process end session
if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["end_session"])){
  // Update session status to completed
  $sql = "UPDATE sessions SET status = 'completed' WHERE session_id = ?";
  
  if($stmt = $conn->prepare($sql)){
      $stmt->bind_param("i", $session_id);
      
      if($stmt->execute()){
          // Redirect to dashboard
          header("location: " . $user_type . ".php?session_completed=1");
          exit;
      } else {
          $end_session_error = "Something went wrong. Please try again.";
      }
      $stmt->close();
  }
}

// Get messages for this conversation - updated to match the database schema
$messages = array();
$sql = "SELECT m.*, u.first_name, u.last_name, u.user_type 
      FROM messages m
      JOIN users u ON m.sender_id = u.user_id
      WHERE (m.sender_id = ? AND m.receiver_id = ?) 
         OR (m.sender_id = ? AND m.receiver_id = ?)
      ORDER BY m.created_at ASC";

if($stmt = $conn->prepare($sql)){
  $stmt->bind_param("iiii", $user_id, $other_user_id, $other_user_id, $user_id);
  $stmt->execute();
  $result = $stmt->get_result();
  
  while($row = $result->fetch_assoc()){
      $messages[] = $row;
  }
  $stmt->close();
}

// Mark messages as read - updated to match the database schema
$sql = "UPDATE messages SET is_read = 1 
      WHERE sender_id = ? AND receiver_id = ? AND is_read = 0";

if($stmt = $conn->prepare($sql)){
  $stmt->bind_param("ii", $other_user_id, $user_id);
  $stmt->execute();
  $stmt->close();
}

// Close connection
$conn->close();
?>

<!DOCTYPE html>
// Close connection
$conn->close();
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Session Chat - MentorConnect</title>
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
  height: 100vh;
  display: flex;
  flex-direction: column;
}

/* Header */
.chat-header {
  background-color: #2c3e50;
  color: white;
  padding: 15px;
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.user-info {
  display: flex;
  align-items: center;
}

.user-avatar {
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

.user-details h3 {
  font-size: 16px;
  margin-bottom: 5px;
}

.user-details p {
  font-size: 14px;
  color: #ecf0f1;
}

.header-actions {
  display: flex;
  gap: 10px;
}

.back-button, .end-session-button {
  background-color: transparent;
  color: white;
  border: none;
  cursor: pointer;
  font-size: 16px;
  display: flex;
  align-items: center;
  padding: 8px 15px;
  border-radius: 4px;
}

.back-button:hover {
  background-color: rgba(255, 255, 255, 0.1);
}

.end-session-button {
  background-color: #e74c3c;
}

.end-session-button:hover {
  background-color: #c0392b;
}

/* Chat Container */
.chat-container {
  flex: 1;
  display: flex;
  flex-direction: column;
  max-width: 1200px;
  margin: 0 auto;
  width: 100%;
  background-color: white;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
}

/* Messages Area */
.messages-area {
  flex: 1;
  overflow-y: auto;
  padding: 20px;
}

.message {
  margin-bottom: 15px;
  max-width: 70%;
  padding: 10px 15px;
  border-radius: 18px;
  position: relative;
  clear: both;
}

.message.sent {
  background-color: #3498db;
  color: white;
  float: right;
  border-bottom-right-radius: 5px;
}

.message.received {
  background-color: #f0f0f0;
  color: #333;
  float: left;
  border-bottom-left-radius: 5px;
}

.message-sender {
  font-size: 12px;
  font-weight: bold;
  margin-bottom: 5px;
}

.message-time {
  font-size: 10px;
  color: rgba(255, 255, 255, 0.7);
  text-align: right;
  margin-top: 5px;
}

.message.received .message-time {
  color: #999;
}

.message-text {
  word-wrap: break-word;
}

/* Message Form */
.message-form {
  padding: 15px;
  border-top: 1px solid #eee;
  display: flex;
  background-color: white;
}

.message-input {
  flex: 1;
  padding: 10px 15px;
  border: 1px solid #ddd;
  border-radius: 30px;
  font-size: 14px;
  outline: none;
}

.send-button {
  background-color: #3498db;
  color: white;
  border: none;
  border-radius: 30px;
  padding: 10px 20px;
  margin-left: 10px;
  cursor: pointer;
  font-size: 14px;
}

.send-button:hover {
  background-color: #2980b9;
}

/* Session Info */
.session-info {
  background-color: #f9f9f9;
  padding: 10px 15px;
  border-bottom: 1px solid #eee;
  text-align: center;
  font-size: 14px;
  color: #666;
}

/* Clearfix */
.clearfix::after {
  content: "";
  clear: both;
  display: table;
}

/* Alert Messages */
.alert {
  padding: 10px 15px;
  margin-bottom: 10px;
  border-radius: 4px;
  font-size: 14px;
}

.alert-danger {
  background-color: #f8d7da;
  color: #721c24;
  border: 1px solid #f5c6cb;
}

/* Responsive Styles */
@media (max-width: 768px) {
  .message {
      max-width: 85%;
  }
  
  .header-actions {
    flex-direction: column;
    gap: 5px;
  }
}
</style>
</head>
<body>
  <div class="chat-container">
      <!-- Header -->
      <div class="chat-header">
          <div class="user-info">
              <div class="user-avatar"><?php echo $other_user_avatar; ?></div>
              <div class="user-details">
                  <h3><?php echo $other_user_name; ?></h3>
                  <p><?php echo ucfirst($other_user_type); ?></p>
              </div>
          </div>
          <div class="header-actions">
              <a href="<?php echo $user_type; ?>.php" class="back-button">
                  ← Back to Dashboard
              </a>
              <form method="post" style="display: inline;">
                  <button type="submit" name="end_session" class="end-session-button" onclick="return confirm('Are you sure you want to end this session? This will mark it as completed.')">
                      End Session
                  </button>
              </form>
          </div>
      </div>

      <!-- Session Info -->
      <div class="session-info">
          <p><strong>Session:</strong> <?php echo $session["topic"]; ?> • <?php echo date('F j, Y', strtotime($session["session_date"])); ?>, <?php echo date('g:i A', strtotime($session["start_time"])); ?></p>
      </div>

      <?php if(isset($message_error)): ?>
          <div class="alert alert-danger"><?php echo $message_error; ?></div>
      <?php endif; ?>
      
      <?php if(isset($end_session_error)): ?>
          <div class="alert alert-danger"><?php echo $end_session_error; ?></div>
      <?php endif; ?>

      <!-- Messages Area -->
      <div class="messages-area" id="messagesArea">
          <?php if(empty($messages)): ?>
              <div style="text-align: center; color: #999; margin-top: 20px;">
                  <p>No messages yet. Start the conversation!</p>
              </div>
          <?php else: ?>
              <?php foreach($messages as $message): ?>
                  <div class="message <?php echo ($message['sender_id'] == $user_id) ? 'sent' : 'received'; ?> clearfix">
                      <?php if($message['sender_id'] != $user_id): ?>
                          <div class="message-sender"><?php echo $message['first_name']; ?></div>
                      <?php endif; ?>
                      <div class="message-text"><?php echo htmlspecialchars($message['message_text']); ?></div>
                      <div class="message-time"><?php echo date('g:i A', strtotime($message['created_at'])); ?></div>
                  </div>
              <?php endforeach; ?>
          <?php endif; ?>
      </div>

      <!-- Message Form -->
      <form method="post" class="message-form">
          <input type="text" name="message_text" class="message-input" placeholder="Type your message..." autocomplete="off" autofocus>
          <button type="submit" name="send_message" class="send-button">Send</button>
      </form>
  </div>

  <script>
  // Scroll to bottom of messages area
  window.onload = function() {
      var messagesArea = document.getElementById('messagesArea');
      messagesArea.scrollTop = messagesArea.scrollHeight;
  };

  // Auto-refresh the page every 10 seconds to get new messages
  setTimeout(function() {
      window.location.reload();
  }, 10000);
  </script>
</body>
</html>

