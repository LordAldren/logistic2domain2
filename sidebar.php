<?php
/**
 * sidebar_revised.php
 *
 * Pinagsamang version ng dalawang sidebar.
 * - Gumagamit ng modernong design at functionality (collapsible) mula sa sidebar_admin.php.
 * - Naglalaman ng business logic (user roles, modules) mula sa sidebar.php.
 */

// Mahalagang simulan ang session para ma-access ang session variables.
// Siguraduhing ito ang unang tatawagin bago mag-output ng kahit anong HTML.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Kunin ang filename ng kasalukuyang page para sa pag-highlight ng active link.
$currentPage = basename($_SERVER['PHP_SELF']);

// Kunin ang user role mula sa session. Gagawing 'admin' by default para sa demonstration.
// Sa totoong application, mas magandang i-default sa 'guest' at i-redirect kung hindi naka-login.
$user_role = $_SESSION['role'] ?? 'admin'; 

// --- Module Access Control ---
// Dito tinutukoy kung anong role ang may access sa bawat module.
$module_access = [
    'dashboard' => ['admin', 'staff'],
    'fvm'       => ['admin', 'staff'],
    'vrds'      => ['admin', 'staff'],
    'dtpm'      => ['admin', 'staff'],
    'tcao'      => ['admin'],
    'ma'        => ['admin']
];

// --- Mga Page Group para sa Active State ---
// Tinutukoy kung aling mga page ang kabilang sa bawat module para sa pag-highlight ng active dropdown.
$fvm_pages = ['vehicle_list.php', 'maintenance_approval.php', 'usage_logs.php'];
$vrds_pages = ['available_vehicles.php', 'reservation_booking.php', 'dispatch_control.php'];
$dtpm_pages = ['live_tracking.php', 'driver_profiles.php', 'trip_history.php', 'route_adherence.php', 'driver_behavior.php', 'delivery_status.php'];
$tcao_pages = ['cost_analysis.php', 'trip_costs.php', 'budget_management.php'];
$ma_pages = ['mobile_app.php', 'admin_alerts.php', 'admin_messaging.php'];

// --- Helper function para malaman kung active ang isang module (dropdown) ---
function is_module_active($pages, $currentPage) {
    return in_array($currentPage, $pages);
}

?>
<!DOCTYPE html>
<html lang="tl">
<head>
    <meta charset="UTF-8" />
    <title>SLATE Logistics Sidebar</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="logo2.png" /> <!-- Palitan ng tamang path sa iyong logo -->

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>

    <style>
        /* Custom styles para sa mas smooth na transition at scrollbar */
        #sidebar {
            transition: width 300ms cubic-bezier(0.4, 0, 0.2, 1);
        }
        #sidebar-toggle i {
            transition: transform 300ms ease-in-out;
        }
        #main-content {
            transition: margin-left 300ms cubic-bezier(0.4, 0, 0.2, 1);
        }
        .rotate-180 {
            transform: rotate(180deg);
        }
        /* Custom scrollbar para sa sidebar navigation */
        #sidebar nav::-webkit-scrollbar {
            width: 6px;
        }
        #sidebar nav::-webkit-scrollbar-track {
            background: transparent;
        }
        #sidebar nav::-webkit-scrollbar-thumb {
            background: #4a5568; /* gray-600 */
            border-radius: 3px;
        }
        #sidebar nav::-webkit-scrollbar-thumb:hover {
            background: #718096; /* gray-500 */
        }
    </style>
