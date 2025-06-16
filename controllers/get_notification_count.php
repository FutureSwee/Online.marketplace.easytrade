<?php
// controllers/get_notification_count.php
session_start();

// Include your database connection
// Assuming you have a config file or database connection
include_once '../includes/database/connection.php'; // Adjust path as needed

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['userArray'])) {
    echo json_encode(['count' => 0]);
    exit;
}

$user_id = $_SESSION['userArray']['id'];

try {
    // Get unread message count
    $stmt = $conn->prepare("SELECT COUNT(*) as unread_count FROM chat_messages WHERE receiver_id = ? AND read_status = FALSE");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    echo json_encode(['count' => (int) $row['unread_count']]);

} catch (Exception $e) {
    echo json_encode(['count' => 0, 'error' => 'Database error']);
}

$conn->close();
?>