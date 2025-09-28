<?php
session_start();
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}
require_once 'db_connect.php';

// --- Filtering and Searching Logic ---
$where_clauses = []; $params = []; $types = '';
$search_query = isset($_GET['search']) ? $_GET['search'] : '';
if (!empty($search_query)) { $where_clauses[] = "(t.trip_code LIKE ? OR t.destination LIKE ? OR v.type LIKE ? OR v.model LIKE ?)"; $search_term = "%".$search_query."%"; array_push($params, $search_term, $search_term, $search_term, $search_term); $types .= 'ssss'; }
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : ''; $end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';
if (!empty($start_date) && !empty($end_date)) { $where_clauses[] = "DATE(t.pickup_time) BETWEEN ? AND ?"; array_push($params, $start_date, $end_date); $types .= 'ss'; }
$driver_id = isset($_GET['driver_id']) && is_numeric($_GET['driver_id']) ? (int)$_GET['driver_id'] : '';
if (!empty($driver_id)) { $where_clauses[] = "t.driver_id = ?"; $params[] = $driver_id; $types .= 'i'; }
$status = isset($_GET['status']) ? $_GET['status'] : '';
if (!empty($status)) { $where_clauses[] = "t.status = ?"; $params[] = $status; $types .= 's'; }

// --- CSV Download Logic ---
if (isset($_GET['download_csv'])) {
    // This logic should be mostly the same as the display logic, so we reuse the WHERE clauses
    header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename=trip_history_'.date('Y-m-d').'.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Trip Code', 'Vehicle', 'Driver', 'Pickup Time', 'Destination', 'Status', 'Delivery Status', 'Actual Arrival', 'POD Path']);
    $sql_csv = "SELECT t.*, v.type AS vehicle_type, v.model AS vehicle_model, d.name AS driver_name FROM trips t JOIN vehicles v ON t.vehicle_id = v.id JOIN drivers d ON t.driver_id = d.id";
    if (!empty($where_clauses)) { $sql_csv .= " WHERE " . implode(" AND ", $where_clauses); }
    $sql_csv .= " ORDER BY t.pickup_time DESC";
    $stmt_csv = $conn->prepare($sql_csv);
    if (!empty($params)) { $stmt_csv->bind_param($types, ...$params); }
    $stmt_csv->execute(); $result_csv = $stmt_csv->get_result();
    while ($row = $result_csv->fetch_assoc()) { fputcsv($output, [$row['trip_code'], $row['vehicle_type'].' '.$row['vehicle_model'], $row['driver_name'], $row['pickup_time'], $row['destination'], $row['status'], $row['arrival_status'], $row['actual_arrival_time'], $row['proof_of_delivery_path']]); }
    fclose($output); exit;
}

