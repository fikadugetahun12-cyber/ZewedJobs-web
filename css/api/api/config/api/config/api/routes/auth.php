<?php
/**
 * Chat Routes
 */

function handleChatRoutes($method, $action, $id, $data, $auth, $response) {
    // Check authentication
    if (!$auth->isAuthenticated()) {
        $response->error('Authentication required', 401);
        return;
    }
    
    $userId = $auth->getUserId();
    
    switch ($method) {
        case 'POST':
            switch ($action) {
                case 'send':
                    sendChatMessage($userId, $data, $response);
                    break;
                    
                case 'analyze-resume':
                    analyzeResume($userId, $data, $response);
                    break;
                    
                case 'simulate-interview':
                    simulateInterview($userId, $data, $response);
                    break;
                    
                default:
                    $response->error('Invalid chat action', 400);
            }
            break;
            
        case 'GET':
            switch ($action) {
                case 'history':
                    getChatHistory($userId, $response);
                    break;
                    
                case 'conversations':
                    getConversations($userId, $response);
                    break;
                    
                case 'conversation':
                    getConversation($userId, $id, $response);
                    break;
                    
                case 'templates':
                    getChatTemplates($response);
                    break;
                    
                default:
                    $response->error('Invalid chat action', 400);
            }
            break;
            
        case 'PUT':
            switch ($action) {
                case 'conversation':
                    updateConversation($userId, $id, $data, $response);
                    break;
                    
                default:
                    $response->error('Invalid chat action', 400);
            }
            break;
            
        case 'DELETE':
            switch ($action) {
                case 'conversation':
                    deleteConversation($userId, $id, $response);
                    break;
                    
                case 'message':
                    deleteMessage($userId, $id, $response);
                    break;
                    
                default:
                    $response->error('Invalid chat action', 400);
            }
            break;
            
        default:
            $response->error('Method not allowed', 405);
    }
}

