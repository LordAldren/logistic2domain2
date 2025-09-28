<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

// RBAC check - Only drivers can access this page
if ($_SESSION['role'] !== 'driver') {
    header("location: landpage.php");
    exit;
}

require_once 'db_connect.php';
$message = '';
$user_id = $_SESSION['id'];

// Get the view from the URL, default to 'dashboard'
$view = isset($_GET['view']) ? $_GET['view'] : 'dashboard';

// --- DATA FETCHING ---

// Get the driver ID to be used in other queries
$driver_id_result = $conn->query("SELECT id FROM drivers WHERE user_id = $user_id LIMIT 1");
$driver_id = $driver_id_result->num_rows > 0 ? $driver_id_result->fetch_assoc()['id'] : null;

// Get the full driver profile
$driver_profile = null;
if ($driver_id) {
    $profile_stmt = $conn->prepare("SELECT * FROM drivers WHERE id = ?");
    $profile_stmt->bind_param("i", $driver_id);
    $profile_stmt->execute();
    $driver_profile = $profile_stmt->get_result()->fetch_assoc();
    $profile_stmt->close();
}
$driver_name = $driver_profile ? $driver_profile['name'] : $_SESSION['username'];

// Get the driver's current trip
$current_trip = null;
if ($driver_id) {
    $trip_sql = "SELECT t.*, v.type as vehicle_type, v.model as vehicle_model FROM trips t JOIN vehicles v ON t.vehicle_id = v.id WHERE t.driver_id = ? AND t.status NOT IN ('Completed', 'Cancelled') ORDER BY t.pickup_time DESC LIMIT 1";
    $trip_stmt = $conn->prepare($trip_sql);
    $trip_stmt->bind_param("i", $driver_id);
    $trip_stmt->execute();
    $current_trip = $trip_stmt->get_result()->fetch_assoc();
    $trip_stmt->close();
}
$destination_json = json_encode($current_trip ? $current_trip['destination'] : null);
$trip_id_for_js = $current_trip ? $current_trip['id'] : 'null';
$driver_id_for_js = $driver_id ? $driver_id : 'null';

