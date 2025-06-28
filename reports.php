<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$conn = new mysqli("localhost", "root", "", "mobipay");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Initialize all variables
$manager_name = 'Admin';
$report_data = [];
$all_types = [
    'topup' => 'Top Up',
    'send' => 'Send Money',
    'received' => 'Received Money',
    'cashout' => 'Cash Out'
];

// Get manager name
$manager_id = $_SESSION['user_id'];
$sql = "SELECT full_name FROM users WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $manager_id);
$stmt->execute();
$stmt->bind_result($manager_name);
$stmt->fetch();
$stmt->close();

// Report configuration
$report_types = [
    'transactions' => 'Transaction Details',
    'financial' => 'Financial Summary',
    'users' => 'User Activity',
    'agents' => 'Agent Performance'
];

$date_ranges = [
    'today' => 'Today',
    'yesterday' => 'Yesterday',
    'week' => 'This Week',
    'month' => 'This Month',
    'quarter' => 'This Quarter',
    'year' => 'This Year',
    'custom' => 'Custom Range'
];

// Get filter data
$all_users = [];
$users_result = $conn->query("SELECT user_id, full_name FROM users WHERE role = 'user' ORDER BY full_name");
while ($row = $users_result->fetch_assoc()) {
    $all_users[$row['user_id']] = $row['full_name'];
}

$all_agents = [];
$agents_result = $conn->query("SELECT AgentId, AgentName FROM agents ORDER BY AgentName");
while ($row = $agents_result->fetch_assoc()) {
    $all_agents[$row['AgentId']] = $row['AgentName'];
}

// Process filters with validation
$report_type = $_GET['report_type'] ?? 'transactions';
if (!array_key_exists($report_type, $report_types)) {
    $report_type = 'transactions';
}
$title = $report_types[$report_type] ?? 'Transaction Report';

$date_range = $_GET['date_range'] ?? 'week';
if (!array_key_exists($date_range, $date_ranges)) {
    $date_range = 'week';
}

$start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-7 days'));
$end_date = $_GET['end_date'] ?? date('Y-m-d');

$selected_types = isset($_GET['types']) && is_array($_GET['types']) ? 
    array_intersect($_GET['types'], array_keys($all_types)) : 
    ['topup', 'send', 'received', 'cashout'];

$selected_users = isset($_GET['users']) && is_array($_GET['users']) ? 
    array_filter($_GET['users'], 'is_numeric') : 
    [];

$selected_agents = isset($_GET['agents']) && is_array($_GET['agents']) ? 
    array_filter($_GET['agents'], 'is_numeric') : 
    [];

// Build WHERE clause
$where_clauses = [];
$params = [];
$param_types = "";

// Date range (always applied)
$where_clauses[] = "t.date BETWEEN ? AND ?";
array_push($params, $start_date, $end_date);
$param_types .= "ss";

// Handle user/agent filtering
if (!empty($selected_users) && !empty($selected_agents)) {
    // Both user and agent selected - show their cashouts
    $where_clauses[] = "t.user_id = ?";
    $where_clauses[] = "t.type = 'cashout'";
    
    $agent_name = $all_agents[$selected_agents[0]] ?? '';
    $where_clauses[] = "t.description LIKE ?";
    
    array_push($params, reset($selected_users), "%Cash out to $agent_name%");
    $param_types .= "is";
} 
elseif (!empty($selected_users)) {
    // Only user selected
    $placeholders = implode(',', array_fill(0, count($selected_users), '?'));
    $where_clauses[] = "t.user_id IN ($placeholders)";
    $params = array_merge($params, $selected_users);
    $param_types .= str_repeat('i', count($selected_users));
} 
elseif (!empty($selected_agents)) {
    // Only agent selected
    $agent_name = $all_agents[$selected_agents[0]] ?? '';
    $where_clauses[] = "t.type = 'cashout'";
    $where_clauses[] = "t.description LIKE ?";
    array_push($params, "%Cash out to $agent_name%");
    $param_types .= "s";
}

// Transaction types
if (!empty($selected_types) && $report_type !== 'financial') {
    $placeholders = implode(',', array_fill(0, count($selected_types), '?'));
    $where_clauses[] = "t.type IN ($placeholders)";
    $params = array_merge($params, $selected_types);
    $param_types .= str_repeat('s', count($selected_types));
}

$where_sql = !empty($where_clauses) ? implode(' AND ', $where_clauses) : "1=1";

