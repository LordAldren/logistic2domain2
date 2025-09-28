<?php
// sidebar.php
$current_page = basename($_SERVER['PHP_SELF']);
$user_role = $_SESSION['role'] ?? 'guest'; // Default to guest if not set

// Tukuyin kung aling role ang may access sa bawat module
$module_access = [
    'fvm' => ['admin', 'staff'],
    'vrds' => ['admin', 'staff'],
    'dtpm' => ['admin', 'staff'],
    'tcao' => ['admin'],
    'ma' => ['admin']
];

// Mga grupo ng pahina para sa pag-set ng 'active' state
$fvm_pages = ['vehicle_list.php', 'maintenance_approval.php', 'usage_logs.php'];
$vrds_pages = ['available_vehicles.php', 'reservation_booking.php', 'dispatch_control.php'];
$dtpm_pages = ['live_tracking.php', 'driver_profiles.php', 'trip_history.php', 'route_adherence.php', 'driver_behavior.php', 'delivery_status.php'];
$tcao_pages = ['cost_analysis.php', 'trip_costs.php', 'budget_management.php'];
$ma_pages = ['mobile_app.php', 'admin_alerts.php', 'admin_messaging.php'];
?>
<div class="sidebar" id="sidebar">
    <div class="logo"><img src="logo.png" alt="SLATE Logo"></div>
    <div class="system-name">SLATE LOGISTICS</div>

    <?php if ($user_role === 'admin' || $user_role === 'staff'): ?>
    <a href="landpage.php" class="<?php echo ($current_page == 'landpage.php') ? 'active' : ''; ?>">
        <span class="sidebar-icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg></span>
        <span>Dashboard</span>
    </a>
    <?php endif; ?>

    <?php if (in_array($user_role, $module_access['fvm'])): ?>
    <div class="dropdown <?php echo (in_array($current_page, $fvm_pages)) ? 'active' : ''; ?>">
        <a href="#" class="dropdown-toggle">
            <div class="link-content">
                <span class="sidebar-icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 17H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h10v14z"></path><path d="M20 17h-4v-7a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v7z"></path><path d="M12 5H9.5a2.5 2.5 0 0 1 0-5C10.9 0 12 1.1 12 2.5V5z"></path><path d="M18 5h-1.5a2.5 2.5 0 0 1 0-5C17.4 0 18 1.1 18 2.5V5z"></path></svg></span>
                <span>Fleet & Vehicle Mgt.</span>
            </div>
        </a>
        <div class="dropdown-menu">
            <a href="vehicle_list.php" class="<?php echo ($current_page == 'vehicle_list.php') ? 'active-sub' : ''; ?>">Vehicle List</a>
            <a href="maintenance_approval.php" class="<?php echo ($current_page == 'maintenance_approval.php') ? 'active-sub' : ''; ?>">Maintenance Approval</a>
            <a href="usage_logs.php" class="<?php echo ($current_page == 'usage_logs.php') ? 'active-sub' : ''; ?>">Usage Logs</a>
        </div>
    </div>
    <?php endif; ?>

    <?php if (in_array($user_role, $module_access['vrds'])): ?>
    <div class="dropdown <?php echo (in_array($current_page, $vrds_pages)) ? 'active' : ''; ?>">
        <a href="#" class="dropdown-toggle">
            <div class="link-content">
                <span class="sidebar-icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 17.929H6c-1.105 0-2-.895-2-2V7c0-1.105.895-2 2-2h12c1.105 0 2 .895 2 2v2.828"></path><path d="M6 17h12"></path><circle cx="6" cy="17" r="2"></circle><circle cx="18" cy="17" r="2"></circle><path d="M12 12V5h4l3 3v2h-3"></path></svg></span>
                <span>Reservation & Dispatch</span>
            </div>
        </a>
        <div class="dropdown-menu">
            <a href="available_vehicles.php" class="<?php echo ($current_page == 'available_vehicles.php') ? 'active-sub' : ''; ?>">Available Vehicles</a>
            <a href="reservation_booking.php" class="<?php echo ($current_page == 'reservation_booking.php') ? 'active-sub' : ''; ?>">Reservation Booking</a>
            <a href="dispatch_control.php" class="<?php echo ($current_page == 'dispatch_control.php') ? 'active-sub' : ''; ?>">Dispatch & Trips</a>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if (in_array($user_role, $module_access['dtpm'])): ?>
    <div class="dropdown <?php echo (in_array($current_page, $dtpm_pages)) ? 'active' : ''; ?>">
        <a href="#" class="dropdown-toggle">
            <div class="link-content">
                <span class="sidebar-icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s-8-4.5-8-11.8A8 8 0 0 1 12 2a8 8 0 0 1 8 8.2c0 7.3-8 11.8-8 11.8z"></path><circle cx="12" cy="10" r="3"></circle></svg></span>
                <span>Driver & Trip Perf.</span>
            </div>
        </a>
        <div class="dropdown-menu">
            <a href="live_tracking.php" class="<?php echo ($current_page == 'live_tracking.php') ? 'active-sub' : ''; ?>">Live Tracking</a>
            <a href="driver_profiles.php" class="<?php echo ($current_page == 'driver_profiles.php') ? 'active-sub' : ''; ?>">Driver Profiles</a>
            <a href="trip_history.php" class="<?php echo ($current_page == 'trip_history.php') ? 'active-sub' : ''; ?>">Trip History</a>
            <a href="route_adherence.php" class="<?php echo ($current_page == 'route_adherence.php') ? 'active-sub' : ''; ?>">Route Adherence</a>
            <a href="driver_behavior.php" class="<?php echo ($current_page == 'driver_behavior.php') ? 'active-sub' : ''; ?>">Driver Behavior</a>
            <a href="delivery_status.php" class="<?php echo ($current_page == 'delivery_status.php') ? 'active-sub' : ''; ?>">Delivery Status</a>
        </div>
    </div>
    <?php endif; ?>

    <?php if (in_array($user_role, $module_access['tcao'])): ?>
    <div class="dropdown <?php echo (in_array($current_page, $tcao_pages)) ? 'active' : ''; ?>">
        <a href="#" class="dropdown-toggle">
            <div class="link-content">
                <span class="sidebar-icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20V10"></path><path d="M18 20V4"></path><path d="M6 20V16"></path></svg></span>
                <span>Transport Cost Analysis</span>
            </div>
        </a>
        <div class="dropdown-menu">
            <a href="cost_analysis.php" class="<?php echo ($current_page == 'cost_analysis.php') ? 'active-sub' : ''; ?>">Cost Analysis</a>
            <a href="trip_costs.php" class="<?php echo ($current_page == 'trip_costs.php') ? 'active-sub' : ''; ?>">Trip Costs</a>
            <a href="budget_management.php" class="<?php echo ($current_page == 'budget_management.php') ? 'active-sub' : ''; ?>">Budget Management</a>
        </div>
    </div>
    <?php endif; ?>

    <?php if (in_array($user_role, $module_access['ma'])): ?>
    <div class="dropdown <?php echo (in_array($current_page, $ma_pages)) ? 'active' : ''; ?>">
        <a href="#" class="dropdown-toggle">
            <div class="link-content">
                <span class="sidebar-icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 18h.01"></path><path d="M10.5 5.5L8 3H4v18h16V3h-4l-2.5 2.5z"></path><path d="M12 11v-1"></path></svg></span>
                <span>Mobile Fleet Command</span>
            </div>
        </a>
        <div class="dropdown-menu">
            <a href="mobile_app.php" class="<?php echo ($current_page == 'mobile_app.php') ? 'active-sub' : ''; ?>">Driver App Sim</a>
            <a href="admin_alerts.php" class="<?php echo ($current_page == 'admin_alerts.php') ? 'active-sub' : ''; ?>">Emergency Alerts</a>
            <a href="admin_messaging.php" class="<?php echo ($current_page == 'admin_messaging.php') ? 'active-sub' : ''; ?>">Messaging</a>
        </div>
    </div>
    <?php endif; ?>
    
    <a href="logout.php" class="logout-link" id="logout-link">
        <span class="sidebar-icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg></span>
        <span>Logout</span>
    </a>
</div>
