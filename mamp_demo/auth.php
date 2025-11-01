<?php
    require 'config.php';
    $action = $_POST["action"] ?? "";
    $username = trim($_POST["username"]) ?? "";
    $password = $_POST["password"] ?? "";
    $avatar = $_POST["avatar"] ?? null;
    if($action === "login")
    {
        $query = $pdo->prepare('SELECT id, password_hash FROM users WHERE username=?');
        $query -> execute([$username]);
        $user = $query->fetch();
        if($user && password_verify($password, $user["password_hash"]))
        {
            //Store locally session
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
        if(!in_array($avatar, $avatars))
        {
            $avatar = $fallback_avatar;
        }
        // Prepare SQL statement
        $count = (int) $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
        //range: density or spacing of graph
        $range = max(1, sqrt($count+1))*300;
        $sql = 'INSERT INTO users (username, password_hash, x_pos, y_pos, avatar, signature) VALUES (?, ?, ?, ?, ?, NULL)';
        $stmt = $pdo->prepare($sql);

        do
        {
            $x = (mt_rand()/mt_getrandmax()*2-1)*$range;
            $y = (mt_rand()/mt_getrandmax()*2-1)*$range;
            try
            {
                $stmt->execute([$username, $password_hash, $x, $y, $avatar]);
                $success = true;
            }
            catch(PDOException $e)
            {
                if($e->getCode()==="23000")
                {
                    if(strpos($e->getMessage(), "username") !== false)
                    {
                        exit("Username exists");
                    }
                    $success = false;
                }
                else
                {
                    throw $e;
                }
            }
        }while(!$success);
        header("Location: index.php?registered=1");exit;

    }










