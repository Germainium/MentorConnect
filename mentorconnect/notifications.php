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
require_once "notification_system.php";

// Get user information
$user_id = $_SESSION["user_id"];
$user_type = $_SESSION["user_type"];

// Mark notification as read if requested
if(isset($_GET["mark_read"]) && !empty($_GET["mark_read"])){
  $notification_id = $_GET["mark_read"];
  markNotificationAsRead($notification_id);
}

// Mark all notifications as read if requested
if(isset($_GET["mark_all_read"])){
  // Update all notifications for this user
  $sql = "UPDATE notifications SET is_read = 1 WHERE user_id = ?";
  if($stmt = $conn->prepare($sql)){
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();
  }
}

// Get user notifications
$notifications = getUserNotifications($user_id, 50); // Get last 50 notifications

// Close connection
closeConnection($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Notifications - MentorConnect</title>
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
      line-height: 1.6;
    }

    .container {
      max-width: 800px;
      margin: 40px auto;
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

    .header h1 {
      font-size: 24px;
      color: #333;
    }

    .back-link {
      color: #3498db;
      text-decoration: none;
      display: flex;
      align-items: center;
    }

    .back-link:hover {
      text-decoration: underline;
    }

    .back-icon {
      margin-right: 5px;
    }

    .notifications-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 20px;
    }

    .mark-all-read {
      color: #3498db;
      text-decoration: none;
      font-size: 14px;
    }

    .mark-all-read:hover {
      text-decoration: underline;
    }

    .notification-list {
      background-color: white;
      border-radius: 8px;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
      overflow: hidden;
    }

    .notification-item {
      padding: 15px 20px;
      border-bottom: 1px solid #eee;
      display: flex;
      align-items: flex-start;
      transition: background-color 0.3s;
    }

    .notification-item:last-child {
      border-bottom: none;
    }

    .notification-item:hover {
      background-color: #f9f9f9;
    }

    .notification-item.unread {
      background-color: #ebf7ff;
    }

    .notification-icon {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      background-color: #3498db;
      color: white;
      display: flex;
      align-items: center;
      justify-content: center;
      margin-right: 15px;
      flex-shrink: 0;
    }

    .notification-content {
      flex: 1;
    }

    .notification-message {
      margin-bottom: 5px;
    }

    .notification-time {
      font-size: 12px;
      color: #7f8c8d;
    }

    .notification-actions {
      display: flex;
      gap: 10px;
      margin-top: 10px;
    }

    .notification-action {
      font-size: 12px;
      color: #3498db;
      text-decoration: none;
    }

    .notification-action:hover {
      text-decoration: underline;
    }

    .empty-state {
      padding: 40px 20px;
      text-align: center;
      background-color: white;
      border-radius: 8px;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    }

    .empty-state i {
      font-size: 48px;
      color: #bdc3c7;
      margin-bottom: 15px;
    }

    .empty-state p {
      color: #7f8c8d;
      font-size: 16px;
    }

    @media (max-width: 768px) {
      .container {
        padding: 15px;
      }
    }
  </style>
</head>
<body>
  <div class="container">
    <div class="header">
      <h1>Notifications</h1>
      <a href="<?php echo $user_type; ?>.php" class="back-link">
        <span class="back-icon"><i class="fas fa-arrow-left"></i></span> Back to Dashboard
      </a>
    </div>

    <?php if(!empty($notifications)): ?>
      <div class="notifications-header">
        <h2>Recent Notifications</h2>
        <a href="?mark_all_read=1" class="mark-all-read">Mark all as read</a>
      </div>

      <div class="notification-list">
        <?php foreach($notifications as $notification): ?>
          <div class="notification-item <?php echo $notification['is_read'] ? '' : 'unread'; ?>">
            <div class="notification-icon">
              <?php
                // Set icon based on notification type
                switch($notification['notification_type']) {
                  case 'welcome':
                    echo '<i class="fas fa-hand-wave"></i>';
                    break;
                  case 'session_request':
                    echo '<i class="fas fa-calendar-plus"></i>';
                    break;
                  case 'session_accepted':
                    echo '<i class="fas fa-check-circle"></i>';
                    break;
                  case 'session_declined':
                    echo '<i class="fas fa-times-circle"></i>';
                    break;
                  case 'session_completed':
                    echo '<i class="fas fa-check-double"></i>';
                    break;
                  case 'session_reminder':
                    echo '<i class="fas fa-bell"></i>';
                    break;
                  case 'feedback_received':
                    echo '<i class="fas fa-comment"></i>';
                    break;
                  default:
                    echo '<i class="fas fa-bell"></i>';
                }
              ?>
            </div>
            <div class="notification-content">
              <div class="notification-message"><?php echo htmlspecialchars($notification['message']); ?></div>
              <div class="notification-time"><?php echo date('F j, Y, g:i a', strtotime($notification['created_at'])); ?></div>
              
              <div class="notification-actions">
                <?php if(!$notification['is_read']): ?>
                  <a href="?mark_read=<?php echo $notification['notification_id']; ?>" class="notification-action">Mark as read</a>
                <?php endif; ?>
                
                <?php if($notification['related_id']): ?>
                  <?php
                    // Set view link based on notification type
                    $view_link = '#';
                    switch($notification['notification_type']) {
                      case 'session_request':
                      case 'session_accepted':
                      case 'session_declined':
                      case 'session_completed':
                      case 'session_reminder':
                        if($user_type == 'mentor') {
                          $view_link = 'mentor-session-details.php?id=' . $notification['related_id'];
                        } else {
                          $view_link = 'student-session-details.php?id=' . $notification['related_id'];
                        }
                        break;
                      case 'feedback_received':
                        $view_link = 'view-feedback.php?id=' . $notification['related_id'];
                        break;
                    }
                  ?>
                  <a href="<?php echo $view_link; ?>" class="notification-action">View details</a>
                <?php endif; ?>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="empty-state">
        <i class="fas fa-bell-slash"></i>
        <p>You have no notifications yet.</p>
      </div>
    <?php endif; ?>
  </div>
</body>
</html>

