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

// Fetch Dashboard Stats (No changes here)
$successful_deliveries = $conn->query("SELECT COUNT(*) as count FROM trips WHERE status = 'Completed'")->fetch_assoc()['count'];
$pending_maintenance = $conn->query("SELECT COUNT(*) as count FROM maintenance_approvals WHERE status = 'Pending'")->fetch_assoc()['count'];
$recent_trips = $conn->query("SELECT t.trip_code, t.destination, t.status, v.type FROM trips t JOIN vehicles v ON t.vehicle_id = v.id ORDER BY t.pickup_time DESC LIMIT 5");
$current_month_cost = $conn->query("SELECT SUM(tc.total_cost) as monthly_cost FROM trip_costs tc JOIN trips t ON tc.trip_id = t.id WHERE MONTH(t.pickup_time) = MONTH(CURDATE()) AND YEAR(t.pickup_time) = YEAR(CURDATE())")->fetch_assoc()['monthly_cost'] ?? 0;
$recent_behavior_logs = $conn->query("SELECT dbl.log_date, dbl.overspeeding_count, dbl.harsh_braking_count, dbl.idle_duration_minutes, d.name as driver_name FROM driver_behavior_logs dbl JOIN drivers d ON dbl.driver_id = d.id WHERE dbl.overspeeding_count > 0 OR dbl.harsh_braking_count > 0 OR dbl.idle_duration_minutes > 0 ORDER BY dbl.id DESC LIMIT 5");
$daily_costs_query = $conn->query("SELECT DATE(t.pickup_time) as trip_date, SUM(tc.total_cost) as grand_total FROM trip_costs tc JOIN trips t ON tc.trip_id = t.id GROUP BY DATE(t.pickup_time) ORDER BY trip_date ASC");
$daily_costs_for_ai = [];
$daily_chart_data = [];
if ($daily_costs_query) { $day_index = 1; while ($row = $daily_costs_query->fetch_assoc()) { $daily_costs_for_ai[] = ["day" => $day_index++, "total" => (float)$row['grand_total']]; $daily_chart_data[] = ["label" => date("M d", strtotime($row['trip_date'])), "cost" => (float)$row['grand_total']]; } }
$daily_costs_json = json_encode($daily_costs_for_ai);
$daily_chart_json = json_encode($daily_chart_data);
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
  
  <!-- CSS Links -->
  <link rel="stylesheet" href="style.css"> <!-- Your original stylesheet -->
  <link rel="stylesheet" href="loader.css"> 
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
  
  <!-- Scripts -->
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://unpkg.com/lucide@latest"></script>
  <script src="https://cdn.jsdelivr.net/npm/@tensorflow/tfjs@latest/dist/tf.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
  <script src="https://www.gstatic.com/firebasejs/8.10.1/firebase-app.js"></script>
  <script src="https://www.gstatic.com/firebasejs/8.10.1/firebase-database.js"></script>

  <style>
      /* Page-specific styles can remain */
      .dashboard-main-grid { display: grid; grid-template-columns: repeat(3, 1fr); grid-template-rows: auto auto 1fr; gap: 1.5rem; }
      .dashboard-stats { grid-column: 1 / -1; }
      .dashboard-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.5rem; }
      .dashboard-map { grid-column: 1 / 3; grid-row: 2 / 4; }
      #map { height: 100%; min-height: 500px; border-radius: 0.5rem; /* Match Tailwind's rounded-lg */ }
      .dashboard-cards-sidebar { grid-column: 3 / 4; grid-row: 2 / 4; display: flex; flex-direction: column; gap: 1.5rem; }
      @media (max-width: 1200px) { .dashboard-main-grid { grid-template-columns: 1fr; } .dashboard-map, .dashboard-cards-sidebar { grid-column: 1 / -1; grid-row: auto; } }
      
      /* --- FIX PARA SA DARK MODE AT CARDS --- */
      .card { 
          background-color: white; 
          border-radius: 0.75rem; 
          padding: 1.5rem; 
          box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
          transition: background-color 0.3s ease-in-out;
      }
      .dark .card {
          background-color: #1f2937; /* Tailwind bg-gray-800 */
          border: 1px solid #374151; /* Tailwind border-gray-700 */
      }
      .dark h3, .dark .table-section h3 { color: #d1d5db; } /* text-gray-300 */
      .dark .table-section th { color: #9ca3af; } /* text-gray-400 */
      .dark .table-section td { border-bottom: 1px solid #374151; } /* border-gray-700 */
      
      .table-section h3 { margin-bottom: 1rem; font-size: 1.125rem; font-weight: 600; }
      .table-section table { width: 100%; border-collapse: collapse; }
      .table-section th, .table-section td { padding: 0.75rem; text-align: left; border-bottom: 1px solid #e5e7eb; }
      .table-section th { font-size: 0.875rem; color: #6b7280; }
  </style>
</head>
<body class="bg-gray-100 dark:bg-gray-900">
  <div id="loading-overlay">
      <div class="loader-content">
          <img src="logo.png" alt="SLATE Logo" class="loader-logo-main">
          <p id="loader-text">Initializing System...</p>
          <!-- --- IBINALIK ANG MGA SVG NG SASAKYAN --- -->
          <div class="road">
              <div class="vehicle-container vehicle-1">
                <svg viewBox="0 0 512 512" xmlns="http://www.w3.org/2000/svg"><path d="M503.3 337.2c-7.2-21.6-21.6-36-43.2-43.2l-43.2-14.4V232c0-23.9-19.4-43.2-43.2-43.2H256V96c0-12.7-5.1-24.9-14.1-33.9L208 28.3c-9-9-21.2-14.1-33.9-14.1H48C21.5 14.2 0 35.7 0 62.2V337c0 23.9 19.4 43.2 43.2 43.2H64c0 35.3 28.7 64 64 64s64-28.7 64-64h128c0 35.3 28.7 64 64 64s64-28.7 64-64h17.3c23.9 0 43.2-19.4 43.2-43.2V337.2zM128 401c-17.7 0-32-14.3-32-32s14.3-32 32-32 32 14.3 32 32-14.3 32-32 32zm256 0c-17.7 0-32-14.3-32-32s14.3-32 32-32 32 14.3 32 32-14.3 32-32 32zm0-192h-88.8v-48H384v48z"/></svg>
              </div>
              <div class="vehicle-container vehicle-2">
                <svg viewBox="0 0 640 512" xmlns="http://www.w3.org/2000/svg"><path d="M624 352h-16V243.9c0-12.7-5.1-24.9-14.1-33.9L494 110.1c-9-9-21.2-14.1-33.9-14.1H416V48c0-26.5-21.5-48-48-48H112C85.5 0 64 21.5 64 48v48H48c-26.5 0-48 21.5-48 48v192c0 26.5 21.5 48 48 48h16c0 35.3 28.7 64 64 64s64-28.7 64-64h192c0 35.3 28.7 64 64 64s64-28.7 64-64h16c26.5 0 48-21.5 48-48V368c0-8.8-7.2-16-16-16zM128 400c-17.7 0-32-14.3-32-32s14.3-32 32-32 32 14.3 32 32-14.3 32-32 32zm384 0c-17.7 0-32-14.3-32-32s14.3-32 32-32 32 14.3 32 32-14.3 32-32 32zM480 224H128V144h288v48c0 26.5 21.5 48 48 48h16v-16z"/></svg>
              </div>
              <div class="vehicle-container vehicle-3">
                 <svg viewBox="0 0 512 512" xmlns="http://www.w3.org/2000/svg"><path d="M503.3 337.2c-7.2-21.6-21.6-36-43.2-43.2l-43.2-14.4V232c0-23.9-19.4-43.2-43.2-43.2H256V96c0-12.7-5.1-24.9-14.1-33.9L208 28.3c-9-9-21.2-14.1-33.9-14.1H48C21.5 14.2 0 35.7 0 62.2V337c0 23.9 19.4 43.2 43.2 43.2H64c0 35.3 28.7 64 64 64s64-28.7 64-64h128c0 35.3 28.7 64 64 64s64-28.7 64-64h17.3c23.9 0 43.2-19.4 43.2-43.2V337.2zM128 401c-17.7 0-32-14.3-32-32s14.3-32 32-32 32 14.3 32 32-14.3 32-32 32zm256 0c-17.7 0-32-14.3-32-32s14.3-32 32-32 32 14.3 32 32-14.3 32-32 32zm0-192h-88.8v-48H384v48z"/></svg>
              </div>
          </div>
      </div>
  </div>

  <?php include 'sidebar.php'; ?> 

  <!-- MAIN CONTENT: Inayos para mag-adjust sa sidebar -->
  <main id="main-content" class="ml-64 transition-all duration-300 ease-in-out">
    <div class="p-6">
        <!-- Header Section inside Main Content -->
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-3xl font-bold text-gray-800 dark:text-gray-200">Dashboard</h1>
            <div class="theme-toggle-container flex items-center gap-2">
                <span class="text-sm font-medium text-gray-600 dark:text-gray-400">Dark Mode</span>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" id="themeToggle" class="sr-only peer">
                    <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-focus:ring-4 peer-focus:ring-blue-300 dark:peer-focus:ring-blue-800 dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-blue-600"></div>
                </label>
            </div>
        </div>

        <!-- The rest of your dashboard content -->
        <div class="dashboard-main-grid">
            <div class="dashboard-stats">
                <div class="dashboard-cards">
                    <div class="card flex items-center"><div class="p-3 rounded-full bg-green-100 text-green-600 mr-4"><i data-lucide="check-circle"></i></div><div><h3 class="text-gray-500">Successful Deliveries</h3><div class="text-2xl font-bold"><?php echo $successful_deliveries; ?></div></div></div>
                    <div class="card flex items-center"><div class="p-3 rounded-full bg-red-100 text-red-600 mr-4"><i data-lucide="wrench"></i></div><div><h3 class="text-gray-500">Pending Maintenance</h3><div class="text-2xl font-bold"><?php echo $pending_maintenance; ?></div></div></div>
                    <div class="card flex items-center"><div class="p-3 rounded-full bg-yellow-100 text-yellow-600 mr-4"><i data-lucide="dollar-sign"></i></div><div><h3 class="text-gray-500">Cost This Month</h3><div class="text-2xl font-bold">₱<?php echo number_format($current_month_cost, 2); ?></div></div></div>
                    <div class="card flex items-center"><div class="p-3 rounded-full bg-purple-100 text-purple-600 mr-4"><i data-lucide="brain-circuit"></i></div><div><h3 class="text-gray-500">AI Forecast (Tomorrow)</h3><div id="daily-prediction-loader-card" class="font-semibold">Training...</div><div id="daily-prediction-result-card" class="text-2xl font-bold" style="display:none;"></div></div></div>
                </div>
            </div>

            <div class="dashboard-map card" style="height: 100%; padding: 0; overflow: hidden;"><div id="map"></div></div>

            <div class="dashboard-cards-sidebar">
                <div class="table-section card">
                    <h3>Recent Trips</h3>
                    <table>
                        <thead><tr><th>Code</th><th>Vehicle</th><th>Status</th></tr></thead>
                        <tbody>
                            <?php if ($recent_trips->num_rows > 0): ?>
                                <?php while($trip = $recent_trips->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($trip['trip_code']); ?></td>
                                    <td><?php echo htmlspecialchars($trip['type']); ?></td>
                                    <td><span class="status-badge status-<?php echo strtolower(str_replace(' ', '.', $trip['status'])); ?>"><?php echo htmlspecialchars($trip['status']); ?></span></td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="3">No recent trips found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="table-section card">
                    <h3>Recent Behavior Incidents</h3>
                    <table>
                        <thead><tr><th>Driver</th><th>Incident Details</th><th>Date</th></tr></thead>
                        <tbody>
                            <?php if ($recent_behavior_logs->num_rows > 0): ?>
                                <?php while($log = $recent_behavior_logs->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($log['driver_name']); ?></td>
                                    <td>
                                        <?php
                                            $incidents = [];
                                            if ($log['overspeeding_count'] > 0) $incidents[] = "Overspeeding (" . $log['overspeeding_count'] . ")";
                                            if ($log['harsh_braking_count'] > 0) $incidents[] = "Harsh Braking (" . $log['harsh_braking_count'] . ")";
                                            if ($log['idle_duration_minutes'] > 0) $incidents[] = "Idle (" . $log['idle_duration_minutes'] . " mins)";
                                            echo implode(', ', $incidents);
                                        ?>
                                    </td>
                                    <td><?php echo htmlspecialchars(date('M d', strtotime($log['log_date']))); ?></td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="3">No recent incidents recorded.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    <a href="driver_behavior.php" style="display:block; text-align:right; margin-top:1rem; font-size:0.9em;">View All Logs</a>
                </div>
                <div class="card"><h3>Trip Cost Trend</h3><div style="height: 250px;"><canvas id="costChart"></canvas></div></div>
            </div>
        </div>
    </div>
  </main>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        
        // --- AI & Chart Logic ---
        const dailyCostDataForAI = <?php echo $daily_costs_json; ?>;
        const dailyChartData = <?php echo $daily_chart_json; ?>;
        async function trainAndPredictDaily() {
            const loaderEl = document.getElementById('daily-prediction-loader-card');
            const resultEl = document.getElementById('daily-prediction-result-card');
            if (dailyCostDataForAI.length < 2) {
                loaderEl.textContent = 'Not enough data.';
                return;
            }
            const days = dailyCostDataForAI.map(d => d.day);
            const totals = dailyCostDataForAI.map(d => d.total);
            const xs = tf.tensor1d(days);
            const ys = tf.tensor1d(totals);
            const model = tf.sequential();
            model.add(tf.layers.dense({ units: 1, inputShape: [1] }));
            model.compile({ loss: 'meanSquaredError', optimizer: tf.train.adam(0.1) });
            await model.fit(xs, ys, { epochs: 200 });
            const nextDayIndex = days.length + 1;
            const prediction = model.predict(tf.tensor1d([nextDayIndex]));
            const predictedCost = prediction.dataSync()[0];
            loaderEl.style.display = 'none';
            resultEl.textContent = '₱' + parseFloat(predictedCost).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            resultEl.style.display = 'block';
        }
        if (typeof tf !== 'undefined') { trainAndPredictDaily(); }

        const ctx = document.getElementById('costChart');
        if (ctx) {
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
        const map = L.map('map').setView([12.8797, 121.7740], 6); // Philippines view
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);

        let markers = {};
        let firebaseInitialized = false;
        const firebaseConfig = {
            apiKey: "AIzaSyCB0_OYZXX3K-AxKeHnVlYMv2wZ_81FeYM",
            authDomain: "slate49-cde60.firebaseapp.com",
            databaseURL: "https://slate49-cde60-default-rtdb.firebaseio.com",
            projectId: "slate49-cde60",
            storageBucket: "slate49-cde60.firebasestorage.app",
            messagingSenderId: "809390854040",
            appId: "1:809390854040:web:f7f77333bb0ac7ab73e5ed"
        };

        function getVehicleIcon(vehicleInfo) {
            let svgIcon = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#4A6CF7" width="40px" height="40px"><path d="M0 0h24v24H0z" fill="none"/><path d="M20 8h-3V4H3c-1.1 0-2 .9-2 2v11h2c0 1.66 1.34 3 3 3s3-1.34 3-3h6c0 1.66 1.34 3 3 3s3-1.34 3-3h2v-5l-3-4zM6 18c-.55 0-1-.45-1-1s.45-1 1-1 1 .45 1 1-.45 1-1 1zm13.5-1.5c-.83 0-1.5.67-1.5 1.5s.67 1.5 1.5 1.5 1.5-.67 1.5-1.5-.67-1.5-1.5-1.5zM18 10h1.5v3H18v-3zM3 6h12v7H3V6z"/></svg>`;
            return L.divIcon({ html: svgIcon, className: 'vehicle-icon', iconSize: [40, 40], iconAnchor: [20, 40], popupAnchor: [0, -40] });
        }
        
        function handleDataChange(tripId, data) {
            if (!map || !data.vehicle_info) return;
            const newLatLng = [data.lat, data.lng];
            if (!markers[tripId]) {
                const popupContent = `<b>Vehicle:</b> ${data.vehicle_info}<br><b>Driver:</b> ${data.driver_name}`;
                markers[tripId] = L.marker(newLatLng, { icon: getVehicleIcon(data.vehicle_info) }).addTo(map).bindPopup(popupContent);
            } else {
                markers[tripId].setLatLng(newLatLng);
            }
        }

        function initializeFirebaseListener() {
            if (firebaseInitialized) return;
            try {
                if (!firebase.apps.length) firebase.initializeApp(firebaseConfig); else firebase.app();
                const database = firebase.database();
                const trackingRef = database.ref('live_tracking');
                trackingRef.on('child_added', (snapshot) => handleDataChange(snapshot.key, snapshot.val()));
                trackingRef.on('child_changed', (snapshot) => handleDataChange(snapshot.key, snapshot.val()));
                trackingRef.on('child_removed', (snapshot) => {
                    if (map && markers[snapshot.key]) {
                        map.removeLayer(markers[snapshot.key]);
                        delete markers[snapshot.key];
                    }
                });
                firebaseInitialized = true;
            } catch(e) { console.error("Firebase init error:", e); }
        }

        const initialLocations = <?php echo $initial_locations_json; ?>;
        initialLocations.forEach(loc => {
            handleDataChange(loc.trip_id, {
                lat: loc.latitude,
                lng: loc.longitude,
                vehicle_info: `${loc.type} ${loc.model}`,
                driver_name: loc.driver_name
            });
        });
        initializeFirebaseListener();

        // Initialize Lucide Icons after DOM is ready
        lucide.createIcons();
    });
</script>
<script src="dark_mode_handler.js" defer></script>
<script src="loader.js"></script>
</body>
</html>

