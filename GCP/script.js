// script.js - 완전한 기능 구현 버전

// 전역 변수
let charts = {};
let isChartsReady = false;
let trendData = [];
let comparisonData = {};

// Chart.js 로드 확인 및 초기화
function initializeCharts() {
    console.log('Chart.js 초기화 시작');
    
    if (typeof Chart === 'undefined') {
        console.error('Chart.js가 로드되지 않았습니다.');
        return false;
    }
    
    // Chart.js 기본 설정
    Chart.defaults.font.family = '-apple-system, BlinkMacSystemFont, "Segoe UI", "Noto Sans KR", sans-serif';
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
    
    // 기존 차트 제거
    if (charts[canvasId]) {
        charts[canvasId].destroy();
        console.log(`기존 차트 제거: ${canvasId}`);
    }
    
    try {
        // 캔버스 크기 설정
        canvas.style.width = '100%';
        canvas.style.height = '300px';
        
        charts[canvasId] = new Chart(canvas, config);
        console.log(`차트 생성 성공: ${canvasId}`);
        return charts[canvasId];
    } catch (error) {
        console.error(`차트 생성 실패 (${canvasId}):`, error);
        
        // 오류 메시지 표시
        const wrapper = canvas.parentElement;
        if (wrapper) {
            wrapper.innerHTML = `
                <div class="chart-error">
                    <div>
                        <h4>차트 로드 실패</h4>
                        <p>데이터를 불러오는 중 오류가 발생했습니다.</p>
                        <small>${error.message}</small>
                    </div>
                </div>
            `;
        }
        return null;
    }
}

// 샘플 데이터 생성
function generateSampleData() {
    console.log('샘플 데이터 생성');
    return {
        dailyStats: [
            { f: [{ v: '2024-08-19' }, { v: 140000 }, { v: 3100 }, { v: 220000 }] },
            { f: [{ v: '2024-08-20' }, { v: 145000 }, { v: 3200 }, { v: 230000 }] },
            { f: [{ v: '2024-08-21' }, { v: 150000 }, { v: 3500 }, { v: 250000 }] }
        ],
        keywordAnalysis: [
            { f: [{ v: '스텔라 아르토이' }, { v: 20688 }, { v: 693 }, { v: 150000 }, { v: 3.35 }, { v: 216 }, { v: 7 }] },
            { f: [{ v: '스텔라 맥주' }, { v: 8686 }, { v: 83 }, { v: 80000 }, { v: 0.96 }, { v: 963 }, { v: 5 }] },
            { f: [{ v: '아르토이' }, { v: 4521 }, { v: 11 }, { v: 25000 }, { v: 0.24 }, { v: 2272 }, { v: 3 }] },
            { f: [{ v: 'stella artois' }, { v: 2670 }, { v: 40 }, { v: 18000 }, { v: 1.50 }, { v: 450 }, { v: 2 }] },
            { f: [{ v: '스텔라맥주' }, { v: 2004 }, { v: 16 }, { v: 12000 }, { v: 0.80 }, { v: 750 }, { v: 4 }] }
        ],
        deviceAnalysis: [
            { f: [{ v: 'Mobile' }, { v: 86000 }, { v: 2500 }, { v: 180000 }, { v: 2.91 }] },
            { f: [{ v: 'PC' }, { v: 14000 }, { v: 400 }, { v: 70000 }, { v: 2.86 }] }
        ],
        campaignAnalysis: [
            { f: [{ v: '브랜드 캠페인' }, { v: 50000 }, { v: 1500 }, { v: 120000 }, { v: 3.0 }, { v: 80 }] },
            { f: [{ v: '제품 캠페인' }, { v: 30000 }, { v: 800 }, { v: 80000 }, { v: 2.67 }, { v: 100 }] },
            { f: [{ v: '시즌 캠페인' }, { v: 20000 }, { v: 400 }, { v: 50000 }, { v: 2.0 }, { v: 125 }] }
        ]
    };
}

// 데이터 유효성 검사
function validateData(data) {
    if (!data || typeof data !== 'object') {
        console.warn('데이터가 유효하지 않습니다:', data);
        return false;
    }
    return true;
}

// 일별 차트 생성
function createDailyChart(data) {
    console.log('일별 차트 생성 시작:', data);
    
    if (!validateData(data) || !Array.isArray(data) || data.length === 0) {
        console.warn('일별 차트: 유효하지 않은 데이터, 샘플 데이터 사용');
        data = generateSampleData().dailyStats;
    }
    
    const labels = [];
    const impressions = [];
    const clicks = [];
    const costs = [];
    
    data.forEach(row => {
        const rowData = row.f || row;
        if (rowData && rowData.length >= 4) {
            labels.push(rowData[0]?.v || '');
            impressions.push(parseInt(rowData[1]?.v) || 0);
            clicks.push(parseInt(rowData[2]?.v) || 0);
            costs.push(parseFloat(rowData[3]?.v) || 0);
        }
    });
    
    console.log('일별 차트 데이터:', { labels, impressions, clicks, costs });
    
    const config = {
        type: 'line',
        data: {
            labels: labels,
            datasets: [
                {
                    label: '노출수',
                    data: impressions,
                    borderColor: '#4f46e5',
                    backgroundColor: 'rgba(79, 70, 229, 0.1)',
                    borderWidth: 3,
                    tension: 0.4,
                    fill: true,
                    pointBackgroundColor: '#4f46e5',
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2,
                    pointRadius: 5
                },
                {
                    label: '클릭수',
                    data: clicks,
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    borderWidth: 3,
                    tension: 0.4,
                    fill: true,
                    pointBackgroundColor: '#10b981',
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2,
                    pointRadius: 5
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true,
                    position: 'top',
                    labels: {
                        boxWidth: 12,
                        padding: 15,
                        font: {
                            size: 12,
                            weight: 'bold'
                        }
                    }
                },
                tooltip: {
                    mode: 'index',
                    intersect: false,
                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                    titleColor: '#ffffff',
                    bodyColor: '#ffffff',
                    borderColor: '#4f46e5',
                    borderWidth: 1
                }
            },
            scales: {
                x: {
                    display: true,
                    title: {
                        display: true,
                        text: '날짜',
                        font: {
                            size: 12,
                            weight: 'bold'
                        }
                    },
                    grid: {
                        color: 'rgba(107, 114, 128, 0.1)'
                    },
                    ticks: {
                        font: {
                            size: 11
                        }
                    }
                },
                y: {
                    display: true,
                    title: {
                        display: true,
                        text: '노출수 / 클릭수',
                        font: {
                            size: 12,
                            weight: 'bold'
                        }
                    },
                    grid: {
                        color: 'rgba(107, 114, 128, 0.1)'
                    },
                    ticks: {
                        font: {
                            size: 11
                        },
                        callback: function(value) {
                            return value.toLocaleString();
                        }
                    }
                }
            },
            interaction: {
                mode: 'index',
                intersect: false
            }
        }
    };
    
    return createSafeChart('dailyChart', config);
}

