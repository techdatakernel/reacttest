<?php
// api/ab-test-config.php - Multi-page A/B Test Configuration API

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/ab-test-error.log');

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

// OPTIONS 요청 처리
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

define('CONFIG_FILE', __DIR__ . '/ab-test-config.json');

// 기본 설정 구조
function getDefaultConfig() {
    return [
        'pages' => [],
        'global' => [
            'cookieExpiry' => 30,
            'defaultMode' => 'ab_test'
        ]
    ];
}

// 새 페이지 기본 설정
function getDefaultPageConfig($pagePath, $testName = '') {
    return [
        'enabled' => true,
        'testName' => $testName ?: "Test for {$pagePath}",
        'mode' => 'ab_test',
        'variants' => [
            'A' => [
                'name' => 'Variant A',
                'order' => []
            ],
            'B' => [
                'name' => 'Variant B',
                'order' => []
            ]
        ],
        'schedule' => [
            'enabled' => false,
            'startDate' => null,
            'endDate' => null,
            'variant' => null
        ],
        'lastUpdated' => date('c'),
        'updatedBy' => 'system'
    ];
}

// 설정 파일 초기화
function initConfigFile() {
    $defaultConfig = getDefaultConfig();

    // 디렉토리 확인
    $dir = dirname(CONFIG_FILE);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    // 파일 생성
    if (file_put_contents(CONFIG_FILE, json_encode($defaultConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
        chmod(CONFIG_FILE, 0666);
        error_log("AB Test Config: 초기 설정 파일 생성 완료");
        return $defaultConfig;
    } else {
        error_log("AB Test Config: 설정 파일 생성 실패");
        return null;
    }
}

// 설정 로드
function loadConfig() {
    if (!file_exists(CONFIG_FILE)) {
        error_log("AB Test Config: 설정 파일 없음, 초기화 시작");
        return initConfigFile();
    }

    $content = file_get_contents(CONFIG_FILE);
    if ($content === false) {
        error_log("AB Test Config: 파일 읽기 실패");
        return getDefaultConfig();
    }

    $config = json_decode($content, true);
    if ($config === null) {
        error_log("AB Test Config: JSON 파싱 실패, 재초기화");
        return initConfigFile();
    }

    // 구조 검증 및 마이그레이션
    if (!isset($config['pages'])) {
        error_log("AB Test Config: 구버전 감지, 마이그레이션 중...");
        $oldConfig = $config;
        $config = getDefaultConfig();

        // 기존 설정이 있으면 첫 페이지로 마이그레이션
        if (isset($oldConfig['mode'])) {
            $config['pages']['/products/hanmac-extracreamydraftcan-handle-package'] = [
                'enabled' => true,
                'testName' => '한맥 판매처 순서 최적화',
                'mode' => $oldConfig['mode'],
                'variants' => $oldConfig['variants'] ?? ['A' => [], 'B' => []],
                'schedule' => $oldConfig['schedule'] ?? getDefaultPageConfig('')['schedule'],
                'lastUpdated' => $oldConfig['lastUpdated'] ?? date('c'),
                'updatedBy' => $oldConfig['updatedBy'] ?? 'system'
            ];
            saveConfig($config);
        }
    }

    return $config;
}

// 설정 저장
function saveConfig($config) {
    $json = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if (file_put_contents(CONFIG_FILE, $json)) {
        chmod(CONFIG_FILE, 0666);
        return true;
    }
    return false;
}

// GET - 설정 조회
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $config = loadConfig();

        if ($config === null) {
            http_response_code(500);
            echo json_encode([
                'error' => 'Failed to load config',
                'defaultConfig' => getDefaultConfig()
            ]);
            exit;
        }

        // 특정 페이지 설정 요청
        if (isset($_GET['pagePath'])) {
            $pagePath = $_GET['pagePath'];

            if (isset($config['pages'][$pagePath])) {
                $pageConfig = $config['pages'][$pagePath];

                // 스케줄 활성 상태 추가
                if ($pageConfig['mode'] === 'scheduled' && $pageConfig['schedule']['enabled']) {
                    $now = time();
                    $start = $pageConfig['schedule']['startDate'] ? strtotime($pageConfig['schedule']['startDate']) : null;
                    $end = $pageConfig['schedule']['endDate'] ? strtotime($pageConfig['schedule']['endDate']) : null;

                    $isActive = true;
                    if ($start && $now < $start) $isActive = false;
                    if ($end && $now > $end) $isActive = false;

                    $pageConfig['schedule']['isActive'] = $isActive;
                }

                http_response_code(200);
                echo json_encode([
                    'pagePath' => $pagePath,
                    'config' => $pageConfig,
                    'global' => $config['global']
                ], JSON_UNESCAPED_UNICODE);
            } else {
                // 페이지 설정이 없으면 전역 기본값 반환
                http_response_code(200);
                echo json_encode([
                    'pagePath' => $pagePath,
                    'config' => null,
                    'global' => $config['global'],
                    'message' => 'No specific config for this page'
                ], JSON_UNESCAPED_UNICODE);
            }
        } else {
            // 전체 페이지 목록 반환 (대시보드용)
            http_response_code(200);
            echo json_encode($config, JSON_UNESCAPED_UNICODE);
        }

    } catch (Exception $e) {
        error_log("AB Test Config GET Error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'error' => $e->getMessage(),
            'defaultConfig' => getDefaultConfig()
        ]);
    }
    exit;
}

