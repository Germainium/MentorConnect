<?php
session_start();

// Check if the user is logged in
if (!isset($_SESSION["student_id"])) {
    header("Location: student-login.php");
    exit();
}

// Database connection details
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "tutor_system";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch available tutors and subjects
$sql_tutors = "SELECT tutor_id, name FROM tutors";
$result_tutors = $conn->query($sql_tutors);

$sql_subjects = "SELECT subject_id, name FROM subjects";
$result_subjects = $conn->query($sql_subjects);

// Handle booking submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $student_id = $_SESSION["student_id"];
    $tutor_id = $_POST["tutor"];
    $subject_id = $_POST["subject"];
    $session_date = $_POST["session_date"];
    $session_time = $_POST["session_time"];

    // Combine date and time into a datetime format
    $session_datetime = $session_date . ' ' . $session_time;

    // Prepare and execute the SQL query to insert the booking
    $sql_book = "INSERT INTO bookings (student_id, tutor_id, subject_id, session_datetime, status) VALUES (?, ?, ?, ?, 'pending')";
    $stmt = $conn->prepare($sql_book);
    $stmt->bind_param("iiis", $student_id, $tutor_id, $subject_id, $session_datetime);

    if ($stmt->execute()) {
        $booking_success = true; // Set success message
    } else {
        $booking_error = "Error booking session: " . $stmt->error; // Set error message
    }

    $stmt->close();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book a Session</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }

        .container {
            display: flex;
            width: 80%;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .sidebar {
            flex: 1;
            background-color: #333;
            color: white;
            padding: 20px;
        }

        .sidebar ul {
            list-style: none;
            padding: 0;
        }

        .sidebar li {
            margin-bottom: 10px;
        }

        .sidebar a {
            color: white;
            text-decoration: none;
            display: block;
            padding: 8px;
            border-radius: 4px;
        }

        .sidebar a:hover {
            background-color: #555;
        }

        .booking-form {
            flex: 2;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 20px;
            max-height: 80vh;
            overflow-y: auto;
        }

        .booking-form h2 {
            text-align: center;
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

        input[type="date"],
        input[type="time"],
        select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
        }

        button {
            background-color: #5cb85c;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            width: 100%;
        }

        button:hover {
            background-color: #449d44;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.4);
        }

        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 20px;
            border-radius: 8px;
            max-width: 500px;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }

        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
        }

        .close:hover,
        .close:focus {
            color: black;
            text-decoration: none;
            cursor: pointer;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="sidebar">
        <h2>Student Dashboard</h2>
        <ul>
            <li><a href="student-dashboard.php">Dashboard</a></li>
            <li><a href="student-profile.php">My Profile</a></li>
            <li><a href="student-book-session.php">Book a Session</a></li>
            <li><a href="student-bookings.php">My Bookings</a></li>
            <li><a href="student-logout.php">Logout</a></li>
        </ul>
    </div>
    <div class="booking-form">
        <h2>Book a Tutoring Session</h2>
        <?php if (isset($booking_success)): ?>
            <div class="alert alert-success">
                Session booked successfully!
            </div>
        <?php endif; ?>
        <?php if (isset($booking_error)): ?>
            <div class="alert alert-danger">
                <?php echo $booking_error; ?>
            </div>
        <?php endif; ?>
        <form method="post" action="student-book-session.php">
            <div class="form-group">
                <label for="tutor">Select Tutor:</label>
                <select class="form-control" id="tutor" name="tutor" required>
                    <?php
                    if ($result_tutors->num_rows > 0) {
                        while ($row = $result_tutors->fetch_assoc()) {
                            echo '<option value="' . $row["tutor_id"] . '">' . $row["name"] . '</option>';
                        }
                    } else {
                        echo '<option value="">No tutors available</option>';
                    }
                    ?>
                </select>
            </div>
            <div class="form-group">
                <label for="subject">Select Subject:</label>
                <select class="form-control" id="subject" name="subject" required>
                    <?php
                    if ($result_subjects->num_rows > 0) {
                        while ($row = $result_subjects->fetch_assoc()) {
                            echo '<option value="' . $row["subject_id"] . '">' . $row["name"] . '</option>';
                        }
                    } else {
                        echo '<option value="">No subjects available</option>';
                    }
                    ?>
                </select>
            </div>
            <div class="form-group">
                <label for="session_date">Session Date:</label>
                <input type="date" class="form-control" id="session_date" name="session_date" required>
            </div>
            <div class="form-group">
                <label for="session_time">Session Time:</label>
                <input type="time" class="form-control" id="session_time" name="session_time" required>
            </div>
            <button type="submit" class="btn btn-primary">Book Session</button>
        </form>
    </div>
</div>

<!-- Success Modal -->
<div id="successModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="document.getElementById('successModal').style.display='none'">&times;</span>
        <p>Session booked successfully!</p>
    </div>
</div>

<!-- Error Modal -->
<div id="errorModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="document.getElementById('errorModal').style.display='none'">&times;</span>
        <p id="errorMessage"></p>
    </div>
</div>

<script>
    // JavaScript to show the modal
    <?php if (isset($booking_success)): ?>
        document.getElementById('successModal').style.display='block';
    <?php endif; ?>

    <?php if (isset($booking_error)): ?>
        document.getElementById('errorMessage').innerText = "<?php echo $booking_error; ?>";
        document.getElementById('errorModal').style.display='block';
    <?php endif; ?>

    // Close modal function (for both modals)
    window.onclick = function(event) {
        if (event.target.className === 'modal') {
            event.target.style.display = "none";
        }
    }
</script>

</body>
</html>

