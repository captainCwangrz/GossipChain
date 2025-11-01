<?php
	require 'config.php';
	if(!isset($_SESSION["user_id"]))
	{
        exit;
	}
	$action = $_POST["action"] ?? "";
	$user_id = $_SESSION["user_id"];
    if($action === "request")
    {
    	$to_id = (int) $_POST["to_id"] ?? 0;
    	$type = $_POST["type"] ?? "";
    	if($to_id && $type)
    	{
    		$sql = 'INSERT INTO requests (from_id, to_id, type) VALUES (?, ?, ?)';//insert into database (config)
        	$stmt = $pdo->prepare($sql); //security stuff
        	$stmt->execute([$user_id, $to_id, $type]); //execute
    	}
    }
    if($action === "accept_request")
    {
    	$id = (int) $_POST["request_id"] ?? 0;
    	$sql = 'SELECT * FROM requests WHERE id=? AND to_id=? AND status="PENDING"';
    	$stmt = $pdo->prepare($sql);
    	$stmt->execute([$id, $user_id]);
    	if($request = $stmt->fetch())
    	{
    		$sql2 = 'UPDATE requests SET status = "ACCEPTED" WHERE id=?';
    		$stmt2 = $pdo->prepare($sql2);
    		$stmt2->execute([$id]);
    		$sql3 = 'INSERT INTO relationships (from_id, to_id, type) VALUES (?,?,?)';
    		$stmt3 = $pdo->prepare($sql3);
    		$stmt3->execute([$request['from_id'], $request['to_id'], $request['type']]);
    	}
    }
    if($action === "reject_request")
    {
    	$id = (int) $_POST["request_id"] ?? 0;
    	$sql = 'UPDATE requests SET status = "REJECTED" WHERE id=? AND to_id=?';
    	$stmt = $pdo->prepare($sql);
    	$stmt->execute([$id, $user_id]);
    }

    if($action === "modify")
    {
    	$to_id = (int) $_POST["to_id"] ?? 0;
    	$type = $_POST["type"] ?? "";

    	if($to_id && $type)
    	{
    		$sql = 'UPDATE relationships SET type = ? WHERE (from_id = ? AND to_id = ?) OR (from_id = ? AND to_id = ?)';//Change database stuff
	        $stmt = $pdo->prepare($sql);
	        $stmt->execute([$type, $user_id, $to_id, $to_id, $user_id]);//have to match up with sql sequence
    	}	
    }
    if($action === "remove")
    {
        $to_id = (int) ($_POST["to_id"] ?? 0);
        if($to_id)
        {
                $sql = 'DELETE FROM relationships WHERE (from_id = ? AND to_id = ?) OR (from_id = ? AND to_id = ?)';//Change database stuff
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$user_id, $to_id, $to_id, $user_id]);//have to match up with sql sequence
        }
    }
    header('Location:dashboard.php');
?>