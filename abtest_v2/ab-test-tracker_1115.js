// ab-test-tracker.js - Multi-page A/B Test Tracker (개선 버전)

(function() {
    'use strict';

    const ABTestTracker = {
        config: {
            cookieName: 'ab_version',
            cookieExpiry: 30,
            apiEndpoint: 'https://abi-ops.miraepmp.co.kr/ob/stella/abtest2/api/ab-test-log.php',
            configEndpoint: 'https://abi-ops.miraepmp.co.kr/ob/stella/abtest2/api/ab-test-config.php',
            trackingPrefix: 'dtc-dwcr-'
        },

        serverConfig: null,
        currentPagePath: null,
        variantApplied: false,

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

        async loadServerConfig() {
            try {
                this.currentPagePath = window.location.pathname;
                const url = `${this.config.configEndpoint}?pagePath=${encodeURIComponent(this.currentPagePath)}`;
                
                console.log('📋 [AB Test] 설정 로드 요청:', url);
                
                const response = await fetch(url);
                const data = await response.json();

                console.log('📋 [AB Test] 서버 응답:', data);

                if (!data.config) {
                    console.log('⚠️ [AB Test] 페이지 설정 없음, 전역 설정 사용');
                    this.serverConfig = {
                        enabled: false,
                        mode: data.global?.defaultMode || 'ab_test',
                        schedule: { enabled: false }
                    };
                    return this.serverConfig;
                }

                if (!data.config.enabled) {
                    console.log('🚫 [AB Test] 페이지 비활성화됨');
                    this.serverConfig = { enabled: false };
                    return this.serverConfig;
                }

                this.serverConfig = data.config;
                this.serverConfig.enabled = true;

                if (data.global?.cookieExpiry) {
                    this.config.cookieExpiry = data.global.cookieExpiry;
                }

                console.log('✅ [AB Test] 설정 로드 완료:', this.serverConfig);
                return this.serverConfig;

            } catch (error) {
                console.error('❌ [AB Test] 설정 로드 실패:', error);
                this.serverConfig = {
                    enabled: false,
                    mode: 'ab_test'
                };
                return this.serverConfig;
            }
        },

        isScheduleActive() {
            if (!this.serverConfig || !this.serverConfig.schedule || !this.serverConfig.schedule.enabled) {
                return false;
            }

            const now = new Date();
            const startDate = this.serverConfig.schedule.startDate ? new Date(this.serverConfig.schedule.startDate) : null;
            const endDate = this.serverConfig.schedule.endDate ? new Date(this.serverConfig.schedule.endDate) : null;

            if (startDate && now < startDate) return false;
            if (endDate && now > endDate) return false;

            return true;
        },

        async getVariant() {
            if (!this.serverConfig) {
                await this.loadServerConfig();
            }

            if (!this.serverConfig.enabled) {
                console.log('⭐️ [AB Test] 비활성화된 페이지, 스킵');
                return null;
            }

            const mode = this.serverConfig.mode;
            console.log('🎯 [AB Test] 모드:', mode);

            if (mode === 'scheduled' && this.isScheduleActive()) {
                const scheduledVariant = this.serverConfig.schedule.variant;
                console.log('📅 [AB Test] 스케줄 활성 - Variant:', scheduledVariant);
                this.cookies.set(this.config.cookieName, scheduledVariant, this.config.cookieExpiry);
                return scheduledVariant;
            }

            if (mode === 'force_a') {
                console.log('🔓 [AB Test] 강제 모드 - Variant A');
                this.cookies.set(this.config.cookieName, 'A', this.config.cookieExpiry);
                return 'A';
            }

            if (mode === 'force_b') {
                console.log('🔓 [AB Test] 강제 모드 - Variant B');
                this.cookies.set(this.config.cookieName, 'B', this.config.cookieExpiry);
                return 'B';
            }

            let variant = this.cookies.get(this.config.cookieName);

            if (!variant) {
                variant = Math.random() < 0.5 ? 'A' : 'B';
                this.cookies.set(this.config.cookieName, variant, this.config.cookieExpiry);
                console.log('🎲 [AB Test] 신규 할당 - Variant:', variant);
            } else {
                console.log('🔖 [AB Test] 쿠키 사용 - Variant:', variant);
            }

            return variant;
        },

        async applyVariant() {
            const variant = await this.getVariant();

            if (!variant) {
                console.log('⭐️ [AB Test] Variant 적용 스킵');
                return null;
            }

            // ⭐ DOM 로드 대기
            if (document.readyState === 'loading') {
                console.log('⏳ [AB Test] DOM 로드 대기...');
                await new Promise(resolve => {
                    document.addEventListener('DOMContentLoaded', resolve);
                });
            }

            const lists = document.querySelectorAll('.dtc-dwcr-list');
            console.log('🔍 [AB Test] 찾은 리스트 요소:', lists.length);

            if (lists.length === 0) {
                console.warn('⚠️ [AB Test] .dtc-dwcr-list 요소가 없습니다');
                return null;
            }

            lists.forEach(list => {
                const listVariant = list.getAttribute('data-variant');
                console.log('📝 [AB Test] 리스트 체크 - Expected:', variant, 'Found:', listVariant);
                
                if (listVariant === variant) {
                    list.style.display = 'grid';
                    console.log('✅ [AB Test] 표시됨:', listVariant);
                } else {
                    list.style.display = 'none';
                    console.log('❌ [AB Test] 숨김:', listVariant);
                }
            });

            this.variantApplied = true;
            console.log('✅ [AB Test] Variant 적용 완료:', variant);
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
            console.log('🧪 [AB Test] 초기화 시작 - 페이지:', window.location.pathname);

            if (targetPath && !window.location.pathname.includes(targetPath)) {
                console.log('⭐️ [AB Test] 타겟 페이지 아님');
                return;
            }

            try {
                // ⭐ 1단계: Variant 적용 (DOM 로드 대기 포함)
                const variant = await this.applyVariant();

                if (!variant) {
                    console.log('⭐️ [AB Test] 초기화 중단 (비활성화된 페이지)');
                    return;
                }

                console.log('✅ [AB Test] Variant 적용됨:', variant);

                // ⭐ 2단계: 클릭 리스너 부착 (Variant 적용 후)
                this.attachListeners();
                
                console.log('🎉 [AB Test] 초기화 완료');
            } catch (error) {
                console.error('❌ [AB Test] 초기화 실패:', error);
            }
        }
    };

    window.ABTestTracker = ABTestTracker;
    console.log('✅ ABTestTracker 로드 완료 (Multi-page 지원)');

})();