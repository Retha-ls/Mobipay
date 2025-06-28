<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header("Location: login.php");
    exit();
}


$user_full_name = 'Guest';
$current_balance = 0;
$error_message = '';
$send_error = '';
$cash_out_error = '';

$conn = new mysqli("localhost", "root", "", "Mobipay");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$user_id = $_SESSION['user_id'];

$sql = "SELECT full_name FROM users WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($user_full_name);
$stmt->fetch();
$stmt->close();

$sql_balance = "SELECT current_balance FROM wallets WHERE user_id = ?";
$stmt_balance = $conn->prepare($sql_balance);
$stmt_balance->bind_param("i", $user_id);
$stmt_balance->execute();
$stmt_balance->bind_result($current_balance);
$stmt_balance->fetch();
$stmt_balance->close();

// Get list of agents for cash out
$agents = [];
$sql_agents = "SELECT AgentID, AgentName FROM agents ORDER BY AgentName";
$result_agents = $conn->query($sql_agents);
if ($result_agents->num_rows > 0) {
    while ($row = $result_agents->fetch_assoc()) {
        $agents[] = $row;
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['top_up'])) {
        $top_up_amount = floatval($_POST['top_up_amount']);
        $user_password = $_POST['password'];
        $transaction_date = $_POST['transaction_date'];
        $description = $_POST['description'];
        $transaction_type = "topup";

        if ($top_up_amount <= 0) {
            $error_message = "Amount must be greater than zero";
        } else {
            $sql_password = "SELECT password_hash FROM users WHERE user_id = ?";
            $stmt_password = $conn->prepare($sql_password);
            $stmt_password->bind_param("i", $user_id);
            $stmt_password->execute();
            $stmt_password->bind_result($stored_password_hash);
            $stmt_password->fetch();
            $stmt_password->close();

            if (password_verify($user_password, $stored_password_hash)) {
                $new_balance = $current_balance + $top_up_amount;

                $sql_update_balance = "UPDATE wallets SET current_balance = ? WHERE user_id = ?";
                $stmt_update_balance = $conn->prepare($sql_update_balance);
                $stmt_update_balance->bind_param("di", $new_balance, $user_id);
                $stmt_update_balance->execute();
                $stmt_update_balance->close();

                $sql_insert_transaction = "INSERT INTO transaction (user_id, type, amount, date, description) VALUES (?, ?, ?, ?, ?)";
                $stmt_insert_transaction = $conn->prepare($sql_insert_transaction);
                $stmt_insert_transaction->bind_param("issss", $user_id, $transaction_type, $top_up_amount, $transaction_date, $description);
                $stmt_insert_transaction->execute();
                $stmt_insert_transaction->close();

                header("Location: dashboard.php");
                exit();
            } else {
                $error_message = "Incorrect password!";
            }
        }
    }

    if (isset($_POST['send_money'])) {
        $recipient_phone = trim($_POST['recipient_phone']);
        $send_amount = floatval($_POST['send_amount']);
        $send_date = $_POST['send_date'];
        $send_description = $_POST['send_description'];

        if (empty($recipient_phone)) {
            $send_error = "Recipient phone number is required";
        } elseif ($send_amount <= 0) {
            $send_error = "Amount must be greater than zero";
        } elseif ($send_amount > $current_balance) {
            $send_error = "Insufficient funds in your wallet";
        } else {
            $sql_recipient = "SELECT user_id FROM users WHERE phone_number = ?";
            $stmt_recipient = $conn->prepare($sql_recipient);
            $stmt_recipient->bind_param("s", $recipient_phone);
            $stmt_recipient->execute();
            $stmt_recipient->bind_result($recipient_id);
            $recipient_found = $stmt_recipient->fetch();
            $stmt_recipient->close();

            if (!$recipient_found) {
                $send_error = "Recipient with phone number $recipient_phone not found";
            } elseif ($recipient_id == $user_id) {
                $send_error = "You cannot send money to yourself";
            } else {
                $new_sender_balance = $current_balance - $send_amount;

                $sql_deduct = "UPDATE wallets SET current_balance = ? WHERE user_id = ?";
                $stmt_deduct = $conn->prepare($sql_deduct);
                $stmt_deduct->bind_param("di", $new_sender_balance, $user_id);
                $stmt_deduct->execute();
                $stmt_deduct->close();

                $sql_get_recipient_balance = "SELECT current_balance FROM wallets WHERE user_id = ?";
                $stmt_get = $conn->prepare($sql_get_recipient_balance);
                $stmt_get->bind_param("i", $recipient_id);
                $stmt_get->execute();
                $stmt_get->bind_result($recipient_balance);
                $stmt_get->fetch();
                $stmt_get->close();

                $new_recipient_balance = $recipient_balance + $send_amount;

                $sql_add = "UPDATE wallets SET current_balance = ? WHERE user_id = ?";
                $stmt_add = $conn->prepare($sql_add);
                $stmt_add->bind_param("di", $new_recipient_balance, $recipient_id);
                $stmt_add->execute();
                $stmt_add->close();

                $sql_send_log = "INSERT INTO transaction (user_id, type, amount, date, description) VALUES (?, ?, ?, ?, ?)";
                $stmt_log = $conn->prepare($sql_send_log);
                $type_send = "send";
                $stmt_log->bind_param("issss", $user_id, $type_send, $send_amount, $send_date, $send_description);
                $stmt_log->execute();
                $stmt_log->close();

                $sql_receive_log = "INSERT INTO transaction (user_id, type, amount, date, description) VALUES (?, ?, ?, ?, ?)";
                $stmt_receive = $conn->prepare($sql_receive_log);
                $type_receive = "received";
                $stmt_receive->bind_param("issss", $recipient_id, $type_receive, $send_amount, $send_date, $send_description);
                $stmt_receive->execute();
                $stmt_receive->close();

                header("Location: dashboard.php");
                exit();
            }
        }
    }

    if (isset($_POST['cash_out'])) {
        $agent_id = intval($_POST['agent_id']);
        $cash_out_amount = floatval($_POST['cash_out_amount']);
        $cash_out_date = $_POST['cash_out_date'];
        $cash_out_description = $_POST['cash_out_description'];

        if ($cash_out_amount <= 0) {
            $cash_out_error = "Amount must be greater than zero";
        } elseif ($cash_out_amount > $current_balance) {
            $cash_out_error = "Insufficient funds in your wallet";
        } else {
            // Start transaction
            $conn->begin_transaction();

            try {
                // 1. Update wallet balance
                $new_balance = $current_balance - $cash_out_amount;
                $sql_update_balance = "UPDATE wallets SET current_balance = ? WHERE user_id = ?";
                $stmt_update_balance = $conn->prepare($sql_update_balance);
                $stmt_update_balance->bind_param("di", $new_balance, $user_id);
                $stmt_update_balance->execute();
                $stmt_update_balance->close();

                // 2. Get agent name for description
                $agent_name = "";
                $sql_agent = "SELECT AgentName FROM agents WHERE AgentID = ?";
                $stmt_agent = $conn->prepare($sql_agent);
                $stmt_agent->bind_param("i", $agent_id);
                $stmt_agent->execute();
                $stmt_agent->bind_result($agent_name);
                $stmt_agent->fetch();
                $stmt_agent->close();

                // 3. Record transaction
                $description_with_agent = "Cash out to " . $agent_name;
                if (!empty($cash_out_description)) {
                    $description_with_agent .= " - " . $cash_out_description;
                }
                
                $sql_insert_transaction = "INSERT INTO transaction (user_id, type, amount, date, description) 
                                          VALUES (?, ?, ?, ?, ?)";
                $stmt_insert_transaction = $conn->prepare($sql_insert_transaction);
                $transaction_type = "cashout";
                $stmt_insert_transaction->bind_param("issss", $user_id, $transaction_type, $cash_out_amount, $cash_out_date, $description_with_agent);
                $stmt_insert_transaction->execute();
                
                if ($stmt_insert_transaction->affected_rows <= 0) {
                    throw new Exception("Failed to record transaction");
                }
                $stmt_insert_transaction->close();
                $sql_bridge = "INSERT INTO user_agent_bridge (user_id, AgentId) 
                          VALUES (?, ?)
                          ON DUPLICATE KEY UPDATE user_id = user_id"; // No-op if exists
            
            $stmt_bridge = $conn->prepare($sql_bridge);
            $stmt_bridge->bind_param("ii", $user_id, $agent_id);
            $stmt_bridge->execute();
            
            if ($stmt_bridge->affected_rows < 0) {
                throw new Exception("Failed to update agent relationship");
            }
            $stmt_bridge->close();
                // Commit transaction
                $conn->commit();

                header("Location: dashboard.php");
                exit();
            } catch (Exception $e) {
                // Rollback transaction on error
                $conn->rollback();
                $cash_out_error = "Transaction failed: " . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Wallet - MobiPay</title>
  <style>
    body {
      margin: 0;
      font-family: Arial, sans-serif;
      background-color: #f3f4f6;
    }
    nav {
      background-color: rgb(13, 10, 10);
      box-shadow: 0 2px 4px rgba(0,0,0,0.1);
      padding: 16px;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    .logo-container {
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .logo-container img {
      height: 50px;
    }
    .logo-text {
      font-size: 1.5rem;
      font-weight: bold;
      color: white;
    }
    .nav-items {
      display: flex;
      gap: 24px;
    }
    .nav-item {
      display: flex;
      align-items: center;
      gap: 4px;
      color: white;
      cursor: pointer;
      transition: color 0.3s;
      font-size: x-large;
      letter-spacing: px;
    }
    .nav-item:hover {
      color: rgb(84, 84, 89);
    }
    .logout-button {
      padding: 8px 16px;
      border: 1px solid #cbd5e1;
      background-color: #ffffff;
      cursor: pointer;
      border-radius: 4px;
    }
    main {
      padding: 50px;
      display: grid;
      grid-template-columns: 1fr;
      gap: 24px;
    }
    @media(min-width: 768px) {
      main {
        grid-template-columns: repeat(2, 1fr);
      }
    }
    .card {
      background-color: #ffffff;
      padding: 24px;
      border-radius: 16px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    }
    .card h2, .card h3 {
      margin-top: 0;
    }
    .button {
      padding: 10px 16px;
      background-color: rgb(0, 0, 0);
      color: white;
      border: none;
      border-radius: 4px;
      width: 100%;
      margin-top: 8px;
      cursor: pointer;
      transition: all 0.3s;
    }
    .button:hover {
      background-color: rgb(50, 50, 50);
      transform: translateY(-2px);
    }
    .button-outline {
      background-color: white;
      color: rgb(0, 0, 0);
      border: 1px solid rgb(0, 0, 0);
    }
    .button-outline:hover {
      background-color: rgb(240, 240, 240);
    }
    .balance-card {
      background-color: #ffffff;
      padding: 24px;
      border-radius: 16px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.05);
      text-align: center;
    }
    .balance {
      font-size: 2rem;
      color: rgb(2, 119, 45);
      margin-top: 8px;
    }
    .transactions-table {
      width: 100%;
      border-collapse: collapse;
    }
    .transactions-table th, .transactions-table td {
      border: 1px solid #e5e7eb;
      padding: 8px;
      text-align: left;
    }
    .transactions-table th {
      background-color: #f9fafb;
    }
    /* Dropdown Styles */
    .dropdown {
      position: relative;
      display: inline-block;
    }
    .dropdown button {
      background: transparent;
      border: none;
      padding: 8px 16px;
      font-size: 25px;
      color: white;
      cursor: pointer;
      outline: none;
      display: inline-block;
      width: auto;
      transition: transform 0.3s ease;
    }
    .dropdown:hover button {
      transform: scale(1.2);
    }
    .dropdown-content {
      display: none;
      position: absolute;
      background-color: #f1f1f1;
      min-width: 160px;
      box-shadow: 0px 8px 16px rgba(0, 0, 0, 0.2);
      z-index: 1;
      border-radius: 4px;
      margin-top: 8px;
      opacity: 0;
      visibility: hidden;
      transition: opacity 0.2s ease, visibility 0.2s ease;
    }
    .dropdown:hover .dropdown-content,
    .dropdown-content:hover {
      display: block;
      opacity: 1;
      visibility: visible;
    }
    .dropdown-content a {
      color: black;
      padding: 12px 16px;
      text-decoration: none;
      display: block;
      cursor: pointer;
      transition: background-color 0.3s;
    }
    .dropdown-content a:hover {
      background-color: #ddd;
    }
    /* Modal Styles */
    .modal {
      display: none;
      position: fixed;
      z-index: 1000;
      left: 0;
      top: 0;
      width: 100%;
      height: 100%;
      background-color: rgba(0,0,0,0.5);
      backdrop-filter: blur(5px);
    }
    .modal-content {
      background-color: #fff;
      margin: 10% auto;
      padding: 30px;
      border: none;
      width: 90%;
      max-width: 500px;
      border-radius: 10px;
      position: relative;
      box-shadow: 0 10px 30px rgba(0,0,0,0.2);
      animation: modalFadeIn 0.3s ease-out;
    }
    @keyframes modalFadeIn {
      from { opacity: 0; transform: translateY(-20px); }
      to { opacity: 1; transform: translateY(0); }
    }
    .modal h3 {
      color: rgb(0, 0, 0);
      margin-top: 0;
      margin-bottom: 25px;
      font-size: 24px;
      text-align: center;
    }
    .close {
      position: absolute;
      right: 25px;
      top: 20px;
      font-size: 28px;
      font-weight: bold;
      color: #aaa;
      cursor: pointer;
      transition: color 0.3s ease;
    }
    .close:hover {
      color: #d32f2f;
    }
    .form-group {
      margin-bottom: 20px;
    }
    .form-label {
      display: block;
      margin-bottom: 8px;
      font-weight: 500;
      color: #333;
    }
    .form-input {
      width: 100%;
      padding: 12px 15px;
      border: 1px solid #ddd;
      border-radius: 6px;
      font-size: 16px;
      transition: border-color 0.3s ease, box-shadow 0.3s ease;
    }
    .form-input:focus {
      border-color: rgb(0, 0, 0);
      outline: none;
      box-shadow: 0 0 0 3px rgba(0,0,0,0.1);
    }
    .form-select {
      width: 100%;
      padding: 12px 15px;
      border: 1px solid #ddd;
      border-radius: 6px;
      font-size: 16px;
      background-color: white;
      transition: border-color 0.3s ease, box-shadow 0.3s ease;
    }
    .form-select:focus {
      border-color: rgb(0, 0, 0);
      outline: none;
      box-shadow: 0 0 0 3px rgba(0,0,0,0.1);
    }
    .form-button {
      width: 100%;
      padding: 15px;
      background-color: rgb(0, 0, 0);
      color: white;
      font-weight: 500;
      border: none;
      border-radius: 6px;
      font-size: 16px;
      cursor: pointer;
      transition: all 0.3s ease;
      margin-top: 10px;
    }
    .form-button:hover {
      background-color: rgb(50, 50, 50);
      transform: translateY(-2px);
      box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }
    .error-message {
      color: #d32f2f;
      font-size: 14px;
      margin-top: 10px;
      padding: 10px;
      background-color: rgba(211,47,47,0.1);
      border-radius: 4px;
      text-align: center;
      animation: fadeIn 0.3s ease;
    }
    @keyframes fadeIn {
      from { opacity: 0; }
      to { opacity: 1; }
    }
    /* Service Providers Styles */
    .service-providers {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
      gap: 30px;
    }
    .provider-card {
      display: flex;
      flex-direction: column;
      align-items: center;
      padding: 30px;
      border-radius: 10px;
      background-color: #f8f9fa;
      cursor: pointer;
      transition: all 0.3s ease;
      border: 1px solid #e5e7eb;
    }
    .provider-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 4px 8px rgba(0,0,0,0.1);
      background-color: #ffffff;
    }
    .provider-card img {
      width: 150px;
      height: 90px;
      object-fit: contain;
      margin-bottom: 10px;
    }
    .provider-card span {
      font-weight: 500;
      color: #333;
    }
  </style>
</head>
<body>
  <nav>
    <div class="logo-container">
      <img src="logos.png" alt="MobiPay Logo">
      <div class="logo-text">MobiPay</div>
    </div>
    <div class="nav-items">
      <div class="nav-item">Home</div>
      <div class="nav-item dropdown">
        <button>Services</button>
        <div class="dropdown-content">
          <a href="#" onclick="openModal('sendMoneyModal')">Send Money</a>
          <a href="#" onclick="openModal('cashOutModal')">Cash out</a>
          <a href="#" onclick="openModal('topUpModal')">Top Up</a>
        </div>
      </div>
      <div class="nav-item">Wallet</div>
      <div class="nav-item">Activity</div>
      <div class="nav-item">Help</div>
      <div class="nav-item">Reports</div>
    </div>
    <button class="logout-button">Log Out</button>
  </nav>

  <main>
    <div class="card" style="grid-column: span 2;">
      <h2>Welcome, <?php echo htmlspecialchars($user_full_name); ?>!</h2>
      <p>Manage your wallet, review transactions, and access relevant services.</p>
    </div>

    <div class="balance-card">
      <h3>Wallet Balance</h3>
      <div class="balance"><?php echo number_format($current_balance, 2); ?></div>
    </div>

    <div class="card">
      <h3>Quick Actions</h3>
      <button class="button" onclick="openModal('sendMoneyModal')">Send Money</button>
      <button class="button button-outline" onclick="openModal('cashOutModal')">Cash Out</button>
      <button class="button" onclick="openModal('topUpModal')">Top Up</button>
    </div>

    <!-- Service Providers Card -->
    <div class="card" style="grid-column: span 2;">
      <h3>Service Providers</h3>
      <div class="service-providers">
        <div class="provider-card" onclick="handleProviderClick('lec')">
          <img src="lec.png" alt="LEC">
          <span>LEC</span>
        </div>
        <div class="provider-card" onclick="handleProviderClick('wasco')">
          <img src="wasco.png" alt="WASCO">
          <span>WASCO</span>
        </div>
        <div class="provider-card" onclick="handleProviderClick('vodacom')">
          <img src="vodacom.png" alt="Vodacom">
          <span>Vodacom</span>
        </div>
        <div class="provider-card" onclick="handleProviderClick('econet')">
          <img src="econet.png" alt="Econet">
          <span>Econet</span>
        </div>
        <div class="provider-card" onclick="handleProviderClick('alliance')">
          <img src="alliance.png" alt="Alliance">
          <span>Alliance</span>
        </div>
        <div class="provider-card" onclick="handleProviderClick('dstv')">
          <img src="dstv.png" alt="DSTV">
          <span>DSTV</span>
        </div>
        <div class="provider-card" onclick="handleProviderClick('metropolitan')">
          <img src="metropolitan.png" alt="Metropolitan">
          <span>Metropolitan</span>
        </div>
        <div class="provider-card" onclick="handleProviderClick('laa')">
          <img src="laa.png" alt="LAA">
          <span>LAA</span>
        </div>
      </div>
    </div>

    <div class="card">
      <h3>Recent Transactions</h3>
      <table class="transactions-table">
        <thead>
          <tr>
            <th>Date</th>
            <th>Type</th>
            <th>Amount</th>
            <th>Description</th>
          </tr>
        </thead>
        <tbody>
          <?php
          $conn = new mysqli("localhost", "root", "", "Mobipay");
          $sql = "SELECT type, amount, date, description FROM transaction WHERE user_id = ? ORDER BY date DESC LIMIT 5";
          $stmt = $conn->prepare($sql);
          $stmt->bind_param("i", $user_id);
          $stmt->execute();
          $stmt->bind_result($type, $amount, $date, $description);
          while ($stmt->fetch()): ?>
          <tr>
            <td><?php echo $date; ?></td>
            <td><?php echo ucfirst($type); ?></td>
            <td><?php 
                $sign = ($type === 'send' || $type === 'cashout') ? "-" : "+";
                echo $sign . "M" . number_format($amount, 2); 
            ?></td>
            <td><?php echo htmlspecialchars($description); ?></td>
          </tr>
          <?php endwhile; 
          $stmt->close();
          $conn->close();
          ?>
        </tbody>
      </table>
    </div>
  </main>

  <!-- Top Up Modal -->
  <div id="topUpModal" class="modal">
    <div class="modal-content">
      <span class="close" onclick="closeModal('topUpModal')">&times;</span>
      <h3>Top Up Wallet</h3>
      <form method="POST">
        <div class="form-group">
          <label class="form-label" for="top_up_amount">Amount</label>
          <input type="number" id="top_up_amount" name="top_up_amount" class="form-input" placeholder="0.00" min="0.01" step="0.01" required>
        </div>
        <div class="form-group">
          <label class="form-label" for="password">Password</label>
          <input type="password" id="password" name="password" class="form-input" placeholder="Enter your password" required>
        </div>
        <div class="form-group">
          <label class="form-label" for="description">Description</label>
          <input type="text" id="description" name="description" class="form-input" placeholder="Transaction description" required>
        </div>
        <div class="form-group">
          <label class="form-label" for="transaction_date">Date</label>
          <input type="date" id="transaction_date" name="transaction_date" class="form-input" required>
        </div>
        <button type="submit" name="top_up" class="form-button">Top Up</button>
        <?php if (!empty($error_message)): ?>
          <div class="error-message"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>
      </form>
    </div>
  </div>

  <!-- Send Money Modal -->
  <div id="sendMoneyModal" class="modal">
    <div class="modal-content">
      <span class="close" onclick="closeModal('sendMoneyModal')">&times;</span>
      <h3>Send Money</h3>
      <form method="POST">
        <div class="form-group">
          <label class="form-label" for="recipient_phone">Recipient Phone</label>
          <input type="text" id="recipient_phone" name="recipient_phone" class="form-input" placeholder="Enter recipient's phone number" required>
        </div>
        <div class="form-group">
          <label class="form-label" for="send_amount">Amount</label>
          <input type="number" id="send_amount" name="send_amount" class="form-input" placeholder="0.00" min="0.01" step="0.01" required>
        </div>
        <div class="form-group">
          <label class="form-label" for="send_date">Date</label>
          <input type="date" id="send_date" name="send_date" class="form-input" required>
        </div>
        <div class="form-group">
          <label class="form-label" for="send_description">Description</label>
          <input type="text" id="send_description" name="send_description" class="form-input" placeholder="Payment description" required>
        </div>
        <button type="submit" name="send_money" class="form-button">Send Money</button>
        <?php if (!empty($send_error)): ?>
          <div class="error-message"><?php echo htmlspecialchars($send_error); ?></div>
        <?php endif; ?>
      </form>
    </div>
  </div>

  <!-- Cash Out Modal -->
  <!-- Cash Out Modal -->
  <div id="cashOutModal" class="modal">
    <div class="modal-content">
      <span class="close" onclick="closeModal('cashOutModal')">&times;</span>
      <h3>Cash Out</h3>
      <form method="POST">
        <div class="form-group">
          <label class="form-label" for="agent_id">Select Agent</label>
          <select id="agent_id" name="agent_id" class="form-select" required>
            <option value="">-- Select Agent --</option>
            <?php foreach ($agents as $agent): ?>
              <option value="<?php echo $agent['AgentID']; ?>">
                <?php echo htmlspecialchars($agent['AgentName'] . " (ID: " . $agent['AgentID'] . ")"); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label" for="cash_out_amount">Amount</label>
          <input type="number" id="cash_out_amount" name="cash_out_amount" class="form-input" 
                 placeholder="0.00" min="0.01" step="0.01" required>
        </div>
        <div class="form-group">
          <label class="form-label" for="cash_out_date">Date</label>
          <input type="date" id="cash_out_date" name="cash_out_date" class="form-input" required>
        </div>
        <div class="form-group">
          <label class="form-label" for="cash_out_description">Description</label>
          <input type="text" id="cash_out_description" name="cash_out_description" 
                 class="form-input" placeholder="Optional description">
        </div>
        <button type="submit" name="cash_out" class="form-button">Cash Out</button>
        <?php if (!empty($cash_out_error)): ?>
          <div class="error-message"><?php echo htmlspecialchars($cash_out_error); ?></div>
        <?php endif; ?>
      </form>
    </div>
  </div>

  <script>
    function openModal(id) {
      document.getElementById(id).style.display = "block";
      // Reset error messages when opening modal
      const errorMessages = document.querySelectorAll('.error-message');
      errorMessages.forEach(msg => msg.style.display = 'none');
      
      // Set today's date as default for date fields
      const today = new Date().toISOString().split('T')[0];
      document.querySelectorAll('input[type="date"]').forEach(input => {
        if (!input.value) input.value = today;
      });
    }

    function closeModal(id) {
      document.getElementById(id).style.display = "none";
    }

    window.onclick = function(event) {
      const modals = document.querySelectorAll('.modal');
      modals.forEach(modal => {
        if (event.target === modal) {
          modal.style.display = "none";
        }
      });
    }

    // Focus first input when modal opens
    document.addEventListener('DOMContentLoaded', function() {
      const modals = document.querySelectorAll('.modal');
      modals.forEach(modal => {
        modal.addEventListener('shown.bs.modal', function() {
          const input = modal.querySelector('input');
          if (input) input.focus();
        });
      });
    });

    // Service Provider Click Handler
    function handleProviderClick(provider) {
      // You can customize what happens when each provider is clicked
      switch(provider) {
        case 'lec':
          alert('LEC Bill Payment selected');
          // window.location.href = 'lec_payment.php';
          break;
        case 'wasco':
          alert('WASCO Bill Payment selected');
          // window.location.href = 'wasco_payment.php';
          break;
        case 'vodacom':
          alert('Vodacom Airtime selected');
          // window.location.href = 'vodacom_airtime.php';
          break;
        case 'econet':
          alert('Econet Airtime selected');
          // window.location.href = 'econet_airtime.php';
          break;
        case 'alliance':
          alert('Alliance Insurance selected');
          // window.location.href = 'alliance_insurance.php';
          break;
        case 'dstv':
          alert('DSTV Subscription selected');
          // window.location.href = 'dstv_payment.php';
          break;
        case 'metropolitan':
          alert('Metropolitan selected');
          // window.location.href = 'metropolitan.php';
          break;
        case 'laa':
          alert('LAA selected');
          // window.location.href = 'laa_payment.php';
          break;
        default:
          alert('Service provider selected');
      }
    }
  </script>
</body>
</html>
