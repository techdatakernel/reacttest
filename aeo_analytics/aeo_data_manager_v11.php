<?php
/**
 * AEO Analytics v11 Data Manager
 * v11.1 데이터 구조에 최적화된 백엔드 API
 */

// 에러 처리
error_reporting(E_ALL);
ini_set('display_errors', 0); // 프로덕션에서는 0

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');

// 데이터 소스 설정 (세 가지 AI 모델 통합)
define('DATA_SOURCES', [
    'openai' => [
        'dir' => __DIR__ . '/aeo_data',
        'name' => 'OpenAI',
        'id_length' => 32
    ],
    'claude' => [
        'dir' => __DIR__ . '/aeo_data_claude',
        'name' => 'Claude',
        'id_length' => 32
    ],
    'gemini' => [
        'dir' => __DIR__ . '/aeo_data_gemini',
        'name' => 'Gemini',
        'id_length' => 8
    ]
]);

define('DEBUG_MODE', isset($_GET['debug'])); // ?debug=1 로 디버그 모드 활성화
define('OPENAI_API_KEY', 'xxx'); // OpenAI API Key
define('API_TIMEOUT', 45);

// 인덱스 파일 로드 (세 가지 데이터 소스 통합)
function loadIndex() {
    $allData = [];

    foreach (DATA_SOURCES as $sourceKey => $source) {
        $indexFile = $source['dir'] . '/index.json';

        if (!file_exists($indexFile)) {
            if (DEBUG_MODE) {
                error_log("Index file not found for {$sourceKey}: {$indexFile}");
            }
            continue;
        }

        $content = file_get_contents($indexFile);
        if ($content === false) {
            if (DEBUG_MODE) {
                error_log("Failed to read index file for {$sourceKey}: {$indexFile}");
            }
            continue;
        }

        $data = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            if (DEBUG_MODE) {
                error_log("JSON decode error for {$sourceKey}: " . json_last_error_msg());
            }
            continue;
        }

        // 각 데이터에 source 정보 추가
        if (is_array($data)) {
            foreach ($data as $id => $meta) {
                $meta['source'] = $sourceKey;
                $meta['source_name'] = $source['name'];
                $allData[$id] = $meta;
            }
        }
    }

    return $allData;
}

// 개별 JSON 파일 로드 (ID 길이에 따라 자동으로 데이터 소스 찾기)
function loadDetailData($id, $date, $source = null) {
    // source가 지정되지 않은 경우, ID 길이로 판단
    if ($source === null) {
        $idLength = strlen($id);
        foreach (DATA_SOURCES as $sourceKey => $sourceInfo) {
            if ($sourceInfo['id_length'] === $idLength) {
                $source = $sourceKey;
                break;
            }
        }

        // 여전히 찾지 못한 경우, 모든 소스 검색
        if ($source === null) {
            foreach (DATA_SOURCES as $sourceKey => $sourceInfo) {
                $result = loadDetailData($id, $date, $sourceKey);
                if ($result !== null) {
                    return $result;
                }
            }
            return null;
        }
    }

    // 데이터 소스 정보 가져오기
    if (!isset(DATA_SOURCES[$source])) {
        if (DEBUG_MODE) {
            error_log("Unknown data source: {$source}");
        }
        return null;
    }

    $sourceInfo = DATA_SOURCES[$source];
    $dateDir = $sourceInfo['dir'] . '/' . $date;

    if (!is_dir($dateDir)) {
        if (DEBUG_MODE) {
            error_log("Date directory not found for {$source}: {$dateDir}");
        }
        return null;
    }

    // ID 길이에 따라 파일 패턴 결정
    $idPrefix = substr($id, 0, min($sourceInfo['id_length'], strlen($id)));
    $pattern = $dateDir . '/' . $date . '_' . $idPrefix . '.json';
    $files = glob($pattern);

    if (empty($files)) {
        if (DEBUG_MODE) {
            error_log("Detail file not found for {$source} with pattern: {$pattern}");
            error_log('Available files: ' . print_r(glob($dateDir . '/*.json'), true));
        }
        return null;
    }

    $content = file_get_contents($files[0]);
    if ($content === false) {
        if (DEBUG_MODE) {
            error_log("Failed to read detail file for {$source}: {$files[0]}");
        }
        return null;
    }

    $data = json_decode($content, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        if (DEBUG_MODE) {
            error_log("JSON decode error in detail file for {$source}: " . json_last_error_msg());
        }
        return null;
    }

    return $data;
}

