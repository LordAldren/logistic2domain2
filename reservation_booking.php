<?php
session_start();
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}
if (!in_array($_SESSION['role'], ['admin', 'staff'])) {
    header("location: " . ($_SESSION['role'] === 'driver' ? 'mobile_app.php' : 'landpage.php'));
    exit;
}

require_once 'db_connect.php';
$message = '';
$current_user_id = $_SESSION['id'];

// --- FORM & ACTION HANDLING --- (Logic remains the same)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['save_reservation'])) {
        $client_name = $_POST['client_name']; $vehicle_id = !empty($_POST['vehicle_id']) ? $_POST['vehicle_id'] : NULL; $reservation_date = $_POST['reservation_date']; $purpose = $_POST['purpose']; $load_capacity_needed = !empty($_POST['load_capacity_needed']) ? $_POST['load_capacity_needed'] : NULL; $destination_address = $_POST['destination_address']; $status = 'Pending'; $reservation_code = 'R' . date('YmdHis');
        $sql = "INSERT INTO reservations (reservation_code, client_name, reserved_by_user_id, vehicle_id, reservation_date, purpose, status, load_capacity_needed, destination_address) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql); $stmt->bind_param("ssiisssis", $reservation_code, $client_name, $current_user_id, $vehicle_id, $reservation_date, $purpose, $status, $load_capacity_needed, $destination_address);
        if ($stmt->execute()) { $message = "<div class='message-banner success'>Reservation saved successfully!</div>"; } else { $message = "<div class='message-banner error'>Error saving reservation: " . $conn->error . "</div>"; }
        $stmt->close();
    } elseif (isset($_POST['schedule_trip'])) {
        $reservation_id = $_POST['reservation_id_for_trip']; $driver_id = $_POST['driver_id']; $pickup_time = $_POST['pickup_time'];
        $stmt = $conn->prepare("SELECT client_name, vehicle_id, destination_address FROM reservations WHERE id = ?"); $stmt->bind_param("i", $reservation_id); $stmt->execute(); $res_result = $stmt->get_result();
        if ($res = $res_result->fetch_assoc()) {
            $trip_code = 'T' . date('YmdHis'); $status = 'Scheduled';
            $insert_trip_stmt = $conn->prepare("INSERT INTO trips (trip_code, reservation_id, vehicle_id, driver_id, client_name, destination, pickup_time, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $insert_trip_stmt->bind_param("siiissss", $trip_code, $reservation_id, $res['vehicle_id'], $driver_id, $res['client_name'], $res['destination_address'], $pickup_time, $status);
            if ($insert_trip_stmt->execute()) { $message = "<div class='message-banner success'>Trip scheduled successfully! You can manage it in the <a href='dispatch_control.php' class='text-white font-bold underline'>Dispatch & Trips</a> page.</div>"; } else { $message = "<div class='message-banner error'>Error scheduling trip: " . $conn->error . "</div>"; }
            $insert_trip_stmt->close();
        } else { $message = "<div class='message-banner error'>Could not find reservation details to create a trip.</div>"; }
        $stmt->close();
    } elseif (isset($_POST['update_status'])) {
        $id = $_POST['reservation_id']; $new_status = $_POST['new_status'];
        if ($new_status === 'Confirmed' || $new_status === 'Rejected') {
            $stmt = $conn->prepare("UPDATE reservations SET status = ? WHERE id = ?"); $stmt->bind_param("si", $new_status, $id);
            if ($stmt->execute()) { $message = "<div class='message-banner success'>Reservation status updated to $new_status.</div>"; } else { $message = "<div class='message-banner error'>Error updating status.</div>"; }
            $stmt->close();
        }
    }
}
// --- CSV REPORT GENERATION --- (Logic remains the same)
if (isset($_GET['download_csv'])) {
    $where_clauses = []; $params = []; $types = '';
    $search_query = isset($_GET['search']) ? trim($_GET['search']) : ''; if (!empty($search_query)) { $where_clauses[] = "(r.reservation_code LIKE ? OR r.client_name LIKE ?)"; $search_term = "%{$search_query}%"; array_push($params, $search_term, $search_term); $types .= 'ss'; }
    $start_date = isset($_GET['start_date']) ? $_GET['start_date'] : ''; if (!empty($start_date)) { $where_clauses[] = "r.reservation_date >= ?"; $params[] = $start_date; $types .= 's'; }
    $end_date = isset($_GET['end_date']) ? $_GET['end_date'] : ''; if (!empty($end_date)) { $where_clauses[] = "r.reservation_date <= ?"; $params[] = $end_date; $types .= 's'; }
    $status_filter = isset($_GET['status']) ? $_GET['status'] : ''; if (!empty($status_filter)) { $where_clauses[] = "r.status = ?"; $params[] = $status_filter; $types .= 's'; }
    $report_sql = "SELECT r.*, v.type as vehicle_type, v.model as vehicle_model, u.username as reserved_by FROM reservations r LEFT JOIN vehicles v ON r.vehicle_id = v.id LEFT JOIN users u ON r.reserved_by_user_id = u.id";
    if (!empty($where_clauses)) { $report_sql .= " WHERE " . implode(" AND ", $where_clauses); }
    $report_sql .= " ORDER BY r.reservation_date DESC";
    $stmt = $conn->prepare($report_sql);
    if (!empty($params)) { $stmt->bind_param($types, ...$params); }
    $stmt->execute();
    $result = $stmt->get_result();
    header('Content-Type: text/csv'); header('Content-Disposition: attachment; filename="reservations_report_'.date('Y-m-d').'.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Reservation Code', 'Client', 'Reserved By', 'Vehicle', 'Date', 'Address', 'Capacity (KG)', 'Purpose/Notes', 'Status']);
    while ($row = $result->fetch_assoc()) { fputcsv($output, [$row['reservation_code'], $row['client_name'], $row['reserved_by'], ($row['vehicle_type'] ?? 'N/A') . ' ' . ($row['vehicle_model'] ?? ''), $row['reservation_date'], $row['destination_address'], $row['load_capacity_needed'], $row['purpose'], $row['status']]); }
    fclose($output);
    exit;
}
// --- DATA FETCHING --- (Logic remains the same)
$where_clauses = []; $params = []; $types = '';
$search_query = isset($_GET['search']) ? trim($_GET['search']) : ''; if (!empty($search_query)) { $where_clauses[] = "(r.reservation_code LIKE ? OR r.client_name LIKE ?)"; $search_term = "%{$search_query}%"; array_push($params, $search_term, $search_term); $types .= 'ss'; }
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : ''; if (!empty($start_date)) { $where_clauses[] = "r.reservation_date >= ?"; $params[] = $start_date; $types .= 's'; }
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : ''; if (!empty($end_date)) { $where_clauses[] = "r.reservation_date <= ?"; $params[] = $end_date; $types .= 's'; }
$status_filter = isset($_GET['status']) ? $_GET['status'] : ''; if (!empty($status_filter)) { $where_clauses[] = "r.status = ?"; $params[] = $status_filter; $types .= 's'; }
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
  <title>Reservation Booking | SLATE Logistics</title>
  <link rel="stylesheet" href="style.css">
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://unpkg.com/lucide@latest"></script>
  <style>
    .dark .card, .dark .modal-content { background-color: #1f2937; border-color: #374151; }
    .dark table, .dark p, .dark label, .dark h2, .dark strong { color: #d1d5db; } .dark th { color: #9ca3af; }
    .dark td { border-bottom-color: #374151; }
    .dark .form-input { background-color: #374151; border-color: #4b5563; color: #d1d5db; }
  </style>
</head>
<body class="bg-gray-100 dark:bg-gray-900">
  <?php include 'sidebar.php'; ?>
  <main id="main-content" class="ml-64 transition-all duration-300 ease-in-out">
    <div class="p-6">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-3xl font-bold text-gray-800 dark:text-gray-200">Reservation Booking</h1>
            <div class="theme-toggle-container flex items-center gap-2">
                <span class="text-sm font-medium text-gray-600 dark:text-gray-400">Dark Mode</span>
                <label class="relative inline-flex items-center cursor-pointer"><input type="checkbox" id="themeToggle" class="sr-only peer"><div class="w-11 h-6 bg-gray-200 rounded-full peer peer-focus:ring-4 peer-focus:ring-blue-300 dark:peer-focus:ring-blue-800 dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-blue-600"></div></label>
            </div>
        </div>
        <?php if (!empty($message)) { echo "<div class='mb-4'>" . $message . "</div>"; } ?>
        <div class="card bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6">
            <form action="reservation_booking.php" method="GET" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
                <div><label for="search" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Search</label><input type="text" name="search" id="search" class="mt-1 form-input w-full" placeholder="Code or Client" value="<?php echo htmlspecialchars($search_query); ?>"></div>
                <div><label for="start_date" class="block text-sm font-medium">Start Date</label><input type="date" name="start_date" id="start_date" class="mt-1 form-input w-full" value="<?php echo htmlspecialchars($start_date); ?>"></div>
                <div><label for="end_date" class="block text-sm font-medium">End Date</label><input type="date" name="end_date" id="end_date" class="mt-1 form-input w-full" value="<?php echo htmlspecialchars($end_date); ?>"></div>
                <div><label for="status" class="block text-sm font-medium">Status</label><select name="status" id="status" class="mt-1 form-input w-full"><option value="">All</option><option value="Pending" <?php if($status_filter == 'Pending') echo 'selected'; ?>>Pending</option><option value="Confirmed" <?php if($status_filter == 'Confirmed') echo 'selected'; ?>>Confirmed</option><option value="Rejected" <?php if($status_filter == 'Rejected') echo 'selected'; ?>>Rejected</option><option value="Cancelled" <?php if($status_filter == 'Cancelled') echo 'selected'; ?>>Cancelled</option></select></div>
                <div class="flex items-end gap-2"><button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">Filter</button><a href="reservation_booking.php" class="bg-gray-300 dark:bg-gray-600 text-gray-800 dark:text-gray-200 px-4 py-2 rounded-lg hover:bg-gray-400">Reset</a></div>
            </form>
            <div class="flex items-center gap-2 mb-4"><button id="createReservationBtn" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 flex items-center gap-2"><i data-lucide="plus-circle" class="w-4 h-4"></i>Create Reservation</button><a href="reservation_booking.php?download_csv=true&<?php echo http_build_query($_GET); ?>" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 flex items-center gap-2"><i data-lucide="download" class="w-4 h-4"></i>Download Report</a></div>
            <div class="overflow-x-auto"><table class="w-full text-left text-sm">
                <thead class="bg-gray-50 dark:bg-gray-700 text-xs uppercase"><tr><th class="p-4">Code</th><th class="p-4">Client</th><th class="p-4">Reserved By</th><th class="p-4">Vehicle</th><th class="p-4">Date</th><th class="p-4">Status</th><th class="p-4">Actions</th></tr></thead>
                <tbody>
                    <?php if ($reservations->num_rows > 0): mysqli_data_seek($reservations, 0); while($row = $reservations->fetch_assoc()): ?>
                    <tr class="border-b dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
                        <td class="p-4"><?php echo htmlspecialchars($row['reservation_code']); ?></td><td class="p-4"><?php echo htmlspecialchars($row['client_name']); ?></td><td class="p-4"><?php echo htmlspecialchars($row['reserved_by'] ?? 'N/A'); ?></td><td class="p-4"><?php echo htmlspecialchars(($row['vehicle_type'] ?? 'Not Assigned') . ' ' . ($row['vehicle_model'] ?? '')); ?></td><td class="p-4"><?php echo htmlspecialchars($row['reservation_date']); ?></td><td class="p-4"><span class="status-badge status-<?php echo strtolower($row['status']); ?>"><?php echo htmlspecialchars($row['status']); ?></span></td>
                        <td class="p-4 flex gap-2">
                            <button class="viewReservationBtn text-blue-500 hover:underline text-xs" data-details='<?php echo htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8'); ?>'>View</button>
                            <?php if ($row['status'] == 'Pending'): ?>
                            <form action="reservation_booking.php" method="POST" class="inline"><input type="hidden" name="reservation_id" value="<?php echo $row['id']; ?>"><input type="hidden" name="new_status" value="Confirmed"><button type="submit" name="update_status" class="text-green-500 hover:underline text-xs">Accept</button></form>
                            <form action="reservation_booking.php" method="POST" class="inline"><input type="hidden" name="reservation_id" value="<?php echo $row['id']; ?>"><input type="hidden" name="new_status" value="Rejected"><button type="submit" name="update_status" class="text-red-500 hover:underline text-xs">Reject</button></form>
                            <?php endif; ?>
                            <?php if ($row['status'] == 'Confirmed' && !empty($row['vehicle_id'])): ?><button class="scheduleTripBtn text-purple-500 hover:underline text-xs" data-reservation-id="<?php echo $row['id']; ?>">Schedule Trip</button><?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; else: ?><tr><td colspan="7" class="text-center p-6">No reservations found.</td></tr><?php endif; ?>
                </tbody>
            </table></div>
        </div>
    </div>
  </main>
  <!-- Modals -->
  <div id="viewReservationModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden items-center justify-center p-4"><div class="modal-content bg-white rounded-lg shadow-xl w-full max-w-lg p-6 relative"><button class="close-button absolute top-4 right-4"><i data-lucide="x" class="w-6 h-6"></i></button><h2 class="text-2xl font-bold mb-4">Reservation Details</h2><div id="viewReservationBody" class="space-y-2 text-sm"></div></div></div>
  <div id="reservationModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden items-center justify-center p-4"><div class="modal-content bg-white rounded-lg shadow-xl w-full max-w-3xl p-6 relative"><button class="close-button absolute top-4 right-4"><i data-lucide="x" class="w-6 h-6"></i></button><h2 id="reservationModalTitle" class="text-2xl font-bold mb-4">Create Reservation</h2><div id="reservationModalBody"></div></div></div>
  <div id="scheduleTripModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden items-center justify-center p-4"><div class="modal-content bg-white rounded-lg shadow-xl w-full max-w-md p-6 relative"><button class="close-button absolute top-4 right-4"><i data-lucide="x" class="w-6 h-6"></i></button><h2 class="text-2xl font-bold mb-4">Schedule Trip</h2><form action="reservation_booking.php" method="POST"><input type="hidden" name="reservation_id_for_trip" id="reservation_id_for_trip"><div class="space-y-4"><div class="form-group"><label>Driver</label><select name="driver_id" class="form-input w-full" required><option value="">-- Choose --</option><?php mysqli_data_seek($available_drivers, 0); while($d = $available_drivers->fetch_assoc()) { echo "<option value='{$d['id']}'>" . htmlspecialchars($d['name']) . "</option>"; } ?></select></div><div class="form-group"><label>Pickup Time</label><input type="datetime-local" name="pickup_time" class="form-input w-full" required></div></div><div class="flex justify-end gap-2 mt-6"><button type="button" class="btn btn-secondary cancelBtn">Cancel</button><button type="submit" name="schedule_trip" class="btn btn-primary">Schedule</button></div></form></div></div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Modal Handling
    const modals = document.querySelectorAll('.modal');
    modals.forEach(modal => {
        modal.querySelector('.close-button')?.addEventListener('click', () => modal.classList.add('hidden'));
        modal.querySelector('.cancelBtn')?.addEventListener('click', () => modal.classList.add('hidden'));
    });
    // View Modal
    document.querySelectorAll('.viewReservationBtn').forEach(button => {
        button.addEventListener('click', () => {
            const details = JSON.parse(button.dataset.details);
            const modal = document.getElementById('viewReservationModal');
            modal.querySelector("#viewReservationBody").innerHTML = `<div class="grid grid-cols-2 gap-x-4 gap-y-1"><p><strong>Code:</strong></p><p>${details.reservation_code}</p><p><strong>Client:</strong></p><p>${details.client_name}</p><p><strong>Reserved By:</strong></p><p>${details.reserved_by || 'N/A'}</p><p><strong>Date:</strong></p><p>${details.reservation_date}</p><p><strong>Vehicle:</strong></p><p>${details.vehicle_type || 'Not Assigned'} ${details.vehicle_model || ''}</p><p><strong>Destination:</strong></p><p>${details.destination_address || 'N/A'}</p><p><strong>Capacity Needed:</strong></p><p>${details.load_capacity_needed ? details.load_capacity_needed + ' kg' : 'N/A'}</p><p><strong>Purpose:</strong></p><p>${details.purpose || 'N/A'}</p><p><strong>Status:</strong></p><p><span class="status-badge status-${details.status.toLowerCase()}">${details.status}</span></p></div>`;
            modal.classList.remove('hidden');
        });
    });
    // Schedule Trip Modal
    document.querySelectorAll('.scheduleTripBtn').forEach(button => {
        button.addEventListener('click', () => {
            const modal = document.getElementById('scheduleTripModal');
            modal.querySelector('#reservation_id_for_trip').value = button.dataset.reservationId;
            modal.classList.remove('hidden');
        });
    });
    // Create/Edit Modal
    document.getElementById("createReservationBtn").addEventListener('click', () => showReservationModal());
    function showReservationModal() {
        const modal = document.getElementById("reservationModal");
        modal.querySelector("#reservationModalBody").innerHTML = `<form action='reservation_booking.php' method='POST'><div class="grid grid-cols-1 md:grid-cols-2 gap-4"><div class='form-group'><label>Client</label><input type='text' name='client_name' class='form-input w-full' required></div><div class='form-group'><label>Date</label><input type='date' name='reservation_date' class='form-input w-full' required></div><div class='form-group'><label>Vehicle</label><select name='vehicle_id' class='form-input w-full'><option value="">-- Optional --</option><?php mysqli_data_seek($available_vehicles_for_form, 0); while($v = $available_vehicles_for_form->fetch_assoc()) { echo "<option value='{$v['id']}'>" . htmlspecialchars($v['type'] . ' - ' . $v['model']) . "</option>"; } ?></select></div><div class='form-group'><label>Load Capacity (KG)</label><input type='number' name='load_capacity_needed' class='form-input w-full'></div><div class="md:col-span-2 form-group"><label>Destination</label><input type="text" name="destination_address" class="form-input w-full" required></div><div class="md:col-span-2 form-group"><label>Purpose/Notes</label><textarea name='purpose' class='form-input w-full' rows="3"></textarea></div></div><div class='flex justify-end gap-2 mt-6'><button type='button' class='btn btn-secondary cancelBtn'>Cancel</button><button type='submit' name='save_reservation' class='btn btn-primary'>Save</button></div></form>`;
        modal.classList.remove('hidden');
        modal.querySelector('.cancelBtn').addEventListener('click', () => modal.classList.add('hidden')); // Re-attach listener
    }
    lucide.createIcons();
    // Dark Mode Handler Logic
    const themeToggle = document.getElementById('themeToggle');
    if (localStorage.getItem('theme') === 'dark'||(!('theme' in localStorage)&&window.matchMedia('(prefers-color-scheme: dark)').matches)){document.documentElement.classList.add('dark');if(themeToggle)themeToggle.checked=true;}else{document.documentElement.classList.remove('dark');if(themeToggle)themeToggle.checked=false;}
    if(themeToggle){themeToggle.addEventListener('change',function(){if(this.checked){document.documentElement.classList.add('dark');localStorage.setItem('theme','dark');}else{document.documentElement.classList.remove('dark');localStorage.setItem('theme','light');}});}
});
</script>
</body>
</html>
