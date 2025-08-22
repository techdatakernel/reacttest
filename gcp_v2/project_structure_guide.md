# 🎯 GCP BigQuery 대시보드 - 완전한 프로젝트 가이드

## 📁 프로젝트 구조

```
gcp-bigquery-dashboard/
├── 📄 config.php                          # 설정 파일 (비용 모니터링 포함)
├── 📄 bigquery.php                        # BigQuery API 클래스 (실제 비용 모니터링)
├── 📄 api.php                            # API 엔드포인트 (비용 API 포함)
├── 📄 dashboard.php                       # 메인 대시보드 (실시간 비용 표시)
├── 📄 login.php                          # 로그인 페이지
├── 📄 logout.php                         # 로그아웃 처리
├── 📄 nimble-mode-415514-ed2ecc37e8f4.json # GCP 서비스 계정 키 (보안 설정 필요)
├── 📁 assets/
│   ├── 🎨 style.css                      # 글래스모피즘 스타일 + 비용 모니터링 UI
│   └── ⚡ script.js                      # 완전한 JavaScript 기능
├── 📁 cache/                             # 캐시 디렉토리 (자동 생성)
│   └── 📄 dashboard_2024-08-22-14.json  # 시간별 캐시 파일
└── 📁 logs/                              # 로그 디렉토리 (자동 생성)
    ├── 📄 cost_monitoring.log            # 비용 모니터링 로그
    ├── 📄 daily_usage.json               # 일일 사용량 데이터
    ├── 📄 cost_alerts.log                # 비용 알림 로그
    ├── 📄 login_attempts.log             # 로그인 시도 로그
    ├── 📄 user_actions.log               # 사용자 액션 로그
    └── 📄 admin_actions.log               # 관리자 액션 로그
```

## 🚀 설치 단계별 가이드

### 1단계: 시스템 요구사항 확인

```bash
# PHP 버전 확인 (7.4 이상 필요)
php --version

# 필요한 PHP 확장 모듈 확인
php -m | grep -E "(curl|openssl|json)"

# 웹서버 확인 (Apache 또는 Nginx)
```

### 2단계: 프로젝트 파일 설정

```bash
# 1. 웹서버 디렉토리에 프로젝트 폴더 생성
sudo mkdir /var/www/html/gcp-dashboard
cd /var/www/html/gcp-dashboard

# 2. 필요한 디렉토리 생성
mkdir -p assets cache logs

# 3. 권한 설정
sudo chown -R www-data:www-data .
chmod 755 cache logs
chmod 644 *.php *.html
chmod 600 *.json  # 서비스 계정 키 보안
```

### 3단계: 파일 생성 순서

각 아티팩트의 내용을 다음 순서로 파일을 생성하세요:

1. **config.php** ← 첫 번째 아티팩트
2. **bigquery.php** ← 두 번째 아티팩트  
3. **api.php** ← 세 번째 아티팩트
4. **dashboard.php** ← 네 번째 아티팩트
5. **login.php** ← 다섯 번째 아티팩트
6. **logout.php** ← 여섯 번째 아티팩트
7. **assets/style.css** ← 일곱 번째 아티팩트
8. **assets/script.js** ← 여덟 번째 아티팩트

### 4단계: GCP 설정

```bash
# 1. GCP 콘솔에서 서비스 계정 키 다운로드
# 2. nimble-mode-415514-ed2ecc37e8f4.json 파일을 프로젝트 루트에 배치
# 3. BigQuery API 활성화 확인
# 4. 서비스 계정에 BigQuery 권한 부여:
#    - BigQuery Data Viewer
#    - BigQuery Job User
```

### 5단계: 웹서버 설정

#### Apache 설정 예시:
```apache
<VirtualHost *:80>
    ServerName dashboard.local
    DocumentRoot /var/www/html/gcp-dashboard
    
    <Directory /var/www/html/gcp-dashboard>
        AllowOverride All
        Require all granted
    </Directory>
    
    # 보안 설정
    <Files "*.json">
        Require all denied
    </Files>
    
    <Files "config*.php">
        Require all denied
    </Files>
    
    # HTTPS 리다이렉트 (운영환경)
    # RewriteEngine On
    # RewriteCond %{HTTPS} off
    # RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
    
    ErrorLog ${APACHE_LOG_DIR}/dashboard_error.log
    CustomLog ${APACHE_LOG_DIR}/dashboard_access.log combined
</VirtualHost>
```

