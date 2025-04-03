<?php
// Initialize the session
session_start();

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

// Get conversations
$conversations = array();
$sql = "";

if($user_type == "student") {
  // Get all mentors the student has had sessions with
  $sql = "SELECT DISTINCT u.user_id, u.first_name, u.last_name, 
          (SELECT MAX(created_at) FROM messages 
           WHERE (from_user_id = ? AND to_user_id = u.user_id) 
              OR (from_user_id = u.user_id AND to_user_id = ?)) as last_message_time,
          (SELECT COUNT(*) FROM messages 
           WHERE to_user_id = ? AND from_user_id = u.user_id AND is_read = 0) as unread_count
          FROM users u
          JOIN sessions s ON u.user_id = s.mentor_id
          WHERE s.student_id = ? AND u.user_type = 'mentor'
          ORDER BY last_message_time DESC";
  
  if($stmt = $conn->prepare($sql)){
      $stmt->bind_param("iiii", $user_id, $user_id, $user_id, $user_id);
      $stmt->execute();
      $result = $stmt->get_result();
      
      while($row = $result->fetch_assoc()){
          $conversations[] = $row;
      }
      $stmt->close();
  }
} else if($user_type == "mentor") {
  // Get all students the mentor has had sessions with
  $sql = "SELECT DISTINCT u.user_id, u.first_name, u.last_name, 
          (SELECT MAX(created_at) FROM messages 
           WHERE (from_user_id = ? AND to_user_id = u.user_id) 
              OR (from_user_id = u.user_id AND to_user_id = ?)) as last_message_time,
          (SELECT COUNT(*) FROM messages 
           WHERE to_user_id = ? AND from_user_id = u.user_id AND is_read = 0) as unread_count
          FROM users u
          JOIN sessions s ON u.user_id = s.student_id
          WHERE s.mentor_id = ? AND u.user_type = 'student'
          ORDER BY last_message_time DESC";
  
  if($stmt = $conn->prepare($sql)){
      $stmt->bind_param("iiii", $user_id, $user_id, $user_id, $user_id);
      $stmt->execute();
      $result = $stmt->get_result();
      
      while($row = $result->fetch_assoc()){
          $conversations[] = $row;
      }
      $stmt->close();
  }
}

// Get selected conversation
$selected_user_id = isset($_GET['user']) ? intval($_GET['user']) : (count($conversations) > 0 ? $conversations[0]['user_id'] : 0);
$selected_user_name = "";

// Get messages for selected conversation
$messages = array();
if($selected_user_id > 0) {
  // Get user name
  $sql = "SELECT first_name, last_name FROM users WHERE user_id = ?";
  if($stmt = $conn->prepare($sql)){
      $stmt->bind_param("i", $selected_user_id);
      $stmt->execute();
      $result = $stmt->get_result();
      
      if($result->num_rows == 1){
          $row = $result->fetch_assoc();
          $selected_user_name = $row['first_name'] . ' ' . $row['last_name'];
      }
      $stmt->close();
  }
  
  // Get messages
  $sql = "SELECT * FROM messages 
          WHERE (from_user_id = ? AND to_user_id = ?) 
             OR (from_user_id = ? AND to_user_id = ?)
          ORDER BY created_at ASC";
  
  if($stmt = $conn->prepare($sql)){
      $stmt->bind_param("iiii", $user_id, $selected_user_id, $selected_user_id, $user_id);
      $stmt->execute();
      $result = $stmt->get_result();
      
      while($row = $result->fetch_assoc()){
          $messages[] = $row;
      }
      $stmt->close();
  }
  
  // Mark messages as read
  $sql = "UPDATE messages SET is_read = 1 
          WHERE from_user_id = ? AND to_user_id = ? AND is_read = 0";
  
  if($stmt = $conn->prepare($sql)){
      $stmt->bind_param("ii", $selected_user_id, $user_id);
      $stmt->execute();
      $stmt->close();
  }
}

