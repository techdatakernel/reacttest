<?php
/**
 * BigQuery API 클래스 - 실제 비용 모니터링 완전 구현
 */
class BigQueryAPI {
    private $projectId;
    private $datasetId;
    private $tableId;
    private $accessToken;
    private $serviceAccountFile;
    private $config;
    private $maxRows;
    
    // 비용 추적 변수
    private $dailyUsageFile;
    private $costLogFile;
    private $alertsLogFile;
    
    public function __construct($config) {
        $this->config = $config;
        $this->projectId = $config['gcp_project_id'];
        $this->datasetId = $config['dataset_id'];
        $this->tableId = $config['table_id'];
        $this->serviceAccountFile = $config['gcp_service_account_file'];
        $this->maxRows = $config['max_query_rows'];
        
        // 비용 추적 파일 설정
        $this->setupCostTracking();
        
        // 인증
        $this->authenticate();
        
        // 비용 모니터링 초기화
        $this->initializeCostMonitoring();
    }
    
    /**
     * 비용 추적 파일 설정
     */
    private function setupCostTracking() {
        $logDir = dirname($this->config['cost_tracking']['log_file']);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        $this->dailyUsageFile = $this->config['cost_tracking']['usage_file'];
        $this->costLogFile = $this->config['cost_tracking']['log_file'];
        $this->alertsLogFile = $this->config['cost_tracking']['alerts_file'];
    }
    
