<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

// RBAC check - Admin lang ang pwedeng pumasok dito
if ($_SESSION['role'] !== 'admin') {
    if ($_SESSION['role'] === 'driver') {
        header("location: mobile_app.php");
    } else {
        header("location: landpage.php");
    }
    exit;
}

require_once 'db_connect.php';
$message = '';
$admin_id = $_SESSION['id'];
$selected_driver_user_id = isset($_GET['driver_user_id']) ? (int)$_GET['driver_user_id'] : null;

// Handle Send Message
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['send_message'])) {
    $message_text = $_POST['message_text'];
    $receiver_id = $_POST['receiver_id']; 
    
    if (!empty($message_text) && !empty($receiver_id)) {
        $sql = "INSERT INTO messages (sender_id, receiver_id, message_text) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iis", $admin_id, $receiver_id, $message_text);
        if($stmt->execute()){
             header("Location: admin_messaging.php?driver_user_id=" . $receiver_id);
             exit;
        } else {
            $message = "<div class='message-banner error'>Failed to send message.</div>";
        }
        $stmt->close();
    }
}

// Fetch active drivers
$active_drivers = $conn->query("SELECT d.id, d.name, u.id as user_id FROM drivers d JOIN users u ON d.user_id = u.id WHERE d.status = 'Active'");
$messages_result = null;
if($selected_driver_user_id){
    $messages_result = $conn->query("
        SELECT u_sender.id as sender_id, u_sender.username as sender, message_text, sent_at 
        FROM messages 
        JOIN users u_sender ON messages.sender_id = u_sender.id 
        WHERE (sender_id = $admin_id AND receiver_id = $selected_driver_user_id) OR (sender_id = $selected_driver_user_id AND receiver_id = $admin_id) 
        ORDER BY sent_at ASC
    ");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Messaging | MFC</title>
  <link rel="stylesheet" href="style.css">
  <style>
    .chat-box { border: 1px solid var(--border-color-light); border-radius: var(--border-radius); padding: 1rem; height: 400px; overflow-y: auto; display: flex; flex-direction: column; gap: 0.75rem; }
    .dark-mode .chat-box { border-color: var(--border-color-dark); }
    .chat-message { max-width: 75%; padding: 0.5rem 1rem; border-radius: 1rem; }
    .chat-message.sent { background-color: var(--primary-color); color: white; align-self: flex-end; border-bottom-right-radius: 0.25rem; }
    .chat-message.received { background-color: #E4E6EB; color: var(--text-dark); align-self: flex-start; border-bottom-left-radius: 0.25rem; }
    .dark-mode .chat-message.received { background-color: #3A3B3C; color: var(--text-light); }
    .chat-input { display: flex; gap: 0.5rem; margin-top: 1rem; }
  </style>
</head>
<body>
 <?php include 'sidebar.php'; ?>

  <div class="content" id="mainContent">
    <div class="header">
      <div class="hamburger" id="hamburger">☰</div>
      <div><h1>Admin-Driver Messaging</h1></div>
      <div class="theme-toggle-container">
        <span class="theme-label">Dark Mode</span>
        <label class="theme-switch"><input type="checkbox" id="themeToggle"><span class="slider"></span></label>
      </div>
    </div>
    
    <?php echo $message; ?>

    <div class="card">
      <h3>Conversation</h3>
      <div class="form-group">
          <label for="driver_select">Select a driver to view conversation:</label>
          <select id="driver_select" class="form-control" onchange="if(this.value) window.location.href='admin_messaging.php?driver_user_id='+this.value">
              <option value="">-- Select Driver --</option>
              <?php mysqli_data_seek($active_drivers, 0); while($driver = $active_drivers->fetch_assoc()): ?>
              <option value="<?php echo $driver['user_id']; ?>" <?php if($selected_driver_user_id == $driver['user_id']) echo 'selected'; ?>>
                  <?php echo htmlspecialchars($driver['name']); ?>
              </option>
              <?php endwhile; ?>
          </select>
      </div>
      
      <?php if($selected_driver_user_id): ?>
      <div class="chat-box" id="chatBox">
          <?php if($messages_result && $messages_result->num_rows > 0): ?>
          <?php while($msg = $messages_result->fetch_assoc()): 
              $msg_class = $msg['sender_id'] == $admin_id ? 'sent' : 'received';
          ?>
              <div class="chat-message <?php echo $msg_class; ?>"><?php echo htmlspecialchars($msg['message_text']); ?></div>
          <?php endwhile; ?>
          <?php else: ?>
          <p style="text-align:center; color: var(--text-muted-dark); margin: auto;">No messages yet. Start the conversation!</p>
          <?php endif; ?>
      </div>
      <form action="admin_messaging.php?driver_user_id=<?php echo $selected_driver_user_id; ?>" method="POST" class="chat-input">
          <input type="hidden" name="receiver_id" value="<?php echo $selected_driver_user_id; ?>">
          <input type="text" name="message_text" class="form-control" placeholder="Type message..." required autocomplete="off">
          <button type="submit" name="send_message" class="btn btn-primary">Send</button>
      </form>
      <?php endif; ?>
    </div>
  </div>

  <script>
    document.addEventListener('DOMContentLoaded', function() {
        document.getElementById('hamburger').addEventListener('click', function() {
          const sidebar = document.getElementById('sidebar');
          const mainContent = document.getElementById('mainContent');
          if (window.innerWidth <= 992) { sidebar.classList.toggle('show'); } 
          else { sidebar.classList.toggle('collapsed'); mainContent.classList.toggle('expanded'); }
        });
        
        const activeDropdown = document.querySelector('.sidebar .dropdown.active');
        if (activeDropdown) {
            activeDropdown.classList.add('open');
            const menu = activeDropdown.querySelector('.dropdown-menu');
            if (menu) {
                menu.style.maxHeight = menu.scrollHeight + 'px';
            }
        }
        document.querySelectorAll('.sidebar .dropdown-toggle').forEach(function(toggle) {
            toggle.addEventListener('click', function(e) {
                e.preventDefault();
                let parent = this.closest('.dropdown');
                let menu = parent.querySelector('.dropdown-menu');
                
                document.querySelectorAll('.sidebar .dropdown.open').forEach(function(otherDropdown) {
                    if (otherDropdown !== parent) {
                        otherDropdown.classList.remove('open');
                        otherDropdown.querySelector('.dropdown-menu').style.maxHeight = '0';
                    }
                });

                parent.classList.toggle('open');
                if (parent.classList.contains('open')) {
                    menu.style.maxHeight = menu.scrollHeight + 'px';
                } else {
                    menu.style.maxHeight = '0';
                }
            });
        });
        const chatBox = document.getElementById('chatBox');
        if(chatBox) {
            chatBox.scrollTop = chatBox.scrollHeight;
        }
    });
  </script>
  <script src="dark_mode_handler.js" defer></script>
</body>
</html>
