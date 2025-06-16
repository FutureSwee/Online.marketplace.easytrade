<?php
ob_start();
// fetch_products.php - API to fetch products by category
require_once '../includes/database/connection.php';

header('Content-Type: application/json');

try {
    // Start session if not already started
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }

    // Get current user ID
    $current_user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

    // Get distinct categories (excluding current user's items if logged in)
    if ($current_user_id) {
        $categoryQuery = $pdo->prepare("SELECT DISTINCT category FROM listings WHERE user_id != ? ORDER BY category");
        $categoryQuery->execute([$current_user_id]);
    } else {
        $categoryQuery = $pdo->query("SELECT DISTINCT category FROM listings ORDER BY category");
    }
    $categories = $categoryQuery->fetchAll(PDO::FETCH_COLUMN);

    $result = [];

    // For each category, fetch products (excluding current user's items)
    foreach ($categories as $category) {
        if ($current_user_id) {
            $stmt = $pdo->prepare("
                SELECT id, item_name, price, image, image_type, user_id, description 
                FROM listings 
                WHERE category = ? AND user_id != ?
            ");
            $stmt->execute([$category, $current_user_id]);
        } else {
            $stmt = $pdo->prepare("
                SELECT id, item_name, price, image, image_type, user_id, description 
                FROM listings 
                WHERE category = ? 
            ");
            $stmt->execute([$category]);
        }

        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($products as &$product) {
            // Convert image BLOB to base64 if it exists
            if ($product['image']) {
                $base64Image = base64_encode($product['image']);
                $product['image_path'] = "data:" . $product['image_type'] . ";base64," . $base64Image;
            } else {
                $product['image_path'] = "../public/images/placeholder.jpg";
            }

            // Match frontend naming
            $product['name'] = $product['item_name'];
            $product['seller_id'] = $product['user_id'];

            // Clean up
            unset($product['image']);
            unset($product['image_type']);
            unset($product['item_name']);
            unset($product['user_id']);
        }

        $result[$category] = $products;
    }

    echo json_encode($result);

} catch (PDOException $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}

?>