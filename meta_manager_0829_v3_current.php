<?php
require_once 'config.php';
require_once 'openai_helper_v2.php';

// 액션 처리
$action = $_GET['action'] ?? 'list';
$message = '';
$error = '';

// AI 업데이트 처리
if ($action == 'ai_update') {
    header('Content-Type: application/json');
    
    $id = $_POST['id'] ?? 0;
    $page_path = $_POST['page_path'] ?? '';
    $full_url = $_POST['full_url'] ?? '';
    $brand = $_POST['brand'] ?? '';
    
    if (!$id || !$page_path || !$full_url) {
        echo json_encode(['success' => false, 'error' => '필수 파라미터가 누락되었습니다.']);
        exit;
    }
    
    try {
        $openai = new OpenAIHelper(OPENAI_API_KEY);
        $result = $openai->optimizeMetaSEO($page_path, $full_url, $brand);
        
        if ($result['success']) {
            $pdo = getConnection();
            $stmt = $pdo->prepare("UPDATE meta SET title=?, description=?, keywords=?, og_title=?, og_description=?, twitter_title=?, twitter_description=?, updated_at=NOW() WHERE id=?");
            $updateResult = $stmt->execute([
                $result['data']['title'],
                $result['data']['description'],
                $result['data']['keywords'],
                $result['data']['og_title'],
                $result['data']['og_description'],
                $result['data']['twitter_title'],
                $result['data']['twitter_description'],
                $id
            ]);
            
            if ($updateResult) {
                echo json_encode([
                    'success' => true,
                    'data' => $result['data'],
                    'message' => 'AI 최적화가 완료되었습니다.'
                ]);
            } else {
                echo json_encode(['success' => false, 'error' => '데이터베이스 업데이트 실패']);
            }
        } else {
            echo json_encode($result);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'AI 처리 중 오류: ' . $e->getMessage()]);
    }
    
    exit;
}

// 자동완성 API
if ($action == 'autocomplete') {
    header('Content-Type: application/json');
    $field = $_GET['field'] ?? '';
    $search = $_GET['search'] ?? '';
    
    if (!$field || !$search) {
        echo json_encode([]);
        exit;
    }
    
    $pdo = getConnection();
    $results = [];
    
    switch ($field) {
        case 'brand':
            $stmt = $pdo->prepare("SELECT DISTINCT brand FROM meta WHERE brand LIKE ? ORDER BY brand LIMIT 10");
            $stmt->execute(["%$search%"]);
            $results = $stmt->fetchAll(PDO::FETCH_COLUMN);
            break;
        case 'page_path':
            $results = getPagePathAutocomplete($search);
            break;
        case 'full_url':
            $stmt = $pdo->prepare("SELECT DISTINCT full_url FROM meta WHERE full_url LIKE ? ORDER BY full_url LIMIT 10");
            $stmt->execute(["%$search%"]);
            $results = $stmt->fetchAll(PDO::FETCH_COLUMN);
            break;
    }
    
    echo json_encode($results);
    exit;
}