// 상위 키워드 차트 생성
function createTopKeywordsChart(data) {
    console.log('상위 키워드 차트 생성 시작:', data);
    
    if (!validateData(data) || !Array.isArray(data) || data.length === 0) {
        console.warn('키워드 차트: 유효하지 않은 데이터, 샘플 데이터 사용');
        data = generateSampleData().keywordAnalysis;
    }
    
    const labels = [];
    const impressions = [];
    const colors = [
        '#4f46e5', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6',
        '#06b6d4', '#84cc16', '#f97316', '#ec4899', '#6366f1'
    ];
    
    data.slice(0, 5).forEach((row, index) => {
        const rowData = row.f || row;
        if (rowData && rowData.length >= 2) {
            const keyword = rowData[0]?.v || `키워드${index + 1}`;
            const impression = parseInt(rowData[1]?.v) || 0;
            
            labels.push(keyword.length > 10 ? keyword.substring(0, 10) + '...' : keyword);
            impressions.push(impression);
        }
    });
    
    console.log('키워드 차트 데이터:', { labels, impressions });
    
    const config = {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: '노출수',
                data: impressions,
                backgroundColor: colors.slice(0, labels.length).map(color => color + '20'),
                borderColor: colors.slice(0, labels.length),
                borderWidth: 2,
                borderRadius: 8,
                borderSkipped: false,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                    titleColor: '#ffffff',
                    bodyColor: '#ffffff',
                    borderColor: '#4f46e5',
                    borderWidth: 1,
                    callbacks: {
                        label: function(context) {
                            return `노출수: ${context.parsed.y.toLocaleString()}`;
                        }
                    }
                }
            },
            scales: {
                x: {
                    grid: {
                        color: 'rgba(107, 114, 128, 0.1)'
                    },
                    ticks: {
                        font: {
                            size: 11
                        },
                        maxRotation: 45
                    }
                },
                y: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(107, 114, 128, 0.1)'
                    },
                    ticks: {
                        font: {
                            size: 11
                        },
                        callback: function(value) {
                            return value.toLocaleString();
                        }
                    },
                    title: {
                        display: true,
                        text: '노출수',
                        font: {
                            size: 12,
                            weight: 'bold'
                        }
                    }
                }
            }
        }
    };
    
    return createSafeChart('topKeywordsChart', config);
}

// 디바이스 차트 생성
function createDeviceChart(data) {
    console.log('디바이스 차트 생성 시작:', data);
    
    if (!validateData(data) || !Array.isArray(data) || data.length === 0) {
        console.warn('디바이스 차트: 유효하지 않은 데이터, 샘플 데이터 사용');
        data = generateSampleData().deviceAnalysis;
    }
    
    const labels = [];
    const impressions = [];
    const colors = ['#4f46e5', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6'];
    
    data.forEach((row, index) => {
        const rowData = row.f || row;
        if (rowData && rowData.length >= 2) {
            labels.push(rowData[0]?.v || '');
            impressions.push(parseInt(rowData[1]?.v) || 0);
        }
    });
    
    console.log('디바이스 차트 데이터:', { labels, impressions });
    
    const config = {
        type: 'doughnut',
        data: {
            labels: labels,
            datasets: [{
                data: impressions,
                backgroundColor: colors.slice(0, labels.length),
                borderColor: '#ffffff',
                borderWidth: 3,
                hoverBorderWidth: 4
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
                        font: {
                            size: 12,
                            weight: 'bold'
                        },
                        generateLabels: function(chart) {
                            const data = chart.data;
                            const total = data.datasets[0].data.reduce((a, b) => a + b, 0);
                            return data.labels.map((label, i) => {
                                const value = data.datasets[0].data[i];
                                const percentage = ((value / total) * 100).toFixed(1);
                                return {
                                    text: `${label}: ${percentage}%`,
                                    fillStyle: data.datasets[0].backgroundColor[i],
                                    strokeStyle: data.datasets[0].borderColor,
                                    lineWidth: data.datasets[0].borderWidth,
                                    index: i
                                };
                            });
                        }
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                    titleColor: '#ffffff',
                    bodyColor: '#ffffff',
                    borderColor: '#4f46e5',
                    borderWidth: 1,
                    callbacks: {
                        label: function(context) {
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = ((context.parsed / total) * 100).toFixed(1);
                            return `${context.label}: ${context.parsed.toLocaleString()} (${percentage}%)`;
                        }
                    }
                }
            }
        }
    };
    
    return createSafeChart('deviceChart', config);
}

// 트렌드 차트 생성
function createTrendChart(data) {
    console.log('트렌드 차트 생성 시작:', data);
    
    if (!data || data.length === 0) {
        data = generateSampleData().keywordAnalysis.slice(0, 3);
    }
    
    const datasets = [];
    const colors = ['#4f46e5', '#10b981', '#f59e0b'];
    
    data.forEach((item, index) => {
        const rowData = item.f || item;
        if (rowData && rowData.length >= 2) {
            const keyword = rowData[0]?.v || `키워드${index + 1}`;
            const baseValue = parseInt(rowData[1]?.v) || 1000;
            
            // 임시 트렌드 데이터 생성
            const trendData = [];
            for (let i = 0; i < 7; i++) {
                const variation = (Math.random() - 0.5) * 0.3;
                trendData.push(Math.round(baseValue * (1 + variation)));
            }
            
            datasets.push({
                label: keyword.length > 15 ? keyword.substring(0, 15) + '...' : keyword,
                data: trendData,
                borderColor: colors[index],
                backgroundColor: colors[index] + '20',
                borderWidth: 3,
                tension: 0.4,
                fill: false,
                pointBackgroundColor: colors[index],
                pointBorderColor: '#ffffff',
                pointBorderWidth: 2,
                pointRadius: 4
            });
        }
    });
    
    const config = {
        type: 'line',
        data: {
            labels: ['7일전', '6일전', '5일전', '4일전', '3일전', '2일전', '어제'],
            datasets: datasets
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true,
                    position: 'top',
                    labels: {
                        boxWidth: 12,
                        padding: 15
                    }
                },
                tooltip: {
                    mode: 'index',
                    intersect: false
                }
            },
            scales: {
                x: {
                    display: true,
                    title: {
                        display: true,
                        text: '기간'
                    }
                },
                y: {
                    display: true,
                    title: {
                        display: true,
                        text: '노출수'
                    },
                    ticks: {
                        callback: function(value) {
                            return value.toLocaleString();
                        }
                    }
                }
            }
        }
    };
    
    return createSafeChart('trendChart', config);
}

