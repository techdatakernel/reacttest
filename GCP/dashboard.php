<?php
// dashboard.php - 완전한 기능 구현 버전
session_start();

$config = include 'config.php';

// 로그인 체크 (기존 방식 유지)
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// 세션 타임아웃 체크
if ((time() - $_SESSION['login_time']) > $config['session_timeout']) {
    session_destroy();
    header('Location: login.php');
    exit;
}

require_once 'bigquery.php';

$error = '';
$stats = [];
$costWarning = '';

try {
    $bigquery = new BigQueryAPI($config);
    
    // 캐시 확인 (기존 방식 유지)
    $cacheFile = 'cache/dashboard_' . date('Y-m-d-H') . '.json';
    
    if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $config['cache_duration']) {
        // 캐시에서 로드
        $cachedData = json_decode(file_get_contents($cacheFile), true);
        $dailyStats = $cachedData['dailyStats'];
        $keywordAnalysis = $cachedData['keywordAnalysis'];
        $deviceAnalysis = $cachedData['deviceAnalysis'];
        $campaignAnalysis = $cachedData['campaignAnalysis'];
        $costWarning = "📋 캐시된 데이터 (업데이트: " . date('H:i', filemtime($cacheFile)) . ")";
    } else {
        // 새로 조회
        $dailyStats = $bigquery->getDailyStats(null, null, 50);
        $keywordAnalysis = $bigquery->getKeywordAnalysis(15);
        $deviceAnalysis = $bigquery->getDeviceAnalysis();
        $campaignAnalysis = $bigquery->getCampaignAnalysis(10);
        
        // 캐시 저장
        if (!is_dir('cache')) mkdir('cache', 0755, true);
        file_put_contents($cacheFile, json_encode([
            'dailyStats' => $dailyStats,
            'keywordAnalysis' => $keywordAnalysis,
            'deviceAnalysis' => $deviceAnalysis,
            'campaignAnalysis' => $campaignAnalysis
        ]));
        
        $costWarning = "💰 실시간 데이터 조회 완료 - 비용 최적화 적용됨 (최대 1,000행)";
    }
    
} catch (Exception $e) {
    $error = $e->getMessage();
}

