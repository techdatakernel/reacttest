<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JSON 저장/조회/필터/페이지네이션 - AEO 분석 결과 그리드 관리 v4 (v10 호환)</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
            min-height: 100vh;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header h1 {
            font-size: 28px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .stats-summary {
            display: flex;
            gap: 20px;
            font-size: 14px;
        }
        
        .stat-item {
            text-align: center;
        }
        
        .stat-value {
            font-size: 24px;
            font-weight: bold;
            display: block;
        }
        
        .filter-section {
            padding: 25px 30px;
            background: #f8f9fa;
            border-bottom: 1px solid #e9ecef;
        }
        
        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .filter-item label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #495057;
            font-size: 14px;
        }
        
        .filter-item input,
        .filter-item select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ced4da;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .filter-item input:focus,
        .filter-item select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .filter-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        .data-grid {
            padding: 0;
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        thead {
            background: #f8f9fa;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        
        th {
            padding: 15px 12px;
            text-align: left;
            font-weight: 600;
            color: #495057;
            border-bottom: 2px solid #dee2e6;
            font-size: 13px;
            cursor: pointer;
            user-select: none;
            white-space: nowrap;
        }
        
        th:hover {
            background: #e9ecef;
        }
        
        th.sortable::after {
            content: ' ⇅';
            opacity: 0.3;
        }
        
        th.sort-asc::after {
            content: ' ↑';
            opacity: 1;
        }
        
        th.sort-desc::after {
            content: ' ↓';
            opacity: 1;
        }
        
        td {
            padding: 15px 12px;
            border-bottom: 1px solid #e9ecef;
            font-size: 14px;
        }
        
        tbody tr {
            transition: background-color 0.2s;
        }
        
        tbody tr:hover {
            background: #f8f9fa;
        }
        
        .score-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 13px;
        }
        
        .eval-excellent {
            background: #d4edda;
            color: #155724;
        }
        
        .eval-good {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .eval-warning {
            background: #fff3cd;
            color: #856404;
        }
        
        .eval-danger {
            background: #f8d7da;
            color: #721c24;
        }
        
        .query-cell {
            max-width: 300px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .url-cell {
            max-width: 350px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            color: #667eea;
        }
        
        .url-cell a {
            color: inherit;
            text-decoration: none;
        }
        
        .url-cell a:hover {
            text-decoration: underline;
        }
        
        .action-btn {
            padding: 6px 12px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            transition: all 0.3s;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .action-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 3px 10px rgba(102, 126, 234, 0.3);
        }
        
        .pagination {
            padding: 20px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #f8f9fa;
            border-top: 1px solid #e9ecef;
        }
        
        .pagination-info {
            color: #6c757d;
            font-size: 14px;
        }
        
        .pagination-controls {
            display: flex;
            gap: 5px;
        }
        
        .page-btn {
            padding: 8px 12px;
            border: 1px solid #dee2e6;
            background: white;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .page-btn:hover:not(:disabled) {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }
        
        .page-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .page-btn.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-color: transparent;
        }
        
        /* 모달 스타일 */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            animation: fadeIn 0.3s;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        .modal-content {
            position: relative;
            background: white;
            width: 90%;
            max-width: 900px;
            max-height: 90vh;
            margin: 50px auto;
            border-radius: 16px;
            overflow: hidden;
            animation: slideUp 0.3s;
        }
        
        @keyframes slideUp {
            from { 
                opacity: 0;
                transform: translateY(50px);
            }
            to { 
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .modal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-header h2 {
            font-size: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .close-btn {
            background: rgba(255,255,255,0.2);
            border: none;
            color: white;
            font-size: 24px;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
            line-height: 1;
        }
        
        .close-btn:hover {
            background: rgba(255,255,255,0.3);
            transform: rotate(90deg);
        }
        
        .modal-body {
            padding: 30px;
            max-height: calc(90vh - 160px);
            overflow-y: auto;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: 150px 1fr;
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .info-label {
            font-weight: 600;
            color: #495057;
        }
        
        .info-value {
            color: #212529;
        }
        
        .tabs {
            display: flex;
            border-bottom: 2px solid #e9ecef;
            margin-bottom: 20px;
        }
        
        .tab {
            padding: 12px 24px;
            cursor: pointer;
            border: none;
            background: none;
            font-size: 15px;
            font-weight: 600;
            color: #6c757d;
            transition: all 0.3s;
            position: relative;
        }
        
        .tab:hover {
            color: #667eea;
        }
        
        .tab.active {
            color: #667eea;
        }
        
        .tab.active::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
            animation: fadeIn 0.3s;
        }
        
        .concept-description {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            border-left: 4px solid #667eea;
        }
        
        .concept-description h4 {
            color: #495057;
            font-size: 16px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .concept-description p {
            color: #6c757d;
            font-size: 14px;
            line-height: 1.7;
            margin: 0;
        }
        
        .keyword-list {
            display: grid;
            gap: 15px;
        }
        
        .keyword-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #667eea;
        }
        
        .keyword-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .keyword-term {
            font-weight: 600;
            color: #212529;
            font-size: 16px;
        }
        
        .keyword-score {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
        }
        
        .keyword-details {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            font-size: 13px;
            color: #6c757d;
        }
        
        .recommendation-section {
            margin-bottom: 25px;
        }
        
        .recommendation-section h3 {
            color: #495057;
            font-size: 16px;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e9ecef;
        }
        
        .recommendation-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 10px;
        }
        
        .recommendation-title {
            font-weight: 600;
            color: #212529;
            margin-bottom: 5px;
        }
        
        .recommendation-text {
            color: #6c757d;
            font-size: 14px;
            line-height: 1.6;
        }
        
        .json-viewer {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            overflow-x: auto;
        }
        
        .json-viewer pre {
            margin: 0;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            line-height: 1.6;
            color: #212529;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #6c757d;
        }
        
        .empty-state-icon {
            font-size: 64px;
            margin-bottom: 20px;
            opacity: 0.3;
        }
        
        .loading {
            display: none;
            text-align: center;
            padding: 40px;
            color: #6c757d;
        }
        
        .loading.active {
            display: block;
        }
        
        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #667eea;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📊 분석 결과 목록</h1>
            <div class="stats-summary">
                <div class="stat-item">
                    <span class="stat-value" id="totalCount">0</span>
                    <span>전체</span>
                </div>
                <div class="stat-item">
                    <span class="stat-value" id="filteredCount">0</span>
                    <span>표시</span>
                </div>
            </div>
        </div>
        
        <div class="filter-section">
            <div class="filter-grid">
                <div class="filter-item">
                    <label>📅 연도-월-일</label>
                    <input type="date" id="filterDate">
                </div>
                <div class="filter-item">
                    <label>📝 질문</label>
                    <input type="text" id="filterQuery" placeholder="질문 검색...">
                </div>
                <div class="filter-item">
                    <label>⚡ 평가</label>
                    <select id="filterEval">
                        <option value="">전체</option>
                        <option value="우수">우수</option>
                        <option value="양호">양호</option>
                        <option value="개선필요">개선필요</option>
                        <option value="위험">위험</option>
                    </select>
                </div>
                <div class="filter-item">
                    <label>🏷️ 타입</label>
                    <select id="filterType">
                        <option value="">전체</option>
                        <option value="정보형">정보형</option>
                        <option value="비교형">비교형</option>
                        <option value="위치 정보">위치 정보</option>
                        <option value="정보 요청형">정보 요청형</option>
                        <option value="정보 조회형">정보 조회형</option>
                        <option value="장소 추천형">장소 추천형</option>
                    </select>
                </div>
                <div class="filter-item">
                    <label>🎯 정렬</label>
                    <select id="sortBy">
                        <option value="timestamp">최신순</option>
                        <option value="hybrid_score_desc">점수 높은순</option>
                        <option value="hybrid_score_asc">점수 낮은순</option>
                    </select>
                </div>
            </div>
            <div class="filter-actions">
                <button class="btn btn-secondary" onclick="resetFilters()">🔄 초기화</button>
                <button class="btn btn-primary" onclick="applyFilters()">🔍 검색</button>
            </div>
        </div>
        
        <div class="loading" id="loading">
            <div class="spinner"></div>
            <p>데이터를 불러오는 중...</p>
        </div>
        
        <div class="data-grid">
            <table>
                <thead>
                    <tr>
                        <th class="sortable" onclick="sortTable('id')">ID</th>
                        <th class="sortable" onclick="sortTable('query')">질문</th>
                        <th class="sortable" onclick="sortTable('url')">URL</th>
                        <th class="sortable" onclick="sortTable('hybrid_score')">점수</th>
                        <th class="sortable" onclick="sortTable('evaluation')">평가</th>
                        <th class="sortable" onclick="sortTable('date')">날짜</th>
                        <th class="sortable" onclick="sortTable('query_type')">타입</th>
                        <th>작업</th>
                    </tr>
                </thead>
                <tbody id="dataTableBody">
                    <!-- 데이터가 동적으로 삽입됩니다 -->
                </tbody>
            </table>
        </div>
        
        <div class="pagination">
            <div class="pagination-info">
                <span id="pageInfo">전체 0개 중 0개 표시</span>
            </div>
            <div class="pagination-controls" id="paginationControls">
                <!-- 페이지네이션 버튼이 동적으로 생성됩니다 -->
            </div>
        </div>
    </div>
    
    <!-- 상세보기 모달 -->
    <div class="modal" id="detailModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>📋 분석 결과 상세 보기</h2>
                <button class="close-btn" onclick="closeModal()">×</button>
            </div>
            <div class="modal-body">
                <div class="info-grid">
                    <div class="info-label">질문</div>
                    <div class="info-value" id="modalQuery"></div>
                    
                    <div class="info-label">URL</div>
                    <div class="info-value"><a id="modalUrl" href="#" target="_blank"></a></div>
                    
                    <div class="info-label">하이브리드 점수</div>
                    <div class="info-value" id="modalScore"></div>
                    
                    <div class="info-label">평가</div>
                    <div class="info-value" id="modalEval"></div>
                </div>
                
                <div class="tabs">
                    <button class="tab active" onclick="switchTab('keywords')">🔤 키워드 (BM25)</button>
                    <button class="tab" onclick="switchTab('semantic')">🧠 시맨틱</button>
                    <button class="tab" onclick="switchTab('improvements')">💡 개선</button>
                    <button class="tab" onclick="switchTab('raw')">📄 원본</button>
                </div>
                
                <div id="keywordsTab" class="tab-content active">
                    <div class="concept-description">
                        <h4>📚 BM25란?</h4>
                        <p>BM25(Best Matching 25)는 검색 엔진에서 사용되는 키워드 기반 관련성 평가 알고리즘입니다. 문서 내 키워드의 빈도(TF), 위치, 희소성(IDF) 등을 종합적으로 분석하여 검색어와 문서의 매칭도를 점수화합니다.</p>
                    </div>
                    <div id="keywordsList"></div>
                </div>
                
                <div id="semanticTab" class="tab-content">
                    <div class="concept-description">
                        <h4>📚 시맨틱 분석이란?</h4>
                        <p>시맨틱(Semantic) 분석은 AI가 텍스트의 의미를 이해하고 평가하는 방식입니다. 단순 키워드 매칭을 넘어 주제 일치도, 의미적 관련성, 맥락 이해도, 정보 충분성 등을 종합적으로 분석하여 콘텐츠의 품질을 평가합니다.</p>
                    </div>
                    <div id="semanticContent"></div>
                </div>
                
                <div id="improvementsTab" class="tab-content">
                    <div class="concept-description">
                        <h4>📚 AEO 개선 제안이란?</h4>
                        <p>Answer Engine Optimization(AEO) 개선 제안은 AI 검색 엔진에서 더 나은 답변 제공을 위한 최적화 방안입니다. 누락된 정보 보완, 우선순위 작업, 콘텐츠 개선 방향 등을 제시합니다.</p>
                    </div>
                    <div id="improvementsList"></div>
                </div>
                
                <div id="rawTab" class="tab-content">
                    <div class="concept-description">
                        <h4>📚 원본 JSON 데이터</h4>
                        <p>API로부터 받은 전체 분석 결과의 원본 데이터입니다. 모든 분석 지표와 메타데이터를 포함하고 있습니다.</p>
                    </div>
                    <div class="json-viewer">
                        <pre id="rawJson"></pre>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        let allData = [];
        let filteredData = [];
        let currentPage = 1;
        const itemsPerPage = 10;
        let currentSort = { field: 'timestamp', direction: 'desc' };
        
        // 데이터 로드
        async function loadData() {
            const loading = document.getElementById('loading');
            loading.classList.add('active');
            
            try {
                const response = await fetch('aeo_data/index.json');
                const indexData = await response.json();
                
                allData = Object.values(indexData).map(item => ({
                    ...item,
                    date: item.timestamp ? item.timestamp.split(' ')[0] : item.date || ''
                }));
                
                filteredData = [...allData];
                updateStats();
                applyFilters();
            } catch (error) {
                console.error('데이터 로드 오류:', error);
                document.getElementById('dataTableBody').innerHTML = `
                    <tr>
                        <td colspan="8" class="empty-state">
                            <div class="empty-state-icon">⚠️</div>
                            <p>데이터를 불러오는데 실패했습니다.</p>
                        </td>
                    </tr>
                `;
            } finally {
                loading.classList.remove('active');
            }
        }
        
        // 통계 업데이트
        function updateStats() {
            document.getElementById('totalCount').textContent = allData.length;
            document.getElementById('filteredCount').textContent = filteredData.length;
        }
        
        // 필터 적용
        function applyFilters() {
            const dateFilter = document.getElementById('filterDate').value;
            const queryFilter = document.getElementById('filterQuery').value.toLowerCase();
            const evalFilter = document.getElementById('filterEval').value;
            const typeFilter = document.getElementById('filterType').value;
            const sortBy = document.getElementById('sortBy').value;
            
            filteredData = allData.filter(item => {
                const dateMatch = !dateFilter || item.date === dateFilter;
                const queryMatch = !queryFilter || item.query.toLowerCase().includes(queryFilter);
                const evalMatch = !evalFilter || item.evaluation === evalFilter;
                const typeMatch = !typeFilter || item.query_type === typeFilter;
                
                return dateMatch && queryMatch && evalMatch && typeMatch;
            });
            
            // 정렬
            switch(sortBy) {
                case 'timestamp':
                    filteredData.sort((a, b) => new Date(b.timestamp) - new Date(a.timestamp));
                    break;
                case 'hybrid_score_desc':
                    filteredData.sort((a, b) => b.hybrid_score - a.hybrid_score);
                    break;
                case 'hybrid_score_asc':
                    filteredData.sort((a, b) => a.hybrid_score - b.hybrid_score);
                    break;
            }
            
            updateStats();
            currentPage = 1;
            renderTable();
            renderPagination();
        }
        
        // 필터 초기화
        function resetFilters() {
            document.getElementById('filterDate').value = '';
            document.getElementById('filterQuery').value = '';
            document.getElementById('filterEval').value = '';
            document.getElementById('filterType').value = '';
            document.getElementById('sortBy').value = 'timestamp';
            applyFilters();
        }
        
        // 테이블 렌더링
        function renderTable() {
            const tbody = document.getElementById('dataTableBody');
            const start = (currentPage - 1) * itemsPerPage;
            const end = start + itemsPerPage;
            const pageData = filteredData.slice(start, end);
            
            if (pageData.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="8" class="empty-state">
                            <div class="empty-state-icon">📭</div>
                            <p>표시할 데이터가 없습니다.</p>
                        </td>
                    </tr>
                `;
                return;
            }
            
            tbody.innerHTML = pageData.map(item => {
                const evalClass = getEvalClass(item.evaluation);
                const shortId = item.id.substring(0, 8);
                const queryType = item.query_type || '-';
                const typeTitle = item.query_type ? '' : 'title="분석 시 타입 정보가 기록되지 않았습니다"';
                
                return `
                    <tr>
                        <td>${shortId}</td>
                        <td class="query-cell" title="${item.query}">${item.query}</td>
                        <td class="url-cell"><a href="${item.url}" target="_blank" title="${item.url}">${item.url}</a></td>
                        <td><span class="score-badge ${evalClass}">${item.hybrid_score}</span></td>
                        <td><span class="score-badge ${evalClass}">${item.evaluation || '-'}</span></td>
                        <td>${item.date || '-'}</td>
                        <td ${typeTitle}>${queryType}</td>
                        <td><button class="action-btn" onclick="showDetail('${item.id}')">📊</button></td>
                    </tr>
                `;
            }).join('');
            
            // 페이지 정보 업데이트
            const total = filteredData.length;
            const showing = Math.min(end, total);
            document.getElementById('pageInfo').textContent = 
                `전체 ${total}개 중 ${start + 1}-${showing}개 표시`;
        }
        
        // 평가 등급별 클래스
        function getEvalClass(evaluation) {
            switch(evaluation) {
                case '우수': return 'eval-excellent';
                case '양호': return 'eval-good';
                case '개선필요': return 'eval-warning';
                case '위험': return 'eval-danger';
                default: return '';
            }
        }
        
        // 페이지네이션 렌더링
        function renderPagination() {
            const totalPages = Math.ceil(filteredData.length / itemsPerPage);
            const controls = document.getElementById('paginationControls');
            
            let html = `
                <button class="page-btn" onclick="changePage(1)" ${currentPage === 1 ? 'disabled' : ''}>«</button>
                <button class="page-btn" onclick="changePage(${currentPage - 1})" ${currentPage === 1 ? 'disabled' : ''}>‹</button>
            `;
            
            for (let i = 1; i <= totalPages; i++) {
                if (i === 1 || i === totalPages || (i >= currentPage - 2 && i <= currentPage + 2)) {
                    html += `<button class="page-btn ${i === currentPage ? 'active' : ''}" onclick="changePage(${i})">${i}</button>`;
                } else if (i === currentPage - 3 || i === currentPage + 3) {
                    html += `<button class="page-btn" disabled>...</button>`;
                }
            }
            
            html += `
                <button class="page-btn" onclick="changePage(${currentPage + 1})" ${currentPage === totalPages ? 'disabled' : ''}>›</button>
                <button class="page-btn" onclick="changePage(${totalPages})" ${currentPage === totalPages ? 'disabled' : ''}>»</button>
            `;
            
            controls.innerHTML = html;
        }
        
        // 페이지 변경
        function changePage(page) {
            const totalPages = Math.ceil(filteredData.length / itemsPerPage);
            if (page < 1 || page > totalPages) return;
            currentPage = page;
            renderTable();
            renderPagination();
        }
        
        // 테이블 정렬
        function sortTable(field) {
            if (currentSort.field === field) {
                currentSort.direction = currentSort.direction === 'asc' ? 'desc' : 'asc';
            } else {
                currentSort.field = field;
                currentSort.direction = 'asc';
            }
            
            filteredData.sort((a, b) => {
                let aVal = a[field];
                let bVal = b[field];
                
                if (typeof aVal === 'string') {
                    aVal = aVal.toLowerCase();
                    bVal = bVal.toLowerCase();
                }
                
                if (aVal < bVal) return currentSort.direction === 'asc' ? -1 : 1;
                if (aVal > bVal) return currentSort.direction === 'asc' ? 1 : -1;
                return 0;
            });
            
            // 정렬 표시 업데이트
            document.querySelectorAll('th.sortable').forEach(th => {
                th.classList.remove('sort-asc', 'sort-desc');
            });
            
            const sortedTh = document.querySelector(`th.sortable[onclick*="${field}"]`);
            if (sortedTh) {
                sortedTh.classList.add(`sort-${currentSort.direction}`);
            }
            
            renderTable();
        }
        
        // 상세 정보 표시
        async function showDetail(id) {
            const item = allData.find(d => d.id === id);
            if (!item) return;
            
            // 기본 정보 표시
            document.getElementById('modalQuery').textContent = item.query;
            document.getElementById('modalUrl').textContent = item.url;
            document.getElementById('modalUrl').href = item.url;
            document.getElementById('modalScore').textContent = item.hybrid_score;
            document.getElementById('modalEval').innerHTML = 
                `<span class="score-badge ${getEvalClass(item.evaluation)}">${item.evaluation || '-'}</span>`;
            
            // 상세 JSON 파일 로드 (ID의 앞 8자리만 사용)
            try {
                const date = item.date || item.timestamp.split(' ')[0];
                const shortId = id.substring(0, 8);
                const response = await fetch(`aeo_data/${date}/${date}_${shortId}.json`);
                const detailData = await response.json();
                
                // 키워드 탭
                renderKeywords(detailData.bm25);
                
                // 시맨틱 탭
                renderSemantic(detailData.semantic);
                
                // 개선사항 탭
                renderImprovements(detailData.aeo_recommendations);
                
                // 원본 JSON 탭
                document.getElementById('rawJson').textContent = JSON.stringify(detailData, null, 2);
            } catch (error) {
                console.error('상세 데이터 로드 오류:', error);
                document.getElementById('keywordsList').innerHTML = '<p>데이터를 불러올 수 없습니다.</p>';
                document.getElementById('semanticContent').innerHTML = '<p>데이터를 불러올 수 없습니다.</p>';
                document.getElementById('improvementsList').innerHTML = '<p>데이터를 불러올 수 없습니다.</p>';
                document.getElementById('rawJson').textContent = 'undefined';
            }
            
            // 모달 표시
            document.getElementById('detailModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }
        
        // 키워드 렌더링
        function renderKeywords(bm25Data) {
            const container = document.getElementById('keywordsList');
            
            if (!bm25Data || !bm25Data.keywords || bm25Data.keywords.length === 0) {
                container.innerHTML = '<p class="empty-state">키워드 정보가 없습니다.</p>';
                return;
            }
            
            let html = `
                <div style="margin-bottom: 20px;">
                    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; margin-bottom: 20px;">
                        <div class="recommendation-item">
                            <div class="recommendation-title">📊 총점</div>
                            <div class="recommendation-text">${bm25Data.total_score || 0}점</div>
                        </div>
                        ${(bm25Data.strengths || bm25Data.strength) ? `
                            <div class="recommendation-item" style="grid-column: 1 / -1;">
                                <div class="recommendation-title">💪 강점</div>
                                <div class="recommendation-text">${bm25Data.strengths || bm25Data.strength || '-'}</div>
                            </div>
                        ` : ''}
                        ${(bm25Data.weaknesses || bm25Data.weakness) ? `
                            <div class="recommendation-item" style="grid-column: 1 / -1;">
                                <div class="recommendation-title">⚠️ 약점</div>
                                <div class="recommendation-text">${bm25Data.weaknesses || bm25Data.weakness || '-'}</div>
                            </div>
                        ` : ''}
                    </div>
                </div>
            `;
            
            html += '<div class="keyword-list">';
            html += bm25Data.keywords.map(kw => `
                <div class="keyword-item">
                    <div class="keyword-header">
                        <span class="keyword-term">${kw.keyword}</span>
                        <span class="keyword-score">${kw.bm25_score || kw.score || 0}점</span>
                    </div>
                    <div class="keyword-details">
                        <div><strong>빈도:</strong> ${kw.tf || 0}</div>
                        <div><strong>관련도:</strong> ${kw.relevance || kw.rarity || '-'}</div>
                        ${kw.idf_estimate ? `<div><strong>IDF:</strong> ${kw.idf_estimate}</div>` : ''}
                        ${kw.position ? `<div><strong>위치:</strong> ${kw.position}</div>` : ''}
                    </div>
                    ${kw.reason ? `
                        <div style="margin-top: 10px; color: #6c757d; font-size: 13px;">
                            ${kw.reason}
                        </div>
                    ` : ''}
                </div>
            `).join('');
            html += '</div>';
            
            container.innerHTML = html;
        }
        
        // 시맨틱 렌더링
        function renderSemantic(semanticData) {
            const container = document.getElementById('semanticContent');
            
            if (!semanticData) {
                container.innerHTML = '<p class="empty-state">시맨틱 정보가 없습니다.</p>';
                return;
            }
            
            // v10 형식 처리 (객체 형태)
            const topicMatch = semanticData.topic_match?.score ?? semanticData.topic_match ?? 0;
            const topicMatchReason = semanticData.topic_match?.reason ?? semanticData.topic_match_reason ?? '-';
            
            const semanticRelevance = semanticData.semantic_relevance?.score ?? semanticData.semantic_relevance ?? 0;
            const semanticRelevanceReason = semanticData.semantic_relevance?.reason ?? semanticData.semantic_relevance_reason ?? '-';
            
            const contextUnderstanding = semanticData.context_understanding?.score ?? semanticData.context_understanding ?? 0;
            const contextUnderstandingReason = semanticData.context_understanding?.reason ?? semanticData.context_understanding_reason ?? '-';
            
            const infoCompleteness = semanticData.information_completeness?.score ?? semanticData.information_sufficiency ?? 0;
            const infoCompletenessReason = semanticData.information_completeness?.reason ?? semanticData.information_sufficiency_reason ?? '-';
            
            const totalScore = semanticData.total_score ?? semanticData.total_semantic_score ?? 0;
            
            let html = `
                <div style="margin-bottom: 20px;">
                    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; margin-bottom: 20px;">
                        <div class="recommendation-item">
                            <div class="recommendation-title">📊 총 시맨틱 점수</div>
                            <div class="recommendation-text" style="font-size: 24px; font-weight: bold; color: #667eea;">
                                ${totalScore}점 / 48점
                            </div>
                        </div>
                        <div class="recommendation-item">
                            <div class="recommendation-title">🏷️ 쿼리 타입</div>
                            <div class="recommendation-text">${semanticData.query_type || '-'}</div>
                        </div>
                    </div>
                </div>
                
                <div class="recommendation-section">
                    <h3>📈 세부 평가 지표</h3>
                    
                    <div class="recommendation-item">
                        <div class="recommendation-title">
                            🎯 주제 일치도: ${topicMatch}/10
                        </div>
                        <div class="recommendation-text">${topicMatchReason}</div>
                    </div>
                    
                    <div class="recommendation-item">
                        <div class="recommendation-title">
                            🔗 의미적 관련성: ${semanticRelevance}/10
                        </div>
                        <div class="recommendation-text">${semanticRelevanceReason}</div>
                    </div>
                    
                    <div class="recommendation-item">
                        <div class="recommendation-title">
                            💡 맥락 이해도: ${contextUnderstanding}/10
                        </div>
                        <div class="recommendation-text">${contextUnderstandingReason}</div>
                    </div>
                    
                    <div class="recommendation-item">
                        <div class="recommendation-title">
                            ✅ 정보 충분성: ${infoCompleteness}/10
                        </div>
                        <div class="recommendation-text">${infoCompletenessReason}</div>
                    </div>
                </div>
            `;
            
            if (semanticData.strengths || semanticData.weaknesses) {
                html += `
                    <div class="recommendation-section">
                        <h3>💪 강점 & 약점</h3>
                        ${semanticData.strengths ? `
                            <div class="recommendation-item">
                                <div class="recommendation-title">💪 강점</div>
                                <div class="recommendation-text">${semanticData.strengths}</div>
                            </div>
                        ` : ''}
                        ${semanticData.weaknesses ? `
                            <div class="recommendation-item">
                                <div class="recommendation-title">⚠️ 약점</div>
                                <div class="recommendation-text">${semanticData.weaknesses}</div>
                            </div>
                        ` : ''}
                    </div>
                `;
            }
            
            if (semanticData.api_metadata) {
                html += `
                    <div class="recommendation-section">
                        <h3>⚙️ API 메타데이터</h3>
                        <div class="recommendation-item">
                            <div class="recommendation-text">
                                처리 시간: ${(semanticData.api_metadata.time_ms / 1000).toFixed(2)}초
                            </div>
                        </div>
                    </div>
                `;
            }
            
            container.innerHTML = html;
        }
        
        // 개선사항 렌더링
        function renderImprovements(recommendations) {
            const container = document.getElementById('improvementsList');
            
            if (!recommendations) {
                container.innerHTML = '<p class="empty-state">개선 제안 정보가 없습니다.</p>';
                return;
            }
            
            let html = '';
            
            // 즉시 요약
            if (recommendations.immediate_summary) {
                html += `
                    <div class="recommendation-section">
                        <h3>📝 즉시 요약</h3>
                        <div class="recommendation-item">
                            <div class="recommendation-text">${recommendations.immediate_summary}</div>
                        </div>
                    </div>
                `;
            }
            
            // 누락된 정보 (v10: missing_info)
            const missingInfo = recommendations.missing_info || recommendations.missing_information || [];
            if (missingInfo.length > 0) {
                html += `
                    <div class="recommendation-section">
                        <h3>❌ 누락된 정보</h3>
                        ${missingInfo.map(item => `
                            <div class="recommendation-item">
                                <div class="recommendation-title">${item.item || item.info}</div>
                                <div class="recommendation-text">
                                    <strong>이유:</strong> ${item.reason}<br>
                                    ${item.effect ? `<strong>효과:</strong> ${item.effect}` : ''}
                                </div>
                            </div>
                        `).join('')}
                    </div>
                `;
            }
            
            // 실행 액션 (v10: action_items)
            const actionItems = recommendations.action_items || recommendations.priority_actions || [];
            if (actionItems.length > 0) {
                html += `
                    <div class="recommendation-section">
                        <h3>🎯 실행 액션</h3>
                        ${actionItems.map(action => `
                            <div class="recommendation-item">
                                <div class="recommendation-title">${action.action}</div>
                                <div class="recommendation-text">
                                    <strong>이유:</strong> ${action.reason}<br>
                                    ${action.expected_result ? `<strong>예상 결과:</strong> ${action.expected_result}` : ''}
                                </div>
                            </div>
                        `).join('')}
                    </div>
                `;
            }
            
            // 예상 점수 증가 (v10: expected_score_increase)
            if (recommendations.expected_score_increase) {
                const scoreIncrease = recommendations.expected_score_increase;
                html += `
                    <div class="recommendation-section">
                        <h3>📈 예상 점수 증가</h3>
                        <div class="recommendation-item">
                            <div class="recommendation-text">
                                <strong>BM25:</strong> +${scoreIncrease.bm25 || 0}점<br>
                                <strong>시맨틱:</strong> +${scoreIncrease.semantic || 0}점<br>
                                <strong>FAQ:</strong> +${scoreIncrease.faq || 0}점
                            </div>
                        </div>
                    </div>
                `;
            }
            
            // 최적화 잠재력
            if (recommendations.optimization_score_potential) {
                html += `
                    <div class="recommendation-section">
                        <h3>📈 최적화 잠재력</h3>
                        <div class="recommendation-item">
                            <div class="recommendation-text">${recommendations.optimization_score_potential}</div>
                        </div>
                    </div>
                `;
            }
            
            container.innerHTML = html || '<p class="empty-state">개선 제안 정보가 없습니다.</p>';
        }
        
        // 탭 전환
        function switchTab(tabName) {
            // 탭 버튼 활성화
            document.querySelectorAll('.tab').forEach(tab => tab.classList.remove('active'));
            event.target.classList.add('active');
            
            // 탭 콘텐츠 표시
            document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
            document.getElementById(tabName + 'Tab').classList.add('active');
        }
        
        // 모달 닫기
        function closeModal() {
            document.getElementById('detailModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }
        
        // 모달 외부 클릭시 닫기
        window.onclick = function(event) {
            const modal = document.getElementById('detailModal');
            if (event.target === modal) {
                closeModal();
            }
        }
        
        // 초기화
        document.addEventListener('DOMContentLoaded', function() {
            loadData();
            
            // Enter 키로 검색
            document.querySelectorAll('.filter-item input, .filter-item select').forEach(input => {
                input.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        applyFilters();
                    }
                });
            });
        });
    </script>
</body>
</html>