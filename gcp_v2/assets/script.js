/**
 * GCP BigQuery 대시보드 완전한 JavaScript
 * 차트, API 호출, 비용 모니터링 등 모든 기능 포함
 */

// === 전역 변수 ===
let charts = {};
let currentPage = 1;
let totalPages = 1;
let costMonitoringInterval;
let lastCostUpdate = 0;

// === 초기화 ===
document.addEventListener('DOMContentLoaded', function() {
    console.log('🎯 GCP BigQuery 대시보드 초기화 시작');
    
    initializeDates();
    loadOverviewData();
    loadCostData();
    setupEventListeners();
    startCostMonitoring();
    
    console.log('✅ 대시보드 초기화 완료');
});

// === 이벤트 리스너 설정 ===
function setupEventListeners() {
    // 키보드 단축키
    document.addEventListener('keydown', handleKeyboardShortcuts);
    
    // 윈도우 리사이즈
    window.addEventListener('resize', debounce(handleWindowResize, 250));
    
    // 페이지 가시성 변경
    document.addEventListener('visibilitychange', handleVisibilityChange);
    
    // 온라인/오프라인 상태
    window.addEventListener('online', handleOnline);
    window.addEventListener('offline', handleOffline);
}

// === 비용 모니터링 시스템 ===
function startCostMonitoring() {
    // 5분마다 비용 데이터 자동 갱신
    costMonitoringInterval = setInterval(loadCostData, 300000);
    
    // 페이지 포커스시 즉시 갱신
    window.addEventListener('focus', function() {
        if (Date.now() - lastCostUpdate > 60000) { // 1분 이상 경과시
            loadCostData();
        }
    });
}

async function loadCostData() {
    try {
        showLoading('비용 데이터 로드 중...');
        
        const response = await fetchWithRetry('api.php?action=cost_status');
        const data = await response.json();
        
        if (data.success) {
            updateCostDisplay(data.data);
            lastCostUpdate = Date.now();
            console.log('💰 비용 데이터 업데이트 완료');
        } else {
            console.error('비용 데이터 로드 실패:', data.error);
            showNotification('비용 데이터 로드 실패', 'error');
        }
    } catch (error) {
        console.error('비용 API 호출 오류:', error);
        showNotification('네트워크 오류가 발생했습니다', 'error');
    } finally {
        hideLoading();
    }
}

function updateCostDisplay(costData) {
    // 기본 비용 정보 업데이트
    updateElement('dailyCost', `$${costData.daily_spent.toFixed(2)}`);
    updateElement('weeklyCost', `$${costData.weekly_spent.toFixed(2)}`);
    updateElement('monthlyCost', `$${costData.monthly_spent.toFixed(2)}`);
    updateElement('remainingBudget', `$${costData.remaining_budget.toFixed(2)}`);
    
    // 예산 원형 차트 업데이트
    updateBudgetCircle(costData.budget_percentage);
    
    // 상태 업데이트
    updateCostStatus(costData.restrictions);
    
    // 경고 확인
    checkCostWarnings(costData);
}

function updateBudgetCircle(percentage) {
    const degree = (percentage / 100) * 360;
    const budgetCircle = document.getElementById('budgetCircle');
    
    if (budgetCircle) {
        budgetCircle.style.setProperty('--percentage', `${degree}deg`);
        updateElement('budgetPercentage', `${percentage.toFixed(0)}%`);
        
        // 위험도에 따른 색상 변경
        let color = '#4CAF50'; // 녹색
        if (percentage >= 90) {
            color = '#F44336'; // 빨간색
            budgetCircle.classList.add('pulse');
        } else if (percentage >= 75) {
            color = '#FFC107'; // 노란색
            budgetCircle.classList.remove('pulse');
        } else {
            budgetCircle.classList.remove('pulse');
        }
        
        budgetCircle.style.background = 
            `conic-gradient(${color} 0deg, ${color} ${degree}deg, rgba(255,255,255,0.3) ${degree}deg, rgba(255,255,255,0.3) 360deg)`;
    }
}

