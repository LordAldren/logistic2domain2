<?php
session_start();
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}

// RBAC check - Admin at Staff lang ang pwedeng pumasok dito
if (!in_array($_SESSION['role'], ['admin', 'staff'])) {
    if ($_SESSION['role'] === 'driver') {
        header("location: mobile_app.php");
    } else {
        header("location: landpage.php");
    }
    exit;
}

require_once 'db_connect.php';
$message = '';
$current_user_id = $_SESSION['id'];

// --- FORM & ACTION HANDLING ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Handle Create Reservation
    if (isset($_POST['save_reservation'])) {
        $client_name = $_POST['client_name'];
        $vehicle_id = !empty($_POST['vehicle_id']) ? $_POST['vehicle_id'] : NULL;
        $reservation_date = $_POST['reservation_date'];
        $purpose = $_POST['purpose'];
        $load_capacity_needed = !empty($_POST['load_capacity_needed']) ? $_POST['load_capacity_needed'] : NULL;
        $destination_address = $_POST['destination_address'];
        
        $status = 'Pending';
        $reservation_code = 'R' . date('YmdHis');
        $sql = "INSERT INTO reservations (reservation_code, client_name, reserved_by_user_id, vehicle_id, reservation_date, purpose, status, load_capacity_needed, destination_address) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssiisssis", $reservation_code, $client_name, $current_user_id, $vehicle_id, $reservation_date, $purpose, $status, $load_capacity_needed, $destination_address);
        if ($stmt->execute()) { 
            $message = "<div class='message-banner success'>Reservation saved successfully!</div>"; 
        } else { 
            $message = "<div class='message-banner error'>Error saving reservation: " . $conn->error . "</div>"; 
        }
        $stmt->close();
    }
    // Handle Schedule Trip from Reservation
    elseif (isset($_POST['schedule_trip'])) {
        $reservation_id = $_POST['reservation_id_for_trip'];
        $driver_id = $_POST['driver_id'];
        $pickup_time = $_POST['pickup_time'];
        
        $stmt = $conn->prepare("SELECT client_name, vehicle_id, destination_address FROM reservations WHERE id = ?");
        $stmt->bind_param("i", $reservation_id);
        $stmt->execute();
        $res_result = $stmt->get_result();
        
        if ($res = $res_result->fetch_assoc()) {
            $trip_code = 'T' . date('YmdHis');
            $status = 'Scheduled';
            
            $insert_trip_stmt = $conn->prepare("INSERT INTO trips (trip_code, reservation_id, vehicle_id, driver_id, client_name, destination, pickup_time, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $insert_trip_stmt->bind_param("siiissss", $trip_code, $reservation_id, $res['vehicle_id'], $driver_id, $res['client_name'], $res['destination_address'], $pickup_time, $status);
            
            if ($insert_trip_stmt->execute()) {
                $message = "<div class='message-banner success'>Trip scheduled successfully! You can manage it in the <a href='dispatch_control.php' style='color:white; font-weight:bold;'>Dispatch & Trips</a> page.</div>";
            } else {
                $message = "<div class='message-banner error'>Error scheduling trip: " . $conn->error . "</div>";
            }
            $insert_trip_stmt->close();
        } else {
            $message = "<div class='message-banner error'>Could not find reservation details to create a trip.</div>";
        }
        $stmt->close();
    }
    // Handle Accept/Reject Reservation
    elseif (isset($_POST['update_status'])) {
        $id = $_POST['reservation_id'];
        $new_status = $_POST['new_status'];
        if ($new_status === 'Confirmed' || $new_status === 'Rejected') {
            $stmt = $conn->prepare("UPDATE reservations SET status = ? WHERE id = ?");
            $stmt->bind_param("si", $new_status, $id);
            if ($stmt->execute()) { $message = "<div class='message-banner success'>Reservation status updated to $new_status.</div>"; } else { $message = "<div class='message-banner error'>Error updating status.</div>"; }
            $stmt->close();
        }
    }
}


