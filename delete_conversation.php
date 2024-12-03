<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $conversation_id = $input['conversation_id'] ?? null;
    
    if (!$conversation_id) {
        throw new Exception('Conversation ID required');
    }
    
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "DELETE FROM conversations 
              WHERE id = :conversation_id AND user_id = :user_id";
    
    $stmt = $db->prepare($query);
    $stmt->execute([
        ':conversation_id' => $conversation_id,
        ':user_id' => $_SESSION['user_id']
    ]);
    
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>