function updateCostStatus(restrictions) {
    const statusElement = document.getElementById('costStatus');
    
    if (!statusElement) return;
    
    if (restrictions.service_suspended) {
        statusElement.className = 'status-badge status-danger pulse';
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

function checkCostWarnings(costData) {
    // 예산 90% 도달시 알림
    if (costData.budget_percentage >= 90 && costData.budget_percentage < 95) {
        showNotification('⚠️ 월간 예산의 90%에 도달했습니다!', 'warning', 10000);
    }
    
    // 예산 95% 도달시 긴급 알림
    if (costData.budget_percentage >= 95) {
        showNotification('🚨 월간 예산의 95%에 도달했습니다! 즉시 확인이 필요합니다.', 'error', 15000);
    }
    
    // 일일 한도 초과시
    if (costData.daily_spent > 5.00) {
        showNotification('📊 일일 비용 한도를 초과했습니다.', 'warning', 8000);
    }
}

// === 탭 관리 ===
function showTab(tabName) {
    // 모든 탭 및 버튼 비활성화
    document.querySelectorAll('.tab-content').forEach(tab => {
        tab.classList.remove('active');
    });
    document.querySelectorAll('.nav-tab').forEach(btn => {
        btn.classList.remove('active');
    });
    
    // 선택된 탭 활성화
    const targetTab = document.getElementById(tabName);
    const activeButton = event?.target;
    
    if (targetTab) {
        targetTab.classList.add('active');
        targetTab.classList.add('fade-in');
    }
    
    if (activeButton) {
        activeButton.classList.add('active');
    }
    
    // 탭별 데이터 로드
    loadTabData(tabName);
    
    // 브라우저 히스토리 업데이트
    if (history.pushState) {
        history.pushState({tab: tabName}, '', `#${tabName}`);
    }
}

async function loadTabData(tabName) {
    try {
        showLoading(`${getTabDisplayName(tabName)} 데이터 로드 중...`);
        
        switch(tabName) {
            case 'overview':
                await loadOverviewData();
                break;
            case 'daily':
                await loadDailyStats();
                break;
            case 'keywords':
                await loadKeywordAnalysis();
                break;
            case 'devices':
                await loadDeviceAnalysis();
                break;
            case 'campaigns':
                await loadCampaignAnalysis();
                break;
            case 'rawdata':
                await loadRawData();
                break;
            case 'cost-details':
                await loadCostDetails();
                break;
        }
    } catch (error) {
        console.error(`${tabName} 탭 로드 오류:`, error);
        showNotification('데이터 로드 중 오류가 발생했습니다', 'error');
    } finally {
        hideLoading();
    }
}

function getTabDisplayName(tabName) {
    const names = {
        'overview': '개요',
        'daily': '일별 분석',
        'keywords': '키워드 분석',
        'devices': '디바이스 분석',
        'campaigns': '캠페인 분석',
        'rawdata': '원본 데이터',
        'cost-details': '비용 상세'
    };
    return names[tabName] || tabName;
}

// === 데이터 로드 함수들 ===
async function loadOverviewData() {
    try {
        const [dailyResponse, keywordResponse, deviceResponse] = await Promise.all([
            fetchWithRetry('api.php?action=daily_stats&limit=1'),
            fetchWithRetry('api.php?action=keyword_analysis&limit=5'),
            fetchWithRetry('api.php?action=device_analysis')
        ]);
        
        const [dailyData, keywordData, deviceData] = await Promise.all([
            dailyResponse.json(),
            keywordResponse.json(),
            deviceResponse.json()
        ]);
        
        // 오늘 통계 업데이트
        if (dailyData.success && dailyData.data.length > 0) {
            const today = dailyData.data[0];
            updateElement('totalImpressions', formatNumber(today.total_impressions || 0));
            updateElement('totalClicks', formatNumber(today.total_clicks || 0));
            updateElement('avgCTR', `${(today.ctr || 0).toFixed(2)}%`);
        }
        
        // 상위 키워드 표시
        if (keywordData.success) {
            displayTopKeywords(keywordData.data);
        }
        
        // 디바이스 차트 생성
        if (deviceData.success) {
            createDeviceChart(deviceData.data);
        }
        
    } catch (error) {
        console.error('개요 데이터 로드 오류:', error);
        throw error;
    }
}

function displayTopKeywords(keywords) {
    const container = document.getElementById('topKeywords');
    if (!container) return;
    
    let html = '<div class="keyword-list">';
    keywords.slice(0, 5).forEach((keyword, index) => {
        html += `
            <div class="keyword-item">
                <span class="keyword-rank">${index + 1}</span>
                <span class="keyword-name">${escapeHtml(keyword.keyword_name)}</span>
                <span class="keyword-impressions">${formatNumber(keyword.total_impressions)}</span>
            </div>
        `;
    });
    html += '</div>';
    
    container.innerHTML = html;
}

async function loadDailyStats() {
    const startDate = document.getElementById('startDate')?.value;
    const endDate = document.getElementById('endDate')?.value;
    
    let url = 'api.php?action=daily_stats&limit=30';
    if (startDate) url += `&start_date=${startDate}`;
    if (endDate) url += `&end_date=${endDate}`;
    
    const response = await fetchWithRetry(url);
    const data = await response.json();
    
    if (data.success) {
        createDailyChart(data.data);
        createDailyTable(data.data);
    } else {
        throw new Error(data.error);
    }
}

async function loadKeywordAnalysis() {
    const limit = document.getElementById('keywordLimit')?.value || 20;
    
    const response = await fetchWithRetry(`api.php?action=keyword_analysis&limit=${limit}`);
    const data = await response.json();
    
    if (data.success) {
        createKeywordChart(data.data);
        createKeywordTable(data.data);
    } else {
        throw new Error(data.error);
    }
}

async function loadDeviceAnalysis() {
    const response = await fetchWithRetry('api.php?action=device_analysis');
    const data = await response.json();
    
    if (data.success) {
        createDeviceDetailChart(data.data);
        createDeviceTable(data.data);
    } else {
        throw new Error(data.error);
    }
}

async function loadCampaignAnalysis() {
    const response = await fetchWithRetry('api.php?action=campaign_analysis&limit=20');
    const data = await response.json();
    
    if (data.success) {
        createCampaignChart(data.data);
        createCampaignTable(data.data);
    } else {
        throw new Error(data.error);
    }
}

async function loadRawData() {
    const deviceFilter = document.getElementById('deviceFilter')?.value || '';
    const campaignFilter = document.getElementById('campaignFilter')?.value || '';
    
    let url = `api.php?action=raw_data&limit=100&offset=${(currentPage - 1) * 100}`;
    if (deviceFilter) url += `&device_type=${encodeURIComponent(deviceFilter)}`;
    if (campaignFilter) url += `&campaign_name=${encodeURIComponent(campaignFilter)}`;
    
    const response = await fetchWithRetry(url);
    const data = await response.json();
    
    if (data.success) {
        createRawDataTable(data.data);
        updatePagination(data.metadata);
    } else {
        throw new Error(data.error);
    }
}

async function loadCostDetails() {
    try {
        const [trendsResponse, historyResponse, forecastResponse] = await Promise.all([
            fetchWithRetry('api.php?action=cost_trends&days=14'),
            fetchWithRetry('api.php?action=cost_history&period=7d&limit=20'),
            fetchWithRetry('api.php?action=cost_forecast&days=7')
        ]);
        
        const [trendsData, historyData, forecastData] = await Promise.all([
            trendsResponse.json(),
            historyResponse.json(),
            forecastResponse.json()
        ]);
        
        if (trendsData.success) {
            createCostTrendChart(trendsData.data.daily_data);
            displayUsageAnalysis(trendsData.data);
        }
        
        if (historyData.success) {
            displayRecentAlerts(historyData.data);
        }
        
        if (forecastData.success) {
            displayCostForecast(forecastData.data.forecast);
        }
        
    } catch (error) {
        console.error('비용 상세 로드 오류:', error);
        throw error;
    }
}

// === 차트 생성 함수들 ===
function createDailyChart(data) {
    const ctx = document.getElementById('dailyChart')?.getContext('2d');
    if (!ctx) return;
    
    destroyChart('daily');
    
    charts.daily = new Chart(ctx, {
        type: 'line',
        data: {
            labels: data.map(item => formatDate(item.report_date)),
            datasets: [
                {
                    label: '노출수',
                    data: data.map(item => item.total_impressions),
                    borderColor: '#667eea',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    tension: 0.4,
                    yAxisID: 'y'
                },
                {
                    label: '클릭수',
                    data: data.map(item => item.total_clicks),
                    borderColor: '#f093fb',
                    backgroundColor: 'rgba(240, 147, 251, 0.1)',
                    tension: 0.4,
                    yAxisID: 'y'
                },
                {
                    label: '비용 ($)',
                    data: data.map(item => item.total_cost),
                    borderColor: '#4CAF50',
                    backgroundColor: 'rgba(76, 175, 80, 0.1)',
                    tension: 0.4,
                    yAxisID: 'y1'
                }
            ]
        },
        options: {
            responsive: true,
            interaction: {
                mode: 'index',
                intersect: false,
            },
            scales: {
                x: {
                    display: true,
                    title: {
                        display: true,
                        text: '날짜'
                    }
                },
                y: {
                    type: 'linear',
                    display: true,
                    position: 'left',
                    title: {
                        display: true,
                        text: '노출수 / 클릭수'
                    }
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    title: {
                        display: true,
                        text: '비용 ($)'
                    },
                    grid: {
                        drawOnChartArea: false,
                    },
                }
            },
            plugins: {
                legend: {
                    position: 'top',
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            let label = context.dataset.label || '';
                            if (label) label += ': ';
                            
                            if (context.dataset.label === '비용 ($)') {
                                label += '$' + context.parsed.y.toFixed(2);
                            } else {
                                label += formatNumber(context.parsed.y);
                            }
                            
                            return label;
                        }
                    }
                }
            }
        }
    });
}

