<?php
session_start();
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}
require_once 'db_connect.php';

// --- PANG-HANDLE NG CSV DOWNLOAD ---
if (isset($_GET['download_csv'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=trip_history_' . date('Y-m-d') . '.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Trip Code', 'Vehicle', 'Driver', 'Pickup Time', 'Destination', 'Status', 'Delivery Status', 'Actual Arrival', 'POD Path']);

    // Re-run the same filtering logic for the CSV export
    $where_clauses_csv = [];
    $params_csv = [];
    $types_csv = '';

    $search_query_csv = isset($_GET['search']) ? $_GET['search'] : '';
    if (!empty($search_query_csv)) {
        $where_clauses_csv[] = "(t.trip_code LIKE ? OR t.destination LIKE ? OR v.type LIKE ? OR v.model LIKE ?)";
        $search_term_csv = "%" . $search_query_csv . "%";
        array_push($params_csv, $search_term_csv, $search_term_csv, $search_term_csv, $search_term_csv);
        $types_csv .= 'ssss';
    }
    // ... (add other filters similarly) ...
    $sql_csv = "
        SELECT t.*, v.type AS vehicle_type, v.model AS vehicle_model, d.name AS driver_name
        FROM trips t
        JOIN vehicles v ON t.vehicle_id = v.id
        JOIN drivers d ON t.driver_id = d.id
    ";
    if (!empty($where_clauses_csv)) { $sql_csv .= " WHERE " . implode(" AND ", $where_clauses_csv); }
    $sql_csv .= " ORDER BY t.pickup_time DESC";

    $stmt_csv = $conn->prepare($sql_csv);
    if (!empty($params_csv)) { $stmt_csv->bind_param($types_csv, ...$params_csv); }
    $stmt_csv->execute();
    $result_csv = $stmt_csv->get_result();

    while ($row = $result_csv->fetch_assoc()) {
        fputcsv($output, [
            $row['trip_code'],
            $row['vehicle_type'] . ' ' . $row['vehicle_model'],
            $row['driver_name'],
            $row['pickup_time'],
            $row['destination'],
            $row['status'],
            $row['arrival_status'],
            $row['actual_arrival_time'],
            $row['proof_of_delivery_path']
        ]);
    }
    fclose($output);
    exit;
}
// --- WAKAS NG CSV DOWNLOAD LOGIC ---


// --- Filtering and Searching Logic ---
$where_clauses = [];
$params = [];
$types = '';

$search_query = isset($_GET['search']) ? $_GET['search'] : '';
if (!empty($search_query)) {
    $where_clauses[] = "(t.trip_code LIKE ? OR t.destination LIKE ? OR v.type LIKE ? OR v.model LIKE ?)";
    $search_term = "%" . $search_query . "%";
    array_push($params, $search_term, $search_term, $search_term, $search_term);
    $types .= 'ssss';
}

$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';
if (!empty($start_date) && !empty($end_date)) {
    $where_clauses[] = "DATE(t.pickup_time) BETWEEN ? AND ?";
    array_push($params, $start_date, $end_date);
    $types .= 'ss';
}

$driver_id = isset($_GET['driver_id']) && is_numeric($_GET['driver_id']) ? (int)$_GET['driver_id'] : '';
if (!empty($driver_id)) {
    $where_clauses[] = "t.driver_id = ?";
    $params[] = $driver_id;
    $types .= 'i';
}

$status = isset($_GET['status']) ? $_GET['status'] : '';
if (!empty($status)) {
    $where_clauses[] = "t.status = ?";
    $params[] = $status;
    $types .= 's';
}

$sql = "
    SELECT t.*, v.type AS vehicle_type, v.model AS vehicle_model, d.name AS driver_name
    FROM trips t
    JOIN vehicles v ON t.vehicle_id = v.id
    JOIN drivers d ON t.driver_id = d.id
";

if (!empty($where_clauses)) {
    $sql .= " WHERE " . implode(" AND ", $where_clauses);
}
$sql .= " ORDER BY t.pickup_time DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$trip_history_result = $stmt->get_result();

$drivers_result = $conn->query("SELECT id, name FROM drivers ORDER BY name ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Trip History | DTPM</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <?php include 'sidebar.php'; ?>

  <div class="content" id="mainContent">
    <div class="header">
      <div class="hamburger" id="hamburger">☰</div>
      <div><h1>Trip History Logs</h1></div>
      <div class="theme-toggle-container">
        <span class="theme-label">Dark Mode</span>
        <label class="theme-switch"><input type="checkbox" id="themeToggle"><span class="slider"></span></label>
      </div>
    </div>
    
    <div class="card">
        <h3>Filter and Search Trips</h3>
        <form action="trip_history.php" method="GET" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; align-items: end;">
            <div class="form-group">
                <label for="search">Search</label>
                <input type="text" name="search" id="search" class="form-control" placeholder="Trip code, destination..." value="<?php echo htmlspecialchars($search_query); ?>">
            </div>
            <div class="form-group">
                <label for="start_date">Start Date</label>
                <input type="date" name="start_date" id="start_date" class="form-control" value="<?php echo htmlspecialchars($start_date); ?>">
            </div>
            <div class="form-group">
                <label for="end_date">End Date</label>
                <input type="date" name="end_date" id="end_date" class="form-control" value="<?php echo htmlspecialchars($end_date); ?>">
            </div>
            <div class="form-group">
                <label for="driver_id">Driver</label>
                <select name="driver_id" id="driver_id" class="form-control">
                    <option value="">All Drivers</option>
                    <?php while($driver = $drivers_result->fetch_assoc()): ?>
                        <option value="<?php echo $driver['id']; ?>" <?php if($driver_id == $driver['id']) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($driver['name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="status">Status</label>
                <select name="status" id="status" class="form-control">
                    <option value="">All Statuses</option>
                    <option value="Scheduled" <?php if($status == 'Scheduled') echo 'selected'; ?>>Scheduled</option>
                    <option value="En Route" <?php if($status == 'En Route') echo 'selected'; ?>>En Route</option>
                    <option value="Completed" <?php if($status == 'Completed') echo 'selected'; ?>>Completed</option>
                    <option value="Cancelled" <?php if($status == 'Cancelled') echo 'selected'; ?>>Cancelled</option>
                    <option value="Breakdown" <?php if($status == 'Breakdown') echo 'selected'; ?>>Breakdown</option>
                </select>
            </div>
            <div class="form-actions" style="grid-column: 1 / -1; justify-content: start; gap: 0.5rem;">
                <button type="submit" class="btn btn-primary">Filter</button>
                <a href="trip_history.php" class="btn btn-secondary">Reset</a>
                <a href="trip_history.php?download_csv=true&<?php echo http_build_query($_GET); ?>" class="btn btn-success">Download CSV</a>
            </div>
        </form>
    </div>

    <div class="card">
        <h3>All Trips</h3>
        <div class="table-section">
          <table>
            <thead>
              <tr><th>Trip Code</th><th>Vehicle</th><th>Driver</th><th>Pickup Time</th><th>Destination</th><th>Status</th><th>Delivery Status</th><th>POD</th></tr>
            </thead>
            <tbody>
                <?php if ($trip_history_result->num_rows > 0): ?>
                    <?php while($row = $trip_history_result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['trip_code']); ?></td>
                        <td><?php echo htmlspecialchars($row['vehicle_type'] . ' ' . $row['vehicle_model']); ?></td>
                        <td><?php echo htmlspecialchars($row['driver_name']); ?></td>
                        <td><?php echo htmlspecialchars(date('M d, Y h:i A', strtotime($row['pickup_time']))); ?></td>
                        <td><?php echo htmlspecialchars($row['destination']); ?></td>
                        <td><span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $row['status'])); ?>"><?php echo htmlspecialchars($row['status']); ?></span></td>
                        <td>
                            <?php if(!empty($row['arrival_status']) && $row['arrival_status'] != 'Pending'): ?>
                                <span class="status-badge status-<?php echo strtolower(str_replace('-', '', $row['arrival_status'])); ?>"><?php echo htmlspecialchars($row['arrival_status']); ?></span>
                            <?php else: echo 'N/A'; endif; ?>
                        </td>
                        <td>
                            <?php if(!empty($row['proof_of_delivery_path'])): ?>
                                <a href="<?php echo htmlspecialchars($row['proof_of_delivery_path']); ?>" target="_blank" class="btn btn-info btn-sm">View</a>
                            <?php else: echo 'None'; endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="8">No trips found matching your criteria.</td></tr>
                <?php endif; ?>
            </tbody>
          </table>
        </div>
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
    });
  </script>
  <script src="dark_mode_handler.js" defer></script>
</body>
</html>