// Generate report
switch ($report_type) {
    case 'financial':
        $sql = "SELECT DATE_FORMAT(t.date, '%Y-%m-%d') AS day,
                SUM(CASE WHEN t.type='topup' THEN t.amount ELSE 0 END) AS topups,
                SUM(CASE WHEN t.type='send' THEN t.amount ELSE 0 END) AS transfers,
                SUM(CASE WHEN t.type='cashout' THEN t.amount ELSE 0 END) AS withdrawals,
                SUM(CASE WHEN t.type='received' THEN t.amount ELSE 0 END) AS payments,
                COUNT(*) AS transaction_count,
                SUM(t.amount) AS total_volume
                FROM transaction t
                WHERE $where_sql
                GROUP BY day
                ORDER BY day";
        break;

    case 'users':
        $sql = "SELECT u.user_id, u.full_name, u.status,
                COUNT(t.transaction_id) AS transaction_count,
                SUM(t.amount) AS total_volume,
                MAX(t.date) AS last_active,
                AVG(t.amount) AS avg_transaction,
                GROUP_CONCAT(DISTINCT a.AgentName) AS agents
                FROM users u
                LEFT JOIN transaction t ON u.user_id = t.user_id AND $where_sql
                LEFT JOIN user_agent_bridge uab ON u.user_id = uab.user_id
                LEFT JOIN agents a ON uab.AgentId = a.AgentId
                GROUP BY u.user_id
                ORDER BY total_volume DESC";
        break;

    case 'agents':
        $sql = "SELECT a.AgentId, a.AgentName, a.Location, a.Status,
                COUNT(t.transaction_id) AS transactions_processed,
                SUM(t.amount) AS total_volume,
                AVG(t.amount) AS avg_transaction,
                COUNT(DISTINCT uab.user_id) AS unique_customers
                FROM agents a
                LEFT JOIN user_agent_bridge uab ON a.AgentId = uab.AgentId
                LEFT JOIN transaction t ON uab.user_id = t.user_id AND $where_sql
                GROUP BY a.AgentId
                ORDER BY total_volume DESC";
        break;

    default: // transactions
    $sql = "SELECT t.transaction_id, t.date, u.full_name AS user,
    t.type, t.amount, t.description
    FROM transaction t
    JOIN users u ON t.user_id = u.user_id
    WHERE $where_sql
    ORDER BY t.date DESC";
        break;
}

$stmt = $conn->prepare($sql);
if ($stmt === false) {
    die("Error preparing statement: " . $conn->error);
}

