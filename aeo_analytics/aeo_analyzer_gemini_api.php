<?php
/**
 * AEO 분석 엔진 (Gemini API 버전 v7)
 * 파일명: aeo_analyzer_gemini_api_1127_v3.php
 *
 * [수정사항 v7 - 2025-11-27]
 * 1. 파일명 형식 수정: 2025-11-26_3901231cd6... → 2025-11-26_3901231c.json (8자리)
 * 2. Gemini 1.5 Pro 모델 제거 (deprecated) → gemini-2.0-flash 계열만 유지
 * 3. 결과 표시 형식 개선 (aeo_analyzer_optimized_1122_v2.php 스타일 적용)
 * 4. HTML 응답 에러 처리 개선
 * 5. index.json 업데이트 로직 강화
 */

set_time_limit(180);
error_reporting(E_ALL);
ini_set('display_errors', 0);

// ▼▼▼ API 키를 여기에 입력하세요 ▼▼▼
define('GEMINI_API_KEY', 'APIKye-Here'); 
// ▲▲▲ API 키를 여기에 입력하세요 ▲▲▲

define('DATA_DIR', __DIR__ . '/aeo_data_gemini');
define('CACHE_DIR', DATA_DIR . '/cache');
define('LOG_DIR', DATA_DIR . '/logs');
define('MAX_TOKENS', 8000);
define('API_TIMEOUT', 120);
define('BM25_MAX_SCORE', 40);
define('SEMANTIC_MAX_SCORE', 48);
define('FAQ_MAX_SCORE', 20);
define('CACHE_TTL', 86400);

if (!is_dir(DATA_DIR)) mkdir(DATA_DIR, 0755, true);
if (!is_dir(CACHE_DIR)) mkdir(CACHE_DIR, 0755, true);
if (!is_dir(LOG_DIR)) mkdir(LOG_DIR, 0755, true);

// 모델 목록 - v7 업데이트 (Gemini 1.5 Pro 제거, 현재 사용 가능한 모델만)
$models = [
    'gemini-2.0-flash-thinking-exp-01-21' => ['name' => 'Gemini 2.0 Thinking', 'speed' => '느림(추론)', 'cost' => '무료(Exp)', 'quality' => '최상(추론형)'],
    'gemini-2.0-flash-exp' => ['name' => 'Gemini 2.0 Flash', 'speed' => '매우 빠름', 'cost' => '무료(Exp)', 'quality' => '양호'],
    'gemini-2.0-flash' => ['name' => 'Gemini 2.0 Flash (Stable)', 'speed' => '빠름', 'cost' => '유료', 'quality' => '우수']
];

// ========================================
// 로깅
// ========================================

function writeLog($message, $level = 'INFO') {
    $logFile = LOG_DIR . '/' . date('Y-m-d') . '.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] [$level] $message\n", FILE_APPEND);
}

// ========================================
// 유틸리티
// ========================================

function getCacheKey($query, $url, $model, $temperature) {
    return md5($query . $url . $model . $temperature . 'v7_stable');
}

function getCachedResult($cacheKey) {
    if (!$cacheKey) return null;
    $cacheFile = CACHE_DIR . '/' . $cacheKey . '.json';
    if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < CACHE_TTL) {
        $cached = json_decode(file_get_contents($cacheFile), true);
        if ($cached && isset($cached['scores']['total']) && $cached['scores']['total'] > 0) {
            $cached['_from_cache'] = true;
            $cached['_cache_age_seconds'] = time() - filemtime($cacheFile);
            return $cached;
        }
    }
    return null;
}

function saveCache($cacheKey, $data) {
    if (!$cacheKey) return;
    if (isset($data['scores']['total']) && $data['scores']['total'] == 0) {
        return;
    }
    $cacheFile = CACHE_DIR . '/' . $cacheKey . '.json';
    file_put_contents($cacheFile, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

// v7: ID 생성 - 8자리 해시
function generateId($query, $url) {
    $fullHash = md5($query . $url . microtime(true) . rand(1000, 9999));
    return substr($fullHash, 0, 8); // 8자리만 사용
}

function getEvaluation($score) {
    if ($score >= 90) return '우수';
    if ($score >= 70) return '양호';
    if ($score >= 50) return '보통';
    return '미흡';
}

// ========================================
// 페이지 콘텐츠 가져오기
// ========================================

function fetchPageContent($url) {
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36\r\n",
            'timeout' => 30
        ],
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]
    ]);
    
    $html = @file_get_contents($url, false, $context);
    if ($html === false) {
        writeLog("URL 콘텐츠 가져오기 실패: $url", 'ERROR');
        return null;
    }
    
    $encoding = mb_detect_encoding($html, ['UTF-8', 'EUC-KR', 'ISO-8859-1'], true);
    if ($encoding !== 'UTF-8') {
        $html = mb_convert_encoding($html, 'UTF-8', $encoding);
    }
    
    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
    libxml_clear_errors();
    
    $xpath = new DOMXPath($dom);
    
    foreach (['script', 'style', 'noscript', 'iframe', 'nav', 'footer', 'header'] as $tag) {
        $nodes = $xpath->query("//{$tag}");
        foreach ($nodes as $node) {
            $node->parentNode->removeChild($node);
        }
    }
    
    $metaDesc = '';
    $metaNodes = $xpath->query("//meta[@name='description']/@content");
    if ($metaNodes->length > 0) {
        $metaDesc = $metaNodes->item(0)->nodeValue;
    }
    
    $titles = [];
    foreach (['h1', 'h2', 'h3'] as $tag) {
        $nodes = $xpath->query("//{$tag}");
        foreach ($nodes as $node) {
            $text = trim($node->textContent);
            if ($text) $titles[] = "[$tag] $text";
        }
    }
    
    $bodyText = [];
    $textNodes = $xpath->query("//p | //li | //td | //span[not(ancestor::script)] | //div[not(child::div)]");
    foreach ($textNodes as $node) {
        $text = trim(preg_replace('/\s+/', ' ', $node->textContent));
        if (mb_strlen($text) > 10) {
            $bodyText[] = $text;
        }
    }
    
    $content = "[Meta] " . $metaDesc . "\n\n";
    $content .= implode("\n", $titles) . "\n\n";
    $content .= implode("\n", array_slice($bodyText, 0, 50));
    
    return mb_substr($content, 0, MAX_TOKENS * 2);
}

