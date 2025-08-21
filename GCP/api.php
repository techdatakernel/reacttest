<?php
// api.php - 완전한 API 엔드포인트
session_start();

// 로그인 체크
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$config = include 'config.php';
require_once 'bigquery.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $bigquery = new BigQueryAPI($config);
    $action = $_GET['action'] ?? '';
    
    switch ($action) {
        case 'raw_data':
            $filters = [];
            if (isset($_GET['device_type'])) $filters['device_type'] = $_GET['device_type'];
            if (isset($_GET['campaign_name'])) $filters['campaign_name'] = $_GET['campaign_name'];
            if (isset($_GET['keyword_name'])) $filters['keyword_name'] = $_GET['keyword_name'];
            if (isset($_GET['start_date'])) $filters['start_date'] = $_GET['start_date'];
            if (isset($_GET['end_date'])) $filters['end_date'] = $_GET['end_date'];
            
            $limit = isset($_GET['limit']) ? min(1000, intval($_GET['limit'])) : 100;
            $offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;
            
            $result = $bigquery->getData($limit, $offset, $filters);
            echo json_encode(['success' => true, 'data' => $result]);
            break;
            
        case 'daily_stats':
            $startDate = $_GET['start_date'] ?? null;
            $endDate = $_GET['end_date'] ?? null;
            $limit = isset($_GET['limit']) ? min(100, intval($_GET['limit'])) : 50;
            
            $result = $bigquery->getDailyStats($startDate, $endDate, $limit);
            echo json_encode(['success' => true, 'data' => $result]);
            break;
            
        case 'keyword_analysis':
            $limit = isset($_GET['limit']) ? min(100, intval($_GET['limit'])) : 20;
            $result = $bigquery->getKeywordAnalysis($limit);
            echo json_encode(['success' => true, 'data' => $result]);
            break;
            
        case 'device_analysis':
            $result = $bigquery->getDeviceAnalysis();
            echo json_encode(['success' => true, 'data' => $result]);
            break;
            
        case 'campaign_analysis':
            $limit = isset($_GET['limit']) ? min(50, intval($_GET['limit'])) : 20;
            $result = $bigquery->getCampaignAnalysis($limit);
            echo json_encode(['success' => true, 'data' => $result]);
            break;
            
        case 'trend_analysis':
            $period = isset($_GET['period']) ? intval($_GET['period']) : 7;
            $result = $bigquery->getTrendAnalysis($period);
            echo json_encode(['success' => true, 'data' => $result]);
            break;
            
        case 'period_comparison':
            $currentStart = $_GET['current_start'] ?? null;
            $currentEnd = $_GET['current_end'] ?? null;
            $compareStart = $_GET['compare_start'] ?? null;
            $compareEnd = $_GET['compare_end'] ?? null;
            
            $result = $bigquery->getPeriodComparison($currentStart, $currentEnd, $compareStart, $compareEnd);
            echo json_encode(['success' => true, 'data' => $result]);
            break;
            
        case 'ai_insights':
            $result = $bigquery->generateAIInsights();
            echo json_encode(['success' => true, 'data' => $result]);
            break;
            
        case 'pivot_data':
            $rows = $_GET['rows'] ?? [];
            $cols = $_GET['cols'] ?? [];
            $values = $_GET['values'] ?? [];
            $filters = $_GET['filters'] ?? [];
            
            $result = $bigquery->getPivotData($rows, $cols, $values, $filters);
            echo json_encode(['success' => true, 'data' => $result]);
            break;
            
        case 'table_schema':
            $result = $bigquery->getTableSchema();
            echo json_encode(['success' => true, 'data' => $result]);
            break;
            
        case 'export_data':
            $format = $_GET['format'] ?? 'csv';
            $type = $_GET['type'] ?? 'raw_data';
            $filters = $_GET['filters'] ?? [];
            
            $result = $bigquery->exportData($type, $format, $filters);
            echo json_encode(['success' => true, 'data' => $result]);
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
            break;
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>