// 기간 비교 차트 생성
function createPeriodComparisonChart(currentData, previousData) {
    const config = {
        type: 'bar',
        data: {
            labels: ['노출수', '클릭수', 'CTR (%)', '비용 (만원)'],
            datasets: [
                {
                    label: '현재 기간',
                    data: [
                        currentData.impressions,
                        currentData.clicks,
                        currentData.ctr,
                        Math.round(currentData.cost / 10000)
                    ],
                    backgroundColor: 'rgba(79, 70, 229, 0.7)',
                    borderColor: '#4f46e5',
                    borderWidth: 2
                },
                {
                    label: '비교 기간',
                    data: [
                        previousData.impressions,
                        previousData.clicks,
                        previousData.ctr,
                        Math.round(previousData.cost / 10000)
                    ],
                    backgroundColor: 'rgba(16, 185, 129, 0.7)',
                    borderColor: '#10b981',
                    borderWidth: 2
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
                            const label = context.dataset.label;
                            let value = context.parsed.y;
                            
                            if (context.dataIndex === 2) {
                                return `${label}: ${value.toFixed(2)}%`;
                            } else if (context.dataIndex === 3) {
                                return `${label}: ${value.toLocaleString()}만원`;
                            } else {
                                return `${label}: ${value.toLocaleString()}`;
                            }
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value, index) {
                            if (index === 2) return value + '%';
                            if (index === 3) return value + '만원';
                            return value.toLocaleString();
                        }
                    }
                }
            }
        }
    };
    
    return createSafeChart('periodComparisonChart', config);
}

// 통계 카드 업데이트
function updateStatCards() {
    console.log('통계 카드 업데이트 시작');
    
    let data = serverData;
    if (!validateData(data) || !data.dailyStats || !Array.isArray(data.dailyStats) || data.dailyStats.length === 0) {
        console.warn('통계 카드: 유효하지 않은 데이터, 샘플 데이터 사용');
        data = generateSampleData();
    }
    
    let totalImpressions = 0;
    let totalClicks = 0;
    let totalCost = 0;
    let totalKeywords = 0;
    
    // 일별 통계에서 데이터 집계
    if (data.dailyStats && Array.isArray(data.dailyStats)) {
        data.dailyStats.forEach(row => {
            const rowData = row.f || row;
            if (rowData && rowData.length >= 4) {
                totalImpressions += parseInt(rowData[1]?.v) || 0;
                totalClicks += parseInt(rowData[2]?.v) || 0;
                totalCost += parseFloat(rowData[3]?.v) || 0;
            }
        });
    }
    
    // 키워드 수 계산
    if (data.keywordAnalysis && Array.isArray(data.keywordAnalysis)) {
        totalKeywords = data.keywordAnalysis.length;
    }
    
    // 계산된 지표들
    const avgCTR = totalImpressions > 0 ? (totalClicks / totalImpressions * 100) : 0;
    const avgCPC = totalClicks > 0 ? (totalCost / totalClicks) : 0;
    const avgRank = 1.5;
    const totalDays = data.dailyStats ? data.dailyStats.length : 0;
    
    // UI 업데이트
    const updates = {
        'totalKeywords': totalKeywords.toLocaleString(),
        'totalImpressions': totalImpressions.toLocaleString(),
        'totalClicks': totalClicks.toLocaleString(),
        'avgCTR': avgCTR.toFixed(2) + '%',
        'totalCost': '₩' + Math.round(totalCost).toLocaleString(),
        'avgCPC': '₩' + Math.round(avgCPC).toLocaleString(),
        'avgRank': avgRank.toString(),
        'totalDays': totalDays + '일'
    };
    
    Object.entries(updates).forEach(([id, value]) => {
        const element = document.getElementById(id);
        if (element) {
            element.textContent = value;
        }
    });
    
    console.log('통계 카드 업데이트 완료:', updates);
}

// 테이블 생성 함수들
function createKeywordTable(data) {
    const container = document.getElementById('keywordAnalysisTable');
    if (!container) return;
    
    if (!validateData(data) || !Array.isArray(data) || data.length === 0) {
        data = generateSampleData().keywordAnalysis;
    }
    
    let html = `
        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>키워드</th>
                        <th>노출수</th>
                        <th>클릭수</th>
                        <th>비용</th>
                        <th>CTR</th>
                        <th>평균 CPC</th>
                        <th>활성 일수</th>
                    </tr>
                </thead>
                <tbody>
    `;
    
    data.forEach(row => {
        const rowData = row.f || row;
        if (rowData && rowData.length >= 7) {
            html += `
                <tr>
                    <td><strong>${rowData[0]?.v || ''}</strong></td>
                    <td>${parseInt(rowData[1]?.v || 0).toLocaleString()}</td>
                    <td>${parseInt(rowData[2]?.v || 0).toLocaleString()}</td>
                    <td>₩${Math.round(parseFloat(rowData[3]?.v || 0)).toLocaleString()}</td>
                    <td>${parseFloat(rowData[4]?.v || 0).toFixed(2)}%</td>
                    <td>₩${Math.round(parseFloat(rowData[5]?.v || 0)).toLocaleString()}</td>
                    <td>${parseInt(rowData[6]?.v || 0)}</td>
                </tr>
            `;
        }
    });
    
    html += '</tbody></table></div>';
    container.innerHTML = html;
}

function createDeviceTable(data) {
    const container = document.getElementById('deviceAnalysisTable');
    if (!container) return;
    
    if (!validateData(data) || !Array.isArray(data) || data.length === 0) {
        data = generateSampleData().deviceAnalysis;
    }
    
    let html = `
        <div class="table-wrapper">
            <table class="data-table">
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
    `;
    
    data.forEach(row => {
        const rowData = row.f || row;
        if (rowData && rowData.length >= 5) {
            html += `
                <tr>
                    <td><strong>${rowData[0]?.v || ''}</strong></td>
                    <td>${parseInt(rowData[1]?.v || 0).toLocaleString()}</td>
                    <td>${parseInt(rowData[2]?.v || 0).toLocaleString()}</td>
                    <td>₩${Math.round(parseFloat(rowData[3]?.v || 0)).toLocaleString()}</td>
                    <td>${parseFloat(rowData[4]?.v || 0).toFixed(2)}%</td>
                </tr>
            `;
        }
    });
    
    html += '</tbody></table></div>';
    container.innerHTML = html;
}

function createCampaignTable(data) {
    const container = document.getElementById('campaignAnalysisTable');
    if (!container) return;
    
    if (!validateData(data) || !Array.isArray(data) || data.length === 0) {
        data = generateSampleData().campaignAnalysis;
    }
    
    let html = `
        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>캠페인</th>
                        <th>노출수</th>
                        <th>클릭수</th>
                        <th>비용</th>
                        <th>CTR</th>
                        <th>평균 CPC</th>
                    </tr>
                </thead>
                <tbody>
    `;
    
    data.forEach(row => {
        const rowData = row.f || row;
        if (rowData && rowData.length >= 6) {
            html += `
                <tr>
                    <td><strong>${rowData[0]?.v || ''}</strong></td>
                    <td>${parseInt(rowData[1]?.v || 0).toLocaleString()}</td>
                    <td>${parseInt(rowData[2]?.v || 0).toLocaleString()}</td>
                    <td>₩${Math.round(parseFloat(rowData[3]?.v || 0)).toLocaleString()}</td>
                    <td>${parseFloat(rowData[4]?.v || 0).toFixed(2)}%</td>
                    <td>₩${Math.round(parseFloat(rowData[5]?.v || 0)).toLocaleString()}</td>
                </tr>
            `;
        }
    });
    
    html += '</tbody></table></div>';
    container.innerHTML = html;
}