// POST - 설정 업데이트
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $input = json_decode(file_get_contents('php://input'), true);

        if (!$input) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON']);
            exit;
        }

        $config = loadConfig();
        if ($config === null) {
            $config = getDefaultConfig();
        }

        // 액션 처리
        $action = $input['action'] ?? 'update';

        // 페이지 추가
        if ($action === 'addPage') {
            $pagePath = $input['pagePath'] ?? null;
            $testName = $input['testName'] ?? '';

            if (!$pagePath) {
                http_response_code(400);
                echo json_encode(['error' => 'pagePath is required']);
                exit;
            }

            if (isset($config['pages'][$pagePath])) {
                http_response_code(400);
                echo json_encode(['error' => 'Page already exists']);
                exit;
            }

            $config['pages'][$pagePath] = getDefaultPageConfig($pagePath, $testName);

            if (saveConfig($config)) {
                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'message' => 'Page added successfully',
                    'pagePath' => $pagePath,
                    'config' => $config['pages'][$pagePath]
                ]);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to save config']);
            }
            exit;
        }

        // 페이지 삭제
        if ($action === 'deletePage') {
            $pagePath = $input['pagePath'] ?? null;

            if (!$pagePath) {
                http_response_code(400);
                echo json_encode(['error' => 'pagePath is required']);
                exit;
            }

            if (!isset($config['pages'][$pagePath])) {
                http_response_code(404);
                echo json_encode(['error' => 'Page not found']);
                exit;
            }

            unset($config['pages'][$pagePath]);

            if (saveConfig($config)) {
                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'message' => 'Page deleted successfully'
                ]);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to save config']);
            }
            exit;
        }

        // 페이지 설정 업데이트
        if ($action === 'update') {
            $pagePath = $input['pagePath'] ?? null;

            if (!$pagePath) {
                http_response_code(400);
                echo json_encode(['error' => 'pagePath is required']);
                exit;
            }

            // 페이지가 없으면 생성
            if (!isset($config['pages'][$pagePath])) {
                $config['pages'][$pagePath] = getDefaultPageConfig($pagePath);
            }

            $pageConfig = &$config['pages'][$pagePath];

            // 필드 업데이트
            if (isset($input['enabled'])) {
                $pageConfig['enabled'] = (bool)$input['enabled'];
            }

            if (isset($input['testName'])) {
                $pageConfig['testName'] = $input['testName'];
            }

            if (isset($input['mode'])) {
                $allowedModes = ['ab_test', 'force_a', 'force_b', 'scheduled'];
                if (in_array($input['mode'], $allowedModes)) {
                    $pageConfig['mode'] = $input['mode'];
                }
            }

            if (isset($input['variants'])) {
                $pageConfig['variants'] = $input['variants'];
            }

            if (isset($input['schedule'])) {
                $pageConfig['schedule'] = array_merge($pageConfig['schedule'], $input['schedule']);
            }

            // 메타데이터
            $pageConfig['lastUpdated'] = date('c');
            $pageConfig['updatedBy'] = $input['updatedBy'] ?? 'admin';

            if (saveConfig($config)) {
                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'message' => 'Configuration updated successfully',
                    'config' => $pageConfig
                ]);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to save config']);
            }
            exit;
        }

        http_response_code(400);
        echo json_encode(['error' => 'Unknown action']);

    } catch (Exception $e) {
        error_log("AB Test Config POST Error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
?>
