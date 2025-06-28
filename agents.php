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

// Handle agent creation
if (isset($_POST['add_agent'])) {
  $agent_name = trim($_POST['agent_name']);
  $location = trim($_POST['location']);
  $phone = trim($_POST['phone']);

  // Basic validation
  if (empty($agent_name) || empty($location) || empty($phone)) {
      echo "<script>alert('All fields are required.');</script>";
  } else {
      $insert_sql = "INSERT INTO agents (AgentName, Location, Phone, Status) VALUES (?, ?, ?, 'Active')";
      $stmt = $conn->prepare($insert_sql);
      $stmt->bind_param("sss", $agent_name, $location, $phone);

      if ($stmt->execute()) {
          header("Location: agents.php"); // Redirect to refresh list
          exit();
      } else {
          echo "<script>alert('Error creating agent: " . $conn->error . "');</script>";
      }
      $stmt->close();
  }
}

// Handle agent actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['edit_agent'])) {
        $agent_id = intval($_POST['agent_id']);
        $agent_name = $_POST['agent_name'];
        $location = $_POST['location'];
        $phone = $_POST['phone'];
        $status = $_POST['status'];

        $update_sql = "UPDATE agents SET AgentName = ?, Location = ?, Phone = ?, Status = ? WHERE AgentId = ?";
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param("ssssi", $agent_name, $location, $phone, $status, $agent_id);

        if ($stmt->execute()) {
            header("Location: agents.php"); // Redirect after update
            exit();
        } else {
            echo "<script>alert('Failed to update agent.');</script>";
        }

        $stmt->close();
    }
}

// Get all agents
$agents = [];
$sql = "SELECT * FROM agents ORDER BY AgentName";
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $agents[] = $row;
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Agents Management - MobiPay</title>
  <style>
    /* Updated CSS to match the first example's navigation style */
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

    /* Rest of the existing CSS remains unchanged */
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
      background-color: rgb(224, 224, 224);
      color: rgb(0, 0, 0);
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
      <div class="user-avatar"><?php echo strtoupper(substr($manager_name, 0, 1)); ?></div>
    </div>
  </nav>

  <main>
    <div class="dashboard-header">
      <div class="dashboard-title">
        <h1>Agents Management</h1>
        <p>Manage all system agents and their locations</p>
      </div>
      <button class="btn btn-primary" onclick="openModal('addAgentModal')">Add New Agent</button>
    </div>
    
    <div class="data-table">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
        <h2>All Agents</h2>
        <div style="display: flex; gap: 1rem; margin-bottom: 1rem;">
          <input 
              type="text" 
              id="locationFilter" 
              placeholder="Filter by location..." 
              class="form-control" 
              onkeyup="filterAgents()"
          >
          <select 
              id="statusFilter" 
              class="form-control" 
              onchange="filterAgents()"
          >
              <option value="">All Statuses</option>
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
          </select>
        </div>
        <div style="display: flex; gap: 1rem;">
          <input type="text" id="agentSearch" placeholder="Search agents..." class="form-control" style="width: 300px;">
          <button class="btn btn-primary" onclick="searchAgents()">Search</button>
        </div>
      </div>
      
      <table>
        <thead>
          <tr>
            <th>ID</th>
            <th>Name</th>
            <th>Location</th>
            <th>Phone</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($agents as $agent): ?>
          <tr>
            <td><?php echo $agent['AgentId']; ?></td>
            <td><?php echo htmlspecialchars($agent['AgentName']); ?></td>
            <td><?php echo htmlspecialchars($agent['Location']); ?></td>
            <td><?php echo htmlspecialchars($agent['Phone']); ?></td>
            <td>
              <span class="badge <?php echo $agent['Status'] === 'Active' ? 'badge-success' : 'badge-warning'; ?>">
                <?php echo ucfirst($agent['Status']); ?>
              </span>
            </td>
            <td>
              <div class="action-buttons">
                <button class="action-btn edit-btn" onclick="openEditAgentModal(<?php echo $agent['AgentId']; ?>)">Edit</button>
                <button class="action-btn <?php echo $agent['Status'] === 'Active' ? 'delete-btn' : 'btn-primary'; ?>" onclick="toggleAgentStatus(<?php echo $agent['AgentId']; ?>, '<?php echo $agent['Status']; ?>')"><?php echo $agent['Status'] === 'Active' ? 'Deactivate' : 'Activate'; ?></button>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </main>

  <!-- Add Agent Modal -->
  <div class="modal" id="addAgentModal">
    <div class="modal-content">
      <div class="modal-header">
        <h2>Add New Agent</h2>
        <button class="close-btn" onclick="closeModal('addAgentModal')">&times;</button>
      </div>
      <form method="POST" onsubmit="return validateAgentForm()">
    <div class="form-group">
        <label class="form-label" for="agentName">Agent Name</label>
        <input type="text" id="agentName" name="agent_name" class="form-control" required maxlength="50">
    </div>
    <div class="form-group">
        <label class="form-label" for="agentLocation">Location</label>
        <input type="text" id="agentLocation" name="location" class="form-control" required maxlength="100">
    </div>
    <div class="form-group">
        <label class="form-label" for="agentPhone">Phone Number</label>
        <input type="tel" id="agentPhone" name="phone" class="form-control" 
               pattern="[0-9]{10,15}" title="10-15 digit phone number" required>
    </div>
    <button type="submit" name="add_agent" class="submit-btn">Add Agent</button>
