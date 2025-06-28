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

// Handle success/error messages
$success_message = '';
$error_message = '';
if (isset($_GET['success'])) {
    $success_message = htmlspecialchars($_GET['success']);
}
if (isset($_GET['error'])) {
    $error_message = htmlspecialchars($_GET['error']);
}

// Handle user actions (add/edit/delete)
if (isset($_POST['add_user'])) {
    // Sanitize input data
    $full_name = $conn->real_escape_string($_POST['full_name']);
    $email = $conn->real_escape_string($_POST['email']);
    $phone = $conn->real_escape_string($_POST['phone']);
    
    // Hash password and PIN
    $password_hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $pin_hash = password_hash($_POST['pin'], PASSWORD_DEFAULT);
    
    // Set initial balance
    $balance = floatval($_POST['initial_balance']);

    // Start transaction
    $conn->begin_transaction();

    try {
        // Insert user into the 'users' table
        $sql = "INSERT INTO users (full_name, email, phone_number, password_hash, pin_hash, role) 
                VALUES ('$full_name', '$email', '$phone', '$password_hash', '$pin_hash', 'user')";
        
        if ($conn->query($sql)) {
            // Get the inserted user's ID
            $user_id = $conn->insert_id;

            // Insert initial balance into the 'wallets' table
            $sql_wallet = "INSERT INTO wallets (user_id, current_balance) VALUES ($user_id, $balance)";
            
            if ($conn->query($sql_wallet)) {
                // Commit the transaction
                $conn->commit();
                header("Location: users.php?success=User added successfully");
                exit();
            } else {
                throw new Exception("Error adding wallet: " . $conn->error);
            }
        } else {
            throw new Exception("Error adding user: " . $conn->error);
        }
    } catch (Exception $e) {
        // Rollback the transaction in case of error
        $conn->rollback();
        header("Location: users.php?error=" . urlencode($e->getMessage()));
        exit();
    }
}
// Get search term
// Get search term
$search = '';
if (isset($_GET['search'])) {
    $search = $conn->real_escape_string($_GET['search']);
}

$sql = "SELECT u.user_id, u.full_name, u.email, u.phone_number, u.status, w.current_balance 
        FROM users u
        LEFT JOIN wallets w ON u.user_id = w.user_id
        WHERE u.role = 'user' 
        AND (u.full_name LIKE '%$search%' 
             OR u.email LIKE '%$search%' 
             OR u.phone_number LIKE '%$search%')
        ORDER BY u.user_id DESC";


if (isset($_POST['edit_user'])) {
    $user_id = intval($_POST['user_id']);
    $full_name = $conn->real_escape_string($_POST['full_name']);
    $email = $conn->real_escape_string($_POST['email']);
    $phone = $conn->real_escape_string($_POST['phone']);
    $balance = floatval($_POST['balance']);
    $status = $conn->real_escape_string($_POST['status']);

    // Start transaction
    $conn->begin_transaction();

    try {
        // Update user
        $sql = "UPDATE users SET 
                full_name = '$full_name',
                email = '$email',
                phone_number = '$phone',
                status = '$status'
                WHERE user_id = $user_id";
        $conn->query($sql);

        // Update wallet balance
        $sql = "UPDATE wallets SET 
                current_balance = $balance
                WHERE user_id = $user_id";
        $conn->query($sql);

        $conn->commit();
        header("Location: users.php?success=User updated successfully");
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        header("Location: users.php?error=Error updating user");
        exit();
    }
}

if (isset($_GET['delete_user'])) {
    $user_id = intval($_GET['delete_user']);

    // Start transaction
    $conn->begin_transaction();

    try {
        // Delete wallet first (foreign key constraint)
        $sql = "DELETE FROM wallets WHERE user_id = $user_id";
        $conn->query($sql);

        // Then delete user
        $sql = "DELETE FROM users WHERE user_id = $user_id";
        $conn->query($sql);

        $conn->commit();
        header("Location: users.php?success=User deleted successfully");
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        header("Location: users.php?error=Error deleting user");
        exit();
    }
}

// Get all users with the "user" role
$users = [];
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
}