function sendChatMessage($userId, $data, $response) {
    global $db;
    
    // Validate input
    $validation = new Validation();
    $validation->validate($data, [
        'message' => 'required|min:1|max:2000',
        'conversation_id' => 'sometimes|integer',
        'type' => 'sometimes|in:text,resume,interview'
    ]);
    
    if ($validation->fails()) {
        $response->error($validation->errors(), 400);
        return;
    }
    
    // Check rate limiting
    if (!checkRateLimit($userId, 'chat')) {
        $response->error('Rate limit exceeded. Please wait a moment.', 429);
        return;
    }
    
    $conversationId = $data['conversation_id'] ?? null;
    
    // Create new conversation if needed
    if (!$conversationId) {
        $conversation = $db->insert('conversations', [
            'user_id' => $userId,
            'title' => substr($data['message'], 0, 50) . '...',
            'type' => $data['type'] ?? 'text',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);
        
        $conversationId = $conversation['insert_id'];
    }
    
    // Save user message
    $messageId = $db->insert('chat_messages', [
        'conversation_id' => $conversationId,
        'user_id' => $userId,
        'role' => 'user',
        'content' => $data['message'],
        'created_at' => date('Y-m-d H:i:s')
    ]);
    
    // Generate AI response
    $aiResponse = generateAIResponse($data['message'], $data['type'] ?? 'text', $userId);
    
    // Save AI response
    $db->insert('chat_messages', [
        'conversation_id' => $conversationId,
        'user_id' => $userId,
        'role' => 'assistant',
        'content' => $aiResponse,
        'created_at' => date('Y-m-d H:i:s')
    ]);
    
    // Update conversation timestamp
    $db->update('conversations', [
        'updated_at' => date('Y-m-d H:i:s')
    ], 'id = ?', [$conversationId]);
    
    $response->success([
        'conversation_id' => $conversationId,
        'response' => $aiResponse,
        'message_id' => $messageId['insert_id']
    ]);
}

function getChatHistory($userId, $response) {
    global $db;
    
    $page = $_GET['page'] ?? 1;
    $limit = $_GET['limit'] ?? 20;
    $offset = ($page - 1) * $limit;
    
    // Get conversations with latest message
    $conversations = $db->query("
        SELECT c.*, 
               (SELECT content FROM chat_messages 
                WHERE conversation_id = c.id 
                ORDER BY created_at DESC LIMIT 1) as last_message,
               (SELECT created_at FROM chat_messages 
                WHERE conversation_id = c.id 
                ORDER BY created_at DESC LIMIT 1) as last_message_time
        FROM conversations c
        WHERE c.user_id = ?
        ORDER BY c.updated_at DESC
        LIMIT ? OFFSET ?
    ", [$userId, $limit, $offset]);
    
    // Get total count
    $total = $db->query("SELECT COUNT(*) as total FROM conversations WHERE user_id = ?", [$userId]);
    $total = $total[0]['total'] ?? 0;
    
    $response->success([
        'conversations' => $conversations,
        'pagination' => [
            'page' => (int)$page,
            'limit' => (int)$limit,
            'total' => (int)$total,
            'pages' => ceil($total / $limit)
        ]
    ]);
}

function getConversation($userId, $conversationId, $response) {
    global $db;
    
    // Verify conversation belongs to user
    $conversation = $db->select('conversations', '*', 'id = ? AND user_id = ?', [$conversationId, $userId], 1);
    
    if (empty($conversation)) {
        $response->error('Conversation not found', 404);
        return;
    }
    
    $conversation = $conversation[0];
    
    // Get messages
    $messages = $db->select('chat_messages', '*', 'conversation_id = ?', [$conversationId], 100, 'created_at ASC');
    
    $response->success([
        'conversation' => $conversation,
        'messages' => $messages
    ]);
}

function analyzeResume($userId, $data, $response) {
    global $db;
    
    // Check if file was uploaded
    if (!isset($_FILES['resume'])) {
        $response->error('No file uploaded', 400);
        return;
    }
    
    $file = $_FILES['resume'];
    
    // Validate file
    $maxSize = MAX_FILE_SIZE;
    $allowedTypes = ALLOWED_FILE_TYPES;
    $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if ($file['error'] !== UPLOAD_OK) {
        $response->error('File upload error', 400);
        return;
    }
    
    if ($file['size'] > $maxSize) {
        $response->error('File too large. Maximum size: ' . ($maxSize / 1024 / 1024) . 'MB', 400);
        return;
    }
    
    if (!in_array($fileExtension, $allowedTypes)) {
        $response->error('Invalid file type. Allowed: ' . implode(', ', $allowedTypes), 400);
        return;
    }
    
    // Create upload directory if it doesn't exist
    $uploadDir = UPLOAD_PATH . 'resumes/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    // Generate unique filename
    $filename = 'resume_' . $userId . '_' . time() . '.' . $fileExtension;
    $filepath = $uploadDir . $filename;
    
    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        $response->error('Failed to save file', 500);
        return;
    }
    
    // Extract text from file (simplified - use libraries for actual extraction)
    $fileContent = '';
    
    if ($fileExtension === 'txt') {
        $fileContent = file_get_contents($filepath);
    } elseif (in_array($fileExtension, ['pdf', 'doc', 'docx'])) {
        // Use a library like phpword/phpword or similar for extraction
        $fileContent = "Resume content extracted from " . $file['name'];
    }
    
    // Create conversation for resume analysis
    $conversation = $db->insert('conversations', [
        'user_id' => $userId,
        'title' => 'Resume Analysis: ' . $file['name'],
        'type' => 'resume',
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ]);
    
    $conversationId = $conversation['insert_id'];
    
    // Save file info
    $db->insert('user_files', [
        'user_id' => $userId,
        'conversation_id' => $conversationId,
        'filename' => $filename,
        'original_name' => $file['name'],
        'filepath' => $filepath,
        'filetype' => $fileExtension,
        'filesize' => $file['size'],
        'created_at' => date('Y-m-d H:i:s')
    ]);
    
    // Generate analysis
    $analysis = analyzeResumeContent($fileContent);
    
    // Save AI response
    $db->insert('chat_messages', [
        'conversation_id' => $conversationId,
        'user_id' => $userId,
        'role' => 'assistant',
        'content' => $analysis,
        'created_at' => date('Y-m-d H:i:s')
    ]);
    
    $response->success([
        'conversation_id' => $conversationId,
        'analysis' => $analysis,
        'filename' => $file['name']
    ]);
}

function simulateInterview($userId, $data, $response) {
    global $db;
    
    $validation = new Validation();
    $validation->validate($data, [
        'job_title' => 'required|min:2|max:100',
        'industry' => 'required|min:2|max:100',
        'experience_level' => 'required|in:entry,mid,senior,executive'
    ]);
    
    if ($validation->fails()) {
        $response->error($validation->errors(), 400);
        return;
    }
    
    // Create interview conversation
    $conversation = $db->insert('conversations', [
        'user_id' => $userId,
        'title' => 'Interview Practice: ' . $data['job_title'],
        'type' => 'interview',
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ]);
    
    $conversationId = $conversation['insert_id'];
    
    // Generate first interview question
    $question = generateInterviewQuestion($data['job_title'], $data['industry'], $data['experience_level']);
    
    // Save AI question
    $db->insert('chat_messages', [
        'conversation_id' => $conversationId,
        'user_id' => $userId,
        'role' => 'assistant',
        'content' => $question,
        'created_at' => date('Y-m-d H:i:s')
    ]);
    
    $response->success([
        'conversation_id' => $conversationId,
        'question' => $question,
        'instruction' => 'Answer this question to continue the interview simulation.'
    ]);
}

function getConversations($userId, $response) {
    global $db;
    
    $type = $_GET['type'] ?? null;
    $limit = $_GET['limit'] ?? 50;
    
    $where = 'user_id = ?';
    $params = [$userId];
    
    if ($type) {
        $where .= ' AND type = ?';
        $params[] = $type;
    }
    
    $conversations = $db->select('conversations', '*', $where, $params, $limit, 'updated_at DESC');
    
    $response->success(['conversations' => $conversations]);
}

function getChatTemplates($response) {
    $templates = [
        'resume' => [
            [
                'title' => 'Review my resume',
                'prompt' => 'Can you review my resume and suggest improvements for a software engineering position?'
            ],
            [
                'title' => 'ATS optimization',
                'prompt' => 'How can I optimize my resume for Applicant Tracking Systems?'
            ],
            [
                'title' => 'Career change resume',
                'prompt' => 'I\'m transitioning from marketing to data science. How should I update my resume?'
            ]
        ],
        'interview' => [
            [
                'title' => 'Behavioral questions',
                'prompt' => 'What are common behavioral interview questions for project managers?'
            ],
            [
                'title' => 'Technical interview',
                'prompt' => 'Help me prepare for a technical interview for a full-stack developer role.'
            ],
            [
                'title' => 'Salary negotiation',
                'prompt' => 'How should I negotiate salary in a job interview?'
            ]
        ],
        'career' => [
            [
                'title' => 'Career path',
                'prompt' => 'What career paths are available for someone with my skills in digital marketing?'
            ],
            [
                'title' => 'Skill development',
                'prompt' => 'What skills should I learn to advance my career in UX design?'
            ],
            [
                'title' => 'Networking',
                'prompt' => 'How can I effectively network in the tech industry?'
            ]
        ]
    ];
    
    $response->success(['templates' => $templates]);
}

function updateConversation($userId, $conversationId, $data, $response) {
    global $db;
    
    // Verify ownership
    $conversation = $db->select('conversations', 'id', 'id = ? AND user_id = ?', [$conversationId, $userId], 1);
    
    if (empty($conversation)) {
        $response->error('Conversation not found', 404);
        return;
    }
    
    $validation = new Validation();
    $validation->validate($data, [
        'title' => 'sometimes|min:1|max:200'
    ]);
    
    if ($validation->fails()) {
        $response->error($validation->errors(), 400);
        return;
    }
    
    $db->update('conversations', [
        'title' => $data['title'] ?? null,
        'updated_at' => date('Y-m-d H:i:s')
    ], 'id = ?', [$conversationId]);
    
    $response->success(['message' => 'Conversation updated']);
}

function deleteConversation($userId, $conversationId, $response) {
    global $db;
    
    // Verify ownership
    $conversation = $db->select('conversations', 'id', 'id = ? AND user_id = ?', [$conversationId, $userId], 1);
    
    if (empty($conversation)) {
        $response->error('Conversation not found', 404);
        return;
    }
    
    // Begin transaction
    $db->beginTransaction();
    
    try {
        // Delete messages
        $db->delete('chat_messages', 'conversation_id = ?', [$conversationId]);
        
        // Delete files
        $db->delete('user_files', 'conversation_id = ?', [$conversationId]);
        
        // Delete conversation
        $db->delete('conversations', 'id = ?', [$conversationId]);
        
        $db->commit();
        
        $response->success(['message' => 'Conversation deleted']);
    } catch (Exception $e) {
        $db->rollback();
        $response->error('Failed to delete conversation', 500);
    }
}

function deleteMessage($userId, $messageId, $response) {
    global $db;
    
    // Verify ownership through conversation
    $message = $db->query("
        SELECT cm.* FROM chat_messages cm
        JOIN conversations c ON cm.conversation_id = c.id
        WHERE cm.id = ? AND c.user_id = ?
    ", [$messageId, $userId]);
    
    if (empty($message)) {
        $response->error('Message not found', 404);
        return;
    }
    
    $db->delete('chat_messages', 'id = ?', [$messageId]);
    
    $response->success(['message' => 'Message deleted']);
}

// AI Response Generation Functions
function generateAIResponse($message, $type, $userId) {
    // This is a simplified version. In production, integrate with AI APIs
    
    $lowerMessage = strtolower($message);
    
    // Simple rule-based responses for demonstration
    if (strpos($lowerMessage, 'resume') !== false) {
        return getResumeAdvice($message);
    } elseif (strpos($lowerMessage, 'interview') !== false) {
        return getInterviewAdvice($message);
    } elseif (strpos($lowerMessage, 'career') !== false || strpos($lowerMessage, 'job') !== false) {
        return getCareerAdvice($message);
    } elseif (strpos($lowerMessage, 'skill') !== false) {
        return getSkillAdvice($message);
    }
    
    // Fallback to OpenAI if available
    if (defined('OPENAI_API_KEY') && OPENAI_API_KEY) {
        return callOpenAI($message, $type);
    }
    
    // Default response
    return "I understand you're asking about: \"$message\". As your AI career assistant, I can help with:\n\n" .
           "1. **Resume Optimization** - Tailoring your resume for specific roles\n" .
           "2. **Interview Preparation** - Common questions and strategies\n" .
           "3. **Career Pathing** - Identifying growth opportunities\n" .
           "4. **Skill Development** - Recommended courses and resources\n\n" .
           "Could you provide more details about what specific aspect you'd like help with?";
}

function callOpenAI($message, $type) {
    $apiKey = OPENAI_API_KEY;
    
    $prompt = "You are an expert career coach and AI assistant. ";
    
    switch ($type) {
        case 'resume':
            $prompt .= "Provide specific, actionable advice for resume improvement.";
            break;
        case 'interview':
            $prompt .= "Provide interview preparation guidance with example questions and answers.";
            break;
        default:
            $prompt .= "Provide helpful career advice.";
    }
    
    $data = [
        'model' => 'gpt-3.5-turbo',
        'messages' => [
            ['role' => 'system', 'content' => $prompt],
            ['role' => 'user', 'content' => $message]
        ],
        'max_tokens' => 1000,
        'temperature' => 0.7
    ];
    
    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ]);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    $result = json_decode($response, true);
    
    return $result['choices'][0]['message']['content'] ?? 'I apologize, but I encountered an error processing your request.';
}

