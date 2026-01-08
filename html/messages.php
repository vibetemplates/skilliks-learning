<?php
require_once 'includes/session.php';
require_once 'includes/messaging_functions.php';

// Check if user is logged in
requireLogin();

$currentUserId = getCurrentUserId();

include 'includes/header.php';
?>

<div class="container-fluid" style="margin-top: 20px;">
    <div class="row">
        <div class="col-12">
            <h1 class="mb-4">Messages</h1>
        </div>
    </div>
    
    <div class="row" id="messagesContainer" style="height: calc(100vh - 200px);">
        <!-- Left Panel: Conversation List -->
        <div class="col-md-4 border-end" style="height: 100%; overflow-y: auto;">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0">Conversations</h5>
                <button class="btn btn-primary btn-sm" id="newMessageBtn" data-bs-toggle="modal" data-bs-target="#newMessageModal">
                    <i class="fas fa-plus"></i> New Message
                </button>
            </div>
            
            <!-- Search Conversations -->
            <div class="mb-3">
                <input type="text" class="form-control" id="searchConversations" placeholder="Search conversations...">
            </div>
            
            <!-- Conversation List -->
            <div id="conversationList">
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Right Panel: Active Conversation -->
        <div class="col-md-8" style="height: 100%; display: flex; flex-direction: column;">
            <div id="conversationArea" style="flex: 1; display: flex; flex-direction: column;">
                <!-- Default Empty State -->
                <div id="emptyState" class="text-center py-5">
                    <i class="fas fa-comments fa-4x text-muted mb-3"></i>
                    <h5 class="text-muted">Select a conversation to start messaging</h5>
                    <p class="text-muted">Choose from your existing conversations or start a new one</p>
                </div>
                
                <!-- Conversation Header -->
                <div id="conversationHeader" class="border-bottom p-3 d-none">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center">
                            <img id="otherUserPhoto" src="" alt="" class="rounded-circle me-3" style="width: 40px; height: 40px; object-fit: cover;">
                            <div>
                                <h6 id="otherUserName" class="mb-0"></h6>
                                <small id="onlineStatus" class="text-muted"></small>
                            </div>
                        </div>
                        <div class="dropdown">
                            <button class="btn btn-sm" type="button" data-bs-toggle="dropdown">
                                <i class="fas fa-ellipsis-v"></i>
                            </button>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="#" id="blockUserBtn">Block User</a></li>
                                <li><a class="dropdown-item" href="#" id="deleteConversationBtn">Delete Conversation</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <!-- Messages Area -->
                <div id="messagesArea" class="flex-grow-1 p-3 d-none" style="overflow-y: auto;">
                    <!-- Messages will be loaded here -->
                </div>
                
                <!-- Message Input -->
                <div id="messageInput" class="border-top p-3 d-none">
                    <form id="sendMessageForm">
                        <div class="input-group">
                            <textarea class="form-control" id="messageText" placeholder="Type a message..." rows="1" style="resize: none;" maxlength="5000"></textarea>
                            <button class="btn btn-primary" type="submit" id="sendBtn">
                                <i class="fas fa-paper-plane"></i> Send
                            </button>
                        </div>
                        <small class="text-muted">
                            <span id="charCount">0</span>/5000 characters | 
                            Paste URLs for links, images, or YouTube videos
                        </small>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- New Message Modal -->
<div class="modal fade" id="newMessageModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">New Message</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Search for a member to message</label>
                    <input type="text" class="form-control" id="searchUsers" placeholder="Type name or email...">
                </div>
                <div id="userSearchResults">
                    <!-- Search results will appear here -->
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Custom styles for messaging */
.conversation-item {
    cursor: pointer;
    transition: background-color 0.2s;
}

.conversation-item:hover {
    background-color: #f8f9fa;
}

.conversation-item.active {
    background-color: #e3f2fd;
}

.message {
    max-width: 70%;
    word-wrap: break-word;
}

.message.mine {
    margin-left: auto;
}

.message.mine .message-bubble {
    background-color: #007bff;
    color: white;
}

.message.theirs .message-bubble {
    background-color: #e9ecef;
}

.message-bubble {
    padding: 10px 15px;
    border-radius: 18px;
    margin-bottom: 2px;
}

.message-time {
    font-size: 0.75rem;
    color: #6c757d;
}

.unread-badge {
    background-color: #dc3545;
    color: white;
    border-radius: 10px;
    padding: 2px 6px;
    font-size: 0.75rem;
}

#messagesArea {
    background-color: #f8f9fa;
}

