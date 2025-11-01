<?php
    require "config.php";
    if(isset($_SESSION["user_id"]))
    {
        header("Location: dashboard.php");exit;
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Social-Demo</title>
    <style>
        body
        {
            font-family: system-ui, sans-serif;
            background-color: lightgreen;
        }
        .box
        {
            max-width: 400px;
            margin: 6rem auto;
            padding: 1.5rem 2rem;
            border-radius: 5px;
            box-shadow: 0 2px 8px #000200;
        }
        label
        {
            display: block;
            margin: 0.75rem 0;
            margin-bottom: 0.5rem;
        }
        input
        {
            width: 100%;
            padding: 0.5rem;
        }
        button
        {
            border-radius: 5px;
            cursor: pointer;
            background-color: lightyellow;
            width: 100%;
            padding: 0.5rem;
            margin-left: 0.5rem;
        }
        hr
        {
            margin: 2rem 0;
            border: none;
            border-top: 1px solid black;   
        }
    </style>
</head>
<body>
    <div class="box">
        <h2>Sign In</h2>
        <?php if(isset($_GET['error'])) echo '<p class="error">invalid credentials</p>';?>
        <?php if(isset($_GET['registered'])) echo '<p class="registered">account created</p>';?>
        
        <form method="post" action="auth.php">
            <input type="hidden" name="action" value="login">
            <label>Username: <input type="text" name="username" placeholder="Name" required></label>
            <label>Password: <input type="password" name="password" placeholder="Password1234!" required></label><br>
            <button type="submit">Login</button>
        </form>
        <hr>
        <h3>Register</h3>
        <form method="post" action="auth.php">
            <input type="hidden" name="action" value="register">
            <label>Username: <input type="text" name="username" placeholder="Name" required></label>
            <label>Password: <input type="password" name="password" placeholder="Password1234!" required></label><br>
            <div>
                <span>Select Your Avatar: </span><br>
                <?php foreach($avatars as $pic): ?>
                    <label>
                        <input type="radio" name="avatar" value="<?php echo htmlspecialchars($pic); ?>">
                        <img src="assets/<?php echo htmlspecialchars($pic); ?>" width="40">
                    </label>
                <?php endforeach; ?>
            </div>
            <button type="submit">Register</button>
        </form>
    </div>
</body>
</html>