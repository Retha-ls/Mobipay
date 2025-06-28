<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$conn = new mysqli("localhost", "root", "", "Mobipay");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get manager name for display
$manager_name = 'Admin';
$manager_id = $_SESSION['user_id'];
$sql = "SELECT full_name FROM users WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $manager_id);
$stmt->execute();
$stmt->bind_result($manager_name);
$stmt->fetch();
$stmt->close();

// Handle transaction filtering
$where_clause = "1=1";
$params = [];
$types = "";

if (isset($_GET['type'])) {
    $where_clause .= " AND t.type = ?";
    $params[] = $_GET['type'];
    $types .= "s";
}

if (isset($_GET['date_from'])) {
    $where_clause .= " AND t.date >= ?";
    $params[] = $_GET['date_from'];
    $types .= "s";
}

if (isset($_GET['date_to'])) {
    $where_clause .= " AND t.date <= ?";
    $params[] = $_GET['date_to'];
    $types .= "s";
}

if (isset($_GET['user_id'])) {
    $where_clause .= " AND t.user_id = ?";
    $params[] = $_GET['user_id'];
    $types .= "i";
}

// Get all transactions
$transactions = [];
$sql = "SELECT t.transaction_id, t.user_id, u.full_name, u.status AS user_status, t.type, t.amount, t.date, t.description 
        FROM transaction t
        JOIN users u ON t.user_id = u.user_id
        WHERE $where_clause
        ORDER BY t.date DESC";

$stmt = $conn->prepare($sql);
if ($stmt === false) {
    die("Error preparing statement: " . $conn->error);
}

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

if (!$stmt->execute()) {
    die("Error executing statement: " . $stmt->error);
}

$result = $stmt->get_result();
if ($result === false) {
    die("Error getting result set: " . $stmt->error);
}

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $transactions[] = $row;
    }
}

