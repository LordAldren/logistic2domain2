<?php
session_start();
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}
if ($_SESSION['role'] === 'driver') {
    header("location: mobile_app.php");
    exit;
}

require_once 'db_connect.php';
$message = '';

// --- PANG-HANDLE NG CSV DOWNLOAD ---
if (isset($_GET['download_csv'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=maintenance_requests_' . date('Y-m-d') . '.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Vehicle', 'Arrival Date', 'Date of Return', 'Status']);

    $result = $conn->query("SELECT m.*, v.type, v.model FROM maintenance_approvals m JOIN vehicles v ON m.vehicle_id = v.id ORDER BY m.arrival_date DESC");
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            fputcsv($output, [
                $row['id'],
                $row['type'] . ' ' . $row['model'],
                $row['arrival_date'],
                $row['date_of_return'] ?? 'N/A',
                $row['status']
            ]);
        }
    }
    fclose($output);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['update_maintenance_status'])) {
        $maintenance_id = $_POST['maintenance_id_status'];
        $new_status = $_POST['new_status'];
        $allowed_statuses = ['Approved', 'On-Queue', 'In Progress', 'Completed', 'Rejected'];
        
        if (in_array($new_status, $allowed_statuses)) {
            $stmt = $conn->prepare("UPDATE maintenance_approvals SET status = ? WHERE id = ?");
            $stmt->bind_param("si", $new_status, $maintenance_id);
            if ($stmt->execute()) {
                $message = "<div class='message-banner success'>Maintenance status updated to '$new_status'.</div>";
            } else {
                $message = "<div class='message-banner error'>Error updating status.</div>";
            }
            $stmt->close();
        } else {
            $message = "<div class='message-banner error'>Invalid status update attempted.</div>";
        }
    }
}

$maintenance_result = $conn->query("SELECT m.*, v.type, v.model FROM maintenance_approvals m JOIN vehicles v ON m.vehicle_id = v.id ORDER BY m.arrival_date DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Maintenance Approval | SLATE Logistics</title>
  
  <link rel="stylesheet" href="style.css">
  <link rel="stylesheet" href="loader.css">
  
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://unpkg.com/lucide@latest"></script>
  
  <style>
    .dark .card { background-color: #1f2937; border: 1px solid #374151; }
    .dark table { color: #d1d5db; }
    .dark th { color: #9ca3af; }
    .dark td { border-bottom-color: #374151; }
  </style>
</head>
<body class="bg-gray-100 dark:bg-gray-900">

  <?php include 'sidebar.php'; ?> 

  <main id="main-content" class="ml-64 transition-all duration-300 ease-in-out">
    <div class="p-6">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-3xl font-bold text-gray-800 dark:text-gray-200">Maintenance Approval</h1>
            <div class="theme-toggle-container flex items-center gap-2">
                <span class="text-sm font-medium text-gray-600 dark:text-gray-400">Dark Mode</span>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" id="themeToggle" class="sr-only peer">
                    <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-focus:ring-4 peer-focus:ring-blue-300 dark:peer-focus:ring-blue-800 dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-blue-600"></div>
                </label>
            </div>
        </div>

        <?php if (!empty($message)) { echo "<div class='mb-4'>" . $message . "</div>"; } ?>

        <div class="card bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6">
            <div class="flex flex-col md:flex-row justify-between items-center mb-4 gap-4">
                <h3 class="text-xl font-semibold text-gray-700 dark:text-gray-300">Pending & Ongoing Maintenance</h3>
                <a href="maintenance_approval.php?download_csv=true" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition duration-300 flex items-center gap-2">
                    <i data-lucide="download" class="w-4 h-4"></i> Download CSV
                </a>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-gray-600 dark:text-gray-400">
                    <thead class="bg-gray-50 dark:bg-gray-700 text-xs uppercase text-gray-700 dark:text-gray-400">
                        <tr>
                            <th class="p-4">Vehicle</th>
                            <th class="p-4">Arrival Date</th>
                            <th class="p-4">Date of Return</th>
                            <th class="p-4">Status</th>
                            <th class="p-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($maintenance_result->num_rows > 0): ?>
                            <?php while($row = $maintenance_result->fetch_assoc()): ?>
                            <tr class="border-b dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
                                <td class="p-4 font-medium"><?php echo htmlspecialchars($row['type'] . ' ' . $row['model']); ?></td>
                                <td class="p-4"><?php echo htmlspecialchars($row['arrival_date']); ?></td>
                                <td class="p-4"><?php echo htmlspecialchars($row['date_of_return'] ?? 'N/A'); ?></td>
                                <td class="p-4"><span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $row['status'])); ?>"><?php echo htmlspecialchars($row['status']); ?></span></td>
                                <td class="p-4 flex gap-2">
                                    <?php if ($row['status'] == 'Pending'): ?>
                                        <form action="maintenance_approval.php" method="POST" class="inline-block">
                                            <input type="hidden" name="maintenance_id_status" value="<?php echo $row['id']; ?>">
                                            <input type="hidden" name="new_status" value="Approved">
                                            <button type="submit" name="update_maintenance_status" class="bg-green-500 text-white px-3 py-1 rounded-md text-xs hover:bg-green-600">Approve</button>
                                        </form>
                                        <form action="maintenance_approval.php" method="POST" class="inline-block">
                                            <input type="hidden" name="maintenance_id_status" value="<?php echo $row['id']; ?>">
                                            <input type="hidden" name="new_status" value="On-Queue">
                                            <button type="submit" name="update_maintenance_status" class="bg-yellow-500 text-white px-3 py-1 rounded-md text-xs hover:bg-yellow-600">On-Queue</button>
                                        </form>
                                    <?php elseif (in_array($row['status'], ['Approved', 'In Progress', 'On-Queue'])): ?>
                                        <form action="maintenance_approval.php" method="POST" class="inline-block">
                                            <input type="hidden" name="maintenance_id_status" value="<?php echo $row['id']; ?>">
                                            <input type="hidden" name="new_status" value="Completed">
                                            <button type="submit" name="update_maintenance_status" class="bg-blue-500 text-white px-3 py-1 rounded-md text-xs hover:bg-blue-600">Mark as Done</button>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-gray-400">No actions</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="5" class="text-center p-6">No maintenance requests found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
  </main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    lucide.createIcons();

    // Dark Mode Handler
    const themeToggle = document.getElementById('themeToggle');
    if (localStorage.getItem('theme') === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
      document.documentElement.classList.add('dark');
      if(themeToggle) themeToggle.checked = true;
    } else {
      document.documentElement.classList.remove('dark');
      if(themeToggle) themeToggle.checked = false;
    }

    if(themeToggle) {
        themeToggle.addEventListener('change', function() {
          if (this.checked) {
            document.documentElement.classList.add('dark');
            localStorage.setItem('theme', 'dark');
          } else {
            document.documentElement.classList.remove('dark');
            localStorage.setItem('theme', 'light');
          }
        });
    }
});
</script>
</body>
</html>
