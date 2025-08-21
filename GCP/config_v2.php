<?php
// config.php
return [
    // 로그인 설정
    'username' => 'admin',
    'password_hash' => password_hash('admin123', PASSWORD_DEFAULT),
    'session_timeout' => 3600,
    
    // GCP BigQuery 설정 (샘플 테이블)
    'gcp_service_account_file' => __DIR__ . '/nimble-mode-415514-ed2ecc37e8f4.json',
    'gcp_project_id' => 'nimble-mode-415514',
    'dataset_id' => 'bqml_tutorial',
    'table_id' => 'sesco3_sample',  // 샘플 테이블 사용
    
    // 비용 최적화 설정
    'max_query_rows' => 1000,
    'default_page_size' => 50,
    'cache_duration' => 600,
    
    // 데이터베이스 설정
    'db_host' => 'localhost',
    'db_name' => 'sesco_dashboard',
    'db_user' => 'root',
    'db_pass' => '',
];
?>