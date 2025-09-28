<?php
session_start();
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}
if ($_SESSION['role'] !== 'admin') {
    header("location: " . ($_SESSION['role'] === 'driver' ? 'mobile_app.php' : 'landpage.php'));
    exit;
}
require_once 'db_connect.php';

$cost_prediction_data = $conn->query("SELECT tc.tolls_cost, tc.total_cost FROM trip_costs tc");
$prediction_json = json_encode($cost_prediction_data->fetch_all(MYSQLI_ASSOC));

$daily_costs_table_result = $conn->query("SELECT DATE(t.pickup_time) as trip_date, SUM(tc.fuel_cost) as total_fuel, SUM(tc.labor_cost) as total_labor, SUM(tc.tolls_cost) as total_tolls, SUM(tc.total_cost) as grand_total FROM trip_costs tc JOIN trips t ON tc.trip_id = t.id GROUP BY DATE(t.pickup_time) ORDER BY trip_date DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"> <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cost Analysis | SLATE Logistics</title>
    <link rel="stylesheet" href="style.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/@tensorflow/tfjs@latest/dist/tf.min.js"></script>
    <style> .dark .card { background-color: #1f2937; border-color: #374151; } .dark table, .dark p, .dark label, .dark h3 { color: #d1d5db; } .dark th { color: #9ca3af; } .dark td { border-bottom-color: #374151; } .dark .form-input { background-color: #374151; border-color: #4b5563; color: #d1d5db; } </style>
</head>
<body class="bg-gray-100 dark:bg-gray-900">
  <?php include 'sidebar.php'; ?>
  <main id="main-content" class="ml-64 transition-all duration-300 ease-in-out">
    <div class="p-6">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-3xl font-bold text-gray-800 dark:text-gray-200">Cost Analysis & Optimization</h1>
            <div class="theme-toggle-container flex items-center gap-2"><span class="text-sm font-medium text-gray-600 dark:text-gray-400">Dark Mode</span><label class="relative inline-flex items-center cursor-pointer"><input type="checkbox" id="themeToggle" class="sr-only peer"><div class="w-11 h-6 bg-gray-200 rounded-full peer peer-focus:ring-4 peer-focus:ring-blue-300 dark:peer-focus:ring-blue-800 dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-blue-600"></div></label></div>
        </div>
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-1">
                <div class="card bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6">
                    <h3 class="text-xl font-semibold text-gray-700 dark:text-gray-300">AI Cost Predictor</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-2">Predicts total trip cost based on estimated toll fees.</p>
                    <div id="ai-predictor-form" class="mt-4" style="display: none;">
                        <div class="form-group mb-4">
                            <label for="tolls" class="block text-sm font-medium mb-1">Estimated Tolls (₱)</label>
                            <input type="number" id="tolls" placeholder="e.g., 350.50" class="form-input w-full">
                        </div>
                        <button id="predictBtn" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 w-full">Predict Cost</button>
                        <div id="prediction-output" class="mt-4"></div>
                    </div>
                    <div id="ai-status" class="mt-4 text-sm font-medium">Training AI model...</div>
                </div>
            </div>
            <div class="lg:col-span-2">
                <div class="card bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6">
                    <h3 class="text-xl font-semibold text-gray-700 dark:text-gray-300">Daily Cost Breakdown</h3>
                    <div class="overflow-x-auto mt-4">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-gray-50 dark:bg-gray-700 text-xs uppercase"><tr><th class="p-4">Date</th><th class="p-4">Total Fuel</th><th class="p-4">Total Labor</th><th class="p-4">Total Tolls</th><th class="p-4">Grand Total</th></tr></thead>
                            <tbody>
                                <?php if ($daily_costs_table_result && $daily_costs_table_result->num_rows > 0): ?>
                                    <?php while($row = $daily_costs_table_result->fetch_assoc()): ?>
                                    <tr class="border-b dark:border-gray-700">
                                        <td class="p-4 font-bold"><?php echo date("M d, Y", strtotime($row['trip_date'])); ?></td>
                                        <td class="p-4">₱<?php echo number_format($row['total_fuel'], 2); ?></td>
                                        <td class="p-4">₱<?php echo number_format($row['total_labor'], 2); ?></td>
                                        <td class="p-4">₱<?php echo number_format($row['total_tolls'], 2); ?></td>
                                        <td class="p-4 font-bold">₱<?php echo number_format($row['grand_total'], 2); ?></td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="5" class="text-center p-6">No daily cost data found.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
  </main>
<script>
document.addEventListener('DOMContentLoaded', function() {
    lucide.createIcons();
    const themeToggle = document.getElementById('themeToggle');
    if(themeToggle) { if (localStorage.getItem('theme')==='dark'||(!('theme'in localStorage)&&window.matchMedia('(prefers-color-scheme: dark)').matches)){document.documentElement.classList.add('dark');themeToggle.checked=true;}else{document.documentElement.classList.remove('dark');themeToggle.checked=false;} themeToggle.addEventListener('change',function(){if(this.checked){document.documentElement.classList.add('dark');localStorage.setItem('theme','dark');}else{document.documentElement.classList.remove('dark');localStorage.setItem('theme','light');}}); }
    const predictionData = <?php echo $prediction_json; ?>; let costModel;
    async function trainCostModel() { const aiStatus = document.getElementById('ai-status'); if (predictionData.length < 2) { aiStatus.textContent = 'Not enough data for AI model.'; return; } tf.util.shuffle(predictionData); const features = predictionData.map(d => parseFloat(d.tolls_cost) || 0); const labels = predictionData.map(d => parseFloat(d.total_cost)); const featureTensor = tf.tensor2d(features, [features.length, 1]); const labelTensor = tf.tensor2d(labels, [labels.length, 1]); costModel = tf.sequential(); costModel.add(tf.layers.dense({ inputShape: [1], units: 1 })); costModel.compile({ optimizer: 'adam', loss: 'meanSquaredError' }); await costModel.fit(featureTensor, labelTensor, { epochs: 50 }); aiStatus.textContent = 'AI Model is ready.'; aiStatus.style.color = '#10B981'; document.getElementById('ai-predictor-form').style.display = 'block'; }
    document.getElementById('predictBtn').addEventListener('click', () => { const tolls = parseFloat(document.getElementById('tolls').value); const output = document.getElementById('prediction-output'); if (isNaN(tolls)) { output.innerHTML = `<div class='message-banner error'>Please enter a valid number.</div>`; return; } const inputTensor = tf.tensor2d([[tolls]]); const prediction = costModel.predict(inputTensor); const predictedCost = prediction.dataSync()[0]; output.innerHTML = `<div class='message-banner success'><strong>Predicted Total Cost:</strong> ₱${predictedCost.toFixed(2)}</div>`; });
    trainCostModel(); 
});
</script>
</body>
</html>
