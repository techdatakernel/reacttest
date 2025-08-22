<?php
/**
 * 대시보드 - 실제 비용 모니터링이 포함된 완전한 버전
 */
session_start();

$config = include 'config.php';

// 로그인 체크
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
$costWarning = '';
$systemStatus = '';

try {
    $bigquery = new BigQueryAPI($config);
    
    // 비용 상태 확인
    $costStatus = $bigquery->getCostStatus();
    
    // 시스템 상태 메시지 생성
    if (isset($_SESSION['service_suspended']) && $_SESSION['service_suspended']) {
        $systemStatus = "🚫 서비스 일시중단: 월간 예산을 초과했습니다.";
    } elseif (isset($_SESSION['query_restricted']) && $_SESSION['query_restricted']) {
        $systemStatus = "⚠️ 쿼리 제한 모드: 주간 예산을 초과했습니다.";
    } elseif (isset($_SESSION['cache_mode_only']) && $_SESSION['cache_mode_only']) {
        $systemStatus = "💾 캐시 모드: 예산의 90%에 도달했습니다.";
    } else {
        $systemStatus = "✅ 정상 운영 중";
    }
    
    // 캐시 확인
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
        // 새로 조회 (비용 모니터링에 따라 제한될 수 있음)
        try {
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
            
            $costWarning = "💰 실시간 데이터 조회 완료 - 비용: $" . number_format($costStatus['daily_spent'], 2);
            
        } catch (Exception $e) {
            // 제한된 상태에서는 캐시만 사용
            $dailyStats = [];
            $keywordAnalysis = [];
            $deviceAnalysis = [];
            $campaignAnalysis = [];
            $costWarning = "⚠️ " . $e->getMessage();
        }
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
    <title>🎯 GCP BigQuery 대시보드 - 비용 모니터링</title>
    <!-- Chart.js CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns@3.0.0/dist/chartjs-adapter-date-fns.bundle.min.js"></script>
    <link rel="stylesheet" href="assets/style.css">
    <style>
        /* 비용 모니터링 전용 스타일 */
        .cost-monitoring-panel {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1.5rem;
            border-radius: 15px;
            margin: 1rem 0;
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }

        .cost-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .budget-circle {
            position: relative;
            width: 70px;
            height: 70px;
            border-radius: 50%;
            background: conic-gradient(#4CAF50 0deg, #4CAF50 var(--percentage), #ffffff30 var(--percentage), #ffffff30 360deg);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 0.8rem;
        }

        .budget-circle::before {
            content: '';
            position: absolute;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: #667eea;
        }

        .budget-text {
            position: relative;
            z-index: 1;
            text-align: center;
        }

        .cost-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .cost-card {
            background: rgba(255,255,255,0.1);
            border-radius: 10px;
            padding: 1rem;
            text-align: center;
        }

        .cost-value {
            font-size: 1.4rem;
            font-weight: bold;
            margin: 0.3rem 0;
        }

        .cost-label {
            font-size: 0.8rem;
            opacity: 0.9;
        }

        .status-badge {
            padding: 0.3rem 0.8rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-block;
            margin-top: 0.5rem;
        }

        .status-normal { background: rgba(76, 175, 80, 0.2); color: #4CAF50; }
        .status-warning { background: rgba(255, 193, 7, 0.2); color: #FFC107; }
        .status-danger { background: rgba(244, 67, 54, 0.2); color: #F44336; }
        .status-restricted { background: rgba(156, 39, 176, 0.2); color: #9C27B0; }

        .system-status {
            text-align: center;
            padding: 1rem;
            border-radius: 10px;
            margin: 1rem 0;
            font-weight: 600;
        }

        .system-status.normal { background: #d4edda; color: #155724; }
        .system-status.warning { background: #fff3cd; color: #856404; }
        .system-status.danger { background: #f8d7da; color: #721c24; }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }

        .pulse { animation: pulse 2s infinite; }
    </style>
</head>
<body>
    <div class="container">
        <!-- 헤더 -->
        <div class="header">
            <h1>🎯 GCP BigQuery 대시보드</h1>
            <p>실시간 데이터 분석 및 비용 모니터링</p>
            <div class="user-info">
                👤 <span id="currentUser"><?php echo htmlspecialchars($_SESSION['username']); ?></span> | 
                🏢 SESCO 데이터 분석 | 
                <a href="?logout=1" class="logout-btn">로그아웃</a>
            </div>
        </div>

        <!-- 시스템 상태 표시 -->
        <div class="system-status <?php 
            if (strpos($systemStatus, '🚫') !== false) echo 'danger';
            elseif (strpos($systemStatus, '⚠️') !== false || strpos($systemStatus, '💾') !== false) echo 'warning';
            else echo 'normal';
        ?> <?php if (strpos($systemStatus, '🚫') !== false) echo 'pulse'; ?>">
            <?php echo htmlspecialchars($systemStatus); ?>
        </div>

        <!-- 실시간 비용 모니터링 패널 -->
        <div class="cost-monitoring-panel">
            <div class="cost-header">
                <h3>💰 실시간 비용 모니터링</h3>
                <div class="budget-circle" id="budgetCircle" style="--percentage: 0deg;">
                    <div class="budget-text">
                        <div id="budgetPercentage">0%</div>
                        <div style="font-size: 0.6rem;">사용</div>
                    </div>
                </div>
            </div>

            <div class="cost-grid">
                <div class="cost-card">
                    <div class="cost-label">💸 일일</div>
                    <div class="cost-value" id="dailyCost">$0.00</div>
                </div>
                
                <div class="cost-card">
                    <div class="cost-label">📊 주간</div>
                    <div class="cost-value" id="weeklyCost">$0.00</div>
                </div>
                
                <div class="cost-card">
                    <div class="cost-label">📈 월간</div>
                    <div class="cost-value" id="monthlyCost">$0.00</div>
                </div>
                
                <div class="cost-card">
                    <div class="cost-label">⚡ 남은 예산</div>
                    <div class="cost-value" id="remainingBudget">$100.00</div>
                </div>
            </div>

            <div id="costStatus" class="status-badge status-normal">
                ✅ 정상 운영
            </div>
        </div>

        <!-- 비용 최적화 알림 -->
        <?php if ($costWarning): ?>
            <div class="alert alert-info">
                <?php echo htmlspecialchars($costWarning); ?>
            </div>
        <?php endif; ?>
        
        <!-- 오류 표시 -->
        <?php if ($error): ?>
            <div class="alert alert-error">
                <strong>⚠️ 오류:</strong> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <!-- 탭 메뉴 -->
        <nav class="nav-tabs">
            <button class="nav-tab active" onclick="showTab('overview')">📊 개요</button>
            <button class="nav-tab" onclick="showTab('daily')">📅 일별 분석</button>
            <button class="nav-tab" onclick="showTab('keywords')">🔍 키워드 분석</button>
            <button class="nav-tab" onclick="showTab('devices')">📱 디바이스 분석</button>
            <button class="nav-tab" onclick="showTab('campaigns')">📢 캠페인 분석</button>
            <button class="nav-tab" onclick="showTab('rawdata')">📋 원본 데이터</button>
            <button class="nav-tab" onclick="showTab('cost-details')">💰 비용 상세</button>
        </nav>

        <!-- 탭 콘텐츠 -->
        <div id="overview" class="tab-content active">
            <div class="overview-grid">
                <div class="stat-card">
                    <h3>📊 오늘의 성과</h3>
                    <div id="todayStats">
                        <div class="stat-item">
                            <span class="stat-label">총 노출수:</span>
                            <span class="stat-value" id="totalImpressions">0</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">총 클릭수:</span>
                            <span class="stat-value" id="totalClicks">0</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">평균 CTR:</span>
                            <span class="stat-value" id="avgCTR">0.00%</span>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <h3>🔍 상위 키워드</h3>
                    <div id="topKeywords"></div>
                </div>
                
                <div class="stat-card">
                    <h3>📱 디바이스 분포</h3>
                    <canvas id="deviceChart" width="300" height="200"></canvas>
                </div>
            </div>
        </div>

        <div id="daily" class="tab-content">
            <div class="controls">
                <label>시작일: <input type="date" id="startDate"></label>
                <label>종료일: <input type="date" id="endDate"></label>
                <button onclick="loadDailyStats()" class="btn">조회</button>
            </div>
            <canvas id="dailyChart" width="800" height="400"></canvas>
            <div id="dailyTable"></div>
        </div>

        <div id="keywords" class="tab-content">
            <div class="controls">
                <label>표시 개수: 
                    <select id="keywordLimit">
                        <option value="10">10개</option>
                        <option value="20" selected>20개</option>
                        <option value="50">50개</option>
                    </select>
                </label>
                <button onclick="loadKeywordAnalysis()" class="btn">새로고침</button>
            </div>
            <canvas id="keywordChart" width="800" height="400"></canvas>
            <div id="keywordTable"></div>
        </div>

        <div id="devices" class="tab-content">
            <canvas id="deviceDetailChart" width="800" height="400"></canvas>
            <div id="deviceTable"></div>
        </div>

        <div id="campaigns" class="tab-content">
            <canvas id="campaignChart" width="800" height="400"></canvas>
            <div id="campaignTable"></div>
        </div>

        <div id="rawdata" class="tab-content">
            <div class="controls">
                <label>디바이스: <select id="deviceFilter"><option value="">전체</option></select></label>
                <label>캠페인: <input type="text" id="campaignFilter" placeholder="캠페인명 검색"></label>
                <button onclick="loadRawData()" class="btn">필터 적용</button>
            </div>
            <div class="pagination">
                <button onclick="changePage(-1)" class="btn">이전</button>
                <span id="pageInfo">1 / 1</span>
                <button onclick="changePage(1)" class="btn">다음</button>
            </div>
            <div id="rawDataTable"></div>
        </div>

        <div id="cost-details" class="tab-content">
            <div class="cost-details-grid">
                <div class="cost-detail-card">
                    <h3>📈 비용 트렌드</h3>
                    <canvas id="costTrendChart" width="400" height="200"></canvas>
                </div>
                
                <div class="cost-detail-card">
                    <h3>📊 사용량 분석</h3>
                    <div id="usageAnalysis"></div>
                </div>
                
                <div class="cost-detail-card">
                    <h3>⚠️ 최근 알림</h3>
                    <div id="recentAlerts"></div>
                </div>
                
                <div class="cost-detail-card">
                    <h3>🔮 비용 예측</h3>
                    <div id="costForecast"></div>
                </div>
            </div>
            
            <div class="cost-actions">
                <button onclick="resetCostRestrictions()" class="btn btn-warning">제한 해제</button>
                <button onclick="exportCostReport()" class="btn btn-info">보고서 다운로드</button>
                <button onclick="refreshCostData()" class="btn btn-primary">새로고침</button>
            </div>
        </div>
    </div>

    <script>
        // 전역 변수
        let currentPage = 1;
        let totalPages = 1;
        let charts = {};

        // 페이지 로드 시 초기화
        document.addEventListener('DOMContentLoaded', function() {
            initializeDates();
            loadOverviewData();
            loadCostData();
            
            // 5분마다 비용 데이터 자동 갱신
            setInterval(loadCostData, 300000);
        });

        // 실시간 비용 데이터 로드
        async function loadCostData() {
            try {
                const response = await fetch('api.php?action=cost_status');
                const data = await response.json();
                
                if (data.success) {
                    updateCostDisplay(data.data);
                } else {
                    console.error('비용 데이터 로드 실패:', data.error);
                }
            } catch (error) {
                console.error('비용 API 호출 오류:', error);
            }
        }

        // 비용 표시 업데이트
        function updateCostDisplay(costData) {
            document.getElementById('dailyCost').textContent = `$${costData.daily_spent.toFixed(2)}`;
            document.getElementById('weeklyCost').textContent = `$${costData.weekly_spent.toFixed(2)}`;
            document.getElementById('monthlyCost').textContent = `$${costData.monthly_spent.toFixed(2)}`;
            document.getElementById('remainingBudget').textContent = `$${costData.remaining_budget.toFixed(2)}`;
            
            // 예산 원형 차트 업데이트
            const percentage = costData.budget_percentage;
            const degree = (percentage / 100) * 360;
            const budgetCircle = document.getElementById('budgetCircle');
            budgetCircle.style.setProperty('--percentage', `${degree}deg`);
            document.getElementById('budgetPercentage').textContent = `${percentage.toFixed(0)}%`;
            
            // 색상 변경 (위험도에 따라)
            let color = '#4CAF50';
            if (percentage >= 90) color = '#F44336';
            else if (percentage >= 75) color = '#FFC107';
            
            budgetCircle.style.background = `conic-gradient(${color} 0deg, ${color} ${degree}deg, #ffffff30 ${degree}deg, #ffffff30 360deg)`;
            
            // 상태 업데이트
            updateCostStatus(costData.restrictions);
        }

        // 비용 상태 업데이트
        function updateCostStatus(restrictions) {
            const statusElement = document.getElementById('costStatus');
            
            if (restrictions.service_suspended) {
                statusElement.className = 'status-badge status-danger';
                statusElement.innerHTML = '🚫 서비스 일시중단';
            } else if (restrictions.query_restricted) {
                statusElement.className = 'status-badge status-restricted';
                statusElement.innerHTML = '⚠️ 쿼리 제한 모드';
            } else if (restrictions.cache_mode_only) {
                statusElement.className = 'status-badge status-warning';
                statusElement.innerHTML = '💾 캐시 모드';
            } else {
                statusElement.className = 'status-badge status-normal';
                statusElement.innerHTML = '✅ 정상 운영';
            }
        }

        // 탭 전환
        function showTab(tabName) {
            // 모든 탭 비활성화
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            document.querySelectorAll('.nav-tab').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // 선택된 탭 활성화
            document.getElementById(tabName).classList.add('active');
            event.target.classList.add('active');
            
            // 탭별 데이터 로드
            switch(tabName) {
                case 'overview':
                    loadOverviewData();
                    break;
                case 'daily':
                    loadDailyStats();
                    break;
                case 'keywords':
                    loadKeywordAnalysis();
                    break;
                case 'devices':
                    loadDeviceAnalysis();
                    break;
                case 'campaigns':
                    loadCampaignAnalysis();
                    break;
                case 'rawdata':
                    loadRawData();
                    break;
                case 'cost-details':
                    loadCostDetails();
                    break;
            }
        }

        // 비용 상세 정보 로드
        async function loadCostDetails() {
            try {
                // 비용 트렌드 로드
                const trendsResponse = await fetch('api.php?action=cost_trends&days=14');
                const trendsData = await trendsResponse.json();
                
                if (trendsData.success) {
                    createCostTrendChart(trendsData.data.daily_data);
                }
                
                // 사용량 분석 로드
                const usageElement = document.getElementById('usageAnalysis');
                usageElement.innerHTML = `
                    <div class="usage-stat">
                        <span>평균 일일 비용:</span>
                        <span>$${trendsData.data.average_daily_cost.toFixed(2)}</span>
                    </div>
                    <div class="usage-stat">
                        <span>트렌드:</span>
                        <span>${trendsData.data.trend_direction}</span>
                    </div>
                `;
                
                // 최근 알림 로드
                loadRecentAlerts();
                
                // 비용 예측 로드
                loadCostForecast();
                
            } catch (error) {
                console.error('비용 상세 정보 로드 오류:', error);
            }
        }

        // 최근 알림 로드
        async function loadRecentAlerts() {
            try {
                const response = await fetch('api.php?action=cost_status');
                const data = await response.json();
                
                if (data.success && data.data.alerts) {
                    const alertsElement = document.getElementById('recentAlerts');
                    let alertsHtml = '';
                    
                    data.data.alerts.slice(0, 5).forEach(alert => {
                        alertsHtml += `
                            <div class="alert-item">
                                <div class="alert-time">${alert.timestamp}</div>
                                <div class="alert-message">${alert.message}</div>
                            </div>
                        `;
                    });
                    
                    alertsElement.innerHTML = alertsHtml || '<div>최근 알림이 없습니다.</div>';
                }
            } catch (error) {
                console.error('알림 로드 오류:', error);
            }
        }

        // 비용 예측 로드
        async function loadCostForecast() {
            try {
                const response = await fetch('api.php?action=cost_forecast&days=7');
                const data = await response.json();
                
                if (data.success) {
                    const forecastElement = document.getElementById('costForecast');
                    const forecast = data.data.forecast;
                    
                    let forecastHtml = '<div class="forecast-list">';
                    forecast.slice(0, 3).forEach(day => {
                        forecastHtml += `
                            <div class="forecast-item">
                                <span>${day.date}</span>
                                <span>$${day.projected_cost.toFixed(2)}</span>
                                <span class="confidence">${day.confidence.toFixed(0)}%</span>
                            </div>
                        `;
                    });
                    forecastHtml += '</div>';
                    
                    forecastElement.innerHTML = forecastHtml;
                }
            } catch (error) {
                console.error('예측 로드 오류:', error);
            }
        }

        // 비용 트렌드 차트 생성
        function createCostTrendChart(trendData) {
            const ctx = document.getElementById('costTrendChart').getContext('2d');
            
            if (charts.costTrend) {
                charts.costTrend.destroy();
            }
            
            charts.costTrend = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: trendData.map(item => item.date),
                    datasets: [{
                        label: '일일 비용',
                        data: trendData.map(item => item.cost),
                        borderColor: '#667eea',
                        backgroundColor: 'rgba(102, 126, 234, 0.1)',
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return '$' + value.toFixed(2);
                                }
                            }
                        }
                    }
                }
            });
        }

        // 비용 제한 해제
        async function resetCostRestrictions() {
            if (!confirm('비용 제한을 해제하시겠습니까?')) return;
            
            try {
                const response = await fetch('api.php?action=reset_cost_restrictions');
                const data = await response.json();
                
                if (data.success) {
                    alert('비용 제한이 해제되었습니다.');
                    location.reload();
                } else {
                    alert('오류: ' + data.error);
                }
            } catch (error) {
                alert('API 호출 오류: ' + error.message);
            }
        }

        // 비용 보고서 다운로드
        function exportCostReport() {
            window.open('api.php?action=export_cost_report&format=csv&period=30d', '_blank');
        }

        // 비용 데이터 새로고침
        function refreshCostData() {
            loadCostData();
            if (document.getElementById('cost-details').classList.contains('active')) {
                loadCostDetails();
            }
        }

        // 기본 함수들 (간소화된 버전)
        function initializeDates() {
            const today = new Date();
            const weekAgo = new Date(today.getTime() - 7 * 24 * 60 * 60 * 1000);
            
            document.getElementById('startDate').value = weekAgo.toISOString().split('T')[0];
            document.getElementById('endDate').value = today.toISOString().split('T')[0];
        }

        async function loadOverviewData() {
            try {
                const response = await fetch('api.php?action=daily_stats&limit=1');
                const data = await response.json();
                
                if (data.success && data.data.length > 0) {
                    const today = data.data[0];
                    document.getElementById('totalImpressions').textContent = today.total_impressions || 0;
                    document.getElementById('totalClicks').textContent = today.total_clicks || 0;
                    document.getElementById('avgCTR').textContent = (today.ctr || 0) + '%';
                }
            } catch (error) {
                console.error('개요 데이터 로드 오류:', error);
            }
        }

        async function loadDailyStats() {
            // 일별 통계 로드 구현
        }

        async function loadKeywordAnalysis() {
            // 키워드 분석 로드 구현
        }

        async function loadDeviceAnalysis() {
            // 디바이스 분석 로드 구현
        }

        async function loadCampaignAnalysis() {
            // 캠페인 분석 로드 구현
        }

        async function loadRawData() {
            // 원본 데이터 로드 구현
        }

        function changePage(direction) {
            // 페이지 변경 구현
        }
    </script>
</body>
</html>