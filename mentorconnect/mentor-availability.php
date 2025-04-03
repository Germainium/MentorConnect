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
$profile_photo = "";

// Get mentor profile photo
$sql = "SELECT profile_photo FROM mentor_profiles WHERE mentor_id = ?";
if($stmt = $conn->prepare($sql)){
  $stmt->bind_param("i", $mentor_id);
  $stmt->execute();
  $result = $stmt->get_result();
  if($result->num_rows == 1){
      $row = $result->fetch_assoc();
      $profile_photo = $row["profile_photo"];
  }
  $stmt->close();
}

// Get current availability
$availability = array();
$sql = "SELECT * FROM mentor_availability WHERE mentor_id = ? ORDER BY day_of_week, start_time";
if($stmt = $conn->prepare($sql)){
  $stmt->bind_param("i", $mentor_id);
  $stmt->execute();
  $result = $stmt->get_result();
  while($row = $result->fetch_assoc()){
      $availability[] = $row;
  }
  $stmt->close();
}

// Process form submission
$success_message = "";
$error_message = "";

if($_SERVER["REQUEST_METHOD"] == "POST"){
  if(isset($_POST["save_availability"])){
    // Delete existing availability
    $sql = "DELETE FROM mentor_availability WHERE mentor_id = ?";
    if($stmt = $conn->prepare($sql)){
      $stmt->bind_param("i", $mentor_id);
      $stmt->execute();
      $stmt->close();
    }
    
    // Add new availability slots
    if(isset($_POST["day"]) && isset($_POST["start_time"]) && isset($_POST["end_time"])){
      $days = $_POST["day"];
      $start_times = $_POST["start_time"];
      $end_times = $_POST["end_time"];
      
      $success = true;
      
      for($i = 0; $i < count($days); $i++){
        if(!empty($days[$i]) && !empty($start_times[$i]) && !empty($end_times[$i])){
          $sql = "INSERT INTO mentor_availability (mentor_id, day_of_week, start_time, end_time) VALUES (?, ?, ?, ?)";
          if($stmt = $conn->prepare($sql)){
            $stmt->bind_param("isss", $mentor_id, $days[$i], $start_times[$i], $end_times[$i]);
            if(!$stmt->execute()){
              $success = false;
              $error_message = "Error saving availability: " . $stmt->error;
            }
            $stmt->close();
          }
        }
      }
      
      if($success){
        $success_message = "Availability updated successfully!";
        
        // Refresh availability data
        $availability = array();
        $sql = "SELECT * FROM mentor_availability WHERE mentor_id = ? ORDER BY day_of_week, start_time";
        if($stmt = $conn->prepare($sql)){
          $stmt->bind_param("i", $mentor_id);
          $stmt->execute();
          $result = $stmt->get_result();
          while($row = $result->fetch_assoc()){
              $availability[] = $row;
          }
          $stmt->close();
        }
      }
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
  <title>Manage Availability - MentorConnect</title>
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
          display: flex;
          min-height: 100vh;
      }

      /* Sidebar */
      .sidebar {
          width: 250px;
          background-color: #2c3e50;
          color: white;
          height: 100vh;
          position: fixed;
          left: 0;
          top: 0;
          overflow-y: auto;
      }

      .profile-section {
          padding: 20px;
          text-align: center;
          border-bottom: 1px solid #34495e;
      }

      .profile-avatar {
          width: 100px;
          height: 100px;
          border-radius: 50%;
          background-color: #3498db;
          color: white;
          display: flex;
          align-items: center;
          justify-content: center;
          font-size: 48px;
          margin: 0 auto 15px;
          overflow: hidden;
      }

      .profile-avatar img {
          width: 100%;
          height: 100%;
          object-fit: cover;
      }

      .profile-name {
          font-size: 18px;
          font-weight: bold;
          margin-bottom: 5px;
      }

      .profile-title {
          font-size: 14px;
          color: #bdc3c7;
          margin-bottom: 15px;
      }

      .logout-btn {
          background-color: #e74c3c;
          color: white;
          border: none;
          padding: 8px 15px;
          border-radius: 4px;
          cursor: pointer;
          font-size: 14px;
          transition: background-color 0.3s;
      }

      .logout-btn:hover {
          background-color: #c0392b;
      }

      .nav-menu {
          padding: 20px 0;
      }

      .nav-item {
          padding: 12px 20px;
          display: flex;
          align-items: center;
          color: #ecf0f1;
          text-decoration: none;
          transition: background-color 0.3s;
      }

      .nav-item:hover {
          background-color: #34495e;
      }

      .nav-item.active {
          background-color: #3498db;
      }

      .nav-icon {
          margin-right: 10px;
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
          margin-bottom: 30px;
          padding-bottom: 20px;
          border-bottom: 1px solid #ddd;
      }

      .page-title {
          font-size: 24px;
          font-weight: bold;
      }

      /* Alert Messages */
      .alert {
          padding: 15px;
          margin-bottom: 20px;
          border-radius: 5px;
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

      /* Availability Form */
      .card {
          background-color: white;
          border-radius: 8px;
          box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
          padding: 20px;
          margin-bottom: 20px;
      }

      .section-title {
          font-size: 18px;
          margin-bottom: 20px;
          color: #2c3e50;
      }

      .availability-slots {
          margin-bottom: 20px;
      }

      .availability-slot {
          display: flex;
          gap: 15px;
          margin-bottom: 15px;
          align-items: center;
      }

      .form-group {
          flex: 1;
      }

      .form-group select,
      .form-group input {
          width: 100%;
          padding: 10px;
          border: 1px solid #ddd;
          border-radius: 4px;
          font-size: 14px;
      }

      .remove-slot {
          background-color: #e74c3c;
          color: white;
          border: none;
          width: 30px;
          height: 30px;
          border-radius: 50%;
          display: flex;
          align-items: center;
          justify-content: center;
          cursor: pointer;
      }

      .add-slot-btn {
          background-color: #3498db;
          color: white;
          border: none;
          padding: 10px 15px;
          border-radius: 4px;
          cursor: pointer;
          display: flex;
          align-items: center;
          gap: 5px;
          margin-bottom: 20px;
      }

      .submit-btn {
          background-color: #2ecc71;
          color: white;
          border: none;
          padding: 12px 20px;
          border-radius: 4px;
          cursor: pointer;
          font-size: 16px;
      }

      .current-availability {
          margin-top: 30px;
      }

      .availability-table {
          width: 100%;
          border-collapse: collapse;
      }

      .availability-table th,
      .availability-table td {
          padding: 12px 15px;
          text-align: left;
          border-bottom: 1px solid #ddd;
      }

      .availability-table th {
          background-color: #f5f5f5;
          font-weight: 600;
      }

      .no-availability {
          padding: 20px;
          text-align: center;
          color: #7f8c8d;
      }

      /* Responsive Styles */
      @media (max-width: 768px) {
          .sidebar {
              width: 100%;
              height: auto;
              position: relative;
          }

          .main-content {
              margin-left: 0;
          }

          .availability-slot {
              flex-direction: column;
              gap: 10px;
          }
      }
  </style>
</head>
<body>
  <!-- Sidebar -->
  <div class="sidebar">
      <div class="profile-section">
          <div class="profile-avatar">
              <?php if(!empty($profile_photo) && file_exists($profile_photo)): ?>
                  <img src="<?php echo htmlspecialchars($profile_photo); ?>" alt="Profile Photo">
              <?php else: ?>
                  <?php echo substr($first_name, 0, 1); ?>
              <?php endif; ?>
          </div>
          <h3 class="profile-name"><?php echo htmlspecialchars($first_name . " " . $last_name); ?></h3>
          <p class="profile-title">Mentor</p>
          <form action="logout.php" method="post">
              <button type="submit" class="logout-btn">Logout</button>
          </form>
      </div>
      <div class="nav-menu">
          <a href="mentor-dashboard.php" class="nav-item">
              <span class="nav-icon"><i class="fas fa-tachometer-alt"></i></span> Dashboard
          </a>
          <a href="mentor-session-requests.php" class="nav-item">
              <span class="nav-icon"><i class="fas fa-envelope"></i></span> Session Requests
          </a>
          <a href="mentor-upcoming-sessions.php" class="nav-item">
              <span class="nav-icon"><i class="fas fa-calendar-alt"></i></span> Upcoming Sessions
          </a>
          <a href="mentor-session-history.php" class="nav-item">
              <span class="nav-icon"><i class="fas fa-history"></i></span> Session History
          </a>
          <a href="mentor-profile-update.php" class="nav-item">
              <span class="nav-icon"><i class="fas fa-user"></i></span> Profile
          </a>
          <a href="mentor-availability.php" class="nav-item active">
              <span class="nav-icon"><i class="fas fa-clock"></i></span> Availability
          </a>
          <a href="mentor-student-profiles.php" class="nav-item">
              <span class="nav-icon"><i class="fas fa-user-graduate"></i></span> Student Profiles
          </a>
          <a href="mentor-provide-feedback.php" class="nav-item">
              <span class="nav-icon"><i class="fas fa-comment"></i></span> Provide Feedback
          </a>
      </div>
  </div>

  <!-- Main Content -->
  <div class="main-content">
      <div class="header">
          <h1 class="page-title">Manage Availability</h1>
      </div>

      <?php if(!empty($success_message)): ?>
          <div class="alert alert-success"><?php echo $success_message; ?></div>
      <?php endif; ?>

      <?php if(!empty($error_message)): ?>
          <div class="alert alert-danger"><?php echo $error_message; ?></div>
      <?php endif; ?>

      <div class="card">
          <h2 class="section-title">Set Your Weekly Availability</h2>
          <p style="margin-bottom: 20px;">Define the days and times when you're available for mentoring sessions. Students will be able to book sessions during these times.</p>
          
          <form method="post">
              <div class="availability-slots" id="availabilitySlots">
                  <?php if(empty($availability)): ?>
                      <div class="availability-slot">
                          <div class="form-group">
                              <select name="day[]" required>
                                  <option value="">Select Day</option>
                                  <option value="Monday">Monday</option>
                                  <option value="Tuesday">Tuesday</option>
                                  <option value="Wednesday">Wednesday</option>
                                  <option value="Thursday">Thursday</option>
                                  <option value="Friday">Friday</option>
                                  <option value="Saturday">Saturday</option>
                                  <option value="Sunday">Sunday</option>
                              </select>
                          </div>
                          <div class="form-group">
                              <input type="time" name="start_time[]" required>
                          </div>
                          <div class="form-group">
                              <input type="time" name="end_time[]" required>
                          </div>
                          <button type="button" class="remove-slot"><i class="fas fa-times"></i></button>
                      </div>
                  <?php else: ?>
                      <?php foreach($availability as $slot): ?>
                          <div class="availability-slot">
                              <div class="form-group">
                                  <select name="day[]" required>
                                      <option value="">Select Day</option>
                                      <option value="Monday" <?php if($slot['day_of_week'] == 'Monday') echo 'selected'; ?>>Monday</option>
                                      <option value="Tuesday" <?php if($slot['day_of_week'] == 'Tuesday') echo 'selected'; ?>>Tuesday</option>
                                      <option value="Wednesday" <?php if($slot['day_of_week'] == 'Wednesday') echo 'selected'; ?>>Wednesday</option>
                                      <option value="Thursday" <?php if($slot['day_of_week'] == 'Thursday') echo 'selected'; ?>>Thursday</option>
                                      <option value="Friday" <?php if($slot['day_of_week'] == 'Friday') echo 'selected'; ?>>Friday</option>
                                      <option value="Saturday" <?php if($slot['day_of_week'] == 'Saturday') echo 'selected'; ?>>Saturday</option>
                                      <option value="Sunday" <?php if($slot['day_of_week'] == 'Sunday') echo 'selected'; ?>>Sunday</option>
                                  </select>
                              </div>
                              <div class="form-group">
                                  <input type="time" name="start_time[]" value="<?php echo $slot['start_time']; ?>" required>
                              </div>
                              <div class="form-group">
                                  <input type="time" name="end_time[]" value="<?php echo $slot['end_time']; ?>" required>
                              </div>
                              <button type="button" class="remove-slot"><i class="fas fa-times"></i></button>
                          </div>
                      <?php endforeach; ?>
                  <?php endif; ?>
              </div>
              
              <button type="button" id="addSlotBtn" class="add-slot-btn">
                  <i class="fas fa-plus"></i> Add Time Slot
              </button>
              
              <button type="submit" name="save_availability" class="submit-btn">Save Availability</button>
          </form>
      </div>

      <div class="card current-availability">
          <h2 class="section-title">Current Availability</h2>
          
          <?php if(empty($availability)): ?>
              <div class="no-availability">
                  <p>You haven't set any availability yet. Add time slots above to let students know when you're available.</p>
              </div>
          <?php else: ?>
              <table class="availability-table">
                  <thead>
                      <tr>
                          <th>Day</th>
                          <th>Start Time</th>
                          <th>End Time</th>
                      </tr>
                  </thead>
                  <tbody>
                      <?php foreach($availability as $slot): ?>
                          <tr>
                              <td><?php echo htmlspecialchars($slot['day_of_week']); ?></td>
                              <td><?php echo date('g:i A', strtotime($slot['start_time'])); ?></td>
                              <td><?php echo date('g:i A', strtotime($slot['end_time'])); ?></td>
                          </tr>
                      <?php endforeach; ?>
                  </tbody>
              </table>
          <?php endif; ?>
      </div>
  </div>

  <script>
      document.addEventListener('DOMContentLoaded', function() {
          // Add new availability slot
          document.getElementById('addSlotBtn').addEventListener('click', function() {
              const slotsContainer = document.getElementById('availabilitySlots');
              const newSlot = document.createElement('div');
              newSlot.className = 'availability-slot';
              newSlot.innerHTML = `
                  <div class="form-group">
                      <select name="day[]" required>
                          <option value="">Select Day</option>
                          <option value="Monday">Monday</option>
                          <option value="Tuesday">Tuesday</option>
                          <option value="Wednesday">Wednesday</option>
                          <option value="Thursday">Thursday</option>
                          <option value="Friday">Friday</option>
                          <option value="Saturday">Saturday</option>
                          <option value="Sunday">Sunday</option>
                      </select>
                  </div>
                  <div class="form-group">
                      <input type="time" name="start_time[]" required>
                  </div>
                  <div class="form-group">
                      <input type="time" name="end_time[]" required>
                  </div>
                  <button type="button" class="remove-slot"><i class="fas fa-times"></i></button>
              `;
              slotsContainer.appendChild(newSlot);
              
              // Add event listener to the new remove button
              newSlot.querySelector('.remove-slot').addEventListener('click', function() {
                  slotsContainer.removeChild(newSlot);
              });
          });
          
          // Remove availability slot
          document.querySelectorAll('.remove-slot').forEach(button => {
              button.addEventListener('click', function() {
                  const slot = this.parentElement;
                  const slotsContainer = document.getElementById('availabilitySlots');
                  
                  // Don't remove if it's the only slot
                  if (slotsContainer.children.length > 1) {
                      slotsContainer.removeChild(slot);
                  }
              });
          });
      });
  </script>
</body>
</html>

