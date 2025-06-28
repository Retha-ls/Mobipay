<?php
session_start();

$error_message = "";
$success_message = "";

// Handle signup form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Database connection
    $conn = new mysqli("localhost", "root", "", "Mobipay");
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    $full_name = $_POST['full_name'];
    $email = $_POST['email'];
    $phone_number = $_POST['phone_number'];
    $password = $_POST['password'];
    $pin = $_POST['pin'];

    // Check if phone or email already exists
    $check_sql = "SELECT user_id FROM users WHERE email = ? OR phone_number = ?";
    $stmt_check = $conn->prepare($check_sql);
    $stmt_check->bind_param("ss", $email, $phone_number);
    $stmt_check->execute();
    $stmt_check->store_result();
    if ($stmt_check->num_rows > 0) {
        $error_message = "Email or phone number already registered.";
    } else {
        // Hash password and PIN
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        $pin_hash = password_hash($pin, PASSWORD_DEFAULT);

        // Insert into users table
        $insert_user = "INSERT INTO users (full_name, email, phone_number, password_hash, pin_hash) VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($insert_user);
        $stmt->bind_param("sssss", $full_name, $email, $phone_number, $password_hash, $pin_hash);
        if ($stmt->execute()) {
            $user_id = $stmt->insert_id;

            // Create wallet with balance 0.00
            $insert_wallet = "INSERT INTO wallets (user_id, current_balance) VALUES (?, 0.00)";
            $stmt_wallet = $conn->prepare($insert_wallet);
            $stmt_wallet->bind_param("i", $user_id);
            $stmt_wallet->execute();
            $stmt_wallet->close();

            // Redirect to login.php after successful signup
            header("Location: login.php");
            exit();
        } else {
            $error_message = "Signup failed. Please try again.";
        }
        $stmt->close();
    }
    $stmt_check->close();
    $conn->close();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>MobiPay Signup</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #e9f1f7;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }
        .signup-form {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            width: 400px;
        }
        .signup-form h2 {
            margin-bottom: 20px;
            color: #014f86;
        }
        .signup-form input {
            width: 100%;
            padding: 10px;
            margin: 10px 0;
            border-radius: 6px;
            border: 1px solid #ccc;
        }
        .signup-form button {
            background-color: #014f86;
            color: white;
            border: none;
            padding: 10px;
            width: 100%;
            border-radius: 6px;
            cursor: pointer;
        }
        .signup-form button:hover {
            background-color: #007acc;
        }
        .message {
            color: red;
            text-align: center;
            margin-top: 10px;
        }
        .success {
            color: green;
        }
    </style>
</head>
<body>
    <form class="signup-form" method="POST">
        <h2>Create Your MobiPay Account</h2>
        <input type="text" name="full_name" placeholder="Full Name" required>
        <input type="email" name="email" placeholder="Email Address" required>
        <input type="text" name="phone_number" placeholder="Phone Number" required>
        <input type="password" name="password" placeholder="Password" required>
        <input type="password" name="pin" placeholder="4-digit PIN" required pattern="\d{4}">
        <button type="submit">Sign Up</button>

        <?php if ($error_message): ?>
            <div class="message"><?php echo $error_message; ?></div>
        <?php elseif ($success_message): ?>
            <div class="message success"><?php echo $success_message; ?></div>
        <?php endif; ?>
    </form>
</body>
</html>
