<?php
/**
 * 로그아웃 처리 페이지
 */
session_start();

// 로그아웃 로깅
if (isset($_SESSION['username'])) {
    logLogoutAction($_SESSION['username'], $_SERVER['REMOTE_ADDR'] ?? 'unknown');
}

// 세션 완전 삭제
$_SESSION = array();

// 세션 쿠키 삭제
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 세션 파기
session_destroy();

/**
 * 로그아웃 액션 로깅
 */
function logLogoutAction($username, $ip) {
    $logEntry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'action' => 'logout',
        'username' => $username,
        'ip' => $ip,
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'session_duration' => isset($_SESSION['login_time']) ? (time() - $_SESSION['login_time']) : 0
    ];
    
    $logDir = __DIR__ . '/logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    file_put_contents($logDir . '/user_actions.log', json_encode($logEntry) . "\n", FILE_APPEND);
}

// 로그인 페이지로 리다이렉트
header('Location: login.php?logged_out=1');
exit;
?>