// Get stats for the dashboard
$dashboard_stats = [
    'weekly_trips' => 0,
    'total_trips' => 0
];
if ($driver_id) {
    $weekly_trips_result = $conn->query("SELECT COUNT(*) as count FROM trips WHERE driver_id = $driver_id AND status = 'Completed' AND pickup_time >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
    $dashboard_stats['weekly_trips'] = $weekly_trips_result->fetch_assoc()['count'];
    
    $total_trips_result = $conn->query("SELECT COUNT(*) as count FROM trips WHERE driver_id = $driver_id AND status = 'Completed'");
    $dashboard_stats['total_trips'] = $total_trips_result->fetch_assoc()['count'];
}

// Get driver's behavior logs
$behavior_logs = [];
if ($driver_id) {
    $logs_stmt = $conn->prepare("
        SELECT log_date, overspeeding_count, harsh_braking_count, idle_duration_minutes, notes
        FROM driver_behavior_logs
        WHERE driver_id = ?
        ORDER BY log_date DESC
        LIMIT 10
    ");
    $logs_stmt->bind_param("i", $driver_id);
    $logs_stmt->execute();
    $logs_result = $logs_stmt->get_result();
    while ($row = $logs_result->fetch_assoc()) {
        $behavior_logs[] = $row;
    }
    $logs_stmt->close();
}


// --- FORM & ACTION HANDLING ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Handle Status Update
    if (isset($_POST['update_trip_status'])) {
        $trip_id = $_POST['trip_id'];
        $new_status = $_POST['new_status'];
        $location_update = $_POST['location_update']; 

        if ($trip_id && $new_status && $driver_id) {
            $conn->begin_transaction();
            try {
                $stmt1 = $conn->prepare("UPDATE trips SET status = ? WHERE id = ? AND driver_id = ?");
                $stmt1->bind_param("sii", $new_status, $trip_id, $driver_id);
                $stmt1->execute();
                
                $stmt2 = $conn->prepare("INSERT INTO tracking_log (trip_id, status_message) VALUES (?, ?)");
                $stmt2->bind_param("is", $trip_id, $location_update);
                $stmt2->execute();
                
                $conn->commit();
                $message = "<div class='message-banner success'>Trip status updated to '$new_status'.</div>";
            } catch (mysqli_sql_exception $exception) {
                $conn->rollback();
                $message = "<div class='message-banner error'>Database error: " . $exception->getMessage() . "</div>";
            }
        }
    }

    // Handle Proof of Delivery
    elseif (isset($_POST['submit_pod'])) {
        $trip_id = $_POST['trip_id'];
        $delivery_notes = $_POST['delivery_notes'];
        $upload_dir = 'uploads/pod/';
        if (!is_dir($upload_dir)) { mkdir($upload_dir, 0755, true); }

        if (isset($_FILES['pod_image']) && $_FILES['pod_image']['error'] == 0) {
            $file_name = time() . '_' . basename($_FILES['pod_image']['name']);
            $target_file = $upload_dir . $file_name;

            if (move_uploaded_file($_FILES['pod_image']['tmp_name'], $target_file)) {
                $stmt = $conn->prepare("UPDATE trips SET proof_of_delivery_path = ?, delivery_notes = ?, status = 'Completed', actual_arrival_time = NOW() WHERE id = ? AND driver_id = ?");
                $stmt->bind_param("ssii", $target_file, $delivery_notes, $trip_id, $driver_id);
                if($stmt->execute()){
                    $message = "<div class='message-banner success'>Proof of Delivery submitted successfully. Trip marked as completed.</div>";
                    
                    // --- START: BAGONG CODE PARA BURAHIN ANG DATA SA FIREBASE ---
                    echo '
                    <script src="https://www.gstatic.com/firebasejs/8.10.1/firebase-app.js"></script>
                    <script src="https://www.gstatic.com/firebasejs/8.10.1/firebase-database.js"></script>
                    <script>
                        const firebaseConfig = {
                            apiKey: "AIzaSyCB0_OYZXX3K-AxKeHnVlYMv2wZ_81FeYM",
                            authDomain: "slate49-cde60.firebaseapp.com",
                            databaseURL: "https://slate49-cde60-default-rtdb.firebaseio.com",
                            projectId: "slate49-cde60",
                            storageBucket: "slate49-cde60.firebasestorage.app",
                            messagingSenderId: "809390854040",
                            appId: "1:809390854040:web:f7f77333bb0ac7ab73e5ed"
                        };
                        if (!firebase.apps.length) {
                            firebase.initializeApp(firebaseConfig);
                        }
                        const database = firebase.database();
                        const tripIdToRemove = ' . $trip_id . ';
                        if(tripIdToRemove) {
                            database.ref("live_tracking/" + tripIdToRemove).remove()
                                .then(() => {
                                    console.log("Live tracking data for trip " + tripIdToRemove + " removed.");
                                    window.location.href = "mobile_app.php?view=dashboard";
                                })
                                .catch((error) => {
                                    console.error("Error removing live tracking data: ", error);
                                    window.location.href = "mobile_app.php?view=dashboard";
                                });
                        } else {
                             window.location.href = "mobile_app.php?view=dashboard";
                        }
                    </script>';
                    // --- END: BAGONG CODE ---
                    exit(); // Itigil ang script dito para ma-execute ang JavaScript bago mag-redirect
                } else {
                    $message = "<div class='message-banner error'>Failed to update trip details in the database.</div>";
                }
            } else {
                $message = "<div class='message-banner error'>Failed to upload proof of delivery image.</div>";
            }
        } else {
            $message = "<div class='message-banner error'>Please select an image to upload as proof of delivery.</div>";
        }
    }
    
    // Handle SOS
    elseif (isset($_POST['send_sos'])) {
        $description = $_POST['description'];
        $trip_id = $_POST['trip_id'];
        if ($driver_id && $trip_id) {
            $sql = "INSERT INTO alerts (trip_id, driver_id, alert_type, description, status) VALUES (?, ?, 'SOS', ?, 'Pending')";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iis", $trip_id, $driver_id, $description);
            $stmt->execute();
            $message = "<div class='message-banner success'>SOS Alert sent to admin! Help is on the way.</div>";
        } else {
            $message = "<div class='message-banner error'>Could not send SOS. You must be on an active trip.</div>";
        }
    }

    // Handle Overspeeding Alert (Bagong feature)
    elseif (isset($_POST['overspeeding_alert'])) {
        $trip_id = $_POST['trip_id'];
        $overspeeding_message = $_POST['message'];
        $driver_id_overspeed = $_POST['driver_id'];

        if ($driver_id_overspeed && $trip_id) {
            // Check if an overspeeding log for today already exists to avoid spamming
            $check_stmt = $conn->prepare("SELECT id FROM driver_behavior_logs WHERE driver_id = ? AND log_date = CURDATE() AND overspeeding_count > 0");
            $check_stmt->bind_param("i", $driver_id_overspeed);
            $check_stmt->execute();
            $check_stmt->store_result();

            if ($check_stmt->num_rows > 0) {
                // Update existing log
                $update_stmt = $conn->prepare("UPDATE driver_behavior_logs SET overspeeding_count = overspeeding_count + 1 WHERE driver_id = ? AND log_date = CURDATE()");
                $update_stmt->bind_param("i", $driver_id_overspeed);
                $update_stmt->execute();
                $update_stmt->close();
            } else {
                // Insert new log
                $log_date = date('Y-m-d');
                $notes = "Overspeeding incident detected via GPS.";
                $sql = "INSERT INTO driver_behavior_logs (driver_id, trip_id, log_date, overspeeding_count, notes) VALUES (?, ?, ?, 1, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("iiss", $driver_id_overspeed, $trip_id, $log_date, $notes);
                $stmt->execute();
                $stmt->close();
            }
             $check_stmt->close();
        }
        // Walang i-return na HTML message, para hindi ma-interrupt ang driver app UI.
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Driver App | SLATE</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap');
        :root {
          --primary-color: #4A6CF7; --success-color: #10B981; --info-color: #3B82F6; --danger-color: #EF4444;
          --bg-light: #F7F7FB; --card-light: #FFFFFF; --text-dark: #212B36; --text-muted: #64748B;
          --border-color: #E2E8F0; --shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Poppins', sans-serif; background-color: var(--bg-light); color: var(--text-dark); line-height: 1.6; -webkit-font-smoothing: antialiased; }
        .app-container { max-width: 480px; margin: 0 auto; min-height: 100vh; background-color: var(--card-light); box-shadow: 0 0 30px rgba(0,0,0,0.1); display: flex; flex-direction: column; position: relative; left: 0; transition: left 0.3s ease-in-out; }
        .app-header { display: flex; align-items: center; padding: 1rem 1.25rem; background-color: var(--card-light); border-bottom: 1px solid var(--border-color); position: sticky; top: 0; z-index: 100; gap: 1rem; }
        .app-header .logo { display: flex; align-items: center; gap: 0.75rem; font-weight: 600; color: var(--text-dark); }
        .app-header .logo img { height: 32px; width: auto; }
        #hamburgerBtn { background: none; border: none; font-size: 1.8rem; cursor: pointer; color: var(--text-dark); padding: 0.5rem; }
        .app-main { padding: 1rem; flex-grow: 1; }
        .card { background-color: var(--card-light); border: 1px solid var(--border-color); border-radius: 0.75rem; padding: 1.25rem; margin-bottom: 1.25rem; box-shadow: var(--shadow); }
        .card h2, .card h3 { margin-bottom: 1rem; font-weight: 600; color: var(--text-dark); }
        .card .card-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color); padding-bottom: 1rem; margin-bottom: 1rem; }
        .card .card-header h2 { margin-bottom: 0; }
        .trip-card .destination p, .profile-card p { font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 0.25rem; }
        .trip-card .destination h3, .profile-card h3 { font-size: 1.5rem; font-weight: 700; color: var(--primary-color); margin-bottom: 1rem; }
        .trip-card .trip-details p, .profile-card ul li, #behavior-view ul li { font-size: 0.9rem; color: var(--text-muted); line-height: 1.8; }
        .profile-card ul, #behavior-view ul { list-style: none; padding-left: 0.5rem; }
        .profile-card ul li strong, #behavior-view ul li strong { color: var(--text-dark); }
        .no-trip-card { text-align: center; padding: 2.5rem 1.5rem; }
        .no-trip-card svg { color: var(--primary-color); margin-bottom: 1rem; }
        .no-trip-card h3 { font-size: 1.25rem; }
        .no-trip-card p { color: var(--text-muted); }
        #map { height: 250px; width: 100%; border-radius: 0.5rem; background-color: #eee; z-index: 1; }
        #map-loader { text-align: center; padding: 2rem; color: var(--text-muted); }
        .actions-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.75rem; }
        .action-btn { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 1rem 0.5rem; border: none; border-radius: 0.5rem; font-size: 0.9rem; font-weight: 500; cursor: pointer; transition: all 0.2s ease; color: white; }
        .action-btn svg { width: 28px; height: 28px; margin-bottom: 0.5rem; fill: white; }
        .action-btn.depart { background-color: var(--primary-color); }
        .action-btn.arrive { background-color: var(--info-color); }
        .action-btn.unload { background-color: var(--text-muted); }
        .action-btn:hover { transform: translateY(-3px); box-shadow: 0 6px 12px rgba(0,0,0,0.1); }
        .pod-card { background-color: #f0f5ff; }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 500; font-size: 0.9rem; }
        .form-control { display: block; width: 100%; padding: 0.75rem; font-size: 1rem; background-color: var(--bg-light); border: 1px solid var(--border-color); border-radius: 0.5rem; transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out; }
        .form-control:focus { outline: 0; border-color: var(--primary-color); }
        .btn { padding: 0.75rem 1.25rem; font-weight: 600; font-size: 1rem; border-radius: 0.5rem; border: none; cursor: pointer; transition: all 0.3s ease; }
        .btn-success { background-color: var(--success-color); color: white; }
        .fab-sos { position: fixed; bottom: 1.5rem; right: 1.5rem; width: 60px; height: 60px; background-color: var(--danger-color); color: white; border: none; border-radius: 50%; display: flex; flex-direction: column; align-items: center; justify-content: center; box-shadow: 0 4px 15px rgba(239, 68, 68, 0.4); cursor: pointer; transition: all 0.3s ease; z-index: 1000; }
        .fab-sos:hover { transform: scale(1.1); }
        .fab-sos span { font-size: 0.7rem; font-weight: 600; margin-top: 2px; }
        .modal { display: none; position: fixed; z-index: 1050; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.6); align-items: flex-end; justify-content: center; }
        .modal-content { background-color: #fff; padding: 1.5rem; border-radius: 1rem 1rem 0 0; width: 100%; max-width: 480px; position: relative; animation: slide-up 0.3s ease-out; }
        @keyframes slide-up { from { transform: translateY(100%); } to { transform: translateY(0); } }
        .modal-content .close-button { color: #aaa; position: absolute; top: 0.75rem; right: 1rem; font-size: 28px; font-weight: bold; cursor: pointer; }
        .message-banner { padding: 1rem; margin: 0 0 1rem 0; border-radius: 0.5rem; color: white; }
        .message-banner.success { background-color: var(--success-color); }
        .message-banner.error { background-color: var(--danger-color); }
        .status-badge { padding: 0.25em 0.7em; font-size: 0.75rem; font-weight: 600; border-radius: 10rem; color: #fff; }
        .status-en-route { background-color: var(--primary-color); }
        .status-arrived-at-destination { background-color: var(--info-color); }
        .status-unloading { background-color: var(--text-muted); }
        .status-scheduled, .status-active { background-color: #F59E0B; color: #333;}
        .expiring-soon { color: var(--danger-color); font-weight: bold; }
        /* Sidebar Styles */
        .sidebar { position: fixed; top: 0; left: -280px; width: 280px; height: 100%; background-color: #1C2434; z-index: 1100; transition: left 0.3s ease-in-out; display: flex; flex-direction: column; }
        .sidebar.open { left: 0; box-shadow: 5px 0 15px rgba(0,0,0,0.2); }
        .sidebar-header { padding: 1.5rem; background-color: rgba(0,0,0,0.2); text-align: center; }
        .sidebar-header h3 { color: white; margin: 0; font-size: 1.2rem; }
        .sidebar-header p { color: #AAB3C2; margin: 0; font-size: 0.9rem; }
        .sidebar-nav { flex-grow: 1; }
        .sidebar-nav a { display: flex; align-items: center; gap: 1rem; padding: 1rem 1.5rem; color: #AAB3C2; text-decoration: none; font-weight: 500; transition: background-color 0.2s, color 0.2s; border-left: 4px solid transparent; }
        .sidebar-nav a > * { transition: color 0.2s, stroke 0.2s; }
        .sidebar-nav a:hover, .sidebar-nav a:focus { background-color: rgba(74, 108, 247, 0.1); color: #FFFFFF; }
        .sidebar-nav a.active { background-color: var(--primary-color); border-left-color: var(--info-color); color: #FFFFFF; }
        .sidebar-nav a.active svg { stroke: #FFFFFF; }
        .sidebar-footer { padding: 1.5rem; border-top: 1px solid #3A3B3C; }
        .sidebar-footer a { color: #AAB3C2; text-decoration: none; display: flex; align-items: center; gap: 1rem; }
        .sidebar-footer a:hover { color: #FFFFFF; }
        .overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); z-index: 1050; }
        .overlay.show { display: block; }
        .view-section { display: none; }
        
        /* New Styles for Navigation and Speed Limit */
        #navigation-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 1rem;
            padding: 1rem;
            background-color: var(--bg-light);
            border-radius: 0.5rem;
            border: 1px solid var(--border-color);
        }
        #navigation-info .speed-display {
            text-align: center;
        }
        #navigation-info .speed-value {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--primary-color);
            display: flex;
            align-items: flex-end;
            justify-content: center;
            line-height: 1;
        }
        #navigation-info .speed-value span {
            font-size: 1.2rem;
            font-weight: 400;
            margin-left: 0.25rem;
            color: var(--text-muted);
        }
        #navigation-info .speed-limit {
            text-align: right;
            font-size: 0.9rem;
            color: var(--text-muted);
        }
        #navigation-info .speed-limit span {
            font-weight: 600;
            color: var(--success-color);
        }
        .speed-warning {
            color: var(--danger-color) !important;
            animation: pulse-danger 1s infinite;
        }
        @keyframes pulse-danger {
            0% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.05); opacity: 0.8; }
            100% { transform: scale(1); opacity: 1; }
        }

    </style>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
</head>
<body>
    <div class="sidebar" id="driverSidebar">
        <div class="sidebar-header">
            <h3><?php echo htmlspecialchars($driver_name); ?></h3>
            <p>Driver Portal</p>
        </div>
        <nav class="sidebar-nav">
            <a href="mobile_app.php?view=dashboard" class="<?php if($view === 'dashboard') echo 'active'; ?>">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
                <span>Dashboard</span>
            </a>
            <a href="mobile_app.php?view=trip" class="<?php if($view === 'trip') echo 'active'; ?>">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s-8-4.5-8-11.8A8 8 0 0 1 12 2a8 8 0 0 1 8 8.2c0 7.3-8 11.8-8 11.8z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                <span>Current Trip</span>
            </a>
             <a href="mobile_app.php?view=behavior" class="<?php if($view === 'behavior') echo 'active'; ?>">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline></svg>
                <span>My Performance</span>
            </a>
            <a href="mobile_app.php?view=profile" class="<?php if($view === 'profile') echo 'active'; ?>">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                <span>My Profile</span>
            </a>
        </nav>
        <div class="sidebar-footer">
            <a href="logout.php">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                <span>Logout</span>
            </a>
        </div>
    </div>
    <div class="overlay" id="overlay"></div>

    <div class="app-container" id="appContainer">
        <header class="app-header">
            <button id="hamburgerBtn">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>
            </button>
            <div class="logo">
                <img src="logo.png" alt="SLATE Logo">
                <span>SLATE</span>
            </div>
        </header>

        <main class="app-main">
            <?php if (!empty($message)) echo $message; ?>

            <!-- DASHBOARD VIEW -->
            <div id="dashboard-view" class="view-section" style="display: <?php echo $view === 'dashboard' ? 'block' : 'none'; ?>;">
                <h2>Dashboard</h2>
                <div class="card">
                    <h3>Welcome back, <?php echo htmlspecialchars(explode(' ', $driver_name)[0]); ?>!</h3>
                    <p><?php echo $current_trip ? "You have an active trip." : "You are currently on standby."; ?></p>
                </div>
                
                <?php if ($current_trip): ?>
                <div class="card">
                    <h3>Trip Information</h3>
                    <p><strong>Trip ID:</strong> <?php echo htmlspecialchars($current_trip['trip_code']); ?></p>
                    <p><strong>Destination:</strong> <?php echo htmlspecialchars($current_trip['destination']); ?></p>
                    <p><strong>Status:</strong> <span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $current_trip['status'])); ?>"><?php echo htmlspecialchars($current_trip['status']); ?></span></p>
                    <a href="mobile_app.php?view=trip" class="btn btn-success" style="width: 100%; text-align:center; margin-top: 1rem;">View Trip Details</a>
                </div>
                <?php endif; ?>

                <div class="card">
                    <h3>Your Stats</h3>
                    <p><strong>Trips Completed (this week):</strong> <?php echo $dashboard_stats['weekly_trips']; ?></p>
                    <p><strong>Total Completed Trips:</strong> <?php echo $dashboard_stats['total_trips']; ?></p>
                     <p><strong>Rating:</strong> <?php echo $driver_profile ? number_format($driver_profile['rating'], 1) . ' ★' : 'N/A'; ?></p>
                </div>
            </div>

            <!-- TRIP VIEW -->
            <div id="trip-view" class="view-section" style="display: <?php echo $view === 'trip' ? 'block' : 'none'; ?>;">
                <?php if ($current_trip): ?>
                    <div class="card trip-card">
                        <div class="card-header">
                            <h2>Current Trip</h2>
                            <span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $current_trip['status'])); ?>"><?php echo htmlspecialchars($current_trip['status']); ?></span>
                        </div>
                        <div class="destination">
                            <p>DESTINATION</p>
                            <h3><?php echo htmlspecialchars($current_trip['destination']); ?></h3>
                        </div>
                        <div class="trip-details">
                            <p><strong>Trip ID:</strong> <?php echo htmlspecialchars($current_trip['trip_code']); ?></p>
                            <p><strong>Vehicle:</strong> <?php echo htmlspecialchars($current_trip['vehicle_type'] . ' ' . $current_trip['vehicle_model']); ?></p>
                            <p><strong>Pickup:</strong> <?php echo htmlspecialchars(date('M d, Y h:i A', strtotime($current_trip['pickup_time']))); ?></p>
                        </div>
                    </div>
                    
                    <div class="card">
                        <h3>Navigation</h3>
                        <div id="map"></div>
                        <div id="map-loader">Loading map...</div>
                        <div id="navigation-info" style="display:none;">
                            <div>
                                <p id="road-name" class="text-muted-dark">Wala pa sa kalsada...</p>
                                <p class="text-muted-dark">Speed Limit: <span id="speed-limit">N/A</span> km/h</p>
                            </div>
                            <div class="speed-display">
                                <p class="text-muted-dark">Current Speed</p>
                                <div id="current-speed" class="speed-value">
                                    0<span>km/h</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <h3>Actions</h3>
                        <form action="mobile_app.php" method="POST" class="actions-grid">
                            <input type="hidden" name="trip_id" value="<?php echo $current_trip['id']; ?>">
                            <input type="hidden" name="new_status" id="new_status_field">
                            <input type="hidden" name="location_update" id="location_update_field">
                            
                            <button type="submit" name="update_trip_status" onclick="setStatus('En Route', 'Departed from origin')" class="action-btn depart">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path d="M16.5 13.5L15 12 8 18 9.5 19.5 16.5 13.5zM15 12L16.5 10.5 9.5 4.5 8 6 15 12z"/></svg>
                                Depart
                            </button>
                            <button type="submit" name="update_trip_status" onclick="setStatus('Arrived at Destination', 'Arrived at Destination')" class="action-btn arrive">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/></svg>
                                Arrive
                            </button>
                            <button type="submit" name="update_trip_status" onclick="setStatus('Unloading', 'Unloading Cargo')" class="action-btn unload">
                               <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path d="M19 15v-3h-2v3h-3v2h3v3h2v-3h3v-2h-3zM5.99 20.25l-.24-.25H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2h10l4 4v2.26c-.83-.62-1.87-.99-3-1.15V8h-4V4H4v14h3.75c.08-.66.31-1.28.69-1.84l.01.09H5.99z"/></svg>
                                Unload
                            </button>
                        </form>
                    </div>
                    
                    <?php if ($current_trip['status'] == 'Arrived at Destination' || $current_trip['status'] == 'Unloading'): ?>
                    <div class="card pod-card">
                        <h3>Proof of Delivery (POD)</h3>
                        <form action="mobile_app.php" method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="trip_id" value="<?php echo $current_trip['id']; ?>">
                            <div class="form-group">
                                <label for="pod_image">Upload Photo (e.g., signed document)</label>
                                <input type="file" name="pod_image" id="pod_image" class="form-control" accept="image/*" required>
                            </div>
                            <div class="form-group">
                                <label for="delivery_notes">Notes (optional)</label>
                                <textarea name="delivery_notes" id="delivery_notes" class="form-control" rows="3"></textarea>
                            </div>
                            <button type="submit" name="submit_pod" class="btn btn-success" style="width: 100%;">Complete Delivery</button>
                        </form>
                    </div>
                    <?php endif; ?>

                <?php else: ?>
                    <div class="card no-trip-card">
                        <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="8" y1="12" x2="16" y2="12"></line></svg>
                        <h3>No Active Trip</h3>
                        <p>You do not have a trip at the moment. Go to the dashboard for a summary.</p>
                         <a href="mobile_app.php?view=dashboard" class="btn btn-success" style="width: 100%; text-align:center; margin-top: 1rem;">Go to Dashboard</a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- BEHAVIOR VIEW (NEW) -->
            <div id="behavior-view" class="view-section" style="display: <?php echo $view === 'behavior' ? 'block' : 'none'; ?>;">
                <h2>My Performance Logs</h2>
                <p>This shows a record of recent driving incidents.</p>

                <?php if (!empty($behavior_logs)): ?>
                    <?php foreach ($behavior_logs as $log): ?>
                        <div class="card">
                            <h3><?php echo date('F j, Y', strtotime($log['log_date'])); ?></h3>
                            <ul>
                                <?php if ($log['overspeeding_count'] > 0): ?>
                                    <li><strong>Overspeeding:</strong> <?php echo $log['overspeeding_count']; ?> incident(s) recorded.</li>
                                <?php endif; ?>
                                <?php if ($log['harsh_braking_count'] > 0): ?>
                                    <li><strong>Harsh Braking:</strong> <?php echo $log['harsh_braking_count']; ?> incident(s) recorded.</li>
                                <?php endif; ?>
                                <?php if ($log['idle_duration_minutes'] > 0): ?>
                                    <li><strong>Excessive Idle Time:</strong> <?php echo $log['idle_duration_minutes']; ?> minutes recorded.</li>
                                <?php endif; ?>
                                <?php if (empty($log['overspeeding_count']) && empty($log['harsh_braking_count']) && empty($log['idle_duration_minutes'])): ?>
                                    <li>No major incidents recorded on this day.</li>
                                <?php endif; ?>
                                <?php if (!empty($log['notes'])): ?>
                                     <li style="margin-top: 0.5rem;"><strong>Admin Notes:</strong> <em><?php echo htmlspecialchars($log['notes']); ?></em></li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="card no-trip-card">
                         <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                         <h3>No Incidents Recorded</h3>
                         <p>Keep up the safe driving!</p>
                    </div>
                <?php endif; ?>
            </div>


            <!-- PROFILE VIEW -->
            <div id="profile-view" class="view-section" style="display: <?php echo $view === 'profile' ? 'block' : 'none'; ?>;">
                <h2>My Profile</h2>
                <?php if ($driver_profile): ?>
                    <div class="card profile-card">
                        <p>NAME</p>
                        <h3><?php echo htmlspecialchars($driver_profile['name']); ?></h3>
                        <ul>
                            <li><strong>Status:</strong> <span class="status-badge status-<?php echo strtolower($driver_profile['status']); ?>"><?php echo htmlspecialchars($driver_profile['status']); ?></span></li>
                            <li><strong>License Number:</strong> <?php echo htmlspecialchars($driver_profile['license_number']); ?></li>
                            <?php
                                $expiry_date = !empty($driver_profile['license_expiry_date']) ? new DateTime($driver_profile['license_expiry_date']) : null;
                                $is_expiring_soon = false;
                                if ($expiry_date) {
                                    $today = new DateTime();
                                    $diff = $today->diff($expiry_date);
                                    $is_expiring_soon = ($diff->days <= 30 && !$diff->invert);
                                }
                            ?>
                            <li class="<?php echo $is_expiring_soon ? 'expiring-soon' : ''; ?>">
                                <strong>License Expiry:</strong> <?php echo $expiry_date ? $expiry_date->format('M d, Y') : 'N/A'; ?>
                                <?php if($is_expiring_soon) echo " (Expiring soon!)"; ?>
                            </li>
                            <li><strong>Contact Number:</strong> <?php echo htmlspecialchars($driver_profile['contact_number']); ?></li>
                            <li><strong>Date Joined:</strong> <?php echo $driver_profile['date_joined'] ? date('M d, Y', strtotime($driver_profile['date_joined'])) : 'N/A'; ?></li>
                        </ul>
                    </div>
                <?php else: ?>
                    <p>Could not find your profile information.</p>
                <?php endif; ?>
            </div>

        </main>

        <?php if ($current_trip): ?>
            <button id="sendSosBtn" class="fab-sos">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s-8-4.5-8-11.8A8 8 0 0 1 12 2a8 8 0 0 1 8 8.2c0 7.3-8 11.8-8 11.8z"></path><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                <span>SOS</span>
            </button>
        <?php endif; ?>
    </div>

    <div id="actionModal" class="modal" style="display: none;">
      <div class="modal-content">
        <span class="close-button">&times;</span>
        <h2 id="modalTitle"></h2>
        <div id="modalBody"></div>
      </div>
    </div>
    
    <div id="overspeedingAlert" class="modal" style="display: none;">
      <div class="modal-content" style="max-width: 400px; text-align: center;">
        <h2>Bilis na Abala!</h2>
        <p>You are exceeding the speed limit. Please slow down.</p>
        <button id="closeOverspeedingAlert" class="btn btn-danger" style="margin-top: 1rem;">OK</button>
      </div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // --- Sidebar Logic ---
        const sidebar = document.getElementById('driverSidebar');
        const hamburgerBtn = document.getElementById('hamburgerBtn');
        const overlay = document.getElementById('overlay');
        const appContainer = document.getElementById('appContainer');

        function openSidebar() {
            sidebar.classList.add('open');
            overlay.classList.add('show');
        }

        function closeSidebar() {
            sidebar.classList.remove('open');
            overlay.classList.remove('show');
        }

        hamburgerBtn.addEventListener('click', openSidebar);
        overlay.addEventListener('click', closeSidebar);

        // --- Modal Logic ---
        const modal = document.getElementById("actionModal");
        if(modal) {
            const modalTitle = document.getElementById("modalTitle");
            const modalBody = document.getElementById("modalBody");
            const closeBtn = modal.querySelector(".close-button");
            if (closeBtn) { closeBtn.onclick = () => { modal.style.display = "none"; } }
            window.onclick = (event) => { if (event.target == modal) { modal.style.display = "none"; } }

            function showModal(title, content) {
                modalTitle.innerHTML = title;
                modalBody.innerHTML = content;
                modal.style.display = "flex";
            }
            
            const sendSosBtn = document.getElementById('sendSosBtn');
            if(sendSosBtn) {
                sendSosBtn.onclick = () => {
                    const formHtml = `
                        <form action="mobile_app.php" method="POST">
                            <input type="hidden" name="trip_id" value="<?php echo $current_trip ? $current_trip['id'] : ''; ?>">
                            <div class="form-group">
                                <label for="description">Describe the emergency:</label>
                                <textarea name="description" class="form-control" required placeholder="e.g., Flat tire, engine trouble..."></textarea>
                            </div>
                            <div class="form-actions" style="justify-content: flex-end; gap: 0.5rem;">
                                <button type="button" class="btn btn-secondary cancelBtn" style="background-color: #6c757d;">Cancel</button>
                                <button type="submit" name="send_sos" class="btn btn-danger">Confirm & Send SOS</button>
                            </div>
                        </form>`;
                    showModal("Confirm SOS Alert", formHtml);
                    modal.querySelector('.cancelBtn').onclick = () => { modal.style.display = "none"; };
                }
            }
        }

        // --- Map & Navigation Logic ---
        const destination = <?php echo $destination_json; ?>;
        const tripId = <?php echo $trip_id_for_js; ?>;
        const driverId = <?php echo $driver_id_for_js; ?>;
        const mapElement = document.getElementById('map');
        const mapLoader = document.getElementById('map-loader');
        const navigationInfo = document.getElementById('navigation-info');
        const roadNameEl = document.getElementById('road-name');
        const speedLimitEl = document.getElementById('speed-limit');
        const currentSpeedEl = document.getElementById('current-speed');
        const overspeedingAlert = document.getElementById('overspeedingAlert');
        const closeOverspeedingAlertBtn = document.getElementById('closeOverspeedingAlert');
        
        let map;
        let vehicleMarker;
        let currentSpeed = 0;
        let speedLimit = 0;
        let lastOverspeedLogTime = 0;
        const maxSpeedLimit = 100; // Default max speed limit in km/h

        if(destination && mapElement) {
            navigationInfo.style.display = 'flex';
            const startLocation = "Manila Port Area"; 
            
            const fetchRoadData = async (lat, lon) => {
                const url = `https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${lat}&lon=${lon}`;
                const options = { headers: { 'User-Agent': 'SLATE Logistics App/1.0 (https://yourdomain.com)' } };
                try {
                    const response = await fetch(url, options);
                    const data = await response.json();
                    if (data && data.address) {
                        const road = data.address.road || 'Unknown Road';
                        roadNameEl.textContent = road;
                        const osmLimit = data.extratags && data.extratags.maxspeed ? parseInt(data.extratags.maxspeed, 10) : null;
                        speedLimit = osmLimit || maxSpeedLimit;
                        speedLimitEl.textContent = speedLimit;
                    } else {
                        roadNameEl.textContent = 'Hindi mahanap ang kalsada.';
                        speedLimit = maxSpeedLimit;
                        speedLimitEl.textContent = speedLimit;
                    }
                } catch (error) {
                    console.error("Error fetching road data:", error);
                    speedLimit = maxSpeedLimit;
                    speedLimitEl.textContent = speedLimit;
                }
            };
            
            const updatePosition = (position) => {
                const lat = position.coords.latitude;
                const lon = position.coords.longitude;
                currentSpeed = position.coords.speed ? Math.round(position.coords.speed * 3.6) : 0;
                
                currentSpeedEl.innerHTML = `${currentSpeed}<span>km/h</span>`;

                if (currentSpeed > speedLimit && speedLimit > 0) {
                    currentSpeedEl.classList.add('speed-warning');
                    overspeedingAlert.style.display = 'flex';
                    logOverspeeding(currentSpeed, speedLimit);
                } else {
                    currentSpeedEl.classList.remove('speed-warning');
                }

                if (!vehicleMarker) {
                    const vehicleIcon = L.divIcon({ html: '<svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="#4A6CF7"><path d="M18.92 6.01C18.72 5.42 18.16 5 17.5 5h-11C5.84 5 5.28 5.42 5.08 6.01L3 12v8c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-1h12v1c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-8l-2.08-5.99zM6.5 16c-.83 0-1.5-.67-1.5-1.5S5.67 13 6.5 13s1.5.67 1.5 1.5S7.33 16 6.5 16zm11 0c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5s-.67-1.5-1.5 1.5zM5 11l1.5-4.5h11L19 11H5z"/></svg>', className: 'vehicle-icon', iconSize: [40, 40], iconAnchor: [20, 40] });
                    vehicleMarker = L.marker([lat, lon], {icon: vehicleIcon}).addTo(map);
                    map.setView([lat, lon], 16);
                } else {
                    vehicleMarker.setLatLng([lat, lon]);
                    map.panTo([lat, lon]);
                }
                
                fetchRoadData(lat, lon);
            };

            const logOverspeeding = (currentSpeed, speedLimit) => {
                const now = Date.now();
                // To avoid spamming the server, only log once every 60 seconds
                if (now - lastOverspeedLogTime < 60000) {
                    return;
                }
                lastOverspeedLogTime = now;

                const xhr = new XMLHttpRequest();
                xhr.open('POST', 'mobile_app.php', true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.send(`overspeeding_alert=1&trip_id=${tripId}&driver_id=${driverId}&message=Exceeded speed limit of ${speedLimit} km/h, current speed is ${currentSpeed} km/h.`);
            };

            const errorPosition = (error) => {
                console.warn(`ERROR(${error.code}): ${error.message}`);
                navigationInfo.style.display = 'none';
                mapLoader.textContent = 'Unable to get location. Check device settings.';
            };
            
            navigator.geolocation.watchPosition(updatePosition, errorPosition, { enableHighAccuracy: true, maximumAge: 0 });
            
            Promise.all([
                fetch(`route_proxy.php?service=geocode&q=${encodeURIComponent(startLocation)}`).then(res => res.json()),
                fetch(`route_proxy.php?service=geocode&q=${encodeURIComponent(destination)}`).then(res => res.json())
            ]).then(([startData, endData]) => {
                if (!startData.length || !endData.length) {
                    throw new Error('Could not find location coordinates.');
                }
                const startCoords = [startData[0].lat, startData[0].lon];
                const endCoords = [endData[0].lat, endData[0].lon];

                map = L.map('map').setView(startCoords, 13);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OpenStreetMap' }).addTo(map);

                L.marker(endCoords).addTo(map).bindPopup('Destination: ' + destination);

                return fetch(`route_proxy.php?service=route&coords=${startCoords[1]},${startCoords[0]};${endCoords[1]},${endCoords[0]}`);

            }).then(res => res.json()).then(data => {
                if(data.routes && data.routes.length > 0) {
                    const routeCoordinates = data.routes[0].geometry.coordinates.map(coord => [coord[1], coord[0]]);
                    const polyline = L.polyline(routeCoordinates, {color: '#4A6CF7', weight: 5}).addTo(map);
                    map.fitBounds(polyline.getBounds().pad(0.1));
                    mapLoader.style.display = 'none';
                } else {
                     throw new Error('No route found.');
                }
            }).catch(error => {
                console.error("Map Error:", error);
                if(mapLoader) mapLoader.textContent = `Error loading map: ${error.message}`;
            });
        } else if (mapLoader) {
            mapLoader.style.display = 'none';
        }
        
        if (closeOverspeedingAlertBtn) {
            closeOverspeedingAlertBtn.addEventListener('click', () => {
                overspeedingAlert.style.display = 'none';
            });
        }
    });

    function setStatus(newStatus, locationUpdate) {
        document.getElementById('new_status_field').value = newStatus;
        document.getElementById('location_update_field').value = locationUpdate;
    }
    </script>
</body>
</html>

