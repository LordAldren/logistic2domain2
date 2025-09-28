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
// --- WAKAS NG CSV DOWNLOAD LOGIC ---


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
  <title>Maintenance Approval | FVM</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <?php include 'sidebar.php'; ?>

  <div class="content" id="mainContent">
    <div class="header">
      <div class="hamburger" id="hamburger">☰</div>
      <div><h1>Maintenance Approval</h1></div>
      <div class="theme-toggle-container">
        <span class="theme-label">Dark Mode</span>
        <label class="theme-switch"><input type="checkbox" id="themeToggle"><span class="slider"></span></label>
      </div>
    </div>
    
    <?php echo $message; ?>

    <div class="card" id="maintenance-approval">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
          <h3>Pending and Ongoing Maintenance</h3>
          <a href="maintenance_approval.php?download_csv=true" class="btn btn-success">Download CSV</a>
      </div>
      <div class="table-section">
          <table>
            <thead>
              <tr>
                <th>VEHICLE NAME</th>
                <th>ARRIVAL DATE</th>
                <th>DATE OF RETURN</th>
                <th>STATUS</th>
                <th>ACTIONS</th>
              </tr>
            </thead>
            <tbody>
              <?php if($maintenance_result->num_rows > 0): ?>
                <?php while($row = $maintenance_result->fetch_assoc()): ?>
                <tr>
                  <td><?php echo htmlspecialchars($row['type'] . ' ' . $row['model']); ?></td>
                  <td><?php echo htmlspecialchars($row['arrival_date']); ?></td>
                  <td><?php echo htmlspecialchars($row['date_of_return'] ?? 'N/A'); ?></td>
                  <td><span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $row['status'])); ?>"><?php echo htmlspecialchars($row['status']); ?></span></td>
                  <td class="action-buttons">
                    <?php if ($row['status'] == 'Pending'): ?>
                        <form action="maintenance_approval.php" method="POST" style="display: inline;">
                            <input type="hidden" name="maintenance_id_status" value="<?php echo $row['id']; ?>">
                            <input type="hidden" name="new_status" value="Approved">
                            <button type="submit" name="update_maintenance_status" class="btn btn-success btn-sm">Approve</button>
                        </form>
                        <form action="maintenance_approval.php" method="POST" style="display: inline;">
                            <input type="hidden" name="maintenance_id_status" value="<?php echo $row['id']; ?>">
                            <input type="hidden" name="new_status" value="On-Queue">
                            <button type="submit" name="update_maintenance_status" class="btn btn-warning btn-sm">On-Queue</button>
                        </form>
                    <?php elseif ($row['status'] == 'Approved' || $row['status'] == 'In Progress' || $row['status'] == 'On-Queue'): ?>
                        <form action="maintenance_approval.php" method="POST" style="display: inline;">
                            <input type="hidden" name="maintenance_id_status" value="<?php echo $row['id']; ?>">
                            <input type="hidden" name="new_status" value="Completed">
                            <button type="submit" name="update_maintenance_status" class="btn btn-primary btn-sm">Mark as Done</button>
                        </form>
                    <?php else: ?>
                        <span>No actions available</span>
                    <?php endif; ?>
                  </td>
                </tr>
                <?php endwhile; ?>
              <?php else: ?>
                <tr><td colspan="5">No maintenance requests found.</td></tr>
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
