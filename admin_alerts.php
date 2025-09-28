<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}
require_once 'db_connect.php';
$message = '';

// --- PANG-HANDLE NG CSV DOWNLOAD ---
if (isset($_GET['download_csv'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=alerts_report_' . date('Y-m-d') . '.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Trip Code', 'Driver Name', 'Alert Type', 'Description', 'Status', 'Date Created']);

    $result = $conn->query("
        SELECT a.*, d.name as driver_name, t.trip_code 
        FROM alerts a 
        JOIN drivers d ON a.driver_id = d.id 
        JOIN trips t ON a.trip_id = t.id
        ORDER BY a.created_at DESC
    ");
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            fputcsv($output, [
                $row['id'],
                $row['trip_code'],
                $row['driver_name'],
                $row['alert_type'],
                $row['description'],
                $row['status'],
                $row['created_at']
            ]);
        }
    }
    fclose($output);
    exit;
}
// --- WAKAS NG CSV DOWNLOAD LOGIC ---


if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_alert_status'])) {
    $alert_id = $_POST['alert_id'];
    $new_status = $_POST['new_status'];
    
    $stmt = $conn->prepare("UPDATE alerts SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $new_status, $alert_id);
    if ($stmt->execute()) {
        $message = "<div class='message-banner success'>Alert status updated successfully.</div>";
    } else {
        $message = "<div class='message-banner error'>Failed to update alert status.</div>";
    }
}

$sos_alerts_result = $conn->query("
    SELECT a.*, d.name as driver_name, t.trip_code 
    FROM alerts a 
    JOIN drivers d ON a.driver_id = d.id 
    JOIN trips t ON a.trip_id = t.id
    ORDER BY a.created_at DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Emergency Alerts | MFC</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <?php include 'sidebar.php'; ?>

  <div class="content" id="mainContent">
    <div class="header">
      <div class="hamburger" id="hamburger">☰</div>
      <div><h1>Emergency SOS Alerts</h1></div>
      <div class="theme-toggle-container">
        <span class="theme-label">Dark Mode</span>
        <label class="theme-switch"><input type="checkbox" id="themeToggle"><span class="slider"></span></label>
      </div>
    </div>
    
    <?php echo $message; ?>

    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
            <h3>Incoming Alerts</h3>
            <a href="admin_alerts.php?download_csv=true" class="btn btn-success">Download CSV</a>
        </div>
        <div class="table-section">
            <table>
                <thead><tr><th>Time</th><th>Driver</th><th>Trip Code</th><th>Description</th><th>Status</th><th>Actions</th></tr></thead>
                <tbody>
                    <?php if($sos_alerts_result->num_rows > 0): ?>
                    <?php while($alert = $sos_alerts_result->fetch_assoc()): ?>
                    <tr style="<?php echo $alert['status'] == 'Pending' ? 'font-weight: bold; background-color: rgba(231, 74, 59, 0.1);' : ''; ?>">
                        <td><?php echo htmlspecialchars(date('M d, Y h:i A', strtotime($alert['created_at']))); ?></td>
                        <td><?php echo htmlspecialchars($alert['driver_name']); ?></td>
                        <td><a href="trip_history.php?search=<?php echo $alert['trip_code']; ?>"><?php echo htmlspecialchars($alert['trip_code']); ?></a></td>
                        <td><?php echo htmlspecialchars($alert['description']); ?></td>
                        <td><span class="status-badge status-<?php echo strtolower($alert['status']); ?>"><?php echo htmlspecialchars($alert['status']); ?></span></td>
                        <td>
                            <?php if ($alert['status'] != 'Resolved'): ?>
                            <form action="admin_alerts.php" method="POST" style="display:inline;">
                                <input type="hidden" name="alert_id" value="<?php echo $alert['id']; ?>">
                                <select name="new_status" onchange="this.form.submit()" class="form-control" style="display: inline-block; width: auto;">
                                    <option value="Pending" <?php if($alert['status'] == 'Pending') echo 'selected'; ?>>Pending</option>
                                    <option value="Acknowledged" <?php if($alert['status'] == 'Acknowledged') echo 'selected'; ?>>Acknowledge</option>
                                    <option value="Resolved" <?php if($alert['status'] == 'Resolved') echo 'selected'; ?>>Resolve</option>
                                </select>
                                <input type="hidden" name="update_alert_status" value="1">
                            </form>
                            <?php else: ?>
                                <span>No actions</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                    <?php else: ?>
                    <tr><td colspan="6">No SOS alerts found.</td></tr>
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
        if (window.innerWidth <= 992) { 
            sidebar.classList.toggle('show'); 
        } else { 
            sidebar.classList.toggle('collapsed'); 
            mainContent.classList.toggle('expanded'); 
        }
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