/* Auto-resize textarea */
#messageText {
    min-height: 40px;
    max-height: 120px;
}

/* Rich content in messages */
.message-bubble img {
    max-width: 300px;
    max-height: 300px;
    border-radius: 8px;
    margin-top: 5px;
}

.message-bubble .youtube-embed {
    max-width: 400px;
    margin-top: 5px;
}

.message-bubble .youtube-embed iframe {
    width: 100%;
    height: 225px;
    border-radius: 8px;
}

.message-image {
    display: inline-block;
}

.message-bubble a {
    word-break: break-all;
}

/* Responsive YouTube embeds */
@media (max-width: 768px) {
    .message-bubble .youtube-embed {
        max-width: 100%;
    }
    
    .message-bubble .youtube-embed iframe {
        height: 180px;
    }
}
</style>

<script>
// Global variables
let currentConversationId = null;
let currentOtherUserId = null;
let lastMessageTimestamp = null;
let pollingInterval = null;

// Helper function to get avatar URL
function getAvatarUrl(photo, userId) {
    if (photo && photo.trim() !== '') {
        // Extract filename from path if it's a full path
        const filename = photo.includes('/') ? photo.split('/').pop() : photo;
        return '/serve-avatar.php?file=' + encodeURIComponent(filename);
    } else {
        // Use user ID to get default avatar
        return '/serve-avatar.php?user_id=' + userId;
    }
}

// Process message content for rich media
function processMessageContent(text) {
    // First escape HTML to prevent XSS
    let processed = escapeHtml(text);
    
    // Convert URLs to clickable links
    const urlRegex = /(https?:\/\/[^\s<]+)/g;
    processed = processed.replace(urlRegex, (url) => {
        // Check if it's a YouTube URL
        const youtubeMatch = url.match(/(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/)([a-zA-Z0-9_-]{11})/);
        if (youtubeMatch) {
            const videoId = youtubeMatch[1];
            return `<div class="youtube-embed my-2">
                        <iframe width="100%" height="315" 
                                src="https://www.youtube.com/embed/${videoId}" 
                                frameborder="0" 
                                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                                allowfullscreen>
                        </iframe>
                    </div>`;
        }
        
        // Check if it's an image URL
        const imageExtensions = /\.(jpg|jpeg|png|gif|webp)$/i;
        if (imageExtensions.test(url)) {
            return `<div class="message-image my-2">
                        <img src="${url}" alt="Shared image" class="img-fluid rounded" 
                             style="max-width: 100%; cursor: pointer;" 
                             onclick="window.open('${url}', '_blank')">
                    </div>`;
        }
        
        // Regular link
        return `<a href="${url}" target="_blank" rel="noopener noreferrer" class="text-primary">${url}</a>`;
    });
    
    // Convert line breaks to <br>
    processed = processed.replace(/\n/g, '<br>');
    
    return processed;
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    loadConversations();
    setupEventListeners();
    setupPolling();
    
    // Auto-resize message textarea
    const messageText = document.getElementById('messageText');
    messageText.addEventListener('input', function() {
        this.style.height = 'auto';
        this.style.height = this.scrollHeight + 'px';
        updateCharCount();
    });
});

// Load conversations
function loadConversations() {
    fetch('/api/messaging/conversations.php')
        .then(response => response.json())
        .then(data => {
            displayConversations(data.conversations);
        })
        .catch(error => {
            console.error('Error loading conversations:', error);
            showToast('Failed to load conversations', 'error');
        });
}

// Display conversations in left panel
function displayConversations(conversations) {
    const conversationList = document.getElementById('conversationList');
    
    if (conversations.length === 0) {
        conversationList.innerHTML = `
            <div class="text-center py-4">
                <p class="text-muted">No conversations yet</p>
                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#newMessageModal">
                    Start a conversation
                </button>
            </div>
        `;
        return;
    }
    
    conversationList.innerHTML = conversations.map(conv => {
        const lastMessageTime = conv.last_message ? formatMessageTime(conv.last_message.time) : '';
        const lastMessageText = conv.last_message ? 
            (conv.last_message.is_mine ? 'You: ' : '') + getMessagePreview(conv.last_message.text) : 
            'No messages yet';
        
        return `
            <div class="conversation-item p-3 border-bottom ${conv.conversation_id == currentConversationId ? 'active' : ''}" 
                 data-conversation-id="${conv.conversation_id}"
                 data-other-user-id="${conv.other_user.id}">
                <div class="d-flex">
                    <img src="${getAvatarUrl(conv.other_user.photo, conv.other_user.id)}" 
                         alt="${conv.other_user.name}" 
                         class="rounded-circle me-3" 
                         style="width: 50px; height: 50px; object-fit: cover;">
                    <div class="flex-grow-1">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="mb-0">
                                    ${conv.other_user.name}
                                    ${conv.other_user.online ? '<span class="text-success">●</span>' : ''}
                                </h6>
                                ${conv.community_name ? `<small class="text-muted">${conv.community_name}</small>` : ''}
                            </div>
                            <small class="text-muted">${lastMessageTime}</small>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <small class="text-muted">${lastMessageText}</small>
                            ${conv.unread_count > 0 ? `<span class="unread-badge">${conv.unread_count}</span>` : ''}
                        </div>
                    </div>
                </div>
            </div>
        `;
    }).join('');
    
    // Add click handlers
    document.querySelectorAll('.conversation-item').forEach(item => {
        item.addEventListener('click', function() {
            const conversationId = this.dataset.conversationId;
            const otherUserId = this.dataset.otherUserId;
            openConversation(conversationId, otherUserId);
        });
    });
}

