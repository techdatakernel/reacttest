# ABtest 솔루션 - 실제 적용 예제 모음

이 문서는 실제 프로젝트에서 복사해서 바로 사용할 수 있는 예제를 제공합니다.

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
        .dtc-dwcr-list {
            display: none;
        }
        .dtc-dwcr-list.active {
            display: block;
        }
    </style>
</head>
<body>
    <!-- Variant A -->
    <div class="dtc-dwcr-list" data-variant="A">
        <h1>옵션 A</h1>
        <p>이것은 Variant A입니다.</p>
    </div>
    
    <!-- Variant B -->
    <div class="dtc-dwcr-list" data-variant="B">
        <h1>옵션 B</h1>
        <p>이것은 Variant B입니다.</p>
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

## 📦 예제 1: 제품 이미지 테스트

**시나리오**: 제품 이미지 두 가지 중 어느 것이 더 높은 클릭률을 기록하는지 테스트

```html
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>프리미엄 제품 - 이미지 테스트</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f5f5f5; padding: 20px; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 40px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        h1 { color: #333; margin-bottom: 10px; }
        .subtitle { color: #999; margin-bottom: 30px; }
        
        .dtc-dwcr-list { display: none; }
        .dtc-dwcr-list.active { display: block; }
        
        .product-image { width: 100%; max-width: 600px; border-radius: 8px; margin-bottom: 20px; }
        .product-info { margin: 20px 0; }
        .price { font-size: 28px; font-weight: bold; color: #1a472a; margin: 15px 0; }
        .description { color: #666; line-height: 1.8; margin-bottom: 20px; }
        .buy-button { background: #1a472a; color: white; padding: 15px 30px; border: none; border-radius: 6px; cursor: pointer; font-size: 16px; font-weight: 600; }
        .buy-button:hover { background: #2d5a3f; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🛍️ 프리미엄 스킨케어 세트</h1>
        <p class="subtitle">제품 이미지 테스트</p>
        
        <!-- Variant A: 라이프스타일 이미지 1 -->
        <div class="dtc-dwcr-list" data-variant="A">
            <img src="/images/skincare-lifestyle-1.jpg" alt="라이프스타일 1" class="product-image">
            <div class="product-info">
                <h2>프리미엄 스킨케어 세트</h2>
                <div class="price">₩89,900</div>
                <div class="description">자연 유래 성분 100%로 만든 프리미엄 스킨케어. 민감한 피부도 사용 가능합니다.</div>
                <button class="buy-button" onclick="trackClick('variant-a')">지금 구매하기</button>
            </div>
        </div>
        
        <!-- Variant B: 라이프스타일 이미지 2 -->
        <div class="dtc-dwcr-list" data-variant="B">
            <img src="/images/skincare-lifestyle-2.jpg" alt="라이프스타일 2" class="product-image">
            <div class="product-info">
                <h2>프리미엄 스킨케어 세트</h2>
                <div class="price">₩89,900</div>
                <div class="description">자연 유래 성분 100%로 만든 프리미엄 스킨케어. 민감한 피부도 사용 가능합니다.</div>
                <button class="buy-button" onclick="trackClick('variant-b')">지금 구매하기</button>
            </div>
        </div>
    </div>
    
    <script src="https://abi-ops.miraepmp.co.kr/ob/stella/abtest2/js/ab-test-tracker.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', async () => {
            await ABTestTracker.init();
        });
        
        function trackClick(variant) {
            console.log(`Purchase button clicked from ${variant}`);
            // 추가 분석 코드가 여기에 들어갑니다
        }
    </script>
</body>
</html>
```

