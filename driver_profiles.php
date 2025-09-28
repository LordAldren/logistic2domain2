<?php
session_start();
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}
require_once 'db_connect.php';
$message = '';

// --- ACTION HANDLING ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Handle Driver Approval/Rejection
    if (isset($_POST['update_driver_status']) && $_SESSION['role'] === 'admin') {
        $driver_id_to_update = $_POST['driver_id_to_update'];
        $new_status = $_POST['new_status'];
        if ($new_status === 'Active') {
            $stmt = $conn->prepare("UPDATE drivers SET status = 'Active' WHERE id = ?");
            $stmt->bind_param("i", $driver_id_to_update);
            if($stmt->execute()) $message = "<div class='message-banner success'>Driver approved.</div>";
        } elseif ($new_status === 'Rejected') {
            $user_id_query = $conn->prepare("SELECT user_id FROM drivers WHERE id = ?");
            $user_id_query->bind_param("i", $driver_id_to_update);
            $user_id_query->execute();
            if($user_id_row = $user_id_query->get_result()->fetch_assoc()){
                $conn->prepare("DELETE FROM users WHERE id = ?")->execute([$user_id_row['user_id']]);
                $message = "<div class='message-banner success'>Registration rejected and deleted.</div>";
            }
        }
    }
    // Handle Add/Edit Driver
    elseif (isset($_POST['save_driver'])) {
        $id = $_POST['driver_id']; $name = $_POST['name']; $license_number = $_POST['license_number']; $license_expiry = !empty($_POST['license_expiry_date'])?$_POST['license_expiry_date']:NULL; $contact_number = $_POST['contact_number']; $date_joined = !empty($_POST['date_joined'])?$_POST['date_joined']:NULL; $status = $_POST['status']; $rating = $_POST['rating']; $user_id = !empty($_POST['user_id'])?(int)$_POST['user_id']:NULL;
        if (empty($id)) { 
            $sql = "INSERT INTO drivers (name, license_number, license_expiry_date, contact_number, date_joined, status, rating, user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)"; 
            $stmt = $conn->prepare($sql); $stmt->bind_param("ssssssdi", $name, $license_number, $license_expiry, $contact_number, $date_joined, $status, $rating, $user_id);
        } else { 
            $sql = "UPDATE drivers SET name=?, license_number=?, license_expiry_date=?, contact_number=?, date_joined=?, status=?, rating=?, user_id=? WHERE id=?"; 
            $stmt = $conn->prepare($sql); $stmt->bind_param("ssssssdii", $name, $license_number, $license_expiry, $contact_number, $date_joined, $status, $rating, $user_id, $id); 
        }
        if($stmt->execute()) $message = "<div class='message-banner success'>Driver saved successfully!</div>"; else $message = "<div class='message-banner error'>Error saving driver.</div>";
    }
}
// --- DATA FETCHING ---
$pending_drivers = $conn->query("SELECT d.id, d.name, d.license_number, u.email FROM drivers d JOIN users u ON d.user_id = u.id WHERE d.status = 'Pending' ORDER BY d.created_at ASC");
$drivers = $conn->query("SELECT d.*, COUNT(t.id) as total_trips, AVG(t.route_adherence_score) as avg_adherence_score FROM drivers d LEFT JOIN trips t ON d.id = t.driver_id AND t.status = 'Completed' WHERE d.status != 'Pending' GROUP BY d.id ORDER BY d.name ASC");
$users = $conn->query("SELECT id, username FROM users WHERE role = 'driver'");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"> <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Driver Profiles | SLATE Logistics</title>
  <link rel="stylesheet" href="style.css"> <script src="https://cdn.tailwindcss.com"></script> <script src="https://unpkg.com/lucide@latest"></script>
  <style> .dark .card, .dark .modal-content { background-color: #1f2937; border-color: #374151; } .dark table, .dark p, .dark label, .dark h2, .dark strong { color: #d1d5db; } .dark th { color: #9ca3af; } .dark td { border-bottom-color: #374151; } .dark .form-input { background-color: #374151; border-color: #4b5563; color: #d1d5db; } </style>
</head>
<body class="bg-gray-100 dark:bg-gray-900">
  <?php include 'sidebar.php'; ?>
  <main id="main-content" class="ml-64 transition-all duration-300 ease-in-out">
    <div class="p-6">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-3xl font-bold text-gray-800 dark:text-gray-200">Driver Profile Management</h1>
            <div class="theme-toggle-container flex items-center gap-2"><span class="text-sm font-medium text-gray-600 dark:text-gray-400">Dark Mode</span><label class="relative inline-flex items-center cursor-pointer"><input type="checkbox" id="themeToggle" class="sr-only peer"><div class="w-11 h-6 bg-gray-200 rounded-full peer peer-focus:ring-4 peer-focus:ring-blue-300 dark:peer-focus:ring-blue-800 dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-blue-600"></div></label></div>
        </div>
        <?php if (!empty($message)) { echo "<div class='mb-4'>" . $message . "</div>"; } ?>
        <?php if ($_SESSION['role'] === 'admin' && $pending_drivers->num_rows > 0): ?>
        <div class="card bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6 mb-6">
            <h3 class="text-xl font-semibold mb-4">Pending Driver Registrations</h3>
            <div class="overflow-x-auto"><table>
                <thead class="bg-gray-50 dark:bg-gray-700 text-xs uppercase"><tr><th class="p-4">Name</th><th class="p-4">License No.</th><th class="p-4">Email</th><th class="p-4">Actions</th></tr></thead>
                <tbody><?php while($row = $pending_drivers->fetch_assoc()): ?>
                    <tr class="border-b dark:border-gray-700">
                        <td class="p-4"><?php echo htmlspecialchars($row['name']); ?></td><td><?php echo htmlspecialchars($row['license_number']); ?></td><td><?php echo htmlspecialchars($row['email']); ?></td>
                        <td class="p-4 flex gap-2"><form action="driver_profiles.php" method="POST" class="inline"><input type="hidden" name="driver_id_to_update" value="<?php echo $row['id']; ?>"><input type="hidden" name="new_status" value="Active"><button type="submit" name="update_driver_status" class="text-green-500 hover:underline text-xs">Approve</button></form><form action="driver_profiles.php" method="POST" class="inline"><input type="hidden" name="driver_id_to_update" value="<?php echo $row['id']; ?>"><input type="hidden" name="new_status" value="Rejected"><button type="submit" name="update_driver_status" class="text-red-500 hover:underline text-xs" onclick="return confirm('Reject and delete?');">Reject</button></form></td>
                    </tr><?php endwhile; ?></tbody>
            </table></div>
        </div><?php endif; ?>
        <div class="card bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6">
            <div class="flex justify-between items-center mb-4"><h3 class="text-xl font-semibold">Driver Profiles</h3><div class="flex gap-2"><button id="addDriverBtn" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 flex items-center gap-2"><i data-lucide="plus-circle" class="w-4 h-4"></i>Add Driver</button><a href="driver_profiles.php?download_csv=true" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 flex items-center gap-2"><i data-lucide="download" class="w-4 h-4"></i>CSV</a></div></div>
            <div class="overflow-x-auto"><table>
                <thead class="bg-gray-50 dark:bg-gray-700 text-xs uppercase"><tr><th class="p-4">Name</th><th class="p-4">Contact</th><th class="p-4">License Expiry</th><th class="p-4">Trips</th><th class="p-4">Status</th><th class="p-4">Rating</th><th class="p-4">Actions</th></tr></thead>
                <tbody><?php if($drivers->num_rows > 0): mysqli_data_seek($drivers, 0); while($row = $drivers->fetch_assoc()): ?>
                    <tr class="border-b dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
                        <td class="p-4"><?php echo htmlspecialchars($row['name']); ?></td><td><?php echo htmlspecialchars($row['contact_number'] ?? 'N/A'); ?></td>
                        <td class="p-4 <?php $exp_date=new DateTime($row['license_expiry_date']); echo ($exp_date < new DateTime('+30 days')) ? 'text-red-500 font-bold' : ''; ?>"><?php echo !empty($row['license_expiry_date']) ? htmlspecialchars($row['license_expiry_date']) : 'N/A'; ?></td>
                        <td class="p-4"><?php echo htmlspecialchars($row['total_trips']); ?></td><td><span class="status-badge status-<?php echo strtolower($row['status']); ?>"><?php echo htmlspecialchars($row['status']); ?></span></td><td><?php echo htmlspecialchars($row['rating']); ?> ★</td>
                        <td class="p-4 flex gap-2"><button class="editDriverBtn text-yellow-500 hover:underline text-xs" data-details='<?php echo htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8'); ?>'>Edit</button></td>
                    </tr><?php endwhile; else: ?><tr><td colspan="7" class="text-center p-6">No active drivers found.</td></tr><?php endif; ?></tbody>
            </table></div>
        </div>
    </div>
  </main>
  <div id="driverModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden items-center justify-center p-4"><div class="modal-content bg-white rounded-lg shadow-xl w-full max-w-2xl p-6 relative"><button class="close-button absolute top-4 right-4"><i data-lucide="x"></i></button><h2 id="modalTitle" class="text-2xl font-bold mb-4">Add Driver</h2><form action="driver_profiles.php" method="POST"><input type="hidden" id="driver_id" name="driver_id"><div class="grid grid-cols-1 md:grid-cols-2 gap-4"><div class="form-group"><label>Full Name</label><input type="text" name="name" id="name" class="form-input w-full" required></div><div class="form-group"><label>Contact Number</label><input type="text" name="contact_number" id="contact_number" class="form-input w-full"></div><div class="form-group"><label>Date Joined</label><input type="date" name="date_joined" id="date_joined" class="form-input w-full"></div><div class="form-group"><label>License Number</label><input type="text" name="license_number" id="license_number" class="form-input w-full" required></div><div class="form-group"><label>License Expiry</label><input type="date" name="license_expiry_date" id="license_expiry_date" class="form-input w-full"></div><div class="form-group"><label>Rating (1-5)</label><input type="number" step="0.1" min="1" max="5" name="rating" id="rating" class="form-input w-full" required></div><div class="form-group"><label>Status</label><select name="status" id="status" class="form-input w-full" required><option value="Active">Active</option><option value="Suspended">Suspended</option><option value="Inactive">Inactive</option></select></div><div class="form-group"><label>User Account</label><select name="user_id" id="user_id" class="form-input w-full"><option value="">-- None --</option><?php mysqli_data_seek($users, 0); while($user = $users->fetch_assoc()): ?><option value="<?php echo $user['id']; ?>"><?php echo htmlspecialchars($user['username']); ?></option><?php endwhile; ?></select></div></div><div class="flex justify-end gap-2 mt-6"><button type="button" class="btn btn-secondary cancelBtn">Cancel</button><button type="submit" name="save_driver" class="btn btn-primary">Save</button></div></form></div></div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    lucide.createIcons();
    const modals = { driver: document.getElementById('driverModal') };
    Object.values(modals).forEach(modal => { modal?.querySelector('.close-button')?.addEventListener('click', () => modal.classList.add('hidden')); modal?.querySelector('.cancelBtn')?.addEventListener('click', () => modal.classList.add('hidden')); });
    document.getElementById("addDriverBtn").addEventListener("click", () => { const m=modals.driver; m.querySelector('form').reset(); m.querySelector('#driver_id').value=''; m.querySelector('#modalTitle').textContent='Add Driver'; m.classList.remove('hidden'); });
    document.querySelectorAll('.editDriverBtn').forEach(btn => btn.addEventListener('click', () => { const d=JSON.parse(btn.dataset.details); const m=modals.driver; m.querySelector('form').reset(); m.querySelector('#modalTitle').textContent='Edit Driver'; m.querySelector('#driver_id').value=d.id; m.querySelector('#name').value=d.name; m.querySelector('#license_number').value=d.license_number; m.querySelector('#license_expiry_date').value=d.license_expiry_date; m.querySelector('#contact_number').value=d.contact_number; m.querySelector('#date_joined').value=d.date_joined; m.querySelector('#rating').value=d.rating; m.querySelector('#status').value=d.status; m.querySelector('#user_id').value=d.user_id; m.classList.remove('hidden'); }));
    const themeToggle=document.getElementById('themeToggle');if(localStorage.getItem('theme')==='dark'||(!('theme'in localStorage)&&window.matchMedia('(prefers-color-scheme: dark)').matches)){document.documentElement.classList.add('dark');if(themeToggle)themeToggle.checked=true;}else{document.documentElement.classList.remove('dark');if(themeToggle)themeToggle.checked=false;}if(themeToggle){themeToggle.addEventListener('change',function(){if(this.checked){document.documentElement.classList.add('dark');localStorage.setItem('theme','dark');}else{document.documentElement.classList.remove('dark');localStorage.setItem('theme','light');}});}
});
</script>
</body>
</html>
