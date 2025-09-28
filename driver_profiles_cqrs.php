<?php
session_start();
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}
require_once 'db_connect.php';
$message = '';

// --- EVENT HANDLER FUNCTIONS ---
// Implements the "write-side" of CQRS. All modifications go through here.

/**
 * Creates a new event record in the database.
 * @param int $aggregateId The ID of the resource being modified (e.g., driver ID).
 * @param string $eventType The type of event (e.g., 'DriverUpdated', 'DriverDeleted').
 * @param array $payload The data payload for the event.
 * @return bool True on success, false on failure.
 */
function createEvent($aggregateId, $eventType, $payload, $conn) {
    $eventPayloadJson = json_encode($payload);
    $stmt = $conn->prepare("INSERT INTO events (aggregate_id, event_type, event_payload) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $aggregateId, $eventType, $eventPayloadJson);
    return $stmt->execute();
}

/**
 * Rebuilds the read-model (drivers table) from the event store.
 * This is a simplified function to demonstrate the concept.
 * In a real application, this would be triggered by a background process.
 */
function rebuildReadModel($conn) {
    // 1. Clear the current read-model table
    $conn->query("TRUNCATE TABLE drivers");
    
    // 2. Fetch all events ordered by timestamp
    $events_result = $conn->query("SELECT * FROM events ORDER BY created_at ASC");
    $drivers_state = [];

    // 3. Process each event to build the final state
    while ($event = $events_result->fetch_assoc()) {
        $aggregateId = $event['aggregate_id'];
        $eventType = $event['event_type'];
        $payload = json_decode($event['event_payload'], true);

        if ($eventType === 'DriverCreated') {
            $drivers_state[$aggregateId] = $payload;
        } elseif ($eventType === 'DriverUpdated') {
            if (isset($drivers_state[$aggregateId])) {
                $drivers_state[$aggregateId] = array_merge($drivers_state[$aggregateId], $payload);
            }
        } elseif ($eventType === 'DriverDeleted') {
            unset($drivers_state[$aggregateId]);
        }
    }
    
    // 4. Insert the final state back into the read-model table
    $stmt = $conn->prepare("INSERT INTO drivers (id, user_id, name, license_number, license_expiry_date, contact_number, date_joined, status, rating, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($drivers_state as $id => $driver_data) {
        $stmt->bind_param("iissssssds", 
            $id, 
            $driver_data['user_id'], 
            $driver_data['name'], 
            $driver_data['license_number'], 
            $driver_data['license_expiry_date'], 
            $driver_data['contact_number'], 
            $driver_data['date_joined'], 
            $driver_data['status'], 
            $driver_data['rating'],
            $driver_data['created_at']
        );
        $stmt->execute();
    }
    $stmt->close();
}


// --- PANG-HANDLE NG CSV DOWNLOAD (QUERY SIDE) ---
if (isset($_GET['download_csv'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=driver_profiles_' . date('Y-m-d') . '.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Name', 'License Number', 'License Expiry', 'Contact Number', 'Date Joined', 'Status', 'Rating', 'Total Trips', 'Avg Adherence Score']);

    // This is the read-model query (does not touch the event store)
    $result = $conn->query("
        SELECT d.*, COUNT(t.id) as total_trips, AVG(t.route_adherence_score) as avg_adherence_score
        FROM drivers d
        LEFT JOIN trips t ON d.id = t.driver_id AND t.status = 'Completed'
        WHERE d.status != 'Pending'
        GROUP BY d.id
        ORDER BY d.name ASC
    ");

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            fputcsv($output, [
                $row['id'],
                $row['name'],
                $row['license_number'],
                $row['license_expiry_date'],
                $row['contact_number'],
                $row['date_joined'],
                $row['status'],
                $row['rating'],
                $row['total_trips'],
                number_format((float)$row['avg_adherence_score'], 2)
            ]);
        }
    }
    fclose($output);
    exit;
}
// --- END OF CSV DOWNLOAD LOGIC ---


// Handle Driver Approval/Rejection (COMMAND SIDE)
if ($_SESSION['role'] === 'admin' && $_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_driver_status'])) {
    $driver_id_to_update = $_POST['driver_id_to_update'];
    $new_status = $_POST['new_status'];

    if ($new_status === 'Active') {
        if (createEvent($driver_id_to_update, 'DriverApproved', ['status' => 'Active'], $conn)) {
             $message = "<div class='message-banner success'>Driver approved. Change will be reflected shortly.</div>";
        } else {
             $message = "<div class='message-banner error'>Error approving driver.</div>";
        }
    } elseif ($new_status === 'Rejected') {
        if (createEvent($driver_id_to_update, 'DriverRejected', ['status' => 'Rejected'], $conn)) {
             $message = "<div class='message-banner success'>Driver rejected. Change will be reflected shortly.</div>";
        } else {
             $message = "<div class='message-banner error'>Error rejecting driver.</div>";
        }
    }
    // Rebuild the read model after every command for immediate consistency (simplified for this example)
    rebuildReadModel($conn);
}


// Handle Add/Edit Driver (COMMAND SIDE)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_driver'])) {
    $id = $_POST['driver_id']; 
    $payload = [
        'name' => $_POST['name'], 
        'license_number' => $_POST['license_number'], 
        'license_expiry_date' => !empty($_POST['license_expiry_date']) ? $_POST['license_expiry_date'] : NULL,
        'contact_number' => $_POST['contact_number'],
        'date_joined' => !empty($_POST['date_joined']) ? $_POST['date_joined'] : NULL,
        'status' => $_POST['status'], 
        'rating' => $_POST['rating'], 
        'user_id' => !empty($_POST['user_id']) ? (int)$_POST['user_id'] : NULL,
    ];
    
    if (empty($id)) { 
        // For 'Create' command, we need to generate a new ID first
        $new_id_query = $conn->query("SELECT MAX(id) + 1 AS new_id FROM drivers");
        $new_id = $new_id_query->fetch_assoc()['new_id'] ?? 1;
        $payload['id'] = $new_id;
        $payload['created_at'] = date('Y-m-d H:i:s');
        if (createEvent($new_id, 'DriverCreated', $payload, $conn)) {
             $message = "<div class='message-banner success'>Driver saved successfully!</div>"; 
        } else {
             $message = "<div class='message-banner error'>Error saving driver.</div>"; 
        }
    } else { 
        if (createEvent($id, 'DriverUpdated', $payload, $conn)) {
             $message = "<div class='message-banner success'>Driver saved successfully!</div>"; 
        } else {
             $message = "<div class='message-banner error'>Error saving driver.</div>"; 
        }
    }
    // Rebuild the read model
    rebuildReadModel($conn);
}

// Handle Delete Driver (COMMAND SIDE)
if (isset($_GET['delete_driver'])) {
    $id = $_GET['delete_driver']; 
    if (createEvent($id, 'DriverDeleted', [], $conn)) {
         $message = "<div class='message-banner success'>Driver deleted successfully!</div>"; 
    } else {
         $message = "<div class='message-banner error'>Error: Cannot delete a driver assigned to a trip.</div>"; 
    }
    // Rebuild the read model
    rebuildReadModel($conn);
}


// Fetch Pending Drivers (QUERY SIDE)
$pending_drivers = $conn->query("SELECT d.id, d.name, d.license_number, u.email FROM drivers d JOIN users u ON d.user_id = u.id WHERE d.status = 'Pending' ORDER BY d.created_at ASC");

// Fetch Active Driver Data with all new details (QUERY SIDE)
$drivers_query = "
    SELECT
        d.*,
        v.type as vehicle_type,
        v.model as vehicle_model,
        v.tag_code,
        AVG(t.route_adherence_score) as avg_adherence_score,
        COUNT(t.id) as total_trips
    FROM
        drivers d
    LEFT JOIN
        vehicles v ON d.id = v.assigned_driver_id
    LEFT JOIN
        trips t ON d.id = t.driver_id AND t.status = 'Completed'
    WHERE
        d.status != 'Pending'
    GROUP BY
        d.id
    ORDER BY
        d.name ASC
";
$drivers = $conn->query($drivers_query);
$users = $conn->query("SELECT id, username FROM users WHERE role = 'driver'");

// Fetch Driver Behavior Data (QUERY SIDE)
$behavior_logs_query = $conn->query("
    SELECT dbl.*, d.name as driver_name
    FROM driver_behavior_logs dbl
    JOIN drivers d ON dbl.driver_id = d.id
    ORDER BY dbl.log_date DESC
");
$behavior_logs = [];
while($row = $behavior_logs_query->fetch_assoc()) {
    $behavior_logs[$row['driver_id']][] = $row;
}
$behavior_logs_json = json_encode($behavior_logs);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Driver Profiles | CQRS</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <?php include 'sidebar.php'; ?>

  <div class="content" id="mainContent">
    <div class="header">
      <div class="hamburger" id="hamburger">☰</div>
      <div><h1>Driver Profile Management (CQRS Version)</h1></div>
      <div class="theme-toggle-container">
        <span class="theme-label">Dark Mode</span>
        <label class="theme-switch"><input type="checkbox" id="themeToggle"><span class="slider"></span></label>
      </div>
    </div>
    
    <?php echo $message; ?>

    <?php if ($_SESSION['role'] === 'admin' && $pending_drivers->num_rows > 0): ?>
    <div class="card">
      <h3>Pending Driver Registrations</h3>
      <div class="table-section">
        <table>
          <thead><tr><th>Name</th><th>License No.</th><th>Email</th><th>Actions</th></tr></thead>
          <tbody>
              <?php while($row = $pending_drivers->fetch_assoc()): ?>
              <tr>
                  <td><?php echo htmlspecialchars($row['name']); ?></td>
                  <td><?php echo htmlspecialchars($row['license_number']); ?></td>
                  <td><?php echo htmlspecialchars($row['email']); ?></td>
                  <td class="action-buttons">
                      <form action="driver_profiles_cqrs.php" method="POST" style="display: inline-block;">
                          <input type="hidden" name="driver_id_to_update" value="<?php echo $row['id']; ?>">
                          <input type="hidden" name="new_status" value="Active">
                          <button type="submit" name="update_driver_status" class="btn btn-success btn-sm">Approve</button>
                      </form>
                      <form action="driver_profiles_cqrs.php" method="POST" style="display: inline-block;">
                          <input type="hidden" name="driver_id_to_update" value="<?php echo $row['id']; ?>">
                          <input type="hidden" name="new_status" value="Rejected">
                          <button type="submit" name="update_driver_status" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to reject and delete this registration?');">Reject</button>
                      </form>
                  </td>
              </tr>
              <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>

    <div class="card">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
          <h3>Driver Profiles</h3>
          <div>
            <button id="addDriverBtn" class="btn btn-primary">Add Driver</button>
            <a href="driver_profiles_cqrs.php?download_csv=true" class="btn btn-success">Download CSV</a>
          </div>
      </div>
      
      <div id="driverModal" class="modal">
        <div class="modal-content">
            <span class="close-button">&times;</span>
            <h2 id="modalTitle">Add Driver</h2>
            <form action="driver_profiles_cqrs.php" method="POST">
                <input type="hidden" id="driver_id" name="driver_id">
                <div class="form-group"><label>Full Name</label><input type="text" name="name" id="name" class="form-control" required></div>
                <div class="form-group"><label>Contact Number</label><input type="text" name="contact_number" id="contact_number" class="form-control"></div>
                 <div class="form-group"><label>Date Joined</label><input type="date" name="date_joined" id="date_joined" class="form-control"></div>
                <div class="form-group"><label>License Number</label><input type="text" name="license_number" id="license_number" class="form-control" required></div>
                <div class="form-group"><label>License Expiry</label><input type="date" name="license_expiry_date" id="license_expiry_date" class="form-control"></div>
                <div class="form-group"><label>Rating (1.0 - 5.0)</label><input type="number" step="0.1" min="1" max="5" name="rating" id="rating" class="form-control" required></div>
                <div class="form-group"><label>Status</label><select name="status" id="status" class="form-control" required><option value="Active">Active</option><option value="Suspended">Suspended</option><option value="Inactive">Inactive</option></select></div>
                <div class="form-group"><label>Link to User Account</label><select name="user_id" id="user_id" class="form-control"><option value="">-- None --</option><?php mysqli_data_seek($users, 0); while($user = $users->fetch_assoc()): ?><option value="<?php echo $user['id']; ?>"><?php echo htmlspecialchars($user['username']); ?></option><?php endwhile; ?></select></div>
                <div class="form-actions"><button type="button" class="btn btn-secondary cancelBtn">Cancel</button><button type="submit" name="save_driver" class="btn btn-primary">Save Driver</button></div>
            </form>
        </div>
      </div>

      <div class="table-section">
          <table>
            <thead>
              <tr><th>Name</th><th>Contact</th><th>License Expiry</th><th>Date Joined</th><th>Trips</th><th>Status</th><th>Avg. Adherence</th><th>Rating</th><th>Actions</th></tr>   
            </thead>
            <tbody>
                <?php if($drivers->num_rows > 0): mysqli_data_seek($drivers, 0); ?>
                   <?php while($row = $drivers->fetch_assoc()): 
                        $expiry_date = !empty($row['license_expiry_date']) ? new DateTime($row['license_expiry_date']) : null;
                        $today = new DateTime();
                        $is_expiring_soon = false;
                        if ($expiry_date) {
                            $diff = $today->diff($expiry_date);
                            $is_expiring_soon = ($diff->days <= 30 && !$diff->invert);
                        }
                   ?>
                  <tr>
                      <td><?php echo htmlspecialchars($row['name']); ?></td>
                      <td><?php echo htmlspecialchars($row['contact_number'] ?? 'N/A'); ?></td>
                      <td class="<?php echo $is_expiring_soon ? 'expiring-soon' : ''; ?>">
                        <?php echo !empty($row['license_expiry_date']) ? htmlspecialchars($row['license_expiry_date']) : 'N/A'; ?>
                      </td>
                      <td><?php echo !empty($row['date_joined']) ? htmlspecialchars($row['date_joined']) : 'N/A'; ?></td>
                      <td><?php echo htmlspecialchars($row['total_trips']); ?></td>
                      <td><span class="status-badge status-<?php echo strtolower($row['status']); ?>"><?php echo htmlspecialchars($row['status']); ?></span></td>
                      <td>
                        <?php 
                            if ($row['avg_adherence_score'] !== null) {
                                $score = $row['avg_adherence_score'];
                                $score_class = 'score-medium';
                                if ($score >= 95) $score_class = 'score-high';
                                if ($score < 80) $score_class = 'score-low';
                                echo '<strong class="' . $score_class . '">' . number_format($score, 2) . '%</strong>';
                            } else {
                                echo '<span style="color: #888;">N/A</span>';
                            }
                        ?>
                      </td>
                       <td><?php echo htmlspecialchars($row['rating']); ?> ★</td>
                      <td class="action-buttons">
                        <button class="btn btn-warning btn-sm editDriverBtn" data-details='<?php echo htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8'); ?>'>Edit</button>
                        <button class="btn btn-info btn-sm viewBehaviorBtn" data-driver-id="<?php echo $row['id']; ?>" data-driver-name="<?php echo htmlspecialchars($row['name']); ?>">Behavior</button>
                        <a href="driver_profiles_cqrs.php?delete_driver=<?php echo $row['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure?');">Delete</a>
                      </td>
                  </tr>
                  <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="9">No active drivers found.</td></tr>
                <?php endif; ?>
            </tbody>
          </table>
      </div>
    </div>
    
    <div class="card" id="behaviorCard" style="display: none;">
      <div style="display: flex; justify-content: space-between; align-items: center;">
        <h3 id="behaviorCardTitle">Driver Behavior Logs</h3>
        <span class="close-button" id="closeBehaviorCard">&times;</span>
      </div>
      <div class="table-section" id="behaviorCardBody" style="margin-top: 1rem;">
        <!-- Content will be inserted by JavaScript -->
      </div>
    </div>

  </div>
  
<script>
    document.addEventListener('DOMContentLoaded', function() {
        document.getElementById('themeToggle').addEventListener('change', function() { document.body.classList.toggle('dark-mode', this.checked); });
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

        document.querySelectorAll('.modal').forEach(modal => {
            const closeBtn = modal.querySelector('.close-button'); 
            const cancelBtn = modal.querySelector('.cancelBtn');
            if(closeBtn) { closeBtn.addEventListener('click', () => modal.style.display = 'none'); }
            if(cancelBtn) { cancelBtn.addEventListener('click', () => modal.style.display = 'none'); }
        });
        const driverModal = document.getElementById("driverModal");
        document.getElementById("addDriverBtn").addEventListener("click", () => {
            driverModal.querySelector('form').reset();
            driverModal.querySelector('#driver_id').value = '';
            driverModal.querySelector('#modalTitle').textContent = 'Add Driver';
            driverModal.style.display = 'block';
        });
        document.querySelectorAll('.editDriverBtn').forEach(btn => {
            btn.addEventListener('click', () => {
                const data = JSON.parse(btn.dataset.details);
                driverModal.querySelector('form').reset();
                driverModal.querySelector('#modalTitle').textContent = 'Edit Driver';
                driverModal.querySelector('#driver_id').value = data.id;
                driverModal.querySelector('#name').value = data.name;
                driverModal.querySelector('#license_number').value = data.license_number;
                driverModal.querySelector('#license_expiry_date').value = data.license_expiry_date;
                driverModal.querySelector('#contact_number').value = data.contact_number;
                driverModal.querySelector('#date_joined').value = data.date_joined;
                driverModal.querySelector('#rating').value = data.rating;
                driverModal.querySelector('#status').value = data.status;
                driverModal.querySelector('#user_id').value = data.user_id;
                driverModal.style.display = 'block';
            });
        });

        // Driver Behavior Logic (This is on the Query side, so it doesn't change)
        const behaviorLogs = <?php echo $behavior_logs_json; ?>;
        const behaviorCard = document.getElementById('behaviorCard');
        const behaviorCardTitle = document.getElementById('behaviorCardTitle');
        const behaviorCardBody = document.getElementById('behaviorCardBody');
        const closeBehaviorCard = document.getElementById('closeBehaviorCard');

        document.querySelectorAll('.viewBehaviorBtn').forEach(btn => {
            btn.addEventListener('click', () => {
                const driverId = btn.dataset.driverId;
                const driverName = btn.dataset.driverName;
                const driverLogs = behaviorLogs[driverId] || [];

                behaviorCardTitle.textContent = `Behavior Logs for ${driverName}`;
                let tableHtml = `
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Overspeeding</th>
                                <th>Harsh Braking</th>
                                <th>Idle Time (Mins)</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                `;

                if (driverLogs.length > 0) {
                    driverLogs.forEach(log => {
                        tableHtml += `
                            <tr>
                                <td>${log.log_date}</td>
                                <td>${log.overspeeding_count} incidents</td>
                                <td>${log.harsh_braking_count} incidents</td>
                                <td>${log.idle_duration_minutes}</td>
                                <td>${log.notes || ''}</td>
                            </tr>
                        `;
                    });
                } else {
                    tableHtml += '<tr><td colspan="5">No behavior logs found for this driver.</td></tr>';
                }

                tableHtml += '</tbody></table>';
                behaviorCardBody.innerHTML = tableHtml;
                behaviorCard.style.display = 'block';
                behaviorCard.scrollIntoView({ behavior: 'smooth', block: 'start' });
            });
        });

        if (closeBehaviorCard) {
            closeBehaviorCard.addEventListener('click', () => {
                behaviorCard.style.display = 'none';
            });
        }
    });
</script>
</body>
</html>
