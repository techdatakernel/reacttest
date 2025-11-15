<?php
// api/ab-test-analytics.php

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

// 로그 디렉토리 설정
define('LOG_DIR', __DIR__ . '/ab-test-logs/');

// GET 파라미터 받기
$startDate = $_GET['startDate'] ?? date('Y-m-d', strtotime('-30 days'));
$endDate = $_GET['endDate'] ?? date('Y-m-d');
$pagePath = isset($_GET['pagePath']) ? urldecode($_GET['pagePath']) : '';
$export = $_GET['export'] ?? '';

// 날짜 범위의 모든 로그 파일 찾기
function getLogFiles($startDate, $endDate) {
    $files = [];
    $start = new DateTime($startDate);
    $end = new DateTime($endDate);
    
    while ($start <= $end) {
        $fileName = LOG_DIR . 'clicks_' . $start->format('Y-m') . '.json';
        if (file_exists($fileName)) {
            $files[] = $fileName;
        }
        $start->modify('+1 month');
    }
    
    return $files;
}

// ⭐ 날짜 비교 함수 (ISO 8601 형식 지원)
function isDateInRange($timestamp, $startDate, $endDate) {
    // timestamp를 DateTime 객체로 변환
    try {
        $logDate = new DateTime($timestamp);
    } catch (Exception $e) {
        // 파싱 실패 시 false 반환
        return false;
    }
    
    // 시작일: 00:00:00
    $start = new DateTime($startDate . ' 00:00:00');
    
    // 종료일: 23:59:59
    $end = new DateTime($endDate . ' 23:59:59');
    
    // 범위 내에 있는지 확인
    return ($logDate >= $start && $logDate <= $end);
}

// 로그 데이터 로드 및 필터링
function loadLogs($startDate, $endDate, $pagePath = '') {
    $logFiles = getLogFiles($startDate, $endDate);
    $allLogs = [];
    
    foreach ($logFiles as $file) {
        $content = file_get_contents($file);
        $logs = json_decode($content, true);
        
        if ($logs && is_array($logs)) {
            foreach ($logs as $log) {
                $logTimestamp = $log['timestamp'] ?? '';
                
                // ⭐ 개선된 날짜 비교
                if (isDateInRange($logTimestamp, $startDate, $endDate)) {
                    // 페이지 필터
                    if (empty($pagePath)) {
                        $allLogs[] = $log;
                    } else {
                        $logPath = $log['pagePath'] ?? '';
                        if ($logPath === $pagePath) {
                            $allLogs[] = $log;
                        }
                    }
                }
            }
        }
    }
    
    return $allLogs;
}

// 통계 계산
function calculateStats($logs) {
    $stats = [
        'summary' => [
            'totalClicks' => 0,
            'variantA' => 0,
            'variantB' => 0,
            'winner' => '-',
            'improvement' => 0
        ],
        'variants' => [
            'A' => ['total' => 0, 'channels' => []],
            'B' => ['total' => 0, 'channels' => []]
        ],
        'logs' => []
    ];
    
    if (empty($logs)) {
        return $stats;
    }
    
    foreach ($logs as $log) {
        $variant = $log['variant'] ?? '';
        $elementId = $log['elementId'] ?? '';
        
        if (!in_array($variant, ['A', 'B'])) {
            continue;
        }
        
        $stats['summary']['totalClicks']++;
        
        if ($variant === 'A') {
            $stats['summary']['variantA']++;
            $stats['variants']['A']['total']++;
        } else {
            $stats['summary']['variantB']++;
            $stats['variants']['B']['total']++;
        }
        
        if (!empty($elementId)) {
            if (!isset($stats['variants'][$variant]['channels'][$elementId])) {
                $stats['variants'][$variant]['channels'][$elementId] = 0;
            }
            $stats['variants'][$variant]['channels'][$elementId]++;
        }
        
        if (count($stats['logs']) < 100) {
            $stats['logs'][] = [
                'timestamp' => $log['timestamp'] ?? '',
                'variant' => $variant,
                'elementId' => $elementId,
                'pagePath' => $log['pagePath'] ?? '',
                'userAgent' => $log['userAgent'] ?? ''
            ];
        }
    }
    
    // 승자 결정
    if ($stats['summary']['variantA'] > 0 && $stats['summary']['variantB'] > 0) {
        if ($stats['summary']['variantA'] > $stats['summary']['variantB']) {
            $stats['summary']['winner'] = 'Variant A';
            $stats['summary']['improvement'] = round(
                (($stats['summary']['variantA'] - $stats['summary']['variantB']) / $stats['summary']['variantB']) * 100, 
                1
            );
        } elseif ($stats['summary']['variantB'] > $stats['summary']['variantA']) {
            $stats['summary']['winner'] = 'Variant B';
            $stats['summary']['improvement'] = round(
                (($stats['summary']['variantB'] - $stats['summary']['variantA']) / $stats['summary']['variantA']) * 100, 
                1
            );
        } else {
            $stats['summary']['winner'] = '동점';
            $stats['summary']['improvement'] = 0;
        }
    } elseif ($stats['summary']['variantA'] > 0) {
        $stats['summary']['winner'] = 'Variant A';
        $stats['summary']['improvement'] = 100;
    } elseif ($stats['summary']['variantB'] > 0) {
        $stats['summary']['winner'] = 'Variant B';
        $stats['summary']['improvement'] = 100;
    }
    
    // 채널별 정렬
    foreach (['A', 'B'] as $variant) {
        if (!empty($stats['variants'][$variant]['channels'])) {
            arsort($stats['variants'][$variant]['channels']);
        }
    }
    
    // 로그 최신순 정렬
    if (!empty($stats['logs'])) {
        usort($stats['logs'], function($a, $b) {
            return strtotime($b['timestamp']) - strtotime($a['timestamp']);
        });
    }
    
    return $stats;
}

// CSV 내보내기
function exportCSV($logs) {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="ab-test-data-' . date('Y-m-d') . '.csv"');
    
    echo "\xEF\xBB\xBF";
    
    $output = fopen('php://output', 'w');
    
    fputcsv($output, ['시간', 'Variant', '판매처 ID', '판매처 URL', '페이지', 'IP', 'User Agent']);
    
    foreach ($logs as $log) {
        fputcsv($output, [
            $log['timestamp'] ?? '',
            $log['variant'] ?? '',
            $log['elementId'] ?? '',
            $log['href'] ?? '',
            $log['pagePath'] ?? '',
            $log['ipAddress'] ?? '',
            $log['userAgent'] ?? ''
        ]);
    }
    
    fclose($output);
    exit;
}

// 메인 로직
try {
    if (!file_exists(LOG_DIR)) {
        throw new Exception('로그 디렉토리가 존재하지 않습니다: ' . LOG_DIR);
    }
    
    $logs = loadLogs($startDate, $endDate, $pagePath);
    
    if ($export === 'csv') {
        exportCSV($logs);
    }
    
    $stats = calculateStats($logs);
    
    echo json_encode($stats, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage(),
        'logDir' => LOG_DIR,
        'exists' => file_exists(LOG_DIR)
    ], JSON_UNESCAPED_UNICODE);
}
?>