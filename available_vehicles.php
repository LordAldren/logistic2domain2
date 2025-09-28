<?php
session_start();
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}

// RBAC check - Admin at Staff lang ang pwedeng pumasok dito
if (!in_array($_SESSION['role'], ['admin', 'staff'])) {
    if ($_SESSION['role'] === 'driver') {
        header("location: mobile_app.php");
    } else {
        header("location: landpage.php");
    }
    exit;
}

require_once 'db_connect.php';
$message = '';
$current_user_id = $_SESSION['id'];

// --- DATA FETCHING ---
$available_vehicles_query = $conn->query("SELECT id, type, model, load_capacity_kg, image_url FROM vehicles WHERE status IN ('Active', 'Idle') ORDER BY type, model");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Available Vehicles | VRDS</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <?php include 'sidebar.php'; ?>

  <div class="content" id="mainContent">
    <div class="header">
        <div class="hamburger" id="hamburger">☰</div>
        <div><h1>Available Vehicles for Reservation</h1></div>
        <div class="theme-toggle-container">
            <span class="theme-label">Dark Mode</span>
            <label class="theme-switch"><input type="checkbox" id="themeToggle"><span class="slider"></span></label>
        </div>
    </div>
    
    <?php echo $message; ?>

    <div class="card">
        <h3>Vehicle Fleet</h3>
        <p>This is a view-only list of all available vehicles. To create a new reservation, please go to the <a href="reservation_booking.php">Reservation Booking</a> page.</p>
        <div class="vehicle-gallery" style="margin-top: 1.5rem;">
            <?php if ($available_vehicles_query->num_rows > 0): mysqli_data_seek($available_vehicles_query, 0); ?>
                <?php while($row = $available_vehicles_query->fetch_assoc()): 
                    $image = 'https://placehold.co/400x300/e2e8f0/e2e8f0?text=No+Image';
                    if (stripos($row['model'], 'elf') !== false) $image = 'elf.PNG';
                    if (stripos($row['model'], 'hiace') !== false) $image = 'hiace.PNG';
                    if (stripos($row['model'], 'canter') !== false) $image = 'canter.PNG';
                ?>
                <div class="vehicle-card">
                    <img src="<?php echo htmlspecialchars($image); ?>" alt="<?php echo htmlspecialchars($row['type']); ?>" class="vehicle-image">
                    <div class="vehicle-details">
                        <div>
                            <div class="vehicle-title"><?php echo htmlspecialchars($row['type'] . ' - ' . $row['model']); ?></div>
                            <div class="vehicle-info">Capacity: <?php echo htmlspecialchars($row['load_capacity_kg']); ?> kg</div>
                        </div>
                         <a href="vehicle_list.php?query=<?php echo urlencode($row['model']); ?>" class="btn btn-info" style="margin-top: 1rem; width: 100%;">View in FVM</a>
                    </div>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p>No vehicles are currently available.</p>
            <?php endif; ?>
        </div>
    </div>
  </div>
  
<script>
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('hamburger').addEventListener('click', function() {
      const sidebar = document.getElementById('sidebar'); const mainContent = document.getElementById('mainContent');
     if (window.innerWidth <= 992) { sidebar.classList.toggle('show'); } else { sidebar.classList.toggle('collapsed'); mainContent.classList.toggle('expanded'); }
    });
    
    const activeDropdown = document.querySelector('.sidebar .dropdown.active');
    if (activeDropdown) {
        activeDropdown.classList.add('open');
        const menu = activeDropdown.querySelector('.dropdown-menu');
        if (menu) {
            menu.style.maxHeight = menu.scrollHeight + 'px';
        }
    }
    document.querySelectorAll('.sidebar .dropdown-toggle').forEach(function(toggle) {
        toggle.addEventListener('click', function(e) {
            e.preventDefault();
            let parent = this.closest('.dropdown');
            let menu = parent.querySelector('.dropdown-menu');
            
            document.querySelectorAll('.sidebar .dropdown.open').forEach(function(otherDropdown) {
                if (otherDropdown !== parent) {
                    otherDropdown.classList.remove('open');
                    otherDropdown.querySelector('.dropdown-menu').style.maxHeight = '0';
                }
            });

            parent.classList.toggle('open');
            if (parent.classList.contains('open')) {
                menu.style.maxHeight = menu.scrollHeight + 'px';
            } else {
                menu.style.maxHeight = '0';
            }
        });
    });
});
</script>
<script src="dark_mode_handler.js" defer></script>
</body>
</html>