**Config 등록:**
```json
{
    "pages": {
        "/skincare-product.html": {
            "enabled": true,
            "testName": "스킨케어 - 제품 이미지 테스트",
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

## 🎨 예제 2: CTA 버튼 색상 테스트

**시나리오**: 버튼 색상과 텍스트가 클릭률에 미치는 영향

```html
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CTA 버튼 색상 테스트</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f5f5f5; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background: white; padding: 60px 40px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); text-align: center; }
        h1 { font-size: 32px; margin-bottom: 20px; color: #333; }
        .subtitle { font-size: 18px; color: #999; margin-bottom: 40px; }
        
        .dtc-dwcr-list { display: none; }
        .dtc-dwcr-list.active { display: block; }
        
        .cta-button { padding: 20px 40px; font-size: 18px; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; transition: all 0.3s; margin-top: 20px; }
        .btn-green { background: #28a745; color: white; }
        .btn-green:hover { background: #218838; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3); }
        .btn-red { background: #dc3545; color: white; }
        .btn-red:hover { background: #c82333; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(220, 53, 69, 0.3); }
    </style>
</head>
<body>
    <div class="container">
        <h1>🎉 특별 한정 세일!</h1>
        <p class="subtitle">이 주에만 모든 상품 50% 할인</p>
        
        <!-- Variant A: 초록 버튼 -->
        <div class="dtc-dwcr-list" data-variant="A">
            <button class="cta-button btn-green" onclick="trackConversion('green')">
                지금 쇼핑하기
            </button>
        </div>
        
        <!-- Variant B: 빨간 버튼 -->
        <div class="dtc-dwcr-list" data-variant="B">
            <button class="cta-button btn-red" onclick="trackConversion('red')">
                🚀 지금 바로 구매! 🚀
            </button>
        </div>
    </div>
    
    <script src="https://abi-ops.miraepmp.co.kr/ob/stella/abtest2/js/ab-test-tracker.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', async () => {
            await ABTestTracker.init();
        });
        
        function trackConversion(color) {
            console.log(`CTA clicked: ${color} button`);
        }
    </script>
</body>
</html>
```

**Config 등록:**
```json
{
    "pages": {
        "/sale-landing.html": {
            "enabled": true,
            "testName": "세일 페이지 - CTA 버튼 색상 테스트",
            "mode": "ab_test",
            "variants": {
                "A": {"name": "초록색 버튼 (신뢰성)"},
                "B": {"name": "빨간색 버튼 (긴급성)"}
            }
        }
    }
}
```

---

## 📐 예제 3: 레이아웃 테스트

**시나리오**: 제품 정보의 레이아웃 변경 (세로 vs 가로)

```html
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>레이아웃 테스트</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f5f5f5; padding: 20px; }
        .container { max-width: 1000px; margin: 0 auto; background: white; padding: 40px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        h1 { margin-bottom: 30px; }
        
        .dtc-dwcr-list { display: none; }
        .dtc-dwcr-list.active { display: grid; }
        
        /* 세로 레이아웃 */
        .layout-vertical {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        
        /* 가로 레이아웃 */
        .layout-horizontal {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            align-items: center;
        }
        
        .product-image { width: 100%; border-radius: 8px; }
        .product-info { padding: 20px; }
        .product-title { font-size: 24px; font-weight: bold; margin-bottom: 10px; }
        .product-desc { color: #666; line-height: 1.8; margin-bottom: 20px; }
        .price { font-size: 28px; font-weight: bold; color: #1a472a; margin: 15px 0; }
        .buy-btn { background: #1a472a; color: white; padding: 12px 24px; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; }
        
        @media (max-width: 768px) {
            .layout-horizontal {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>프리미엄 제품 - 레이아웃 테스트</h1>
        
        <!-- Variant A: 세로 레이아웃 -->
        <div class="dtc-dwcr-list" data-variant="A">
            <div class="layout-vertical">
                <img src="/product.jpg" alt="제품" class="product-image">
                <div class="product-info">
                    <div class="product-title">프리미엄 제품</div>
                    <div class="product-desc">세로 레이아웃으로 이미지를 먼저 보여주고, 그 다음 정보를 제공하는 전통적인 방식</div>
                    <div class="price">₩99,900</div>
                    <button class="buy-btn" onclick="buy('layout-a')">구매하기</button>
                </div>
            </div>
        </div>
        
        <!-- Variant B: 가로 레이아웃 -->
        <div class="dtc-dwcr-list" data-variant="B">
            <div class="layout-horizontal">
                <img src="/product.jpg" alt="제품" class="product-image">
                <div class="product-info">
                    <div class="product-title">프리미엄 제품</div>
                    <div class="product-desc">가로 레이아웃으로 이미지와 정보를 동시에 표시하여 빠른 정보 파악이 가능한 현대적 방식</div>
                    <div class="price">₩99,900</div>
                    <button class="buy-btn" onclick="buy('layout-b')">구매하기</button>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://abi-ops.miraepmp.co.kr/ob/stella/abtest2/js/ab-test-tracker.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', async () => {
            await ABTestTracker.init();
        });
        
        function buy(layout) {
            console.log(`Purchased from ${layout}`);
        }
    </script>
</body>
</html>
```

**Config 등록:**
```json
{
    "pages": {
        "/product-layout-test.html": {
            "enabled": true,
            "testName": "제품 페이지 - 레이아웃 테스트",
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

## 📝 예제 4: 텍스트 메시지 테스트

**시나리오**: 제품 설명 톤 변경 (합리적 vs 감정적)

```html
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>메시지 톤 테스트</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f5f5f5; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background: white; padding: 40px; border-radius: 12px; }
        
        .dtc-dwcr-list { display: none; }
        .dtc-dwcr-list.active { display: block; }
        
        .card { border: 1px solid #ddd; border-radius: 8px; padding: 30px; }
        .card-title { font-size: 22px; font-weight: bold; margin-bottom: 15px; color: #333; }
        .card-message { font-size: 16px; line-height: 1.8; margin-bottom: 20px; color: #666; }
        .card-price { font-size: 28px; font-weight: bold; color: #1a472a; margin: 20px 0; }
        .card-button { width: 100%; padding: 12px; background: #1a472a; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; }
    </style>
</head>
<body>
    <div class="container">
        <!-- Variant A: 합리적 톤 (기능 강조) -->
        <div class="dtc-dwcr-list" data-variant="A">
            <div class="card">
                <div class="card-title">프리미엄 스킨케어</div>
                <div class="card-message">
                    과학적으로 입증된 성분으로 피부 개선을 돕습니다.
                    <br><br>
                    순수한 천연 성분 90% 이상 함유
                </div>
                <div class="card-price">₩45,000</div>
                <button class="card-button" onclick="select('rational')">상세 보기</button>
            </div>
        </div>
        
        <!-- Variant B: 감정적 톤 (이점 강조) -->
        <div class="dtc-dwcr-list" data-variant="B">
            <div class="card">
                <div class="card-title">프리미엄 스킨케어</div>
                <div class="card-message">
                    당신의 피부는 최고급 관리를 받을 자격이 있습니다.
                    <br><br>
                    이미 수천 명의 만족한 고객들이 경험했습니다.
                </div>
                <div class="card-price">₩45,000</div>
                <button class="card-button" onclick="select('emotional')">지금 시작하기</button>
            </div>
        </div>
    </div>
    
    <script src="https://abi-ops.miraepmp.co.kr/ob/stella/abtest2/js/ab-test-tracker.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', async () => {
            await ABTestTracker.init();
        });
        
        function select(tone) {
            console.log(`Selected ${tone} message`);
        }
    </script>
</body>
</html>
```

**Config 등록:**
```json
{
    "pages": {
        "/skincare-message-test.html": {
            "enabled": true,
            "testName": "스킨케어 - 메시지 톤 테스트",
            "mode": "ab_test",
            "variants": {
                "A": {"name": "합리적 톤 (기능 강조)"},
                "B": {"name": "감정적 톤 (이점 강조)"}
            }
        }
    }
}
```

---

## 🎯 예제 5: 배너 위치 테스트

**시나리오**: 광고 배너의 위치 변경 (상단 vs 하단)

```html
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>배너 위치 테스트</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f5f5f5; }
        .content { max-width: 800px; margin: 0 auto; padding: 40px 20px; background: white; }
        
        .dtc-dwcr-list { display: none; }
        .dtc-dwcr-list.active { display: block; }
        
        .banner {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 8px;
            text-align: center;
            margin: 20px 0;
        }
        .banner h3 { font-size: 24px; margin-bottom: 10px; }
        .banner p { margin-bottom: 15px; }
        .banner a {
            display: inline-block;
            background: white;
            color: #667eea;
            padding: 12px 24px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
        }
        
        .article {
            line-height: 1.8;
            color: #333;
            margin: 20px 0;
        }
        .article h2 { margin-bottom: 15px; }
        .article p { margin-bottom: 10px; }
    </style>
</head>
<body>
    <div class="content">
        <!-- Variant A: 배너 상단 -->
        <div class="dtc-dwcr-list" data-variant="A">
            <div class="banner">
                <h3>🎉 특별 오퍼: 50% 할인!</h3>
                <p>이 주에만 모든 상품에 50% 할인을 제공합니다.</p>
                <a href="/promotion">지금 쇼핑하기 →</a>
            </div>
            
            <article class="article">
                <h2>제품 리뷰</h2>
                <p>이 제품은 시장에서 가장 인기 있는 제품 중 하나입니다...</p>
                <p>많은 사용자들이 높은 만족도를 표현했습니다...</p>
            </article>
        </div>
        
        <!-- Variant B: 배너 하단 -->
        <div class="dtc-dwcr-list" data-variant="B">
            <article class="article">
                <h2>제품 리뷰</h2>
                <p>이 제품은 시장에서 가장 인기 있는 제품 중 하나입니다...</p>
                <p>많은 사용자들이 높은 만족도를 표현했습니다...</p>
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

**Config 등록:**
```json
{
    "pages": {
        "/article-banner-test.html": {
            "enabled": true,
            "testName": "아티클 페이지 - 배너 위치 테스트",
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

## 🔄 Config 파일 전체 예제

여러 브랜드, 여러 페이지에서 동시에 ABtest를 실행하는 경우:

```json
{
    "pages": {
        "/brand-a/product.html": {
            "enabled": true,
            "testName": "Brand A - 제품 이미지 테스트",
            "mode": "ab_test",
            "variants": {
                "A": {"name": "라이프스타일 1"},
                "B": {"name": "라이프스타일 2"}
            },
            "lastUpdated": "2025-11-15T10:00:00+00:00",
            "updatedBy": "admin"
        },
        "/brand-b/cta-button.html": {
            "enabled": true,
            "testName": "Brand B - CTA 버튼 색상 테스트",
            "mode": "ab_test",
            "variants": {
                "A": {"name": "초록색 버튼"},
                "B": {"name": "빨간색 버튼"}
            },
            "lastUpdated": "2025-11-15T10:00:00+00:00",
            "updatedBy": "admin"
        },
        "/brand-c/layout.html": {
            "enabled": true,
            "testName": "Brand C - 레이아웃 테스트",
            "mode": "ab_test",
            "variants": {
                "A": {"name": "세로 레이아웃"},
                "B": {"name": "가로 레이아웃"}
            },
            "lastUpdated": "2025-11-15T10:00:00+00:00",
            "updatedBy": "admin"
        },
        "/brand-d/message.html": {
            "enabled": true,
            "testName": "Brand D - 메시지 톤 테스트",
            "mode": "ab_test",
            "variants": {
                "A": {"name": "합리적 톤"},
                "B": {"name": "감정적 톤"}
            },
            "lastUpdated": "2025-11-15T10:00:00+00:00",
            "updatedBy": "admin"
        }
    },
    "global": {
        "cookieExpiry": 30,
        "defaultMode": "ab_test"
    }
}
```

---

## ✅ 체크리스트 - 페이지 배포 전

각 페이지마다 확인:

- [ ] `.dtc-dwcr-list` 클래스 2개 있음?
- [ ] `data-variant="A"`, `data-variant="B"` 설정?
- [ ] 초기 `display: none` 또는 `visibility: hidden` 설정?
- [ ] `.active` 클래스 추가 시 보이는 CSS?
- [ ] `<script src="...ab-test-tracker.js">` 로드?
- [ ] `DOMContentLoaded` 이벤트에서 `ABTestTracker.init()` 호출?
- [ ] Config 파일에 페이지 경로 추가?
- [ ] 경로에 escaped slashes 없음? (`/` ✅, `\/` ❌)
- [ ] `?debug=1` 파라미터로 테스트?

---

## 🚀 배포 명령어

```bash
# 1. Config 파일 업데이트
nano /var/www/html_bak/ob/stella/abtest2/api/ab-test-config.json

# 2. 형식 검증
python -m json.tool /var/www/html_bak/ob/stella/abtest2/api/ab-test-config.json

# 3. 권한 설정
chmod 644 /var/www/html_bak/ob/stella/abtest2/api/ab-test-config.json

# 4. 서버 재시작 (필요시)
sudo systemctl restart apache2

# 5. 테스트 접속
# https://your-domain.com/page.html?debug=1
```

---

이 예제들을 복사해서 필요에 맞게 수정하여 사용하면 됩니다!

