# AEO Analytics API 설정 가이드

## 문제 상황

Claude와 Gemini API가 **404 에러**를 반환하는 경우, 대부분 API 키가 올바르게 설정되지 않았기 때문입니다.

## 해결 방법

### 1. API 키 발급

#### Claude API 키 발급
1. [Anthropic Console](https://console.anthropic.com/)에 접속
2. API Keys 메뉴로 이동
3. "Create Key" 버튼 클릭
4. API 키를 복사 (예: `sk-ant-api03-...`)

#### Gemini API 키 발급
1. [Google AI Studio](https://aistudio.google.com/app/apikey)에 접속
2. "Get API Key" 또는 "Create API Key" 클릭
3. API 키를 복사 (예: `AIza...`)

### 2. API 키 설정

**방법 1: config.php 파일에 직접 입력 (권장)**

`/aeo_analytics/config.php` 파일을 열고 다음 줄을 수정하세요:

```php
// 수정 전
define('CLAUDE_API_KEY', getenv('CLAUDE_API_KEY') ?: 'your-claude-api-key-here');
define('GEMINI_API_KEY', getenv('GEMINI_API_KEY') ?: 'your-gemini-api-key-here');

// 수정 후 (실제 API 키로 교체)
define('CLAUDE_API_KEY', getenv('CLAUDE_API_KEY') ?: 'sk-ant-api03-여기에실제키입력');
define('GEMINI_API_KEY', getenv('GEMINI_API_KEY') ?: 'AIza여기에실제키입력');
```

**방법 2: 환경 변수 사용 (더 안전)**

서버 환경 변수로 설정:

```bash
export CLAUDE_API_KEY="sk-ant-api03-여기에실제키입력"
export GEMINI_API_KEY="AIza여기에실제키입력"
```

또는 `.env` 파일 사용 (PHP dotenv 라이브러리 필요):
```
CLAUDE_API_KEY=sk-ant-api03-여기에실제키입력
GEMINI_API_KEY=AIza여기에실제키입력
```

### 3. 변경사항 확인

1. 웹 브라우저에서 해당 페이지 새로고침
2. 테스트 쿼리 실행
3. 404 에러 대신 정상적인 분석 결과가 나오는지 확인

## 보안 주의사항

⚠️ **중요**: API 키는 절대 Git에 커밋하지 마세요!

1. `config.php` 파일을 `.gitignore`에 추가하세요
2. 또는 환경 변수를 사용하세요
3. API 키가 유출되면 즉시 폐기하고 새로 발급받으세요

## 업데이트된 모델 목록

### Claude 모델
- `claude-sonnet-4-20250514` - Claude Sonnet 4 (최신, 기본값)
- `claude-3-5-sonnet-20241022` - Claude 3.5 Sonnet
- `claude-3-5-haiku-20241022` - Claude 3.5 Haiku (빠름)
- `claude-3-opus-20240229` - Claude 3 Opus (고품질)

### Gemini 모델
- `gemini-2.0-flash-thinking-exp-01-21` - Gemini 2.0 Thinking (추론형, 기본값)
- `gemini-1.5-pro` - Gemini 1.5 Pro
- `gemini-2.0-flash-exp` - Gemini 2.0 Flash (빠름)

## 문제 해결

### 여전히 404 에러가 발생하는 경우

1. **API 키 확인**
   - config.php의 API 키가 `your-*-api-key-here`가 아닌 실제 키인지 확인
   - API 키에 공백이나 줄바꿈이 없는지 확인

2. **API 키 유효성 확인**
   - Claude: [API 콘솔](https://console.anthropic.com/)에서 키 상태 확인
   - Gemini: [AI Studio](https://aistudio.google.com/app/apikey)에서 키 상태 확인

3. **모델 ID 확인**
   - 선택한 모델 ID가 위 목록에 있는지 확인
   - 오타가 없는지 확인

4. **네트워크 확인**
   - 방화벽이나 프록시가 API 요청을 차단하지 않는지 확인
   - `curl` 명령으로 직접 테스트:
     ```bash
     curl -X POST https://api.anthropic.com/v1/messages \
       -H "x-api-key: YOUR_API_KEY" \
       -H "anthropic-version: 2023-06-01" \
       -H "content-type: application/json" \
       -d '{"model":"claude-sonnet-4-20250514","max_tokens":100,"messages":[{"role":"user","content":"Hello"}]}'
     ```

## 지원

문제가 계속되면 다음 정보를 제공해주세요:
- 사용 중인 PHP 버전
- 브라우저 개발자 도구의 네트워크 탭 스크린샷
- PHP 에러 로그
