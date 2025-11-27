<?php
/**
 * AEO Analytics v12 Data Manager
 * OpenAI, Claude, Gemini 3개 API 데이터 통합 관리
 * 
 * [v12 변경사항]
 * - 멀티 데이터 소스 지원 (aeo_data, aeo_data_claude, aeo_data_gemini)
 * - API 제공자별 필터링 기능 추가
 * - ID 형식 호환 (8자리/32자리)
 * - 통합 통계 기능
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');

// ========================================
// 데이터 디렉토리 설정
// ========================================

$BASE_DIR = __DIR__;

// 멀티 데이터 소스 정의
$DATA_SOURCES = [
    'openai' => [
        'dir' => $BASE_DIR . '/aeo_data',
        'name' => 'OpenAI',
        'icon' => '🤖',
        'color' => '#10a37f'
    ],
    'claude' => [
        'dir' => $BASE_DIR . '/aeo_data_claude',
        'name' => 'Claude',
        'icon' => '🧠',
        'color' => '#8b5cf6'
    ],
    'gemini' => [
        'dir' => $BASE_DIR . '/aeo_data_gemini',
        'name' => 'Gemini',
        'icon' => '✨',
        'color' => '#4285f4'
    ]
];

define('DEBUG_MODE', isset($_GET['debug']));
define('OPENAI_API_KEY', 'xxx');
define('API_TIMEOUT', 45);

// ========================================
// 인덱스 파일 로드 (멀티 소스)
// ========================================

function loadIndex($source = 'all') {
    global $DATA_SOURCES;
    
    $allData = [];
    
    $sources = ($source === 'all') ? array_keys($DATA_SOURCES) : [$source];
    
    foreach ($sources as $src) {
        if (!isset($DATA_SOURCES[$src])) continue;
        
        $indexFile = $DATA_SOURCES[$src]['dir'] . '/index.json';
        
        if (!file_exists($indexFile)) {
            if (DEBUG_MODE) {
                error_log("Index file not found: $indexFile");
            }
            continue;
        }
        
        $content = file_get_contents($indexFile);
        if ($content === false) continue;
        
        $data = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) continue;
        
        // 각 항목에 api_provider 추가
        foreach ($data as $id => &$item) {
            $item['api_provider'] = $src;
            $item['api_name'] = $DATA_SOURCES[$src]['name'];
            $item['api_icon'] = $DATA_SOURCES[$src]['icon'];
            $item['api_color'] = $DATA_SOURCES[$src]['color'];
            
            // ID 정규화 (8자리 또는 32자리)
            $item['id'] = $id;
            $item['id_short'] = substr($id, 0, 8);
        }
        
        $allData = array_merge($allData, $data);
    }
    
    return $allData;
}

// ========================================
// 개별 JSON 파일 로드 (멀티 소스 지원)
// ========================================

function loadDetailData($id, $date, $apiProvider = null) {
    global $DATA_SOURCES;
    
    // API 제공자가 지정된 경우 해당 소스에서만 검색
    $sources = $apiProvider ? [$apiProvider] : array_keys($DATA_SOURCES);
    
    foreach ($sources as $src) {
        if (!isset($DATA_SOURCES[$src])) continue;
        
        $dateDir = $DATA_SOURCES[$src]['dir'] . '/' . $date;
        
        if (!is_dir($dateDir)) continue;
        
        // ID 길이에 따라 패턴 설정 (8자리 또는 32자리)
        $idShort = substr($id, 0, 8);
        
        // 패턴 1: 짧은 ID (Gemini 스타일)
        $pattern1 = $dateDir . '/' . $date . '_' . $idShort . '.json';
        
        // 패턴 2: 전체 ID (OpenAI/Claude 스타일)
        $pattern2 = $dateDir . '/' . $date . '_' . $id . '.json';
        
        // 패턴 3: glob으로 부분 매칭
        $pattern3 = $dateDir . '/' . $date . '_' . $idShort . '*.json';
        
        // 패턴 1 확인
        if (file_exists($pattern1)) {
            $content = file_get_contents($pattern1);
            if ($content !== false) {
                $data = json_decode($content, true);
                if ($data) {
                    $data['api_provider'] = $src;
                    $data['api_name'] = $DATA_SOURCES[$src]['name'];
                    return $data;
                }
            }
        }
        
        // 패턴 2 확인
        if (file_exists($pattern2)) {
            $content = file_get_contents($pattern2);
            if ($content !== false) {
                $data = json_decode($content, true);
                if ($data) {
                    $data['api_provider'] = $src;
                    $data['api_name'] = $DATA_SOURCES[$src]['name'];
                    return $data;
                }
            }
        }
        
        // 패턴 3: glob으로 검색
        $files = glob($pattern3);
        if (!empty($files)) {
            $content = file_get_contents($files[0]);
            if ($content !== false) {
                $data = json_decode($content, true);
                if ($data) {
                    $data['api_provider'] = $src;
                    $data['api_name'] = $DATA_SOURCES[$src]['name'];
                    return $data;
                }
            }
        }
    }
    
    if (DEBUG_MODE) {
        error_log("Detail file not found for ID: $id, Date: $date");
    }
    
    return null;
}

// ========================================
// 전체 데이터 목록 조회
// ========================================

function getAllData($filters = []) {
    $source = $filters['api_provider'] ?? 'all';
    $index = loadIndex($source);
    $results = [];
    
    foreach ($index as $id => $meta) {
        // 평가 필터
        if (!empty($filters['rating']) && ($meta['evaluation'] ?? '') !== $filters['rating']) {
            continue;
        }
        
        // 모델 필터
        if (!empty($filters['model']) && ($meta['model'] ?? '') !== $filters['model']) {
            continue;
        }
        
        // API 제공자 필터 (단일 소스 선택 시 이미 적용됨)
        if (!empty($filters['api_provider']) && $filters['api_provider'] !== 'all') {
            if (($meta['api_provider'] ?? '') !== $filters['api_provider']) {
                continue;
            }
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
        if (!empty($filters['min_score']) && ($meta['hybrid_score'] ?? 0) < $filters['min_score']) {
            continue;
        }
        
        if (!empty($filters['max_score']) && ($meta['hybrid_score'] ?? 0) > $filters['max_score']) {
            continue;
        }
        
        // 날짜 범위 필터
        if (!empty($filters['date_from']) && ($meta['date'] ?? '') < $filters['date_from']) {
            continue;
        }
        
        if (!empty($filters['date_to']) && ($meta['date'] ?? '') > $filters['date_to']) {
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

// ========================================
// 상세 데이터 조회
// ========================================

function getDetailData($id) {
    $index = loadIndex('all');
    
    // ID로 직접 검색 (8자리 또는 32자리)
    $meta = null;
    foreach ($index as $indexId => $item) {
        if ($indexId === $id || substr($indexId, 0, 8) === substr($id, 0, 8)) {
            $meta = $item;
            $id = $indexId; // 전체 ID로 업데이트
            break;
        }
    }
    
    if (!$meta) {
        return [
            'success' => false,
            'error' => 'ID를 찾을 수 없습니다: ' . $id
        ];
    }
    
    $apiProvider = $meta['api_provider'] ?? null;
    $detail = loadDetailData($id, $meta['date'], $apiProvider);
    
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

// ========================================
// 통합 통계 데이터 생성
// ========================================

function getStatistics($source = 'all') {
    global $DATA_SOURCES;
    
    $index = loadIndex($source);
    
    $stats = [
        'total_count' => count($index),
        'rating_distribution' => [
            '우수' => 0,
            '양호' => 0,
            '보통' => 0,
            '미흡' => 0
        ],
        'model_distribution' => [],
        'api_distribution' => [],
        'avg_score' => 0,
        'score_ranges' => [
            '90-100' => 0,
            '75-89' => 0,
            '60-74' => 0,
            '0-59' => 0
        ],
        'by_api' => []
    ];
    
    // API별 통계 초기화
    foreach ($DATA_SOURCES as $src => $info) {
        $stats['api_distribution'][$src] = 0;
        $stats['by_api'][$src] = [
            'name' => $info['name'],
            'icon' => $info['icon'],
            'color' => $info['color'],
            'count' => 0,
            'avg_score' => 0,
            'total_score' => 0
        ];
    }
    
    $totalScore = 0;
    
    foreach ($index as $meta) {
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
        
        // API 분포
        $api = $meta['api_provider'] ?? 'unknown';
        if (isset($stats['api_distribution'][$api])) {
            $stats['api_distribution'][$api]++;
            $stats['by_api'][$api]['count']++;
            $stats['by_api'][$api]['total_score'] += ($meta['hybrid_score'] ?? 0);
        }
        
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
    
    // 평균 계산
    if (count($index) > 0) {
        $stats['avg_score'] = round($totalScore / count($index), 2);
    }
    
    // API별 평균 계산
    foreach ($stats['by_api'] as $api => &$apiStats) {
        if ($apiStats['count'] > 0) {
            $apiStats['avg_score'] = round($apiStats['total_score'] / $apiStats['count'], 2);
        }
        unset($apiStats['total_score']); // 임시 데이터 제거
    }
    
    return [
        'success' => true,
        'data' => $stats
    ];
}

// ========================================
// 사용 가능한 모델 목록
// ========================================

function getAvailableModels() {
    $index = loadIndex('all');
    $models = [];
    
    foreach ($index as $meta) {
        $model = $meta['model'] ?? 'unknown';
        $api = $meta['api_provider'] ?? 'unknown';
        
        $key = $model;
        if (!isset($models[$key])) {
            $models[$key] = [
                'model' => $model,
                'api_provider' => $api,
                'count' => 0
            ];
        }
        $models[$key]['count']++;
    }
    
    return [
        'success' => true,
        'data' => array_values($models)
    ];
}

// ========================================
// OpenAI API 호출
// ========================================

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

// ========================================
// 질문 리스트 생성
// ========================================

function generateQuestionList($id) {
    $detailResult = getDetailData($id);
    
    if (!$detailResult['success']) {
        return $detailResult;
    }
    
    $data = $detailResult['data'];
    
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

    $userPrompt = "다음 분석 데이터를 바탕으로 이 콘텐츠 주제에 맞는 '검색 적합 공통 질문 리스트'를 생성해주세요.

[분석 데이터]
원본 검색어: {$query}
페이지 URL: {$url}
주요 키워드: {$keywordsStr}

쿼리 확장 예시:
{$expansionsStr}

부족한 정보:
{$missingInfoStr}

위 정보를 참고하여, 이 주제에 대해 사용자들이 실제로 검색할 만한 7-10개의 자연스러운 질문을 생성해주세요.";

    $result = callOpenAIAPI($systemPrompt, $userPrompt, 'gpt-4o-mini', 0.8);
    
    if (isset($result['error'])) {
        return [
            'success' => false,
            'error' => $result['error']
        ];
    }
    
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
                'keywords' => array_slice($keywords, 0, 5),
                'api_provider' => $data['api_provider'] ?? 'unknown'
            ]
        ]
    ];
}

// ========================================
// API 소스 목록 조회
// ========================================

function getApiSources() {
    global $DATA_SOURCES;
    
    $sources = [];
    foreach ($DATA_SOURCES as $key => $info) {
        $indexFile = $info['dir'] . '/index.json';
        $count = 0;
        
        if (file_exists($indexFile)) {
            $content = file_get_contents($indexFile);
            $data = json_decode($content, true);
            if ($data) {
                $count = count($data);
            }
        }
        
        $sources[] = [
            'key' => $key,
            'name' => $info['name'],
            'icon' => $info['icon'],
            'color' => $info['color'],
            'count' => $count,
            'available' => is_dir($info['dir'])
        ];
    }
    
    return [
        'success' => true,
        'data' => $sources
    ];
}

// ========================================
// 라우팅
// ========================================

try {
    $action = $_GET['action'] ?? 'list';
    
    // 디버그 모드
    if ($action === 'debug') {
        global $DATA_SOURCES;
        $debugInfo = [
            'php_version' => PHP_VERSION,
            'current_dir' => __DIR__,
            'data_sources' => []
        ];
        
        foreach ($DATA_SOURCES as $key => $info) {
            $debugInfo['data_sources'][$key] = [
                'dir' => $info['dir'],
                'exists' => is_dir($info['dir']),
                'writable' => is_writable($info['dir']),
                'index_exists' => file_exists($info['dir'] . '/index.json'),
                'index_count' => 0
            ];
            
            if (file_exists($info['dir'] . '/index.json')) {
                $content = file_get_contents($info['dir'] . '/index.json');
                $data = json_decode($content, true);
                $debugInfo['data_sources'][$key]['index_count'] = $data ? count($data) : 0;
            }
        }
        
        echo json_encode([
            'success' => true,
            'debug_info' => $debugInfo
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
    
    switch ($action) {
        case 'list':
            $filters = [
                'rating' => $_GET['rating'] ?? '',
                'model' => $_GET['model'] ?? '',
                'api_provider' => $_GET['api_provider'] ?? 'all',
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
            $source = $_GET['api_provider'] ?? 'all';
            echo json_encode(getStatistics($source), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            break;
            
        case 'models':
            echo json_encode(getAvailableModels(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            break;
            
        case 'sources':
            echo json_encode(getApiSources(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
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