function analyzeResumeContent($content) {
    // Simplified resume analysis
    $analysis = "## Resume Analysis Report\n\n";
    
    // Check for common sections
    $sections = ['experience', 'education', 'skills', 'summary'];
    $foundSections = [];
    
    foreach ($sections as $section) {
        if (stripos($content, $section) !== false) {
            $foundSections[] = ucfirst($section);
        }
    }
    
    if (!empty($foundSections)) {
        $analysis .= "✅ **Good structure**: Found the following sections: " . implode(', ', $foundSections) . "\n\n";
    } else {
        $analysis .= "⚠️ **Structure improvement needed**: Consider adding clear sections (Experience, Education, Skills)\n\n";
    }
    
    // Check for action verbs
    $actionVerbs = ['managed', 'developed', 'created', 'implemented', 'increased', 'reduced', 'improved'];
    $foundVerbs = [];
    
    foreach ($actionVerbs as $verb) {
        if (stripos($content, $verb) !== false) {
            $foundVerbs[] = $verb;
        }
    }
    
    if (count($foundVerbs) >= 3) {
        $analysis .= "✅ **Strong action verbs**: Good use of impactful language\n\n";
    } else {
        $analysis .= "💡 **Tip**: Use more action verbs to describe your achievements\n\n";
    }
    
    // Check for metrics/numbers
    if (preg_match('/\d+%|\$\d+|\d+\s*(years|months)/i', $content)) {
        $analysis .= "✅ **Quantifiable achievements**: Good use of numbers and metrics\n\n";
    } else {
        $analysis .= "💡 **Tip**: Add numbers to quantify your achievements (e.g., 'increased sales by 30%')\n\n";
    }
    
    // Recommendations
    $analysis .= "## Recommendations\n\n";
    $analysis .= "1. **Tailor for each job**: Customize keywords from the job description\n";
    $analysis .= "2. **Keep it concise**: Aim for 1-2 pages maximum\n";
    $analysis .= "3. **Proofread carefully**: Check for typos and grammatical errors\n";
    $analysis .= "4. **Update contact info**: Make sure your email and phone are current\n\n";
    
    $analysis .= "Would you like specific feedback on any particular section of your resume?";
    
    return $analysis;
}