#### Nginx 설정 예시:
```nginx
server {
    listen 80;
    server_name dashboard.local;
    root /var/www/html/gcp-dashboard;
    index login.php;
    
    # PHP 처리
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.0-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
    
    # 보안 설정
    location ~ \.(json|log)$ {
        deny all;
        return 404;
    }
    
    location ~ /logs/ {
        deny all;
        return 404;
    }
    
    # 정적 파일 캐싱
    location ~* \.(css|js|png|jpg|jpeg|gif|ico|svg)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
}
```

### 6단계: 초기 설정 확인

```bash
# 1. 브라우저에서 접속
http://localhost/gcp-dashboard/login.php

# 2. 기본 로그인 정보
# 사용자명: admin
# 비밀번호: admin123

# 3. 로그 파일 권한 확인
ls -la logs/

# 4. 캐시 디렉토리 확인
ls -la cache/
```

## 🔧 설정 커스터마이징

### config.php 주요 설정 변경:

```php
// 로그인 정보 변경
'username' => 'your_username',
'password_hash' => password_hash('your_secure_password', PASSWORD_DEFAULT),

// 비용 한도 조정
'cost_monitoring' => [
    'daily_limit' => 10.00,     // $10 일일 한도
    'weekly_limit' => 50.00,    // $50 주간 한도  
    'monthly_limit' => 200.00,  // $200 월간 한도
    'monthly_budget' => 250.00, // $250 월간 예산
],

// 이메일 알림 설정
'notifications' => [
    'email' => [
        'enabled' => true,
        'recipients' => ['admin@yourcompany.com']
    ]
]
```

## 📊 실제 비용 모니터링 기능

### ✅ 구현된 실제 기능들:

1. **실시간 비용 추적**
   - 일일/주간/월간 사용량 실제 계산
   - BigQuery API 호출 비용 예측
   - 실시간 예산 사용률 표시

2. **자동 비용 제어**
   - 예산 90% 도달시 캐시 모드 전환
   - 주간 한도 초과시 쿼리 제한
   - 월간 한도 초과시 서비스 일시중단

3. **비용 알림 시스템**
   - 단계별 알림 (이메일, 로그)
   - 관리자 액션 로깅
   - 사용 패턴 분석

4. **비용 분석 도구**
   - 7일/14일/30일 트렌드 분석
   - 비용 예측 모델
   - CSV 보고서 다운로드

### 📈 모니터링되는 실제 데이터:

- **일일 사용량**: `logs/daily_usage.json`에 실제 저장
- **비용 로그**: `logs/cost_monitoring.log`에 상세 기록
- **알림 내역**: `logs/cost_alerts.log`에 모든 알림 저장
- **사용자 액션**: `logs/user_actions.log`에 모든 활동 기록

## 🛡️ 보안 기능

1. **인증 및 권한**
   - bcrypt 비밀번호 해싱
   - 세션 타임아웃 (1시간)
   - 로그인 시도 제한 (5회)

2. **파일 보안**
   - JSON 키 파일 접근 차단
   - 로그 디렉토리 보호
   - CSRF 방지

3. **비용 보안**
   - 예산 초과 방지
   - 자동 서비스 보호
   - 관리자 권한 제어

## 🔍 문제 해결

### 자주 발생하는 문제들:

1. **서비스 계정 인증 오류**
   ```bash
   # 파일 권한 확인
   chmod 600 nimble-mode-415514-ed2ecc37e8f4.json
   
   # 파일 경로 확인
   ls -la *.json
   ```

2. **캐시/로그 디렉토리 권한 오류**
   ```bash
   # 권한 재설정
   sudo chown -R www-data:www-data cache logs
   chmod 755 cache logs
   ```

3. **비용 모니터링 데이터 초기화**
   ```bash
   # 사용량 데이터 리셋
   rm logs/daily_usage.json
   rm logs/cost_monitoring.log
   ```

4. **제한 모드 수동 해제**
   ```php
   // dashboard.php에서 세션 리셋
   unset($_SESSION['query_restricted']);
   unset($_SESSION['service_suspended']);  
   unset($_SESSION['cache_mode_only']);
   ```

## 📞 지원 및 문의

이 대시보드는 **실제 작동하는 비용 모니터링 시스템**을 포함한 완전한 솔루션입니다. 

모든 비용 데이터는 실제로 계산되고 저장되며, 예산 한도에 따라 자동으로 서비스를 보호합니다.

설치나 설정 중 문제가 발생하면 로그 파일(`logs/` 디렉토리)을 확인하여 상세한 오류 정보를 얻을 수 있습니다.