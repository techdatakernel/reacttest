<?php
// api/ab-test-log.php - A/B 테스트 클릭 로그 저장 API
// 버전: v1.3 (첫/마지막 방문 페이지 정보 추가)
// 최종 업데이트: 2025-11-17

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// 로그 디렉토리 설정
define('LOG_DIR', __DIR__ . '/ab-test-logs/');

// ⭐ 경로 정규화 함수 (ab-test-config.php와 동일)
function normalizePath($path) {
    if (!$path) return '';
    $path = str_replace('\\/', '/', $path);
    $path = preg_replace('#/+#', '/', $path);
    return trim($path);
}

// ⭐ 고유 ID 생성
function generateClickId() {
    return 'click_' . uniqid() . '.' . mt_rand();
}

// ⭐ 로그 데이터 유효성 검사
function validateLog($data) {
    $errors = [];
    
    if (empty($data['variant']) || !in_array($data['variant'], ['A', 'B'])) {
        $errors[] = 'variant은 "A" 또는 "B"여야 합니다';
    }
    
    if (empty($data['elementId'])) {
        $errors[] = 'elementId는 필수입니다';
    }
    
    if (empty($data['timestamp'])) {
        $errors[] = 'timestamp는 필수입니다';
    }
    
    return $errors;
}

// ⭐ 로그 파일 경로 생성
function getLogFilePath($timestamp) {
    try {
        $date = new DateTime($timestamp);
        $fileName = 'clicks_' . $date->format('Y-m') . '.json';
        return LOG_DIR . $fileName;
    } catch (Exception $e) {
        return LOG_DIR . 'clicks_' . date('Y-m') . '.json';
    }
}

// ⭐ 로그 저장 (globalVariant 포함)
function saveLog($data) {
    try {
        // 로그 디렉토리 생성 확인
        if (!is_dir(LOG_DIR)) {
            if (!mkdir(LOG_DIR, 0755, true)) {
                throw new Exception('로그 디렉토리 생성 실패: ' . LOG_DIR);
            }
        }
        
        $logFile = getLogFilePath($data['timestamp']);
        
        // 기존 로그 읽기
        $logs = [];
        if (file_exists($logFile)) {
            $content = file_get_contents($logFile);
            $logs = json_decode($content, true) ?: [];
        }
        
        // ⭐ NEW: 새 로그 항목 생성 (globalVariant 포함)
        $newLog = [
            'id' => generateClickId(),
            'variant' => $data['variant'],
            'globalVariant' => $data['globalVariant'] ?? $data['variant'],
            'elementId' => $data['elementId'],
            'href' => $data['href'] ?? '',
            'pagePath' => normalizePath($data['pagePath'] ?? ''),
            'timestamp' => $data['timestamp'],
            'userAgent' => substr($data['userAgent'] ?? '', 0, 500),
            'referrer' => $data['referrer'] ?? '',
            'ipAddress' => $_SERVER['REMOTE_ADDR'] ?? '',
            'serverTimestamp' => date('c')
        ];
        
        // 로그 추가
        array_unshift($logs, $newLog);
        
        // 로그 개수 제한 (최근 10000개만 유지)
        if (count($logs) > 10000) {
            $logs = array_slice($logs, 0, 10000);
        }
        
        // 파일 저장
        $jsonOptions = JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES;
        if (file_put_contents($logFile, json_encode($logs, $jsonOptions), LOCK_EX) === false) {
            throw new Exception('로그 파일 저장 실패: ' . $logFile);
        }
        
        return [
            'success' => true,
            'id' => $newLog['id'],
            'message' => '로그가 저장되었습니다',
            'logFile' => basename($logFile)
        ];
        
    } catch (Exception $e) {
        throw $e;
    }
}

// ⭐ 모든 로그 파일 읽기
function getAllLogs() {
    $allLogs = [];
    
    if (!is_dir(LOG_DIR)) {
        return $allLogs;
    }
    
    $files = glob(LOG_DIR . 'clicks_*.json');
    
    foreach ($files as $file) {
        $content = file_get_contents($file);
        $logs = json_decode($content, true);
        
        if (is_array($logs)) {
            $allLogs = array_merge($allLogs, $logs);
        }
    }
    
    return $allLogs;
}