// ========================================
// Gemini API 호출 (v7 개선)
// ========================================

function callGeminiAPI($prompt, $model, $temperature = 0.7) {
    $startTime = microtime(true);
    $isThinkingModel = (strpos($model, 'thinking') !== false);
    
    $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . GEMINI_API_KEY;
    
    $jsonInstruction = $isThinkingModel 
        ? "\n\n중요: 반드시 순수한 JSON 형식으로만 응답하세요. 마크다운, HTML, 설명 텍스트 없이 오직 JSON만 출력하세요."
        : "";
    
    $data = [
        'contents' => [[
            'parts' => [['text' => $prompt . $jsonInstruction]]
        ]],
        'generationConfig' => [
            'temperature' => $temperature,
            'maxOutputTokens' => 4096
        ]
    ];
    
    if (!$isThinkingModel) {
        $data['generationConfig']['responseMimeType'] = 'application/json';
    }
    
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT => API_TIMEOUT,
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    $elapsed = round((microtime(true) - $startTime) * 1000, 2);
    
    if ($curlError) {
        writeLog("CURL 에러: $curlError (모델: $model)", 'ERROR');
        throw new Exception("CURL 에러: $curlError");
    }
    
    if ($httpCode !== 200) {
        $errorData = json_decode($response, true);
        $errorMsg = $errorData['error']['message'] ?? "HTTP $httpCode";
        writeLog("API 오류 (HTTP $httpCode): $errorMsg (모델: $model)", 'ERROR');
        throw new Exception("API 오류 (HTTP $httpCode): $errorMsg");
    }
    
    $result = json_decode($response, true);
    
    if (!isset($result['candidates'][0]['content']['parts'][0]['text'])) {
        writeLog("API 응답 형식 오류 (모델: $model)", 'ERROR');
        throw new Exception("API 응답 형식이 올바르지 않습니다");
    }
    
    $text = $result['candidates'][0]['content']['parts'][0]['text'];
    
    return [
        'text' => $text,
        'time_ms' => $elapsed
    ];
}

// ========================================
// JSON 안전 파싱 (v7 개선)
// ========================================

function safeJsonDecode($text, $default = []) {
    if (preg_match('/^[\s]*<(!doctype|html|head|body)/i', trim($text))) {
        writeLog("HTML 응답 감지됨 (파싱 실패)", 'WARNING');
        return $default;
    }
    
    $text = preg_replace('/```json\s*/i', '', $text);
    $text = preg_replace('/```\s*$/m', '', $text);
    $text = trim($text);
    
    if (preg_match('/\{[\s\S]*\}/m', $text, $matches)) {
        $text = $matches[0];
    }
    
    $decoded = json_decode($text, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        writeLog("JSON 파싱 실패: " . json_last_error_msg() . " | 원문 앞부분: " . mb_substr($text, 0, 200), 'WARNING');
        return $default;
    }
    
    return $decoded;
}

// ========================================
// 분석 프롬프트
// ========================================

function getBm25Prompt($query, $content) {
    return <<<PROMPT
당신은 BM25 키워드 매칭 분석 전문가입니다.

질문: {$query}

콘텐츠:
{$content}

위 콘텐츠를 분석하여 BM25 관점에서 키워드 매칭 점수를 평가하세요.

다음 JSON 형식으로만 응답하세요 (다른 텍스트 없이):
{
    "keywords": [
        {"keyword": "키워드1", "tf": "상/중/하", "position": "제목/본문/메타", "rarity": "높음/중간/낮음", "score": 1-10, "reasoning": "분석 근거"}
    ],
    "total_score": 0-40,
    "strengths": "강점 분석",
    "weaknesses": "약점 분석"
}
PROMPT;
}

function getSemanticPrompt($query, $content) {
    return <<<PROMPT
당신은 시맨틱 분석 전문가입니다.

질문: {$query}

콘텐츠:
{$content}

다음 4가지 관점에서 시맨틱 관련성을 평가하세요:
1. 주제 일치도 (topic_match): 0-12점
2. 의미적 관련성 (semantic_relevance): 0-12점
3. 맥락 이해도 (context_understanding): 0-12점
4. 정보 충족도 (information_completeness): 0-12점

다음 JSON 형식으로만 응답하세요:
{
    "topic_match": {"score": 0-12, "reason": "분석"},
    "semantic_relevance": {"score": 0-12, "reason": "분석"},
    "context_understanding": {"score": 0-12, "reason": "분석"},
    "information_completeness": {"score": 0-12, "reason": "분석"},
    "total_score": 0-48,
    "strengths": "강점",
    "weaknesses": "약점"
}
PROMPT;
}

function getFaqPrompt($query, $content) {
    return <<<PROMPT
당신은 FAQ 구조 분석 전문가입니다.

질문: {$query}

콘텐츠:
{$content}

FAQ 형식의 구조화 정도를 분석하세요.

다음 JSON 형식으로만 응답하세요:
{
    "has_faq_format": true/false,
    "faq_score": 0-20,
    "ai_friendliness_score": 0-10,
    "structure_analysis": "구조 분석",
    "recommendation": "개선 권장사항",
    "priority": "필수/권장/선택"
}
PROMPT;
}

function getQueryExpansionPrompt($query) {
    return <<<PROMPT
다음 질문에 대해 검색 확장 쿼리 5개를 생성하세요:
질문: {$query}

다음 JSON 형식으로만 응답하세요:
{
    "expansions": [
        {"query": "확장 쿼리1", "relevance": "높음/중간"},
        {"query": "확장 쿼리2", "relevance": "높음/중간"}
    ]
}
PROMPT;
}

