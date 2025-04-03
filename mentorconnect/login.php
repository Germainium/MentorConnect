<?php
// Initialize the session
session_start();

// Check if the user is already logged in, if yes then redirect to appropriate dashboard
if(isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true){
    if($_SESSION["user_type"] === "admin") {
        header("location: admin.php");
    } else if($_SESSION["user_type"] === "mentor") {
        header("location: mentor.php");
    } else {
        header("location: student.php");
    }
    exit;
}

// Include database connection
require_once "config/db_connect.php";

// Define variables and initialize with empty values
$email = $password = "";
$email_err = $password_err = $login_err = "";
$email_exists = false;

// Processing form data when form is submitted
if($_SERVER["REQUEST_METHOD"] == "POST"){
 
    // Check if email is empty
    if(empty(trim($_POST["email"]))){
        $email_err = "Please enter your email.";
    } else{
        $email = trim($_POST["email"]);
        
        // Check if email exists in the database
        $sql = "SELECT user_id FROM users WHERE email = ?";
        if($stmt = $conn->prepare($sql)){
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $stmt->store_result();
            
            if($stmt->num_rows == 1){
                $email_exists = true;
            } else {
                $email_err = "This email is not registered. <a href='register.php'>Register now</a>";
            }
            $stmt->close();
        }
    }
    
    // Check if password is empty
    if(empty(trim($_POST["password"]))){
        $password_err = "Please enter your password.";
    } else{
        $password = trim($_POST["password"]);
    }
    
    // Validate credentials
    if(empty($email_err) && empty($password_err) && $email_exists){
        // Prepare a select statement
        $sql = "SELECT user_id, first_name, last_name, email, password, user_type FROM users WHERE email = ?";
        
        if($stmt = $conn->prepare($sql)){
            // Bind variables to the prepared statement as parameters
            $stmt->bind_param("s", $param_email);
            
            // Set parameters
            $param_email = $email;
            
            // Attempt to execute the prepared statement
            if($stmt->execute()){
                // Store result
                $stmt->store_result();
                
                // Check if email exists, if yes then verify password
                if($stmt->num_rows == 1){                    
                    // Bind result variables
                    $stmt->bind_result($user_id, $first_name, $last_name, $email, $hashed_password, $user_type);
                    if($stmt->fetch()){
                        if(password_verify($password, $hashed_password)){
                            // Password is correct, so start a new session
                            session_start();
                            
                            // Store data in session variables
                            $_SESSION["loggedin"] = true;
                            $_SESSION["user_id"] = $user_id;
                            $_SESSION["first_name"] = $first_name;
                            $_SESSION["last_name"] = $last_name;
                            $_SESSION["email"] = $email;
                            $_SESSION["user_type"] = $user_type;
                            
                            // For debugging - you can remove this after fixing the issue
                            if($user_type === "admin") {
                                echo "<script>console.log('Admin login successful. Redirecting to admin.php');</script>";
                            }
                            
                            // Redirect user to appropriate dashboard
                            if($user_type === "admin") {
                                header("location: admin.php");
                            } else if($user_type === "mentor") {
                                header("location: mentor.php");
                            } else {
                                header("location: student.php");
                            }
                        } else{
                            // Password is not valid
                            $password_err = "The password you entered is not valid.";
                        }
                    }
                }
            } else{
                $login_err = "Oops! Something went wrong. Please try again later.";
            }

            // Close statement
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
<title>Login - MentorConnect Platform</title>
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

  .login-container {
    display: flex;
    max-width: 900px;
    background-color: white;
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
  }

  .login-image {
    flex: 1;
    background: linear-gradient(rgba(52, 152, 219, 0.8), rgba(52, 152, 219, 0.8)), url('https://images.unsplash.com/photo-1516321318423-f06f85e504b3?ixlib=rb-1.2.1&auto=format&fit=crop&w=1350&q=80') center/cover no-repeat;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 40px;
    color: white;
    text-align: center;
  }

  .login-image-content h2 {
    font-size: 2rem;
    margin-bottom: 20px;
  }

  .login-image-content p {
    font-size: 1.1rem;
    margin-bottom: 30px;
  }

  .login-form-container {
    flex: 1;
    padding: 40px;
  }

  .login-header {
    text-align: center;
    margin-bottom: 30px;
  }

  .login-header h1 {
    font-size: 2rem;
    color: #333;
    margin-bottom: 10px;
  }

  .login-header p {
    color: #777;
  }

  .login-form {
    max-width: 400px;
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

  .remember-forgot {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
  }

  .remember-me {
    display: flex;
    align-items: center;
  }

  .remember-me input {
    margin-right: 8px;
  }

  .login-button {
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

  .login-button:hover {
    background-color: #2980b9;
  }

  .register-link {
    text-align: center;
    margin-top: 20px;
  }

  /* Alert Messages */
  .alert {
    padding: 15px;
    margin-bottom: 20px;
    border-radius: 5px;
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

  /* Responsive Styles */
  @media (max-width: 768px) {
    .login-container {
      flex-direction: column;
    }

    .login-image {
      display: none;
    }

    .login-form-container {
      padding: 30px 20px;
    }
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
        <a href="index.php" class="back-link">
          <span class="back-icon">←</span> Back to Home
        </a>
      </div>
    </div>
  </header>

  <!-- Main Content -->
  <main>
    <div class="login-container">
      <div class="login-image">
        <div class="login-image-content">
          <h2>Welcome Back!</h2>
          <p>Log in to continue your career growth journey with personalized mentorship.</p>
        </div>
      </div>
      <div class="login-form-container">
        <div class="login-header">
          <h1>Log In</h1>
          <p>Enter your credentials to access your account</p>
        </div>
        
        <?php 
        if(!empty($login_err)){
            echo '<div class="alert alert-danger">' . $login_err . '</div>';
        }        
        ?>

        <form class="login-form" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
          <div class="form-group <?php echo (!empty($email_err)) ? 'error' : ''; ?>">
            <label for="email">Email Address</label>
            <input type="email" id="email" name="email" placeholder="Enter your email" value="<?php echo $email; ?>">
            <?php if (!empty($email_err)): ?>
              <div class="error-message"><?php echo $email_err; ?></div>
            <?php endif; ?>
          </div>
          <div class="form-group <?php echo (!empty($password_err)) ? 'error' : ''; ?>">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" placeholder="Enter your password">
            <?php if (!empty($password_err)): ?>
              <div class="error-message"><?php echo $password_err; ?></div>
            <?php endif; ?>
          </div>
          <div class="remember-forgot">
            <div class="remember-me">
              <input type="checkbox" id="remember" name="remember">
              <label for="remember">Remember me</label>
            </div>
            <!-- Removed the forgot password link as requested -->
          </div>
          <button type="submit" class="login-button">Log In</button>
          
          <div class="register-link">
            Don't have an account? <a href="register.php">Register now</a>
          </div>
        </form>
      </div>
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
    const loginForm = document.querySelector('.login-form');
    const emailInput = document.getElementById('email');
    const passwordInput = document.getElementById('password');
    
    // Only add client-side validation if there are no server-side errors
    <?php if(empty($login_err) && empty($email_err) && empty($password_err)): ?>
    
    loginForm.addEventListener('submit', function(e) {
      let isValid = true;
      const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      
      // Validate email
      if (!emailRegex.test(emailInput.value)) {
        document.querySelector('.form-group:nth-child(1)').classList.add('error');
        document.querySelector('.form-group:nth-child(1) .error-message').textContent = 'Please enter a valid email address';
        isValid = false;
      } else {
        document.querySelector('.form-group:nth-child(1)').classList.remove('error');
      }
      
      // Validate password
      if (passwordInput.value.length < 6) {
        document.querySelector('.form-group:nth-child(2)').classList.add('error');
        document.querySelector('.form-group:nth-child(2) .error-message').textContent = 'Password must be at least 6 characters';
        isValid = false;
      } else {
        document.querySelector('.form-group:nth-child(2)').classList.remove('error');
      }
      
      if (!isValid) {
        e.preventDefault();
      }
    });
    
    // Clear error on input
    emailInput.addEventListener('input', function() {
      document.querySelector('.form-group:nth-child(1)').classList.remove('error');
      document.querySelector('.form-group:nth-child(1) .error-message').textContent = '';
    });
    
    passwordInput.addEventListener('input', function() {
      document.querySelector('.form-group:nth-child(2)').classList.remove('error');
      document.querySelector('.form-group:nth-child(2) .error-message').textContent = '';
    });
    
    <?php endif; ?>
  </script>
</body>
</html>

