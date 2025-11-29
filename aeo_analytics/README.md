# AEO Analytics - AI 검색 엔진 최적화 분석 도구

Claude API와 Gemini API를 사용하여 웹 페이지의 AI 검색 엔진 최적화(AEO) 점수를 분석합니다.

## 🚀 빠른 시작

### 1. API 키 설정

먼저 `config.php` 파일을 생성하세요:

```bash
cd aeo_analytics
cp config.example.php config.php
```

### 2. API 키 입력

`config.php` 파일을 열고 실제 API 키를 입력하세요:

```php
// Claude API 키 (15번째 줄)
define('CLAUDE_API_KEY', 'sk-ant-api03-xxxxx');

// Gemini API 키 (20번째 줄)
define('GEMINI_API_KEY', 'AIzaSyxxxxxx');
```

#### API 키 발급 방법

**Claude API 키:**
1. https://console.anthropic.com/ 접속
2. 로그인 후 API Keys 메뉴로 이동
3. "Create Key" 클릭하여 새 API 키 생성
4. 생성된 키를 복사 (sk-ant-로 시작)

**Gemini API 키:**
1. https://aistudio.google.com/app/apikey 접속
2. Google 계정으로 로그인
3. "Create API Key" 클릭
4. 생성된 키를 복사 (AIza로 시작)

### 3. 사용 방법

브라우저에서 다음 파일 중 하나를 열면 됩니다:

- **Claude 버전:** `aeo_analyzer_claude_api.php`
- **Gemini 버전:** `aeo_analyzer_gemini_api.php`

예시:
```
http://localhost/aeo_analytics/aeo_analyzer_claude_api.php
```

## 📋 분석 항목

1. **BM25 키워드 분석** (40점)
   - 키워드 빈도 (TF)
   - 키워드 위치
   - 키워드 희소성

2. **시맨틱 유사도 분석** (48점)
   - 주제 일치도
   - 의미적 연관성
   - 맥락 이해도
   - 정보 충족도

3. **FAQ 구조 분석** (20점)
   - FAQ 형식 존재 여부
   - AI 친화성

4. **추가 분석**
   - 쿼리 확장
   - 관련성 증거
   - 개선 권고사항

## ⚙️ 환경 변수 사용 (선택사항)

파일에 직접 API 키를 입력하는 대신 환경 변수를 사용할 수 있습니다:

```bash
export CLAUDE_API_KEY="sk-ant-api03-xxxxx"
export GEMINI_API_KEY="AIzaSyxxxxxx"
```

## 🔒 보안

- `config.php` 파일은 `.gitignore`에 포함되어 있어 Git에 커밋되지 않습니다
- API 키를 절대 공개 저장소에 올리지 마세요
- 프로덕션 환경에서는 환경 변수 사용을 권장합니다

## 🐛 문제 해결

### "API 키 설정 오류" 메시지가 나올 때

1. `config.php` 파일이 존재하는지 확인
2. API 키가 올바르게 입력되었는지 확인
3. API 키에 불필요한 공백이 없는지 확인

### "JSON 파싱 실패" 오류가 나올 때

1. API 키가 유효한지 확인 (API 콘솔에서 확인)
2. API 사용량 제한을 초과하지 않았는지 확인
3. 인터넷 연결 상태 확인

### HTTP 403/401 오류

- API 키가 만료되었거나 잘못되었습니다
- API 콘솔에서 새 키를 생성하세요

### HTTP 429 오류

- API 사용량 제한(Rate Limit)을 초과했습니다
- 잠시 후 다시 시도하세요

## 📦 파일 구조

```
aeo_analytics/
├── aeo_analyzer_claude_api.php   # Claude API 버전
├── aeo_analyzer_gemini_api.php   # Gemini API 버전
├── config.example.php             # 설정 파일 템플릿
├── config.php                     # 실제 설정 파일 (직접 생성)
└── README.md                      # 이 파일
```

## 💡 팁

1. **처음 사용 시:** Gemini API는 무료 사용량이 있어 테스트하기 좋습니다
2. **정확도 우선:** Claude Sonnet 4 모델 사용 권장
3. **속도 우선:** Gemini 2.0 Flash 또는 Claude 3.5 Haiku 사용
4. **Temperature:** 0.7 (기본값) 사용 권장, 일관된 결과를 원하면 0.3-0.5로 낮추세요