function getRelevanceEvidencePrompt($query, $content) {
    return <<<PROMPT
질문: {$query}
콘텐츠: {$content}

질문과 가장 관련성 높은 문장 3개를 추출하세요.

다음 JSON 형식으로만 응답하세요:
{
    "evidence": [
        {"passage": "관련 문장", "keyword_relevance": 1-10, "semantic_relevance": 1-10}
    ]
}
PROMPT;
}

function getRecommendationsPrompt($query, $scores) {
    $scoresJson = json_encode($scores, JSON_UNESCAPED_UNICODE);
    return <<<PROMPT
질문: {$query}
현재 점수: {$scoresJson}

개선 권장사항을 제시하세요.

다음 JSON 형식으로만 응답하세요:
{
    "missing_info": [{"item": "누락 정보", "reason": "이유", "effect": "영향"}],
    "action_items": [{"action": "조치", "reason": "이유", "expected_result": "기대 결과"}],
    "expected_score_increase": {"bm25": 0-10, "semantic": 0-10, "faq": 0-10}
}
PROMPT;
}

// ========================================
// 메인 분석 함수
// ========================================

function runAnalysis($query, $url, $model, $temperature) {
    global $models;
    
    $analysisId = generateId($query, $url); // 8자리 ID
    $timestamp = date('Y-m-d H:i:s');
    $dateFolder = date('Y-m-d');
    
    writeLog("분석 시작 - ID: $analysisId, 모델: $model, URL: $url");
    
    // 캐시 확인
    $cacheKey = getCacheKey($query, $url, $model, $temperature);
    $cached = getCachedResult($cacheKey);
    if ($cached) {
        writeLog("캐시 히트 - ID: $analysisId");
        return $cached;
    }
    
    // 콘텐츠 가져오기
    $content = fetchPageContent($url);
    if (!$content) {
        throw new Exception("URL 콘텐츠를 가져올 수 없습니다");
    }
    
    // 분석 실행
    $results = [
        'id' => $analysisId,
        'query' => $query,
        'url' => $url,
        'model' => $model,
        'temperature' => $temperature,
        'timestamp' => $timestamp,
        'date' => $dateFolder
    ];
    
    $totalTime = 0;
    
    try {
        // BM25 분석
        $bm25Response = callGeminiAPI(getBm25Prompt($query, $content), $model, $temperature);
        $bm25Data = safeJsonDecode($bm25Response['text'], ['total_score' => 0, 'keywords' => [], 'strengths' => '분석 불가', 'weaknesses' => '']);
        $bm25Data['api_metadata'] = ['time_ms' => $bm25Response['time_ms']];
        $results['bm25_analysis'] = $bm25Data;
        $totalTime += $bm25Response['time_ms'];
        
        // 시맨틱 분석
        $semanticResponse = callGeminiAPI(getSemanticPrompt($query, $content), $model, $temperature);
        $semanticData = safeJsonDecode($semanticResponse['text'], ['total_score' => 0, 'strengths' => '분석 불가', 'weaknesses' => '']);
        $semanticData['api_metadata'] = ['time_ms' => $semanticResponse['time_ms']];
        $results['semantic_analysis'] = $semanticData;
        $totalTime += $semanticResponse['time_ms'];
        
        // FAQ 분석
        $faqResponse = callGeminiAPI(getFaqPrompt($query, $content), $model, $temperature);
        $faqData = safeJsonDecode($faqResponse['text'], ['faq_score' => 0, 'ai_friendliness_score' => 0, 'structure_analysis' => '분석 불가', 'recommendation' => '']);
        $faqData['api_metadata'] = ['time_ms' => $faqResponse['time_ms']];
        $results['faq_analysis'] = $faqData;
        $totalTime += $faqResponse['time_ms'];
        
        // 쿼리 확장
        $qeResponse = callGeminiAPI(getQueryExpansionPrompt($query), $model, $temperature);
        $qeData = safeJsonDecode($qeResponse['text'], ['expansions' => []]);
        $qeData['api_metadata'] = ['time_ms' => $qeResponse['time_ms']];
        $results['query_expansion'] = $qeData;
        $totalTime += $qeResponse['time_ms'];
        
        // 관련성 증거
        $reResponse = callGeminiAPI(getRelevanceEvidencePrompt($query, $content), $model, $temperature);
        $reData = safeJsonDecode($reResponse['text'], ['evidence' => []]);
        $reData['api_metadata'] = ['time_ms' => $reResponse['time_ms']];
        $results['relevance_evidence'] = $reData;
        $totalTime += $reResponse['time_ms'];
        
        // 점수 계산
        $bm25Score = min(BM25_MAX_SCORE, intval($bm25Data['total_score'] ?? 0));
        $semanticScore = min(SEMANTIC_MAX_SCORE, intval($semanticData['total_score'] ?? 0));
        $faqScore = min(FAQ_MAX_SCORE, intval($faqData['faq_score'] ?? 0));
        
        $totalScore = $bm25Score + $semanticScore + $faqScore;
        $hybridScore = round(($bm25Score / BM25_MAX_SCORE * 50) + ($semanticScore / SEMANTIC_MAX_SCORE * 50), 1);
        
        $results['scores'] = [
            'bm25' => $bm25Score,
            'semantic' => $semanticScore,
            'faq' => $faqScore,
            'total' => $totalScore,
            'hybrid' => $hybridScore,
            'rating' => getEvaluation($hybridScore)
        ];
        
        // 권장사항
        $recResponse = callGeminiAPI(getRecommendationsPrompt($query, $results['scores']), $model, $temperature);
        $recData = safeJsonDecode($recResponse['text'], ['missing_info' => [], 'action_items' => [], 'expected_score_increase' => ['bm25' => 0, 'semantic' => 0, 'faq' => 0]]);
        $recData['api_metadata'] = ['time_ms' => $recResponse['time_ms']];
        $results['recommendations'] = $recData;
        $totalTime += $recResponse['time_ms'];
        
    } catch (Exception $e) {
        writeLog("분석 중 에러: " . $e->getMessage(), 'ERROR');
        $results['scores'] = [
            'bm25' => 0, 'semantic' => 0, 'faq' => 0,
            'total' => 0, 'hybrid' => 0, 'rating' => '분석 실패'
        ];
        $results['error'] = $e->getMessage();
    }
    
    // 처리 시간
    $results['processing_time'] = [
        'total_ms' => round($totalTime, 2),
        'bm25_ms' => $results['bm25_analysis']['api_metadata']['time_ms'] ?? 0,
        'semantic_ms' => $results['semantic_analysis']['api_metadata']['time_ms'] ?? 0,
        'faq_ms' => $results['faq_analysis']['api_metadata']['time_ms'] ?? 0,
        'query_expansion_ms' => $results['query_expansion']['api_metadata']['time_ms'] ?? 0,
        'relevance_evidence_ms' => $results['relevance_evidence']['api_metadata']['time_ms'] ?? 0,
        'recommendations_ms' => $results['recommendations']['api_metadata']['time_ms'] ?? 0
    ];
    
    $results['_optimization_notes'] = [
        'api_provider' => 'Google Gemini',
        'api_calls' => 'Sequential (Stable)',
        'cache_enabled' => true,
        'cache_ttl' => CACHE_TTL . ' seconds',
        'version' => 'Gemini v7 (Filename Fix + UI Enhanced)'
    ];
    
    // v7: 파일명 형식 수정 - 8자리 ID
    $folderPath = DATA_DIR . '/' . $dateFolder;
    if (!is_dir($folderPath)) mkdir($folderPath, 0755, true);
    
    $filename = "{$dateFolder}_{$analysisId}.json"; // 예: 2025-11-27_a1b2c3d4.json
    $filepath = $folderPath . '/' . $filename;
    file_put_contents($filepath, json_encode($results, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    
    // index.json 업데이트
    try {
        updateIndex($results);
        writeLog("index.json 업데이트 성공 - ID: $analysisId");
    } catch (Exception $e) {
        writeLog("index.json 업데이트 실패: " . $e->getMessage(), 'ERROR');
    }
    
    // 캐시 저장
    saveCache($cacheKey, $results);
    
    writeLog("분석 완료 - ID: $analysisId, 점수: {$results['scores']['total']}");
    
    return $results;
}

// ========================================
// index.json 업데이트
// ========================================

function updateIndex($result) {
    $indexFile = DATA_DIR . '/index.json';
    
    $index = [];
    if (file_exists($indexFile)) {
        $content = file_get_contents($indexFile);
        $index = json_decode($content, true) ?? [];
    }
    
    $index[$result['id']] = [
        'id' => $result['id'],
        'query' => $result['query'],
        'url' => $result['url'],
        'hybrid_score' => $result['scores']['hybrid'] ?? 0,
        'evaluation' => $result['scores']['rating'] ?? '미흡',
        'timestamp' => $result['timestamp'],
        'date' => $result['date'],
        'model' => $result['model'],
        'temperature' => $result['temperature']
    ];
    
    if (file_exists($indexFile)) {
        copy($indexFile, $indexFile . '.bak');
    }
    
    $saveResult = file_put_contents(
        $indexFile, 
        json_encode($index, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
    );
    
    if ($saveResult === false) {
        throw new Exception("index.json 저장 실패");
    }
}

// ========================================
// API 엔드포인트 처리
// ========================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=UTF-8');
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        echo json_encode(['error' => '잘못된 요청입니다']);
        exit;
    }
    
    $action = $input['action'] ?? '';
    
    if ($action === 'analyze') {
        $query = trim($input['query'] ?? '');
        $url = trim($input['url'] ?? '');
        $model = $input['model'] ?? 'gemini-2.0-flash-exp';
        $temperature = floatval($input['temperature'] ?? 0.7);
        
        if (!$query || !$url) {
            echo json_encode(['error' => '질문과 URL을 입력하세요']);
            exit;
        }
        
        try {
            $result = runAnalysis($query, $url, $model, $temperature);
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
        exit;
    }
    
    if ($action === 'test_api') {
        try {
            $response = callGeminiAPI("Say 'API Connected' in JSON format: {\"status\": \"connected\"}", 'gemini-2.0-flash-exp', 0.1);
            echo json_encode(['success' => true, 'message' => 'API 연결 성공', 'response' => $response['text']]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'API 연결 실패: ' . $e->getMessage()]);
        }
        exit;
    }
    
    if ($action === 'clear_cache') {
        $files = glob(CACHE_DIR . '/*.json');
        foreach ($files as $file) {
            unlink($file);
        }
        echo json_encode(['success' => true, 'message' => '캐시가 초기화되었습니다', 'deleted' => count($files)]);
        exit;
    }
    
    echo json_encode(['error' => '알 수 없는 액션입니다']);
    exit;
}

// ========================================
// HTML 인터페이스 (v7 - 결과 표시 개선)
// ========================================
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AEO 분석기 - Gemini API v7</title>
    <style>
        :root {
            --primary: #5B5FE0;
            --primary-light: #7B7FE8;
            --secondary: #10B981;
            --danger: #EF4444;
            --warning: #F59E0B;
            --gray-50: #F9FAFB;
            --gray-100: #F3F4F6;
            --gray-200: #E5E7EB;
            --gray-300: #D1D5DB;
            --gray-500: #6B7280;
            --gray-700: #374151;
            --gray-900: #111827;
        }
        
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { 
            font-family: 'Pretendard', -apple-system, BlinkMacSystemFont, sans-serif; 
            background: var(--gray-100);
            min-height: 100vh;
            color: var(--gray-900);
        }
        
        .header {
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            color: white;
            padding: 2rem;
            text-align: center;
        }
        .header h1 { font-size: 1.75rem; margin-bottom: 0.5rem; }
        .header p { opacity: 0.9; font-size: 0.95rem; }
        
        .container { max-width: 1200px; margin: 0 auto; padding: 2rem; }
        
        .card {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.06);
        }
        
        .toolbar {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin-top: 1rem;
        }
        .toolbar button {
            padding: 8px 16px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 500;
            transition: all 0.2s;
        }
        .btn-test { background: var(--secondary); color: white; }
        .btn-cache { background: var(--warning); color: white; }
        .btn-test:hover, .btn-cache:hover { transform: translateY(-2px); }
        
        .form-group { margin-bottom: 1.25rem; }
        .form-group label { 
            display: block; 
            margin-bottom: 0.5rem; 
            font-weight: 600; 
            color: var(--gray-700);
            font-size: 0.95rem;
        }
        .form-group input[type="text"],
        .form-group input[type="url"] { 
            width: 100%; 
            padding: 12px 16px; 
            border: 2px solid var(--gray-200); 
            border-radius: 10px; 
            font-size: 1rem;
            transition: border-color 0.2s;
        }
        .form-group input:focus { 
            outline: none; 
            border-color: var(--primary); 
        }
        
        .model-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); 
            gap: 12px; 
        }
        .model-card { 
            padding: 16px; 
            border: 2px solid var(--gray-200); 
            border-radius: 12px; 
            cursor: pointer;
            transition: all 0.2s;
            text-align: center;
        }
        .model-card:hover { border-color: var(--primary); background: var(--gray-50); }
        .model-card.selected { 
            border-color: var(--primary); 
            background: linear-gradient(135deg, rgba(91,95,224,0.1), rgba(123,127,232,0.1));
        }
        .model-card input { display: none; }
        .model-name { font-weight: 700; font-size: 1rem; margin-bottom: 6px; color: var(--gray-900); }
        .model-info { font-size: 0.8rem; color: var(--gray-500); line-height: 1.4; }
        
        .submit-btn { 
            width: 100%; 
            padding: 16px; 
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            color: white; 
            border: none; 
            border-radius: 12px; 
            font-size: 1.1rem; 
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        .submit-btn:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(91,95,224,0.3); }
        .submit-btn:disabled { background: var(--gray-300); cursor: not-allowed; transform: none; }
        
        .error-box {
            background: #FEF2F2;
            border: 2px solid var(--danger);
            color: #991B1B;
            padding: 16px;
            border-radius: 12px;
            margin-bottom: 1rem;
            display: none;
        }
        .error-box.show { display: block; }
        
        .loading { 
            text-align: center; 
            padding: 3rem;
            display: none;
        }
        .loading.show { display: block; }
        .spinner { 
            width: 48px; 
            height: 48px; 
            border: 4px solid var(--gray-200); 
            border-top: 4px solid var(--primary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 1rem;
        }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        
        /* 결과 표시 스타일 (aeo_analyzer_optimized 스타일) */
        .result-card { display: none; }
        .result-card.show { display: block; animation: fadeIn 0.4s; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        
        .info-card {
            background: var(--gray-50);
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }
        .info-row {
            display: flex;
            padding: 0.5rem 0;
            border-bottom: 1px solid var(--gray-200);
        }
        .info-row:last-child { border-bottom: none; }
        .info-label { 
            width: 100px; 
            font-weight: 600; 
            color: var(--gray-500);
            flex-shrink: 0;
        }
        .info-value { 
            flex: 1; 
            color: var(--gray-700);
            word-break: break-all;
        }
        .info-value a { color: var(--primary); text-decoration: none; }
        
        .score-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        .score-card {
            background: var(--gray-50);
            border-radius: 12px;
            padding: 1.25rem;
            text-align: center;
        }
        .score-card.final {
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            color: white;
        }
        .score-label { font-size: 0.85rem; color: var(--gray-500); margin-bottom: 0.5rem; }
        .score-card.final .score-label { color: rgba(255,255,255,0.8); }
        .score-value { font-size: 2rem; font-weight: 700; }
        .score-total { font-size: 0.8rem; color: var(--gray-400); }
        .score-card.final .score-total { color: rgba(255,255,255,0.7); }
        
        .rating-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            margin-top: 0.5rem;
        }
        .rating-excellent { background: var(--secondary); color: white; }
        .rating-good { background: #3B82F6; color: white; }
        .rating-fair { background: var(--warning); color: white; }
        .rating-poor { background: var(--danger); color: white; }
        
        .section { margin-top: 1.5rem; }
        .section-header {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1rem;
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--gray-900);
        }
        .section-icon {
            width: 36px;
            height: 36px;
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
        }
        
        .keyword-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 1rem;
        }
        .keyword-table th, .keyword-table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid var(--gray-200);
        }
        .keyword-table th {
            background: var(--gray-50);
            font-weight: 600;
            color: var(--gray-700);
            font-size: 0.9rem;
        }
        .keyword-table td { font-size: 0.95rem; }
        
        .analysis-box {
            background: var(--gray-50);
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        .analysis-box.strength { border-left: 4px solid var(--secondary); }
        .analysis-box.weakness { border-left: 4px solid var(--danger); }
        .analysis-label {
            font-weight: 700;
            font-size: 0.9rem;
            color: var(--gray-700);
            margin-bottom: 0.5rem;
        }
        .analysis-content {
            font-size: 0.95rem;
            line-height: 1.6;
            color: var(--gray-600);
        }
        
        .dimension-card {
            background: var(--gray-50);
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 0.75rem;
        }
        .dimension-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }
        .dimension-name { font-weight: 600; color: var(--gray-700); }
        .dimension-score { 
            font-weight: 700; 
            font-size: 1.25rem; 
            color: var(--primary);
        }
        .dimension-reason { font-size: 0.9rem; color: var(--gray-500); line-height: 1.5; }
        
        .list-item {
            background: var(--gray-50);
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 0.75rem;
        }
        .list-item-header { font-weight: 600; color: var(--gray-800); margin-bottom: 0.5rem; }
        .list-item-content { font-size: 0.9rem; color: var(--gray-600); line-height: 1.5; }
        .relevance-high { border-left: 4px solid var(--secondary); }
        
        .score-increase-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
        }
        .score-increase-item {
            background: linear-gradient(135deg, var(--secondary), #059669);
            color: white;
            border-radius: 10px;
            padding: 1rem;
            text-align: center;
        }
        .score-increase-label { font-size: 0.85rem; opacity: 0.9; margin-bottom: 0.25rem; }
        .score-increase-value { font-size: 1.5rem; font-weight: 700; }
        
        .cache-badge {
            display: inline-block;
            background: var(--warning);
            color: white;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .toast {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 12px 20px;
            border-radius: 10px;
            color: white;
            font-weight: 600;
            z-index: 1000;
            animation: slideIn 0.3s;
        }
        .toast.success { background: var(--secondary); }
        .toast.error { background: var(--danger); }
        @keyframes slideIn { from { transform: translateX(100%); } to { transform: translateX(0); } }
        
        @media (max-width: 768px) {
            .score-grid { grid-template-columns: repeat(2, 1fr); }
            .score-increase-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🤖 AI 검색 엔진 최적화 분석</h1>
        <p>ChatGPT, Gemini 등 AI 검색 엔진에 맞춰 콘텐츠 분석 및 점수 평가하는 분석 도구입니다.<br>Powered by Google Gemini API v7</p>
        <div class="toolbar">
            <button class="btn-test" onclick="testApi()">🔌 API 테스트</button>
            <button class="btn-cache" onclick="clearCache()">🗑️ 캐시 초기화</button>
        </div>
    </div>
    
    <div class="container">
        <div class="card">
            <div class="form-group">
                <label>📝 질문 입력</label>
                <input type="text" id="query" placeholder="예: 한맥 맥주 마실 때 같이 먹기 좋은 안주 알려줘">
            </div>
            
            <div class="form-group">
                <label>🔗 분석할 URL</label>
                <input type="url" id="url" placeholder="https://example.com/page">
            </div>
            
            <div class="form-group">
                <label>🧠 AI 모델 선택</label>
                <div class="model-grid">
                    <?php foreach ($models as $modelId => $info): ?>
                    <label class="model-card" onclick="selectModel('<?= $modelId ?>')">
                        <input type="radio" name="model" value="<?= $modelId ?>" <?= $modelId === 'gemini-2.0-flash-exp' ? 'checked' : '' ?>>
                        <div class="model-name"><?= $info['name'] ?></div>
                        <div class="model-info">
                            ⚡ <?= $info['speed'] ?><br>
                            💰 <?= $info['cost'] ?> | 📊 <?= $info['quality'] ?>
                        </div>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <button class="submit-btn" id="analyzeBtn" onclick="runAnalysis()">
                🚀 분석 시작
            </button>
        </div>
        
        <div class="error-box" id="errorBox"></div>
        
        <div class="loading" id="loading">
            <div class="spinner"></div>
            <p>AI가 콘텐츠를 분석하고 있습니다...</p>
            <p style="font-size: 0.9rem; color: var(--gray-500);">Thinking 모델은 1-2분 정도 소요될 수 있습니다.</p>
        </div>
        
        <div class="result-card" id="result"></div>
    </div>

    <script>
        let selectedModel = 'gemini-2.0-flash-exp';
        
        function selectModel(model) {
            selectedModel = model;
            document.querySelectorAll('.model-card').forEach(card => {
                card.classList.remove('selected');
                if (card.querySelector('input').value === model) {
                    card.classList.add('selected');
                }
            });
        }
        
        document.querySelector('.model-card input[value="gemini-2.0-flash-exp"]').closest('.model-card').classList.add('selected');
        
        function showToast(message, type = 'success') {
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            toast.textContent = message;
            document.body.appendChild(toast);
            setTimeout(() => toast.remove(), 3000);
        }
        
        async function testApi() {
            try {
                const res = await fetch('', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'test_api' })
                });
                const data = await res.json();
                showToast(data.message, data.success ? 'success' : 'error');
            } catch (e) {
                showToast('API 테스트 실패: ' + e.message, 'error');
            }
        }
        
        async function clearCache() {
            if (!confirm('캐시를 초기화하시겠습니까?')) return;
            try {
                const res = await fetch('', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'clear_cache' })
                });
                const data = await res.json();
                showToast(data.message, 'success');
            } catch (e) {
                showToast('캐시 초기화 실패', 'error');
            }
        }
        
        async function runAnalysis() {
            const query = document.getElementById('query').value.trim();
            const url = document.getElementById('url').value.trim();
            
            if (!query || !url) {
                showToast('질문과 URL을 입력하세요', 'error');
                return;
            }
            
            const btn = document.getElementById('analyzeBtn');
            const loading = document.getElementById('loading');
            const result = document.getElementById('result');
            const errorBox = document.getElementById('errorBox');
            
            btn.disabled = true;
            loading.classList.add('show');
            result.classList.remove('show');
            errorBox.classList.remove('show');
            
            try {
                const res = await fetch('', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'analyze',
                        query: query,
                        url: url,
                        model: selectedModel,
                        temperature: 0.7
                    })
                });
                
                const text = await res.text();
                
                if (text.trim().startsWith('<') || text.includes('<!DOCTYPE') || text.includes('<html')) {
                    throw new Error('서버가 HTML을 반환했습니다. API 키를 확인하세요.');
                }
                
                let data;
                try {
                    data = JSON.parse(text);
                } catch (parseError) {
                    console.error('JSON 파싱 실패:', text.substring(0, 500));
                    throw new Error('응답 파싱 실패: ' + parseError.message);
                }
                
                if (data.error) {
                    throw new Error(data.error);
                }
                
                displayResult(data);
                showToast('분석 완료!', 'success');
                
            } catch (e) {
                console.error('분석 에러:', e);
                errorBox.innerHTML = `<strong>⚠️ 오류 발생</strong><br>${e.message}`;
                errorBox.classList.add('show');
                showToast(e.message, 'error');
            } finally {
                btn.disabled = false;
                loading.classList.remove('show');
            }
        }
        
        function displayResult(data) {
            const resultDiv = document.getElementById('result');
            
            const ratingClass = data.scores.hybrid >= 90 ? 'rating-excellent' :
                               data.scores.hybrid >= 70 ? 'rating-good' :
                               data.scores.hybrid >= 50 ? 'rating-fair' : 'rating-poor';
            
            let html = `
                <div class="card">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                        <h2 style="font-size: 1.25rem;">분석 결과</h2>
                        ${data._from_cache ? '<div class="cache-badge">⚡ 캐시된 결과</div>' : ''}
                    </div>
                    
                    <div class="info-card">
                        <div class="info-row">
                            <div class="info-label">질문</div>
                            <div class="info-value">${data.query}</div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">URL</div>
                            <div class="info-value"><a href="${data.url}" target="_blank">${data.url}</a></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">분석 시간</div>
                            <div class="info-value">${data.timestamp}</div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">처리 시간</div>
                            <div class="info-value">${((data.processing_time?.total_ms || 0) / 1000).toFixed(1)}초</div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">AI 모델</div>
                            <div class="info-value">${data.model}</div>
                        </div>
                    </div>
                    
                    <div class="score-grid">
                        <div class="score-card">
                            <div class="score-label">BM25 키워드</div>
                            <div class="score-value">${data.scores.bm25}</div>
                            <div class="score-total">/ 40점</div>
                        </div>
                        <div class="score-card">
                            <div class="score-label">시맨틱 유사도</div>
                            <div class="score-value">${data.scores.semantic}</div>
                            <div class="score-total">/ 48점</div>
                        </div>
                        <div class="score-card">
                            <div class="score-label">FAQ 구조</div>
                            <div class="score-value">${data.scores.faq}</div>
                            <div class="score-total">/ 20점</div>
                        </div>
                        <div class="score-card final">
                            <div class="score-label">종합 점수</div>
                            <div class="score-value">${data.scores.hybrid}</div>
                            <div class="score-total">/ 100점</div>
                            <div class="rating-badge ${ratingClass}">${data.scores.rating}</div>
                        </div>
                    </div>
                </div>
                
                <!-- BM25 분석 -->
                <div class="card section">
                    <div class="section-header">
                        <div class="section-icon">🔤</div>
                        <span>BM25 키워드 분석</span>
                    </div>
                    
                    ${data.bm25_analysis?.keywords && data.bm25_analysis.keywords.length > 0 ? `
                    <table class="keyword-table">
                        <thead>
                            <tr>
                                <th>키워드</th>
                                <th>빈도(TF)</th>
                                <th>위치</th>
                                <th>희소성</th>
                                <th>점수</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${data.bm25_analysis.keywords.map(kw => `
                            <tr>
                                <td><strong>${kw.keyword || '-'}</strong></td>
                                <td>${kw.tf || '-'}</td>
                                <td>${kw.position || '-'}</td>
                                <td>${kw.rarity || '-'}</td>
                                <td><strong>${kw.score || 0}</strong></td>
                            </tr>
                            `).join('')}
                        </tbody>
                    </table>
                    ` : '<p style="color: var(--gray-500); text-align: center; padding: 1rem;">키워드 데이터 없음</p>'}
                    
                    <div class="analysis-box strength">
                        <div class="analysis-label">✓ 강점</div>
                        <div class="analysis-content">${data.bm25_analysis?.strengths || '-'}</div>
                    </div>
                    
                    <div class="analysis-box weakness">
                        <div class="analysis-label">✗ 개선 필요</div>
                        <div class="analysis-content">${data.bm25_analysis?.weaknesses || '-'}</div>
                    </div>
                </div>
                
                <!-- 시맨틱 분석 -->
                <div class="card section">
                    <div class="section-header">
                        <div class="section-icon">🧠</div>
                        <span>시맨틱 유사도 분석</span>
                    </div>
                    
                    <div class="dimension-card">
                        <div class="dimension-header">
                            <div class="dimension-name">1️⃣ 주제 일치도</div>
                            <div class="dimension-score">${data.semantic_analysis?.topic_match?.score || 0}</div>
                        </div>
                        <div class="dimension-reason">${data.semantic_analysis?.topic_match?.reason || '-'}</div>
                    </div>
                    
                    <div class="dimension-card">
                        <div class="dimension-header">
                            <div class="dimension-name">2️⃣ 의미적 연관성</div>
                            <div class="dimension-score">${data.semantic_analysis?.semantic_relevance?.score || 0}</div>
                        </div>
                        <div class="dimension-reason">${data.semantic_analysis?.semantic_relevance?.reason || '-'}</div>
                    </div>
                    
                    <div class="dimension-card">
                        <div class="dimension-header">
                            <div class="dimension-name">3️⃣ 맥락 이해도</div>
                            <div class="dimension-score">${data.semantic_analysis?.context_understanding?.score || 0}</div>
                        </div>
                        <div class="dimension-reason">${data.semantic_analysis?.context_understanding?.reason || '-'}</div>
                    </div>
                    
                    <div class="dimension-card">
                        <div class="dimension-header">
                            <div class="dimension-name">4️⃣ 정보 충족도</div>
                            <div class="dimension-score">${data.semantic_analysis?.information_completeness?.score || 0}</div>
                        </div>
                        <div class="dimension-reason">${data.semantic_analysis?.information_completeness?.reason || '-'}</div>
                    </div>
                    
                    <div class="analysis-box strength">
                        <div class="analysis-label">✓ 강점</div>
                        <div class="analysis-content">${data.semantic_analysis?.strengths || '-'}</div>
                    </div>
                    
                    <div class="analysis-box weakness">
                        <div class="analysis-label">✗ 개선 필요</div>
                        <div class="analysis-content">${data.semantic_analysis?.weaknesses || '-'}</div>
                    </div>
                </div>
                
                <!-- FAQ 분석 -->
                <div class="card section">
                    <div class="section-header">
                        <div class="section-icon">❓</div>
                        <span>FAQ/Q&A 구조 분석</span>
                    </div>
                    
                    <div class="score-grid" style="grid-template-columns: 1fr 1fr;">
                        <div class="score-card">
                            <div class="score-label">FAQ 점수</div>
                            <div class="score-value">${data.faq_analysis?.faq_score || 0}</div>
                            <div class="score-total">/ 20점</div>
                        </div>
                        <div class="score-card">
                            <div class="score-label">AI 친화성</div>
                            <div class="score-value">${data.faq_analysis?.ai_friendliness_score || 0}</div>
                            <div class="score-total">/ 10점</div>
                        </div>
                    </div>
                    
                    <div class="analysis-box">
                        <div class="analysis-label">📋 구조 분석</div>
                        <div class="analysis-content">${data.faq_analysis?.structure_analysis || '-'}</div>
                    </div>
                    
                    <div class="analysis-box">
                        <div class="analysis-label">💡 개선 권고</div>
                        <div class="analysis-content">${data.faq_analysis?.recommendation || '-'}</div>
                        <div style="margin-top: 0.75rem; padding-top: 0.75rem; border-top: 1px solid var(--gray-200);">
                            <strong>우선순위:</strong> <span style="color: var(--primary); font-weight: 600;">${data.faq_analysis?.priority || '-'}</span>
                        </div>
                    </div>
                </div>
                
                <!-- 쿼리 확장 -->
                ${data.query_expansion?.expansions && data.query_expansion.expansions.length > 0 ? `
                <div class="card section">
                    <div class="section-header">
                        <div class="section-icon">🔍</div>
                        <span>쿼리 확장 결과</span>
                    </div>
                    <div>
                        ${data.query_expansion.expansions.map(exp => `
                        <div class="list-item ${exp.relevance === '높음' ? 'relevance-high' : ''}">
                            <div class="list-item-header">${exp.query}</div>
                            <div class="list-item-content">관련성: <strong>${exp.relevance}</strong></div>
                        </div>
                        `).join('')}
                    </div>
                </div>
                ` : ''}
                
                <!-- 관련성 증거 -->
                ${data.relevance_evidence?.evidence && data.relevance_evidence.evidence.length > 0 ? `
                <div class="card section">
                    <div class="section-header">
                        <div class="section-icon">📌</div>
                        <span>관련성 증거</span>
                    </div>
                    <div>
                        ${data.relevance_evidence.evidence.map(ev => `
                        <div class="list-item">
                            <div class="list-item-content" style="margin-bottom: 0.5rem;">"${ev.passage}"</div>
                            <div style="display: flex; gap: 1rem; font-size: 0.85rem;">
                                <div>키워드 관련성: <strong style="color: var(--primary);">${ev.keyword_relevance}/10</strong></div>
                                <div>의미 관련성: <strong style="color: var(--secondary);">${ev.semantic_relevance}/10</strong></div>
                            </div>
                        </div>
                        `).join('')}
                    </div>
                </div>
                ` : ''}
                
                <!-- AEO 권고사항 -->
                <div class="card section">
                    <div class="section-header">
                        <div class="section-icon">💡</div>
                        <span>AEO 개선 권고사항</span>
                    </div>
                    
                    ${data.recommendations?.missing_info && data.recommendations.missing_info.length > 0 ? `
                    <h3 style="color: var(--danger); margin-bottom: 1rem; font-size: 1rem;">📌 누락된 정보</h3>
                    <div>
                        ${data.recommendations.missing_info.map(item => `
                        <div class="list-item">
                            <div class="list-item-header">${item.item || '-'}</div>
                            <div class="list-item-content">
                                <div style="margin-bottom: 0.5rem;"><strong>필요 이유:</strong> ${item.reason || '-'}</div>
                                <div><strong>예상 효과:</strong> ${item.effect || '-'}</div>
                            </div>
                        </div>
                        `).join('')}
                    </div>
                    ` : ''}
                    
                    ${data.recommendations?.action_items && data.recommendations.action_items.length > 0 ? `
                    <h3 style="color: var(--secondary); margin: 1.5rem 0 1rem; font-size: 1rem;">🎯 실행 액션</h3>
                    <div>
                        ${data.recommendations.action_items.map(item => `
                        <div class="list-item">
                            <div class="list-item-header">${item.action || '-'}</div>
                            <div class="list-item-content">
                                <div style="margin-bottom: 0.5rem;"><strong>필요 이유:</strong> ${item.reason || '-'}</div>
                                <div><strong>예상 결과:</strong> ${item.expected_result || '-'}</div>
                            </div>
                        </div>
                        `).join('')}
                    </div>
                    ` : ''}
                    
                    <h3 style="color: var(--primary); margin: 1.5rem 0 1rem; font-size: 1rem;">📈 예상 점수 증가</h3>
                    <div class="score-increase-grid">
                        <div class="score-increase-item">
                            <div class="score-increase-label">BM25</div>
                            <div class="score-increase-value">+${data.recommendations?.expected_score_increase?.bm25 || 0}</div>
                        </div>
                        <div class="score-increase-item">
                            <div class="score-increase-label">시맨틱</div>
                            <div class="score-increase-value">+${data.recommendations?.expected_score_increase?.semantic || 0}</div>
                        </div>
                        <div class="score-increase-item">
                            <div class="score-increase-label">FAQ</div>
                            <div class="score-increase-value">+${data.recommendations?.expected_score_increase?.faq || 0}</div>
                        </div>
                    </div>
                </div>
            `;
            
            resultDiv.innerHTML = html;
            resultDiv.classList.add('show');
            window.scrollTo({ top: resultDiv.offsetTop - 100, behavior: 'smooth' });
        }
    </script>
</body>
</html>