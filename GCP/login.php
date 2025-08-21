<?php
// login.php
session_start();

$config = include 'config.php';

// 이미 로그인된 경우 대시보드로 리다이렉트
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if ($username === $config['username'] && password_verify($password, $config['password_hash'])) {
        $_SESSION['logged_in'] = true;
        $_SESSION['login_time'] = time();
        $_SESSION['username'] = $username;
        header('Location: dashboard.php');
        exit;
    } else {
        $error = '잘못된 사용자명 또는 비밀번호입니다.';
        sleep(1); // 브루트포스 공격 방지
    }
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SESCO 데이터 대시보드 - 로그인</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body class="login-page">
    <div class="login-container">
        <div class="login-box">
            <div class="login-header">
                <h1>📊 SESCO 데이터 대시보드</h1>
                <p>GCP BigQuery 연동 분석 플랫폼</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" class="login-form">
                <div class="form-group">
                    <label for="username">사용자명</label>
                    <input type="text" id="username" name="username" required 
                           placeholder="사용자명을 입력하세요" value="admin">
                </div>
                
                <div class="form-group">
                    <label for="password">비밀번호</label>
                    <input type="password" id="password" name="password" required 
                           placeholder="비밀번호를 입력하세요" value="admin123">
                </div>
                
                <button type="submit" class="btn btn-primary btn-login">
                    🔐 로그인
                </button>
            </form>
            
            <div class="login-footer">
                <p>💡 기본 계정: admin / admin123</p>
                <small>보안을 위해 운영 환경에서는 비밀번호를 변경해주세요.</small>
            </div>
        </div>
    </div>
</body>
</html>