function generateInterviewQuestion($jobTitle, $industry, $experienceLevel) {
    $questions = [
        'entry' => [
            "Tell me about yourself and why you're interested in this role.",
            "What relevant coursework or projects have you completed?",
            "How do you handle working on a team?",
            "Where do you see yourself in 5 years?"
        ],
        'mid' => [
            "Describe a challenging project you worked on and how you overcame obstacles.",
            "How do you prioritize tasks when managing multiple projects?",
            "Tell me about a time you had a conflict with a coworker and how you resolved it.",
            "What's your approach to learning new technologies or skills?"
        ],
        'senior' => [
            "Describe your leadership style and how you mentor junior team members.",
            "How do you approach strategic planning for your team/department?",
            "Tell me about a time you had to make a difficult business decision.",
            "How do you measure success for your team?"
        ]
    ];
    
    $level = $experienceLevel === 'executive' ? 'senior' : $experienceLevel;
    $questionsList = $questions[$level] ?? $questions['mid'];
    
    $question = $questionsList[array_rand($questionsList)];
    
    return "**Interview Question for $jobTitle position:**\n\n" .
           "$question\n\n" .
           "Take your time to answer. After you respond, I'll provide feedback and ask follow-up questions.";
}

function checkRateLimit($userId, $action) {
    global $db;
    
    $minuteLimit = RATE_LIMIT_PER_MINUTE;
    $hourLimit = RATE_LIMIT_PER_HOUR;
    
    // Check minute rate limit
    $minuteAgo = date('Y-m-d H:i:s', strtotime('-1 minute'));
    $minuteCount = $db->query(
        "SELECT COUNT(*) as count FROM rate_limits 
         WHERE user_id = ? AND action = ? AND created_at > ?",
        [$userId, $action, $minuteAgo]
    );
    
    $minuteCount = $minuteCount[0]['count'] ?? 0;
    
    if ($minuteCount >= $minuteLimit) {
        return false;
    }
    
    // Check hour rate limit
    $hourAgo = date('Y-m-d H:i:s', strtotime('-1 hour'));
    $hourCount = $db->query(
        "SELECT COUNT(*) as count FROM rate_limits 
         WHERE user_id = ? AND action = ? AND created_at > ?",
        [$userId, $action, $hourAgo]
    );
    
    $hourCount = $hourCount[0]['count'] ?? 0;
    
    if ($hourCount >= $hourLimit) {
        return false;
    }
    
    // Record this request
    $db->insert('rate_limits', [
        'user_id' => $userId,
        'action' => $action,
        'created_at' => date('Y-m-d H:i:s')
    ]);
    
    return true;
}
?>
