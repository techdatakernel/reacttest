<?php
/**
 * 로그인 페이지
 */
session_start();

$config = include 'config.php';

// 이미 로그인된 경우 대시보드로 리다이렉트
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$loginAttempts = $_SESSION['login_attempts'] ?? 0;
$lastAttempt = $_SESSION['last_attempt'] ?? 0;

// 로그인 시도 제한 (5회 실패시 5분 대기)
if ($loginAttempts >= 5 && (time() - $lastAttempt) < 300) {
    $error = '너무 많은 로그인 시도로 인해 5분간 대기해야 합니다.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error)) {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    // 입력값 검증
    if (empty($username) || empty($password)) {
        $error = '사용자명과 비밀번호를 입력해주세요.';
    } else {
        // 로그인 검증
        if ($username === $config['username'] && password_verify($password, $config['password_hash'])) {
            // 로그인 성공
            $_SESSION['logged_in'] = true;
            $_SESSION['username'] = $username;
            $_SESSION['login_time'] = time();
            
            // 로그인 시도 초기화
            unset($_SESSION['login_attempts']);
            unset($_SESSION['last_attempt']);
            
            // 로그인 성공 로깅
            logLoginAttempt($username, true, $_SERVER['REMOTE_ADDR'] ?? 'unknown');
            
            header('Location: dashboard.php');
            exit;
        } else {
            // 로그인 실패
            $error = '사용자명 또는 비밀번호가 올바르지 않습니다.';
            
            // 실패 횟수 증가
            $_SESSION['login_attempts'] = $loginAttempts + 1;
            $_SESSION['last_attempt'] = time();
            
            // 로그인 실패 로깅
            logLoginAttempt($username, false, $_SERVER['REMOTE_ADDR'] ?? 'unknown');
        }
    }
}

/**
 * 로그인 시도 로깅
 */
