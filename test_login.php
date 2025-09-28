<?php
session_start();
require_once 'db_connect.php';

echo "<h2>Login Debug Test</h2>";

// Test database connection
if ($conn->connect_error) {
    echo "❌ Database connection failed: " . $conn->connect_error;
    exit;
} else {
    echo "✅ Database connection successful!<br>";
}

// Test user lookup
$username = "admin";
$sql = "SELECT id, username, email, password, role FROM users WHERE username = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 1) {
    $user = $result->fetch_assoc();
    echo "✅ User found: " . $user['username'] . "<br>";
    echo "User ID: " . $user['id'] . "<br>";
    echo "Email: " . $user['email'] . "<br>";
    echo "Role: " . $user['role'] . "<br>";
    echo "Password (first 20 chars): " . substr($user['password'], 0, 20) . "...<br>";
    
    // Test password verification
    $test_password = "admin123";
    echo "<h3>Password Test:</h3>";
    echo "Testing password: '$test_password'<br>";
    echo "Stored password: '$user[password]'<br>";
    
    if (password_verify($test_password, $user['password'])) {
        echo "✅ Password verification SUCCESS (hashed)<br>";
    } elseif ($test_password === $user['password']) {
        echo "✅ Password verification SUCCESS (plain text)<br>";
    } else {
        echo "❌ Password verification FAILED<br>";
    }
    
    // Test session setting
    echo "<h3>Session Test:</h3>";
    $_SESSION["loggedin"] = true;
    $_SESSION["id"] = $user['id'];
    $_SESSION["username"] = $user['username'];
    $_SESSION["role"] = $user['role'];
    
    echo "Session loggedin: " . ($_SESSION["loggedin"] ? 'true' : 'false') . "<br>";
    echo "Session id: " . $_SESSION["id"] . "<br>";
    echo "Session username: " . $_SESSION["username"] . "<br>";
    echo "Session role: " . $_SESSION["role"] . "<br>";
    
    echo "<br><a href='landpage.php'>Test Landpage Redirect</a><br>";
    echo "<a href='login.php'>Back to Login</a>";
    
} else {
    echo "❌ User not found!<br>";
}

$stmt->close();
$conn->close();
?>
