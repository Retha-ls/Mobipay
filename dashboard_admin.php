<?php
ini_set('session.cookie_secure', '0');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$manager_name = 'admin';
$total_balance = 0;
$total_transactions = 0;
$active_users = 0;
$active_agents = 0;

$conn = new mysqli("localhost", "root", "", "Mobipay");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$manager_id = $_SESSION['user_id'];

// Get manager details
$sql = "SELECT full_name FROM users WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $manager_id);
$stmt->execute();
$stmt->bind_result($manager_name);
$stmt->fetch();
$stmt->close();

// Get system statistics
$sql_stats = "SELECT 
    (SELECT SUM(current_balance) FROM wallets) AS total_balance,
    (SELECT COUNT(*) FROM transaction) AS today_transactions,
    (SELECT COUNT(*) FROM users) AS active_users,
    (SELECT COUNT(*) FROM agents) AS active_agents";

$result_stats = $conn->query($sql_stats);

if ($result_stats && $result_stats->num_rows > 0) {
    $stats = $result_stats->fetch_assoc();
    $total_balance = $stats['total_balance'];
    $total_transactions = $stats['today_transactions'];
    $active_users = $stats['active_users'];
    $active_agents = $stats['active_agents'];
} else {
    echo "Query failed: " . $conn->error;
}

// Get recent transactions
$recent_transactions = [];
$sql_transactions = "SELECT t.transaction_id, u.full_name, t.type, t.amount, t.date, t.description 
                     FROM transaction t
                     JOIN users u ON t.user_id = u.user_id
                     ORDER BY t.date DESC LIMIT 10";
$result_transactions = $conn->query($sql_transactions);
if ($result_transactions->num_rows > 0) {
    while ($row = $result_transactions->fetch_assoc()) {
        $recent_transactions[] = $row;
    }
}

// Get transaction types data for chart
$transaction_types = [];
$sql_types = "SELECT type, COUNT(*) as count, SUM(amount) as total 
              FROM transaction 
              WHERE date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
              GROUP BY type";
$result_types = $conn->query($sql_types);
if ($result_types->num_rows > 0) {
    while ($row = $result_types->fetch_assoc()) {
        $transaction_types[] = $row;
    }
}


$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Manager Dashboard - MobiPay</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/chart.js">
  <style>
    .dashboard-title h1,
.dashboard-title p,
.stat-card h3,
.stat-card .value,
.data-table h2 {
  color: black !important;
}

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
      background-color:rgb(238, 238, 238);
      color:rgb(0, 0, 0);
    }
    
    nav {
      background-color: rgb(0, 0, 0);
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
      gap: 10px;
    }
    
    .logo-container img {
      height: 80px;
    }
    
    .logo-text {
      font-size: 1.5rem;
      font-weight: bold;
    }
    
    .nav-items {
      display: flex;
      gap: 2rem;
    }
    
    .nav-item {
      padding: 0.5rem 1rem;
      cursor: pointer;
      transition: all 0.3s;
      border-radius: 4px;
    }
    
    .nav-item:hover {
      background-color: var(--secondary);
    }
    
    .nav-item.active {
      background-color: var(--accent);
    }
    .sidebar .menu {
  display: flex; /* Enables horizontal layout */
  gap: 60px; /* Optional: adds space between buttons */
  list-style: none; /* Removes the default list item styling */
  padding: 0; /* Removes default padding */
}

.sidebar .menu li {
  display: inline-block; /* Makes each list item behave like a block element but horizontally */
}

.sidebar .menu li a {
  text-decoration: none; /* Removes the underline */
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
  font-size: 30px; /* Larger text size */
  font-weight: 500; /* Medium font weight */
}

.sidebar .menu li a button:hover {
  background-color: rgba(128, 128, 128, 0.1); /* Light grey background */
  border-color: rgba(128, 128, 128, 0.4); /* Darker grey border */
  transform: translateY(-1px); /* Slight lift effect */
}