// --- CSV REPORT GENERATION ---
if (isset($_GET['download_csv'])) {
    $where_clauses = []; $params = []; $types = '';
    $search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
    if (!empty($search_query)) { $where_clauses[] = "(r.reservation_code LIKE ? OR r.client_name LIKE ?)"; $search_term = "%{$search_query}%"; array_push($params, $search_term, $search_term); $types .= 'ss'; }
    $start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
    if (!empty($start_date)) { $where_clauses[] = "r.reservation_date >= ?"; $params[] = $start_date; $types .= 's'; }
    $end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';
    if (!empty($end_date)) { $where_clauses[] = "r.reservation_date <= ?"; $params[] = $end_date; $types .= 's'; }
    $status_filter = isset($_GET['status']) ? $_GET['status'] : '';
    if (!empty($status_filter)) { $where_clauses[] = "r.status = ?"; $params[] = $status_filter; $types .= 's'; }
    $report_sql = "SELECT r.*, v.type as vehicle_type, v.model as vehicle_model, u.username as reserved_by FROM reservations r LEFT JOIN vehicles v ON r.vehicle_id = v.id LEFT JOIN users u ON r.reserved_by_user_id = u.id";
    if (!empty($where_clauses)) { $report_sql .= " WHERE " . implode(" AND ", $where_clauses); }
    $report_sql .= " ORDER BY r.reservation_date DESC";
    $stmt = $conn->prepare($report_sql);
    if (!empty($params)) { $stmt->bind_param($types, ...$params); }
    $stmt->execute();
    $result = $stmt->get_result();
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="reservations_report_'.date('Y-m-d').'.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Reservation Code', 'Client', 'Reserved By', 'Vehicle', 'Date', 'Address', 'Capacity (KG)', 'Purpose/Notes', 'Status']);
    while ($row = $result->fetch_assoc()) { fputcsv($output, [$row['reservation_code'], $row['client_name'], $row['reserved_by'], ($row['vehicle_type'] ?? 'N/A') . ' ' . ($row['vehicle_model'] ?? ''), $row['reservation_date'], $row['destination_address'], $row['load_capacity_needed'], $row['purpose'], $row['status']]); }
    fclose($output);
    exit;
}


