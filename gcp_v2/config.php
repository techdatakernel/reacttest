<?php
/**
 * GCP BigQuery 대시보드 설정 파일
 * 완전한 비용 모니터링 및 보안 설정 포함
 */

return [
    // === GCP 기본 설정 ===
    'gcp_service_account_file' => __DIR__ . '/nimble-mode-415514-ed2ecc37e8f4.json',
    'gcp_project_id' => 'nimble-mode-415514',
    'dataset_id' => 'bqml_tutorial',
    'table_id' => 'sesco3_sample',
    'max_query_rows' => 1000,
    
    // === 캐시 설정 ===
    'cache_duration' => 3600, // 1시간 (초 단위)
    'cache_enabled' => true,
    
    // === 보안 설정 ===
   'username' => 'admin',  // login.php에서 참조하는 키 이름으로 변경
    'password_hash' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', // password
    'session_timeout' => 7200, // 2시간 (초 단위)
    'max_login_attempts' => 5,
    'lockout_duration' => 1800, // 30분 (초 단위)
    
    // === 비용 모니터링 설정 ===
    'cost_monitoring' => [
        'enabled' => true,
        'daily_limit' => 1.0,      // $1.00 per day
        'weekly_limit' => 5.0,     // $5.00 per week
        'monthly_limit' => 20.0,   // $20.00 per month
        'monthly_budget' => 50.0,  // $50.00 monthly budget
        
        // 알림 임계값
        'warning_threshold' => 80, // 80% 사용 시 경고
        'critical_threshold' => 95, // 95% 사용 시 심각 경고
        
        // 자동 제한 설정
        'auto_suspend_on_limit' => true,
        'auto_restrict_queries' => true,
    ],
    
    // === 비용 추적 파일 경로 ===
    'cost_tracking' => [
        'usage_file' => __DIR__ . '/logs/daily_usage.json',
        'log_file' => __DIR__ . '/logs/cost_monitoring.log',
        'alerts_file' => __DIR__ . '/logs/cost_alerts.log',
        'user_actions_file' => __DIR__ . '/logs/user_actions.log',
    ],
    
    // === 알림 설정 ===
    'notifications' => [
        'email' => [
            'enabled' => false, // 이메일 알림 비활성화 (SMTP 설정 필요 시 true로 변경)
            'recipients' => [
                'admin@example.com',
                'manager@example.com'
            ],
            'smtp_host' => 'smtp.gmail.com',
            'smtp_port' => 587,
            'smtp_username' => '',
            'smtp_password' => '',
            'smtp_encryption' => 'tls'
        ],
        'slack' => [
            'enabled' => false,
            'webhook_url' => '',
            'channel' => '#alerts'
        ]
    ],
    
    // === 로깅 설정 ===
    'logging' => [
        'enabled' => true,
        'log_level' => 'INFO', // DEBUG, INFO, WARNING, ERROR
        'max_log_size' => 10485760, // 10MB
        'log_rotation' => true,
        'keep_logs_days' => 30
    ],
    
    // === 쿼리 최적화 설정 ===
    'query_optimization' => [
        'use_cache' => true,
        'cache_ttl' => 3600,
        'max_concurrent_queries' => 3,
        'query_timeout' => 30,
        'auto_retry' => true,
        'max_retries' => 3
    ],
    
    // === 대시보드 UI 설정 ===
    'dashboard' => [
        'refresh_interval' => 300, // 5분 (초 단위)
        'auto_refresh' => true,
        'show_debug_info' => false,
        'chart_animation' => true,
        'chart_height' => 400,
        'rows_per_page' => 50
    ],
    
    // === 데이터 보존 설정 ===
    'data_retention' => [
        'cache_cleanup_days' => 7,
        'log_cleanup_days' => 30,
        'usage_data_cleanup_days' => 90
    ],
    
    // === API 설정 ===
    'api' => [
        'rate_limit' => 100, // 시간당 요청 수
        'enable_cors' => false,
        'allowed_origins' => [],
        'api_key_required' => false,
        'api_key' => ''
    ],
    
    // === 개발/디버그 설정 ===
    'debug' => [
        'enabled' => false, // 운영 환경에서는 false
        'show_sql_queries' => false,
        'log_all_requests' => false,
        'simulate_costs' => true, // 실제 비용 대신 시뮬레이션 사용
        'mock_data' => false
    ],
    
    // === 백업 설정 ===
    'backup' => [
        'enabled' => false,
        'backup_interval' => 86400, // 24시간 (초 단위)
        'backup_retention_days' => 30,
        'backup_location' => __DIR__ . '/backups/',
        'include_logs' => true
    ],
    
    // === 성능 모니터링 ===
    'performance' => [
        'track_query_time' => true,
        'track_memory_usage' => true,
        'slow_query_threshold' => 5.0, // 초
        'memory_limit_warning' => 128 // MB
    ],
    
    // === 환경별 설정 ===
    'environment' => 'production', // development, staging, production
    
    // === 버전 정보 ===
    'version' => '2.0.0',
    'last_updated' => '2024-08-22',
    
    // === 추가 보안 설정 ===
    'security' => [
        'enable_ip_whitelist' => false,
        'allowed_ips' => [
            '127.0.0.1',
            '::1'
        ],
        'enable_2fa' => false,
        'password_min_length' => 8,
        'password_require_special_chars' => true,
        'session_regenerate_id' => true,
        'secure_cookies' => true,
        'csrf_protection' => true
    ],
    
    // === 외부 서비스 통합 ===
    'integrations' => [
        'google_analytics' => [
            'enabled' => false,
            'tracking_id' => ''
        ],
        'datadog' => [
            'enabled' => false,
            'api_key' => ''
        ]
    ]
];
?>