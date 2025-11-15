# 🎯 ABtest 솔루션 표준 적용 가이드

**버전**: 1.0  
**최종 업데이트**: 2025-11-15  
**대상**: 웹 개발자, 마케팅 담당자

---

## 📚 목차

1. [개요](#개요)
2. [파일 구조](#파일-구조)
3. [기본 설정](#기본-설정)
4. [HTML 마크업 표준](#html-마크업-표준)
5. [ABtest 유형별 적용](#abtest-유형별-적용)
6. [Config 설정](#config-설정)
7. [배포 체크리스트](#배포-체크리스트)
8. [FAQ](#faq)

---

## 개요

### 🎯 ABtest 솔루션이란?

웹사이트의 특정 요소(이미지, 텍스트, 레이아웃 등)에 대해 A/B 테스트를 수행하여 사용자 반응을 측정하는 솔루션입니다.

### ✨ 주요 특징

- ✅ **간단한 HTML 마크업**: 기존 코드에 최소한의 수정만으로 적용
- ✅ **자동 Variant 분배**: 사용자를 50/50으로 자동 분배
- ✅ **쿠키 기반**: 사용자별 일관된 Variant 제공 (30일)
- ✅ **멀티 페이지 지원**: 여러 페이지에서 동시 테스트 가능
- ✅ **통합 대시보드**: 모든 테스트 결과를 한 곳에서 관리

---

## 파일 구조

### 프로젝트 디렉토리 구조

```
/var/www/html/
└── ob/stella/abtest2/
    ├── index.html                          # 관리 대시보드
    ├── api/
    │   ├── ab-test-config.php              # 설정 API
    │   ├── ab-test-config.json             # 설정 파일
    │   ├── ab-test-analytics.php           # 분석 API
    │   ├── ab-test-log.php                 # 로그 저장 API
    │   └── ab-test-logs/                   # 로그 저장소
    ├── js/
    │   └── ab-test-tracker.js              # ✅ 핵심 라이브러리
    │
    └── 테스트 페이지들 (여러 브랜드)
        ├── brand-a/
        │   ├── product-1.html              # ← ABtest 적용
        │   ├── product-2.html              # ← ABtest 적용
        │   └── images/
        ├── brand-b/
        │   ├── index.html                  # ← ABtest 적용
        │   └── images/
        └── brand-c/
            └── landing.html                # ← ABtest 적용
```

---

## 기본 설정

### Step 1: ab-test-tracker.js 로드

**모든 ABtest 페이지에서 필수적으로 추가해야 함**

```html
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>My Product Page</title>
</head>
<body>
    <!-- 페이지 컨텐츠 -->
    
    <!-- ✅ Step 1: ABtest 라이브러리 로드 (body 끝 부분) -->
    <script src="https://abi-ops.miraepmp.co.kr/ob/stella/abtest2/js/ab-test-tracker.js"></script>
    
    <!-- ✅ Step 2: ABtest 초기화 -->
    <script>
        document.addEventListener('DOMContentLoaded', async () => {
            await ABTestTracker.init();
        });
    </script>
</body>
</html>
```

### Step 2: 페이지 경로 등록

Config 파일에 페이지를 등록해야 합니다.

```json
{
    "pages": {
        "/brand-a/product-1.html": {
            "enabled": true,
            "testName": "Brand A - 제품 1 배너 테스트",
            "mode": "ab_test",
            "variants": {
                "A": {"name": "Variant A", "order": []},
                "B": {"name": "Variant B", "order": []}
            }
        }
    }
}
```

---

## HTML 마크업 표준

### 📌 핵심 원칙

ABtest를 적용하려는 요소는 다음 조건을 만족해야 합니다:

1. ✅ **두 개의 Variant가 존재** (`.dtc-dwcr-list` 클래스)
2. ✅ **각각 고유한 `data-variant` 속성** ("A" 또는 "B")
3. ✅ **초기 상태: `display: none` 또는 `visibility: hidden`**
4. ✅ **활성 상태: `.active` 클래스 추가 시 표시**

---

## ABtest 유형별 적용

### 유형 1️⃣: 이미지 변경 테스트

**상황**: 제품 이미지 두 가지 중 어느 것이 더 높은 클릭률을 기록하는지 테스트

#### HTML 마크업

```html
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>Brand A - Product</title>
    <style>
        .dtc-dwcr-list {
            display: none;
        }
        
        .dtc-dwcr-list.active {
            display: block;
        }
        
        .product-image {
            width: 100%;
            max-width: 500px;
            border-radius: 8px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>프리미엄 제품</h1>
        <p>최고 품질의 제품입니다.</p>
        
        <!-- ✅ Variant A: 이미지 1 -->
        <div class="dtc-dwcr-list" data-variant="A">
            <img src="/images/product-lifestyle-1.jpg" 
                 alt="라이프스타일 이미지 1" 
                 class="product-image">
        </div>
        
        <!-- ✅ Variant B: 이미지 2 -->
        <div class="dtc-dwcr-list" data-variant="B">
            <img src="/images/product-lifestyle-2.jpg" 
                 alt="라이프스타일 이미지 2" 
                 class="product-image">
        </div>
        
        <button class="buy-btn">구매하기</button>
    </div>
    
    <script src="https://abi-ops.miraepmp.co.kr/ob/stella/abtest2/js/ab-test-tracker.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', async () => {
            await ABTestTracker.init();
        });
    </script>
</body>
</html>
```

#### 📊 Config 설정

```json
{
    "pages": {
        "/brand-a/product.html": {
            "enabled": true,
            "testName": "Brand A - 제품 이미지 테스트",
            "mode": "ab_test",
            "variants": {
                "A": {"name": "라이프스타일 이미지 1"},
                "B": {"name": "라이프스타일 이미지 2"}
            }
        }
    }
}
```

---

### 유형 2️⃣: CTA 버튼 텍스트/스타일 테스트

**상황**: 버튼 텍스트나 색상이 구매율에 영향을 주는지 테스트

#### HTML 마크업

```html
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>Brand B - CTA Test</title>
    <style>
        .dtc-dwcr-list {
            display: none;
            margin-top: 20px;
        }
        
        .dtc-dwcr-list.active {
            display: block;
        }
        
        .cta-button {
            padding: 15px 30px;
            font-size: 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .cta-green {
            background: #28a745;
            color: white;
        }
        
        .cta-green:hover {
            background: #218838;
            transform: translateY(-2px);
        }
        
        .cta-red {
            background: #dc3545;
            color: white;
        }
        
        .cta-red:hover {
            background: #c82333;
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>지금 구매하세요!</h2>
        <p>한정된 시간만 할인합니다.</p>
        
        <!-- ✅ Variant A: 초록색 버튼 -->
        <div class="dtc-dwcr-list" data-variant="A">
            <button class="cta-button cta-green" 
                    id="buy-btn-a" 
                    onclick="trackClick('buy-btn-a')">
                지금 구매하기
            </button>
        </div>
        
        <!-- ✅ Variant B: 빨간색 버튼 -->
        <div class="dtc-dwcr-list" data-variant="B">
            <button class="cta-button cta-red" 
                    id="buy-btn-b" 
                    onclick="trackClick('buy-btn-b')">
                🎉 지금 바로 구매! 🎉
            </button>
        </div>
    </div>
    
    <script src="https://abi-ops.miraepmp.co.kr/ob/stella/abtest2/js/ab-test-tracker.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', async () => {
            await ABTestTracker.init();
        });
        
        function trackClick(buttonId) {
            console.log(`Button clicked: ${buttonId}`);
            // 추가 분석 코드
        }
    </script>
</body>
</html>
```

#### 📊 Config 설정

```json
{
    "pages": {
        "/brand-b/purchase.html": {
            "enabled": true,
            "testName": "Brand B - CTA 버튼 색상 테스트",
            "mode": "ab_test",
            "variants": {
                "A": {"name": "초록색 버튼 (중성)"},
                "B": {"name": "빨간색 버튼 (긴급성)"}
            }
        }
    }
}
```

---

### 유형 3️⃣: 레이아웃 변경 테스트

**상황**: 제품 정보의 표시 순서 변경이 전환율에 미치는 영향 측정

#### HTML 마크업

```html
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>Brand C - Layout Test</title>
    <style>
        .dtc-dwcr-list {
            display: none;
        }
        
        .dtc-dwcr-list.active {
            display: grid;
        }
        
        /* Variant A: 세로 레이아웃 */
        .layout-vertical {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        
        /* Variant B: 가로 레이아웃 */
        .layout-horizontal {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            align-items: center;
        }
        
        .product-image {
            width: 100%;
            border-radius: 8px;
        }
        
        .product-info {
            padding: 20px;
        }
        
        .price {
            font-size: 28px;
            font-weight: bold;
            color: #1a472a;
            margin: 15px 0;
        }
        
        .buy-button {
            background: #1a472a;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>프리미엄 제품</h1>
        
        <!-- ✅ Variant A: 세로 레이아웃 -->
        <div class="dtc-dwcr-list" data-variant="A">
            <div class="layout-vertical">
                <img src="/images/product.jpg" 
                     alt="제품 이미지" 
                     class="product-image">
                <div class="product-info">
                    <h2>프리미엄 제품명</h2>
                    <p>세로 레이아웃으로 이미지를 먼저 보여주는 전통적인 방식</p>
                    <div class="price">₩99,900</div>
                    <button class="buy-button" 
                            id="buy-a" 
                            onclick="trackPurchase('variant-a')">
                        구매하기
                    </button>
                </div>
            </div>
        </div>
        
        <!-- ✅ Variant B: 가로 레이아웃 -->
        <div class="dtc-dwcr-list" data-variant="B">
            <div class="layout-horizontal">
                <img src="/images/product.jpg" 
                     alt="제품 이미지" 
                     class="product-image">
                <div class="product-info">
                    <h2>프리미엄 제품명</h2>
                    <p>가로 레이아웃으로 이미지와 정보를 동시에 표시하는 현대적 방식</p>
                    <div class="price">₩99,900</div>
                    <button class="buy-button" 
                            id="buy-b" 
                            onclick="trackPurchase('variant-b')">
                        구매하기
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://abi-ops.miraepmp.co.kr/ob/stella/abtest2/js/ab-test-tracker.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', async () => {
            await ABTestTracker.init();
        });
        
        function trackPurchase(variant) {
            console.log(`Purchase from ${variant}`);
        }
    </script>
</body>
</html>
```

#### 📊 Config 설정

```json
{
    "pages": {
        "/brand-c/product.html": {
            "enabled": true,
            "testName": "Brand C - 레이아웃 테스트",
            "mode": "ab_test",
            "variants": {
                "A": {"name": "세로 레이아웃 (이미지 우선)"},
                "B": {"name": "가로 레이아웃 (동시 표시)"}
            }
        }
    }
}
```

---

### 유형 4️⃣: 링크/배너 위치 변경 테스트

**상황**: 광고 배너 위치 변경에 따른 클릭률 변화 측정

#### HTML 마크업

```html
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>Brand D - Banner Position Test</title>
    <style>
        .dtc-dwcr-list {
            display: none;
        }
        
        .dtc-dwcr-list.active {
            display: block;
        }
        
        .content {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .banner {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            margin: 20px 0;
        }
        
        .banner a {
            display: inline-block;
            background: white;
            color: #667eea;
            padding: 12px 24px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            margin-top: 10px;
            transition: all 0.3s;
        }
        
        .banner a:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }
        
        .article {
            line-height: 1.8;
            color: #333;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="content">
        <!-- ✅ Variant A: 배너를 상단에 배치 -->
        <div class="dtc-dwcr-list" data-variant="A">
            <div class="banner">
                <h3>🎉 특별 오퍼: 50% 할인!</h3>
                <p>이 주에만 모든 상품에 50% 할인을 제공합니다.</p>
                <a href="/promotion">지금 쇼핑하기 →</a>
            </div>
            
            <article class="article">
                <h2>제품 리뷰</h2>
                <p>이 제품은 시장에서 가장 인기 있는 제품 중 하나입니다...</p>
                <!-- 더 많은 콘텐츠 -->
            </article>
        </div>
        
        <!-- ✅ Variant B: 배너를 하단에 배치 -->
        <div class="dtc-dwcr-list" data-variant="B">
            <article class="article">
                <h2>제품 리뷰</h2>
                <p>이 제품은 시장에서 가장 인기 있는 제품 중 하나입니다...</p>
                <!-- 더 많은 콘텐츠 -->
            </article>
            
            <div class="banner">
                <h3>🎉 특별 오퍼: 50% 할인!</h3>
                <p>이 주에만 모든 상품에 50% 할인을 제공합니다.</p>
                <a href="/promotion">지금 쇼핑하기 →</a>
            </div>
        </div>
    </div>
    
    <script src="https://abi-ops.miraepmp.co.kr/ob/stella/abtest2/js/ab-test-tracker.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', async () => {
            await ABTestTracker.init();
        });
    </script>
</body>
</html>
```

#### 📊 Config 설정

```json
{
    "pages": {
        "/brand-d/article.html": {
            "enabled": true,
            "testName": "Brand D - 배너 위치 테스트",
            "mode": "ab_test",
            "variants": {
                "A": {"name": "배너 상단 위치"},
                "B": {"name": "배너 하단 위치"}
            }
        }
    }
}
```

---

### 유형 5️⃣: 텍스트 메시지 변경 테스트

**상황**: 제품 설명 메시지의 톤이 전환율에 미치는 영향 측정

#### HTML 마크업

```html
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>Brand E - Messaging Test</title>
    <style>
        .dtc-dwcr-list {
            display: none;
        }
        
        .dtc-dwcr-list.active {
            display: block;
        }
        
        .product-card {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            max-width: 400px;
        }
        
        .product-title {
            font-size: 20px;
            font-weight: bold;
            color: #333;
            margin-bottom: 10px;
        }
        
        .product-message {
            color: #666;
            line-height: 1.6;
            margin-bottom: 15px;
            font-size: 14px;
        }
        
        .price {
            font-size: 24px;
            font-weight: bold;
            color: #1a472a;
            margin: 15px 0;
        }
        
        .btn {
            width: 100%;
            padding: 12px;
            background: #1a472a;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="container" style="padding: 20px;">
        <!-- ✅ Variant A: 합리적인 톤 (기능 강조) -->
        <div class="dtc-dwcr-list" data-variant="A">
            <div class="product-card">
                <div class="product-title">프리미엄 스킨케어</div>
                <div class="product-message">
                    과학적으로 입증된 성분으로 피부 개선을 돕습니다.
                    순수한 천연 성분 90% 이상 함유.
                </div>
                <div class="price">₩45,000</div>
                <button class="btn" onclick="trackClick('variant-a')">
                    상세 보기
                </button>
            </div>
        </div>
        
        <!-- ✅ Variant B: 감정적인 톤 (이점 강조) -->
        <div class="dtc-dwcr-list" data-variant="B">
            <div class="product-card">
                <div class="product-title">프리미엄 스킨케어</div>
                <div class="product-message">
                    당신의 피부는 최고급 관리를 받을 자격이 있습니다.
                    수천 명의 만족한 고객들이 이미 경험했습니다.
                </div>
                <div class="price">₩45,000</div>
                <button class="btn" onclick="trackClick('variant-b')">
                    지금 시작하기
                </button>
            </div>
        </div>
    </div>
    
    <script src="https://abi-ops.miraepmp.co.kr/ob/stella/abtest2/js/ab-test-tracker.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', async () => {
            await ABTestTracker.init();
        });
        
        function trackClick(variant) {
            console.log(`Clicked from ${variant}`);
        }
    </script>
</body>
</html>
```

#### 📊 Config 설정

```json
{
    "pages": {
        "/brand-e/skincare.html": {
            "enabled": true,
            "testName": "Brand E - 메시지 톤 테스트",
            "mode": "ab_test",
            "variants": {
                "A": {"name": "기능 강조 (합리적 톤)"},
                "B": {"name": "이점 강조 (감정적 톤)"}
            }
        }
    }
}
```

---

## Config 설정

### Config 파일 구조

```json
{
    "pages": {
        "/{페이지_경로}": {
            "enabled": true,                    // 테스트 활성화 여부
            "testName": "테스트 이름",           // 대시보드에 표시될 이름
            "mode": "ab_test",                  // 모드: ab_test, force_a, force_b, scheduled
            "variants": {
                "A": {
                    "name": "Variant A 설명",
                    "order": []                 // 순서 변경이 필요할 때만 사용
                },
                "B": {
                    "name": "Variant B 설명",
                    "order": []
                }
            },
            "schedule": {                       // scheduled 모드일 때만 사용
                "enabled": false,
                "startDate": null,
                "endDate": null,
                "variant": null
            },
            "lastUpdated": "2025-11-15T...",
            "updatedBy": "admin",
            "createdAt": "2025-11-15T..."
        }
    },
    "global": {
        "cookieExpiry": 30,                     // 쿠키 유효 기간 (일)
        "defaultMode": "ab_test"                // 기본 모드
    }
}
```

### Mode 설명

| Mode | 설명 | 사용 시기 |
|------|------|---------|
| **ab_test** | Variant A/B를 50/50으로 분배 | 일반적인 A/B 테스트 |
| **force_a** | 모든 사용자에게 Variant A만 표시 | 테스트 전 확인/배포 후 A로 고정 |
| **force_b** | 모든 사용자에게 Variant B만 표시 | Variant B 고정 필요 시 |
| **scheduled** | 특정 기간만 Variant 전환 | 시간대별 테스트 필요 시 |

---

## 배포 체크리스트

### 📋 ABtest 페이지 배포 전 확인사항

#### 1️⃣ HTML 마크업 확인

- [ ] `.dtc-dwcr-list` 클래스 2개 있는가?
- [ ] 각각 다른 `data-variant` 속성 ("A", "B")을 가지고 있는가?
- [ ] 초기 CSS에서 `display: none` 또는 `visibility: hidden`으로 설정했는가?
- [ ] `.active` 클래스 추가 시 표시되는 CSS를 작성했는가?

#### 2️⃣ ab-test-tracker.js 로드 확인

- [ ] `<script src="...ab-test-tracker.js"></script>` 추가했는가?
- [ ] `DOMContentLoaded` 이벤트 리스너에서 `ABTestTracker.init()` 호출했는가?

#### 3️⃣ Config 파일 등록

- [ ] 페이지 경로를 config.json에 추가했는가?
- [ ] `testName`을 의미 있게 작성했는가?
- [ ] `mode`를 "ab_test"로 설정했는가? (처음 배포 시)
- [ ] 경로에 escaped slashes가 없는가? (예: `/` 아니라 `\/`)

#### 4️⃣ 데이터 측정 태그

- [ ] CTA 요소에 고유한 `id` 속성이 있는가?
- [ ] 클릭 이벤트 추적이 설정되어 있는가? (선택사항)

#### 5️⃣ 테스트 전 확인

- [ ] 개발 환경에서 양쪽 Variant 모두 표시되는가?
- [ ] `?debug=1` 파라미터로 디버그 정보를 확인했는가?
- [ ] 브라우저 F12 개발자 도구에서 에러가 없는가?

---

### 배포 프로세스

```bash
# 1️⃣ Config 파일 업데이트
vim /var/www/html_bak/ob/stella/abtest2/api/ab-test-config.json

# 2️⃣ 권한 설정
chmod 644 /var/www/html_bak/ob/stella/abtest2/api/ab-test-config.json

# 3️⃣ 페이지 배포
cp your-page.html /var/www/html/brand-a/product.html

# 4️⃣ 테스트 접속
# https://your-domain.com/brand-a/product.html?debug=1

# 5️⃣ 브라우저 캐시 삭제 후 확인
# Ctrl+Shift+Delete (Chrome/Firefox)
```

---

## 자주 묻는 질문 (FAQ)

### Q1: ABtest 라이브러리는 어디에 위치해야 하나요?

**A:** 중앙 집중식 관리를 위해 다음 위치에 배치합니다:
```
https://abi-ops.miraepmp.co.kr/ob/stella/abtest2/js/ab-test-tracker.js
```

모든 페이지에서 동일한 경로로 로드하면, 업데이트 시 모든 페이지에 자동 적용됩니다.

---

### Q2: 여러 브랜드 페이지에서 ABtest를 사용할 수 있나요?

**A:** ✅ 예, 완전히 가능합니다. Config 파일에 각 브랜드별 페이지를 등록하세요:

```json
{
    "pages": {
        "/brand-a/product.html": { ... },
        "/brand-b/index.html": { ... },
        "/brand-c/landing.html": { ... }
    }
}
```

각 페이지는 독립적으로 관리됩니다.

---

### Q3: 사용자별로 같은 Variant를 계속 보게 할 수 있나요?

**A:** ✅ 네, ab-test-tracker.js가 쿠키를 사용하여 자동으로 처리합니다.

- 쿠키 유효 기간: 기본 30일
- 같은 사용자가 30일 내에 재방문 시: 동일 Variant 표시
- Config에서 `cookieExpiry` 값으로 조정 가능

```json
{
    "global": {
        "cookieExpiry": 30  // 원하는 일수로 변경
    }
}
```

---

### Q4: ABtest 결과를 어디서 확인할 수 있나요?

**A:** 통합 관리 대시보드에서 확인합니다:

```
https://abi-ops.miraepmp.co.kr/ob/stella/abtest2/index.html
```

대시보드 기능:
- 📊 실시간 통계 (A/B 클릭 비율)
- 📈 날짜별 필터링
- 📥 CSV 다운로드
- ⚙️ 테스트 설정 변경

---

### Q5: Variant가 표시되지 않으면 어떻게 해야 하나요?

**A:** 다음 순서로 확인하세요:

1. **Config 파일 확인**
   ```bash
   cat /var/www/html_bak/ob/stella/abtest2/api/ab-test-config.json | grep "페이지경로"
   ```

2. **경로 형식 확인**
   - ❌ `/\/brand\/a\/` (escaped slashes)
   - ✅ `/brand/a/` (normal slashes)

3. **F12 개발자 도구 확인**
   - Console 탭에서 에러 메시지 확인
   - `?debug=1` 파라미터 추가해서 디버그 정보 확인

4. **브라우저 캐시 삭제**
   - Ctrl+Shift+Delete

5. **ab-test-tracker.js 로드 확인**
   ```javascript
   // 콘솔에 입력
   console.log(typeof ABTestTracker);  // "object" 여야 함
   ```

---

### Q6: 테스트 중간에 설정을 변경할 수 있나요?

**A:** ✅ 가능하지만 주의가 필요합니다:

- **모드 변경** (ab_test → force_a): 즉시 적용, 기존 쿠키는 유지
- **페이지 이름 변경**: 통계에는 영향 없음, 대시보드 표시만 변경
- **variant 설명 변경**: 마찬가지로 대시보드 표시만 변경

**권장**: 테스트 완료 후 결과를 정리한 뒤 설정 변경

---

### Q7: 동시에 여러 개의 ABtest를 실행할 수 있나요?

**A:** ✅ 예, 여러 페이지에서 동시에 실행 가능합니다.

```json
{
    "pages": {
        "/brand-a/product.html": { "mode": "ab_test" },
        "/brand-b/banner.html": { "mode": "ab_test" },
        "/brand-c/cta.html": { "mode": "ab_test" }
    }
}
```

각 페이지별로 독립적으로 작동하며, 같은 사용자가 여러 페이지를 방문해도 각 페이지에서의 Variant는 독립적입니다.

---

### Q8: 모바일에서도 작동하나요?

**A:** ✅ 완전히 작동합니다.

- 쿠키 기반: 모바일 브라우저에서도 정상 작동
- 반응형 CSS: 모바일 화면 크기에 맞게 조정 필요
- 주의: 앱 환경에서는 쿠키가 제한될 수 있음

---

### Q9: ABtest 데이터를 내보낼 수 있나요?

**A:** ✅ 대시보드에서 CSV 형식으로 내보낼 수 있습니다.

```
대시보드 → 통계 분석 → CSV 다운로드
```

CSV 데이터에 포함:
- 테스트 기간
- Variant A/B 클릭수
- 시간대별 데이터
- 선택한 날짜 범위의 모든 로그

---

### Q10: 테스트를 완료하고 한 Variant로 고정할 때는?

**A:** Config에서 mode를 변경합니다:

```json
{
    "pages": {
        "/brand-a/product.html": {
            "mode": "force_a"  // ← Variant A로 모든 사용자에게 표시
        }
    }
}
```

또는 우승한 Variant의 콘텐츠를 본 페이지로 이동:

```html
<!-- 기존의 .dtc-dwcr-list를 제거하고 우승 콘텐츠만 유지 -->
<div>
    <!-- Variant A 콘텐츠를 여기에 직접 삽입 -->
</div>
```

---

## 📞 추가 지원

### 문제 발생 시

1. **개발자 도구에서 에러 확인**
   - F12 → Console 탭
   - 에러 메시지 스크린샷 저장

2. **Debug 모드 활성화**
   ```
   페이지URL?debug=1
   ```

3. **Config 파일 유효성 검사**
   ```bash
   # JSON 형식 확인
   python -m json.tool ab-test-config.json
   ```

4. **ab-test-tracker.js 로드 확인**
   ```bash
   # 서버에서 파일 존재 확인
   ls -la /var/www/html_bak/ob/stella/abtest2/js/ab-test-tracker.js
   ```

---

## 📝 참고 자료

- **Config 파일 위치**: `/var/www/html_bak/ob/stella/abtest2/api/ab-test-config.json`
- **라이브러리 위치**: `https://abi-ops.miraepmp.co.kr/ob/stella/abtest2/js/ab-test-tracker.js`
- **관리 대시보드**: `https://abi-ops.miraepmp.co.kr/ob/stella/abtest2/index.html`
- **로그 저장소**: `/var/www/html_bak/ob/stella/abtest2/api/ab-test-logs/`

---

**이 가이드는 필요에 따라 계속 업데이트됩니다.**  
마지막 업데이트: 2025-11-15

