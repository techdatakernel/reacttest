<?php
/**
 * AEO 분석 엔진 (Claude API 버전)
 * 파일명: aeo_analyzer_claude_api.php
 *
 * [특징]
 * - Anthropic Claude API 사용
 * - v11의 캐싱 시스템 유지
 * - v11의 강화된 프롬프트 유지
 * - v11의 전문 디자인 유지
 * - 순차 API 호출 방식
 */

set_time_limit(120);
error_reporting(E_ALL);
ini_set('display_errors', 1);

// API 키 설정 파일 로드
require_once __DIR__ . '/config.php';

define('DATA_DIR', __DIR__ . '/aeo_data_claude');
define('CACHE_DIR', DATA_DIR . '/cache');
define('MAX_TOKENS', 3000);
define('API_TIMEOUT', 45);
define('BM25_MAX_SCORE', 40);
define('SEMANTIC_MAX_SCORE', 48);
define('FAQ_MAX_SCORE', 20);
define('CACHE_TTL', 86400); // 24시간

// 디렉토리 생성
if (!is_dir(DATA_DIR)) mkdir(DATA_DIR, 0755, true);
if (!is_dir(CACHE_DIR)) mkdir(CACHE_DIR, 0755, true);

$models = [
    'claude-sonnet-4-20250514' => ['name' => 'Claude Sonnet 4', 'speed' => '빠름', 'cost' => '약 $3.00', 'quality' => '최고'],
    'claude-3-5-haiku-20241022' => ['name' => 'Claude 3.5 Haiku', 'speed' => '매우 빠름', 'cost' => '약 $0.80', 'quality' => '우수'],
    'claude-opus-4-20250514' => ['name' => 'Claude Opus 4', 'speed' => '느림', 'cost' => '약 $15.00', 'quality' => '최상']
];

// ========================================
// 캐싱 시스템
// ========================================

function getCacheKey($query, $url, $model, $temperature) {
    $content = fetchPageContent($url);
    if (!$content) return null;

    $contentHash = md5($content);
    return md5($query . $url . $contentHash . $model . $temperature);
}

function getCachedResult($cacheKey) {
    if (!$cacheKey) return null;

    $cacheFile = CACHE_DIR . "/$cacheKey.json";

    if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < CACHE_TTL) {
        $data = json_decode(file_get_contents($cacheFile), true);
        if ($data) {
            $data['_from_cache'] = true;
            $data['_cache_age_seconds'] = time() - filemtime($cacheFile);
            return $data;
        }
    }

    return null;
}

function setCachedResult($cacheKey, $result) {
    if (!$cacheKey) return;

    $cacheFile = CACHE_DIR . "/$cacheKey.json";
    file_put_contents($cacheFile, json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

// ========================================
// Claude API 호출
// ========================================

function callClaudeAPI($systemPrompt, $userPrompt, $model = 'claude-sonnet-4-20250514', $temperature = 0.7, $retryCount = 0) {
    if ($retryCount > 1) {
        return ['error' => 'API 호출 실패', 'elapsed_time' => 0];
    }

    $url = 'https://api.anthropic.com/v1/messages';

    $payload = [
        'model' => $model,
        'max_tokens' => MAX_TOKENS,
        'temperature' => (float)$temperature,
        'system' => $systemPrompt,
        'messages' => [
            [
                'role' => 'user',
                'content' => $userPrompt
            ]
        ]
    ];

    $startTime = microtime(true);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'x-api-key: ' . CLAUDE_API_KEY,
            'anthropic-version: 2023-06-01'
        ],
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => API_TIMEOUT,
        CURLOPT_SSL_VERIFYPEER => false
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    $elapsedTime = round((microtime(true) - $startTime) * 1000, 2);

    if (!$response) {
        if ($retryCount < 1) {
            sleep(1);
            return callClaudeAPI($systemPrompt, $userPrompt, $model, $temperature, $retryCount + 1);
        }
        return ['error' => 'API 연결 실패: ' . $curlError, 'elapsed_time' => $elapsedTime];
    }

    if ($httpCode !== 200) {
        // JSON 에러 메시지 파싱 시도
        $errorData = json_decode($response, true);
        $errorMsg = "API 오류 (HTTP $httpCode)";

        if ($errorData && isset($errorData['error'])) {
            if (isset($errorData['error']['message'])) {
                $errorMsg .= ': ' . $errorData['error']['message'];
            } elseif (isset($errorData['error']['type'])) {
                $errorMsg .= ': ' . $errorData['error']['type'];
            }
        }

        if ($retryCount < 1 && $httpCode >= 500) {
            sleep(1);
            return callClaudeAPI($systemPrompt, $userPrompt, $model, $temperature, $retryCount + 1);
        }

        return ['error' => $errorMsg, 'elapsed_time' => $elapsedTime];
    }

    $data = json_decode($response, true);

    if (!$data) {
        return ['error' => 'JSON 파싱 실패. API가 HTML 또는 잘못된 형식을 반환했습니다.', 'elapsed_time' => $elapsedTime];
    }

    if (!isset($data['content'][0]['text'])) {
        $errorMsg = '응답 형식 오류';
        if (isset($data['error'])) {
            $errorMsg .= ': ' . (isset($data['error']['message']) ? $data['error']['message'] : json_encode($data['error']));
        }
        return ['error' => $errorMsg, 'elapsed_time' => $elapsedTime];
    }

    return [
        'content' => $data['content'][0]['text'],
        'model' => $model,
        'elapsed_time' => $elapsedTime,
        'temperature' => $temperature
    ];
}

// ========================================
// 콘텐츠 추출
// ========================================

function fetchPageContent($url) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
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

    // H1, H2, H3 태그 우선 추출
    foreach ($dom->getElementsByTagName('h1') as $h) {
        $text = trim($h->textContent);
        if (strlen($text) > 3) $texts[] = "[H1] $text";
    }

    foreach ($dom->getElementsByTagName('h2') as $h) {
        $text = trim($h->textContent);
        if (strlen($text) > 3) $texts[] = "[H2] $text";
    }

    foreach ($dom->getElementsByTagName('h3') as $h) {
        $text = trim($h->textContent);
        if (strlen($text) > 3) $texts[] = "[H3] $text";
    }

    // 본문 텍스트 추출
    foreach ($dom->getElementsByTagName('p') as $p) {
        $text = trim($p->textContent);
        if (strlen($text) > 20) $texts[] = $text;
    }

    return implode("\n", array_slice($texts, 0, 50));
}

