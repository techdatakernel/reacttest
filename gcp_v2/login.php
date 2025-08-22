<?php
/**
 * 로그인 페이지 - 보안 강화 버전 (수정됨)
 */
session_start();

// config.php 파일 확인 및 로드
if (!file_exists('config.php')) {
    die('config.php 파일이 존재하지 않습니다.');
}

$config = include 'config.php';
if (!$config) {
    die('config.php 파일을 읽을 수 없습니다.');
}

// 보안 헤더 설정
if (isset($config['security_headers']) && is_array($config['security_headers'])) {
    foreach ($config['security_headers'] as $header => $value) {
        header("$header: $value");
    }
}

$error = '';
$loginAttempts = 0;
$isLocked = false;

// 로그인 시도 횟수 확인
if (isset($_SESSION['login_attempts'])) {
    $loginAttempts = $_SESSION['login_attempts'];
    $lastAttempt = $_SESSION['last_attempt'] ?? 0;
    
    // 잠금 시간 확인
    if ($loginAttempts >= $config['max_login_attempts']) {
        $timeSinceLastAttempt = time() - $lastAttempt;
        if ($timeSinceLastAttempt < $config['lockout_duration']) {
            $isLocked = true;
            $remainingTime = $config['lockout_duration'] - $timeSinceLastAttempt;
            $error = "너무 많은 로그인 시도로 계정이 잠겼습니다. " . ceil($remainingTime / 60) . "분 후 다시 시도하세요.";
        } else {
            // 잠금 시간이 지나면 리셋
            $_SESSION['login_attempts'] = 0;
            unset($_SESSION['last_attempt']);
            $loginAttempts = 0;
            $isLocked = false;
        }
    }
}

// 이미 로그인된 경우 대시보드로 이동
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header('Location: dashboard.php');
    exit;
}

// 로그인 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$isLocked) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // 로그인 시도 로깅
    $logEntry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'username' => $username,
        'success' => false
    ];
    
    if (empty($username) || empty($password)) {
        $error = '사용자명과 비밀번호를 입력하세요.';
    } elseif ($username === $config['username'] && password_verify($password, $config['password_hash'])) {
        // 로그인 성공
        $_SESSION['logged_in'] = true;
        $_SESSION['username'] = $username;
        $_SESSION['login_time'] = time();
        $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        
        // 로그인 시도 횟수 리셋
        unset($_SESSION['login_attempts']);
        unset($_SESSION['last_attempt']);
        
        $logEntry['success'] = true;
        
        // 로그인 성공 로그
        $logLine = date('Y-m-d H:i:s') . " - LOGIN SUCCESS - " . json_encode($logEntry) . "\n";
        
        // 로그 디렉토리 생성
        if (!is_dir('logs')) {
            mkdir('logs', 0755, true);
        }
        
        file_put_contents('logs/login_attempts.log', $logLine, FILE_APPEND | LOCK_EX);
        
        header('Location: dashboard.php');
        exit;
    } else {
        // 로그인 실패
        $loginAttempts++;
        $_SESSION['login_attempts'] = $loginAttempts;
        $_SESSION['last_attempt'] = time();
        
        $error = '잘못된 사용자명 또는 비밀번호입니다.';
        
        if ($loginAttempts >= $config['max_login_attempts']) {
            $error = "로그인 시도 횟수를 초과했습니다. " . ($config['lockout_duration'] / 60) . "분 후 다시 시도하세요.";
            $isLocked = true;
        }
        
        // 로그인 실패 로그
        $logLine = date('Y-m-d H:i:s') . " - LOGIN FAILED - " . json_encode($logEntry) . "\n";
        
        if (!is_dir('logs')) {
            mkdir('logs', 0755, true);
        }
        
        file_put_contents('logs/login_attempts.log', $logLine, FILE_APPEND | LOCK_EX);
    }
}

