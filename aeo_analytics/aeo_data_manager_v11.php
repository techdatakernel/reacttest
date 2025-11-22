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

// 데이터 디렉토리 설정 (절대 경로 우선, 없으면 상대 경로)
if (file_exists(__DIR__ . '/aeo_data')) {
    define('DATA_DIR', __DIR__ . '/aeo_data');
} elseif (file_exists(__DIR__ . '/../aeo_data')) {
    define('DATA_DIR', __DIR__ . '/../aeo_data');
} else {
    // 현재 디렉토리 기준
    define('DATA_DIR', __DIR__ . '/aeo_data');
}

define('INDEX_FILE', DATA_DIR . '/index.json');
define('DEBUG_MODE', isset($_GET['debug'])); // ?debug=1 로 디버그 모드 활성화

// 인덱스 파일 로드
function loadIndex() {
    if (!file_exists(INDEX_FILE)) {
        if (DEBUG_MODE) {
            error_log('Index file not found: ' . INDEX_FILE);
        }
        return [];
    }

    $content = file_get_contents(INDEX_FILE);
    if ($content === false) {
        if (DEBUG_MODE) {
            error_log('Failed to read index file: ' . INDEX_FILE);
        }
        return [];
    }

    $data = json_decode($content, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        if (DEBUG_MODE) {
            error_log('JSON decode error: ' . json_last_error_msg());
        }
        return [];
    }

    return $data ?? [];
}

// 개별 JSON 파일 로드
function loadDetailData($id, $date) {
    $dateDir = DATA_DIR . '/' . $date;

    if (!is_dir($dateDir)) {
        if (DEBUG_MODE) {
            error_log('Date directory not found: ' . $dateDir);
        }
        return null;
    }

    // ID의 첫 8자로 파일 찾기
    $pattern = $dateDir . '/' . $date . '_' . substr($id, 0, 8) . '.json';
    $files = glob($pattern);

    if (empty($files)) {
        if (DEBUG_MODE) {
            error_log('Detail file not found with pattern: ' . $pattern);
            error_log('Available files: ' . print_r(glob($dateDir . '/*.json'), true));
        }
        return null;
    }

    $content = file_get_contents($files[0]);
    if ($content === false) {
        if (DEBUG_MODE) {
            error_log('Failed to read detail file: ' . $files[0]);
        }
        return null;
    }

    $data = json_decode($content, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        if (DEBUG_MODE) {
            error_log('JSON decode error in detail file: ' . json_last_error_msg());
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
        // 필터 적용
        if (!empty($filters['rating']) && $meta['evaluation'] !== $filters['rating']) {
            continue;
        }

        if (!empty($filters['model']) && $meta['model'] !== $filters['model']) {
            continue;
        }

        if (!empty($filters['search'])) {
            $search = strtolower($filters['search']);
            $query = strtolower($meta['query'] ?? '');
            $url = strtolower($meta['url'] ?? '');

            if (strpos($query, $search) === false && strpos($url, $search) === false) {
                continue;
            }
        }

        if (!empty($filters['min_score']) && $meta['hybrid_score'] < $filters['min_score']) {
            continue;
        }

        if (!empty($filters['max_score']) && $meta['hybrid_score'] > $filters['max_score']) {
            continue;
        }

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

// 라우팅
try {
    $action = $_GET['action'] ?? 'list';

    // 디버그 모드
    if ($action === 'debug') {
        echo json_encode([
            'success' => true,
            'debug_info' => [
                'php_version' => PHP_VERSION,
                'data_dir' => DATA_DIR,
                'data_dir_exists' => is_dir(DATA_DIR),
                'data_dir_writable' => is_writable(DATA_DIR),
                'index_file' => INDEX_FILE,
                'index_exists' => file_exists(INDEX_FILE),
                'index_readable' => is_readable(INDEX_FILE),
                'current_dir' => __DIR__,
                'script_name' => $_SERVER['SCRIPT_NAME'] ?? 'unknown',
                'index_count' => count(loadIndex())
            ]
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    switch ($action) {
        case 'list':
            $filters = [
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
