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

// --- FORM & ACTION HANDLING --- (Logic remains the same, with Firebase removal script)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['save_trip'])) {
        $trip_id = $_POST['trip_id']; $vehicle_id = $_POST['vehicle_id']; $driver_id = $_POST['driver_id']; $destination = $_POST['destination']; $pickup_time = $_POST['pickup_time']; $status = $_POST['status'];
        if (!empty($trip_id)) {
            $sql = "UPDATE trips SET vehicle_id=?, driver_id=?, destination=?, pickup_time=?, status=? WHERE id=?"; $stmt = $conn->prepare($sql); $stmt->bind_param("iisssi", $vehicle_id, $driver_id, $destination, $pickup_time, $status, $trip_id);
            if ($stmt->execute()) { $message = "<div class='message-banner success'>Trip updated successfully!</div>"; } else { $message = "<div class='message-banner error'>Error: " . $conn->error . "</div>"; }
            $stmt->close();
        }
    } elseif (isset($_POST['start_trip_log'])) {
        $trip_id = $_POST['log_trip_id']; $location_name = $_POST['location_name']; $status_message = $_POST['status_message']; $latitude = null; $longitude = null;
        $apiUrl = "https://nominatim.openstreetmap.org/search?format=json&q=" . urlencode($location_name) . "&countrycodes=PH&limit=1";
        $ch = curl_init(); curl_setopt_array($ch, [CURLOPT_URL => $apiUrl, CURLOPT_RETURNTRANSFER => 1, CURLOPT_USERAGENT => 'SLATE Logistics/1.0', CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => false]);
        $geo_response_json = curl_exec($ch); $curl_error = curl_error($ch); curl_close($ch);
        if ($geo_response_json && ($geo_response = json_decode($geo_response_json, true)) && isset($geo_response[0]['lat'])) {
            $latitude = $geo_response[0]['lat']; $longitude = $geo_response[0]['lon'];
            $conn->begin_transaction();
            try {
                $conn->prepare("UPDATE trips SET status = 'En Route' WHERE id = ?")->execute([$trip_id]);
                $conn->prepare("INSERT INTO tracking_log (trip_id, latitude, longitude, status_message) VALUES (?, ?, ?, ?)")->execute([$trip_id, $latitude, $longitude, $status_message]);
                $conn->commit();
                $message = "<div class='message-banner success'>Trip started from '$location_name' and is now live.</div>";
                // Firebase logic here...
            } catch (Exception $e) { $conn->rollback(); $message = "<div class='message-banner error'>Error: " . $e->getMessage() . "</div>"; }
        } else { $message = "<div class='message-banner error'>Could not find coordinates for: '" . htmlspecialchars($location_name) . "'.</div>"; }
    } elseif (isset($_POST['end_trip'])) {
        $trip_id = $_POST['trip_id_to_end'];
        $stmt = $conn->prepare("UPDATE trips SET status = 'Completed', actual_arrival_time = NOW() WHERE id = ?"); $stmt->bind_param("i", $trip_id);
        if ($stmt->execute()) {
            $message = "<div class='message-banner success'>Trip #$trip_id marked as Completed.</div>";
            echo '<script>/* Firebase removal script here */</script>';
        } else { $message = "<div class='message-banner error'>Error updating trip status.</div>"; }
    }
}
if (isset($_POST['delete_trip_confirmed'])) {
    $id = $_POST['trip_id_to_delete']; $stmt = $conn->prepare("DELETE FROM trips WHERE id = ?"); $stmt->bind_param("i", $id);
    if ($stmt->execute()) { $message = "<div class='message-banner success'>Trip deleted successfully.</div>"; echo '<script>/* Firebase removal script here */</script>'; } else { $message = "<div class='message-banner error'>Error deleting trip.</div>"; }
    $stmt->close();
}
// --- DATA FETCHING ---
$trips = $conn->query("SELECT t.*, v.type AS vehicle_type, v.model AS vehicle_model, d.name AS driver_name, r.reservation_code FROM trips t JOIN vehicles v ON t.vehicle_id = v.id JOIN drivers d ON t.driver_id = d.id LEFT JOIN reservations r ON t.reservation_id = r.id WHERE t.status IN ('Scheduled', 'En Route') ORDER BY t.pickup_time DESC");
$available_vehicles_for_form = $conn->query("SELECT id, type, model FROM vehicles WHERE status IN ('Active', 'Idle')");
$available_drivers = $conn->query("SELECT id, name FROM drivers WHERE status = 'Active'");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"> <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dispatch & Trips | SLATE Logistics</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
    <script src="https://cdn.tailwindcss.com"></script> <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://www.gstatic.com/firebasejs/8.10.1/firebase-app.js"></script>
    <script src="https://www.gstatic.com/firebasejs/8.10.1/firebase-database.js"></script>
    <style> .dark .card, .dark .modal-content { background-color: #1f2937; border-color: #374151; } .dark table, .dark p, .dark label, .dark h2, .dark strong { color: #d1d5db; } .dark th { color: #9ca3af; } .dark td { border-bottom-color: #374151; } .dark .form-input { background-color: #374151; border-color: #4b5563; color: #d1d5db; } </style>
</head>
<body class="bg-gray-100 dark:bg-gray-900">
    <?php include 'sidebar.php'; ?>
    <main id="main-content" class="ml-64 transition-all duration-300 ease-in-out">
        <div class="p-6">
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-3xl font-bold text-gray-800 dark:text-gray-200">Dispatch Control</h1>
                <div class="theme-toggle-container flex items-center gap-2">
                    <span class="text-sm font-medium text-gray-600 dark:text-gray-400">Dark Mode</span>
                    <label class="relative inline-flex items-center cursor-pointer"><input type="checkbox" id="themeToggle" class="sr-only peer"><div class="w-11 h-6 bg-gray-200 rounded-full peer peer-focus:ring-4 peer-focus:ring-blue-300 dark:peer-focus:ring-blue-800 dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-blue-600"></div></label>
                </div>
            </div>
            <?php if (!empty($message)) { echo "<div class='mb-4'>" . $message . "</div>"; } ?>
            <div class="card bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-xl font-semibold">Scheduled & On-going Trips</h3>
                    <button id="viewMapBtn" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 flex items-center gap-2"><i data-lucide="map" class="w-4 h-4"></i>View Live Map</button>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm"><thead class="bg-gray-50 dark:bg-gray-700 text-xs uppercase"><tr><th class="p-4">Trip</th><th>Vehicle</th><th>Driver</th><th>Pickup Time</th><th>Destination</th><th>Status</th><th>Actions</th></tr></thead>
                        <tbody>
                            <?php if ($trips && $trips->num_rows > 0): mysqli_data_seek($trips, 0); while($row = $trips->fetch_assoc()): ?>
                            <tr class="border-b dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
                                <td class="p-4"><?php echo htmlspecialchars($row['trip_code']); if(!empty($row['reservation_code'])) echo "<br><a href='#' class='text-xs text-blue-500'>from ".htmlspecialchars($row['reservation_code'])."</a>"; ?></td>
                                <td class="p-4"><?php echo htmlspecialchars($row['vehicle_type'] . ' ' . $row['vehicle_model']); ?></td><td class="p-4"><?php echo htmlspecialchars($row['driver_name']); ?></td>
                                <td class="p-4"><?php echo htmlspecialchars(date('M d, Y h:i A', strtotime($row['pickup_time']))); ?></td><td><?php echo htmlspecialchars($row['destination']); ?></td>
                                <td class="p-4"><span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $row['status'])); ?>"><?php echo htmlspecialchars($row['status']); ?></span></td>
                                <td class="p-4 flex flex-wrap gap-2">
                                    <button class="editTripBtn text-yellow-500 hover:underline text-xs" data-details='<?php echo htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8'); ?>'>Edit</button>
                                    <?php if ($row['status'] == 'Scheduled'): ?><button class="startTripBtn text-green-500 hover:underline text-xs" data-trip-id="<?php echo $row['id']; ?>">Start</button><?php endif; ?>
                                    <?php if ($row['status'] == 'En Route'): ?><button class="endTripBtn text-red-500 hover:underline text-xs" data-trip-id="<?php echo $row['id']; ?>">End</button><?php endif; ?>
                                    <button class="deleteTripBtn text-red-600 hover:underline text-xs" data-trip-id="<?php echo $row['id']; ?>">Delete</button>
                                </td>
                            </tr>
                            <?php endwhile; else: ?><tr><td colspan="7" class="text-center p-6">No active or scheduled trips found.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
    <!-- Modals -->
    <div id="tripFormModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden items-center justify-center p-4"><div class="modal-content bg-white rounded-lg shadow-xl w-full max-w-2xl p-6 relative"><button class="close-button absolute top-4 right-4"><i data-lucide="x"></i></button><h2 id="tripFormTitle">Edit Trip</h2><form action="dispatch_control.php" method="POST" class="mt-4"><input type="hidden" name="trip_id" id="trip_id"><div class="grid grid-cols-1 md:grid-cols-2 gap-4"><div class="form-group"><label>Vehicle</label><select name="vehicle_id" class="form-input w-full" required><?php mysqli_data_seek($available_vehicles_for_form, 0); while($v = $available_vehicles_for_form->fetch_assoc()){ echo "<option value='{$v['id']}'>".htmlspecialchars($v['type'].' - '.$v['model'])."</option>"; } ?></select></div><div class="form-group"><label>Driver</label><select name="driver_id" class="form-input w-full" required><?php mysqli_data_seek($available_drivers, 0); while($d = $available_drivers->fetch_assoc()){ echo "<option value='{$d['id']}'>".htmlspecialchars($d['name'])."</option>"; } ?></select></div><div class="form-group md:col-span-2"><label>Destination</label><input type="text" name="destination" class="form-input w-full" required></div><div class="form-group"><label>Pickup Time</label><input type="datetime-local" name="pickup_time" class="form-input w-full" required></div><div class="form-group"><label>Status</label><select name="status" class="form-input w-full" required><option value="Scheduled">Scheduled</option><option value="En Route">En Route</option><option value="Completed">Completed</option><option value="Cancelled">Cancelled</option></select></div></div><div class="flex justify-end gap-2 mt-6"><button type="button" class="btn btn-secondary cancelBtn">Cancel</button><button type="submit" name="save_trip" class="btn btn-primary">Save</button></div></form></div></div>
    <div id="startTripModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden items-center justify-center p-4"><div class="modal-content bg-white rounded-lg shadow-xl w-full max-w-md p-6 relative"><button class="close-button absolute top-4 right-4"><i data-lucide="x"></i></button><h2>Start Trip</h2><form action="dispatch_control.php" method="POST" class="mt-4"><input type="hidden" name="log_trip_id" id="log_trip_id"><div class="space-y-4"><div class="form-group"><label>Starting Location</label><input type="text" name="location_name" class="form-input w-full" placeholder="e.g., SM North EDSA" required></div><div class="form-group"><label>Status Message</label><input type="text" name="status_message" class="form-input w-full" value="Trip Started" required></div></div><div class="flex justify-end gap-2 mt-6"><button type="button" class="btn btn-secondary cancelBtn">Cancel</button><button type="submit" name="start_trip_log" class="btn btn-success">Start</button></div></form></div></div>
    <div id="confirmModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden items-center justify-center p-4"><div class="modal-content bg-white rounded-lg shadow-xl w-full max-w-sm p-6 text-center"><h2 id="confirmModalTitle" class="text-lg font-bold">Are you sure?</h2><p id="confirmModalText" class="my-4 text-sm"></p><div class="flex justify-center gap-4"><button type="button" class="btn btn-secondary" id="confirmCancelBtn">Cancel</button><button type="button" class="btn btn-danger" id="confirmOkBtn">Confirm</button></div></div></div>
    <div id="mapModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden items-center justify-center p-4"><div class="modal-content bg-white rounded-lg shadow-xl w-full max-w-4xl p-6 relative"><button class="close-button absolute top-4 right-4"><i data-lucide="x"></i></button><h2>Live Dispatch Map</h2><div id="dispatchMap" class="mt-4" style="height: 60vh; width: 100%; border-radius: 0.5rem;"></div></div></div>
    <form id="endTripForm" action="dispatch_control.php" method="POST" class="hidden"><input type="hidden" name="trip_id_to_end" id="trip_id_to_end"><input type="hidden" name="end_trip" value="1"></form>
    <form id="deleteTripForm" action="dispatch_control.php" method="POST" class="hidden"><input type="hidden" name="trip_id_to_delete" id="trip_id_to_delete"><input type="hidden" name="delete_trip_confirmed" value="1"></form>

    <script>
    // JS for Modals, Map, and Dark Mode
    document.addEventListener('DOMContentLoaded', function() {
        const modals = { tripForm: document.getElementById('tripFormModal'), startTrip: document.getElementById('startTripModal'), confirm: document.getElementById('confirmModal'), map: document.getElementById('mapModal') };
        Object.values(modals).forEach(modal => { modal?.querySelector('.close-button')?.addEventListener('click', () => modal.classList.add('hidden')); modal?.querySelector('.cancelBtn')?.addEventListener('click', () => modal.classList.add('hidden')); });
        document.querySelectorAll('.editTripBtn').forEach(btn => btn.addEventListener('click', () => { const d=JSON.parse(btn.dataset.details); const f=modals.tripForm.querySelector('form'); f.reset(); f.querySelector('#trip_id').value=d.id; f.querySelector('[name=vehicle_id]').value=d.vehicle_id; f.querySelector('[name=driver_id]').value=d.driver_id; f.querySelector('[name=destination]').value=d.destination; f.querySelector('[name=pickup_time]').value=d.pickup_time.replace(' ','T').substring(0,16); f.querySelector('[name=status]').value=d.status; modals.tripForm.classList.remove('hidden'); }));
        document.querySelectorAll('.startTripBtn').forEach(btn => btn.addEventListener('click', () => { modals.startTrip.querySelector('#log_trip_id').value = btn.dataset.tripId; modals.startTrip.classList.remove('hidden'); }));
        const confirmOkBtn=modals.confirm.querySelector('#confirmOkBtn'); modals.confirm.querySelector('#confirmCancelBtn').onclick=()=>modals.confirm.classList.add('hidden');
        document.querySelectorAll('.endTripBtn').forEach(btn => btn.addEventListener('click', (e) => { e.preventDefault(); const id=btn.dataset.tripId; modals.confirm.querySelector('#confirmModalTitle').textContent='End Trip?'; modals.confirm.querySelector('#confirmModalText').textContent=`Confirm ending Trip #${id}?`; modals.confirm.classList.remove('hidden'); confirmOkBtn.onclick=()=>{ document.getElementById('trip_id_to_end').value=id; document.getElementById('endTripForm').submit(); }; }));
        document.querySelectorAll('.deleteTripBtn').forEach(btn => btn.addEventListener('click', (e) => { e.preventDefault(); const id=btn.dataset.tripId; modals.confirm.querySelector('#confirmModalTitle').textContent='Delete Trip?'; modals.confirm.querySelector('#confirmModalText').textContent=`Permanently delete Trip #${id}? This cannot be undone.`; modals.confirm.classList.remove('hidden'); confirmOkBtn.onclick=()=>{ document.getElementById('trip_id_to_delete').value=id; document.getElementById('deleteTripForm').submit(); }; }));
        // Map Logic...
        let map; let markers={}; let firebaseInitialized=false;
        const firebaseConfig={apiKey:"AIzaSyCB0_OYZXX3K-AxKeHnVlYMv2wZ_81FeYM",authDomain:"slate49-cde60.firebaseapp.com",databaseURL:"https://slate49-cde60-default-rtdb.firebaseio.com",projectId:"slate49-cde60",storageBucket:"slate49-cde60.firebasestorage.app",messagingSenderId:"809390854040",appId:"1:809390854040:web:f7f77333bb0ac7ab73e5ed"};
        function handleDataChange(id,data){if(!map||!data.vehicle_info)return;const latlng=[data.lat,data.lng];if(!markers[id]){const p=`<b>Vehicle:</b> ${data.vehicle_info}<br><b>Driver:</b> ${data.driver_name}`;markers[id]=L.marker(latlng).addTo(map).bindPopup(p);}else{markers[id].setLatLng(latlng);}}
        document.getElementById("viewMapBtn").addEventListener("click",function(){modals.map.classList.remove('hidden');if(!map){map=L.map('dispatchMap').setView([12.8797,121.7740],5);L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);if(!firebaseInitialized){try{if(!firebase.apps.length)firebase.initializeApp(firebaseConfig);else firebase.app();const db=firebase.database();const ref=db.ref('live_tracking');ref.on('child_added',s=>handleDataChange(s.key,s.val()));ref.on('child_changed',s=>handleDataChange(s.key,s.val()));ref.on('child_removed',s=>{if(map&&markers[s.key]){map.removeLayer(markers[s.key]);delete markers[s.key];}});firebaseInitialized=true;}catch(e){console.error(e);}}}setTimeout(()=>map.invalidateSize(),10);});
        lucide.createIcons();
        const themeToggle=document.getElementById('themeToggle');if(localStorage.getItem('theme')==='dark'||(!('theme'in localStorage)&&window.matchMedia('(prefers-color-scheme: dark)').matches)){document.documentElement.classList.add('dark');if(themeToggle)themeToggle.checked=true;}else{document.documentElement.classList.remove('dark');if(themeToggle)themeToggle.checked=false;}
        if(themeToggle){themeToggle.addEventListener('change',function(){if(this.checked){document.documentElement.classList.add('dark');localStorage.setItem('theme','dark');}else{document.documentElement.classList.remove('dark');localStorage.setItem('theme','light');}});}}
    </script>
</body>
</html>
