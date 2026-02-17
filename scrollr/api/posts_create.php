<?php
// POST /api/posts (multipart lub JSON)
$user = require_auth();

$isMultipart = str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'multipart');
if ($isMultipart) {
    $type    = clean($_POST['type']    ?? 'text');
    $title   = clean($_POST['title']   ?? '');
    $content = clean($_POST['content'] ?? '');
    $tagsRaw =       $_POST['tags']    ?? '[]';
} else {
    $data    = json_decode(file_get_contents('php://input'), true);
    $type    = clean($data['type']    ?? 'text');
    $title   = clean($data['title']   ?? '');
    $content = clean($data['content'] ?? '');
    $tagsRaw =       $data['tags']    ?? '[]';
}

if (!$content && !$title) json_error('Post musi zawierać tekst lub tytuł');
if (!in_array($type, ['image','video','text','ai'])) json_error('Nieprawidłowy typ postu');

$tags    = is_array($tagsRaw) ? $tagsRaw : json_decode($tagsRaw, true);
$mediaUrl = null;

// Obsługa uploadu pliku
if ($isMultipart && !empty($_FILES['media']['name'])) {
    try {
        $mediaUrl = upload_file($_FILES['media'], $type);
    } catch (Exception $e) {
        json_error($e->getMessage());
    }
}

$stmt = db()->prepare(
    "INSERT INTO posts (user_id, type, title, content, media_url, tags)
     VALUES (?, ?, ?, ?, ?, ?)"
);
$stmt->execute([
    $user['id'], $type, $title ?: null, $content ?: null,
    $mediaUrl, json_encode($tags ?? [], JSON_UNESCAPED_UNICODE)
]);

json_ok(['post_id' => db()->lastInsertId()], 'Post dodany!');
