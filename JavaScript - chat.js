// Chat UI Management Module
class ChatUI {
    constructor() {
        this.aiAssistant = null;
        this.initializeUI();
    }

    initializeUI() {
        // Initialize mobile menu
        this.initMobileMenu();
        
        // Initialize chat functionality
        this.initChat();
        
        // Add event listeners for UI interactions
        this.addEventListeners();
        
        console.log('Chat UI initialized');
    }

    initMobileMenu() {
        const menuBtn = document.querySelector('.mobile-menu-btn');
        if (menuBtn) {
            menuBtn.addEventListener('click', () => {
                const navMenu = document.querySelector('.nav-menu');
                const navActions = document.querySelector('.nav-actions');
                
                // Toggle mobile menu
                const isExpanded = navMenu.style.display === 'flex';
                navMenu.style.display = isExpanded ? 'none' : 'flex';
                navActions.style.display = isExpanded ? 'none' : 'flex';
                
                if (!isExpanded) {
                    navMenu.style.flexDirection = 'column';
                    navMenu.style.position = 'absolute';
                    navMenu.style.top = '100%';
                    navMenu.style.left = '0';
                    navMenu.style.right = '0';
                    navMenu.style.background = 'white';
                    navMenu.style.padding = '20px';
                    navMenu.style.boxShadow = 'var(--box-shadow)';
                    navMenu.style.gap = '15px';
                    
                    navActions.style.flexDirection = 'column';
                    navActions.style.position = 'absolute';
                    navActions.style.top = 'calc(100% + 200px)';
                    navActions.style.left = '0';
                    navActions.style.right = '0';
                    navActions.style.background = 'white';
                    navActions.style.padding = '20px';
                    navActions.style.boxShadow = 'var(--box-shadow)';
                    navActions.style.gap = '10px';
                }
            });
        }
    }

    initChat() {
        // Auto-focus input
        const messageInput = document.getElementById('message-input');
        if (messageInput) {
            setTimeout(() => {
                messageInput.focus();
            }, 500);
        }
    }

    addEventListeners() {
        // Send button click
        const sendBtn = document.querySelector('.send-btn');
        if (sendBtn) {
            sendBtn.addEventListener('click', () => {
                if (window.aiAssistant) {
                    window.aiAssistant.sendMessage();
                }
            });
        }
        
        // Clear chat button
        const clearBtn = document.querySelector('[onclick="clearChat()"]');
        if (clearBtn) {
            clearBtn.addEventListener('click', () => {
                if (window.aiAssistant) {
                    window.aiAssistant.clearChat();
                }
            });
        }
        
        // New chat button
        const newChatBtn = document.querySelector('[onclick="startNewChat()"]');
        if (newChatBtn) {
            newChatBtn.addEventListener('click', () => {
                if (window.aiAssistant) {
                    window.aiAssistant.startNewChat();
                }
            });
        }
        
        // Download chat button
        const downloadBtn = document.querySelector('[onclick="downloadChat()"]');
        if (downloadBtn) {
            downloadBtn.addEventListener('click', () => {
                if (window.aiAssistant) {
                    window.aiAssistant.downloadChat();
                }
            });
        }
        
        // Theme toggle button
        const themeBtn = document.querySelector('.theme-toggle');
        if (themeBtn) {
            themeBtn.addEventListener('click', () => {
                if (window.aiAssistant) {
                    window.aiAssistant.toggleTheme();
                }
            });
        }
        
        // Quick question buttons
        document.querySelectorAll('.quick-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const type = btn.getAttribute('onclick')?.match(/'([^']+)'/)?.[1];
                if (type && window.aiAssistant) {
                    window.aiAssistant.askQuestion(type);
                }
            });
        });
    }
}

// Global functions for HTML onclick handlers
function sendMessage() {
    if (window.aiAssistant) {
        window.aiAssistant.sendMessage();
    }
}

function askQuestion(type) {
    if (window.aiAssistant) {
        window.aiAssistant.askQuestion(type);
    }
}

function clearChat() {
    if (window.aiAssistant) {
        window.aiAssistant.clearChat();
    }
}

function startNewChat() {
    if (window.aiAssistant) {
        window.aiAssistant.startNewChat();
    }
}

function downloadChat() {
    if (window.aiAssistant) {
        window.aiAssistant.downloadChat();
    }
}

function toggleTheme() {
    if (window.aiAssistant) {
        window.aiAssistant.toggleTheme();
    }
}

function attachFile() {
    if (window.aiAssistant) {
        window.aiAssistant.attachFile();
    }
}

function toggleVoiceInput() {
    if (window.aiAssistant) {
        window.aiAssistant.toggleVoiceInput();
    }
}

function handleKeyPress(event) {
    if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        sendMessage();
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    // Initialize AI Assistant (from ai-integration.js)
    window.aiAssistant = new AICareerAssistant();
    
    // Initialize Chat UI
    window.chatUI = new ChatUI();
});

// Make functions globally available
window.sendMessage = sendMessage;
window.askQuestion = askQuestion;
window.clearChat = clearChat;
window.startNewChat = startNewChat;
window.downloadChat = downloadChat;
window.toggleTheme = toggleTheme;
window.attachFile = attachFile;
window.toggleVoiceInput = toggleVoiceInput;
window.handleKeyPress = handleKeyPress;
