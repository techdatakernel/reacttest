<?php
// OpenAI API 도우미 함수

class OpenAIHelper {
    private $apiKey;
    private $apiUrl = 'https://api.openai.com/v1/chat/completions';
    
    public function __construct($apiKey) {
        $this->apiKey = $apiKey;
    }
    
    /**
     * 이미지 SEO 최적화
     */
    public function optimizeImageSEO($imageUrl, $pageUrl, $currentFilename) {
        $prompt = $this->buildImageSEOPrompt($imageUrl, $pageUrl, $currentFilename);
        
        $data = [
            'model' => 'gpt-4o-mini',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => '당신은 한국의 SEO 전문가입니다. 이미지 SEO 최적화에 특화되어 있으며, 파일명은 영어로만, ALT와 Title은 한국어로 자연스럽고 검색 엔진에 최적화된 이미지 메타데이터를 작성합니다. 응답은 반드시 JSON 형식으로만 제공하세요.'
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'max_tokens' => 500,
            'temperature' => 0.3,
        ];
        
        $result = $this->makeRequest($data);
        
        if ($result && isset($result['choices'][0]['message']['content'])) {
            $content = trim($result['choices'][0]['message']['content']);
            
            // JSON 응답 파싱
            $jsonData = $this->extractJSON($content);
            
            if ($jsonData) {
                return [
                    'success' => true,
                    'data' => $jsonData
                ];
            }
        }
        
        return [
            'success' => false,
            'error' => 'OpenAI API 응답을 파싱할 수 없습니다.'
        ];
    }
    
    /**
     * 메타 태그 SEO 최적화 (새 기능)
     */
    public function optimizeMetaSEO($pagePath, $fullUrl, $brand = '') {
        $prompt = $this->buildMetaSEOPrompt($pagePath, $fullUrl, $brand);
        
        $data = [
            'model' => 'gpt-4o-mini',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => '당신은 한국의 SEO 전문가입니다. 웹페이지 메타 태그 최적화에 특화되어 있으며, 검색 엔진과 소셜 미디어에서 최적의 성과를 내는 한국어 메타 태그를 작성합니다. 모든 응답은 JSON 형식으로만 제공하세요.'
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'max_tokens' => 800,
            'temperature' => 0.3,
        ];
        
        $result = $this->makeRequest($data);
        
        if ($result && isset($result['choices'][0]['message']['content'])) {
            $content = trim($result['choices'][0]['message']['content']);
            
            // JSON 응답 파싱
            $jsonData = $this->extractJSON($content);
            
            if ($jsonData) {
                return [
                    'success' => true,
                    'data' => $jsonData
                ];
            }
        }
        
        return [
            'success' => false,
            'error' => 'OpenAI API 응답을 파싱할 수 없습니다.'
        ];
    }
    
    private function buildImageSEOPrompt($imageUrl, $pageUrl, $currentFilename) {
        return "
웹사이트 이미지 SEO 최적화를 위한 분석을 요청합니다.

입력 정보:
- 이미지 URL: {$imageUrl}
- 페이지 URL: {$pageUrl}
- 현재 파일명: {$currentFilename}

다음 항목들을 SEO 최적화 관점에서 개선해주세요:

1. SEO 권장 파일명 
   - 반드시 영어로만 작성 (한글 사용 금지)
   - 소문자와 하이픈(-) 구분자만 사용
   - 원본 확장자 유지 (.jpg, .png, .webp 등)
   - 50자 이하
   - 브랜드명과 주요 키워드 포함
   - 예시: hoegaarden-brand-story-section-image.png

2. ALT 텍스트 
   - 한국어로 작성
   - 이미지 내용 정확히 설명
   - 키워드 포함
   - 125자 이하

3. Title 텍스트 
   - 한국어로 작성
   - 사용자에게 유용한 정보
   - 브랜드/제품 특징 강조
   - 100자 이하

응답 형식 (JSON만):
{
    \"seo_filename\": \"영어-파일명-only.jpg\",
    \"alt_text\": \"한국어 ALT 텍스트\",
    \"title_text\": \"한국어 Title 텍스트\"
}

주의사항:
- 파일명은 반드시 영어로만 작성 (한글 절대 금지)
- ALT와 Title은 한국어로 자연스럽게 작성
- 브랜드명이 URL에 포함되어 있다면 영어로 변환하여 활용
- 검색 키워드를 고려한 최적화
- JSON 형식 외의 다른 텍스트는 포함하지 마세요

파일명 영어 변환 예시:
- 호가든 → hoegaarden
- 한맥 → hanmac
- 카스 → cass
- 브랜드 스토리 → brand-story
- 제품 이미지 → product-image
- 메인 배너 → main-banner
";
    }
    