function createKeywordChart(data) {
    const ctx = document.getElementById('keywordChart')?.getContext('2d');
    if (!ctx) return;
    
    destroyChart('keyword');
    
    charts.keyword = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: data.slice(0, 10).map(item => truncateText(item.keyword_name, 15)),
            datasets: [{
                label: '노출수',
                data: data.slice(0, 10).map(item => item.total_impressions),
                backgroundColor: 'rgba(102, 126, 234, 0.8)',
                borderColor: '#667eea',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: '노출수'
                    }
                },
                x: {
                    title: {
                        display: true,
                        text: '키워드'
                    }
                }
            },
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        title: function(tooltipItems) {
                            const index = tooltipItems[0].dataIndex;
                            return data[index].keyword_name;
                        },
                        afterBody: function(tooltipItems) {
                            const index = tooltipItems[0].dataIndex;
                            const item = data[index];
                            return [
                                `클릭수: ${formatNumber(item.total_clicks)}`,
                                `CTR: ${item.ctr}%`,
                                `비용: $${item.total_cost.toFixed(2)}`
                            ];
                        }
                    }
                }
            }
        }
    });
}

function createDeviceChart(data) {
    const ctx = document.getElementById('deviceChart')?.getContext('2d');
    if (!ctx) return;
    
    destroyChart('device');
    
    const colors = ['#667eea', '#f093fb', '#4CAF50', '#FFC107', '#F44336'];
    
    charts.device = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: data.map(item => item.device_type),
            datasets: [{
                data: data.map(item => item.total_impressions),
                backgroundColor: colors.slice(0, data.length),
                borderWidth: 2,
                borderColor: '#fff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = ((context.parsed / total) * 100).toFixed(1);
                            return `${context.label}: ${formatNumber(context.parsed)} (${percentage}%)`;
                        }
                    }
                }
            }
        }
    });
}

