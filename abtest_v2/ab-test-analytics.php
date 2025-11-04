<?php
// ab-test-analytics.php

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

// 로그 디렉토리 설정
define('LOG_DIR', __DIR__ . '/ab-test-logs/');

// GET 파라미터 받기
$startDate = $_GET['startDate'] ?? date('Y-m-d', strtotime('-30 days'));
$endDate = $_GET['endDate'] ?? date('Y-m-d');
$pagePath = $_GET['pagePath'] ?? '';
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

// 로그 데이터 로드 및 필터링
function loadLogs($startDate, $endDate, $pagePath = '') {
    $logFiles = getLogFiles($startDate, $endDate);
    $allLogs = [];
    
    foreach ($logFiles as $file) {
        $content = file_get_contents($file);
        $logs = json_decode($content, true);
        
        if ($logs && is_array($logs)) {
            foreach ($logs as $log) {
                // 날짜 필터
                $logDate = substr($log['timestamp'], 0, 10);
                if ($logDate >= $startDate && $logDate <= $endDate) {
                    // 페이지 필터
                    if (empty($pagePath) || strpos($log['pagePath'], $pagePath) !== false) {
                        $allLogs[] = $log;
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
    
    // 로그가 없으면 바로 반환
    if (empty($logs)) {
        return $stats;
    }
    
    foreach ($logs as $log) {
        $variant = $log['variant'] ?? '';
        $elementId = $log['elementId'] ?? '';
        
        // Variant 검증
        if (!in_array($variant, ['A', 'B'])) {
            continue;
        }
        
        // 총 클릭수
        $stats['summary']['totalClicks']++;
        
        // Variant별 카운트
        if ($variant === 'A') {
            $stats['summary']['variantA']++;
            $stats['variants']['A']['total']++;
        } else {
            $stats['summary']['variantB']++;
            $stats['variants']['B']['total']++;
        }
        
        // 채널별 클릭수
        if (!empty($elementId)) {
            if (!isset($stats['variants'][$variant]['channels'][$elementId])) {
                $stats['variants'][$variant]['channels'][$elementId] = 0;
            }
            $stats['variants'][$variant]['channels'][$elementId]++;
        }
        
        // 로그 저장 (최근 100개만)
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
    
    // 승자 결정 (⭐ 0으로 나누기 방지)
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
    
    // 채널별 정렬 (클릭수 내림차순)
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
    
    // BOM 추가 (Excel에서 한글 깨짐 방지)
    echo "\xEF\xBB\xBF";
    
    $output = fopen('php://output', 'w');
    
    // 헤더
    fputcsv($output, ['시간', 'Variant', '판매처 ID', '판매처 URL', '페이지', 'IP', 'User Agent']);
    
    // 데이터
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
    // 로그 디렉토리 확인
    if (!file_exists(LOG_DIR)) {
        throw new Exception('로그 디렉토리가 존재하지 않습니다: ' . LOG_DIR);
    }
    
    // 로그 로드
    $logs = loadLogs($startDate, $endDate, $pagePath);
    
    // CSV 내보내기 요청
    if ($export === 'csv') {
        exportCSV($logs);
    }
    
    // 통계 계산
    $stats = calculateStats($logs);
    
    // JSON 응답
    echo json_encode($stats, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage(),
        'logDir' => LOG_DIR,
        'exists' => file_exists(LOG_DIR),
        'files' => glob(LOG_DIR . '*.json')
    ], JSON_UNESCAPED_UNICODE);
}
?>