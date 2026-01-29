<?php
/**
 * AI Integration Routes
 */

function handleAIRoutes($method, $action, $id, $data, $auth, $response) {
    // Check authentication
    if (!$auth->isAuthenticated()) {
        $response->error('Authentication required', 401);
        return;
    }
    
    $userId = $auth->getUserId();
    
    switch ($method) {
        case 'POST':
            switch ($action) {
                case 'generate-cover-letter':
                    generateCoverLetter($userId, $data, $response);
                    break;
                    
                case 'optimize-resume':
                    optimizeResume($userId, $data, $response);
                    break;
                    
                case 'interview-feedback':
                    getInterviewFeedback($userId, $data, $response);
                    break;
                    
                case 'career-suggestions':
                    getCareerSuggestions($userId, $data, $response);
                    break;
                    
                case 'skill-gap-analysis':
                    analyzeSkillGaps($userId, $data, $response);
                    break;
                    
                default:
                    $response->error('Invalid AI action', 400);
            }
            break;
            
        case 'GET':
            switch ($action) {
                case 'models':
                    getAIModels($response);
                    break;
                    
                case 'usage':
                    getAIUsage($userId, $response);
                    break;
                    
                default:
                    $response->error('Invalid AI action', 400);
            }
            break;
            
        default:
            $response->error('Method not allowed', 405);
    }
}

function generateCoverLetter($userId, $data, $response) {
    global $db;
    
    $validation = new Validation();
    $validation->validate($data, [
        'job_title' => 'required|min:2|max:100',
        'company' => 'required|min:2|max:100',
        'job_description' => 'required|min:10',
        'tone' => 'sometimes|in:formal,professional,friendly,enthusiastic'
    ]);
    
    if ($validation->fails()) {
        $response->error($validation->errors(), 400);
        return;
    }
    
    // Get user profile
    $user = $db->select('users', 'name, email, phone, skills, experience', 'id = ?', [$userId], 1);
    
    if (empty($user)) {
        $response->error('User not found', 404);
        return;
    }
    
    $user = $user[0];
    
    // Prepare prompt for AI
    $prompt = "Generate a professional cover letter with the following details:\n\n";
    $prompt .= "Applicant Name: " . $user['name'] . "\n";
    $prompt .= "Job Title: " . $data['job_title'] . "\n";
    $prompt .= "Company: " . $data['company'] . "\n\n";
    $prompt .= "Job Description:\n" . $data['job_description'] . "\n\n";
    
    if (!empty($user['skills'])) {
        $prompt .= "Applicant Skills: " . $user['skills'] . "\n";
    }
    
    if (!empty($user['experience'])) {
        $prompt .= "Applicant Experience: " . $user['experience'] . "\n";
    }
    
    $prompt .= "\nTone: " . ($data['tone'] ?? 'professional');
    $prompt .= "\n\nGenerate a personalized cover letter that highlights relevant skills and experience.";
    
    // Call AI service
    $coverLetter = callOpenAI($prompt, 'cover_letter');
    
    // Save generated cover letter
    $saved = $db->insert('generated_content', [
        'user_id' => $userId,
        'type' => 'cover_letter',
        'title' => 'Cover Letter for ' . $data['job_title'] . ' at ' . $data['company'],
        'content' => $coverLetter,
        'parameters' => json_encode($data),
        'created_at' => date('Y-m-d H:i:s')
    ]);
    
    $response->success([
        'cover_letter' => $coverLetter,
        'content_id' => $saved['insert_id'],
        'message' => 'Cover letter generated successfully'
    ]);
}

