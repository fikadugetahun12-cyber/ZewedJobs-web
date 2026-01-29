// AI Integration Module for Career Assistant
class AICareerAssistant {
    constructor() {
        this.conversationHistory = [];
        this.isTyping = false;
        this.voiceEnabled = false;
        this.currentTheme = 'light';
        this.apiEndpoint = 'https://api.zewed.career/ai/chat';
        this.fallbackResponses = this.getFallbackResponses();
        this.initializeChat();
    }

    initializeChat() {
        // Load saved conversation
        this.loadConversation();
        
        // Load theme preference
        this.loadTheme();
        
        // Initialize event listeners
        this.setupEventListeners();
        
        // Initialize voice recognition if supported
        this.initVoiceRecognition();
        
        console.log('AI Career Assistant initialized');
    }

    setupEventListeners() {
        // Auto-resize textarea
        const textarea = document.getElementById('message-input');
        textarea.addEventListener('input', this.autoResizeTextarea);
        
        // Enter to send, Shift+Enter for new line
        textarea.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                this.sendMessage();
            }
        });
        
        // Load quick question handlers
        document.querySelectorAll('.quick-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const type = e.target.closest('.quick-btn').getAttribute('onclick')?.match(/'([^']+)'/)?.[1];
                if (type) this.askQuestion(type);
            });
        });
    }

    autoResizeTextarea() {
        const textarea = this;
        textarea.style.height = 'auto';
        textarea.style.height = Math.min(textarea.scrollHeight, 120) + 'px';
    }

    async sendMessage() {
        const input = document.getElementById('message-input');
        const message = input.value.trim();
        
        if (!message || this.isTyping) return;
        
        // Add user message to chat
        this.addMessage(message, 'user');
        
        // Clear input
        input.value = '';
        input.style.height = 'auto';
        
        // Show typing indicator
        this.showTypingIndicator();
        
        // Get AI response
        try {
            const response = await this.getAIResponse(message);
            this.addMessage(response, 'bot');
        } catch (error) {
            console.error('Error getting AI response:', error);
            const fallback = this.getFallbackResponse(message);
            this.addMessage(fallback, 'bot');
        }
        
        // Hide typing indicator
        this.hideTypingIndicator();
        
        // Save conversation
        this.saveConversation();
    }

    async getAIResponse(message) {
        // In a real implementation, this would call your AI API
        // For now, we'll use simulated responses
        
        // Add to conversation history
        this.conversationHistory.push({
            role: 'user',
            content: message,
            timestamp: new Date().toISOString()
        });
        
        // Simulate API call delay
        await new Promise(resolve => setTimeout(resolve, 1000));
        
        // Generate AI response based on message content
        const response = this.generateResponse(message);
        
        // Add AI response to history
        this.conversationHistory.push({
            role: 'assistant',
            content: response,
            timestamp: new Date().toISOString()
        });
        
        return response;
    }

    generateResponse(message) {
        const lowerMessage = message.toLowerCase();
        
        // Resume-related queries
        if (lowerMessage.includes('resume') || lowerMessage.includes('cv')) {
            return this.getResumeAdvice(message);
        }
        
        // Interview-related queries
        if (lowerMessage.includes('interview') || lowerMessage.includes('interviewing')) {
            return this.getInterviewAdvice(message);
        }
        
        // Career-related queries
        if (lowerMessage.includes('career') || lowerMessage.includes('job') || lowerMessage.includes('work')) {
            return this.getCareerAdvice(message);
        }
        
        // Skills-related queries
        if (lowerMessage.includes('skill') || lowerMessage.includes('learn') || lowerMessage.includes('develop')) {
            return this.getSkillAdvice(message);
        }
        
        // Default response
        return `I understand you're asking about: "${message}". As your career assistant, I can provide guidance on:\n\n` +
               `1. **Resume Optimization** - Tailoring your resume for specific roles\n` +
               `2. **Interview Preparation** - Common questions and strategies\n` +
               `3. **Career Pathing** - Identifying growth opportunities\n` +
               `4. **Skill Development** - Recommended courses and resources\n\n` +
               `Could you provide more details about what specific aspect you'd like help with?`;
    }

    getResumeAdvice(message) {
        const tips = [
            "**Tailor for Each Job:** Customize your resume for every application by matching keywords from the job description.",
            "**Quantify Achievements:** Use numbers to demonstrate impact (e.g., 'Increased sales by 30%' instead of 'Improved sales').",
            "**Use Action Verbs:** Start bullet points with strong verbs like 'Managed', 'Developed', 'Implemented'.",
            "**Keep it Concise:** Aim for 1-2 pages maximum. Recruiters spend only about 6 seconds on initial review.",
            "**Include Relevant Skills:** List technical skills and soft skills that match the job requirements.",
            "**Professional Format:** Use a clean, readable font (Arial, Calibri, Times New Roman) and consistent formatting.",
            "**Proofread:** Have someone else review for typos and grammatical errors.",
            "**Include Keywords:** Many companies use ATS (Applicant Tracking Systems) that scan for keywords."
        ];
        
        const randomTips = this.shuffleArray(tips).slice(0, 4);
        
        return `Here are some resume tips based on your query:\n\n` +
               `${randomTips.join('\n\n')}\n\n` +
               `**Pro Tip:** Consider creating an "Achievements" section to highlight your most significant contributions. ` +
               `Would you like me to review a specific section of your resume or help with formatting?`;
    }

    getInterviewAdvice(message) {
        const commonQuestions = [
            "**Tell me about yourself.** - Prepare a 2-minute pitch covering your background, key achievements, and why you're interested in this role.",
            "**Why do you want to work here?** - Research the company and connect your skills to their mission and needs.",
            "**What's your greatest weakness?** - Choose a real weakness and explain how you're working to improve it.",
            "**Describe a challenging situation and how you handled it.** - Use the STAR method (Situation, Task, Action, Result).",
            "**Where do you see yourself in 5 years?** - Show ambition while aligning with the company's growth opportunities."
        ];
        
        const strategies = [
            "**Research the Company:** Understand their products, culture, recent news, and competitors.",
            "**Practice Aloud:** Rehearse your answers until they sound natural, not memorized.",
            "**Prepare Questions:** Have 3-5 thoughtful questions ready for the interviewer.",
            "**Dress Appropriately:** When in doubt, it's better to be slightly overdressed.",
            "**Follow Up:** Send thank-you emails within 24 hours, mentioning specific discussion points."
        ];
        
        return `For interview preparation:\n\n` +
               `**Common Questions to Prepare:**\n${commonQuestions.join('\n\n')}\n\n` +
               `**Key Strategies:**\n${strategies.slice(0, 3).join('\n\n')}\n\n` +
               `Would you like to practice a specific interview question or learn more about virtual interview tips?`;
    }

    getCareerAdvice(message) {
        const careerStages = {
            early: "**Early Career:** Focus on skill-building, networking, and exploring different roles. Consider mentorship programs and professional certifications.",
            mid: "**Mid-Career:** Look for leadership opportunities, specialize in a niche, and build your professional brand. Consider lateral moves for broader experience.",
            transition: "**Career Transition:** Identify transferable skills, fill skill gaps through courses, network in the new industry, and consider contract work to gain experience.",
            advancement: "**Advancement:** Seek stretch assignments, develop leadership skills, build strategic relationships, and contribute to high-visibility projects."
        };
        
        return `Based on your career query, here's some general guidance:\n\n` +
               `${careerStages.mid}\n\n` +
               `**Career Development Actions:**\n` +
               `1. **Skill Assessment:** Regularly evaluate your skills against market demands\n` +
               `2. **Networking:** Build relationships both within and outside your organization\n` +
               `3. **Continuous Learning:** Stay updated with industry trends through courses and conferences\n` +
               `4. **Mentorship:** Both seek mentors and mentor others\n` +
               `5. **Visibility:** Contribute to projects that align with your career goals\n\n` +
               `Could you share more about your specific career stage or goals for more personalized advice?`;
    }

    getSkillAdvice(message) {
        const inDemandSkills = {
            technical: [
                "**Data Analysis:** Python, SQL, Excel, Tableau",
                "**Cloud Computing:** AWS, Azure, Google Cloud",
                "**AI/ML:** Python, TensorFlow, PyTorch",
                "**Cybersecurity:** Risk assessment, network security, compliance"
            ],
            soft: [
                "**Communication:** Clear writing and speaking, active listening",
                "**Problem-Solving:** Analytical thinking, creativity",
                "**Adaptability:** Learning agility, resilience",
                "**Leadership:** Team management, strategic thinking"
            ]
        };
        
        return `For skill development:\n\n` +
               `**In-Demand Technical Skills:**\n${inDemandSkills.technical.join('\n')}\n\n` +
               `**Essential Soft Skills:**\n${inDemandSkills.soft.join('\n')}\n\n` +
               `**Development Strategies:**\n` +
               `1. **Online Courses:** Coursera, edX, Udemy, LinkedIn Learning\n` +
               `2. **Certifications:** Industry-recognized credentials in your field\n` +
               `3. **Projects:** Build a portfolio through personal or open-source projects\n` +
               `4. **Networking:** Join professional associations and attend events\n` +
               `5. **Mentorship:** Learn from experienced professionals\n\n` +
               `What specific skills are you looking to develop or what industry are you targeting?`;
    }

    askQuestion(type) {
        const questions = {
            resume: "Can you review my resume for a software engineering position and suggest improvements?",
            interview: "What are the most common behavioral interview questions and how should I answer them?",
            career: "How can I transition from a technical role to a management position?",
            skills: "What are the most in-demand skills for data scientists in 2024?",
            job_search: "What's the most effective job search strategy in today's market?"
        };
        
        const question = questions[type] || "How can I improve my career prospects?";
        
        // Set the question in input
        const input = document.getElementById('message-input');
        input.value = question;
        input.style.height = 'auto';
        input.style.height = Math.min(input.scrollHeight, 120) + 'px';
        input.focus();
    }

    addMessage(content, sender) {
        const messagesContainer = document.getElementById('chat-messages');
        const time = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        
        const messageDiv = document.createElement('div');
        messageDiv.className = `message ${sender}-message`;
        
        const avatarSrc = sender === 'user' 
            ? 'https://ui-avatars.com/api/?name=You&background=10B981&color=fff'
            : 'https://ui-avatars.com/api/?name=AI+Assistant&background=4F46E5&color=fff';
        
        messageDiv.innerHTML = `
            <div class="message-avatar">
                <img src="${avatarSrc}" alt="${sender === 'user' ? 'You' : 'AI Assistant'}">
            </div>
            <div class="message-content">
                <div class="message-header">
                    <span class="sender-name">${sender === 'user' ? 'You' : 'Career Assistant'}</span>
                    <span class="message-time">${time}</span>
                </div>
                <div class="message-text">${this.formatMessage(content)}</div>
            </div>
        `;
        
        messagesContainer.appendChild(messageDiv);
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }

    formatMessage(text) {
        // Convert markdown-like formatting to HTML
        return text
            .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
            .replace(/\n/g, '<br>')
            .replace(/\d+\.\s+(.*?)(?=\n|$)/g, '<li>$1</li>')
            .replace(/<li>/g, '<ul><li>')
            .replace(/<\/li>(?!<li>)/g, '</li></ul>');
    }

    showTypingIndicator() {
        this.isTyping = true;
        const indicator = document.getElementById('typing-indicator');
        indicator.style.display = 'flex';
        
        // Scroll to bottom
        const messagesContainer = document.getElementById('chat-messages');
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }

    hideTypingIndicator() {
        this.isTyping = false;
        const indicator = document.getElementById('typing-indicator');
        indicator.style.display = 'none';
    }

    clearChat() {
        if (confirm('Are you sure you want to clear the chat? This action cannot be undone.')) {
            const messagesContainer = document.getElementById('chat-messages');
            
            // Keep only the initial welcome message
            const welcomeMessage = messagesContainer.querySelector('.bot-message');
            messagesContainer.innerHTML = '';
            
            if (welcomeMessage) {
                messagesContainer.appendChild(welcomeMessage);
            } else {
                // Add default welcome message if not present
                this.addMessage(
                    "Hello! I'm your AI Career Assistant. How can I help you today?",
                    'bot'
                );
            }
            
            this.conversationHistory = [];
            localStorage.removeItem('zewed_chat_history');
        }
    }

    startNewChat() {
        if (this.conversationHistory.length > 0) {
            if (confirm('Start a new chat? Your current conversation will be saved.')) {
                this.saveConversation();
                this.clearChat();
            }
        }
    }

    saveConversation() {
        const chatData = {
            history: this.conversationHistory,
            timestamp: new Date().toISOString(),
            lastMessage: this.conversationHistory[this.conversationHistory.length - 1]?.content || ''
        };
        
        localStorage.setItem('zewed_chat_history', JSON.stringify(chatData));
    }

    loadConversation() {
        try {
            const saved = localStorage.getItem('zewed_chat_history');
            if (saved) {
                const data = JSON.parse(saved);
                this.conversationHistory = data.history || [];
                
                // Optional: Restore conversation UI
                // Note: In a full implementation, you might want to restore the full conversation
            }
        } catch (error) {
            console.error('Error loading conversation:', error);
        }
    }

    downloadChat() {
        const chatContent = this.generateChatTranscript();
        const blob = new Blob([chatContent], { type: 'text/plain' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `zewed_chat_${new Date().toISOString().split('T')[0]}.txt`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
        
        this.showNotification('Chat saved successfully!', 'success');
    }

    generateChatTranscript() {
        let transcript = `Zewed Career Assistant - Chat Transcript\n`;
        transcript += `Date: ${new Date().toLocaleDateString()}\n`;
        transcript += `Time: ${new Date().toLocaleTimeString()}\n`;
        transcript += `========================================\n\n`;
        
        this.conversationHistory.forEach(msg => {
            const time = new Date(msg.timestamp).toLocaleTimeString();
            const sender = msg.role === 'user' ? 'You' : 'Career Assistant';
            transcript += `[${time}] ${sender}:\n`;
            transcript += `${msg.content}\n\n`;
        });
        
        return transcript;
    }

    toggleTheme() {
        this.currentTheme = this.currentTheme === 'light' ? 'dark' : 'light';
        document.body.classList.toggle('dark-theme', this.currentTheme === 'dark');
        
        // Update button icon
        const themeBtn = document.querySelector('.theme-toggle i');
        themeBtn.className = this.currentTheme === 'light' ? 'fas fa-moon' : 'fas fa-sun';
        
        // Save preference
        localStorage.setItem('zewed_theme', this.currentTheme);
    }

    loadTheme() {
        const savedTheme = localStorage.getItem('zewed_theme') || 'light';
        this.currentTheme = savedTheme;
        document.body.classList.toggle('dark-theme', savedTheme === 'dark');
        
        // Update button icon
        const themeBtn = document.querySelector('.theme-toggle i');
        if (themeBtn) {
            themeBtn.className = savedTheme === 'light' ? 'fas fa-moon' : 'fas fa-sun';
        }
    }

    initVoiceRecognition() {
        if ('webkitSpeechRecognition' in window || 'SpeechRecognition' in window) {
            this.voiceEnabled = true;
            console.log('Voice recognition supported');
        } else {
            console.log('Voice recognition not supported in this browser');
        }
    }

    toggleVoiceInput() {
        this.showNotification('Voice input coming soon!', 'info');
    }

    attachFile() {
        this.showNotification('File attachment coming soon!', 'info');
    }

    showNotification(message, type = 'info') {
        // Create notification element
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.innerHTML = `
            <i class="fas fa-${type === 'success' ? 'check-circle' : 'info-circle'}"></i>
            <span>${message}</span>
        `;
        
        // Add styles if not already present
        if (!document.querySelector('#notification-styles')) {
            const styles = document.createElement('style');
            styles.id = 'notification-styles';
            styles.textContent = `
                .notification {
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    padding: 15px 20px;
                    border-radius: 8px;
                    background: white;
                    color: var(--dark-color);
                    box-shadow: var(--box-shadow-lg);
                    display: flex;
                    align-items: center;
                    gap: 10px;
                    z-index: 10000;
                    animation: slideIn 0.3s ease-out;
                }
                body.dark-theme .notification {
                    background: #374151;
                    color: white;
                }
                .notification-success {
                    border-left: 4px solid var(--success-color);
                }
                .notification-info {
                    border-left: 4px solid var(--primary-color);
                }
                @keyframes slideIn {
                    from { transform: translateX(100%); opacity: 0; }
                    to { transform: translateX(0); opacity: 1; }
                }
            `;
            document.head.appendChild(styles);
        }
        
        document.body.appendChild(notification);
        
        // Remove after 3 seconds
        setTimeout(() => {
            notification.style.animation = 'slideOut 0.3s ease-out';
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    }

    getFallbackResponses() {
        return [
            "I understand you're asking about career development. Could you provide more specific details about what you're looking for?",
            "That's an interesting question about career growth. Could you clarify which aspect you'd like to focus on?",
            "I'd be happy to help with that career question. Could you tell me more about your current situation?",
            "For personalized career advice, it would help to know more about your background and goals.",
            "I specialize in resume optimization, interview preparation, and career strategy. Which area would you like to explore?"
        ];
    }

    getFallbackResponse(message) {
        const responses = this.fallbackResponses;
        return responses[Math.floor(Math.random() * responses.length)];
    }

    shuffleArray(array) {
        const shuffled = [...array];
        for (let i = shuffled.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));
            [shuffled[i], shuffled[j]] = [shuffled[j], shuffled[i]];
        }
        return shuffled;
    }
}

// Initialize AI Assistant
let aiAssistant;

document.addEventListener('DOMContentLoaded', () => {
    aiAssistant = new AICareerAssistant();
});
