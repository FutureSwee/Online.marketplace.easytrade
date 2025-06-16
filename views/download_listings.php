<?php
require_once '../includes/database/connection.php';

class ListingManager {
    private $conn;
    
    public function __construct() {
        global $conn;
        
        if (!$conn) {
            throw new Exception("Database connection not available. Make sure connection.php is included.");
        }
        
        $this->conn = $conn;
    }
    
    /**
     * Download/retrieve listings with optional user filtering
     * 
     * @param array $filters - Array of filters: ['user_id' => int, 'user_name' => string, 'user_surname' => string]
     * @param string $format - Output format: 'array', 'json', 'csv'
     * @param bool $include_images - Whether to include image data (can be large)
     * @return mixed - Returns data in specified format
     */
    public function downloadListings($filters = [], $format = 'array', $include_images = false) {
        try {
            // Base query
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
            
            // Add image fields if requested
            if ($include_images) {
                $sql .= ", l.image, l.image_type";
            }
            
            $sql .= " FROM listings l 
                      LEFT JOIN Users u ON l.user_id = u.id";
            
            // Build WHERE clause based on filters
            $whereConditions = [];
            $params = [];
            $types = "";
            
            if (!empty($filters['user_id'])) {
                $whereConditions[] = "u.id = ?";
                $params[] = $filters['user_id'];
                $types .= "i";
            }
            
            if (!empty($filters['user_name'])) {
                $whereConditions[] = "u.name LIKE ?";
                $params[] = '%' . $filters['user_name'] . '%';
                $types .= "s";
            }
            
            if (!empty($filters['user_surname'])) {
                $whereConditions[] = "u.surname LIKE ?";
                $params[] = '%' . $filters['user_surname'] . '%';
                $types .= "s";
            }
            
            if (!empty($filters['category'])) {
                $whereConditions[] = "l.category = ?";
                $params[] = $filters['category'];
                $types .= "s";
            }
            
            if (!empty($filters['min_price'])) {
                $whereConditions[] = "l.price >= ?";
                $params[] = $filters['min_price'];
                $types .= "d";
            }
            
            if (!empty($filters['max_price'])) {
                $whereConditions[] = "l.price <= ?";
                $params[] = $filters['max_price'];
                $types .= "d";
            }
            
            // Add WHERE clause if there are conditions
            if (!empty($whereConditions)) {
                $sql .= " WHERE " . implode(" AND ", $whereConditions);
            }
            
            // Add ordering
            $sql .= " ORDER BY l.created_at DESC";
            
            // Prepare and execute query
            if (!$stmt = $this->conn->prepare($sql)) {
                throw new Exception("Prepare failed: " . $this->conn->error);
            }
            
            if (!empty($params)) {
                if (!$stmt->bind_param($types, ...$params)) {
                    throw new Exception("Binding parameters failed: " . $stmt->error);
                }
            }
            
            if (!$stmt->execute()) {
                throw new Exception("Execute failed: " . $stmt->error);
            }
            
            $result = $stmt->get_result();
            if (!$result) {
                throw new Exception("Getting result set failed: " . $stmt->error);
            }
            
            $results = $result->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            
            // Return data in requested format
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
            
        } catch (mysqli_sql_exception $e) {
            throw new Exception("Database error: " . $e->getMessage());
        }
    }
    
    /**
     * Get listings by specific user ID
     */
    public function getListingsByUserId($userId, $format = 'array') {
        return $this->downloadListings(['user_id' => $userId], $format);
    }
    
    /**
     * Get listings by user name (partial match)
     */
    public function getListingsByUserName($userName, $format = 'array') {
        return $this->downloadListings(['user_name' => $userName], $format);
    }
    
    /**
     * Get listings by user full name
     */
    public function getListingsByFullName($name, $surname, $format = 'array') {
        return $this->downloadListings([
            'user_name' => $name, 
            'user_surname' => $surname
        ], $format);
    }
    