// Open a conversation
function openConversation(conversationId, otherUserId) {
    currentConversationId = conversationId;
    currentOtherUserId = otherUserId;
    
    // Update UI
    document.querySelectorAll('.conversation-item').forEach(item => {
        item.classList.remove('active');
    });
    document.querySelector(`[data-conversation-id="${conversationId}"]`).classList.add('active');
    
    // Show conversation area
    document.getElementById('emptyState').classList.add('d-none');
    document.getElementById('conversationHeader').classList.remove('d-none');
    document.getElementById('messagesArea').classList.remove('d-none');
    document.getElementById('messageInput').classList.remove('d-none');
    
    // Load conversation details
    loadConversationMessages();
    updateConversationHeader();
}

// Load messages for current conversation
function loadConversationMessages(offset = 0) {
    fetch(`/api/messaging/conversation-messages.php?conversation_id=${currentConversationId}&offset=${offset}`)
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                showToast(data.error, 'error');
                return;
            }
            
            displayMessages(data.messages, offset === 0);
            
            // Update last message timestamp for polling
            if (data.messages.length > 0) {
                lastMessageTimestamp = data.messages[data.messages.length - 1].created_at;
            }
        })
        .catch(error => {
            console.error('Error loading messages:', error);
            showToast('Failed to load messages', 'error');
        });
}

// Display messages
function displayMessages(messages, clearExisting = true) {
    const messagesArea = document.getElementById('messagesArea');
    
    if (clearExisting) {
        messagesArea.innerHTML = '';
    }
    
    messages.forEach(message => {
        addMessageToUI(message);
    });
    
    // Scroll to bottom
    messagesArea.scrollTop = messagesArea.scrollHeight;
}

// Add a single message to UI
function addMessageToUI(message) {
    const messagesArea = document.getElementById('messagesArea');
    const messageDiv = document.createElement('div');
    messageDiv.className = `message ${message.is_mine ? 'mine' : 'theirs'} mb-3`;
    messageDiv.innerHTML = `
        <div class="d-flex ${message.is_mine ? 'justify-content-end' : ''}">
            ${!message.is_mine ? `
                <img src="${getAvatarUrl(message.sender.photo, message.sender.id)}" 
                     alt="${message.sender.name}" 
                     class="rounded-circle me-2" 
                     style="width: 30px; height: 30px; object-fit: cover;">
            ` : ''}
            <div>
                <div class="message-bubble">${processMessageContent(message.text)}</div>
                <div class="message-time ${message.is_mine ? 'text-end' : ''}">${formatMessageTime(message.created_at)}</div>
            </div>
        </div>
    `;
    messagesArea.appendChild(messageDiv);
}

// Update conversation header
function updateConversationHeader() {
    const activeConv = document.querySelector('.conversation-item.active');
    if (!activeConv) return;
    
    const otherUserName = activeConv.querySelector('h6').textContent.replace('●', '').trim();
    const otherUserPhoto = activeConv.querySelector('img').src;
    const isOnline = activeConv.querySelector('.text-success') !== null;
    
    document.getElementById('otherUserName').textContent = otherUserName;
    document.getElementById('otherUserPhoto').src = otherUserPhoto;
    document.getElementById('onlineStatus').textContent = isOnline ? 'Online' : 'Offline';
}

