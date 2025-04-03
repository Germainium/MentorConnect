<?php
// Include database connection
require_once "config/db_connect.php";

// Display current admin users
echo "<h2>Current Admin Users</h2>";
$sql = "SELECT user_id, email, password FROM users WHERE user_type = 'admin'";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    echo "<table border='1'>";
    echo "<tr><th>ID</th><th>Email</th><th>Current Password Hash</th></tr>";
    while($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row["user_id"] . "</td>";
        echo "<td>" . $row["email"] . "</td>";
        echo "<td>" . $row["password"] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "No admin users found!";
}

// Update admin password
if (isset($_POST['update_password'])) {
    $admin_email = $_POST['admin_email'];
    $new_password = $_POST['new_password'];
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    
    $update_sql = "UPDATE users SET password = ? WHERE email = ? AND user_type = 'admin'";
    
    if ($stmt = $conn->prepare($update_sql)) {
        $stmt->bind_param("ss", $hashed_password, $admin_email);
        
        if ($stmt->execute()) {
            echo "<p style='color:green'>Password updated successfully for $admin_email!</p>";
            echo "<p>New password: $new_password</p>";
            echo "<p>New hash: $hashed_password</p>";
        } else {
            echo "<p style='color:red'>Error updating password: " . $conn->error . "</p>";
        }
        $stmt->close();
    }
}

// Create admin user if needed
if (isset($_POST['create_admin'])) {
    $admin_email = $_POST['new_admin_email'];
    $admin_password = $_POST['admin_password'];
    $hashed_password = password_hash($admin_password, PASSWORD_DEFAULT);
    $first_name = "Admin";
    $last_name = "User";
    
    // Check if email already exists
    $check_sql = "SELECT user_id FROM users WHERE email = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("s", $admin_email);
    $check_stmt->execute();
    $check_stmt->store_result();
    
    if ($check_stmt->num_rows > 0) {
        echo "<p style='color:red'>Email already exists!</p>";
    } else {
        $insert_sql = "INSERT INTO users (email, password, first_name, last_name, user_type) VALUES (?, ?, ?, ?, 'admin')";
        
        if ($stmt = $conn->prepare($insert_sql)) {
            $stmt->bind_param("ssss", $admin_email, $hashed_password, $first_name, $last_name);
            
            if ($stmt->execute()) {
                echo "<p style='color:green'>Admin user created successfully!</p>";
                echo "<p>Email: $admin_email</p>";
                echo "<p>Password: $admin_password</p>";
            } else {
                echo "<p style='color:red'>Error creating admin: " . $conn->error . "</p>";
            }
            $stmt->close();
        }
    }
    $check_stmt->close();
}

// Close connection
closeConnection($conn);
?>

<h2>Update Admin Password</h2>
<form method="post">
    <label for="admin_email">Admin Email:</label>
    <input type="email" name="admin_email" required>
    <br><br>
    <label for="new_password">New Password:</label>
    <input type="text" name="new_password" value="Combined12" required>
    <br><br>
    <input type="submit" name="update_password" value="Update Password">
</form>

<h2>Create New Admin User</h2>
<form method="post">
    <label for="new_admin_email">Email:</label>
    <input type="email" name="new_admin_email" required>
    <br><br>
    <label for="admin_password">Password:</label>
    <input type="text" name="admin_password" value="Combined12" required>
    <br><br>
    <input type="submit" name="create_admin" value="Create Admin">
</form>

<p style="color:red;font-weight:bold">IMPORTANT: Delete this file after use for security reasons!</p>