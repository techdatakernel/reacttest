// ab-test-tracker.js - 설정 기반 Variant 제어

(function() {
    'use strict';
    
    const ABTestTracker = {
        config: {
            cookieName: 'ab_version',
            cookieExpiry: 30,
            apiEndpoint: 'https://abi-ops.miraepmp.co.kr/ob/stella/abtest/api/ab-test-log.php',
            configEndpoint: 'https://abi-ops.miraepmp.co.kr/ob/stella/abtest/api/ab-test-config.php',
            trackingPrefix: 'dtc-dwcr-'
        },

        serverConfig: null,  // 서버 설정 저장

        cookies: {
            set: function(name, value, days) {
                const date = new Date();
                date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
                const expires = "expires=" + date.toUTCString();
                document.cookie = name + "=" + value + ";" + expires + ";path=/;SameSite=Lax";
            },
            
            get: function(name) {
                const nameEQ = name + "=";
                const ca = document.cookie.split(';');
                for(let i = 0; i < ca.length; i++) {
                    let c = ca[i];
                    while (c.charAt(0) === ' ') c = c.substring(1, c.length);
                    if (c.indexOf(nameEQ) === 0) return c.substring(nameEQ.length, c.length);
                }
                return null;
            },

            delete: function(name) {
                document.cookie = name + "=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;";
            }
        },

        // 서버 설정 로드
        async loadServerConfig() {
            try {
                const response = await fetch(this.config.configEndpoint);
                this.serverConfig = await response.json();
                console.log('📋 [AB Test] 서버 설정 로드:', this.serverConfig);
                return this.serverConfig;
            } catch (error) {
                console.error('❌ [AB Test] 설정 로드 실패:', error);
                this.serverConfig = { mode: 'ab_test' };  // 기본값
                return this.serverConfig;
            }
        },

        // 스케줄 확인
        isScheduleActive() {
            if (!this.serverConfig || !this.serverConfig.schedule || !this.serverConfig.schedule.enabled) {
                return false;
            }

            const now = new Date();
            const startDate = this.serverConfig.schedule.startDate ? new Date(this.serverConfig.schedule.startDate) : null;
            const endDate = this.serverConfig.schedule.endDate ? new Date(this.serverConfig.schedule.endDate) : null;

            if (startDate && now < startDate) {
                return false;
            }

            if (endDate && now > endDate) {
                return false;
            }

            return true;
        },

        // Variant 결정 (설정 기반)
        async getVariant() {
            // 서버 설정이 없으면 로드
            if (!this.serverConfig) {
                await this.loadServerConfig();
            }

            const mode = this.serverConfig.mode;
            console.log('🎯 [AB Test] 모드:', mode);

            // 1. 스케줄 모드 확인
            if (mode === 'scheduled' && this.isScheduleActive()) {
                const scheduledVariant = this.serverConfig.schedule.variant;
                console.log('📅 [AB Test] 스케줄 활성 - Variant:', scheduledVariant);
                this.cookies.set(this.config.cookieName, scheduledVariant, this.config.cookieExpiry);
                return scheduledVariant;
            }

            // 2. 강제 모드
            if (mode === 'force_a') {
                console.log('🔒 [AB Test] 강제 모드 - Variant A');
                this.cookies.set(this.config.cookieName, 'A', this.config.cookieExpiry);
                return 'A';
            }

            if (mode === 'force_b') {
                console.log('🔒 [AB Test] 강제 모드 - Variant B');
                this.cookies.set(this.config.cookieName, 'B', this.config.cookieExpiry);
                return 'B';
            }

            // 3. 일반 A/B 테스트 모드
            let variant = this.cookies.get(this.config.cookieName);
            
            if (!variant) {
                variant = Math.random() < 0.5 ? 'A' : 'B';
                this.cookies.set(this.config.cookieName, variant, this.config.cookieExpiry);
                console.log('🎲 [AB Test] 신규 할당 - Variant:', variant);
            } else {
                console.log('🍪 [AB Test] 쿠키 사용 - Variant:', variant);
            }
            
            return variant;
        },

        async applyVariant() {
            const variant = await this.getVariant();
            const lists = document.querySelectorAll('.dtc-dwcr-list');
            
            lists.forEach(list => {
                if (list.getAttribute('data-variant') === variant) {
                    list.style.display = 'grid';
                } else {
                    list.style.display = 'none';
                }
            });

            return variant;
        },

        logClick: function(elementId, href) {
            const variant = this.cookies.get(this.config.cookieName) || 'A';
            const data = {
                variant: variant,
                elementId: elementId,
                href: href,
                pagePath: window.location.pathname,
                timestamp: new Date().toISOString(),
                userAgent: navigator.userAgent,
                referrer: document.referrer
            };

            console.log('📤 [AB Test] 클릭 전송:', data);

            fetch(this.config.apiEndpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(result => {
                console.log('✅ [AB Test] 저장 완료:', result);
            })
            .catch(err => {
                console.error('❌ [AB Test] 에러:', err);
            });
        },

        attachListeners: function() {
            const trackedLinks = document.querySelectorAll(`a[id^="${this.config.trackingPrefix}"]`);
            
            console.log('🔗 [AB Test] 추적 링크:', trackedLinks.length + '개');
            
            trackedLinks.forEach(link => {
                link.addEventListener('click', (e) => {
                    console.log('🖱️ [AB Test] 클릭:', link.id);
                    this.logClick(link.id, link.href);
                });
            });
        },

        async init(targetPath) {
            console.log('🧪 [AB Test] 초기화 시작');
            
            if (targetPath && !window.location.pathname.includes(targetPath)) {
                console.log('⏭️ [AB Test] 타겟 페이지 아님');
                return;
            }

            const variant = await this.applyVariant();
            console.log('✅ [AB Test] Variant 적용:', variant);

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', () => {
                    this.attachListeners();
                });
            } else {
                this.attachListeners();
            }
        }
    };

    window.ABTestTracker = ABTestTracker;
    console.log('✅ ABTestTracker 로드 완료');

})();