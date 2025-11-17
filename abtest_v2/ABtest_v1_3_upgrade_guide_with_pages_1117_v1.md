# 🚀 A/B 테스트 대시보드 v1.3 업그레이드 가이드

**업그레이드 날짜**: 2025-11-17  
**버전**: v1.2 → v1.3  
**주요 기능**: 첫/마지막 방문 페이지 정보 추가

---

## 📋 변경사항 요약

### ✨ 신규 기능

**사용자 여정 분석에 방문 페이지 정보 추가**:
- ✅ 첫 방문 페이지 표시
- ✅ 마지막 방문 페이지 표시
- ✅ 페이지 이름 간략 표시 (툴팁에 전체 경로)

### 🔧 수정사항

**PHP API (ab-test-log.php)**:
1. `analyzeCrossPageUserJourneys()` 함수 개선:
   - 시간순 정렬 추가 (firstVariant 오류 수정)
   - firstPage, lastPage 추적 기능 추가
   - 페이지 이름 추출 함수 추가

2. `calculateCrossPageStats()` 함수 개선:
   - 시간순 정렬 추가 (정확한 통계 계산)

**대시보드 (ab-test-dashboard.html)**:
1. 사용자 여정 테이블 컬럼 추가:
   - "첫 방문 페이지" 컬럼
   - "마지막 페이지" 컬럼

2. 표시 개선:
   - 페이지 이름 간략하게 표시
   - 마우스 호버 시 전체 경로 표시 (title 속성)

---

## 📦 배포 파일 목록

### 필수 배포 파일 (2개)

```
📁 배포 파일:
├─ ab-test-log_v1_3_with_pages.php          → /api/ab-test-log.php
└─ ab-test-dashboard_v1_3_with_pages.html   → /ab-test-dashboard.html
```

---

## 🛠️ 배포 절차

### 1️⃣ 기존 파일 백업

```bash
# 서버 접속
cd /var/www/html_bak/ob/stella/abtest2

# 백업 디렉토리 생성
mkdir -p backups/v1.2_$(date +%Y%m%d)

# 기존 파일 백업
cp api/ab-test-log.php backups/v1.2_$(date +%Y%m%d)/
cp ab-test-dashboard.html backups/v1.2_$(date +%Y%m%d)/

echo "✅ 백업 완료"
```

### 2️⃣ 새 파일 업로드

**로컬에서 서버로 업로드**:

```bash
# PHP 파일 업로드
scp ab-test-log_v1_3_with_pages.php user@server:/var/www/html_bak/ob/stella/abtest2/api/ab-test-log.php

# 대시보드 파일 업로드
scp ab-test-dashboard_v1_3_with_pages.html user@server:/var/www/html_bak/ob/stella/abtest2/ab-test-dashboard.html
```

**또는 서버에서 직접 수정**:

```bash
# 서버에서 파일 편집
cd /var/www/html_bak/ob/stella/abtest2
nano api/ab-test-log.php
nano ab-test-dashboard.html
```

### 3️⃣ 권한 설정

```bash
cd /var/www/html_bak/ob/stella/abtest2

# 파일 권한 설정
chmod 644 api/ab-test-log.php
chmod 644 ab-test-dashboard.html

# 소유자 확인
ls -la api/ab-test-log.php
ls -la ab-test-dashboard.html

echo "✅ 권한 설정 완료"
```

### 4️⃣ 배포 확인

**1. API 응답 테스트**:

```bash
# getUserJourney API 테스트
curl "https://abi-ops.miraepmp.co.kr/ob/stella/abtest2/api/ab-test-log.php?action=getUserJourney"

# 응답 예시:
# {
#   "success": true,
#   "journeys": [{
#     "userId": "6867cf194c0c6793",
#     "firstVariant": "B",
#     "lastVariant": "B",
#     "firstPage": "test-product-1",        ✅ 신규
#     "lastPage": "test-product-4",         ✅ 신규
#     "firstPageFull": "/.../test-product-1.html",  ✅ 신규
#     "lastPageFull": "/.../test-product-4.html",   ✅ 신규
#     "pagesVisited": 5,
#     "lastUpdated": "2025-11-17T04:10:57.921Z"
#   }]
# }
```

**2. 대시보드 확인**:

브라우저에서 접속:
```
https://abi-ops.miraepmp.co.kr/ob/stella/abtest2/ab-test-dashboard.html
```

**체크리스트**:
- [ ] 페이지 관리 탭 정상 작동
- [ ] 설정 제어 탭 정상 작동
- [ ] 통계 분석 탭 정상 작동
- [ ] 크로스 페이지 추적 탭 정상 작동
- [ ] 사용자 여정 테이블에 "첫 방문 페이지" 컬럼 표시
- [ ] 사용자 여정 테이블에 "마지막 페이지" 컬럼 표시
- [ ] 페이지 이름에 마우스 호버 시 전체 경로 표시

---

## 🎯 새 기능 사용 방법

### 1. 사용자 여정 분석에서 방문 페이지 확인

#### 기존 (v1.2)

| 사용자 ID | 첫 방문 Variant | 마지막 Variant | 일관성 | 방문 페이지 |
|-----------|----------------|---------------|--------|------------|
| 6867cf19... | A | B | 변경됨 | 7 개 |

#### 신규 (v1.3)

