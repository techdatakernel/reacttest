<?php
/**
 * H-mall 상품 간결 문구 관리 시스템 v4.2 FINAL
 * 작성일: 2025-11-14
 * 버전: 4.2 FINAL (v3 + v4 통합, 이미지 URL 자동 생성 로직 완벽 복구)
 * 
 * 주요 수정 사항:
 * 1. 이미지 URL 자동 생성 로직 완벽 복구 (generateImageUrl 호출)
 * 2. 이미지가 JSON에 없으면 자동 표시하지 않도록 수정
 * 3. "생성 문구" 열 굵은색 표시
 * 4. 수정 버튼 복구 (사라진 기능 복구)
 * 5. 기간 필터 기능 유지
 * 6. 검색 조건 해당 행 수 텍스트 표시 기능 복구
 * 7. 정렬 기능 복구 (최근순/오래된순)
 * 8. 수동 추가 폼 정상화
 * 9. 이미지 클릭 시 팝업 모달
 * 10. 풀페이지 레이아웃 (좌우 여백 없음)
 * 11. 검색 초기화 버튼 추가
 * 12. 테이블 열 너비 고정
 * 13. 테이블 셀 텍스트 wrapping
 */

// 오류 표시 설정
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 한글 처리를 위한 인코딩 설정
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');

// 한국 시간대 설정 (KST +9)
date_default_timezone_set('Asia/Seoul');

// OpenAI API 설정
define('OPENAI_API_KEY', 'sk-proj-xxxx');
define('DATA_FILE', 'hmall_products_data.json');

// 데이터 파일 초기화
if (!file_exists(DATA_FILE)) {
    file_put_contents(DATA_FILE, json_encode(['products' => []], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

/**
 * 데이터 로드
 */
function loadData() {
    if (!file_exists(DATA_FILE)) {
        return ['products' => []];
    }
    
    $json = file_get_contents(DATA_FILE);
    $data = json_decode($json, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("JSON decode error: " . json_last_error_msg());
        return ['products' => []];
    }
    
    return $data;
}

/**
 * 데이터 저장
 */
function saveData($data) {
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    
    if ($json === false) {
        error_log("JSON encode error: " . json_last_error_msg());
        return false;
    }
    
    return file_put_contents(DATA_FILE, $json) !== false;
}

/**
 * H-mall 상품 정보 가져오기
 */
function fetchHmallProduct($slitmCd) {
    try {
        $fetchUrl = "https://www.hmall.com/pd/pda/itemPtc?slitmCd={$slitmCd}&preview=true";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $fetchUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $html = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($httpCode !== 200 || !$html) {
            error_log("Failed to fetch product {$slitmCd}: HTTP {$httpCode}, Error: {$curlError}");
            return null;
        }
        
        preg_match('/<script id="__NEXT_DATA__" type="application\/json">(.*?)<\/script>/s', $html, $jsonMatches);
        if (!isset($jsonMatches[1])) {
            error_log("Failed to extract JSON from product page: {$slitmCd}");
            return null;
        }
        
        $jsonData = json_decode($jsonMatches[1], true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("JSON decode error for product {$slitmCd}: " . json_last_error_msg());
            return null;
        }
        
        return $jsonData['props']['pageProps']['respData']['itemPtc'] ?? null;
    } catch (Exception $e) {
        error_log("Exception in fetchHmallProduct: " . $e->getMessage());
        return null;
    }
}

/**
 * OpenAI API 호출 - AI 문구 생성
 */
function generateTitleWithAI($productName, $additionalPrompt = '') {
    try {
        if (empty($productName)) {
            error_log("generateTitleWithAI: Empty product name");
            return "문구 생성 실패";
        }
        
        $systemPrompt = "당신은 H-mall의 전문 상품 카피라이터입니다. 상품명을 보고 고객의 눈길을 끄는 짧고 임팩트 있는 문구를 만듭니다.";
        
        $userPrompt = "상품명: {$productName}\n\n";
        $userPrompt .= "요구사항:\n";
        $userPrompt .= "1. 12자 이내로 작성\n";
        $userPrompt .= "2. 브랜드명은 절대 포함하지 않기\n";
        $userPrompt .= "3. 상품의 핵심 특징이나 혜택을 강조\n";
        $userPrompt .= "4. 고객의 관심을 끌 수 있는 간결한 문구\n";
        $userPrompt .= "5. 예시: '하루 한 포로 가볍게!', '전 사이즈 균일가'\n\n";
        
        if ($additionalPrompt) {
            $userPrompt .= "추가 요청사항: {$additionalPrompt}\n\n";
        }
        
        $userPrompt .= "위 상품명에 맞는 12자 이내의 간결한 문구만 작성해주세요. 설명 없이 문구만 제공해주세요.";
        
        $data = [
            'model' => 'gpt-4',
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt]
            ],
            'temperature' => 0.8,
            'max_tokens' => 100
        ];
        
        $ch = curl_init('https://api.openai.com/v1/chat/completions');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . OPENAI_API_KEY
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            error_log("OpenAI API error: HTTP {$httpCode}, Error: {$curlError}");
            return "문구 생성 실패";
        }
        
        $result = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("OpenAI response JSON decode error: " . json_last_error_msg());
            return "문구 생성 실패";
        }
        
        $generatedText = trim($result['choices'][0]['message']['content'] ?? '문구 생성 실패');
        
        // 작은따옴표 제거
        $generatedText = str_replace("'", "", $generatedText);
        $generatedText = str_replace('"', "", $generatedText);
        
        return $generatedText;
    } catch (Exception $e) {
        error_log("Exception in generateTitleWithAI: " . $e->getMessage());
        return "문구 생성 실패";
    }
}

/**
 * JSON 응답 출력 헬퍼 함수
 */
function sendJsonResponse($data) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * ✅ 이미지 URL 자동 생성 함수 (v3과 동일)
 */
