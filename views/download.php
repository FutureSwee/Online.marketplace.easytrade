<?php
require_once '../includes/database/connection.php';

class ListingManager
{
    private $conn;

    public function __construct()
    {
        global $conn;

        if (!$conn) {
            throw new Exception("Database connection not available. Make sure connection.php is included.");
        }

        $this->conn = $conn;
    }

    public function downloadListings($filters = [], $format = 'array', $include_images = false)
    {
        try {
            $sql = "SELECT 
                        l.id as listing_id,
                        l.item_name,
                        l.category,
                        l.item_condition,
                        l.purchase_date,
                        l.price,
                        l.description,
                        l.created_at as listing_created_at,
                        u.id as user_id,
                        u.name as user_name,
                        u.surname as user_surname,
                        u.email as user_email,
                        u.role as user_role";

            if ($include_images) {
                $sql .= ", l.image, l.image_type";
            }

            $sql .= " FROM listings l 
                      LEFT JOIN Users u ON l.user_id = u.id";

            $whereConditions = [];
            $params = [];
            $types = '';

            if (!empty($filters['user_id'])) {
                $whereConditions[] = "u.id = ?";
                $params[] = $filters['user_id'];
                $types .= 'i';
            }

            if (!empty($filters['user_name'])) {
                $whereConditions[] = "u.name LIKE ?";
                $params[] = '%' . $filters['user_name'] . '%';
                $types .= 's';
            }

            if (!empty($filters['user_surname'])) {
                $whereConditions[] = "u.surname LIKE ?";
                $params[] = '%' . $filters['user_surname'] . '%';
                $types .= 's';
            }

            if (!empty($filters['category'])) {
                $whereConditions[] = "l.category = ?";
                $params[] = $filters['category'];
                $types .= 's';
            }

            if (!empty($filters['min_price'])) {
                $whereConditions[] = "l.price >= ?";
                $params[] = $filters['min_price'];
                $types .= 'd';
            }

            if (!empty($filters['max_price'])) {
                $whereConditions[] = "l.price <= ?";
                $params[] = $filters['max_price'];
                $types .= 'd';
            }

            if (!empty($whereConditions)) {
                $sql .= " WHERE " . implode(" AND ", $whereConditions);
            }

            $sql .= " ORDER BY l.created_at DESC";

            $stmt = $this->conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $this->conn->error);
            }

            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }

            $stmt->execute();
            $result = $stmt->get_result();
            $results = $result->fetch_all(MYSQLI_ASSOC);

            $stmt->close();

            // Format results
            switch ($format) {
                case 'json':
                    return json_encode($results, JSON_PRETTY_PRINT);
                case 'csv':
                    return $this->arrayToCsv($results);
                case 'download_csv':
                    $this->downloadAsCsv($results, 'listings_export.csv');
                    break;
                case 'download_json':
                    $this->downloadAsJson($results, 'listings_export.json');
                    break;
                default:
                    return $results;
            }
        } catch (Exception $e) {
            throw new Exception("Database error: " . $e->getMessage());
        }
    }

    public function getListingsByUserId($userId, $format = 'array')
    {
        return $this->downloadListings(['user_id' => $userId], $format);
    }

    public function getListingsByUserName($userName, $format = 'array')
    {
        return $this->downloadListings(['user_name' => $userName], $format);
    }

    public function getListingsByFullName($name, $surname, $format = 'array')
    {
        return $this->downloadListings([
            'user_name' => $name,
            'user_surname' => $surname
        ], $format);
    }

    private function arrayToCsv($data)
    {
        if (empty($data))
            return '';

        $output = fopen('php://temp', 'r+');
        fputcsv($output, array_keys($data[0]));

        foreach ($data as $row) {
            if (isset($row['image'])) {
                $row['image'] = base64_encode($row['image']);
            }
            fputcsv($output, $row);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }

    private function downloadAsCsv($data, $filename)
    {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: must-revalidate');

        echo $this->arrayToCsv($data);
        exit();
    }

    private function downloadAsJson($data, $filename)
    {
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: must-revalidate');

        echo json_encode($data, JSON_PRETTY_PRINT);
        exit();
    }

    public function getUserListingStats($userId = null)
    {
        $sql = "SELECT 
                    u.id,
                    u.name,
                    u.surname,
                    u.email,
                    COUNT(l.id) as total_listings,
                    AVG(l.price) as avg_price,
                    MIN(l.price) as min_price,
                    MAX(l.price) as max_price,
                    MAX(l.created_at) as last_listing_date
                FROM Users u
                LEFT JOIN listings l ON u.id = l.user_id";

        $params = [];
        $types = '';

        if ($userId) {
            $sql .= " WHERE u.id = ?";
            $params[] = $userId;
            $types .= 'i';
        }

        $sql .= " GROUP BY u.id ORDER BY total_listings DESC";

        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $this->conn->error);
        }

        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }

        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }

        $result = $stmt->get_result();
        if (!$result) {
            throw new Exception("Getting result set failed: " . $stmt->error);
        }

        $data = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return $data;
    }

    public function __destruct()
    {
        // Do not close global $conn
    }
}


