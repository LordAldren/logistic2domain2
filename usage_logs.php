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
// --- WAKAS NG CSV DOWNLOAD LOGIC ---


$usage_logs_result = $conn->query("SELECT u.*, v.type, v.model FROM usage_logs u JOIN vehicles v ON u.vehicle_id = v.id ORDER BY u.log_date DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Usage Logs | FVM</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <?php include 'sidebar.php'; ?>

  <div class="content" id="mainContent">
    <div class="header">
      <div class="hamburger" id="hamburger">☰</div>
      <div><h1>Usage Logs</h1></div>
      <div class="theme-toggle-container">
        <span class="theme-label">Dark Mode</span>
        <label class="theme-switch"><input type="checkbox" id="themeToggle"><span class="slider"></span></label>
      </div>
    </div>
    
    <?php echo $message; ?>

    <div class="card" id="usage-logs">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
          <h3>Vehicle Usage History</h3>
          <a href="usage_logs.php?download_csv=true" class="btn btn-success">Download CSV</a>
      </div>
      <div class="table-section">
          <table>
            <thead>
              <tr>
                <th>VEHICLE NAME</th>
                <th>DATE</th>
                <th>METRICS</th>
                <th>FUEL (L)</th>
                <th>MILEAGE (km)</th>
              </tr>
            </thead>
            <tbody>
              <?php if($usage_logs_result->num_rows > 0): ?>
                <?php while($row = $usage_logs_result->fetch_assoc()): ?>
                <tr>
                  <td><?php echo htmlspecialchars($row['type'] . ' ' . $row['model']); ?></td>
                  <td><?php echo htmlspecialchars($row['log_date']); ?></td>
                  <td><?php echo htmlspecialchars($row['metrics']); ?></td>
                  <td><?php echo htmlspecialchars($row['fuel_usage']); ?></td>
                  <td><?php echo htmlspecialchars($row['mileage']); ?></td>
                </tr>
                <?php endwhile; ?>
              <?php else: ?>
                <tr><td colspan="5">No usage logs found.</td></tr>
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
