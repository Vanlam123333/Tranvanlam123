<?php
session_start();
if (!isset($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['error'=>'Unauthorized']); exit; }

header('Content-Type: application/json');
$GROQ_KEY = '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';
$GROQ_URL = 'https://api.groq.com/openai/v1/chat/completions';
$MODEL = 'llama-3.3-70b-versatile';

$input = json_decode(file_get_contents('php://input'), true);
$type = $input['type'] ?? 'chat';

function callGroq($messages, $key, $url, $model) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $key
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'model' => $model,
            'messages' => $messages,
            'max_tokens' => 1500,
            'temperature' => 0.7
        ])
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    $data = json_decode($res, true);
    return $data['choices'][0]['message']['content'] ?? 'Lỗi từ AI';
}

if ($type === 'chat') {
    $messages = $input['messages'] ?? [];
    array_unshift($messages, ['role'=>'system', 'content'=>'Bạn là gia sư AI giỏi mọi môn học. Trả lời bằng tiếng Việt, giải thích từng bước rõ ràng, dùng ví dụ cụ thể.']);
    echo json_encode(['result' => callGroq($messages, $GROQ_KEY, $GROQ_URL, $MODEL)]);

} elseif ($type === 'summarize') {
    $text = $input['text'] ?? '';
    $mode = $input['mode'] ?? 'brief';
    $prompts = [
        'brief' => 'Tóm tắt thành 5-7 điểm chính quan trọng nhất dùng gạch đầu dòng (•).',
        'detail' => 'Tóm tắt chi tiết theo cấu trúc: Tổng quan → Nội dung chính → Kết luận.',
        'mindmap' => "Tạo sơ đồ tư duy:\n🎯 CHỦ ĐỀ CHÍNH\n├─ Nhánh 1\n│  └─ Chi tiết\n└─ Nhánh 2",
    ];
    $messages = [
        ['role'=>'system','content'=>'Chuyên gia tóm tắt tài liệu học thuật bằng tiếng Việt.'],
        ['role'=>'user','content'=>"Tài liệu:\n\n".substr($text,0,4000)."\n\n".$prompts[$mode]]
    ];
    echo json_encode(['result' => callGroq($messages, $GROQ_KEY, $GROQ_URL, $MODEL)]);

} elseif ($type === 'flashcard') {
    $topic = $input['topic'] ?? '';
    $messages = [
        ['role'=>'system','content'=>'Tạo flashcard học tập. CHỈ trả về JSON array thuần túy.'],
        ['role'=>'user','content'=>"Tạo 8 flashcard về \"$topic\".\nJSON: [{\"front\":\"câu hỏi\",\"back\":\"đáp án\"}]"]
    ];
    $raw = callGroq($messages, $GROQ_KEY, $GROQ_URL, $MODEL);
    $clean = preg_replace('/```json|```/', '', $raw);
    $s = strpos($clean,'['); $e = strrpos($clean,']');
    $cards = json_decode(substr($clean,$s,$e-$s+1), true) ?? [];
    echo json_encode(['cards' => $cards]);

} elseif ($type === 'flashcard_en') {
    $topic = $input['topic'] ?? '';
    $level = $input['level'] ?? 'B1-B2';
    $wordType = $input['wordType'] ?? 'tất cả';
    $typeNote = $wordType !== 'tất cả' ? ", chỉ lấy $wordType" : '';
    $messages = [
        ['role'=>'system','content'=>'Bạn là giáo viên tiếng Anh. CHỈ trả về JSON array thuần túy, không giải thích thêm.'],
        ['role'=>'user','content'=>"Tạo 10 flashcard từ vựng tiếng Anh chủ đề \"$topic\", trình độ $level$typeNote.
Mỗi card: word (từ tiếng Anh), phonetic (phiên âm IPA), type (noun/verb/adj/adv), meaning (nghĩa tiếng Việt ngắn), example (câu ví dụ tiếng Anh tự nhiên), example_vi (dịch tiếng Việt).
CHỈ JSON: [{\"word\":\"...\",\"phonetic\":\"...\",\"type\":\"...\",\"meaning\":\"...\",\"example\":\"...\",\"example_vi\":\"...\"}]"]
    ];
    $raw = callGroq($messages, $GROQ_KEY, $GROQ_URL, $MODEL);
    $clean = preg_replace('/```json|```/', '', $raw);
    $s = strpos($clean,'['); $e = strrpos($clean,']');
    $cards = json_decode(substr($clean,$s,$e-$s+1), true) ?? [];
    echo json_encode(['cards' => $cards]);

} elseif ($type === 'flashcard_list') {
    $words = $input['words'] ?? '';
    $messages = [
        ['role'=>'system','content'=>'Bạn là giáo viên tiếng Anh. CHỈ trả về JSON array thuần túy, không giải thích thêm.'],
        ['role'=>'user','content'=>"Tra từ điển và tạo flashcard cho các từ tiếng Anh sau:\n$words\n
Mỗi card: word (từ tiếng Anh đúng chính tả), phonetic (phiên âm IPA), type (noun/verb/adj/adv), meaning (nghĩa tiếng Việt ngắn gọn), example (câu ví dụ tiếng Anh), example_vi (dịch tiếng Việt).
Nếu từ đã có nghĩa kèm theo thì dùng nghĩa đó.
CHỈ JSON: [{\"word\":\"...\",\"phonetic\":\"...\",\"type\":\"...\",\"meaning\":\"...\",\"example\":\"...\",\"example_vi\":\"...\"}]"]
    ];
    $raw = callGroq($messages, $GROQ_KEY, $GROQ_URL, $MODEL);
    $clean = preg_replace('/```json|```/', '', $raw);
    $s = strpos($clean,'['); $e = strrpos($clean,']');
    $cards = json_decode(substr($clean,$s,$e-$s+1), true) ?? [];
    echo json_encode(['cards' => $cards]);

} elseif ($type === 'math_solve') {
    $problem = $input['problem'] ?? '';
    $solveType = $input['solveType'] ?? 'free';
    $typePrompts = [
        'pt'   => 'Giải phương trình sau từng bước chi tiết.',
        'bpt'  => 'Giải bất phương trình sau từng bước chi tiết, tìm tập nghiệm.',
        'dao'  => 'Tính đạo hàm của hàm số sau, trình bày từng bước.',
        'tich' => 'Tính tích phân sau, trình bày từng bước.',
        'luong'=> 'Giải phương trình lượng giác sau từng bước.',
        'free' => 'Giải bài toán sau từng bước chi tiết.',
    ];
    $prompt = $typePrompts[$solveType] ?? $typePrompts['free'];
    $messages = [
        ['role'=>'system','content'=>'Bạn là giáo viên Toán cấp 3 giỏi. Giải toán từng bước rõ ràng bằng tiếng Việt. Dùng LaTeX cho công thức: inline dùng $...$, display dùng $$...$$. Mỗi bước đặt trong thẻ <div class="step"><div class="step-num">Bước N</div>nội dung</div>. Kết quả cuối đặt trong <div class="step" style="border-color:rgba(52,211,153,0.4);"><div class="step-num" style="color:var(--green)">✅ Kết quả</div>nội dung</div>'],
        ['role'=>'user','content'=>"$prompt\n\nBài toán: $problem"]
    ];
    echo json_encode(['result' => callGroq($messages, $GROQ_KEY, $GROQ_URL, $MODEL)]);

} elseif ($type === 'quiz') {
    $topic = $input['topic'] ?? '';
    $level = $input['level'] ?? 1;
    $diff = ['1'=>'cơ bản','2'=>'trung bình','3'=>'nâng cao'][$level] ?? 'cơ bản';
    $messages = [
        ['role'=>'system','content'=>'Giáo viên ra đề trắc nghiệm. CHỈ trả về JSON thuần.'],
        ['role'=>'user','content'=>"Câu hỏi về \"$topic\", độ khó $diff.\nJSON: {\"question\":\"...\",\"options\":[\"A....\",\"B....\",\"C....\",\"D....\"],\"answer\":0,\"explain\":\"...\"}"]
    ];
    $raw = callGroq($messages, $GROQ_KEY, $GROQ_URL, $MODEL);
    $clean = preg_replace('/```json|```/', '', $raw);
    $s = strpos($clean,'{'); $e = strrpos($clean,'}');
    $q = json_decode(substr($clean,$s,$e-$s+1), true) ?? [];
    echo json_encode(['question' => $q]);
}
?>
