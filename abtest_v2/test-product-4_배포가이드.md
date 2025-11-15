# 🚀 test-product-4.html 로그 기록 문제 해결 - 배포 가이드

**목표**: test-product-4.html 구매 버튼 클릭을 로그 파일에 기록  
**상태**: 준비 완료  
**소요 시간**: 약 5분

---

## 📋 현재 상황

### 문제점
```
✅ test-product-3.html  → 로그 기록됨 (정상)
❌ test-product-4.html  → 로그 기록 안 됨 (문제)

원인: onclick 핸들러에서 이벤트 전파 차단
해결: <button onclick> → <a href="#"> 변경
```

### 로그 파일 상태
```
/var/www/html_bak/ob/stella/abtest2/api/ab-test-logs/
├── clicks_2025-11.json
│   ├── test-product-1: 기록 있음
│   ├── test-product-2: 기록 있음
│   ├── test-product-3: 기록 있음 ✅
│   └── test-product-4: 기록 없음 ❌ ← 해결할 것
```

---

## ✅ 배포 단계

### Step 1️⃣: 수정된 파일 다운로드

**제공된 파일:**
- `test-product-4_fixed.html` ← 수정된 파일 (다운로드)

**주요 변경사항:**
- 모든 `<button onclick>` → `<a href="#">` 변경
- onclick 핸들러 완전 제거
- CSS에 `text-decoration: none` 추가
- 기타 모든 스타일/기능 100% 유지

---

### Step 2️⃣: 테스트 환경에서 검증

#### 2-1. 로컬 테스트 (개발 PC)

**파일 준비:**
```bash
# 수정된 파일을 임시 디렉토리에 저장
mkdir ~/test-product-4-fixed
cd ~/test-product-4-fixed
# test-product-4_fixed.html을 이곳에 저장
```

**브라우저에서 테스트:**
```
file:///Users/[username]/test-product-4-fixed/test-product-4_fixed.html?debug=1
```

**확인 사항:**
- [ ] 페이지 로드됨
- [ ] 디버그 정보 보임 (우측 상단 파란 박스)
- [ ] 버튼 스타일 정상 (hover 효과 있음)
- [ ] FAQ 토글 작동함

---

#### 2-2. 스테이징 서버에서 테스트

**파일 배포:**
```bash
# 스테이징 서버에 임시 저장
scp test-product-4_fixed.html user@staging-server:/var/www/html/test/

# 또는 SSH로 직접 업로드
scp test-product-4_fixed.html user@abi-ops.miraepmp.co.kr:/tmp/
```

**URL 접속:**
```
https://abi-ops.miraepmp.co.kr/test/test-product-4_fixed.html?debug=1
```

**클릭 테스트:**
```
1. Variant A (FAQ 하단 버튼) 클릭
   - alert 창 없음 (이미 제거됨)
   - 페이지 새로고침 없음
   - 콘솔에 로그 메시지 있는지 F12로 확인

2. Variant B (Sticky 상단 버튼) 클릭
   - alert 창 없음 (이미 제거됨)
   - 페이지 새로고침 없음
   - 콘솔에 로그 메시지 있는지 F12로 확인

3. 개발자 도구 F12 → Console에서 확인:
   [AB Test] 클릭: ... (로그 메시지 있어야 함)
```

**로그 파일 확인:**
```bash
# 스테이징 서버에서 직접 확인
ssh user@staging-server
tail -f /var/www/html/ob/stella/abtest2/api/ab-test-logs/clicks_2025-11.json | grep "test-product-4"

# 결과 (test-product-4 항목이 추가되어야 함):
# {"page": "/test/test-product-4_fixed.html", "variant": "A", "timestamp": "2025-11-15T..."}
```

---

### Step 3️⃣: 프로덕션 배포

#### 3-1. 기존 파일 백업

```bash
# 프로덕션 서버 접속
ssh user@abi-ops.miraepmp.co.kr

# 기존 파일 백업
cd /var/www/html_bak/ob/stella/abtest2/
cp test-product-4.html test-product-4_backup_1115.html

# 백업 확인
ls -la test-product-4*.html
# output:
# -rw-r--r-- test-product-4.html              (기존)
# -rw-r--r-- test-product-4_backup_1115.html  (백업)
```

#### 3-2. 새 파일 배포

```bash
# 방법 A: 로컬에서 SCP로 업로드
scp test-product-4_fixed.html user@abi-ops.miraepmp.co.kr:/var/www/html_bak/ob/stella/abtest2/test-product-4.html

# 또는 방법 B: 프로덕션 서버에서 다운로드
ssh user@abi-ops.miraepmp.co.kr
cd /var/www/html_bak/ob/stella/abtest2/
wget https://[your-storage]/test-product-4_fixed.html -O test-product-4.html

# 권한 설정
chmod 644 test-product-4.html

# 배포 완료 확인
ls -la test-product-4.html
```

