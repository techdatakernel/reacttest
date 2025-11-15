# Abtest 실제 적용 예제 모음

> Multi-page A/B Test 시스템의 다양한 실전 적용 사례와 코드 예제

---

## 📑 목차

1. [기본 적용 예제](#1-기본-적용-예제)
2. [판매처 순서 테스트 (한맥 사례)](#2-판매처-순서-테스트-한맥-사례)
3. [CTA 버튼 스타일 테스트](#3-cta-버튼-스타일-테스트)
4. [배너 레이아웃 테스트](#4-배너-레이아웃-테스트)
5. [제품 정보 순서 테스트](#5-제품-정보-순서-테스트)
6. [모바일 최적화 테스트](#6-모바일-최적화-테스트)
7. [가격 표시 방식 테스트](#7-가격-표시-방식-테스트)
8. [다국어 콘텐츠 테스트](#8-다국어-콘텐츠-테스트)

---

## 1. 기본 적용 예제

### 1.1 최소 구성 예제

**HTML 구조:**
```html
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>기본 AB 테스트</title>
</head>
<body>
    <h1>제품 페이지</h1>

    <!-- Variant A -->
    <div class="dtc-dwcr-list" data-variant="A">
        <a id="dtc-dwcr-buy-a" href="/checkout">구매하기</a>
    </div>

    <!-- Variant B -->
    <div class="dtc-dwcr-list" data-variant="B">
        <a id="dtc-dwcr-buy-b" href="/checkout">지금 구매!</a>
    </div>

    <!-- AB Test 스크립트 -->
    <script src="/path/to/ab-test-tracker.js"></script>
    <script>
        window.addEventListener('DOMContentLoaded', () => {
            ABTestTracker.init();
        });
    </script>
</body>
</html>
```

**대시보드 설정:**
1. 페이지 추가: `/products/basic-example`
2. 테스트명: "기본 구매 버튼 테스트"
3. 모드: 🎲 A/B 테스트 모드
4. 저장

**측정 결과 예시:**
- Variant A: 42 클릭 (48%)
- Variant B: 45 클릭 (52%)
- 승자: Variant B (+7.1% 향상)

---

## 2. 판매처 순서 테스트 (한맥 사례)

### 2.1 실제 운영 중인 예제

**페이지:** `https://hanmac.ob.co.kr/products/hanmac-extracreamydraftcan-handle-package`

**HTML 구조:**
```html
<!-- Variant A: 카카오 선물하기 우선 -->
<ul class="dtc-dwcr-list" data-variant="A">
    <li>
        <a id="dtc-dwcr-kakao-gift"
           href="https://kko.kakao.com/Sn9n9e87U5"
           target="_blank"
           rel="noopener">
            <img src="/resources/images/purchase/logo/kakao-gift-logo.png"
                 alt="카카오 선물하기">
        </a>
    </li>
    <li>
        <a id="dtc-dwcr-cu-pocket"
           href="https://www.pocketcu.co.kr/..."
           target="_blank"
           rel="noopener">
            <img src="/resources/images/purchase/logo/cu-pocket-logo.png"
                 alt="CU 포켓">
        </a>
    </li>
    <li>
        <a id="dtc-dwcr-gs-25"
           href="https://abr.ge/1kg2l3"
           target="_blank"
           rel="noopener">
            <img src="/resources/images/purchase/logo/gs-25-logo.png"
                 alt="GS25">
        </a>
    </li>
    <li>
        <a id="dtc-dwcr-daily-shot"
           href="https://open.dailyshot.co/..."
           target="_blank"
           rel="noopener">
            <img src="/resources/images/purchase/logo/daily-shot-logo.png"
                 alt="데일리샷">
        </a>
    </li>
    <li>
        <a id="dtc-dwcr-emart-24"
           href="https://abr.ge/4rmf25..."
           target="_blank"
           rel="noopener">
            <img src="/resources/images/purchase/logo/emart-24-logo.png"
                 alt="이마트24">
        </a>
    </li>
    <li>
        <a id="dtc-dwcr-seven-eleven"
           href="https://new.7-elevenapp.co.kr/..."
           target="_blank"
           rel="noopener">
            <img src="/resources/images/purchase/logo/seven-eleven-logo.png"
                 alt="세븐일레븐">
        </a>
    </li>
</ul>

<!-- Variant B: CU 포켓 우선 -->
<ul class="dtc-dwcr-list" data-variant="B">
    <li>
        <a id="dtc-dwcr-cu-pocket" href="...">
            <img src="/resources/images/purchase/logo/cu-pocket-logo.png" alt="CU 포켓">
        </a>
    </li>
    <li>
        <a id="dtc-dwcr-gs-25" href="...">
            <img src="/resources/images/purchase/logo/gs-25-logo.png" alt="GS25">
        </a>
    </li>
    <li>
        <a id="dtc-dwcr-kakao-gift" href="...">
            <img src="/resources/images/purchase/logo/kakao-gift-logo.png" alt="카카오 선물하기">
        </a>
    </li>
    <li>
        <a id="dtc-dwcr-emart-24" href="...">
            <img src="/resources/images/purchase/logo/emart-24-logo.png" alt="이마트24">
        </a>
    </li>
    <li>
        <a id="dtc-dwcr-seven-eleven" href="...">
            <img src="/resources/images/purchase/logo/seven-eleven-logo.png" alt="세븐일레븐">
        </a>
    </li>
    <li>
        <a id="dtc-dwcr-daily-shot" href="...">
            <img src="/resources/images/purchase/logo/daily-shot-logo.png" alt="데일리샷">
        </a>
    </li>
</ul>
```

**CSS 스타일:**
```css
.dtc-dwcr-list {
    display: none;  /* 기본값 숨김 */
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
    padding: 20px 0;
}

.dtc-dwcr-list li {
    list-style: none;
}

.dtc-dwcr-list a {
    display: block;
    transition: transform 0.2s;
}

.dtc-dwcr-list a:hover {
    transform: scale(1.05);
}

.dtc-dwcr-list img {
    width: 100%;
    height: auto;
}
```

**대시보드 설정:**
```javascript
// 페이지 경로: /products/hanmac-extracreamydraftcan-handle-package
// 테스트명: 한맥 판매처 순서 최적화
// 모드: A/B 테스트 모드
// Variant A: 카카오 선물하기 우선
// Variant B: CU 포켓 우선
```

**측정 기간:** 2025-11-01 ~ 2025-11-30 (1개월)

**예상 결과:**
- 총 방문자: 10,000명
- Variant A 클릭: 1,200회
- Variant B 클릭: 1,450회
- 승자: Variant B (CU 포켓 우선 순서가 +20.8% 더 효과적)

---

## 3. CTA 버튼 스타일 테스트

### 3.1 색상 대비 테스트

**Variant A - 전통적 녹색:**
```html
<div class="dtc-dwcr-list" data-variant="A">
    <button id="dtc-dwcr-cta-green" class="cta-button green">
        지금 구매하기
    </button>
</div>
```

**CSS:**
```css
.cta-button.green {
    background: #28a745;
    color: white;
    padding: 16px 40px;
    border: none;
    border-radius: 8px;
    font-size: 18px;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.3s;
}

.cta-button.green:hover {
    background: #218838;
}
```

**Variant B - 강렬한 오렌지:**
```html
<div class="dtc-dwcr-list" data-variant="B">
    <button id="dtc-dwcr-cta-orange" class="cta-button orange">
        지금 구매하기
    </button>
</div>
```

**CSS:**
```css
.cta-button.orange {
    background: #ff6b35;
    color: white;
    padding: 16px 40px;
    border: none;
    border-radius: 8px;
    font-size: 18px;
    font-weight: 600;
    cursor: pointer;
    box-shadow: 0 4px 12px rgba(255, 107, 53, 0.3);
    transition: all 0.3s;
}

.cta-button.orange:hover {
    background: #ff5722;
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(255, 107, 53, 0.4);
}
```

**테스트 결과 예시:**
- 페이지: `/products/cta-test`
- Variant A (녹색): 320 클릭 (45%)
- Variant B (오렌지): 390 클릭 (55%)
- 결론: 오렌지 버튼이 +21.9% 더 효과적

---

## 4. 배너 레이아웃 테스트

### 4.1 세로 vs 가로 배치

**Variant A - 세로 레이아웃:**
```html
<div class="dtc-dwcr-list" data-variant="A">
    <div class="banner-vertical">
        <div class="banner-image">
            <img src="/images/product-banner.jpg" alt="제품 이미지">
        </div>
        <div class="banner-content">
            <h2>프리미엄 제품</h2>
            <p>최고의 품질을 경험하세요</p>
            <div class="price">₩49,900</div>
            <a id="dtc-dwcr-banner-cta-vertical" href="/checkout" class="cta-link">
                구매하기
            </a>
        </div>
    </div>
</div>
```

**CSS:**
```css
.banner-vertical {
    display: flex;
    flex-direction: column;
    gap: 30px;
}

.banner-image img {
    width: 100%;
    height: auto;
    border-radius: 12px;
}

.banner-content {
    text-align: center;
}

.banner-content h2 {
    font-size: 32px;
    margin-bottom: 15px;
}

.banner-content .price {
    font-size: 36px;
    font-weight: bold;
    color: #1a472a;
    margin: 20px 0;
}
```

**Variant B - 가로 레이아웃:**
```html
<div class="dtc-dwcr-list" data-variant="B">
    <div class="banner-horizontal">
        <div class="banner-content">
            <h2>프리미엄 제품</h2>
            <p>최고의 품질을 경험하세요</p>
            <div class="price">₩49,900</div>
            <a id="dtc-dwcr-banner-cta-horizontal" href="/checkout" class="cta-link">
                구매하기
            </a>
        </div>
        <div class="banner-image">
            <img src="/images/product-banner.jpg" alt="제품 이미지">
        </div>
    </div>
</div>
```

**CSS:**
```css
.banner-horizontal {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 40px;
    align-items: center;
}

@media (max-width: 768px) {
    .banner-horizontal {
        grid-template-columns: 1fr;
    }
}
```

---

## 5. 제품 정보 순서 테스트

### 5.1 가격 우선 vs 정보 우선

**Variant A - 가격 먼저 표시:**
```html
<div class="dtc-dwcr-list" data-variant="A">
    <div class="product-details">
        <!-- 1. 가격 강조 -->
        <div class="price-box highlight">
            <span class="label">특별가</span>
            <span class="price">₩99,900</span>
            <span class="original">₩149,900</span>
        </div>

        <!-- 2. 구매 혜택 -->
        <div class="benefits">
            <h3>구매 혜택</h3>
            <ul>
                <li>무료 배송</li>
                <li>30일 반품 보장</li>
                <li>1년 품질 보증</li>
            </ul>
        </div>

        <!-- 3. 상세 정보 -->
        <div class="description">
            <h3>제품 상세</h3>
            <p>프리미엄 품질의 최신 제품...</p>
        </div>

        <!-- 4. CTA -->
        <a id="dtc-dwcr-buy-price-first" href="/checkout" class="cta-button">
            구매하기
        </a>
    </div>
</div>
```

**Variant B - 정보 먼저 표시:**
```html
<div class="dtc-dwcr-list" data-variant="B">
    <div class="product-details">
        <!-- 1. 상세 정보 -->
        <div class="description">
            <h3>제품 상세</h3>
            <p>프리미엄 품질의 최신 제품...</p>
        </div>

        <!-- 2. 구매 혜택 -->
        <div class="benefits">
            <h3>구매 혜택</h3>
            <ul>
                <li>무료 배송</li>
                <li>30일 반품 보장</li>
                <li>1년 품질 보증</li>
            </ul>
        </div>

        <!-- 3. 가격 -->
        <div class="price-box">
            <span class="label">특별가</span>
            <span class="price">₩99,900</span>
            <span class="original">₩149,900</span>
        </div>

        <!-- 4. CTA -->
        <a id="dtc-dwcr-buy-info-first" href="/checkout" class="cta-button">
            구매하기
        </a>
    </div>
</div>
```

**테스트 가설:**
- Variant A (가격 우선): 할인에 민감한 고객 타겟
- Variant B (정보 우선): 품질 중시 고객 타겟

---

## 6. 모바일 최적화 테스트

### 6.1 모바일 전용 레이아웃

**Variant A - 스크롤형:**
```html
<div class="dtc-dwcr-list" data-variant="A">
    <div class="mobile-layout scroll">
        <div class="product-image">
            <img src="/images/product.jpg" alt="제품">
        </div>
        <div class="product-info">
            <h2>제품명</h2>
            <div class="price">₩49,900</div>
        </div>
        <div class="sticky-cta">
            <a id="dtc-dwcr-mobile-scroll-cta" href="/checkout">
                구매하기
            </a>
        </div>
    </div>
</div>
```

**CSS:**
```css
.mobile-layout.scroll {
    padding-bottom: 80px; /* sticky CTA 공간 */
}

.sticky-cta {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    padding: 15px;
    background: white;
    box-shadow: 0 -2px 10px rgba(0,0,0,0.1);
    z-index: 1000;
}

.sticky-cta a {
    display: block;
    background: #1a472a;
    color: white;
    padding: 16px;
    text-align: center;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 600;
}
```

**Variant B - 접기/펼치기형:**
```html
<div class="dtc-dwcr-list" data-variant="B">
    <div class="mobile-layout accordion">
        <div class="product-summary">
            <img src="/images/product-thumb.jpg" alt="제품">
            <div class="quick-info">
                <h2>제품명</h2>
                <div class="price">₩49,900</div>
            </div>
        </div>
        <button id="toggle-details" class="toggle-btn">
            상세 정보 보기 ▼
        </button>
        <div class="details-panel" style="display: none;">
            <!-- 상세 정보 -->
        </div>
        <a id="dtc-dwcr-mobile-accordion-cta" href="/checkout" class="cta-button">
            구매하기
        </a>
    </div>
</div>

<script>
document.getElementById('toggle-details').addEventListener('click', function() {
    const panel = document.querySelector('.details-panel');
    if (panel.style.display === 'none') {
        panel.style.display = 'block';
        this.textContent = '상세 정보 닫기 ▲';
    } else {
        panel.style.display = 'none';
        this.textContent = '상세 정보 보기 ▼';
    }
});
</script>
```

---

## 7. 가격 표시 방식 테스트

### 7.1 정가 대비 vs 할인율 강조

**Variant A - 할인 금액 강조:**
```html
<div class="dtc-dwcr-list" data-variant="A">
    <div class="price-display amount">
        <div class="original-price">정가: ₩150,000</div>
        <div class="discount-amount">50,000원 할인!</div>
        <div class="final-price">₩99,900</div>
        <a id="dtc-dwcr-price-amount" href="/checkout">구매하기</a>
    </div>
</div>
```

**Variant B - 할인율 강조:**
```html
<div class="dtc-dwcr-list" data-variant="B">
    <div class="price-display percent">
        <div class="discount-badge">33% OFF</div>
        <div class="original-price">₩150,000</div>
        <div class="final-price">₩99,900</div>
        <a id="dtc-dwcr-price-percent" href="/checkout">구매하기</a>
    </div>
</div>
```

**CSS:**
```css
.discount-badge {
    background: #ff0000;
    color: white;
    padding: 8px 16px;
    border-radius: 20px;
    font-size: 20px;
    font-weight: bold;
    display: inline-block;
    margin-bottom: 10px;
}

.discount-amount {
    background: #fff3cd;
    color: #856404;
    padding: 10px;
    border-radius: 4px;
    font-size: 18px;
    font-weight: bold;
    margin: 10px 0;
}
```

---

## 8. 다국어 콘텐츠 테스트

### 8.1 한국어 vs 영어 메시지

**Variant A - 한국어:**
```html
<div class="dtc-dwcr-list" data-variant="A">
    <div class="cta-section korean">
        <h2>지금 바로 구매하세요!</h2>
        <p>오늘만 특별 할인 진행 중</p>
        <a id="dtc-dwcr-lang-ko" href="/checkout" class="cta-button">
            구매하기
        </a>
    </div>
</div>
```

**Variant B - 영어 + 한국어:**
```html
<div class="dtc-dwcr-list" data-variant="B">
    <div class="cta-section bilingual">
        <h2>Buy Now! 지금 구매!</h2>
        <p>Special Discount Today 오늘만 특별 할인</p>
        <a id="dtc-dwcr-lang-en-ko" href="/checkout" class="cta-button">
            Buy Now 구매하기
        </a>
    </div>
</div>
```

---

## 📊 실전 팁

### 1. 테스트 기간 설정
- 최소 2주 이상 테스트 권장
- 충분한 샘플 사이즈 확보 (최소 1,000회 방문)
- 요일/시간대 편향 방지

### 2. 동시 테스트 피하기
- 한 페이지에서 한 번에 하나의 요소만 테스트
- 예: 버튼 색상 테스트 중에는 레이아웃 고정

### 3. 통계적 유의성 확인
- 5% 이상 차이가 나야 의미 있음
- 승자가 명확하지 않으면 테스트 기간 연장

### 4. 모바일/데스크톱 분리 분석
- 디바이스별로 다른 결과가 나올 수 있음
- Analytics API에서 userAgent 기반 필터링

### 5. 스케줄 모드 활용
```javascript
// 이벤트 기간 동안만 특별 디자인 표시
{
  "mode": "scheduled",
  "schedule": {
    "enabled": true,
    "startDate": "2025-12-01T00:00:00Z",
    "endDate": "2025-12-25T23:59:59Z",
    "variant": "B"  // 크리스마스 특별 디자인
  }
}
```

---

## 🚀 빠른 시작 체크리스트

- [ ] 1. 대시보드에서 페이지 추가
- [ ] 2. HTML에 Variant A/B 코드 작성
- [ ] 3. 추적 스크립트 삽입
- [ ] 4. 테스트 모드 선택 (A/B Test 권장)
- [ ] 5. 설정 저장
- [ ] 6. 브라우저에서 테스트 확인
- [ ] 7. 최소 2주 운영
- [ ] 8. Analytics에서 결과 확인
- [ ] 9. 승자 결정 후 모드 변경 (Force Mode)

---

## 📚 추가 리소스

- [Abtest 표준 적용 가이드](./Abtest_표준_적용_가이드.md)
- [API 레퍼런스](#)
- [트러블슈팅 가이드](#)

---

**작성일:** 2025-11-15
**버전:** 2.0 (Multi-page 지원)
