<?php
session_start();

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['moderator', 'evaluator'])) {
    header("Location: ../auth/login.php");
    exit();
}

require_once '../config/config.php';
require_once '../includes/functions.php';

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

// Handle sending messages
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $recipient_id = (int)$_POST['recipient_id'];
    $message = trim($_POST['message']);
    
    if ($message && $recipient_id) {
        try {
            $insert_query = "INSERT INTO messages (sender_id, recipient_id, message, created_at) VALUES (?, ?, ?, NOW())";
            $stmt = $pdo->prepare($insert_query);
            $stmt->execute([$user_id, $recipient_id, $message]);
            
            $success_message = "Message sent successfully!";
        } catch (Exception $e) {
            $error_message = "Error sending message: " . $e->getMessage();
        }
    }
}

// Get conversation partners
if ($user_role === 'moderator') {
    // Get evaluators under this moderator
    $partners_query = "SELECT id, name, email FROM users WHERE moderator_id = ? AND role = 'evaluator' AND is_active = 1 ORDER BY name";
    $partners_stmt = $pdo->prepare($partners_query);
    $partners_stmt->execute([$user_id]);
} else {
    // Get the moderator for this evaluator
    $partners_query = "SELECT id, name, email FROM users WHERE id = (SELECT moderator_id FROM users WHERE id = ?) AND role = 'moderator'";
    $partners_stmt = $pdo->prepare($partners_query);
    $partners_stmt->execute([$user_id]);
}
$partners = $partners_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get selected conversation
$selected_partner_id = isset($_GET['with']) ? (int)$_GET['with'] : ($partners[0]['id'] ?? 0);

if ($selected_partner_id) {
    // Get messages between current user and selected partner
    $messages_query = "
        SELECT m.*, 
               s.name as sender_name, 
               r.name as recipient_name,
               s.role as sender_role
        FROM messages m
        JOIN users s ON m.sender_id = s.id
        JOIN users r ON m.recipient_id = r.id
        WHERE (m.sender_id = ? AND m.recipient_id = ?) 
           OR (m.sender_id = ? AND m.recipient_id = ?)
        ORDER BY m.created_at ASC
    ";
    $messages_stmt = $pdo->prepare($messages_query);
    $messages_stmt->execute([$user_id, $selected_partner_id, $selected_partner_id, $user_id]);
    $messages = $messages_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get partner info
    $partner_query = "SELECT name, email, role FROM users WHERE id = ?";
    $partner_stmt = $pdo->prepare($partner_query);
    $partner_stmt->execute([$selected_partner_id]);
    $partner_info = $partner_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Mark messages as read
    $mark_read_query = "UPDATE messages SET is_read = 1 WHERE sender_id = ? AND recipient_id = ? AND is_read = 0";
    $mark_read_stmt = $pdo->prepare($mark_read_query);
    $mark_read_stmt->execute([$selected_partner_id, $user_id]);
}

include '../includes/header.php';

// Create messages table if it doesn't exist
try {
    $create_table = "CREATE TABLE IF NOT EXISTS messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sender_id INT NOT NULL,
        recipient_id INT NOT NULL,
        message TEXT NOT NULL,
        is_read BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (sender_id) REFERENCES users(id),
        FOREIGN KEY (recipient_id) REFERENCES users(id)
    )";
    $pdo->exec($create_table);
} catch (Exception $e) {
    // Table might already exist
}
?>

<style>
.chat-container {
    height: 600px;
    background: white;
    border-radius: 15px;
    box-shadow: 0 8px 25px rgba(0,0,0,0.1);
    overflow: hidden;
}

.chat-sidebar {
    background: #f8f9fa;
    border-right: 1px solid #dee2e6;
    height: 100%;
    overflow-y: auto;
}

.chat-main {
    height: 100%;
    display: flex;
    flex-direction: column;
}

.chat-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 1rem;
    border-bottom: 1px solid #dee2e6;
}

.chat-messages {
    flex: 1;
    overflow-y: auto;
    padding: 1rem;
    background: #f8f9fa;
}

.chat-input {
    padding: 1rem;
    border-top: 1px solid #dee2e6;
    background: white;
}

.message {
    margin-bottom: 1rem;
    padding: 0.75rem 1rem;
    border-radius: 18px;
    max-width: 70%;
    word-wrap: break-word;
}

.message.sent {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    margin-left: auto;
    text-align: right;
}

.message.received {
    background: white;
    border: 1px solid #dee2e6;
    margin-right: auto;
}

.message-time {
    font-size: 0.75rem;
    opacity: 0.7;
    margin-top: 0.25rem;
}

.partner-item {
    padding: 1rem;
    border-bottom: 1px solid #dee2e6;
    cursor: pointer;
    transition: all 0.3s ease;
}

.partner-item:hover, .partner-item.active {
    background: #e9ecef;
}

.partner-item.active {
    border-left: 4px solid #667eea;
}