// --- DATA FETCHING ---
$where_clauses = []; $params = []; $types = '';
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
if (!empty($search_query)) { $where_clauses[] = "(r.reservation_code LIKE ? OR r.client_name LIKE ?)"; $search_term = "%{$search_query}%"; array_push($params, $search_term, $search_term); $types .= 'ss'; }
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
if (!empty($start_date)) { $where_clauses[] = "r.reservation_date >= ?"; $params[] = $start_date; $types .= 's'; }
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';
if (!empty($end_date)) { $where_clauses[] = "r.reservation_date <= ?"; $params[] = $end_date; $types .= 's'; }
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
if (!empty($status_filter)) { $where_clauses[] = "r.status = ?"; $params[] = $status_filter; $types .= 's'; }
$reservations_sql = "SELECT r.*, v.type as vehicle_type, v.model as vehicle_model, u.username as reserved_by FROM reservations r LEFT JOIN vehicles v ON r.vehicle_id = v.id LEFT JOIN users u ON r.reserved_by_user_id = u.id";
if (!empty($where_clauses)) { $reservations_sql .= " WHERE " . implode(" AND ", $where_clauses); }
$reservations_sql .= " ORDER BY r.reservation_date DESC";
$stmt = $conn->prepare($reservations_sql);
if (!empty($params)) { $stmt->bind_param($types, ...$params); }
$stmt->execute();
$reservations = $stmt->get_result();
$available_vehicles_for_form = $conn->query("SELECT id, type, model FROM vehicles WHERE status IN ('Active', 'Idle')");
$available_drivers = $conn->query("SELECT id, name FROM drivers WHERE status = 'Active'");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Reservation Booking | VRDS</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <?php include 'sidebar.php'; ?>

  <div class="content" id="mainContent">
    <div class="header">
        <div class="hamburger" id="hamburger">☰</div>
        <div><h1>Reservation Booking Management</h1></div>
        <div class="theme-toggle-container">
            <span class="theme-label">Dark Mode</span>
            <label class="theme-switch"><input type="checkbox" id="themeToggle"><span class="slider"></span></label>
        </div>
    </div>
    
    <?php echo $message; ?>

    <div class="table-section">
      <h3>Reservation Booking</h3>
      <div class="card" style="margin-bottom: 1.5rem; padding: 1.5rem;">
        <form action="reservation_booking.php" method="GET" class="filter-form">
            <div class="form-group"><label>Search</label><input type="text" name="search" class="form-control" placeholder="Code or Client" value="<?php echo htmlspecialchars($search_query); ?>"></div>
            <div class="form-group"><label>Start Date</label><input type="date" name="start_date" class="form-control" value="<?php echo htmlspecialchars($start_date); ?>"></div>
            <div class="form-group"><label>End Date</label><input type="date" name="end_date" class="form-control" value="<?php echo htmlspecialchars($end_date); ?>"></div>
            <div class="form-group"><label>Status</label>
                <select name="status" class="form-control">
                    <option value="">All</option>
                    <option value="Pending" <?php if($status_filter == 'Pending') echo 'selected'; ?>>Pending</option>
                    <option value="Confirmed" <?php if($status_filter == 'Confirmed') echo 'selected'; ?>>Confirmed</option>
                    <option value="Rejected" <?php if($status_filter == 'Rejected') echo 'selected'; ?>>Rejected</option>
                    <option value="Cancelled" <?php if($status_filter == 'Cancelled') echo 'selected'; ?>>Cancelled</option>
                </select>
            </div>
            <div class="form-actions" style="grid-column: 1 / -1;"><button type="submit" class="btn btn-primary">Filter</button><a href="reservation_booking.php" class="btn btn-secondary">Reset</a></div>
        </form>
      </div>
      <div style="margin-bottom: 1rem; display: flex; gap: 0.5rem;">
        <button id="createReservationBtn" class="btn btn-primary">Create Reservation</button>
        <a href="reservation_booking.php?download_csv=true&<?php echo http_build_query($_GET); ?>" class="btn btn-success">Download Report (CSV)</a>
      </div>

      <!-- Start: Modals converted to inline blocks -->
      <div id="viewReservationModal" class="modal"><div class="modal-content"><span class="close-button">&times;</span><h2>Reservation Details</h2><div id="viewReservationBody"></div></div></div>
      <div id="reservationModal" class="modal"><div class="modal-content" style="max-width: 800px;"><span class="close-button">&times;</span><h2 id="reservationModalTitle">Create Reservation</h2><div id="reservationModalBody"></div></div></div>
      <div id="scheduleTripModal" class="modal"><div class="modal-content"><span class="close-button">&times;</span><h2>Schedule Trip from Reservation</h2><form action="reservation_booking.php" method="POST"><input type="hidden" name="reservation_id_for_trip" id="reservation_id_for_trip"><div class="form-group"><label>Driver</label><select name="driver_id" class="form-control" required><option value="">-- Choose a Driver --</option><?php mysqli_data_seek($available_drivers, 0); while($d = $available_drivers->fetch_assoc()) { echo "<option value='{$d['id']}'>" . htmlspecialchars($d['name']) . "</option>"; } ?></select></div><div class="form-group"><label>Pickup Time</label><input type="datetime-local" name="pickup_time" class="form-control" required></div><div class="form-actions"><button type="button" class="btn btn-secondary cancelBtn">Cancel</button><button type="submit" name="schedule_trip" class="btn btn-primary">Schedule Trip</button></div></form></div></div>
      <!-- End: Modals converted to inline blocks -->

      <table>
        <thead><tr><th>Code</th><th>Client</th><th>Reserved By</th><th>Vehicle Details</th><th>Date</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
            <?php if ($reservations->num_rows > 0): mysqli_data_seek($reservations, 0); while($row = $reservations->fetch_assoc()): ?>
            <tr>
                <td><?php echo htmlspecialchars($row['reservation_code']); ?></td><td><?php echo htmlspecialchars($row['client_name']); ?></td><td><?php echo htmlspecialchars($row['reserved_by'] ?? 'N/A'); ?></td><td><?php echo htmlspecialchars(($row['vehicle_type'] ?? 'Not Assigned') . ' ' . ($row['vehicle_model'] ?? '')); ?></td><td><?php echo htmlspecialchars($row['reservation_date']); ?></td><td><span class="status-badge status-<?php echo strtolower($row['status']); ?>"><?php echo htmlspecialchars($row['status']); ?></span></td>
                <td class="action-buttons">
                    <button class="btn btn-info btn-sm viewReservationBtn" data-details='<?php echo htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8'); ?>'>View</button>
                    <?php if ($row['status'] == 'Pending'): ?>
                    <form action="reservation_booking.php" method="POST" style="display: inline;">
                        <input type="hidden" name="reservation_id" value="<?php echo $row['id']; ?>">
                        <input type="hidden" name="new_status" value="Confirmed">
                        <button type="submit" name="update_status" class="btn btn-success btn-sm">Accept</button>
                    </form>
                    <form action="reservation_booking.php" method="POST" style="display: inline;">
                        <input type="hidden" name="reservation_id" value="<?php echo $row['id']; ?>">
                        <input type="hidden" name="new_status" value="Rejected">
                        <button type="submit" name="update_status" class="btn btn-danger btn-sm">Reject</button>
                    </form>
                    <?php endif; ?>
                    <?php if ($row['status'] == 'Confirmed' && !empty($row['vehicle_id'])): ?>
                        <button class="btn btn-primary btn-sm scheduleTripBtn" data-reservation-id="<?php echo $row['id']; ?>">Schedule Trip</button>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endwhile; else: ?><tr><td colspan="7">No reservations found.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('hamburger').addEventListener('click', function() {
      const sidebar = document.getElementById('sidebar'); const mainContent = document.getElementById('mainContent');
      if (window.innerWidth <= 992) { sidebar.classList.toggle('show'); } else { sidebar.classList.toggle('collapsed'); mainContent.classList.toggle('expanded'); }
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

    const viewModal = document.getElementById("viewReservationModal");
    const reservationModal = document.getElementById("reservationModal");
    const scheduleTripModal = document.getElementById("scheduleTripModal");

    document.querySelectorAll('.modal').forEach(modal => {
        const closeBtn = modal.querySelector('.close-button');
        if (closeBtn) closeBtn.onclick = () => modal.style.display = 'none';
        const cancelBtn = modal.querySelector('.cancelBtn');
        if (cancelBtn) cancelBtn.onclick = () => modal.style.display = 'none';
    });

    // View Modal Logic
    document.querySelectorAll('.viewReservationBtn').forEach(button => {
        button.addEventListener('click', () => {
            const details = JSON.parse(button.dataset.details);
            viewModal.querySelector("#viewReservationBody").innerHTML = `
                <p><strong>Code:</strong> ${details.reservation_code}</p><p><strong>Client:</strong> ${details.client_name}</p>
                <p><strong>Reserved By:</strong> ${details.reserved_by || 'N/A'}</p><p><strong>Date:</strong> ${details.reservation_date}</p>
                <p><strong>Vehicle:</strong> ${details.vehicle_type || 'Not Assigned'} ${details.vehicle_model || ''}</p>
                <p><strong>Destination Address:</strong> ${details.destination_address || 'Not specified'}</p>
                <p><strong>Load Capacity Needed:</strong> ${details.load_capacity_needed ? details.load_capacity_needed + ' kg' : 'Not specified'}</p>
                <p><strong>Purpose / Notes:</strong><br>${(details.purpose || 'Not specified').replace(/\n/g, '<br>')}</p>
                <p><strong>Status:</strong> <span class="status-badge status-${details.status.toLowerCase()}">${details.status}</span></p>
            `;
            viewModal.style.display = 'block';
        });
    });

    // Create Reservation Modal Logic
    document.getElementById("createReservationBtn").addEventListener('click', () => showReservationModal());
    
    function showReservationModal(data = {}) {
        reservationModal.querySelector("#reservationModalTitle").innerHTML = "Create New Reservation";
        reservationModal.querySelector("#reservationModalBody").innerHTML = `
            <form action='reservation_booking.php' method='POST'>
                <input type='hidden' name='reservation_id' value="">
                 <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class='form-group'><label>Client</label><input type='text' name='client_name' class='form-control' required></div>
                    <div class='form-group'><label>Date</label><input type='date' name='reservation_date' class='form-control' required></div>
                    <div class='form-group'><label>Vehicle</label><select name='vehicle_id' class='form-control'><option value="">-- Optional: Assign Later --</option><?php mysqli_data_seek($available_vehicles_for_form, 0); while($v = $available_vehicles_for_form->fetch_assoc()) { echo "<option value='{$v['id']}'>" . htmlspecialchars($v['type'] . ' - ' . $v['model']) . "</option>"; } ?></select></div>
                    <div class='form-group'><label>Load Capacity Needed (KG)</label><input type='number' name='load_capacity_needed' class='form-control' placeholder="e.g., 5000"></div>
                </div>
                <hr style="margin: 1.5rem 0; border-color: rgba(0,0,0,0.1);">
                <h4 style="margin-bottom: 1rem;">Route Planner & Address</h4>
                <div class="form-group"><label>Start Location</label><input type="text" id="startLocation" class="form-control" placeholder="e.g., FCM, Quezon City"></div>
                <div class="form-group"><label>Destination Address</label><input type="text" id="endLocation" name="destination_address" class="form-control" placeholder="e.g., SM North Edsa, Quezon City" required></div>
                <button type="button" id="findRouteBtn" class="btn btn-info">Calculate Route Details</button>
                <div id="route-output" style="display:none; margin-top:1rem;"></div>
                <hr style="margin: 1.5rem 0; border-color: rgba(0,0,0,0.1);">
                <div class='form-group'><label>Purpose / Notes</label><textarea name='purpose' id='purpose-textarea' class='form-control' rows="3"></textarea></div>
                <div class='form-actions'><button type='button' class='btn btn-secondary cancelBtn'>Cancel</button><button type='submit' name='save_reservation' class='btn btn-primary'>Save Reservation</button></div>
            </form>`;
        
        attachRoutePlannerEvents();
        reservationModal.style.display = "block";
    }

    // Schedule Trip Modal Logic
    document.querySelectorAll('.scheduleTripBtn').forEach(button => {
        button.addEventListener('click', () => {
            const reservationId = button.dataset.reservationId;
            scheduleTripModal.querySelector('#reservation_id_for_trip').value = reservationId;
            scheduleTripModal.style.display = 'block';
        });
    });

    function attachRoutePlannerEvents() {
        const findRouteBtn = reservationModal.querySelector('#findRouteBtn');
        if(!findRouteBtn) return;
        findRouteBtn.addEventListener('click', async () => {
            const start = reservationModal.querySelector('#startLocation').value;
            const end = reservationModal.querySelector('#endLocation').value;
            const routeOutput = reservationModal.querySelector('#route-output');
            const purposeTextarea = reservationModal.querySelector('#purpose-textarea');
            if (!start || !end) { alert('Please enter both Start and End locations.'); return; }
            findRouteBtn.textContent = 'Calculating...'; findRouteBtn.disabled = true;
            routeOutput.innerHTML = 'Calculating...';
            routeOutput.style.display = 'block';
            try {
                const startCoordsUrl = `route_proxy.php?service=geocode&q=${encodeURIComponent(start)}`;
                const endCoordsUrl = `route_proxy.php?service=geocode&q=${encodeURIComponent(end)}`;
                const [startResponse, endResponse] = await Promise.all([fetch(startCoordsUrl), fetch(endCoordsUrl)]);
                if (!startResponse.ok || !endResponse.ok) throw new Error('Geocoding failed.');
                const startData = await startResponse.json(); const endData = await endResponse.json();
                if (startData.length === 0 || endData.length === 0) throw new Error('Could not find one or both locations.');
                const startCoords = { lat: startData[0].lat, lon: startData[0].lon };
                const endCoords = { lat: endData[0].lat, lon: endData[0].lon };
                const directionsUrl = `route_proxy.php?service=route&coords=${startCoords.lon},${startCoords.lat};${endCoords.lon},${endCoords.lat}`;
                const directionsResponse = await fetch(directionsUrl);
                if (!directionsResponse.ok) throw new Error('Routing failed.');
                const directionsData = await directionsResponse.json();
                if (directionsData.code !== 'Ok' || directionsData.routes.length === 0) throw new Error('No route found.');
                const route = directionsData.routes[0];
                const distanceKm = (route.distance / 1000).toFixed(2);
                const durationSeconds = route.duration;
                const hours = Math.floor(durationSeconds / 3600);
                const minutes = Math.floor((durationSeconds % 3600) / 60);
                const durationFormatted = `${hours}h ${minutes}m`;
                
                routeOutput.innerHTML = `<div class='message-banner success' style='margin-bottom: 0;'><strong>Distance:</strong> ${distanceKm} km | <strong>Est. Duration:</strong> ${durationFormatted}</div>`;
                purposeTextarea.value = `Route Details:\n- Start: ${start}\n- End: ${end}\n- Distance: ${distanceKm} km\n- Duration: ${durationFormatted}`;
            } catch (error) {
                routeOutput.innerHTML = `<div class='message-banner error' style='margin-bottom: 0;'><b>Error:</b> ${error.message}</span>`;
            } finally {
                findRouteBtn.textContent = 'Calculate Route Details'; findRouteBtn.disabled = false;
            }
        });
    }

});
</script>
<script src="dark_mode_handler.js" defer></script>
</body>
</html>
