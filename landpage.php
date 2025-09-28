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

// Fetch Dashboard Stats
$successful_deliveries = $conn->query("SELECT COUNT(*) as count FROM trips WHERE status = 'Completed'")->fetch_assoc()['count'];
$pending_maintenance = $conn->query("SELECT COUNT(*) as count FROM maintenance_approvals WHERE status = 'Pending'")->fetch_assoc()['count'];
$recent_trips = $conn->query("SELECT t.trip_code, t.destination, t.status, v.type FROM trips t JOIN vehicles v ON t.vehicle_id = v.id ORDER BY t.pickup_time DESC LIMIT 5");
$current_month_cost = $conn->query("SELECT SUM(tc.total_cost) as monthly_cost FROM trip_costs tc JOIN trips t ON tc.trip_id = t.id WHERE MONTH(t.pickup_time) = MONTH(CURDATE()) AND YEAR(t.pickup_time) = YEAR(CURDATE())")->fetch_assoc()['monthly_cost'] ?? 0;

// Fetch Recent Behavior Logs
$recent_behavior_logs = $conn->query("SELECT dbl.log_date, dbl.overspeeding_count, d.name as driver_name FROM driver_behavior_logs dbl JOIN drivers d ON dbl.driver_id = d.id WHERE dbl.overspeeding_count > 0 ORDER BY dbl.id DESC LIMIT 5");

// Fetch data for charts
$daily_chart_query = $conn->query("SELECT DATE(t.pickup_time) as trip_date, SUM(tc.total_cost) as total_cost FROM trip_costs tc JOIN trips t ON tc.trip_id = t.id GROUP BY DATE(t.pickup_time) ORDER BY trip_date ASC LIMIT 15");
$daily_chart_data = [];
if ($daily_chart_query) {
    while ($row = $daily_chart_query->fetch_assoc()) {
        $daily_chart_data[] = ["label" => date("M d", strtotime($row['trip_date'])), "cost" => (float)$row['total_cost']];
    }
}
$daily_chart_json = json_encode($daily_chart_data);

// Fetch initial locations for the map
$tracking_data_query = $conn->query("SELECT t.id as trip_id, v.type, v.model, d.name as driver_name, tl.latitude, tl.longitude FROM tracking_log tl JOIN trips t ON tl.trip_id = t.id AND t.status = 'En Route' JOIN vehicles v ON t.vehicle_id = v.id JOIN drivers d ON t.driver_id = d.id INNER JOIN ( SELECT trip_id, MAX(log_time) AS max_log_time FROM tracking_log GROUP BY trip_id) latest_log ON tl.trip_id = latest_log.trip_id AND tl.log_time = latest_log.max_log_time");
$initial_locations = [];
if ($tracking_data_query) { while($row = $tracking_data_query->fetch_assoc()) { $initial_locations[] = $row; } }
$initial_locations_json = json_encode($initial_locations);

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard | SLATE Logistics</title>
  
  <!-- Tailwind CSS -->
  <script src="https://cdn.tailwindcss.com"></script>
  <!-- Lucide Icons -->
  <script src="https://unpkg.com/lucide@latest/dist/lucide.min.js"></script>
  <!-- Chart.js -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <!-- Leaflet Maps -->
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
  <!-- Firebase -->
  <script src="https://www.gstatic.com/firebasejs/8.10.1/firebase-app.js"></script>
  <script src="https://www.gstatic.com/firebasejs/8.10.1/firebase-database.js"></script>
  
  <!-- Custom Styles -->
  <link rel="stylesheet" href="style.css">
  <link rel="stylesheet" href="loader.css">
</head>
<body class="bg-slate-100">
  <div id="loading-overlay">
    <!-- Loader content here -->
  </div>

  <?php include 'sidebar.php'; ?> 

  <main id="mainContent" class="content">
    <header class="header">
      <!-- Hamburger for mobile will be handled by sidebar.js -->
      <h1>Dashboard</h1>
      <div class="theme-toggle-container">
        <span class="theme-label">Dark Mode</span>
        <label class="theme-switch"><input type="checkbox" id="themeToggle"><span class="slider"></span></label>
      </div>
    </header>

    <div class="dashboard-main-grid">
        <!-- Stat Cards -->
        <div class="dashboard-stats">
            <div class="dashboard-cards">
                <div class="card"><div class="stat-icon icon-deliveries"><i data-lucide="check-circle-2"></i></div><div class="stat-details"><h3>Successful Deliveries</h3><div class="stat-value"><?php echo $successful_deliveries; ?></div></div></div>
                <div class="card"><div class="stat-icon icon-maintenance"><i data-lucide="wrench"></i></div><div class="stat-details"><h3>Pending Maintenance</h3><div class="stat-value"><?php echo $pending_maintenance; ?></div></div></div>
                <div class="card"><div class="stat-icon icon-cost"><i data-lucide="dollar-sign"></i></div><div class="stat-details"><h3>Cost This Month</h3><div class="stat-value">₱<?php echo number_format($current_month_cost, 2); ?></div></div></div>
                <div class="card"><div class="stat-icon icon-ai"><i data-lucide="bar-chart-3"></i></div><div class="stat-details"><h3>AI Forecast</h3><div class="stat-value">₱12,345.67</div></div></div>
            </div>
        </div>

        <!-- Map -->
        <div class="dashboard-map card"><div id="map"></div></div>

        <!-- Sidebar Cards -->
        <div class="dashboard-cards-sidebar">
            <div class="table-section card">
                <h3>Recent Trips</h3>
                <table>
                    <thead><tr><th>Code</th><th>Vehicle</th><th>Status</th></tr></thead>
                    <tbody>
                        <?php if ($recent_trips->num_rows > 0): while($trip = $recent_trips->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($trip['trip_code']); ?></td>
                                <td><?php echo htmlspecialchars($trip['type']); ?></td>
                                <td><span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $trip['status'])); ?>"><?php echo htmlspecialchars($trip['status']); ?></span></td>
                            </tr>
                        <?php endwhile; else: ?>
                            <tr><td colspan="3">No recent trips.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="card"><h3>Trip Cost Trend</h3><div style="height: 250px;"><canvas id="costChart"></canvas></div></div>
        </div>
    </div>
  </main>

<script src="sidebar.js" defer></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // --- Initialize Icons ---
        lucide.createIcons();

        // --- Chart Logic ---
        const dailyChartData = <?php echo $daily_chart_json; ?>;
        const ctx = document.getElementById('costChart');
        if (ctx && dailyChartData.length > 0) {
            new Chart(ctx.getContext('2d'), {
                type: 'line',
                data: {
                    labels: dailyChartData.map(d => d.label),
                    datasets: [{
                        label: 'Total Daily Cost',
                        data: dailyChartData.map(d => d.cost),
                        borderColor: 'rgba(74, 108, 247, 1)',
                        backgroundColor: 'rgba(74, 108, 247, 0.1)',
                        fill: true,
                        tension: 0.3
                    }]
                },
                options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true } } }
            });
        }

        // --- MAP LOGIC ---
        const map = L.map('map').setView([12.8797, 121.7740], 6);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);

        // Firebase and map markers logic here...
    });
</script>
<script src="dark_mode_handler.js" defer></script>
<script src="loader.js"></script>
</body>
</html>
