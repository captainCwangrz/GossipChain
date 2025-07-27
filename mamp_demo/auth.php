<?php
    require 'config.php';
    $action = $_POST["action"] ?? "";
    $username = trim($_POST["username"]) ?? "";
    $password = $_POST["password"] ?? "";
    if($action === "login")
    {
        $query = $pdo->prepare('SELECT id, password_hash FROM users WHERE username=?');
        $query -> execute([$username]);
        $user = $query->fetch();
        if($user && password_verify($password, $user["password_hash"]))
        {
            // Store user information in the session
            $_SESSION["user_id"] = $user["id"];
            $_SESSION["username"] = $username;
            header("Location: dashboard.php");exit;
        }
        header("Location: index.php?error=1");exit;
    }
    if($action === "register")
    {
        if(!$username || !$password)
        {
            exit("Missing field!");
        }
        
        
        $password_hash = password_hash($password, PASSWORD_DEFAULT);

        // Prepare SQL statement
        $sql = 'INSERT INTO users (username, password_hash) VALUES (?, ?)';
        $stmt = $pdo->prepare($sql);

        try 
        {
            // Execute query
            $stmt->execute([$username, $password_hash]);
        } 
        catch (PDOException $e) 
        {
            if($e -> getCode() === "23000")
            {
                exit("Username exists");
            }
            throw $e;
        }
        header("Location: index.php?registered=1");exit;

    }










