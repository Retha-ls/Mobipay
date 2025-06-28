<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

// Change 'id' to 'AgentId' to match your database field
if (!isset($data['AgentId']) || !isset($data['status'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit;
}

$agentId = intval($data['AgentId']);
$status = $data['status'];

if (!in_array($status, ['Active', 'Inactive'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
    exit;
}

$conn = new mysqli("localhost", "root", "", "Mobipay");

if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Make sure the field name matches your database (AgentId)
$stmt = $conn->prepare("UPDATE agents SET Status = ? WHERE AgentId = ?");
$stmt->bind_param("si", $status, $agentId);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update status']);
}

$stmt->close();
$conn->close();
?>