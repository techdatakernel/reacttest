<?php
/**
 * AEO 분석 엔진 (통합 개선 버전 v10 - Final)
 * 파일명: aeo_analyzer_unified_1121_v10.php
 * 타이틀: v9 + 기존 시스템 JSON 구조 완벽 호환 (상세보기 지원)
 * 요약: 기존/신규 두 가지 JSON 구조 동시 지원 - 완벽한 하위 호환성
 */

set_time_limit(120);

define('OPENAI_API_KEY', 'xxx');
define('DATA_DIR', __DIR__ . '/aeo_data');
define('MAX_TOKENS', 3000);
define('API_TIMEOUT', 45);
define('BM25_MAX_SCORE', 40);
define('SEMANTIC_MAX_SCORE', 48);
define('FAQ_MAX_SCORE', 20);

if (!is_dir(DATA_DIR)) mkdir(DATA_DIR, 0755, true);

$models = [
    'gpt-4-turbo-preview' => ['name' => 'GPT-4 Turbo', 'speed' => '빠름'],
    'gpt-4' => ['name' => 'GPT-4', 'speed' => '느림'],
    'gpt-3.5-turbo' => ['name' => 'GPT-3.5 Turbo', 'speed' => '매우빠름']
];

function callOpenAIAPI($systemPrompt, $userPrompt, $model = 'gpt-4-turbo-preview', $temperature = 0.7, $retryCount = 0) {
    if ($retryCount > 1) {
        return ['error' => 'API 호출 실패', 'elapsed_time' => 0];
    }
    
    $url = 'https://api.openai.com/v1/chat/completions';
    
    $payload = [
        'model' => $model,
        'max_tokens' => MAX_TOKENS,
        'temperature' => (float)$temperature,
        'top_p' => 0.9,
        'messages' => [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userPrompt]
        ]
    ];
    
    $startTime = microtime(true);
    
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . OPENAI_API_KEY
        ],
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => API_TIMEOUT,
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $elapsedTime = round((microtime(true) - $startTime) * 1000, 2);
    
    if (!$response || $httpCode !== 200) {
        if ($retryCount < 1) {
            sleep(1);
            return callOpenAIAPI($systemPrompt, $userPrompt, $model, $temperature, $retryCount + 1);
        }
        return ['error' => "API 오류 ($httpCode)", 'elapsed_time' => $elapsedTime];
    }
    
    $data = json_decode($response, true);
    
    if (!isset($data['choices'][0]['message']['content'])) {
        return ['error' => '응답 파싱 실패', 'elapsed_time' => $elapsedTime];
    }
    
    return [
        'content' => $data['choices'][0]['message']['content'],
        'model' => $model,
        'elapsed_time' => $elapsedTime,
        'temperature' => $temperature
    ];
}

function fetchPageContent($url) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_USERAGENT => 'Mozilla/5.0',
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    
    $content = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if (!$content || $httpCode !== 200) {
        return null;
    }
    
    $dom = new DOMDocument();
    @$dom->loadHTML(mb_convert_encoding($content, 'HTML-ENTITIES', 'UTF-8'));
    $texts = [];
    
    foreach ($dom->getElementsByTagName('p') as $p) {
        $text = trim($p->textContent);
        if (strlen($text) > 20) $texts[] = $text;
    }
    
    foreach ($dom->getElementsByTagName('h1') as $h) {
        $text = trim($h->textContent);
        if (strlen($text) > 5) $texts[] = "[H1] $text";
    }
    
    foreach ($dom->getElementsByTagName('h2') as $h) {
        $text = trim($h->textContent);
        if (strlen($text) > 5) $texts[] = "[H2] $text";
    }
    
    foreach ($dom->getElementsByTagName('h3') as $h) {
        $text = trim($h->textContent);
        if (strlen($text) > 5) $texts[] = "[H3] $text";
    }
    
    return implode("\n", array_slice($texts, 0, 30));
}

function safeJsonDecode($json, $default = []) {
    $json = preg_replace('/```json\s*/i', '', $json);
    $json = preg_replace('/```\s*/i', '', $json);
    $json = trim($json);
    
    $decoded = json_decode($json, true);
    return ($decoded === null) ? $default : $decoded;
}