/* Optional: Add active state styling */
.sidebar .menu li a button.active {
  background-color: rgba(128, 128, 128, 0.15);
  border-color: rgba(128, 128, 128, 0.5);
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
    
    .dashboard-title h1 {
      margin: 0;
      color: var(--primary);
    }
    
    .dashboard-title p {
      margin: 0.5rem 0 0;
      color: #718096;
    }
    
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 1.5rem;
      margin-bottom: 2rem;
    }
    
    .stat-card {
      background: white;
      border-radius: 8px;
      padding: 1.5rem;
      box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
      transition: transform 0.3s;
    }
    
    .stat-card:hover {
      transform: translateY(-5px);
    }
    
    .stat-card h3 {
      margin-top: 0;
      color: #718096;
      font-size: 1rem;
      font-weight: 500;
    }
    
    .stat-card .value {
      font-size: 2rem;
      font-weight: 700;
      margin: 0.5rem 0;
    }
    
    .stat-card .trend {
      display: flex;
      align-items: center;
      font-size: 0.875rem;
    }
    
    .trend.up {
      color: var(--success);
    }
    
    .trend.down {
      color: var(--danger);
    }
    
    .charts-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.5rem;
    margin-bottom: 2rem;
    align-items: stretch;
  }
    
    @media (max-width: 1024px) {
      .charts-row {
        grid-template-columns: 1fr;
      }
    }
    
    .chart-container {
    background: white;
    border-radius: 8px;
    padding: 1rem;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    height: 10000px;
  }
  .chart-container canvas {
    width: 95% !important;
    height: 80% !important;
  }

    
    .chart-container h2 {
      margin-top: 0;
      font-size: 1.25rem;
      color: var(--primary);
    }
    
    .data-table {
    background: white;
    border-radius: 8px;
    padding: 1.5rem;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    height: 300px;
    overflow-y: auto;
  }
  .chart-container, .data-table {
    height: 70vh; /* 70% of viewport height */
    min-height: 300px; /* Minimum height */
  }

    .data-table h2 {
      margin-top: 0;
      font-size: 1.25rem;
      color: var(--primary);
    }
    
    table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 1rem;
    }
    
    th, td {
      padding: 0.75rem 1rem;
      text-align: left;
      border-bottom: 1px solid #e2e8f0;
    }
    
    th {
      background-color:rgb(224, 224, 224);
      font-weight: 600;
      color:rgb(0, 0, 0);
    }
    
    tr:hover {
      background-color: #f7fafc;
    }
    
    .badge {
      display: inline-block;
      padding: 0.25rem 0.5rem;
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
    
    .btn {
      padding: 0.5rem 1rem;
      border-radius: 4px;
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
    
    .quick-actions {
      display: flex;
      gap: 1rem;
      margin-bottom: 2rem;
    }
    
    .modal {
      display: none;
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background-color: rgba(0,0,0,0.5);
      z-index: 1000;
      justify-content: center;
      align-items: center;
    }
    
    .modal-content {
      background: white;
      border-radius: 8px;
      width: 90%;
      max-width: 600px;
      max-height: 90vh;
      overflow-y: auto;
      padding: 2rem;
      box-shadow: 0 10px 25px rgba(0,0,0,0.2);
    }
    
    .modal-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 1.5rem;
    }
    
    .modal-header h2 {
      margin: 0;
      color: var(--primary);
    }
    
    .close-btn {
      background: none;
      border: none;
      font-size: 1.5rem;
      cursor: pointer;
      color: #718096;
    }
    
    .form-group {
      margin-bottom: 1.5rem;
    }
    
    .form-label {
      display: block;
      margin-bottom: 0.5rem;
      font-weight: 500;
    }
    
    .form-control {
      width: 100%;
      padding: 0.75rem;
      border: 1px solid #e2e8f0;
      border-radius: 4px;
      font-size: 1rem;
    }
    
    .form-control:focus {
      outline: none;
      border-color: var(--accent);
      box-shadow: 0 0 0 3px rgba(66, 153, 225, 0.2);
    }
    
    .submit-btn {
      background-color: var(--primary);
      color: white;
      padding: 0.75rem 1.5rem;
      border: none;
      border-radius: 4px;
      font-weight: 500;
      cursor: pointer;
      transition: background-color 0.3s;
    }
    
    .submit-btn:hover {
      background-color: var(--secondary);
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
      <li><a href="dashboard_admin.php"><button class="btn <?= basename($_SERVER['PHP_SELF']) == 'dashboard_admin.php' ? 'active' : '' ?>">Dashboard</button></a></li>
      <li><a href="users.php"><button class="btn <?= basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : '' ?>">Users</button></a></li>
      <li><a href="agents.php"><button class="btn <?= basename($_SERVER['PHP_SELF']) == 'agents.php' ? 'active' : '' ?>">Agents</button></a></li>
      <li><a href="transactions.php"><button class="btn <?= basename($_SERVER['PHP_SELF']) == 'transactions.php' ? 'active' : '' ?>">Transactions</button></a></li>
      <li><a href="reports.php"><button class="btn <?= basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : '' ?>">Reports</button></a></li>
    </ul>
  </div>
  <div class="user-menu">
    <div class="user-avatar"><?= strtoupper(substr($manager_name, 0, 1)) ?></div>
  </div>
</nav>

  <main>
    <div class="dashboard-header">
      <div class="dashboard-title">
        <h1>Welcome, <?php echo htmlspecialchars($manager_name); ?></h1>
        <p>Here's what's happening with your MobiPay system today</p>
      </div>
    </div>
    
    <div class="stats-grid">
      <div class="stat-card">
        <h3>Total System Balance</h3>
        <div class="value">M<?php echo number_format($total_balance, 2); ?></div>
        <div class="trend up">↑ 12% from previous month</div>
      </div>
      
      <div class="stat-card">
        <h3>Transactions</h3>
        <div class="value"><?php echo number_format($total_transactions); ?></div>
        <div class="trend up">↑ 8% from previous month</div>
      </div>
      
      <div class="stat-card">
        <h3>Active Users</h3>
        <div class="value"><?php echo number_format($active_users); ?></div>
        <div class="trend up">↑ 5 new users this month</div>
      </div>
      
      <div class="stat-card">
        <h3>Active Agents</h3>
        <div class="value"><?php echo number_format($active_agents); ?></div>
        <div class="trend down">↓ 2 inactive</div>
      </div>
    </div>
    
    <div class="charts-row">
      <div class="chart-container">
        <h2>Transaction Types (Last 7 Days)</h2>
        <canvas id="transactionTypesChart"></canvas>
      </div>
      <div class="data-table">
        <h2>Recent Transactions</h2>
        <table>
          <thead>
            <tr>
              <th>ID</th>
              <th>User</th>
              <th>Type</th>
              <th>Amount</th>
              <th>Date</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($recent_transactions as $transaction): ?>
            <tr>
              <td><?php echo $transaction['transaction_id']; ?></td>
              <td><?php echo htmlspecialchars($transaction['full_name']); ?></td>
              <td>
                <?php 
                  $badge_class = '';
                  if ($transaction['type'] === 'topup') $badge_class = 'badge-success';
                  elseif ($transaction['type'] === 'send') $badge_class = 'badge-warning';
                  else $badge_class = 'badge-danger';
                ?>
                <span class="badge <?php echo $badge_class; ?>"><?php echo ucfirst($transaction['type']); ?></span>
              </td>
              <td>M<?php echo number_format($transaction['amount'], 2); ?></td>
              <td><?php echo $transaction['date']; ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    
  </main>

  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script>
    // Transaction Types Chart
    const transactionTypesCtx = document.getElementById('transactionTypesChart').getContext('2d');
    const transactionTypesChart = new Chart(transactionTypesCtx, {
      type: 'doughnut',
      data: {
        labels: <?php echo json_encode(array_column($transaction_types, 'type')); ?>,
        datasets: [{
          data: <?php echo json_encode(array_column($transaction_types, 'count')); ?>,
          backgroundColor: [
            'rgba(56, 161, 105, 0.7)',
            'rgba(221, 107, 32, 0.7)',
            'rgba(229, 62, 62, 0.7)',
            'rgba(150, 122, 220, 0.7)'
          ],
          borderColor: [
            'rgba(56, 161, 105, 1)',
            'rgba(221, 107, 32, 1)',
            'rgba(229, 62, 62, 1)',
            'rgba(150, 122, 220, 1)'
          ],
          borderWidth: 1
        }]
      },
      options: {
        responsive: true,
        plugins: {
          legend: {
            position: 'right',
          },
          tooltip: {
            callbacks: {
              label: function(context) {
                const label = context.label || '';
                const value = context.raw || 0;
                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                const percentage = Math.round((value / total) * 100);
                return `${label}: ${value} (${percentage}%)`;
              }
            }
          }
        }
      }
    });
  </script>
</body>
</html>