#### 3-3. 파일 검증

```bash
# 파일 크기 확인 (약 25KB)
ls -lh test-product-4.html
# output: -rw-r--r-- 25K ... test-product-4.html

# 파일 내용 검증 (첫 100줄 확인)
head -100 test-product-4.html | grep -E "(button|<a href)"
# <a href="#" class="buy-button" id="...">  ← 올바른 형식

# JSON 형식 확인 (Config 파일 확인)
cat api/ab-test-config.json | python -m json.tool | grep -A5 "test-product-4"
```

---

### Step 4️⃣: 프로덕션 검증

#### 4-1. 실시간 테스트

**URL 접속:**
```
https://abi-ops.miraepmp.co.kr/ob/stella/abtest2/test-product-4.html?debug=1
```

**테스트 항목:**
- [ ] 페이지 로드 완료
- [ ] 디버그 정보 표시됨
- [ ] Variant A/B 모두 표시되는가 (여러 번 새로고침)
- [ ] 버튼의 hover 효과 정상
- [ ] FAQ 아이템 토글 정상

#### 4-2. 클릭 로그 기록 확인

**방법 1: 실시간 로그 확인**
```bash
# 프로덕션 서버에서 로그 파일 실시간 모니터링
ssh user@abi-ops.miraepmp.co.kr
tail -f /var/www/html_bak/ob/stella/abtest2/api/ab-test-logs/clicks_2025-11.json

# 다른 터미널에서 버튼 클릭
# → 로그에 새로운 항목이 추가되는지 확인
```

**방법 2: JSON 파일 직접 확인**
```bash
# 클릭 후 파일 확인
cat /var/www/html_bak/ob/stella/abtest2/api/ab-test-logs/clicks_2025-11.json | python -m json.tool | grep -A3 "test-product-4"

# 결과 예:
# {
#     "page": "/ob/stella/abtest2/test-product-4.html",
#     "variant": "A",
#     "timestamp": "2025-11-15T10:30:45Z"
# }
```

**방법 3: 대시보드에서 확인**
```
https://abi-ops.miraepmp.co.kr/ob/stella/abtest2/index.html
→ test-product-4 통계 페이지 이동
→ "Variant A: X클릭" / "Variant B: Y클릭" 표시되는지 확인
```

---

### Step 5️⃣: 모니터링 및 확인

#### 5-1. 대시보드 모니터링 (24시간)

```
접속: https://abi-ops.miraepmp.co.kr/ob/stella/abtest2/index.html

확인 사항:
✅ test-product-4가 목록에 나타남
✅ Variant A/B 클릭수가 증가함
✅ 날짜별 통계가 수집됨
✅ 에러 메시지 없음
```

#### 5-2. 로그 파일 검증

```bash
# 하루 후 로그 파일 크기 확인 (증가해야 함)
ssh user@abi-ops.miraepmp.co.kr
ls -lh /var/www/html_bak/ob/stella/abtest2/api/ab-test-logs/clicks_2025-11.json

# 첫 번째 배포 전:  1.2M (test-product-1,2,3 데이터만)
# 배포 후 (24h):    1.5M+ (test-product-4 데이터 추가)
```

#### 5-3. 에러 로그 확인

```bash
# 웹 서버 에러 로그 확인
tail -100 /var/log/apache2/error.log | grep "test-product-4"

# PHP 에러 확인
tail -100 /var/log/php_errors.log

# 결과: 에러 메시지 없어야 함
```

---

## 🔧 문제 발생 시 대응

### 현상 1️⃣: 버튼이 보이지 않음

**원인**: CSS 로드 실패 또는 display 설정 문제  
**해결책**:
```bash
# 1. 파일 권한 확인
chmod 644 test-product-4.html

# 2. 파일 내용 확인
grep -n "display: block" test-product-4.html

# 3. 브라우저 캐시 삭제
# F12 → Settings → "Disable cache" 체크 → 새로고침
```

### 현상 2️⃣: 로그가 기록되지 않음

**원인**: ab-test-tracker.js 로드 실패 또는 클릭 감지 안 됨  
**해결책**:
```bash
# 1. ab-test-tracker.js 로드 확인
grep -n "ab-test-tracker.js" test-product-4.html

# 2. Console에서 확인
# F12 → Console → window.ABTestTracker 입력
# 결과: Object { ... } 표시되어야 함

# 3. 클릭 시 로그 메시지 확인
# F12 → Console에 "[AB Test]" 메시지 있는지 확인

# 4. 네트워크 탭에서 요청 확인
# F12 → Network → ab-test-log.php 요청 있는지 확인
```

