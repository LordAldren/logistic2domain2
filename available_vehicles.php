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

$available_vehicles_query = $conn->query("SELECT id, type, model, load_capacity_kg, image_url FROM vehicles WHERE status IN ('Active', 'Idle') ORDER BY type, model");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Available Vehicles | SLATE Logistics</title>
  
  <link rel="stylesheet" href="style.css">
  <link rel="stylesheet" href="loader.css">
  
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://unpkg.com/lucide@latest"></script>

  <style>
    .dark .card { background-color: #1f2937; border: 1px solid #374151; }
    .dark .vehicle-card { background-color: #374151; }
  </style>
</head>
<body class="bg-gray-100 dark:bg-gray-900">

  <?php include 'sidebar.php'; ?> 

  <main id="main-content" class="ml-64 transition-all duration-300 ease-in-out">
    <div class="p-6">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-3xl font-bold text-gray-800 dark:text-gray-200">Available Vehicles</h1>
            <div class="theme-toggle-container flex items-center gap-2">
                <span class="text-sm font-medium text-gray-600 dark:text-gray-400">Dark Mode</span>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" id="themeToggle" class="sr-only peer">
                    <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-focus:ring-4 peer-focus:ring-blue-300 dark:peer-focus:ring-blue-800 dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-blue-600"></div>
                </label>
            </div>
        </div>

        <div class="card bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6">
            <h3 class="text-xl font-semibold text-gray-700 dark:text-gray-300">Vehicle Fleet</h3>
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">This is a view-only list of all available vehicles. To create a new reservation, please go to the <a href="reservation_booking.php" class="text-blue-600 hover:underline">Reservation Booking</a> page.</p>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6 mt-6">
                <?php if ($available_vehicles_query->num_rows > 0): ?>
                    <?php while($row = $available_vehicles_query->fetch_assoc()): 
                        $image = 'https://placehold.co/400x300/e2e8f0/e2e8f0?text=No+Image';
                        if (stripos($row['model'], 'elf') !== false) $image = 'elf.PNG';
                        if (stripos($row['model'], 'hiace') !== false) $image = 'hiace.PNG';
                        if (stripos($row['model'], 'canter') !== false) $image = 'canter.PNG';
                    ?>
                    <div class="vehicle-card bg-gray-50 dark:bg-gray-700 rounded-lg shadow-md overflow-hidden transition-transform duration-300 hover:transform hover:-translate-y-1 hover:shadow-xl">
                        <img src="<?php echo htmlspecialchars($image); ?>" alt="<?php echo htmlspecialchars($row['type']); ?>" class="w-full h-48 object-cover">
                        <div class="p-4 flex flex-col flex-grow">
                            <div>
                                <h4 class="text-lg font-bold text-gray-800 dark:text-gray-200"><?php echo htmlspecialchars($row['type'] . ' - ' . $row['model']); ?></h4>
                                <p class="text-sm text-gray-600 dark:text-gray-400">Capacity: <?php echo htmlspecialchars($row['load_capacity_kg']); ?> kg</p>
                            </div>
                            <a href="vehicle_list.php?query=<?php echo urlencode($row['model']); ?>" class="mt-4 w-full bg-blue-600 text-white text-center px-4 py-2 rounded-lg hover:bg-blue-700 transition duration-300 text-sm">View Details</a>
                        </div>
                    </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p class="col-span-full text-center text-gray-500 dark:text-gray-400 py-10">No vehicles are currently available.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
  </main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    lucide.createIcons();
    const themeToggle = document.getElementById('themeToggle');
    if (localStorage.getItem('theme') === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
      document.documentElement.classList.add('dark');
      if(themeToggle) themeToggle.checked = true;
    } else {
      document.documentElement.classList.remove('dark');
      if(themeToggle) themeToggle.checked = false;
    }
    if(themeToggle) {
        themeToggle.addEventListener('change', function() {
          if (this.checked) {
            document.documentElement.classList.add('dark');
            localStorage.setItem('theme', 'dark');
          } else {
            document.documentElement.classList.remove('dark');
            localStorage.setItem('theme', 'light');
          }
        });
    }
});
</script>
</body>
</html>
