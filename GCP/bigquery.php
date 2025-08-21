<?php
// bigquery.php (개선된 버전)
class BigQueryAPI {
    private $serviceAccountFile;
    private $projectId;
    private $datasetId;
    private $tableId;
    private $accessToken;
    private $maxRows;
    
    public function __construct($config) {
        $this->serviceAccountFile = $config['gcp_service_account_file'];
        $this->projectId = $config['gcp_project_id'];
        $this->datasetId = $config['dataset_id'];
        $this->tableId = $config['table_id'];
        $this->maxRows = $config['max_query_rows'];
        
        if (!file_exists($this->serviceAccountFile)) {
            throw new Exception('서비스 계정 JSON 파일을 찾을 수 없습니다: ' . $this->serviceAccountFile);
        }
        
        $this->authenticate();
    }
    
    /**
     * 개선된 서비스 계정 인증
     */
    private function authenticate() {
        $serviceAccount = json_decode(file_get_contents($this->serviceAccountFile), true);
        
        if (!$serviceAccount) {
            throw new Exception('서비스 계정 JSON 파일이 유효하지 않습니다.');
        }
        
        // 더 안정적인 JWT 생성
        $this->accessToken = $this->getAccessToken($serviceAccount);
    }
    
    /**
     * OAuth2 액세스 토큰 획득
     */
    private function getAccessToken($serviceAccount) {
        $now = time();
        $exp = $now + 3600; // 1시간
        
        // JWT Header
        $header = json_encode([
            'alg' => 'RS256',
            'typ' => 'JWT'
        ]);
        
        // JWT Payload
        $payload = json_encode([
            'iss' => $serviceAccount['client_email'],
            'scope' => 'https://www.googleapis.com/auth/bigquery https://www.googleapis.com/auth/cloud-platform',
            'aud' => 'https://oauth2.googleapis.com/token',
            'exp' => $exp,
            'iat' => $now
        ]);
        
        // Base64 URL 인코딩
        $base64Header = $this->base64UrlEncode($header);
        $base64Payload = $this->base64UrlEncode($payload);
        
        // 서명할 데이터
        $data = $base64Header . '.' . $base64Payload;
        
        // 개인키로 서명
        $signature = '';
        if (!openssl_sign($data, $signature, $serviceAccount['private_key'], OPENSSL_ALGO_SHA256)) {
            throw new Exception('JWT 서명 생성 실패: ' . openssl_error_string());
        }
        
        $base64Signature = $this->base64UrlEncode($signature);
        $jwt = $data . '.' . $base64Signature;
        
        // 토큰 요청
        return $this->requestAccessToken($jwt);
    }
    