// Get all users for filter dropdown
$users = [];
$sql_users = "SELECT user_id, full_name FROM users ORDER BY full_name";
$result_users = $conn->query($sql_users);
if ($result_users->num_rows > 0) {
    while ($row = $result_users->fetch_assoc()) {
        $users[] = $row;
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Transactions Management - MobiPay</title>
  <style>
    /* Use the same styles as dashboard_admin.php */
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
    
    .dashboard-title h1 {
      margin: 0;
      color: var(--primary);
    }
    
    .dashboard-title p {
      margin: 0.5rem 0 0;
      color: #718096;
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
    
    .data-table {
      background: white;
      border-radius: 8px;
      padding: 1.5rem;
      box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
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
      background-color: #f7fafc;
      font-weight: 600;
      color: #4a5568;
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
    
    .badge-info {
      background-color: #bee3f8;
      color: #2a4365;
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
    
    .action-buttons {
      display: flex;
      gap: 0.5rem;
    }
    
    .action-btn {
      padding: 0.5rem 1rem;
      border-radius: 4px;
      font-weight: 500;
      cursor: pointer;
      transition: all 0.3s;
      border: none;
    }
    
    .edit-btn {
      background-color: var(--warning);
      color: white;
    }
    
    .edit-btn:hover {
      background-color: #c05621;
    }
    
    .delete-btn {
      background-color: var(--danger);
      color: white;
    }
    
    .delete-btn:hover {
      background-color: #c53030;
    }
    
    .filter-section {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
      gap: 1rem;
      margin-bottom: 1.5rem;
      background: white;
      padding: 1.5rem;
      border-radius: 8px;
      box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    }
    
    .filter-group {
      display: flex;
      flex-direction: column;
    }
    
    .filter-actions {
      display: flex;
      gap: 1rem;
      align-items: flex-end;
    }
    
    .pagination {
      display: flex;
      justify-content: center;
      gap: 0.5rem;
      margin-top: 1.5rem;
    }
    
    .page-item {
      padding: 0.5rem 1rem;
      border: 1px solid #e2e8f0;
      border-radius: 4px;
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
      <div class="user-avatar">A</div>
    </div>
  </nav>

  <main>
    <div class="dashboard-header">
      <div class="dashboard-title">
        <h1>Transactions Management</h1>
        <p>View and manage all system transactions</p>
      </div>
    </div>
    
    <div class="filter-section">
      <div class="filter-group">
        <label class="form-label" for="transactionType">Transaction Type</label>
        <select id="transactionType" class="form-control" onchange="applyFilters()">
          <option value="">All Types</option>
          <option value="topup" <?php echo isset($_GET['type']) && $_GET['type'] == 'topup' ? 'selected' : ''; ?>>Top Up</option>
          <option value="send" <?php echo isset($_GET['type']) && $_GET['type'] == 'send' ? 'selected' : ''; ?>>Send Money</option>
          <option value="withdraw" <?php echo isset($_GET['type']) && $_GET['type'] == 'withdraw' ? 'selected' : ''; ?>>Withdraw</option>
          <option value="payment" <?php echo isset($_GET['type']) && $_GET['type'] == 'payment' ? 'selected' : ''; ?>>Payment</option>
        </select>
      </div>
      
      <div class="filter-group">
        <label class="form-label" for="dateFrom">Date From</label>
        <input type="date" id="dateFrom" class="form-control" value="<?php echo isset($_GET['date_from']) ? $_GET['date_from'] : ''; ?>" onchange="applyFilters()">
      </div>
      
      <div class="filter-group">
        <label class="form-label" for="dateTo">Date To</label>
        <input type="date" id="dateTo" class="form-control" value="<?php echo isset($_GET['date_to']) ? $_GET['date_to'] : ''; ?>" onchange="applyFilters()">
      </div>
      
      <div class="filter-group">
        <label class="form-label" for="userFilter">User</label>
        <select id="userFilter" class="form-control" onchange="applyFilters()">
          <option value="">All Users</option>
          <?php foreach ($users as $user): ?>
            <option value="<?php echo $user['user_id']; ?>" <?php echo isset($_GET['user_id']) && $_GET['user_id'] == $user['user_id'] ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($user['full_name']); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      
      <div class="filter-actions">
        <button class="btn btn-primary" onclick="applyFilters()">Apply Filters</button>
        <button class="btn" onclick="resetFilters()">Reset</button>
      </div>
    </div>
    
    <div class="data-table">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
        <h2>Transaction History</h2>
        <div>
          <span>Total: <?php echo count($transactions); ?> transactions</span>
        </div>
      </div>
      
      <table>
        <thead>
          <tr>
            <th>ID</th>
            <th>User</th>
            <th>Type</th>
            <th>Amount</th>
            <th>Date</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($transactions as $transaction): ?>
          <tr>
            <td><?php echo $transaction['transaction_id']; ?></td>
            <td><?php echo htmlspecialchars($transaction['full_name']); ?></td>
            <td>
              <?php 
                $badge_class = '';
                if ($transaction['type'] === 'topup') $badge_class = 'badge-success';
                elseif ($transaction['type'] === 'send') $badge_class = 'badge-warning';
                elseif ($transaction['type'] === 'withdraw') $badge_class = 'badge-danger';
                else $badge_class = 'badge-info';
              ?>
              <span class="badge <?php echo $badge_class; ?>"><?php echo ucfirst($transaction['type']); ?></span>
            </td>
            <td>M<?php echo number_format($transaction['amount'], 2); ?></td>
            <td><?php echo date('M d, Y H:i', strtotime($transaction['date'])); ?></td>
            <td>
              <span class="badge <?php echo $transaction['user_status'] === 'Active' ? 'badge-success' : 'badge-danger'; ?>">
                <?php echo $transaction['user_status']; ?>
              </span>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      
      <div class="pagination">
        <div class="page-item active">1</div>
        <div class="page-item">2</div>
        <div class="page-item">3</div>
        <div class="page-item">Next</div>
      </div>
    </div>
  </main>

  <script>
    // Apply filters function
    function applyFilters() {
      const type = document.getElementById('transactionType').value;
      const dateFrom = document.getElementById('dateFrom').value;
      const dateTo = document.getElementById('dateTo').value;
      const userId = document.getElementById('userFilter').value;
      
      let queryParams = [];
      
      if (type) queryParams.push(`type=${type}`);
      if (dateFrom) queryParams.push(`date_from=${dateFrom}`);
      if (dateTo) queryParams.push(`date_to=${dateTo}`);
      if (userId) queryParams.push(`user_id=${userId}`);
      
      window.location.href = `transactions.php?${queryParams.join('&')}`;
    }
    
    // Reset filters function
    function resetFilters() {
      window.location.href = 'transactions.php';
    }
    
    // Modal controls
    function openModal(id) {
      document.getElementById(id).style.display = 'flex';
    }
    
    function closeModal(id) {
      document.getElementById(id).style.display = 'none';
    }
    
    // Initialize event listeners when page loads
    document.addEventListener('DOMContentLoaded', function() {
      // Close modals when clicking outside
      document.addEventListener('click', function(event) {
        if (event.target.classList.contains('modal')) {
          event.target.style.display = 'none';
        }
      });
    });
  </script>
</body>
</html>