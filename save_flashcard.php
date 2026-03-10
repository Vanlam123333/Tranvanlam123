<?php
session_start();
require_once __DIR__ . "/db.php";
if (!isset($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['error'=>'Unauthorized']); exit; }

$db->exec("CREATE TABLE IF NOT EXISTS flashcard_history (
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
);");

$uid = $_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? 'save';

if ($action === 'history') {
    $rows = $db->query("SELECT date, topic, COUNT(*) as total, SUM(CASE WHEN rating IN ('good','easy') THEN 1 ELSE 0 END) as known FROM flashcard_history WHERE user_id=$uid GROUP BY date, topic ORDER BY date DESC, id DESC LIMIT 60");
    $data = [];
    while ($r = $rows->fetchArray(SQLITE3_ASSOC)) $data[] = $r;
    echo json_encode(['history' => $data]); exit;
}

if ($action === 'day_detail') {
    $date = SQLite3::escapeString($input['date'] ?? date('Y-m-d'));
    $rows = $db->query("SELECT * FROM flashcard_history WHERE user_id=$uid AND date='$date' ORDER BY id DESC");
    $data = [];
    while ($r = $rows->fetchArray(SQLITE3_ASSOC)) $data[] = $r;
    echo json_encode(['words' => $data]); exit;
}

// action = save
$topic = $input['topic'] ?? '';
$words = $input['words'] ?? [];
$date  = date('Y-m-d');

if (!is_array($words) || count($words) === 0) {
    echo json_encode(['ok' => false]); exit;
}

$stmt = $db->prepare('INSERT INTO flashcard_history (user_id, date, topic, word, phonetic, word_type, meaning, rating) VALUES (:uid, :date, :topic, :word, :phonetic, :type, :meaning, :rating)');
foreach ($words as $w) {
    $stmt->bindValue(':uid',      $uid);
    $stmt->bindValue(':date',     $date);
    $stmt->bindValue(':topic',    $topic);
    $stmt->bindValue(':word',     $w['word'] ?? '');
    $stmt->bindValue(':phonetic', $w['phonetic'] ?? '');
    $stmt->bindValue(':type',     $w['type'] ?? '');
    $stmt->bindValue(':meaning',  $w['meaning'] ?? '');
    $stmt->bindValue(':rating',   $w['rating'] ?? 'ok');
    $stmt->execute();
}

// Award XP
require_once __DIR__ . '/gamification.php';
$wordCount = count($words);
awardXP($uid, 'flashcard', max(5, $wordCount * 2), "Học $wordCount flashcard - $topic");
updateStreak($uid);

echo json_encode(['ok' => true]);