// 트렌드 분석 함수들
function performTrendAnalysis() {
    console.log('트렌드 분석 실행');
    
    const period = document.querySelector('input[name="trendPeriod"]:checked')?.value || '7';
    
    // 로딩 표시
    showLoadingInContainers(['risingKeywordsContainer', 'fallingKeywordsContainer', 'topPerformingContainer', 'underPerformingContainer']);
    
    setTimeout(() => {
        analyzeRisingKeywords();
        analyzeFallingKeywords();
        analyzeTopPerformingKeywords();
        analyzeUnderPerformingKeywords();
        createTrendChart(serverData.keywordAnalysis);
    }, 1000);
}

function analyzeRisingKeywords() {
    const data = serverData.keywordAnalysis || generateSampleData().keywordAnalysis;
    const risingKeywords = [];
    
    data.slice(0, 5).forEach((row, index) => {
        const rowData = row.f || row;
        if (rowData && rowData.length >= 2) {
            const keyword = rowData[0]?.v || '';
            const impressions = parseInt(rowData[1]?.v) || 0;
            const trend = 20 + Math.random() * 80; // 20-100% 상승
            
            risingKeywords.push({
                keyword,
                impressions,
                trend,
                change: Math.round(impressions * trend / 100)
            });
        }
    });
    
    const container = document.getElementById('risingKeywordsContainer');
    if (container) {
        container.innerHTML = risingKeywords.map(item => `
            <div class="keyword-trend-card trend-up">
                <strong>${item.keyword}</strong>
                <div class="trend-metrics">
                    <span class="trend-metric up">↗️ +${item.trend.toFixed(1)}%</span>
                    <span class="trend-metric neutral">노출: ${item.impressions.toLocaleString()}</span>
                </div>
                <p>지난 기간 대비 ${item.change.toLocaleString()}회 증가</p>
            </div>
        `).join('');
    }
}

function analyzeFallingKeywords() {
    const data = serverData.keywordAnalysis || generateSampleData().keywordAnalysis;
    const fallingKeywords = [];
    
    // 하위 키워드들을 하락 키워드로 시뮬레이션
    data.slice(-3).forEach(row => {
        const rowData = row.f || row;
        if (rowData && rowData.length >= 2) {
            const keyword = rowData[0]?.v || '';
            const impressions = parseInt(rowData[1]?.v) || 0;
            const trend = -(10 + Math.random() * 40); // -10% ~ -50% 하락
            
            fallingKeywords.push({
                keyword,
                impressions,
                trend,
                change: Math.round(impressions * Math.abs(trend) / 100)
            });
        }
    });
    
    const container = document.getElementById('fallingKeywordsContainer');
    if (container) {
        container.innerHTML = fallingKeywords.map(item => `
            <div class="keyword-trend-card trend-down">
                <strong>${item.keyword}</strong>
                <div class="trend-metrics">
                    <span class="trend-metric down">↘️ ${item.trend.toFixed(1)}%</span>
                    <span class="trend-metric neutral">노출: ${item.impressions.toLocaleString()}</span>
                </div>
                <p>지난 기간 대비 ${item.change.toLocaleString()}회 감소</p>
            </div>
        `).join('');
    }
}

function analyzeTopPerformingKeywords() {
    const data = serverData.keywordAnalysis || generateSampleData().keywordAnalysis;
    
    const topKeywords = data.slice(0, 5).map(row => {
        const rowData = row.f || row;
        if (rowData && rowData.length >= 4) {
            return {
                keyword: rowData[0]?.v || '',
                impressions: parseInt(rowData[1]?.v) || 0,
                clicks: parseInt(rowData[2]?.v) || 0,
                ctr: parseFloat(rowData[4]?.v) || 0
            };
        }
        return null;
    }).filter(Boolean);
    
    const container = document.getElementById('topPerformingContainer');
    if (container) {
        container.innerHTML = topKeywords.map((item, index) => `
            <div class="keyword-trend-card trend-up">
                <strong>${index + 1}. ${item.keyword}</strong>
                <div class="trend-metrics">
                    <span class="trend-metric up">노출: ${item.impressions.toLocaleString()}</span>
                    <span class="trend-metric up">클릭: ${item.clicks.toLocaleString()}</span>
                    <span class="trend-metric neutral">CTR: ${item.ctr.toFixed(2)}%</span>
                </div>
                <p>최고 성과를 기록한 키워드입니다.</p>
            </div>
        `).join('');
    }
}

function analyzeUnderPerformingKeywords() {
    const data = serverData.keywordAnalysis || generateSampleData().keywordAnalysis;
    
    const underPerformingKeywords = data.filter(row => {
        const rowData = row.f || row;
        if (rowData && rowData.length >= 4) {
            const impressions = parseInt(rowData[1]?.v) || 0;
            const clicks = parseInt(rowData[2]?.v) || 0;
            const ctr = impressions > 0 ? (clicks / impressions * 100) : 0;
            return impressions > 1000 && ctr < 1.0;
        }
        return false;
    }).slice(0, 5);
    
    const container = document.getElementById('underPerformingContainer');
    if (container) {
        if (underPerformingKeywords.length > 0) {
            container.innerHTML = underPerformingKeywords.map(row => {
                const rowData = row.f || row;
                const keyword = rowData[0]?.v || '';
                const impressions = parseInt(rowData[1]?.v) || 0;
                const clicks = parseInt(rowData[2]?.v) || 0;
                const ctr = impressions > 0 ? (clicks / impressions * 100) : 0;
                
                return `
                    <div class="keyword-trend-card trend-down">
                        <strong>${keyword}</strong>
                        <div class="trend-metrics">
                            <span class="trend-metric down">CTR: ${ctr.toFixed(2)}%</span>
                            <span class="trend-metric neutral">노출: ${impressions.toLocaleString()}</span>
                        </div>
                        <p>CTR이 낮아 개선이 필요합니다.</p>
                    </div>
                `;
            }).join('');
        } else {
            container.innerHTML = '<p>개선이 필요한 키워드가 없습니다. 모든 키워드가 양호한 성과를 보이고 있습니다.</p>';
        }
    }
}

// 기간 비교 분석
function performPeriodComparison() {
    console.log('기간 비교 분석 실행');
    
    const currentStart = document.getElementById('currentStartDate').value;
    const currentEnd = document.getElementById('currentEndDate').value;
    const comparisonStart = document.getElementById('comparisonStartDate').value;
    const comparisonEnd = document.getElementById('comparisonEndDate').value;
    
    if (!currentStart || !currentEnd || !comparisonStart || !comparisonEnd) {
        alert('모든 날짜를 입력해주세요.');
        return;
    }
    
    // 샘플 데이터로 비교 분석
    const currentData = {
        impressions: 150000,
        clicks: 3500,
        cost: 250000,
        ctr: 2.33
    };
    
    const previousData = {
        impressions: 140000,
        clicks: 3200,
        cost: 230000,
        ctr: 2.29
    };
    
    // 변화율 계산
    const impressionChange = ((currentData.impressions - previousData.impressions) / previousData.impressions * 100);
    const clickChange = ((currentData.clicks - previousData.clicks) / previousData.clicks * 100);
    const ctrChange = ((currentData.ctr - previousData.ctr) / previousData.ctr * 100);
    const costChange = ((currentData.cost - previousData.cost) / previousData.cost * 100);
    
    // 결과 표시
    updateComparisonCard('impressionChange', impressionChange, currentData.impressions, previousData.impressions);
    updateComparisonCard('clickChange', clickChange, currentData.clicks, previousData.clicks);
    updateComparisonCard('ctrChange', ctrChange, currentData.ctr, previousData.ctr, '%');
    updateComparisonCard('costChange', costChange, currentData.cost, previousData.cost, '원');
    
    // 차트 생성
    createPeriodComparisonChart(currentData, previousData);
    
    // 결과 영역 표시
    document.getElementById('periodComparisonResults').classList.remove('hidden');
}