// 전체 데이터 목록 조회
function getAllData($filters = []) {
    $index = loadIndex();
    $results = [];

    foreach ($index as $id => $meta) {
        // 소스 필터 (새로 추가)
        if (!empty($filters['source']) && $meta['source'] !== $filters['source']) {
            continue;
        }

        // 평가 필터
        if (!empty($filters['rating']) && $meta['evaluation'] !== $filters['rating']) {
            continue;
        }

        // 모델 필터
        if (!empty($filters['model']) && $meta['model'] !== $filters['model']) {
            continue;
        }

        // 검색 필터
        if (!empty($filters['search'])) {
            $search = strtolower($filters['search']);
            $query = strtolower($meta['query'] ?? '');
            $url = strtolower($meta['url'] ?? '');

            if (strpos($query, $search) === false && strpos($url, $search) === false) {
                continue;
            }
        }

        // 점수 범위 필터
        if (!empty($filters['min_score']) && $meta['hybrid_score'] < $filters['min_score']) {
            continue;
        }

        if (!empty($filters['max_score']) && $meta['hybrid_score'] > $filters['max_score']) {
            continue;
        }

        // 날짜 범위 필터
        if (!empty($filters['date_from']) && $meta['date'] < $filters['date_from']) {
            continue;
        }

        if (!empty($filters['date_to']) && $meta['date'] > $filters['date_to']) {
            continue;
        }

        $results[] = $meta;
    }

    // 정렬
    $sortBy = $filters['sort_by'] ?? 'timestamp';
    $sortOrder = $filters['sort_order'] ?? 'desc';

    usort($results, function($a, $b) use ($sortBy, $sortOrder) {
        $aVal = $a[$sortBy] ?? 0;
        $bVal = $b[$sortBy] ?? 0;

        if ($sortOrder === 'desc') {
            return $bVal <=> $aVal;
        } else {
            return $aVal <=> $bVal;
        }
    });

    // 페이지네이션
    $page = intval($filters['page'] ?? 1);
    $perPage = intval($filters['per_page'] ?? 20);
    $offset = ($page - 1) * $perPage;

    $total = count($results);
    $paged = array_slice($results, $offset, $perPage);

    return [
        'success' => true,
        'data' => $paged,
        'pagination' => [
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => ceil($total / $perPage)
        ]
    ];
}

// 상세 데이터 조회
function getDetailData($id) {
    $index = loadIndex();

    if (!isset($index[$id])) {
        return [
            'success' => false,
            'error' => 'ID를 찾을 수 없습니다.'
        ];
    }

    $meta = $index[$id];
    $detail = loadDetailData($id, $meta['date']);

    if (!$detail) {
        return [
            'success' => false,
            'error' => '상세 데이터를 로드할 수 없습니다.'
        ];
    }

    return [
        'success' => true,
        'data' => $detail
    ];
}

// 통계 데이터 생성
function getStatistics() {
    $index = loadIndex();

    $stats = [
        'total_count' => count($index),
        'source_distribution' => [
            'openai' => 0,
            'claude' => 0,
            'gemini' => 0
        ],
        'rating_distribution' => [
            '우수' => 0,
            '양호' => 0,
            '보통' => 0,
            '미흡' => 0
        ],
        'model_distribution' => [],
        'avg_score' => 0,
        'avg_processing_time' => 0,
        'score_ranges' => [
            '90-100' => 0,
            '75-89' => 0,
            '60-74' => 0,
            '0-59' => 0
        ]
    ];

    $totalScore = 0;

    foreach ($index as $meta) {
        // 소스 분포 (새로 추가)
        $source = $meta['source'] ?? 'unknown';
        if (isset($stats['source_distribution'][$source])) {
            $stats['source_distribution'][$source]++;
        }

        // 평가 분포
        $rating = $meta['evaluation'] ?? '미흡';
        if (isset($stats['rating_distribution'][$rating])) {
            $stats['rating_distribution'][$rating]++;
        }

        // 모델 분포
        $model = $meta['model'] ?? 'unknown';
        if (!isset($stats['model_distribution'][$model])) {
            $stats['model_distribution'][$model] = 0;
        }
        $stats['model_distribution'][$model]++;

        // 점수 범위
        $score = $meta['hybrid_score'] ?? 0;
        $totalScore += $score;

        if ($score >= 90) {
            $stats['score_ranges']['90-100']++;
        } elseif ($score >= 75) {
            $stats['score_ranges']['75-89']++;
        } elseif ($score >= 60) {
            $stats['score_ranges']['60-74']++;
        } else {
            $stats['score_ranges']['0-59']++;
        }
    }

    if (count($index) > 0) {
        $stats['avg_score'] = round($totalScore / count($index), 2);
    }

    return [
        'success' => true,
        'data' => $stats
    ];
}

