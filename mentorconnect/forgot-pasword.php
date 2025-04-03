<?php
// Initialize the session
session_start();

// Include database connection
require_once "config/db_connect.php";

// Define variables and initialize with empty values
$email = "";
$email_err = "";
$success_message = "";
$error_message = "";

// Processing form data when form is submitted
if($_SERVER["REQUEST_METHOD"] == "POST"){
    
    // Validate email
    if(empty(trim($_POST["email"]))){
        $email_err = "Please enter your email.";
    } else{
        // Check if email exists
        $sql = "SELECT user_id FROM users WHERE email = ?";
        
        if($stmt = $conn->prepare($sql)){
            $stmt->bind_param("s", $param_email);
            $param_email = trim($_POST["email"]);
            
            if($stmt->execute()){
                $stmt->store_result();
                
                if($stmt->num_rows == 1){
                    $email = trim($_POST["email"]);
                    
                    // Generate a unique token
                    $token = bin2hex(random_bytes(32));
                    $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
                    
                    // Store token in database
                    $sql = "INSERT INTO password_reset_tokens (email, token, expires_at) VALUES (?, ?, ?)";
                    
                    if($reset_stmt = $conn->prepare($sql)){
                        $reset_stmt->bind_param("sss", $email, $token, $expires);
                        
                        if($reset_stmt->execute()){
                            // Send email with reset link (in a real application)
                            // For this demo, we'll just show the reset link
                            $reset_link = "http://" . $_SERVER['HTTP_HOST'] . "/reset-password.php?token=" . $token;
                            
                            $success_message = "Password reset instructions have been sent to your email.";
                            
                            // In a real application, you would send an email here
                            // For demo purposes, we'll display the reset link
                            $success_message .= "<br><br>For demo purposes, here's the reset link: <a href='" . $reset_link . "'>" . $reset_link . "</a>";
                        } else{
                            $error_message = "Oops! Something went wrong. Please try again later.";
                        }
                        
                        $reset_stmt->close();
                    }
                } else{
                    $email_err = "No account found with that email address.";
                }
            } else{
                $error_message = "Oops! Something went wrong. Please try again later.";
            }
            
            $stmt->close();
        }
    }
    
    // Close connection
    closeConnection($conn);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Forgot Password - MentorConnect Platform</title>
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
    min-height: 100vh;
    display: flex;
    flex-direction: column;
  }

  a {
    text-decoration: none;
    color: #3498db;
    transition: color 0.3s;
  }

  a:hover {
    color: #2980b9;
  }

  /* Header */
  header {
    background-color: #fff;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    padding: 15px 0;
  }

  .container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 20px;
  }

  .header-container {
    display: flex;
    justify-content: space-between;
    align-items: center;
  }

  .logo {
    display: flex;
    align-items: center;
  }

  .logo-icon {
    width: 40px;
    height: 40px;
    background-color: #3498db;
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    margin-right: 10px;
  }

  .logo-text {
    font-size: 20px;
    font-weight: bold;
  }

  .back-link {
    display: flex;
    align-items: center;
    color: #333;
    font-weight: 500;
  }

  .back-link:hover {
    color: #3498db;
  }

  .back-icon {
    margin-right: 5px;
  }

  /* Main Content */
  main {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 40px 20px;
  }

  .forgot-password-container {
    max-width: 500px;
    width: 100%;
    background-color: white;
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
    padding: 40px;
  }

  .forgot-password-header {
    text-align: center;
    margin-bottom: 30px;
  }

  .forgot-password-header h1 {
    font-size: 2rem;
    color: #333;
    margin-bottom: 10px;
  }

  .forgot-password-header p {
    color: #777;
  }

  .forgot-password-form {
    margin: 0 auto;
  }

  .form-group {
    margin-bottom: 20px;
  }

  .form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
    color: #555;
  }

  .form-group input {
    width: 100%;
    padding: 12px 15px;
    border: 1px solid #ddd;
    border-radius: 5px;
    font-size: 1rem;
    transition: border-color 0.3s;
  }

  .form-group input:focus {
    outline: none;
    border-color: #3498db;
  }

  .form-group .error-message {
    color: #e74c3c;
    font-size: 0.9rem;
    margin-top: 5px;
  }

  .form-group.error input {
    border-color: #e74c3c;
  }

  .submit-button {
    width: 100%;
    padding: 12px;
    background-color: #3498db;
    color: white;
    border: none;
    border-radius: 5px;
    font-size: 1rem;
    font-weight: bold;
    cursor: pointer;
    transition: background-color 0.3s;
  }

  .submit-button:hover {
    background-color: #2980b9;
  }

  .login-link {
    text-align: center;
    margin-top: 20px;
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

  /* Footer */
  footer {
    background-color: #2c3e50;
    color: white;
    padding: 20px 0;
    text-align: center;
  }

  .footer-content {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 20px;
  }
</style>
</head>
<body>
  <!-- Header -->
  <header>
    <div class="container">
      <div class="header-container">
        <div class="logo">
          <div class="logo-icon">MC</div>
          <div class="logo-text">MentorConnect</div>
        </div>
        <a href="login.php" class="back-link">
          <span class="back-icon">←</span> Back to Login
        </a>
      </div>
    </div>
  </header>

  <!-- Main Content -->
  <main>
    <div class="forgot-password-container">
      <div class="forgot-password-header">
        <h1>Forgot Password</h1>
        <p>Enter your email address to reset your password</p>
      </div>
      
      <?php 
      if(!empty($success_message)){
          echo '<div class="alert alert-success">' . $success_message . '</div>';
      }
      
      if(!empty($error_message)){
          echo '<div class="alert alert-danger">' . $error_message . '</div>';
      }
      ?>
      
      <form class="forgot-password-form" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
        <div class="form-group <?php echo (!empty($email_err)) ? 'error' : ''; ?>">
          <label for="email">Email Address</label>
          <input type="email" id="email" name="email" placeholder="Enter your email" value="<?php echo $email; ?>">
          <div class="error-message"><?php echo $email_err; ?></div>
        </div>
        <button type="submit" class="submit-button">Reset Password</button>
        <div class="login-link">
          Remember your password? <a href="login.php">Log in</a>
        </div>
      </form>
    </div>
  </main>

  <!-- Footer -->
  <footer>
    <div class="footer-content">
      <p>&copy; 2025 MentorConnect platform. All rights reserved.</p>
    </div>
  </footer>

  <script>
    // Client-side validation
    const form = document.querySelector('.forgot-password-form');
    const emailInput = document.getElementById('email');
    
    form.addEventListener('submit', function(e) {
      let isValid = true;
      const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      
      // Validate email
      if (!emailRegex.test(emailInput.value)) {
        document.querySelector('.form-group').classList.add('error');
        document.querySelector('.error-message').textContent = 'Please enter a valid email address';
        isValid = false;
      } else {
        document.querySelector('.form-group').classList.remove('error');
      }
      
      if (!isValid) {
        e.preventDefault();
      }
    });
    
    // Clear error on input
    emailInput.addEventListener('input', function() {
      document.querySelector('.form-group').classList.remove('error');
    });
  </script>
</body>
</html>