// ⭐ 실제 크로스 페이지 통계 계산
function calculateCrossPageStats() {
    $allLogs = getAllLogs();
    
    if (empty($allLogs)) {
        return [
            'trackedUsers' => 0,
            'consistencyRate' => 0,
            'avgPagesPerUser' => 0,
            'globalCookieRate' => 0,
            'aToACount' => 0,
            'aToAPercent' => 0,
            'bToBCount' => 0,
            'bToBPercent' => 0,
            'changedCount' => 0,
            'changedPercent' => 0
        ];
    }
    
    // ⭐ NEW: 시간순 정렬 (오래된 것부터)
    usort($allLogs, function($a, $b) {
        return strtotime($a['timestamp']) - strtotime($b['timestamp']);
    });
    
    // IP 주소 기반으로 사용자 구분 (globalVariant도 고려)
    $userSessions = [];
    
    foreach ($allLogs as $log) {
        $ipAddress = $log['ipAddress'] ?? '';
        $globalVariant = $log['globalVariant'] ?? $log['variant'] ?? '';
        
        if (!$ipAddress || !$globalVariant) {
            continue;
        }
        
        if (!isset($userSessions[$ipAddress])) {
            $userSessions[$ipAddress] = [
                'variants' => [],
                'pages' => [],
                'firstVariant' => null,
                'lastVariant' => null,
                'firstTimestamp' => $log['timestamp'],
                'lastTimestamp' => $log['timestamp']
            ];
        }
        
        // 페이지 경로 추가 (중복 제거)
        $pagePath = $log['pagePath'] ?? '';
        if ($pagePath && !in_array($pagePath, $userSessions[$ipAddress]['pages'])) {
            $userSessions[$ipAddress]['pages'][] = $pagePath;
        }
        
        // Variant 기록
        $userSessions[$ipAddress]['variants'][] = $globalVariant;
        
        // 첫 번째와 마지막 Variant 기록
        if ($userSessions[$ipAddress]['firstVariant'] === null) {
            $userSessions[$ipAddress]['firstVariant'] = $globalVariant;
        }
        $userSessions[$ipAddress]['lastVariant'] = $globalVariant;
        
        // 타임스탬프 업데이트
        if (strtotime($log['timestamp']) > strtotime($userSessions[$ipAddress]['lastTimestamp'])) {
            $userSessions[$ipAddress]['lastTimestamp'] = $log['timestamp'];
        }
    }
    
    // 여러 페이지를 방문한 사용자만 필터링
    $crossPageUsers = [];
    foreach ($userSessions as $ip => $session) {
        if (count($session['pages']) > 1) {
            $crossPageUsers[$ip] = $session;
        }
    }
    
    $trackedUsers = count($crossPageUsers);
    $consistentUsers = 0;
    $aToACount = 0;
    $bToBCount = 0;
    $changedCount = 0;
    $totalPages = 0;
    $globalCookieCount = 0;
    
    foreach ($crossPageUsers as $ip => $session) {
        $totalPages += count($session['pages']);
        
        // 전역 쿠키 적용 확인 (모든 variants가 같은지 확인)
        $uniqueVariants = array_unique($session['variants']);
        
        if (count($uniqueVariants) === 1) {
            // 모든 Variant가 동일
            $consistentUsers++;
            $globalCookieCount++;
            
            if ($session['firstVariant'] === 'A') {
                $aToACount++;
            } else {
                $bToBCount++;
            }
        } else {
            // Variant가 변경됨
            $changedCount++;
        }
    }
    
    $avgPagesPerUser = $trackedUsers > 0 ? round($totalPages / $trackedUsers, 2) : 0;
    $consistencyRate = $trackedUsers > 0 ? round(($consistentUsers / $trackedUsers) * 100, 2) : 0;
    $globalCookieRate = $trackedUsers > 0 ? round(($globalCookieCount / $trackedUsers) * 100, 2) : 0;
    
    $variantChangeCount = $trackedUsers > 0 ? $trackedUsers - $aToACount - $bToBCount : 0;
    $aToAPercent = $trackedUsers > 0 ? round(($aToACount / $trackedUsers) * 100, 2) : 0;
    $bToBPercent = $trackedUsers > 0 ? round(($bToBCount / $trackedUsers) * 100, 2) : 0;
    $changedPercent = $trackedUsers > 0 ? round(($variantChangeCount / $trackedUsers) * 100, 2) : 0;
    
    return [
        'trackedUsers' => $trackedUsers,
        'consistencyRate' => $consistencyRate,
        'avgPagesPerUser' => $avgPagesPerUser,
        'globalCookieRate' => $globalCookieRate,
        'aToACount' => $aToACount,
        'aToAPercent' => $aToAPercent,
        'bToBCount' => $bToBCount,
        'bToBPercent' => $bToBPercent,
        'changedCount' => $variantChangeCount,
        'changedPercent' => $changedPercent
    ];
}

