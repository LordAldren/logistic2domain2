<?php
session_start();
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}
if ($_SESSION['role'] === 'driver') {
    header("location: mobile_app.php");
    exit;
}
require_once 'db_connect.php';
$message = '';

// --- PANG-HANDLE NG CSV DOWNLOAD ---
if (isset($_GET['download_csv'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=vehicle_list_' . date('Y-m-d') . '.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Type', 'Model', 'Tag Type', 'Tag Code', 'Capacity (KG)', 'Plate No', 'Status', 'Assigned Driver']);

    // Gumamit ng parehong filter logic para sa export
    $search_query_csv = isset($_GET['query']) ? $conn->real_escape_string($_GET['query']) : '';
    $where_clause_csv = '';
    if (!empty($search_query_csv)) {
        $where_clause_csv = "WHERE v.type LIKE '%$search_query_csv%' OR v.model LIKE '%$search_query_csv%' OR v.tag_code LIKE '%$search_query_csv%' OR v.plate_no LIKE '%$search_query_csv%' OR v.status LIKE '%$search_query_csv%'";
    }
    
    $result = $conn->query("SELECT v.*, d.name as driver_name FROM vehicles v LEFT JOIN drivers d ON v.assigned_driver_id = d.id $where_clause_csv ORDER BY v.id DESC");
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            fputcsv($output, [
                $row['id'],
                $row['type'],
                $row['model'],
                $row['tag_type'],
                $row['tag_code'],
                $row['load_capacity_kg'],
                $row['plate_no'],
                $row['status'],
                $row['driver_name'] ?? 'N/A'
            ]);
        }
    }
    fclose($output);
    exit;
}
// --- WAKAS NG CSV DOWNLOAD LOGIC ---


$search_query = isset($_GET['query']) ? $conn->real_escape_string($_GET['query']) : '';
$where_clause = '';
if (!empty($search_query)) {
    $where_clause = "WHERE v.type LIKE '%$search_query%' OR v.model LIKE '%$search_query%' OR v.tag_code LIKE '%$search_query%' OR v.plate_no LIKE '%$search_query%' OR v.status LIKE '%$search_query%'";
}

