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

// --- PANG-HANDLE NG CSV DOWNLOAD ---
if (isset($_GET['download_csv'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=vehicle_list_' . date('Y-m-d') . '.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Type', 'Model', 'Tag Type', 'Tag Code', 'Capacity (KG)', 'Plate No', 'Status', 'Assigned Driver']);
    
    $search_query_csv = isset($_GET['query']) ? $conn->real_escape_string($_GET['query']) : '';
    $where_clause_csv = '';
    if (!empty($search_query_csv)) {
        $where_clause_csv = "WHERE v.type LIKE '%$search_query_csv%' OR v.model LIKE '%$search_query_csv%' OR v.tag_code LIKE '%$search_query_csv%' OR v.plate_no LIKE '%$search_query_csv%' OR v.status LIKE '%$search_query_csv%'";
    }
    
    $result = $conn->query("SELECT v.*, d.name as driver_name FROM vehicles v LEFT JOIN drivers d ON v.assigned_driver_id = d.id $where_clause_csv ORDER BY v.id DESC");
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            fputcsv($output, [ $row['id'], $row['type'], $row['model'], $row['tag_type'], $row['tag_code'], $row['load_capacity_kg'], $row['plate_no'], $row['status'], $row['driver_name'] ?? 'N/A' ]);
        }
    }
    fclose($output);
    exit;
}

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
  <title>Vehicle List | SLATE Logistics</title>
  
  <link rel="stylesheet" href="style.css">
  <link rel="stylesheet" href="loader.css">
  
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://unpkg.com/lucide@latest"></script>
  
  <style>
    .dark .card {
        background-color: #1f2937;
        border: 1px solid #374151;
    }
    .dark table { color: #d1d5db; }
    .dark th { color: #9ca3af; }
    .dark td { border-bottom-color: #374151; }
    .dark .form-input {
        background-color: #374151;
        border-color: #4b5563;
        color: #d1d5db;
    }
    .dark .modal-content {
        background-color: #1f2937;
    }
  </style>
</head>
<body class="bg-gray-100 dark:bg-gray-900">

  <?php include 'sidebar.php'; ?> 

  <main id="main-content" class="ml-64 transition-all duration-300 ease-in-out">
    <div class="p-6">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-3xl font-bold text-gray-800 dark:text-gray-200">Vehicle List</h1>
            <div class="theme-toggle-container flex items-center gap-2">
                <span class="text-sm font-medium text-gray-600 dark:text-gray-400">Dark Mode</span>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" id="themeToggle" class="sr-only peer">
                    <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-focus:ring-4 peer-focus:ring-blue-300 dark:peer-focus:ring-blue-800 dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-blue-600"></div>
                </label>
            </div>
        </div>

        <div class="card bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6">
            <div class="flex flex-col md:flex-row justify-between items-center mb-4 gap-4">
                <h3 class="text-xl font-semibold text-gray-700 dark:text-gray-300">All Registered Vehicles</h3>
                <div class="flex items-center gap-2">
                    <form action="vehicle_list.php" method="GET" class="flex gap-2">
                        <input type="text" name="query" class="form-input w-full md:w-64 px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Search..." value="<?php echo htmlspecialchars($search_query); ?>">
                        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition duration-300 flex items-center gap-2">
                            <i data-lucide="search" class="w-4 h-4"></i> Search
                        </button>
                    </form>
                    <a href="vehicle_list.php?download_csv=true&query=<?php echo urlencode($search_query); ?>" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition duration-300 flex items-center gap-2">
                        <i data-lucide="download" class="w-4 h-4"></i> CSV
                    </a>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-gray-600 dark:text-gray-400">
                    <thead class="bg-gray-50 dark:bg-gray-700 text-xs uppercase text-gray-700 dark:text-gray-400">
                        <tr>
                            <th class="p-4">ID</th>
                            <th class="p-4">Vehicle Type</th>
                            <th class="p-4">Model</th>
                            <th class="p-4">Tag Code</th>
                            <th class="p-4">Capacity (KG)</th>
                            <th class="p-4">Plate No</th>
                            <th class="p-4">Status</th>
                            <th class="p-4">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($vehicles_result->num_rows > 0): ?>
                            <?php while($row = $vehicles_result->fetch_assoc()): ?>
                            <tr class="border-b dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
                                <td class="p-4 font-medium"><?php echo $row['id']; ?></td>
                                <td class="p-4"><?php echo htmlspecialchars($row['type']); ?></td>
                                <td class="p-4"><?php echo htmlspecialchars($row['model']); ?></td>
                                <td class="p-4"><?php echo htmlspecialchars($row['tag_code']); ?></td>
                                <td class="p-4"><?php echo htmlspecialchars($row['load_capacity_kg']); ?> kg</td>
                                <td class="p-4"><?php echo htmlspecialchars($row['plate_no']); ?></td>
                                <td class="p-4"><span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $row['status'])); ?>"><?php echo htmlspecialchars($row['status']); ?></span></td>
                                <td class="p-4">
                                    <button class="viewVehicleBtn text-blue-500 hover:underline"
                                        data-id="<?php echo $row['id']; ?>" data-type="<?php echo htmlspecialchars($row['type']); ?>"
                                        data-model="<?php echo htmlspecialchars($row['model']); ?>" data-tag_type="<?php echo htmlspecialchars($row['tag_type']); ?>"
                                        data-tag_code="<?php echo htmlspecialchars($row['tag_code']); ?>" data-load_capacity_kg="<?php echo htmlspecialchars($row['load_capacity_kg']); ?>"
                                        data-plate_no="<?php echo htmlspecialchars($row['plate_no']); ?>" data-status="<?php echo htmlspecialchars($row['status']); ?>">
                                        View
                                    </button>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="8" class="text-center p-6">No vehicles found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
  </main>
  
  <!-- View Vehicle Modal -->
  <div id="viewVehicleModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden items-center justify-center">
    <div class="modal-content bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-lg p-6 relative">
      <button class="close-button absolute top-4 right-4 text-gray-500 hover:text-gray-800 dark:hover:text-gray-200">
        <i data-lucide="x" class="w-6 h-6"></i>
      </button>
      <h2 class="text-2xl font-bold mb-4 text-gray-800 dark:text-gray-200">Vehicle Details</h2>
      <div id="viewVehicleBody" class="space-y-3 text-gray-700 dark:text-gray-300"></div>
    </div>
  </div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const viewVehicleModal = document.getElementById('viewVehicleModal');
    const viewVehicleBody = document.getElementById('viewVehicleBody');
    const closeBtn = viewVehicleModal.querySelector('.close-button');

    function showModal() { viewVehicleModal.classList.remove('hidden'); viewVehicleModal.classList.add('flex'); }
    function hideModal() { viewVehicleModal.classList.add('hidden'); viewVehicleModal.classList.remove('flex'); }

    closeBtn.addEventListener('click', hideModal);
    
    document.querySelectorAll('.viewVehicleBtn').forEach(button => {
        button.addEventListener('click', () => {
            const model = button.dataset.model.toLowerCase();
            let imageUrl = `https://placehold.co/400x300/e2e8f0/e2e8f0?text=No+Image`;
            if (model.includes('elf')) imageUrl = `elf.PNG`;
            else if (model.includes('hiace')) imageUrl = `hiace.PNG`;
            else if (model.includes('canter')) imageUrl = `canter.PNG`;

            const status = button.dataset.status;
            const statusClass = status.toLowerCase().replace(/\s+/g, '-');
            
            const detailsHtml = `
                <img src="${imageUrl}" alt="${button.dataset.type}" class="w-full h-auto max-h-64 object-cover rounded-lg mb-4">
                <div class="grid grid-cols-2 gap-x-4 gap-y-2">
                    <p><strong>ID:</strong></p> <p>${button.dataset.id}</p>
                    <p><strong>Type:</strong></p> <p>${button.dataset.type}</p>
                    <p><strong>Model:</strong></p> <p>${button.dataset.model}</p>
                    <p><strong>Plate No.:</strong></p> <p>${button.dataset.plate_no}</p>
                    <p><strong>Tag Type:</strong></p> <p>${button.dataset.tag_type}</p>
                    <p><strong>Tag Code:</strong></p> <p>${button.dataset.tag_code}</p>
                    <p><strong>Capacity:</strong></p> <p>${button.dataset.load_capacity_kg} kg</p>
                    <p><strong>Status:</strong></p> <p><span class="status-badge status-${statusClass}">${status}</span></p>
                </div>
            `;
            viewVehicleBody.innerHTML = detailsHtml;
            showModal();
        });
    });
    
    lucide.createIcons();

    // Dark Mode Handler
    const themeToggle = document.getElementById('themeToggle');
    if (localStorage.getItem('theme') === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
      document.documentElement.classList.add('dark');
      themeToggle.checked = true;
    } else {
      document.documentElement.classList.remove('dark');
      themeToggle.checked = false;
    }

    themeToggle.addEventListener('change', function() {
      if (this.checked) {
        document.documentElement.classList.add('dark');
        localStorage.setItem('theme', 'dark');
      } else {
        document.documentElement.classList.remove('dark');
        localStorage.setItem('theme', 'light');
      }
    });
});
</script>
</body>
</html>
