<?php
// config.php - 비용 모니터링 설정이 포함된 완전한 설정 파일
return [
    // 로그인 설정
    'username' => 'admin',
    'password_hash' => password_hash('admin123', PASSWORD_DEFAULT),
    'session_timeout' => 3600, // 1시간
    
    // GCP BigQuery 설정
    'gcp_service_account_file' => __DIR__ . '/nimble-mode-415514-ed2ecc37e8f4.json',
    'gcp_project_id' => 'nimble-mode-415514',
    'dataset_id' => 'bqml_tutorial',
    'table_id' => 'sesco3_sample',
    
    // 비용 최적화 설정
    'max_query_rows' => 1000,
    'default_page_size' => 50,
    'cache_duration' => 600, // 10분
    
    // 비용 모니터링 설정
    'cost_monitoring' => [
        'enabled' => true,
        'daily_limit' => 5.00,      // $5 일일 한도
        'weekly_limit' => 20.00,    // $20 주간 한도
        'monthly_limit' => 80.00,   // $80 월간 한도
        'monthly_budget' => 100.00, // $100 월간 예산
        'budget_warning_threshold' => 0.90, // 90% 경고
        
        // 알림 설정
        'notifications' => [
            'email' => [
                'enabled' => true,
                'recipients' => ['admin@sesco.com']
            ],
            'slack' => [
                'enabled' => false,
                'webhook_url' => ''
            ]
        ],
        
        // 자동 제어 설정
        'auto_controls' => [
            'enable_cache_mode_at_90_percent' => true,
            'restrict_queries_at_weekly_limit' => true,
            'suspend_service_at_monthly_limit' => true
        ]
    ],
    
    // 비용 추적 파일 경로
    'cost_tracking' => [
        'log_file' => __DIR__ . '/logs/cost_monitoring.log',
        'usage_file' => __DIR__ . '/logs/daily_usage.json',
        'alerts_file' => __DIR__ . '/logs/cost_alerts.log'
    ],
    
    // BigQuery 비용 계산 설정
    'bigquery_pricing' => [
        'query_cost_per_tb' => 6.00, // $6 per TB processed
        'storage_cost_per_gb_month' => 0.02, // $0.02 per GB/month
        'bytes_per_tb' => 1099511627776 // 1TB in bytes
    ],
    
    // 캐시 설정
    'cache' => [
        'enabled' => true,
        'directory' => __DIR__ . '/cache',
        'cleanup_interval' => 3600, // 1시간마다 정리
        'max_cache_age' => 86400 // 24시간
    ],
    
    // 로그 설정
    'logging' => [
        'enabled' => true,
        'level' => 'INFO', // DEBUG, INFO, WARNING, ERROR
        'max_file_size' => 10485760, // 10MB
        'rotate_files' => true
    ],
    
    // 보안 설정
    'security' => [
        'csrf_protection' => true,
        'rate_limiting' => [
            'enabled' => true,
            'max_requests_per_minute' => 60,
            'max_queries_per_hour' => 100
        ]
    ]
];
?>