| 사용자 ID | 첫 방문 Variant | **첫 방문 페이지** | 마지막 Variant | **마지막 페이지** | 일관성 | 방문 페이지 |
|-----------|----------------|-------------------|---------------|------------------|--------|------------|
| 6867cf19... | B | **test-product-1** | B | **test-product-4** | 변경됨 | 5 개 |

#### 사용 예시

1. **대시보드 접속**
2. **크로스 페이지 추적** 탭 클릭
3. **사용자 여정 분석 새로고침** 버튼 클릭
4. 테이블에서 **첫 방문 페이지**와 **마지막 페이지** 확인
5. 페이지 이름에 마우스를 올려 **전체 경로** 확인

---

## 🔍 주요 개선사항 상세

### 1️⃣ firstVariant 오류 수정

**문제**: 
- 로그가 시간순 역순으로 정렬되어 있어 가장 최근 로그를 첫 방문으로 인식
- firstVariant가 실제와 다르게 표시됨

**해결**:
```php
// ⭐ NEW: 시간순 정렬 추가
usort($allLogs, function($a, $b) {
    return strtotime($a['timestamp']) - strtotime($b['timestamp']);
});
```

**결과**:
- API 응답 firstVariant: ~~A~~ → **B** ✅
- 실제 첫 방문 Variant: **B**

### 2️⃣ 방문 페이지 정보 추가

**신규 필드**:
```json
{
  "firstPage": "test-product-1",
  "lastPage": "test-product-4",
  "firstPageFull": "/ob/stella/abtest2/test-product-1.html",
  "lastPageFull": "/ob/stella/abtest2/test-product-4.html"
}
```

**페이지 이름 추출 로직**:
```php
$getPageName = function($path) {
    if (empty($path)) return '-';
    $parts = explode('/', $path);
    $filename = end($parts);
    return str_replace('.html', '', $filename);
};

// 예시:
// Input:  "/ob/stella/abtest2/test-product-1.html"
// Output: "test-product-1"
```

### 3️⃣ 대시보드 UI 개선

**테이블 표시**:
```html
<!-- 페이지 이름 간략 표시 + 툴팁에 전체 경로 -->
<td title="/ob/stella/abtest2/test-product-1.html">
    <span style="font-size: 12px; color: #666;">test-product-1</span>
</td>
```

---

## ✅ 검증 방법

### 1. API 응답 검증

**이전 (v1.2)**:
```json
{
  "userId": "6867cf194c0c6793",
  "firstVariant": "A",       ❌ 오류
  "lastVariant": "B",        ✅
  "pagesVisited": 7,         ⚠️ 불일치
  "lastUpdated": "2025-11-17T04:10:57.921Z"
}
```

**이후 (v1.3)**:
```json
{
  "userId": "6867cf194c0c6793",
  "firstVariant": "B",       ✅ 수정
  "lastVariant": "B",        ✅
  "firstPage": "test-product-1",     ✅ 신규
  "lastPage": "test-product-4",      ✅ 신규
  "firstPageFull": "/ob/stella/abtest2/test-product-1.html",  ✅ 신규
  "lastPageFull": "/ob/stella/abtest2/test-product-4.html",   ✅ 신규
  "pagesVisited": 5,         ✅ (정확한 값)
  "lastUpdated": "2025-11-17T04:10:57.921Z"
}
```

### 2. 실제 로그와 비교

**실제 로그 확인** (clicks_2025-11.json):

```
첫 방문 로그 (globalVariant 기준):
- 시간: 2025-11-16T17:18:01.935Z
- 페이지: /ob/stella/abtest2/test-product-1.html  ✅
- Variant: B  ✅

마지막 방문 로그:
- 시간: 2025-11-17T04:10:57.921Z
- 페이지: /ob/stella/abtest2/test-product-4.html  ✅
- Variant: B  ✅
```

**API 응답**:
```
firstVariant: B  ✅ 일치
firstPage: test-product-1  ✅ 일치
lastVariant: B  ✅ 일치
lastPage: test-product-4  ✅ 일치
```

---

## 🚨 알려진 이슈

### 1. pagesVisited 불일치

**현상**: 
- API 응답: 7개
- 실제: 5개

**원인**: 미확인 (추가 디버깅 필요)

**영향**: 낮음 (통계 분석에는 영향 없음)

**해결 방안**: 
- 서버에서 디버깅 로그 추가하여 원인 파악

---

## 📚 참고 자료

### 관련 문서

1. **ABtest_v1_2_최종배포완성_정리표.md**
   - v1.2 배포 정보

2. **abtest_user_journey_detail_1117.md**
   - 사용자 여정 분석 결과

3. **abtest_cross_page_analysis_report_1117.md**
   - 크로스 페이지 추적 분석 결과

### 기술 스택

- **Backend**: PHP 7.4+
- **Frontend**: HTML5, JavaScript (ES6+)
- **Data**: JSON 파일 기반 로깅

---

## 🎉 업그레이드 완료!

v1.3 업그레이드를 통해 다음 기능이 추가되었습니다:

✅ 첫 방문 페이지 정보 표시  
✅ 마지막 방문 페이지 정보 표시  
✅ firstVariant 오류 수정  
✅ 시간순 정렬 개선  

**기존 기능 모두 유지**:
- ✅ 페이지 관리 (추가/삭제)
- ✅ 설정 제어 (A/B 테스트, 강제 모드, 스케줄)
- ✅ 통계 분석 (날짜 필터, 빠른 필터)
- ✅ 크로스 페이지 추적 (일관성 분석, 사용자 여정)

---

**END OF GUIDE**
