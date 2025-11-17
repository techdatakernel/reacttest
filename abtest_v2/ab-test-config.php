<?php
// api/ab-test-config.php - A/B 테스트 설정 API (경로 정규화 수정)

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// 설정 파일 경로
define('CONFIG_FILE', __DIR__ . '/ab-test-config.json');

// ⭐ 경로 정규화 함수 - 역슬래시 제거
function normalizePath($path) {
    // 역슬래시 제거 (이스케이프된 슬래시)
    $path = str_replace('\\/', '/', $path);
    // 슬래시 정규화 (여러 슬래시를 하나로)
    $path = preg_replace('#/+#', '/', $path);
    return trim($path);
}

// ⭐ 설정 로드 - 강화된 버전
function loadConfig() {
    if (!file_exists(CONFIG_FILE)) {
        return [
            'pages' => [],
            'global' => ['cookieExpiry' => 30, 'defaultMode' => 'ab_test']
        ];
    }
    
    $content = file_get_contents(CONFIG_FILE);
    $config = json_decode($content, true);
    
    if (!$config) {
        return [
            'pages' => [],
            'global' => ['cookieExpiry' => 30, 'defaultMode' => 'ab_test']
        ];
    }
    
    return $config;
}

// ⭐ 설정 저장 - 경로 정규화 포함
function saveConfig($data) {
    // 저장 전에 모든 페이지의 경로를 정규화
    if (isset($data['pages']) && is_array($data['pages'])) {
        $normalizedPages = [];
        foreach ($data['pages'] as $path => $config) {
            $normalizedPath = normalizePath($path);
            $normalizedPages[$normalizedPath] = $config;
        }
        $data['pages'] = $normalizedPages;
    }
    
    // JSON_UNESCAPED_SLASHES 옵션으로 슬래시 이스케이프 방지
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    
    if (file_put_contents(CONFIG_FILE, $json) === false) {
        throw new Exception('설정 파일 저장 실패');
    }
}

// ⭐ 경로에서 페이지 설정 찾기 - 유연한 매칭
function findPageConfig($pages, $searchPath) {
    $normalizedSearchPath = normalizePath($searchPath);
    
    // 정확히 일치하는 경로 찾기
    foreach ($pages as $path => $config) {
        $normalizedPath = normalizePath($path);
        if ($normalizedPath === $normalizedSearchPath) {
            return $config;
        }
    }
    
    // 일치하지 않으면 null 반환
    return null;
}