// ✅ v2: BM25 분석 (상세 버전)
function analyzeBM25($query, $content, $model, $temperature) {
    $systemPrompt = <<<PROMPT
AEO 분석 전문가. BM25 키워드 분석. JSON만 반환. 마크다운 금지.
PROMPT;

    $userPrompt = <<<PROMPT
질문: "$query"
콘텐츠(30줄):
$content

BM25 키워드 분석. JSON 반환:
{
  "keywords": [
    {"keyword": "키워드", "tf": 빈도, "idf_estimate": 추정IDF, "bm25_score": 점수, "relevance": "높음/중간/낮음"}
  ],
  "total_score": 35,
  "strengths": "강점 설명",
  "weaknesses": "약점 설명"
}
PROMPT;

    $result = callOpenAIAPI($systemPrompt, $userPrompt, $model, $temperature);
    
    if (isset($result['error'])) {
        return [
            'keywords' => [],
            'total_score' => 0,
            'strengths' => '분석 불가',
            'weaknesses' => '분석 불가',
            'api_metadata' => ['time_ms' => $result['elapsed_time']]
        ];
    }
    
    $parsed = safeJsonDecode($result['content'], [
        'keywords' => [],
        'total_score' => 0,
        'strengths' => '분석 불가',
        'weaknesses' => '분석 불가'
    ]);
    
    if (isset($parsed['total_score'])) {
        $parsed['total_score'] = round(min(BM25_MAX_SCORE, max(0, (float)$parsed['total_score'])), 1);
    }
    
    return array_merge($parsed, ['api_metadata' => ['time_ms' => $result['elapsed_time']]]);
}

// ✅ v2: 시맨틱 분석 (상세 버전)
function analyzeSemanticSimilarity($query, $content, $model, $temperature) {
    $systemPrompt = <<<PROMPT
AEO 분석 전문가. 시맨틱 유사도 분석. JSON만 반환. 마크다운 금지.
PROMPT;

    $userPrompt = <<<PROMPT
질문: "$query"
콘텐츠(30줄):
$content

시맨틱 분석. JSON 반환:
{
  "topic_match": {"score": 9, "reason": "주제 일치 이유"},
  "semantic_relevance": {"score": 8, "reason": "의미 연관성 이유"},
  "context_understanding": {"score": 7, "reason": "맥락 이해 이유"},
  "information_completeness": {"score": 8, "reason": "정보 충족도 이유"},
  "total_score": 38,
  "strengths": "강점 설명",
  "weaknesses": "약점 설명"
}
PROMPT;

    $result = callOpenAIAPI($systemPrompt, $userPrompt, $model, $temperature);
    
    if (isset($result['error'])) {
        return [
            'topic_match' => ['score' => 0, 'reason' => '분석 불가'],
            'semantic_relevance' => ['score' => 0, 'reason' => '분석 불가'],
            'context_understanding' => ['score' => 0, 'reason' => '분석 불가'],
            'information_completeness' => ['score' => 0, 'reason' => '분석 불가'],
            'total_score' => 0,
            'strengths' => '분석 불가',
            'weaknesses' => '분석 불가',
            'api_metadata' => ['time_ms' => $result['elapsed_time']]
        ];
    }
    
    $parsed = safeJsonDecode($result['content'], [
        'topic_match' => ['score' => 0, 'reason' => '분석 불가'],
        'semantic_relevance' => ['score' => 0, 'reason' => '분석 불가'],
        'context_understanding' => ['score' => 0, 'reason' => '분석 불가'],
        'information_completeness' => ['score' => 0, 'reason' => '분석 불가'],
        'total_score' => 0,
        'strengths' => '분석 불가',
        'weaknesses' => '분석 불가'
    ]);
    
    if (isset($parsed['total_score'])) {
        $parsed['total_score'] = round(min(SEMANTIC_MAX_SCORE, max(0, (float)$parsed['total_score'])), 1);
    }
    
    return array_merge($parsed, ['api_metadata' => ['time_ms' => $result['elapsed_time']]]);
}

