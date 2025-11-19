<?php
// api/ab-test-config.php - Dashboard 호환 버전

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

define('CONFIG_FILE', __DIR__ . '/ab-test-config.json');

function normalizePath($path) {
    $path = str_replace('\\/', '/', $path);
    $path = preg_replace('#/+#', '/', $path);
    return trim($path);
}

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

function saveConfig($data) {
    if (isset($data['pages']) && is_array($data['pages'])) {
        $normalizedPages = [];
        foreach ($data['pages'] as $path => $config) {
            $normalizedPath = normalizePath($path);
            $normalizedPages[$normalizedPath] = $config;
        }
        $data['pages'] = $normalizedPages;
    }
    
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    
    if (file_put_contents(CONFIG_FILE, $json) === false) {
        throw new Exception('설정 파일 저장 실패');
    }
}

function findPageConfig($pages, $searchPath) {
    $normalizedSearchPath = normalizePath($searchPath);
    
    foreach ($pages as $path => $config) {
        $normalizedPath = normalizePath($path);
        if ($normalizedPath === $normalizedSearchPath) {
            return $config;
        }
    }
    
    return null;
}

try {
    $method = $_SERVER['REQUEST_METHOD'];
    
    if ($method === 'GET') {
        $pagePath = isset($_GET['pagePath']) ? $_GET['pagePath'] : '';
        $config = loadConfig();
        
        // ✅ 전체 페이지 목록 요청 - Dashboard 호환 수정
        if (empty($pagePath)) {
            $normalizedPages = [];
            if (isset($config['pages']) && is_array($config['pages'])) {
                foreach ($config['pages'] as $path => $pageConfig) {
                    $normalizedPath = normalizePath($path);
                    $normalizedPages[$normalizedPath] = $pageConfig;
                }
            }
            
            // ⭐ config 키로 반환 (Dashboard 호환)
            echo json_encode([
                'success' => true,
                'config' => $normalizedPages,  // ← pages에서 config로 변경!
                'global' => $config['global'] ?? ['cookieExpiry' => 30, 'defaultMode' => 'ab_test'],
                'timestamp' => date('c')
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }
        
        // 특정 페이지 설정 요청
        $pageConfig = null;
        if (isset($config['pages']) && is_array($config['pages'])) {
            $pageConfig = findPageConfig($config['pages'], $pagePath);
        }
        
        echo json_encode([
            'success' => true,
            'config' => $pageConfig,
            'global' => $config['global'] ?? ['cookieExpiry' => 30, 'defaultMode' => 'ab_test'],
            'timestamp' => date('c')
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        
    } elseif ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            throw new Exception('요청 본문이 비어있습니다');
        }
        
        $action = $input['action'] ?? 'update';
        $config = loadConfig();
        
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
                
                $normalizedPath = normalizePath($pagePath);
                
                foreach ($config['pages'] as $existingPath => $existingConfig) {
                    if (normalizePath($existingPath) === $normalizedPath) {
                        unset($config['pages'][$existingPath]);
                    }
                }
                
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
                
                if (isset($input['schedule'])) {
                    $config['pages'][$normalizedPath]['schedule'] = $input['schedule'];
                }
                
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
                
                $normalizedPath = normalizePath($pagePath);
                
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
                
                $normalizedPath = normalizePath($pagePath);
                
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