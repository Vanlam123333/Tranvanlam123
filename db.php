<?php
$db = new SQLite3(__DIR__ . '/mindspark.db');
$db->exec("PRAGMA journal_mode=WAL");

$db->exec("
CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    email TEXT UNIQUE NOT NULL,
    password TEXT NOT NULL,
    avatar TEXT,
    bio TEXT DEFAULT '',
    cover_color TEXT DEFAULT '#4f6ef7',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS notes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    title TEXT, content TEXT, ai_summary TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(user_id) REFERENCES users(id)
);
CREATE TABLE IF NOT EXISTS plans (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    date TEXT NOT NULL, subject TEXT, task TEXT, done INTEGER DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(user_id) REFERENCES users(id)
);
CREATE TABLE IF NOT EXISTS quiz_results (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    topic TEXT, score INTEGER, total INTEGER,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(user_id) REFERENCES users(id)
);
CREATE TABLE IF NOT EXISTS chat_history (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    role TEXT, content TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(user_id) REFERENCES users(id)
);
CREATE TABLE IF NOT EXISTS pomodoro_sessions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    type TEXT DEFAULT 'focus', duration INTEGER DEFAULT 25, completed INTEGER DEFAULT 1, subject TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(user_id) REFERENCES users(id)
);
CREATE TABLE IF NOT EXISTS mindmaps (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    title TEXT, topic TEXT, data TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(user_id) REFERENCES users(id)
);
CREATE TABLE IF NOT EXISTS social_posts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    content TEXT NOT NULL,
    image_data TEXT,
    likes_count INTEGER DEFAULT 0,
    comments_count INTEGER DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(user_id) REFERENCES users(id)
);
CREATE TABLE IF NOT EXISTS post_likes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    post_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(post_id, user_id)
);
CREATE TABLE IF NOT EXISTS post_comments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    post_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL,
    content TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(post_id) REFERENCES social_posts(id),
    FOREIGN KEY(user_id) REFERENCES users(id)
);
CREATE TABLE IF NOT EXISTS chat_rooms (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    description TEXT,
    icon TEXT DEFAULT '💬',
    created_by INTEGER,
    is_public INTEGER DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS room_messages (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    room_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL,
    content TEXT NOT NULL,
    reply_to INTEGER DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(room_id) REFERENCES chat_rooms(id),
    FOREIGN KEY(user_id) REFERENCES users(id)
);
");

// Migrate old users table (add new columns if missing)
@$db->exec("ALTER TABLE users ADD COLUMN avatar TEXT");
@$db->exec("ALTER TABLE users ADD COLUMN bio TEXT DEFAULT ''");
@$db->exec("ALTER TABLE users ADD COLUMN cover_color TEXT DEFAULT '#4f6ef7'");

// Seed default rooms
$rc = $db->query("SELECT COUNT(*) as c FROM chat_rooms")->fetchArray()['c'];
if ($rc == 0) {
    $db->exec("INSERT INTO chat_rooms (name,description,icon,is_public) VALUES
        ('Chung','Nói chuyện về bất cứ thứ gì 🎉','🏠',1),
        ('Học tập','Hỏi đáp bài vở, chia sẻ tài liệu','📚',1),
        ('Toán & Khoa học','Giải toán, lý, hóa cùng nhau','🔬',1),
        ('Giải trí','Games, phim, nhạc, meme','🎮',1)
    ");
}

function getCurrentUser() {
    if (isset($_SESSION['user_id'])) {
        global $db;
        $stmt = $db->prepare('SELECT * FROM users WHERE id = :id');
        $stmt->bindValue(':id', $_SESSION['user_id']);
        return $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    }
    return null;
}

function requireLogin() {
    session_start();
    if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
}

function userAvatar($user, $size=36) {
    $s = (int)$size;
    $fs = max(12, round($s*0.42));
    if (!empty($user['avatar'])) {
        return '<img src="'.htmlspecialchars($user['avatar']).'" style="width:'.$s.'px;height:'.$s.'px;border-radius:50%;object-fit:cover;flex-shrink:0;" alt="">';
    }
    $initial = mb_strtoupper(mb_substr($user['name'],0,1,'UTF-8'));
    $colors  = ['#4f6ef7','#10b981','#f59e0b','#ef4444','#8b5cf6','#ec4899','#06b6d4','#f97316'];
    $col     = $colors[abs(crc32($user['name'])) % count($colors)];
    return '<div style="width:'.$s.'px;height:'.$s.'px;border-radius:50%;background:'.$col.';display:flex;align-items:center;justify-content:center;font-size:'.$fs.'px;font-weight:800;color:#fff;flex-shrink:0;">'.$initial.'</div>';
}

function timeAgo($dt) {
    $diff = time() - strtotime($dt);
    if ($diff < 60)    return 'Vừa xong';
    if ($diff < 3600)  return floor($diff/60).' phút trước';
    if ($diff < 86400) return floor($diff/3600).' giờ trước';
    if ($diff < 604800) return floor($diff/86400).' ngày trước';
    return date('d/m/Y', strtotime($dt));
}
?>
