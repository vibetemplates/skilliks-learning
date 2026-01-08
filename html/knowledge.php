<?php
/**
 * Knowledge Page
 * 
 * AI-powered knowledge assistant with chat interface
 */

$page_title = 'Knowledge Assistant';
require_once 'includes/session.php';
require_once 'config/database.php';
require_once 'config/functions.php';

// Require login
requireLogin();

$currentUserId = getCurrentUserId();
$currentCommunityId = getCurrentCommunityId();

// Common topics for the sidebar
$commonTopics = [
    'Getting Started' => [
        'How do I join a project?',
        'What are the different user roles?',
        'How do I complete my profile?',
        'Where can I find help?'
    ],
    'Projects' => [
        'How to create a new project?',
        'Managing project tasks',
        'Understanding project workflows',
        'Git integration basics'
    ],
    'Courses & Learning' => [
        'How to enroll in a course?',
        'Taking quizzes and assessments',
        'Tracking my progress',
        'Understanding my learning plan'
    ],
    'Community' => [
        'Creating community posts',
        'Participating in discussions',
        'Community guidelines',
        'Finding team members'
    ],
    'Technical Help' => [
        'Troubleshooting common issues',
        'Browser requirements',
        'Account settings',
        'Notification preferences'
    ]
];

require_once 'includes/header.php';
?>

<style>
/* Chat interface styles */
.knowledge-container {
    height: calc(100vh - 120px);
    display: flex;
}

.topics-sidebar {
    width: 280px;
    background-color: #f8f9fa;
    border-right: 1px solid #dee2e6;
    overflow-y: auto;
    padding: 1rem;
}

.chat-container {
    flex: 1;
    display: flex;
    flex-direction: column;
    background-color: #ffffff;
}

.chat-messages {
    flex: 1;
    overflow-y: auto;
    padding: 2rem;
    background-color: #f8f9fa;
}

.chat-input-container {
    padding: 1.5rem;
    background-color: #ffffff;
    border-top: 1px solid #dee2e6;
}

.message {
    margin-bottom: 1.5rem;
    display: flex;
    gap: 1rem;
}

.message-avatar {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    flex-shrink: 0;
}

.message-user .message-avatar {
    background-color: #0d6efd;
    color: white;
}

.message-assistant .message-avatar {
    background-color: #6c757d;
    color: white;
}

.message-content {
    flex: 1;
    padding: 0.75rem 1rem;
    border-radius: 0.5rem;
    max-width: 70%;
}

.message-user .message-content {
    background-color: #0d6efd;
    color: white;
    margin-left: auto;
}

.message-assistant .message-content {
    background-color: #ffffff;
    border: 1px solid #dee2e6;
}

.topic-item {
    cursor: pointer;
    padding: 0.5rem 0.75rem;
    border-radius: 0.375rem;
    margin-bottom: 0.25rem;
    transition: background-color 0.2s;
}

.topic-item:hover {
    background-color: #e9ecef;
}

.topic-category {
    font-weight: 600;
    color: #495057;
    margin-bottom: 0.5rem;
    margin-top: 1rem;
}

.topic-category:first-child {
    margin-top: 0;
}

.welcome-message {
    text-align: center;
    padding: 3rem;
    color: #6c757d;
}