function createDeviceDetailChart(data) {
    const ctx = document.getElementById('deviceDetailChart')?.getContext('2d');
    if (!ctx) return;
    
    destroyChart('deviceDetail');
    
    charts.deviceDetail = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: data.map(item => item.device_type),
            datasets: [
                {
                    label: '노출수',
                    data: data.map(item => item.total_impressions),
                    backgroundColor: 'rgba(102, 126, 234, 0.8)',
                    yAxisID: 'y'
                },
                {
                    label: '클릭수',
                    data: data.map(item => item.total_clicks),
                    backgroundColor: 'rgba(240, 147, 251, 0.8)',
                    yAxisID: 'y'
                },
                {
                    label: 'CTR (%)',
                    data: data.map(item => item.ctr),
                    type: 'line',
                    borderColor: '#4CAF50',
                    backgroundColor: 'rgba(76, 175, 80, 0.1)',
                    yAxisID: 'y1'
                }
            ]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    type: 'linear',
                    display: true,
                    position: 'left',
                    title: {
                        display: true,
                        text: '노출수 / 클릭수'
                    }
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    title: {
                        display: true,
                        text: 'CTR (%)'
                    },
                    grid: {
                        drawOnChartArea: false,
                    },
                }
            }
        }
    });
}

function createCampaignChart(data) {
    const ctx = document.getElementById('campaignChart')?.getContext('2d');
    if (!ctx) return;
    
    destroyChart('campaign');
    
    charts.campaign = new Chart(ctx, {
        type: 'horizontalBar',
        data: {
            labels: data.slice(0, 10).map(item => truncateText(item.campaign_name, 20)),
            datasets: [{
                label: '노출수',
                data: data.slice(0, 10).map(item => item.total_impressions),
                backgroundColor: 'rgba(102, 126, 234, 0.8)',
                borderColor: '#667eea',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            indexAxis: 'y',
            scales: {
                x: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: '노출수'
                    }
                }
            },
            plugins: {
                legend: {
                    display: false
                }
            }
        }
    });
}