if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();
$report_data = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Reports - MobiPay</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/chart.js">
  <style>
    :root {
      --primary: #1a365d;
      --secondary: #2c5282;
      --accent: #4299e1;
      --success: #38a169;
      --warning: #dd6b20;
      --danger: #e53e3e;
      --light: #f7fafc;
      --dark: #1a202c;
    }
    
    body {
      margin: 0;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      background-color: #f0f4f8;
      color: #2d3748;
    }
    
    nav {
      background-color: rgb(0,0,0);
      color: white;
      padding: 1rem 2rem;
      display: flex;
      justify-content: space-between;
      align-items: center;
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    
    .logo-container {
      display: flex;
      align-items: center;
      gap: 1rem;
    }
    
    .logo-container img {
      height: 60px;
    }
    
    .logo-text {
      font-size: 1.5rem;
      font-weight: bold;
    }
    
    .sidebar .menu {
      display: flex;
      gap: 60px;
      list-style: none;
      padding: 0;
    }
    
    .sidebar .menu li {
      display: inline-block;
    }
    
    .sidebar .menu li a {
      text-decoration: none;
    }
    
    .sidebar .menu li a button {
      padding: 10px 20px;
      background-color: transparent;
      color: white;
      border-radius: 5px;
      text-align: center;
      cursor: pointer;
      display: inline-block;
      transition: all 0.3s ease;
      font-size: 30px;
      font-weight: 500;
      border: none;
    }
    
    .sidebar .menu li a button:hover {
      background-color: rgba(128, 128, 128, 0.1);
      border-color: rgba(128, 128, 128, 0.4);
      transform: translateY(-1px);
    }
    
    .user-menu {
      display: flex;
      align-items: center;
      gap: 1rem;
    }
    
    .user-avatar {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      background-color: var(--accent);
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: bold;
      cursor: pointer;
    }
    
    main {
      padding: 2rem;
    }
    
    .dashboard-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 2rem;
    }
    
    .report-container {
      background: white;
      border-radius: 8px;
      padding: 2rem;
      box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
      margin-bottom: 2rem;
    }
    
    .report-filters {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
      gap: 1.5rem;
      margin-bottom: 2rem;
    }
    
    .filter-group {
      background: #f7fafc;
      border-radius: 8px;
      padding: 1rem;
    }
    
    .filter-group h3 {
      margin-top: 0;
      color: var(--primary);
    }
    
    .filter-options {
      display: flex;
      flex-wrap: wrap;
      gap: 0.5rem;
    }
    
    .filter-checkbox {
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }
    
    .report-actions {
      display: flex;
      gap: 1rem;
      margin-bottom: 2rem;
    }
    
    .btn {
      padding: 0.75rem 1.5rem;
      border-radius: 6px;
      font-weight: 500;
      cursor: pointer;
      transition: all 0.3s;
      border: none;
    }
    
    .btn-primary {
      background-color: var(--primary);
      color: white;
    }
    
    .btn-primary:hover {
      background-color: var(--secondary);
    }
    
    .btn-outline {
      background: transparent;
      border: 1px solid var(--primary);
      color: var(--primary);
    }
    
    .btn-outline:hover {
      background-color: #f0f4f8;
    }
    
    .form-control {
      width: 100%;
      padding: 0.75rem;
      border: 1px solid #e2e8f0;
      border-radius: 6px;
      font-size: 1rem;
    }
    
    .chart-container {
      background: white;
      border-radius: 8px;
      padding: 1.5rem;
      margin-bottom: 2rem;
      box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
    }
    
    .chart-row {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 1.5rem;
      margin-bottom: 2rem;
    }
    
    @media (max-width: 1024px) {
      .chart-row {
        grid-template-columns: 1fr;
      }
    }
    
    .metric-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 1.5rem;
      margin-bottom: 2rem;
    }
    
    .metric-card {
      background: white;
      border-radius: 8px;
      padding: 1.5rem;
      box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
    }
    
    .metric-value {
      font-size: 2rem;
      font-weight: bold;
      color: var(--primary);
      margin: 0.5rem 0;
    }
    
    .metric-label {
      color: #718096;
      font-size: 0.875rem;
    }
    
    .report-table-container {
      overflow-x: auto;
      background: white;
      border-radius: 8px;
      box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
    }
    
    .report-table {
      width: 100%;
      border-collapse: collapse;
    }
    
    .report-table th {
      background-color: #f7fafc;
      padding: 1rem;
      text-align: left;
      font-weight: 600;
      color: #4a5568;
    }
    
    .report-table td {
      padding: 1rem;
      border-bottom: 1px solid #e2e8f0;
    }
    
    .report-table tr:hover {
      background-color: #f7fafc;
    }
    
    .badge {
      display: inline-block;
      padding: 0.25rem 0.75rem;
      border-radius: 9999px;
      font-size: 0.75rem;
      font-weight: 600;
    }
    
    .badge-success {
      background-color: #c6f6d5;
      color: #22543d;
    }
    
    .badge-warning {
      background-color: #feebc8;
      color: #7b341e;
    }
    
    .badge-danger {
      background-color: #fed7d7;
      color: #742a2a;
    }
    
    .badge-info {
      background-color: #bee3f8;
      color: #2a4365;
    }
    
    .pagination {
      display: flex;
      justify-content: center;
      gap: 0.5rem;
      padding: 1.5rem;
    }
    
    .page-item {
      padding: 0.5rem 1rem;
      border: 1px solid #e2e8f0;
      border-radius: 6px;
      cursor: pointer;
    }
    
    .page-item.active {
      background-color: var(--primary);
      color: white;
    }
    
    .page-item:hover:not(.active) {
      background-color: #f7fafc;
    }
  </style>
