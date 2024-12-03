<?php
session_start();
require_once 'config.php';
require_once 'azure_config.php';

header('Content-Type: application/json');

// Hata gösterimi açık
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Oturum kontrolü
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Oturum sonlanmış.']);
    exit();
}

try {
    // Girdi verilerini al
    $input = json_decode(file_get_contents('php://input'), true);
    $message = $input['message'] ?? '';
    $conversation_id = $input['conversation_id'] ?? null;

    if (empty($message)) {
        throw new Exception('Mesaj boş olamaz.');
    }

    // Veritabanı bağlantısı
    $database = new Database();
    $db = $database->getConnection();

    // Eğer conversation_id yoksa veya geçersizse yeni bir sohbet başlat
    if (!$conversation_id) {
        $query = "INSERT INTO conversations (user_id, title) VALUES (:user_id, :title)";
        $stmt = $db->prepare($query);
        $stmt->execute([
            ':user_id' => $_SESSION['user_id'],
            ':title' => substr($message, 0, 50) . '...'
        ]);
        $conversation_id = $db->lastInsertId();
        error_log("Yeni sohbet oluşturuldu. ID: " . $conversation_id);
    }

    // Sohbet geçmişini al (maksimum son 10 mesaj)
    $historyQuery = "SELECT message, response FROM chat_history 
                    WHERE conversation_id = :conversation_id 
                    ORDER BY created_at DESC LIMIT 10";
    $historyStmt = $db->prepare($historyQuery);
    $historyStmt->execute([':conversation_id' => $conversation_id]);
    $history = array_reverse($historyStmt->fetchAll(PDO::FETCH_ASSOC));

    // API mesajlarını hazırla
    $messages = [
        [
            'role' => 'system',
            'content' => 'Sen GPT-4O modelinin 2024-08-06 versiyonusun. En son güncellemen Ağustos 2024\'te yapıldı. Yardımsever bir asistan olarak kullanıcılara destek oluyorsun.'
        ]
    ];

    // Geçmiş mesajları ekle
    foreach ($history as $chat) {
        $messages[] = ['role' => 'user', 'content' => $chat['message']];
        $messages[] = ['role' => 'assistant', 'content' => $chat['response']];
    }

    // Yeni mesajı ekle
    $messages[] = ['role' => 'user', 'content' => $message];

    // Azure OpenAI API'yi çağır
    $azure = new AzureConfig();
    $response = $azure->makeRequest($messages);
    
    if (!isset($response['choices'][0]['message']['content'])) {
        throw new Exception('API yanıtı geçerli değil: ' . json_encode($response));
    }

    $botResponse = $response['choices'][0]['message']['content'];

    // Mesajı veritabanına kaydet
    $query = "INSERT INTO chat_history (conversation_id, user_id, message, response) 
              VALUES (:conversation_id, :user_id, :message, :response)";
    $stmt = $db->prepare($query);
    $stmt->execute([
        ':conversation_id' => $conversation_id,
        ':user_id' => $_SESSION['user_id'],
        ':message' => $message,
        ':response' => $botResponse
    ]);

    // Conversation'ın son güncelleme zamanını güncelle
    $updateQuery = "UPDATE conversations 
                   SET updated_at = CURRENT_TIMESTAMP 
                   WHERE id = :conversation_id";
    $updateStmt = $db->prepare($updateQuery);
    $updateStmt->execute([':conversation_id' => $conversation_id]);

    // Başarılı yanıt döndür
    echo json_encode([
        'success' => true,
        'response' => $botResponse,
        'conversation_id' => $conversation_id
    ]);

} catch (Exception $e) {
    // Hata logla
    error_log('Chat Error: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    
    // Hata yanıtı döndür
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'debug_info' => [
            'error_message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]
    ]);
}
?>