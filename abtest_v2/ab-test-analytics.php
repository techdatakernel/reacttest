<?php
// api/ab-test-analytics.php - 신규 로그 형식 지원 버전

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

define('LOG_DIR', __DIR__ . '/ab-test-logs/');

// ============================================
// 유틸리티 함수
// ============================================

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

function isDateInRange($timestamp, $startDate, $endDate) {
    try {
        $logDate = new DateTime($timestamp);
    } catch (Exception $e) {
        return false;
    }
    
    $start = new DateTime($startDate . ' 00:00:00');
    $end = new DateTime($endDate . ' 23:59:59');
    
    return ($logDate >= $start && $logDate <= $end);
}

function loadLogs($startDate, $endDate, $pagePath = '') {
    $logFiles = getLogFiles($startDate, $endDate);
    $allLogs = [];
    
    foreach ($logFiles as $file) {
        $content = file_get_contents($file);
        $logs = json_decode($content, true);
        
        if ($logs && is_array($logs)) {
            foreach ($logs as $log) {
                $logTimestamp = $log['timestamp'] ?? '';
                
                if (isDateInRange($logTimestamp, $startDate, $endDate)) {
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

// ============================================
// 기본 통계 분석
// ============================================
function analyzeBasicStats($logs, $startDate, $endDate) {
    $stats = [
        'summary' => [
            'totalClicks' => 0,
            'variantA' => 0,
            'variantB' => 0,
            'winner' => '-',
            'improvement' => 0
        ],
        'daily' => [],
        'logs' => []
    ];
    
    $dailyStats = [];
    
    foreach ($logs as $log) {
        // ⭐ globalVariant 우선, 없으면 variant 사용
        $variant = $log['globalVariant'] ?? $log['variant'] ?? '';
        $timestamp = $log['timestamp'] ?? '';
        
        if (!in_array($variant, ['A', 'B'])) {
            continue;
        }
        
        $stats['summary']['totalClicks']++;
        
        if ($variant === 'A') {
            $stats['summary']['variantA']++;
        } else {
            $stats['summary']['variantB']++;
        }
        
        // 날짜별 집계
        try {
            $date = new DateTime($timestamp);
            $dateKey = $date->format('Y-m-d');
            
            if (!isset($dailyStats[$dateKey])) {
                $dailyStats[$dateKey] = [
                    'date' => $dateKey,
                    'variantA' => 0,
                    'variantB' => 0
                ];
            }
            
            if ($variant === 'A') {
                $dailyStats[$dateKey]['variantA']++;
            } else {
                $dailyStats[$dateKey]['variantB']++;
            }
        } catch (Exception $e) {
            // 날짜 파싱 실패 시 무시
        }
    }
    
    // 승자 결정
    $aClicks = $stats['summary']['variantA'];
    $bClicks = $stats['summary']['variantB'];
    
    if ($aClicks > $bClicks && $aClicks > 0) {
        $stats['summary']['winner'] = 'Variant A';
        $stats['summary']['improvement'] = round((($aClicks - $bClicks) / $aClicks) * 100, 1);
    } elseif ($bClicks > $aClicks && $bClicks > 0) {
        $stats['summary']['winner'] = 'Variant B';
        $stats['summary']['improvement'] = round((($bClicks - $aClicks) / $bClicks) * 100, 1);
    }
    
    // daily 배열로 변환 및 정렬
    $stats['daily'] = array_values($dailyStats);
    usort($stats['daily'], function($a, $b) {
        return strcmp($a['date'], $b['date']);
    });
    
    // 최근 20개 로그 추가
    $recentLogs = array_slice($logs, -20);
    $recentLogs = array_reverse($recentLogs);
    
    foreach ($recentLogs as $log) {
        $stats['logs'][] = [
            'timestamp' => $log['timestamp'] ?? '',
            'variant' => $log['globalVariant'] ?? $log['variant'] ?? '',
            'elementId' => $log['elementId'] ?? '',
            'pagePath' => $log['pagePath'] ?? ''
        ];
    }
    
    return $stats;
}

// ============================================
// 크로스페이지 분석 (신규 로그 형식 지원)
// ============================================
function analyzeCrosspage($logs) {
    $userJourneys = [];
    
    foreach ($logs as $log) {
        // ⭐ userId 우선, 없으면 IP 사용
        $userId = $log['userId'] ?? null;
        $ipAddress = $log['ipAddress'] ?? null;
        
        // userId가 있으면 우선 사용
        if ($userId) {
            $identifier = 'user_' . $userId;
        } else {
            $identifier = 'ip_' . ($ipAddress ?: 'unknown');
        }
        
        if (!isset($userJourneys[$identifier])) {
            $userJourneys[$identifier] = [
                'userId' => $identifier,
                'tracking_method' => $userId ? 'userId' : 'IP',
                'original_userId' => $userId ?: '-',
                'original_ip' => $ipAddress,
                'visits' => [],
                'variants' => []
            ];
        }
        
        $userJourneys[$identifier]['visits'][] = [
            'timestamp' => $log['timestamp'] ?? '',
            'pagePath' => $log['pagePath'] ?? '',
            'variant' => $log['variant'] ?? '',
            'globalVariant' => $log['globalVariant'] ?? ''
        ];
        
        $variant = $log['globalVariant'] ?? $log['variant'] ?? '';
        if (!empty($variant)) {
            $userJourneys[$identifier]['variants'][] = $variant;
        }
    }
    
    // 통계 계산
    $totalUsers = 0;
    $consistentUsers = 0;
    $aToA = 0;
    $bToB = 0;
    $changed = 0;
    $totalPages = 0;
    $userIdBased = 0;
    $ipBased = 0;
    
    $userDetails = [];
    
    foreach ($userJourneys as $identifier => $journey) {
        if (count($journey['visits']) < 2) {
            continue;
        }
        
        $totalUsers++;
        $totalPages += count($journey['visits']);
        
        if ($journey['tracking_method'] === 'userId') {
            $userIdBased++;
        } else {
            $ipBased++;
        }
        
        usort($journey['visits'], function($a, $b) {
            return strcmp($a['timestamp'], $b['timestamp']);
        });
        
        $firstVisit = $journey['visits'][0];
        $lastVisit = $journey['visits'][count($journey['visits']) - 1];
        
        $firstVariant = $firstVisit['globalVariant'] ?: $firstVisit['variant'];
        $lastVariant = $lastVisit['globalVariant'] ?: $lastVisit['variant'];
        
        $variants = array_unique($journey['variants']);
        $consistent = (count($variants) === 1);
        
        if ($consistent) {
            $consistentUsers++;
            if ($firstVariant === 'A') {
                $aToA++;
            } else {
                $bToB++;
            }
        } else {
            $changed++;
        }
        
        $userDetails[] = [
            'userId' => substr($identifier, 0, 20) . '...',
            'tracking_method' => $journey['tracking_method'],
            'first_page' => $firstVisit['pagePath'],
            'first_variant' => $firstVariant,
            'last_page' => $lastVisit['pagePath'],
            'last_variant' => $lastVariant,
            'consistent' => $consistent,
            'total_pages' => count($journey['visits']),
            'last_timestamp' => $lastVisit['timestamp']
        ];
    }
    
    usort($userDetails, function($a, $b) {
        return strcmp($b['last_timestamp'], $a['last_timestamp']);
    });
    
    $userDetails = array_slice($userDetails, 0, 50);
    
    return [
        'summary' => [
            'total_users' => $totalUsers,
            'consistency_rate' => $totalUsers > 0 ? round($consistentUsers / $totalUsers, 2) : 0,
            'avg_pages_per_user' => $totalUsers > 0 ? round($totalPages / $totalUsers, 1) : 0,
            'userId_adoption_rate' => $totalUsers > 0 ? round($userIdBased / $totalUsers, 2) : 0,
            'userId_based' => $userIdBased,
            'ip_based' => $ipBased,
            'a_to_a' => $aToA,
            'b_to_b' => $bToB,
            'changed' => $changed
        ],
        'users' => $userDetails
    ];
}

// ============================================
// CSV 내보내기
// ============================================
function exportCSV($logs) {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="ab-test-data-' . date('Y-m-d') . '.csv"');
    
    echo "\xEF\xBB\xBF";
    
    $output = fopen('php://output', 'w');
    
    fputcsv($output, ['시간', 'Variant', 'Global Variant', 'User ID', 'IP', 'IP Source', '페이지', 'Element ID']);
    
    foreach ($logs as $log) {
        fputcsv($output, [
            $log['timestamp'] ?? '',
            $log['variant'] ?? '',
            $log['globalVariant'] ?? '',
            $log['userId'] ?? '-',
            $log['ipAddress'] ?? '-',
            $log['ipSource'] ?? '-',
            $log['pagePath'] ?? '',
            $log['elementId'] ?? ''
        ]);
    }
    
    fclose($output);
    exit;
}

// ============================================
// 메인 로직
// ============================================
try {
    if (!file_exists(LOG_DIR)) {
        throw new Exception('로그 디렉토리가 존재하지 않습니다: ' . LOG_DIR);
    }
    
    $action = $_GET['action'] ?? 'basic';
    $startDate = $_GET['start'] ?? $_GET['startDate'] ?? date('Y-m-d', strtotime('-30 days'));
    $endDate = $_GET['end'] ?? $_GET['endDate'] ?? date('Y-m-d');
    $pagePath = isset($_GET['pagePath']) ? urldecode($_GET['pagePath']) : '';
    $export = $_GET['export'] ?? '';
    
    $logs = loadLogs($startDate, $endDate, $pagePath);
    
    if ($export === 'csv') {
        exportCSV($logs);
    }
    
    switch ($action) {
        case 'crosspage':
            $result = analyzeCrosspage($logs);
            break;
            
        case 'basic':
        default:
            $result = analyzeBasicStats($logs, $startDate, $endDate);
            break;
    }
    
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage(),
        'logDir' => LOG_DIR,
        'exists' => file_exists(LOG_DIR)
    ], JSON_UNESCAPED_UNICODE);
}
?>