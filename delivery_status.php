<?php
session_start();
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}
require_once 'db_connect.php';
$message = '';

// --- PANG-HANDLE NG CSV DOWNLOAD ---
if (isset($_GET['download_csv'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=delivery_status_' . date('Y-m-d') . '.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Trip Code', 'Driver', 'ETA', 'Actual Arrival', 'Delivery Status', 'Notes', 'POD Path']);

    $result = $conn->query("
        SELECT t.trip_code, d.name as driver_name, t.eta, t.actual_arrival_time, t.arrival_status, t.delivery_notes, t.proof_of_delivery_path
        FROM trips t
        JOIN drivers d ON t.driver_id = d.id
        WHERE t.status IN ('Completed', 'En Route', 'Arrived at Destination')
        ORDER BY t.pickup_time DESC
    ");

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            fputcsv($output, [
                $row['trip_code'],
                $row['driver_name'],
                $row['eta'],
                $row['actual_arrival_time'],
                $row['arrival_status'],
                $row['delivery_notes'],
                $row['proof_of_delivery_path']
            ]);
        }
    }
    fclose($output);
    exit;
}
// --- WAKAS NG CSV DOWNLOAD LOGIC ---


// Handle Update Delivery Status and POD Upload
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_delivery'])) {
    $trip_id = $_POST['trip_id'];
    $arrival_status = $_POST['arrival_status'];
    $actual_arrival_time = !empty($_POST['actual_arrival_time']) ? $_POST['actual_arrival_time'] : NULL;
    $notes = $_POST['delivery_notes'];

    // Handle File Upload
    $pod_path_update = "";
    if (isset($_FILES['pod_image']) && $_FILES['pod_image']['error'] == 0) {
        $upload_dir = 'uploads/pod/';
        if (!is_dir($upload_dir)) { mkdir($upload_dir, 0755, true); }
        $file_name = time() . '_' . basename($_FILES['pod_image']['name']);
        $target_file = $upload_dir . $file_name;

        if (move_uploaded_file($_FILES['pod_image']['tmp_name'], $target_file)) {
            $pod_path_update = ", proof_of_delivery_path = ?";
        } else {
            $message = "<div class='message-banner error'>Failed to upload new Proof of Delivery image.</div>";
        }
    }

    $sql = "UPDATE trips SET arrival_status = ?, actual_arrival_time = ?, delivery_notes = ? $pod_path_update WHERE id = ?";
    $stmt = $conn->prepare($sql);

    if (!empty($pod_path_update)) {
        $stmt->bind_param("ssssi", $arrival_status, $actual_arrival_time, $notes, $target_file, $trip_id);
    } else {
        $stmt->bind_param("sssi", $arrival_status, $actual_arrival_time, $notes, $trip_id);
    }

    if ($stmt->execute()) {
        $message = "<div class='message-banner success'>Delivery status for Trip #$trip_id updated successfully.</div>";
    } else {
        $message = "<div class='message-banner error'>Error updating delivery status: " . $stmt->error . "</div>";
    }
    $stmt->close();
}