    /**
     * JWT 토큰 기반 인증
     */
    private function authenticate() {
        if (!file_exists($this->serviceAccountFile)) {
            throw new Exception("서비스 계정 키 파일을 찾을 수 없습니다: " . $this->serviceAccountFile);
        }
        
        $serviceAccount = json_decode(file_get_contents($this->serviceAccountFile), true);
        
        // JWT 토큰 생성
        $header = json_encode(['typ' => 'JWT', 'alg' => 'RS256']);
        $now = time();
        $payload = json_encode([
            'iss' => $serviceAccount['client_email'],
            'scope' => 'https://www.googleapis.com/auth/bigquery https://www.googleapis.com/auth/cloud-billing',
            'aud' => 'https://oauth2.googleapis.com/token',
            'exp' => $now + 3600,
            'iat' => $now
        ]);
        
        $base64Header = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
        $base64Payload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));
        
        $signature = '';
        openssl_sign($base64Header . '.' . $base64Payload, $signature, $serviceAccount['private_key'], 'sha256WithRSAEncryption');
        $base64Signature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
        
        $jwt = $base64Header . '.' . $base64Payload . '.' . $base64Signature;
        
        // 액세스 토큰 요청
        $tokenResponse = $this->makeRequest('https://oauth2.googleapis.com/token', [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt
        ], 'POST');
        
        if (isset($tokenResponse['access_token'])) {
            $this->accessToken = $tokenResponse['access_token'];
        } else {
            throw new Exception('토큰 획득 실패: ' . json_encode($tokenResponse));
        }
    }
    
    /**
     * 비용 모니터링 초기화
     */
    private function initializeCostMonitoring() {
        // 현재 사용량 확인
        $dailyUsage = $this->getDailyUsage();
        $weeklyUsage = $this->getWeeklyUsage();
        $monthlyUsage = $this->getMonthlyUsage();
        
        $costConfig = $this->config['cost_monitoring'];
        
        // 비용 제한 체크
        if ($monthlyUsage >= $costConfig['monthly_limit']) {
            $_SESSION['service_suspended'] = true;
            $_SESSION['suspension_reason'] = 'monthly_budget_exceeded';
            $this->logAlert('CRITICAL', 'Service suspended - Monthly limit exceeded', $monthlyUsage);
        } elseif ($weeklyUsage >= $costConfig['weekly_limit']) {
            $_SESSION['query_restricted'] = true;
            $_SESSION['restriction_reason'] = 'weekly_budget_exceeded';
            $this->logAlert('WARNING', 'Query restrictions enabled - Weekly limit exceeded', $weeklyUsage);
        } elseif ($monthlyUsage >= ($costConfig['monthly_budget'] * $costConfig['budget_warning_threshold'])) {
            $_SESSION['cache_mode_only'] = true;
            $_SESSION['cache_mode_reason'] = 'budget_warning_90_percent';
            $this->logAlert('INFO', 'Cache mode enabled - 90% budget reached', $monthlyUsage);
        }
    }
    
    /**
     * 쿼리 비용 예측
     */
    private function estimateQueryCost($query) {
        try {
            // Dry run으로 처리될 바이트 수 확인
            $dryRunUrl = "https://bigquery.googleapis.com/bigquery/v2/projects/{$this->projectId}/queries";
            
            $queryData = [
                'query' => $query,
                'dryRun' => true,
                'useLegacySql' => false
            ];
            
            $response = $this->makeRequest($dryRunUrl, $queryData, 'POST');
            
            if (isset($response['totalBytesProcessed'])) {
                $bytesProcessed = (int)$response['totalBytesProcessed'];
                $costPerTB = $this->config['bigquery_pricing']['query_cost_per_tb'];
                $bytesPerTB = $this->config['bigquery_pricing']['bytes_per_tb'];
                
                return ($bytesProcessed / $bytesPerTB) * $costPerTB;
            }
            
            return 0.01; // 기본 예상 비용
            
        } catch (Exception $e) {
            $this->logError('Cost estimation failed: ' . $e->getMessage());
            return 0.01; // 안전한 기본값
        }
    }
    
    /**
     * 비용 제어가 적용된 쿼리 실행
     */
    public function executeQuery($query) {
        // 서비스 중단 체크
        if (isset($_SESSION['service_suspended']) && $_SESSION['service_suspended']) {
            throw new Exception("서비스가 일시 중단되었습니다: 월간 예산을 초과했습니다.");
        }
        
        // 쿼리 제한 체크
        if (isset($_SESSION['query_restricted']) && $_SESSION['query_restricted']) {
            throw new Exception("쿼리 실행이 제한되었습니다: 주간 예산을 초과했습니다.");
        }
        
        // 캐시 모드 체크
        if (isset($_SESSION['cache_mode_only']) && $_SESSION['cache_mode_only']) {
            $cachedResult = $this->getCachedResult($query);
            if ($cachedResult) {
                return $cachedResult;
            }
            throw new Exception("캐시 모드입니다. 캐시된 데이터만 제공됩니다.");
        }
        
        // 비용 예측
        $estimatedCost = $this->estimateQueryCost($query);
        
        // 비용 예측 로깅
        $this->logCostEstimation($query, $estimatedCost);
        
        // 실제 쿼리 실행
        $startTime = microtime(true);
        $result = $this->performActualQuery($query);
        $executionTime = microtime(true) - $startTime;
        
        // 비용 및 사용량 기록
        $this->recordUsage($estimatedCost, $executionTime, $query);
        
        return $result;
    }
    
    /**
     * 실제 BigQuery 쿼리 실행
     */
    private function performActualQuery($query) {
        $url = "https://bigquery.googleapis.com/bigquery/v2/projects/{$this->projectId}/queries";
        
        $queryData = [
            'query' => $query,
            'useLegacySql' => false,
            'maxResults' => $this->maxRows
        ];
        
        $response = $this->makeRequest($url, $queryData, 'POST');
        
        if (isset($response['error'])) {
            throw new Exception('BigQuery 오류: ' . $response['error']['message']);
        }
        
        $rows = [];
        if (isset($response['rows'])) {
            $schema = $response['schema']['fields'];
            foreach ($response['rows'] as $row) {
                $rowData = [];
                foreach ($row['f'] as $index => $field) {
                    $columnName = $schema[$index]['name'];
                    $rowData[$columnName] = $field['v'];
                }
                $rows[] = $rowData;
            }
        }
        
        return $rows;
    }
    
    /**
     * 사용량 기록
     */
    private function recordUsage($cost, $executionTime, $query) {
        $today = date('Y-m-d');
        $usage = $this->loadDailyUsage();
        
        if (!isset($usage[$today])) {
            $usage[$today] = [
                'total_cost' => 0,
                'query_count' => 0,
                'total_execution_time' => 0,
                'queries' => []
            ];
        }
        
        $usage[$today]['total_cost'] += $cost;
        $usage[$today]['query_count']++;
        $usage[$today]['total_execution_time'] += $executionTime;
        $usage[$today]['queries'][] = [
            'timestamp' => date('H:i:s'),
            'cost' => $cost,
            'execution_time' => $executionTime,
            'query_hash' => md5($query)
        ];
        
        file_put_contents($this->dailyUsageFile, json_encode($usage, JSON_PRETTY_PRINT));
    }
    
    /**
     * 일일 사용량 로드
     */
    private function loadDailyUsage() {
        if (file_exists($this->dailyUsageFile)) {
            return json_decode(file_get_contents($this->dailyUsageFile), true) ?: [];
        }
        return [];
    }
    
    /**
     * 일일 사용량 가져오기
     */
    public function getDailyUsage($date = null) {
        $date = $date ?: date('Y-m-d');
        $usage = $this->loadDailyUsage();
        return isset($usage[$date]) ? $usage[$date]['total_cost'] : 0;
    }
    
    /**
     * 주간 사용량 가져오기
     */
    public function getWeeklyUsage() {
        $usage = $this->loadDailyUsage();
        $weekStart = date('Y-m-d', strtotime('monday this week'));
        $total = 0;
        
        for ($i = 0; $i < 7; $i++) {
            $date = date('Y-m-d', strtotime($weekStart . " +{$i} days"));
            if (isset($usage[$date])) {
                $total += $usage[$date]['total_cost'];
            }
        }
        
        return $total;
    }
    
    /**
     * 월간 사용량 가져오기
     */
    public function getMonthlyUsage() {
        $usage = $this->loadDailyUsage();
        $monthStart = date('Y-m-01');
        $total = 0;
        
        foreach ($usage as $date => $dayUsage) {
            if ($date >= $monthStart) {
                $total += $dayUsage['total_cost'];
            }
        }
        
        return $total;
    }
    
    /**
     * 비용 상태 반환
     */
    public function getCostStatus() {
        $dailyUsage = $this->getDailyUsage();
        $weeklyUsage = $this->getWeeklyUsage();
        $monthlyUsage = $this->getMonthlyUsage();
        $monthlyBudget = $this->config['cost_monitoring']['monthly_budget'];
        
        return [
            'daily_spent' => round($dailyUsage, 2),
            'weekly_spent' => round($weeklyUsage, 2),
            'monthly_spent' => round($monthlyUsage, 2),
            'monthly_budget' => $monthlyBudget,
            'budget_percentage' => round(($monthlyUsage / $monthlyBudget) * 100, 1),
            'remaining_budget' => round($monthlyBudget - $monthlyUsage, 2),
            'restrictions' => [
                'query_restricted' => isset($_SESSION['query_restricted']) ? $_SESSION['query_restricted'] : false,
                'service_suspended' => isset($_SESSION['service_suspended']) ? $_SESSION['service_suspended'] : false,
                'cache_mode_only' => isset($_SESSION['cache_mode_only']) ? $_SESSION['cache_mode_only'] : false
            ],
            'cost_limits' => $this->config['cost_monitoring'],
            'projected_monthly' => $this->calculateProjectedMonthlyCost(),
            'days_remaining' => $this->calculateDaysRemaining()
        ];
    }
    
    /**
     * 월간 예상 비용 계산
     */
    private function calculateProjectedMonthlyCost() {
        $monthlyUsage = $this->getMonthlyUsage();
        $currentDay = (int)date('j');
        $daysInMonth = (int)date('t');
        
        if ($currentDay == 0) return 0;
        
        $dailyAverage = $monthlyUsage / $currentDay;
        return round($dailyAverage * $daysInMonth, 2);
    }
    
    /**
     * 예산 소진까지 남은 일수 계산
     */
    private function calculateDaysRemaining() {
        $monthlyUsage = $this->getMonthlyUsage();
        $monthlyBudget = $this->config['cost_monitoring']['monthly_budget'];
        $dailyUsage = $this->getDailyUsage();
        
        if ($dailyUsage <= 0) return 999;
        
        $remaining = $monthlyBudget - $monthlyUsage;
        return max(0, floor($remaining / $dailyUsage));
    }
    
    /**
     * 캐시된 결과 가져오기
     */
    private function getCachedResult($query) {
        $cacheKey = md5($query);
        $cacheFile = $this->config['cache']['directory'] . "/query_cache_{$cacheKey}.json";
        
        if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $this->config['cache_duration']) {
            return json_decode(file_get_contents($cacheFile), true);
        }
        
        return null;
    }
    
    /**
     * 비용 추정 로깅
     */
    private function logCostEstimation($query, $cost) {
        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'type' => 'cost_estimation',
            'estimated_cost' => $cost,
            'query_hash' => md5($query),
            'daily_total' => $this->getDailyUsage(),
            'monthly_total' => $this->getMonthlyUsage()
        ];
        
        file_put_contents($this->costLogFile, json_encode($logEntry) . "\n", FILE_APPEND);
    }
    
    /**
     * 알림 로깅
     */
    private function logAlert($level, $message, $amount) {
        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'level' => $level,
            'message' => $message,
            'amount' => $amount,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ];
        
        file_put_contents($this->alertsLogFile, json_encode($logEntry) . "\n", FILE_APPEND);
        
        // 이메일 알림 발송 (설정에 따라)
        if ($this->config['cost_monitoring']['notifications']['email']['enabled']) {
            $this->sendEmailAlert($level, $message, $amount);
        }
    }
    
    /**
     * 이메일 알림 발송
     */
    private function sendEmailAlert($level, $message, $amount) {
        $recipients = $this->config['cost_monitoring']['notifications']['email']['recipients'];
        $subject = "[BigQuery Alert - $level] 비용 모니터링 알림";
        $body = "시간: " . date('Y-m-d H:i:s') . "\n";
        $body .= "레벨: $level\n";
        $body .= "메시지: $message\n";
        $body .= "금액: $" . number_format($amount, 2) . "\n";
        
        foreach ($recipients as $email) {
            // 실제 메일 발송 (mail() 함수 또는 PHPMailer 사용)
            // mail($email, $subject, $body);
        }
    }
    
    /**
     * 오류 로깅
     */
    private function logError($message) {
        error_log("[BigQuery] " . date('Y-m-d H:i:s') . " - " . $message);
    }
    
    /**
     * HTTP 요청 실행
     */
    private function makeRequest($url, $data = null, $method = 'GET') {
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->accessToken,
                'Content-Type: application/json'
            ]
        ]);
        
        if ($method === 'POST' && $data) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new Exception("HTTP Error $httpCode: $response");
        }
        
        return json_decode($response, true);
    }
    
    // 기존 메서드들 (비용 모니터링 적용)
    public function getDailyStats($startDate = null, $endDate = null, $limit = 50) {
        $tableName = "`{$this->projectId}.{$this->datasetId}.{$this->tableId}`";
        
        $query = "
            SELECT 
                report_date,
                SUM(impression) as total_impressions,
                SUM(click) as total_clicks,
                SUM(cost) as total_cost,
                ROUND(SAFE_DIVIDE(SUM(click), SUM(impression)) * 100, 2) as ctr,
                ROUND(SAFE_DIVIDE(SUM(cost), SUM(click)), 2) as avg_cpc,
                COUNT(*) as total_records
            FROM {$tableName}
        ";
        
        $whereConditions = [];
        if ($startDate) $whereConditions[] = "report_date >= '{$startDate}'";
        if ($endDate) $whereConditions[] = "report_date <= '{$endDate}'";
        
        if (!empty($whereConditions)) {
            $query .= " WHERE " . implode(" AND ", $whereConditions);
        }
        
        $query .= " GROUP BY report_date ORDER BY report_date DESC LIMIT {$limit}";
        
        return $this->executeQuery($query);
    }
    
    public function getKeywordAnalysis($limit = 20) {
        $limit = min($limit, 100);
        $tableName = "`{$this->projectId}.{$this->datasetId}.{$this->tableId}`";
        
        $query = "
            SELECT 
                keyword_name,
                SUM(impression) as total_impressions,
                SUM(click) as total_clicks,
                SUM(cost) as total_cost,
                ROUND(SAFE_DIVIDE(SUM(click), SUM(impression)) * 100, 2) as ctr,
                ROUND(SAFE_DIVIDE(SUM(cost), SUM(click)), 2) as avg_cpc,
                COUNT(DISTINCT report_date) as active_days
            FROM {$tableName}
            WHERE keyword_name IS NOT NULL AND keyword_name != ''
            GROUP BY keyword_name
            HAVING total_impressions > 0
            ORDER BY total_impressions DESC
            LIMIT {$limit}
        ";
        
        return $this->executeQuery($query);
    }
    
    public function getDeviceAnalysis() {
        $tableName = "`{$this->projectId}.{$this->datasetId}.{$this->tableId}`";
        
        $query = "
            SELECT 
                device_type,
                SUM(impression) as total_impressions,
                SUM(click) as total_clicks,
                SUM(cost) as total_cost,
                ROUND(SAFE_DIVIDE(SUM(click), SUM(impression)) * 100, 2) as ctr,
                ROUND(SAFE_DIVIDE(SUM(cost), SUM(click)), 2) as avg_cpc
            FROM {$tableName}
            WHERE device_type IS NOT NULL AND device_type != ''
            GROUP BY device_type
            ORDER BY total_impressions DESC
        ";
        
        return $this->executeQuery($query);
    }
    
    public function getCampaignAnalysis($limit = 20) {
        $limit = min($limit, 50);
        $tableName = "`{$this->projectId}.{$this->datasetId}.{$this->tableId}`";
        
        $query = "
            SELECT 
                campaign_name,
                SUM(impression) as total_impressions,
                SUM(click) as total_clicks,
                SUM(cost) as total_cost,
                ROUND(SAFE_DIVIDE(SUM(click), SUM(impression)) * 100, 2) as ctr,
                ROUND(SAFE_DIVIDE(SUM(cost), SUM(click)), 2) as avg_cpc
            FROM {$tableName}
            WHERE campaign_name IS NOT NULL AND campaign_name != ''
            GROUP BY campaign_name
            ORDER BY total_impressions DESC
            LIMIT {$limit}
        ";
        
        return $this->executeQuery($query);
    }
    
    public function getData($limit = 100, $offset = 0, $filters = []) {
        $limit = min($limit, $this->maxRows);
        $tableName = "`{$this->projectId}.{$this->datasetId}.{$this->tableId}`";
        
        $query = "SELECT * FROM {$tableName}";
        
        $whereConditions = [];
        foreach ($filters as $field => $value) {
            if (!empty($value)) {
                $whereConditions[] = "{$field} = '" . addslashes($value) . "'";
            }
        }
        
        if (!empty($whereConditions)) {
            $query .= " WHERE " . implode(" AND ", $whereConditions);
        }
        
        $query .= " ORDER BY report_date DESC, impression DESC LIMIT {$limit}";
        if ($offset > 0) $query .= " OFFSET {$offset}";
        
        return $this->executeQuery($query);
    }
}
?>