    /**
     * 액세스 토큰 요청
     */
    private function requestAccessToken($jwt) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => 'https://oauth2.googleapis.com/token',
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt
            ]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded'
            ]
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($curlError) {
            throw new Exception('CURL 오류: ' . $curlError);
        }
        
        if ($httpCode !== 200) {
            $errorResponse = json_decode($response, true);
            throw new Exception('토큰 요청 실패 (HTTP ' . $httpCode . '): ' . 
                ($errorResponse['error_description'] ?? $response));
        }
        
        $tokenData = json_decode($response, true);
        
        if (!isset($tokenData['access_token'])) {
            throw new Exception('액세스 토큰을 받지 못했습니다: ' . $response);
        }
        
        return $tokenData['access_token'];
    }
    
    /**
     * BigQuery 쿼리 실행
     */
    public function executeQuery($query, $dryRun = false) {
        $url = "https://bigquery.googleapis.com/bigquery/v2/projects/{$this->projectId}/queries";
        
        $data = [
            'query' => $query,
            'useLegacySql' => false,
            'maxResults' => $this->maxRows,
            'dryRun' => $dryRun
        ];
        
        $response = $this->makeAuthenticatedRequest($url, 'POST', $data);
        
        if (isset($response['error'])) {
            throw new Exception('BigQuery 오류: ' . $response['error']['message'] . 
                (isset($response['error']['details']) ? ' 상세: ' . json_encode($response['error']['details']) : ''));
        }
        
        return $response;
    }
    
    /**
     * 간단한 테스트 쿼리
     */
    public function testConnection() {
        try {
            $query = "SELECT 1 as test_value";
            $result = $this->executeQuery($query);
            return ['success' => true, 'message' => 'BigQuery 연결 성공!'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * 기본 데이터 조회
     */
    public function getData($limit = 100, $offset = 0, $filters = []) {
        $limit = min($limit, $this->maxRows);
        
        $tableName = "`{$this->projectId}.{$this->datasetId}.{$this->tableId}`";
        
        $query = "SELECT 
            report_date,
            media_product,
            device_type,
            campaign_name,
            group_name,
            keyword_name,
            impression,
            click,
            cost,
            rank
        FROM {$tableName}";
        
        // 필터 적용
        $whereConditions = [];
        foreach ($filters as $field => $value) {
            if (!empty($value)) {
                if (is_array($value)) {
                    $values = "'" . implode("','", array_map('addslashes', $value)) . "'";
                    $whereConditions[] = "{$field} IN ({$values})";
                } else {
                    $whereConditions[] = "{$field} = '" . addslashes($value) . "'";
                }
            }
        }
        
        if (!empty($whereConditions)) {
            $query .= " WHERE " . implode(" AND ", $whereConditions);
        }
        
        $query .= " ORDER BY report_date DESC, impression DESC";
        $query .= " LIMIT {$limit}";
        
        if ($offset > 0) {
            $query .= " OFFSET {$offset}";
        }
        
        return $this->executeQuery($query);
    }
    
    /**
     * 일별 집계 데이터
     */
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
        
        if ($startDate) {
            $whereConditions[] = "report_date >= '{$startDate}'";
        }
        if ($endDate) {
            $whereConditions[] = "report_date <= '{$endDate}'";
        }
        
        if (!empty($whereConditions)) {
            $query .= " WHERE " . implode(" AND ", $whereConditions);
        }
        
        $query .= " GROUP BY report_date";
        $query .= " ORDER BY report_date DESC";
        $query .= " LIMIT {$limit}";
        
        return $this->executeQuery($query);
    }
    
    /**
     * 키워드 분석
     */
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
            WHERE keyword_name IS NOT NULL 
                AND keyword_name != ''
            GROUP BY keyword_name
            HAVING total_impressions > 0
            ORDER BY total_impressions DESC
            LIMIT {$limit}
        ";
        
        return $this->executeQuery($query);
    }
    
    /**
     * 디바이스 분석
     */
    public function getDeviceAnalysis() {
        $tableName = "`{$this->projectId}.{$this->datasetId}.{$this->tableId}`";
        
        $query = "
            SELECT 
                device_type,
                SUM(impression) as total_impressions,
                SUM(click) as total_clicks,
                SUM(cost) as total_cost,
                ROUND(SAFE_DIVIDE(SUM(click), SUM(impression)) * 100, 2) as ctr
            FROM {$tableName}
            WHERE device_type IS NOT NULL
            GROUP BY device_type
            ORDER BY total_impressions DESC
        ";
        
        return $this->executeQuery($query);
    }
    
    /**
     * 캠페인 분석
     */
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
            WHERE campaign_name IS NOT NULL 
                AND campaign_name != ''
            GROUP BY campaign_name
            HAVING total_impressions > 0
            ORDER BY total_impressions DESC
            LIMIT {$limit}
        ";
        
        return $this->executeQuery($query);
    }
    
    /**
     * 인증된 HTTP 요청
     */
    private function makeAuthenticatedRequest($url, $method = 'GET', $data = null) {
        $headers = [
            'Authorization: Bearer ' . $this->accessToken,
            'Content-Type: application/json',
            'Accept: application/json'
        ];
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_VERBOSE => false, // 디버깅 시 true로 변경
        ]);
        
        if ($data && in_array($method, ['POST', 'PUT'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($curlError) {
            throw new Exception('CURL 오류: ' . $curlError);
        }
        
        $decoded = json_decode($response, true);
        
        if ($httpCode >= 400) {
            $errorMsg = "HTTP 오류 {$httpCode}";
            if (isset($decoded['error']['message'])) {
                $errorMsg .= ": " . $decoded['error']['message'];
            }
            if (isset($decoded['error']['details'])) {
                $errorMsg .= " (상세: " . json_encode($decoded['error']['details']) . ")";
            }
            throw new Exception($errorMsg);
        }
        
        return $decoded;
    }
    
    /**
     * Base64 URL 인코딩
     */
    private function base64UrlEncode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
?>