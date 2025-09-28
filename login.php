<?php
session_start();

if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true) {
    header("location: landpage.php");
    exit;
}

require_once 'db_connect.php';
require_once 'mailer.php'; 

$username = "";
$error_message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST["username"]);
    $password = trim($_POST["password"]);

    if (empty($username) || empty($password)) {
        $error_message = "Please enter both username and password.";
    } else {
        $sql = "SELECT id, username, email, password, role, failed_login_attempts, lockout_until FROM users WHERE username = ?";
        
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("s", $username);
            
            if ($stmt->execute()) {
                $stmt->store_result();
                
                if ($stmt->num_rows == 1) {                    
                    $stmt->bind_result($id, $db_username, $email, $hashed_password, $role, $failed_attempts, $lockout_until);
                    if ($stmt->fetch()) {
                        
                        if ($lockout_until !== null) {
                            $now = new DateTime();
                            $lockout_time = new DateTime($lockout_until);
                            if ($now < $lockout_time) {
                                $error_message = "Account is locked. Please try again later.";
                            }
                        }

                        if (empty($error_message)) {
                            if (password_verify($password, $hashed_password)) {
                                $reset_stmt = $conn->prepare("UPDATE users SET failed_login_attempts = 0, lockout_until = NULL WHERE id = ?");
                                $reset_stmt->bind_param("i", $id);
                                $reset_stmt->execute();
                                $reset_stmt->close();

                                $otp_code = rand(100000, 999999);
                                $otp_expires = (new DateTime())->add(new DateInterval("PT5M"))->format('Y-m-d H:i:s');
                                
                                $otp_stmt = $conn->prepare("UPDATE users SET otp_code = ?, otp_expires_at = ? WHERE id = ?");
                                $otp_stmt->bind_param("ssi", $otp_code, $otp_expires, $id);
                                $otp_stmt->execute();
                                $otp_stmt->close();
                                
                                $subject = "Your OTP for SLATE Logistics Login";
                                $body = "<h3>Login Verification</h3>
                                         <p>Your One-Time Password (OTP) is: <strong>$otp_code</strong></p>
                                         <p>This code will expire in 5 minutes.</p>
                                         <p>If you did not request this, please ignore this email.</p>";
                                
                                if (sendEmail($email, $subject, $body)) {
                                    $_SESSION['otp_user_id'] = $id;
                                    header("location: verify_otp.php");
                                    exit;
                                } else {
                                    $error_message = "Failed to send OTP email. Please try again later.";
                                }

                            } else {
                                $failed_attempts++;
                                $max_attempts = 5;
                                if ($failed_attempts >= $max_attempts) {
                                    $lockout_until_time = (new DateTime())->add(new DateInterval("PT15M"))->format('Y-m-d H:i:s');
                                    $lock_stmt = $conn->prepare("UPDATE users SET failed_login_attempts = ?, lockout_until = ? WHERE id = ?");
                                    $lock_stmt->bind_param("isi", $failed_attempts, $lockout_until_time, $id);
                                    $lock_stmt->execute();
                                    $lock_stmt->close();
                                    $error_message = "Account locked for 15 minutes due to too many failed attempts.";
                                } else {
                                    $update_stmt = $conn->prepare("UPDATE users SET failed_login_attempts = ? WHERE id = ?");
                                    $update_stmt->bind_param("ii", $failed_attempts, $id);
                                    $update_stmt->execute();
                                    $update_stmt->close();
                                    $error_message = "Invalid username or password.";
                                }
                            }
                        }
                    }
                } else {
                    $error_message = "Invalid username or password.";
                }
            } else {
                $error_message = "Oops! Something went wrong.";
            }
            $stmt->close();
        }
    }
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Login - SLATE System</title>
  <link rel="stylesheet" href="login-style.css?v=1.8">
  <link rel="stylesheet" href="loader.css">