// ✅ v8: FAQ 분석 (효율적인 버전)
function analyzeFAQStructure($query, $content, $model, $temperature) {
    $systemPrompt = <<<PROMPT
AEO FAQ 분석 전문가. JSON만 반환. 마크다운 금지.
PROMPT;

    $userPrompt = <<<PROMPT
질문: "$query"
콘텐츠(30줄):
$content

FAQ/Q&A 구조 분석. JSON 반환:
{
  "has_faq_format": true,
  "faq_score": 15,
  "ai_friendliness_score": 14,
  "structure_analysis": "페이지에 Q&A 형식 있음. 질문은 명확하나 답변이 짧음",
  "recommendation": "더 구체적인 답변 필요. 추가 질문 3-5개 권장",
  "priority": "필수"
}

채점 기준:
- FAQ 점수 (0-20점): FAQ/Q&A 형식 존재 여부 및 품질
- AI 친화성 점수 (0-20점): AI가 이해하고 인용하기 쉬운 구조
- priority: 필수/권장/선택
PROMPT;

    $result = callOpenAIAPI($systemPrompt, $userPrompt, $model, $temperature);
    
    if (isset($result['error'])) {
        return [
            'has_faq_format' => false,
            'faq_score' => 0,
            'ai_friendliness_score' => 0,
            'structure_analysis' => '분석 불가',
            'recommendation' => 'FAQ 형식 추가 권장',
            'priority' => '권장',
            'api_metadata' => ['time_ms' => $result['elapsed_time']]
        ];
    }
    
    $parsed = safeJsonDecode($result['content'], [
        'has_faq_format' => false,
        'faq_score' => 0,
        'ai_friendliness_score' => 0,
        'structure_analysis' => '분석 불가',
        'recommendation' => 'FAQ 형식 추가 권장',
        'priority' => '권장'
    ]);
    
    if (isset($parsed['faq_score'])) {
        $parsed['faq_score'] = round(min(FAQ_MAX_SCORE, max(0, (float)$parsed['faq_score'])), 1);
    }
    if (isset($parsed['ai_friendliness_score'])) {
        $parsed['ai_friendliness_score'] = round(min(FAQ_MAX_SCORE, max(0, (float)$parsed['ai_friendliness_score'])), 1);
    }
    
    return array_merge($parsed, ['api_metadata' => ['time_ms' => $result['elapsed_time']]]);
}

// ✅ v2: AEO 권고사항
function generateAEORecommendations($query, $content, $bm25Score, $semanticScore, $faqScore, $model, $temperature) {
    $systemPrompt = <<<PROMPT
AEO 최적화 전문가. JSON만 반환. 마크다운 금지.
PROMPT;

    $userPrompt = <<<PROMPT
질문: "$query"
콘텐츠(30줄):
$content
BM25: {$bm25Score}/40
시맨틱: {$semanticScore}/48
FAQ: {$faqScore}/20

AEO 권고. JSON 반환:
{
  "missing_info": [
    {"item": "누락 항목", "reason": "필요 이유", "effect": "추가 시 예상 효과"}
  ],
  "action_items": [
    {"action": "실행 액션", "reason": "필요 이유", "expected_result": "예상 결과"}
  ],
  "expected_score_increase": {"bm25": 5, "semantic": 8, "faq": 3}
}
PROMPT;

    $result = callOpenAIAPI($systemPrompt, $userPrompt, $model, $temperature);
    
    if (isset($result['error'])) {
        return [
            'missing_info' => [],
            'action_items' => [],
            'expected_score_increase' => ['bm25' => 0, 'semantic' => 0, 'faq' => 0],
            'api_metadata' => ['time_ms' => $result['elapsed_time']]
        ];
    }
    
    $parsed = safeJsonDecode($result['content'], [
        'missing_info' => [],
        'action_items' => [],
        'expected_score_increase' => ['bm25' => 0, 'semantic' => 0, 'faq' => 0]
    ]);
    
    return array_merge($parsed, ['api_metadata' => ['time_ms' => $result['elapsed_time']]]);
}

