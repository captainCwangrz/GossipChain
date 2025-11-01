<?php
	session_start();
	
	$host = 'localhost';
	$root_user = 'root';
	$root_password = 'root';
	$db = 'social_demo';
	$options = [
		PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, 
		PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
	];
	$avatars = ['1.png', '2.png', '3.png'];
	$fallback_avatar = '0.png';

	try {
		// Create connection without specifying database
		$pdo = new PDO("mysql:host=$host;charset=utf8mb4", $root_user, $root_password, $options);
		
		// Create database if not exists
		$pdo->exec("CREATE DATABASE IF NOT EXISTS `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
		
		// Now connect to the specific database
		$pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $root_user, $root_password, $options);
		
		// Create tables
		$pdo->exec("
			CREATE TABLE IF NOT EXISTS users (
				id INT AUTO_INCREMENT PRIMARY KEY, 
				username VARCHAR(50) UNIQUE NOT NULL,
				password_hash VARCHAR(255) NOT NULL,
				x_pos DOUBLE NOT NULL,
				y_pos DOUBLE NOT NULL,
				avatar VARCHAR(50) NOT NULL,
				UNIQUE KEY coord (x_pos, y_pos)
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
				status ENUM('ACCEPTED', 'PENDING', 'REJECTED') DEFAULT 'PENDING',
				FOREIGN KEY (from_id) REFERENCES users(id) ON DELETE CASCADE,
				FOREIGN KEY (to_id) REFERENCES users(id) ON DELETE CASCADE
			) ENGINE=InnoDB;

			CREATE TABLE IF NOT EXISTS messages (
				id INT AUTO_INCREMENT PRIMARY KEY, 
				from_id INT NOT NULL,
				to_id INT NOT NULL,
				message TEXT NOT NULL,
				timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
				FOREIGN KEY (from_id) REFERENCES users(id) ON DELETE CASCADE,
				FOREIGN KEY (to_id) REFERENCES users(id) ON DELETE CASCADE
			) ENGINE=InnoDB;
		");
	} catch (PDOException $e) {
		die("Database error: " . $e->getMessage());
	}
?>