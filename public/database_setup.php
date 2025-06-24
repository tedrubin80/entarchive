<?php
// Database configuration
$dbHost = 'sql19158.dreamhostps.com';
$dbUser = 'collectmevhs23';
$dbPass = 'fehpir-wapxy0-sevqUp';
$dbName = 'media_collection';


// Create database connection
$conn = new mysqli($dbHost, $dbUser, $dbPass);

// Check connection
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// Create database
$sql = "CREATE DATABASE IF NOT EXISTS $dbName";
if ($conn->query($sql) === TRUE) {
    echo "Database created successfully or already exists.\n";
} else {
    die("Error creating database: " . $conn->error);
}

// Select the database
$conn->select_db($dbName);

// Create media table
$mediaTableSql = "CREATE TABLE IF NOT EXISTS media (
    id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    file_path VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if ($conn->query($mediaTableSql) === TRUE) {
    echo "Media table created successfully or already exists.\n";
} else {
    die("Error creating media table: " . $conn->error);
}

// Create users table
$usersTableSql = "CREATE TABLE IF NOT EXISTS users (
    id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if ($conn->query($usersTableSql) === TRUE) {
    echo "Users table created successfully or already exists.\n";
} else {
    die("Error creating users table: " . $conn->error);
}

// Close the database connection
$conn->close();
echo "Database setup completed successfully!";
?>