// Get user data for editing
$edit_user = null;
if (isset($_GET['edit_user'])) {
    $user_id = intval($_GET['edit_user']);
    $sql = "SELECT u.*, w.current_balance 
            FROM users u
            LEFT JOIN wallets w ON u.user_id = w.user_id
            WHERE u.user_id = $user_id";
    $result = $conn->query($sql);
    if ($result->num_rows > 0) {
        $edit_user = $result->fetch_assoc();
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Users Management - MobiPay</title>
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
      background-color: rgb(238, 238, 238);
      color: rgb(0, 0, 0);
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
    .dashboard-title h1,
    .dashboard-title p,
    .data-table h2,
    .alert {
      color: black !important;
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
    
    .action-buttons {
      display: flex;
      gap: 0.5rem;
    }
    
    .action-btn {
      padding: 0.25rem 0.5rem;
      border-radius: 4px;
      font-size: 0.875rem;
      cursor: pointer;
    }
    
    .edit-btn {
      background-color: var(--accent);
      color: white;
      border: none;
    }
    
    .delete-btn {
      background-color: var(--danger);
      color: white;
      border: none;
    }
    
    .data-table {
      background: white;
      border-radius: 8px;
      padding: 1.5rem;
      box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
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
    
    .btn {
      padding: 0.5rem 1rem;
      border-radius: 4px;
      font-weight: 500;
      cursor: pointer;
      transition: all 0.3s;
      border: none;
    }
    .btn, .action-btn {
  border: none !important;
    }
    
    .btn-primary {
      background-color: var(--primary);
      color: white;
    }
    
    .btn-primary:hover {
      background-color: var(--secondary);
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
    
    .form-control {
      width: 100%;
      padding: 0.75rem;
      border: 1px solid #e2e8f0;
      border-radius: 4px;
      font-size: 1rem;
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

    .status-active {
      color: var(--success);
      font-weight: bold;
    }

    .status-inactive {
      color: var(--danger);
      font-weight: bold;
    }

    .alert {
      padding: 1rem;
      margin-bottom: 1rem;
      border-radius: 4px;
    }

    .alert-success {
      background-color: #f0fff4;
      color: var(--success);
      border: 1px solid #c6f6d5;
    }

    .alert-error {
      background-color: #fff5f5;
      color: var(--danger);
      border: 1px solid #fed7d7;
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
        <h1>Users Management</h1>
        <p>Manage all system users and their accounts</p>
      </div>
      <button class="btn btn-primary" onclick="openModal('addUserModal')">Add New User</button>
    </div>

    <?php if ($success_message): ?>
      <div class="alert alert-success">
        <?php echo $success_message; ?>
      </div>
    <?php endif; ?>

    <?php if ($error_message): ?>
      <div class="alert alert-error">
        <?php echo $error_message; ?>
      </div>
    <?php endif; ?>
    
    <div class="data-table">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
        <h2>All Users</h2>
        <div style="display: flex; gap: 1rem;">
      <form method="GET" action="users.php" style="display: flex; gap: 0.5rem;">
        <input type="text" name="search" placeholder="Search users..." class="form-control" 
              value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>" style="width: 300px;">
        <button type="submit" class="btn btn-primary">Search</button>
      </form>
</div>
      </div>
      
      <table>
        <thead>
          <tr>
            <th>ID</th>
            <th>Name</th>
            <th>Email</th>
            <th>Phone</th>
            <th>Balance</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($users as $user): ?>
          <tr>
            <td><?php echo $user['user_id']; ?></td>
            <td><?php echo htmlspecialchars($user['full_name']); ?></td>
            <td><?php echo htmlspecialchars($user['email']); ?></td>
            <td><?php echo htmlspecialchars($user['phone_number']); ?></td>
            <td>M<?php echo number_format($user['current_balance'] ?? 0, 2); ?></td>
            <td>
              <span class="status-<?php echo strtolower($user['status']); ?>">
                <?php echo htmlspecialchars($user['status']); ?>
              </span>
            </td>
            <td>
              <button class="action-btn edit-btn" onclick="openEditModal(<?php echo $user['user_id']; ?>)">Edit</button>
              <button class="action-btn delete-btn" onclick="confirmDelete(<?php echo $user['user_id']; ?>)">Delete</button>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </main>

  <!-- Add User Modal -->
  <div id="addUserModal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <h2>Add New User</h2>
        <button class="close-btn" onclick="closeModal('addUserModal')">&times;</button>
      </div>
      <form action="users.php" method="POST">
        <div class="form-group">
            <label class="form-label" for="full_name">Full Name</label>
            <input type="text" class="form-control" id="full_name" name="full_name" required>
        </div>
        <div class="form-group">
            <label class="form-label" for="email">Email</label>
            <input type="email" class="form-control" id="email" name="email" required>
        </div>
        <div class="form-group">
            <label class="form-label" for="phone">Phone Number</label>
            <input type="tel" class="form-control" id="phone" name="phone" required>
        </div>
        <div class="form-group">
            <label class="form-label" for="password">Password</label>
            <input type="password" class="form-control" id="password" name="password" required>
        </div>
        <div class="form-group">
            <label class="form-label" for="pin">PIN</label>
            <input type="password" class="form-control" id="pin" name="pin" required minlength="4" maxlength="4">
        </div>
        <div class="form-group">
            <label class="form-label" for="initial_balance">Initial Balance</label>
            <input type="number" class="form-control" id="initial_balance" name="initial_balance" value="0.00" step="0.01">
        </div>
        <input type="hidden" name="add_user" value="1">
        <button type="submit" class="submit-btn">Add User</button>
      </form>
    </div>
  </div>

  <!-- Edit User Modal -->
  <div id="editUserModal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <h2>Edit User</h2>
        <button class="close-btn" onclick="closeModal('editUserModal')">&times;</button>
      </div>
      <?php if ($edit_user): ?>
      <form action="users.php" method="POST">
        <input type="hidden" id="edit_user_id" name="user_id" value="<?php echo $edit_user['user_id']; ?>">
        <div class="form-group">
          <label class="form-label" for="edit_full_name">Full Name</label>
          <input type="text" class="form-control" id="edit_full_name" name="full_name" 
                 value="<?php echo htmlspecialchars($edit_user['full_name']); ?>" required>
        </div>
        <div class="form-group">
          <label class="form-label" for="edit_email">Email</label>
          <input type="email" class="form-control" id="edit_email" name="email" 
                 value="<?php echo htmlspecialchars($edit_user['email']); ?>" required>
        </div>
        <div class="form-group">
          <label class="form-label" for="edit_phone">Phone Number</label>
          <input type="tel" class="form-control" id="edit_phone" name="phone" 
                 value="<?php echo htmlspecialchars($edit_user['phone_number']); ?>" required>
        </div>
        <div class="form-group">
          <label class="form-label" for="edit_balance">Current Balance</label>
          <input type="number" class="form-control" id="edit_balance" name="balance" 
                 value="<?php echo htmlspecialchars($edit_user['current_balance'] ?? '0.00'); ?>" step="0.01">
        </div>
        <div class="form-group">
          <label class="form-label" for="edit_status">Status</label>
          <select class="form-control" id="edit_status" name="status" required>
            <option value="Active" <?php echo ($edit_user['status'] ?? '') === 'Active' ? 'selected' : ''; ?>>Active</option>
            <option value="Inactive" <?php echo ($edit_user['status'] ?? '') === 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
          </select>
        </div>
        <input type="hidden" name="edit_user" value="1">
        <button type="submit" class="submit-btn">Update User</button>
      </form>
      <?php endif; ?>
    </div>
  </div>

  <script>
    function openModal(modalId) {
      document.getElementById(modalId).style.display = 'flex';
    }

    function closeModal(modalId) {
      document.getElementById(modalId).style.display = 'none';
      // Remove edit_user parameter from URL when closing modal
      if (modalId === 'editUserModal') {
        const url = new URL(window.location.href);
        url.searchParams.delete('edit_user');
        window.history.replaceState({}, '', url);
      }
    }

    function openEditModal(userId) {
      window.location.href = 'users.php?edit_user=' + userId;
    }

    function confirmDelete(userId) {
      if (confirm('Are you sure you want to delete this user?')) {
        window.location.href = 'users.php?delete_user=' + userId;
      }
    }

    // Open edit modal if we have edit_user parameter in URL
    window.onload = function() {
      const urlParams = new URLSearchParams(window.location.search);
      if (urlParams.has('edit_user')) {
        openModal('editUserModal');
      }
    };

    // Close modal when clicking outside of it
    window.onclick = function(event) {
      if (event.target.className === 'modal') {
        event.target.style.display = 'none';
        // Remove edit_user parameter from URL when closing modal
        if (event.target.id === 'editUserModal') {
          const url = new URL(window.location.href);
          url.searchParams.delete('edit_user');
          window.history.replaceState({}, '', url);
        }
      }
    }
  </script>
</body>
</html>