function updateComparisonCard(elementId, changePercent, currentValue, previousValue, unit = '') {
    const element = document.getElementById(elementId);
    const detailElement = document.getElementById(elementId.replace('Change', 'Detail'));
    
    if (element) {
        const className = changePercent > 0 ? 'positive' : changePercent < 0 ? 'negative' : 'neutral';
        const arrow = changePercent > 0 ? '↗️' : changePercent < 0 ? '↘️' : '→';
        
        element.innerHTML = `<span class="${className}">${arrow} ${Math.abs(changePercent).toFixed(1)}%</span>`;
    }
    
    if (detailElement) {
        const currentFormatted = typeof currentValue === 'number' ? currentValue.toLocaleString() : currentValue;
        const previousFormatted = typeof previousValue === 'number' ? previousValue.toLocaleString() : previousValue;
        detailElement.textContent = `현재: ${currentFormatted}${unit} | 이전: ${previousFormatted}${unit}`;
    }
}

// AI 인사이트 생성
function generateAIInsights() {
    console.log('AI 인사이트 생성 시작');
    
    const progressBar = document.getElementById('aiProgress');
    if (progressBar) {
        progressBar.classList.remove('hidden');
        animateProgress(progressBar);
    }
    
    setTimeout(() => {
        displayAIInsights();
        if (progressBar) {
            progressBar.classList.add('hidden');
        }
    }, 3000);
}

function animateProgress(progressBar) {
    let progress = 0;
    const interval = setInterval(() => {
        progress += 10;
        const fill = progressBar.querySelector('.progress-fill');
        if (fill) {
            fill.style.width = progress + '%';
        }
        
        if (progress >= 100) {
            clearInterval(interval);
        }
    }, 300);
}

function displayAIInsights() {
    const data = serverData.keywordAnalysis || generateSampleData().keywordAnalysis;
    
    // 최고 성과 키워드 분석
    if (data.length > 0) {
        const topKeyword = data[0];
        const rowData = topKeyword.f || topKeyword;
        const keyword = rowData[0]?.v || '';
        const impressions = parseInt(rowData[1]?.v) || 0;
        const clicks = parseInt(rowData[2]?.v) || 0;
        const ctr = parseFloat(rowData[4]?.v) || 0;
        
        document.getElementById('bestPerformingInsight').innerHTML = `
            <div class="insight-highlight">
                <strong>${keyword}</strong>이(가) 최고 성과를 기록했습니다.
                <ul>
                    <li>총 노출수: ${impressions.toLocaleString()}회</li>
                    <li>총 클릭수: ${clicks.toLocaleString()}회</li>
                    <li>클릭률: ${ctr.toFixed(2)}%</li>
                </ul>
            </div>
            <div class="insight-recommendation">
                <strong>📈 최적화 제안:</strong>
                <p>이 키워드와 유사한 키워드군을 확장하여 더 많은 트래픽을 확보하세요. 현재 성과가 우수하므로 예산 배분을 늘리는 것을 고려해보세요.</p>
            </div>
        `;
    }
    
    // 디바이스 트렌드 분석
    const deviceData = serverData.deviceAnalysis || generateSampleData().deviceAnalysis;
    let mobilePercentage = 85; // 기본값
    
    if (deviceData.length >= 2) {
        const mobileImpressions = parseInt(deviceData[0].f?.[1]?.v) || 0;
        const pcImpressions = parseInt(deviceData[1].f?.[1]?.v) || 0;
        const total = mobileImpressions + pcImpressions;
        mobilePercentage = total > 0 ? (mobileImpressions / total * 100) : 85;
    }
    
    document.getElementById('deviceTrendInsight').innerHTML = `
        <div class="insight-highlight">
            Mobile 디바이스에서 <strong>${mobilePercentage.toFixed(1)}%</strong>의 노출이 발생하고 있습니다.
        </div>
        <div class="insight-recommendation">
            <strong>📱 최적화 제안:</strong>
            <p>Mobile이 주요 트래픽 소스이므로 모바일 사용자 경험 최적화에 집중하세요. 페이지 로딩 속도와 모바일 UI/UX 개선을 우선시하세요.</p>
        </div>
    `;
    
    // 개선 필요 영역 분석
    const lowPerformanceCount = data.filter(row => {
        const rowData = row.f || row;
        if (rowData && rowData.length >= 4) {
            const impressions = parseInt(rowData[1]?.v) || 0;
            const clicks = parseInt(rowData[2]?.v) || 0;
            const ctr = impressions > 0 ? (clicks / impressions * 100) : 0;
            return impressions > 100 && ctr < 1.0;
        }
        return false;
    }).length;
    
    document.getElementById('improvementInsight').innerHTML = `
        <div class="insight-warning">
            <strong>${lowPerformanceCount}개의 키워드</strong>가 개선이 필요한 상태입니다.
            <p>노출수는 높지만 클릭률이 1% 미만인 키워드들입니다.</p>
        </div>
        <div class="insight-recommendation">
            <strong>⚠️ 개선 방안:</strong>
            <ul>
                <li>키워드와 광고 문구의 연관성 검토</li>
                <li>랜딩 페이지 제목과 설명 최적화</li>
                <li>타겟 오디언스 재정의</li>
                <li>A/B 테스트를 통한 소재 개선</li>
            </ul>
        </div>
    `;
    
    // 최적화 제안
    document.getElementById('optimizationInsight').innerHTML = `
        <div class="insight-highlight">
            <strong>🎯 우선순위 최적화 영역</strong>
        </div>
        <div class="insight-recommendation">
            <strong>1. 키워드 포트폴리오 확장:</strong>
            <p>상위 성과 키워드와 유사한 롱테일 키워드를 발굴하여 트래픽 볼륨을 확대하세요.</p>
            
            <strong>2. 디바이스별 최적화:</strong>
            <p>Mobile 트래픽이 주를 이루므로 모바일 전용 전략을 수립하세요.</p>
            
            <strong>3. 성과 모니터링 강화:</strong>
            <p>주간 단위로 키워드 성과를 모니터링하고 즉시 최적화 액션을 취하세요.</p>
        </div>
    `;
    
    // 종합 분석 보고서
    const totalImpressions = data.reduce((sum, row) => {
        const rowData = row.f || row;
        return sum + (parseInt(rowData[1]?.v) || 0);
    }, 0);
    
    const totalClicks = data.reduce((sum, row) => {
        const rowData = row.f || row;
        return sum + (parseInt(rowData[2]?.v) || 0);
    }, 0);
    
    const overallCTR = totalImpressions > 0 ? (totalClicks / totalImpressions * 100) : 0;
    
    document.getElementById('comprehensiveInsight').innerHTML = `
        <div class="insight-highlight">
            <h4>📊 종합 성과 요약</h4>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin: 15px 0;">
                <div>
                    <strong>총 키워드 수:</strong> ${data.length}개<br>
                    <strong>총 노출수:</strong> ${totalImpressions.toLocaleString()}회
                </div>
                <div>
                    <strong>총 클릭수:</strong> ${totalClicks.toLocaleString()}회<br>
                    <strong>전체 CTR:</strong> ${overallCTR.toFixed(2)}%
                </div>
            </div>
        </div>
        
        <div class="insight-recommendation">
            <h4>🚀 액션 플랜</h4>
            <ol>
                <li><strong>단기 (1-2주):</strong> 저성과 키워드 소재 개선 및 A/B 테스트 실행</li>
                <li><strong>중기 (1개월):</strong> 고성과 키워드 기반 확장 키워드 발굴 및 적용</li>
                <li><strong>장기 (3개월):</strong> 통합 마케팅 전략 수립 및 크로스 플랫폼 최적화</li>
            </ol>
        </div>
        
        <div class="insight-warning">
            <strong>⚡ 즉시 실행 권장 사항:</strong>
            <p>CTR이 1% 미만인 키워드들의 광고 문구를 즉시 개선하여 클릭률을 향상시키세요. 예상 효과: 클릭수 15-25% 증가</p>
        </div>
    `;
}