// Send message
if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["send_message"])){
  $to_user_id = $_POST["to_user_id"];
  $message_text = trim($_POST["message_text"]);
  
  if(!empty($message_text)){
      $sql = "INSERT INTO messages (from_user_id, to_user_id, message, is_read, created_at) 
              VALUES (?, ?, ?, 0, NOW())";
      
      if($stmt = $conn->prepare($sql)){
          $stmt->bind_param("iis", $user_id, $to_user_id, $message_text);
          
          if($stmt->execute()){
              // Redirect to avoid form resubmission
              header("location: messages.php?user=" . $to_user_id);
              exit;
          } else {
              $message_error = "Something went wrong. Please try again.";
          }
          $stmt->close();
      }
  } else {
      $message_error = "Please enter a message.";
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
<title>Messages - MentorConnect</title>
<style>
/* Basic Reset */
* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
  font-family: Arial, sans-serif;
}

body {
  background-color: #f5f5f5;
}

/* Main Layout */
.container {
  display: flex;
  min-height: 100vh;
}

/* Sidebar */
.sidebar {
  width: 250px;
  background-color: #333;
  color: white;
  height: 100vh;
  position: fixed;
  z-index: 100;
}

.sidebar-header {
  padding: 20px;
  text-align: center;
  border-bottom: 1px solid #444;
}

.user-avatar {
  width: 80px;
  height: 80px;
  background-color: #3498db;
  border-radius: 50%;
  margin: 0 auto 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 36px;
  color: white;
}

.user-name {
  font-size: 18px;
  margin-bottom: 5px;
}

.user-title {
  font-size: 14px;
  color: #bdc3c7;
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
  text-decoration: none;
  color: white;
}

.menu-item:hover {
  background-color: #444;
}

.menu-item.active {
  background-color: #555;
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

/* Messages Layout */
.messages-container {
  display: flex;
  height: calc(100vh - 100px);
  background-color: white;
  border-radius: 8px;
  box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
  overflow: hidden;
}

.conversations-list {
  width: 300px;
  border-right: 1px solid #eee;
  overflow-y: auto;
}

.conversation-item {
  padding: 15px;
  border-bottom: 1px solid #eee;
  cursor: pointer;
  transition: background-color 0.3s;
  display: flex;
  align-items: center;
}

.conversation-item:hover {
  background-color: #f9f9f9;
}

.conversation-item.active {
  background-color: #f0f0f0;
}

.conversation-avatar {
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

.conversation-details {
  flex: 1;
}

.conversation-name {
  font-weight: bold;
  margin-bottom: 5px;
}

.conversation-preview {
  font-size: 12px;
  color: #777;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.unread-badge {
  background-color: #e74c3c;
  color: white;
  border-radius: 50%;
  width: 20px;
  height: 20px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 12px;
  margin-left: 10px;
}

.chat-area {
  flex: 1;
  display: flex;
  flex-direction: column;
}

.chat-header {
  padding: 15px;
  border-bottom: 1px solid #eee;
  display: flex;
  align-items: center;
}

.chat-avatar {
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

.chat-name {
  font-weight: bold;
}

.messages-list {
  flex: 1;
  padding: 15px;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
}

.message {
  max-width: 70%;
  padding: 10px 15px;
  border-radius: 18px;
  margin-bottom: 10px;
  position: relative;
}

.message.sent {
  align-self: flex-end;
  background-color: #3498db;
  color: white;
  border-bottom-right-radius: 5px;
}

.message.received {
  align-self: flex-start;
  background-color: #f0f0f0;
  border-bottom-left-radius: 5px;
}

.message-time {
  font-size: 10px;
  color: #aaa;
  margin-top: 5px;
  text-align: right;
}

.message.sent .message-time {
  color: rgba(255, 255, 255, 0.7);
}

.message-form {
  padding: 15px;
  border-top: 1px solid #eee;
  display: flex;
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

.no-messages {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  height: 100%;
  color: #777;
}

.no-messages-icon {
  font-size: 48px;
  margin-bottom: 15px;
  color: #ddd;
}

/* Mobile Menu Button */
.mobile-menu-btn {
  display: none;
  position: fixed;
  top: 10px;
  left: 10px;
  z-index: 200;
  background-color: #3498db;
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

  .messages-container {
      flex-direction: column;
      height: calc(100vh - 150px);
  }

  .conversations-list {
      width: 100%;
      height: 200px;
      border-right: none;
      border-bottom: 1px solid #eee;
  }

  .chat-area {
      height: calc(100vh - 350px);
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
          <div class="user-avatar"><?php echo substr($first_name, 0, 1); ?></div>
          <h3 class="user-name"><?php echo $first_name . " " . $last_name; ?></h3>
          <p class="user-title"><?php echo ucfirst($user_type); ?></p>
          <form action="logout.php" method="post">
              <button type="submit" class="logout-btn" style="margin-top: 10px; background-color: #e74c3c; color: white; border: none; padding: 5px 10px; border-radius: 4px; cursor: pointer;">Logout</button>
          </form>
      </div>
      <div class="sidebar-menu">
          <?php if($user_type == "student"): ?>
              <a href="student.php" class="menu-item">
                  <span class="menu-icon">📊</span> Dashboard
              </a>
              <a href="student.php?section=find-mentors" class="menu-item">
                  <span class="menu-icon">🔍</span> Find Mentors
              </a>
              <a href="student.php?section=upcoming-sessions" class="menu-item">
                  <span class="menu-icon">📅</span> Upcoming Sessions
              </a>
              <a href="student.php?section=session-history" class="menu-item">
                  <span class="menu-icon">📚</span> Session History
              </a>
              <a href="messages.php" class="menu-item active">
                  <span class="menu-icon">💬</span> Messages
              </a>
              <a href="student.php?section=profile" class="menu-item">
                  <span class="menu-icon">👤</span> My Profile
              </a>
          <?php elseif($user_type == "mentor"): ?>
              <a href="mentor.php" class="menu-item">
                  <span class="menu-icon">📊</span> Dashboard
              </a>
              <a href="mentor.php?section=session-requests" class="menu-item">
                  <span class="menu-icon">📩</span> Session Requests
              </a>
              <a href="mentor.php?section=upcoming-sessions" class="menu-item">
                  <span class="menu-icon">📅</span> Upcoming Sessions
              </a>
              <a href="mentor.php?section=session-history" class="menu-item">
                  <span class="menu-icon">📚</span> Session History
              </a>
              <a href="mentor.php?section=students" class="menu-item">
                  <span class="menu-icon">👨‍🎓</span> My Students
              </a>
              <a href="messages.php" class="menu-item active">
                  <span class="menu-icon">💬</span> Messages
              </a>
              <a href="mentor.php?section=profile" class="menu-item">
                  <span class="menu-icon">👤</span> My Profile
              </a>
          <?php endif; ?>
      </div>
  </div>

  <!-- Main Content -->
  <div class="main-content">
      <!-- Header -->
      <div class="header">
          <h1 class="page-title">Messages</h1>
      </div>

      <?php if(isset($message_error)): ?>
          <div class="alert alert-danger"><?php echo $message_error; ?></div>
      <?php endif; ?>

      <div class="messages-container">
          <!-- Conversations List -->
          <div class="conversations-list">
              <?php if(empty($conversations)): ?>
                  <div style="padding: 20px; text-align: center; color: #777;">
                      No conversations yet.
                  </div>
              <?php else: ?>
                  <?php foreach($conversations as $conversation): ?>
                      <a href="messages.php?user=<?php echo $conversation['user_id']; ?>" class="conversation-item <?php echo ($selected_user_id == $conversation['user_id']) ? 'active' : ''; ?>">
                          <div class="conversation-avatar"><?php echo substr($conversation['first_name'], 0, 1) . substr($conversation['last_name'], 0, 1); ?></div>
                          <div class="conversation-details">
                              <div class="conversation-name"><?php echo $conversation['first_name'] . ' ' . $conversation['last_name']; ?></div>
                              <div class="conversation-preview">
                                  <?php 
                                      if($conversation['last_message_time']) {
                                          echo date('M j, g:i a', strtotime($conversation['last_message_time']));
                                      } else {
                                          echo "No messages yet";
                                      }
                                  ?>
                              </div>
                          </div>
                          <?php if($conversation['unread_count'] > 0): ?>
                              <div class="unread-badge"><?php echo $conversation['unread_count']; ?></div>
                          <?php endif; ?>
                      </a>
                  <?php endforeach; ?>
              <?php endif; ?>
          </div>

          <!-- Chat Area -->
          <div class="chat-area">
              <?php if($selected_user_id > 0): ?>
                  <div class="chat-header">
                      <div class="chat-avatar"><?php echo substr($selected_user_name, 0, 1); ?></div>
                      <div class="chat-name"><?php echo $selected_user_name; ?></div>
                  </div>
                  <div class="messages-list" id="messagesList">
                      <?php if(empty($messages)): ?>
                          <div style="text-align: center; color: #777; margin: 20px 0;">
                              No messages yet. Start the conversation!
                          </div>
                      <?php else: ?>
                          <?php foreach($messages as $message): ?>
                              <div class="message <?php echo ($message['from_user_id'] == $user_id) ? 'sent' : 'received'; ?>">
                                  <?php echo htmlspecialchars($message['message']); ?>
                                  <div class="message-time"><?php echo date('M j, g:i a', strtotime($message['created_at'])); ?></div>
                              </div>
                          <?php endforeach; ?>
                      <?php endif; ?>
                  </div>
                  <form method="post" class="message-form">
                      <input type="hidden" name="to_user_id" value="<?php echo $selected_user_id; ?>">
                      <input type="text" name="message_text" class="message-input" placeholder="Type a message..." autocomplete="off" autofocus>
                      <button type="submit" name="send_message" class="send-button">Send</button>
                  </form>
              <?php else: ?>
                  <div class="no-messages">
                      <div class="no-messages-icon">💬</div>
                      <p>Select a conversation to start messaging</p>
                  </div>
              <?php endif; ?>
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

// Scroll to bottom of messages list
const messagesList = document.getElementById('messagesList');
if (messagesList) {
  messagesList.scrollTop = messagesList.scrollHeight;
}
</script>
</body>
</html>