</form>
    </div>
  </div>

  <!-- Edit Agent Modal -->
  <div class="modal" id="editAgentModal">
    <div class="modal-content">
      <div class="modal-header">
        <h2>Edit Agent</h2>
        <button class="close-btn" onclick="closeModal('editAgentModal')">&times;</button>
      </div>
      <form method="POST">
        <input type="hidden" name="agent_id" id="edit_agent_id">
        <div class="form-group">
          <label class="form-label" for="edit_agentName">Agent Name</label>
          <input type="text" id="edit_agentName" name="agent_name" class="form-control" required>
        </div>
        <div class="form-group">
          <label class="form-label" for="edit_agentLocation">Location</label>
          <input type="text" id="edit_agentLocation" name="location" class="form-control" required>
        </div>
        <div class="form-group">
          <label class="form-label" for="edit_agentPhone">Phone Number</label>
          <input type="tel" id="edit_agentPhone" name="phone" class="form-control" required>
        </div>
        <div class="form-group">
          <label class="form-label" for="edit_agentStatus">Status</label>
          <select id="edit_agentStatus" name="status" class="form-control" required>
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
          </select>
        </div>
        <button type="submit" name="edit_agent" class="submit-btn">Save Changes</button>
      </form>
    </div>
  </div>

  <script>
    // Agent status management
    // Agent status management
function toggleAgentStatus(agentId, currentStatus) {
    const newStatus = currentStatus === 'Active' ? 'Inactive' : 'Active';
    if (confirm(`Are you sure you want to set this agent to ${newStatus}?`)) {
        updateAgentStatus(agentId, newStatus);
    }
}

function validateAgentForm() {
    const phone = document.getElementById('agentPhone').value;
    if (!/^\d{8,15}$/.test(phone)) {
        alert('Please enter a valid phone number (8-15 digits)');
        return false;
    }
    return true;
}

function updateAgentStatus(agentId, status) {
    fetch('update_agent_status.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            AgentId: agentId,
            status: status
        })
    })
    .then(response => {
        if (!response.ok) throw new Error('Network error');
        return response.json();
    })
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert(data.message || 'Update failed');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error: ' + error.message);
    });
}

function searchAgents() {
    const query = document.getElementById('agentSearch').value.toLowerCase();
    document.querySelectorAll('tbody tr').forEach(row => {
        const name = row.cells[1].textContent.toLowerCase();
        const phone = row.cells[3].textContent.toLowerCase();
        const location = row.cells[2].textContent.toLowerCase();

        const match = name.includes(query) || phone.includes(query) || location.includes(query);
        row.style.display = match ? '' : 'none';
    });
}

// Filter functionality
function filterAgents() {
    const locationFilter = document.getElementById('locationFilter').value.toLowerCase();
    const statusFilter = document.getElementById('statusFilter').value.toLowerCase();

    document.querySelectorAll('tbody tr').forEach(row => {
        const location = row.cells[2].textContent.toLowerCase();
        const statusSpan = row.cells[4].querySelector('span');
        const status = statusSpan ? statusSpan.textContent.toLowerCase().trim() : '';

        const locationMatches = location.includes(locationFilter);
        const statusMatches = statusFilter === '' || status === statusFilter;

        row.style.display = (locationMatches && statusMatches) ? '' : 'none';
    });
}

function openEditAgentModal(agentId) {
    // Find agent data from the row (basic inline approach)
    const row = Array.from(document.querySelectorAll('tbody tr')).find(
        tr => tr.cells[0].textContent == agentId
    );
    
    if (row) {
        document.getElementById('edit_agent_id').value = agentId;
        document.getElementById('edit_agentName').value = row.cells[1].textContent.trim();
        document.getElementById('edit_agentLocation').value = row.cells[2].textContent.trim();
        document.getElementById('edit_agentPhone').value = row.cells[3].textContent.trim();
        document.getElementById('edit_agentStatus').value = row.cells[4].textContent.trim().toLowerCase();
        
        openModal('editAgentModal');
    }
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
    // Set up filter event listeners
    document.getElementById('locationFilter').addEventListener('input', filterAgents);
    document.getElementById('statusFilter').addEventListener('change', filterAgents);
    
    // Close modals when clicking outside
    document.addEventListener('click', function(event) {
        if (event.target.classList.contains('modal')) {
            event.target.style.display = 'none';
        }
    });
});

    function openEditAgentModal(agentId) {
        // Find agent data from the row (basic inline approach)
        const row = Array.from(document.querySelectorAll('tbody tr')).find(
            tr => tr.cells[0].textContent == agentId
        );
        
        if (row) {
            document.getElementById('edit_agent_id').value = agentId;
            document.getElementById('edit_agentName').value = row.cells[1].textContent.trim();
            document.getElementById('edit_agentLocation').value = row.cells[2].textContent.trim();
            document.getElementById('edit_agentPhone').value = row.cells[3].textContent.trim();
            document.getElementById('edit_agentStatus').value = row.cells[4].textContent.trim().toLowerCase();
            
            openModal('editAgentModal');
        }
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
        // Set up filter event listeners
        document.getElementById('locationFilter').addEventListener('input', filterAgents);
        document.getElementById('statusFilter').addEventListener('change', filterAgents);
        
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