    /**
     * Convert array to CSV format
     */
    private function arrayToCsv($data) {
        if (empty($data)) {
            return '';
        }
        
        $output = fopen('php://temp', 'r+');
        
        // Add header row
        fputcsv($output, array_keys($data[0]));
        
        // Add data rows
        foreach ($data as $row) {
            // Handle binary image data
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
    
    /**
     * Download data as CSV file
     */
    private function downloadAsCsv($data, $filename) {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: must-revalidate');
        
        echo $this->arrayToCsv($data);
        exit();
    }
    
    /**
     * Download data as JSON file
     */
    private function downloadAsJson($data, $filename) {
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: must-revalidate');
        
        echo json_encode($data, JSON_PRETTY_PRINT);
        exit();
    }
    
    /**
     * Get user statistics
     */
    public function getUserListingStats($userId = null) {
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
        $types = "";
        
        if ($userId) {
            $sql .= " WHERE u.id = ?";
            $params[] = $userId;
            $types = "i";
        }
        
        $sql .= " GROUP BY u.id ORDER BY total_listings DESC";
        
        if (!$stmt = $this->conn->prepare($sql)) {
            throw new Exception("Prepare failed: " . $this->conn->error);
        }
        
        if (!empty($params)) {
            if (!$stmt->bind_param($types, ...$params)) {
                throw new Exception("Binding parameters failed: " . $stmt->error);
            }
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
    
    /**
     * Get all users for dropdown
     */
    public function getAllUsers() {
        $sql = "SELECT id, name, surname, email FROM Users ORDER BY name, surname";
        
        if (!$stmt = $this->conn->prepare($sql)) {
            throw new Exception("Prepare failed: " . $this->conn->error);
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
    
    /**
     * Get all categories for dropdown
     */
    public function getAllCategories() {
        $sql = "SELECT DISTINCT category FROM listings WHERE category IS NOT NULL ORDER BY category";
        
        if (!$stmt = $this->conn->prepare($sql)) {
            throw new Exception("Prepare failed: " . $this->conn->error);
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
}

// Handle form submission
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $listingManager = new ListingManager();
        
        // Build filters array
        $filters = [];
        
        if (!empty($_POST['user_id'])) {
            $filters['user_id'] = intval($_POST['user_id']);
        }
        
        if (!empty($_POST['user_name'])) {
            $filters['user_name'] = trim($_POST['user_name']);
        }
        
        if (!empty($_POST['user_surname'])) {
            $filters['user_surname'] = trim($_POST['user_surname']);
        }
        
        if (!empty($_POST['category'])) {
            $filters['category'] = trim($_POST['category']);
        }
        
        if (!empty($_POST['min_price'])) {
            $filters['min_price'] = floatval($_POST['min_price']);
        }
        
        if (!empty($_POST['max_price'])) {
            $filters['max_price'] = floatval($_POST['max_price']);
        }
        
        $format = $_POST['format'] ?? 'json';
        $include_images = isset($_POST['include_images']) && $_POST['include_images'] == '1';
        
        // Process the request
        $data = $listingManager->downloadListings($filters, $format, $include_images);
        
        if ($format === 'json' || $format === 'csv') {
            // Display the data
            $message = "Data exported successfully!";
        }
        
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Get data for dropdowns
try {
    $listingManager = new ListingManager();
    $users = $listingManager->getAllUsers();
    $categories = $listingManager->getAllCategories();
} catch (Exception $e) {
    $error = "Error loading form data: " . $e->getMessage();
    $users = [];
    $categories = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Download Listings</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        h1 {
            color: #333;
            text-align: center;
            margin-bottom: 30px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #555;
        }
        
        input, select, textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            box-sizing: border-box;
        }
        
        .form-row {
            display: flex;
            gap: 15px;
        }
        
        .form-row .form-group {
            flex: 1;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .checkbox-group input[type="checkbox"] {
            width: auto;
        }
        
        button {
            background-color: #007bff;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            width: 100%;
        }
        
        button:hover {
            background-color: #0056b3;
        }
        
        .message {
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        
        .success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .data-display {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            padding: 15px;
            margin-top: 20px;
            max-height: 400px;
            overflow-y: auto;
        }
        
        pre {
            white-space: pre-wrap;
            word-wrap: break-word;
            margin: 0;
            font-size: 12px;
        }
        
        .stats-section {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #dee2e6;
        }
        
        .quick-actions {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .quick-btn {
            padding: 8px 15px;
            background-color: #6c757d;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .quick-btn:hover {
            background-color: #545b62;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>📊 Download Listings</h1>
        
        <?php if ($error): ?>
            <div class="message error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($message): ?>
            <div class="message success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        
        <!-- Quick Actions -->
        <div class="quick-actions">
            <form method="POST" style="display: inline;">
                <input type="hidden" name="format" value="download_csv">
                <button type="submit" class="quick-btn">📥 Download All as CSV</button>
            </form>
            
            <form method="POST" style="display: inline;">
                <input type="hidden" name="format" value="download_json">
                <button type="submit" class="quick-btn">📥 Download All as JSON</button>
            </form>
        </div>
        
        <!-- Main Form -->
        <form method="POST">
            <div class="form-row">
                <div class="form-group">
                    <label for="user_id">Filter by User:</label>
                    <select name="user_id" id="user_id">
                        <option value="">All Users</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?php echo $user['id']; ?>" 
                                <?php echo (isset($_POST['user_id']) && $_POST['user_id'] == $user['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($user['name'] . ' ' . $user['surname'] . ' (' . $user['email'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="user_name">Or Filter by Name:</label>
                    <input type="text" name="user_name" id="user_name" 
                           placeholder="Enter user name" 
                           value="<?php echo htmlspecialchars($_POST['user_name'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="user_surname">Filter by Surname:</label>
                    <input type="text" name="user_surname" id="user_surname" 
                           placeholder="Enter user surname"
                           value="<?php echo htmlspecialchars($_POST['user_surname'] ?? ''); ?>">
                </div>
            </div>
            
            <div class="form-group">
                <label for="category">Category:</label>
                <select name="category" id="category">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo htmlspecialchars($cat['category']); ?>"
                            <?php echo (isset($_POST['category']) && $_POST['category'] == $cat['category']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat['category']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="min_price">Minimum Price:</label>
                    <input type="number" name="min_price" id="min_price" 
                           placeholder="0.00" step="0.01" min="0"
                           value="<?php echo htmlspecialchars($_POST['min_price'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="max_price">Maximum Price:</label>
                    <input type="number" name="max_price" id="max_price" 
                           placeholder="999999.99" step="0.01" min="0"
                           value="<?php echo htmlspecialchars($_POST['max_price'] ?? ''); ?>">
                </div>
            </div>
            
            <div class="form-group">
                <label for="format">Export Format:</label>
                <select name="format" id="format">
                    <option value="json" <?php echo (isset($_POST['format']) && $_POST['format'] == 'json') ? 'selected' : ''; ?>>View as JSON</option>
                    <option value="csv" <?php echo (isset($_POST['format']) && $_POST['format'] == 'csv') ? 'selected' : ''; ?>>View as CSV</option>
                    <option value="download_csv" <?php echo (isset($_POST['format']) && $_POST['format'] == 'download_csv') ? 'selected' : ''; ?>>Download as CSV</option>
                    <option value="download_json" <?php echo (isset($_POST['format']) && $_POST['format'] == 'download_json') ? 'selected' : ''; ?>>Download as JSON</option>
                </select>
            </div>
            
            <div class="form-group">
                <div class="checkbox-group">
                    <input type="checkbox" name="include_images" id="include_images" value="1"
                           <?php echo (isset($_POST['include_images']) && $_POST['include_images'] == '1') ? 'checked' : ''; ?>>
                    <label for="include_images">Include Images (Warning: Large file size)</label>
                </div>
            </div>
            
            <button type="submit">🔍 Filter & Export Listings</button>
        </form>
        
        <?php if (isset($data) && ($format === 'json' || $format === 'csv')): ?>
            <div class="data-display">
                <h3>Exported Data:</h3>
                <pre><?php echo htmlspecialchars($data); ?></pre>
            </div>
        <?php endif; ?>
        
        <!-- Statistics Section -->
        <div class="stats-section">
            <h2>📈 Quick Statistics</h2>
            <div class="quick-actions">
                <a href="?action=user_stats" class="quick-btn">👥 User Statistics</a>
                <a href="?action=category_stats" class="quick-btn">📊 Category Statistics</a>
            </div>
            
            <?php
            if (isset($_GET['action']) && $_GET['action'] === 'user_stats') {
                try {
                    $stats = $listingManager->getUserListingStats();
                    echo "<div class='data-display'>";
                    echo "<h4>User Statistics:</h4>";
                    echo "<pre>" . htmlspecialchars(json_encode($stats, JSON_PRETTY_PRINT)) . "</pre>";
                    echo "</div>";
                } catch (Exception $e) {
                    echo "<div class='message error'>Error loading statistics: " . htmlspecialchars($e->getMessage()) . "</div>";
                }
            }
            ?>
        </div>
    </div>

    <script>
        // Auto-clear user dropdown when typing in name fields
        document.getElementById('user_name').addEventListener('input', function() {
            if (this.value) {
                document.getElementById('user_id').value = '';
            }
        });
        
        document.getElementById('user_surname').addEventListener('input', function() {
            if (this.value) {
                document.getElementById('user_id').value = '';
            }
        });
        
        document.getElementById('user_id').addEventListener('change', function() {
            if (this.value) {
                document.getElementById('user_name').value = '';
                document.getElementById('user_surname').value = '';
            }
        });
    </script>
</body>
</html>