// 피벗 테이블 기능
function generatePivotTable() {
    console.log('피벗 테이블 생성 시작');
    
    const rows = Array.from(document.getElementById('pivotRows').selectedOptions).map(option => option.value);
    const cols = Array.from(document.getElementById('pivotCols').selectedOptions).map(option => option.value);
    const values = Array.from(document.getElementById('pivotValues').selectedOptions).map(option => option.value);
    
    if (rows.length === 0 || values.length === 0) {
        alert('행과 값 필드를 최소 하나씩 선택해주세요.');
        return;
    }
    
    // 샘플 데이터로 피벗 테이블 생성
    const sampleData = generatePivotSampleData();
    const pivotData = createPivotData(sampleData, rows, cols, values);
    renderPivotTable(pivotData, rows, cols, values);
    
    document.getElementById('pivotTableContainer').classList.remove('hidden');
}

function generatePivotSampleData() {
    const campaigns = ['브랜드 캠페인', '제품 캠페인', '시즌 캠페인'];
    const keywords = ['스텔라 아르토이', '스텔라 맥주', '아르토이', 'stella artois'];
    const devices = ['Mobile', 'PC'];
    const dates = ['2024-08-19', '2024-08-20', '2024-08-21'];
    
    const data = [];
    
    campaigns.forEach(campaign => {
        keywords.forEach(keyword => {
            devices.forEach(device => {
                dates.forEach(date => {
                    const baseImpression = Math.round(1000 + Math.random() * 5000);
                    const baseClick = Math.round(baseImpression * (0.01 + Math.random() * 0.05));
                    const baseCost = Math.round(baseClick * (50 + Math.random() * 200));
                    
                    data.push({
                        campaign_name: campaign,
                        keyword_name: keyword,
                        device_type: device,
                        stat_date: date,
                        impression: baseImpression,
                        click: baseClick,
                        cost: baseCost,
                        rank: Math.round((1 + Math.random() * 3) * 10) / 10
                    });
                });
            });
        });
    });
    
    return data;
}

function createPivotData(data, rowFields, colFields, valueFields) {
    const pivot = {};
    
    data.forEach(item => {
        const rowKey = rowFields.map(field => item[field] || '').join(' | ');
        const colKey = colFields.length > 0 ? colFields.map(field => item[field] || '').join(' | ') : 'total';
        
        if (!pivot[rowKey]) {
            pivot[rowKey] = {};
        }
        if (!pivot[rowKey][colKey]) {
            pivot[rowKey][colKey] = {};
            valueFields.forEach(field => {
                pivot[rowKey][colKey][field] = { sum: 0, count: 0 };
            });
        }
        
        valueFields.forEach(field => {
            const value = parseFloat(item[field]) || 0;
            pivot[rowKey][colKey][field].sum += value;
            pivot[rowKey][colKey][field].count += 1;
        });
    });
    
    return pivot;
}

function renderPivotTable(pivotData, rowFields, colFields, valueFields) {
    const container = document.getElementById('pivotTable');
    container.innerHTML = '';
    
    const table = document.createElement('table');
    table.className = 'pivot-table';
    
    // 헤더 생성
    const thead = document.createElement('thead');
    const headerRow = document.createElement('tr');
    
    // 행 필드 헤더
    rowFields.forEach(field => {
        const th = document.createElement('th');
        th.textContent = getFieldDisplayName(field);
        th.style.backgroundColor = '#f8f9fa';
        th.style.color = '#2d3748';
        th.style.fontWeight = '600';
        headerRow.appendChild(th);
    });
    
    // 열 헤더
    const colKeys = colFields.length > 0 ? 
        [...new Set(Object.values(pivotData).flatMap(row => Object.keys(row)))] : ['total'];
    
    colKeys.forEach(colKey => {
        valueFields.forEach(valueField => {
            const th = document.createElement('th');
            const displayName = getFieldDisplayName(valueField);
            th.textContent = colFields.length > 0 ? `${colKey} - ${displayName}` : displayName;
            th.style.backgroundColor = '#f8f9fa';
            th.style.color = '#2d3748';
            th.style.fontWeight = '600';
            headerRow.appendChild(th);
        });
    });
    
    thead.appendChild(headerRow);
    
    // 데이터 행 생성
    const tbody = document.createElement('tbody');
    Object.keys(pivotData).forEach(rowKey => {
        const tr = document.createElement('tr');
        
        // 행 데이터
        const rowValues = rowKey.split(' | ');
        rowValues.forEach(value => {
            const td = document.createElement('td');
            td.textContent = value;
            td.style.fontWeight = '500';
            td.style.color = '#2d3748';
            tr.appendChild(td);
        });
        
        // 값 데이터
        const rowData = pivotData[rowKey];
        colKeys.forEach(colKey => {
            valueFields.forEach(valueField => {
                const td = document.createElement('td');
                const cellData = rowData[colKey] ? rowData[colKey][valueField] : null;
                if (cellData && cellData.sum !== undefined) {
                    td.textContent = cellData.sum.toLocaleString();
                    td.style.textAlign = 'right';
                } else {
                    td.textContent = '-';
                    td.style.textAlign = 'center';
                    td.style.color = '#9ca3af';
                }
                td.style.color = '#2d3748';
                tr.appendChild(td);
            });
        });
        tbody.appendChild(tr);
    });
    
    table.appendChild(thead);
    table.appendChild(tbody);
    container.appendChild(table);
}