// 메타 태그 HTML 가져오기
if ($action == 'get_meta_html' && isset($_GET['id'])) {
    $pdo = getConnection();
    $stmt = $pdo->prepare("SELECT * FROM meta WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $meta = $stmt->fetch();
    
    if ($meta) {
        echo generateMetaTags($meta);
    }
    exit;
}

// 다운로드 처리
if ($action == 'download') {
    $selected_ids = $_POST['selected_ids'] ?? [];
    
    if (empty($selected_ids)) {
        $error = '다운로드할 항목을 선택해주세요.';
    } else {
        $pdo = getConnection();
        $placeholders = str_repeat('?,', count($selected_ids) - 1) . '?';
        $stmt = $pdo->prepare("SELECT * FROM meta WHERE id IN ($placeholders)");
        $stmt->execute($selected_ids);
        $data = $stmt->fetchAll();
        
        $html_content = generateMetaTagsFile($data);
        
        header('Content-Type: text/html; charset=utf-8');
        header('Content-Disposition: attachment; filename="meta_tags_' . date('Y-m-d_H-i-s') . '.html"');
        echo $html_content;
        exit;
    }
}

// 기타 필요한 함수들
function validatePagePathAndFullUrl($page_path, $full_url) {
    $parsed_url = parse_url($full_url);
    $full_url_path = $parsed_url['path'] ?? '';
    if ($full_url_path !== $page_path) {
        return "Page Path와 Full URL의 경로 부분이 일치하지 않습니다. Page Path: $page_path, Full URL Path: $full_url_path";
    }
    return '';
}

function extractPagePath($full_url) {
    if (empty($full_url)) return '';
    $parsed_url = parse_url($full_url);
    return $parsed_url['path'] ?? '';
}

function generateMetaTagsFile($data) {
    $html = "<!DOCTYPE html>\n<html lang=\"ko\">\n<head>\n<meta charset=\"UTF-8\">\n";
    $html .= "<title>Generated Meta Tags - " . date('Y-m-d H:i:s') . "</title>\n\n";
    
    foreach ($data as $meta) {
        $html .= "<!-- Page: " . htmlspecialchars($meta['page_path']) . " -->\n";
        $html .= generateMetaTags($meta) . "\n";
    }
    
    $html .= "</head>\n<body>\n<h1>Generated Meta Tags</h1>\n";
    $html .= "<p>Generated on: " . date('Y-m-d H:i:s') . "</p>\n";
    $html .= "<p>Total pages: " . count($data) . "</p>\n";
    $html .= "</body>\n</html>";
    
    return $html;
}

// 생성/수정 처리
if ($_POST && $action !== 'ai_update' && $action !== 'autocomplete') {
    $pdo = getConnection();
    
    $page_path = $_POST['page_path'] ?? '';
    $full_url = $_POST['full_url'] ?? '';
    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';
    $keywords = $_POST['keywords'] ?? '';
    $robots = $_POST['robots'] ?? '';
    $image = $_POST['image'] ?? '';
    $meta_title = $_POST['meta_title'] ?? '';
    $og_title = $_POST['og_title'] ?? '';
    $og_description = $_POST['og_description'] ?? '';
    $og_image = $_POST['og_image'] ?? '';
    $og_url = $_POST['og_url'] ?? '';
    $twitter_card = $_POST['twitter_card'] ?? '';
    $twitter_title = $_POST['twitter_title'] ?? '';
    $twitter_description = $_POST['twitter_description'] ?? '';
    $twitter_image = $_POST['twitter_image'] ?? '';
    $brand = $_POST['brand'] ?? '';
    $status = $_POST['status'] ?? '작성 중';
    $comment = $_POST['comment'] ?? '';
    
    if (empty($page_path) && !empty($full_url)) {
        $page_path = extractPagePath($full_url);
    }
    
    $validation_error = validatePagePathAndFullUrl($page_path, $full_url);
    if ($validation_error) {
        $error = $validation_error;
    } else {
        if ($action == 'create') {
            $stmt = $pdo->prepare("INSERT INTO meta (page_path, full_url, title, description, keywords, robots, image, meta_title, og_title, og_description, og_image, og_url, twitter_card, twitter_title, twitter_description, twitter_image, brand, status, comment, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            
            if ($stmt->execute([$page_path, $full_url, $title, $description, $keywords, $robots, $image, $meta_title, $og_title, $og_description, $og_image, $og_url, $twitter_card, $twitter_title, $twitter_description, $twitter_image, $brand, $status, $comment, 'admin'])) {
                $message = '메타 태그가 성공적으로 생성되었습니다.';
                $action = 'list';
            } else {
                $error = '메타 태그 생성에 실패했습니다.';
            }
        } elseif ($action == 'edit') {
            $id = $_POST['id'];
            $stmt = $pdo->prepare("UPDATE meta SET page_path=?, full_url=?, title=?, description=?, keywords=?, robots=?, image=?, meta_title=?, og_title=?, og_description=?, og_image=?, og_url=?, twitter_card=?, twitter_title=?, twitter_description=?, twitter_image=?, brand=?, status=?, comment=?, updated_at=NOW() WHERE id=?");
            
            if ($stmt->execute([$page_path, $full_url, $title, $description, $keywords, $robots, $image, $meta_title, $og_title, $og_description, $og_image, $og_url, $twitter_card, $twitter_title, $twitter_description, $twitter_image, $brand, $status, $comment, $id])) {
                $message = '메타 태그가 성공적으로 수정되었습니다.';
                $action = 'list';
            } else {
                $error = '메타 태그 수정에 실패했습니다.';
            }
        }
    }
}

// 삭제 처리
if ($action == 'delete' && isset($_GET['id'])) {
    $pdo = getConnection();
    $stmt = $pdo->prepare("DELETE FROM meta WHERE id = ?");
    if ($stmt->execute([$_GET['id']])) {
        $message = '메타 태그가 성공적으로 삭제되었습니다.';
    } else {
        $error = '메타 태그 삭제에 실패했습니다.';
    }
    $action = 'list';
}

// 데이터 조회
$meta_data = null;
if ($action == 'edit' && isset($_GET['id'])) {
    $pdo = getConnection();
    $stmt = $pdo->prepare("SELECT * FROM meta WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $meta_data = $stmt->fetch();
    if (!$meta_data) {
        $error = '해당 메타 태그를 찾을 수 없습니다.';
        $action = 'list';
    }
}

// 페이징 설정 (개선됨)
$page = $_GET['page'] ?? 1;
$per_page = $_GET['per_page'] ?? 20; // 기본값 20
$allowed_per_page = [10, 20, 50, 100];
if (!in_array($per_page, $allowed_per_page)) $per_page = 20;
$offset = ($page - 1) * $per_page;

// 필터링
$brand_filter = $_GET['brand'] ?? '';
$status_filter = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';
$page_path_search = $_GET['page_path_search'] ?? '';

// 정렬
$sort = $_GET['sort'] ?? 'updated_at';
$dir = $_GET['dir'] ?? 'DESC';
$allowed_sorts = ['id', 'brand', 'page_path', 'title', 'status', 'created_by', 'created_at', 'updated_at'];
if (!in_array($sort, $allowed_sorts)) $sort = 'updated_at';
if (!in_array(strtoupper($dir), ['ASC', 'DESC'])) $dir = 'DESC';

// 목록 조회
if ($action == 'list') {
    $pdo = getConnection();
    
    // 전체 개수 조회
    $count_sql = "SELECT COUNT(*) FROM meta WHERE 1=1";
    $count_params = [];
    
    if ($brand_filter) {
        $count_sql .= " AND brand = ?";
        $count_params[] = $brand_filter;
    }
    
    if ($status_filter) {
        $count_sql .= " AND status = ?";
        $count_params[] = $status_filter;
    }
    
    if ($search) {
        $count_sql .= " AND (title LIKE ? OR description LIKE ?)";
        $count_params[] = "%$search%";
        $count_params[] = "%$search%";
    }
    
    if ($page_path_search) {
        $count_sql .= " AND page_path LIKE ?";
        $count_params[] = "%$page_path_search%";
    }
    
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute($count_params);
    $total_count = $count_stmt->fetchColumn();
    $total_pages = ceil($total_count / $per_page);
    
    // 데이터 조회
    $sql = "SELECT * FROM meta WHERE 1=1";
    $params = [];
    
    if ($brand_filter) {
        $sql .= " AND brand = ?";
        $params[] = $brand_filter;
    }
    
    if ($status_filter) {
        $sql .= " AND status = ?";
        $params[] = $status_filter;
    }
    
    if ($search) {
        $sql .= " AND (title LIKE ? OR description LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    if ($page_path_search) {
        $sql .= " AND page_path LIKE ?";
        $params[] = "%$page_path_search%";
    }
    
    $sql .= " ORDER BY $sort $dir LIMIT $per_page OFFSET $offset";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $meta_list = $stmt->fetchAll();
    
    // 브랜드 목록 조회
    $brand_stmt = $pdo->query("SELECT DISTINCT brand FROM meta WHERE brand IS NOT NULL AND brand != '' ORDER BY brand");
    $brands = $brand_stmt->fetchAll(PDO::FETCH_COLUMN);
}
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>메타 태그 관리자 v2.0</title>
    <?php echo getCommonStyles(); ?>
    <style>
        /* 추가 스타일 */
        .row {
            display: flex;
            gap: 30px;
            margin-bottom: 20px;
        }
        
        .col {
            flex: 1;
            min-width: 0;
        }
        
        .section-title {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 20px;
            margin: 20px 0 15px 0;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
        }
        
        .char-counter {
            font-size: 12px;
            color: #6c757d;
            float: right;
            margin-top: 5px;
        }
        
        /* 코멘트 툴팁 스타일 수정 (원래대로 복원) */
        .comment-tooltip {
            position: relative;
            cursor: help;
            display: inline-block;
            max-width: 150px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .comment-tooltip .tooltiptext {
            visibility: hidden;
            width: 300px;
            background-color: #333;
            color: #fff;
            text-align: left;
            border-radius: 6px;
            padding: 10px;
            position: absolute;
            z-index: 1000;
            bottom: 125%;
            left: 50%;
            margin-left: -150px;
            opacity: 0;
            transition: opacity 0.3s;
            font-size: 12px;
            line-height: 1.4;
            white-space: pre-wrap;
            word-wrap: break-word;
            box-shadow: 0 4px 8px rgba(0,0,0,0.3);
        }
        
        .comment-tooltip .tooltiptext::after {
            content: "";
            position: absolute;
            top: 100%;
            left: 50%;
            margin-left: -5px;
            border-width: 5px;
            border-style: solid;
            border-color: #333 transparent transparent transparent;
        }
        
        .comment-tooltip:hover .tooltiptext {
            visibility: visible;
            opacity: 1;
        }
        
        /* 미리보기 화면 스타일 개선 */
        .meta-preview {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 30px;
            border-radius: 15px;
            margin: 30px 0;
            border: 1px solid #dee2e6;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .preview-section {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid #667eea;
        }
        
        .preview-section h3 {
            color: #2c3e50;
            margin-bottom: 15px;
            font-size: 18px;
        }
        
        .preview-section p {
            margin-bottom: 8px;
            line-height: 1.6;
        }
        
        .preview-section strong {
            color: #495057;
            font-weight: 600;
        }
        
        .copy-section {
            background: white;
            padding: 20px;
            border-radius: 10px;
            border-left: 4px solid #28a745;
        }
        
        .copy-section h4 {
            color: #2c3e50;
            margin-bottom: 15px;
            font-size: 16px;
        }
        
        .copy-section pre {
            background: #2d3748;
            color: #e2e8f0;
            padding: 20px;
            border-radius: 8px;
            overflow-x: auto;
            font-size: 13px;
            line-height: 1.4;
            white-space: pre-wrap;
            word-wrap: break-word;
        }
        
        /* 페이징 개선 - 항상 보이도록 수정 */
        .pagination-controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 30px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #dee2e6;
        }
        
        .per-page-selector {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .per-page-selector select {
            padding: 8px 12px;
            border: 1px solid #dee2e6;
            border-radius: 6px;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
        }
        
        .pagination a {
            padding: 8px 12px;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            text-decoration: none;
            color: #6c757d;
        }
        
        .pagination a:hover {
            background: #f8f9fa;
        }
        
        .pagination .current {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }
        
        /* 버튼 아이콘 스타일 */
        .btn-ai::before { content: "🤖 "; }
        .btn-view::before { content: "👁️ "; }
        .btn-copy::before { content: "📋 "; }
        .btn-edit::before { content: "✏️ "; }
        .btn-delete::before { content: "🗑️ "; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>메타 태그 관리자</h1>
            <p>AI 기반 SEO 메타 태그 최적화 시스템 v2.0</p>
            <div class="nav-menu">
                <a href="index.php">대시보드</a>
                <a href="image_seo_manager.php">이미지 SEO 관리</a>
                <a href="meta_manager_v2.php" class="active">메타 태그 관리</a>
                <a href="schema_manager.php">스키마 관리</a>
            </div>
        </div>

        <div class="content">
            <?php if ($message): ?>
                <div class="message success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="message error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if ($action == 'list'): ?>
                <div class="filter-section">
                    <form method="GET">
                        <div class="filter-row">
                            <div>
                                <label>브랜드 필터</label>
                                <select name="brand" onchange="this.form.submit()">
                                    <option value="">전체 브랜드</option>
                                    <?php foreach ($brands as $brand): ?>
                                        <option value="<?php echo htmlspecialchars($brand); ?>" <?php echo $brand_filter == $brand ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($brand); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div>
                                <label>상태 필터</label>
                                <select name="status" onchange="this.form.submit()">
                                    <option value="">전체 상태</option>
                                    <option value="작성 중" <?php echo $status_filter == '작성 중' ? 'selected' : ''; ?>>작성 중</option>
                                    <option value="작성 완료" <?php echo $status_filter == '작성 완료' ? 'selected' : ''; ?>>작성 완료</option>
                                </select>
                            </div>
                            
                            <div>
                                <label>제목, 설명 검색</label>
                                <input type="text" name="search" placeholder="제목, 설명 검색..." value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            
                            <div>
                                <label>페이지 경로 검색</label>
                                <div class="autocomplete-container">
                                    <input type="text" name="page_path_search" id="page_path_search" placeholder="페이지 경로 검색..." value="<?php echo htmlspecialchars($page_path_search); ?>" autocomplete="off">
                                    <div id="page_path_search_autocomplete" class="autocomplete-list"></div>
                                </div>
                            </div>
                            
                            <div>
                                <label>&nbsp;</label>
                                <button type="submit" class="btn">검색</button>
                                <a href="meta_manager_v2.php" class="btn btn-secondary">초기화</a>
                                <a href="?action=create" class="btn">새 메타 태그</a>
                            </div>
                        </div>
                        
                        <input type="hidden" name="sort" value="<?php echo $sort; ?>">
                        <input type="hidden" name="dir" value="<?php echo $dir; ?>">
                        <input type="hidden" name="page" value="<?php echo $page; ?>">
                        <input type="hidden" name="per_page" value="<?php echo $per_page; ?>">
                    </form>
                </div>

                <div class="bulk-actions">
                    <label>
                        <input type="checkbox" id="select_all" onchange="toggleSelectAll()"> 전체 선택
                    </label>
                    <button type="button" onclick="downloadSelected()" class="btn btn-secondary">선택 항목 다운로드</button>
                </div>

                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>선택</th>
                                <th class="sortable <?php echo $sort == 'id' ? 'sort-' . strtolower($dir) : ''; ?>" onclick="sortTable('id')">ID</th>
                                <th class="sortable <?php echo $sort == 'brand' ? 'sort-' . strtolower($dir) : ''; ?>" onclick="sortTable('brand')">브랜드</th>
                                <th class="sortable <?php echo $sort == 'page_path' ? 'sort-' . strtolower($dir) : ''; ?>" onclick="sortTable('page_path')">페이지 경로</th>
                                <th class="sortable <?php echo $sort == 'title' ? 'sort-' . strtolower($dir) : ''; ?>" onclick="sortTable('title')">제목</th>
                                <th>설명</th>
                                <th>코멘트</th>
                                <th class="sortable <?php echo $sort == 'status' ? 'sort-' . strtolower($dir) : ''; ?>" onclick="sortTable('status')">상태</th>
                                <th class="sortable <?php echo $sort == 'created_at' ? 'sort-' . strtolower($dir) : ''; ?>" onclick="sortTable('created_at')">생성일</th>
                                <th>액션</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($meta_list as $meta): ?>
                            <?php 
                                $brandColors = getBrandColor($meta['brand']); 
                            ?>
                            <tr>
                                <td>
                                    <input type="checkbox" name="selected_ids[]" value="<?php echo $meta['id']; ?>">
                                </td>
                                <td><?php echo $meta['id']; ?></td>
                                <td>
                                    <?php if ($meta['brand']): ?>
                                        <span class="brand-tag" style="background-color: <?php echo $brandColors['bg']; ?>; color: <?php echo $brandColors['text']; ?>;">
                                            <?php echo htmlspecialchars($meta['brand']); ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td style="max-width: 200px; overflow: hidden; text-overflow: ellipsis;">
                                    <?php echo htmlspecialchars($meta['page_path']); ?>
                                </td>
                                <td id="title-<?php echo $meta['id']; ?>" style="max-width: 300px; overflow: hidden; text-overflow: ellipsis;">
                                    <?php echo htmlspecialchars($meta['title']); ?>
                                </td>
                                <td id="description-<?php echo $meta['id']; ?>" style="max-width: 300px; overflow: hidden; text-overflow: ellipsis;">
                                    <?php echo htmlspecialchars(substr($meta['description'], 0, 100)); ?><?php echo strlen($meta['description']) > 100 ? '...' : ''; ?>
                                </td>
                                <td>
                                    <?php if (!empty($meta['comment'])): ?>
                                        <div class="comment-tooltip">
                                            <?php echo htmlspecialchars(substr($meta['comment'], 0, 50)); ?><?php echo strlen($meta['comment']) > 50 ? '...' : ''; ?>
                                            <span class="tooltiptext"><?php echo htmlspecialchars($meta['comment']); ?></span>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo str_replace(' ', '', $meta['status']); ?>">
                                        <?php echo htmlspecialchars($meta['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('Y-m-d', strtotime($meta['created_at'])); ?></td>
                                <td style="white-space: nowrap;">
                                    <button id="ai-btn-<?php echo $meta['id']; ?>" 
                                            onclick="aiUpdate(<?php echo $meta['id']; ?>, '<?php echo htmlspecialchars($meta['page_path'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($meta['full_url'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($meta['brand'], ENT_QUOTES); ?>')"
                                            class="btn btn-ai btn-small">
                                        AI
                                    </button>
                                    <a href="?action=view&id=<?php echo $meta['id']; ?>" class="btn btn-view btn-secondary btn-small">미리보기</a>
                                    <button onclick="copyMetaTags(<?php echo $meta['id']; ?>)" class="btn btn-copy btn-secondary btn-small">복사</button>
                                    <a href="?action=edit&id=<?php echo $meta['id']; ?>" class="btn btn-edit btn-small">수정</a>
                                    <a href="?action=delete&id=<?php echo $meta['id']; ?>" onclick="return confirm('정말 삭제하시겠습니까?')" class="btn btn-delete btn-danger btn-small">삭제</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- 페이징 컨트롤 항상 표시 -->
                <div class="pagination-controls">
                    <div class="per-page-selector">
                        <label>페이지당 항목 수:</label>
                        <select name="per_page" onchange="changePerPage(this.value)">
                            <option value="10" <?php echo $per_page == 10 ? 'selected' : ''; ?>>10개</option>
                            <option value="20" <?php echo $per_page == 20 ? 'selected' : ''; ?>>20개</option>
                            <option value="50" <?php echo $per_page == 50 ? 'selected' : ''; ?>>50개</option>
                            <option value="100" <?php echo $per_page == 100 ? 'selected' : ''; ?>>100개</option>
                        </select>
                    </div>
                    
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page-1; ?>&brand=<?php echo urlencode($brand_filter); ?>&status=<?php echo urlencode($status_filter); ?>&search=<?php echo urlencode($search); ?>&page_path_search=<?php echo urlencode($page_path_search); ?>&sort=<?php echo $sort; ?>&dir=<?php echo $dir; ?>&per_page=<?php echo $per_page; ?>">이전</a>
                        <?php endif; ?>
                        
                        <?php if ($total_pages > 1): ?>
                            <?php for ($i = max(1, $page-2); $i <= min($total_pages, $page+2); $i++): ?>
                                <a href="?page=<?php echo $i; ?>&brand=<?php echo urlencode($brand_filter); ?>&status=<?php echo urlencode($status_filter); ?>&search=<?php echo urlencode($search); ?>&page_path_search=<?php echo urlencode($page_path_search); ?>&sort=<?php echo $sort; ?>&dir=<?php echo $dir; ?>&per_page=<?php echo $per_page; ?>" 
                                   class="<?php echo $i == $page ? 'current' : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>
                        <?php else: ?>
                            <span class="current">1</span>
                        <?php endif; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo $page+1; ?>&brand=<?php echo urlencode($brand_filter); ?>&status=<?php echo urlencode($status_filter); ?>&search=<?php echo urlencode($search); ?>&page_path_search=<?php echo urlencode($page_path_search); ?>&sort=<?php echo $sort; ?>&dir=<?php echo $dir; ?>&per_page=<?php echo $per_page; ?>">다음</a>
                        <?php endif; ?>
                    </div>
                    
                    <span style="color: #6c757d;">
                        총 <?php echo $total_count; ?>개 중 <?php echo ($page-1)*$per_page+1; ?>-<?php echo min($page*$per_page, $total_count); ?>개 표시
                    </span>
                </div>

            <?php elseif ($action == 'view' && isset($_GET['id'])): ?>
                <?php
                $pdo = getConnection();
                $stmt = $pdo->prepare("SELECT * FROM meta WHERE id = ?");
                $stmt->execute([$_GET['id']]);
                $view_meta = $stmt->fetch();
                ?>

                <h2>메타 태그 미리보기</h2>
                <div class="meta-preview">
                    <div class="preview-section">
                        <h3>페이지 정보</h3>
                        <p><strong>브랜드:</strong> <?php echo htmlspecialchars($view_meta['brand'] ?: '-'); ?></p>
                        <p><strong>페이지 경로:</strong> <?php echo htmlspecialchars($view_meta['page_path']); ?></p>
                        <p><strong>생성자:</strong> <?php echo htmlspecialchars($view_meta['created_by'] ?: '-'); ?></p>
                        <p><strong>상태:</strong> 
                            <span class="status-badge status-<?php echo str_replace(' ', '', $view_meta['status']); ?>">
                                <?php echo $view_meta['status']; ?>
                            </span>
                        </p>
                        <p><strong>생성일:</strong> <?php echo $view_meta['created_at']; ?></p>
                        <p><strong>최종 수정일:</strong> <?php echo $view_meta['updated_at']; ?></p>
                        
                        <?php if ($view_meta['comment']): ?>
                            <div style="margin: 15px 0;">
                                <strong>코멘트/이력:</strong>
                                <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-top: 8px; white-space: pre-wrap; font-family: 'Courier New', monospace; font-size: 13px; line-height: 1.5; color: #495057;">
                                    <?php echo htmlspecialchars($view_meta['comment']); ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($view_meta['full_url']): ?>
                            <p><strong>Full URL:</strong> <a href="<?php echo htmlspecialchars($view_meta['full_url']); ?>" target="_blank" style="color: #007bff; text-decoration: none;"><?php echo htmlspecialchars($view_meta['full_url']); ?></a></p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="copy-section">
                        <h4>생성된 메타 태그 HTML</h4>
                        <button onclick="copyToClipboard(document.getElementById('metaTagsCode').textContent)" class="btn btn-secondary" style="margin-bottom: 15px;">전체 복사</button>
                        <pre id="metaTagsCode"><?php echo htmlspecialchars(generateMetaTags($view_meta)); ?></pre>
                    </div>
                </div>

                <a href="?action=list" class="btn btn-secondary">목록으로</a>

            <?php elseif ($action == 'create' || $action == 'edit'): ?>
                <h2><?php echo $action == 'create' ? '새 메타 태그 추가' : '메타 태그 수정'; ?></h2>

                <form method="post" onsubmit="return validateForm(this)">
                    <?php if ($action == 'edit'): ?>
                        <input type="hidden" name="id" value="<?php echo $meta_data['id']; ?>">
                    <?php endif; ?>

                    <!-- 기본 정보 -->
                    <div class="row">
                        <div class="col">
                            <div class="form-group autocomplete-container">
                                <label>브랜드:</label>
                                <input type="text" name="brand" id="brand" value="<?php echo htmlspecialchars($meta_data['brand'] ?? ''); ?>" autocomplete="off">
                                <div id="brand_autocomplete" class="autocomplete-list"></div>
                            </div>

                            <div class="form-group autocomplete-container">
                                <label>전체 URL: *</label>
                                <input type="url" name="full_url" id="full_url" value="<?php echo htmlspecialchars($meta_data['full_url'] ?? ''); ?>" required autocomplete="off">
                                <div id="full_url_autocomplete" class="autocomplete-list"></div>
                            </div>

                            <div class="form-group">
                                <label>상태:</label>
                                <select name="status">
                                    <option value="작성 중" <?php echo ($meta_data['status'] ?? '') == '작성 중' ? 'selected' : ''; ?>>작성 중</option>
                                    <option value="작성 완료" <?php echo ($meta_data['status'] ?? '') == '작성 완료' ? 'selected' : ''; ?>>작성 완료</option>
                                </select>
                            </div>
                        </div>

                        <div class="col">
                            <div class="form-group autocomplete-container">
                                <label>페이지 경로: *</label>
                                <input type="text" name="page_path" id="page_path" value="<?php echo htmlspecialchars($meta_data['page_path'] ?? ''); ?>" required autocomplete="off">
                                <div id="page_path_autocomplete" class="autocomplete-list"></div>
                                <small style="color: #6c757d;">전체 URL을 입력하면 자동으로 채워집니다.</small>
                            </div>

                            <div class="form-group">
                                <label>Keywords:</label>
                                <input type="text" name="keywords" value="<?php echo htmlspecialchars($meta_data['keywords'] ?? ''); ?>">
                            </div>

                            <div class="form-group">
                                <label>Robots:</label>
                                <select name="robots">
                                    <option value="">선택하세요</option>
                                    <option value="index, follow" <?php echo ($meta_data['robots'] ?? '') == 'index, follow' ? 'selected' : ''; ?>>index, follow</option>
                                    <option value="noindex, nofollow" <?php echo ($meta_data['robots'] ?? '') == 'noindex, nofollow' ? 'selected' : ''; ?>>noindex, nofollow</option>
                                    <option value="index, nofollow" <?php echo ($meta_data['robots'] ?? '') == 'index, nofollow' ? 'selected' : ''; ?>>index, nofollow</option>
                                    <option value="noindex, follow" <?php echo ($meta_data['robots'] ?? '') == 'noindex, follow' ? 'selected' : ''; ?>>noindex, follow</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- SEO 기본 태그 -->
                    <div class="section-title">SEO 기본 태그</div>
                    <div class="row">
                        <div class="col">
                            <div class="form-group">
                                <label>Title: *</label>
                                <span class="char-counter" id="title_counter">0/60</span>
                                <input type="text" name="title" id="title" value="<?php echo htmlspecialchars($meta_data['title'] ?? ''); ?>" 
                                       oninput="updateCharCounter('title', 'title_counter', 60)" required>
                            </div>
                        </div>

                        <div class="col">
                            <div class="form-group">
                                <label>Description: *</label>
                                <span class="char-counter" id="description_counter">0/160</span>
                                <textarea name="description" id="description" rows="3" 
                                          oninput="updateCharCounter('description', 'description_counter', 160)" required><?php echo htmlspecialchars($meta_data['description'] ?? ''); ?></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Open Graph 태그 -->
                    <div class="section-title">Open Graph 태그</div>
                    <div class="row">
                        <div class="col">
                            <div class="form-group">
                                <label>OG Title:</label>
                                <span class="char-counter" id="og_title_counter">0/60</span>
                                <input type="text" name="og_title" id="og_title" value="<?php echo htmlspecialchars($meta_data['og_title'] ?? ''); ?>"
                                       oninput="updateCharCounter('og_title', 'og_title_counter', 60)">
                            </div>

                            <div class="form-group">
                                <label>OG Image:</label>
                                <input type="url" name="og_image" value="<?php echo htmlspecialchars($meta_data['og_image'] ?? ''); ?>">
                            </div>
                        </div>

                        <div class="col">
                            <div class="form-group">
                                <label>OG Description:</label>
                                <span class="char-counter" id="og_description_counter">0/160</span>
                                <textarea name="og_description" id="og_description" rows="3"
                                          oninput="updateCharCounter('og_description', 'og_description_counter', 160)"><?php echo htmlspecialchars($meta_data['og_description'] ?? ''); ?></textarea>
                            </div>

                            <div class="form-group">
                                <label>OG URL:</label>
                                <input type="url" name="og_url" value="<?php echo htmlspecialchars($meta_data['og_url'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>

                    <!-- Twitter Card 태그 -->
                    <div class="section-title">Twitter Card 태그</div>
                    <div class="row">
                        <div class="col">
                            <div class="form-group">
                                <label>Twitter Card:</label>
                                <select name="twitter_card">
                                    <option value="">선택하세요</option>
                                    <option value="summary" <?php echo ($meta_data['twitter_card'] ?? '') == 'summary' ? 'selected' : ''; ?>>summary</option>
                                    <option value="summary_large_image" <?php echo ($meta_data['twitter_card'] ?? '') == 'summary_large_image' ? 'selected' : ''; ?>>summary_large_image</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>Twitter Image:</label>
                                <input type="url" name="twitter_image" value="<?php echo htmlspecialchars($meta_data['twitter_image'] ?? ''); ?>">
                            </div>
                        </div>

                        <div class="col">
                            <div class="form-group">
                                <label>Twitter Title:</label>
                                <input type="text" name="twitter_title" value="<?php echo htmlspecialchars($meta_data['twitter_title'] ?? ''); ?>">
                            </div>

                            <div class="form-group">
                                <label>Twitter Description:</label>
                                <textarea name="twitter_description" rows="3"><?php echo htmlspecialchars($meta_data['twitter_description'] ?? ''); ?></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>메모/댓글:</label>
                        <textarea name="comment" rows="3" placeholder="작업 관련 메모나 댓글을 입력하세요."><?php echo htmlspecialchars($meta_data['comment'] ?? ''); ?></textarea>
                    </div>

                    <button type="submit" class="btn"><?php echo $action == 'create' ? '생성' : '수정'; ?></button>
                    <a href="?action=list" class="btn btn-secondary">목록으로</a>
                </form>

                <?php if ($action == 'edit' && $meta_data): ?>
                <div style="margin-top: 30px;">
                    <h3>미리보기</h3>
                    <div class="copy-section">
                        <button onclick="copyToClipboard(document.getElementById('previewCode').textContent)" class="btn btn-secondary">복사</button>
                        <pre id="previewCode"><?php echo htmlspecialchars(generateMetaTags($meta_data)); ?></pre>
                    </div>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // 페이지당 항목 수 변경
        function changePerPage(value) {
            const urlParams = new URLSearchParams(window.location.search);
            urlParams.set('per_page', value);
            urlParams.set('page', '1'); // 첫 페이지로 리셋
            window.location.search = urlParams.toString();
        }
        
        // 자동완성 기능
        function setupAutocomplete(inputId, field) {
            const input = document.getElementById(inputId);
            const list = document.getElementById(inputId + "_autocomplete");
            
            if (!input || !list) return;
            
            input.addEventListener("input", function() {
                const value = this.value;
                if (value.length < 1) {
                    list.style.display = "none";
                    return;
                }
                
                fetch(`?action=autocomplete&field=${field}&search=${encodeURIComponent(value)}`)
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Network response was not ok');
                        }
                        return response.json();
                    })
                    .then(data => {
                        list.innerHTML = "";
                        if (data && data.length > 0) {
                            data.forEach(item => {
                                const div = document.createElement("div");
                                div.className = "autocomplete-item";
                                div.textContent = item;
                                div.onclick = () => {
                                    input.value = item;
                                    list.style.display = "none";
                                    if (field === "full_url") {
                                        updatePagePath();
                                    }
                                };
                                list.appendChild(div);
                            });
                            list.style.display = "block";
                        } else {
                            list.style.display = "none";
                        }
                    })
                    .catch(error => {
                        console.error('자동완성 오류:', error);
                        list.style.display = "none";
                    });
            });
            
            document.addEventListener("click", function(e) {
                if (!input.contains(e.target) && !list.contains(e.target)) {
                    list.style.display = "none";
                }
            });
        }
        
        // Full URL에서 page_path 자동 추출
        function updatePagePath() {
            const fullUrlInput = document.getElementById("full_url");
            const pagePathInput = document.getElementById("page_path");
            
            if (fullUrlInput && pagePathInput && !pagePathInput.value) {
                const url = fullUrlInput.value;
                if (url) {
                    try {
                        const parsedUrl = new URL(url);
                        pagePathInput.value = parsedUrl.pathname;
                    } catch (e) {
                        console.log("Invalid URL");
                    }
                }
            }
        }
        
        // AI 업데이트 함수
        function aiUpdate(id, pagePath, fullUrl, brand) {
            const btn = document.getElementById(`ai-btn-${id}`);
            const originalText = btn.innerHTML;
            
            btn.disabled = true;
            btn.innerHTML = '<span class="ai-loading"></span>처리 중...';
            
            const formData = new FormData();
            formData.append("id", id);
            formData.append("page_path", pagePath);
            formData.append("full_url", fullUrl);
            formData.append("brand", brand);
            
            fetch("meta_manager_v2.php?action=ai_update", {
                method: "POST",
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    updateTableCell(`title-${id}`, data.data.title, 60);
                    updateTableCell(`description-${id}`, data.data.description, 100);
                    
                    alert(data.message);
                } else {
                    alert("오류: " + (data.error || "알 수 없는 오류가 발생했습니다."));
                }
            })
            .catch(error => {
                console.error("AI 업데이트 오류:", error);
                alert("AI 업데이트 중 오류가 발생했습니다.");
            })
            .finally(() => {
                btn.disabled = false;
                btn.innerHTML = originalText;
            });
        }
        
        // 테이블 셀 업데이트
        function updateTableCell(cellId, newValue, maxLength = 50) {
            const cell = document.getElementById(cellId);
            if (cell) {
                cell.textContent = newValue.length > maxLength ? newValue.substring(0, maxLength) + "..." : newValue;
                cell.style.background = "#d4edda";
                setTimeout(() => {
                    cell.style.background = "";
                }, 2000);
            }
        }
        
        // 문자 수 카운터
        function updateCharCounter(inputId, counterId, maxLength) {
            const input = document.getElementById(inputId);
            const counter = document.getElementById(counterId);
            if (input && counter) {
                const length = input.value.length;
                counter.textContent = `${length}/${maxLength}`;
                counter.style.color = length > maxLength ? "#dc3545" : "#6c757d";
            }
        }
        
        // 테이블 정렬
        function sortTable(column) {
            const urlParams = new URLSearchParams(window.location.search);
            const currentSort = urlParams.get('sort');
            const currentDir = urlParams.get('dir');
            
            let newDir = 'ASC';
            if (currentSort === column && currentDir === 'ASC') {
                newDir = 'DESC';
            }
            
            urlParams.set('sort', column);
            urlParams.set('dir', newDir);
            urlParams.set('page', '1');
            
            window.location.search = urlParams.toString();
        }
        
        // 전체 선택/해제
        function toggleSelectAll() {
            const checkboxes = document.querySelectorAll('input[name="selected_ids[]"]');
            const selectAll = document.getElementById("select_all");
            
            checkboxes.forEach(checkbox => {
                checkbox.checked = selectAll.checked;
            });
        }
        
        // 선택된 항목 다운로드
        function downloadSelected() {
            const checkboxes = document.querySelectorAll('input[name="selected_ids[]"]:checked');
            if (checkboxes.length === 0) {
                alert("다운로드할 항목을 선택해주세요.");
                return;
            }
            
            const form = document.createElement("form");
            form.method = "POST";
            form.action = "meta_manager_v2.php?action=download";
            
            checkboxes.forEach(checkbox => {
                const input = document.createElement("input");
                input.type = "hidden";
                input.name = "selected_ids[]";
                input.value = checkbox.value;
                form.appendChild(input);
            });
            
            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);
        }
        
        // 메타 태그 복사
        function copyMetaTags(id) {
            fetch(`meta_manager_v2.php?action=get_meta_html&id=${id}`)
                .then(response => response.text())
                .then(html => {
                    copyToClipboard(html);
                    alert('메타 태그가 복사되었습니다!');
                })
                .catch(error => {
                    console.error('복사 오류:', error);
                    alert('복사 중 오류가 발생했습니다.');
                });
        }
        
        // 클립보드 복사
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(function() {
                // 성공
            }, function(err) {
                // 실패 시 fallback
                const textArea = document.createElement("textarea");
                textArea.value = text;
                document.body.appendChild(textArea);
                textArea.focus();
                textArea.select();
                try {
                    document.execCommand('copy');
                } catch (err) {
                    console.error('Fallback: Oops, unable to copy', err);
                }
                document.body.removeChild(textArea);
            });
        }
        
        // 폼 유효성 검사
        function validateForm(form) {
            const pagePathInput = form.page_path;
            const fullUrlInput = form.full_url;
            
            if (pagePathInput.value && fullUrlInput.value) {
                try {
                    const url = new URL(fullUrlInput.value);
                    const urlPath = url.pathname;
                    
                    if (urlPath !== pagePathInput.value) {
                        alert(`Page Path와 Full URL의 경로가 일치하지 않습니다.\nPage Path: ${pagePathInput.value}\nFull URL Path: ${urlPath}`);
                        return false;
                    }
                } catch (e) {
                    alert('올바른 URL 형식을 입력해주세요.');
                    return false;
                }
            }
            
            return true;
        }
        
        // 페이지 로드 시 초기화
        document.addEventListener("DOMContentLoaded", function() {
            // 자동완성 설정
            if (document.getElementById("brand")) {
                setupAutocomplete("brand", "brand");
            }
            if (document.getElementById("page_path")) {
                setupAutocomplete("page_path", "page_path");
            }
            if (document.getElementById("full_url")) {
                setupAutocomplete("full_url", "full_url");
            }
            if (document.getElementById("page_path_search")) {
                setupAutocomplete("page_path_search", "page_path");
            }
            
            // Full URL 변경 시 page_path 자동 업데이트
            const fullUrlInput = document.getElementById("full_url");
            if (fullUrlInput) {
                fullUrlInput.addEventListener("blur", updatePagePath);
            }
            
            // 문자 수 카운터 초기화
            updateCharCounter('title', 'title_counter', 60);
            updateCharCounter('description', 'description_counter', 160);
            updateCharCounter('og_title', 'og_title_counter', 60);
            updateCharCounter('og_description', 'og_description_counter', 160);
        });
    </script>
</body>
</html>