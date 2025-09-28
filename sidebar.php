<?php
// sidebar.php
$current_page = basename($_SERVER['PHP_SELF']);
$user_role = $_SESSION['role'] ?? 'guest';

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

function render_sidebar_link($href, $icon_svg, $text, $is_active) {
    echo "<a href=\"$href\" class=\"sidebar-link " . ($is_active ? 'active' : '') . "\">";
    echo "<span class=\"sidebar-icon\">$icon_svg</span>";
    echo "<span>$text</span>";
    echo "</a>";
}

function render_dropdown($text, $icon_svg, $pages, $current_page, $sub_links) {
    $is_active = in_array($current_page, $pages);
    echo "<div class=\"dropdown " . ($is_active ? 'active' : '') . "\">";
    echo "<a href=\"#\" class=\"dropdown-toggle\">";
    echo "<div class=\"link-content\">";
    echo "<span class=\"sidebar-icon\">$icon_svg</span>";
    echo "<span>$text</span>";
    echo "</div>";
    echo "<span class=\"arrow\"></span>"; // Arrow for dropdown
    echo "</a>";
    echo "<div class=\"dropdown-menu\">";
    foreach ($sub_links as $link_href => $link_text) {
        echo "<a href=\"$link_href\" class=\"" . ($current_page == $link_href ? 'active-sub' : '') . "\">$link_text</a>";
    }
    echo "</div>";
    echo "</div>";
}
?>
<div class="sidebar" id="sidebar">
    <div class="logo"><img src="logo.png" alt="SLATE Logo"></div>
    <div class="system-name">SLATE LOGISTICS</div>

    <?php if ($user_role === 'admin' || $user_role === 'staff'): ?>
        <?php render_sidebar_link('landpage.php', '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>', 'Dashboard', $current_page == 'landpage.php'); ?>
    <?php endif; ?>

    <?php if (in_array($user_role, $module_access['fvm'])): ?>
        <?php render_dropdown('Fleet & Vehicle Mgt.', '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 17H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h10v14z"></path><path d="M20 17h-4v-7a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v7z"></path><path d="M12 5H9.5a2.5 2.5 0 0 1 0-5C10.9 0 12 1.1 12 2.5V5z"></path><path d="M18 5h-1.5a2.5 2.5 0 0 1 0-5C17.4 0 18 1.1 18 2.5V5z"></path></svg>', $fvm_pages, $current_page, [
            'vehicle_list.php' => 'Vehicle List',
            'maintenance_approval.php' => 'Maintenance Approval',
            'usage_logs.php' => 'Usage Logs'
        ]); ?>
    <?php endif; ?>

    <?php if (in_array($user_role, $module_access['vrds'])): ?>
        <?php render_dropdown('Reservation & Dispatch', '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 17.929H6c-1.105 0-2-.895-2-2V7c0-1.105.895-2 2-2h12c1.105 0 2 .895 2 2v2.828"></path><path d="M6 17h12"></path><circle cx="6" cy="17" r="2"></circle><circle cx="18" cy="17" r="2"></circle><path d="M12 12V5h4l3 3v2h-3"></path></svg>', $vrds_pages, $current_page, [
            'available_vehicles.php' => 'Available Vehicles',
            'reservation_booking.php' => 'Reservation Booking',
            'dispatch_control.php' => 'Dispatch & Trips'
        ]); ?>
    <?php endif; ?>
    
    <?php if (in_array($user_role, $module_access['dtpm'])): ?>
        <?php render_dropdown('Driver & Trip Perf.', '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s-8-4.5-8-11.8A8 8 0 0 1 12 2a8 8 0 0 1 8 8.2c0 7.3-8 11.8-8 11.8z"></path><circle cx="12" cy="10" r="3"></circle></svg>', $dtpm_pages, $current_page, [
            'live_tracking.php' => 'Live Tracking',
            'driver_profiles.php' => 'Driver Profiles',
            'trip_history.php' => 'Trip History',
            'route_adherence.php' => 'Route Adherence',
            'driver_behavior.php' => 'Driver Behavior',
            'delivery_status.php' => 'Delivery Status'
        ]); ?>
    <?php endif; ?>

    <?php if (in_array($user_role, $module_access['tcao'])): ?>
        <?php render_dropdown('Transport Cost Analysis', '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20V10"></path><path d="M18 20V4"></path><path d="M6 20V16"></path></svg>', $tcao_pages, $current_page, [
            'cost_analysis.php' => 'Cost Analysis',
            'trip_costs.php' => 'Trip Costs',
            'budget_management.php' => 'Budget Management'
        ]); ?>
    <?php endif; ?>

    <?php if (in_array($user_role, $module_access['ma'])): ?>
        <?php render_dropdown('Mobile Fleet Command', '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 18h.01"></path><path d="M10.5 5.5L8 3H4v18h16V3h-4l-2.5 2.5z"></path><path d="M12 11v-1"></path></svg>', $ma_pages, $current_page, [
            'mobile_app.php' => 'Driver App Sim',
            'admin_alerts.php' => 'Emergency Alerts',
            'admin_messaging.php' => 'Messaging'
        ]); ?>
    <?php endif; ?>
    
    <a href="logout.php" class="logout-link sidebar-link" id="logout-link">
        <span class="sidebar-icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg></span>
        <span>Logout</span>
    </a>
</div>

