<?php
ini_set('session.cookie_secure', '0');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');

session_start();

$conn = new mysqli("localhost", "root", "", "Mobipay");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$error_message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $phone_number = $_POST["phone_number"];
    $password = $_POST["password"];
    $login_role = $_POST["login_role"]; // 'user' or 'admin'

    $sql = "SELECT user_id, password_hash, role, status FROM users WHERE phone_number = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $phone_number);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows == 1) {
        $stmt->bind_result($user_id, $password_hash, $actual_role, $status);
        $stmt->fetch();

        if ($status === "Inactive") {
            $error_message = "Your account has been deactivated.";
        } elseif ($login_role !== $actual_role) {
            $error_message = "Access denied: You are not logging in from the correct portal.";
        } elseif (password_verify($password, $password_hash)) {
            $_SESSION["user_id"] = $user_id;
            $_SESSION["role"] = $actual_role;

            // 🔁 Redirect based on role
            if ($actual_role === 'admin') {
                header("Location: dashboard_admin.php");
            } else {
                header("Location: dashboard.php");
            }
            exit();
        } else {
            $error_message = "Incorrect password.";
        }
    } else {
        $error_message = "User not found.";
    }

    $stmt->close();
}

$conn->close();
?>
<!DOCTYPE html>
<html>
<head>
    <title>MobiPay Login</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #e9f1f7;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }
        .login-container {
            background-color: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            width: 350px;
        }
        .tabs {
            display: flex;
            justify-content: space-around;
            margin-bottom: 20px;
        }
        .tabs button {
            padding: 10px;
            width: 48%;
            background-color: #dbe9f4;
            border: none;
            cursor: pointer;
            font-weight: bold;
            border-radius: 6px;
        }
        .tabs button.active {
            background-color: #014f86;
            color: white;
        }
        form {
            display: none;
        }
        form.active {
            display: block;
        }
        input[type="text"],
        input[type="password"],
        button[type="submit"] {
            width: 100%;
            padding: 10px;
            margin: 10px 0;
            border-radius: 6px;
            border: 1px solid #ccc;
            box-sizing: border-box;
        }
        button[type="submit"] {
            background-color: #014f86;
            color: white;
            cursor: pointer;
        }
        button[type="submit"]:hover {
            background-color: #007acc;
        }
        .error-message {
            color: red;
            text-align: center;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="tabs">
            <button type="button" onclick="showForm('user')" id="userTab" class="active">User Login</button>
            <button type="button" onclick="showForm('admin')" id="adminTab">Admin Login</button>
        </div>

        <!-- USER LOGIN FORM -->
        <form id="userForm" class="active" method="POST">
            <input type="hidden" name="login_role" value="user">
            <input type="text" name="phone_number" placeholder="Phone Number" required>
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit">Login as User</button>
        </form>

        <!-- ADMIN LOGIN FORM -->
        <form id="adminForm" method="POST">
            <input type="hidden" name="login_role" value="admin">
            <input type="text" name="phone_number" placeholder="Phone Number" required>
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit">Login as Admin</button>
        </form>

        <?php if (!empty($error_message)) : ?>
            <p class="error-message"><?php echo $error_message; ?></p>
        <?php endif; ?>
    </div>

    <script>
        function showForm(type) {
            document.getElementById('userForm').classList.remove('active');
            document.getElementById('adminForm').classList.remove('active');
            document.getElementById('userTab').classList.remove('active');
            document.getElementById('adminTab').classList.remove('active');

            document.getElementById(type + 'Form').classList.add('active');
            document.getElementById(type + 'Tab').classList.add('active');
        }
    </script>
</body>
</html>