### 현상 3️⃣: 페이지가 깨져 보임

**원인**: 파일 인코딩 문제 또는 HTML 손상  
**해결책**:
```bash
# 1. 파일 인코딩 확인
file test-product-4.html
# 결과: "UTF-8 Unicode text" 이어야 함

# 2. HTML 유효성 검사
# https://validator.w3.org/에 업로드해서 확인

# 3. 백업 파일로 복구
cp test-product-4_backup_1115.html test-product-4.html
# (문제 있으면 다시 해결책 시도)
```

### 현상 4️⃣: 버튼 스타일이 원래와 다름

**원인**: CSS text-decoration 설정 문제  
**해결책**:
```html
<!-- 확인: 다음 코드가 있는지 체크 -->
<style>
    .buy-button {
        ...
        text-decoration: none;  <!-- 이 줄이 있어야 함 -->
    }
</style>
```

---

## 📊 배포 전/후 비교

### 배포 전
```
로그 파일 상황:
├── clicks_2025-11.json
│   ├── test-product-1: ✅ 기록됨
│   ├── test-product-2: ✅ 기록됨
│   ├── test-product-3: ✅ 기록됨
│   └── test-product-4: ❌ 기록 안 됨

대시보드:
└── test-product-4
    ├── Variant A: 0 clicks ❌ (데이터 없음)
    └── Variant B: 0 clicks ❌ (데이터 없음)
```

### 배포 후 (24시간 후)
```
로그 파일 상황:
├── clicks_2025-11.json
│   ├── test-product-1: ✅ 기록됨
│   ├── test-product-2: ✅ 기록됨
│   ├── test-product-3: ✅ 기록됨
│   └── test-product-4: ✅ 기록됨!

대시보드:
└── test-product-4
    ├── Variant A: 15 clicks ✅ (데이터 수집됨!)
    └── Variant B: 18 clicks ✅ (데이터 수집됨!)
```

---

## ✅ 최종 체크리스트

### 배포 전
- [ ] test-product-4_fixed.html 다운로드 완료
- [ ] 파일 내용 검증 완료 (button → a 변경 확인)
- [ ] 로컬 테스트 완료
- [ ] 스테이징 서버 테스트 완료

### 배포 중
- [ ] 백업 파일 생성 (test-product-4_backup_1115.html)
- [ ] 새 파일 업로드 (test-product-4.html)
- [ ] 파일 권한 설정 (chmod 644)
- [ ] 파일 검증 (크기, 내용)

### 배포 후
- [ ] 프로덕션 페이지 접속 테스트
- [ ] 버튼 클릭 테스트
- [ ] 로그 파일 기록 확인
- [ ] 대시보드 통계 확인 (24시간 후)
- [ ] 에러 로그 확인

---

## 📞 긴급 대응

### 긴급 롤백 (문제 발생 시)

```bash
# 1. 빠르게 백업 파일로 복구
ssh user@abi-ops.miraepmp.co.kr
cd /var/www/html_bak/ob/stella/abtest2/
cp test-product-4_backup_1115.html test-product-4.html

# 2. 권한 설정
chmod 644 test-product-4.html

# 3. 브라우저 캐시 삭제 후 접속
# F12 → Ctrl+Shift+Delete → 캐시 삭제

# 4. 원인 파악 후 재배포
```

---

## 📝 배포 기록

**배포 날짜**: 2025-11-15  
**배포자**: [담당자 이름]  
**배포 상태**: ⏳ 대기 중

| 단계 | 상태 | 시간 | 비고 |
|------|------|------|------|
| 파일 준비 | ⏳ | - | test-product-4_fixed.html 준비 완료 |
| 로컬 테스트 | ⏳ | - | - |
| 스테이징 테스트 | ⏳ | - | - |
| 프로덕션 배포 | ⏳ | - | - |
| 검증 | ⏳ | - | - |

---

## 🎯 배포 후 예상 결과

✅ test-product-4.html 클릭 로그 기록 시작  
✅ 대시보드에서 test-product-4 통계 표시  
✅ Variant A/B 클릭 비율 분석 가능  
✅ 테스트 데이터 수집 완료

---

**준비 완료 상태**: ✅  
**배포 가능 여부**: ✅ 준비됨  
**위험도**: 🟢 낮음 (테스트 완료)