.chat-input-wrapper {
    position: relative;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.chat-input {
    flex: 1;
    padding: 0.75rem 1rem;
    border: 1px solid #dee2e6;
    border-radius: 0.5rem;
    resize: none;
    min-height: 50px;
    max-height: 150px;
}

.chat-submit {
    padding: 0.75rem 1.5rem;
    background-color: #0d6efd;
    color: white;
    border: none;
    border-radius: 0.5rem;
    cursor: pointer;
    transition: background-color 0.2s;
}

.chat-submit:hover {
    background-color: #0b5ed7;
}

.chat-submit:disabled {
    background-color: #6c757d;
    cursor: not-allowed;
}

/* Responsive design */
@media (max-width: 768px) {
    .knowledge-container {
        flex-direction: column;
    }
    
    .topics-sidebar {
        width: 100%;
        height: auto;
        border-right: none;
        border-bottom: 1px solid #dee2e6;
    }
    
    .message-content {
        max-width: 90%;
    }
}
</style>

<main class="container-fluid px-0">
    <div class="knowledge-container">
        <!-- Sidebar with common topics -->
        <div class="topics-sidebar">
            <h5 class="mb-3">Common Topics</h5>
            <?php foreach ($commonTopics as $category => $topics): ?>
                <div class="topic-category"><?php echo htmlspecialchars($category); ?></div>
                <?php foreach ($topics as $topic): ?>
                    <div class="topic-item" onclick="askQuestion('<?php echo htmlspecialchars($topic, ENT_QUOTES); ?>')">
                        <?php echo htmlspecialchars($topic); ?>
                    </div>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </div>

        <!-- Chat interface -->
        <div class="chat-container">
            <div class="chat-messages" id="chatMessages">
                <div class="welcome-message">
                    <i class="bi bi-chat-dots display-1 mb-3"></i>
                    <h3>Welcome to the Knowledge Assistant</h3>
                    <p>Ask me anything about using the platform, or select a topic from the sidebar to get started.</p>
                </div>
            </div>

            <div class="chat-input-container">
                <form id="chatForm" class="chat-input-wrapper">
                    <textarea 
                        id="chatInput" 
                        class="chat-input form-control" 
                        placeholder="Type your question here..."
                        rows="1"
                        maxlength="1000"
                    ></textarea>
                    <button type="submit" class="chat-submit" id="submitButton">
                        <i class="bi bi-send"></i> Send
                    </button>
                </form>
            </div>
        </div>
    </div>
</main>

<script>
// Auto-resize textarea
const chatInput = document.getElementById('chatInput');
const chatMessages = document.getElementById('chatMessages');
const chatForm = document.getElementById('chatForm');
const submitButton = document.getElementById('submitButton');

chatInput.addEventListener('input', function() {
    this.style.height = 'auto';
    this.style.height = (this.scrollHeight) + 'px';
});

// Handle Enter key (submit on Enter, new line on Shift+Enter)
chatInput.addEventListener('keydown', function(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        chatForm.dispatchEvent(new Event('submit'));
    }
});

// Ask a question from the sidebar
function askQuestion(question) {
    chatInput.value = question;
    chatInput.style.height = 'auto';
    chatInput.style.height = (chatInput.scrollHeight) + 'px';
    chatInput.focus();
}

// Handle form submission
chatForm.addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const question = chatInput.value.trim();
    if (!question) return;
    
    // Clear welcome message if it exists
    const welcomeMsg = document.querySelector('.welcome-message');
    if (welcomeMsg) {
        welcomeMsg.remove();
    }
    
    // Add user message
    addMessage('user', question);
    
    // Clear input and disable submit
    chatInput.value = '';
    chatInput.style.height = 'auto';
    submitButton.disabled = true;
    
    // Show typing indicator
    const typingId = addTypingIndicator();
    
    // Simulate AI response (replace with actual API call later)
    setTimeout(() => {
        removeTypingIndicator(typingId);
        
        // Generate a helpful response based on the question
        const response = generateResponse(question);
        addMessage('assistant', response);
        
        submitButton.disabled = false;
        chatInput.focus();
    }, 1000);
});

// Add a message to the chat
function addMessage(type, content) {
    const messageDiv = document.createElement('div');
    messageDiv.className = `message message-${type}`;
    
    const avatar = document.createElement('div');
    avatar.className = 'message-avatar';
    avatar.innerHTML = type === 'user' ? '<i class="bi bi-person"></i>' : '<i class="bi bi-robot"></i>';
    
    const contentDiv = document.createElement('div');
    contentDiv.className = 'message-content';
    contentDiv.textContent = content;
    
    if (type === 'user') {
        messageDiv.appendChild(contentDiv);
        messageDiv.appendChild(avatar);
    } else {
        messageDiv.appendChild(avatar);
        messageDiv.appendChild(contentDiv);
    }
    
    chatMessages.appendChild(messageDiv);
    chatMessages.scrollTop = chatMessages.scrollHeight;
}

