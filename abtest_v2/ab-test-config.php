<?php
// api/ab-test-config.php

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/ab-test-error.log');

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// OPTIONS 요청 처리
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

define('CONFIG_FILE', __DIR__ . '/ab-test-config.json');

// 기본 설정
function getDefaultConfig() {
    return [
        'mode' => 'ab_test',
        'forceVariant' => null,
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
    
    return $config;
}

// GET - 현재 설정 조회
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
        
        // 스케줄 모드인 경우 현재 활성 상태 추가
        if ($config['mode'] === 'scheduled' && $config['schedule']['enabled']) {
            $now = time();
            $start = $config['schedule']['startDate'] ? strtotime($config['schedule']['startDate']) : null;
            $end = $config['schedule']['endDate'] ? strtotime($config['schedule']['endDate']) : null;
            
            $isActive = true;
            if ($start && $now < $start) {
                $isActive = false;
            }
            if ($end && $now > $end) {
                $isActive = false;
            }
            
            $config['schedule']['isActive'] = $isActive;
        }
        
        http_response_code(200);
        echo json_encode($config, JSON_UNESCAPED_UNICODE);
        
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
        
        // 현재 설정 로드
        $config = loadConfig();
        if ($config === null) {
            $config = getDefaultConfig();
        }
        
        // 모드 업데이트
        if (isset($input['mode'])) {
            $allowedModes = ['ab_test', 'force_a', 'force_b', 'scheduled'];
            if (in_array($input['mode'], $allowedModes)) {
                $config['mode'] = $input['mode'];
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid mode']);
                exit;
            }
        }
        
        // 강제 Variant 업데이트
        if (isset($input['forceVariant'])) {
            $config['forceVariant'] = $input['forceVariant'];
        }
        
        // 스케줄 업데이트
        if (isset($input['schedule'])) {
            $config['schedule'] = array_merge($config['schedule'], $input['schedule']);
        }
        
        // 메타데이터 업데이트
        $config['lastUpdated'] = date('c');
        $config['updatedBy'] = $input['updatedBy'] ?? 'admin';
        
        // 저장
        if (file_put_contents(CONFIG_FILE, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
            chmod(CONFIG_FILE, 0666);
            
            error_log("AB Test Config: 설정 업데이트 완료 - Mode: " . $config['mode']);
            
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'config' => $config,
                'message' => '설정이 저장되었습니다.'
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to save config']);
        }
        
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