<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "SELECT id, title, created_at 
              FROM conversations 
              WHERE user_id = :user_id 
              ORDER BY updated_at DESC";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->execute();
    
    $conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Debug için
    error_log("Fetched conversations for user " . $_SESSION['user_id'] . ": " . print_r($conversations, true));
    
    echo json_encode($conversations);
} catch (Exception $e) {
    error_log("Error fetching conversations: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>