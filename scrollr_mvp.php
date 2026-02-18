<?php
/**
 * SCROLLR MVP - Single File Version
 * Najprostsza wersja - wgraj na serwer i działa od razu
 * 
 * INSTALACJA:
 * 1. Wgraj ten plik do /var/www/html/scrollr.php
 * 2. Utwórz bazę: mysql -u root -p -e "CREATE DATABASE scrollr"
 * 3. Odwiedź: http://TWOJ_SERWER/scrollr.php?setup
 * 4. Gotowe!
 */

// ==== KONFIGURACJA - ZMIEŃ NA SWOJE ====
define('DB_HOST', 'localhost');
define('DB_NAME', 'scrollr');
define('DB_USER', 'root');
define('DB_PASS', '');

// ==== AUTO-SETUP BAZY ====
if (isset($_GET['setup'])) {
    try {
        $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
        $pdo->exec("CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) UNIQUE,
            email VARCHAR(150) UNIQUE,
            password VARCHAR(255),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        $pdo->exec("CREATE TABLE IF NOT EXISTS posts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT,
            content TEXT,
            likes INT DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id)
        )");
        $pdo->exec("CREATE TABLE IF NOT EXISTS sessions (
            token VARCHAR(64) PRIMARY KEY,
            user_id INT,
            expires_at DATETIME,
            FOREIGN KEY (user_id) REFERENCES users(id)
        )");
        
        // Dodaj demo użytkownika (hasło: test123)
        $hash = password_hash('test123', PASSWORD_BCRYPT);
        $pdo->exec("INSERT IGNORE INTO users (username, email, password) 
                    VALUES ('demo', 'demo@scrollr.app', '$hash')");
        
        die("✅ Baza utworzona! <a href='scrollr.php'>Przejdź do aplikacji</a>");
    } catch (Exception $e) {
        die("❌ Błąd: " . $e->getMessage());
    }
}

// ==== API ROUTER ====
$uri = $_SERVER['REQUEST_URI'];
if (strpos($uri, '/api/') !== false) {
    header('Content-Type: application/json');
    
    try {
        $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $path = parse_url($uri, PHP_URL_PATH);
        $method = $_SERVER['REQUEST_METHOD'];
        
        // GET /api/posts - Lista postów
        if ($path == '/scrollr.php/api/posts' && $method == 'GET') {
            $stmt = $pdo->query("SELECT p.*, u.username FROM posts p 
                                  JOIN users u ON p.user_id = u.id 
                                  ORDER BY p.created_at DESC LIMIT 20");
            die(json_encode(['success' => true, 'posts' => $stmt->fetchAll(PDO::FETCH_ASSOC)]));
        }
        
        // POST /api/login
        if ($path == '/scrollr.php/api/login' && $method == 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$data['email']]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($data['password'], $user['password'])) {
                $token = bin2hex(random_bytes(32));
                $pdo->prepare("INSERT INTO sessions (token, user_id, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 30 DAY))")
                    ->execute([$token, $user['id']]);
                die(json_encode(['success' => true, 'token' => $token, 'username' => $user['username']]));
            }
            die(json_encode(['success' => false, 'error' => 'Błędne dane']));
        }
        
        // POST /api/posts - Dodaj post (wymaga tokena)
        if ($path == '/scrollr.php/api/posts' && $method == 'POST') {
            $token = $_SERVER['HTTP_X_AUTH_TOKEN'] ?? '';
            $stmt = $pdo->prepare("SELECT user_id FROM sessions WHERE token = ? AND expires_at > NOW()");
            $stmt->execute([$token]);
            $session = $stmt->fetch();
            
            if (!$session) die(json_encode(['success' => false, 'error' => 'Zaloguj się']));
            
            $data = json_decode(file_get_contents('php://input'), true);
            $pdo->prepare("INSERT INTO posts (user_id, content) VALUES (?, ?)")
                ->execute([$session['user_id'], $data['content']]);
            die(json_encode(['success' => true]));
        }
        
        die(json_encode(['success' => false, 'error' => 'Endpoint nie istnieje']));
        
    } catch (Exception $e) {
        die(json_encode(['success' => false, 'error' => $e->getMessage()]));
    }
}

// ==== FRONTEND HTML ====
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Scrollr MVP</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: system-ui; background: #0a0a0f; color: #fff; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .post { background: #1a1a24; padding: 20px; margin: 10px 0; border-radius: 12px; }
        .post-author { font-weight: 600; margin-bottom: 8px; }
        .post-content { line-height: 1.6; }
        input, textarea { width: 100%; padding: 12px; margin: 8px 0; border-radius: 8px; 
                          border: 1px solid #333; background: #0a0a0f; color: #fff; }
        button { background: #e8c97d; color: #000; border: none; padding: 12px 24px; 
                 border-radius: 8px; cursor: pointer; font-weight: 600; }
        .auth-box { background: #1a1a24; padding: 30px; border-radius: 12px; max-width: 400px; margin: 100px auto; }
        #app { display: none; }
    </style>
</head>
<body>
    
<div id="auth" class="auth-box">
    <h1 style="margin-bottom: 20px;">Scrollr MVP</h1>
    <input type="email" id="email" placeholder="Email" value="demo@scrollr.app">
    <input type="password" id="password" placeholder="Hasło" value="test123">
    <button onclick="login()">Zaloguj</button>
    <p style="margin-top: 16px; font-size: 14px; color: #888;">Demo: demo@scrollr.app / test123</p>
</div>

<div id="app" class="container">
    <h1 style="margin-bottom: 20px;">Feed</h1>
    <div style="background: #1a1a24; padding: 20px; border-radius: 12px; margin-bottom: 20px;">
        <textarea id="newPost" placeholder="Co chcesz przekazać?" rows="3"></textarea>
        <button onclick="addPost()">Dodaj post</button>
    </div>
    <div id="feed"></div>
</div>

<script>
let token = localStorage.getItem('token');

async function api(path, method = 'GET', body = null) {
    const opts = {method, headers: {'X-Auth-Token': token || ''}};
    if (body) {
        opts.headers['Content-Type'] = 'application/json';
        opts.body = JSON.stringify(body);
    }
    const res = await fetch('/scrollr.php/api/' + path, opts);
    return await res.json();
}

async function login() {
    const email = document.getElementById('email').value;
    const password = document.getElementById('password').value;
    const data = await api('login', 'POST', {email, password});
    if (data.success) {
        token = data.token;
        localStorage.setItem('token', token);
        showApp();
    } else {
        alert(data.error);
    }
}

async function addPost() {
    const content = document.getElementById('newPost').value;
    if (!content) return alert('Wpisz treść posta');
    await api('posts', 'POST', {content});
    document.getElementById('newPost').value = '';
    loadPosts();
}

async function loadPosts() {
    const data = await api('posts');
    const feed = document.getElementById('feed');
    feed.innerHTML = data.posts.map(p => `
        <div class="post">
            <div class="post-author">@${p.username}</div>
            <div class="post-content">${p.content}</div>
        </div>
    `).join('');
}

function showApp() {
    document.getElementById('auth').style.display = 'none';
    document.getElementById('app').style.display = 'block';
    loadPosts();
}

if (token) showApp();
</script>

</body>
</html>
