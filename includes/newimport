<?php
// Database configuration
$dbHost = 'mysql.entarchive.com';
$dbUser = 'media_collection';
$dbPass = 'hyqpU2-gybfuq-wowzig';
$dbName = 'media_collectingru';

// Create database connection
$conn = new mysqli($dbHost, $dbUser, $dbPass);

// Check connection
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// Create the media_collectingru database if it doesn't exist
$sql = "CREATE DATABASE IF NOT EXISTS $dbName";
if ($conn->query($sql) === TRUE) {
    echo "Database created successfully or already exists.\n";
} else {
    die("Error creating database: " . $conn->error);
}

// Select the media_collectingru database
$conn->select_db($dbName);

// Create the users table
$sql = "CREATE TABLE IF NOT EXISTS users (
    id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
if ($conn->query($sql) === TRUE) {
    echo "Users table created successfully or already exists.\n";
} else {
    die("Error creating users table: " . $conn->error);
}

// Create the user_roles table
$sql = "CREATE TABLE IF NOT EXISTS user_roles (
    id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL
)";
if ($conn->query($sql) === TRUE) {
    echo "User roles table created successfully or already exists.\n";
} else {
    die("Error creating user roles table: " . $conn->error);
}

// Create the user_role_assignments table
$sql = "CREATE TABLE IF NOT EXISTS user_role_assignments (
    user_id INT(11) UNSIGNED,
    role_id INT(11) UNSIGNED,
    PRIMARY KEY (user_id, role_id),
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (role_id) REFERENCES user_roles(id)
)";
if ($conn->query($sql) === TRUE) {
    echo "User role assignments table created successfully or already exists.\n";
} else {
    die("Error creating user role assignments table: " . $conn->error);
}

// Create the media_types table
$sql = "CREATE TABLE IF NOT EXISTS media_types (
    id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL
)";
if ($conn->query($sql) === TRUE) {
    echo "Media types table created successfully or already exists.\n";
} else {
    die("Error creating media types table: " . $conn->error);
}

// Create the genres table
$sql = "CREATE TABLE IF NOT EXISTS genres (
    id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL
)";
if ($conn->query($sql) === TRUE) {
    echo "Genres table created successfully or already exists.\n";
} else {
    die("Error creating genres table: " . $conn->error);
}

// Create the creators table
$sql = "CREATE TABLE IF NOT EXISTS creators (
    id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL
)";
if ($conn->query($sql) === TRUE) {
    echo "Creators table created successfully or already exists.\n";
} else {
    die("Error creating creators table: " . $conn->error);
}

// Create the media table
$sql = "CREATE TABLE IF NOT EXISTS media (
    id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    release_year INT(4),
    media_type_id INT(11) UNSIGNED,
    FOREIGN KEY (media_type_id) REFERENCES media_types(id)
)";
if ($conn->query($sql) === TRUE) {
    echo "Media table created successfully or already exists.\n";
} else {
    die("Error creating media table: " . $conn->error);
}

// Create the media_genres table
$sql = "CREATE TABLE IF NOT EXISTS media_genres (
    media_id INT(11) UNSIGNED,
    genre_id INT(11) UNSIGNED,
    PRIMARY KEY (media_id, genre_id),
    FOREIGN KEY (media_id) REFERENCES media(id),
    FOREIGN KEY (genre_id) REFERENCES genres(id)
)";
if ($conn->query($sql) === TRUE) {
    echo "Media genres table created successfully or already exists.\n";
} else {
    die("Error creating media genres table: " . $conn->error);
}

// Create the media_creators table
$sql = "CREATE TABLE IF NOT EXISTS media_creators (
    media_id INT(11) UNSIGNED,
    creator_id INT(11) UNSIGNED,
    PRIMARY KEY (media_id, creator_id),
    FOREIGN KEY (media_id) REFERENCES media(id),
    FOREIGN KEY (creator_id) REFERENCES creators(id)
)";
if ($conn->query($sql) === TRUE) {
    echo "Media creators table created successfully or already exists.\n";
} else {
    die("Error creating media creators table: " . $conn->error);
}

// Create the books table
$sql = "CREATE TABLE IF NOT EXISTS books (
    id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    media_id INT(11) UNSIGNED,
    author VARCHAR(100),
    isbn VARCHAR(20),
    FOREIGN KEY (media_id) REFERENCES media(id)
)";
if ($conn->query($sql) === TRUE) {
    echo "Books table created successfully or already exists.\n";
} else {
    die("Error creating books table: " . $conn->error);
}

// Create the comics table
$sql = "CREATE TABLE IF NOT EXISTS comics (
    id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    media_id INT(11) UNSIGNED,
    issue_number INT(11),
    publisher VARCHAR(100),
    FOREIGN KEY (media_id) REFERENCES media(id)
)";
if ($conn->query($sql) === TRUE) {
    echo "Comics table created successfully or already exists.\n";
} else {
    die("Error creating comics table: " . $conn->error);
}

// Create the vhs table
$sql = "CREATE TABLE IF NOT EXISTS vhs (
    id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    media_id INT(11) UNSIGNED,
    runtime INT(11),
    FOREIGN KEY (media_id) REFERENCES media(id)
)";
if ($conn->query($sql) === TRUE) {
    echo "VHS table created successfully or already exists.\n";
} else {
    die("Error creating VHS table: " . $conn->error);
}

// Create the discs table
$sql = "CREATE TABLE IF NOT EXISTS discs (
    id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    media_id INT(11) UNSIGNED,
    disc_type VARCHAR(20),
    runtime INT(11),
    FOREIGN KEY (media_id) REFERENCES media(id)
)";
if ($conn->query($sql) === TRUE) {
    echo "Discs table created successfully or already exists.\n";
} else {
    die("Error creating discs table: " . $conn->error);
}

// Close the database connection
$conn->close();
echo "Schema creation completed successfully!";
?>