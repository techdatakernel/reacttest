<?php
/**
 * 완전한 실시간 BigQuery 대시보드 - 풀페이지 뷰
 * 오류 수정 버전
 */

// 오류 리포팅 설정 (개발 시에만)
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// config.php 파일 확인 및 로드
if (!file_exists('config.php')) {
    die('config.php 파일이 존재하지 않습니다.');
}

$config = include 'config.php';
if (!$config) {
    die('config.php 파일을 읽을 수 없습니다.');
}

// 로그인 체크
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// 세션 타임아웃 체크
if (isset($config['session_timeout']) && (time() - $_SESSION['login_time']) > $config['session_timeout']) {
    session_destroy();
    header('Location: login.php');
    exit;
}

// BigQuery 클래스 파일 확인 및 로드
if (!file_exists('bigquery.php')) {
    $error = 'bigquery.php 파일이 존재하지 않습니다.';
} else {
    require_once 'bigquery.php';
}

$error = '';
$dailyStats = [];
$keywordAnalysis = [];
$deviceAnalysis = [];
$campaignAnalysis = [];
$costWarning = '';
$dataSource = '';
$costStatus = [];

try {
    if (class_exists('BigQueryAPI')) {
        $bigquery = new BigQueryAPI($config);
        
        // 비용 상태 확인
        if (method_exists($bigquery, 'getCostStatus')) {
            $costStatus = $bigquery->getCostStatus();
        }
        
        // 캐시 확인
        $cacheFile = 'cache/dashboard_' . date('Y-m-d-H') . '.json';
        
        if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < ($config['cache_duration'] ?? 3600)) {
            // 캐시에서 로드
            $cachedData = json_decode(file_get_contents($cacheFile), true);
            if ($cachedData) {
                $dailyStats = $cachedData['dailyStats'] ?? [];
                $keywordAnalysis = $cachedData['keywordAnalysis'] ?? [];
                $deviceAnalysis = $cachedData['deviceAnalysis'] ?? [];
                $campaignAnalysis = $cachedData['campaignAnalysis'] ?? [];
                $costWarning = "🔋 캐시된 데이터 (업데이트: " . date('H:i', filemtime($cacheFile)) . ")";
                $dataSource = 'cache';
            }
        } else {
            // 실제 BigQuery에서 데이터 로드
            $costWarning = "🔄 실시간 데이터 로딩 중...";
            $dataSource = 'live';
            
            // 실제 BigQuery 데이터 조회
            if (method_exists($bigquery, 'getDailyStats')) {
                $dailyStats = $bigquery->getDailyStats(30);
            }
            if (method_exists($bigquery, 'getKeywordAnalysis')) {
                $keywordAnalysis = $bigquery->getKeywordAnalysis(7, 20);
            }
            if (method_exists($bigquery, 'getDeviceAnalysis')) {
                $deviceAnalysis = $bigquery->getDeviceAnalysis(7);
            }
            if (method_exists($bigquery, 'getCampaignAnalysis')) {
                $campaignAnalysis = $bigquery->getCampaignAnalysis(7, 10);
            }
            
            // 캐시 저장
            $cacheData = [
                'dailyStats' => $dailyStats,
                'keywordAnalysis' => $keywordAnalysis,
                'deviceAnalysis' => $deviceAnalysis,
                'campaignAnalysis' => $campaignAnalysis,
                'timestamp' => time()
            ];
            
            if (!is_dir('cache')) {
                mkdir('cache', 0755, true);
            }
            file_put_contents($cacheFile, json_encode($cacheData));
            
            $costWarning = "✅ 실시간 데이터 (업데이트: " . date('H:i') . ")";
        }
    } else {
        throw new Exception('BigQueryAPI 클래스를 찾을 수 없습니다.');
    }
    
} catch (Exception $e) {
    $error = "데이터 로드 오류: " . $e->getMessage();
    $costWarning = "❌ 연결 오류 - 샘플 데이터 사용";
    
    // 오류 시 샘플 데이터 사용
    $dailyStats = [
        [
            'report_date' => '2024-08-20',
            'total_impression' => 145000,
            'total_click' => 3200,
            'total_cost' => 230000,
            'avg_rank' => 7.1
        ],
        [
            'report_date' => '2024-08-21',
            'total_impression' => 150000,
            'total_click' => 3500,
            'total_cost' => 250000,
            'avg_rank' => 6.8
        ]
    ];
    
    $keywordAnalysis = [
        [
            'keyword_name' => '스텔라 아르토이',
            'total_impression' => 20688,
            'total_click' => 693,
            'total_cost' => 150000,
            'ctr' => 3.35,
            'cpc' => 216,
            'avg_rank' => 7
        ]
    ];
    
    $deviceAnalysis = [
        [
            'device_type' => 'MOBILE',
            'total_impression' => 85000,
            'total_click' => 2100,
            'total_cost' => 180000,
            'ctr' => 2.47
        ]
    ];
    
    $campaignAnalysis = [
        [
            'campaign_name' => '스텔라 브랜드 캠페인',
            'total_impression' => 45000,
            'total_click' => 1200,
            'total_cost' => 120000,
            'ctr' => 2.67,
            'cpc' => 100
        ]
    ];
}