function createCostTrendChart(trendData) {
    const ctx = document.getElementById('costTrendChart')?.getContext('2d');
    if (!ctx) return;
    
    destroyChart('costTrend');
    
    charts.costTrend = new Chart(ctx, {
        type: 'line',
        data: {
            labels: trendData.map(item => formatDate(item.date)),
            datasets: [{
                label: '일일 비용',
                data: trendData.map(item => item.cost),
                borderColor: '#667eea',
                backgroundColor: 'rgba(102, 126, 234, 0.1)',
                tension: 0.4,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: '비용 ($)'
                    },
                    ticks: {
                        callback: function(value) {
                            return '$' + value.toFixed(2);
                        }
                    }
                }
            },
            plugins: {
                legend: {
                    display: false
                }
            }
        }
    });
}

// === 테이블 생성 함수들 ===
function createDailyTable(data) {
    const container = document.getElementById('dailyTable');
    if (!container) return;
    
    let html = `
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>날짜</th>
                        <th>노출수</th>
                        <th>클릭수</th>
                        <th>CTR (%)</th>
                        <th>비용 ($)</th>
                        <th>CPC ($)</th>
                    </tr>
                </thead>
                <tbody>
    `;
    
    data.forEach(row => {
        html += `
            <tr>
                <td>${formatDate(row.report_date)}</td>
                <td>${formatNumber(row.total_impressions)}</td>
                <td>${formatNumber(row.total_clicks)}</td>
                <td>${row.ctr}</td>
                <td>$${row.total_cost.toFixed(2)}</td>
                <td>$${row.avg_cpc.toFixed(2)}</td>
            </tr>
        `;
    });
    
    html += '</tbody></table></div>';
    container.innerHTML = html;
}

function createKeywordTable(data) {
    const container = document.getElementById('keywordTable');
    if (!container) return;
    
    let html = `
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>순위</th>
                        <th>키워드</th>
                        <th>노출수</th>
                        <th>클릭수</th>
                        <th>CTR (%)</th>
                        <th>비용 ($)</th>
                        <th>CPC ($)</th>
                    </tr>
                </thead>
                <tbody>
    `;
    
    data.forEach((row, index) => {
        html += `
            <tr>
                <td>${index + 1}</td>
                <td>${escapeHtml(row.keyword_name)}</td>
                <td>${formatNumber(row.total_impressions)}</td>
                <td>${formatNumber(row.total_clicks)}</td>
                <td>${row.ctr}</td>
                <td>$${row.total_cost.toFixed(2)}</td>
                <td>$${row.avg_cpc.toFixed(2)}</td>
            </tr>
        `;
    });
    
    html += '</tbody></table></div>';
    container.innerHTML = html;
}

function createDeviceTable(data) {
    const container = document.getElementById('deviceTable');
    if (!container) return;
    
    let html = `
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>디바이스</th>
                        <th>노출수</th>
                        <th>클릭수</th>
                        <th>CTR (%)</th>
                        <th>비용 ($)</th>
                        <th>CPC ($)</th>
                    </tr>
                </thead>
                <tbody>
    `;
    
    data.forEach(row => {
        html += `
            <tr>
                <td>${escapeHtml(row.device_type)}</td>
                <td>${formatNumber(row.total_impressions)}</td>
                <td>${formatNumber(row.total_clicks)}</td>
                <td>${row.ctr}</td>
                <td>$${row.total_cost.toFixed(2)}</td>
                <td>$${row.avg_cpc.toFixed(2)}</td>
            </tr>
        `;
    });
    
    html += '</tbody></table></div>';
    container.innerHTML = html;
}

