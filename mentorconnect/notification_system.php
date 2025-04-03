<?php
// Notification System for MentorConnect
// This file contains functions for sending emails and notifications

// Function to send welcome email to new users
function sendWelcomeEmail($email, $first_name, $user_type) {
    $subject = "Welcome to MentorConnect!";
    
    // Create different messages based on user type
    if ($user_type == "mentor") {
        $message = "
        <html>
        <head>
            <title>Welcome to MentorConnect</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background-color: #3498db; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background-color: #f9f9f9; }
                .footer { text-align: center; margin-top: 20px; font-size: 12px; color: #777; }
                .button { display: inline-block; background-color: #3498db; color: white; padding: 10px 20px; 
                          text-decoration: none; border-radius: 5px; margin-top: 20px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Welcome to MentorConnect!</h1>
                </div>
                <div class='content'>
                    <h2>Hello $first_name,</h2>
                    <p>Thank you for joining MentorConnect as a mentor! We're excited to have you as part of our community.</p>
                    <p>As a mentor, you'll be able to:</p>
                    <ul>
                        <li>Share your expertise with students</li>
                        <li>Schedule mentoring sessions</li>
                        <li>Make a real difference in someone's career journey</li>
                    </ul>
                    <p>To get started, please complete your profile and set your availability.</p>
                    <a href='https://mentorconnect.com/mentor.php' class='button'>Go to Your Dashboard</a>
                    <p>If you have any questions, please don't hesitate to contact our support team.</p>
                    <p>Best regards,<br>The MentorConnect Team</p>
                </div>
                <div class='footer'>
                    <p>© 2025 MentorConnect. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ";
    } else {
        $message = "
        <html>
        <head>
            <title>Welcome to MentorConnect</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background-color: #3498db; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background-color: #f9f9f9; }
                .footer { text-align: center; margin-top: 20px; font-size: 12px; color: #777; }
                .button { display: inline-block; background-color: #3498db; color: white; padding: 10px 20px; 
                          text-decoration: none; border-radius: 5px; margin-top: 20px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Welcome to MentorConnect!</h1>
                </div>
                <div class='content'>
                    <h2>Hello $first_name,</h2>
                    <p>Thank you for joining MentorConnect! We're excited to have you as part of our community.</p>
                    <p>As a student, you'll be able to:</p>
                    <ul>
                        <li>Connect with experienced mentors</li>
                        <li>Book mentoring sessions</li>
                        <li>Accelerate your learning and career growth</li>
                    </ul>
                    <p>To get started, please complete your profile and explore our mentor directory.</p>
                    <a href='https://mentorconnect.com/student.php' class='button'>Go to Your Dashboard</a>
                    <p>If you have any questions, please don't hesitate to contact our support team.</p>
                    <p>Best regards,<br>The MentorConnect Team</p>
                </div>
                <div class='footer'>
                    <p>© 2025 MentorConnect. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }
    
    // Set email headers
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: MentorConnect <noreply@mentorconnect.com>" . "\r\n";
    
    // Send email
    return mail($email, $subject, $message, $headers);
}

// Function to send session confirmation email
function sendSessionConfirmationEmail($email, $first_name, $session_data) {
    $subject = "Session Confirmation - MentorConnect";
    
    // Format date and time
    $session_date = date('l, F j, Y', strtotime($session_data['session_date']));
    $start_time = date('g:i A', strtotime($session_data['start_time']));
    $end_time = date('g:i A', strtotime($session_data['end_time']));
    
    $message = "
    <html>
    <head>
        <title>Session Confirmation</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background-color: #3498db; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; background-color: #f9f9f9; }
            .session-details { background-color: #fff; padding: 15px; border-radius: 5px; margin: 20px 0; }
            .footer { text-align: center; margin-top: 20px; font-size: 12px; color: #777; }
            .button { display: inline-block; background-color: #3498db; color: white; padding: 10px 20px; 
                      text-decoration: none; border-radius: 5px; margin-top: 20px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>Session Confirmation</h1>
            </div>
            <div class='content'>
                <h2>Hello $first_name,</h2>
                <p>Your mentoring session has been confirmed with the following details:</p>
                
                <div class='session-details'>
                    <p><strong>Topic:</strong> {$session_data['topic']}</p>
                    <p><strong>Date:</strong> $session_date</p>
                    <p><strong>Time:</strong> $start_time - $end_time</p>
                    <p><strong>With:</strong> {$session_data['other_name']}</p>
                </div>
                
                <p>Please make sure to be on time for your session. If you need to reschedule or cancel, please do so at least 24 hours in advance.</p>
                
                <a href='https://mentorconnect.com/dashboard.php' class='button'>View in Dashboard</a>
                
                <p>If you have any questions, please don't hesitate to contact our support team.</p>
                <p>Best regards,<br>The MentorConnect Team</p>
            </div>
            <div class='footer'>
                <p>© 2025 MentorConnect. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    // Set email headers
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: MentorConnect <noreply@mentorconnect.com>" . "\r\n";
    
    // Send email
    return mail($email, $subject, $message, $headers);
}

// Function to send session reminder email (24 hours before)
function sendSessionReminderEmail($email, $first_name, $session_data) {
    $subject = "Reminder: Upcoming Session - MentorConnect";
    
    // Format date and time
    $session_date = date('l, F j, Y', strtotime($session_data['session_date']));
    $start_time = date('g:i A', strtotime($session_data['start_time']));
    $end_time = date('g:i A', strtotime($session_data['end_time']));
    
    $message = "
    <html>
    <head>
        <title>Session Reminder</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background-color: #3498db; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; background-color: #f9f9f9; }
            .session-details { background-color: #fff; padding: 15px; border-radius: 5px; margin: 20px 0; }
            .footer { text-align: center; margin-top: 20px; font-size: 12px; color: #777; }
            .button { display: inline-block; background-color: #3498db; color: white; padding: 10px 20px; 
                      text-decoration: none; border-radius: 5px; margin-top: 20px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>Session Reminder</h1>
            </div>
            <div class='content'>
                <h2>Hello $first_name,</h2>
                <p>This is a friendly reminder about your upcoming mentoring session:</p>
                
                <div class='session-details'>
                    <p><strong>Topic:</strong> {$session_data['topic']}</p>
                    <p><strong>Date:</strong> $session_date</p>
                    <p><strong>Time:</strong> $start_time - $end_time</p>
                    <p><strong>With:</strong> {$session_data['other_name']}</p>
                </div>
                
                <p>Please make sure to be on time for your session. If you need to reschedule or cancel, please do so as soon as possible.</p>
                
                <a href='https://mentorconnect.com/dashboard.php' class='button'>View in Dashboard</a>
                
                <p>If you have any questions, please don't hesitate to contact our support team.</p>
                <p>Best regards,<br>The MentorConnect Team</p>
            </div>
            <div class='footer'>
                <p>© 2025 MentorConnect. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    // Set email headers
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: MentorConnect <noreply@mentorconnect.com>" . "\r\n";
    
    // Send email
    return mail($email, $subject, $message, $headers);
}




// Function to create in-app notification
function createNotification($user_id, $notification_type, $message, $related_id = null) {
    // Include database connection
    require_once "config/db_connect.php";
    
    // Insert notification into database
    $sql = "INSERT INTO notifications (user_id, notification_type, message, related_id, created_at, is_read) 
            VALUES (?, ?, ?, ?, NOW(), 0)";
    
    if($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("issi", $user_id, $notification_type, $message, $related_id);
        $result = $stmt->execute();
        $stmt->close();
        
        // Close connection
        closeConnection($conn);
        
        return $result;
    }
    
    // Close connection
    closeConnection($conn);
    
    return false;
}

// Function to mark notification as read
function markNotificationAsRead($notification_id) {
    // Include database connection
    require_once "config/db_connect.php";
    
    // Update notification status
    $sql = "UPDATE notifications SET is_read = 1 WHERE notification_id = ?";
    
    if($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("i", $notification_id);
        $result = $stmt->execute();
        $stmt->close();
        
        // Close connection
        closeConnection($conn);
        
        return $result;
    }
    
    // Close connection
    closeConnection($conn);
    
    return false;
}

// Function to get user notifications
function getUserNotifications($user_id, $limit = 10) {
    // Include database connection
    require_once "config/db_connect.php";
    
    // Get notifications
    $sql = "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ?";
    $notifications = array();
    
    if($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("ii", $user_id, $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while($row = $result->fetch_assoc()) {
            $notifications[] = $row;
        }
        
        $stmt->close();
    }
    
    // Close connection
    closeConnection($conn);
    
    return $notifications;
}

// Function to schedule session reminders
function scheduleSessionReminders() {
    // Include database connection
    require_once "config/db_connect.php";
    
    // Get sessions scheduled for tomorrow
    $tomorrow = date('Y-m-d', strtotime('+1 day'));
    $sql = "SELECT s.*, 
            m.first_name as mentor_first_name, m.last_name as mentor_last_name, m.email as mentor_email,
            st.first_name as student_first_name, st.last_name as student_last_name, st.email as student_email
            FROM sessions s 
            JOIN users m ON s.mentor_id = m.user_id
            JOIN users st ON s.student_id = st.user_id
            WHERE s.session_date = ? AND s.status = 'accepted'";
    
    if($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("s", $tomorrow);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while($row = $result->fetch_assoc()) {
            // Prepare session data for mentor
            $mentor_session_data = array(
                'topic' => $row['topic'],
                'session_date' => $row['session_date'],
                'start_time' => $row['start_time'],
                'end_time' => $row['end_time'],
                'other_name' => $row['student_first_name'] . ' ' . $row['student_last_name']
            );
            
            // Prepare session data for student
            $student_session_data = array(
                'topic' => $row['topic'],
                'session_date' => $row['session_date'],
                'start_time' => $row['start_time'],
                'end_time' => $row['end_time'],
                'other_name' => $row['mentor_first_name'] . ' ' . $row['mentor_last_name']
            );
            
            // Send reminder emails
            sendSessionReminderEmail($row['mentor_email'], $row['mentor_first_name'], $mentor_session_data);
            sendSessionReminderEmail($row['student_email'], $row['student_first_name'], $student_session_data);
            
            // Create in-app notifications
            $mentor_message = "Reminder: You have a session with " . $row['student_first_name'] . " tomorrow at " . date('g:i A', strtotime($row['start_time']));
            $student_message = "Reminder: You have a session with " . $row['mentor_first_name'] . " tomorrow at " . date('g:i A', strtotime($row['start_time']));
            
            createNotification($row['mentor_id'], 'session_reminder', $mentor_message, $row['session_id']);
            createNotification($row['student_id'], 'session_reminder', $student_message, $row['session_id']);
        }
        
        $stmt->close();
    }
    
    // Close connection
    closeConnection($conn);
}
?>