// JavaScript로 전달할 데이터 준비
$jsData = [
    'dailyStats' => $dailyStats,
    'keywordAnalysis' => $keywordAnalysis,
    'deviceAnalysis' => $deviceAnalysis,
    'campaignAnalysis' => $campaignAnalysis,
    'costStatus' => $costStatus,
    'dataSource' => $dataSource,
    'error' => $error
];

// JSON 인코딩 안전하게 처리
$jsonData = json_encode($jsData);
if (json_last_error() !== JSON_ERROR_NONE) {
    $jsonData = json_encode([
        'dailyStats' => [],
        'keywordAnalysis' => [],
        'deviceAnalysis' => [],
        'campaignAnalysis' => [],
        'costStatus' => [],
        'dataSource' => 'error',
        'error' => 'JSON 인코딩 오류: ' . json_last_error_msg()
    ]);
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🎯 실시간 비용 모니터링</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.min.js"></script>
    <style>
        /* === 풀페이지 뷰 기본 리셋 === */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html, body {
            width: 100% !important;
            height: 100% !important;
            overflow-x: hidden !important;
            margin: 0 !important;
            padding: 0 !important;
        }

        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --glass-bg: rgba(255, 255, 255, 0.25);
            --glass-border: rgba(255, 255, 255, 0.18);
            --glass-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.37);
            --success-color: #4CAF50;
            --warning-color: #FFC107;
            --danger-color: #F44336;
            --info-color: #2196F3;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: var(--primary-gradient);
            color: #333;
            line-height: 1.6;
        }

        /* === 풀페이지 컨테이너 === */
        .fullscreen-container {
            width: 100vw;
            min-height: 100vh;
            padding: 0;
            margin: 0;
        }

        /* === 헤더 === */
        .header {
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid var(--glass-border);
            padding: 15px 20px;
            box-shadow: var(--glass-shadow);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 1000;
            flex-wrap: wrap;
            gap: 15px;
        }

        .header h1 {
            font-size: 1.8rem;
            font-weight: 700;
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .status-indicator {
            background: var(--success-color);
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .cost-monitor {
            background: rgba(255,255,255,0.9);
            padding: 10px 15px;
            border-radius: 10px;
            font-size: 0.85rem;
            font-weight: 600;
            color: #333;
            border: 1px solid var(--glass-border);
        }

        .cost-overview {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }

        .cost-item {
            background: rgba(255,255,255,0.8);
            padding: 8px 12px;
            border-radius: 8px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .cost-item.warning {
            background: rgba(255, 193, 7, 0.2);
            border: 1px solid var(--warning-color);
        }

        .cost-item.danger {
            background: rgba(244, 67, 54, 0.2);
            border: 1px solid var(--danger-color);
        }

        /* === 메인 콘텐츠 === */
        .main-content {
            padding: 20px;
            width: 100%;
        }

        /* === 통계 카드 그리드 === */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            border: 1px solid var(--glass-border);
            border-radius: 15px;
            padding: 25px;
            text-align: center;
            box-shadow: var(--glass-shadow);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 40px rgba(31, 38, 135, 0.5);
        }

        .stat-icon {
            font-size: 2.5rem;
            margin-bottom: 10px;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 0.9rem;
            color: #666;
            font-weight: 500;
        }

        /* === 탭 네비게이션 === */
        .tab-navigation {
            display: flex;
            background: rgba(255,255,255,0.8);
            border-radius: 15px;
            padding: 10px;
            margin-bottom: 30px;
            box-shadow: var(--glass-shadow);
            flex-wrap: wrap;
            gap: 10px;
        }

        .tab-btn {
            background: transparent;
            border: none;
            padding: 12px 20px;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            color: #666;
            display: flex;
            align-items: center;
            gap: 8px;
            flex: 1;
            min-width: 120px;
        }

        .tab-btn.active {
            background: var(--primary-gradient);
            color: white;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }

        .tab-btn:hover:not(.active) {
            background: rgba(102, 126, 234, 0.1);
            color: #333;
        }

        /* === 탭 콘텐츠 === */
        .tab-content {
            display: none;
            animation: fadeIn 0.3s ease;
        }

        .tab-content.active {
            display: block;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* === 카드 === */
        .card {
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            border: 1px solid var(--glass-border);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: var(--glass-shadow);
        }

        .card h3 {
            margin-bottom: 20px;
            color: #333;
            font-size: 1.3rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* === 차트 컨테이너 === */
        .chart-container {
            margin: 20px 0;
        }

        .chart-wrapper {
            background: rgba(255,255,255,0.9);
            border-radius: 15px;
            padding: 20px;
            box-shadow: var(--glass-shadow);
            height: 400px;
            position: relative;
        }

        .chart-wrapper canvas {
            max-height: 100% !important;
        }

        /* === 그리드 레이아웃 === */
        .grid {
            display: grid;
            gap: 20px;
        }

        .grid-2 { grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); }
        .grid-3 { grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); }
        .grid-4 { grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); }

        /* === 테이블 === */
        .table-container {
            background: rgba(255,255,255,0.9);
            border-radius: 15px;
            overflow: hidden;
            box-shadow: var(--glass-shadow);
            margin: 20px 0;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 600px;
        }

        th, td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #f0f0f0;
        }

        th {
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
            font-size: 0.9rem;
        }

        td {
            font-size: 0.85rem;
        }

        tr:hover {
            background: rgba(102, 126, 234, 0.05);
        }

        /* === 버튼 === */
        .btn {
            background: var(--primary-gradient);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 10px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
        }

        .btn-success { background: linear-gradient(135deg, var(--success-color), #388e3c); }
        .btn-warning { background: linear-gradient(135deg, var(--warning-color), #ff8f00); }
        .btn-danger { background: linear-gradient(135deg, var(--danger-color), #d32f2f); }
        .btn-info { background: linear-gradient(135deg, var(--info-color), #1976d2); }

        /* === 컨트롤 === */
        .controls {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 30px;
            padding: 20px;
            background: rgba(255,255,255,0.8);
            border-radius: 15px;
            backdrop-filter: blur(5px);
        }

        .controls label {
            display: flex;
            flex-direction: column;
            gap: 5px;
            font-weight: 500;
            color: #333;
            min-width: 150px;
        }

        .controls input,
        .controls select {
            padding: 10px 15px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 0.9rem;
            transition: border-color 0.3s ease;
            background: rgba(255,255,255,0.9);
        }

        .controls input:focus,
        .controls select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        /* === 오류 메시지 === */
        .error-message {
            background: #fee;
            color: #c33;
            padding: 15px;
            border-radius: 10px;
            margin: 20px 0;
            border: 1px solid #fcc;
        }

        .loading {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 200px;
            font-size: 1.1rem;
            color: #666;
        }

        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid var(--info-color);
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin-right: 15px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* === 반응형 === */
        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                text-align: center;
            }

            .header h1 {
                font-size: 1.5rem;
            }

            .tab-navigation {
                justify-content: center;
            }

            .tab-btn {
                min-width: 100px;
                font-size: 0.8rem;
                padding: 10px 15px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .chart-wrapper {
                height: 300px;
            }

            .grid-2, .grid-3, .grid-4 {
                grid-template-columns: 1fr;
            }

            .controls {
                flex-direction: column;
            }

            .cost-overview {
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="fullscreen-container">
        <!-- 헤더 -->
        <header class="header">
            <h1>🎯 실시간 비용 모니터링</h1>
            <div class="status-indicator">
                ✅ 정상 운영 중
            </div>
            <div class="cost-monitor">
                <?php echo htmlspecialchars($costWarning); ?>
            </div>
            <?php if (!empty($costStatus) && is_array($costStatus)): ?>
            <div class="cost-overview">
                <?php if (isset($costStatus['daily'])): ?>
                <div class="cost-item <?php echo ($costStatus['daily']['percentage'] ?? 0) > 80 ? 'danger' : (($costStatus['daily']['percentage'] ?? 0) > 60 ? 'warning' : ''); ?>">
                    일일: $<?php echo number_format($costStatus['daily']['current'] ?? 0, 2); ?> / $<?php echo number_format($costStatus['daily']['limit'] ?? 0, 2); ?>
                    (<?php echo number_format($costStatus['daily']['percentage'] ?? 0, 1); ?>%)
                </div>
                <?php endif; ?>
                <?php if (isset($costStatus['monthly'])): ?>
                <div class="cost-item <?php echo ($costStatus['monthly']['percentage'] ?? 0) > 80 ? 'danger' : (($costStatus['monthly']['percentage'] ?? 0) > 60 ? 'warning' : ''); ?>">
                    월간: $<?php echo number_format($costStatus['monthly']['current'] ?? 0, 2); ?> / $<?php echo number_format($costStatus['monthly']['limit'] ?? 0, 2); ?>
                    (<?php echo number_format($costStatus['monthly']['percentage'] ?? 0, 1); ?>%)
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </header>

        <!-- 메인 콘텐츠 -->
        <main class="main-content">
            <?php if ($error): ?>
            <div class="error-message">
                <strong>오류:</strong> <?php echo htmlspecialchars($error); ?>
            </div>
            <?php endif; ?>

            <!-- 통계 카드들 -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">👁️</div>
                    <div class="stat-value" id="totalImpressions">로딩중...</div>
                    <div class="stat-label">총 노출수</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">👆</div>
                    <div class="stat-value" id="totalClicks">로딩중...</div>
                    <div class="stat-label">총 클릭수</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">💰</div>
                    <div class="stat-value" id="totalCost">로딩중...</div>
                    <div class="stat-label">총 비용</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">📊</div>
                    <div class="stat-value" id="averageRank">로딩중...</div>
                    <div class="stat-label">평균 순위</div>
                </div>
            </div>

            <!-- 탭 네비게이션 -->
            <nav class="tab-navigation">
                <button class="tab-btn active" onclick="showTab('개요')">📊 개요</button>
                <button class="tab-btn" onclick="showTab('일별분석')">📅 일별 분석</button>
                <button class="tab-btn" onclick="showTab('키워드분석')">🔍 키워드 분석</button>
                <button class="tab-btn" onclick="showTab('디바이스분석')">📱 디바이스 분석</button>
                <button class="tab-btn" onclick="showTab('캠페인분석')">📢 캠페인 분석</button>
                <button class="tab-btn" onclick="showTab('비용모니터링')">💰 비용 모니터링</button>
                <button class="tab-btn" onclick="showTab('원본데이터')">📋 원본 데이터</button>
            </nav>

            <!-- 개요 탭 -->
            <div id="개요" class="tab-content active">
                <div class="grid grid-2">
                    <div class="card">
                        <h3>📈 일별 트렌드 (최근 30일)</h3>
                        <div class="chart-wrapper">
                            <canvas id="dailyChart"></canvas>
                        </div>
                    </div>
                    <div class="card">
                        <h3>🔍 상위 키워드 (최근 7일)</h3>
                        <div class="chart-wrapper">
                            <canvas id="keywordChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 일별 분석 탭 -->
            <div id="일별분석" class="tab-content">
                <div class="card">
                    <h3>📅 일별 성과 분석</h3>
                    <div class="controls">
                        <button class="btn btn-info" onclick="refreshData()">🔄 데이터 새로고침</button>
                        <button class="btn btn-success" onclick="exportDailyData()">📊 일별 데이터 내보내기</button>
                    </div>
                    <div class="chart-wrapper">
                        <canvas id="dailyDetailChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- 키워드 분석 탭 -->
            <div id="키워드분석" class="tab-content">
                <div class="card">
                    <h3>🔍 키워드 성과 분석 (최근 7일)</h3>
                    <div class="table-container">
                        <table id="keywordTable">
                            <thead>
                                <tr>
                                    <th>키워드</th>
                                    <th>노출수</th>
                                    <th>클릭수</th>
                                    <th>비용</th>
                                    <th>CTR</th>
                                    <th>CPC</th>
                                    <th>평균순위</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- 데이터가 여기에 로드됩니다 -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- 디바이스 분석 탭 -->
            <div id="디바이스분석" class="tab-content">
                <div class="grid grid-2">
                    <div class="card">
                        <h3>📱 디바이스별 분포 (최근 7일)</h3>
                        <div class="chart-wrapper">
                            <canvas id="deviceChart"></canvas>
                        </div>
                    </div>
                    <div class="card">
                        <h3>📊 디바이스 성과 테이블</h3>
                        <div class="table-container">
                            <table id="deviceTable">
                                <thead>
                                    <tr>
                                        <th>디바이스</th>
                                        <th>노출수</th>
                                        <th>클릭수</th>
                                        <th>비용</th>
                                        <th>CTR</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- 데이터가 여기에 로드됩니다 -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 캠페인 분석 탭 -->
            <div id="캠페인분석" class="tab-content">
                <div class="card">
                    <h3>📢 캠페인 성과 분석 (최근 7일)</h3>
                    <div class="table-container">
                        <table id="campaignTable">
                            <thead>
                                <tr>
                                    <th>캠페인명</th>
                                    <th>노출수</th>
                                    <th>클릭수</th>
                                    <th>비용</th>
                                    <th>CTR</th>
                                    <th>CPC</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- 데이터가 여기에 로드됩니다 -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- 비용 모니터링 탭 -->
            <div id="비용모니터링" class="tab-content">
                <div class="grid grid-2">
                    <div class="card">
                        <h3>💰 비용 현황</h3>
                        <div id="costStatusContainer">
                            <p>비용 데이터를 로드 중...</p>
                        </div>
                    </div>
                    <div class="card">
                        <h3>📈 비용 트렌드 (최근 7일)</h3>
                        <div class="chart-wrapper">
                            <canvas id="costTrendChart"></canvas>
                        </div>
                    </div>
                </div>
                <div class="card">
                    <h3>🔧 비용 관리</h3>
                    <div class="controls">
                        <button class="btn btn-info" onclick="loadCostHistory()">📊 비용 이력 조회</button>
                        <button class="btn btn-warning" onclick="resetCostRestrictions()">🔓 제한 해제</button>
                        <button class="btn btn-success" onclick="exportCostReport()">📄 비용 보고서</button>
                    </div>
                </div>
            </div>

            <!-- 원본 데이터 탭 -->
            <div id="원본데이터" class="tab-content">
                <div class="card">
                    <h3>📋 실제 BigQuery 원본 데이터</h3>
                    <div class="controls">
                        <button class="btn btn-info" onclick="loadRawData()">📊 원본 데이터 로드</button>
                        <button class="btn btn-success" onclick="exportRawData()">📄 CSV 내보내기</button>
                    </div>
                    <div id="rawDataContainer">
                        <p>원본 데이터 로드 버튼을 클릭하세요.</p>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // PHP에서 전달받은 실제 BigQuery 데이터
        let realData = {};
        
        try {
            realData = <?php echo $jsonData; ?>;
            console.log('실제 BigQuery 데이터:', realData);
        } catch (e) {
            console.error('데이터 파싱 오류:', e);
            realData = {
                dailyStats: [],
                keywordAnalysis: [],
                deviceAnalysis: [],
                campaignAnalysis: [],
                costStatus: {},
                dataSource: 'error',
                error: '데이터 파싱 오류'
            };
        }

        // 전역 변수
        let charts = {};
        let isChartsReady = false;

        // Chart.js 초기화
        function initializeCharts() {
            console.log('Chart.js 초기화 시작');
            
            if (typeof Chart === 'undefined') {
                console.error('Chart.js가 로드되지 않았습니다.');
                return false;
            }
            
            Chart.defaults.font.family = "'Segoe UI', Tahoma, Geneva, Verdana, sans-serif";
            Chart.defaults.color = '#2d3748';
            Chart.defaults.plugins.legend.labels.usePointStyle = true;
            
            isChartsReady = true;
            console.log('Chart.js 초기화 완료');
            return true;
        }

        // 안전한 차트 생성
        function createSafeChart(canvasId, config) {
            const canvas = document.getElementById(canvasId);
            if (!canvas) {
                console.error(`캔버스를 찾을 수 없습니다: ${canvasId}`);
                return null;
            }
            
            if (charts[canvasId]) {
                charts[canvasId].destroy();
                console.log(`기존 차트 제거: ${canvasId}`);
            }
            
            try {
                canvas.style.width = '100%';
                canvas.style.height = '100%';
                
                charts[canvasId] = new Chart(canvas, config);
                console.log(`차트 생성 성공: ${canvasId}`);
                return charts[canvasId];
            } catch (error) {
                console.error(`차트 생성 실패 (${canvasId}):`, error);
                return null;
            }
        }

        // 숫자 포맷팅 함수
        function formatNumber(num) {
            return num.toLocaleString();
        }

        // 실제 BigQuery 데이터로 일별 차트 생성
        function createDailyChart(data, chartId = 'dailyChart') {
            console.log('일별 차트 생성:', chartId, data);
            
            if (!data || !Array.isArray(data) || data.length === 0) {
                console.warn('일별 차트: 데이터가 없습니다.');
                return;
            }
            
            const labels = [];
            const impressions = [];
            const clicks = [];
            
            data.forEach(row => {
                if (row.report_date) {
                    const date = row.report_date.substring(5); // YYYY-MM-DD -> MM-DD
                    labels.push(date);
                    impressions.push(parseInt(row.total_impression) || 0);
                    clicks.push(parseInt(row.total_click) || 0);
                }
            });
            
            // 데이터를 날짜 순으로 정렬
            const combined = labels.map((label, i) => ({
                date: label,
                impression: impressions[i],
                click: clicks[i]
            })).sort((a, b) => a.date.localeCompare(b.date));
            
            const sortedLabels = combined.map(item => item.date);
            const sortedImpressions = combined.map(item => item.impression);
            const sortedClicks = combined.map(item => item.click);
            
            const config = {
                type: 'line',
                data: {
                    labels: sortedLabels,
                    datasets: [
                        {
                            label: '노출수',
                            data: sortedImpressions,
                            borderColor: '#667eea',
                            backgroundColor: 'rgba(102, 126, 234, 0.1)',
                            borderWidth: 3,
                            tension: 0.4,
                            fill: true,
                            yAxisID: 'y'
                        },
                        {
                            label: '클릭수',
                            data: sortedClicks,
                            borderColor: '#f093fb',
                            backgroundColor: 'rgba(240, 147, 251, 0.1)',
                            borderWidth: 3,
                            tension: 0.4,
                            fill: false,
                            yAxisID: 'y1'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top'
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false,
                            callbacks: {
                                label: function(context) {
                                    let label = context.dataset.label || '';
                                    if (label) {
                                        label += ': ';
                                    }
                                    label += formatNumber(context.parsed.y);
                                    return label;
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            display: true,
                            title: {
                                display: true,
                                text: '날짜 (MM-DD)'
                            }
                        },
                        y: {
                            type: 'linear',
                            display: true,
                            position: 'left',
                            title: {
                                display: true,
                                text: '노출수'
                            },
                            ticks: {
                                callback: function(value) {
                                    return formatNumber(value);
                                }
                            }
                        },
                        y1: {
                            type: 'linear',
                            display: true,
                            position: 'right',
                            title: {
                                display: true,
                                text: '클릭수'
                            },
                            grid: {
                                drawOnChartArea: false,
                            },
                            ticks: {
                                callback: function(value) {
                                    return formatNumber(value);
                                }
                            }
                        }
                    }
                }
            };
            
            return createSafeChart(chartId, config);
        }

        // 실제 BigQuery 데이터로 키워드 차트 생성
        function createKeywordChart(data) {
            console.log('키워드 차트 생성:', data);
            
            if (!data || !Array.isArray(data) || data.length === 0) {
                console.warn('키워드 차트: 데이터가 없습니다.');
                return;
            }
            
            const labels = [];
            const impressions = [];
            const colors = ['#667eea', '#f093fb', '#4CAF50', '#FFC107', '#FF5722', '#9C27B0', '#00BCD4', '#FF9800'];
            
            data.slice(0, 8).forEach(row => {
                if (row.keyword_name) {
                    labels.push(row.keyword_name);
                    impressions.push(parseInt(row.total_impression) || 0);
                }
            });
            
            const config = {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: impressions,
                        backgroundColor: colors,
                        borderColor: colors.map(color => color + 'CC'),
                        borderWidth: 2,
                        hoverOffset: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 20,
                                usePointStyle: true
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.parsed || 0;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = ((value / total) * 100).toFixed(1);
                                    return `${label}: ${formatNumber(value)} (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            };
            
            return createSafeChart('keywordChart', config);
        }

        // 실제 BigQuery 데이터로 디바이스 차트 생성
        function createDeviceChart(data) {
            console.log('디바이스 차트 생성:', data);
            
            if (!data || !Array.isArray(data) || data.length === 0) {
                console.warn('디바이스 차트: 데이터가 없습니다.');
                return;
            }
            
            const labels = [];
            const impressions = [];
            const clicks = [];
            
            data.forEach(row => {
                if (row.device_type) {
                    labels.push(row.device_type);
                    impressions.push(parseInt(row.total_impression) || 0);
                    clicks.push(parseInt(row.total_click) || 0);
                }
            });
            
            const config = {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: '노출수',
                            data: impressions,
                            backgroundColor: 'rgba(102, 126, 234, 0.8)',
                            borderColor: '#667eea',
                            borderWidth: 1
                        },
                        {
                            label: '클릭수',
                            data: clicks,
                            backgroundColor: 'rgba(240, 147, 251, 0.8)',
                            borderColor: '#f093fb',
                            borderWidth: 1
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top'
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return `${context.dataset.label}: ${formatNumber(context.parsed.y)}`;
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return formatNumber(value);
                                }
                            }
                        }
                    }
                }
            };
            
            return createSafeChart('deviceChart', config);
        }

        // 실제 데이터로 테이블 생성
        function createKeywordTable(data) {
            const tbody = document.querySelector('#keywordTable tbody');
            if (!tbody) return;
            
            tbody.innerHTML = '';
            
            if (!data || !Array.isArray(data) || data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7">데이터가 없습니다.</td></tr>';
                return;
            }
            
            data.forEach(row => {
                if (row.keyword_name) {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td>${row.keyword_name}</td>
                        <td>${formatNumber(parseInt(row.total_impression) || 0)}</td>
                        <td>${formatNumber(parseInt(row.total_click) || 0)}</td>
                        <td>₩${formatNumber(parseFloat(row.total_cost) || 0)}</td>
                        <td>${(parseFloat(row.ctr) || 0).toFixed(2)}%</td>
                        <td>₩${formatNumber(parseFloat(row.cpc) || 0)}</td>
                        <td>${(parseFloat(row.avg_rank) || 0).toFixed(1)}</td>
                    `;
                    tbody.appendChild(tr);
                }
            });
        }

        function createDeviceTable(data) {
            const tbody = document.querySelector('#deviceTable tbody');
            if (!tbody) return;
            
            tbody.innerHTML = '';
            
            if (!data || !Array.isArray(data) || data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="5">데이터가 없습니다.</td></tr>';
                return;
            }
            
            data.forEach(row => {
                if (row.device_type) {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td>${row.device_type}</td>
                        <td>${formatNumber(parseInt(row.total_impression) || 0)}</td>
                        <td>${formatNumber(parseInt(row.total_click) || 0)}</td>
                        <td>₩${formatNumber(parseFloat(row.total_cost) || 0)}</td>
                        <td>${(parseFloat(row.ctr) || 0).toFixed(2)}%</td>
                    `;
                    tbody.appendChild(tr);
                }
            });
        }

        function createCampaignTable(data) {
            const tbody = document.querySelector('#campaignTable tbody');
            if (!tbody) return;
            
            tbody.innerHTML = '';
            
            if (!data || !Array.isArray(data) || data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6">데이터가 없습니다.</td></tr>';
                return;
            }
            
            data.forEach(row => {
                if (row.campaign_name) {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td>${row.campaign_name}</td>
                        <td>${formatNumber(parseInt(row.total_impression) || 0)}</td>
                        <td>${formatNumber(parseInt(row.total_click) || 0)}</td>
                        <td>₩${formatNumber(parseFloat(row.total_cost) || 0)}</td>
                        <td>${(parseFloat(row.ctr) || 0).toFixed(2)}%</td>
                        <td>₩${formatNumber(parseFloat(row.cpc) || 0)}</td>
                    `;
                    tbody.appendChild(tr);
                }
            });
        }

        // 통계 카드 업데이트
        function updateStatCards() {
            let totalImpressions = 0;
            let totalClicks = 0;
            let totalCost = 0;
            let totalRank = 0;
            let rankCount = 0;
            
            // 일별 데이터에서 총합 계산
            if (realData.dailyStats && Array.isArray(realData.dailyStats)) {
                realData.dailyStats.forEach(row => {
                    if (row.total_impression) {
                        totalImpressions += parseInt(row.total_impression) || 0;
                        totalClicks += parseInt(row.total_click) || 0;
                        totalCost += parseFloat(row.total_cost) || 0;
                        
                        if (row.avg_rank) {
                            totalRank += parseFloat(row.avg_rank) || 0;
                            rankCount++;
                        }
                    }
                });
            }
            
            const averageRank = rankCount > 0 ? (totalRank / rankCount) : 0;
            
            document.getElementById('totalImpressions').textContent = formatNumber(totalImpressions);
            document.getElementById('totalClicks').textContent = formatNumber(totalClicks);
            document.getElementById('totalCost').textContent = '₩' + formatNumber(totalCost);
            document.getElementById('averageRank').textContent = averageRank.toFixed(1);
        }

        // 탭 전환 함수
        function showTab(tabName) {
            // 모든 탭 버튼에서 active 클래스 제거
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // 모든 탭 콘텐츠 숨기기
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            
            // 클릭된 탭 버튼에 active 클래스 추가
            event.target.classList.add('active');
            
            // 해당 탭 콘텐츠 표시
            const tabContent = document.getElementById(tabName);
            if (tabContent) {
                tabContent.classList.add('active');
            }
            
            // 탭별 데이터 로드
            setTimeout(() => {
                switch(tabName) {
                    case '개요':
                        loadOverviewData();
                        break;
                    case '일별분석':
                        createDailyChart(realData.dailyStats, 'dailyDetailChart');
                        break;
                    case '키워드분석':
                        createKeywordTable(realData.keywordAnalysis);
                        break;
                    case '디바이스분석':
                        createDeviceChart(realData.deviceAnalysis);
                        createDeviceTable(realData.deviceAnalysis);
                        break;
                    case '캠페인분석':
                        createCampaignTable(realData.campaignAnalysis);
                        break;
                    case '비용모니터링':
                        displayCostStatus();
                        break;
                }
            }, 100);
        }

        // 개요 데이터 로드
        function loadOverviewData() {
            if (!isChartsReady) {
                console.log('차트가 준비되지 않음, 재시도...');
                setTimeout(loadOverviewData, 500);
                return;
            }
            
            updateStatCards();
            createDailyChart(realData.dailyStats);
            createKeywordChart(realData.keywordAnalysis);
        }

        // 비용 상태 표시
        function displayCostStatus() {
            const container = document.getElementById('costStatusContainer');
            if (!container) return;
            
            if (realData.costStatus && Object.keys(realData.costStatus).length > 0) {
                const cost = realData.costStatus;
                container.innerHTML = `
                    <div class="cost-status-grid">
                        <div class="cost-status-item">
                            <h4>일일 사용량</h4>
                            <p>$${(cost.daily?.current || 0).toFixed(4)} / $${(cost.daily?.limit || 0).toFixed(2)}</p>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: ${Math.min(cost.daily?.percentage || 0, 100)}%"></div>
                            </div>
                            <small>${(cost.daily?.percentage || 0).toFixed(1)}% 사용</small>
                        </div>
                        <div class="cost-status-item">
                            <h4>주간 사용량</h4>
                            <p>$${(cost.weekly?.current || 0).toFixed(4)} / $${(cost.weekly?.limit || 0).toFixed(2)}</p>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: ${Math.min(cost.weekly?.percentage || 0, 100)}%"></div>
                            </div>
                            <small>${(cost.weekly?.percentage || 0).toFixed(1)}% 사용</small>
                        </div>
                        <div class="cost-status-item">
                            <h4>월간 사용량</h4>
                            <p>$${(cost.monthly?.current || 0).toFixed(4)} / $${(cost.monthly?.limit || 0).toFixed(2)}</p>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: ${Math.min(cost.monthly?.percentage || 0, 100)}%"></div>
                            </div>
                            <small>${(cost.monthly?.percentage || 0).toFixed(1)}% 사용</small>
                        </div>
                    </div>
                    <style>
                        .cost-status-grid {
                            display: grid;
                            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                            gap: 15px;
                        }
                        .cost-status-item {
                            background: rgba(255,255,255,0.8);
                            padding: 15px;
                            border-radius: 10px;
                            border: 1px solid #e0e0e0;
                        }
                        .cost-status-item h4 {
                            margin-bottom: 10px;
                            color: #333;
                        }
                        .progress-bar {
                            width: 100%;
                            height: 8px;
                            background: #f0f0f0;
                            border-radius: 4px;
                            margin: 8px 0;
                            overflow: hidden;
                        }
                        .progress-fill {
                            height: 100%;
                            background: linear-gradient(90deg, #4CAF50, #FFC107, #F44336);
                            border-radius: 4px;
                            transition: width 0.3s ease;
                        }
                    </style>
                `;
            } else {
                container.innerHTML = '<p>비용 데이터를 사용할 수 없습니다.</p>';
            }
        }

        // API 함수들
        function refreshData() {
            console.log('데이터 새로고침');
            location.reload();
        }

        function exportDailyData() {
            window.open('api.php?action=export_cost_report&format=csv&period=30', '_blank');
        }

        function loadCostHistory() {
            console.log('비용 이력 로드');
            alert('비용 이력 기능은 준비 중입니다.');
        }

        function resetCostRestrictions() {
            console.log('비용 제한 해제');
            alert('비용 제한 해제 기능은 준비 중입니다.');
        }

        function exportCostReport() {
            console.log('비용 보고서 내보내기');
            alert('비용 보고서 기능은 준비 중입니다.');
        }

        function loadRawData() {
            const container = document.getElementById('rawDataContainer');
            container.innerHTML = '<div class="loading"><div class="spinner"></div>원본 데이터 로드 중...</div>';
            
            setTimeout(() => {
                container.innerHTML = '<p>원본 데이터 로드 기능은 준비 중입니다.</p>';
            }, 2000);
        }

        function exportRawData() {
            console.log('원본 데이터 내보내기');
            alert('원본 데이터 내보내기 기능은 준비 중입니다.');
        }

        // 페이지 로드 시 초기화
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM 로드 완료');
            console.log('실제 BigQuery 데이터:', realData);
            
            // Chart.js 초기화 대기
            function waitForChart() {
                if (typeof Chart !== 'undefined') {
                    console.log('Chart.js 로드 확인됨');
                    initializeCharts();
                    loadOverviewData();
                } else {
                    console.log('Chart.js 로드 대기 중...');
                    setTimeout(waitForChart, 100);
                }
            }
            
            waitForChart();
        });
    </script>
</body>
</html>