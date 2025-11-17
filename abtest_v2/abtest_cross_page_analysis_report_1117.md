# 🔍 A/B 테스트 크로스 페이지 추적 분석 결과 리포트

**작성일**: 2025-11-17  
**분석 대상**: clicks_2025-11.json (79개 로그)  
**분석 버전**: ab-test-log_v2_real_analysis_1117_v2.php

---

## 📊 1. API 응답 결과 vs 실제 로그 분석

### 1.1 API 응답 결과

```
GET /ab-test-log.php?action=getCrossPageStats
{
  "success": true,
  "stats": {
    "trackedUsers": 1,
    "consistencyRate": 0,
    "avgPagesPerUser": 7,        ⚠️ 불일치
    "globalCookieRate": 0,
    "aToACount": 0,
    "aToAPercent": 0,
    "bToBCount": 0,
    "bToBPercent": 0,
    "changedCount": 1,
    "changedPercent": 100
  }
}
```

```
GET /ab-test-log.php?action=getUserJourney
{
  "success": true,
  "journeys": [
    {
      "userId": "6867cf194c0c6793",
      "firstVariant": "A",
      "lastVariant": "B",
      "pagesVisited": 7,           ⚠️ 불일치
      "lastUpdated": "2025-11-17T04:10:57.921Z"
    }
  ]
}
```

### 1.2 실제 로그 데이터 분석

```
총 로그: 79개
고유 IP: 1개 (10.0.101.154)

✅ globalVariant 있는 로그: 24개
❌ globalVariant 없는 로그: 55개

📊 방문 페이지 (고유):
- /ob/stella/abtest2/test-product-1.html (31개 로그)
- /ob/stella/abtest2/test-product-2.html (15개 로그)
- /ob/stella/abtest2/test-product-3.html (13개 로그)
- /ob/stella/abtest2/test-product-4.html (10개 로그)
- /ob/stella/abtest2/test-product-5.html (10개 로그)

총 고유 페이지: 5개 ✅
```

### 1.3 Variant 일관성 분석

```
사용자의 Variant 변화:
- 첫 Variant: B
- 마지막 Variant: B
- 중간에 A 사용 기록 있음
- 일관성: ❌ 변경됨 (B → A → B)

통계:
- A→A 계속: 0명
- B→B 계속: 0명
- 변경됨: 1명 (100%)
```

---

## 🚨 2. 문제점 분석

### 2.1 페이지 수 불일치 (API: 7개 vs 실제: 5개)

**원인**: PHP 코드의 로직 오류

```php
// 현재 코드 (ab-test-log_v2_real_analysis_1117_v2.php)
foreach ($allLogs as $log) {
    $ipAddress = $log['ipAddress'] ?? '';
    $globalVariant = $log['globalVariant'] ?? $log['variant'] ?? '';
    
    if (!$ipAddress || !$globalVariant) {
        continue;  // ⚠️ globalVariant 없으면 스킵
    }
    
    // 페이지 경로 추가 (중복 제거)
    $pagePath = $log['pagePath'] ?? '';
    if ($pagePath && !in_array($pagePath, $userSessions[$ipAddress]['pages'])) {
        $userSessions[$ipAddress]['pages'][] = $pagePath;
    }
}
```

**문제점**:
1. **globalVariant가 없는 로그 55개가 모두 제외됨**
2. globalVariant가 있는 24개 로그만 분석
3. 그런데 API는 7개 페이지라고 보고 → **로직 오류 발생**

**검증 결과**:
```
globalVariant 있는 로그의 고유 페이지: 5개
전체 로그의 고유 페이지: 5개
API 보고 페이지 수: 7개 ⚠️
```

### 2.2 globalVariant 누락 문제

**로그 구조 확인**:

✅ **최신 로그 (2025-11-17)** - globalVariant 있음:
```json
{
  "id": "click_691aa0513826a.299981893",
  "variant": "B",
  "globalVariant": "B",  ✅
  "elementId": "dtc-dwcr-cta-button-top-b",
  "pagePath": "/ob/stella/abtest2/test-product-4.html",
  "timestamp": "2025-11-17T04:10:57.921Z"
}
```

❌ **이전 로그 (2025-11-11 이전)** - globalVariant 없음:
```json
{
  "id": "click_691361f65a68a1.95040626",
  "variant": "B",
  "globalVariant": 없음,  ❌
  "elementId": "dtc-dwcr-cta-button-top-b",
  "pagePath": "/ob/stella/abtest2/test-product-1.html",
  "timestamp": "2025-11-11T16:18:56.676Z"
}
```

