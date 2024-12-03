<?php
session_start();
require_once 'config.php';
require_once 'azure_config.php';

// Oturum kontrolü
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Veritabanı tablolarını kontrol et ve oluştur
try {
    $database = new Database();
    $db = $database->getConnection();

    // Conversations tablosu
    $createConversationsSQL = "
    CREATE TABLE IF NOT EXISTS conversations (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id)
    )";

    // Chat history tablosu
    $createMessagesSQL = "
    CREATE TABLE IF NOT EXISTS chat_history (
        id INT PRIMARY KEY AUTO_INCREMENT,
        conversation_id INT NOT NULL,
        user_id INT NOT NULL,
        message TEXT NOT NULL,
        response TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id),
        FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE
    )";

    $db->exec($createConversationsSQL);
    $db->exec($createMessagesSQL);
} catch (PDOException $e) {
    error_log("Table creation error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .chat-sidebar {
            height: calc(100vh - 56px);
            overflow-y: auto;
            background-color: #f8f9fa;
        }
        .chat-container {
            height: calc(100vh - 160px);
            overflow-y: auto;
            border: 1px solid #dee2e6;
            border-radius: 0.25rem;
            padding: 1rem;
            background-color: #fff;
        }
        .message {
            margin-bottom: 1rem;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            max-width: 80%;
            white-space: pre-wrap;
        }
        .user-message {
            background-color: #007bff;
            color: white;
            margin-left: auto;
        }
        .bot-message {
            background-color: #f8f9fa;
            margin-right: auto;
        }
        .chat-item {
            cursor: pointer;
            padding: 0.5rem 1rem;
            border-bottom: 1px solid #dee2e6;
        }
        .chat-item:hover {
            background-color: #e9ecef;
        }
        .chat-item.active {
            background-color: #007bff;
            color: white;
        }
        .typing-indicator {
            display: none;
            padding: 0.5rem;
            color: #6c757d;
        }
        .delete-chat {
            float: right;
            cursor: pointer;
            opacity: 0.7;
        }
        .delete-chat:hover {
            opacity: 1;
        }
        .file-preview {
            max-height: 200px;
            overflow-y: auto;
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 0.25rem;
            padding: 0.5rem;
            margin-bottom: 1rem;
            font-family: monospace;
            white-space: pre;
        }
        #fileList {
            max-height: 200px;
            overflow-y: auto;
        }
        .file-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem;
            border-bottom: 1px solid #dee2e6;
        }
        .file-item:last-child {
            border-bottom: none;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">Chat Dashboard</a>
            <div class="navbar-nav ms-auto">
                <span class="nav-item nav-link">Hoş geldin, <?php echo htmlspecialchars($_SESSION['username']); ?></span>
                <a class="nav-link" href="logout.php">Çıkış Yap</a>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <!-- Sol Sidebar -->
            <div class="col-md-3 col-lg-2 px-0 chat-sidebar border-end">
                <div class="p-3">
                    <button id="newChatBtn" class="btn btn-primary w-100 mb-3">
                        <i class="bi bi-plus-circle"></i> Yeni Sohbet
                    </button>
                </div>
                <div id="chatList">
                    <!-- Sohbet listesi JavaScript ile doldurulacak -->
                </div>
            </div>

            <!-- Ana Sohbet Alanı -->
            <div class="col-md-9 col-lg-10 p-4">
                <div id="chatTitle" class="h4 mb-3">Yeni Sohbet</div>
                <div class="chat-container" id="chatContainer">
                    <!-- Mesajlar buraya gelecek -->
                </div>
                <div class="typing-indicator" id="typingIndicator">
                    Bot yazıyor...
                </div>
                <form id="chatForm" class="mt-3">
                    <div class="input-group">
                        <input type="text" id="messageInput" class="form-control" placeholder="Mesajınızı yazın...">
                        <input type="file" id="codeFileInput" class="form-control" accept=".php,.html,.css,.js,.sql,.xml,.json,.txt" multiple style="display: none;">
                        <button type="button" id="uploadButton" class="btn btn-secondary">
                            <i class="bi bi-file-earmark-code"></i>
                        </button>
                        <button type="submit" class="btn btn-primary">Gönder</button>
                    </div>
                    <div id="filePreviewContainer" style="display: none;" class="mt-2">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="mb-0">Seçilen Dosyalar:</h6>
                            <button type="button" class="btn btn-sm btn-outline-primary" id="sendFilesButton">Dosyaları Gönder</button>
                        </div>
                        <div id="fileList" class="list-group mb-2"></div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
	<script>
    let currentConversationId = null;
    const chatForm = document.getElementById('chatForm');
    const messageInput = document.getElementById('messageInput');
    const chatContainer = document.getElementById('chatContainer');
    const typingIndicator = document.getElementById('typingIndicator');
    const chatList = document.getElementById('chatList');
    const newChatBtn = document.getElementById('newChatBtn');
    const uploadButton = document.getElementById('uploadButton');
    const codeFileInput = document.getElementById('codeFileInput');
    const filePreviewContainer = document.getElementById('filePreviewContainer');
    const fileList = document.getElementById('fileList');
    const sendFilesButton = document.getElementById('sendFilesButton');
    let selectedFiles = [];

    // Dosya yükleme işlemleri
    uploadButton.onclick = () => codeFileInput.click();

    codeFileInput.onchange = function() {
        selectedFiles = Array.from(this.files);
        
        if (selectedFiles.length > 0) {
            fileList.innerHTML = '';
            selectedFiles.forEach(file => {
                const item = document.createElement('div');
                item.className = 'list-group-item d-flex justify-content-between align-items-center';
                item.innerHTML = `
                    <span>${file.name}</span>
                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeFile('${file.name}')">
                        <i class="bi bi-x"></i>
                    </button>
                `;
                fileList.appendChild(item);
            });
            filePreviewContainer.style.display = 'block';
        } else {
            filePreviewContainer.style.display = 'none';
        }
    };

    function removeFile(fileName) {
        selectedFiles = selectedFiles.filter(file => file.name !== fileName);
        if (selectedFiles.length === 0) {
            filePreviewContainer.style.display = 'none';
            codeFileInput.value = '';
        } else {
            const itemToRemove = Array.from(fileList.children).find(item => 
                item.querySelector('span').textContent === fileName
            );
            if (itemToRemove) fileList.removeChild(itemToRemove);
        }
    }

    sendFilesButton.onclick = async function() {
        if (selectedFiles.length === 0) return;
        
        const formData = new FormData();
        selectedFiles.forEach(file => {
            formData.append('code_files[]', file);
        });
        
        if (currentConversationId) {
            formData.append('conversation_id', currentConversationId);
        }
        
        typingIndicator.style.display = 'block';
        
        try {
            const response = await fetch('file_upload_handler.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                appendMessage('user', 'Dosyalar yüklendi:\n' + selectedFiles.map(f => f.name).join('\n'));
                appendMessage('bot', 'Dosyalar başarıyla yüklendi. Şimdi dosyalar hakkında istediğiniz soruyu sorabilirsiniz.');
                
                if (!currentConversationId && data.conversation_id) {
                    currentConversationId = data.conversation_id;
                    await loadConversations();
                }
            } else {
                console.error('Upload error:', data.error);
                appendMessage('bot', 'Dosya yükleme hatası: ' + data.error);
            }
        } catch (error) {
            console.error('Network error:', error);
            appendMessage('bot', 'Bir ağ hatası oluştu. Lütfen tekrar deneyin.');
        } finally {
            typingIndicator.style.display = 'none';
            filePreviewContainer.style.display = 'none';
            codeFileInput.value = '';
            selectedFiles = [];
        }
    };

    // Sohbet listesini yükle
    async function loadConversations() {
        try {
            const response = await fetch('get_conversations.php');
            const data = await response.json();
            
            chatList.innerHTML = '';
            data.forEach(conversation => {
                const div = document.createElement('div');
                div.className = `chat-item ${conversation.id == currentConversationId ? 'active' : ''}`;
                div.innerHTML = `
                    ${conversation.title}
                    <i class="bi bi-trash delete-chat" data-id="${conversation.id}"></i>
                `;
                div.onclick = (e) => {
                    if (!e.target.classList.contains('delete-chat')) {
                        loadConversation(conversation.id);
                    }
                };
                div.querySelector('.delete-chat').onclick = (e) => {
                    e.stopPropagation();
                    deleteConversation(conversation.id);
                };
                chatList.appendChild(div);
            });
        } catch (error) {
            console.error('Error loading conversations:', error);
        }
    }

    // Yeni sohbet başlat
    newChatBtn.onclick = () => {
        currentConversationId = null;
        chatContainer.innerHTML = '';
        document.getElementById('chatTitle').textContent = 'Yeni Sohbet';
        messageInput.focus();
    };

    // Sohbet sil
    async function deleteConversation(conversationId) {
        if (confirm('Bu sohbeti silmek istediğinizden emin misiniz?')) {
            try {
                const response = await fetch('delete_conversation.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ conversation_id: conversationId })
                });
                const data = await response.json();
                
                if (data.success) {
                    if (currentConversationId === conversationId) {
                        newChatBtn.click();
                    }
                    loadConversations();
                }
            } catch (error) {
                console.error('Error deleting conversation:', error);
            }
        }
    }

    // Sohbet yükle
    async function loadConversation(conversationId) {
        try {
            const response = await fetch(`get_messages.php?conversation_id=${conversationId}`);
            const data = await response.json();
            
            currentConversationId = conversationId;
            chatContainer.innerHTML = '';
            document.getElementById('chatTitle').textContent = data.title;
            
            data.messages.forEach(msg => {
                appendMessage('user', msg.message);
                appendMessage('bot', msg.response);
            });
            
            document.querySelectorAll('.chat-item').forEach(item => {
                item.classList.remove('active');
                if (item.querySelector(`[data-id="${conversationId}"]`)) {
                    item.classList.add('active');
                }
            });
        } catch (error) {
            console.error('Error loading conversation:', error);
        }
    }

    // Form gönderimi
    chatForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const message = messageInput.value.trim();
        if (!message) return;

        appendMessage('user', message);
        messageInput.value = '';
        typingIndicator.style.display = 'block';

        try {
            const response = await fetch('chat_handler.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    message,
                    conversation_id: currentConversationId
                })
            });

            const data = await response.json();
            
            if (data.success) {
                appendMessage('bot', data.response);
                if (data.conversation_id) {
                    currentConversationId = data.conversation_id;
                    await loadConversations();
                }
            } else {
                console.error('Chat error:', data.error);
                appendMessage('bot', 'Üzgünüm, bir hata oluştu: ' + data.error);
            }
        } catch (error) {
            console.error('Network error:', error);
            appendMessage('bot', 'Üzgünüm, bir ağ hatası oluştu. Lütfen tekrar deneyin.');
        } finally {
            typingIndicator.style.display = 'none';
        }
    });

    function appendMessage(sender, message) {
        const messageDiv = document.createElement('div');
        messageDiv.className = `message ${sender}-message`;
        messageDiv.textContent = message;
        chatContainer.appendChild(messageDiv);
        chatContainer.scrollTop = chatContainer.scrollHeight;
    }

    // Sayfa yüklendiğinde sohbet listesini yükle
    document.addEventListener('DOMContentLoaded', () => {
        loadConversations();
    });

    // Her 30 saniyede bir sohbet listesini güncelle
    setInterval(loadConversations, 30000);
</script>
</body>
</html>