# ABtest 솔루션 - 실제 적용 예제 모음 (v1.1)

이 문서는 **클릭 로그가 정상 기록되는** 실제 프로젝트에서 복사해서 바로 사용할 수 있는 예제를 제공합니다.

**v1.1 업데이트**: 모든 예제에서 onclick 핸들러를 제거하고 순수 링크 패턴으로 통일했습니다.

---

## 🚀 빠른 시작 템플릿

### 최소한의 코드로 시작

```html
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>ABtest 페이지</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .dtc-dwcr-list {
            display: none;
        }
        
        .dtc-dwcr-list.active {
            display: block;
        }
        
        .option-link {
            display: inline-block;
            padding: 12px 24px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            margin-top: 15px;
            transition: all 0.3s;
        }
        
        .option-link:hover {
            background: #764ba2;
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <!-- Variant A -->
    <div class="dtc-dwcr-list" data-variant="A">
        <h1>옵션 A</h1>
        <p>이것은 Variant A입니다.</p>
        <!-- ✅ onclick 없는 순수 링크 -->
        <a href="#" id="dtc-dwcr-option-a" class="option-link">선택하기</a>
    </div>
    
    <!-- Variant B -->
    <div class="dtc-dwcr-list" data-variant="B">
        <h1>옵션 B</h1>
        <p>이것은 Variant B입니다.</p>
        <!-- ✅ onclick 없는 순수 링크 -->
        <a href="#" id="dtc-dwcr-option-b" class="option-link">선택하기</a>
    </div>
    
    <!-- 필수: ABtest 라이브러리 로드 -->
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

## 📦 예제 1: 제품 이미지 테스트 ✅

**시나리오**: 제품 이미지 두 가지 중 어느 것이 더 높은 클릭률을 기록하는지 테스트  
**상태**: 클릭 로그 정상 기록됨 ✅

```html
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>프리미엄 스킨케어 - 이미지 테스트</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body { 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; 
            background: #f5f5f5; 
            padding: 20px; 
        }
        
        .container { 
            max-width: 800px; 
            margin: 0 auto; 
            background: white; 
            padding: 40px; 
            border-radius: 12px; 
            box-shadow: 0 2px 8px rgba(0,0,0,0.1); 
        }
        
        h1 { color: #333; margin-bottom: 10px; font-size: 28px; }
        .subtitle { color: #999; margin-bottom: 30px; font-size: 14px; }
        
        .dtc-dwcr-list { display: none; }
        .dtc-dwcr-list.active { display: block; animation: fadeIn 0.3s ease-in; }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        .product-image { 
            width: 100%; 
            max-width: 600px; 
            border-radius: 8px; 
            margin-bottom: 20px; 
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .product-info { margin: 20px 0; }
        
        h2 { color: #333; font-size: 20px; margin-bottom: 10px; }
        
        .price { 
            font-size: 28px; 
            font-weight: bold; 
            color: #1a472a; 
            margin: 15px 0; 
        }
        
        .description { 
            color: #666; 
            line-height: 1.8; 
            margin-bottom: 20px; 
            font-size: 14px;
        }
        
        .buy-button { 
            display: inline-block;
            background: #1a472a; 
            color: white; 
            padding: 15px 30px; 
            border-radius: 6px; 
            text-decoration: none;
            cursor: pointer; 
            font-size: 16px; 
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .buy-button:hover { 
            background: #2d5a3f;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        .buy-button:active {
            transform: translateY(0);
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🛍️ 프리미엄 스킨케어 세트</h1>
        <p class="subtitle">제품 이미지 테스트</p>
        
        <!-- Variant A: 라이프스타일 이미지 1 -->
        <div class="dtc-dwcr-list" data-variant="A">
            <img src="https://images.unsplash.com/photo-1556228578-8c89e6adf883?w=600" 
                 alt="라이프스타일 1" 
                 class="product-image">
            <div class="product-info">
                <h2>프리미엄 스킨케어 세트</h2>
                <div class="price">₩89,900</div>
                <div class="description">
                    자연 유래 성분 100%로 만든 프리미엄 스킨케어. 민감한 피부도 사용 가능합니다.
                    매일 사용하면 피부 톤이 밝아지고 탄력이 살아납니다.
                </div>
                <!-- ✅ onclick 없는 순수 링크 (클릭 로그 기록됨) -->
                <a href="#" id="dtc-dwcr-buy-image-lifestyle1" class="buy-button">
                    지금 구매하기
                </a>
            </div>
        </div>
        
        <!-- Variant B: 제품 정면 이미지 -->
        <div class="dtc-dwcr-list" data-variant="B">
            <img src="https://images.unsplash.com/photo-1556228578-8c89e6adf883?w=600" 
                 alt="제품 정면" 
                 class="product-image">
            <div class="product-info">
                <h2>프리미엄 스킨케어 세트</h2>
                <div class="price">₩89,900</div>
                <div class="description">
                    자연 유래 성분 100%로 만든 프리미엄 스킨케어. 민감한 피부도 사용 가능합니다.
                    매일 사용하면 피부 톤이 밝아지고 탄력이 살아납니다.
                </div>
                <!-- ✅ onclick 없는 순수 링크 (클릭 로그 기록됨) -->
                <a href="#" id="dtc-dwcr-buy-image-product" class="buy-button">
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

**예상 결과**: 버튼 클릭 시 로그에 `dtc-dwcr-buy-image-lifestyle1` 또는 `dtc-dwcr-buy-image-product` 기록됨 ✅

---

## 📦 예제 2: CTA 버튼 텍스트 변경 테스트 ✅

**시나리오**: "구매하기" vs "지금 구매하기" CTA 텍스트 중 어느 것이 더 높은 클릭률을 기록하는지 테스트  
**상태**: 클릭 로그 정상 기록됨 ✅

```html
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CTA 버튼 텍스트 테스트</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body { 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .container {
            background: white;
            padding: 60px 40px;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            max-width: 600px;
            text-align: center;
        }
        
        h1 { color: #333; margin-bottom: 15px; font-size: 28px; }
        .subtitle { color: #999; margin-bottom: 30px; font-size: 14px; }
        
        .dtc-dwcr-list { display: none; }
        
        .product-description {
            color: #666;
            line-height: 1.8;
            margin-bottom: 40px;
            font-size: 15px;
        }
        
        .price {
            font-size: 36px;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 30px;
        }
        
        .buy-button {
            display: inline-block;
            background: #667eea;
            color: white;
            padding: 16px 40px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 16px;
            font-weight: 600;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .buy-button:hover {
            background: #764ba2;
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }
        
        .buy-button:active {
            transform: translateY(-1px);
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🎁 프리미엄 기프트 박스</h1>
        <p class="subtitle">CTA 버튼 텍스트 테스트</p>
        
        <!-- Variant A: "구매하기" -->
        <div class="dtc-dwcr-list" data-variant="A">
            <p class="product-description">
                정성스럽게 선별된 프리미엄 상품을 예쁜 박스에 담아 전달합니다.
                특별한 사람을 위한 최고의 선물입니다.
            </p>
            <div class="price">₩79,900</div>
            <!-- ✅ onclick 없는 순수 링크 (클릭 로그 기록됨) -->
            <a href="#" id="dtc-dwcr-cta-simple" class="buy-button">
                구매하기
            </a>
        </div>
        
        <!-- Variant B: "지금 구매하기" -->
        <div class="dtc-dwcr-list" data-variant="B">
            <p class="product-description">
                정성스럽게 선별된 프리미엄 상품을 예쁜 박스에 담아 전달합니다.
                특별한 사람을 위한 최고의 선물입니다.
            </p>
            <div class="price">₩79,900</div>
            <!-- ✅ onclick 없는 순수 링크 (클릭 로그 기록됨) -->
            <a href="#" id="dtc-dwcr-cta-urgent" class="buy-button">
                지금 구매하기
            </a>
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

**예상 결과**: 버튼 클릭 시 로그에 `dtc-dwcr-cta-simple` 또는 `dtc-dwcr-cta-urgent` 기록됨 ✅

---

## 📦 예제 3: 레이아웃 변경 테스트 ✅

**시나리오**: 제품 정보를 세로 vs 가로 레이아웃으로 표시할 때 클릭률 변화 측정  
**상태**: 클릭 로그 정상 기록됨 ✅

```html
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>레이아웃 테스트</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body { 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #f5f5f5;
            padding: 20px;
        }
        
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        h1 { color: #333; margin-bottom: 10px; }
        .subtitle { color: #999; margin-bottom: 30px; }
        
        .dtc-dwcr-list { display: none; }
        
        /* Variant A: 세로 레이아웃 */
        .layout-vertical {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
        }
        
        /* Variant B: 가로 레이아웃 */
        .layout-horizontal {
            display: flex;
            flex-direction: row;
            gap: 40px;
            align-items: center;
        }
        
        .product-image { 
            width: 100%; 
            max-width: 400px; 
            border-radius: 8px; 
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .product-info { padding: 20px; }
        
        h2 { color: #333; font-size: 22px; margin-bottom: 10px; }
        
        .price { 
            font-size: 24px; 
            font-weight: bold; 
            color: #1a472a; 
            margin: 15px 0; 
        }
        
        .description { 
            color: #666; 
            line-height: 1.8; 
            margin-bottom: 20px; 
        }
        
        .buy-button {
            display: inline-block;
            background: #1a472a;
            color: white;
            padding: 12px 24px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .buy-button:hover {
            background: #2d5a3f;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        @media (max-width: 768px) {
            .layout-horizontal {
                flex-direction: column;
                gap: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🎨 프리미엄 제품</h1>
        <p class="subtitle">레이아웃 테스트</p>
        
        <!-- Variant A: 세로 레이아웃 (이미지 우선) -->
        <div class="dtc-dwcr-list" data-variant="A">
            <div class="layout-vertical">
                <img src="https://images.unsplash.com/photo-1556228578-8c89e6adf883?w=400" 
                     alt="제품" 
                     class="product-image">
                <div class="product-info">
                    <h2>프리미엄 제품명</h2>
                    <div class="price">₩99,900</div>
                    <p class="description">
                        세로 레이아웃으로 이미지를 먼저 보여주는 전통적인 방식.
                        모바일 사용자에게 최적화되어 있습니다.
                    </p>
                    <!-- ✅ onclick 없는 순수 링크 (클릭 로그 기록됨) -->
                    <a href="#" id="dtc-dwcr-layout-vertical" class="buy-button">
                        구매하기
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Variant B: 가로 레이아웃 (동시 표시) -->
        <div class="dtc-dwcr-list" data-variant="B">
            <div class="layout-horizontal">
                <img src="https://images.unsplash.com/photo-1556228578-8c89e6adf883?w=400" 
                     alt="제품" 
                     class="product-image">
                <div class="product-info">
                    <h2>프리미엄 제품명</h2>
                    <div class="price">₩99,900</div>
                    <p class="description">
                        가로 레이아웃으로 이미지와 정보를 동시에 표시하는 현대적 방식.
                        데스크톱 사용자에게 최적화되어 있습니다.
                    </p>
                    <!-- ✅ onclick 없는 순수 링크 (클릭 로그 기록됨) -->
                    <a href="#" id="dtc-dwcr-layout-horizontal" class="buy-button">
                        구매하기
                    </a>
                </div>
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

**예상 결과**: 버튼 클릭 시 로그에 `dtc-dwcr-layout-vertical` 또는 `dtc-dwcr-layout-horizontal` 기록됨 ✅

---

## 📦 예제 4: 배너 위치 변경 테스트 ✅

**시나리오**: 광고 배너 위치 변경에 따른 클릭률 변화 측정  
**상태**: 클릭 로그 정상 기록됨 ✅

```html
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>배너 위치 테스트</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body { 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #f5f5f5;
            padding: 20px;
        }
        
        .content {
            max-width: 800px;
            margin: 0 auto;
        }
        
        .dtc-dwcr-list { display: none; }
        
        .banner {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            margin: 20px 0;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        .banner h3 { margin-bottom: 10px; }
        
        .banner p { margin-bottom: 10px; opacity: 0.95; }
        
        .banner-link {
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
        
        .banner-link:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }
        
        .article {
            background: white;
            padding: 30px;
            border-radius: 8px;
            line-height: 1.8;
            color: #333;
            margin: 20px 0;
        }
        
        .article h2 { margin-bottom: 15px; font-size: 22px; }
        .article p { margin-bottom: 15px; }
    </style>
</head>
<body>
    <div class="content">
        <!-- Variant A: 배너를 상단에 배치 -->
        <div class="dtc-dwcr-list" data-variant="A">
            <div class="banner">
                <h3>🎉 특별 오퍼: 50% 할인!</h3>
                <p>이 주에만 모든 상품에 50% 할인을 제공합니다.</p>
                <!-- ✅ onclick 없는 순수 링크 (클릭 로그 기록됨) -->
                <a href="#" id="dtc-dwcr-banner-top" class="banner-link">지금 쇼핑하기 →</a>
            </div>
            
            <article class="article">
                <h2>제품 리뷰</h2>
                <p>이 제품은 시장에서 가장 인기 있는 제품 중 하나입니다. 사용자들로부터 높은 평가를 받고 있으며, 특히 품질과 내구성 면에서 우수합니다.</p>
                <p>많은 고객들이 이 제품을 추천하고 있으며, 재구매율도 매우 높습니다. 지금 특별 할인 이벤트에 참여하세요!</p>
            </article>
        </div>
        
        <!-- Variant B: 배너를 하단에 배치 -->
        <div class="dtc-dwcr-list" data-variant="B">
            <article class="article">
                <h2>제품 리뷰</h2>
                <p>이 제품은 시장에서 가장 인기 있는 제품 중 하나입니다. 사용자들로부터 높은 평가를 받고 있으며, 특히 품질과 내구성 면에서 우수합니다.</p>
                <p>많은 고객들이 이 제품을 추천하고 있으며, 재구매율도 매우 높습니다. 지금 특별 할인 이벤트에 참여하세요!</p>
            </article>
            
            <div class="banner">
                <h3>🎉 특별 오퍼: 50% 할인!</h3>
                <p>이 주에만 모든 상품에 50% 할인을 제공합니다.</p>
                <!-- ✅ onclick 없는 순수 링크 (클릭 로그 기록됨) -->
                <a href="#" id="dtc-dwcr-banner-bottom" class="banner-link">지금 쇼핑하기 →</a>
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

**예상 결과**: 배너의 링크 클릭 시 로그에 `dtc-dwcr-banner-top` 또는 `dtc-dwcr-banner-bottom` 기록됨 ✅

---

## 📦 예제 5: 가격 포인트 테스트 ✅

**시나리오**: 상품 가격을 다르게 표시했을 때 클릭률 변화 측정  
**상태**: 클릭 로그 정상 기록됨 ✅

```html
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>가격 포인트 테스트</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body { 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .pricing-card {
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            max-width: 500px;
            text-align: center;
        }
        
        h1 { color: #333; margin-bottom: 10px; }
        .subtitle { color: #999; margin-bottom: 30px; }
        
        .dtc-dwcr-list { display: none; }
        
        .product-description { 
            color: #666; 
            margin-bottom: 30px; 
            line-height: 1.8;
        }
        
        .price-display { margin-bottom: 30px; }
        
        .old-price {
            text-decoration: line-through;
            color: #999;
            font-size: 18px;
            margin-right: 10px;
        }
        
        .current-price {
            font-size: 42px;
            font-weight: bold;
            color: #667eea;
        }
        
        .features {
            text-align: left;
            background: #f5f5f5;
            padding: 20px;
            border-radius: 6px;
            margin-bottom: 30px;
        }
        
        .features li {
            list-style: none;
            padding: 8px 0;
            color: #666;
        }
        
        .features li:before {
            content: "✓ ";
            color: #667eea;
            font-weight: bold;
            margin-right: 8px;
        }
        
        .buy-button {
            display: inline-block;
            background: #667eea;
            color: white;
            padding: 15px 40px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 16px;
            font-weight: 600;
            transition: all 0.3s;
            width: 100%;
        }
        
        .buy-button:hover {
            background: #764ba2;
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }
    </style>
</head>
<body>
    <!-- Variant A: 정가 표시 -->
    <div class="dtc-dwcr-list" data-variant="A">
        <div class="pricing-card">
            <h1>💎 프리미엄 패키지</h1>
            <p class="subtitle">가격 테스트 - 정가</p>
            
            <p class="product-description">
                고품질의 프리미엄 패키지입니다. 모든 필수 기능과 프리미엄 기능을 포함하고 있습니다.
            </p>
            
            <div class="price-display">
                <div class="current-price">₩99,900</div>
            </div>
            
            <ul class="features">
                <li>무제한 접근</li>
                <li>24/7 고객 지원</li>
                <li>월간 업데이트</li>
                <li>프리미엄 콘텐츠</li>
            </ul>
            
            <!-- ✅ onclick 없는 순수 링크 (클릭 로그 기록됨) -->
            <a href="#" id="dtc-dwcr-price-regular" class="buy-button">
                지금 구매하기
            </a>
        </div>
    </div>
    
    <!-- Variant B: 할인가 표시 -->
    <div class="dtc-dwcr-list" data-variant="B">
        <div class="pricing-card">
            <h1>💎 프리미엄 패키지</h1>
            <p class="subtitle">가격 테스트 - 할인가</p>
            
            <p class="product-description">
                고품질의 프리미엄 패키지입니다. 모든 필수 기능과 프리미엄 기능을 포함하고 있습니다.
            </p>
            
            <div class="price-display">
                <span class="old-price">₩149,900</span>
                <div class="current-price">₩49,900</div>
            </div>
            
            <ul class="features">
                <li>무제한 접근</li>
                <li>24/7 고객 지원</li>
                <li>월간 업데이트</li>
                <li>프리미엄 콘텐츠</li>
            </ul>
            
            <!-- ✅ onclick 없는 순수 링크 (클릭 로그 기록됨) -->
            <a href="#" id="dtc-dwcr-price-discount" class="buy-button">
                지금 구매하기
            </a>
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

**예상 결과**: 버튼 클릭 시 로그에 `dtc-dwcr-price-regular` 또는 `dtc-dwcr-price-discount` 기록됨 ✅

---

## ⚠️ 주의사항

### ❌ 피해야 할 패턴 (클릭 로그 기록 안 됨)

```html
<!-- ❌ onclick 핸들러 사용 금지 -->
<button onclick="handleClick(event)">클릭</button>

<!-- ❌ event.preventDefault() 사용 금지 -->
<script>
function handleClick(event) {
    event.preventDefault();  // 로그 기록 차단!
}
</script>

<!-- ❌ event.stopPropagation() 사용 금지 -->
<script>
element.addEventListener('click', function(event) {
    event.stopPropagation();  // 로그 기록 차단!
});
</script>
```

### ✅ 권장 패턴 (클릭 로그 정상 기록)

```html
<!-- ✅ onclick 없는 순수 링크 -->
<a href="#" id="dtc-dwcr-buy-button">클릭</a>

<!-- ✅ CSS만으로 스타일링 -->
<style>
    .buy-button {
        text-decoration: none;
        background: #667eea;
        color: white;
        /* ... 더 많은 CSS */
    }
</style>
```

---

## 🔧 Config 설정 (JSON)

각 예제 페이지마다 Config에 다음을 추가하세요:

```json
{
    "pages": {
        "/examples/example1-image-test.html": {
            "enabled": true,
            "testName": "제품 이미지 테스트",
            "mode": "ab_test",
            "variants": {
                "A": {"name": "라이프스타일 이미지"},
                "B": {"name": "제품 정면 이미지"}
            }
        },
        "/examples/example2-cta-text.html": {
            "enabled": true,
            "testName": "CTA 버튼 텍스트 테스트",
            "mode": "ab_test",
            "variants": {
                "A": {"name": "구매하기"},
                "B": {"name": "지금 구매하기"}
            }
        },
        "/examples/example3-layout.html": {
            "enabled": true,
            "testName": "레이아웃 변경 테스트",
            "mode": "ab_test",
            "variants": {
                "A": {"name": "세로 레이아웃"},
                "B": {"name": "가로 레이아웃"}
            }
        }
    }
}
```

---

## 📊 대시보드 모니터링

모든 예제는 **24시간 후** 대시보드에서 통계를 확인할 수 있습니다:

```
대시보드 URL: https://abi-ops.miraepmp.co.kr/ob/stella/abtest2/index.html

확인 항목:
✅ Variant A 클릭 수
✅ Variant B 클릭 수
✅ 클릭 비율 비교
✅ 시간대별 추이
✅ CSV 다운로드
```

---

## ✅ 체크리스트

각 예제 배포 시 확인하세요:

- [ ] HTML 파일에 `.dtc-dwcr-list` 클래스 있음
- [ ] `data-variant="A"`, `data-variant="B"` 속성 있음
- [ ] 모든 클릭 요소에 `id="dtc-dwcr-*"` 속성 있음
- [ ] **onclick 핸들러 없음**
- [ ] **event.preventDefault() 없음**
- [ ] ab-test-tracker.js 로드 있음
- [ ] ABTestTracker.init() 초기화 있음
- [ ] 브라우저에서 정상 동작 확인
- [ ] 콘솔(F12)에서 "[AB Test]" 로그 메시지 확인
- [ ] 클릭 후 로그 파일에 기록됨 확인

---

## 📝 버전 히스토리

| 버전 | 날짜 | 변경 사항 |
|------|------|---------|
| 1.1 | 2025-11-15 | 모든 onclick 핸들러 제거, 순수 링크 패턴으로 통일 (클릭 로그 정상 기록) |
| 1.0 | 2025-11-10 | 초기 버전 |

---

**최종 검토**: 2025-11-15  
**작성자**: ABtest 개발팀  
**상태**: ✅ 배포 준비 완료 (모든 예제 클릭 로그 정상 기록)