function createCampaignTable(data) {
    const container = document.getElementById('campaignTable');
    if (!container) return;
    
    let html = `
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>순위</th>
                        <th>캠페인</th>
                        <th>노출수</th>
                        <th>클릭수</th>
                        <th>CTR (%)</th>
                        <th>비용 ($)</th>
                        <th>CPC ($)</th>
                    </tr>
                </thead>
                <tbody>
    `;
    
    data.forEach((row, index) => {
        html += `
            <tr>
                <td>${index + 1}</td>
                <td>${escapeHtml(row.campaign_name)}</td>
                <td>${formatNumber(row.total_impressions)}</td>
                <td>${formatNumber(row.total_clicks)}</td>
                <td>${row.ctr}</td>
                <td>$${row.total_cost.toFixed(2)}</td>
                <td>$${row.avg_cpc.toFixed(2)}</td>
            </tr>
        `;
    });
    
    html += '</tbody></table></div>';
    container.innerHTML = html;
}

function createRawDataTable(data) {
    const container = document.getElementById('rawDataTable');
    if (!container) return;
    
    let html = `
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>날짜</th>
                        <th>매체</th>
                        <th>디바이스</th>
                        <th>캠페인</th>
                        <th>키워드</th>
                        <th>노출수</th>
                        <th>클릭수</th>
                        <th>비용</th>
                    </tr>
                </thead>
                <tbody>
    `;
    
    data.forEach(row => {
        html += `
            <tr>
                <td>${formatDate(row.report_date)}</td>
                <td>${escapeHtml(row.media_product || '-')}</td>
                <td>${escapeHtml(row.device_type || '-')}</td>
                <td>${escapeHtml(row.campaign_name || '-')}</td>
                <td>${escapeHtml(row.keyword_name || '-')}</td>
                <td>${formatNumber(row.impression)}</td>
                <td>${formatNumber(row.click)}</td>
                <td>$${parseFloat(row.cost || 0).toFixed(2)}</td>
            </tr>
        `;
    });
    
    html += '</tbody></table></div>';
    container.innerHTML = html;
}

// === 비용 상세 표시 함수들 ===
function displayUsageAnalysis(trendsData) {
    const container = document.getElementById('usageAnalysis');
    if (!container) return;
    
    const html = `
        <div class="usage-stat">
            <span>평균 일일 비용:</span>
            <span>$${trendsData.average_daily_cost.toFixed(2)}</span>
        </div>
        <div class="usage-stat">
            <span>트렌드:</span>
            <span class="trend-${trendsData.trend_direction}">${translateTrend(trendsData.trend_direction)}</span>
        </div>
        <div class="usage-stat">
            <span>최고 사용일:</span>
            <span>${formatDate(trendsData.peak_day.date)} ($${trendsData.peak_day.cost.toFixed(2)})</span>
        </div>
        <div class="usage-stat">
            <span>최저 사용일:</span>
            <span>${formatDate(trendsData.lowest_day.date)} ($${trendsData.lowest_day.cost.toFixed(2)})</span>
        </div>
    `;
    
    container.innerHTML = html;
}

function displayRecentAlerts(alertsData) {
    const container = document.getElementById('recentAlerts');
    if (!container) return;
    
    if (!alertsData || alertsData.length === 0) {
        container.innerHTML = '<div class="no-data">최근 알림이 없습니다.</div>';
        return;
    }
    
    let html = '';
    alertsData.slice(0, 5).forEach(alert => {
        html += `
            <div class="alert-item">
                <div class="alert-time">${formatDateTime(alert.date)}</div>
                <div class="alert-message">쿼리 ${alert.queries}회 실행 - $${alert.cost.toFixed(2)}</div>
            </div>
        `;
    });
    
    container.innerHTML = html;
}

function displayCostForecast(forecastData) {
    const container = document.getElementById('costForecast');
    if (!container) return;
    
    let html = '<div class="forecast-list">';
    forecastData.slice(0, 7).forEach(day => {
        html += `
            <div class="forecast-item">
                <span>${formatDate(day.date)}</span>
                <span>$${day.projected_cost.toFixed(2)}</span>
                <span class="confidence">${day.confidence.toFixed(0)}% 신뢰도</span>
            </div>
        `;
    });
    html += '</div>';
    
    container.innerHTML = html;
}

