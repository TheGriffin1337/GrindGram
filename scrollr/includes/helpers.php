<?php
require_once __DIR__ . '/config.php';

// ============================================
// POŁĄCZENIE Z BAZĄ DANYCH (PDO)
// ============================================
function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            die(json_encode(['error' => 'Błąd połączenia z bazą danych']));
        }
    }
    return $pdo;
}

// ============================================
// ODPOWIEDŹ JSON
// ============================================
function json_response(mixed $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function json_error(string $message, int $code = 400): void {
    json_response(['success' => false, 'error' => $message], $code);
}

function json_ok(mixed $data = [], string $message = 'OK'): void {
    json_response(['success' => true, 'message' => $message, 'data' => $data]);
}

// ============================================
// AUTENTYKACJA (token w nagłówku)
// ============================================
function auth(): ?array {
    $token = $_SERVER['HTTP_X_AUTH_TOKEN']
          ?? ($_COOKIE['scrollr_token'] ?? null);

    if (!$token) return null;

    $stmt = db()->prepare(
        "SELECT u.* FROM user_sessions s
         JOIN users u ON u.id = s.user_id
         WHERE s.token = ? AND s.expires_at > NOW()"
    );
    $stmt->execute([$token]);
    return $stmt->fetch() ?: null;
}

function require_auth(): array {
    $user = auth();
    if (!$user) json_error('Wymagane logowanie', 401);
    return $user;
}

// ============================================
// UPLOAD PLIKU
// ============================================
function upload_file(array $file, string $type = 'image'): string {
    $allowed_images = ['image/jpeg','image/png','image/gif','image/webp'];
    $allowed_videos = ['video/mp4','video/webm','video/ogg'];
    $allowed = $type === 'video' ? array_merge($allowed_images, $allowed_videos) : $allowed_images;

    if (!in_array($file['type'], $allowed)) {
        throw new Exception("Niedozwolony format pliku");
    }
    if ($file['size'] > MAX_FILE_SIZE) {
        throw new Exception("Plik jest za duży (max 50MB)");
    }

    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid('media_', true) . '.' . strtolower($ext);
    $subdir = $type === 'video' ? 'videos/' : 'images/';
    $dest = UPLOAD_PATH . $subdir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        throw new Exception("Nie udało się zapisać pliku");
    }

    return UPLOAD_URL . $subdir . $filename;
}

// ============================================
// GENEROWANIE TREŚCI PRZEZ AI (OpenAI)
// ============================================
function ai_generate_post(string $prompt, string $category = 'ogólne'): string {
    $system = "Jesteś twórcą inspirujących, edukacyjnych postów po polsku.
Pisz krótko (max 300 słów), angażująco, z emojami.
Kategoria: {$category}.
Formatuj odpowiedź jako gotowy post – bez nagłówków 'post:', 'tytuł:' itp.";

    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . OPENAI_API_KEY,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'model'    => OPENAI_MODEL,
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user',   'content' => $prompt],
            ],
            'max_tokens'  => 500,
            'temperature' => 0.85,
        ]),
        CURLOPT_TIMEOUT => 30,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) throw new Exception("Błąd AI API (kod: $httpCode)");

    $data = json_decode($response, true);
    return trim($data['choices'][0]['message']['content'] ?? '');
}

// ============================================
// SANITYZACJA
// ============================================
function clean(string $str): string {
    return htmlspecialchars(strip_tags(trim($str)), ENT_QUOTES, 'UTF-8');
}