function safeJsonDecode($json, $default = []) {
    $json = preg_replace('/```json\s*/i', '', $json);
    $json = preg_replace('/```\s*/i', '', $json);
    $json = trim($json);

    $decoded = json_decode($json, true);
    return ($decoded === null) ? $default : $decoded;
}

// ========================================
// 분석 함수들
// ========================================

function analyzeBM25($query, $content, $model, $temperature) {
    $systemPrompt = <<<PROMPT
당신은 AEO(Answer Engine Optimization) 전문가입니다.
BM25 키워드 분석을 수행하며, 다음 예시와 같은 상세한 분석을 제공해야 합니다:

[예시 형식]
운전자 보험: TF=매우 높음, 위치=최상단(제목, 첫 문단, 소제목), 희소성=높음 → 점수: 10
보험: TF=높음, 위치=전반적 분포, 희소성=중간 → 점수: 7

각 키워드에 대해 다음을 분석하세요:
1. TF (Term Frequency): 매우 높음/높음/중간/낮음
2. 위치: 최상단(제목)/중간/하단
3. 희소성: 높음(전문용어)/중간/낮음(일반단어)
4. BM25 점수 및 상세한 근거

반드시 JSON 형식만 반환하세요. 마크다운이나 추가 설명은 금지입니다.
PROMPT;

    $userPrompt = <<<PROMPT
질문: "$query"

콘텐츠:
$content

위 콘텐츠에 대한 BM25 키워드 분석을 수행하고 다음 JSON 형식으로 반환하세요:

{
  "keywords": [
    {
      "keyword": "키워드",
      "tf": "매우 높음/높음/중간/낮음",
      "position": "최상단(제목, 첫 문단)/중간/하단",
      "rarity": "높음(전문용어)/중간/낮음(일반단어)",
      "score": 점수(숫자),
      "reasoning": "점수 부여에 대한 상세한 근거"
    }
  ],
  "total_score": 35,
  "strengths": "키워드 배치 및 밀도의 강점을 구체적으로 설명",
  "weaknesses": "개선이 필요한 부분을 구체적으로 설명"
}

중요: total_score는 0-40점 사이여야 합니다.
PROMPT;

    $result = callClaudeAPI($systemPrompt, $userPrompt, $model, $temperature);

    if (isset($result['error'])) {
        return [
            'keywords' => [],
            'total_score' => 0,
            'strengths' => '분석 불가: ' . $result['error'],
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

function analyzeSemanticSimilarity($query, $content, $model, $temperature) {
    $systemPrompt = <<<PROMPT
당신은 AEO 시맨틱 분석 전문가입니다.
질문과 콘텐츠 간의 의미적 유사도를 4가지 차원에서 평가합니다:

1. 주제 일치도 (Topic Match): 0-12점
2. 의미적 연관성 (Semantic Relevance): 0-12점
3. 맥락 이해도 (Context Understanding): 0-12점
4. 정보 충족도 (Information Completeness): 0-12점

각 차원마다 점수와 함께 상세한 이유를 제공해야 합니다.
반드시 JSON 형식만 반환하세요.
PROMPT;

    $userPrompt = <<<PROMPT
질문: "$query"

콘텐츠:
$content

위 콘텐츠에 대한 시맨틱 유사도 분석을 수행하고 다음 JSON 형식으로 반환하세요:

{
  "topic_match": {
    "score": 10,
    "reason": "질의와 문서의 주제가 얼마나 일치하는지 상세히 설명"
  },
  "semantic_relevance": {
    "score": 9,
    "reason": "관련 용어와 개념이 얼마나 유기적으로 연결되는지 설명"
  },
  "context_understanding": {
    "score": 8,
    "reason": "질의의 의도를 콘텐츠가 얼마나 정확히 파악하는지 설명"
  },
  "information_completeness": {
    "score": 10,
    "reason": "질문에 대한 정보 충족도를 상세히 설명"
  },
  "total_score": 37,
  "strengths": "의미적 관련성의 강점을 구체적으로 설명",
  "weaknesses": "개선이 필요한 부분을 구체적으로 설명"
}

중요: total_score는 0-48점 사이여야 하며, 4개 차원 점수의 합과 일치해야 합니다.
PROMPT;

    $result = callClaudeAPI($systemPrompt, $userPrompt, $model, $temperature);

    if (isset($result['error'])) {
        return [
            'topic_match' => ['score' => 0, 'reason' => '분석 불가: ' . $result['error']],
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

function analyzeFAQStructure($query, $content, $model, $temperature) {
    $systemPrompt = <<<PROMPT
당신은 FAQ/Q&A 구조 분석 전문가입니다.
콘텐츠에 AI가 이해하고 인용하기 쉬운 FAQ 형식이 있는지 평가합니다.
반드시 JSON 형식만 반환하세요.
PROMPT;

    $userPrompt = <<<PROMPT
질문: "$query"

콘텐츠:
$content

FAQ/Q&A 구조를 분석하고 다음 JSON 형식으로 반환하세요:

{
  "has_faq_format": true/false,
  "faq_score": 15,
  "ai_friendliness_score": 14,
  "structure_analysis": "FAQ 형식의 존재 여부와 품질을 상세히 분석",
  "recommendation": "구체적인 개선 방안 제시",
  "priority": "필수/권장/선택"
}

채점 기준:
- faq_score (0-20점): FAQ/Q&A 형식의 존재 여부 및 답변 품질
- ai_friendliness_score (0-20점): AI가 이해하고 인용하기 쉬운 구조
- priority: 개선의 우선순위 (필수/권장/선택)
PROMPT;

    $result = callClaudeAPI($systemPrompt, $userPrompt, $model, $temperature);

    if (isset($result['error'])) {
        return [
            'has_faq_format' => false,
            'faq_score' => 0,
            'ai_friendliness_score' => 0,
            'structure_analysis' => '분석 불가: ' . $result['error'],
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

function generateQueryExpansion($query, $content, $model, $temperature) {
    $systemPrompt = "당신은 검색 쿼리 확장 전문가입니다. 사용자의 질문 의도를 파악하여 관련 쿼리를 생성합니다. JSON만 반환하세요.";

    $userPrompt = <<<PROMPT
원본 질문: "$query"

콘텐츠:
$content

이 질문에 대한 5가지 확장 쿼리를 생성하세요. JSON 반환:

{
  "expansions": [
    {"query": "확장된 질문 1", "relevance": "높음"},
    {"query": "확장된 질문 2", "relevance": "높음"}
  ]
}

relevance는 "높음", "중간", "낮음" 중 하나여야 합니다.
PROMPT;

    $result = callClaudeAPI($systemPrompt, $userPrompt, $model, $temperature);

    if (isset($result['error'])) {
        return [
            'expansions' => [],
            'api_metadata' => ['time_ms' => $result['elapsed_time']]
        ];
    }

    $parsed = safeJsonDecode($result['content'], ['expansions' => []]);
    return array_merge($parsed, ['api_metadata' => ['time_ms' => $result['elapsed_time']]]);
}

function generateRelevanceEvidence($query, $content, $model, $temperature) {
    $systemPrompt = "당신은 문서 분석 전문가입니다. 질문과 가장 관련성 높은 문장을 찾아 평가합니다. JSON만 반환하세요.";

    $userPrompt = <<<PROMPT
질문: "$query"

콘텐츠:
$content

문서에서 질문과 가장 관련성 높은 구절 3개를 추출하고 각각 평가하세요:

{
  "evidence": [
    {
      "passage": "문서에서 추출한 정확한 구절",
      "keyword_relevance": 9,
      "semantic_relevance": 10
    }
  ]
}

keyword_relevance: 키워드 관련성 (0-10점)
semantic_relevance: 의미적 관련성 (0-10점)
PROMPT;

    $result = callClaudeAPI($systemPrompt, $userPrompt, $model, $temperature);

    if (isset($result['error'])) {
        return [
            'evidence' => [],
            'api_metadata' => ['time_ms' => $result['elapsed_time']]
        ];
    }

    $parsed = safeJsonDecode($result['content'], ['evidence' => []]);
    return array_merge($parsed, ['api_metadata' => ['time_ms' => $result['elapsed_time']]]);
}

function generateAEORecommendations($query, $content, $bm25Score, $semanticScore, $faqScore, $model, $temperature) {
    $systemPrompt = "당신은 AEO 최적화 컨설턴트입니다. 구체적이고 실행 가능한 개선 방안을 제시합니다. JSON만 반환하세요.";

    $userPrompt = <<<PROMPT
질문: "$query"

콘텐츠:
$content

현재 점수:
- BM25: {$bm25Score}/40점
- 시맨틱: {$semanticScore}/48점
- FAQ: {$faqScore}/20점

AEO 개선 권고사항을 다음 JSON 형식으로 반환하세요:

{
  "missing_info": [
    {
      "item": "누락된 정보 항목",
      "reason": "필요한 이유",
      "effect": "추가 시 예상 효과"
    }
  ],
  "action_items": [
    {
      "action": "실행 가능한 구체적 액션",
      "reason": "필요한 이유",
      "expected_result": "예상 결과"
    }
  ],
  "expected_score_increase": {
    "bm25": 5,
    "semantic": 8,
    "faq": 3
  }
}
PROMPT;

    $result = callClaudeAPI($systemPrompt, $userPrompt, $model, $temperature);

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

// ========================================
// 메인 분석 로직
// ========================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $query = $_POST['query'] ?? '';
    $url = $_POST['url'] ?? '';
    $model = $_POST['model'] ?? 'claude-sonnet-4-20250514';
    $temperature = (float)($_POST['temperature'] ?? 0.7);

    header('Content-Type: application/json; charset=utf-8');

    // API 키 검증 (Claude만)
    $apiKeyErrors = validateApiKey('claude');
    if (!empty($apiKeyErrors)) {
        echo json_encode([
            'error' => 'API 키 설정 오류: ' . implode(' ', $apiKeyErrors)
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (empty($query) || empty($url)) {
        echo json_encode(['error' => '질문과 URL을 입력해주세요'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $startTime = microtime(true);

    // 캐시 확인
    $cacheKey = getCacheKey($query, $url, $model, $temperature);
    $cached = getCachedResult($cacheKey);

    if ($cached) {
        echo json_encode($cached, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    // 콘텐츠 추출
    $content = fetchPageContent($url);
    if (!$content) {
        echo json_encode(['error' => 'URL 콘텐츠를 가져올 수 없습니다. URL을 확인해주세요.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 순차 분석
    $bm25Data = analyzeBM25($query, $content, $model, $temperature);
    $semanticData = analyzeSemanticSimilarity($query, $content, $model, $temperature);
    $faqData = analyzeFAQStructure($query, $content, $model, $temperature);

    // 추가 분석
    $queryExpansionData = generateQueryExpansion($query, $content, $model, $temperature);
    $relevanceEvidenceData = generateRelevanceEvidence($query, $content, $model, $temperature);

    // 권고사항 생성
    $recommendationsData = generateAEORecommendations(
        $query,
        $content,
        $bm25Data['total_score'],
        $semanticData['total_score'],
        $faqData['faq_score'],
        $model,
        $temperature
    );

    // 점수 계산
    $totalScore = $bm25Data['total_score'] + $semanticData['total_score'] + $faqData['faq_score'];
    $hybridScore = round(($totalScore / 108) * 100, 1);
    $rating = $hybridScore >= 90 ? '우수' : ($hybridScore >= 75 ? '양호' : ($hybridScore >= 60 ? '보통' : '미흡'));

    $totalTime = round((microtime(true) - $startTime) * 1000, 2);
    $uniqueId = md5($query . $url . time());

    // 결과 구성
    $result = [
        'id' => $uniqueId,
        'query' => $query,
        'url' => $url,
        'model' => $model,
        'temperature' => $temperature,
        'timestamp' => date('Y-m-d H:i:s'),
        'date' => date('Y-m-d'),
        'scores' => [
            'bm25' => $bm25Data['total_score'],
            'semantic' => $semanticData['total_score'],
            'faq' => $faqData['faq_score'],
            'total' => $totalScore,
            'hybrid' => $hybridScore,
            'rating' => $rating
        ],
        'bm25_analysis' => $bm25Data,
        'semantic_analysis' => $semanticData,
        'faq_analysis' => $faqData,
        'query_expansion' => $queryExpansionData,
        'relevance_evidence' => $relevanceEvidenceData,
        'recommendations' => $recommendationsData,
        'processing_time' => [
            'total_ms' => $totalTime,
            'bm25_ms' => $bm25Data['api_metadata']['time_ms'],
            'semantic_ms' => $semanticData['api_metadata']['time_ms'],
            'faq_ms' => $faqData['api_metadata']['time_ms'],
            'query_expansion_ms' => $queryExpansionData['api_metadata']['time_ms'] ?? 0,
            'relevance_evidence_ms' => $relevanceEvidenceData['api_metadata']['time_ms'] ?? 0,
            'recommendations_ms' => $recommendationsData['api_metadata']['time_ms']
        ],
        '_optimization_notes' => [
            'api_provider' => 'Anthropic Claude',
            'api_calls' => 'Sequential (stable method)',
            'cache_enabled' => true,
            'cache_ttl' => CACHE_TTL . ' seconds',
            'version' => 'Claude API v1.0'
        ]
    ];

    // 결과 저장
    $dateDir = DATA_DIR . '/' . date('Y-m-d');
    if (!is_dir($dateDir)) mkdir($dateDir, 0755, true);

    $filename = date('Y-m-d') . '_' . substr($uniqueId, 0, 8) . '.json';
    file_put_contents("$dateDir/$filename", json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

    // 인덱스 업데이트
    $indexFile = DATA_DIR . '/index.json';
    $index = file_exists($indexFile) ? json_decode(file_get_contents($indexFile), true) ?? [] : [];

    $index[$uniqueId] = [
        'id' => $uniqueId,
        'query' => $query,
        'url' => $url,
        'hybrid_score' => $hybridScore,
        'evaluation' => $rating,
        'timestamp' => date('Y-m-d H:i:s'),
        'date' => date('Y-m-d'),
        'model' => $model,
        'temperature' => $temperature
    ];

    file_put_contents($indexFile, json_encode($index, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

    // 캐시 저장
    setCachedResult($cacheKey, $result);

    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AEO Analytics Pro - Claude API</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #2563eb;
            --primary-dark: #1e40af;
            --secondary: #8b5cf6;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-600: #4b5563;
            --gray-700: #374151;
            --gray-800: #1f2937;
            --gray-900: #111827;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--gray-50);
            color: var(--gray-900);
            line-height: 1.6;
            min-height: 100vh;
        }

        .topbar {
            background: white;
            border-bottom: 1px solid var(--gray-200);
            padding: 1rem 0;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        .topbar-content {
            max-width: 1280px;
            margin: 0 auto;
            padding: 0 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-weight: 700;
            font-size: 1.25rem;
            color: var(--gray-900);
        }

        .logo-icon {
            width: 32px;
            height: 32px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.125rem;
        }

        .version-badge {
            background: var(--primary);
            color: white;
            padding: 0.25rem 0.625rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .container {
            max-width: 1280px;
            margin: 0 auto;
            padding: 2rem 1.5rem;
        }

        .hero {
            text-align: center;
            margin-bottom: 3rem;
        }

        .hero h1 {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--gray-900);
            margin-bottom: 0.75rem;
            letter-spacing: -0.025em;
        }

        .hero p {
            font-size: 1.125rem;
            color: var(--gray-600);
            max-width: 600px;
            margin: 0 auto;
        }

        .api-badge {
            display: inline-block;
            background: linear-gradient(135deg, #FF6B35, #F7931E);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-size: 0.875rem;
            font-weight: 600;
            margin-top: 1rem;
        }

        .card {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }

        .form-grid {
            display: grid;
            gap: 1.5rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .form-group label {
            font-weight: 600;
            font-size: 0.875rem;
            color: var(--gray-700);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 2px solid var(--gray-200);
            border-radius: 10px;
            font-size: 0.9375rem;
            font-family: inherit;
            transition: all 0.2s;
            background: white;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .model-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }

        .model-card {
            padding: 1rem;
            border: 2px solid var(--gray-200);
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .model-card:hover {
            border-color: var(--primary);
            background: var(--gray-50);
        }

        .model-card.selected {
            border-color: var(--primary);
            background: rgba(37, 99, 235, 0.05);
        }

        .model-name {
            font-weight: 600;
            color: var(--gray-900);
            margin-bottom: 0.25rem;
        }

        .model-meta {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
            margin-top: 0.5rem;
        }

        .model-badge {
            padding: 0.125rem 0.5rem;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .badge-speed {
            background: var(--gray-100);
            color: var(--gray-700);
        }

        .badge-cost {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
        }

        .badge-quality {
            background: rgba(37, 99, 235, 0.1);
            color: var(--primary);
        }

        .btn {
            padding: 0.875rem 1.75rem;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            font-family: inherit;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover:not(:disabled) {
            background: var(--primary-dark);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
        }

        .btn-primary:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .btn-full {
            width: 100%;
        }

        #loading {
            display: none;
            text-align: center;
            padding: 3rem;
        }

        .spinner {
            width: 48px;
            height: 48px;
            border: 4px solid var(--gray-200);
            border-top-color: var(--primary);
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            margin: 0 auto 1rem;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .loading-text {
            color: var(--gray-600);
            font-weight: 500;
        }

        .progress-bar {
            width: 100%;
            height: 4px;
            background: var(--gray-200);
            border-radius: 2px;
            overflow: hidden;
            margin-top: 1rem;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
            width: 0%;
            animation: progress 30s ease-out forwards;
        }

        @keyframes progress {
            to { width: 95%; }
        }

        #result {
            display: none;
        }

        .result-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .result-title {
            font-size: 1.875rem;
            font-weight: 700;
            color: var(--gray-900);
        }

        .cache-badge {
            padding: 0.5rem 1rem;
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
            border-radius: 8px;
            font-size: 0.875rem;
            font-weight: 600;
        }

        .info-card {
            background: var(--gray-50);
            padding: 1.25rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            border-left: 4px solid var(--primary);
        }

        .info-row {
            display: grid;
            grid-template-columns: 140px 1fr;
            gap: 0.75rem;
            margin-bottom: 0.5rem;
        }

        .info-row:last-child {
            margin-bottom: 0;
        }

        .info-label {
            font-weight: 600;
            color: var(--gray-700);
            font-size: 0.875rem;
        }

        .info-value {
            color: var(--gray-900);
            font-size: 0.875rem;
            word-break: break-all;
        }

        .score-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.25rem;
            margin-bottom: 2rem;
        }

        .score-card {
            background: white;
            border: 2px solid var(--gray-200);
            border-radius: 12px;
            padding: 1.5rem;
            text-align: center;
            transition: all 0.2s;
        }

        .score-card:hover {
            border-color: var(--primary);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        }

        .score-label {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--gray-600);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.75rem;
        }

        .score-value {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 0.5rem;
        }

        .score-total {
            font-size: 0.875rem;
            color: var(--gray-500);
        }

        .score-card.final {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border: none;
            color: white;
        }

        .score-card.final .score-label,
        .score-card.final .score-total {
            color: rgba(255, 255, 255, 0.9);
        }

        .score-card.final .score-value {
            color: white;
        }

        .rating-badge {
            display: inline-block;
            padding: 0.375rem 1rem;
            border-radius: 9999px;
            font-size: 0.875rem;
            font-weight: 600;
            margin-top: 0.5rem;
        }

        .rating-excellent { background: rgba(16, 185, 129, 0.2); color: var(--success); }
        .rating-good { background: rgba(37, 99, 235, 0.2); color: var(--primary); }
        .rating-fair { background: rgba(245, 158, 11, 0.2); color: var(--warning); }
        .rating-poor { background: rgba(239, 68, 68, 0.2); color: var(--danger); }

        .section {
            margin-bottom: 2rem;
        }

        .section-header {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 1.25rem;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid var(--gray-200);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .section-icon {
            width: 36px;
            height: 36px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }

        .keyword-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            border: 1px solid var(--gray-200);
            border-radius: 10px;
            overflow: hidden;
        }

        .keyword-table thead {
            background: var(--gray-50);
        }

        .keyword-table th {
            padding: 0.875rem 1rem;
            text-align: left;
            font-weight: 600;
            font-size: 0.875rem;
            color: var(--gray-700);
            border-bottom: 2px solid var(--gray-200);
        }

        .keyword-table td {
            padding: 0.875rem 1rem;
            border-bottom: 1px solid var(--gray-100);
        }

        .keyword-table tr:last-child td {
            border-bottom: none;
        }

        .keyword-table tr:hover {
            background: var(--gray-50);
        }

        .analysis-box {
            background: var(--gray-50);
            padding: 1.25rem;
            border-radius: 10px;
            margin-top: 1rem;
            border-left: 4px solid var(--primary);
        }

        .analysis-box.strength {
            border-left-color: var(--success);
        }

        .analysis-box.weakness {
            border-left-color: var(--warning);
        }

        .analysis-label {
            font-weight: 600;
            color: var(--gray-700);
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .analysis-content {
            color: var(--gray-600);
            line-height: 1.6;
        }

        .dimension-card {
            background: white;
            border: 1px solid var(--gray-200);
            border-radius: 10px;
            padding: 1.25rem;
            margin-bottom: 0.75rem;
        }

        .dimension-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.75rem;
        }

        .dimension-name {
            font-weight: 600;
            color: var(--gray-900);
        }

        .dimension-score {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary);
        }

        .dimension-reason {
            color: var(--gray-600);
            font-size: 0.9375rem;
            line-height: 1.6;
        }

        .expansion-list, .evidence-list, .recommendation-list {
            display: grid;
            gap: 0.75rem;
        }

        .list-item {
            background: white;
            border: 1px solid var(--gray-200);
            border-radius: 10px;
            padding: 1rem;
        }

        .list-item-header {
            font-weight: 600;
            color: var(--gray-900);
            margin-bottom: 0.5rem;
        }

        .list-item-content {
            color: var(--gray-600);
            font-size: 0.9375rem;
        }

        .relevance-high {
            background: rgba(16, 185, 129, 0.1);
            border-color: var(--success);
        }

        .relevance-medium {
            background: rgba(245, 158, 11, 0.1);
            border-color: var(--warning);
        }

        .score-increase-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }

        .score-increase-item {
            text-align: center;
            padding: 1rem;
            background: rgba(16, 185, 129, 0.05);
            border-radius: 10px;
        }

        .score-increase-label {
            font-size: 0.875rem;
            color: var(--gray-600);
            margin-bottom: 0.5rem;
        }

        .score-increase-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--success);
        }

        @media (max-width: 768px) {
            .hero h1 {
                font-size: 2rem;
            }

            .model-grid {
                grid-template-columns: 1fr;
            }

            .score-grid {
                grid-template-columns: 1fr;
            }

            .info-row {
                grid-template-columns: 1fr;
                gap: 0.25rem;
            }
        }
    </style>
</head>
<body>
    <div class="topbar">
        <div class="topbar-content">
            <div class="logo">
                <div class="logo-icon">⚡</div>
                <span>AEO Analytics Pro</span>
            </div>
            <div class="version-badge">Claude API</div>
        </div>
    </div>

    <div class="container">
        <div class="hero">
            <h1>AI 검색 엔진 최적화 분석</h1>
            <p>ChatGPT, Gemini 등 AI 검색 엔진에서 당신의 콘텐츠가 얼마나 잘 노출되는지 분석합니다</p>
            <div class="api-badge">🤖 Powered by Anthropic Claude API</div>
        </div>

        <div class="card">
            <form id="analysisForm">
                <div class="form-grid">
                    <div class="form-group">
                        <label>
                            <span>검색 질문</span>
                        </label>
                        <input
                            type="text"
                            name="query"
                            placeholder="예: 운전자 보험에 대해서 알려줘"
                            required>
                    </div>

                    <div class="form-group">
                        <label>
                            <span>분석할 URL</span>
                        </label>
                        <input
                            type="url"
                            name="url"
                            placeholder="https://example.com/your-page"
                            required>
                    </div>

                    <div class="form-group">
                        <label>AI 모델 선택</label>
                        <input type="hidden" name="model" id="selectedModel" value="claude-sonnet-4-20250514">
                        <div class="model-grid">
                            <?php foreach ($models as $key => $info): ?>
                            <div class="model-card" data-model="<?= $key ?>">
                                <div class="model-name"><?= $info['name'] ?></div>
                                <div class="model-meta">
                                    <span class="model-badge badge-speed"><?= $info['speed'] ?></span>
                                    <span class="model-badge badge-cost"><?= $info['cost'] ?></span>
                                    <span class="model-badge badge-quality"><?= $info['quality'] ?></span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>
                            <span>Temperature</span>
                            <span style="color: var(--gray-500); font-weight: 400; font-size: 0.8125rem;">(0.0 = 일관적, 1.0 = 창의적)</span>
                        </label>
                        <input
                            type="range"
                            name="temperature"
                            id="temperatureSlider"
                            min="0"
                            max="1"
                            step="0.1"
                            value="0.7"
                            style="width: 100%;">
                        <div style="text-align: center; color: var(--gray-600); font-weight: 600; margin-top: 0.5rem;">
                            <span id="temperatureValue">0.7</span>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-full">
                        <span>⚡</span>
                        <span>분석 시작</span>
                    </button>
                </div>
            </form>
        </div>

        <div id="loading" class="card">
            <div class="spinner"></div>
            <div class="loading-text">Claude AI가 콘텐츠를 분석하고 있습니다...</div>
            <div class="loading-text" style="margin-top: 0.5rem; font-size: 0.875rem; color: var(--gray-500);">약 20-30초 소요됩니다</div>
            <div class="progress-bar">
                <div class="progress-fill"></div>
            </div>
        </div>

        <div id="result"></div>
    </div>

    <script>
        // 모델 선택
        document.querySelectorAll('.model-card').forEach(card => {
            card.addEventListener('click', function() {
                document.querySelectorAll('.model-card').forEach(c => c.classList.remove('selected'));
                this.classList.add('selected');
                document.getElementById('selectedModel').value = this.dataset.model;
            });
        });

        // 초기 선택
        document.querySelector('[data-model="claude-sonnet-4-20250514"]').classList.add('selected');

        // Temperature 슬라이더
        const temperatureSlider = document.getElementById('temperatureSlider');
        const temperatureValue = document.getElementById('temperatureValue');

        temperatureSlider.addEventListener('input', function() {
            temperatureValue.textContent = this.value;
        });

        // 폼 제출
        document.getElementById('analysisForm').addEventListener('submit', async (e) => {
            e.preventDefault();

            const form = e.target;
            const formData = new FormData(form);

            document.getElementById('loading').style.display = 'block';
            document.getElementById('result').style.display = 'none';
            form.querySelector('button').disabled = true;
            window.scrollTo({ top: document.getElementById('loading').offsetTop - 100, behavior: 'smooth' });

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
                console.error('Error:', error);
            } finally {
                document.getElementById('loading').style.display = 'none';
                form.querySelector('button').disabled = false;
            }
        });

        function displayResult(data) {
            const resultDiv = document.getElementById('result');

            // 평가 등급에 따른 클래스
            const ratingClass = data.scores.hybrid >= 90 ? 'rating-excellent' :
                               data.scores.hybrid >= 75 ? 'rating-good' :
                               data.scores.hybrid >= 60 ? 'rating-fair' : 'rating-poor';

            let html = `
                <div class="card">
                    <div class="result-header">
                        <h2 class="result-title">분석 결과</h2>
                        ${data._from_cache ? '<div class="cache-badge">⚡ 캐시된 결과 (0.1초)</div>' : ''}
                    </div>

                    <div class="info-card">
                        <div class="info-row">
                            <div class="info-label">질문</div>
                            <div class="info-value">${data.query}</div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">URL</div>
                            <div class="info-value"><a href="${data.url}" target="_blank" style="color: var(--primary);">${data.url}</a></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">분석 시간</div>
                            <div class="info-value">${data.timestamp}</div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">처리 시간</div>
                            <div class="info-value">${(data.processing_time.total_ms / 1000).toFixed(1)}초 (${data.processing_time.total_ms.toLocaleString()}ms)</div>
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

                    ${data.bm25_analysis.keywords && data.bm25_analysis.keywords.length > 0 ? `
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
                    ` : '<p style="color: var(--gray-500); text-align: center; padding: 2rem;">키워드 데이터 없음</p>'}

                    <div class="analysis-box strength">
                        <div class="analysis-label">✓ 강점</div>
                        <div class="analysis-content">${data.bm25_analysis.strengths || '-'}</div>
                    </div>

                    <div class="analysis-box weakness">
                        <div class="analysis-label">✗ 개선 필요</div>
                        <div class="analysis-content">${data.bm25_analysis.weaknesses || '-'}</div>
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
                            <div class="dimension-score">${data.semantic_analysis.topic_match?.score || 0}</div>
                        </div>
                        <div class="dimension-reason">${data.semantic_analysis.topic_match?.reason || '-'}</div>
                    </div>

                    <div class="dimension-card">
                        <div class="dimension-header">
                            <div class="dimension-name">2️⃣ 의미적 연관성</div>
                            <div class="dimension-score">${data.semantic_analysis.semantic_relevance?.score || 0}</div>
                        </div>
                        <div class="dimension-reason">${data.semantic_analysis.semantic_relevance?.reason || '-'}</div>
                    </div>

                    <div class="dimension-card">
                        <div class="dimension-header">
                            <div class="dimension-name">3️⃣ 맥락 이해도</div>
                            <div class="dimension-score">${data.semantic_analysis.context_understanding?.score || 0}</div>
                        </div>
                        <div class="dimension-reason">${data.semantic_analysis.context_understanding?.reason || '-'}</div>
                    </div>

                    <div class="dimension-card">
                        <div class="dimension-header">
                            <div class="dimension-name">4️⃣ 정보 충족도</div>
                            <div class="dimension-score">${data.semantic_analysis.information_completeness?.score || 0}</div>
                        </div>
                        <div class="dimension-reason">${data.semantic_analysis.information_completeness?.reason || '-'}</div>
                    </div>

                    <div class="analysis-box strength">
                        <div class="analysis-label">✓ 강점</div>
                        <div class="analysis-content">${data.semantic_analysis.strengths || '-'}</div>
                    </div>

                    <div class="analysis-box weakness">
                        <div class="analysis-label">✗ 개선 필요</div>
                        <div class="analysis-content">${data.semantic_analysis.weaknesses || '-'}</div>
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
                            <div class="score-value">${data.faq_analysis.faq_score || 0}</div>
                            <div class="score-total">/ 20점</div>
                        </div>
                        <div class="score-card">
                            <div class="score-label">AI 친화성</div>
                            <div class="score-value">${data.faq_analysis.ai_friendliness_score || 0}</div>
                            <div class="score-total">/ 20점</div>
                        </div>
                    </div>

                    <div class="analysis-box">
                        <div class="analysis-label">📋 구조 분석</div>
                        <div class="analysis-content">${data.faq_analysis.structure_analysis || '-'}</div>
                    </div>

                    <div class="analysis-box">
                        <div class="analysis-label">💡 개선 권고</div>
                        <div class="analysis-content">${data.faq_analysis.recommendation || '-'}</div>
                        <div style="margin-top: 0.75rem; padding-top: 0.75rem; border-top: 1px solid var(--gray-200);">
                            <strong>우선순위:</strong> <span style="color: var(--primary); font-weight: 600;">${data.faq_analysis.priority || '-'}</span>
                        </div>
                    </div>
                </div>

                <!-- 쿼리 확장 -->
                ${data.query_expansion && data.query_expansion.expansions && data.query_expansion.expansions.length > 0 ? `
                <div class="card section">
                    <div class="section-header">
                        <div class="section-icon">🔍</div>
                        <span>쿼리 확장 결과</span>
                    </div>
                    <div class="expansion-list">
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
                ${data.relevance_evidence && data.relevance_evidence.evidence && data.relevance_evidence.evidence.length > 0 ? `
                <div class="card section">
                    <div class="section-header">
                        <div class="section-icon">📌</div>
                        <span>관련성 증거</span>
                    </div>
                    <div class="evidence-list">
                        ${data.relevance_evidence.evidence.map(ev => `
                        <div class="list-item">
                            <div class="list-item-content" style="margin-bottom: 0.75rem;">"${ev.passage}"</div>
                            <div style="display: flex; gap: 1rem; font-size: 0.875rem;">
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

                    ${data.recommendations.missing_info && data.recommendations.missing_info.length > 0 ? `
                    <h3 style="color: var(--danger); margin-bottom: 1rem; font-size: 1.125rem;">📌 누락된 정보</h3>
                    <div class="recommendation-list">
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

                    ${data.recommendations.action_items && data.recommendations.action_items.length > 0 ? `
                    <h3 style="color: var(--success); margin: 2rem 0 1rem; font-size: 1.125rem;">🎯 실행 액션</h3>
                    <div class="recommendation-list">
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

                    <h3 style="color: var(--primary); margin: 2rem 0 1rem; font-size: 1.125rem;">📈 예상 점수 증가</h3>
                    <div class="score-increase-grid">
                        <div class="score-increase-item">
                            <div class="score-increase-label">BM25</div>
                            <div class="score-increase-value">+${data.recommendations.expected_score_increase?.bm25 || 0}</div>
                        </div>
                        <div class="score-increase-item">
                            <div class="score-increase-label">시맨틱</div>
                            <div class="score-increase-value">+${data.recommendations.expected_score_increase?.semantic || 0}</div>
                        </div>
                        <div class="score-increase-item">
                            <div class="score-increase-label">FAQ</div>
                            <div class="score-increase-value">+${data.recommendations.expected_score_increase?.faq || 0}</div>
                        </div>
                    </div>
                </div>
            `;

            resultDiv.innerHTML = html;
            resultDiv.style.display = 'block';
            window.scrollTo({ top: resultDiv.offsetTop - 100, behavior: 'smooth' });
        }
    </script>
</body>
</html>