function optimizeResume($userId, $data, $response) {
    global $db;
    
    $validation = new Validation();
    $validation->validate($data, [
        'resume_content' => 'required|min:50',
        'job_description' => 'sometimes|min:10',
        'target_role' => 'sometimes|min:2|max:100',
        'optimization_level' => 'sometimes|in:light,moderate,heavy'
    ]);
    
    if ($validation->fails()) {
        $response->error($validation->errors(), 400);
        return;
    }
    
    // Prepare prompt for AI
    $prompt = "Optimize the following resume for ";
    
    if (!empty($data['target_role'])) {
        $prompt .= "a " . $data['target_role'] . " position";
    } else {
        $prompt .= "better job prospects";
    }
    
    $prompt .= ":\n\n" . $data['resume_content'] . "\n\n";
    
    if (!empty($data['job_description'])) {
        $prompt .= "Target Job Description:\n" . $data['job_description'] . "\n\n";
    }
    
    $prompt .= "Optimization Level: " . ($data['optimization_level'] ?? 'moderate') . "\n";
    $prompt .= "Please provide:\n";
    $prompt .= "1. An optimized version of the resume\n";
    $prompt .= "2. Specific recommendations for improvement\n";
    $prompt .= "3. Keywords to include for ATS optimization\n";
    
    // Call AI service
    $optimization = callOpenAI($prompt, 'resume_optimization');
    
    // Parse AI response
    $sections = extractOptimizationSections($optimization);
    
    // Save optimization
    $saved = $db->insert('generated_content', [
        'user_id' => $userId,
        'type' => 'resume_optimization',
        'title' => 'Resume Optimization' . (!empty($data['target_role']) ? ' for ' . $data['target_role'] : ''),
        'content' => $optimization,
        'parameters' => json_encode($data),
        'created_at' => date('Y-m-d H:i:s')
    ]);
    
    $response->success([
        'optimization' => $sections,
        'full_response' => $optimization,
        'content_id' => $saved['insert_id'],
        'message' => 'Resume optimized successfully'
    ]);
}

function getInterviewFeedback($userId, $data, $response) {
    global $db;
    
    $validation = new Validation();
    $validation->validate($data, [
        'question' => 'required|min:5',
        'answer' => 'required|min:10',
        'job_role' => 'sometimes|min:2|max:100',
        'experience_level' => 'sometimes|in:entry,mid,senior'
    ]);
    
    if ($validation->fails()) {
        $response->error($validation->errors(), 400);
        return;
    }
    
    // Prepare prompt for AI
    $prompt = "Provide feedback on this interview answer:\n\n";
    $prompt .= "Question: " . $data['question'] . "\n\n";
    $prompt .= "Candidate's Answer: " . $data['answer'] . "\n\n";
    
    if (!empty($data['job_role'])) {
        $prompt .= "Job Role: " . $data['job_role'] . "\n";
    }
    
    if (!empty($data['experience_level'])) {
        $prompt .= "Experience Level: " . $data['experience_level'] . "\n";
    }
    
    $prompt .= "\nPlease evaluate the answer and provide:\n";
    $prompt .= "1. Strengths of the answer\n";
    $prompt .= "2. Areas for improvement\n";
    $prompt .= "3. A better example answer\n";
    $prompt .= "4. Tips for improvement\n";
    
    // Call AI service
    $feedback = callOpenAI($prompt, 'interview_feedback');
    
    // Save feedback
    $saved = $db->insert('generated_content', [
        'user_id' => $userId,
        'type' => 'interview_feedback',
        'title' => 'Interview Feedback',
        'content' => $feedback,
        'parameters' => json_encode($data),
        'created_at' => date('Y-m-d H:i:s')
    ]);
    
    $response->success([
        'feedback' => $feedback,
        'content_id' => $saved['insert_id'],
        'message' => 'Feedback generated successfully'
    ]);
}

function getCareerSuggestions($userId, $data, $response) {
    global $db;
    
    // Get user profile
    $user = $db->select('users', 'skills, experience, education, current_role', 'id = ?', [$userId], 1);
    
    if (empty($user)) {
        $response->error('User not found', 404);
        return;
    }
    
    $user = $user[0];
    
    // Prepare prompt for AI
    $prompt = "Provide career path suggestions based on this profile:\n\n";
    
    if (!empty($user['current_role'])) {
        $prompt .= "Current Role: " . $user['current_role'] . "\n";
    }
    
    if (!empty($user['skills'])) {
        $prompt .= "Skills: " . $user['skills'] . "\n";
    }
    
    if (!empty($user['experience'])) {
        $prompt .= "Experience: " . $user['experience'] . "\n";
    }
    
    if (!empty($user['education'])) {
        $prompt .= "Education: " . $user['education'] . "\n";
    }
    
    $prompt .= "\nPlease suggest:\n";
    $prompt .= "1. Potential career paths to explore\n";
    $prompt .= "2. Skills to develop for each path\n";
    $prompt .= "3. Recommended courses or certifications\n";
    $prompt .= "4. Job titles to target\n";
    $prompt .= "5. Salary expectations for each path\n";
    
    // Call AI service
    $suggestions = callOpenAI($prompt, 'career_suggestions');
    
    // Save suggestions
    $saved = $db->insert('generated_content', [
        'user_id' => $userId,
        'type' => 'career_suggestions',
        'title' => 'Career Path Suggestions',
        'content' => $suggestions,
        'parameters' => json_encode($user),
        'created_at' => date('Y-m-d H:i:s')
    ]);
    
    $response->success([
        'suggestions' => $suggestions,
        'content_id' => $saved['insert_id'],
        'message' => 'Career suggestions generated successfully'
    ]);
}

