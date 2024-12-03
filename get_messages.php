<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

try {
    $conversation_id = $_GET['conversation_id'] ?? null;
    
    if (!$conversation_id) {
        throw new Exception('Conversation ID required');
    }
    
    $database = new Database();
    $db = $database->getConnection();
    
    // Get conversation title
    $titleQuery = "SELECT title FROM conversations 
                   WHERE id = :conversation_id AND user_id = :user_id";
    $titleStmt = $db->prepare($titleQuery);
    $titleStmt->execute([
        ':conversation_id' => $conversation_id,
        ':user_id' => $_SESSION['user_id']
    ]);
    $title = $titleStmt->fetch(PDO::FETCH_ASSOC)['title'];
    
    // Get messages
    $query = "SELECT message, response FROM chat_history 
              WHERE conversation_id = :conversation_id AND user_id = :user_id 
              ORDER BY created_at ASC";
    
    $stmt = $db->prepare($query);
    $stmt->execute([
        ':conversation_id' => $conversation_id,
        ':user_id' => $_SESSION['user_id']
    ]);
    
    echo json_encode([
        'title' => $title,
        'messages' => $stmt->fetchAll(PDO::FETCH_ASSOC)
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>