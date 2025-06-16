<?php
// submit_rating.php
session_start();

require_once '../includes/database/connection.php'; // $conn = new mysqli(...)

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if user is logged in and get reviewer_id from session
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    // Redirect to login or back to chat with error if user not logged in
    header("Location: ../views/chat.php?listing_id=0&user_id=0&error=not_logged_in");
    exit();
}

$reviewer_id = intval($_SESSION['user_id']);

// Sanitize and validate POST inputs
$listing_id = isset($_POST['listing_id']) ? intval($_POST['listing_id']) : 0;
$seller_id = isset($_POST['seller_id']) ? intval($_POST['seller_id']) : 0;
$rating_score = isset($_POST['rating_score']) ? intval($_POST['rating_score']) : 0;
$review_text = isset($_POST['review_text']) ? trim($_POST['review_text']) : '';

// Basic validation
if ($listing_id <= 0 || $seller_id <= 0 || $rating_score < 1 || $rating_score > 5) {
    // Redirect back with error
    header("Location: ../views/chat.php?listing_id=$listing_id&user_id=$seller_id&error=invalid_input");
    exit();
}

// Prepare and bind
$stmt = $conn->prepare("INSERT INTO ratings 
    (reviewer_id, seller_id, listing_id, rating_score, review_text, item_accuracy, communication_quality, shipping_speed, created_at) 
    VALUES (?, ?, ?, ?, ?, 0, 0, 0, NOW())");

if (!$stmt) {
    header("Location: ../views/chat.php?listing_id=$listing_id&user_id=$seller_id&error=db_error");
    exit();
}

$stmt->bind_param(
    "iiiis",
    $reviewer_id,
    $seller_id,
    $listing_id,
    $rating_score,
    $review_text
);

if ($stmt->execute()) {
    // Success: redirect back with success message
    header("Location: ../views/chat.php?listing_id=$listing_id&user_id=$seller_id&success=rating_submitted");
} else {
    // Failure: redirect back with error
    // header("Location: chat.php?listing_id=$listing_id&user_id=$seller_id&error=db_error");
    echo "Error: " . $stmt->error; // For debugging, remove in production
}

$stmt->close();
$conn->close();
exit();
?>