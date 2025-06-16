CREATE TABLE Users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    surname VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    remember_token VARCHAR(100) NULL,
    role ENUM('buyer', 'seller', 'admin' ,'customer') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);


CREATE TABLE listings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    item_name VARCHAR(255) NOT NULL,
    category VARCHAR(100) NOT NULL,
    item_condition VARCHAR(100) NOT NULL,
    purchase_date DATE DEFAULT NULL,
    price DECIMAL(10, 2) NOT NULL,
    description TEXT NOT NULL,
    image LONGBLOB, -- store binary image here
    image_type VARCHAR(50), -- e.g. image/jpeg
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create messages table to store chat messages
CREATE TABLE chat_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    listing_id INT NOT NULL,
    message TEXT NOT NULL,
    read_status BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE
);

-- Create chat sessions table to track conversations
CREATE TABLE chat_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    listing_id INT NOT NULL,
    buyer_id INT NOT NULL,
    seller_id INT NOT NULL, 
    last_message_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE,
    UNIQUE KEY unique_chat (listing_id, buyer_id, seller_id)
);
CREATE TABLE `ratings` (
  `id` int(11) NOT NULL,
  `reviewer_id` int(11) NOT NULL,
  `seller_id` int(11) NOT NULL,
  `listing_id` int(11) NOT NULL,
  `transaction_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `rating_score` int(11) NOT NULL CHECK (`rating_score` between 1 and 5),
  `review_text` text DEFAULT NULL,
  `item_accuracy` tinyint(1) NOT NULL,
  `communication_quality` tinyint(1) NOT NULL,
  `shipping_speed` tinyint(1) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
-- The `Users` table stores user information, including their role (buyer, seller, admin, customer).
-- The `listings` table stores information about items listed for sale, including the user who listed it.

