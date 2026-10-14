{{-- Global Network Quality & Offline Status Banner --}}
<div id="globalNetworkBannerWrapper" class="hidden transition-all duration-300 w-full z-40">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-3 pb-1">
        <div id="globalNetworkStatusBanner">
            <!-- Rendered dynamically by Global Network Controller -->
        </div>
    </div>
</div>

<script>
(function() {
    let currentGlobalState = 'normal';
    let globalRecoveredTimeout = null;
    let consecutivePingFailures = 0;

    // Cek apakah halaman saat ini sudah memiliki banner lokal spesifik (seperti di live scoring & drawing)
    function hasPageSpecificBanner() {
        return document.getElementById('networkStatusBannerContainer') !== null;
    }

    function renderBannerContent(container, status, details = {}, wrapper = null) {
        if (!container) return;

        if (status === 'normal') {
            if (wrapper) wrapper.classList.add('hidden');
            container.classList.add('hidden');
            container.innerHTML = '';
            return;
        }

        if (wrapper) wrapper.classList.remove('hidden');
        container.classList.remove('hidden');

        if (status === 'offline') {
            const badgeText = details.badge || 'Mode Offline';
            const descText = details.message || 'Koneksi internet Anda terputus. Halaman saat ini berjalan dalam mode offline hingga koneksi kembali pulih.';

            container.innerHTML = `
                <div class="p-3.5 mb-2 rounded-2xl bg-rose-50 border border-rose-300 text-rose-900 flex items-start sm:items-center justify-between gap-3 shadow-xs transition-all duration-200">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="w-9 h-9 rounded-xl bg-rose-100 border border-rose-200 flex items-center justify-center shrink-0 text-rose-600">
                            <svg class="w-5 h-5 animate-pulse" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="2" y1="2" x2="22" y2="22"></line>
                                <path d="M8.5 16.5a5 5 0 0 1 7 0"></path>
                                <path d="M2 8.82a15 15 0 0 1 4.17-2.65"></path>
                                <path d="M10.66 5c4.01-.36 8.14.9 11.34 3.82"></path>
                                <path d="M5 12.86a10 10 0 0 1 4.31-2.52"></path>
                                <path d="M14 10.35a10 10 0 0 1 5 2.5"></path>
                                <line x1="12" y1="20" x2="12.01" y2="20"></line>
                            </svg>
                        </div>
                        <div class="min-w-0">
                            <div class="font-bold text-xs sm:text-sm text-rose-950 flex items-center gap-1.5 flex-wrap">
                                <span>Koneksi Terputus (Mode Offline)</span>
                                <span class="inline-block w-2 h-2 rounded-full bg-rose-500 animate-ping"></span>
                            </div>
                            <p class="text-[11px] sm:text-xs text-rose-700 mt-0.5 leading-snug">
                                ${descText}
                            </p>
                        </div>
                    </div>
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-rose-100 text-rose-700 text-[10px] font-semibold shrink-0 self-start sm:self-center">
                        <svg class="w-3 h-3 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="2" y1="2" x2="22" y2="22"></line>
                            <path d="M8.5 16.5a5 5 0 0 1 7 0"></path>
                            <line x1="12" y1="20" x2="12.01" y2="20"></line>
                        </svg>
                        ${badgeText}
                    </span>
                </div>
            `;
        } else if (status === 'slow') {
            const latencyMs = details.latencyMs || 0;
            const latencySec = latencyMs > 0 ? (latencyMs / 1000).toFixed(1) + 's' : '>2.5s';
            const latencyBadge = details.badge || (latencyMs > 0 ? `~${latencySec}` : 'Lambat');
            const descText = details.message || `Respon server memakan waktu ${latencySec}. Memuat atau menyimpan data mungkin membutuhkan beberapa saat.`;

            container.innerHTML = `
                <div class="p-3.5 mb-2 rounded-2xl bg-amber-50 border border-amber-300 text-amber-900 flex items-start sm:items-center justify-between gap-3 shadow-xs transition-all duration-200">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="w-9 h-9 rounded-xl bg-amber-100 border border-amber-200 flex items-center justify-center shrink-0 text-amber-700">
                            <i class="fa-solid fa-triangle-exclamation text-base animate-pulse"></i>
                        </div>
                        <div class="min-w-0">
                            <div class="font-bold text-xs sm:text-sm text-amber-950 flex items-center gap-1.5 flex-wrap">
                                <span>Koneksi Jaringan Lambat</span>
                                <span class="text-[11px] font-semibold text-amber-700">(${latencyBadge})</span>
                            </div>
                            <p class="text-[11px] sm:text-xs text-amber-800 mt-0.5 leading-snug">
                                ${descText}
                            </p>
                        </div>
                    </div>
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-amber-200/80 text-amber-900 text-[10px] font-bold shrink-0 self-start sm:self-center">
                        <i class="fa-solid fa-gauge-high text-[10px]"></i> ${latencyBadge}
                    </span>
                </div>
            `;
        } else if (status === 'recovered') {
            const descText = details.message || 'Jaringan internet kembali stabil dan terhubung.';

            container.innerHTML = `
                <div class="p-3.5 mb-2 rounded-2xl bg-emerald-50 border border-emerald-300 text-emerald-900 flex items-start sm:items-center justify-between gap-3 shadow-xs transition-all duration-200">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="w-9 h-9 rounded-xl bg-emerald-100 border border-emerald-200 flex items-center justify-center shrink-0 text-emerald-600">
                            <i class="fa-solid fa-circle-check text-base"></i>
                        </div>
                        <div class="min-w-0">
                            <div class="font-bold text-xs sm:text-sm text-emerald-950 flex items-center gap-1.5 flex-wrap">
                                <span>Koneksi Normal Kembali</span>
                            </div>
                            <p class="text-[11px] sm:text-xs text-emerald-800 mt-0.5 leading-snug">
                                ${descText}
                            </p>
                        </div>
                    </div>
                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-emerald-100 text-emerald-800 text-[10px] font-bold shrink-0 self-start sm:self-center">
                        <i class="fa-solid fa-check text-[10px]"></i> Terhubung
                    </span>
                </div>
            `;
        }
    }

    function updateGlobalNetworkStatus(status, details = {}) {
        if (hasPageSpecificBanner()) return;

        const wrapper = document.getElementById('globalNetworkBannerWrapper');
        const container = document.getElementById('globalNetworkStatusBanner');
        if (!wrapper || !container) return;

        if (globalRecoveredTimeout) {
            clearTimeout(globalRecoveredTimeout);
            globalRecoveredTimeout = null;
        }

        currentGlobalState = status;

        if (status === 'normal') {
            renderBannerContent(container, 'normal', details, wrapper);
            return;
        }

        renderBannerContent(container, status, details, wrapper);

        if (status === 'recovered') {
            globalRecoveredTimeout = setTimeout(() => {
                if (currentGlobalState === 'recovered') {
                    updateGlobalNetworkStatus('normal');
                }
            }, 3500);
        }
    }

    // Expose global controller agar dapat dipanggil dari mana pun
    window.updateNetworkStatusUI = function(status, details = {}) {
        const pageContainer = document.getElementById('networkStatusBannerContainer');
        if (pageContainer) {
            renderBannerContent(pageContainer, status, details, pageContainer);
        } else {
            updateGlobalNetworkStatus(status, details);
        }
    };

    // Global fetch interceptor untuk memonitor latency request pada seluruh halaman
    const originalFetch = window.fetch;
    if (typeof originalFetch === 'function') {
        window.fetch = async function(...args) {
            const urlStr = (typeof args[0] === 'string') ? args[0] : (args[0]?.url || '');
            const isPing = urlStr.includes('_ping=');

            const start = performance.now();
            try {
                const response = await originalFetch.apply(this, args);
                const duration = performance.now() - start;

                if (!isPing && !hasPageSpecificBanner()) {
                    if (duration > 2500) {
                        updateGlobalNetworkStatus('slow', { latencyMs: Math.round(duration) });
                    } else if (duration <= 1500 && (currentGlobalState === 'slow' || currentGlobalState === 'offline')) {
                        updateGlobalNetworkStatus('recovered');
                    }
                }
                return response;
            } catch (err) {
                const duration = performance.now() - start;
                if (!isPing && !hasPageSpecificBanner()) {
                    if (typeof navigator !== 'undefined' && !navigator.onLine) {
                        updateGlobalNetworkStatus('offline');
                    } else if (err && err.name !== 'AbortError') {
                        updateGlobalNetworkStatus('slow', { latencyMs: Math.round(duration) });
                    }
                }
                throw err;
            }
        };
    }

    // Pengecekan latensi & koneksi secara periodik
    async function checkGlobalConnection() {
        if (hasPageSpecificBanner()) return;

        if (typeof navigator !== 'undefined' && !navigator.onLine) {
            updateGlobalNetworkStatus('offline');
            return;
        }

        // Cek Network Information API jika browser mendukung
        if (typeof navigator !== 'undefined' && 'connection' in navigator && navigator.connection) {
            const conn = navigator.connection;
            if (conn.effectiveType === 'slow-2g' || conn.effectiveType === '2g') {
                updateGlobalNetworkStatus('slow', { latencyMs: conn.rtt || 2800 });
                return;
            }
        }

        try {
            const start = performance.now();
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 6000);

            const res = await originalFetch('{{ route('csrf.token') }}?_ping=' + Date.now(), {
                cache: 'no-store',
                signal: controller.signal,
                headers: { 'Accept': 'application/json' }
            });
            clearTimeout(timeoutId);

            const duration = performance.now() - start;

            if (!res.ok) {
                consecutivePingFailures++;
                if (consecutivePingFailures >= 2) {
                    updateGlobalNetworkStatus('slow', { latencyMs: Math.round(duration) });
                }
                return;
            }

            consecutivePingFailures = 0;
            if (duration > 2500) {
                updateGlobalNetworkStatus('slow', { latencyMs: Math.round(duration) });
            } else if (currentGlobalState === 'slow' || currentGlobalState === 'offline') {
                updateGlobalNetworkStatus('recovered');
            }
        } catch (e) {
            consecutivePingFailures++;
            if (typeof navigator !== 'undefined' && !navigator.onLine) {
                updateGlobalNetworkStatus('offline');
            } else if (consecutivePingFailures >= 2 || e.name === 'AbortError') {
                updateGlobalNetworkStatus('slow', { latencyMs: 3000 });
            }
        }
    }

    function initNetworkController() {
        if (hasPageSpecificBanner()) return;

        // Cek status offline saat halaman pertama kali dibuka
        if (typeof navigator !== 'undefined' && !navigator.onLine) {
            updateGlobalNetworkStatus('offline');
        }

        // Monitor perubahan koneksi browser (Network Information API)
        if (typeof navigator !== 'undefined' && 'connection' in navigator && navigator.connection) {
            navigator.connection.addEventListener('change', () => {
                if (hasPageSpecificBanner()) return;
                const conn = navigator.connection;
                if (!navigator.onLine) {
                    updateGlobalNetworkStatus('offline');
                } else if (conn.effectiveType === 'slow-2g' || conn.effectiveType === '2g') {
                    updateGlobalNetworkStatus('slow', { latencyMs: conn.rtt || 2800 });
                } else if (conn.effectiveType === '4g' && currentGlobalState === 'slow') {
                    updateGlobalNetworkStatus('recovered');
                }
            });
        }

        // Listener event bawaan browser
        window.addEventListener('offline', () => {
            if (!hasPageSpecificBanner()) {
                updateGlobalNetworkStatus('offline');
            }
        });

        window.addEventListener('online', () => {
            if (!hasPageSpecificBanner()) {
                checkGlobalConnection();
            }
        });

        // Pengecekan saat tab aktif kembali
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden && !hasPageSpecificBanner()) {
                checkGlobalConnection();
            }
        });

        // Pengecekan koneksi berkala setiap 45 detik
        setInterval(() => {
            if (!document.hidden && !hasPageSpecificBanner()) {
                checkGlobalConnection();
            }
        }, 45000);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initNetworkController);
    } else {
        initNetworkController();
    }
})();
</script>
