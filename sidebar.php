<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$currentPage = basename($_SERVER['PHP_SELF']);
$user_role = $_SESSION['role'] ?? 'admin';

// Module Access Control
$module_access = [
    'dashboard' => ['admin', 'staff'],
    'fvm'       => ['admin', 'staff'],
    'vrds'      => ['admin', 'staff'],
    'dtpm'      => ['admin', 'staff'],
    'tcao'      => ['admin'],
    'ma'        => ['admin']
];

// Page groups for active state highlighting
$fvm_pages = ['vehicle_list.php', 'maintenance_approval.php', 'usage_logs.php'];
$vrds_pages = ['available_vehicles.php', 'reservation_booking.php', 'dispatch_control.php'];
$dtpm_pages = ['live_tracking.php', 'driver_profiles.php', 'trip_history.php', 'route_adherence.php', 'driver_behavior.php', 'delivery_status.php'];
$tcao_pages = ['cost_analysis.php', 'trip_costs.php', 'budget_management.php'];
$ma_pages = ['mobile_app.php', 'admin_alerts.php', 'admin_messaging.php'];

function is_module_active($pages, $currentPage) {
    return in_array($currentPage, $pages);
}
?>
<!-- Sidebar Container -->
<aside id="sidebar" class="bg-gray-800 text-white w-64 min-h-screen flex-col overflow-hidden fixed top-0 left-0 h-full z-20 transition-all duration-300 ease-in-out">
    <!-- Logo and System Name -->
    <div class="flex items-center justify-between px-4 py-3 border-b border-gray-700">
         <a href="landpage.php" class="flex items-center gap-3" title="SLATE Logistics Home">
            <img src="logo.png" alt="SLATE Logo" class="h-10 sidebar-logo-expanded" />
            <img src="logo2.png" alt="SLATE Logo" class="h-10 sidebar-logo-collapsed hidden" />
            <span class="text-xl font-bold sidebar-text">SLATE</span>
        </a>
        <button id="sidebar-toggle" class="text-white focus:outline-none" aria-label="Toggle Sidebar">
            <i data-lucide="chevron-left" class="w-6 h-6 transition-transform duration-300"></i>
        </button>
    </div>

    <!-- Navigation Links -->
    <nav class="flex-1 px-2 py-4 space-y-2 overflow-y-auto">
        <!-- Dashboard -->
        <?php if (in_array($user_role, $module_access['dashboard'])): ?>
            <a href="landpage.php" class="flex items-center gap-3 px-3 py-2 rounded-md hover:bg-gray-700 <?= $currentPage === 'landpage.php' ? 'bg-gray-700 font-semibold' : '' ?>">
                <i data-lucide="layout-dashboard" class="w-5 h-5"></i>
                <span class="sidebar-text">Dashboard</span>
            </a>
        <?php endif; ?>

        <!-- Fleet & Vehicle Management -->
        <?php if (in_array($user_role, $module_access['fvm'])): $is_active = is_module_active($fvm_pages, $currentPage); ?>
            <div class="sidebar-menu-item">
                <button type="button" class="w-full flex items-center justify-between px-3 py-2 rounded-md hover:bg-gray-700 group <?= $is_active ? 'bg-gray-700' : '' ?>" data-submenu-toggle="fvm-submenu">
                    <span class="flex items-center gap-3">
                        <i data-lucide="truck" class="w-5 h-5"></i>
                        <span class="sidebar-text">Fleet & Vehicle Mgt.</span>
                    </span>
                    <i data-lucide="chevron-down" class="w-4 h-4 transition-transform submenu-chevron sidebar-text <?= $is_active ? 'rotate-180' : '' ?>"></i>
                </button>
                <div id="fvm-submenu" class="ml-4 space-y-1 overflow-hidden transition-all duration-300 <?= $is_active ? 'max-h-screen' : 'max-h-0' ?>">
                    <a href="vehicle_list.php" class="block px-3 py-2 rounded-md text-sm hover:bg-gray-700 <?= $currentPage === 'vehicle_list.php' ? 'bg-gray-600' : '' ?> sidebar-text pl-8">Vehicle List</a>
                    <a href="maintenance_approval.php" class="block px-3 py-2 rounded-md text-sm hover:bg-gray-700 <?= $currentPage === 'maintenance_approval.php' ? 'bg-gray-600' : '' ?> sidebar-text pl-8">Maintenance</a>
                    <a href="usage_logs.php" class="block px-3 py-2 rounded-md text-sm hover:bg-gray-700 <?= $currentPage === 'usage_logs.php' ? 'bg-gray-600' : '' ?> sidebar-text pl-8">Usage Logs</a>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Other menu items like Reservation, Driver Perf, etc. would follow the same pattern -->

    </nav>
    
    <!-- Logout Link -->
    <div class="px-2 py-4 mt-auto border-t border-gray-700">
         <a href="logout.php" class="flex items-center gap-3 px-3 py-2 rounded-md hover:bg-gray-700">
            <i data-lucide="log-out" class="w-5 h-5"></i>
            <span class="sidebar-text">Logout</span>
        </a>
    </div>
</aside>
