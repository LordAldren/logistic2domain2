<?php
session_start();
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) { header("location: login.php"); exit; }
require_once 'db_connect.php';
$message = '';
// CSV Download Logic (omitted for brevity, no changes)
// Expense CRUD Logic (omitted for brevity, no changes)

// --- Fetch Data ---
$budgets = $conn->query("SELECT * FROM budgets ORDER BY start_date DESC");
$expenses = $conn->query("SELECT * FROM expenses ORDER BY expense_date DESC");
$current_budget_query = $conn->query("SELECT * FROM budgets WHERE CURDATE() BETWEEN start_date AND end_date LIMIT 1");
$current_budget = $current_budget_query->fetch_assoc();
$total_expenses_this_period = 0; $expense_breakdown_data = [];
if ($current_budget) {
    $exp_stmt = $conn->prepare("SELECT SUM(amount) as total FROM expenses WHERE expense_date BETWEEN ? AND ?");
    $exp_stmt->bind_param("ss", $current_budget['start_date'], $current_budget['end_date']); $exp_stmt->execute();
    $total_expenses_this_period = $exp_stmt->get_result()->fetch_assoc()['total'] ?? 0;
    $breakdown_stmt = $conn->prepare("SELECT expense_category, SUM(amount) as total_amount FROM expenses WHERE expense_date BETWEEN ? AND ? GROUP BY expense_category");
    $breakdown_stmt->bind_param("ss", $current_budget['start_date'], $current_budget['end_date']); $breakdown_stmt->execute();
    $breakdown_result = $breakdown_stmt->get_result();
    while($row = $breakdown_result->fetch_assoc()){ $expense_breakdown_data[] = $row; }
}
$expense_breakdown_json = json_encode($expense_breakdown_data);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"> <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Budget Management | SLATE Logistics</title>
  <link rel="stylesheet" href="style.css"> <script src="https://cdn.tailwindcss.com"></script> <script src="https://unpkg.com/lucide@latest"></script> <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style> .dark .card { background-color: #1f2937; border-color: #374151; } .dark table, .dark p, .dark label, .dark h3, .dark strong { color: #d1d5db; } .dark th { color: #9ca3af; } .dark td { border-bottom-color: #374151; } .dark .form-input { background-color: #374151; border-color: #4b5563; color: #d1d5db; } .dark .progress-bar-container { background-color: #374151; } </style>
</head>
<body class="bg-gray-100 dark:bg-gray-900">
  <?php include 'sidebar.php'; ?>
  <main id="main-content" class="ml-64 transition-all duration-300 ease-in-out">
    <div class="p-6">
        <div class="flex justify-between items-center mb-6"><h1 class="text-3xl font-bold text-gray-800 dark:text-gray-200">Budget & Expense</h1><div class="theme-toggle-container flex items-center gap-2"><span class="text-sm font-medium text-gray-600 dark:text-gray-400">Dark Mode</span><label class="relative inline-flex items-center cursor-pointer"><input type="checkbox" id="themeToggle" class="sr-only peer"><div class="w-11 h-6 bg-gray-200 rounded-full peer peer-focus:ring-4 peer-focus:ring-blue-300 dark:peer-focus:ring-blue-800 dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-blue-600"></div></label></div></div>
        <?php if (!empty($message)) { echo "<div class='mb-4'>" . $message . "</div>"; } ?>
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
            <div class="lg:col-span-2 card bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6">
                <h3 class="text-xl font-semibold">Current Budget Overview</h3>
                <?php if($current_budget): $remaining = $current_budget['amount'] - $total_expenses_this_period; $percentage_used = ($current_budget['amount'] > 0) ? ($total_expenses_this_period / $current_budget['amount']) * 100 : 0; ?>
                    <div class="mt-4 space-y-2 text-sm"><p><strong>Period:</strong> <?php echo date("M d, Y", strtotime($current_budget['start_date'])) . " - " . date("M d, Y", strtotime($current_budget['end_date'])); ?></p><p><strong>Total Budget:</strong> ₱<?php echo number_format($current_budget['amount'], 2); ?></p><p><strong>Expenses to Date:</strong> ₱<?php echo number_format($total_expenses_this_period, 2); ?></p><p><strong>Remaining Budget:</strong> <span class="<?php echo ($remaining < 0) ? 'text-red-500' : 'text-green-500'; ?> font-bold">₱<?php echo number_format($remaining, 2); ?></span></p></div>
                    <div class="progress-bar-container w-full bg-gray-200 rounded-full h-2.5 mt-4"><div class="bg-blue-600 h-2.5 rounded-full" style="width: <?php echo min(100, $percentage_used); ?>%;"></div></div>
                <?php else: ?><p class="mt-4 text-gray-500">No active budget for the current period.</p><?php endif; ?>
            </div>
            <div class="card bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6"><h3 class="text-xl font-semibold">Expense Breakdown</h3><div class="h-48 mt-4"><canvas id="expenseChart"></canvas></div></div>
        </div>
        <div class="card bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6">
             <div class="flex justify-between items-center mb-4"><h3 class="text-xl font-semibold">Expense History</h3><button id="addExpenseBtn" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 flex items-center gap-2 text-sm"><i data-lucide="plus" class="w-4 h-4"></i>Add Expense</button></div>
            <div id="addExpenseFormContainer" class="hidden mb-6 p-4 bg-gray-50 dark:bg-gray-700/50 rounded-lg"><form action='budget_management.php' method='POST'><input type="hidden" name="expense_id"><div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 items-end"><div class='form-group'><label>Category</label><select name='expense_category' class='form-input w-full'><option value="Fuel">Fuel</option><option value="Maintenance">Maintenance</option><option value="Tolls">Tolls</option><option value="Salaries">Salaries</option><option value="Other">Other</option></select></div><div class='form-group'><label>Description</label><input type='text' name='description' class='form-input w-full'></div><div class='form-group'><label>Amount</label><input type='number' step='0.01' name='amount' class='form-input w-full' required></div><div class='form-group'><label>Date</label><input type='date' name='expense_date' class='form-input w-full' required></div></div><div class='flex justify-end mt-4'><button type='submit' name='save_expense' class='btn btn-primary'>Save Expense</button></div></form></div>
            <div class="overflow-x-auto"><table>
                <thead class="bg-gray-50 dark:bg-gray-700 text-xs uppercase"><tr><th class="p-4">Date</th><th class="p-4">Category</th><th class="p-4">Description</th><th class="p-4">Amount</th><th class="p-4">Actions</th></tr></thead>
                <tbody><?php if ($expenses->num_rows > 0): mysqli_data_seek($expenses, 0); while($row = $expenses->fetch_assoc()): ?>
                    <tr class="border-b dark:border-gray-700">
                        <td class="p-4"><?php echo date("M d, Y", strtotime($row['expense_date'])); ?></td><td><?php echo htmlspecialchars($row['expense_category']); ?></td><td><?php echo htmlspecialchars($row['description']); ?></td><td>₱<?php echo number_format($row['amount'], 2); ?></td>
                        <td class="p-4"><a href="budget_management.php?delete_expense=<?php echo $row['id']; ?>" class="text-red-500 hover:underline text-xs" onclick="return confirm('Are you sure?');">Delete</a></td>
                    </tr><?php endwhile; else: ?><tr><td colspan="5" class="text-center p-6">No expenses recorded.</td></tr><?php endif; ?>
                </tbody>
            </table></div>
        </div>
    </div>
  </main>
<script>
document.addEventListener('DOMContentLoaded', function() {
    lucide.createIcons();
    const themeToggle=document.getElementById('themeToggle');if(localStorage.getItem('theme')==='dark'||(!('theme'in localStorage)&&window.matchMedia('(prefers-color-scheme: dark)').matches)){document.documentElement.classList.add('dark');if(themeToggle)themeToggle.checked=true;}else{document.documentElement.classList.remove('dark');if(themeToggle)themeToggle.checked=false;}if(themeToggle){themeToggle.addEventListener('change',function(){if(this.checked){document.documentElement.classList.add('dark');localStorage.setItem('theme','dark');}else{document.documentElement.classList.remove('dark');localStorage.setItem('theme','light');}});}
    document.getElementById('addExpenseBtn').addEventListener('click', () => document.getElementById('addExpenseFormContainer').classList.toggle('hidden'));
    const expenseData = <?php echo $expense_breakdown_json; ?>;
    const expenseChartCtx = document.getElementById('expenseChart');
    if (expenseChartCtx && expenseData.length > 0) { new Chart(expenseChartCtx, { type: 'pie', data: { labels: expenseData.map(i=>i.expense_category), datasets: [{ data: expenseData.map(i=>i.total_amount), backgroundColor: ['#4A6CF7','#F59E0B','#10B981','#EF4444','#64748B'], hoverOffset: 4 }] }, options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } } }); } else if (expenseChartCtx) { const ctx=expenseChartCtx.getContext('2d'); ctx.textAlign='center'; ctx.textBaseline='middle'; ctx.fillText('No expense data for this period.', expenseChartCtx.width/2, expenseChartCtx.height/2); }
});
</script>
</body>
</html>
