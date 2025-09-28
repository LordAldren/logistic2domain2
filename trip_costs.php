<?php
session_start();
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}
require_once 'db_connect.php';

if (isset($_GET['download_csv'])) {
    header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename=trip_costs_'.date('Y-m-d').'.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Cost ID', 'Trip Code', 'Vehicle', 'Fuel Cost', 'Labor Cost', 'Tolls Cost', 'Other Cost', 'Total Cost']);
    $result = $conn->query("SELECT tc.*, t.trip_code, v.type, v.model FROM trip_costs tc JOIN trips t ON tc.trip_id = t.id JOIN vehicles v ON t.vehicle_id = v.id ORDER BY tc.id DESC");
    if ($result->num_rows > 0) { while ($row = $result->fetch_assoc()) { fputcsv($output, [$row['id'], $row['trip_code'], $row['type'].' '.$row['model'], $row['fuel_cost'], $row['labor_cost'], $row['tolls_cost'], $row['other_cost'], $row['total_cost']]); } }
    fclose($output); exit;
}

$trip_costs = $conn->query("SELECT tc.*, t.trip_code, v.type, v.model FROM trip_costs tc JOIN trips t ON tc.trip_id = t.id JOIN vehicles v ON t.vehicle_id = v.id ORDER BY tc.id DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"> <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Per-Trip Costs | SLATE Logistics</title>
  <link rel="stylesheet" href="style.css"> <script src="https://cdn.tailwindcss.com"></script> <script src="https://unpkg.com/lucide@latest"></script>
  <style> .dark .card { background-color: #1f2937; border-color: #374151; } .dark table { color: #d1d5db; } .dark th { color: #9ca3af; } .dark td { border-bottom-color: #374151; } </style>
</head>
<body class="bg-gray-100 dark:bg-gray-900">
  <?php include 'sidebar.php'; ?>
  <main id="main-content" class="ml-64 transition-all duration-300 ease-in-out">
    <div class="p-6">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-3xl font-bold text-gray-800 dark:text-gray-200">Per-Trip Cost Report</h1>
            <div class="theme-toggle-container flex items-center gap-2"><span class="text-sm font-medium text-gray-600 dark:text-gray-400">Dark Mode</span><label class="relative inline-flex items-center cursor-pointer"><input type="checkbox" id="themeToggle" class="sr-only peer"><div class="w-11 h-6 bg-gray-200 rounded-full peer peer-focus:ring-4 peer-focus:ring-blue-300 dark:peer-focus:ring-blue-800 dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-blue-600"></div></label></div>
        </div>
        <div class="card bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-semibold text-gray-700 dark:text-gray-300">Per-Trip Cost Breakdown</h3>
                <a href="trip_costs.php?download_csv=true" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 flex items-center gap-2 text-sm"><i data-lucide="download" class="w-4 h-4"></i>Download CSV</a>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-700 text-xs uppercase"><tr><th class="p-4">Trip Code</th><th class="p-4">Vehicle</th><th class="p-4">Fuel Cost</th><th class="p-4">Labor Cost</th><th class="p-4">Tolls</th><th class="p-4">Other</th><th class="p-4">Total Cost</th><th class="p-4">Actions</th></tr></thead>
                    <tbody>
                        <?php if ($trip_costs->num_rows > 0): mysqli_data_seek($trip_costs, 0); ?>
                            <?php while($row = $trip_costs->fetch_assoc()): ?>
                            <tr class="border-b dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
                                <td class="p-4"><?php echo htmlspecialchars($row['trip_code']); ?></td>
                                <td class="p-4"><?php echo htmlspecialchars($row['type'] . ' ' . $row['model']); ?></td>
                                <td class="p-4">₱<?php echo number_format($row['fuel_cost'], 2); ?></td>
                                <td class="p-4">₱<?php echo number_format($row['labor_cost'], 2); ?></td>
                                <td class="p-4">₱<?php echo number_format($row['tolls_cost'], 2); ?></td>
                                <td class="p-4">₱<?php echo number_format($row['other_cost'], 2); ?></td>
                                <td class="p-4 font-bold">₱<?php echo number_format($row['total_cost'], 2); ?></td>
                                <td class="p-4"><a href="trip_history.php?search=<?php echo urlencode($row['trip_code']); ?>" class="text-blue-500 hover:underline text-xs">View Trip</a></td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="8" class="text-center p-6">No per-trip cost data found.</td></tr>
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
    const themeToggle=document.getElementById('themeToggle');if(localStorage.getItem('theme')==='dark'||(!('theme'in localStorage)&&window.matchMedia('(prefers-color-scheme: dark)').matches)){document.documentElement.classList.add('dark');if(themeToggle)themeToggle.checked=true;}else{document.documentElement.classList.remove('dark');if(themeToggle)themeToggle.checked=false;}if(themeToggle){themeToggle.addEventListener('change',function(){if(this.checked){document.documentElement.classList.add('dark');localStorage.setItem('theme','dark');}else{document.documentElement.classList.remove('dark');localStorage.setItem('theme','light');}});}
});
</script>
</body>
</html>