// --- Data Fetching for Display ---
$sql = "SELECT t.*, v.type AS vehicle_type, v.model AS vehicle_model, d.name AS driver_name FROM trips t JOIN vehicles v ON t.vehicle_id = v.id JOIN drivers d ON t.driver_id = d.id";
if (!empty($where_clauses)) { $sql .= " WHERE " . implode(" AND ", $where_clauses); }
$sql .= " ORDER BY t.pickup_time DESC";
$stmt = $conn->prepare($sql);
if (!empty($params)) { $stmt->bind_param($types, ...$params); }
$stmt->execute();
$trip_history_result = $stmt->get_result();
$drivers_result = $conn->query("SELECT id, name FROM drivers ORDER BY name ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"> <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Trip History | SLATE Logistics</title>
  <link rel="stylesheet" href="style.css"> <script src="https://cdn.tailwindcss.com"></script> <script src="https://unpkg.com/lucide@latest"></script>
  <style> .dark .card { background-color: #1f2937; border-color: #374151; } .dark table, .dark label { color: #d1d5db; } .dark th { color: #9ca3af; } .dark td { border-bottom-color: #374151; } .dark .form-input { background-color: #374151; border-color: #4b5563; color: #d1d5db; } </style>
</head>
<body class="bg-gray-100 dark:bg-gray-900">
  <?php include 'sidebar.php'; ?>
  <main id="main-content" class="ml-64 transition-all duration-300 ease-in-out">
    <div class="p-6">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-3xl font-bold text-gray-800 dark:text-gray-200">Trip History Logs</h1>
            <div class="theme-toggle-container flex items-center gap-2"><span class="text-sm font-medium text-gray-600 dark:text-gray-400">Dark Mode</span><label class="relative inline-flex items-center cursor-pointer"><input type="checkbox" id="themeToggle" class="sr-only peer"><div class="w-11 h-6 bg-gray-200 rounded-full peer peer-focus:ring-4 peer-focus:ring-blue-300 dark:peer-focus:ring-blue-800 dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-blue-600"></div></label></div>
        </div>
        <div class="card bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6 mb-6">
            <h3 class="text-xl font-semibold mb-4">Filter Trips</h3>
            <form action="trip_history.php" method="GET" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 xl:grid-cols-6 gap-4">
                <div class="xl:col-span-2"><label for="search" class="block text-sm font-medium">Search</label><input type="text" name="search" id="search" class="mt-1 form-input w-full" placeholder="Trip code, destination..." value="<?php echo htmlspecialchars($search_query); ?>"></div>
                <div><label for="start_date" class="block text-sm font-medium">Start Date</label><input type="date" name="start_date" id="start_date" class="mt-1 form-input w-full" value="<?php echo htmlspecialchars($start_date); ?>"></div>
                <div><label for="end_date" class="block text-sm font-medium">End Date</label><input type="date" name="end_date" id="end_date" class="mt-1 form-input w-full" value="<?php echo htmlspecialchars($end_date); ?>"></div>
                <div class="xl:col-span-2 flex items-end gap-2">
                    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 w-full">Filter</button>
                    <a href="trip_history.php" class="bg-gray-300 dark:bg-gray-600 text-gray-800 dark:text-gray-200 px-4 py-2 rounded-lg hover:bg-gray-400 w-full text-center">Reset</a>
                </div>
            </form>
        </div>
        <div class="card bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6">
            <div class="flex justify-between items-center mb-4"><h3 class="text-xl font-semibold">All Trips</h3><a href="trip_history.php?download_csv=true&<?php echo http_build_query($_GET); ?>" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 flex items-center gap-2 text-sm"><i data-lucide="download" class="w-4 h-4"></i>Download CSV</a></div>
            <div class="overflow-x-auto"><table>
                <thead class="bg-gray-50 dark:bg-gray-700 text-xs uppercase"><tr><th class="p-4">Trip Code</th><th class="p-4">Vehicle</th><th class="p-4">Driver</th><th class="p-4">Pickup Time</th><th class="p-4">Destination</th><th class="p-4">Status</th><th class="p-4">Delivery</th><th class="p-4">POD</th></tr></thead>
                <tbody><?php if ($trip_history_result->num_rows > 0): while($row = $trip_history_result->fetch_assoc()): ?>
                    <tr class="border-b dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
                        <td class="p-4"><?php echo htmlspecialchars($row['trip_code']); ?></td><td><?php echo htmlspecialchars($row['vehicle_type'].' '.$row['vehicle_model']); ?></td><td><?php echo htmlspecialchars($row['driver_name']); ?></td>
                        <td class="p-4"><?php echo htmlspecialchars(date('M d, Y h:i A', strtotime($row['pickup_time']))); ?></td><td><?php echo htmlspecialchars($row['destination']); ?></td>
                        <td class="p-4"><span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $row['status'])); ?>"><?php echo htmlspecialchars($row['status']); ?></span></td>
                        <td class="p-4"><?php if(!empty($row['arrival_status']) && $row['arrival_status'] != 'Pending'): ?><span class="status-badge status-<?php echo strtolower(str_replace('-', '', $row['arrival_status'])); ?>"><?php echo htmlspecialchars($row['arrival_status']); ?></span><?php else: echo 'N/A'; endif; ?></td>
                        <td class="p-4"><?php if(!empty($row['proof_of_delivery_path'])): ?><a href="<?php echo htmlspecialchars($row['proof_of_delivery_path']); ?>" target="_blank" class="text-blue-500 hover:underline">View</a><?php else: echo 'None'; endif; ?></td>
                    </tr><?php endwhile; else: ?><tr><td colspan="8" class="text-center p-6">No trips found matching your criteria.</td></tr><?php endif; ?>
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
