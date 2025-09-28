<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['role'] !== 'admin') {
    header('Location: login.php'); exit;
}
require_once 'db_connect.php';
$message = ''; $admin_id = $_SESSION['id'];
$selected_driver_user_id = isset($_GET['driver_user_id']) ? (int)$_GET['driver_user_id'] : null;

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['send_message'])) {
    $message_text = $_POST['message_text']; $receiver_id = $_POST['receiver_id']; 
    if (!empty($message_text) && !empty($receiver_id)) {
        $stmt = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, message_text) VALUES (?, ?, ?)");
        $stmt->bind_param("iis", $admin_id, $receiver_id, $message_text);
        if($stmt->execute()){ header("Location: admin_messaging.php?driver_user_id=" . $receiver_id); exit; }
    }
}
$active_drivers = $conn->query("SELECT d.id, d.name, u.id as user_id FROM drivers d JOIN users u ON d.user_id = u.id WHERE d.status = 'Active'");
$messages_result = null;
if($selected_driver_user_id){
    $messages_result = $conn->query("SELECT u_sender.id as sender_id, message_text, sent_at FROM messages JOIN users u_sender ON messages.sender_id = u_sender.id WHERE (sender_id = $admin_id AND receiver_id = $selected_driver_user_id) OR (sender_id = $selected_driver_user_id AND receiver_id = $admin_id) ORDER BY sent_at ASC");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"> <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Messaging | SLATE Logistics</title>
  <link rel="stylesheet" href="style.css"> <script src="https://cdn.tailwindcss.com"></script> <script src="https://unpkg.com/lucide@latest"></script>
  <style> .dark .card { background-color: #1f2937; border-color: #374151; } .dark label { color: #d1d5db; } .dark .form-input { background-color: #374151; border-color: #4b5563; color: #d1d5db; } .dark .chat-box { background-color: #111827; } .dark .msg-received { background-color: #374151; color: #e5e7eb; } </style>
</head>
<body class="bg-gray-100 dark:bg-gray-900">
 <?php include 'sidebar.php'; ?>
  <main id="main-content" class="ml-64 transition-all duration-300 ease-in-out">
    <div class="p-6">
        <div class="flex justify-between items-center mb-6"><h1 class="text-3xl font-bold text-gray-800 dark:text-gray-200">Admin-Driver Messaging</h1><div class="theme-toggle-container flex items-center gap-2"><span class="text-sm font-medium text-gray-600 dark:text-gray-400">Dark Mode</span><label class="relative inline-flex items-center cursor-pointer"><input type="checkbox" id="themeToggle" class="sr-only peer"><div class="w-11 h-6 bg-gray-200 rounded-full peer peer-focus:ring-4 peer-focus:ring-blue-300 dark:peer-focus:ring-blue-800 dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-blue-600"></div></label></div></div>
        <div class="card bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6">
            <div class="form-group mb-4">
                <label for="driver_select" class="block text-sm font-medium mb-1">Select conversation:</label>
                <select id="driver_select" class="form-input w-full max-w-sm" onchange="if(this.value) window.location.href='admin_messaging.php?driver_user_id='+this.value">
                    <option value="">-- Select Driver --</option>
                    <?php mysqli_data_seek($active_drivers, 0); while($driver = $active_drivers->fetch_assoc()): ?>
                    <option value="<?php echo $driver['user_id']; ?>" <?php if($selected_driver_user_id == $driver['user_id']) echo 'selected'; ?>><?php echo htmlspecialchars($driver['name']); ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <?php if($selected_driver_user_id): ?>
            <div class="chat-box bg-gray-50 dark:bg-gray-900/50 rounded-lg p-4 h-96 overflow-y-auto flex flex-col space-y-4" id="chatBox">
                <?php if($messages_result && $messages_result->num_rows > 0): ?>
                    <?php while($msg = $messages_result->fetch_assoc()): $is_sent = $msg['sender_id'] == $admin_id; ?>
                    <div class="flex <?php echo $is_sent ? 'justify-end' : 'justify-start'; ?>">
                        <div class="max-w-xs lg:max-w-md px-4 py-2 rounded-2xl <?php echo $is_sent ? 'bg-blue-600 text-white rounded-br-none' : 'bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-bl-none'; ?>">
                            <p class="text-sm"><?php echo htmlspecialchars($msg['message_text']); ?></p>
                        </div>
                    </div>
                    <?php endwhile; ?>
                <?php else: ?><p class="text-center text-gray-500 m-auto">No messages yet. Start the conversation!</p><?php endif; ?>
            </div>
            <form action="admin_messaging.php?driver_user_id=<?php echo $selected_driver_user_id; ?>" method="POST" class="mt-4 flex gap-2">
                <input type="hidden" name="receiver_id" value="<?php echo $selected_driver_user_id; ?>">
                <input type="text" name="message_text" class="form-input flex-grow" placeholder="Type message..." required autocomplete="off">
                <button type="submit" name="send_message" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 flex items-center gap-2"><i data-lucide="send" class="w-4 h-4"></i></button>
            </form>
            <?php endif; ?>
        </div>
    </div>
  </main>
<script>
document.addEventListener('DOMContentLoaded', function() {
    lucide.createIcons();
    const themeToggle=document.getElementById('themeToggle');if(localStorage.getItem('theme')==='dark'||(!('theme'in localStorage)&&window.matchMedia('(prefers-color-scheme: dark)').matches)){document.documentElement.classList.add('dark');if(themeToggle)themeToggle.checked=true;}else{document.documentElement.classList.remove('dark');if(themeToggle)themeToggle.checked=false;}if(themeToggle){themeToggle.addEventListener('change',function(){if(this.checked){document.documentElement.classList.add('dark');localStorage.setItem('theme','dark');}else{document.documentElement.classList.remove('dark');localStorage.setItem('theme','light');}});}
    const chatBox=document.getElementById('chatBox');if(chatBox){chatBox.scrollTop=chatBox.scrollHeight;}
});
</script>
</body>
</html>
