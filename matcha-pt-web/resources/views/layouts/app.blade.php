<!DOCTYPE html>
<html lang="id" class="w-full overflow-x-hidden">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Matcha — Tennis & Padel Community' }}</title>
    
    <!-- Google Fonts: Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet" crossorigin="anonymous">
    
    <!-- Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous">
    
    <!-- Tailwind CSS with Vite -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        body {
            font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
            background-color: #f8fafc;
            color: #050608;
        }

        /* Subtle Frosted Glassmorphism */
        .glass-card {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.9);
            box-shadow: 0 4px 20px -2px rgba(5, 6, 8, 0.04), 0 1px 3px 0 rgba(5, 6, 8, 0.02);
            transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .glass-card:hover {
            background: rgba(255, 255, 255, 0.96);
            border-color: #ffffff;
            box-shadow: 0 12px 32px -4px rgba(5, 6, 8, 0.07);
        }

        .glass-subtle {
            background: rgba(255, 255, 255, 0.65);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(226, 232, 240, 0.6);
        }
    </style>
<body class="min-h-screen flex flex-col antialiased selection:bg-[#A8E63A] selection:text-[#050608] relative overflow-x-clip w-full max-w-full">
    
    <!-- Ambient Background Lighting (Subtle Pastel Blooms for Glass Effect) -->
    <div class="fixed inset-0 pointer-events-none -z-10 overflow-hidden">
        <div class="absolute -top-32 -left-32 w-96 h-96 bg-[#A8E63A]/15 rounded-full blur-3xl"></div>
        <div class="absolute top-1/3 -right-32 w-96 h-96 bg-emerald-100/40 rounded-full blur-3xl"></div>
        <div class="absolute bottom-10 left-1/4 w-80 h-80 bg-lime-50/50 rounded-full blur-3xl"></div>
    </div>

    <!-- Navbar Component -->
    @include('components.navbar')

    <!-- Main Content Area -->
    <main class="flex-grow pb-24 md:pb-8 w-full max-w-full overflow-x-hidden">
        @yield('content')
    </main>

    <!-- Mobile Bottom Navigation Bar (Visible only on mobile/tablet) -->
    @include('components.mobile-bottom-nav')

    <!-- Footer Component -->
    <div class="mb-20 md:mb-0">
        @include('components.footer')
    </div>

    <!-- Global Delete Confirmation Modal (Glassmorphic) -->
    @include('components.delete-confirm-modal')

    <!-- Notification Toast Container (Always on top of all modals and backdrops) -->
    <div id="toast-container" class="fixed bottom-24 md:bottom-6 right-4 md:right-6 z-[9999] flex flex-col space-y-2 pointer-events-none max-w-[calc(100vw-2rem)]"></div>

    <script>
        function showToast(message, type = 'success') {
            const container = document.getElementById('toast-container');
            if (!container) return;
            const toast = document.createElement('div');
            const isError = type === 'error';
            const dotColor = isError ? 'bg-rose-500 shadow-[0_0_10px_#f43f5e]' : 'bg-[#A8E63A] shadow-[0_0_10px_#A8E63A]';
            toast.className = `glass-card !bg-[#111318]/95 !text-white px-4 py-3 rounded-2xl shadow-2xl flex items-center gap-3 transition-all duration-300 transform translate-y-3 opacity-0 pointer-events-auto border border-white/15 text-xs font-semibold select-none`;
            toast.innerHTML = `<span class="w-2.5 h-2.5 rounded-full ${dotColor} shrink-0"></span> <span class="text-white drop-shadow-xs">${message}</span>`;
            container.appendChild(toast);
            
            setTimeout(() => {
                toast.classList.remove('translate-y-3', 'opacity-0');
            }, 50);

            setTimeout(() => {
                toast.classList.add('opacity-0', 'translate-y-2');
                setTimeout(() => toast.remove(), 300);
            }, 3500);
        }

        // Auto-refresh CSRF token and keep session alive on tab reactivation or periodic interval
        (function() {
            let lastActivityTime = Date.now();
            let isRefreshing = false;

            async function refreshCsrfToken() {
                if (isRefreshing) return;
                isRefreshing = true;
                try {
                    const res = await fetch('{{ route('csrf.token') }}', {
                        headers: { 'Accept': 'application/json' }
                    });
                    if (res.ok) {
                        const data = await res.json();
                        if (data && data.token) {
                            // Update meta tag
                            const meta = document.querySelector('meta[name="csrf-token"]');
                            if (meta) meta.setAttribute('content', data.token);

                            // Update all hidden _token form inputs
                            document.querySelectorAll('input[name="_token"]').forEach(input => {
                                input.value = data.token;
                            });

                            // Update axios default header if present
                            if (window.axios) {
                                window.axios.defaults.headers.common['X-CSRF-TOKEN'] = data.token;
                            }
                        }
                    }
                } catch (e) {
                    console.debug('CSRF keep-alive check skipped:', e);
                } finally {
                    isRefreshing = false;
                }
            }

            // Refresh when user returns to the tab after 3+ minutes
            document.addEventListener('visibilitychange', function() {
                if (!document.hidden) {
                    const elapsed = Date.now() - lastActivityTime;
                    if (elapsed > 3 * 60 * 1000) {
                        refreshCsrfToken();
                    }
                    lastActivityTime = Date.now();
                }
            });

            window.addEventListener('focus', function() {
                const elapsed = Date.now() - lastActivityTime;
                if (elapsed > 3 * 60 * 1000) {
                    refreshCsrfToken();
                }
                lastActivityTime = Date.now();
            });

            // Keep session alive every 15 minutes if page stays active
            setInterval(function() {
                refreshCsrfToken();
                lastActivityTime = Date.now();
            }, 15 * 60 * 1000);
        })();
    </script>
    @if(session('toast'))
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                showToast("{{ session('toast') }}");
            });
        </script>
    @endif
    @stack('scripts')
</body>
</html>
