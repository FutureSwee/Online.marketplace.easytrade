<?php
$mysqli = new mysqli(
    'localhost',      // host
    'root',           // username
    'your_password',  // password (replace with actual password)
    'marketplace_db', // database name
    3306,            // port (default is 3306)
    '/var/run/mysqld/mysqld.sock' // socket path if needed
);

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