</head>
<body class="flex bg-gray-100">

    <!-- Sidebar Container -->
    <div id="sidebar" class="bg-gray-800 text-white w-64 min-h-screen flex flex-col overflow-hidden fixed top-0 left-0 h-full z-10">

        <!-- Logo at System Name -->
        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-700">
             <a href="landpage.php" class="flex items-center gap-3" title="SLATE Logistics Home">
                <img src="logo.png" alt="SLATE Logo" class="h-10 sidebar-logo-expanded" />
                <img src="logo2.png" alt="SLATE Logo" class="h-10 sidebar-logo-collapsed hidden" />
                <span class="text-xl font-bold sidebar-text">SLATE</span>
            </a>
            <button id="sidebar-toggle" class="text-white focus:outline-none" aria-label="Toggle Sidebar">
                <i data-lucide="chevron-left" class="w-6 h-6"></i>
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
            <?php if (in_array($user_role, $module_access['fvm'])): 
                $is_active = is_module_active($fvm_pages, $currentPage);
            ?>
                <button type="button" class="w-full flex items-center justify-between px-3 py-2 rounded-md hover:bg-gray-700 group <?= $is_active ? 'bg-gray-700' : '' ?>" data-submenu-toggle="fvm-submenu">
                    <span class="flex items-center gap-3">
                        <i data-lucide="truck" class="w-5 h-5"></i>
                        <span class="sidebar-text">Fleet & Vehicle Mgt.</span>
                    </span>
                    <i data-lucide="chevron-down" class="w-4 h-4 transition-transform submenu-chevron <?= $is_active ? 'rotate-180' : '' ?>"></i>
                </button>
                <div id="fvm-submenu" class="ml-9 space-y-1 <?= $is_active ? '' : 'hidden' ?>">
                    <a href="vehicle_list.php" class="block px-3 py-2 rounded-md text-sm hover:bg-gray-700 <?= $currentPage === 'vehicle_list.php' ? 'bg-gray-600' : '' ?>">Vehicle List</a>
                    <a href="maintenance_approval.php" class="block px-3 py-2 rounded-md text-sm hover:bg-gray-700 <?= $currentPage === 'maintenance_approval.php' ? 'bg-gray-600' : '' ?>">Maintenance</a>
                    <a href="usage_logs.php" class="block px-3 py-2 rounded-md text-sm hover:bg-gray-700 <?= $currentPage === 'usage_logs.php' ? 'bg-gray-600' : '' ?>">Usage Logs</a>
                </div>
            <?php endif; ?>

            <!-- Reservation & Dispatch -->
            <?php if (in_array($user_role, $module_access['vrds'])): 
                $is_active = is_module_active($vrds_pages, $currentPage);
            ?>
                <button type="button" class="w-full flex items-center justify-between px-3 py-2 rounded-md hover:bg-gray-700 group <?= $is_active ? 'bg-gray-700' : '' ?>" data-submenu-toggle="vrds-submenu">
                    <span class="flex items-center gap-3">
                        <i data-lucide="calendar-check" class="w-5 h-5"></i>
                        <span class="sidebar-text">Reservation & Dispatch</span>
                    </span>
                    <i data-lucide="chevron-down" class="w-4 h-4 transition-transform submenu-chevron <?= $is_active ? 'rotate-180' : '' ?>"></i>
                </button>
                <div id="vrds-submenu" class="ml-9 space-y-1 <?= $is_active ? '' : 'hidden' ?>">
                    <a href="available_vehicles.php" class="block px-3 py-2 rounded-md text-sm hover:bg-gray-700 <?= $currentPage === 'available_vehicles.php' ? 'bg-gray-600' : '' ?>">Available Vehicles</a>
                    <a href="reservation_booking.php" class="block px-3 py-2 rounded-md text-sm hover:bg-gray-700 <?= $currentPage === 'reservation_booking.php' ? 'bg-gray-600' : '' ?>">Booking</a>
                    <a href="dispatch_control.php" class="block px-3 py-2 rounded-md text-sm hover:bg-gray-700 <?= $currentPage === 'dispatch_control.php' ? 'bg-gray-600' : '' ?>">Dispatch & Trips</a>
                </div>
            <?php endif; ?>

             <!-- Driver & Trip Performance -->
            <?php if (in_array($user_role, $module_access['dtpm'])): 
                $is_active = is_module_active($dtpm_pages, $currentPage);
            ?>
                <button type="button" class="w-full flex items-center justify-between px-3 py-2 rounded-md hover:bg-gray-700 group <?= $is_active ? 'bg-gray-700' : '' ?>" data-submenu-toggle="dtpm-submenu">
                    <span class="flex items-center gap-3">
                        <i data-lucide="map-pin" class="w-5 h-5"></i>
                        <span class="sidebar-text">Driver & Trip Perf.</span>
                    </span>
                    <i data-lucide="chevron-down" class="w-4 h-4 transition-transform submenu-chevron <?= $is_active ? 'rotate-180' : '' ?>"></i>
                </button>
                <div id="dtpm-submenu" class="ml-9 space-y-1 <?= $is_active ? '' : 'hidden' ?>">
                    <a href="live_tracking.php" class="block px-3 py-2 rounded-md text-sm hover:bg-gray-700 <?= $currentPage === 'live_tracking.php' ? 'bg-gray-600' : '' ?>">Live Tracking</a>
                    <a href="driver_profiles.php" class="block px-3 py-2 rounded-md text-sm hover:bg-gray-700 <?= $currentPage === 'driver_profiles.php' ? 'bg-gray-600' : '' ?>">Driver Profiles</a>
                    <a href="trip_history.php" class="block px-3 py-2 rounded-md text-sm hover:bg-gray-700 <?= $currentPage === 'trip_history.php' ? 'bg-gray-600' : '' ?>">Trip History</a>
                </div>
            <?php endif; ?>

            <!-- Transport Cost Analysis -->
            <?php if (in_array($user_role, $module_access['tcao'])): 
                $is_active = is_module_active($tcao_pages, $currentPage);
            ?>
                <button type="button" class="w-full flex items-center justify-between px-3 py-2 rounded-md hover:bg-gray-700 group <?= $is_active ? 'bg-gray-700' : '' ?>" data-submenu-toggle="tcao-submenu">
                    <span class="flex items-center gap-3">
                        <i data-lucide="bar-chart-2" class="w-5 h-5"></i>
                        <span class="sidebar-text">Transport Cost Analysis</span>
                    </span>
                    <i data-lucide="chevron-down" class="w-4 h-4 transition-transform submenu-chevron <?= $is_active ? 'rotate-180' : '' ?>"></i>
                </button>
                <div id="tcao-submenu" class="ml-9 space-y-1 <?= $is_active ? '' : 'hidden' ?>">
                    <a href="cost_analysis.php" class="block px-3 py-2 rounded-md text-sm hover:bg-gray-700 <?= $currentPage === 'cost_analysis.php' ? 'bg-gray-600' : '' ?>">Cost Analysis</a>
                    <a href="trip_costs.php" class="block px-3 py-2 rounded-md text-sm hover:bg-gray-700 <?= $currentPage === 'trip_costs.php' ? 'bg-gray-600' : '' ?>">Trip Costs</a>
                    <a href="budget_management.php" class="block px-3 py-2 rounded-md text-sm hover:bg-gray-700 <?= $currentPage === 'budget_management.php' ? 'bg-gray-600' : '' ?>">Budget Management</a>
                </div>
            <?php endif; ?>

            <!-- Mobile Fleet Command -->
            <?php if (in_array($user_role, $module_access['ma'])): 
                $is_active = is_module_active($ma_pages, $currentPage);
            ?>
                <button type="button" class="w-full flex items-center justify-between px-3 py-2 rounded-md hover:bg-gray-700 group <?= $is_active ? 'bg-gray-700' : '' ?>" data-submenu-toggle="ma-submenu">
                    <span class="flex items-center gap-3">
                        <i data-lucide="smartphone" class="w-5 h-5"></i>
                        <span class="sidebar-text">Mobile Fleet Command</span>
                    </span>
                    <i data-lucide="chevron-down" class="w-4 h-4 transition-transform submenu-chevron <?= $is_active ? 'rotate-180' : '' ?>"></i>
                </button>
                <div id="ma-submenu" class="ml-9 space-y-1 <?= $is_active ? '' : 'hidden' ?>">
                    <a href="mobile_app.php" class="block px-3 py-2 rounded-md text-sm hover:bg-gray-700 <?= $currentPage === 'mobile_app.php' ? 'bg-gray-600' : '' ?>">Driver App Sim</a>
                    <a href="admin_alerts.php" class="block px-3 py-2 rounded-md text-sm hover:bg-gray-700 <?= $currentPage === 'admin_alerts.php' ? 'bg-gray-600' : '' ?>">Emergency Alerts</a>
                    <a href="admin_messaging.php" class="block px-3 py-2 rounded-md text-sm hover:bg-gray-700 <?= $currentPage === 'admin_messaging.php' ? 'bg-gray-600' : '' ?>">Messaging</a>
                </div>
            <?php endif; ?>

        </nav>
        
        <!-- Logout Link sa baba -->
        <div class="px-2 py-4 mt-auto border-t border-gray-700">
             <a href="logout.php" class="flex items-center gap-3 px-3 py-2 rounded-md hover:bg-gray-700">
                <i data-lucide="log-out" class="w-5 h-5"></i>
                <span class="sidebar-text">Logout</span>
            </a>
        </div>
    </div>

  
   

    <script>
        document.addEventListener("DOMContentLoaded", () => {
            const sidebar = document.getElementById("sidebar");
            const toggleBtn = document.getElementById("sidebar-toggle");
            const mainContent = document.getElementById("main-content");

            const logoExpanded = document.querySelectorAll(".sidebar-logo-expanded");
            const logoCollapsed = document.querySelectorAll(".sidebar-logo-collapsed");
            const sidebarTextElements = document.querySelectorAll(".sidebar-text");
            const submenuChevrons = document.querySelectorAll('.submenu-chevron');
            const icon = toggleBtn.querySelector("i");
            
            // Function para sa pag-toggle ng sidebar
            const toggleSidebar = () => {
                sidebar.classList.toggle("w-64");
                sidebar.classList.toggle("w-20");
                mainContent.classList.toggle("ml-64");
                mainContent.classList.toggle("ml-20");

                // I-toggle ang visibility ng mga logo at text
                logoExpanded.forEach(el => el.classList.toggle("hidden"));
                logoCollapsed.forEach(el => el.classList.toggle("hidden"));
                sidebarTextElements.forEach(el => el.classList.toggle("hidden"));
                submenuChevrons.forEach(chevron => chevron.classList.toggle('hidden'));

                // I-rotate ang toggle icon
                icon.classList.toggle("rotate-180");
                
                // Kung naka-collapse ang sidebar, isara lahat ng submenu
                if (sidebar.classList.contains('w-20')) {
                    document.querySelectorAll('[id$="-submenu"]').forEach(submenu => {
                        submenu.classList.add('hidden');
                    });
                     document.querySelectorAll('.submenu-chevron').forEach(chevron => {
                        chevron.classList.remove('rotate-180');
                    });
                }
            };
            
            toggleBtn.addEventListener("click", toggleSidebar);

            // Para sa mga submenu toggle
            document.querySelectorAll('[data-submenu-toggle]').forEach(btn => {
                btn.addEventListener('click', () => {
                    // Huwag i-toggle ang submenu kung naka-collapse ang sidebar
                    if (sidebar.classList.contains('w-20')) {
                        toggleSidebar(); // Palakihin muna ang sidebar
                        return;
                    }

                    const targetId = btn.getAttribute('data-submenu-toggle');
                    const submenu = document.getElementById(targetId);
                    const chevron = btn.querySelector('.submenu-chevron');
                    
                    if (submenu) {
                        submenu.classList.toggle('hidden');
                        chevron && chevron.classList.toggle('rotate-180');
                    }
                });
            });

            // I-initialize ang Lucide icons
            if (typeof lucide !== "undefined") {
                lucide.createIcons();
            }
        });
    </script>
</body>
</html>
