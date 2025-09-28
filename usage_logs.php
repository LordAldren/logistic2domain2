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
    header('Content-Disposition: attachment; filename=usage_logs_' . date('Y-m-d') . '.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Log ID', 'Vehicle', 'Date', 'Metrics', 'Fuel Usage (L)', 'Mileage (km)']);
    
    $result = $conn->query("SELECT u.*, v.type, v.model FROM usage_logs u JOIN vehicles v ON u.vehicle_id = v.id ORDER BY u.log_date DESC");
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            fputcsv($output, [
                $row['id'],
                $row['type'] . ' ' . $row['model'],
                $row['log_date'],
                $row['metrics'],
                $row['fuel_usage'],
                $row['mileage']
            ]);
        }
    }
    fclose($output);
    exit;
}

$usage_logs_result = $conn->query("SELECT u.*, v.type, v.model FROM usage_logs u JOIN vehicles v ON u.vehicle_id = v.id ORDER BY u.log_date DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Usage Logs | SLATE Logistics</title>
  
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
            <h1 class="text-3xl font-bold text-gray-800 dark:text-gray-200">Vehicle Usage Logs</h1>
            <div class="theme-toggle-container flex items-center gap-2">
                <span class="text-sm font-medium text-gray-600 dark:text-gray-400">Dark Mode</span>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" id="themeToggle" class="sr-only peer">
                    <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-focus:ring-4 peer-focus:ring-blue-300 dark:peer-focus:ring-blue-800 dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-blue-600"></div>
                </label>
            </div>
        </div>

        <div class="card bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6">
            <div class="flex flex-col md:flex-row justify-between items-center mb-4 gap-4">
                <h3 class="text-xl font-semibold text-gray-700 dark:text-gray-300">Vehicle Usage History</h3>
                <a href="usage_logs.php?download_csv=true" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition duration-300 flex items-center gap-2">
                    <i data-lucide="download" class="w-4 h-4"></i> Download CSV
                </a>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-gray-600 dark:text-gray-400">
                    <thead class="bg-gray-50 dark:bg-gray-700 text-xs uppercase text-gray-700 dark:text-gray-400">
                        <tr>
                            <th class="p-4">Vehicle</th>
                            <th class="p-4">Date</th>
                            <th class="p-4">Metrics</th>
                            <th class="p-4">Fuel (L)</th>
                            <th class="p-4">Mileage (km)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($usage_logs_result->num_rows > 0): ?>
                            <?php while($row = $usage_logs_result->fetch_assoc()): ?>
                            <tr class="border-b dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
                                <td class="p-4 font-medium"><?php echo htmlspecialchars($row['type'] . ' ' . $row['model']); ?></td>
                                <td class="p-4"><?php echo htmlspecialchars($row['log_date']); ?></td>
                                <td class="p-4"><?php echo htmlspecialchars($row['metrics']); ?></td>
                                <td class="p-4"><?php echo htmlspecialchars($row['fuel_usage']); ?></td>
                                <td class="p-4"><?php echo htmlspecialchars($row['mileage']); ?></td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="5" class="text-center p-6">No usage logs found.</td></tr>
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