</head>
<body>
  <nav>
    <div class="logo-container">
      <img src="logos.png" alt="MobiPay Logo">
      <div class="logo-text">MobiPay Admin</div>
    </div>
    <div class="sidebar">
      <ul class="menu">
        <li><a href="dashboard_admin.php"><button class="btn">Dashboard</button></a></li>
        <li><a href="users.php"><button class="btn">Users</button></a></li>
        <li><a href="agents.php"><button class="btn">Agents</button></a></li>
        <li><a href="transactions.php"><button class="btn">Transactions</button></a></li>
        <li><a href="reports.php"><button class="btn">Reports</button></a></li>
      </ul>
    </div>
    <div class="user-menu">
      <div class="user-avatar"><?= strtoupper(substr($manager_name, 0, 1)) ?></div>
    </div>
  </nav>

  <main>
    <div class="dashboard-header">
      <div class="dashboard-title">
        <h1>Advanced Reporting System</h1>
        <p>Comprehensive analytics and data visualization</p>
      </div>
    </div>
    
    <div class="report-container">
      <form method="GET" action="reports.php">
        <div class="report-filters">
          <!-- Report Type Selection -->
          <div class="filter-group">
            <h3>Report Type</h3>
            <select name="report_type" class="form-control">
              <?php foreach ($report_types as $value => $label): ?>
                <option value="<?= $value ?>" <?= $report_type == $value ? 'selected' : '' ?>><?= $label ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          
          <!-- Date Range Selection -->
          <div class="filter-group">
            <h3>Date Range</h3>
            <select name="date_range" class="form-control" id="dateRange">
              <?php foreach ($date_ranges as $value => $label): ?>
                <option value="<?= $value ?>" <?= $date_range == $value ? 'selected' : '' ?>><?= $label ?></option>
              <?php endforeach; ?>
            </select>
            
            <div id="customDates" style="margin-top: 1rem; <?= $date_range == 'custom' ? '' : 'display:none;' ?>">
              <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div>
                  <label>Start Date</label>
                  <input type="date" name="start_date" value="<?= $start_date ?>" class="form-control">
                </div>
                <div>
                  <label>End Date</label>
                  <input type="date" name="end_date" value="<?= $end_date ?>" class="form-control">
                </div>
              </div>
            </div>
          </div>
          
          <!-- Transaction Type Filters -->
          <div class="filter-group">
            <h3>Transaction Types</h3>
            <div class="filter-options">
              <?php foreach ($all_types as $value => $label): ?>
                <label class="filter-checkbox">
                  <input type="checkbox" name="types[]" value="<?= $value ?>" 
                    <?= in_array($value, $selected_types) ? 'checked' : '' ?>>
                  <?= $label ?>
                </label>
              <?php endforeach; ?>
            </div>
          </div>
          
         <!-- User Filter -->
<div class="filter-group">
    <h3>Filter by User</h3>
    <select name="users[]" class="form-control">
        <option value="">All Users</option>
        <?php foreach ($all_users as $id => $name): ?>
            <option value="<?= $id ?>" <?= in_array($id, $selected_users) ? 'selected' : '' ?>>
                <?= htmlspecialchars($name) ?>
            </option>
        <?php endforeach; ?>
    </select>
</div>

<!-- Agent Filter -->
<div class="filter-group">
    <h3>Filter by Agent</h3>
    <select name="agents[]" class="form-control">
        <option value="">All Agents</option>
        <?php foreach ($all_agents as $id => $name): ?>
            <option value="<?= $id ?>" <?= in_array($id, $selected_agents) ? 'selected' : '' ?>>
                <?= htmlspecialchars($name) ?>
            </option>
        <?php endforeach; ?>
    </select>
