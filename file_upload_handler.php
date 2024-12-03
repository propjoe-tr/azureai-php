<?php
session_start();
require_once 'config.php';
require_once 'azure_config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Oturum sonlanmış']);
    exit();
}

try {
    if (!isset($_FILES['code_files'])) {
        throw new Exception('Dosya yüklenmedi');
    }

    $files = reArrayFiles($_FILES['code_files']);
    $userQuestion = $_POST['question'] ?? 'Bu kodları analiz et';
    
    // Dosya kontrolü için ayarlar
    $allowedTypes = ['php', 'html', 'css', 'js', 'sql', 'xml', 'json', 'txt'];
    $maxFileSize = 1048576; // 1MB
    $totalContent = '';

    // Her dosyayı işle
    foreach ($files as $file) {
        // Dosya boyutu kontrolü
        if ($file['size'] > $maxFileSize) {
            throw new Exception($file['name'] . ' dosyası çok büyük. Maksimum 1MB.');
        }
        
        // Dosya uzantısı kontrolü
        $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($fileExt, $allowedTypes)) {
            throw new Exception($file['name'] . ' dosya türü desteklenmiyor. Desteklenen türler: ' . implode(', ', $allowedTypes));
        }

        // Dosya içeriği kontrolü
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception($file['name'] . ' dosyası yüklenirken hata: ' . getUploadErrorMessage($file['error']));
        }

        // Dosya içeriğini oku
        $content = file_get_contents($file['tmp_name']);
        if ($content === false) {
            throw new Exception($file['name'] . ' dosya içeriği okunamadı');
        }

        $totalContent .= "\nDosya: {$file['name']}\n";
        $totalContent .= "```{$fileExt}\n{$content}\n```\n";
    }

    // Yapay zekaya gönderilecek mesajı hazırla
    $message = "Kullanıcı Sorusu: {$userQuestion}\n\n";
    $message .= "İncelenen Dosyalar:\n{$totalContent}";

    // Azure API'ye gönder
    $azure = new AzureConfig();
    $messages = [
        [
            'role' => 'system',
            'content' => 'Sen bir kod analiz uzmanısın. Kullanıcının sorusuna göre gönderilen kod dosyalarını incele ve analiz et. Kod ile ilgili detaylı bilgi ver, varsa güvenlik açıklarını belirt, kodun kalitesini değerlendir ve iyileştirme önerileri sun.'
        ],
        [
            'role' => 'user',
            'content' => $message
        ]
    ];

    $response = $azure->makeRequest($messages);

    if (!isset($response['choices'][0]['message']['content'])) {
        throw new Exception('API yanıtı geçersiz');
    }

    $botResponse = $response['choices'][0]['message']['content'];

    // Veritabanı işlemleri
    $database = new Database();
    $db = $database->getConnection();

    // Yeni conversation oluştur veya mevcut olanı kullan
    if (!isset($_POST['conversation_id']) || empty($_POST['conversation_id'])) {
        $query = "INSERT INTO conversations (user_id, title) VALUES (:user_id, :title)";
        $stmt = $db->prepare($query);
        $stmt->execute([
            ':user_id' => $_SESSION['user_id'],
            ':title' => "Kod Analizi: " . substr($userQuestion, 0, 50)
        ]);
        $conversation_id = $db->lastInsertId();
    } else {
        $conversation_id = $_POST['conversation_id'];
    }

    // Chat history'ye kaydet
    $query = "INSERT INTO chat_history (conversation_id, user_id, message, response) 
              VALUES (:conversation_id, :user_id, :message, :response)";
    $stmt = $db->prepare($query);
    $stmt->execute([
        ':conversation_id' => $conversation_id,
        ':user_id' => $_SESSION['user_id'],
        ':message' => $message,
        ':response' => $botResponse
    ]);

    // Başarılı yanıt döndür
    echo json_encode([
        'success' => true,
        'message' => $message,
        'response' => $botResponse,
        'conversation_id' => $conversation_id
    ]);

} catch (Exception $e) {
    error_log('File Upload Error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

// Yardımcı fonksiyonlar
function reArrayFiles($files) {
    $result = [];
    foreach($files as $key1 => $value1)
        foreach($value1 as $key2 => $value2)
            $result[$key2][$key1] = $value2;
    return $result;
}

function getUploadErrorMessage($code) {
    switch ($code) {
        case UPLOAD_ERR_INI_SIZE:
            return 'Dosya boyutu PHP konfigürasyonundaki upload_max_filesize değerini aşıyor';
        case UPLOAD_ERR_FORM_SIZE:
            return 'Dosya boyutu HTML formundaki MAX_FILE_SIZE değerini aşıyor';
        case UPLOAD_ERR_PARTIAL:
            return 'Dosya kısmen yüklendi';
        case UPLOAD_ERR_NO_FILE:
            return 'Dosya yüklenmedi';
        case UPLOAD_ERR_NO_TMP_DIR:
            return 'Geçici klasör eksik';
        case UPLOAD_ERR_CANT_WRITE:
            return 'Dosya diske yazılamadı';
        case UPLOAD_ERR_EXTENSION:
            return 'Dosya yüklemesi bir PHP uzantısı tarafından durduruldu';
        default:
            return 'Bilinmeyen yükleme hatası';
    }
}
?>