    private function buildMetaSEOPrompt($pagePath, $fullUrl, $brand) {
        return "
웹페이지 메타 태그 SEO 최적화를 위한 분석을 요청합니다.

입력 정보:
- 페이지 경로: {$pagePath}
- 전체 URL: {$fullUrl}
- 브랜드: {$brand}

다음 메타 태그들을 SEO 최적화 관점에서 한국어로 작성해주세요:

1. Title (페이지 제목)
   - 한국어로 작성
   - 60자 이하 권장
   - 브랜드명 포함
   - 검색 키워드 최적화
   - 클릭을 유도하는 매력적인 제목

2. Description (페이지 설명)
   - 한국어로 작성
   - 160자 이하 권장
   - 페이지 내용 요약
   - 키워드 자연스럽게 포함
   - 사용자 행동 유도

3. Keywords (키워드)
   - 쉼표로 구분
   - 5-10개 정도
   - 관련성 높은 키워드

4. OG Title (오픈그래프 제목)
   - 한국어로 작성
   - 60자 이하
   - 소셜 미디어 공유에 최적화
   - Title과 유사하지만 더 매력적

5. OG Description (오픈그래프 설명)
   - 한국어로 작성
   - 160자 이하
   - 소셜 미디어 미리보기용
   - 클릭 유도 문구 포함

6. Twitter Title (트위터 제목)
   - 한국어로 작성
   - 60자 이하
   - 트위터 카드 최적화

7. Twitter Description (트위터 설명)
   - 한국어로 작성
   - 160자 이하
   - 트위터 공유 최적화

응답 형식 (JSON만):
{
    \"title\": \"최적화된 페이지 제목\",
    \"description\": \"최적화된 페이지 설명\",
    \"keywords\": \"키워드1, 키워드2, 키워드3\",
    \"og_title\": \"소셜 미디어용 제목\",
    \"og_description\": \"소셜 미디어용 설명\",
    \"twitter_title\": \"트위터용 제목\",
    \"twitter_description\": \"트위터용 설명\"
}

최적화 가이드라인:
- 모든 텍스트는 한국어로 자연스럽게 작성
- 브랜드명과 관련 키워드 적절히 포함
- 검색 의도와 사용자 니즈 고려
- 각 플랫폼 특성에 맞는 톤앤매너
- JSON 형식 외의 다른 텍스트는 포함하지 마세요

브랜드별 특화 키워드:
- 한맥: 프리미엄 라거, 국내산 쌀, 부드러운
- 호가든: 벨기에 밀맥주, 화이트 비어, 상큼한
- 카스: 라이트 맥주, 깔끔한, 시원한
- 클라우드: 생맥주, 부드러운, 프리미엄
";
    }
    
    private function makeRequest($data) {
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apiKey
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->apiUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            return json_decode($response, true);
        }
        
        return false;
    }
    
    private function extractJSON($content) {
        // JSON 블록 추출 시도
        if (preg_match('/```json\s*(.*?)\s*```/s', $content, $matches)) {
            $jsonStr = $matches[1];
        } else if (preg_match('/\{.*\}/s', $content, $matches)) {
            $jsonStr = $matches[0];
        } else {
            return false;
        }
        
        $decoded = json_decode($jsonStr, true);
        
        if (json_last_error() === JSON_ERROR_NONE) {
            // 이미지 SEO 데이터 후처리
            if (isset($decoded['seo_filename'])) {
                $decoded['seo_filename'] = $this->ensureEnglishFilename($decoded['seo_filename']);
            }
            
            return $decoded;
        }
        
        return false;
    }
    
    private function ensureEnglishFilename($filename) {
        // 한글을 영어로 매핑
        $koreanToEnglish = [
            '호가든' => 'hoegaarden',
            '한맥' => 'hanmac',
            '카스' => 'cass',
            '클라우드' => 'cloud',
            '맥주' => 'beer',
            '생맥주' => 'draft-beer',
            '라거' => 'lager',
            '프리미엄' => 'premium',
            '브랜드' => 'brand',
            '스토리' => 'story',
            '이미지' => 'image',
            '제품' => 'product',
            '캠페인' => 'campaign',
            '이벤트' => 'event'
        ];
        
        // 한글 치환
        foreach ($koreanToEnglish as $korean => $english) {
            $filename = str_replace($korean, $english, $filename);
        }
        
        // 남은 한글 제거 및 정규화
        $filename = preg_replace('/[가-힣]/u', '', $filename);
        $filename = preg_replace('/[^a-zA-Z0-9\-\.]/', '-', $filename);
        $filename = preg_replace('/-+/', '-', $filename);
        $filename = trim($filename, '-');
        $filename = strtolower($filename);
        
        return $filename;
    }
}
?>