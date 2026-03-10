<?php
session_start();
if (!isset($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['error'=>'Unauthorized']); exit; }

header('Content-Type: application/json');
$GROQ_KEY = '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';
$GROQ_URL = 'https://api.groq.com/openai/v1/chat/completions';
$MODEL = 'llama-3.3-70b-versatile';

$input = json_decode(file_get_contents('php://input'), true);
$type = $input['type'] ?? 'chat';

function callGroq($messages, $key, $url, $model, $maxTokens = 1500) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $key
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'model'       => $model,
            'messages'    => $messages,
            'max_tokens'  => $maxTokens,
            'temperature' => 0.7
        ])
    ]);
    $res  = curl_exec($ch);
    $errno = curl_errno($ch);
    $errmsg = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($errno) {
        return "Lỗi cURL ($errno): $errmsg";
    }
    if ($httpCode !== 200) {
        $errData = json_decode($res, true);
        $detail  = $errData['error']['message'] ?? $res;
        return "Lỗi API (HTTP $httpCode): $detail";
    }
    $data = json_decode($res, true);
    return $data['choices'][0]['message']['content'] ?? 'Lỗi: Không có phản hồi từ AI';
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
    $count = max(1, (int)($input['count'] ?? 10));
    $typeNote = $wordType !== 'tất cả' ? ", chỉ lấy $wordType" : '';
    $messages = [
        ['role'=>'system','content'=>'Bạn là giáo viên tiếng Anh. CHỈ trả về JSON array thuần túy, không giải thích thêm.'],
        ['role'=>'user','content'=>"Tạo $count flashcard từ vựng tiếng Anh chủ đề \"$topic\", trình độ $level$typeNote.
Mỗi card: word (từ tiếng Anh), phonetic (phiên âm IPA), type (noun/verb/adj/adv), meaning (nghĩa tiếng Việt ngắn), example (câu ví dụ tiếng Anh tự nhiên), example_vi (dịch tiếng Việt).
CHỈ JSON: [{\"word\":\"...\",\"phonetic\":\"...\",\"type\":\"...\",\"meaning\":\"...\",\"example\":\"...\",\"example_vi\":\"...\"}]"]
    ];
    $raw = callGroq($messages, $GROQ_KEY, $GROQ_URL, $MODEL, 6000);
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
    $raw = callGroq($messages, $GROQ_KEY, $GROQ_URL, $MODEL, 6000);
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
    $result = callGroq($messages, $GROQ_KEY, $GROQ_URL, $MODEL);
    // Nếu trả về lỗi (không phải HTML), bọc vào div để hiển thị đẹp
    if (strpos($result, 'Lỗi') === 0) {
        $result = '<div class="step"><div class="step-num" style="color:var(--red)">⚠️ Lỗi</div>' . htmlspecialchars($result) . '</div>';
    }
    echo json_encode(['result' => $result]);

} elseif ($type === 'mindmap_content') {
    $content = mb_substr(trim($input['content'] ?? ''), 0, 6000); // max 6000 chars
    $depth   = max(1, min(10, (int)($input['depth'] ?? 3)));

    $depthGuide = $depth <= 2
        ? "Chỉ tạo nhánh chính, KHÔNG có children."
        : "Tạo cây $depth cấp lồng nhau. Mỗi node có 3-5 con. Càng sâu càng cụ thể.";

    $system = <<<'SYS'
Bạn là chuyên gia phân tích và tóm tắt nội dung học thuật. Nhiệm vụ: đọc văn bản người dùng cung cấp, tự động xác định chủ đề chính, rồi tạo sơ đồ tư duy (mind map) CÓ NỘI DUNG THỰC CHẤT từ chính văn bản đó.

QUY TẮC:
1. Đọc và HIỂU nội dung → xác định chủ đề trung tâm → phân tách thành các nhóm ý chính.
2. Nhánh chính = các ý lớn/chủ đề con thực sự có trong văn bản.
3. Nhánh con = thông tin CỤ THỂ trích từ nội dung (định nghĩa, số liệu, tên gọi, ví dụ thật).
4. KHÔNG bịa thêm thông tin ngoài văn bản.
5. Tên node ngắn gọn (2-5 từ), giữ nguyên thuật ngữ quan trọng.
6. Trả về 2 thứ trong JSON: "title" (tên chủ đề tự xác định) và "tree" (cây mind map).
7. CHỈ JSON thuần túy, KHÔNG markdown, KHÔNG giải thích.
Schema: {"title":"Tên chủ đề","tree":{"name":"Tên chủ đề","children":[...]}}
SYS;

    $messages = [
        ['role' => 'system', 'content' => $system],
        ['role' => 'user',   'content' =>
            "Nội dung cần phân tích:\n\n" . $content . "\n\n" .
            "Cấu trúc yêu cầu: $depthGuide\n" .
            "CHỈ JSON, không text thêm."
        ]
    ];

    $raw   = callGroq($messages, $GROQ_KEY, $GROQ_URL, $MODEL, 4000);
    $clean = preg_replace('/```json|```/i', '', $raw);
    $s     = strpos($clean, '{');
    $e     = strrpos($clean, '}');
    if ($s === false) {
        echo json_encode(['error' => 'AI không trả về JSON. Thử lại hoặc rút ngắn nội dung.']);
        exit;
    }
    $result = json_decode(substr($clean, $s, $e - $s + 1), true);
    if (!$result) {
        echo json_encode(['error' => 'Lỗi parse JSON. Thử lại.']);
        exit;
    }
    // Hỗ trợ cả 2 format: {title, tree} hoặc trực tiếp {name, children}
    if (isset($result['tree'])) {
        echo json_encode(['tree' => $result['tree'], 'title' => $result['title'] ?? '']);
    } elseif (isset($result['name'])) {
        echo json_encode(['tree' => $result, 'title' => $result['name'] ?? '']);
    } else {
        echo json_encode(['error' => 'Cấu trúc JSON không hợp lệ.']);
    }

} elseif ($type === 'mindmap') {
    $topic = $input['topic'] ?? '';
    $depth = max(1, min(10, (int)($input['depth'] ?? 2)));

    $depthGuide = $depth <= 2
        ? "Tạo nhánh chính (4-6 nhánh), mỗi nhánh có " . ($depth == 1 ? "KHÔNG có children." : "3-5 nhánh con cụ thể.")
        : "Tạo cây $depth cấp lồng nhau. Mỗi node có 3-5 con. Càng sâu càng cụ thể và chi tiết hơn. Đào sâu đến mức chi tiết nhất có thể (số liệu, công thức, ví dụ cụ thể, tên gọi chính xác).";

    $system = <<<'SYS'
Bạn là chuyên gia giáo dục và tri thức bách khoa. Nhiệm vụ: tạo sơ đồ tư duy (mind map) có nội dung THỰC CHẤT, CHÍNH XÁC về chủ đề được yêu cầu.

QUY TẮC BẮT BUỘC:
1. Phân tích sâu chủ đề: nếu là bài học Sinh/Toán/Sử/Lý/Hóa → dùng đúng thuật ngữ, kiến thức thật của bài đó.
2. Nhánh chính = các KHÍA CẠNH/CHỦ ĐỀ CON thực sự của nội dung (VD: "Định nghĩa", "Cấu tạo", "Chức năng", "Phân loại", "Ví dụ", "Ứng dụng").
3. Nhánh con = thông tin CỤ THỂ, CHÍNH XÁC (tên, số liệu, khái niệm thật). KHÔNG dùng từ chung chung như "Phương pháp", "Giải pháp", "Tư duy", "Phát triển".
4. Tên node: ngắn gọn (2-5 từ), đúng thuật ngữ chuyên ngành.
5. CHỈ trả về JSON thuần túy, KHÔNG có markdown, KHÔNG có text ngoài JSON.
6. JSON schema: {"name":"Tên chủ đề","children":[{"name":"Nhánh chính","children":[{"name":"Chi tiết cụ thể"},...]},...]}
SYS;

    $messages = [
        ['role' => 'system', 'content' => $system],
        ['role' => 'user',   'content' =>
            "Chủ đề: \"$topic\"\n\n" .
            "Cấu trúc yêu cầu: $depthGuide\n\n" .
            "Hãy phân tích chủ đề này và tạo mind map với nội dung THỰC CHẤT:\n" .
            "- Nếu là bài học (Sinh 10, Toán 11...): dùng đúng kiến thức của bài đó\n" .
            "- Nếu là khái niệm khoa học: dùng định nghĩa, thành phần, cơ chế thật\n" .
            "- Nếu là sự kiện lịch sử: dùng mốc thời gian, nhân vật, nguyên nhân thật\n" .
            "- Nếu là kỹ năng/công nghệ: dùng các bước, công cụ, ứng dụng thật\n\n" .
            "CHỈ JSON, không giải thích thêm."
        ]
    ];

    $raw   = callGroq($messages, $GROQ_KEY, $GROQ_URL, $MODEL, 4000);
    $clean = preg_replace('/```json|```/i', '', $raw);
    $s     = strpos($clean, '{');
    $e     = strrpos($clean, '}');
    if ($s === false) {
        echo json_encode(['error' => 'AI không trả về JSON hợp lệ. Phản hồi: ' . mb_substr($raw, 0, 200)]);
        exit;
    }
    $tree = json_decode(substr($clean, $s, $e - $s + 1), true);
    if (!$tree || !isset($tree['name'])) {
        echo json_encode(['error' => 'JSON không hợp lệ. Thử lại với chủ đề cụ thể hơn.']);
        exit;
    }
    echo json_encode(['tree' => $tree]);

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
} elseif ($type === 'study_insight') {
    $done = (int)($input['done'] ?? 0);
    $total = (int)($input['total'] ?? 0);
    $pomo = (int)($input['pomo'] ?? 0);
    $hour = (int)date('H');
    $tod = $hour < 12 ? 'buổi sáng' : ($hour < 17 ? 'buổi chiều' : 'buổi tối');
    $messages = [
        ['role'=>'system','content'=>'Bạn là Spark, trợ lý học tập AI thân thiện. Đưa ra lời khuyên ngắn gọn, truyền cảm hứng, bằng tiếng Việt. 2-3 câu, dùng emoji phù hợp, thực tế không sáo rỗng.'],
        ['role'=>'user','content'=>"Tôi đang học vào $tod. Đã hoàn thành $done/$total nhiệm vụ. Học $pomo pomodoro hôm nay. Cho tôi 1 lời khuyên ngắn gọn."]
    ];
    echo json_encode(['result' => callGroq($messages, $GROQ_KEY, $GROQ_URL, $MODEL, 200)]);

} elseif ($type === 'improve_note') {
    $note = mb_substr(trim($input['note'] ?? ''), 0, 3000);
    $messages = [
        ['role'=>'system','content'=>'Bạn là chuyên gia giáo dục. Cải thiện ghi chú học tập: thêm tiêu đề rõ ràng, bullet points, highlight từ khóa quan trọng. Trả lời bằng tiếng Việt.'],
        ['role'=>'user','content'=>"Cải thiện ghi chú này:\n\n$note"]
    ];
    echo json_encode(['result' => callGroq($messages, $GROQ_KEY, $GROQ_URL, $MODEL, 1500)]);

} elseif ($type === 'quiz_from_note') {
    $note = mb_substr(trim($input['note'] ?? ''), 0, 3000);
    $messages = [
        ['role'=>'system','content'=>'Tạo 5 câu hỏi trắc nghiệm từ nội dung ghi chú. CHỈ JSON array thuần túy.'],
        ['role'=>'user','content'=>"Tạo quiz từ:\n$note\nJSON: [{\"question\":\"...\",\"options\":[\"A...\",\"B...\",\"C...\",\"D...\"],\"answer\":0,\"explain\":\"...\"}]"]
    ];
    $raw = callGroq($messages, $GROQ_KEY, $GROQ_URL, $MODEL);
    $clean = preg_replace('/```json|```/', '', $raw);
    $s = strpos($clean,'['); $e = strrpos($clean,']');
    $qs = $s!==false ? json_decode(substr($clean,$s,$e-$s+1), true) : [];
    echo json_encode(['questions' => $qs ?? []]);


} elseif ($type === 'math_analyze') {
    $expr = $input['expr'] ?? '';
    $messages = [
        ['role'=>'system','content'=>'Bạn là giáo viên toán cấp 3 Việt Nam. Phân tích hàm số ngắn gọn, rõ ràng bằng tiếng Việt.'],
        ['role'=>'user','content'=>"Phân tích hàm số f(x) = $expr. Nêu: 1. Tập xác định  2. Đơn điệu  3. Cực trị  4. Tiệm cận  5. Nhận xét. Ngắn gọn dễ hiểu cho học sinh cấp 3."]
    ];
    echo json_encode(['result' => callGroq($messages, $GROQ_KEY, $GROQ_URL, $MODEL, 2000)]);

} elseif ($type === 'math_image') {
    $imageBase64 = $input['image'] ?? '';
    if (!$imageBase64) { echo json_encode(['result'=>'Không có ảnh.']); exit; }
    $ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer '.$GROQ_KEY,'Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode([
            'model' => 'llama-3.2-11b-vision-preview',
            'max_tokens' => 3000,
            'messages' => [['role'=>'user','content'=>[
                ['type'=>'image_url','image_url'=>['url'=>'data:image/jpeg;base64,'.$imageBase64]],
                ['type'=>'text','text'=>'Đây là bài toán cấp 3. Đọc đề và giải từng bước chi tiết bằng tiếng Việt. Trình bày rõ ràng.']
            ]]]
        ]),
        CURLOPT_TIMEOUT => 60
    ]);
    $raw = curl_exec($ch); curl_close($ch);
    $json = json_decode($raw, true);
    $result = $json['choices'][0]['message']['content'] ?? 'Không thể đọc ảnh. Thử ảnh rõ hơn!';
    echo json_encode(['result' => $result]);

} elseif ($type === 'smart_plan') {
    $done = (int)($input['done'] ?? 0);
    $total = (int)($input['total'] ?? 0);
    $pomo = (int)($input['pomo'] ?? 0);
    $hour = (int)date('H');
    $messages = [
        ['role'=>'system','content'=>'Bạn là chuyên gia quản lý thời gian. Tạo kế hoạch học ngắn gọn, thực tế với emoji. Bằng tiếng Việt.'],
        ['role'=>'user','content'=>"Bây giờ là $hour giờ. Đã làm $done/$total nhiệm vụ, học $pomo pomodoro. Tạo kế hoạch học từ giờ đến tối với khung giờ pomodoro 25 phút. Ngắn gọn."]
    ];
    echo json_encode(['result' => callGroq($messages, $GROQ_KEY, $GROQ_URL, $MODEL, 500)]);
}
?>
