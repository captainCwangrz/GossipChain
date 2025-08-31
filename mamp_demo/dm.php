<?php
/**
 * Simple direct messaging endpoint.
 * Actions determined by the `action` parameter:
 *   - fetch: full conversation with a specific user
 *   - latest_id: highest message ID received so far
 *   - latest: messages newer than a given ID
 *   - send: store a new outgoing message
 */
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$action  = $_REQUEST['action'] ?? '';

// Verify that two users have an established relationship before messaging
function hasRelationship(PDO $pdo, int $a, int $b): bool {
    $stmt = $pdo->prepare('SELECT 1 FROM relationships WHERE (from_id=? AND to_id=?) OR (from_id=? AND to_id=?)');
    $stmt->execute([$a,$b,$b,$a]);
    return (bool)$stmt->fetchColumn();
}

// Return the full message history with a particular user
if ($action === 'fetch') {
    $other_id = (int)($_GET['user_id'] ?? 0);
    if (!$other_id || !hasRelationship($pdo, $user_id, $other_id)) {
        http_response_code(403);
        exit;
    }
    $stmt = $pdo->prepare('SELECT id, sender_id, message, DATE_FORMAT(created_at, "%Y-%m-%d %H:%i:%s") AS created_at FROM messages WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?) ORDER BY id ASC');

    $stmt->execute([$user_id,$other_id,$other_id,$user_id]);
    header('Content-Type: application/json');
    echo json_encode($stmt->fetchAll());
    exit;
}

// Return the highest message ID the user has received (used on login)
if ($action === 'latest_id') {
    $stmt = $pdo->prepare('SELECT MAX(id) FROM messages WHERE receiver_id=?');
    $stmt->execute([$user_id]);
    header('Content-Type: application/json');
    echo json_encode(['latest' => (int)$stmt->fetchColumn()]);
    exit;
}

// Fetch all messages newer than the provided ID
if ($action === 'latest') {
    $since = (int)($_GET['since'] ?? 0);
    $stmt = $pdo->prepare('SELECT id, sender_id, message FROM messages WHERE receiver_id = ? AND id > ? ORDER BY id ASC');
    $stmt->execute([$user_id, $since]);
    header('Content-Type: application/json');
    echo json_encode($stmt->fetchAll());
    exit;
}

// Store a new message addressed to another user
if ($action === 'send') {
    $other_id = (int)($_POST['user_id'] ?? 0);
    $msg = trim($_POST['message'] ?? '');
    if (!$other_id || $msg === '' || !hasRelationship($pdo, $user_id, $other_id)) {
        http_response_code(400);
        exit;
    }
    $stmt = $pdo->prepare('INSERT INTO messages (sender_id, receiver_id, message) VALUES (?,?,?)');
    $stmt->execute([$user_id,$other_id,$msg]);
    echo 'OK';
    exit;
}

http_response_code(400);
?>