// Usage Examples:
try {
    // Initialize the listing manager (now uses your connection.php)
    $listingManager = new ListingManager();

    // Example 1: Get all listings as array
    $allListings = $listingManager->downloadListings();

    // Example 2: Get listings by specific user ID
    $userListings = $listingManager->getListingsByUserId(123);

    // Example 3: Get listings by user name (partial match)
    $nameListings = $listingManager->getListingsByUserName('John');

    // Example 4: Get listings with multiple filters
    $filteredListings = $listingManager->downloadListings([
        'user_name' => 'John',
        'category' => 'Electronics',
        'min_price' => 100,
        'max_price' => 500
    ]);

    // Example 5: Export as JSON
    $jsonData = $listingManager->downloadListings(['user_id' => 123], 'json');

    // Example 6: Export as CSV string
    $csvData = $listingManager->downloadListings(['user_name' => 'Smith'], 'csv');

    // Example 7: Download as CSV file (triggers browser download)
    // $listingManager->downloadListings(['category' => 'Books'], 'download_csv');

    // Example 8: Get user statistics
    $userStats = $listingManager->getUserListingStats();

    // Example 9: Get statistics for specific user
    $specificUserStats = $listingManager->getUserListingStats(123);

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}

// Additional utility function for search (updated for MySQLi)
function searchListings($listingManager)
{
    // You would need to add a public getter method for $conn or make this a class method
    // This is just an example of how you might implement search functionality
}

// Alternative: Add this method to the ListingManager class for search functionality
// 
// public function searchListings($searchTerm) {
//     $sql = "SELECT l.*, u.name, u.surname, u.email 
//             FROM listings l 
//             LEFT JOIN Users u ON l.user_id = u.id 
//             WHERE l.item_name LIKE ? 
//                OR l.description LIKE ? 
//                OR u.name LIKE ? 
//                OR u.surname LIKE ?
//             ORDER BY l.created_at DESC";
//     
//     $searchParam = '%' . $searchTerm . '%';
//     
//     if (!$stmt = $this->conn->prepare($sql)) {
//         throw new Exception("Prepare failed: " . $this->conn->error);
//     }
//     
//     if (!$stmt->bind_param('ssss', $searchParam, $searchParam, $searchParam, $searchParam)) {
//         throw new Exception("Binding parameters failed: " . $stmt->error);
//     }
//     
//     if (!$stmt->execute()) {
//         throw new Exception("Execute failed: " . $stmt->error);
//     }
//     
//     $result = $stmt->get_result();
//     if (!$result) {
//         throw new Exception("Getting result set failed: " . $stmt->error);
//     }
//     
//     $data = $result->fetch_all(MYSQLI_ASSOC);
//     $stmt->close();
//     
//     return $data;
// }

// Simple test to verify the class works
function testListingManager()
{
    try {
        $listingManager = new ListingManager();
        echo "Database connection successful!<br>";

        // Test getting all listings
        $listings = $listingManager->downloadListings();
        echo "Found " . count($listings) . " listings.<br>";

        return $listingManager;
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "<br>";
        return null;
    }
}

