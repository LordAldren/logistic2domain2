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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Tracking | SLATE Logistics</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://www.gstatic.com/firebasejs/8.10.1/firebase-app.js"></script>
    <script src="https://www.gstatic.com/firebasejs/8.10.1/firebase-database.js"></script>
    <style>
        .dark .card { background-color: #1f2937; border-color: #374151; }
        .dark table { color: #d1d5db; } .dark th { color: #9ca3af; }
        .dark td { border-bottom-color: #374151; }
        #liveTrackingMap { height: 60vh; }
    </style>
</head>
<body class="bg-gray-100 dark:bg-gray-900">
    <?php include 'sidebar.php'; ?>
    <main id="main-content" class="ml-64 transition-all duration-300 ease-in-out">
        <div class="p-6">
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-3xl font-bold text-gray-800 dark:text-gray-200">Live Vehicle Tracking</h1>
                <div class="theme-toggle-container flex items-center gap-2">
                    <span class="text-sm font-medium text-gray-600 dark:text-gray-400">Dark Mode</span>
                    <label class="relative inline-flex items-center cursor-pointer"><input type="checkbox" id="themeToggle" class="sr-only peer"><div class="w-11 h-6 bg-gray-200 rounded-full peer peer-focus:ring-4 peer-focus:ring-blue-300 dark:peer-focus:ring-blue-800 dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-blue-600"></div></label>
                </div>
            </div>

            <div class="card bg-white dark:bg-gray-800 rounded-xl shadow-lg p-0 overflow-hidden">
                <div id="liveTrackingMap" class="w-full rounded-t-xl z-0"></div>
                <div class="p-6">
                    <h3 class="text-xl font-semibold text-gray-700 dark:text-gray-300 mb-4">Active Trips</h3>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm text-gray-600 dark:text-gray-400">
                            <thead class="bg-gray-50 dark:bg-gray-700 text-xs uppercase">
                                <tr><th class="p-4">Vehicle</th><th class="p-4">Driver</th><th class="p-4">Location (Lat, Lng)</th><th class="p-4">Status</th><th class="p-4">Actions</th></tr>
                            </thead>
                            <tbody id="tracking-table-body">
                                <tr id="no-tracking-placeholder"><td colspan="5" class="text-center p-6 text-gray-500">Waiting for live feed...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>      
        </div>
    </main>
<script>
document.addEventListener('DOMContentLoaded', function() {
    lucide.createIcons();
    const themeToggle = document.getElementById('themeToggle');
    if(themeToggle) {
        if (localStorage.getItem('theme') === 'dark'||(!('theme'in localStorage)&&window.matchMedia('(prefers-color-scheme: dark)').matches)){document.documentElement.classList.add('dark');themeToggle.checked=true;}else{document.documentElement.classList.remove('dark');themeToggle.checked=false;}
        themeToggle.addEventListener('change',function(){if(this.checked){document.documentElement.classList.add('dark');localStorage.setItem('theme','dark');}else{document.documentElement.classList.remove('dark');localStorage.setItem('theme','light');}});
    }

    const firebaseConfig = { apiKey: "AIzaSyCB0_OYZXX3K-AxKeHnVlYMv2wZ_81FeYM", authDomain: "slate49-cde60.firebaseapp.com", databaseURL: "https://slate49-cde60-default-rtdb.firebaseio.com", projectId: "slate49-cde60", storageBucket: "slate49-cde60.firebasestorage.app", messagingSenderId: "809390854040", appId: "1:809390854040:web:f7f77333bb0ac7ab73e5ed" };
    try { if (!firebase.apps.length) { firebase.initializeApp(firebaseConfig); } } catch(e) { console.error("Firebase init failed.", e); }
    const database = firebase.database();
    
    const map = L.map('liveTrackingMap').setView([12.8797, 121.7740], 6);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OpenStreetMap' }).addTo(map);
    
    const markers = {};
    const trackingTableBody = document.getElementById('tracking-table-body');
    const noTrackingPlaceholder = document.getElementById('no-tracking-placeholder');

    function getVehicleIcon(vehicleInfo) {
        let svgIcon = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#4A6CF7" width="40px" height="40px"><path d="M0 0h24v24H0z" fill="none"/><path d="M20 8h-3V4H3c-1.1 0-2 .9-2 2v11h2c0 1.66 1.34 3 3 3s3-1.34 3-3h6c0 1.66 1.34 3 3 3s3-1.34 3-3h2v-5l-3-4zM6 18c-.55 0-1-.45-1-1s.45-1 1-1 1 .45 1 1-.45 1-1 1zm13.5-1.5c-.83 0-1.5.67-1.5 1.5s.67 1.5 1.5 1.5 1.5-.67 1.5-1.5-.67-1.5-1.5-1.5zM18 10h1.5v3H18v-3zM3 6h12v7H3V6z"/></svg>`;
        return L.divIcon({ html: svgIcon, className: 'vehicle-icon', iconSize: [40, 40], iconAnchor: [20, 40], popupAnchor: [0, -40] });
    }

    function createOrUpdateMarkerAndRow(tripId, data) {
        if (!data.vehicle_info || !data.driver_name) return;
        if(noTrackingPlaceholder) noTrackingPlaceholder.style.display = 'none';
        
        const newLatLng = [data.lat, data.lng];
        let tripRow = document.getElementById(`trip-row-${tripId}`);
        
        if (!markers[tripId]) {
            const popupContent = `<b>Vehicle:</b> ${data.vehicle_info}<br><b>Driver:</b> ${data.driver_name}`;
            markers[tripId] = L.marker(newLatLng, { icon: getVehicleIcon(data.vehicle_info) }).addTo(map).bindPopup(popupContent);
            if (!tripRow) {
                const newRow = document.createElement('tr');
                newRow.id = `trip-row-${tripId}`; newRow.className = 'border-b dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 cursor-pointer';
                newRow.innerHTML = `<td class="p-4">${data.vehicle_info}</td><td class="p-4">${data.driver_name}</td><td class="p-4 location-cell">${data.lat.toFixed(6)}, ${data.lng.toFixed(6)}</td><td class="p-4"><span class="status-badge status-en-route">En Route</span></td><td class="p-4"><a href="dispatch_control.php" class="text-blue-500 hover:underline">View Dispatch</a></td>`;
                newRow.addEventListener('click', () => { map.flyTo(newLatLng, 15); markers[tripId].openPopup(); });
                trackingTableBody.appendChild(newRow);
            }
        } else {
            markers[tripId].setLatLng(newLatLng);
            if (tripRow) tripRow.querySelector('.location-cell').textContent = `${data.lat.toFixed(6)}, ${data.lng.toFixed(6)}`;
        }
    }

    function removeMarkerAndRow(tripId) {
        if (markers[tripId]) { map.removeLayer(markers[tripId]); delete markers[tripId]; }
        const tripRow = document.getElementById(`trip-row-${tripId}`);
        if (tripRow) tripRow.remove();
        if (Object.keys(markers).length === 0 && noTrackingPlaceholder) { noTrackingPlaceholder.style.display = ''; }
    }
    
    const trackingRef = database.ref('live_tracking');
    trackingRef.on('child_added', (snapshot) => createOrUpdateMarkerAndRow(snapshot.key, snapshot.val()));
    trackingRef.on('child_changed', (snapshot) => createOrUpdateMarkerAndRow(snapshot.key, snapshot.val()));
    trackingRef.on('child_removed', (snapshot) => removeMarkerAndRow(snapshot.key));
});
</script>
</body>
</html>