// === 비용 관리 함수들 ===
async function resetCostRestrictions() {
    if (!confirm('비용 제한을 해제하시겠습니까? 이 작업은 즉시 효력을 발휘합니다.')) {
        return;
    }
    
    try {
        showLoading('제한 해제 중...');
        
        const response = await fetchWithRetry('api.php?action=reset_cost_restrictions');
        const data = await response.json();
        
        if (data.success) {
            showNotification('✅ 비용 제한이 해제되었습니다.', 'success');
            setTimeout(() => location.reload(), 2000);
        } else {
            showNotification('오류: ' + data.error, 'error');
        }
    } catch (error) {
        showNotification('네트워크 오류: ' + error.message, 'error');
    } finally {
        hideLoading();
    }
}

function exportCostReport() {
    showNotification('📄 비용 보고서를 다운로드합니다...', 'info');
    window.open('api.php?action=export_cost_report&format=csv&period=30d', '_blank');
}

function refreshCostData() {
    loadCostData();
    if (document.getElementById('cost-details')?.classList.contains('active')) {
        loadCostDetails();
    }
    showNotification('🔄 비용 데이터가 새로고침되었습니다.', 'success');
}

// === 페이지네이션 ===
function changePage(direction) {
    const newPage = currentPage + direction;
    if (newPage >= 1 && newPage <= totalPages) {
        currentPage = newPage;
        loadRawData();
    }
}

function updatePagination(metadata) {
    totalPages = Math.ceil(metadata.total_count / 100) || 1;
    updateElement('pageInfo', `${currentPage} / ${totalPages}`);
}

// === 유틸리티 함수들 ===
function initializeDates() {
    const today = new Date();
    const weekAgo = new Date(today.getTime() - 7 * 24 * 60 * 60 * 1000);
    
    const startDateInput = document.getElementById('startDate');
    const endDateInput = document.getElementById('endDate');
    
    if (startDateInput) startDateInput.value = weekAgo.toISOString().split('T')[0];
    if (endDateInput) endDateInput.value = today.toISOString().split('T')[0];
}

async function fetchWithRetry(url, options = {}, retries = 3) {
    for (let i = 0; i < retries; i++) {
        try {
            const response = await fetch(url, {
                ...options,
                headers: {
                    'Content-Type': 'application/json',
                    ...options.headers
                }
            });
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            return response;
        } catch (error) {
            console.warn(`요청 실패 (시도 ${i + 1}/${retries}):`, error);
            
            if (i === retries - 1) throw error;
            
            // 지수 백오프
            await new Promise(resolve => setTimeout(resolve, Math.pow(2, i) * 1000));
        }
    }
}

function destroyChart(chartName) {
    if (charts[chartName]) {
        charts[chartName].destroy();
        delete charts[chartName];
    }
}

function formatNumber(num) {
    return new Intl.NumberFormat('ko-KR').format(num || 0);
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('ko-KR', { 
        month: 'short', 
        day: 'numeric' 
    });
}

