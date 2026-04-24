<?php
// chat_widget.php - Floating Chat Widget
// Save this file and include it in your pages where you want the chat widget

// Configuration - Set your support agent name and initial message
$config = [
    'support_agent_name' => 'Support Agent',
    'initial_message' => 'Hello! How can I help you today?',
    'company_name' => 'Your Company',
    'widget_position' => 'right', // 'left' or 'right'
    'primary_color' => '#2563eb',
    'offline_message' => 'Sorry, our support team is currently offline. Please leave a message and we\'ll get back to you soon.',
    'is_online' => true, // Set to false to show offline status
];

// Function to output the chat widget as a div
function displayChatWidget($customConfig = []) {
    global $config;
    
    // Merge custom config with default config
    $config = array_merge($config, $customConfig);
    
    // Output the chat widget div directly
    ?>
    <style>
        /* Chat Widget Styles */
        .chat-widget-container {
            position: fixed;
            <?php echo $config['widget_position']; ?>: 20px;
            bottom: 20px;
            z-index: 10000;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
        }

        /* Chat Button - Improved Icon */
        .chat-button {
            width: 60px;
            height: 60px;
            background-color: <?php echo $config['primary_color']; ?>;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            transition: all 0.3s ease;
            color: white;
            margin-bottom: 70px;
            position: relative;
        }

        .chat-button:hover {
            transform: scale(1.05);
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.2);
        }

        .chat-button .chat-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            height: 100%;
        }

        .chat-button .chat-icon svg {
            width: 28px;
            height: 28px;
            stroke-width: 1.5;
        }

        .chat-button .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background-color: #ef4444;
            color: white;
            font-size: 12px;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            display: none;
        }

        /* Chat Window - Original Design */
        .chat-window {
            width: 350px;
            height: 500px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
            display: none;
            flex-direction: column;
            overflow: hidden;
            margin-bottom: 15px;
        }

        .chat-window.active {
            display: flex;
        }

        /* Chat Header */
        .chat-header {
            background: <?php echo $config['primary_color']; ?>;
            color: white;
            padding: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .chat-header .agent-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .chat-header .agent-avatar {
            width: 32px;
            height: 32px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .agent-status {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 12px;
        }

        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background-color: <?php echo $config['is_online'] ? '#10b981' : '#9ca3af'; ?>;
        }

        .close-chat {
            background: none;
            border: none;
            color: white;
            font-size: 20px;
            cursor: pointer;
            padding: 5px;
        }

        /* Chat Messages Area */
        .chat-messages {
            flex: 1;
            padding: 15px;
            overflow-y: auto;
            background: #f9fafb;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .message {
            max-width: 80%;
            padding: 10px 15px;
            border-radius: 18px;
            position: relative;
            word-wrap: break-word;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .message.user {
            background: <?php echo $config['primary_color']; ?>;
            color: white;
            align-self: flex-end;
            border-bottom-right-radius: 5px;
        }

        .message.support {
            background: #e5e7eb;
            color: #111827;
            align-self: flex-start;
            border-bottom-left-radius: 5px;
        }

        .message-time {
            font-size: 11px;
            margin-top: 5px;
            opacity: 0.7;
            text-align: right;
        }

        /* Chat Input */
        .chat-input-area {
            padding: 15px;
            border-top: 1px solid #e5e7eb;
            background: white;
            display: flex;
            gap: 10px;
        }

        .chat-input {
            flex: 1;
            padding: 12px 15px;
            border: 1px solid #d1d5db;
            border-radius: 24px;
            font-size: 14px;
            outline: none;
            transition: border-color 0.3s;
        }

        .chat-input:focus {
            border-color: <?php echo $config['primary_color']; ?>;
        }

        .send-button {
            background: <?php echo $config['primary_color']; ?>;
            color: white;
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.3s;
        }

        .send-button:hover {
            background: #1d4ed8;
        }

        .send-button:disabled {
            background: #9ca3af;
            cursor: not-allowed;
        }

        /* Typing Indicator */
        .typing-indicator {
            display: none;
            align-self: flex-start;
            background: #e5e7eb;
            padding: 10px 15px;
            border-radius: 18px;
            border-bottom-left-radius: 5px;
            margin-bottom: 5px;
        }

        .typing-indicator span {
            height: 8px;
            width: 8px;
            background: #6b7280;
            border-radius: 50%;
            display: inline-block;
            margin: 0 1px;
            animation: typing 1.4s infinite;
        }

        .typing-indicator span:nth-child(1) { animation-delay: 0s; }
        .typing-indicator span:nth-child(2) { animation-delay: 0.2s; }
        .typing-indicator span:nth-child(3) { animation-delay: 0.4s; }

        @keyframes typing {
            0%, 60%, 100% { transform: translateY(0); }
            30% { transform: translateY(-5px); }
        }

        /* Welcome Message */
        .welcome-message {
            text-align: center;
            padding: 20px;
            color: #6b7280;
            font-size: 14px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .chat-widget-container {
                <?php echo $config['widget_position']; ?>: 10px;
                bottom: 10px;
            }
            
            .chat-window {
                width: 90vw;
                height: 70vh;
                max-width: 350px;
            }
        }
    </style>

    <div class="chat-widget-container">
        <div class="chat-window" id="chatWindow">
            <div class="chat-header">
                <div class="agent-info">
                    <div class="agent-avatar">
                        <span></span>
                    </div>
                    <div>
                        <strong><?php echo htmlspecialchars($config['support_agent_name']); ?></strong>
                        <div class="agent-status">
                            <span class="status-dot"></span>
                            <span><?php echo $config['is_online'] ? 'Online' : 'Offline'; ?></span>
                        </div>
                    </div>
                </div>
                <button class="close-chat" id="closeChat">&times;</button>
            </div>
            
            <div class="chat-messages" id="chatMessages">
                <div class="welcome-message">
                    <p>Welcome to <?php echo htmlspecialchars($config['company_name']); ?> support!</p>
                    <?php if (!$config['is_online']): ?>
                        <p><small><?php echo htmlspecialchars($config['offline_message']); ?></small></p>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="chat-input-area">
                <input type="text" 
                       class="chat-input" 
                       id="chatInput" 
                       placeholder="Type your message..."
                       <?php echo !$config['is_online'] ? 'disabled placeholder="Support is offline"' : ''; ?>>
                <button class="send-button" id="sendButton" <?php echo !$config['is_online'] ? 'disabled' : ''; ?>>
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="22" y1="2" x2="11" y2="13"></line>
                        <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                    </svg>
                </button>
            </div>
        </div>
        
        <div class="chat-button" id="chatButton">
            <div class="chat-icon">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                </svg>
            </div>
            <div class="notification-badge" id="notificationBadge">1</div>
        </div>
    </div>

    <script>
        // Chat Widget JavaScript
        document.addEventListener('DOMContentLoaded', function() {
            // Elements
            const chatButton = document.getElementById('chatButton');
            const chatWindow = document.getElementById('chatWindow');
            const closeChat = document.getElementById('closeChat');
            const chatInput = document.getElementById('chatInput');
            const sendButton = document.getElementById('sendButton');
            const chatMessages = document.getElementById('chatMessages');
            const notificationBadge = document.getElementById('notificationBadge');
            
            // Configuration
            const config = <?php echo json_encode($config); ?>;
            let isChatOpen = false;
            let unreadMessages = 0;
            let chatHistory = [];
            
            // Load chat history from localStorage
            loadChatHistory();
            
            // Add initial support message if chat is empty
            if (chatHistory.length === 0 && config.is_online) {
                addMessage('support', config.initial_message);
            } else if (chatHistory.length === 0 && !config.is_online) {
                addMessage('support', config.offline_message);
            }
            
            // Event Listeners
            chatButton.addEventListener('click', toggleChat);
            closeChat.addEventListener('click', closeChatWindow);
            sendButton.addEventListener('click', sendMessage);
            chatInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    sendMessage();
                }
            });
            
            // Toggle chat window
            function toggleChat() {
                isChatOpen = !isChatOpen;
                if (isChatOpen) {
                    chatWindow.classList.add('active');
                    chatInput.focus();
                    resetNotification();
                } else {
                    chatWindow.classList.remove('active');
                }
            }
            
            // Close chat window
            function closeChatWindow() {
                chatWindow.classList.remove('active');
                isChatOpen = false;
            }
            
            // Send message
            function sendMessage() {
                const message = chatInput.value.trim();
                if (!message) return;
                
                // Add user message
                addMessage('user', message);
                chatInput.value = '';
                
                // If support is offline, just save the message
                if (!config.is_online) {
                    saveChatHistory();
                    return;
                }
                
                // Show typing indicator
                showTypingIndicator();
                
                // Simulate support response after delay
                setTimeout(() => {
                    removeTypingIndicator();
                    const response = generateSupportResponse(message);
                    addMessage('support', response);
                    saveChatHistory();
                    
                    // Show notification if chat is closed
                    if (!isChatOpen) {
                        showNotification();
                    }
                }, 1000 + Math.random() * 2000); // Random delay 1-3 seconds
            }
            
            // Add message to chat
            function addMessage(sender, text) {
                const messageDiv = document.createElement('div');
                messageDiv.className = `message ${sender}`;
                
                const time = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                
                messageDiv.innerHTML = `
                    <div class="message-text">${escapeHtml(text)}</div>
                    <div class="message-time">${time}</div>
                `;
                
                chatMessages.appendChild(messageDiv);
                scrollToBottom();
                
                // Save to history
                chatHistory.push({
                    sender: sender,
                    text: text,
                    time: new Date().toISOString()
                });
                
                // Auto-save after adding message
                saveChatHistory();
            }
            
            // Show typing indicator
            function showTypingIndicator() {
                const typingDiv = document.createElement('div');
                typingDiv.className = 'typing-indicator';
                typingDiv.id = 'typingIndicator';
                typingDiv.innerHTML = '<span></span><span></span><span></span>';
                chatMessages.appendChild(typingDiv);
                scrollToBottom();
            }
            
            // Remove typing indicator
            function removeTypingIndicator() {
                const typingIndicator = document.getElementById('typingIndicator');
                if (typingIndicator) {
                    typingIndicator.remove();
                }
            }
            
            // Generate simulated support response
            function generateSupportResponse(userMessage) {
                const responses = [
                    "I understand. Could you please provide more details about your issue?",
                    "Thank you for sharing that. Let me check how we can help you with that.",
                    "I'll need to look into this. Can you tell me when this issue started?",
                    "Thanks for reaching out. Our team is working on similar issues and we'll update you soon.",
                    "I can help with that. Have you tried restarting the application?",
                    "Let me guide you through the steps to resolve this issue.",
                    "I appreciate you bringing this to our attention. We'll investigate it right away.",
                    "For security purposes, could you verify your account information?",
                    "This is a known issue and our developers are working on a fix. Expected resolution time is 24-48 hours.",
                    "Can you share any error messages you're seeing? That would help me assist you better."
                ];
                
                // Simple keyword-based response selection
                const lowerMessage = userMessage.toLowerCase();
                
                if (lowerMessage.includes('error') || lowerMessage.includes('not working')) {
                    return "I'm sorry you're experiencing this issue. Could you share the specific error message you're seeing?";
                } else if (lowerMessage.includes('password') || lowerMessage.includes('login')) {
                    return "For account security, please use the password reset feature on our login page. If you continue to have issues, let me know.";
                } else if (lowerMessage.includes('refund') || lowerMessage.includes('money')) {
                    return "I understand you have questions about a refund. Let me connect you with our billing department for assistance.";
                } else if (lowerMessage.includes('urgent') || lowerMessage.includes('emergency')) {
                    return "I understand this is urgent. Let me escalate this to our priority support team.";
                } else if (lowerMessage.includes('thank') || lowerMessage.includes('thanks')) {
                    return "You're welcome! Is there anything else I can help you with today?";
                }
                
                // Random response if no keywords match
                return responses[Math.floor(Math.random() * responses.length)];
            }
            
            // Show notification badge
            function showNotification() {
                unreadMessages++;
                notificationBadge.textContent = unreadMessages;
                notificationBadge.style.display = 'flex';
            }
            
            // Reset notification badge
            function resetNotification() {
                unreadMessages = 0;
                notificationBadge.style.display = 'none';
            }
            
            // Scroll to bottom of chat
            function scrollToBottom() {
                chatMessages.scrollTop = chatMessages.scrollHeight;
            }
            
            // Save chat history to localStorage
            function saveChatHistory() {
                try {
                    localStorage.setItem('chatSupportHistory', JSON.stringify(chatHistory));
                } catch (e) {
                    console.error('Failed to save chat history:', e);
                }
            }
            
            // Load chat history from localStorage
            function loadChatHistory() {
                try {
                    const savedHistory = localStorage.getItem('chatSupportHistory');
                    if (savedHistory) {
                        chatHistory = JSON.parse(savedHistory);
                        
                        // Render saved messages
                        chatHistory.forEach(msg => {
                            const messageDiv = document.createElement('div');
                            messageDiv.className = `message ${msg.sender}`;
                            
                            const time = new Date(msg.time).toLocaleTimeString([], { 
                                hour: '2-digit', 
                                minute: '2-digit' 
                            });
                            
                            messageDiv.innerHTML = `
                                <div class="message-text">${escapeHtml(msg.text)}</div>
                                <div class="message-time">${time}</div>
                            `;
                            
                            chatMessages.appendChild(messageDiv);
                        });
                        
                        scrollToBottom();
                    }
                } catch (e) {
                    console.error('Failed to load chat history:', e);
                }
            }
            
            // Clear chat history (for testing)
            function clearChatHistory() {
                if (confirm('Clear all chat history?')) {
                    localStorage.removeItem('chatSupportHistory');
                    chatHistory = [];
                    chatMessages.innerHTML = '<div class="welcome-message"><p>Welcome to ' + 
                                            escapeHtml(config.company_name) + ' support!</p></div>';
                    
                    if (config.is_online) {
                        addMessage('support', config.initial_message);
                    } else {
                        addMessage('support', config.offline_message);
                    }
                }
            }
            
            // Expose clear function for testing (optional)
            window.clearChatHistory = clearChatHistory;
            
            // Helper: Escape HTML to prevent XSS
            function escapeHtml(text) {
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }
            
            // Auto-open chat for first-time visitors (optional)
            if (!localStorage.getItem('chatSupportSeen')) {
                setTimeout(() => {
                    if (!isChatOpen) {
                        toggleChat();
                        localStorage.setItem('chatSupportSeen', 'true');
                    }
                }, 3000);
            }
        });
    </script>
    <?php
}

// For AJAX requests, return JSON
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'success', 'message' => 'Chat widget functions available']);
    exit;
}

// To use the chat widget in your page, simply call:
// displayChatWidget();
// Or with custom config:
// displayChatWidget(['support_agent_name' => 'Muhasba Support', 'company_name' => 'Muhasba.com']);
?>