function logLoginAttempt($username, $success, $ip) {
    $logEntry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'username' => $username,
        'success' => $success,
        'ip' => $ip,
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ];
    
    $logDir = __DIR__ . '/logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    file_put_contents($logDir . '/login_attempts.log', json_encode($logEntry) . "\n", FILE_APPEND);
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🔐 로그인 - GCP BigQuery 대시보드</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #333;
        }

        .login-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 3rem;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            width: 100%;
            max-width: 400px;
            text-align: center;
        }

        .login-header {
            margin-bottom: 2rem;
        }

        .login-header h1 {
            font-size: 2rem;
            margin-bottom: 0.5rem;
            color: #333;
        }

        .login-header p {
            color: #666;
            font-size: 1rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
            text-align: left;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #333;
        }

        .form-group input {
            width: 100%;
            padding: 1rem;
            border: 2px solid #e1e5e9;
            border-radius: 10px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
            background: rgba(255, 255, 255, 0.8);
        }

        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .login-btn {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            margin-bottom: 1rem;
        }

        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
        }

        .login-btn:active {
            transform: translateY(0);
        }

        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            border: 1px solid #f5c6cb;
            text-align: center;
        }

        .info-box {
            background: rgba(102, 126, 234, 0.1);
            border: 1px solid rgba(102, 126, 234, 0.2);
            color: #667eea;
            padding: 1rem;
            border-radius: 10px;
            margin-top: 1.5rem;
            font-size: 0.9rem;
        }

        .security-info {
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid #e1e5e9;
        }

        .security-info h3 {
            font-size: 1rem;
            margin-bottom: 1rem;
            color: #333;
        }

        .security-item {
            display: flex;
            align-items: center;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
            color: #666;
        }

        .security-item span {
            margin-right: 0.5rem;
        }

        .login-attempts {
            font-size: 0.8rem;
            color: #666;
            margin-top: 1rem;
        }

        @media (max-width: 768px) {
            .login-container {
                margin: 1rem;
                padding: 2rem;
            }
        }

        /* 로딩 애니메이션 */
        .loading {
            display: none;
        }

        .loading.active {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 2px solid #ffffff;
            border-radius: 50%;
            border-top-color: transparent;
            animation: spin 1s ease-in-out infinite;
            margin-left: 10px;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1>🎯 GCP 대시보드</h1>
            <p>BigQuery 데이터 분석 및 비용 모니터링</p>
        </div>

        <?php if ($error): ?>
            <div class="error-message">
                <strong>⚠️ 오류:</strong> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST" id="loginForm">
            <div class="form-group">
                <label for="username">👤 사용자명</label>
                <input 
                    type="text" 
                    id="username" 
                    name="username" 
                    required 
                    autocomplete="username"
                    value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                >
            </div>

            <div class="form-group">
                <label for="password">🔐 비밀번호</label>
                <input 
                    type="password" 
                    id="password" 
                    name="password" 
                    required 
                    autocomplete="current-password"
                >
            </div>

            <button type="submit" class="login-btn" id="loginBtn">
                로그인
                <div class="loading" id="loadingSpinner"></div>
            </button>
        </form>

        <?php if ($loginAttempts > 0): ?>
            <div class="login-attempts">
                로그인 시도: <?php echo $loginAttempts; ?>/5
                <?php if ($loginAttempts >= 5): ?>
                    <br>다음 시도까지: <span id="countdown"></span>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="info-box">
            <strong>💡 데모 계정:</strong><br>
            사용자명: admin<br>
            비밀번호: admin123
        </div>

        <div class="security-info">
            <h3>🛡️ 보안 기능</h3>
            <div class="security-item">
                <span>🔒</span> 비밀번호 해시 암호화
            </div>
            <div class="security-item">
                <span>⏱️</span> 세션 타임아웃 (1시간)
            </div>
            <div class="security-item">
                <span>🚫</span> 로그인 시도 제한 (5회)
            </div>
            <div class="security-item">
                <span>📊</span> 실시간 비용 모니터링
            </div>
            <div class="security-item">
                <span>💰</span> 자동 예산 보호 시스템
            </div>
        </div>
    </div>

    <script>
        // 로그인 폼 제출 시 로딩 표시
        document.getElementById('loginForm').addEventListener('submit', function() {
            const button = document.getElementById('loginBtn');
            const spinner = document.getElementById('loadingSpinner');
            
            button.disabled = true;
            spinner.classList.add('active');
            button.textContent = '로그인 중...';
        });

        // 로그인 제한 카운트다운
        <?php if ($loginAttempts >= 5 && (time() - $lastAttempt) < 300): ?>
        let remainingTime = <?php echo 300 - (time() - $lastAttempt); ?>;
        
        function updateCountdown() {
            const minutes = Math.floor(remainingTime / 60);
            const seconds = remainingTime % 60;
            
            document.getElementById('countdown').textContent = 
                `${minutes}:${seconds.toString().padStart(2, '0')}`;
            
            if (remainingTime <= 0) {
                location.reload();
            } else {
                remainingTime--;
                setTimeout(updateCountdown, 1000);
            }
        }
        
        updateCountdown();
        <?php endif; ?>

        // 키보드 단축키
        document.addEventListener('keydown', function(event) {
            // Ctrl+Enter로 로그인
            if (event.ctrlKey && event.key === 'Enter') {
                document.getElementById('loginForm').submit();
            }
        });

        // 자동 포커스
        document.addEventListener('DOMContentLoaded', function() {
            const usernameField = document.getElementById('username');
            const passwordField = document.getElementById('password');
            
            if (!usernameField.value) {
                usernameField.focus();
            } else {
                passwordField.focus();
            }
        });

        // 보안 강화: 개발자 도구 감지 및 경고
        let devtools = {
            open: false,
            orientation: null
        };

        setInterval(function() {
            if (window.outerHeight - window.innerHeight > 200 || 
                window.outerWidth - window.innerWidth > 200) {
                if (!devtools.open) {
                    devtools.open = true;
                    console.warn('🚨 보안 알림: 이 시스템은 모니터링되고 있습니다.');
                }
            } else {
                devtools.open = false;
            }
        }, 500);

        // 우클릭 방지
        document.addEventListener('contextmenu', function(e) {
            e.preventDefault();
        });

        // 특정 키 조합 방지
        document.addEventListener('keydown', function(e) {
            // F12, Ctrl+Shift+I, Ctrl+Shift+C, Ctrl+U 방지
            if (e.key === 'F12' || 
                (e.ctrlKey && e.shiftKey && (e.key === 'I' || e.key === 'C')) ||
                (e.ctrlKey && e.key === 'U')) {
                e.preventDefault();
            }
        });
    </script>
</body>
</html>