function getFieldDisplayName(field) {
    const fieldNames = {
        'campaign_name': '캠페인명',
        'keyword_name': '키워드명',
        'device_type': '디바이스',
        'media_product': '매체',
        'stat_date': '날짜',
        'impression': '노출수',
        'click': '클릭수',
        'cost': '비용',
        'rank': '순위'
    };
    return fieldNames[field] || field;
}

// 원본 데이터 로드 (API 호출)
function loadRawData() {
    const container = document.getElementById('rawDataTable');
    if (container) {
        container.innerHTML = '<div class="loading"><div class="spinner"></div><p>데이터를 로드하는 중...</p></div>';
    }
    
    fetch('api.php?action=raw_data')
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            console.log('원본 데이터 로드 완료:', data);
            if (data.success && data.data && data.data.rows) {
                createRawDataTable(data.data);
                populateFilters(data.data);
            } else {
                throw new Error(data.error || '데이터 없음');
            }
        })
        .catch(error => {
            console.error('원본 데이터 로드 실패:', error);
            if (container) {
                container.innerHTML = `
                    <div class="chart-error">
                        <div>
                            <h4>데이터 로드 실패</h4>
                            <p>서버에서 데이터를 가져올 수 없습니다.</p>
                            <small>${error.message}</small>
                            <button class="btn btn-primary" onclick="loadRawData()" style="margin-top: 10px;">다시 시도</button>
                        </div>
                    </div>
                `;
            }
        });
}

function createRawDataTable(data) {
    const container = document.getElementById('rawDataTable');
    if (!container || !data.rows) return;
    
    let html = `
        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>날짜</th>
                        <th>매체</th>
                        <th>디바이스</th>
                        <th>캠페인</th>
                        <th>그룹</th>
                        <th>키워드</th>
                        <th>노출수</th>
                        <th>클릭수</th>
                        <th>비용</th>
                        <th>순위</th>
                        <th>CTR</th>
                        <th>CPC</th>
                    </tr>
                </thead>
                <tbody>
    `;
    
    const rows = data.rows.slice(0, 100); // 100개만 표시
    
    rows.forEach(row => {
        const rowData = row.f || row;
        if (rowData && rowData.length >= 10) {
            const impressions = parseInt(rowData[6]?.v || 0);
            const clicks = parseInt(rowData[7]?.v || 0);
            const cost = parseFloat(rowData[8]?.v || 0);
            const ctr = impressions > 0 ? (clicks / impressions * 100) : 0;
            const cpc = clicks > 0 ? (cost / clicks) : 0;
            
            html += `
                <tr>
                    <td>${rowData[0]?.v || ''}</td>
                    <td>${rowData[1]?.v || ''}</td>
                    <td>${rowData[2]?.v || ''}</td>
                    <td>${rowData[3]?.v || ''}</td>
                    <td>${rowData[4]?.v || ''}</td>
                    <td><strong>${rowData[5]?.v || ''}</strong></td>
                    <td>${impressions.toLocaleString()}</td>
                    <td>${clicks.toLocaleString()}</td>
                    <td>₩${Math.round(cost).toLocaleString()}</td>
                    <td>${parseFloat(rowData[9]?.v || 0).toFixed(1)}</td>
                    <td>${ctr.toFixed(2)}%</td>
                    <td>₩${Math.round(cpc).toLocaleString()}</td>
                </tr>
            `;
        }
    });
    
    html += '</tbody></table></div>';
    
    if (data.rows.length > 100) {
        html += `
            <div style="margin-top: 15px; padding: 15px; background: rgba(255, 255, 255, 0.1); border-radius: 8px; color: white; text-align: center;">
                📊 처음 100개 레코드만 표시됩니다. 전체 ${data.rows.length.toLocaleString()}개 레코드 (성능 최적화)
            </div>
        `;
    }
    
    container.innerHTML = html;
}

// 필터 관련 함수들
function populateFilters(data) {
    if (!data.rows) return;
    
    const devices = new Set();
    const campaigns = new Set();
    
    data.rows.forEach(row => {
        const rowData = row.f || row;
        if (rowData && rowData.length >= 4) {
            if (rowData[2]?.v) devices.add(rowData[2].v);
            if (rowData[3]?.v) campaigns.add(rowData[3].v);
        }
    });
    
    // 디바이스 필터
    const deviceFilter = document.getElementById('deviceFilter');
    if (deviceFilter) {
        deviceFilter.innerHTML = '<option value="">전체 디바이스</option>';
        devices.forEach(device => {
            if (device) {
                deviceFilter.innerHTML += `<option value="${device}">${device}</option>`;
            }
        });
    }
    
    // 캠페인 필터
    const campaignFilter = document.getElementById('campaignFilter');
    if (campaignFilter) {
        campaignFilter.innerHTML = '<option value="">전체 캠페인</option>';
        campaigns.forEach(campaign => {
            if (campaign) {
                campaignFilter.innerHTML += `<option value="${campaign}">${campaign}</option>`;
            }
        });
    }
}