// Setup event listeners
function setupEventListeners() {
    // Send message form
    document.getElementById('sendMessageForm').addEventListener('submit', function(e) {
        e.preventDefault();
        sendMessage();
    });
    
    // Search conversations
    document.getElementById('searchConversations').addEventListener('input', function(e) {
        filterConversations(e.target.value);
    });
    
    // Search users for new message
    let searchTimeout;
    document.getElementById('searchUsers').addEventListener('input', function(e) {
        clearTimeout(searchTimeout);
        const searchTerm = e.target.value.trim();
        
        if (searchTerm.length < 2) {
            document.getElementById('userSearchResults').innerHTML = '';
            return;
        }
        
        searchTimeout = setTimeout(() => {
            searchUsersForMessage(searchTerm);
        }, 300);
    });
    
    // Block user
    document.getElementById('blockUserBtn').addEventListener('click', function(e) {
        e.preventDefault();
        if (confirm('Are you sure you want to block this user?')) {
            blockUser(currentOtherUserId);
        }
    });
    
    // Delete conversation
    document.getElementById('deleteConversationBtn').addEventListener('click', function(e) {
        e.preventDefault();
        if (confirm('Are you sure you want to delete this conversation? This action cannot be undone.')) {
            deleteConversation(currentConversationId);
        }
    });
}

// Send message
function sendMessage() {
    const messageText = document.getElementById('messageText').value.trim();
    
    if (!messageText) return;
    if (!currentConversationId) return;
    
    const sendBtn = document.getElementById('sendBtn');
    sendBtn.disabled = true;
    
    fetch('/api/messaging/send-message.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            conversation_id: currentConversationId,
            message_text: messageText
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.error) {
            showToast(data.error, 'error');
            return;
        }
        
        // Add message to UI
        addMessageToUI(data.message);
        
        // Clear input
        document.getElementById('messageText').value = '';
        document.getElementById('messageText').style.height = 'auto';
        updateCharCount();
        
        // Scroll to bottom
        const messagesArea = document.getElementById('messagesArea');
        messagesArea.scrollTop = messagesArea.scrollHeight;
        
        // Update conversation list
        loadConversations();
    })
    .catch(error => {
        console.error('Error sending message:', error);
        showToast('Failed to send message', 'error');
    })
    .finally(() => {
        sendBtn.disabled = false;
    });
}

// Search users for new message
function searchUsersForMessage(searchTerm) {
    const resultsDiv = document.getElementById('userSearchResults');
    resultsDiv.innerHTML = '<div class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></div>';
    
    fetch(`/api/messaging/search-users.php?q=${encodeURIComponent(searchTerm)}`)
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                resultsDiv.innerHTML = `<div class="alert alert-danger">${data.error}</div>`;
                return;
            }
            
            if (data.users.length === 0) {
                resultsDiv.innerHTML = '<div class="text-center py-3 text-muted">No users found</div>';
                return;
            }
            
            resultsDiv.innerHTML = `
                <div class="list-group">
                    ${data.users.map(user => `
                        <a href="#" class="list-group-item list-group-item-action ${user.is_blocked ? 'disabled' : ''}" 
                           onclick="startConversationWith(${user.id}, '${escapeHtml(user.name)}'); return false;">
                            <div class="d-flex align-items-center">
                                <img src="${getAvatarUrl(user.photo, user.id)}" 
                                     class="rounded-circle me-3" 
                                     style="width: 40px; height: 40px; object-fit: cover;">
                                <div>
                                    <h6 class="mb-0">${escapeHtml(user.name)}</h6>
                                    ${user.communities ? `<small class="text-muted">${escapeHtml(user.communities)}</small>` : ''}
                                    ${user.is_blocked ? '<small class="text-danger d-block">Blocked</small>' : ''}
                                </div>
                            </div>
                        </a>
                    `).join('')}
                </div>
            `;
        })
        .catch(error => {
            console.error('Error searching users:', error);
            resultsDiv.innerHTML = '<div class="alert alert-danger">Failed to search users</div>';
        });
}

// Start conversation with user
function startConversationWith(userId, userName) {
    fetch('/api/messaging/create-conversation.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            user_id: userId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.error) {
            showToast(data.error, 'error');
            return;
        }
        
        // Close modal
        const modal = bootstrap.Modal.getInstance(document.getElementById('newMessageModal'));
        modal.hide();
        
        // Clear search
        document.getElementById('searchUsers').value = '';
        document.getElementById('userSearchResults').innerHTML = '';
        
        // Open conversation
        openConversation(data.conversation_id, userId);
        
        // Reload conversations
        loadConversations();
    })
    .catch(error => {
        console.error('Error creating conversation:', error);
        showToast('Failed to create conversation', 'error');
    });
}

