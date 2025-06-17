<?php



// Change from:
$conn = new mysqli('localhost', 'root', 'password', 'marketplace_db');

// To (for Render):
$conn = new mysqli(
    getenv('https://supportindeed.com/phpMyAdmin/?server=1&xck=523290911'),    // e.g., 'dpg-xxxxxx-a.oregon-postgres.render.com'
    getenv('4593147_marketplace'),
    getenv('aY77%Lql2MLZtru^'),
    getenv('4593147_marketplace')
);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}



?>

