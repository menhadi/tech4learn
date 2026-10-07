<?php
header('Content-Type: application/json');
error_reporting(0);

$host = 'localhost';
$user = 'examelite';
$pass = 'FATIMA@hasan7';
$dbname = 'examelite';

$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'DB connection failed']);
    exit;
}

// Handle different operations
$input = json_decode(file_get_contents('php://input'), true);
$stat_id = $input['stat_id'] ?? null;
$bulk = $input['bulk'] ?? false;
$get_pending = $input['get_pending'] ?? false;
$get_recent = $input['get_recent'] ?? false;

// Get pending count
if ($get_pending) {
    $sql = "SELECT COUNT(*) as pending FROM exam_stats es
            JOIN questions q ON q.id = es.question_id
            JOIN qtypes qt ON qt.id = q.qtype_id
            WHERE qt.type = 'S' 
            AND es.answer IS NOT NULL 
            AND es.answer != ''
            AND es.ai_assessed = 0";
    $result = $conn->query($sql);
    $row = $result->fetch_assoc();
    echo json_encode(['pending' => $row['pending']]);
    exit;
}

// Get recent assessments
if ($get_recent) {
    $sql = "SELECT es.id, LEFT(es.answer, 50) as answer, es.ai_chatgpt_score, es.ai_assessed 
            FROM exam_stats es
            JOIN questions q ON q.id = es.question_id
            JOIN qtypes qt ON qt.id = q.qtype_id
            WHERE qt.type = 'S'
            ORDER BY es.id DESC
            LIMIT 10";
    $result = $conn->query($sql);
    $recent = [];
    while ($row = $result->fetch_assoc()) {
        $recent[] = $row;
    }
    echo json_encode(['recent' => $recent]);
    exit;
}

// Bulk assessment
if ($bulk || $stat_id === 'all') {
    $sql = "SELECT es.id FROM exam_stats es
            JOIN questions q ON q.id = es.question_id
            JOIN qtypes qt ON qt.id = q.qtype_id
            WHERE qt.type = 'S' 
            AND es.answer IS NOT NULL 
            AND es.answer != ''
            AND es.ai_assessed = 0";
    $result = $conn->query($sql);
    $stat_ids = [];
    while ($row = $result->fetch_assoc()) {
        $stat_ids[] = $row['id'];
    }
    
    $results = [];
    foreach ($stat_ids as $id) {
        $results[] = assessSingle($conn, $id);
        sleep(1); // Rate limiting
    }
    
    echo json_encode(['success' => true, 'total' => count($results), 'results' => $results]);
    exit;
}

// Single assessment
if (!$stat_id) {
    echo json_encode(['success' => false, 'message' => 'stat_id required']);
    exit;
}

$result = assessSingle($conn, $stat_id);
echo json_encode($result);
exit;

function assessSingle($conn, $stat_id) {
    // Check if subjective
    $checkSql = "SELECT qt.type FROM exam_stats es
                JOIN questions q ON q.id = es.question_id
                JOIN qtypes qt ON qt.id = q.qtype_id
                WHERE es.id = ?";
    $stmt = $conn->prepare($checkSql);
    $stmt->bind_param("i", $stat_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $check = $result->fetch_assoc();
    
    if (!$check) {
        return ['success' => false, 'message' => 'Record not found', 'stat_id' => $stat_id];
    }
    
    if ($check['type'] !== 'S') {
        return ['success' => false, 'message' => 'Not a subjective question', 'stat_id' => $stat_id];
    }
    
    // Get answer details
    $sql = "SELECT es.*, q.question, q.marks, q.si_answer1 as model_answer 
            FROM exam_stats es 
            JOIN questions q ON q.id = es.question_id 
            WHERE es.id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $stat_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $stat = $result->fetch_assoc();
    
    if (!$stat || empty($stat['answer'])) {
        return ['success' => false, 'message' => 'No answer found', 'stat_id' => $stat_id];
    }
    
    // Get API key
    $configSql = "SELECT openai_api_key FROM configurations WHERE id = 4";
    $configResult = $conn->query($configSql);
    $config = $configResult->fetch_assoc();
    $apiKey = $config['openai_api_key'] ?? null;
    
    if (!$apiKey) {
        return ['success' => false, 'message' => 'API key not found', 'stat_id' => $stat_id];
    }
    
    $questionText = $stat['question'];
    $studentAnswer = $stat['answer'];
    $maxMarks = floatval($stat['marks'] ?? 1);
    
    // Call ChatGPT
    $aiAnswer = callChatGPT($apiKey, "Answer in 2-3 sentences: $questionText");
    $scoreResponse = callChatGPT($apiKey, "Score out of $maxMarks. My answer: $aiAnswer. Student: $studentAnswer. Return ONLY number.");
    preg_match('/\d+(?:\.\d+)?/', $scoreResponse, $matches);
    $score = isset($matches[0]) ? min(floatval($matches[0]), $maxMarks) : 0;
    
    // Update database
    $updateSql = "UPDATE exam_stats SET 
        ai_chatgpt_answer = ?,
        ai_chatgpt_score = ?,
        ai_average_score = ?,
        marks_obtained = ?,
        ai_assessed = 1
        WHERE id = ?";
    $stmt = $conn->prepare($updateSql);
    $stmt->bind_param("sdddi", $aiAnswer, $score, $score, $score, $stat_id);
    $stmt->execute();
    
    // Update exam total
    $examResultSql = "SELECT exam_result_id FROM exam_stats WHERE id = ?";
    $stmt = $conn->prepare($examResultSql);
    $stmt->bind_param("i", $stat_id);
    $stmt->execute();
    $examResult = $stmt->get_result();
    $examRow = $examResult->fetch_assoc();
    $examResultId = $examRow['exam_result_id'] ?? 0;
    
    if ($examResultId) {
        $totalSql = "SELECT SUM(marks_obtained) as total FROM exam_stats WHERE exam_result_id = ?";
        $stmt = $conn->prepare($totalSql);
        $stmt->bind_param("i", $examResultId);
        $stmt->execute();
        $totalResult = $stmt->get_result();
        $totalRow = $totalResult->fetch_assoc();
        $total = $totalRow['total'] ?? 0;
        
        $updateExamSql = "UPDATE exam_results SET obtained_marks = ? WHERE id = ?";
        $stmt = $conn->prepare($updateExamSql);
        $stmt->bind_param("di", $total, $examResultId);
        $stmt->execute();
    }
    
    return [
        'success' => true,
        'stat_id' => $stat_id,
        'score' => $score,
        'max_marks' => $maxMarks
    ];
}

function callChatGPT($apiKey, $prompt) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api.openai.com/v1/chat/completions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'model' => 'gpt-3.5-turbo',
        'messages' => [['role' => 'user', 'content' => $prompt]],
        'temperature' => 0.3,
        'max_tokens' => 500
    ]));
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $response = curl_exec($ch);
    curl_close($ch);
    $data = json_decode($response, true);
    return trim($data['choices'][0]['message']['content'] ?? '');
}
?>