function analyzeSkillGaps($userId, $data, $response) {
    global $db;
    
    $validation = new Validation();
    $validation->validate($data, [
        'target_role' => 'required|min:2|max:100',
        'target_industry' => 'sometimes|min:2|max:100'
    ]);
    
    if ($validation->fails()) {
        $response->error($validation->errors(), 400);
        return;
    }
    
    // Get user skills
    $user = $db->select('users', 'skills, experience', 'id = ?', [$userId], 1);
    
    if (empty($user)) {
        $response->error('User not found', 404);
        return;
    }
    
    $user = $user[0];
    
    // Get job market data for target role
    $marketSkills = getMarketSkills($data['target_role'], $data['target_industry'] ?? '');
    
    // Prepare prompt for AI
    $prompt = "Analyze skill gaps for transitioning to " . $data['target_role'] . ":\n\n";
    $prompt .= "Current Skills: " . ($user['skills'] ?? 'Not specified') . "\n";
    $prompt .= "Current Experience: " . ($user['experience'] ?? 'Not specified') . "\n\n";
    
    if (!empty($marketSkills)) {
        $prompt .= "Required Skills for " . $data['target_role'] . ":\n" . $marketSkills . "\n\n";
    }
    
    $prompt .= "Please provide:\n";
    $prompt .= "1. Skill gap analysis\n";
    $prompt .= "2. Priority skills to learn\n";
    $prompt .= "3. Learning resources for each skill\n";
    $prompt .= "4. Timeline for skill development\n";
    $prompt .= "5. Project ideas to build portfolio\n";
    
    // Call AI service
    $analysis = callOpenAI($prompt, 'skill_gap_analysis');
    
    // Save analysis
    $saved = $db->insert('generated_content', [
        'user_id' => $userId,
        'type' => 'skill_gap_analysis',
        'title' => 'Skill Gap Analysis for ' . $data['target_role'],
        'content' => $analysis,
        'parameters' => json_encode($data),
        'created_at' => date('Y-m-d H:i:s')
    ]);
    
    $response->success([
        'analysis' => $analysis,
        'content_id' => $saved['insert_id'],
        'message' => 'Skill gap analysis completed'
    ]);
}

function getAIModels($response) {
    $models = [
        'openai' => [
            'gpt-3.5-turbo' => 'Fast, cost-effective for most tasks',
            'gpt-4' => 'More capable, better reasoning',
            'gpt-4-turbo' => 'Latest, with vision capabilities'
        ],
        'anthropic' => [
            'claude-3-opus' => 'Most capable',
            'claude-3-sonnet' => 'Balanced',
            'claude-3-haiku' => 'Fast and efficient'
        ],
        'google' => [
            'gemini-pro' => 'General purpose',
            'gemini-ultra' => 'Most capable'
        ]
    ];
    
    // Get active model from config
    $activeModel = defined('AI_MODEL') ? AI_MODEL : 'gpt-3.5-turbo';
    
    $response->success([
        'models' => $models,
        'active_model' => $activeModel,
        'features' => [
            'chat' => FEATURE_AI_CHAT,
            'resume_analysis' => FEATURE_RESUME_ANALYSIS,
            'interview_prep' => FEATURE_INTERVIEW_PREP
        ]
    ]);
}