</div>
          
          <div class="report-actions">
            <button type="submit" class="btn btn-primary">Generate Report</button>
            <button type="reset" class="btn btn-outline">Reset Filters</button>
          </div>
        </div>
      </form>
      
      <?php if (!empty($report_data)): ?>
        <h2><?= $title ?> 
          <small style="font-size: 1rem; color: #718096;">
            (<?= date('M d, Y', strtotime($start_date)) ?> - <?= date('M d, Y', strtotime($end_date)) ?>)
          </small>
        </h2>
        
        <!-- Summary Metrics -->
        <?php if ($report_type === 'financial'): ?>
          <div class="metric-grid">
            <div class="metric-card">
              <div class="metric-label">Total Volume</div>
              <div class="metric-value">M<?= number_format(array_sum(array_column($report_data, 'total_volume')), 2) ?></div>
            </div>
            <div class="metric-card">
              <div class="metric-label">Total Transactions</div>
              <div class="metric-value"><?= number_format(array_sum(array_column($report_data, 'transaction_count'))) ?></div>
            </div>
            <div class="metric-card">
              <div class="metric-label">Avg. Daily Volume</div>
              <div class="metric-value">M<?= number_format(array_sum(array_column($report_data, 'total_volume')) / count($report_data), 2) ?></div>
            </div>
            <div class="metric-card">
              <div class="metric-label">Days Analyzed</div>
              <div class="metric-value"><?= count($report_data) ?></div>
            </div>
          </div>
          
          <!-- Financial Charts -->
          <div class="chart-row">
            <div class="chart-container">
              <h3>Daily Transaction Volume</h3>
              <canvas id="volumeChart"></canvas>
            </div>
            <div class="chart-container">
              <h3>Transaction Type Breakdown</h3>
              <canvas id="typeChart"></canvas>
            </div>
          </div>
        <?php elseif ($report_type === 'users'): ?>
          <div class="metric-grid">
            <div class="metric-card">
              <div class="metric-label">Total Users</div>
              <div class="metric-value"><?= count($report_data) ?></div>
            </div>
            <div class="metric-card">
              <div class="metric-label">Total Volume</div>
              <div class="metric-value">M<?= number_format(array_sum(array_column($report_data, 'total_volume')), 2) ?></div>
            </div>
            <div class="metric-card">
              <div class="metric-label">Avg. Transactions/User</div>
              <div class="metric-value"><?= number_format(array_sum(array_column($report_data, 'transaction_count')) / count($report_data), 1) ?></div>
            </div>
            <div class="metric-card">
              <div class="metric-label">Avg. Transaction Size</div>
              <div class="metric-value">M<?= number_format(array_sum(array_column($report_data, 'total_volume')) / array_sum(array_column($report_data, 'transaction_count')), 2) ?></div>
            </div>
          </div>
          
          <div class="chart-container">
            <h3>User Activity Distribution</h3>
            <canvas id="userActivityChart"></canvas>
          </div>
        <?php elseif ($report_type === 'agents'): ?>
          <div class="metric-grid">
            <div class="metric-card">
              <div class="metric-label">Total Agents</div>
              <div class="metric-value"><?= count($report_data) ?></div>
            </div>
            <div class="metric-card">
              <div class="metric-label">Total Volume</div>
              <div class="metric-value">M<?= number_format(array_sum(array_column($report_data, 'total_volume')), 2) ?></div>
            </div>
            <div class="metric-card">
              <div class="metric-label">Avg. Transactions/Agent</div>
              <div class="metric-value"><?= number_format(array_sum(array_column($report_data, 'transactions_processed')) / count($report_data), 1) ?></div>
            </div>
            <div class="metric-card">
              <div class="metric-label">Unique Customers</div>
              <div class="metric-value"><?= number_format(array_sum(array_column($report_data, 'unique_customers'))) ?></div>
            </div>
          </div>
          
          <div class="chart-container">
            <h3>Agent Performance Comparison</h3>
            <canvas id="agentChart"></canvas>
          </div>
        <?php endif; ?>
        
        <!-- Data Table -->
        <div class="report-table-container">
          <table class="report-table">
            <thead>
              <tr>
                <?php foreach (array_keys($report_data[0]) as $column): ?>
                  <th><?= ucwords(str_replace('_', ' ', $column)) ?></th>
                <?php endforeach; ?>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($report_data as $row): ?>
                <tr>
                  <?php foreach ($row as $key => $value): ?>
                    <td>
                      <?php if ($key === 'status'): ?>
                        <span class="badge <?= $value === 'Active' ? 'badge-success' : 'badge-danger' ?>">
                          <?= $value ?>
                        </span>
                      <?php elseif (is_numeric($value) && in_array($key, ['amount', 'total_volume', 'topups', 'transfers', 'withdrawals', 'payments', 'avg_transaction'])): ?>
                        M<?= number_format($value, 2) ?>
                      <?php elseif (is_numeric($value)): ?>
                        <?= number_format($value) ?>
                      <?php elseif (strtotime($value) !== false && in_array($key, ['date', 'last_active', 'created_at'])): ?>
                        <?= date('M d, Y H:i', strtotime($value)) ?>
                      <?php else: ?>
                        <?= htmlspecialchars($value) ?>
                      <?php endif; ?>
                    </td>
                  <?php endforeach; ?>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        
        <div class="pagination">
          <div class="page-item active">1</div>
          <div class="page-item">2</div>
          <div class="page-item">3</div>
          <div class="page-item">Next</div>
        </div>
      <?php else: ?>
        <div style="text-align: center; padding: 2rem; background: white; border-radius: 8px;">
          <h3>No data found for the selected filters</h3>
          <p>Try adjusting your report criteria</p>
        </div>
      <?php endif; ?>
    </div>
  </main>

  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script>
    // Toggle custom date range
    document.getElementById('dateRange').addEventListener('change', function() {
      document.getElementById('customDates').style.display = 
        this.value === 'custom' ? 'block' : 'none';
    });
    
    // Initialize charts if we have financial data
    <?php if ($report_type === 'financial' && !empty($report_data)): ?>
      // Daily Volume Chart
      const volumeCtx = document.getElementById('volumeChart').getContext('2d');
      new Chart(volumeCtx, {
        type: 'line',
        data: {
          labels: <?= json_encode(array_column($report_data, 'day')) ?>,
          datasets: [
            {
              label: 'Total Volume',
              data: <?= json_encode(array_column($report_data, 'total_volume')) ?>,
              borderColor: 'rgba(66, 153, 225, 1)',
              backgroundColor: 'rgba(66, 153, 225, 0.1)',
              tension: 0.3,
              fill: true
            }
          ]
        },
        options: {
          responsive: true,
          plugins: {
            tooltip: {
              callbacks: {
                label: (context) => {
                  return `M${context.raw.toLocaleString()}`;
                }
              }
            }
          },
          scales: {
            y: {
              beginAtZero: true,
              ticks: {
                callback: (value) => `M${value.toLocaleString()}`,
              }
            }
          }
        }
      });

      // Transaction Type Chart
      const typeCtx = document.getElementById('typeChart').getContext('2d');
      new Chart(typeCtx, {
        type: 'bar',
        data: {
          labels: ['Topups', 'Transfers', 'Withdrawals', 'Payments'],
          datasets: [
            {
              label: 'Total Amount',
              data: [
                <?= array_sum(array_column($report_data, 'topups')) ?>,
                <?= array_sum(array_column($report_data, 'transfers')) ?>,
                <?= array_sum(array_column($report_data, 'withdrawals')) ?>,
                <?= array_sum(array_column($report_data, 'payments')) ?>
              ],
              backgroundColor: [
                'rgba(56, 161, 105, 0.7)',
                'rgba(221, 107, 32, 0.7)',
                'rgba(229, 62, 62, 0.7)',
                'rgba(150, 122, 220, 0.7)'
              ]
            }
          ]
        },
        options: {
          responsive: true,
          plugins: {
            tooltip: {
              callbacks: {
                label: (context) => {
                  return `M${context.raw.toLocaleString()}`;
                }
              }
            }
          },
          scales: {
            y: {
              beginAtZero: true,
              ticks: {
                callback: (value) => `M${value.toLocaleString()}`,
              }
            }
          }
        }
      });
    <?php endif; ?>

    // Agent Performance Chart
    <?php if ($report_type === 'agents' && !empty($report_data)): ?>
      const agentCtx = document.getElementById('agentChart').getContext('2d');
      new Chart(agentCtx, {
        type: 'bar',
        data: {
          labels: <?= json_encode(array_column($report_data, 'AgentName')) ?>,
          datasets: [
            {
              label: 'Transaction Count',
              data: <?= json_encode(array_column($report_data, 'transactions_processed')) ?>,
              backgroundColor: 'rgba(66, 153, 225, 0.7)',
              yAxisID: 'y'
            },
            {
              label: 'Total Volume (M)',
              data: <?= json_encode(array_column($report_data, 'total_volume')) ?>,
              backgroundColor: 'rgba(56, 161, 105, 0.7)',
              yAxisID: 'y1',
              type: 'line'
            }
          ]
        },
        options: {
          responsive: true,
          plugins: {
            tooltip: {
              callbacks: {
                label: (context) => {
                  let label = context.dataset.label || '';
                  if (label.includes('Volume')) {
                    label += `: M${context.raw.toLocaleString()}`;
                  } else {
                    label += `: ${context.raw.toLocaleString()}`;
                  }
                  return label;
                }
              }
            }
          },
          scales: {
            y: {
              type: 'linear',
              display: true,
              position: 'left',
              title: {
                display: true,
                text: 'Transaction Count'
              }
            },
            y1: {
              type: 'linear',
              display: true,
              position: 'right',
              title: {
                display: true,
                text: 'Total Volume (M)'
              },
              ticks: {
                callback: (value) => `M${value.toLocaleString()}`,
              },
              grid: {
                drawOnChartArea: false
              }
            }
          }
        }
      });
    <?php endif; ?>
  </script>
</body>
</html>