</head>
<body class="login-page-body">
  
  <div id="loading-overlay">
    <div class="loader-content">
        <img src="logo.png" alt="SLATE Logo" class="loader-logo-main">
        <p id="loader-text">Initializing System...</p>
        <div class="road">
          <div class="vehicle-container vehicle-1">
            <svg viewBox="0 0 512 512" xmlns="http://www.w3.org/2000/svg"><path d="M503.3 337.2c-7.2-21.6-21.6-36-43.2-43.2l-43.2-14.4V232c0-23.9-19.4-43.2-43.2-43.2H256V96c0-12.7-5.1-24.9-14.1-33.9L208 28.3c-9-9-21.2-14.1-33.9-14.1H48C21.5 14.2 0 35.7 0 62.2V337c0 23.9 19.4 43.2 43.2 43.2H64c0 35.3 28.7 64 64 64s64-28.7 64-64h128c0 35.3 28.7 64 64 64s64-28.7 64-64h17.3c23.9 0 43.2-19.4 43.2-43.2V337.2zM128 401c-17.7 0-32-14.3-32-32s14.3-32 32-32 32 14.3 32 32-14.3 32-32 32zm256 0c-17.7 0-32-14.3-32-32s14.3-32 32-32 32 14.3 32 32-14.3 32-32 32zm0-192h-88.8v-48H384v48z"/></svg>
          </div>
          
           <div class="vehicle-container vehicle-3">
             <svg viewBox="0 0 512 512" xmlns="http://www.w3.org/2000/svg"><path d="M503.3 337.2c-7.2-21.6-21.6-36-43.2-43.2l-43.2-14.4V232c0-23.9-19.4-43.2-43.2-43.2H256V96c0-12.7-5.1-24.9-14.1-33.9L208 28.3c-9-9-21.2-14.1-33.9-14.1H48C21.5 14.2 0 35.7 0 62.2V337c0 23.9 19.4 43.2 43.2 43.2H64c0 35.3 28.7 64 64 64s64-28.7 64-64h128c0 35.3 28.7 64 64 64s64-28.7 64-64h17.3c23.9 0 43.2-19.4 43.2-43.2V337.2zM128 401c-17.7 0-32-14.3-32-32s14.3-32 32-32 32 14.3 32 32-14.3 32-32 32zm256 0c-17.7 0-32-14.3-32-32s14.3-32 32-32 32 14.3 32 32-14.3 32-32 32zm0-192h-88.8v-48H384v48z"/></svg>
          </div>
        </div>
    </div>
  </div>

  <div class="main-container">
    <div class="login-container">
      <div class="welcome-panel">
        <div class="animation-container">
            <div class="road-line"></div>
            <div class="road-line" style="bottom: 65%; animation-delay: -2s; opacity: 0.3;"></div>
            
            <!-- Container Truck -->
            <div class="truck-container truck-1">
                <div class="predictive-route"></div>
                <div class="data-trail"><span></span><span></span><span></span></div>
                <svg viewBox="0 0 640 512" xmlns="http://www.w3.org/2000/svg"><path d="M624 352h-16V243.9c0-12.7-5.1-24.9-14.1-33.9L494 110.1c-9-9-21.2-14.1-33.9-14.1H416V48c0-26.5-21.5-48-48-48H112C85.5 0 64 21.5 64 48v48H48c-26.5 0-48 21.5-48 48v192c0 26.5 21.5 48 48 48h16c0 35.3 28.7 64 64 64s64-28.7 64-64h192c0 35.3 28.7 64 64 64s64-28.7 64-64h16c26.5 0 48-21.5 48-48V368c0-8.8-7.2-16-16-16zM128 400c-17.7 0-32-14.3-32-32s14.3-32 32-32 32 14.3 32 32-14.3 32-32 32zm384 0c-17.7 0-32-14.3-32-32s14.3-32 32-32 32 14.3 32 32-14.3 32-32 32zM480 224H128V144h288v48c0 26.5 21.5 48 48 48h16v-16z"/></svg>
            </div>

            
            <!-- Panel Van -->
            <div class="truck-container truck-3">
                <div class="predictive-route"></div>
                <div class="data-trail"><span></span><span></span><span></span></div>
                <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M20,8H4V6H20V8M21.17,3.24L18.39,2.14C17.6,1.86 16.8,2 16.2,2.58L15,4H5C3.89,4 3,4.89 3,6V17A2,2 0 0,0 5,19H6A3,3 0 0,0 9,22A3,3 0 0,0 12,19H15A3,3 0 0,0 18,22A3,3 0 0,0 21,19H22C22.55,19 23,18.55 23,18V7C23,5.16 21.95,3.5 20.2,3.23L21.17,3.24M9,20.5A1.5,1.5 0 0,1 7.5,19A1.5,1.5 0 0,1 9,17.5A1.5,1.5 0 0,1 10.5,19A1.5,1.5 0 0,1 9,20.5M18,20.5A1.5,1.5 0 0,1 16.5,19A1.5,1.5 0 0,1 18,17.5A1.5,1.5 0 0,1 19.5,19A1.5,1.5 0 0,1 18,20.5M21,17H5V9H21V17Z" /></svg>
            </div>
        </div>
      </div>
      <div class="login-panel">
        <div class="login-box">
          <img src="logo.png" alt="SLATE Logo">
          <h2>SLATE Login</h2>
          <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <input type="text" name="username" placeholder="Username" required value="">
            <div class="password-wrapper">
              <input type="password" name="password" id="password" placeholder="Password" required value="">
              <span class="toggle-password" id="togglePassword"></span>
            </div>
            <div class="forgot-password">
              <a href="forgot_password.php">Forgot Password?</a>
            </div>
            <button type="submit">Log In</button>
            <?php if(!empty($error_message)){ echo '<div class="error-message">' . $error_message . '</div>'; } ?>
          </form>
          
        </div>
      </div>
    </div>
  </div>
  <footer>
    © <?php echo date("Y"); ?> SLATE Freight Management System. All rights reserved. &nbsp;|&nbsp;
    <a href="terms.php" target="_blank">Terms & Conditions</a> &nbsp;|&nbsp;
    <a href="privacy.php" target="_blank">Privacy Policy</a>
  </footer>

  <script src="loader.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', function() {
        const togglePassword = document.querySelector('#togglePassword');
        const password = document.querySelector('#password');

        const eyeIcon = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" width="20" height="20"><path d="M10 12.5a2.5 2.5 0 100-5 2.5 2.5 0 000 5z" /><path fill-rule="evenodd" d="M.664 10.59a1.651 1.651 0 010-1.18l3.25-3.25a1.651 1.651 0 012.333 0L10 9.899l3.753-3.753a1.651 1.651 0 012.333 0l3.25 3.25a1.651 1.651 0 010 1.18l-3.25 3.25a1.651 1.651 0 01-2.333 0L10 10.101l-3.753 3.753a1.651 1.651 0 01-2.333 0l-3.25-3.25z" clip-rule="evenodd" /></svg>`;
        const eyeSlashIcon = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" width="20" height="20"><path fill-rule="evenodd" d="M3.707 2.293a1 1 0 00-1.414 1.414l14 14a1 1 0 001.414-1.414l-1.473-1.473A10.014 10.014 0 0019.542 10C18.268 5.943 14.478 3 10 3a9.958 9.958 0 00-4.512 1.074l-1.78-1.781z" clip-rule="evenodd" /><path d="M2 10C3.268 5.943 7.022 3 10 3a9.958 9.958 0 014.512 1.074l-1.78 1.781a7.006 7.006 0 00-2.732 0l-4.044 4.044a3.5 3.5 0 005.173 5.173l1.78 1.781A9.958 9.958 0 0110 17c-4.97 0-9.268-4.057-10.458-9.943a1.052 1.052 0 01.023-.112zM10 12.5a2.5 2.5 0 100-5 2.5 2.5 0 000 5z" /></svg>`;

        togglePassword.innerHTML = eyeIcon;

        togglePassword.addEventListener('click', function (e) {
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);
            this.innerHTML = type === 'password' ? eyeIcon : eyeSlashIcon;
        });
    });
  </script>
</body>
</html>