$vehicles_result = $conn->query("SELECT v.* FROM vehicles v $where_clause ORDER BY v.id DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Vehicle List | FVM</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <?php include 'sidebar.php'; ?>

  <div class="content" id="mainContent">
    <div class="header">
      <div class="hamburger" id="hamburger">☰</div>
      <div><h1>Vehicle List</h1></div>
      <div class="theme-toggle-container">
        <span class="theme-label">Dark Mode</span>
        <label class="theme-switch"><input type="checkbox" id="themeToggle"><span class="slider"></span></label>
      </div>
    </div>
    
    <?php echo $message; ?>

    <div class="card" id="vehicle-list">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
        <h3>All Registered Vehicles</h3>
        <div style="display: flex; gap: 0.5rem; align-items: center;">
            <form action="vehicle_list.php" method="GET" style="display: flex; gap: 0.5rem;">
              <input type="text" name="query" class="form-control" placeholder="Search for vehicle..." value="<?php echo htmlspecialchars($search_query); ?>">
              <button type="submit" class="btn btn-primary">Search</button>
            </form>
            <a href="vehicle_list.php?download_csv=true&<?php echo http_build_query($_GET); ?>" class="btn btn-success">Download CSV</a>
        </div>
      </div>

      <div id="viewVehicleModal" class="modal">
        <div class="modal-content">
          <span class="close-button">&times;</span>
          <h2>Vehicle Details</h2>
          <div id="viewVehicleBody" style="line-height: 1.8;"></div>
           <div class="form-actions">
               <button type="button" class="btn btn-secondary cancelBtn">Close</button>
           </div>
        </div>
      </div>

      <div class="table-section">
        <table>
          <thead>
            <tr>
              <th>ID</th><th>Vehicle Type</th><th>Model</th><th>Tag Code</th><th>Capacity (KG)</th><th>Plate No</th><th>Status</th><th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if($vehicles_result->num_rows > 0): ?>
              <?php while($row = $vehicles_result->fetch_assoc()): ?>
              <tr>
                <td><?php echo $row['id']; ?></td>
                <td><?php echo htmlspecialchars($row['type']); ?></td>
                <td><?php echo htmlspecialchars($row['model']); ?></td>
                <td><?php echo htmlspecialchars($row['tag_code']); ?></td>
                <td><?php echo htmlspecialchars($row['load_capacity_kg']); ?> kg</td>
                <td><?php echo htmlspecialchars($row['plate_no']); ?></td>
                <td><span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $row['status'])); ?>"><?php echo htmlspecialchars($row['status']); ?></span></td>
                <td>
                  <button class="btn btn-info btn-sm viewVehicleBtn"
                    data-id="<?php echo $row['id']; ?>"
                    data-type="<?php echo htmlspecialchars($row['type']); ?>"
                    data-model="<?php echo htmlspecialchars($row['model']); ?>"
                    data-tag_type="<?php echo htmlspecialchars($row['tag_type']); ?>"
                    data-tag_code="<?php echo htmlspecialchars($row['tag_code']); ?>"
                    data-load_capacity_kg="<?php echo htmlspecialchars($row['load_capacity_kg']); ?>"
                    data-plate_no="<?php echo htmlspecialchars($row['plate_no']); ?>"
                    data-status="<?php echo htmlspecialchars($row['status']); ?>">View</button>
                </td>
              </tr>
              <?php endwhile; ?>
            <?php else: ?>
              <tr><td colspan="8">No vehicles found.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <script>
    document.addEventListener('DOMContentLoaded', function() {
        document.getElementById('hamburger').addEventListener('click', function() {
         const sidebar = document.getElementById('sidebar');
          const mainContent = document.getElementById('mainContent');
          if (window.innerWidth <= 992) { sidebar.classList.toggle('show'); } 
          else { sidebar.classList.toggle('collapsed'); mainContent.classList.toggle('expanded'); }
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
                if (parent.classList.contains('open')) { menu.style.maxHeight = menu.scrollHeight + 'px'; } 
                else { menu.style.maxHeight = '0'; }
            });
        });

        document.querySelectorAll('.modal').forEach(modal => {
            const closeBtn = modal.querySelector('.close-button');
            const cancelBtn = modal.querySelector('.cancelBtn');
            if(closeBtn) { closeBtn.addEventListener('click', () => modal.style.display = 'none'); }
            if(cancelBtn) { cancelBtn.addEventListener('click', () => modal.style.display = 'none'); }
        });

        const viewVehicleModal = document.getElementById('viewVehicleModal');
        const viewVehicleBody = document.getElementById('viewVehicleBody');
        document.querySelectorAll('.viewVehicleBtn').forEach(button => {
          button.addEventListener('click', () => {
            const model = button.dataset.model.toLowerCase();
            const type = button.dataset.type.toLowerCase();
            let imageUrl;

            if (model.includes('elf')) { imageUrl = `elf.PNG`; } 
            else if (model.includes('hiace')) { imageUrl = `hiace.PNG`; } 
            else if (model.includes('canter')) { imageUrl = `canter.PNG`; }
            else { imageUrl = 'https://placehold.co/400x300/e2e8f0/e2e8f0?text=No+Image'; }

            const status = button.dataset.status;
            const statusClass = status.toLowerCase().replace(' ', '-');

            const detailsHtml = `
                <img src="${imageUrl}" alt="${button.dataset.type}" style="width: 100%; height: auto; max-height: 250px; object-fit: cover; border-radius: 0.35rem; margin-bottom: 1rem;">
                <p><strong>ID:</strong> ${button.dataset.id}</p>
                <p><strong>Type:</strong> ${button.dataset.type}</p>
                <p><strong>Model:</strong> ${button.dataset.model}</p>
                <p><strong>Plate No.:</strong> ${button.dataset.plate_no}</p>
                <p><strong>Tag Type:</strong> ${button.dataset.tag_type}</p>
                <p><strong>Tag Code:</strong> ${button.dataset.tag_code}</p>
                <p><strong>Capacity:</strong> ${button.dataset.load_capacity_kg} kg</p>
                <p><strong>Status:</strong> <span class="status-badge status-${statusClass}">${status}</span></p>
            `;
            viewVehicleBody.innerHTML = detailsHtml;
            viewVehicleModal.style.display = 'block';
          });
        });
    });
  </script>
  <script src="dark_mode_handler.js" defer></script>
</body>
</html>
