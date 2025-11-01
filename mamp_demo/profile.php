<?php
require 'config.php';

header('Content-Type: application/json; charset=utf-8');

if($_SERVER['REQUEST_METHOD'] !== 'POST')
{
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed.'
    ]);
    exit;
}

if(!isset($_SESSION["user_id"]))
{
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Authentication required.'
    ]);
    exit;
}

$raw_signature = $_POST['signature'] ?? '';
$signature = trim($raw_signature);

if(function_exists('mb_substr'))
{
    $signature = mb_substr($signature, 0, 160);
}
else
{
    $signature = substr($signature, 0, 160);
}

$normalized = $signature === '' ? null : $signature;

try
{
    $stmt = $pdo->prepare('UPDATE users SET signature = :signature WHERE id = :id');
    $stmt->execute([
        ':signature' => $normalized,
        ':id' => $_SESSION['user_id']
    ]);
    echo json_encode([
        'success' => true,
        'signature' => $normalized ?? '',
        'message' => $normalized ? 'Signature updated.' : 'Signature cleared.'
    ]);
}
catch(PDOException $e)
{
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Unable to update signature at the moment.'
    ]);
}
