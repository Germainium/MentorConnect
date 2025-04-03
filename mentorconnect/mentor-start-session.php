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

// Verify that the session belongs to this mentor and is scheduled for today
$sql = "SELECT s.*, u.first_name, u.last_name, u.email 
        FROM sessions s 
        JOIN users u ON s.student_id = u.user_id 
        WHERE s.session_id = ? AND s.mentor_id = ? AND s.status = 'accepted' 
        AND s.session_date = CURDATE()";
if($stmt = $conn->prepare($sql)){
    $stmt->bind_param("ii", $session_id, $mentor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if($result->num_rows != 1){
        // Session not found or not eligible for starting
        header("location: mentor.php?section=upcoming-sessions&error=invalid_session");
        exit;
    }
    
    $session = $result->fetch_assoc();
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
    <title>Session with <?php echo htmlspecialchars($session['first_name'] . ' ' . $session['last_name']); ?> - MentorConnect</title>
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
            max-width: 1200px;
            margin: 20px auto;
            padding: 0 20px;
            flex: 1;
        }

        .session-info {
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
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }

        .student-info {
            display: flex;
            align-items: center;
        }

        .student-avatar {
            width: 50px;
            height: 50px;
            background-color: #3498db;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 20px;
            margin-right: 15px;
        }

        .student-details h2 {
            font-size: 18px;
            margin-bottom: 5px;
        }

        .student-details p {
            color: #7f8c8d;
            font-size: 14px;
        }

        .session-time {
            text-align: right;
            color: #7f8c8d;
        }

        .session-time .time {
            font-size: 18px;
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }

        .session-details {
            margin-bottom: 20px;
        }

        .detail-item {
            margin-bottom: 10px;
        }

        .detail-label {
            font-weight: bold;
            margin-right: 10px;
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

        .session-content {
            display: flex;
            gap: 20px;
        }

        .video-container {
            flex: 2;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            padding: 20px;
            height: 500px;
            display: flex;
            flex-direction: column;
        }

        .video-placeholder {
            flex: 1;
            background-color: #2c3e50;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 18px;
            margin-bottom: 20px;
        }

        .video-controls {
            display: flex;
            justify-content: center;
            gap: 20px;
        }

        .video-btn {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background-color: #3498db;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            cursor: pointer;
        }

        .video-btn.mute {
            background-color: #f39c12;
        }

        .video-btn.end {
            background-color: #e74c3c;
        }

        .chat-container {
            flex: 1;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            padding: 20px;
            height: 500px;
            display: flex;
            flex-direction: column;
        }

        .chat-messages {
            flex: 1;
            overflow-y: auto;
            margin-bottom: 15px;
            padding: 10px;
            background-color: #f9f9f9;
            border-radius: 8px;
        }

        .message {
            margin-bottom: 15px;
            padding: 10px;
            border-radius: 8px;
            max-width: 80%;
        }

        .message.sent {
            background-color: #3498db;
            color: white;
            align-self: flex-end;
            margin-left: auto;
        }

        .message.received {
            background-color: #eee;
            align-self: flex-start;
        }

        .message-sender {
            font-weight: bold;
            margin-bottom: 5px;
            font-size: 14px;
        }

        .message-text {
            font-size: 14px;
        }

        .chat-input {
            display: flex;
            gap: 10px;
        }

        .chat-input input {
            flex: 1;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .chat-input button {
            padding: 10px 15px;
            background-color: #3498db;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }

        @media (max-width: 768px) {
            .session-content {
                flex-direction: column;
            }

            .video-container, .chat-container {
                height: auto;
            }

            .video-placeholder {
                height: 300px;
            }

            .chat-messages {
                height: 300px;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-title">MentorConnect Session</div>
        <div class="header-actions">
            <a href="mentor.php?section=upcoming-sessions"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
        </div>
    </div>

    <div class="container">
        <div class="session-info">
            <div class="session-header">
                <div class="student-info">
                    <div class="student-avatar"><?php echo substr($session['first_name'], 0, 1) . substr($session['last_name'], 0, 1); ?></div>
                    <div class="student-details">
                        <h2><?php echo htmlspecialchars($session['first_name'] . ' ' . $session['last_name']); ?></h2>
                        <p><?php echo htmlspecialchars($session['email']); ?></p>
                    </div>
                </div>
                <div class="session-time">
                    <div class="time"><?php echo date('g:i A', strtotime($session['start_time'])); ?> - <?php echo date('g:i A', strtotime($session['end_time'])); ?></div>
                    <div class="date"><?php echo date('F j, Y', strtotime($session['session_date'])); ?></div>
                </div>
            </div>
            <div class="session-details">
                <div class="detail-item">
                    <span class="detail-label">Topic:</span>
                    <span><?php echo htmlspecialchars($session['topic']); ?></span>
                </div>
                <?php if(!empty($session['notes'])): ?>
                    <div class="detail-item">
                        <span class="detail-label">Notes:</span>
                        <span><?php echo htmlspecialchars($session['notes']); ?></span>
                    </div>
                <?php endif; ?>
            </div>
            <div class="session-actions">
                <form method="post" action="mentor.php">
                    <input type="hidden" name="session_id" value="<?php echo $session_id; ?>">
                    <button type="submit" name="complete_session" class="btn btn-success">Mark as Completed</button>
                </form>
            </div>
        </div>

        <div class="session-content">
            <div class="video-container">
                <div class="video-placeholder">
                    <p>Video call will appear here</p>
                </div>
                <div class="video-controls">
                    <div class="video-btn"><i class="fas fa-video"></i></div>
                    <div class="video-btn mute"><i class="fas fa-microphone-slash"></i></div>
                    <div class="video-btn end"><i class="fas fa-phone-slash"></i></div>
                </div>
            </div>
            <div class="chat-container">
                <h3 style="margin-bottom: 15px;">Session Chat</h3>
                <div class="chat-messages">
                    <div class="message received">
                        <div class="message-sender"><?php echo htmlspecialchars($session['first_name'] . ' ' . $session['last_name']); ?></div>
                        <div class="message-text">Hello! I'm ready for our session today.</div>
                    </div>
                    <div class="message sent">
                        <div class="message-sender">You</div>
                        <div class="message-text">Hi there! Let's get started. What specific questions do you have about <?php echo htmlspecialchars($session['topic']); ?>?</div>
                    </div>
                </div>
                <div class="chat-input">
                    <input type="text" placeholder="Type your message...">
                    <button><i class="fas fa-paper-plane"></i> Send</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Simulate chat functionality
        document.querySelector('.chat-input button').addEventListener('click', function() {
            const input = document.querySelector('.chat-input input');
            const message = input.value.trim();
            
            if (message) {
                const chatMessages = document.querySelector('.chat-messages');
                const messageElement = document.createElement('div');
                messageElement.className = 'message sent';
                messageElement.innerHTML = `
                    <div class="message-sender">You</div>
                    <div class="message-text">${message}</div>
                `;
                
                chatMessages.appendChild(messageElement);
                chatMessages.scrollTop = chatMessages.scrollHeight;
                input.value = '';
            }
        });

        // Allow pressing Enter to send message
        document.querySelector('.chat-input input').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                document.querySelector('.chat-input button').click();
            }
        });

        // Video control buttons
        document.querySelectorAll('.video-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                if (this.classList.contains('end')) {
                    if (confirm('Are you sure you want to end this session?')) {
                        window.location.href = 'mentor.php?section=upcoming-sessions';
                    }
                } else {
                    this.classList.toggle('active');
                }
            });
        });
    </script>
</body>
</html>