function getAIUsage($userId, $response) {
    global $db;
    
    $today = date('Y-m-d');
    $monthStart = date('Y-m-01');
    
    // Get usage stats
    $usage = $db->query("
        SELECT 
            COUNT(*) as total_requests,
            SUM(CASE WHEN DATE(created_at) = ? THEN 1 ELSE 0 END) as today_requests,
            SUM(CASE WHEN MONTH(created_at) = MONTH(?) THEN 1 ELSE 0 END) as month_requests
        FROM ai_usage 
        WHERE user_id = ?
    ", [$today, $monthStart, $userId]);
    
    $usage = $usage[0] ?? ['total_requests' => 0, 'today_requests' => 0, 'month_requests' => 0];
    
    // Get recent requests
    $recent = $db->select('ai_usage', '*', 'user_id = ?', [$userId], 10, 'created_at DESC');
    
    $response->success([
        'usage' => $usage,
        'recent_requests' => $recent,
        'limits' => [
            'daily' => 50,
            'monthly' => 1000,
            'remaining_today' => max(0, 50 - $usage['today_requests'])
        ]
    ]);
}

// Helper functions
function callOpenAI($prompt, $type = 'general') {
    $apiKey = OPENAI_API_KEY;
    
    if (!$apiKey) {
        return "AI service is currently unavailable. Please try again later.";
    }
    
    // Track usage
    global $db, $userId;
    if (isset($userId)) {
        $db->insert('ai_usage', [
            'user_id' => $userId,
            'type' => $type,
            'prompt_length' => strlen($prompt),
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    // Determine model based on type
    $model = 'gpt-3.5-turbo';
    if (in_array($type, ['resume_optimization', 'career_suggestions'])) {
        $model = 'gpt-4';
    }
    
    $data = [
        'model' => $model,
        'messages' => [
            ['role' => 'system', 'content' => 'You are an expert career coach and AI assistant.'],
            ['role' => 'user', 'content' => $prompt]
        ],
        'max_tokens' => 2000,
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
    
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        error_log("OpenAI API error: " . $result);
        return "I apologize, but I encountered an error generating the response. Please try again.";
    }
    
    $result = json_decode($result, true);
    return $result['choices'][0]['message']['content'] ?? 'No response generated.';
}

function getMarketSkills($role, $industry) {
    // This would typically call an external API or database
    // For now, return hardcoded data
    
    $skillsMap = [
        'software engineer' => 'JavaScript, Python, React, Node.js, SQL, Git, AWS, Docker, Agile methodologies',
        'data scientist' => 'Python, R, SQL, Machine Learning, Statistics, Data Visualization, Pandas, TensorFlow',
        'product manager' => 'Product Strategy, User Research, Agile, Roadmapping, Analytics, Stakeholder Management',
        'marketing manager' => 'Digital Marketing, SEO, Social Media, Content Strategy, Analytics, Campaign Management',
        'ux designer' => 'User Research, Wireframing, Prototyping, Figma, User Testing, Design Systems'
    ];
    
    $roleLower = strtolower($role);
    
    foreach ($skillsMap as $key => $skills) {
        if (strpos($roleLower, $key) !== false) {
            return $skills;
        }
    }
    
    return 'Communication, Problem Solving, Teamwork, Adaptability, Technical Skills relevant to the role';
}

function extractOptimizationSections($content) {
    // Simple parsing of AI response
    $sections = [
        'optimized_resume' => '',
        'recommendations' => '',
        'keywords' => ''
    ];
    
    $lines = explode("\n", $content);
    $currentSection = '';
    
    foreach ($lines as $line) {
        $line = trim($line);
        
        if (preg_match('/^\d+\.\s+(.+)/', $line, $matches)) {
            $title = strtolower($matches[1]);
            
            if (strpos($title, 'optimized') !== false || strpos($title, 'version') !== false) {
                $currentSection = 'optimized_resume';
            } elseif (strpos($title, 'recommend') !== false || strpos($title, 'improvement') !== false) {
                $currentSection = 'recommendations';
            } elseif (strpos($title, 'keyword') !== false) {
                $currentSection = 'keywords';
            }
        } elseif ($currentSection && $line) {
            $sections[$currentSection] .= $line . "\n";
        }
    }
    
    return $sections;
}
?>