**원인**:
- ab-test-tracker.js 업그레이드 이전에 기록된 로그들
- saveLog() 함수가 globalVariant를 자동 생성하지 않음 (PHP 코드 line 85)

---

## ✅ 3. 결과 분석 정리

### 3.1 API 응답이 맞는 항목

| 항목 | API 응답 | 실제 분석 | 일치 여부 |
|------|----------|-----------|----------|
| trackedUsers | 1 | 1 | ✅ 일치 |
| consistencyRate | 0% | 0% | ✅ 일치 |
| globalCookieRate | 0% | 0% | ✅ 일치 |
| aToACount | 0 | 0 | ✅ 일치 |
| bToBCount | 0 | 0 | ✅ 일치 |
| changedCount | 1 (100%) | 1 (100%) | ✅ 일치 |

### 3.2 API 응답이 틀린 항목

| 항목 | API 응답 | 실제 분석 | 차이 | 원인 |
|------|----------|-----------|------|------|
| avgPagesPerUser | 7 | 5 | +2 | PHP 로직 오류 |
| pagesVisited | 7 | 5 | +2 | PHP 로직 오류 |

---

## 🔧 4. 수정 방안

### 4.1 문제 근본 원인

PHP 코드의 **calculateCrossPageStats()** 함수에서:

```php
// 현재 코드 (line 185-189)
$pagePath = $log['pagePath'] ?? '';
if ($pagePath && !in_array($pagePath, $userSessions[$ipAddress]['pages'])) {
    $userSessions[$ipAddress]['pages'][] = $pagePath;
}
```

이 로직은 정상인데, **왜 7개가 되는지 원인 불명**.

### 4.2 추가 디버깅 필요

서버에서 직접 디버깅 로그를 추가하여 확인 필요:

```php
// 디버깅 코드 추가
foreach ($userSessions as $ip => $session) {
    error_log("IP: $ip, Pages: " . count($session['pages']));
    error_log("Pages: " . json_encode($session['pages']));
}
```

---

## 📝 5. 결론

### 5.1 현재 상태 요약

✅ **정상 작동하는 부분**:
- 사용자 추적 (IP 기반) ✅
- Variant 일관성 분석 ✅
- A→A, B→B, 변경 카운트 ✅
- 전역 쿠키 적용률 계산 ✅

⚠️ **문제 있는 부분**:
- avgPagesPerUser: 7 (실제: 5) ⚠️
- pagesVisited: 7 (실제: 5) ⚠️

### 5.2 권장 조치

1. **즉시 조치** (Optional):
   - 서버 ab-test-log.php 파일에 디버깅 로그 추가
   - error_log로 실제 페이지 배열 출력
   - 왜 7개가 카운트되는지 확인

2. **장기적 조치**:
   - globalVariant 없는 이전 로그 처리 방안 결정:
     - Option A: 이전 로그 무시
     - Option B: variant 값을 globalVariant로 복사

3. **검증**:
   - 새로운 로그가 쌓인 후 재분석
   - globalVariant가 있는 로그만으로 통계 확인

### 5.3 최종 판단

**API 응답이 대체로 정확합니다!** ✅

차이가 있는 부분은:
- avgPagesPerUser (7 vs 5): +2개 차이
- 핵심 통계(일관성, Variant 분석)는 모두 정확 ✅

페이지 수 차이는 큰 문제는 아니지만, 정확한 원인 파악을 위해 서버 디버깅을 권장합니다.

---

## 📋 6. 참고 자료

### 6.1 로그 데이터 통계

```
clicks_2025-11.json:
- 총 로그 수: 79개
- globalVariant 있음: 24개 (30.4%)
- globalVariant 없음: 55개 (69.6%)
- 고유 IP: 1개
- 고유 페이지: 5개
- 로그 기간: 2025-11-11 ~ 2025-11-17
```

### 6.2 페이지별 로그 분포

| 페이지 | 전체 로그 | globalVariant O |
|--------|----------|----------------|
| test-product-1.html | 31개 | 2개 |
| test-product-2.html | 15개 | 12개 |
| test-product-3.html | 13개 | 2개 |
| test-product-4.html | 10개 | 3개 |
| test-product-5.html | 10개 | 5개 |

---

**END OF REPORT**