// 로그아웃 처리
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🎯 키워드 성과 분석 대시보드</title>
    <!-- Chart.js CDN - 최신 안정 버전 -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns@3.0.0/dist/chartjs-adapter-date-fns.bundle.min.js"></script>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div class="container">
        <!-- 헤더 -->
        <div class="header">
            <h1>🎯 키워드 성과 분석 대시보드</h1>
            <p>GCP BigQuery를 이용한 실시간 데이터 분석</p>
            <div class="user-info">
                👤 <span id="currentUser"><?php echo htmlspecialchars($_SESSION['username']); ?></span> | 
                🏢 SESCO 데이터 대시보드 | 
                <a href="?logout=1" class="logout-btn">로그아웃</a>
            </div>
        </div>

        <!-- 비용 최적화 알림 -->
        <?php if ($costWarning): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($costWarning); ?>
            </div>
        <?php endif; ?>
        
        <!-- 오류 표시 -->
        <?php if ($error): ?>
            <div class="alert alert-error">
                <strong>⚠️ 오류:</strong> <?php echo htmlspecialchars($error); ?>
                <br><small>서비스 계정 JSON 파일이 올바른 위치에 있는지 확인하세요.</small>
            </div>
        <?php endif; ?>

        <!-- 탭 메뉴 -->
        <nav class="nav-tabs">
            <button class="nav-tab active" onclick="showTab('개요')">📈 개요</button>
            <button class="nav-tab" onclick="showTab('키워드분석')">🔍 키워드 분석</button>
            <button class="nav-tab" onclick="showTab('디바이스분석')">📱 디바이스 분석</button>
            <button class="nav-tab" onclick="showTab('캠페인분석')">📢 캠페인 분석</button>
            <button class="nav-tab" onclick="showTab('트렌드분석')">📈 트렌드 분석</button>
            <button class="nav-tab" onclick="showTab('기간비교')">📊 기간 비교</button>
            <button class="nav-tab" onclick="showTab('AI인사이트')">🤖 AI 인사이트</button>
            <button class="nav-tab" onclick="showTab('피벗')">📋 피벗</button>
            <button class="nav-tab" onclick="showTab('원본데이터')">📄 원본 데이터</button>
        </nav>

        <!-- 개요 탭 -->
        <div id="개요" class="tab-content active">
            <div class="grid grid-4">
                <div class="stat-card">
                    <div class="stat-number" id="totalKeywords">-</div>
                    <div class="stat-label">총 키워드 수</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number" id="totalImpressions">-</div>
                    <div class="stat-label">총 노출수</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number" id="totalClicks">-</div>
                    <div class="stat-label">총 클릭수</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number" id="avgCTR">-</div>
                    <div class="stat-label">평균 CTR</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number" id="totalCost">-</div>
                    <div class="stat-label">총 비용</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number" id="avgCPC">-</div>
                    <div class="stat-label">평균 CPC</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number" id="avgRank">-</div>
                    <div class="stat-label">평균 순위</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number" id="totalDays">-</div>
                    <div class="stat-label">분석 기간</div>
                </div>
            </div>

            <div class="grid grid-2">
                <div class="chart-container">
                    <h3>📊 일별 성과 추이</h3>
                    <div class="chart-wrapper">
                        <canvas id="dailyChart" width="400" height="300"></canvas>
                    </div>
                </div>
                <div class="chart-container">
                    <h3>🎯 상위 키워드 성과</h3>
                    <div class="chart-wrapper">
                        <canvas id="topKeywordsChart" width="400" height="300"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- 키워드 분석 탭 -->
        <div id="키워드분석" class="tab-content">
            <div class="card">
                <h3>🔍 키워드 성과 분석</h3>
                <div class="analysis-controls">
                    <button class="btn btn-primary" onclick="refreshKeywordAnalysis()">데이터 새로고침</button>
                    <button class="btn btn-success" onclick="exportKeywordReport()">키워드 보고서 내보내기</button>
                </div>
                <div id="keywordAnalysisTable"></div>
            </div>
        </div>

        <!-- 디바이스 분석 탭 -->
        <div id="디바이스분석" class="tab-content">
            <div class="grid grid-2">
                <div class="chart-container">
                    <h3>📱 디바이스별 분포</h3>
                    <div class="chart-wrapper">
                        <canvas id="deviceChart" width="400" height="300"></canvas>
                    </div>
                </div>
                <div class="card">
                    <h3>📊 디바이스 성과 분석</h3>
                    <div id="deviceAnalysisTable"></div>
                </div>
            </div>
        </div>

        <!-- 캠페인 분석 탭 -->
        <div id="캠페인분석" class="tab-content">
            <div class="card">
                <h3>📢 캠페인 성과 분석</h3>
                <div class="analysis-controls">
                    <button class="btn btn-primary" onclick="refreshCampaignAnalysis()">데이터 새로고침</button>
                    <button class="btn btn-success" onclick="exportCampaignReport()">캠페인 보고서 내보내기</button>
                </div>
                <div id="campaignAnalysisTable"></div>
            </div>
        </div>

        <!-- 트렌드 분석 탭 -->
        <div id="트렌드분석" class="tab-content">
            <div class="card">
                <h3>📈 키워드 트렌드 분석</h3>
                <div class="trend-controls">
                    <button class="btn btn-primary" onclick="performTrendAnalysis()">트렌드 분석 실행</button>
                    <button class="btn btn-success" onclick="refreshTrendData()">데이터 새로고침</button>
                    <div class="trend-options">
                        <label>
                            <input type="radio" name="trendPeriod" value="7" checked> 최근 7일
                        </label>
                        <label>
                            <input type="radio" name="trendPeriod" value="14"> 최근 14일
                        </label>
                        <label>
                            <input type="radio" name="trendPeriod" value="30"> 최근 30일
                        </label>
                    </div>
                </div>
            </div>
            
            <div class="grid grid-2">
                <div class="card">
                    <h3>🚀 급상승 키워드</h3>
                    <div id="risingKeywordsContainer">
                        <p>트렌드 분석을 실행하면 결과가 표시됩니다.</p>
                    </div>
                </div>
                <div class="card">
                    <h3>📉 급하락 키워드</h3>
                    <div id="fallingKeywordsContainer">
                        <p>트렌드 분석을 실행하면 결과가 표시됩니다.</p>
                    </div>
                </div>
                <div class="card">
                    <h3>🏆 최고 성과 키워드</h3>
                    <div id="topPerformingContainer">
                        <p>트렌드 분석을 실행하면 결과가 표시됩니다.</p>
                    </div>
                </div>
                <div class="card">
                    <h3>⚠️ 개선 필요 키워드</h3>
                    <div id="underPerformingContainer">
                        <p>트렌드 분석을 실행하면 결과가 표시됩니다.</p>
                    </div>
                </div>
            </div>

            <div class="chart-container">
                <h3>📊 트렌드 차트</h3>
                <div class="chart-wrapper">
                    <canvas id="trendChart" width="400" height="300"></canvas>
                </div>
            </div>
        </div>

        <!-- 기간 비교 탭 -->
        <div id="기간비교" class="tab-content">
            <div class="card">
                <h3>📊 기간별 성과 비교</h3>
                <div class="grid grid-2">
                    <div class="comparison-period">
                        <h4>📅 현재 기간</h4>
                        <div class="input-group">
                            <label for="currentStartDate">시작 날짜:</label>
                            <input type="date" id="currentStartDate" value="2024-08-01">
                        </div>
                        <div class="input-group">
                            <label for="currentEndDate">종료 날짜:</label>
                            <input type="date" id="currentEndDate" value="2024-08-21">
                        </div>
                    </div>
                    <div class="comparison-period">
                        <h4>📅 비교 기간</h4>
                        <div class="input-group">
                            <label for="comparisonStartDate">시작 날짜:</label>
                            <input type="date" id="comparisonStartDate" value="2024-07-01">
                        </div>
                        <div class="input-group">
                            <label for="comparisonEndDate">종료 날짜:</label>
                            <input type="date" id="comparisonEndDate" value="2024-07-21">
                        </div>
                    </div>
                </div>
                <div class="comparison-controls">
                    <button class="btn btn-primary" onclick="performPeriodComparison()">기간 비교 분석</button>
                    <button class="btn btn-warning" onclick="exportComparisonReport()">비교 보고서 내보내기</button>
                </div>
            </div>

            <div id="periodComparisonResults" class="comparison-results hidden">
                <div class="grid grid-4">
                    <div class="comparison-card">
                        <h4>📈 노출수 변화</h4>
                        <div class="comparison-value" id="impressionChange">-</div>
                        <div class="comparison-detail" id="impressionDetail">-</div>
                    </div>
                    <div class="comparison-card">
                        <h4>👆 클릭수 변화</h4>
                        <div class="comparison-value" id="clickChange">-</div>
                        <div class="comparison-detail" id="clickDetail">-</div>
                    </div>
                    <div class="comparison-card">
                        <h4>📊 CTR 변화</h4>
                        <div class="comparison-value" id="ctrChange">-</div>
                        <div class="comparison-detail" id="ctrDetail">-</div>
                    </div>
                    <div class="comparison-card">
                        <h4>💰 비용 변화</h4>
                        <div class="comparison-value" id="costChange">-</div>
                        <div class="comparison-detail" id="costDetail">-</div>
                    </div>
                </div>

                <div class="chart-container">
                    <h3>📈 기간별 비교 차트</h3>
                    <div class="chart-wrapper">
                        <canvas id="periodComparisonChart" width="400" height="300"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- AI 인사이트 탭 -->
        <div id="AI인사이트" class="tab-content">
            <div class="card">
                <h3>🤖 AI 인사이트 생성</h3>
                <div class="ai-controls">
                    <button class="btn btn-primary" onclick="generateAIInsights()">인사이트 생성</button>
                    <button class="btn btn-success" onclick="refreshAIAnalysis()">분석 새로고침</button>
                    <div class="progress-bar hidden" id="aiProgress">
                        <div class="progress-fill" style="width: 0%"></div>
                    </div>
                </div>
            </div>

            <div id="insightsContainer">
                <div class="grid grid-2">
                    <div class="insight-card">
                        <h4>🎯 최고 성과 키워드</h4>
                        <div id="bestPerformingInsight">
                            <p>AI 인사이트를 생성하면 결과가 표시됩니다.</p>
                        </div>
                    </div>
                    <div class="insight-card">
                        <h4>📱 주요 디바이스 트렌드</h4>
                        <div id="deviceTrendInsight">
                            <p>AI 인사이트를 생성하면 결과가 표시됩니다.</p>
                        </div>
                    </div>
                    <div class="insight-card">
                        <h4>⚠️ 개선이 필요한 영역</h4>
                        <div id="improvementInsight">
                            <p>AI 인사이트를 생성하면 결과가 표시됩니다.</p>
                        </div>
                    </div>
                    <div class="insight-card">
                        <h4>💡 최적화 제안</h4>
                        <div id="optimizationInsight">
                            <p>AI 인사이트를 생성하면 결과가 표시됩니다.</p>
                        </div>
                    </div>
                </div>

                <div class="insight-card">
                    <h4>📊 종합 분석 보고서</h4>
                    <div id="comprehensiveInsight">
                        <p>AI 인사이트를 생성하면 상세 보고서가 표시됩니다.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- 피벗 탭 -->
        <div id="피벗" class="tab-content">
            <div class="card">
                <h3>📋 피벗 테이블 설정</h3>
                <div class="grid grid-3">
                    <div class="input-group">
                        <label for="pivotRows">행 (Rows):</label>
                        <select id="pivotRows" multiple>
                            <option value="campaign_name">캠페인명</option>
                            <option value="keyword_name">키워드명</option>
                            <option value="device_type">디바이스</option>
                            <option value="media_product">매체</option>
                            <option value="stat_date">날짜</option>
                        </select>
                    </div>
                    <div class="input-group">
                        <label for="pivotCols">열 (Columns):</label>
                        <select id="pivotCols" multiple>
                            <option value="device_type">디바이스</option>
                            <option value="media_product">매체</option>
                            <option value="stat_date">날짜</option>
                            <option value="campaign_name">캠페인명</option>
                        </select>
                    </div>
                    <div class="input-group">
                        <label for="pivotValues">값 (Values):</label>
                        <select id="pivotValues" multiple>
                            <option value="impression" selected>노출수</option>
                            <option value="click" selected>클릭수</option>
                            <option value="cost">비용</option>
                            <option value="rank">순위</option>
                        </select>
                    </div>
                </div>
                <div class="pivot-controls">
                    <button class="btn btn-primary" onclick="generatePivotTable()">피벗 테이블 생성</button>
                    <button class="btn btn-success" onclick="exportPivotTable()">피벗 데이터 내보내기</button>
                    <button class="btn btn-warning" onclick="resetPivotSettings()">설정 초기화</button>
                </div>
            </div>

            <div id="pivotTableContainer" class="hidden">
                <div class="card">
                    <div class="table-header">
                        <h3>📊 피벗 테이블 결과</h3>
                        <div class="export-buttons">
                            <button class="btn btn-success" onclick="exportPivotToExcel()">📁 Excel 내보내기</button>
                            <button class="btn btn-warning" onclick="exportPivotToCSV()">📄 CSV 내보내기</button>
                        </div>
                    </div>
                    <div class="table-wrapper">
                        <div id="pivotTable"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 원본 데이터 탭 -->
        <div id="원본데이터" class="tab-content">
            <div class="card">
                <div class="filters">
                    <div class="filter-group">
                        <label>검색:</label>
                        <input type="search" id="searchInput" placeholder="키워드 검색..." onkeyup="filterTable()">
                    </div>
                    <div class="filter-group">
                        <label>디바이스:</label>
                        <select id="deviceFilter" onchange="applyFilters()">
                            <option value="">전체 디바이스</option>
                            <option value="Mobile">Mobile</option>
                            <option value="PC">PC</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>캠페인:</label>
                        <select id="campaignFilter" onchange="applyFilters()">
                            <option value="">전체 캠페인</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>날짜 범위:</label>
                        <input type="date" id="filterStartDate" onchange="applyFilters()">
                        <input type="date" id="filterEndDate" onchange="applyFilters()">
                    </div>
                </div>
                <div class="data-controls">
                    <button class="btn btn-primary" onclick="loadRawData()">데이터 새로고침</button>
                    <button class="btn btn-success" onclick="exportToExcel()">📁 Excel 내보내기</button>
                    <button class="btn btn-warning" onclick="exportToCSV()">📄 CSV 내보내기</button>
                </div>
            </div>

            <div class="table-container">
                <div class="table-wrapper">
                    <div id="rawDataTable">
                        <p>원본 데이터를 로드하려면 '데이터 새로고침' 버튼을 클릭하세요.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // 서버에서 전달받은 데이터 (기존 방식 유지)
        const serverData = {
            dailyStats: <?php echo json_encode($dailyStats['rows'] ?? []); ?>,
            keywordAnalysis: <?php echo json_encode($keywordAnalysis['rows'] ?? []); ?>,
            deviceAnalysis: <?php echo json_encode($deviceAnalysis['rows'] ?? []); ?>,
            campaignAnalysis: <?php echo json_encode($campaignAnalysis['rows'] ?? []); ?>
        };

        console.log('서버 데이터:', serverData);
    </script>
    <script src="assets/script.js"></script>
</body>
</html>