// --- Fetch Data ---
$trips_result = $conn->query("
    SELECT t.id, t.trip_code, t.destination, t.eta, t.actual_arrival_time, t.arrival_status, t.proof_of_delivery_path, t.delivery_notes, d.name as driver_name
    FROM trips t
    JOIN drivers d ON t.driver_id = d.id
    WHERE t.status IN ('Completed', 'En Route', 'Arrived at Destination')
    ORDER BY t.pickup_time DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Delivery Status | DTPM</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <?php include 'sidebar.php'; ?>

  <div class="content" id="mainContent">
    <div class="header">
      <div class="hamburger" id="hamburger">☰</div>
      <div><h1>Delivery Status & Proof of Delivery</h1></div>
      <div class="theme-toggle-container">
        <span class="theme-label">Dark Mode</span>
        <label class="theme-switch"><input type="checkbox" id="themeToggle"><span class="slider"></span></label>
      </div>
    </div>
    
    <?php echo $message; ?>

    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
          <h3>Monitor and Update Deliveries</h3>
          <a href="delivery_status.php?download_csv=true" class="btn btn-success">Download CSV</a>
      </div>
      <div class="table-section">
          <table>
            <thead>
              <tr>
                <th>Trip Code</th>
                <th>Driver</th>
                <th>ETA</th>
                <th>Actual Arrival</th>
                <th>Delivery Status</th>
                <th>POD</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if($trips_result->num_rows > 0): ?>
                <?php while($row = $trips_result->fetch_assoc()): ?>
                <tr>
                  <td><?php echo htmlspecialchars($row['trip_code']); ?></td>
                  <td><?php echo htmlspecialchars($row['driver_name']); ?></td>
                  <td><?php echo !empty($row['eta']) ? date('M d, Y h:i A', strtotime($row['eta'])) : 'N/A'; ?></td>
                  <td><?php echo !empty($row['actual_arrival_time']) ? date('M d, Y h:i A', strtotime($row['actual_arrival_time'])) : 'N/A'; ?></td>
                  <td>
                    <span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $row['arrival_status'])); ?>">
                        <?php echo htmlspecialchars($row['arrival_status']); ?>
                    </span>
                  </td>
                  <td>
                    <?php if(!empty($row['proof_of_delivery_path'])): ?>
                        <a href="<?php echo htmlspecialchars($row['proof_of_delivery_path']); ?>" target="_blank" class="btn btn-info btn-sm">View</a>
                    <?php else: ?>
                        <span style="color: #888;">None</span>
                    <?php endif; ?>
                  </td>
                  <td class="action-buttons">
                    <button class="btn btn-warning btn-sm updateDeliveryBtn" data-details='<?php echo htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8'); ?>'>Update</button>
                  </td>
                </tr>
                <?php endwhile; ?>
              <?php else: ?>
                <tr><td colspan="7">No completed or ongoing trips found.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
      </div>
    </div>

    <div class="card" id="updateDeliveryCard" style="display: none;">
      <div style="display: flex; justify-content: space-between; align-items: center;">
        <h3 id="updateDeliveryCardTitle">Update Delivery Status</h3>
        <span class="close-button" id="closeUpdateCard">&times;</span>
      </div>
       <form action="delivery_status.php" method="POST" enctype="multipart/form-data" style="margin-top: 1rem;">
            <input type="hidden" name="trip_id" id="update_trip_id">
             <div class="form-group">
                <label for="actual_arrival_time">Actual Arrival Time</label>
                <input type="datetime-local" name="actual_arrival_time" id="actual_arrival_time" class="form-control">
            </div>
             <div class="form-group">
                <label for="arrival_status">Delivery Status</label>
                <select name="arrival_status" id="arrival_status" class="form-control" required>
                    <option value="Pending">Pending</option>
                    <option value="On-Time">On-Time</option>
                    <option value="Early">Early</option>
                    <option value="Late">Late</option>
                </select>
            </div>
             <div class="form-group">
                <label for="delivery_notes">Delivery Notes</label>
                <textarea name="delivery_notes" id="delivery_notes" class="form-control" rows="3"></textarea>
            </div>
             <div class="form-group">
                <label>Current Proof of Delivery</label>
                <div id="current_pod_view"></div>
            </div>
            <div class="form-group">
                <label for="pod_image">Upload New/Replacement POD</label>
                <input type="file" name="pod_image" id="pod_image" class="form-control" accept="image/*">
            </div>
            <div class="form-actions">
                <button type="submit" name="update_delivery" class="btn btn-primary">Save Changes</button>
            </div>
       </form>
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
            if (parent.classList.contains('open')) { menu.style.maxHeight = menu.scrollHeight + 'px'; } 
            else { menu.style.maxHeight = '0'; }
        });
    });

    // Update Delivery Card Logic
    const updateCard = document.getElementById('updateDeliveryCard');
    const closeUpdateCard = document.getElementById('closeUpdateCard');

    document.querySelectorAll('.updateDeliveryBtn').forEach(btn => {
        btn.addEventListener('click', () => {
            const data = JSON.parse(btn.dataset.details);
            
            updateCard.querySelector('#update_trip_id').value = data.id;
            updateCard.querySelector('#updateDeliveryCardTitle').textContent = `Update Trip: ${data.trip_code}`;
            
            // Format datetime-local correctly
            if (data.actual_arrival_time) {
                const date = new Date(data.actual_arrival_time);
                // Adjust for timezone offset
                date.setMinutes(date.getMinutes() - date.getTimezoneOffset());
                updateCard.querySelector('#actual_arrival_time').value = date.toISOString().slice(0,16);
            } else {
                 updateCard.querySelector('#actual_arrival_time').value = '';
            }

            updateCard.querySelector('#arrival_status').value = data.arrival_status;
            updateCard.querySelector('#delivery_notes').value = data.delivery_notes;

            const podView = updateCard.querySelector('#current_pod_view');
            if(data.proof_of_delivery_path) {
                podView.innerHTML = `<a href="${data.proof_of_delivery_path}" target="_blank"><img src="${data.proof_of_delivery_path}" alt="POD" style="max-width: 100px; max-height: 100px; border-radius: 5px;"></a>`;
            } else {
                podView.innerHTML = `<span>No POD uploaded yet.</span>`;
            }

            updateCard.style.display = 'block';
            updateCard.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    });

    if(closeUpdateCard) {
        closeUpdateCard.addEventListener('click', () => {
            updateCard.style.display = 'none';
        });
    }
});
</script>
<script src="dark_mode_handler.js" defer></script>
</body>
</html>