function generateImageUrl($slitmCd) {
    $codeStr = (string)$slitmCd;
    if (strlen($codeStr) < 8) return '';
    
    $middle = substr($codeStr, 2, -2);
    if (strlen($middle) != 6) return '';
    
    // 중간 6자리 역순
    $reversed = strrev($middle); // 013720 -> 027310
    
    $part1 = substr($reversed, 0, 1); // 0
    $part2 = substr($reversed, 1, 1); // 2
    $part3 = substr($reversed, 3, 1) . substr($reversed, 2, 1); // 3 . 7 = 37
    $part4 = substr($reversed, 5, 1) . substr($reversed, 4, 1); // 0 . 1 = 01
    
    return "https://image.hmall.com/static/{$part1}/{$part2}/{$part3}/{$part4}/{$slitmCd}_0.jpg?RS=600x600&AR=0&ao=1&cVer=202511120001&SF=webp";
}

// API 요청 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['action'])) {
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    
    // ✅ CSV 다운로드 처리 (v2 방식 - 직접 스트리밍)
    if ($action === 'export_csv') {
        $idsJson = $_POST['ids'] ?? '[]';
        $ids = json_decode($idsJson, true);
        
        $data = loadData();
        
        // 선택된 상품 필터링
        if (!empty($ids) && is_array($ids)) {
            $products = array_filter($data['products'], function($product) use ($ids) {
                return in_array($product['id'], $ids);
            });
        } else {
            $products = $data['products'];
        }
        
        // UTF-8 설정 (Excel 호환)
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="hmall_products_' . date('Ymd_His') . '.csv"');
        header('Cache-Control: no-cache, must-revalidate');
        
        $output = fopen('php://output', 'w');
        
        // UTF-8 BOM 추가 (Excel에서 한글 자동 인식)
        fputs($output, "\xEF\xBB\xBF");
        
        // 헤더 배열
        $headers = ['제품코드', 'URL', '이미지URL', '추가요청사항', '상품명', '브랜드', '가격', '생성된문구', '상태', '생성일시'];
        
        // 헤더 출력 (인코딩 변환 없이 UTF-8 유지)
        fputcsv($output, $headers);
        
        // 데이터 출력
        foreach ($products as $product) {
            $row = [
                $product['product_code'] ?? '',
                $product['url'] ?? '',
                $product['image_url'] ?? '',
                $product['additional_request'] ?? '',
                $product['product_name'] ?? '',
                $product['brand_name'] ?? '',
                $product['price'] ?? 0,
                $product['generated_title'] ?? '',
                $product['status'] === 'completed' ? '완료' : '대기',
                $product['created_at'] ?? ''
            ];
            fputcsv($output, $row);
        }
        
        fclose($output);
        exit;
    }
    
    // ✅ 템플릿 다운로드 처리 (v2 방식 - 직접 스트리밍)
    if ($action === 'download_template') {
        // UTF-8 설정 (Excel 호환)
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="hmall_template.csv"');
        header('Cache-Control: no-cache, must-revalidate');
        
        $output = fopen('php://output', 'w');
        
        // UTF-8 BOM 추가
        fputs($output, "\xEF\xBB\xBF");
        
        // 헤더
        $headers = ['제품코드', 'URL', '이미지URL', '추가요청사항', '제품명', '브랜드명', '가격'];
        fputcsv($output, $headers);
        
        // 샘플 데이터
        $sampleData = [
            ['2243196081', 'https://www.hmall.com/pd/pda/itemPtc?slitmCd=2243196081&preview=true', '', '가성비 강조', '', '', ''],
            ['2242937882', 'https://www.hmall.com/pd/pda/itemPtc?slitmCd=2242937882&preview=true', '', '할인 혜택 강조', '', '', '']
        ];
        
        foreach ($sampleData as $row) {
            fputcsv($output, $row);
        }
        
        fclose($output);
        exit;
    }
    
    // JSON 응답이 필요한 액션들
    header('Content-Type: application/json; charset=UTF-8');
    
    try {
        switch ($action) {
            case 'upload_csv':
                if (!isset($_FILES['csv_file'])) {
                    sendJsonResponse(['success' => false, 'error' => 'CSV 파일이 없습니다.']);
                }
                
                $file = $_FILES['csv_file']['tmp_name'];
                
                if (!file_exists($file)) {
                    sendJsonResponse(['success' => false, 'error' => '파일을 읽을 수 없습니다.']);
                }
                
                // 인코딩 감지 및 변환
                $csvData = file_get_contents($file);
                $encoding = mb_detect_encoding($csvData, ['UTF-8', 'EUC-KR', 'CP949'], true);
                if ($encoding !== 'UTF-8') {
                    $csvData = mb_convert_encoding($csvData, 'UTF-8', $encoding);
                }
                
                // UTF-8 BOM 제거
                $csvData = str_replace("\xEF\xBB\xBF", '', $csvData);
                
                $lines = explode("\n", $csvData);
                $data = loadData();
                
                // ✅ 제품코드로 기존 제품 인덱싱 (업데이트/삽입 로직)
                $productsByCode = [];
                foreach ($data['products'] as $index => $product) {
                    if (!empty($product['product_code'])) {
                        $productsByCode[$product['product_code']] = $index;
                    }
                }
                
                $imported = 0;
                $updated = 0;
                $errors = [];
                
                foreach ($lines as $index => $line) {
                    if ($index === 0 || empty(trim($line))) continue; // 헤더 및 빈 줄 스킵
                    
                    $row = str_getcsv($line);
                    if (count($row) < 2) continue;
                    
                    $productCode = trim($row[0] ?? '');
                    $url = trim($row[1] ?? '');
                    $imageUrl = trim($row[2] ?? '');
                    $additionalRequest = trim($row[3] ?? '');
                    
                    if (empty($productCode)) continue;
                    
                    // 상품 정보 가져오기
                    $productInfo = fetchHmallProduct($productCode);
                    
                    // ✅ 이미지 URL 자동 생성 규칙 적용 (v3과 동일)
                    $generatedImageUrl = generateImageUrl($productCode);
                    
                    // ✅ 업데이트 또는 삽입 로직
                    if (isset($productsByCode[$productCode])) {
                        // 기존 상품 업데이트
                        $existingIndex = $productsByCode[$productCode];
                        $data['products'][$existingIndex]['url'] = $url;
                        $data['products'][$existingIndex]['image_url'] = $imageUrl ?: $generatedImageUrl;
                        $data['products'][$existingIndex]['additional_request'] = $additionalRequest;
                        
                        // 상품 정보 업데이트
                        if ($productInfo) {
                            $data['products'][$existingIndex]['product_name'] = $productInfo['slitmNm'] ?? '';
                            $data['products'][$existingIndex]['brand_name'] = $productInfo['brndNm'] ?? '';
                            $data['products'][$existingIndex]['price'] = $productInfo['sellPrc'] ?? 0;
                            
                            // 이미지 URL이 비어있으면 자동 생성
                            if (empty($data['products'][$existingIndex]['image_url']) && isset($productInfo['orglImgNm'])) {
                                $data['products'][$existingIndex]['image_url'] = $generatedImageUrl;
                            }
                        }
                        
                        $data['products'][$existingIndex]['updated_at'] = date('Y-m-d H:i:s');
                        $updated++;
                    } else {
                        // 새 상품 추가
                        $newProduct = [
                            'id' => uniqid(),
                            'product_code' => $productCode,
                            'url' => $url,
                            'image_url' => $imageUrl ?: $generatedImageUrl,
                            'additional_request' => $additionalRequest,
                            'product_name' => $productInfo['slitmNm'] ?? '',
                            'brand_name' => $productInfo['brndNm'] ?? '',
                            'price' => $productInfo['sellPrc'] ?? 0,
                            'generated_title' => '',
                            'status' => 'pending',
                            'created_at' => date('Y-m-d H:i:s'),
                            'updated_at' => date('Y-m-d H:i:s')
                        ];
                        
                        // 상품명을 가져오지 못한 경우
                        if (empty($newProduct['product_name'])) {
                            $errors[] = "상품코드 {$productCode}: 상품 정보를 가져올 수 없습니다.";
                        }
                        
                        $data['products'][] = $newProduct;
                        $imported++;
                    }
                }
                
                if (saveData($data)) {
                    sendJsonResponse([
                        'success' => true, 
                        'imported' => $imported,
                        'updated' => $updated,
                        'errors' => $errors
                    ]);
                } else {
                    sendJsonResponse(['success' => false, 'error' => '데이터 저장 실패']);
                }
                break;
                
            case 'fetch_product':
                $productCode = $_POST['product_code'] ?? '';
                
                if (empty($productCode)) {
                    sendJsonResponse(['success' => false, 'error' => '상품 코드가 필요합니다.']);
                }
                
                $productInfo = fetchHmallProduct($productCode);
                
                if ($productInfo) {
                    sendJsonResponse([
                        'success' => true,
                        'product_name' => $productInfo['slitmNm'],
                        'brand_name' => $productInfo['brndNm'],
                        'price' => $productInfo['sellPrc'],
                        'image_url' => generateImageUrl($productCode)
                    ]);
                } else {
                    sendJsonResponse(['success' => false, 'error' => '상품 정보를 가져올 수 없습니다.']);
                }
                break;
                
            case 'generate_titles':
                $idsJson = $_POST['ids'] ?? '[]';
                $ids = json_decode($idsJson, true);
                
                if (!is_array($ids) || empty($ids)) {
                    sendJsonResponse(['success' => false, 'error' => '선택된 항목이 없습니다.']);
                }
                
                $data = loadData();
                
                $generated = 0;
                $errors = [];
                
                foreach ($data['products'] as &$product) {
                    if (in_array($product['id'], $ids)) {
                        // 상품명이 비어있는 경우 상품 정보 다시 가져오기
                        if (empty($product['product_name']) && !empty($product['product_code'])) {
                            $productInfo = fetchHmallProduct($product['product_code']);
                            if ($productInfo) {
                                $product['product_name'] = $productInfo['slitmNm'] ?? '';
                                $product['brand_name'] = $productInfo['brndNm'] ?? '';
                                $product['price'] = $productInfo['sellPrc'] ?? 0;
                                
                                if (empty($product['image_url'])) {
                                    $product['image_url'] = generateImageUrl($product['product_code']);
                                }
                            }
                        }
                        
                        // 상품명이 있는 경우에만 AI 생성
                        if (!empty($product['product_name'])) {
                            $title = generateTitleWithAI($product['product_name'], $product['additional_request']);
                            
                            if ($title !== "문구 생성 실패") {
                                $product['generated_title'] = $title;
                                $product['status'] = 'completed';
                                $product['updated_at'] = date('Y-m-d H:i:s');
                                $generated++;
                            } else {
                                $errors[] = "상품코드 {$product['product_code']}: AI 생성 실패";
                            }
                        } else {
                            $errors[] = "상품코드 {$product['product_code']}: 상품명이 없습니다.";
                        }
                    }
                }
                
                if (saveData($data)) {
                    sendJsonResponse([
                        'success' => true, 
                        'generated' => $generated,
                        'errors' => $errors
                    ]);
                } else {
                    sendJsonResponse(['success' => false, 'error' => '데이터 저장 실패']);
                }
                break;
                
            case 'add_product':
                $productCode = $_POST['product_code'] ?? '';
                $url = $_POST['url'] ?? '';
                $imageUrl = $_POST['image_url'] ?? '';
                $additionalRequest = $_POST['additional_request'] ?? '';
                
                if (empty($productCode)) {
                    sendJsonResponse(['success' => false, 'error' => '상품 코드가 필요합니다.']);
                }
                
                $data = loadData();
                $productInfo = fetchHmallProduct($productCode);
                
                // ✅ 이미지 URL 자동 생성 (v3과 동일)
                $generatedImageUrl = generateImageUrl($productCode);
                
                $newProduct = [
                    'id' => uniqid(),
                    'product_code' => $productCode,
                    'url' => $url,
                    'image_url' => $imageUrl ?: $generatedImageUrl,
                    'additional_request' => $additionalRequest,
                    'product_name' => $productInfo['slitmNm'] ?? '',
                    'brand_name' => $productInfo['brndNm'] ?? '',
                    'price' => $productInfo['sellPrc'] ?? 0,
                    'generated_title' => '',
                    'status' => 'pending',
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ];
                
                $data['products'][] = $newProduct;
                
                if (saveData($data)) {
                    sendJsonResponse(['success' => true, 'product' => $newProduct]);
                } else {
                    sendJsonResponse(['success' => false, 'error' => '데이터 저장 실패']);
                }
                break;
                
            case 'update_product':
                $id = $_POST['id'] ?? '';
                
                if (empty($id)) {
                    sendJsonResponse(['success' => false, 'error' => 'ID가 필요합니다.']);
                }
                
                $data = loadData();
                
                $found = false;
                foreach ($data['products'] as &$product) {
                    if ($product['id'] === $id) {
                        $product['product_code'] = $_POST['product_code'] ?? $product['product_code'];
                        $product['url'] = $_POST['url'] ?? $product['url'];
                        $product['image_url'] = $_POST['image_url'] ?? $product['image_url'];
                        $product['additional_request'] = $_POST['additional_request'] ?? $product['additional_request'];
                        $product['generated_title'] = $_POST['generated_title'] ?? $product['generated_title'];
                        $product['updated_at'] = date('Y-m-d H:i:s');
                        $found = true;
                        break;
                    }
                }
                
                if (!$found) {
                    sendJsonResponse(['success' => false, 'error' => '상품을 찾을 수 없습니다.']);
                }
                
                if (saveData($data)) {
                    sendJsonResponse(['success' => true]);
                } else {
                    sendJsonResponse(['success' => false, 'error' => '데이터 저장 실패']);
                }
                break;
                
            case 'delete_products':
                $idsJson = $_POST['ids'] ?? '[]';
                $ids = json_decode($idsJson, true);
                
                if (!is_array($ids)) {
                    sendJsonResponse(['success' => false, 'error' => '잘못된 요청입니다.']);
                }
                
                $data = loadData();
                
                $data['products'] = array_filter($data['products'], function($product) use ($ids) {
                    return !in_array($product['id'], $ids);
                });
                
                $data['products'] = array_values($data['products']);
                
                if (saveData($data)) {
                    sendJsonResponse(['success' => true, 'deleted' => count($ids)]);
                } else {
                    sendJsonResponse(['success' => false, 'error' => '데이터 저장 실패']);
                }
                break;
                
            case 'search_products':
                $keyword = $_POST['keyword'] ?? '';
                $page = intval($_POST['page'] ?? 1);
                $perPage = intval($_POST['per_page'] ?? 20);
                $dateFilter = $_POST['date_filter'] ?? '';
                $startDate = $_POST['start_date'] ?? '';
                $endDate = $_POST['end_date'] ?? '';
                $sortOrder = $_POST['sort_order'] ?? 'desc';
                
                $data = loadData();
                $filtered = $data['products'];
                
                // 키워드 필터
                if (!empty($keyword)) {
                    $filtered = array_filter($filtered, function($product) use ($keyword) {
                        return stripos($product['product_name'], $keyword) !== false ||
                               stripos($product['product_code'], $keyword) !== false ||
                               stripos($product['generated_title'], $keyword) !== false;
                    });
                }
                
                // 날짜 필터
                if (!empty($dateFilter)) {
                    $today = date('Y-m-d');
                    switch ($dateFilter) {
                        case 'today':
                            $startDate = $today;
                            $endDate = $today;
                            break;
                        case 'yesterday':
                            $startDate = date('Y-m-d', strtotime('-1 day'));
                            $endDate = $startDate;
                            break;
                        case 'last_week':
                            $startDate = date('Y-m-d', strtotime('-7 days'));
                            $endDate = $today;
                            break;
                        case 'custom':
                            if (empty($startDate) || empty($endDate)) break;
                            break;
                    }
                    
                    if (!empty($startDate) && !empty($endDate)) {
                        $filtered = array_filter($filtered, function($product) use ($startDate, $endDate) {
                            $createdDate = date('Y-m-d', strtotime($product['created_at']));
                            return $createdDate >= $startDate && $createdDate <= $endDate;
                        });
                    }
                }
                
                // 정렬 기능: created_at 기준 (desc 또는 asc)
                usort($filtered, function($a, $b) use ($sortOrder) {
                    $timeA = strtotime($a['created_at']);
                    $timeB = strtotime($b['created_at']);
                    return $sortOrder === 'desc' ? $timeB <=> $timeA : $timeA <=> $timeB;
                });
                
                $total = count($filtered);
                $paged = array_slice($filtered, ($page - 1) * $perPage, $perPage);
                
                sendJsonResponse([
                    'success' => true,
                    'products' => array_values($paged),
                    'total' => $total,
                    'page' => $page,
                    'total_pages' => ceil($total / $perPage)
                ]);
                break;
                
            case 'autocomplete':
                $keyword = $_POST['keyword'] ?? '';
                
                if (empty($keyword)) {
                    sendJsonResponse(['success' => true, 'suggestions' => []]);
                }
                
                $data = loadData();
                $suggestions = [];
                
                foreach ($data['products'] as $product) {
                    if (stripos($product['product_name'], $keyword) !== false) {
                        $suggestions[] = $product['product_name'];
                    }
                    if (stripos($product['product_code'], $keyword) !== false) {
                        $suggestions[] = $product['product_code'];
                    }
                    if (stripos($product['generated_title'], $keyword) !== false) {
                        $suggestions[] = $product['generated_title'];
                    }
                }
                
                $suggestions = array_unique($suggestions);
                $suggestions = array_slice($suggestions, 0, 10);
                
                sendJsonResponse([
                    'success' => true,
                    'suggestions' => array_values($suggestions)
                ]);
                break;
                
            default:
                sendJsonResponse(['success' => false, 'error' => '알 수 없는 액션입니다.']);
        }
    } catch (Exception $e) {
        error_log("Exception in AJAX handler: " . $e->getMessage());
        sendJsonResponse(['success' => false, 'error' => '서버 오류: ' . $e->getMessage()]);
    }
}
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🛒 H-mall 상품 간결 문구 관리 시스템</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        html, body {
            width: 100%;
            height: 100%;
            overflow-x: hidden;
        }
        
        body {
            font-family: 'Noto Sans KR', -apple-system, BlinkMacSystemFont, sans-serif;
            background: #f5f7fa;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .header h1 {
            font-size: 24px;
            margin-bottom: 8px;
        }
        
        .header p {
            opacity: 0.9;
            font-size: 13px;
        }
        
        .info-box {
            background: #d4edda;
            border-left: 4px solid #28a745;
            padding: 15px;
            margin: 20px;
            border-radius: 4px;
            color: #155724;
            font-size: 13px;
        }
        
        .container {
            width: 100%;
            margin: 0;
            padding: 0 20px 20px 20px;
        }
        
        .toolbar {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin: 0 20px 20px 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        
        .toolbar-row {
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
            margin-bottom: 15px;
        }
        
        .toolbar-row:last-child {
            margin-bottom: 0;
        }
        
        .btn {
            background: #667eea;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
            white-space: nowrap;
        }
        
        .btn:hover {
            background: #5568d3;
            transform: translateY(-1px);
        }
        
        .btn:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
        }
        
        .btn-secondary {
            background: #6c757d;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        .btn-success {
            background: #28a745;
        }
        
        .btn-success:hover {
            background: #218838;
        }
        
        .btn-danger {
            background: #dc3545;
        }
        
        .btn-danger:hover {
            background: #c82333;
        }
        
        .btn-warning {
            background: #ffc107;
            color: #212529;
        }
        
        .btn-warning:hover {
            background: #e0a800;
        }
        
        .btn-info {
            background: #17a2b8;
        }
        
        .btn-info:hover {
            background: #138496;
        }
        
        .btn-primary {
            background: #667eea;
        }
        
        .btn-primary:hover {
            background: #5568d3;
        }
        
        .search-box {
            display: flex;
            gap: 10px;
            flex: 1;
            max-width: 400px;
            position: relative;
        }
        
        .search-box input {
            flex: 1;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
        }
        
        .autocomplete-results {
            position: absolute;
            top: 100%;
            left: 0;
            right: 50px;
            background: white;
            border: 1px solid #ddd;
            border-top: none;
            border-radius: 0 0 6px 6px;
            max-height: 200px;
            overflow-y: auto;
            display: none;
            z-index: 1000;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .autocomplete-results.show {
            display: block;
        }
        
        .autocomplete-item {
            padding: 10px 15px;
            cursor: pointer;
            font-size: 14px;
        }
        
        .autocomplete-item:hover {
            background: #f8f9fa;
        }
        
        .result-count {
            color: #6c757d;
            font-size: 14px;
            font-weight: 500;
        }
        
        .table-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            overflow: hidden;
            margin: 0 20px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #f0f0f0;
            word-wrap: break-word;
        }
        
        th {
            background: #f8f9fa;
            font-weight: 600;
            color: #495057;
            font-size: 13px;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        
        tbody tr:hover {
            background: #f8f9fa;
        }
        
        .product-image {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 4px;
            cursor: pointer;
            transition: transform 0.2s;
        }
        
        .product-image:hover {
            transform: scale(1.1);
        }
        
        .product-name {
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .status-completed {
            background: #d4edda;
            color: #155724;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .generated-title {
            font-weight: bold;
        }
        
        .char-count {
            font-size: 11px;
            color: #6c757d;
            margin-top: 4px;
        }
        
        .char-count.good {
            color: #28a745;
        }
        
        .char-count.warning {
            color: #ffc107;
        }
        
        .char-count.danger {
            color: #dc3545;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin: 20px;
            padding: 20px;
        }
        
        .pagination button {
            padding: 8px 12px;
            border: 1px solid #ddd;
            background: white;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.2s;
        }
        
        .pagination button:hover:not(:disabled) {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }
        
        .pagination button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .pagination button.active {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        
        .modal.active {
            display: flex;
        }
        
        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 10px;
            max-width: 600px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .modal-header h2 {
            font-size: 20px;
            color: #333;
        }
        
        .close-btn {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #999;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #495057;
            font-size: 14px;
        }
        
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            font-family: inherit;
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }
        
        .form-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }
        
        .loading-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.7);
            z-index: 2000;
            justify-content: center;
            align-items: center;
        }
        
        .loading-overlay.active {
            display: flex;
        }
        
        .loading-spinner {
            background: white;
            padding: 30px;
            border-radius: 10px;
            text-align: center;
        }
        
        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #667eea;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            animation: spin 1s linear infinite;
            margin: 0 auto 15px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .checkbox {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        
        input[type="file"] {
            display: none;
        }
        
        /* 이미지 모달 스타일 */
        #imageModal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.9);
            justify-content: center;
            align-items: center;
        }
        
        #imageModal.active {
            display: flex;
        }
        
        #imageModal img {
            max-width: 80%;
            max-height: 80%;
        }
        
        #imageModal .close {
            position: absolute;
            top: 15px;
            right: 35px;
            color: #f1f1f1;
            font-size: 40px;
            font-weight: bold;
            transition: 0.3s;
            cursor: pointer;
        }
        
        #imageModal .close:hover {
            color: #bbb;
        }
        
        /* 날짜 필터 스타일 */
        .date-filter {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .date-filter select,
        .date-filter input {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .sort-controls {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .sort-controls select {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🛒 H-mall 상품 간결 문구 관리 시스템 v4.2 FINAL</h1>
        <p>CSV 업로드 → 자동 상품명 수집 → AI 문구 생성 → 통합 관리</p>
    </div>
    
    <div class="info-box">
        ✅ <strong>v3 + v4 통합 버전:</strong> 이미지 URL 자동 생성 로직 완벽 복구 | 한글 인코딩 완벽 처리 | 모든 기능 정상 작동
    </div>
    
    <div class="container">
        <div class="toolbar">
            <div class="toolbar-row">
                <button class="btn btn-info" onclick="downloadTemplate()">
                    📋 템플릿 다운로드
                </button>
                
                <label class="btn btn-primary" for="csvUpload">
                    📤 CSV 업로드
                    <input type="file" id="csvUpload" accept=".csv">
                </label>
                
                <button class="btn btn-success" onclick="openModal()">
                    ➕ 수동 추가
                </button>
                
                <button class="btn btn-warning" id="generateBtn" disabled>
                    🤖 선택 항목 AI 생성
                </button>
                
                <button class="btn btn-primary" onclick="exportCSV()">
                    📥 선택 CSV 다운로드
                </button>
                
                <button class="btn btn-danger" id="deleteBtn" disabled>
                    🗑️ 선택 삭제
                </button>
            </div>
            
            <div class="toolbar-row">
                <div class="search-box">
                    <input type="text" id="searchInput" placeholder="상품명, 제품코드, 생성문구 검색..." autocomplete="off">
                    <button class="btn" onclick="searchProducts()">🔍 검색</button>
                    <button class="btn btn-secondary" onclick="resetSearch()">↻ 초기화</button>
                    <div class="autocomplete-results" id="autocompleteResults"></div>
                </div>
                
                <div class="date-filter">
                    <select id="dateFilter" onchange="handleDateFilterChange()">
                        <option value="">기간 필터</option>
                        <option value="today">오늘</option>
                        <option value="yesterday">어제</option>
                        <option value="last_week">지난 일주일</option>
                        <option value="custom">사용자 지정</option>
                    </select>
                    <input type="date" id="startDate" style="display:none;">
                    <input type="date" id="endDate" style="display:none;">
                    <button class="btn" onclick="applyDateFilter()" id="applyDateBtn" style="display:none;">적용</button>
                </div>
                
                <div class="sort-controls">
                    <select id="sortOrder" onchange="changeSortOrder()">
                        <option value="desc">최근순</option>
                        <option value="asc">오래된순</option>
                    </select>
                </div>
                
                <span class="result-count" id="resultCount">전체 0개</span>
                
                <select id="perPageSelect" onchange="changePerPage()">
                    <option value="10">10개씩</option>
                    <option value="20" selected>20개씩</option>
                    <option value="50">50개씩</option>
                    <option value="100">100개씩</option>
                </select>
            </div>
        </div>
        
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th style="width: 30px;"><input type="checkbox" class="checkbox" onclick="toggleAllCheckboxes(this)"></th>
                        <th style="width: 80px;">이미지</th>
                        <th style="width: 100px;">제품코드</th>
                        <th style="width: 150px;">URL</th>
                        <th style="width: 180px;">상품명</th>
                        <th style="width: 100px;">브랜드</th>
                        <th style="width: 90px;">가격</th>
                        <th style="width: 150px;">생성문구</th>
                        <th style="width: 120px;">추가요청</th>
                        <th style="width: 70px;">상태</th>
                        <th style="width: 150px;">생성일시</th>
                        <th style="width: 60px;">작업</th>
                    </tr>
                </thead>
                <tbody id="tableBody">
                    <tr>
                        <td colspan="12" style="text-align: center; padding: 40px; color: #6c757d;">
                            데이터가 없습니다. CSV를 업로드하거나 수동으로 추가해주세요.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <div class="pagination" id="pagination"></div>
    </div>
    
    <!-- 상품 추가/수정 모달 -->
    <div class="modal" id="productModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">상품 추가</h2>
                <button class="close-btn" onclick="closeModal()">&times;</button>
            </div>
            
            <form id="productForm">
                <input type="hidden" id="productId">
                
                <div class="form-group">
                    <label>제품코드 *</label>
                    <input type="text" id="productCode" required>
                </div>
                
                <div class="form-group">
                    <label>URL</label>
                    <input type="text" id="productUrl">
                </div>
                
                <div class="form-group">
                    <label>이미지 URL</label>
                    <input type="text" id="imageUrl" placeholder="비워두면 자동 생성됨">
                </div>
                
                <div class="form-group">
                    <label>추가 요청사항</label>
                    <textarea id="additionalRequest"></textarea>
                </div>
                
                <div class="form-group">
                    <label>생성된 문구</label>
                    <input type="text" id="generatedTitle">
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">취소</button>
                    <button type="submit" class="btn btn-primary">저장</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- 이미지 모달 -->
    <div id="imageModal" class="modal">
        <span class="close-btn" onclick="closeImageModal()">&times;</span>
        <img id="modalImage" src="">
    </div>
    
    <!-- 로딩 오버레이 -->
    <div class="loading-overlay" id="loading">
        <div class="loading-spinner">
            <div class="spinner"></div>
            <div>처리 중...</div>
        </div>
    </div>
    
    <script>
        let allProducts = [];
        let currentPage = 1;
        let totalPages = 1;
        let currentKeyword = '';
        let autocompleteTimeout = null;
        let perPage = 20;
        let dateFilter = '';
        let startDate = '';
        let endDate = '';
        let sortOrder = 'desc';
        
        // CSV 업로드
        document.getElementById('csvUpload').addEventListener('change', async function(e) {
            const file = e.target.files[0];
            if (!file) return;
            
            const formData = new FormData();
            formData.append('action', 'upload_csv');
            formData.append('csv_file', file);
            
            showLoading(true);
            
            try {
                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    let message = `✅ 업로드 완료!\n- 추가: ${data.imported}개\n- 업데이트: ${data.updated}개`;
                    if (data.errors.length > 0) {
                        message += `\n\n⚠️ 오류:\n${data.errors.slice(0, 5).join('\n')}`;
                        if (data.errors.length > 5) message += `\n... 외 ${data.errors.length - 5}개`;
                    }
                    alert(message);
                    loadProducts();
                } else {
                    alert('❌ 오류: ' + data.error);
                }
            } catch (error) {
                console.error('Upload error:', error);
                alert('❌ 오류: ' + error.message);
            } finally {
                document.getElementById('csvUpload').value = '';
                showLoading(false);
            }
        });
        
        // 검색 입력 - 자동완성
        document.getElementById('searchInput').addEventListener('input', function(e) {
            const keyword = e.target.value.trim();
            
            clearTimeout(autocompleteTimeout);
            
            if (keyword.length < 2) {
                document.getElementById('autocompleteResults').classList.remove('show');
                return;
            }
            
            autocompleteTimeout = setTimeout(async () => {
                const formData = new FormData();
                formData.append('action', 'autocomplete');
                formData.append('keyword', keyword);
                
                try {
                    const response = await fetch('', {
                        method: 'POST',
                        body: formData
                    });
                    
                    const data = await response.json();
                    
                    if (data.success && data.suggestions.length > 0) {
                        const resultsDiv = document.getElementById('autocompleteResults');
                        resultsDiv.innerHTML = data.suggestions.map(s => 
                            `<div class="autocomplete-item" onclick="selectSuggestion('${s.replace(/'/g, "\\'")}')">${s}</div>`
                        ).join('');
                        resultsDiv.classList.add('show');
                    } else {
                        document.getElementById('autocompleteResults').classList.remove('show');
                    }
                } catch (error) {
                    console.error('Autocomplete error:', error);
                }
            }, 300);
        });
        
        // 자동완성 선택
        function selectSuggestion(text) {
            document.getElementById('searchInput').value = text;
            document.getElementById('autocompleteResults').classList.remove('show');
            searchProducts();
        }
        
        // 검색 엔터키
        document.getElementById('searchInput').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                document.getElementById('autocompleteResults').classList.remove('show');
                searchProducts();
            }
        });
        
        // 외부 클릭 시 자동완성 닫기
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.search-box')) {
                document.getElementById('autocompleteResults').classList.remove('show');
            }
        });
        
        function searchProducts() {
            currentKeyword = document.getElementById('searchInput').value;
            currentPage = 1;
            loadProducts();
        }
        
        // 검색 초기화
        function resetSearch() {
            document.getElementById('searchInput').value = '';
            document.getElementById('dateFilter').value = '';
            document.getElementById('startDate').style.display = 'none';
            document.getElementById('endDate').style.display = 'none';
            document.getElementById('applyDateBtn').style.display = 'none';
            document.getElementById('sortOrder').value = 'desc';
            currentKeyword = '';
            dateFilter = '';
            startDate = '';
            endDate = '';
            sortOrder = 'desc';
            currentPage = 1;
            loadProducts();
        }
        
        // 페이징 갯수 변경
        function changePerPage() {
            perPage = parseInt(document.getElementById('perPageSelect').value);
            currentPage = 1;
            loadProducts();
        }
        
        // 정렬 순서 변경
        function changeSortOrder() {
            sortOrder = document.getElementById('sortOrder').value;
            currentPage = 1;
            loadProducts();
        }
        
        // 날짜 필터 변경
        function handleDateFilterChange() {
            dateFilter = document.getElementById('dateFilter').value;
            const startInput = document.getElementById('startDate');
            const endInput = document.getElementById('endDate');
            const applyBtn = document.getElementById('applyDateBtn');
            
            if (dateFilter === 'custom') {
                startInput.style.display = 'block';
                endInput.style.display = 'block';
                applyBtn.style.display = 'block';
            } else {
                startInput.style.display = 'none';
                endInput.style.display = 'none';
                applyBtn.style.display = 'none';
                applyDateFilter();
            }
        }
        
        // 날짜 필터 적용
        function applyDateFilter() {
            startDate = document.getElementById('startDate').value;
            endDate = document.getElementById('endDate').value;
            currentPage = 1;
            loadProducts();
        }
        
        // 상품 추가/수정 폼 제출
        document.getElementById('productForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const id = document.getElementById('productId').value;
            
            const formData = new FormData();
            formData.append('action', id ? 'update_product' : 'add_product');
            if (id) formData.append('id', id);
            formData.append('product_code', document.getElementById('productCode').value);
            formData.append('url', document.getElementById('productUrl').value);
            formData.append('image_url', document.getElementById('imageUrl').value);
            formData.append('additional_request', document.getElementById('additionalRequest').value);
            formData.append('generated_title', document.getElementById('generatedTitle').value);
            
            showLoading(true);
            
            try {
                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    alert('✅ 저장되었습니다.');
                    closeModal();
                    loadProducts();
                } else {
                    alert('❌ 오류: ' + data.error);
                }
            } catch (error) {
                console.error('Save error:', error);
                alert('❌ 오류: ' + error.message);
            } finally {
                showLoading(false);
            }
        });
        
        // 상품 목록 로드
        async function loadProducts() {
            showLoading(true);
            
            const formData = new FormData();
            formData.append('action', 'search_products');
            formData.append('keyword', currentKeyword);
            formData.append('page', currentPage);
            formData.append('per_page', perPage);
            formData.append('date_filter', dateFilter);
            formData.append('start_date', startDate);
            formData.append('end_date', endDate);
            formData.append('sort_order', sortOrder);
            
            try {
                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    allProducts = data.products;
                    totalPages = data.total_pages;
                    renderTable(data.products);
                    renderPagination(data.total_pages, data.page);
                    document.getElementById('resultCount').textContent = `전체 ${data.total}개`;
                }
            } catch (error) {
                console.error('Load error:', error);
            } finally {
                showLoading(false);
            }
        }
        
        // 테이블 렌더링
        function renderTable(products) {
            const tbody = document.getElementById('tableBody');
            
            if (products.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="12" style="text-align: center; padding: 40px; color: #6c757d;">
                            ${currentKeyword ? '검색 결과가 없습니다.' : '데이터가 없습니다. CSV를 업로드하거나 수동으로 추가해주세요.'}
                        </td>
                    </tr>
                `;
                return;
            }
            
            tbody.innerHTML = products.map(product => {
                const charCount = (product.generated_title || '').length;
                let charClass = 'good';
                if (charCount > 15) charClass = 'danger';
                else if (charCount > 12) charClass = 'warning';
                
                return `
                    <tr>
                        <td><input type="checkbox" class="row-checkbox checkbox" data-id="${product.id}" onchange="updateToolbarButtons()"></td>
                        <td>
                            ${product.image_url ? `<img src="${product.image_url}" class="product-image" alt="상품이미지" onclick="showImageModal('${product.image_url}')" onerror="this.style.display='none'">` : '<span style="color: #999; font-size: 12px;">이미지 없음</span>'}
                        </td>
                        <td>${product.product_code || ''}</td>
                        <td><a href="${product.url || '#'}" target="_blank" style="color: #667eea; text-decoration: none;">${product.url ? product.url.substring(0, 30) + '...' : '-'}</a></td>
                        <td class="product-name" title="${product.product_name || ''}">${product.product_name || '정보 없음'}</td>
                        <td>${product.brand_name || '-'}</td>
                        <td>${product.price ? Number(product.price).toLocaleString() + '원' : '-'}</td>
                        <td>
                            ${product.generated_title ? `
                                <div class="generated-title">${product.generated_title}</div>
                                <div class="char-count ${charClass}">${charCount}자</div>
                            ` : '<span style="color: #999;">미생성</span>'}
                        </td>
                        <td style="max-width: 120px; overflow: hidden; text-overflow: ellipsis;" title="${product.additional_request || ''}">${product.additional_request || '-'}</td>
                        <td>
                            <span class="status-badge status-${product.status}">
                                ${product.status === 'completed' ? '완료' : '대기'}
                            </span>
                        </td>
                        <td style="font-size: 12px;">${product.created_at || '-'}</td>
                        <td>
                            <button class="btn btn-secondary" style="padding: 6px 12px; font-size: 12px;" onclick="editProduct('${product.id}')">수정</button>
                        </td>
                    </tr>
                `;
            }).join('');
        }
        
        // 페이지네이션 렌더링
        function renderPagination(totalPages, currentPage) {
            const pagination = document.getElementById('pagination');
            
            if (totalPages <= 1) {
                pagination.style.display = 'none';
                return;
            }
            
            pagination.style.display = 'flex';
            
            let html = `
                <button ${currentPage === 1 ? 'disabled' : ''} onclick="goToPage(1)">처음</button>
                <button ${currentPage === 1 ? 'disabled' : ''} onclick="goToPage(${currentPage - 1})">이전</button>
            `;
            
            const startPage = Math.max(1, currentPage - 2);
            const endPage = Math.min(totalPages, currentPage + 2);
            
            for (let i = startPage; i <= endPage; i++) {
                html += `<button class="${i === currentPage ? 'active' : ''}" onclick="goToPage(${i})">${i}</button>`;
            }
            
            html += `
                <button ${currentPage === totalPages ? 'disabled' : ''} onclick="goToPage(${currentPage + 1})">다음</button>
                <button ${currentPage === totalPages ? 'disabled' : ''} onclick="goToPage(${totalPages})">마지막</button>
            `;
            
            pagination.innerHTML = html;
        }
        
        // 페이지 이동
        function goToPage(page) {
            currentPage = page;
            loadProducts();
        }
        
        // 모달 열기
        function openModal(product = null) {
            const modal = document.getElementById('productModal');
            const title = document.getElementById('modalTitle');
            
            if (product) {
                title.textContent = '상품 수정';
                document.getElementById('productId').value = product.id;
                document.getElementById('productCode').value = product.product_code;
                document.getElementById('productUrl').value = product.url;
                document.getElementById('imageUrl').value = product.image_url;
                document.getElementById('additionalRequest').value = product.additional_request;
                document.getElementById('generatedTitle').value = product.generated_title;
            } else {
                title.textContent = '상품 추가';
                document.getElementById('productForm').reset();
                document.getElementById('productId').value = '';
            }
            
            modal.classList.add('active');
        }
        
        // 모달 닫기
        function closeModal() {
            document.getElementById('productModal').classList.remove('active');
        }
        
        // 상품 수정
        function editProduct(id) {
            const product = allProducts.find(p => p.id === id);
            if (product) {
                openModal(product);
            }
        }
        
        // 선택된 ID 가져오기
        function getSelectedIds() {
            const checkboxes = document.querySelectorAll('.row-checkbox:checked');
            return Array.from(checkboxes).map(cb => cb.dataset.id);
        }
        
        // 전체 선택/해제
        function toggleAllCheckboxes(checkbox) {
            const checkboxes = document.querySelectorAll('.row-checkbox');
            checkboxes.forEach(cb => cb.checked = checkbox.checked);
            updateToolbarButtons();
        }
        
        // 툴바 버튼 상태 업데이트
        function updateToolbarButtons() {
            const selected = getSelectedIds();
            document.getElementById('generateBtn').disabled = selected.length === 0;
            document.getElementById('deleteBtn').disabled = selected.length === 0;
        }
        
        // AI 문구 생성
        document.getElementById('generateBtn').addEventListener('click', async function() {
            const ids = getSelectedIds();
            if (ids.length === 0) return;
            
            showLoading(true);
            
            const formData = new FormData();
            formData.append('action', 'generate_titles');
            formData.append('ids', JSON.stringify(ids));
            
            try {
                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    alert(`✅ 생성 완료!\n- 생성: ${data.generated}개\n${data.errors.length > 0 ? '- 오류: ' + data.errors.length + '개' : ''}`);
                    loadProducts();
                } else {
                    alert('❌ 오류: ' + data.error);
                }
            } catch (error) {
                console.error('Generate error:', error);
                alert('❌ 오류: ' + error.message);
            } finally {
                showLoading(false);
            }
        });
        
        // CSV 내보내기
        function exportCSV() {
            const ids = getSelectedIds();
            
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '';
            
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'export_csv';
            form.appendChild(actionInput);
            
            const idsInput = document.createElement('input');
            idsInput.type = 'hidden';
            idsInput.name = 'ids';
            idsInput.value = JSON.stringify(ids);
            form.appendChild(idsInput);
            
            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);
        }
        
        // 템플릿 다운로드
        function downloadTemplate() {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '';
            
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'download_template';
            form.appendChild(actionInput);
            
            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);
        }
        
        // 선택 삭제
        document.getElementById('deleteBtn').addEventListener('click', async function() {
            const ids = getSelectedIds();
            if (ids.length === 0) return;
            
            if (!confirm(`${ids.length}개 항목을 삭제하시겠습니까?`)) return;
            
            showLoading(true);
            
            const formData = new FormData();
            formData.append('action', 'delete_products');
            formData.append('ids', JSON.stringify(ids));
            
            try {
                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    alert(`✅ ${data.deleted}개 삭제되었습니다.`);
                    loadProducts();
                } else {
                    alert('❌ 오류: ' + data.error);
                }
            } catch (error) {
                console.error('Delete error:', error);
                alert('❌ 오류: ' + error.message);
            } finally {
                showLoading(false);
            }
        });
        
        // 로딩 표시
        function showLoading(show) {
            const loading = document.getElementById('loading');
            if (show) {
                loading.classList.add('active');
            } else {
                loading.classList.remove('active');
            }
        }
        
        // 이미지 모달 열기
        function showImageModal(src) {
            if (!src) return;
            const modal = document.getElementById('imageModal');
            const img = document.getElementById('modalImage');
            img.src = src;
            modal.classList.add('active');
        }
        
        // 이미지 모달 닫기
        function closeImageModal() {
            const modal = document.getElementById('imageModal');
            modal.classList.remove('active');
        }
        
        // 초기 로드
        loadProducts();
    </script>
</body>
</html>