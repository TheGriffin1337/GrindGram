<?php
// POST /api/ai/generate
$user = require_auth();
$data = json_decode(file_get_contents('php://input'), true);

$prompt   = clean($data['prompt']   ?? '');
$category = clean($data['category'] ?? 'ogólne');

if (strlen($prompt) < 5) json_error('Prompt jest za krótki (min 5 znaków)');
if (OPENAI_API_KEY === 'sk-YOUR-KEY-HERE') json_error('Nie skonfigurowano klucza OpenAI API');

try {
    $aiContent = ai_generate_post($prompt, $category);
} catch (Exception $e) {
    json_error('Błąd AI: ' . $e->getMessage());
}

// Zapisz jako post
$title = mb_substr($aiContent, 0, 80) . (mb_strlen($aiContent) > 80 ? '...' : '');
$stmt = db()->prepare(
    "INSERT INTO posts (user_id, type, title, content, tags, ai_prompt)
     VALUES (?, 'ai', ?, ?, ?, ?)"
);
$stmt->execute([
    $user['id'],
    $title,
    $aiContent,
    json_encode([$category, 'ai-generated']),
    $prompt
]);

json_ok([
    'post_id' => db()->lastInsertId(),
    'content' => $aiContent,
], 'Post AI wygenerowany!');
