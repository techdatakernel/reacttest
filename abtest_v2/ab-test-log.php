<?php
// ab-test-log.php

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

define('LOG_BASE_DIR', __DIR__ . '/ab-test-logs/');
define('ARCHIVE_DIR', LOG_BASE_DIR . 'archive/');

function getLogFileName($date = null) {
    if ($date === null) {
        $date = date('Y-m');
    }
    return LOG_BASE_DIR . 'clicks_' . $date . '.json';
}

// 디렉토리 생성
if (!file_exists(LOG_BASE_DIR)) {
    mkdir(LOG_BASE_DIR, 0755, true);
}
if (!file_exists(ARCHIVE_DIR)) {
    mkdir(ARCHIVE_DIR, 0755, true);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed', 'method' => $_SERVER['REQUEST_METHOD']]);
    exit;
}

$rawData = file_get_contents('php://input');
$data = json_decode($rawData, true);

if (!$data) {
    http_response_code(400);
    echo json_encode([
        'error' => 'Invalid JSON',
        'received' => substr($rawData, 0, 100),
        'json_error' => json_last_error_msg()
    ]);
    exit;
}

$requiredFields = ['variant', 'elementId', 'href', 'pagePath', 'timestamp'];
foreach ($requiredFields as $field) {
    if (!isset($data[$field])) {
        http_response_code(400);
        echo json_encode([
            'error' => "Missing required field: $field",
            'received_fields' => array_keys($data)
        ]);
        exit;
    }
}

$logEntry = [
    'id' => uniqid('click_', true),
    'variant' => $data['variant'],
    'elementId' => $data['elementId'],
    'href' => $data['href'],
    'pagePath' => $data['pagePath'],
    'timestamp' => $data['timestamp'],
    'userAgent' => $data['userAgent'] ?? $_SERVER['HTTP_USER_AGENT'] ?? '',
    'referrer' => $data['referrer'] ?? '',
    'ipAddress' => $_SERVER['REMOTE_ADDR'] ?? '',
    'serverTimestamp' => date('c')
];

$logFile = getLogFileName();

// ⭐ 파일 잠금을 사용한 안전한 쓰기
$fp = fopen($logFile, 'c+');
if (!$fp) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Cannot open log file',
        'file' => $logFile,
        'exists' => file_exists($logFile),
        'writable' => is_writable($logFile),
        'dir_writable' => is_writable(LOG_BASE_DIR)
    ]);
    exit;
}

// 배타적 잠금
if (flock($fp, LOCK_EX)) {
    // 파일 내용 읽기
    $size = filesize($logFile);
    $logs = [];
    
    if ($size > 0) {
        $content = fread($fp, $size);
        $logs = json_decode($content, true) ?? [];
    }
    
    // 새 로그 추가
    $logs[] = $logEntry;
    
    // 파일 포인터를 처음으로 이동하고 내용 삭제
    ftruncate($fp, 0);
    rewind($fp);
    
    // 새 내용 쓰기
    $jsonData = json_encode($logs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    fwrite($fp, $jsonData);
    fflush($fp);
    
    // 잠금 해제
    flock($fp, LOCK_UN);
    fclose($fp);
    
    // 권한 확인 및 설정
    chmod($logFile, 0666);
    
    archiveOldLogs();
    
    http_response_code(200);
    echo json_encode([
        'success' => true, 
        'id' => $logEntry['id'],
        'file' => basename($logFile),
        'total_clicks' => count($logs),
        'file_size' => strlen($jsonData),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
} else {
    fclose($fp);
    http_response_code(500);
    echo json_encode(['error' => 'Cannot lock file']);
}

function archiveOldLogs() {
    $files = glob(LOG_BASE_DIR . 'clicks_*.json');
    $cutoffDate = date('Y-m', strtotime('-3 months'));
    
    foreach ($files as $file) {
        if (preg_match('/clicks_(\d{4}-\d{2})\.json$/', $file, $matches)) {
            $fileDate = $matches[1];
            if ($fileDate < $cutoffDate) {
                $archivePath = ARCHIVE_DIR . basename($file);
                if (!file_exists($archivePath)) {
                    @rename($file, $archivePath);
                }
            }
        }
    }
}
?>