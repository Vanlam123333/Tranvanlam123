<?php
// save_quiz.php
session_start();
require_once __DIR__ . "/db.php";
if (!isset($_SESSION['user_id'])) { http_response_code(401); exit; }
require_once 'db.php';
$uid = $_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true);
$topic = $input['topic'] ?? '';
$score = (int)($input['score'] ?? 0);
$total = (int)($input['total'] ?? 0);
$level = (int)($input['level'] ?? 1);
if ($topic && $total > 0) {
    $stmt = $db->prepare('INSERT INTO quiz_results (user_id, topic, score, total) VALUES (:uid, :topic, :score, :total)');
    $stmt->bindValue(':uid', $uid); $stmt->bindValue(':topic', $topic);
    $stmt->bindValue(':score', $score); $stmt->bindValue(':total', $total);
    $stmt->execute();
}
echo json_encode(['ok' => true]);