// 통합 분석 실행
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $query = $_POST['query'] ?? '';
    $url = $_POST['url'] ?? '';
    $model = $_POST['model'] ?? 'gpt-4-turbo-preview';
    $temperature = (float)($_POST['temperature'] ?? 0.7);
    
    header('Content-Type: application/json; charset=utf-8');
    
    if (empty($query) || empty($url)) {
        echo json_encode(['error' => '질문과 URL을 입력해주세요'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    $startTime = microtime(true);
    
    $content = fetchPageContent($url);
    if (!$content) {
        echo json_encode(['error' => 'URL 콘텐츠를 가져올 수 없습니다'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // 분석 실행
    $bm25Data = analyzeBM25($query, $content, $model, $temperature);
    $semanticData = analyzeSemanticSimilarity($query, $content, $model, $temperature);
    $faqData = analyzeFAQStructure($query, $content, $model, $temperature);
    $recommendationsData = generateAEORecommendations(
        $query, 
        $content, 
        $bm25Data['total_score'] ?? 0,
        $semanticData['total_score'] ?? 0,
        $faqData['faq_score'] ?? 0,
        $model, 
        $temperature
    );
    
    $totalScore = ($bm25Data['total_score'] ?? 0) + 
                  ($semanticData['total_score'] ?? 0) + 
                  ($faqData['faq_score'] ?? 0);
    
    $hybridScore = round(($totalScore / 108) * 100, 1);
    
    $rating = $hybridScore >= 90 ? '우수' : 
             ($hybridScore >= 75 ? '양호' : 
             ($hybridScore >= 60 ? '보통' : '미흡'));
    
    $totalTime = round((microtime(true) - $startTime) * 1000, 2);
    
    // 고유 ID 생성 (기존 형식과 동일)
    $uniqueId = md5($query . $url . microtime());
    
    $result = [
        'id' => $uniqueId,
        'query' => $query,
        'url' => $url,
        'model' => $model,
        'temperature' => $temperature,
        'timestamp' => date('Y-m-d H:i:s'),
        'date' => date('Y-m-d'),
        'query_type' => '', // 사용자가 직접 입력 (aeo_data_manager에서 수정 가능)
        'scores' => [
            'bm25' => $bm25Data['total_score'] ?? 0,
            'semantic' => $semanticData['total_score'] ?? 0,
            'faq' => $faqData['faq_score'] ?? 0,
            'total' => $totalScore,
            'hybrid' => $hybridScore,
            'rating' => $rating
        ],
        // 기존 형식 호환 (상세보기용)
        'bm25' => $bm25Data,
        'semantic' => $semanticData,
        'faq' => $faqData,
        'aeo_recommendations' => $recommendationsData,
        // 새 형식 (v9 호환)
        'bm25_analysis' => $bm25Data,
        'semantic_analysis' => $semanticData,
        'faq_analysis' => $faqData,
        'recommendations' => $recommendationsData,
        'processing_time' => [
            'total_ms' => $totalTime,
            'bm25_ms' => $bm25Data['api_metadata']['time_ms'] ?? 0,
            'semantic_ms' => $semanticData['api_metadata']['time_ms'] ?? 0,
            'faq_ms' => $faqData['api_metadata']['time_ms'] ?? 0,
            'recommendations_ms' => $recommendationsData['api_metadata']['time_ms'] ?? 0
        ]
    ];
    
    // JSON 파일 저장 (기존 형식과 동일)
    $dateDir = DATA_DIR . '/' . date('Y-m-d');
    if (!is_dir($dateDir)) mkdir($dateDir, 0755, true);
    
    $filename = date('Y-m-d') . '_' . substr($uniqueId, 0, 8) . '.json';
    file_put_contents("$dateDir/$filename", json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    
    // index.json 업데이트
    $indexFile = DATA_DIR . '/index.json';
    $index = [];
    
    if (file_exists($indexFile)) {
        $indexContent = file_get_contents($indexFile);
        $index = json_decode($indexContent, true) ?? [];
    }
    
    // index에 새 항목 추가
    $index[$uniqueId] = [
        'id' => $uniqueId,
        'query' => $query,
        'url' => $url,
        'hybrid_score' => $hybridScore,
        'evaluation' => $rating,
        'timestamp' => date('Y-m-d H:i:s'),
        'date' => date('Y-m-d'),
        'query_type' => '', // 사용자가 직접 입력
        'model' => $model,
        'temperature' => $temperature
    ];
    
    file_put_contents($indexFile, json_encode($index, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AEO 분석 엔진 (v10 - Final)</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 0;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 40px 20px;
        }
        
        .header {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 32px;
            color: #2c3e50;
            margin-bottom: 10px;
        }
        
        .header p {
            color: #7f8c8d;
            font-size: 14px;
        }
        
        .form-card {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .form-group input, .form-group select {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: #667eea;
        }
        
        button {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 14px 28px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }
        
        button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        #loading {
            display: none;
            text-align: center;
            padding: 40px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        
        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #667eea;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        #result {
            display: none;
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        
        .score-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        
        .score-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 12px;
            text-align: center;
        }
        
        .score-card h3 {
            font-size: 14px;
            margin-bottom: 10px;
            opacity: 0.9;
        }
        
        .score-card .score {
            font-size: 32px;
            font-weight: bold;
        }
        
        .report-section {
            margin: 30px 0;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid #667eea;
        }
        
        .report-header {
            font-size: 18px;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 15px;
        }
        
        .keyword-table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        
        .keyword-table th, .keyword-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .keyword-table th {
            background: #667eea;
            color: white;
            font-weight: 600;
        }
        
        .keyword-table tr:hover {
            background: #f8f9fa;
        }
        
        .recommendation-item {
            background: white;
            padding: 15px;
            margin: 10px 0;
            border-radius: 8px;
            border-left: 3px solid #27ae60;
        }
        
        .recommendation-item strong {
            color: #27ae60;
        }
        
        .info-box {
            background: #e8f4f8;
            padding: 15px;
            border-radius: 8px;
            margin: 10px 0;
            border-left: 3px solid #3498db;
        }
        
        .warning-box {
            background: #fff3cd;
            padding: 15px;
            border-radius: 8px;
            margin: 10px 0;
            border-left: 3px solid #ffc107;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🚀 AEO 분석 엔진 (v10 - Final)</h1>
            <p>완벽한 기존 시스템 호환 (상세보기 지원)</p>
        </div>
        
        <div class="form-card">
            <form id="analysisForm">
                <div class="form-group">
                    <label>질문 (Query)</label>
                    <input type="text" name="query" placeholder="예: 한맥에 맞는 디저트 추천해줘" required>
                </div>
                
                <div class="form-group">
                    <label>분석할 URL</label>
                    <input type="url" name="url" placeholder="https://example.com/page" required>
                </div>
                
                <div class="form-group">
                    <label>AI 모델</label>
                    <select name="model">
                        <?php foreach ($models as $key => $info): ?>
                            <option value="<?= $key ?>"><?= $info['name'] ?> (<?= $info['speed'] ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Temperature (0.0 - 1.0)</label>
                    <input type="number" name="temperature" value="0.7" min="0" max="1" step="0.1">
                </div>
                
                <button type="submit" style="width: 100%; margin-top: 20px; padding: 16px;">⚡ 분석 시작</button>
            </form>
        </div>
        
        <div id="loading">
            <div class="spinner"></div>
            <p style="color: #7f8c8d; font-size: 16px;">분석 진행 중... (약 20-30초 소요)</p>
        </div>
        
        <div id="result"></div>
    </div>
    
    <script>
        document.getElementById('analysisForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const form = e.target;
            const formData = new FormData(form);
            
            document.getElementById('loading').style.display = 'block';
            document.getElementById('result').style.display = 'none';
            form.querySelector('button').disabled = true;
            
            try {
                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.error) {
                    alert('오류: ' + data.error);
                    return;
                }
                
                displayResult(data);
            } catch (error) {
                alert('분석 중 오류가 발생했습니다: ' + error.message);
            } finally {
                document.getElementById('loading').style.display = 'none';
                form.querySelector('button').disabled = false;
            }
        });
        
        function displayResult(data) {
            const resultDiv = document.getElementById('result');
            
            let html = `
                <h2 style="margin-bottom: 20px;">📊 분석 결과</h2>
                
                <div class="info-box">
                    <strong>질문:</strong> ${data.query}<br>
                    <strong>URL:</strong> <a href="${data.url}" target="_blank">${data.url}</a><br>
                    <strong>분석 시간:</strong> ${data.timestamp}<br>
                    <strong>처리 시간:</strong> ${data.processing_time.total_ms}ms
                </div>
                
                <div class="score-grid">
                    <div class="score-card">
                        <h3>BM25 키워드</h3>
                        <div class="score">${data.scores.bm25}/${<?= BM25_MAX_SCORE ?>}</div>
                    </div>
                    <div class="score-card">
                        <h3>시맨틱 유사도</h3>
                        <div class="score">${data.scores.semantic}/${<?= SEMANTIC_MAX_SCORE ?>}</div>
                    </div>
                    <div class="score-card">
                        <h3>FAQ 구조</h3>
                        <div class="score">${data.scores.faq}/${<?= FAQ_MAX_SCORE ?>}</div>
                    </div>
                    <div class="score-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                        <h3>종합 점수</h3>
                        <div class="score">${data.scores.hybrid}/100</div>
                        <div style="font-size: 14px; margin-top: 5px;">${data.scores.rating}</div>
                    </div>
                </div>
                
                <!-- BM25 분석 -->
                <div class="report-section">
                    <div class="report-header">🔤 BM25 키워드 분석</div>
                    
                    <table class="keyword-table">
                        <thead>
                            <tr>
                                <th>키워드</th>
                                <th>빈도(TF)</th>
                                <th>IDF 추정</th>
                                <th>BM25 점수</th>
                                <th>관련성</th>
                            </tr>
                        </thead>
                        <tbody>
            `;
            
            if (data.bm25_analysis.keywords && data.bm25_analysis.keywords.length > 0) {
                data.bm25_analysis.keywords.forEach(kw => {
                    html += `
                        <tr>
                            <td><strong>${kw.keyword || '-'}</strong></td>
                            <td>${kw.tf || 0}</td>
                            <td>${kw.idf_estimate || 0}</td>
                            <td>${kw.bm25_score || 0}</td>
                            <td>${kw.relevance || '-'}</td>
                        </tr>
                    `;
                });
            } else {
                html += '<tr><td colspan="5" style="text-align: center;">키워드 데이터 없음</td></tr>';
            }
            
            html += `
                        </tbody>
                    </table>
                    
                    <div style="margin-top: 15px;">
                        <div class="info-box">
                            <strong>✓ 강점:</strong> ${data.bm25_analysis.strengths || '-'}
                        </div>
                        <div class="warning-box">
                            <strong>✗ 개선 필요:</strong> ${data.bm25_analysis.weaknesses || '-'}
                        </div>
                    </div>
                </div>
                
                <!-- 시맨틱 분석 -->
                <div class="report-section">
                    <div class="report-header">🧠 시맨틱 유사도 분석</div>
                    
                    <div style="margin: 15px 0;">
                        <div style="background: white; padding: 15px; border-radius: 8px; margin-bottom: 10px;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                <strong>1️⃣ 주제 일치도</strong>
                                <span style="font-size: 20px; color: #667eea; font-weight: bold;">${data.semantic_analysis.topic_match?.score || 0}점</span>
                            </div>
                            <div style="color: #666; font-size: 14px;">${data.semantic_analysis.topic_match?.reason || '-'}</div>
                        </div>
                        
                        <div style="background: white; padding: 15px; border-radius: 8px; margin-bottom: 10px;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                <strong>2️⃣ 의미 연관성</strong>
                                <span style="font-size: 20px; color: #667eea; font-weight: bold;">${data.semantic_analysis.semantic_relevance?.score || 0}점</span>
                            </div>
                            <div style="color: #666; font-size: 14px;">${data.semantic_analysis.semantic_relevance?.reason || '-'}</div>
                        </div>
                        
                        <div style="background: white; padding: 15px; border-radius: 8px; margin-bottom: 10px;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                <strong>3️⃣ 맥락 이해도</strong>
                                <span style="font-size: 20px; color: #667eea; font-weight: bold;">${data.semantic_analysis.context_understanding?.score || 0}점</span>
                            </div>
                            <div style="color: #666; font-size: 14px;">${data.semantic_analysis.context_understanding?.reason || '-'}</div>
                        </div>
                        
                        <div style="background: white; padding: 15px; border-radius: 8px; margin-bottom: 10px;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                <strong>4️⃣ 정보 충족도</strong>
                                <span style="font-size: 20px; color: #667eea; font-weight: bold;">${data.semantic_analysis.information_completeness?.score || 0}점</span>
                            </div>
                            <div style="color: #666; font-size: 14px;">${data.semantic_analysis.information_completeness?.reason || '-'}</div>
                        </div>
                    </div>
                    
                    <div style="margin-top: 15px;">
                        <div class="info-box">
                            <strong>✓ 강점:</strong> ${data.semantic_analysis.strengths || '-'}
                        </div>
                        <div class="warning-box">
                            <strong>✗ 개선 필요:</strong> ${data.semantic_analysis.weaknesses || '-'}
                        </div>
                    </div>
                </div>
                
                <!-- FAQ 분석 -->
                <div class="report-section">
                    <div class="report-header">❓ FAQ/Q&A 구조 분석</div>
                    
                    <div style="margin: 15px 0;">
                        <div style="background: white; padding: 15px; border-radius: 8px; margin-bottom: 10px;">
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                                <div>
                                    <div style="font-size: 12px; color: #666; margin-bottom: 8px;"><strong>FAQ 점수</strong></div>
                                    <div style="font-size: 28px; color: #667eea; font-weight: bold;">${data.faq_analysis.faq_score || 0}/${<?= FAQ_MAX_SCORE ?>}</div>
                                </div>
                                <div>
                                    <div style="font-size: 12px; color: #666; margin-bottom: 8px;"><strong>AI 친화성 점수</strong></div>
                                    <div style="font-size: 28px; color: #667eea; font-weight: bold;">${data.faq_analysis.ai_friendliness_score || 0}/${<?= FAQ_MAX_SCORE ?>}</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="info-box">
                            <strong>📋 구조 분석:</strong><br>
                            ${data.faq_analysis.structure_analysis || '-'}
                        </div>
                        
                        <div class="recommendation-item">
                            <strong>💡 개선 권고:</strong><br>
                            ${data.faq_analysis.recommendation || '-'}
                            <div style="margin-top: 8px; padding: 8px; background: #f8f9fa; border-radius: 4px;">
                                <strong>우선순위:</strong> ${data.faq_analysis.priority || '-'}
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- AEO 권고사항 -->
                <div class="report-section">
                    <div class="report-header">💡 AEO 개선 권고사항</div>
                    
                    <h4 style="color: #e74c3c; margin: 20px 0 10px;">📌 누락된 정보</h4>
            `;
            
            if (data.recommendations.missing_info && data.recommendations.missing_info.length > 0) {
                data.recommendations.missing_info.forEach(item => {
                    html += `
                        <div class="recommendation-item">
                            <strong>${item.item || '-'}</strong><br>
                            <div style="color: #666; margin-top: 5px;">
                                <strong>필요 이유:</strong> ${item.reason || '-'}<br>
                                <strong>예상 효과:</strong> ${item.effect || '-'}
                            </div>
                        </div>
                    `;
                });
            } else {
                html += '<p style="color: #7f8c8d;">권고사항 없음</p>';
            }
            
            html += `
                    <h4 style="color: #27ae60; margin: 20px 0 10px;">🎯 실행 액션</h4>
            `;
            
            if (data.recommendations.action_items && data.recommendations.action_items.length > 0) {
                data.recommendations.action_items.forEach(item => {
                    html += `
                        <div class="recommendation-item">
                            <strong>${item.action || '-'}</strong><br>
                            <div style="color: #666; margin-top: 5px;">
                                <strong>필요 이유:</strong> ${item.reason || '-'}<br>
                                <strong>예상 결과:</strong> ${item.expected_result || '-'}
                            </div>
                        </div>
                    `;
                });
            } else {
                html += '<p style="color: #7f8c8d;">실행 액션 없음</p>';
            }
            
            html += `
                    <h4 style="color: #3498db; margin: 20px 0 10px;">📈 예상 점수 증가</h4>
                    <div style="background: white; padding: 15px; border-radius: 8px;">
                        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px;">
                            <div>
                                <div style="font-size: 12px; color: #666;">BM25</div>
                                <div style="font-size: 24px; color: #27ae60; font-weight: bold;">+${data.recommendations.expected_score_increase?.bm25 || 0}</div>
                            </div>
                            <div>
                                <div style="font-size: 12px; color: #666;">시맨틱</div>
                                <div style="font-size: 24px; color: #27ae60; font-weight: bold;">+${data.recommendations.expected_score_increase?.semantic || 0}</div>
                            </div>
                            <div>
                                <div style="font-size: 12px; color: #666;">FAQ</div>
                                <div style="font-size: 24px; color: #27ae60; font-weight: bold;">+${data.recommendations.expected_score_increase?.faq || 0}</div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            resultDiv.innerHTML = html;
            resultDiv.style.display = 'block';
            resultDiv.scrollIntoView({ behavior: 'smooth' });
        }
    </script>
</body>
</html>