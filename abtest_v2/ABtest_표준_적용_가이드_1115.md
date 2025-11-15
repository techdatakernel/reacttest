# 🎯 ABtest 솔루션 표준 적용 가이드 (v1.1)

**버전**: 1.1 (클릭 로그 기록 문제 해결)  
**최종 업데이트**: 2025-11-15  
**대상**: 웹 개발자, 마케팅 담당자

---

## 📚 목차

1. [개요](#개요)
2. [파일 구조](#파일-구조)
3. [기본 설정](#기본-설정)
4. [HTML 마크업 표준](#html-마크업-표준)
5. [클릭 로그 추적 (v1.1 NEW)](#클릭-로그-추적-v11-new)
6. [ABtest 유형별 적용](#abtest-유형별-적용)
7. [Config 설정](#config-설정)
8. [배포 체크리스트](#배포-체크리스트)
9. [FAQ](#faq)

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
- ✅ **자동 클릭 로그**: 설정만으로 클릭 이벤트 자동 기록

---

## 파일 구조

### 프로젝트 디렉토리 구조

```
/var/www/html_bak/ob/stella/abtest2/
├── index.html                          # 관리 대시보드
├── api/
│   ├── ab-test-config.php              # 설정 API
│   ├── ab-test-config.json             # 설정 파일
│   ├── ab-test-analytics.php           # 분석 API
│   ├── ab-test-log.php                 # 로그 저장 API
│   └── ab-test-logs/                   # 로그 저장소
├── js/
│   └── ab-test-tracker.js              # ✅ 핵심 라이브러리 (자동 클릭 로그)
│
└── 테스트 페이지들
    ├── test-product-1.html             # ✅ 정상 작동
    ├── test-product-2.html             # ✅ 정상 작동
    ├── test-product-3.html             # ✅ 정상 작동
    └── test-product-4.html             # ✅ 고정됨 (클릭 로그 기록)
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

Config 파일에 페이지를 등록해야 합니다. (대시보드에서 "새 페이지 추가" 또는 API 사용)

---

## HTML 마크업 표준

### 기본 구조

```html
<!-- Variant A: 첫 번째 변형 -->
<div class="dtc-dwcr-list" data-variant="A">
    <!-- Variant A의 콘텐츠 -->
</div>

<!-- Variant B: 두 번째 변형 -->
<div class="dtc-dwcr-list" data-variant="B">
    <!-- Variant B의 콘텐츠 -->
</div>
```

### 필수 CSS

```html
<style>
    .dtc-dwcr-list {
        display: none;  /* ← 중요: 기본 상태에서는 숨김 */
    }
    
    /* JavaScript에서 선택된 variant만 표시됨 */
    /* .dtc-dwcr-list는 JavaScript에서 display: block 또는 display: grid로 변경됨 */
</style>
```

---

## 클릭 로그 추적 (v1.1 NEW) ⭐

### 🎯 핵심 원칙: onclick 핸들러 제거

ab-test-tracker.js는 **글로벌 클릭 리스너**로 자동 추적합니다. onclick 핸들러를 사용하면 **이벤트 전파가 차단되어 로그 기록이 안 됩니다.**

### ❌ 잘못된 패턴 (클릭 로그 안 됨)

```html
<!-- ❌ 문제: onclick 핸들러 사용 -->
<button class="buy-button" 
        id="dtc-dwcr-buy-btn" 
        onclick="handleClick(event)">
    구매하기
</button>

<script>
function handleClick(event) {
    event.preventDefault();  // ← 이벤트 전파 차단! 로그 기록 불가
    alert('구매 버튼이 클릭되었습니다!');
}
</script>

결과:
❌ 버튼 클릭 → onclick 실행 → preventDefault() 호출
❌ 이벤트 전파 중단 → ab-test-tracker.js 감지 못함
❌ 클릭 로그 기록 안 됨
```

### ✅ 올바른 패턴 (클릭 로그 정상 기록)

```html
<!-- ✅ 권장: 순수 링크 사용 (onclick 핸들러 없음) -->
<a href="#" 
   class="buy-button" 
   id="dtc-dwcr-buy-btn">
    구매하기
</a>

<style>
    .buy-button {
        text-decoration: none;  /* 링크 밑줄 제거 */
        display: inline-block;
        padding: 12px 24px;
        background: #1a472a;
        color: white;
        border-radius: 6px;
        cursor: pointer;
        transition: all 0.3s;
    }
    
    .buy-button:hover {
        background: #2d5a3f;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.2);
    }
</style>

결과:
✅ 버튼 클릭 → 링크 기본 동작 (href="#" → 새로고침 없음)
✅ 이벤트 전파 계속됨 → ab-test-tracker.js 감지
✅ 클릭 로그 자동 기록됨
```

### 📋 클릭 추적 ID 규칙

```html
<!-- CTA 버튼: id="dtc-dwcr-{용도}-{variant_구분}" -->
<a href="#" id="dtc-dwcr-buy-button">구매하기</a>

<!-- 링크: id="dtc-dwcr-{용도}" -->
<a href="/promotion" id="dtc-dwcr-promo-link">프로모션 보기</a>

<!-- 이미지 링크: id="dtc-dwcr-{용도}" -->
<a href="/product" id="dtc-dwcr-product-image">
    <img src="/img.jpg" alt="상품">
</a>
```

### 🔧 고급: 사용자 정의 동작이 필요한 경우

**onclick 핸들러가 꼭 필요하다면** (예: 폼 검증, 페이지 내 동작), 다음과 같이 설계하세요:

```html
<!-- ✅ 권장: onclick 있지만 이벤트 전파 유지 -->
<button class="buy-button" 
        id="dtc-dwcr-subscribe" 
        onclick="validateForm(event)">
    구독하기
</button>

<script>
function validateForm(event) {
    // ❌ event.preventDefault() 호출 금지!
    // ❌ event.stopPropagation() 호출 금지!
    
    // ✅ 입력값 검증만 수행
    if (!validateEmail()) {
        alert('이메일을 입력해주세요.');
        return false;  // 폼 제출 방지만 함
    }
    
    // ✅ 추가 로직 수행 (사용자 동작)
    // 예: 분석 이벤트, 상태 업데이트 등
    
    // ✅ 클릭 로그는 자동으로 기록됨 (이벤트 전파)
}
</script>
```

**하지만 대부분의 경우 순수 링크(`<a href="#">`)를 사용하는 것이 권장됩니다.**

---

## ABtest 유형별 적용

### 유형 1️⃣: 이미지 테스트 (클릭 로그 기록)

**상황**: 제품 이미지 두 가지 중 어느 것이 더 높은 클릭률을 기록하는지 테스트

#### ✅ 올바른 HTML (클릭 로그 기록됨)

```html
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>제품 이미지 테스트</title>
    <style>
        .dtc-dwcr-list { display: none; }
        .product-image { width: 100%; max-width: 600px; border-radius: 8px; margin-bottom: 20px; }
        .price { font-size: 28px; font-weight: bold; color: #1a472a; margin: 15px 0; }
        .buy-link { 
            display: inline-block;
            background: #1a472a; 
            color: white; 
            padding: 12px 30px; 
            border-radius: 6px; 
            text-decoration: none;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
        }
        .buy-link:hover { background: #2d5a3f; transform: translateY(-2px); }
    </style>
</head>
<body>
    <div class="container">
        <h1>🛍️ 프리미엄 제품</h1>
        
        <!-- Variant A: 라이프스타일 이미지 -->
        <div class="dtc-dwcr-list" data-variant="A">
            <img src="/images/lifestyle-1.jpg" alt="라이프스타일" class="product-image">
            <div class="product-info">
                <h2>프리미엄 제품명</h2>
                <div class="price">₩89,900</div>
                <p>자연 유래 성분 100%로 만든 프리미엄 제품입니다.</p>
                <!-- ✅ onclick 없는 순수 링크 -->
                <a href="#" id="dtc-dwcr-buy-image-a" class="buy-link">
                    지금 구매하기
                </a>
            </div>
        </div>
        
        <!-- Variant B: 제품 정면 이미지 -->
        <div class="dtc-dwcr-list" data-variant="B">
            <img src="/images/product-front.jpg" alt="제품" class="product-image">
            <div class="product-info">
                <h2>프리미엄 제품명</h2>
                <div class="price">₩89,900</div>
                <p>자연 유래 성분 100%로 만든 프리미엄 제품입니다.</p>
                <!-- ✅ onclick 없는 순수 링크 -->
                <a href="#" id="dtc-dwcr-buy-image-b" class="buy-link">
                    지금 구매하기
                </a>
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
        "/product/image-test.html": {
            "enabled": true,
            "testName": "제품 이미지 테스트",
            "mode": "ab_test",
            "variants": {
                "A": {"name": "라이프스타일 이미지"},
                "B": {"name": "제품 정면 이미지"}
            }
        }
    }
}
```

---

### 유형 2️⃣: 버튼 텍스트 변경 테스트 (클릭 로그 기록)

**상황**: "구매하기" vs "지금 구매하기" CTA 텍스트 중 어느 것이 더 높은 클릭률을 기록하는지 테스트

#### ✅ 올바른 HTML (클릭 로그 기록됨)

```html
<div class="dtc-dwcr-list" data-variant="A">
    <a href="#" id="dtc-dwcr-cta-button" class="buy-button">
        구매하기
    </a>
</div>

<div class="dtc-dwcr-list" data-variant="B">
    <a href="#" id="dtc-dwcr-cta-button" class="buy-button">
        지금 구매하기
    </a>
</div>

<style>
    .buy-button {
        display: inline-block;
        background: #667eea;
        color: white;
        padding: 14px 28px;
        border-radius: 6px;
        text-decoration: none;
        font-weight: 600;
        transition: all 0.3s ease;
    }
    
    .buy-button:hover {
        background: #764ba2;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
    }
    
    .buy-button:active {
        transform: translateY(0);
    }
</style>
```

---

### 유형 3️⃣: 레이아웃 변경 테스트 (클릭 로그 기록)

**상황**: 제품 정보를 세로 vs 가로 레이아웃으로 표시할 때 클릭률 변화 측정

#### ✅ 올바른 HTML (클릭 로그 기록됨)

```html
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>레이아웃 테스트</title>
    <style>
        .dtc-dwcr-list { display: none; }
        
        .layout-vertical {
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        
        .layout-horizontal {
            display: flex;
            flex-direction: row;
            gap: 40px;
            align-items: center;
        }
        
        .product-image { width: 100%; max-width: 400px; border-radius: 8px; }
        
        .buy-link {
            display: inline-block;
            background: #1a472a;
            color: white;
            padding: 12px 24px;
            border-radius: 6px;
            text-decoration: none;
            margin-top: 15px;
            transition: all 0.3s;
        }
        
        .buy-link:hover {
            background: #2d5a3f;
        }
    </style>
</head>
<body>
    <!-- Variant A: 세로 레이아웃 -->
    <div class="dtc-dwcr-list" data-variant="A">
        <div class="layout-vertical">
            <img src="/images/product.jpg" alt="제품" class="product-image">
            <div class="product-info">
                <h2>프리미엄 제품</h2>
                <p>자연 유래 성분 100%</p>
                <div class="price">₩99,900</div>
                <!-- ✅ onclick 없는 순수 링크 -->
                <a href="#" id="dtc-dwcr-layout-buy-a" class="buy-link">
                    구매하기
                </a>
            </div>
        </div>
    </div>
    
    <!-- Variant B: 가로 레이아웃 -->
    <div class="dtc-dwcr-list" data-variant="B">
        <div class="layout-horizontal">
            <img src="/images/product.jpg" alt="제품" class="product-image">
            <div class="product-info">
                <h2>프리미엄 제품</h2>
                <p>자연 유래 성분 100%</p>
                <div class="price">₩99,900</div>
                <!-- ✅ onclick 없는 순수 링크 -->
                <a href="#" id="dtc-dwcr-layout-buy-b" class="buy-link">
                    구매하기
                </a>
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

---

## Config 설정

### API를 통한 페이지 추가

```bash
curl -X POST https://abi-ops.miraepmp.co.kr/ob/stella/abtest2/api/ab-test-config.php \
  -H "Content-Type: application/json" \
  -d '{
    "action": "addPage",
    "pagePath": "/product/test-page.html",
    "testName": "제목 텍스트 테스트"
  }'
```

### Config 파일 직접 수정 (JSON)

```json
{
    "pages": {
        "/product/test-page.html": {
            "enabled": true,
            "testName": "제목 텍스트 테스트",
            "mode": "ab_test",
            "variants": {
                "A": {"name": "변형 A", "order": []},
                "B": {"name": "변형 B", "order": []}
            },
            "schedule": {
                "enabled": false,
                "startDate": null,
                "endDate": null,
                "variant": null
            },
            "lastUpdated": "2025-11-15T10:00:00+00:00",
            "updatedBy": "admin",
            "createdAt": "2025-11-15T10:00:00+00:00"
        }
    },
    "global": {
        "cookieExpiry": 30,
        "defaultMode": "ab_test"
    }
}
```

### 모드 설정

| 모드 | 설명 | 사용 시나리오 |
|------|------|------------|
| `ab_test` | 50/50 자동 분배 | 일반적인 A/B 테스트 |
| `force_a` | 모든 사용자에게 Variant A 강제 | 테스트 검증 |
| `force_b` | 모든 사용자에게 Variant B 강제 | 테스트 검증 |
| `scheduled` | 일정 기간만 특정 Variant 표시 | 시간 제한 테스트 |

---

## 배포 체크리스트

### 개발 단계
- [ ] HTML 파일에서 `.dtc-dwcr-list` 클래스 사용 확인
- [ ] Variant A/B `data-variant` 속성 확인
- [ ] 모든 클릭 요소에 `id="dtc-dwcr-*"` 추가
- [ ] onclick 핸들러 제거 확인 (event.preventDefault() 없는지 확인)
- [ ] ab-test-tracker.js 로드 스크립트 확인
- [ ] 초기화 스크립트 (`ABTestTracker.init()`) 확인

### 테스트 단계
- [ ] 로컬에서 페이지 로드 및 Variant 표시 확인
- [ ] 개발자 도구(F12) 콘솔에서 "[AB Test]" 로그 메시지 확인
- [ ] 버튼/링크 클릭 시 콘솔에 클릭 로그 메시지 보이는지 확인
- [ ] Variant A/B 모두 테스트 (여러 번 새로고침)

### 배포 단계
- [ ] Config 파일에 페이지 경로 등록 확인
- [ ] 권한 설정 (644)
- [ ] 브라우저 캐시 삭제 후 확인

### 검증 단계
- [ ] 대시보드에 페이지 목록에 표시되는지 확인
- [ ] 클릭 후 로그 파일에 기록되는지 확인
- [ ] 24시간 후 대시보드 통계에 클릭 수 표시되는지 확인

---

## 자주 묻는 질문 (FAQ)

### Q1: onclick 핸들러를 사용해야 하는 경우는?

**A**: 사용자 정의 동작이 필요한 경우 onclick을 사용할 수 있지만, **event.preventDefault()나 event.stopPropagation()을 호출하면 클릭 로그가 기록되지 않습니다.**

올바른 방식:
```javascript
// ✅ 좋음: 로직만 수행하고 이벤트 전파 유지
function handleClick(event) {
    console.log('사용자 정의 로직');
    // 클릭 로그는 자동으로 기록됨
}

// ❌ 나쁨: 이벤트 전파 차단 (로그 미기록)
function handleClick(event) {
    event.preventDefault();
    event.stopPropagation();
    console.log('사용자 정의 로직');
    // 클릭 로그 기록 안 됨!
}
```

### Q2: 클릭 로그가 기록되지 않을 때는?

**A**: 다음을 확인하세요:

1. **콘솔 확인** (F12):
   - "[AB Test] 클릭:" 메시지 보이나요?
   - 에러 메시지는 없나요?

2. **HTML 확인**:
   - `id="dtc-dwcr-*"` 속성 있나요?
   - onclick 핸들러에서 preventDefault() 호출하고 있나요?

3. **API 확인** (F12 Network 탭):
   - ab-test-log.php 요청 보이나요?
   - 200 상태 코드로 응답했나요?

### Q3: 여러 클릭 요소를 추적하려면?

**A**: 각 요소에 고유한 id를 부여하세요:

```html
<a href="#" id="dtc-dwcr-buy-button" class="btn">구매</a>
<a href="#" id="dtc-dwcr-more-info" class="btn">더보기</a>
<a href="#" id="dtc-dwcr-share" class="btn">공유</a>
```

로그에 기록되는 정보:
```json
{
    "elementId": "dtc-dwcr-buy-button",
    "variant": "A",
    "timestamp": "2025-11-15T10:30:45Z",
    "pagePath": "/product/page.html"
}
```

### Q4: 캐시 문제가 있을 때는?

**A**: 브라우저 캐시 삭제 후 다시 시도:
```
F12 → 점 3개 → Settings → "Disable cache" 체크 → 페이지 새로고침
또는
Ctrl+Shift+Delete → "모든 시간" → "캐시된 이미지 및 파일" 체크 → 삭제
```

### Q5: test-product-4의 로그 기록 문제와 같은 상황에서 해결책은?

**A**: test-product-4는 기존에 onclick 핸들러가 있었기 때문에 로그가 기록되지 않았습니다. 해결책:

```html
<!-- ❌ 이전 (로그 안 됨) -->
<button onclick="handleClick(event)">구매</button>
<script>
function handleClick(event) {
    event.preventDefault();  // 로그 기록 차단!
}
</script>

<!-- ✅ 수정 (로그 정상) -->
<a href="#" id="dtc-dwcr-buy-btn">구매</a>
<!-- onclick 핸들러 완전 제거 -->
```

모든 CSS 스타일은 유지하되 onclick 핸들러만 제거하면 됩니다.

---

## 📊 클릭 로그 기록 데이터 구조

### 로그 파일 위치
```
/var/www/html_bak/ob/stella/abtest2/api/ab-test-logs/clicks_2025-11.json
```

### 기록되는 정보
```json
{
    "elementId": "dtc-dwcr-buy-button",
    "variant": "A",
    "href": "#",
    "pagePath": "/product/test-page.html",
    "timestamp": "2025-11-15T10:30:45.123Z",
    "userAgent": "Mozilla/5.0...",
    "referrer": "https://example.com"
}
```

### 대시보드 통계
- Variant별 클릭 수
- 시간대별 추이
- 사용자 경로 분석
- CSV 다운로드 지원

---

## 버전 히스토리

| 버전 | 날짜 | 변경 사항 |
|------|------|---------|
| 1.1 | 2025-11-15 | 클릭 로그 기록 문제 해결 (onclick 핸들러 제거 표준화) |
| 1.0 | 2025-11-10 | 초기 버전 |

---

**최종 검토**: 2025-11-15  
**작성자**: ABtest 개발팀  
**상태**: ✅ 배포 준비 완료