?>

<!-- HTML form example for user interface -->
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Listing Download Tool</title>
    <style>
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #555;
            font-size: 0.95rem;
        }

        input[type="text"],
        input[type="number"],
        select {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e1e5e9;
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: white;
            color: #333;
        }

        input[type="text"]:focus,
        input[type="number"]:focus,
        select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            transform: translateY(-1px);
        }

        input[type="text"]::placeholder,
        input[type="number"]::placeholder {
            color: #999;
        }

        .price-range {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 0;
        }

        input[type="checkbox"] {
            width: 20px;
            height: 20px;
            accent-color: #667eea;
            cursor: pointer;
        }

        .checkbox-group label {
            margin-bottom: 0;
            cursor: pointer;
            color: #666;
            font-size: 0.95rem;
        }

        .warning-text {
            color: #e74c3c;
            font-weight: 500;
        }

        button {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 10px;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }

        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
        }

        button:active {
            transform: translateY(0);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        @media (max-width: 768px) {
            .container {
                padding: 25px;
                margin: 10px;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .price-range {
                grid-template-columns: 1fr;
            }

            h2 {
                font-size: 1.5rem;
            }
        }

        /* Subtle animations */
        .form-group {
            animation: fadeInUp 0.6s ease forwards;
            opacity: 0;
        }

        .form-group:nth-child(1) { animation-delay: 0.1s; }
        .form-group:nth-child(2) { animation-delay: 0.2s; }
        .form-group:nth-child(3) { animation-delay: 0.3s; }
        .form-group:nth-child(4) { animation-delay: 0.4s; }
        .form-group:nth-child(5) { animation-delay: 0.5s; }
        .form-group:nth-child(6) { animation-delay: 0.6s; }
        .form-group:nth-child(7) { animation-delay: 0.7s; }
        .form-group:nth-child(8) { animation-delay: 0.8s; }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .container {
            animation: scaleIn 0.5s ease forwards;
        }

        @keyframes scaleIn {
            from {
                opacity: 0;
                transform: scale(0.9);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }
    </style>
</head>

<body>
    <div class="container">
  
        <form method="POST" action="download_listings.php">
            <div class="form-row">
                <div class="form-group">
                    <label for="user_id">Filter by User ID:</label>
                    <input type="number" id="user_id" name="user_id" placeholder="Enter User ID">
                </div>

                <div class="form-group">
                    <label for="user_name">Filter by User Name:</label>
                    <input type="text" id="user_name" name="user_name" placeholder="Enter User Name">
                </div>
            </div>

            <div class="form-group">
                <label for="user_surname">Filter by User Surname:</label>
                <input type="text" id="user_surname" name="user_surname" placeholder="Enter User Surname">
            </div>

            <div class="form-group">
                <label for="category">Category:</label>
                <select id="category" name="category">
                    <option value="">All Categories</option>
                    <option value="Electronics">Electronics</option>
                    <option value="Books">Books</option>
                    <option value="Clothing">Clothing</option>
                    <option value="Home">Home</option>
                </select>
            </div>

            <div class="form-group">
                <label>Price Range:</label>
                <div class="price-range">
                    <input type="number" name="min_price" placeholder="Min Price" step="0.01">
                    <input type="number" name="max_price" placeholder="Max Price" step="0.01">
                </div>
            </div>

            <div class="form-group">
                <label for="format">Export Format:</label>
                <select id="format" name="format">
                    <option value="download_csv">Download as CSV</option>
                    <option value="download_json">Download as JSON</option>
                    <option value="json">View as JSON</option>
                </select>
            </div>

            <div class="form-group">
                <div class="checkbox-group">
                    <input type="checkbox" id="include_images" name="include_images" value="1">
                    <label for="include_images">
                        Include Images <span class="warning-text">(Warning: Large file size)</span>
                    </label>
                </div>
            </div>

            <div class="form-group">
                <button type="submit">Download Listings</button>
            </div>
        </form>
    </div>
</body>

</html>