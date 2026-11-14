<?php

declare(strict_types=1);

function escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function json_response(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    exit;
}

function request_json(): array
{
    $body = file_get_contents('php://input');
    if ($body === false || $body === '') {
        return [];
    }

    try {
        $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        json_response(['error' => 'Dữ liệu JSON không hợp lệ.'], 400);
    }
    if (!is_array($decoded)) {
        json_response(['error' => 'Nội dung yêu cầu phải là một đối tượng JSON.'], 400);
    }
    return $decoded;
}

function require_method(string ...$methods): void
{
    if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', $methods, true)) {
        header('Allow: ' . implode(', ', $methods));
        json_response(['error' => 'Phương thức không được hỗ trợ.'], 405);
    }
}

function require_csrf(): void
{
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!is_string($token) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        json_response(['error' => 'Phiên làm việc hết hạn hoặc CSRF không hợp lệ. Hãy tải lại trang.'], 419);
    }
}

function current_user(): ?array
{
    global $pdo;
    $id = $_SESSION['user_id'] ?? null;
    if (!is_int($id)) {
        return null;
    }
    $statement = $pdo->prepare('SELECT id, email, display_name, avatar_url, role, is_active, activation_token FROM users WHERE id = :id');
    $statement->execute(['id' => $id]);
    $user = $statement->fetch();
    return $user === false ? null : $user;
}

function require_user(): array
{
    $user = current_user();
    if ($user === null) {
        json_response(['error' => 'Vui lòng đăng nhập để tiếp tục.'], 401);
    }
    return $user;
}

function require_login_page(): array
{
    $user = current_user();
    if ($user === null) {
        header('Location: /Modules/Auth/login.php');
        exit;
    }
    return $user;
}

/**
 * AI Summary Helper: Gọi Gemini API nếu có GEMINI_API_KEY, hoặc dùng Extractive NLP Summarizer
 */
function ai_summarize_content(string $content): string
{
    $content = trim(strip_tags($content));
    if ($content === '') {
        return 'Nội dung bài viết quá ngắn để tóm tắt.';
    }

    $apiKey = getenv('GEMINI_API_KEY');
    if ($apiKey !== false && strlen($apiKey) > 10) {
        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key={$apiKey}";
        $prompt = "Hãy tóm tắt bài viết sau một cách súc tích, ngắn gọn trong khoảng 2-3 câu bằng tiếng Việt, giữ nguyên ý chính:\n\n" . mb_substr($content, 0, 4000);
        $payload = json_encode([
            'contents' => [
                ['parts' => [['text' => $prompt]]]
            ]
        ]);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && $response) {
            $data = json_decode($response, true);
            $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
            if ($text) {
                return trim($text);
            }
        }
    }

    // Local extractive summarizer (dự phòng ngoại tuyến / khi không có API key)
    $sentences = preg_split('/(?<=[.?!])\s+/u', $content, -1, PREG_SPLIT_NO_EMPTY);
    if (count($sentences) <= 2) {
        return $content;
    }
    return trim($sentences[0] . ' ' . $sentences[1]);
}

/**
 * AI Q&A Helper: Trả lời câu hỏi dựa trên các bài viết của người dùng
 */
function ai_answer_articles_question(string $question, array $articles): array
{
    $question = trim($question);
    if ($question === '' || empty($articles)) {
        return [
            'answer' => 'Chưa có bài viết nào hoặc câu hỏi rỗng để phân tích.',
            'references' => [],
        ];
    }

    $apiKey = getenv('GEMINI_API_KEY');
    if ($apiKey !== false && strlen($apiKey) > 10) {
        $context = '';
        foreach ($articles as $a) {
            $context .= "[ID: {$a['id']}, Tiêu đề: {$a['title']}]\nNội dung: " . mb_substr(strip_tags($a['content']), 0, 500) . "\n\n";
        }
        $prompt = "Dựa vào các bài viết sau đây của người dùng:\n{$context}\nHãy trả lời câu hỏi sau bằng tiếng Việt: \"{$question}\". Nêu rõ thông tin được trích xuất từ bài viết nào (kèm ID bài viết).";
        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key={$apiKey}";
        $payload = json_encode([
            'contents' => [
                ['parts' => [['text' => $prompt]]]
            ]
        ]);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 12);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && $response) {
            $data = json_decode($response, true);
            $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
            if ($text) {
                // Tìm kiếm reference IDs
                $refs = [];
                foreach ($articles as $a) {
                    if (str_contains($text, (string)$a['id']) || stripos($text, $a['title']) !== false) {
                        $refs[] = ['id' => $a['id'], 'title' => $a['title']];
                    }
                }
                return ['answer' => trim($text), 'references' => $refs];
            }
        }
    }

    // Local heuristic search & synthesis
    $keywords = array_filter(explode(' ', mb_strtolower($question)), fn($w) => mb_strlen($w) > 2);
    $matched = [];
    foreach ($articles as $a) {
        $score = 0;
        $haystack = mb_strtolower($a['title'] . ' ' . $a['content']);
        foreach ($keywords as $kw) {
            if (str_contains($haystack, $kw)) {
                $score++;
            }
        }
        if ($score > 0) {
            $matched[] = ['article' => $a, 'score' => $score];
        }
    }

    usort($matched, fn($x, $y) => $y['score'] <=> $x['score']);

    if (empty($matched)) {
        return [
            'answer' => "Không tìm thấy nội dung liên quan trực tiếp đến câu hỏi \"{$question}\" trong các bài viết đã lưu.",
            'references' => [],
        ];
    }

    $topArticles = array_slice($matched, 0, 3);
    $refs = array_map(fn($m) => ['id' => $m['article']['id'], 'title' => $m['article']['title']], $topArticles);
    $leadText = "Dựa trên nội dung tìm thấy trong bài viết \"" . $topArticles[0]['article']['title'] . "\":\n";
    $excerpt = mb_substr(strip_tags($topArticles[0]['article']['content']), 0, 200) . '...';

    return [
        'answer' => $leadText . $excerpt,
        'references' => $refs,
    ];
}