.online-indicator {
    width: 10px;
    height: 10px;
    background: #28a745;
    border-radius: 50%;
    display: inline-block;
    margin-right: 0.5rem;
}
</style>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3 mb-2">
                        <i class="fas fa-comments text-primary"></i> Communication Center
                    </h1>
                    <p class="text-muted mb-0">Direct communication between moderators and evaluators</p>
                </div>
                <div>
                    <?php if ($user_role === 'moderator'): ?>
                        <a href="../moderator/dashboard.php" class="btn btn-outline-primary">
                            <i class="fas fa-arrow-left"></i> Back to Dashboard
                        </a>
                    <?php else: ?>
                        <a href="assignments.php" class="btn btn-outline-primary">
                            <i class="fas fa-arrow-left"></i> Back to Assignments
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php if (isset($success_message)): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success_message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error_message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Chat Interface -->
    <div class="row">
        <div class="col-12">
            <div class="chat-container">
                <div class="row h-100 g-0">
                    <!-- Sidebar -->
                    <div class="col-md-4 chat-sidebar">
                        <div class="p-3 border-bottom">
                            <h6 class="mb-0">
                                <?= $user_role === 'moderator' ? 'Your Evaluators' : 'Your Moderator' ?>
                            </h6>
                        </div>
                        
                        <?php if (empty($partners)): ?>
                            <div class="p-3 text-center text-muted">
                                <i class="fas fa-user-slash fa-2x mb-2"></i>
                                <p class="mb-0">
                                    <?= $user_role === 'moderator' ? 'No evaluators assigned' : 'No moderator assigned' ?>
                                </p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($partners as $partner): ?>
                                <div class="partner-item <?= $partner['id'] == $selected_partner_id ? 'active' : '' ?>" 
                                     onclick="location.href='?with=<?= $partner['id'] ?>'">
                                    <div class="d-flex align-items-center">
                                        <div class="me-3">
                                            <i class="fas fa-user-circle fa-2x text-muted"></i>
                                        </div>
                                        <div>
                                            <h6 class="mb-1"><?= htmlspecialchars($partner['name']) ?></h6>
                                            <small class="text-muted"><?= htmlspecialchars($partner['email']) ?></small>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <!-- Main Chat Area -->
                    <div class="col-md-8 chat-main">
                        <?php if ($selected_partner_id && $partner_info): ?>
                            <!-- Chat Header -->
                            <div class="chat-header">
                                <div class="d-flex align-items-center">
                                    <div class="me-3">
                                        <i class="fas fa-user-circle fa-2x"></i>
                                    </div>
                                    <div>
                                        <h6 class="mb-1"><?= htmlspecialchars($partner_info['name']) ?></h6>
                                        <small class="opacity-75">
                                            <?= ucfirst($partner_info['role']) ?> • <?= htmlspecialchars($partner_info['email']) ?>
                                        </small>
                                    </div>
                                </div>
                            </div>

                            <!-- Messages -->
                            <div class="chat-messages" id="messagesContainer">
                                <?php if (empty($messages)): ?>
                                    <div class="text-center text-muted py-5">
                                        <i class="fas fa-comment fa-3x mb-3"></i>
                                        <p class="mb-0">No messages yet. Start a conversation!</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($messages as $message): ?>
                                        <div class="message <?= $message['sender_id'] == $user_id ? 'sent' : 'received' ?>">
                                            <div><?= nl2br(htmlspecialchars($message['message'])) ?></div>
                                            <div class="message-time">
                                                <?= date('M j, Y g:i A', strtotime($message['created_at'])) ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>

                            <!-- Message Input -->
                            <div class="chat-input">
                                <form method="POST" id="messageForm">
                                    <input type="hidden" name="recipient_id" value="<?= $selected_partner_id ?>">
                                    <div class="input-group">
                                        <textarea class="form-control" name="message" id="messageInput" 
                                                  placeholder="Type your message..." rows="2" required></textarea>
                                        <button type="submit" name="send_message" class="btn btn-primary">
                                            <i class="fas fa-paper-plane"></i> Send
                                        </button>
                                    </div>
                                </form>
                            </div>
                        <?php else: ?>
                            <div class="d-flex align-items-center justify-content-center h-100">
                                <div class="text-center text-muted">
                                    <i class="fas fa-comments fa-4x mb-3"></i>
                                    <h5>Select a conversation</h5>
                                    <p class="mb-0">Choose someone from the sidebar to start chatting</p>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Auto-scroll to bottom of messages
function scrollToBottom() {
    const container = document.getElementById('messagesContainer');
    if (container) {
        container.scrollTop = container.scrollHeight;
    }
}

// Scroll to bottom on page load
document.addEventListener('DOMContentLoaded', scrollToBottom);

// Handle message form submission
document.getElementById('messageForm')?.addEventListener('submit', function(e) {
    const messageInput = document.getElementById('messageInput');
    if (!messageInput.value.trim()) {
        e.preventDefault();
        messageInput.focus();
    }
});

// Auto-refresh messages every 30 seconds
setInterval(function() {
    if (window.location.search.includes('with=')) {
        // Only reload if we're in a conversation
        location.reload();
    }
}, 30000);

// Allow Enter to send message (Shift+Enter for new line)
document.getElementById('messageInput')?.addEventListener('keydown', function(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        document.getElementById('messageForm').submit();
    }
});
</script>

<?php include('../includes/footer.php'); ?>