<?php
/**
 * Configuration File
 */

define('ENVIRONMENT', 'development'); // development, staging, production
define('API_VERSION', '1.0.0');
define('SITE_NAME', 'Zewed AI Career Assistant');
define('SITE_URL', 'https://yourdomain.com');
define('TIMEZONE', 'UTC');

// API Keys
define('OPENAI_API_KEY', 'your-openai-api-key-here');
define('ANTHROPIC_API_KEY', 'your-anthropic-api-key-here');
define('GOOGLE_AI_KEY', 'your-google-ai-key-here');
define('SERPER_API_KEY', 'your-serper-api-key-here'); // For job search

// Rate limiting
define('RATE_LIMIT_PER_MINUTE', 60);
define('RATE_LIMIT_PER_HOUR', 1000);

// File upload settings
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB
define('ALLOWED_FILE_TYPES', ['pdf', 'doc', 'docx', 'txt', 'jpg', 'png']);
define('UPLOAD_PATH', __DIR__ . '/../../uploads/');

// Session settings
define('SESSION_LIFETIME', 24 * 60 * 60); // 24 hours
define('SESSION_REGENERATE', 15 * 60); // 15 minutes

// JWT settings
define('JWT_SECRET', 'your-jwt-secret-key-here-change-this');
define('JWT_ALGORITHM', 'HS256');
define('JWT_EXPIRY', 24 * 60 * 60); // 24 hours

// Email settings
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'your-email@gmail.com');
define('SMTP_PASS', 'your-app-password');
define('SMTP_FROM', 'noreply@yourdomain.com');
define('SMTP_FROM_NAME', 'Zewed AI');

// Logging
define('LOG_PATH', __DIR__ . '/../../logs/');
define('LOG_LEVEL', 'DEBUG'); // DEBUG, INFO, WARNING, ERROR

// Caching
define('CACHE_ENABLED', true);
define('CACHE_TTL', 3600); // 1 hour

// Feature flags
define('FEATURE_AI_CHAT', true);
define('FEATURE_JOB_SEARCH', true);
define('FEATURE_RESUME_ANALYSIS', true);
define('FEATURE_INTERVIEW_PREP', true);
define('FEATURE_EMAIL_NOTIFICATIONS', true);
?>