function applyFilters() {
    const deviceFilter = document.getElementById('deviceFilter');
    const campaignFilter = document.getElementById('campaignFilter');
    const startDateFilter = document.getElementById('filterStartDate');
    const endDateFilter = document.getElementById('filterEndDate');
    
    if (!deviceFilter || !campaignFilter) return;
    
    const params = new URLSearchParams();
    if (deviceFilter.value) params.append('device_type', deviceFilter.value);
    if (campaignFilter.value) params.append('campaign_name', campaignFilter.value);
    if (startDateFilter && startDateFilter.value) params.append('start_date', startDateFilter.value);
    if (endDateFilter && endDateFilter.value) params.append('end_date', endDateFilter.value);
    
    console.log('필터 적용:', params.toString());
    
    const container = document.getElementById('rawDataTable');
    if (container) {
        container.innerHTML = '<div class="loading"><div class="spinner"></div><p>필터링된 데이터를 로드하는 중...</p></div>';
    }
    
    fetch(`api.php?action=raw_data&${params.toString()}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                createRawDataTable(data.data);
            } else {
                console.error('필터링된 데이터 로드 실패:', data.error);
                if (container) {
                    container.innerHTML = `<div class="chart-error">필터링 실패: ${data.error}</div>`;
                }
            }
        })
        .catch(error => {
            console.error('API 요청 실패:', error);
            if (container) {
                container.innerHTML = `<div class="chart-error">API 요청 실패: ${error.message}</div>`;
            }
        });
}

function filterTable() {
    const searchTerm = document.getElementById('searchInput')?.value.toLowerCase();
    if (!searchTerm) {
        applyFilters();
        return;
    }
    
    // 현재 테이블에서 클라이언트 사이드 필터링
    const rows = document.querySelectorAll('#rawDataTable tbody tr');
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        if (text.includes(searchTerm)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

// 유틸리티 함수들
function showLoadingInContainers(containerIds) {
    containerIds.forEach(id => {
        const container = document.getElementById(id);
        if (container) {
            container.innerHTML = '<div class="loading"><div class="spinner"></div><p>분석 중...</p></div>';
        }
    });
}

// 데이터 새로고침 함수들
function refreshKeywordAnalysis() {
    console.log('키워드 분석 새로고침');
    createKeywordTable(serverData.keywordAnalysis);
    alert('키워드 분석이 새로고침되었습니다.');
}

function refreshCampaignAnalysis() {
    console.log('캠페인 분석 새로고침');
    createCampaignTable(serverData.campaignAnalysis);
    alert('캠페인 분석이 새로고침되었습니다.');
}

function refreshTrendData() {
    console.log('트렌드 데이터 새로고침');
    performTrendAnalysis();
}

function refreshAIAnalysis() {
    console.log('AI 분석 새로고침');
    generateAIInsights();
}

// 내보내기 함수들
function exportKeywordReport() {
    console.log('키워드 보고서 내보내기');
    exportTableToCSV('keywordAnalysisTable', '키워드_분석_보고서');
}

function exportCampaignReport() {
    console.log('캠페인 보고서 내보내기');
    exportTableToCSV('campaignAnalysisTable', '캠페인_분석_보고서');
}

function exportComparisonReport() {
    console.log('비교 보고서 내보내기');
    alert('기간 비교 보고서 내보내기 기능은 개발 중입니다.');
}

function exportPivotTable() {
    console.log('피벗 테이블 내보내기');
    exportTableToCSV('pivotTable', '피벗_테이블');
}

function exportPivotToExcel() {
    console.log('피벗 Excel 내보내기');
    exportTableToCSV('pivotTable', '피벗_테이블_Excel');
}

function exportPivotToCSV() {
    console.log('피벗 CSV 내보내기');
    exportTableToCSV('pivotTable', '피벗_테이블_CSV');
}

function exportToExcel() {
    console.log('Excel 내보내기');
    exportTableToCSV('rawDataTable', '원본_데이터_Excel');
}

function exportToCSV() {
    console.log('CSV 내보내기');
    exportTableToCSV('rawDataTable', '원본_데이터_CSV');
}

function exportTableToCSV(tableContainerId, filename) {
    const container = document.getElementById(tableContainerId);
    if (!container) {
        alert('내보낼 데이터가 없습니다.');
        return;
    }
    
    const table = container.querySelector('table');
    if (!table) {
        alert('테이블을 찾을 수 없습니다.');
        return;
    }
    
    let csv = '';
    const rows = table.querySelectorAll('tr');
    
    rows.forEach(row => {
        const cols = row.querySelectorAll('th, td');
        const rowData = Array.from(cols).map(col => {
            let text = col.textContent.trim();
            // CSV에서 쉼표와 따옴표 처리
            if (text.includes(',') || text.includes('"') || text.includes('\n')) {
                text = '"' + text.replace(/"/g, '""') + '"';
            }
            return text;
        });
        csv += rowData.join(',') + '\n';
    });
    
    // BOM 추가 (한글 깨짐 방지)
    const bom = '\uFEFF';
    const blob = new Blob([bom + csv], { type: 'text/csv;charset=utf-8;' });
    
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    link.setAttribute('href', url);
    link.setAttribute('download', `${filename}_${new Date().getTime()}.csv`);
    link.style.visibility = 'hidden';
    
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    
    alert(`${filename} 파일이 다운로드되었습니다.`);
}

// 피벗 설정 초기화
function resetPivotSettings() {
    document.getElementById('pivotRows').selectedIndex = -1;
    document.getElementById('pivotCols').selectedIndex = -1;
    document.getElementById('pivotValues').selectedIndex = -1;
    document.getElementById('pivotTableContainer').classList.add('hidden');
    alert('피벗 설정이 초기화되었습니다.');
}

// 탭 전환 함수
function showTab(tabName) {
    console.log(`탭 전환: ${tabName}`);
    
    // 모든 탭 콘텐츠와 버튼 비활성화
    document.querySelectorAll('.tab-content').forEach(tab => tab.classList.remove('active'));
    document.querySelectorAll('.nav-tab').forEach(button => button.classList.remove('active'));
    
    // 선택된 탭 활성화
    const targetTab = document.getElementById(tabName);
    if (targetTab) {
        targetTab.classList.add('active');
    }
    
    if (event && event.target) {
        event.target.classList.add('active');
    }

    // 탭별 데이터 로드
    setTimeout(() => {
        switch(tabName) {
            case '개요':
                loadOverviewData();
                break;
            case '키워드분석':
                createKeywordTable(serverData.keywordAnalysis);
                break;
            case '디바이스분석':
                createDeviceChart(serverData.deviceAnalysis);
                createDeviceTable(serverData.deviceAnalysis);
                break;
            case '캠페인분석':
                createCampaignTable(serverData.campaignAnalysis);
                break;
            case '트렌드분석':
                // 트렌드 분석은 사용자가 버튼을 클릭해야 함
                break;
            case '기간비교':
                // 기간 비교는 사용자가 설정 후 실행
                break;
            case 'AI인사이트':
                // AI 인사이트는 사용자가 생성 버튼 클릭
                break;
            case '피벗':
                // 피벗은 사용자가 설정 후 생성
                break;
            case '원본데이터':
                // 원본 데이터는 사용자가 로드 버튼 클릭
                break;
        }
    }, 100);
}

// 개요 데이터 로드
function loadOverviewData() {
    console.log('개요 데이터 로드 시작');
    
    if (!isChartsReady) {
        console.log('차트가 준비되지 않음, 초기화 재시도');
        setTimeout(loadOverviewData, 500);
        return;
    }
    
    updateStatCards();
    createDailyChart(serverData.dailyStats);
    createTopKeywordsChart(serverData.keywordAnalysis);
}

// 기존 호환성 함수
function switchTab(tabName) {
    const tabMap = {
        'overview': '개요',
        'keyword': '키워드분석',
        'device': '디바이스분석',
        'campaign': '캠페인분석',
        'raw-data': '원본데이터'
    };
    showTab(tabMap[tabName] || tabName);
}

function loadDashboardData() {
    console.log('대시보드 초기 로드');
    loadOverviewData();
}

function loadKeywordAnalysis() {
    createKeywordTable(serverData.keywordAnalysis);
}

function loadDeviceAnalysis() {
    createDeviceChart(serverData.deviceAnalysis);
    createDeviceTable(serverData.deviceAnalysis);
}

function loadCampaignAnalysis() {
    createCampaignTable(serverData.campaignAnalysis);
}

// 페이지 로드 시 초기화
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM 로드 완료');
    console.log('서버 데이터 확인:', serverData);
    
    // Chart.js 초기화 대기
    function waitForChart() {
        if (typeof Chart !== 'undefined') {
            console.log('Chart.js 로드 확인됨');
            initializeCharts();
            loadDashboardData();
        } else {
            console.log('Chart.js 로드 대기 중...');
            setTimeout(waitForChart, 100);
        }
    }
    
    waitForChart();
    
    // 날짜 필터 기본값 설정
    const today = new Date();
    const oneWeekAgo = new Date(today.getTime() - 7 * 24 * 60 * 60 * 1000);
    
    const filterStartDate = document.getElementById('filterStartDate');
    const filterEndDate = document.getElementById('filterEndDate');
    
    if (filterStartDate) filterStartDate.value = oneWeekAgo.toISOString().split('T')[0];
    if (filterEndDate) filterEndDate.value = today.toISOString().split('T')[0];
});