// ⭐ 실제 사용자 여정 분석 (첫/마지막 페이지 정보 추가)
function analyzeCrossPageUserJourneys() {
    $allLogs = getAllLogs();
    
    if (empty($allLogs)) {
        return [];
    }
    
    // ⭐ NEW: 시간순 정렬 (오래된 것부터)
    usort($allLogs, function($a, $b) {
        return strtotime($a['timestamp']) - strtotime($b['timestamp']);
    });
    
    // IP 주소 기반으로 사용자 구분
    $userSessions = [];
    
    foreach ($allLogs as $log) {
        $ipAddress = $log['ipAddress'] ?? '';
        $globalVariant = $log['globalVariant'] ?? $log['variant'] ?? '';
        $pagePath = $log['pagePath'] ?? '';
        $timestamp = $log['timestamp'] ?? '';
        
        if (!$ipAddress || !$globalVariant) {
            continue;
        }
        
        if (!isset($userSessions[$ipAddress])) {
            $userSessions[$ipAddress] = [
                'variants' => [],
                'pages' => [],
                'logs' => [],
                'firstVariant' => null,
                'lastVariant' => null,
                'firstPage' => null,
                'lastPage' => null,
                'firstTimestamp' => $timestamp,
                'lastUpdated' => $timestamp
            ];
        }
        
        // 로그 저장
        $userSessions[$ipAddress]['logs'][] = $log;
        
        // 페이지 경로 추가 (중복 제거)
        if ($pagePath && !in_array($pagePath, $userSessions[$ipAddress]['pages'])) {
            $userSessions[$ipAddress]['pages'][] = $pagePath;
        }
        
        // Variant 기록
        $userSessions[$ipAddress]['variants'][] = $globalVariant;
        
        // ⭐ NEW: 첫 번째와 마지막 Variant 및 페이지 기록
        if ($userSessions[$ipAddress]['firstVariant'] === null) {
            $userSessions[$ipAddress]['firstVariant'] = $globalVariant;
            $userSessions[$ipAddress]['firstPage'] = $pagePath;
            $userSessions[$ipAddress]['firstTimestamp'] = $timestamp;
        }
        
        // 마지막 값은 계속 업데이트
        $userSessions[$ipAddress]['lastVariant'] = $globalVariant;
        $userSessions[$ipAddress]['lastPage'] = $pagePath;
        $userSessions[$ipAddress]['lastUpdated'] = $timestamp;
    }
    
    // 여러 페이지를 방문한 사용자만 필터링
    $journeys = [];
    foreach ($userSessions as $ip => $session) {
        if (count($session['pages']) > 1) {
            // ⭐ NEW: 페이지 이름 추출 함수
            $getPageName = function($path) {
                if (empty($path)) return '-';
                $parts = explode('/', $path);
                $filename = end($parts);
                return str_replace('.html', '', $filename);
            };
            
            $journeys[] = [
                'userId' => substr(md5($ip), 0, 16),
                'firstVariant' => $session['firstVariant'],
                'lastVariant' => $session['lastVariant'],
                'firstPage' => $getPageName($session['firstPage']),
                'lastPage' => $getPageName($session['lastPage']),
                'firstPageFull' => $session['firstPage'],
                'lastPageFull' => $session['lastPage'],
                'pagesVisited' => count($session['pages']),
                'lastUpdated' => $session['lastUpdated']
            ];
        }
    }
    
    // 최신 활동 순으로 정렬
    usort($journeys, function($a, $b) {
        return strtotime($b['lastUpdated']) - strtotime($a['lastUpdated']);
    });
    
    // 최근 100개만 반환
    return array_slice($journeys, 0, 100);
}

// ⭐ GET 요청: 통계 조회
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $action = $_GET['action'] ?? null;
        
        switch ($action) {
            case 'getCrossPageStats':
                $stats = calculateCrossPageStats();
                $result = ['success' => true, 'stats' => $stats];
                break;
                
            case 'getUserJourney':
                $journeys = analyzeCrossPageUserJourneys();
                $result = ['success' => true, 'journeys' => $journeys];
                break;
                
            default:
                throw new Exception('알 수 없는 액션입니다: ' . $action);
        }
        
        echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    exit;
}

// ⭐ POST 요청: 로그 저장
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            throw new Exception('요청 본문이 비어있습니다');
        }
        
        // 데이터 유효성 검사
        $errors = validateLog($input);
        if (!empty($errors)) {
            throw new Exception(implode(', ', $errors));
        }
        
        // 로그 저장
        $result = saveLog($input);
        
        echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    exit;
}

// ⭐ 지원하지 않는 요청
http_response_code(405);
echo json_encode(['success' => false, 'error' => 'GET 또는 POST 요청만 지원합니다'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
?>