// Block user
function blockUser(userId) {
    fetch('/api/messaging/block-user.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            user_id: userId,
            action: 'block'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.error) {
            showToast(data.error, 'error');
            return;
        }
        
        showToast('User blocked successfully', 'success');
        
        // Reset view
        currentConversationId = null;
        currentOtherUserId = null;
        document.getElementById('emptyState').classList.remove('d-none');
        document.getElementById('conversationHeader').classList.add('d-none');
        document.getElementById('messagesArea').classList.add('d-none');
        document.getElementById('messageInput').classList.add('d-none');
        
        // Reload conversations
        loadConversations();
    })
    .catch(error => {
        console.error('Error blocking user:', error);
        showToast('Failed to block user', 'error');
    });
}

// Delete conversation
function deleteConversation(conversationId) {
    // This would mark the conversation as deleted for the current user
    // Implementation depends on backend approach
    showToast('Conversation deleted', 'success');
    
    // Reset view
    currentConversationId = null;
    currentOtherUserId = null;
    document.getElementById('emptyState').classList.remove('d-none');
    document.getElementById('conversationHeader').classList.add('d-none');
    document.getElementById('messagesArea').classList.add('d-none');
    document.getElementById('messageInput').classList.add('d-none');
    
    // Reload conversations
    loadConversations();
}

// Filter conversations
function filterConversations(searchTerm) {
    const items = document.querySelectorAll('.conversation-item');
    const term = searchTerm.toLowerCase();
    
    items.forEach(item => {
        const name = item.querySelector('h6').textContent.toLowerCase();
        if (name.includes(term)) {
            item.style.display = '';
        } else {
            item.style.display = 'none';
        }
    });
}

// Setup polling for new messages
function setupPolling() {
    // Poll every 5 seconds
    pollingInterval = setInterval(() => {
        if (currentConversationId) {
            checkForNewMessages();
        }
        // Always update conversation list for unread counts
        loadConversations();
    }, 5000);
}

// Check for new messages
function checkForNewMessages() {
    const since = lastMessageTimestamp || new Date().toISOString();
    
    fetch(`/api/messaging/check-new-messages.php?since=${encodeURIComponent(since)}`)
        .then(response => response.json())
        .then(data => {
            if (data.messages && data.messages.length > 0) {
                data.messages.forEach(message => {
                    if (message.conversation_id == currentConversationId) {
                        addMessageToUI(message);
                        lastMessageTimestamp = message.created_at;
                    }
                });
                
                // Scroll to bottom if near bottom
                const messagesArea = document.getElementById('messagesArea');
                if (messagesArea.scrollHeight - messagesArea.scrollTop - messagesArea.clientHeight < 100) {
                    messagesArea.scrollTop = messagesArea.scrollHeight;
                }
            }
        })
        .catch(error => {
            console.error('Error checking for new messages:', error);
        });
}

// Update character count
function updateCharCount() {
    const messageText = document.getElementById('messageText');
    const charCount = document.getElementById('charCount');
    charCount.textContent = messageText.value.length;
}

// Helper functions
function formatMessageTime(timestamp) {
    const date = new Date(timestamp);
    const now = new Date();
    const diff = now - date;
    
    if (diff < 60000) { // Less than 1 minute
        return 'Just now';
    } else if (diff < 3600000) { // Less than 1 hour
        return Math.floor(diff / 60000) + 'm ago';
    } else if (diff < 86400000) { // Less than 1 day
        return Math.floor(diff / 3600000) + 'h ago';
    } else if (diff < 172800000) { // Less than 2 days
        return 'Yesterday';
    } else {
        return date.toLocaleDateString();
    }
}

function truncateText(text, maxLength) {
    if (text.length <= maxLength) return text;
    return text.substr(0, maxLength) + '...';
}

// Strip rich content indicators for preview
function getMessagePreview(text) {
    // Check for YouTube URLs
    if (text.match(/(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/)/)) {
        return '📹 Video';
    }
    
    // Check for image URLs
    if (text.match(/https?:\/\/[^\s]+\.(jpg|jpeg|png|gif|webp)/i)) {
        return '🖼️ Image';
    }
    
    // Return truncated text for regular messages
    return truncateText(text, 50);
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function showToast(message, type = 'info') {
    // This should be implemented to show toast notifications
    console.log(`${type}: ${message}`);
}

// Cleanup on page unload
window.addEventListener('beforeunload', () => {
    if (pollingInterval) {
        clearInterval(pollingInterval);
    }
});
</script>

<?php include 'includes/footer.php'; ?>