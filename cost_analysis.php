<?php
session_start();
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}

// RBAC check - Admin lang ang pwedeng pumasok dito
if ($_SESSION['role'] !== 'admin') {
    if ($_SESSION['role'] === 'driver') {
        header("location: mobile_app.php");
    } else {
        header("location: landpage.php");
    }
    exit;
}

require_once 'db_connect.php';

// Fetch Data for AI and Tables
$cost_prediction_data = $conn->query("SELECT tc.tolls_cost, tc.total_cost FROM trip_costs tc");
$prediction_json = json_encode($cost_prediction_data->fetch_all(MYSQLI_ASSOC));

$daily_costs_table_result = $conn->query("
    SELECT
        DATE(t.pickup_time) as trip_date,
        SUM(tc.fuel_cost) as total_fuel,
        SUM(tc.labor_cost) as total_labor,
        SUM(tc.tolls_cost) as total_tolls,
        SUM(tc.total_cost) as grand_total
    FROM trip_costs tc
    JOIN trips t ON tc.trip_id = t.id
    GROUP BY DATE(t.pickup_time)
    ORDER BY trip_date DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Cost Analysis | TCAO</title>
  <link rel="stylesheet" href="style.css">
  <script src="https://cdn.jsdelivr.net/npm/@tensorflow/tfjs@latest/dist/tf.min.js"></script>
</head>
<body>
  <?php include 'sidebar.php'; ?>

  <div class="content" id="mainContent">
    <div class="header">
      <div class="hamburger" id="hamburger">☰</div>
      <div><h1>Cost Analysis & Optimization</h1></div>
      <div class="theme-toggle-container">
        <span class="theme-label">Dark Mode</span>
        <label class="theme-switch"><input type="checkbox" id="themeToggle"><span class="slider"></span></label>
      </div>
    </div>
    
    <div class="card">
        <h3>AI Per-Trip Cost Predictor</h3>
        <p>This simple AI model predicts the total trip cost based on the estimated toll fees.</p>
        <div id="ai-predictor-form" style="margin-top: 1rem; display: none;">
            <div class="form-group">
                <label for="tolls">Estimated Tolls (₱)</label>
                <input type="number" id="tolls" placeholder="e.g., 350.50" class="form-control">
            </div>
            <button id="predictBtn" class="btn btn-primary">Predict Cost</button>
            <div id="prediction-output" style="margin-top: 1rem;"></div>
        </div>
        <div id="ai-status" style="margin-top: 1rem;">Training AI model...</div>
    </div>
    <div class="card">
        <h3>Daily Cost Breakdown Engine</h3>
        <div class="table-section">
            <table>
                <thead><tr><th>Date</th><th>Total Fuel</th><th>Total Labor</th><th>Total Tolls</th><th>Grand Total</th></tr></thead>
                <tbody>
                    <?php if ($daily_costs_table_result && $daily_costs_table_result->num_rows > 0): ?>
                        <?php while($row = $daily_costs_table_result->fetch_assoc()): ?>
                        <tr>
                            <td><strong><?php echo date("M d, Y", strtotime($row['trip_date'])); ?></strong></td>
                            <td>₱<?php echo number_format($row['total_fuel'], 2); ?></td>
                            <td>₱<?php echo number_format($row['total_labor'], 2); ?></td>
                            <td>₱<?php echo number_format($row['total_tolls'], 2); ?></td>
                            <td><strong>₱<?php echo number_format($row['grand_total'], 2); ?></strong></td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="5">No daily cost data found. Add trip costs to see analysis.</td></tr>
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
                if (parent.classList.contains('open')) {
                    menu.style.maxHeight = menu.scrollHeight + 'px';
                } else {
                    menu.style.maxHeight = '0';
                }
            });
        });

        const predictionData = <?php echo $prediction_json; ?>;
        let costModel;

        async function trainCostModel() {
            const aiStatus = document.getElementById('ai-status');
            if (predictionData.length < 2) {
                aiStatus.textContent = 'Not enough data for AI model.';
                aiStatus.style.color = 'var(--warning-color)';
                return;
            }
            tf.util.shuffle(predictionData);
            
            const features = predictionData.map(d => parseFloat(d.tolls_cost) || 0);
            const labels = predictionData.map(d => parseFloat(d.total_cost));
            const featureTensor = tf.tensor2d(features, [features.length, 1]);
            const labelTensor = tf.tensor2d(labels, [labels.length, 1]);
            
            costModel = tf.sequential();
            costModel.add(tf.layers.dense({ inputShape: [1], units: 1 }));
            costModel.compile({ optimizer: 'adam', loss: 'meanSquaredError' });
            
            await costModel.fit(featureTensor, labelTensor, { epochs: 50 });
            
            aiStatus.textContent = 'AI Model is ready.';
            aiStatus.style.color = 'var(--success-color)';
            document.getElementById('ai-predictor-form').style.display = 'block';
        }
        
        document.getElementById('predictBtn').addEventListener('click', () => {
            const tolls = parseFloat(document.getElementById('tolls').value);
            const output = document.getElementById('prediction-output');
            
            if (isNaN(tolls)) {
                output.innerHTML = `<div class='message-banner error'>Please enter a valid number for tolls.</div>`;
                return;
            }
            
            const inputTensor = tf.tensor2d([[tolls]]);
            const prediction = costModel.predict(inputTensor);
            const predictedCost = prediction.dataSync()[0];
            output.innerHTML = `<div class='message-banner success'><strong>Predicted Total Cost:</strong> ₱${predictedCost.toFixed(2)}</div>`;
        });
        
        trainCostModel(); 
    });
  </script>
  <script src="dark_mode_handler.js" defer></script>
</body>
</html>