// Add typing indicator
function addTypingIndicator() {
    const indicatorId = 'typing-' + Date.now();
    const messageDiv = document.createElement('div');
    messageDiv.className = 'message message-assistant';
    messageDiv.id = indicatorId;
    
    const avatar = document.createElement('div');
    avatar.className = 'message-avatar';
    avatar.innerHTML = '<i class="bi bi-robot"></i>';
    
    const contentDiv = document.createElement('div');
    contentDiv.className = 'message-content';
    contentDiv.innerHTML = '<div class="typing-indicator"><span></span><span></span><span></span></div>';
    
    messageDiv.appendChild(avatar);
    messageDiv.appendChild(contentDiv);
    
    chatMessages.appendChild(messageDiv);
    chatMessages.scrollTop = chatMessages.scrollHeight;
    
    return indicatorId;
}

// Remove typing indicator
function removeTypingIndicator(id) {
    const indicator = document.getElementById(id);
    if (indicator) {
        indicator.remove();
    }
}

// Generate a response based on the question (placeholder logic)
function generateResponse(question) {
    const lowerQuestion = question.toLowerCase();
    
    // Basic response logic - replace with actual AI integration
    if (lowerQuestion.includes('join') && lowerQuestion.includes('project')) {
        return "To join a project, navigate to the Projects page from the main menu. Browse through available projects and click the 'Join Project' button on any project you're interested in. Some projects may require approval from the project manager.";
    } else if (lowerQuestion.includes('role')) {
        return "There are three main roles in the system: Developer (students), Project Manager (team leads), and Administrator (instructors). Each role has different permissions for managing projects, tasks, and community features.";
    } else if (lowerQuestion.includes('profile')) {
        return "To complete your profile, click on your name in the top right corner and select 'Profile'. Fill in your personal information, skills, and location. Don't forget to complete the Skills Survey to get personalized learning recommendations!";
    } else if (lowerQuestion.includes('course') && lowerQuestion.includes('enroll')) {
        return "To enroll in a course, go to the Classroom section from the main menu. Browse available courses and click 'Enroll' on any course you want to take. Once enrolled, you can access lessons, take quizzes, and track your progress.";
    } else if (lowerQuestion.includes('quiz')) {
        return "Quizzes are part of course lessons. When you reach a quiz lesson, click 'Take Quiz' to start. Questions are presented one at a time, and you can review your answers before submitting. You can retake quizzes if allowed by the instructor.";
    } else if (lowerQuestion.includes('learning plan')) {
        return "Your Learning Plan provides personalized recommendations based on your survey responses. It includes recommended projects, courses, and skill assessments. Complete the Skills Survey first to get customized recommendations tailored to your interests and experience level.";
    } else if (lowerQuestion.includes('help')) {
        return "You can find help in several ways: 1) Use this Knowledge Assistant for quick answers, 2) Check the documentation in the About section, 3) Ask questions in community posts, or 4) Contact your instructor or community administrator for specific issues.";
    } else {
        return "I'm here to help you navigate the platform. You can ask me about projects, courses, profiles, community features, or any other aspect of the system. For specific technical issues, please provide more details about what you're trying to accomplish.";
    }
}
</script>

<style>
/* Typing indicator animation */
.typing-indicator {
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

.typing-indicator span {
    display: inline-block;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background-color: #6c757d;
    animation: typing 1.4s infinite;
}

.typing-indicator span:nth-child(2) {
    animation-delay: 0.2s;
}

.typing-indicator span:nth-child(3) {
    animation-delay: 0.4s;
}

@keyframes typing {
    0%, 60%, 100% {
        opacity: 0.3;
        transform: translateY(0);
    }
    30% {
        opacity: 1;
        transform: translateY(-10px);
    }
}
</style>

<?php require_once 'includes/footer.php'; ?>