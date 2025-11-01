<?php
	require "config.php";
	if(!isset($_SESSION["user_id"]))
	{
		header("Location: index.php");
        exit;
	}
	$user_id = $_SESSION["user_id"];
	$action = $_REQUEST["action"];

	function checkRelationship(int $from_id, int $to_id, PDO $pdo): bool
	{
		$sql = 'SELECT * FROM relationships WHERE (from_id=? AND to_id=?) OR (from_id=? AND to_id=?)';
		$stmt = $pdo->prepare($sql);
	    $stmt->execute([$from_id, $to_id, $to_id, $from_id]);
	    return (bool) $stmt->fetchColumn();
	}

	if($action === "send")
	{
		$to_id = (int) $_POST["to_id"] ?? 0;
		$message = trim($_POST["message"]) ?? "";
		if(checkRelationship($user_id, $to_id, $pdo) && $to_id && $message != "")
		{
			$sql = 'INSERT INTO messages (from_id, to_id, message) VALUES (?, ?, ?)';
			$stmt = $pdo->prepare($sql);
        	$stmt->execute([$user_id, $to_id, $message]);
        	echo "OK";
        	exit;
		}
		else
		{
			http_response_code(400);
			exit;
		}
	}

	if($action === "retrieve")
	{
		$to_id = (int)$_GET["to_id"] ?? 0;
		if(checkRelationship($user_id, $to_id, $pdo) && $to_id)
		{
			$sql = 'SELECT id, from_id, message, DATE_FORMAT(timestamp, "%Y-%m-%d %H:%i:%s") AS created_at FROM messages WHERE (from_id=? AND to_id=?) OR (from_id=? AND to_id=?) ORDER BY id ASC';
			$stmt = $pdo->prepare($sql);
        	$stmt->execute([$user_id, $to_id, $to_id, $user_id]);
        	header('Content-Type: application/json');
        	echo json_encode($stmt->fetchAll());
        	exit;
		}
		else
		{
			http_response_code(403);
			exit;
		}
	}

	if ($action === 'latest_id') 
	{
	    $stmt = $pdo->prepare('SELECT MAX(id) FROM messages WHERE to_id=?');
	    $stmt->execute([$user_id]);
	    header('Content-Type: application/json');
	    echo json_encode(['latest' => (int)$stmt->fetchColumn()]);
	    exit;
	}

	if($action === "latest")
	{
		$since = (int) $_GET["since"] ?? 0;
		$sql = 'SELECT id, from_id, message FROM messages WHERE to_id=? AND id > ? ORDER BY id ASC';
		$stmt = $pdo->prepare($sql);
		$stmt->execute([$user_id, $since]);
		header('Content-Type: application/json');
		echo json_encode($stmt->fetchAll());
		exit;
	}
?>