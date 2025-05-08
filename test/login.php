<?php
// Start session
session_start();

// Include database configuration
include 'db_config.php';

// Initialize variables
$error_message = "";
$success_message = "";

// Check if form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
  // Get form data
  $username = $conn->real_escape_string($_POST['username']);
  $password = $_POST['password'];
  $role = $_POST['role'];

  // Additional role-specific fields
  $admin_code = isset($_POST['admin_auth_code']) ? $conn->real_escape_string($_POST['admin_auth_code']) : "";
  $doctor_license = isset($_POST['doctor_license']) ? $conn->real_escape_string($_POST['doctor_license']) : "";
  $nurse_id = isset($_POST['nurse_id_number']) ? $conn->real_escape_string($_POST['nurse_id_number']) : "";

  // Validate input based on role
  $valid = true;

  if (empty($username) || empty($password) || empty($role)) {
    $error_message = "Please enter all required fields";
    $valid = false;
  } elseif ($role == "admin" && empty($admin_code)) {
    $error_message = "Admin authentication code is required";
    $valid = false;
  } elseif ($role == "doctor" && empty($doctor_license)) {
    $error_message = "Medical license ID is required";
    $valid = false;
  } elseif ($role == "nurse" && empty($nurse_id)) {
    $error_message = "Nursing ID is required";
    $valid = false;
  }

  // Process valid form
  if ($valid) {
    // Query to check if user exists
    $sql = "SELECT u.UserID, u.Username, u.PasswordHash, u.Role, u.LinkedID 
                FROM USER u 
                WHERE u.Username = '{$username}' AND u.Role = '{$role}'";

    $result = $conn->query($sql);

    if ($result && $result->num_rows > 0) {
      $user = $result->fetch_assoc();

      // Kiểm tra password theo cả 2 cách
      if (password_verify($password, $user['PasswordHash']) || $password === $user['PasswordHash']) {
        // Additional verification for specific roles
        $role_verified = false;

        switch ($role) {
          case 'admin':
            $role_verified = ($admin_code === "ADMIN123");
            break;

          case 'doctor':
            $doctor_sql = "SELECT DoctorID FROM DOCTOR WHERE DoctorID = {$user['LinkedID']} AND Username = '{$username}'";
            $doctor_result = $conn->query($doctor_sql);
            $role_verified = ($doctor_result && $doctor_result->num_rows > 0);
            break;

          case 'nurse':
            $nurse_sql = "SELECT NurseID FROM NURSE WHERE NurseID = {$user['LinkedID']} AND Username = '{$username}'";
            $nurse_result = $conn->query($nurse_sql);
            $role_verified = ($nurse_result && $nurse_result->num_rows > 0);
            break;

          case 'patient':
            $role_verified = true;
            break;
        }

        if ($role_verified) {
          // Update last login time
          $update_sql = "UPDATE USER SET LastLogin = NOW() WHERE UserID = {$user['UserID']}";
          $conn->query($update_sql);

          // Record login in audit log
          $ip_address = $_SERVER['REMOTE_ADDR'];
          $audit_sql = "INSERT INTO AUDIT_LOG (UserID, Timestamp, Action, TableAffected, IPAddress) 
                                  VALUES ({$user['UserID']}, NOW(), 'Login', 'USER', '{$ip_address}')";
          $conn->query($audit_sql);

          // Set session variables
          $_SESSION['user_id'] = $user['UserID'];
          $_SESSION['username'] = $user['Username'];
          $_SESSION['role'] = $user['Role'];
          $_SESSION['linked_id'] = $user['LinkedID'];

          // Redirect based on role
          switch ($role) {
            case 'admin':
              header("Refresh: 2; URL=index.php");
              break;
            case 'doctor':
              header("Refresh: 2; URL=index.php");
              break;
            case 'nurse':
              header("Refresh: 2; URL=index.php");
              break;
            case 'patient':
              header("Refresh: 2; URL=index.php");
              break;
            default:
              header("Refresh: 2; URL=index.php");
              break;
          }
        } else {
          $error_message = "Invalid credentials for the selected role";
        }
      } else {
        $error_message = "Invalid password";
      }
    } else {
      $error_message = "User not found with the provided username and role";
    }
  }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>HealthcarePortal - Login</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <style>
    :root {
      --primary-color: #8a55d7;
      --primary-dark: #6941c6;
      --light-gray: #f5f5f7;
      --text-color: #333333;
      --error-color: #e74c3c;
      --success-color: #2ecc71;
    }

    .page-header {
      display: flex;
      align-items: center;
      padding: 10px 20px;
      background-color: white;
      border-bottom: 1px solid #ddd;
      box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    }

    .logo {
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .logo-icon {
      color: #ff4d4d;
      /* Red color for the cross icon */
      font-size: 1.5rem;
      font-weight: bold;
    }

    .logo-text {
      font-size: 1.2rem;
      font-weight: 600;
      color: #333;
    }

    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
      font-family: 'Poppins', sans-serif;
    }

    body {
      min-height: 100vh;
      background-color: #fff;
      display: flex;
      flex-direction: column;
    }

    .login-container {
      display: flex;
      width: 100%;
      flex: 1;
    }

    .login-form-section {
      flex: 1;
      padding: 2rem;
      display: flex;
      flex-direction: column;
      justify-content: center;
      max-width: 1200px;
    }

    .illustration-section {
      flex: 1;
      background-color: var(--light-gray);
      display: flex;
      align-items: center;
      justify-content: center;
      background-color: #e9d9ff;
      position: relative;
      overflow: hidden;
    }

    /* Container for the doctor-patient image and decorations */
    .doctor-patient-container {
      position: relative;
      width: 350px;
      height: 350px;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    /* Add styles for the doctor-patient image */
    .doctor-patient-image {
      width: 280px;
      height: 280px;
      border-radius: 24px;
      position: relative;
      z-index: 5;
      box-shadow: 0 12px 30px rgba(0, 0, 0, 0.1);
      object-fit: cover;
    }

    /* Decorative elements for the image */
    .image-decoration {
      position: absolute;
      border-radius: 24px;
      z-index: 2;
    }

    .decoration-1 {
      width: 280px;
      height: 280px;
      background-color: rgba(255, 243, 216, 0.8);
      top: -15px;
      left: -15px;
    }

    .decoration-2 {
      width: 280px;
      height: 280px;
      background-color: rgba(209, 233, 220, 0.8);
      bottom: -15px;
      right: -15px;
    }

    .decoration-3 {
      width: 280px;
      height: 280px;
      background-color: rgba(205, 232, 246, 0.8);
      top: 15px;
      right: 15px;
    }

    .logo {
      height: 32px;
      margin-bottom: 2rem;
    }

    h1 {
      font-size: 1.8rem;
      margin-bottom: 0.5rem;
      color: var(--text-color);
    }

    .subtitle {
      color: #666;
      margin-bottom: 2rem;
    }

    .form-group {
      margin-bottom: 1.5rem;
    }

    label {
      display: block;
      margin-bottom: 0.5rem;
      color: var(--text-color);
      font-weight: 500;
    }

    input,
    select {
      width: 100%;
      padding: 0.75rem 1rem;
      border: 1px solid #ddd;
      border-radius: 0.5rem;
      font-size: 1rem;
      transition: border 0.3s ease;
    }

    input:focus,
    select:focus {
      border-color: var(--primary-color);
      outline: none;
    }

    .forgot-password {
      text-align: right;
      margin-top: 0.5rem;
    }

    .forgot-password a {
      color: var(--primary-color);
      text-decoration: none;
      font-size: 0.9rem;
    }

    .forgot-password a:hover {
      text-decoration: underline;
    }

    .btn {
      width: 100%;
      padding: 0.75rem;
      background-color: var(--primary-color);
      color: white;
      border: none;
      border-radius: 0.5rem;
      font-size: 1rem;
      cursor: pointer;
      transition: background-color 0.3s ease;
    }

    .btn:hover {
      background-color: var(--primary-dark);
    }

    .btn-google {
      background-color: white;
      color: #333;
      border: 1px solid #ddd;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 0.5rem;
      margin-top: 1rem;
    }

    .btn-google:hover {
      background-color: #f5f5f5;
    }

    .google-icon {
      width: 20px;
      height: 20px;
    }

    .or-divider {
      display: flex;
      align-items: center;
      margin: 1.5rem 0;
      color: #777;
    }

    .or-divider::before,
    .or-divider::after {
      content: "";
      flex: 1;
      height: 1px;
      background-color: #ddd;
    }

    .or-divider span {
      padding: 0 1rem;
      font-size: 0.9rem;
    }

    .role-selector {
      margin-bottom: 1.5rem;
    }

    .role-options {
      display: flex;
      gap: 0.5rem;
      margin-top: 0.5rem;
    }

    .role-option {
      flex: 1;
      border: 1px solid #ddd;
      border-radius: 0.5rem;
      padding: 1rem 0.5rem;
      display: flex;
      flex-direction: column;
      align-items: center;
      cursor: pointer;
      transition: all 0.3s ease;
    }

    .role-option.selected {
      border-color: var(--primary-color);
      background-color: rgba(138, 85, 215, 0.05);
    }

    .role-icon {
      font-size: 1.5rem;
      margin-bottom: 0.5rem;
    }

    .role-name {
      font-size: 0.9rem;
      font-weight: 500;
    }

    .illustration {
      max-width: 70%;
      position: relative;
      z-index: 2;
    }

    .bg-pattern {
      position: absolute;
      width: 100%;
      height: 100%;
      opacity: 0.2;
      background-image: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%236941c6' fill-opacity='0.4'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
    }

    @media (max-width: 768px) {
      .login-container {
        flex-direction: column-reverse;
      }

      .illustration-section {
        height: 200px;
      }

      .login-form-section {
        max-width: 100%;
      }
    }

    .error-message {
      color: var(--error-color);
      font-size: 0.9rem;
      margin-top: 0.5rem;
      <?php if (empty($error_message)) {
        echo "display: none;";
      } ?>
    }

    .success-message {
      color: var(--success-color);
      font-size: 0.9rem;
      margin-top: 0.5rem;
      <?php if (empty($success_message)) {
        echo "display: none;";
      } ?>
    }

    .logo-image {
      height: 36px;
      /* Adjust the height as needed */
      width: auto;
      /* Maintain aspect ratio */
    }
  </style>
</head>

<body>
  <!-- Page Header -->
  <header class="page-header">
    <div class="logo">
      <img src="assets/icons/healthcare.png" alt="Hospital Logo" class="logo-image">
      <span class="logo-text">Hospital's Name</span>
    </div>
  </header>
  <div class="login-container">
    <div class="login-form-section">
      <!-- Form logo -->
      <div class="logo">
        <svg xmlns="http://www.w3.org/2000/svg" width="180" height="32" viewBox="0 0 180 32" fill="none">
          <path
            d="M15.8 2.4C10.6 2.4 6.4 6.6 6.4 11.8C6.4 17 10.6 21.2 15.8 21.2C21 21.2 25.2 17 25.2 11.8C25.2 6.6 21 2.4 15.8 2.4Z"
            fill="#8A55D7" />
          <path
            d="M29.8 23.4C26.6 27.8 21.6 30.6 15.9 30.6C10.2 30.6 5.1 27.8 2 23.4C0.7 21.6 0 19.4 0 17C0 12.6 3.6 9 8 9C10.4 9 12.6 10 14 11.7C15.4 10 17.6 9 20 9C24.4 9 28 12.6 28 17C28 19.4 27.2 21.6 26 23.4H29.8Z"
            fill="#6941C6" />
        </svg>
      </div>

      <h1>Welcome back</h1>
      <p class="subtitle">Please enter your credentials to login</p>

      <form id="login-form" method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
        <div class="role-selector">
          <label>Select your role</label>
          <div class="role-options">
            <div class="role-option <?php if (isset($_POST['role']) && $_POST['role'] == 'patient') echo 'selected'; ?>" data-role="patient" onclick="selectRole('patient')">
              <div class="role-icon">👤</div>
              <div class="role-name">Patient</div>
            </div>
            <div class="role-option <?php if (isset($_POST['role']) && $_POST['role'] == 'nurse') echo 'selected'; ?>" data-role="nurse" onclick="selectRole('nurse')">
              <div class="role-icon">👩‍⚕️</div>
              <div class="role-name">Nurse</div>
            </div>
            <div class="role-option <?php if (isset($_POST['role']) && $_POST['role'] == 'doctor') echo 'selected'; ?>" data-role="doctor" onclick="selectRole('doctor')">
              <div class="role-icon">👨‍⚕️</div>
              <div class="role-name">Doctor</div>
            </div>
            <div class="role-option <?php if (isset($_POST['role']) && $_POST['role'] == 'admin') echo 'selected'; ?>" data-role="admin" onclick="selectRole('admin')">
              <div class="role-icon">🔐</div>
              <div class="role-name">Admin</div>
            </div>
          </div>
          <input type="hidden" name="role" id="selected-role" value="<?php echo isset($_POST['role']) ? $_POST['role'] : 'patient'; ?>">
        </div>

        <div class="form-group">
          <label for="username">Username</label> <!-- Changed from 'Email address' -->
          <input type="text" id="username" name="username" placeholder="Enter your username" required value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"> <!-- Changed from 'email' -->
        </div>

        <div class="form-group">
          <label for="password">Password</label>
          <input type="password" id="password" name="password" placeholder="••••••••" required>
          <div class="forgot-password">
            <a href="forgot-password.php">Forgot password?</a>
          </div>
        </div>

        <div id="admin-code" class="form-group" style="<?php echo (isset($_POST['role']) && $_POST['role'] == 'admin') ? 'display: block;' : 'display: none;'; ?>">
          <label for="admin-auth-code">Admin Authentication Code</label>
          <input type="text" id="admin-auth-code" name="admin_auth_code" placeholder="Enter admin code" value="<?php echo isset($_POST['admin_auth_code']) ? htmlspecialchars($_POST['admin_auth_code']) : ''; ?>">
        </div>

        <div id="doctor-id" class="form-group" style="<?php echo (isset($_POST['role']) && $_POST['role'] == 'doctor') ? 'display: block;' : 'display: none;'; ?>">
          <label for="doctor-license">Medical License ID</label>
          <input type="text" id="doctor-license" name="doctor_license" placeholder="Enter license number" value="<?php echo isset($_POST['doctor_license']) ? htmlspecialchars($_POST['doctor_license']) : ''; ?>">
        </div>

        <div id="nurse-id" class="form-group" style="<?php echo (isset($_POST['role']) && $_POST['role'] == 'nurse') ? 'display: block;' : 'display: none;'; ?>">
          <label for="nurse-id-number">Nursing ID</label>
          <input type="text" id="nurse-id-number" name="nurse_id_number" placeholder="Enter nursing ID" value="<?php echo isset($_POST['nurse_id_number']) ? htmlspecialchars($_POST['nurse_id_number']) : ''; ?>">
        </div>

        <div class="error-message" id="error-message"><?php echo $error_message; ?></div>
        <div class="success-message" id="success-message"><?php echo $success_message; ?></div>

        <button type="submit" class="btn">Sign in</button>



        <p style="text-align: center; margin-top: 2rem; font-size: 0.9rem; color: #666;">
          Don't have an account? <a href="register.php" style="color: var(--primary-color); text-decoration: none;">Sign up</a>
        </p>
      </form>
    </div>

    <div class="illustration-section">
      <div class="bg-pattern"></div>
      <div class="doctor-patient-container">
        <div class="image-decoration decoration-1"></div>
        <div class="image-decoration decoration-2"></div>
        <div class="image-decoration decoration-3"></div>
        <img src="unnamed.jpg" alt="Doctor with patient" class="doctor-patient-image">
      </div>
    </div>
  </div>

  <script>
    function selectRole(role) {
      // Remove selected class from all options
      document.querySelectorAll('.role-option').forEach(option => {
        option.classList.remove('selected');
      });

      // Add selected class to the clicked option
      document.querySelector(`.role-option[data-role="${role}"]`).classList.add('selected');

      // Update the hidden input value
      document.getElementById('selected-role').value = role;

      // Hide all role-specific fields
      document.getElementById('admin-code').style.display = 'none';
      document.getElementById('doctor-id').style.display = 'none';
      document.getElementById('nurse-id').style.display = 'none';

      // Show role-specific field if needed
      if (role === 'admin') {
        document.getElementById('admin-code').style.display = 'block';
      } else if (role === 'doctor') {
        document.getElementById('doctor-id').style.display = 'block';
      } else if (role === 'nurse') {
        document.getElementById('nurse-id').style.display = 'block';
      }
    }

    // Pre-select role from post or default to patient
    <?php
    if (isset($_POST['role'])) {
      echo "selectRole('" . $_POST['role'] . "');";
    } else {
      echo "selectRole('patient');";
    }
    ?>
  </script>
</body>

</html>
<?php
// Close the database connection
$conn->close();
?>