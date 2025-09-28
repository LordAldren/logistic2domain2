<?php
require_once 'db_connect.php';

echo "<h2>Database Users Check</h2>";

// Check current users in database
$result = $conn->query("SELECT id, username, email, role, created_at FROM users");
if ($result->num_rows > 0) {
    echo "<h3>Users in Database:</h3>";
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background-color: #f2f2f2;'><th>ID</th><th>Username</th><th>Email</th><th>Role</th><th>Created</th></tr>";
    while($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td><strong>" . $row['username'] . "</strong></td>";
        echo "<td>" . $row['email'] . "</td>";
        echo "<td>" . $row['role'] . "</td>";
        echo "<td>" . $row['created_at'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "❌ No users found in database<br>";
}

// Test login with first user
$result = $conn->query("SELECT id, username, email, password, role FROM users LIMIT 1");
if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
    echo "<h3>Testing Login for User: " . $user['username'] . "</h3>";
    echo "User ID: " . $user['id'] . "<br>";
    echo "Username: " . $user['username'] . "<br>";
    echo "Email: " . $user['email'] . "<br>";
    echo "Role: " . $user['role'] . "<br>";
    echo "Password Hash: " . substr($user['password'], 0, 20) . "...<br>";
}

$conn->close();

echo "<br><a href='login.php'>Go to Login Page</a>";
?>
