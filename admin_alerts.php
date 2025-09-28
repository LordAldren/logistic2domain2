<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}
require_once 'db_connect.php';
$message = '';
// CSV Download and Status Update Logic (omitted for brevity, no changes)

$sos_alerts_result = $conn->query("SELECT a.*, d.name as driver_name, t.trip_code FROM alerts a JOIN drivers d ON a.driver_id = d.id JOIN trips t ON a.trip_id = t.id ORDER BY a.created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"> <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Emergency Alerts | SLATE Logistics</title>
    <link rel="stylesheet" href="style.css"> <script src="https://cdn.tailwindcss.com"></script> <script src="https://unpkg.com/lucide@latest"></script>
    <style> .dark .card { background-color: #1f2937; border-color: #374151; } .dark table, .dark p, .dark label, .dark h3 { color: #d1d5db; } .dark th { color: #9ca3af; } .dark td { border-bottom-color: #374151; } .dark .form-input { background-color: #374151; border-color: #4b5563; color: #d1d5db; } </style>
</head>
<body class="bg-gray-100 dark:bg-gray-900">
  <?php include 'sidebar.php'; ?>
  <main id="main-content" class="ml-64 transition-all duration-300 ease-in-out">
    <div class="p-6">
        <div class="flex justify-between items-center mb-6"><h1 class="text-3xl font-bold text-gray-800 dark:text-gray-200">Emergency SOS Alerts</h1><div class="theme-toggle-container flex items-center gap-2"><span class="text-sm font-medium text-gray-600 dark:text-gray-400">Dark Mode</span><label class="relative inline-flex items-center cursor-pointer"><input type="checkbox" id="themeToggle" class="sr-only peer"><div class="w-11 h-6 bg-gray-200 rounded-full peer peer-focus:ring-4 peer-focus:ring-blue-300 dark:peer-focus:ring-blue-800 dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-blue-600"></div></label></div></div>
        <?php if (!empty($message)) { echo "<div class='mb-4'>" . $message . "</div>"; } ?>
        <div class="card bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6">
            <div class="flex justify-between items-center mb-4"><h3 class="text-xl font-semibold">Incoming Alerts</h3><a href="admin_alerts.php?download_csv=true" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 flex items-center gap-2 text-sm"><i data-lucide="download" class="w-4 h-4"></i>Download CSV</a></div>
            <div class="overflow-x-auto"><table>
                <thead class="bg-gray-50 dark:bg-gray-700 text-xs uppercase"><tr><th class="p-4">Time</th><th class="p-4">Driver</th><th class="p-4">Trip Code</th><th class="p-4">Description</th><th class="p-4">Status</th><th class="p-4">Actions</th></tr></thead>
                <tbody>
                    <?php if($sos_alerts_result->num_rows > 0): ?>
                    <?php while($alert = $sos_alerts_result->fetch_assoc()): ?>
                    <tr class="border-b dark:border-gray-700 <?php echo $alert['status'] == 'Pending' ? 'bg-red-50 dark:bg-red-900/20' : ''; ?>">
                        <td class="p-4"><?php echo htmlspecialchars(date('M d, Y h:i A', strtotime($alert['created_at']))); ?></td>
                        <td class="p-4"><?php echo htmlspecialchars($alert['driver_name']); ?></td>
                        <td class="p-4"><a href="trip_history.php?search=<?php echo $alert['trip_code']; ?>" class="text-blue-500 hover:underline"><?php echo htmlspecialchars($alert['trip_code']); ?></a></td>
                        <td class="p-4"><?php echo htmlspecialchars($alert['description']); ?></td>
                        <td class="p-4"><span class="status-badge status-<?php echo strtolower($alert['status']); ?>"><?php echo htmlspecialchars($alert['status']); ?></span></td>
                        <td class="p-4">
                            <?php if ($alert['status'] != 'Resolved'): ?>
                            <form action="admin_alerts.php" method="POST" class="inline">
                                <input type="hidden" name="alert_id" value="<?php echo $alert['id']; ?>"><input type="hidden" name="update_alert_status" value="1">
                                <select name="new_status" onchange="this.form.submit()" class="form-input text-xs p-1 rounded-md">
                                    <option value="Pending" <?php if($alert['status'] == 'Pending') echo 'selected'; ?>>Pending</option>
                                    <option value="Acknowledged" <?php if($alert['status'] == 'Acknowledged') echo 'selected'; ?>>Acknowledge</option>
                                    <option value="Resolved" <?php if($alert['status'] == 'Resolved') echo 'selected'; ?>>Resolve</option>
                                </select>
                            </form>
                            <?php else: ?><span class="text-gray-400">No actions</span><?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                    <?php else: ?><tr><td colspan="6" class="text-center p-6">No SOS alerts found.</td></tr><?php endif; ?>
                </tbody>
            </table></div>
        </div>
    </div>
  </main>
<script>
document.addEventListener('DOMContentLoaded', function() {
    lucide.createIcons();
    const themeToggle=document.getElementById('themeToggle');if(localStorage.getItem('theme')==='dark'||(!('theme'in localStorage)&&window.matchMedia('(prefers-color-scheme: dark)').matches)){document.documentElement.classList.add('dark');if(themeToggle)themeToggle.checked=true;}else{document.documentElement.classList.remove('dark');if(themeToggle)themeToggle.checked=false;}if(themeToggle){themeToggle.addEventListener('change',function(){if(this.checked){document.documentElement.classList.add('dark');localStorage.setItem('theme','dark');}else{document.documentElement.classList.remove('dark');localStorage.setItem('theme','light');}});}
});
</script>
</body>
</html>