// 새로운 비밀번호 해시 생성 함수 (개발용)
function generatePasswordHash($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

// 개발 환경에서 비밀번호 해시 표시
if (isset($config['environment']) && $config['environment'] === 'development') {
    // admin123의 해시값을 콘솔에 출력
    $newHash = generatePasswordHash('admin123');
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🔐 GCP BigQuery 대시보드 로그인</title>
    <style>
        /* === 풀페이지 뷰 기본 리셋 === */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html, body {
            width: 100% !important;
            height: 100% !important;
            overflow: hidden !important;
            margin: 0 !important;
            padding: 0 !important;
        }

        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --glass-bg: rgba(255, 255, 255, 0.25);
            --glass-border: rgba(255, 255, 255, 0.18);
            --glass-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.37);
            --success-color: #4CAF50;
            --warning-color: #FFC107;
            --danger-color: #F44336;
            --info-color: #2196F3;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: var(--primary-gradient);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 20px;
        }

        /* === 배경 애니메이션 === */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 1000"><defs><radialGradient id="a" cx="50%" cy="50%"><stop offset="0%" stop-color="rgba(255,255,255,0.1)"/><stop offset="100%" stop-color="rgba(255,255,255,0)"/></radialGradient></defs><circle cx="200" cy="200" r="100" fill="url(%23a)"><animate attributeName="cx" values="200;800;200" dur="20s" repeatCount="indefinite"/></circle><circle cx="800" cy="800" r="150" fill="url(%23a)"><animate attributeName="cy" values="800;200;800" dur="25s" repeatCount="indefinite"/></circle></svg>') no-repeat center center;
            background-size: cover;
            opacity: 0.3;
            pointer-events: none;
            z-index: -1;
        }

        /* === 로그인 컨테이너 === */
        .login-container {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            padding: 3rem 2.5rem;
            box-shadow: var(--glass-shadow);
            width: 100%;
            max-width: 450px;
            text-align: center;
            animation: slideUp 0.6s ease-out;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* === 헤더 === */
        .login-header {
            margin-bottom: 2rem;
        }

        .login-header h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .login-header p {
            color: rgba(255, 255, 255, 0.8);
            font-size: 1.1rem;
            font-weight: 300;
        }

        /* === 폼 스타일 === */
        .login-form {
            margin-bottom: 2rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
            text-align: left;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: rgba(255, 255, 255, 0.9);
            font-weight: 600;
            font-size: 0.9rem;
        }

        .form-group input {
            width: 100%;
            padding: 1rem 1.25rem;
            border: 2px solid rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            color: white;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .form-group input::placeholder {
            color: rgba(255, 255, 255, 0.6);
        }

        .form-group input:focus {
            outline: none;
            border-color: rgba(255, 255, 255, 0.5);
            background: rgba(255, 255, 255, 0.15);
            box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.1);
        }

        /* === 버튼 === */
        .login-btn {
            width: 100%;
            padding: 1rem 1.5rem;
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 12px;
            color: white;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .login-btn:hover:not(:disabled) {
            background: rgba(255, 255, 255, 0.3);
            border-color: rgba(255, 255, 255, 0.5);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
        }

        .login-btn:active:not(:disabled) {
            transform: translateY(0);
        }

        .login-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        /* === 오류 메시지 === */
        .error-message {
            background: rgba(244, 67, 54, 0.2);
            border: 1px solid rgba(244, 67, 54, 0.5);
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1.5rem;
            color: #ffebee;
            font-size: 0.9rem;
            animation: shake 0.5s ease-in-out;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }

        /* === 로그인 시도 인디케이터 === */
        .login-attempts {
            margin-bottom: 1rem;
            font-size: 0.85rem;
            color: rgba(255, 255, 255, 0.7);
        }

        .attempts-bar {
            width: 100%;
            height: 4px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 2px;
            margin-top: 0.5rem;
            overflow: hidden;
        }

        .attempts-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--success-color), var(--warning-color), var(--danger-color));
            border-radius: 2px;
            transition: width 0.3s ease;
        }

        /* === 추가 정보 === */
        .login-info {
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid rgba(255, 255, 255, 0.2);
        }

        .info-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 0.8rem;
            font-size: 0.85rem;
            color: rgba(255, 255, 255, 0.7);
        }

        .info-item .label {
            font-weight: 600;
        }

        .info-item .value {
            font-family: monospace;
            background: rgba(255, 255, 255, 0.1);
            padding: 0.2rem 0.5rem;
            border-radius: 4px;
        }

        /* === 보안 배지 === */
        .security-badges {
            display: flex;
            justify-content: center;
            gap: 1rem;
            margin-top: 1.5rem;
        }

        .security-badge {
            display: flex;
            align-items: center;
            gap: 0.3rem;
            background: rgba(255, 255, 255, 0.1);
            padding: 0.3rem 0.6rem;
            border-radius: 20px;
            font-size: 0.75rem;
            color: rgba(255, 255, 255, 0.8);
        }

        /* === 개발자 정보 === */
        .dev-info {
            margin-top: 1rem;
            padding: 1rem;
            background: rgba(255, 193, 7, 0.1);
            border: 1px solid rgba(255, 193, 7, 0.3);
            border-radius: 8px;
            font-size: 0.8rem;
            color: rgba(255, 255, 255, 0.9);
        }

        .dev-info h4 {
            margin-bottom: 0.5rem;
            color: #FFC107;
        }

        /* === 반응형 === */
        @media (max-width: 480px) {
            .login-container {
                padding: 2rem 1.5rem;
                margin: 1rem;
            }

            .login-header h1 {
                font-size: 2rem;
            }

            .security-badges {
                flex-direction: column;
                align-items: center;
            }
        }

        /* === 로딩 애니메이션 === */
        .loading {
            display: none;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
        }

        .loading.active {
            display: block;
        }

        .loading .spinner {
            width: 30px;
            height: 30px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-top: 3px solid white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1>🔐 보안 로그인</h1>
            <p>GCP BigQuery 대시보드</p>
        </div>

        <?php if ($error): ?>
        <div class="error-message">
            <strong>⚠️ 로그인 실패</strong><br>
            <?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>

        <?php if ($loginAttempts > 0 && !$isLocked): ?>
        <div class="login-attempts">
            <div>로그인 시도: <?php echo $loginAttempts; ?> / <?php echo $config['max_login_attempts']; ?></div>
            <div class="attempts-bar">
                <div class="attempts-fill" style="width: <?php echo ($loginAttempts / $config['max_login_attempts']) * 100; ?>%"></div>
            </div>
        </div>
        <?php endif; ?>

        <form class="login-form" method="POST" action="" <?php echo $isLocked ? 'style="opacity: 0.5; pointer-events: none;"' : ''; ?>>
            <div class="form-group">
                <label for="username">🧑‍💼 사용자명</label>
                <input 
                    type="text" 
                    id="username" 
                    name="username" 
                    placeholder="사용자명을 입력하세요"
                    required
                    autocomplete="username"
                    <?php echo $isLocked ? 'disabled' : ''; ?>
                >
            </div>

            <div class="form-group">
                <label for="password">🔑 비밀번호</label>
                <input 
                    type="password" 
                    id="password" 
                    name="password" 
                    placeholder="비밀번호를 입력하세요"
                    required
                    autocomplete="current-password"
                    <?php echo $isLocked ? 'disabled' : ''; ?>
                >
            </div>

            <button 
                type="submit" 
                class="login-btn"
                <?php echo $isLocked ? 'disabled' : ''; ?>
            >
                🚀 로그인
            </button>

            <div class="loading" id="loading">
                <div class="spinner"></div>
            </div>
        </form>

        <?php if (isset($config['environment']) && $config['environment'] === 'development'): ?>
        <div class="dev-info">
            <h4>🔧 개발 환경 로그인 정보</h4>
            <div><strong>사용자명:</strong> admin</div>
            <div><strong>비밀번호:</strong> admin123</div>
            <div style="font-size: 0.7rem; margin-top: 0.5rem; opacity: 0.7;">
                운영 환경에서는 이 정보가 표시되지 않습니다.
            </div>
        </div>
        <?php endif; ?>

        <div class="login-info">
            <div class="info-item">
                <span class="label">🌐 서버 시간:</span>
                <span class="value"><?php echo date('Y-m-d H:i:s'); ?></span>
            </div>
            <div class="info-item">
                <span class="label">🔒 세션 유지:</span>
                <span class="value"><?php echo ($config['session_timeout'] / 60); ?>분</span>
            </div>
            <div class="info-item">
                <span class="label">🛡️ 보안 수준:</span>
                <span class="value">높음</span>
            </div>
            <div class="info-item">
                <span class="label">💾 환경:</span>
                <span class="value"><?php echo $config['environment'] ?? 'production'; ?></span>
            </div>
        </div>

        <div class="security-badges">
            <div class="security-badge">
                <span>🔐</span>
                <span>SSL 암호화</span>
            </div>
            <div class="security-badge">
                <span>🛡️</span>
                <span>bcrypt 해싱</span>
            </div>
            <div class="security-badge">
                <span>⏱️</span>
                <span>세션 보호</span>
            </div>
        </div>
    </div>

    <script>
        // 폼 제출 시 로딩 애니메이션
        document.querySelector('.login-form').addEventListener('submit', function(e) {
            if (!this.querySelector('button').disabled) {
                document.getElementById('loading').classList.add('active');
                this.querySelector('button').disabled = true;
                this.querySelector('button').textContent = '🔄 로그인 중...';
            }
        });

        // 엔터 키 처리
        document.addEventListener('keypress', function(e) {
            if (e.key === 'Enter' && !document.querySelector('.login-btn').disabled) {
                document.querySelector('.login-form').submit();
            }
        });

        // 입력 필드 포커스 효과
        const inputs = document.querySelectorAll('input');
        inputs.forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.style.transform = 'translateY(-2px)';
            });
            
            input.addEventListener('blur', function() {
                this.parentElement.style.transform = 'translateY(0)';
            });
        });

        // 보안 정보 업데이트
        setInterval(function() {
            const timeElement = document.querySelector('.info-item .value');
            if (timeElement) {
                const now = new Date();
                timeElement.textContent = now.getFullYear() + '-' + 
                    String(now.getMonth() + 1).padStart(2, '0') + '-' + 
                    String(now.getDate()).padStart(2, '0') + ' ' + 
                    String(now.getHours()).padStart(2, '0') + ':' + 
                    String(now.getMinutes()).padStart(2, '0') + ':' + 
                    String(now.getSeconds()).padStart(2, '0');
            }
        }, 1000);

        // 개발 환경에서 기본 로그인 정보 표시
        <?php if (isset($config['environment']) && $config['environment'] === 'development'): ?>
        console.log('🔧 개발 환경 기본 로그인 정보:');
        console.log('사용자명: admin');
        console.log('비밀번호: admin123');
        
        <?php if (isset($newHash)): ?>
        console.log('새 비밀번호 해시:', '<?php echo $newHash; ?>');
        <?php endif; ?>
        <?php endif; ?>
    </script>
</body>
</html>