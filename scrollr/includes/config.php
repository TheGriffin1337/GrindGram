<?php
// ============================================
// SCROLLR - Konfiguracja
// Zmień poniższe dane na swoje!
// ============================================

define('DB_HOST', 'localhost');
define('DB_NAME', 'scrollr');
define('DB_USER', 'root');          // ← zmień
define('DB_PASS', '');              // ← zmień
define('DB_CHARSET', 'utf8mb4');

define('APP_NAME', 'Scrollr');
define('APP_URL', 'http://localhost/scrollr');  // ← zmień na swój URL
define('UPLOAD_PATH', __DIR__ . '/../uploads/');
define('UPLOAD_URL', APP_URL . '/uploads/');
define('MAX_FILE_SIZE', 50 * 1024 * 1024);     // 50 MB
define('SESSION_LIFETIME', 60 * 60 * 24 * 30); // 30 dni

// OpenAI API (do generowania treści przez AI)
define('OPENAI_API_KEY', 'sk-YOUR-KEY-HERE');   // ← wstaw swój klucz
define('OPENAI_MODEL', 'gpt-4o-mini');

define('DEBUG', true); // ustaw false na produkcji!
