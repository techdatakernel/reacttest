<?php
// /var/www/html_bak/ob/stella/abtest/api/create-dummy-data.php

define('LOG_DIR', __DIR__ . '/ab-test-logs/');
define('LOG_FILE', LOG_DIR . 'clicks_' . date('Y-m') . '.json');

echo "현재 디렉토리: " . __DIR__ . "\n";
echo "로그 디렉토리: " . LOG_DIR . "\n";
echo "로그 파일: " . LOG_FILE . "\n\n";

// 디렉토리 확인
if (!file_exists(LOG_DIR)) {
    echo "디렉토리 생성 중...\n";
    mkdir(LOG_DIR, 0755, true);
}

// 판매처 목록
$channels = [
    'dtc-dwcr-kakao-gift' => 'https://kko.kakao.com/Sn9n9e87U5',
    'dtc-dwcr-cu-pocket' => 'https://www.pocketcu.co.kr/deepLink/checkAppInstall',
    'dtc-dwcr-gs-25' => 'https://abr.ge/1kg2l3',
    'dtc-dwcr-daily-shot' => 'https://open.dailyshot.co/pu4k3a',
    'dtc-dwcr-emart-24' => 'https://abr.ge/4rmf25',
    'dtc-dwcr-seven-eleven' => 'https://new.7-elevenapp.co.kr/common/share-call-back/'
];

$userAgents = [
    'Mozilla/5.0 (iPhone; CPU iPhone OS 14_6 like Mac OS X) AppleWebKit/605.1.15',
    'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
    'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36',
    'Mozilla/5.0 (Linux; Android 11) AppleWebKit/537.36'
];

// 더미 데이터 생성 (최근 30일간 150개)
$logs = [];
$now = time();

echo "더미 데이터 생성 중...\n";

for ($i = 0; $i < 150; $i++) {
    // 랜덤 시간 (최근 30일)
    $randomTime = $now - rand(0, 30 * 24 * 60 * 60);
    
    // 랜덤 채널 선택
    $channelKeys = array_keys($channels);
    $channelId = $channelKeys[array_rand($channelKeys)];
    
    // Variant B에 약간 더 많은 클릭 부여 (테스트용)
    $variant = (rand(1, 100) <= 55) ? 'B' : 'A';
    
    $logs[] = [
        'id' => uniqid('click_', true),
        'variant' => $variant,
        'elementId' => $channelId,
        'href' => $channels[$channelId],
        'pagePath' => '/products/hanmac-extracreamydraftcan-handle-package',
        'timestamp' => date('c', $randomTime),
        'userAgent' => $userAgents[array_rand($userAgents)],
        'referrer' => rand(0, 1) ? 'https://www.google.com' : '',
        'ipAddress' => '127.0.0.' . rand(1, 255),
        'serverTimestamp' => date('c', $randomTime)
    ];
}

// 시간순 정렬
usort($logs, function($a, $b) {
    return strtotime($a['timestamp']) - strtotime($b['timestamp']);
});

// 파일 저장
$jsonData = json_encode($logs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

if (file_put_contents(LOG_FILE, $jsonData)) {
    echo "\n✅ 더미 데이터 생성 완료!\n";
    echo "파일 위치: " . $LOG_FILE . "\n";
    echo "파일 크기: " . number_format(strlen($jsonData)) . " bytes\n";
    echo "데이터 개수: " . count($logs) . "개\n";
    
    // 통계 출력
    $variantA = count(array_filter($logs, fn($l) => $l['variant'] === 'A'));
    $variantB = count(array_filter($logs, fn($l) => $l['variant'] === 'B'));
    
    echo "\n📊 통계:\n";
    echo "- Variant A: {$variantA}개 (" . round($variantA/count($logs)*100, 1) . "%)\n";
    echo "- Variant B: {$variantB}개 (" . round($variantB/count($logs)*100, 1) . "%)\n";
    
    // 파일 권한 확인
    $perms = substr(sprintf('%o', fileperms(LOG_FILE)), -4);
    echo "\n파일 권한: {$perms}\n";
    
} else {
    echo "\n❌ 파일 저장 실패\n";
    echo "에러: " . error_get_last()['message'] . "\n";
}
?>