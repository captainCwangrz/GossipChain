<?php
/**
 * Handles user authentication and registration logic.
 */

require 'config.php';

// Determine requested action
$action   = $_POST['action']   ?? '';
$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if ($action === 'login') {
    // Attempt to log the user in
    $query = $pdo->prepare('SELECT id, password_hash FROM users WHERE username = ?');
    $query->execute([$username]);
    $user = $query->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        // Store user information in the session
        $_SESSION['user_id']  = $user['id'];
        $_SESSION['username'] = $username;
        header('Location: dashboard.php');
        exit;
    }

    // Invalid credentials
    header('Location: index.php?error=1');
    exit;
}

if ($action === 'register') {
    // Validate input fields
    if (!$username || !$password) {
        exit('Missing field!');
    }

    $password_hash = password_hash($password, PASSWORD_DEFAULT);

    // Create new user record
    $sql  = 'INSERT INTO users (username, password_hash) VALUES (?, ?)';
    $stmt = $pdo->prepare($sql);

    try {
        $stmt->execute([$username, $password_hash]);
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            exit('Username exists');
        }
        throw $e;
    }

    header('Location: index.php?registered=1');
    exit;
}

