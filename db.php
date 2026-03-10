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
CREATE TABLE IF NOT EXISTS flashcard_history (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    date TEXT NOT NULL,
    topic TEXT,
    word TEXT NOT NULL,
    phonetic TEXT,
    word_type TEXT,
    meaning TEXT,
    rating TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
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

// ── Additional tables (consolidated from feature files) ──
$db->exec("
CREATE TABLE IF NOT EXISTS ai_companion_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        role TEXT NOT NULL,
        content TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );
CREATE TABLE IF NOT EXISTS ai_reminders (
    id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL,
    title TEXT NOT NULL, remind_at DATETIME NOT NULL, done INTEGER DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS cowork_sessions (
    id INTEGER PRIMARY KEY AUTOINCREMENT, room_id INTEGER NOT NULL,
    host_id INTEGER NOT NULL, title TEXT, music TEXT DEFAULT 'lofi',
    status TEXT DEFAULT 'active', pomo_duration INTEGER DEFAULT 25,
    started_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS cowork_whiteboard (
    id INTEGER PRIMARY KEY AUTOINCREMENT, session_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL, data TEXT,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS daily_challenges (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    date TEXT NOT NULL,
    challenge_id TEXT NOT NULL,
    target INTEGER NOT NULL,
    progress INTEGER DEFAULT 0,
    completed INTEGER DEFAULT 0,
    xp_reward INTEGER DEFAULT 0,
    UNIQUE(user_id, date, challenge_id)
);
CREATE TABLE IF NOT EXISTS duels (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    code TEXT UNIQUE NOT NULL,
    host_id INTEGER NOT NULL,
    guest_id INTEGER,
    topic TEXT NOT NULL,
    questions TEXT,
    host_answers TEXT DEFAULT '[]',
    guest_answers TEXT DEFAULT '[]',
    host_score INTEGER DEFAULT 0,
    guest_score INTEGER DEFAULT 0,
    status TEXT DEFAULT 'waiting',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS focus_sessions (
    id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL,
    duration_min INTEGER NOT NULL, goal TEXT, completed INTEGER DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS marketplace_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT, seller_id INTEGER NOT NULL,
    title TEXT NOT NULL, description TEXT, price INTEGER DEFAULT 0,
    category TEXT DEFAULT 'other', file_data TEXT, preview_data TEXT,
    status TEXT DEFAULT 'active', sales_count INTEGER DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS marketplace_purchases (
    id INTEGER PRIMARY KEY AUTOINCREMENT, item_id INTEGER NOT NULL,
    buyer_id INTEGER NOT NULL, price INTEGER NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP, UNIQUE(item_id,buyer_id)
);
CREATE TABLE IF NOT EXISTS notifications (
    id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL,
    type TEXT NOT NULL, title TEXT, body TEXT, link TEXT,
    read_at DATETIME, created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS question_bank (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    creator_id INTEGER,
    subject TEXT NOT NULL,
    grade TEXT DEFAULT '10',
    title TEXT NOT NULL,
    questions TEXT NOT NULL,
    is_public INTEGER DEFAULT 1,
    play_count INTEGER DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS srs_cards (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    word TEXT NOT NULL,
    phonetic TEXT DEFAULT '',
    word_type TEXT DEFAULT '',
    meaning TEXT NOT NULL,
    example TEXT DEFAULT '',
    topic TEXT DEFAULT '',
    interval_days INTEGER DEFAULT 1,
    ease_factor REAL DEFAULT 2.5,
    repetitions INTEGER DEFAULT 0,
    next_review TEXT DEFAULT CURRENT_DATE,
    last_rating TEXT DEFAULT '',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS study_room_members (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    room_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL,
    last_seen DATETIME DEFAULT CURRENT_TIMESTAMP,
    status TEXT DEFAULT 'active',
    UNIQUE(room_id,user_id)
);
CREATE TABLE IF NOT EXISTS study_rooms (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    code TEXT UNIQUE NOT NULL,
    host_id INTEGER NOT NULL,
    timer_state TEXT DEFAULT 'idle',
    timer_end INTEGER DEFAULT 0,
    focus_min INTEGER DEFAULT 25,
    break_min INTEGER DEFAULT 5,
    session_count INTEGER DEFAULT 0,
    topic TEXT DEFAULT '',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS time_capsules (
    id INTEGER PRIMARY KEY AUTOINCREMENT, sender_id INTEGER NOT NULL,
    recipient_id INTEGER, title TEXT NOT NULL, content TEXT NOT NULL,
    image_data TEXT, unlock_at DATETIME NOT NULL, is_public INTEGER DEFAULT 0,
    opened INTEGER DEFAULT 0, created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS user_badges (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    badge_id TEXT NOT NULL,
    earned_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(user_id, badge_id)
);
CREATE TABLE IF NOT EXISTS user_locations (
    id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL UNIQUE,
    lat REAL, lng REAL, visible_to TEXT DEFAULT 'friends',
    expires_at DATETIME, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS verify_requests (
    id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL,
    selfie_data TEXT, status TEXT DEFAULT 'pending', admin_note TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS virtual_gifts (
    id INTEGER PRIMARY KEY AUTOINCREMENT, sender_id INTEGER NOT NULL,
    recipient_id INTEGER NOT NULL, post_id INTEGER, gift_type TEXT NOT NULL,
    coins_value INTEGER DEFAULT 0, message TEXT, created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS writing_submissions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    essay TEXT NOT NULL,
    feedback TEXT,
    score INTEGER DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS xp_log (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    action TEXT NOT NULL,
    xp INTEGER NOT NULL,
    note TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS friendships (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        friend_id INTEGER NOT NULL,
        status TEXT DEFAULT 'pending',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(user_id, friend_id)
    );
");

// ── Additional user columns ──
@$db->exec("ALTER TABLE users ADD COLUMN xp INTEGER DEFAULT 0");
@$db->exec("ALTER TABLE users ADD COLUMN coins INTEGER DEFAULT 500");
@$db->exec("ALTER TABLE users ADD COLUMN level INTEGER DEFAULT 1");
@$db->exec("ALTER TABLE users ADD COLUMN streak INTEGER DEFAULT 0");
@$db->exec("ALTER TABLE users ADD COLUMN last_active_date TEXT DEFAULT ''");
@$db->exec("ALTER TABLE users ADD COLUMN longest_streak INTEGER DEFAULT 0");
@$db->exec("ALTER TABLE users ADD COLUMN cover_image TEXT");
@$db->exec("ALTER TABLE users ADD COLUMN verified INTEGER DEFAULT 0");
@$db->exec("ALTER TABLE users ADD COLUMN verify_type TEXT DEFAULT ''");
@$db->exec("ALTER TABLE users ADD COLUMN ai_name TEXT DEFAULT 'Spark'");
@$db->exec("ALTER TABLE users ADD COLUMN ai_style TEXT DEFAULT ''");

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
    if (session_status() === PHP_SESSION_NONE) session_start();
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