try {
    $method = $_SERVER['REQUEST_METHOD'];
    
    if ($method === 'GET') {
        // ⭐ GET 요청: 전체 페이지 설정 또는 특정 페이지 설정
        $pagePath = isset($_GET['pagePath']) ? $_GET['pagePath'] : '';
        $config = loadConfig();
        
        // ⭐ 전체 페이지 목록 요청 (pagePath가 없을 때)
        if (empty($pagePath)) {
            // 반환 시 경로 정규화
            $normalizedPages = [];
            foreach ($config['pages'] as $path => $pageConfig) {
                $normalizedPath = normalizePath($path);
                $normalizedPages[$normalizedPath] = $pageConfig;
            }
            
            echo json_encode([
                'success' => true,
                'config' => $normalizedPages ?: [],
                'global' => $config['global'] ?: ['cookieExpiry' => 30, 'defaultMode' => 'ab_test'],
                'timestamp' => date('c')
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }
        
        // ⭐ 특정 페이지 설정 요청 - 유연한 매칭
        $pageConfig = findPageConfig($config['pages'], $pagePath);
        
        echo json_encode([
            'success' => true,
            'config' => $pageConfig,
            'global' => $config['global'] ?: ['cookieExpiry' => 30, 'defaultMode' => 'ab_test'],
            'timestamp' => date('c')
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        
    } elseif ($method === 'POST') {
        // ⭐ POST 요청: 설정 수정/추가/삭제
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            throw new Exception('요청 본문이 비어있습니다');
        }
        
        $action = $input['action'] ?? 'update';
        $config = loadConfig();
        
        // ⭐ 페이지 초기화 확인
        if (!isset($config['pages'])) {
            $config['pages'] = [];
        }
        if (!isset($config['global'])) {
            $config['global'] = ['cookieExpiry' => 30, 'defaultMode' => 'ab_test'];
        }
        
        switch ($action) {
            case 'update':
                $pagePath = $input['pagePath'] ?? '';
                $mode = $input['mode'] ?? 'ab_test';
                
                if (empty($pagePath)) {
                    throw new Exception('pagePath 필수');
                }
                
                // ⭐ 경로 정규화
                $normalizedPath = normalizePath($pagePath);
                
                // 기존 정규화되지 않은 경로가 있으면 제거
                foreach ($config['pages'] as $existingPath => $existingConfig) {
                    if (normalizePath($existingPath) === $normalizedPath) {
                        unset($config['pages'][$existingPath]);
                    }
                }
                
                // ⭐ 기존 설정이 없으면 새로 생성
                if (!isset($config['pages'][$normalizedPath])) {
                    $config['pages'][$normalizedPath] = [
                        'enabled' => true,
                        'testName' => 'A/B Test',
                        'mode' => 'ab_test',
                        'variants' => [
                            'A' => ['name' => 'Variant A', 'order' => []],
                            'B' => ['name' => 'Variant B', 'order' => []]
                        ],
                        'schedule' => ['enabled' => false, 'startDate' => null, 'endDate' => null, 'variant' => null],
                        'lastUpdated' => date('c'),
                        'updatedBy' => 'system',
                        'createdAt' => date('c')
                    ];
                }
                
                $config['pages'][$normalizedPath]['mode'] = $mode;
                $config['pages'][$normalizedPath]['lastUpdated'] = date('c');
                $config['pages'][$normalizedPath]['updatedBy'] = $input['updatedBy'] ?? 'system';
                
                saveConfig($config);
                
                echo json_encode([
                    'success' => true,
                    'message' => '설정이 저장되었습니다',
                    'config' => $config['pages'][$normalizedPath]
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                break;
                
            case 'addPage':
                $pagePath = $input['pagePath'] ?? '';
                $testName = $input['testName'] ?? 'New Test';
                
                if (empty($pagePath)) {
                    throw new Exception('pagePath 필수');
                }
                
                // ⭐ 경로 정규화
                $normalizedPath = normalizePath($pagePath);
                
                // 중복 체크 (정규화된 경로로 비교)
                foreach ($config['pages'] as $existingPath => $existingConfig) {
                    if (normalizePath($existingPath) === $normalizedPath) {
                        throw new Exception('이미 존재하는 페이지입니다');
                    }
                }
                
                $now = date('c');
                $config['pages'][$normalizedPath] = [
                    'enabled' => true,
                    'testName' => $testName,
                    'mode' => 'ab_test',
                    'variants' => [
                        'A' => ['name' => 'Variant A', 'order' => []],
                        'B' => ['name' => 'Variant B', 'order' => []]
                    ],
                    'schedule' => [
                        'enabled' => false,
                        'startDate' => null,
                        'endDate' => null,
                        'variant' => null
                    ],
                    'lastUpdated' => $now,
                    'updatedBy' => 'admin',
                    'createdAt' => $now
                ];
                
                saveConfig($config);
                
                echo json_encode([
                    'success' => true,
                    'message' => '페이지가 추가되었습니다',
                    'config' => $config['pages'][$normalizedPath]
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                break;
                
            case 'deletePage':
                $pagePath = $input['pagePath'] ?? '';
                
                if (empty($pagePath)) {
                    throw new Exception('pagePath 필수');
                }
                
                // ⭐ 경로 정규화
                $normalizedPath = normalizePath($pagePath);
                
                // 정규화된 경로로 찾아서 삭제
                $found = false;
                foreach ($config['pages'] as $existingPath => $existingConfig) {
                    if (normalizePath($existingPath) === $normalizedPath) {
                        unset($config['pages'][$existingPath]);
                        $found = true;
                        break;
                    }
                }
                
                if (!$found) {
                    throw new Exception('존재하지 않는 페이지입니다');
                }
                
                saveConfig($config);
                
                echo json_encode([
                    'success' => true,
                    'message' => '페이지가 삭제되었습니다'
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                break;
                
            default:
                throw new Exception('알 수 없는 action: ' . $action);
        }
        
    } else {
        throw new Exception('지원하지 않는 메서드: ' . $method);
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
?>
