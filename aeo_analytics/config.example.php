<?php
/**
 * AEO Analytics API 설정 파일 (예제)
 *
 * 사용 방법:
 * 1. 이 파일을 config.php로 복사하세요
 *    cp config.example.php config.php
 *
 * 2. config.php 파일에 실제 API 키를 입력하세요
 *
 * 주의: config.php 파일은 .gitignore에 추가되어 있어 Git에 커밋되지 않습니다!
 *
 * API 키 설정 방법:
 * 1. 이 파일에 직접 API 키를 입력하거나
 * 2. 환경 변수를 사용하세요
 */

// Claude API 키
// Anthropic 콘솔에서 API 키를 받으세요: https://console.anthropic.com/
// 환경 변수 CLAUDE_API_KEY를 설정하거나, 아래에 직접 입력하세요
define('CLAUDE_API_KEY', getenv('CLAUDE_API_KEY') ?: '');

// Gemini API 키
// Google AI Studio에서 API 키를 받으세요: https://aistudio.google.com/app/apikey
// 환경 변수 GEMINI_API_KEY를 설정하거나, 아래에 직접 입력하세요
define('GEMINI_API_KEY', getenv('GEMINI_API_KEY') ?: '');

// API 키 유효성 검사
function validateApiKeys() {
    $errors = [];

    if (empty(CLAUDE_API_KEY) || CLAUDE_API_KEY === 'your-claude-api-key-here') {
        $errors[] = 'Claude API 키가 설정되지 않았습니다. https://console.anthropic.com/ 에서 API 키를 발급받으세요.';
    }

    if (empty(GEMINI_API_KEY) || GEMINI_API_KEY === 'your-gemini-api-key-here') {
        $errors[] = 'Gemini API 키가 설정되지 않았습니다. https://aistudio.google.com/app/apikey 에서 API 키를 발급받으세요.';
    }

    return $errors;
}

// 특정 API 키만 검증하는 함수
function validateApiKey($provider) {
    $errors = [];

    if ($provider === 'claude') {
        if (empty(CLAUDE_API_KEY) || CLAUDE_API_KEY === 'your-claude-api-key-here') {
            $errors[] = 'Claude API 키가 설정되지 않았습니다. https://console.anthropic.com/ 에서 API 키를 발급받으세요.';
        }
    } elseif ($provider === 'gemini') {
        if (empty(GEMINI_API_KEY) || GEMINI_API_KEY === 'your-gemini-api-key-here') {
            $errors[] = 'Gemini API 키가 설정되지 않았습니다. https://aistudio.google.com/app/apikey 에서 API 키를 발급받으세요.';
        }
    }

    return $errors;
}