function formatDateTime(dateString) {
    const date = new Date(dateString);
    return date.toLocaleString('ko-KR', { 
        month: 'short', 
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function truncateText(text, maxLength) {
    return text.length > maxLength ? text.substring(0, maxLength) + '...' : text;
}

function translateTrend(trend) {
    const translations = {
        'increasing': '상승 📈',
        'decreasing': '하락 📉',
        'stable': '안정 ➡️'
    };
    return translations[trend] || trend;
}

function updateElement(id, content) {
    const element = document.getElementById(id);
    if (element) {
        element.textContent = content;
    }
}

// === 알림 시스템 ===
function showNotification(message, type = 'info', duration = 5000) {
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.innerHTML = `
        <span>${message}</span>
        <button onclick="this.parentElement.remove()" class="notification-close">×</button>
    `;
    
    // 스타일 추가
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 10000;
        padding: 1rem 1.5rem;
        border-radius: 10px;
        box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        color: white;
        font-weight: 600;
        max-width: 400px;
        backdrop-filter: blur(10px);
        animation: slideInRight 0.3s ease;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 1rem;
    `;
    
    // 타입별 색상
    const colors = {
        'success': 'linear-gradient(135deg, #4CAF50, #45a049)',
        'error': 'linear-gradient(135deg, #F44336, #d32f2f)',
        'warning': 'linear-gradient(135deg, #FFC107, #f57c00)',
        'info': 'linear-gradient(135deg, #2196F3, #1976d2)'
    };
    
    notification.style.background = colors[type] || colors.info;
    
    document.body.appendChild(notification);
    
    // 자동 제거
    if (duration > 0) {
        setTimeout(() => {
            if (notification.parentElement) {
                notification.remove();
            }
        }, duration);
    }
}

// === 로딩 표시 ===
function showLoading(message = '로드 중...') {
    let loader = document.getElementById('globalLoader');
    
    if (!loader) {
        loader = document.createElement('div');
        loader.id = 'globalLoader';
        loader.innerHTML = `
            <div class="loader-backdrop">
                <div class="loader-content">
                    <div class="spinner"></div>
                    <div class="loader-message">${message}</div>
                </div>
            </div>
        `;
        
        loader.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
        `;
        
        // CSS 추가
        const style = document.createElement('style');
        style.textContent = `
            .loader-backdrop {
                background: rgba(0,0,0,0.5);
                backdrop-filter: blur(5px);
                border-radius: 15px;
                padding: 2rem;
                text-align: center;
                color: white;
            }
            .spinner {
                width: 40px;
                height: 40px;
                border: 4px solid rgba(255,255,255,0.3);
                border-top: 4px solid white;
                border-radius: 50%;
                animation: spin 1s linear infinite;
                margin: 0 auto 1rem auto;
            }
            @keyframes spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
            .loader-message {
                font-weight: 600;
            }
        `;
        document.head.appendChild(style);
        
        document.body.appendChild(loader);
    } else {
        loader.querySelector('.loader-message').textContent = message;
        loader.style.display = 'flex';
    }
}

function hideLoading() {
    const loader = document.getElementById('globalLoader');
    if (loader) {
        loader.style.display = 'none';
    }
}

// === 이벤트 핸들러들 ===
function handleKeyboardShortcuts(event) {
    // Ctrl+R: 새로고침
    if (event.ctrlKey && event.key === 'r') {
        event.preventDefault();
        location.reload();
    }
    
    // Ctrl+1~7: 탭 전환
    if (event.ctrlKey && event.key >= '1' && event.key <= '7') {
        event.preventDefault();
        const tabs = ['overview', 'daily', 'keywords', 'devices', 'campaigns', 'rawdata', 'cost-details'];
        const tabIndex = parseInt(event.key) - 1;
        if (tabs[tabIndex]) {
            showTab(tabs[tabIndex]);
        }
    }
}

function handleWindowResize() {
    // 차트 리사이즈
    Object.values(charts).forEach(chart => {
        if (chart && typeof chart.resize === 'function') {
            chart.resize();
        }
    });
}

function handleVisibilityChange() {
    if (!document.hidden) {
        // 페이지가 다시 보일 때 비용 데이터 갱신
        if (Date.now() - lastCostUpdate > 60000) {
            loadCostData();
        }
    }
}

function handleOnline() {
    showNotification('🌐 인터넷 연결이 복원되었습니다.', 'success');
    loadCostData();
}

function handleOffline() {
    showNotification('📡 인터넷 연결이 끊어졌습니다. 캐시된 데이터를 표시합니다.', 'warning');
}

// === 디바운스 함수 ===
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// === 브라우저 히스토리 관리 ===
window.addEventListener('popstate', function(event) {
    if (event.state && event.state.tab) {
        showTab(event.state.tab);
    }
});

// === 전역 오류 처리 ===
window.addEventListener('error', function(event) {
    console.error('전역 오류:', event.error);
    showNotification('예상치 못한 오류가 발생했습니다.', 'error');
});

window.addEventListener('unhandledrejection', function(event) {
    console.error('처리되지 않은 Promise 거부:', event.reason);
    showNotification('데이터 처리 중 오류가 발생했습니다.', 'error');
});

// === 성능 모니터링 ===
if ('performance' in window) {
    window.addEventListener('load', function() {
        setTimeout(() => {
            const perfData = performance.getEntriesByType('navigation')[0];
            console.log('페이지 로드 성능:', {
                domContentLoaded: perfData.domContentLoadedEventEnd - perfData.domContentLoadedEventStart,
                pageLoad: perfData.loadEventEnd - perfData.loadEventStart
            });
        }, 0);
    });
}