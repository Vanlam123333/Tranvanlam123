<?php
// translator.php — Real-time translation endpoint
session_start();
if (!isset($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['error'=>'Unauthorized']); exit; }
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$text  = trim($input['text'] ?? '');
$from  = $input['from'] ?? 'auto';
$to    = $input['to']   ?? 'vi';

if (!$text) { echo json_encode(['ok'=>false,'msg'=>'No text']); exit; }

// Use the existing Groq API
$GROQ_KEY = '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';
$GROQ_URL = 'https://api.groq.com/openai/v1/chat/completions';

$langNames = [
  'vi'=>'tiếng Việt', 'en'=>'English', 'zh'=>'Chinese',
  'ja'=>'Japanese', 'ko'=>'Korean', 'fr'=>'French',
  'es'=>'Spanish', 'de'=>'German', 'th'=>'Thai',
];
$targetLang = $langNames[$to] ?? 'Vietnamese';

$ch = curl_init($GROQ_URL);
curl_setopt_array($ch,[
    CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,
    CURLOPT_TIMEOUT=>15,CURLOPT_SSL_VERIFYPEER=>false,CURLOPT_SSL_VERIFYHOST=>false,
    CURLOPT_HTTPHEADER=>['Content-Type: application/json','Authorization: Bearer '.$GROQ_KEY],
    CURLOPT_POSTFIELDS=>json_encode([
        'model'=>'llama-3.3-70b-versatile',
        'max_tokens'=>300,
        'temperature'=>0.1,
        'messages'=>[
            ['role'=>'system','content'=>"You are a translator. Translate the given text to $targetLang. Return ONLY the translation, no explanations, no quotes."],
            ['role'=>'user','content'=>$text]
        ]
    ])
]);
$res=curl_exec($ch);
$errno=curl_errno($ch);
curl_close($ch);

if($errno){echo json_encode(['ok'=>false,'msg'=>'Network error']);exit;}

$data=json_decode($res,true);
$translated=$data['choices'][0]['message']['content']??'';
$translated=trim($translated);

// Save to DB if message context provided
if(!empty($input['msg_id'])){
    require_once __DIR__.'/db.php';
    $mid=(int)$input['msg_id'];
    @$db->exec("ALTER TABLE room_messages ADD COLUMN translated TEXT");
    $st=$db->prepare('UPDATE room_messages SET translated=:t WHERE id=:id');
    $st->bindValue(':t',$translated);$st->bindValue(':id',$mid);
    $st->execute();
}

echo json_encode(['ok'=>true,'original'=>$text,'translated'=>$translated,'to'=>$to]);
