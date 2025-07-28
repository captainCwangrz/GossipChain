<?php
/**
 * Application configuration and database initialization.
 */

session_start();

$host          = 'localhost';
$root_user     = 'root';
$root_password = 'root';
$db            = 'social_demo';

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    // Connect without specifying database to create it if necessary
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $root_user, $root_password, $options);

    // Create database if it doesn't exist
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

    // Connect to the newly ensured database
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $root_user, $root_password, $options);

    // Create required tables
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) UNIQUE NOT NULL,
            password_hash VARCHAR(255) NOT NULL
        ) ENGINE=InnoDB;

        CREATE TABLE IF NOT EXISTS relationships (
            id INT AUTO_INCREMENT PRIMARY KEY,
            from_id INT NOT NULL,
            to_id INT NOT NULL,
            type ENUM('DATING', 'BEST_FRIEND', 'BROTHER', 'SISTER', 'BEEFING', 'CRUSH') NOT NULL,
            FOREIGN KEY (from_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (to_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB;

        CREATE TABLE IF NOT EXISTS requests (
            id INT AUTO_INCREMENT PRIMARY KEY,
            from_id INT NOT NULL,
            to_id INT NOT NULL,
            type ENUM('DATING', 'BEST_FRIEND', 'BROTHER', 'SISTER', 'BEEFING', 'CRUSH') NOT NULL,
            status ENUM('PENDING','ACCEPTED','REJECTED') DEFAULT 'PENDING',
            FOREIGN KEY (from_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (to_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB;"
    );
} catch (PDOException $e) {
    die('Database error: ' . $e->getMessage());
}
