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
    display: none;
  }

  .form-group.error input {
    border-color: #e74c3c;
  }

  .form-group.error .error-message {
    display: block;
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

  .login-divider {
    display: flex;
    align-items: center;
    margin: 20px 0;
    color: #777;
  }

  .login-divider::before,
  .login-divider::after {
    content: "";
    flex: 1;
    height: 1px;
    background-color: #ddd;
  }

  .login-divider span {
    padding: 0 10px;
  }

  .social-login {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
  }

  .social-button {
    flex: 1;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 5px;
    background-color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: background-color 0.3s;
  }

  .social-button:hover {
    background-color: #f5f5f5;
  }

  .register-link {
    text-align: center;
    margin-top: 20px;
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
        <a href="index.html" class="back-link">
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
        <form class="login-form" id="loginForm">
          <div class="form-group" id="emailGroup">
            <label for="email">Email Address</label>
            <input type="email" id="email" placeholder="Enter your email">
            <div class="error-message">Please enter a valid email address</div>
          </div>
          <div class="form-group" id="passwordGroup">
            <label for="password">Password</label>
            <input type="password" id="password" placeholder="Enter your password">
            <div class="error-message">Password must be at least 6 characters</div>
          </div>
          <div class="remember-forgot">
            <div class="remember-me">
              <input type="checkbox" id="remember">
              <label for="remember">Remember me</label>
            </div>
            <a href="#">Forgot password?</a>
          </div>
          <button type="submit" class="login-button">Log In</button>
          <div class="login-divider">
            <span>OR</span>
          </div>
          <div class="social-login">
            <button type="button" class="social-button">Google</button>
          </div>
          <div class="register-link">
            Don't have an account? <a href="register.html">Register now</a>
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
    // Form Validation
    const loginForm = document.getElementById('loginForm');
    const emailGroup = document.getElementById('emailGroup');
    const passwordGroup = document.getElementById('passwordGroup');
    const emailInput = document.getElementById('email');
    const passwordInput = document.getElementById('password');

    // Email validation regex
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

    loginForm.addEventListener('submit', function(e) {
      e.preventDefault();
      let isValid = true;

      // Validate email
      if (!emailRegex.test(emailInput.value)) {
        emailGroup.classList.add('error');
        isValid = false;
      } else {
        emailGroup.classList.remove('error');
      }

      // Validate password
      if (passwordInput.value.length < 6) {
        passwordGroup.classList.add('error');
        isValid = false;
      } else {
        passwordGroup.classList.remove('error');
      }

      // If form is valid, redirect to dashboard
      if (isValid) {
        // Determine which dashboard to redirect to based on user type
        // For demo purposes, we'll use a simple check
        const email = emailInput.value.toLowerCase();
        
        if (email.includes('admin')) {
          window.location.href = 'admin-dashboard.html';
        } else if (email.includes('mentor')) {
          window.location.href = 'mentor-dashboard.html';
        } else {
          window.location.href = 'student-dashboard.html';
        }
      }
    });

    // Clear error on input
    emailInput.addEventListener('input', function() {
      emailGroup.classList.remove('error');
    });

    passwordInput.addEventListener('input', function() {
      passwordGroup.classList.remove('error');
    });
  </script>
</body>
</html>