// OpenAI API 호출
function callOpenAIAPI($systemPrompt, $userPrompt, $model = 'gpt-4o-mini', $temperature = 0.7) {
    $url = 'https://api.openai.com/v1/chat/completions';

    $payload = [
        'model' => $model,
        'max_tokens' => 2000,
        'temperature' => (float)$temperature,
        'messages' => [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userPrompt]
        ]
    ];

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

    if (!$response || $httpCode !== 200) {
        return ['error' => "API 호출 실패 (HTTP $httpCode)"];
    }

    $data = json_decode($response, true);

    if (!isset($data['choices'][0]['message']['content'])) {
        return ['error' => '응답 파싱 실패'];
    }

    return ['content' => $data['choices'][0]['message']['content']];
}

// 질문 리스트 생성
function generateQuestionList($id) {
    // 상세 데이터 로드
    $detailResult = getDetailData($id);

    if (!$detailResult['success']) {
        return $detailResult;
    }

    $data = $detailResult['data'];

    // 프롬프트 구성을 위한 컨텍스트 추출
    $query = $data['query'] ?? '';
    $url = $data['url'] ?? '';

    // BM25 키워드 추출
    $keywords = [];
    if (isset($data['bm25_analysis']['keywords'])) {
        foreach ($data['bm25_analysis']['keywords'] as $kw) {
            $keywords[] = $kw['keyword'];
        }
    }
    $keywordsStr = implode(', ', array_slice($keywords, 0, 5));

    // 쿼리 확장 추출
    $expansions = [];
    if (isset($data['query_expansion']['expansions'])) {
        foreach ($data['query_expansion']['expansions'] as $exp) {
            $expansions[] = $exp['query'];
        }
    }
    $expansionsStr = implode("\n", array_slice($expansions, 0, 5));

    // 추천사항 추출
    $missingInfo = [];
    if (isset($data['recommendations']['missing_info'])) {
        foreach ($data['recommendations']['missing_info'] as $info) {
            $missingInfo[] = $info['item'];
        }
    }
    $missingInfoStr = implode("\n", array_slice($missingInfo, 0, 3));

    // System Prompt
    $systemPrompt = "당신은 AEO(Answer Engine Optimization) 전문가입니다.
사용자 검색 의도를 분석하여 해당 콘텐츠 주제에 대해 사용자들이 실제로 검색할 만한 '공통 질문 리스트'를 생성하는 것이 임무입니다.

생성 기준:
1. 자연스러운 한국어 구어체 질문 (예: ~해줘, ~알려줘, ~추천해줘)
2. 다양한 검색 의도 반영 (추천, 비교, 가격, 품질, 순위, 방법 등)
3. 실제 사용자가 검색할 법한 구체적인 질문
4. 7-10개의 질문 생성
5. 각 질문은 한 줄로 작성

출력 형식:
- 번호 없이 질문만 한 줄씩 작성
- 각 질문은 줄바꿈으로 구분";

    // User Prompt
    $userPrompt = "다음 분석 데이터를 바탕으로 이 콘텐츠 주제에 맞는 '검색 적합 공통 질문 리스트'를 생성해주세요.

[분석 데이터]
원본 검색어: {$query}
페이지 URL: {$url}
주요 키워드: {$keywordsStr}

쿼리 확장 예시:
{$expansionsStr}

부족한 정보:
{$missingInfoStr}

위 정보를 참고하여, 이 주제에 대해 사용자들이 실제로 검색할 만한 7-10개의 자연스러운 질문을 생성해주세요.

예시 형식:
{$keywords[0]} 어디가 좋은지 추천해줘
{$keywords[0]} 가격 저렴한 곳 알려줘
가성비 좋은 {$keywords[0]} 추천해줘
{$keywords[0]} 순위 1위는 어디야
{$keywords[0]} 비교 분석해줘";

    // OpenAI API 호출
    $result = callOpenAIAPI($systemPrompt, $userPrompt, 'gpt-4o-mini', 0.8);

    if (isset($result['error'])) {
        return [
            'success' => false,
            'error' => $result['error']
        ];
    }

    // 질문 리스트 파싱 (줄바꿈으로 분리)
    $content = trim($result['content']);
    $questions = array_filter(array_map('trim', explode("\n", $content)));

    return [
        'success' => true,
        'data' => [
            'questions' => array_values($questions),
            'total_count' => count($questions),
            'context' => [
                'query' => $query,
                'url' => $url,
                'keywords' => array_slice($keywords, 0, 5)
            ]
        ]
    ];
}

