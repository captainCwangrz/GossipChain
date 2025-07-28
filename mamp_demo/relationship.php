<?php
/**
 * Handles modifications to relationship data via POST requests.
 */

require 'config.php';

// Ensure the user is authenticated
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit('Unauthorized');
}

$action  = $_POST['action'] ?? '';
$user_id = $_SESSION['user_id'];

switch($action){
    case 'send_request':
        // Create a new relationship request
        $to_id = (int)($_POST['to_id'] ?? 0);
        $type  = $_POST['type'] ?? '';
        if ($to_id && $type) {
            $stmt = $pdo->prepare('INSERT INTO requests (from_id,to_id,type) VALUES (?,?,?)');
            $stmt->execute([$user_id, $to_id, $type]);
        }
        break;
    case 'modify_relationship':
        // Update an existing relationship
        $to_id = (int)($_POST['to_id'] ?? 0);
        $type  = $_POST['type'] ?? '';
        if ($to_id && $type) {
            $stmt = $pdo->prepare('UPDATE relationships SET type=? WHERE (from_id=? AND to_id=?) OR (from_id=? AND to_id=?)');
            $stmt->execute([$type, $user_id, $to_id, $to_id, $user_id]);
        }
        break;
    case 'remove_relationship':
        // Delete a relationship
        $to_id = (int)($_POST['to_id'] ?? 0);
        if ($to_id) {
            $stmt = $pdo->prepare('DELETE FROM relationships WHERE (from_id=? AND to_id=?) OR (from_id=? AND to_id=?)');
            $stmt->execute([$user_id, $to_id, $to_id, $user_id]);
        }
        break;
    case 'accept_request':
        // Accept an incoming relationship request
        $id   = (int)($_POST['request_id'] ?? 0);
        $stmt = $pdo->prepare('SELECT * FROM requests WHERE id=? AND to_id=? AND status="PENDING"');
        $stmt->execute([$id, $user_id]);
        if ($req = $stmt->fetch()) {
            $pdo->prepare('UPDATE requests SET status="ACCEPTED" WHERE id=?')->execute([$id]);
            $pdo->prepare('INSERT INTO relationships (from_id,to_id,type) VALUES (?,?,?)')->execute([$req['from_id'], $req['to_id'], $req['type']]);
        }
        break;
    case 'reject_request':
        // Reject a request
        $id = (int)($_POST['request_id'] ?? 0);
        $pdo->prepare('UPDATE requests SET status="REJECTED" WHERE id=? AND to_id=?')->execute([$id, $user_id]);
        break;
    default:
        exit('Unknown action');
}
header('Location: dashboard.php');

