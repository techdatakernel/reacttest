#!/bin/bash
# init-ab-test-config.sh

CONFIG_DIR="/var/www/html_bak/ob/stella/abtest/api"
CONFIG_FILE="$CONFIG_DIR/ab-test-config.json"

echo "🚀 A/B 테스트 설정 초기화 시작"

# 디렉토리 확인
if [ ! -d "$CONFIG_DIR" ]; then
    echo "❌ API 디렉토리가 없습니다: $CONFIG_DIR"
    exit 1
fi

# 기본 설정 JSON
cat > "$CONFIG_FILE" << 'EOF'
{
    "mode": "ab_test",
    "forceVariant": null,
    "schedule": {
        "enabled": false,
        "startDate": null,
        "endDate": null,
        "variant": null
    },
    "lastUpdated": "2025-10-31T20:00:00Z",
    "updatedBy": "system"
}
EOF

# 권한 설정
chmod 666 "$CONFIG_FILE"
chown apache:apache "$CONFIG_FILE"

echo "✅ 설정 파일 생성 완료: $CONFIG_FILE"
echo ""
echo "파일 내용:"
cat "$CONFIG_FILE"
echo ""
echo "권한:"
ls -la "$CONFIG_FILE"