// 라우팅
try {
    $action = $_GET['action'] ?? 'list';

    // 디버그 모드
    if ($action === 'debug') {
        $debugInfo = [
            'php_version' => PHP_VERSION,
            'current_dir' => __DIR__,
            'script_name' => $_SERVER['SCRIPT_NAME'] ?? 'unknown',
            'data_sources' => []
        ];

        // 각 데이터 소스 정보 추가
        foreach (DATA_SOURCES as $sourceKey => $source) {
            $indexFile = $source['dir'] . '/index.json';
            $debugInfo['data_sources'][$sourceKey] = [
                'name' => $source['name'],
                'dir' => $source['dir'],
                'dir_exists' => is_dir($source['dir']),
                'dir_writable' => is_writable($source['dir']),
                'index_file' => $indexFile,
                'index_exists' => file_exists($indexFile),
                'index_readable' => file_exists($indexFile) && is_readable($indexFile)
            ];
        }

        $debugInfo['total_index_count'] = count(loadIndex());

        echo json_encode([
            'success' => true,
            'debug_info' => $debugInfo
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    switch ($action) {
        case 'list':
            $filters = [
                'source' => $_GET['source'] ?? '',  // 새로 추가: openai, claude, gemini
                'rating' => $_GET['rating'] ?? '',
                'model' => $_GET['model'] ?? '',
                'search' => $_GET['search'] ?? '',
                'min_score' => $_GET['min_score'] ?? '',
                'max_score' => $_GET['max_score'] ?? '',
                'date_from' => $_GET['date_from'] ?? '',
                'date_to' => $_GET['date_to'] ?? '',
                'sort_by' => $_GET['sort_by'] ?? 'timestamp',
                'sort_order' => $_GET['sort_order'] ?? 'desc',
                'page' => $_GET['page'] ?? 1,
                'per_page' => $_GET['per_page'] ?? 20
            ];
            echo json_encode(getAllData($filters), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            break;

        case 'detail':
            $id = $_GET['id'] ?? '';
            if (empty($id)) {
                echo json_encode(['success' => false, 'error' => 'ID가 필요합니다.'], JSON_UNESCAPED_UNICODE);
            } else {
                echo json_encode(getDetailData($id), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            }
            break;

        case 'stats':
            echo json_encode(getStatistics(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            break;

        case 'generate_questions':
            $id = $_GET['id'] ?? '';
            if (empty($id)) {
                echo json_encode(['success' => false, 'error' => 'ID가 필요합니다.'], JSON_UNESCAPED_UNICODE);
            } else {
                echo json_encode(generateQuestionList($id), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            }
            break;

        default:
            echo json_encode(['success' => false, 'error' => '잘못된 액션입니다.'], JSON_UNESCAPED_UNICODE);
            break;
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => DEBUG_MODE ? $e->getTraceAsString() : null
    ], JSON_UNESCAPED_UNICODE);
}
