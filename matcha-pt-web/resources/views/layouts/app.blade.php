<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Matcha — Tennis & Padel Community' }}</title>
    
    <!-- Google Fonts: Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
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
</head>
<body class="min-h-screen flex flex-col antialiased selection:bg-[#A8E63A] selection:text-[#050608] relative">
    
    <!-- Ambient Background Lighting (Subtle Pastel Blooms for Glass Effect) -->
    <div class="fixed inset-0 pointer-events-none -z-10 overflow-hidden">
        <div class="absolute -top-32 -left-32 w-96 h-96 bg-[#A8E63A]/15 rounded-full blur-3xl"></div>
        <div class="absolute top-1/3 -right-32 w-96 h-96 bg-emerald-100/40 rounded-full blur-3xl"></div>
        <div class="absolute bottom-10 left-1/4 w-80 h-80 bg-lime-50/50 rounded-full blur-3xl"></div>
    </div>

    <!-- Navbar Component -->
    @include('components.navbar')

    <!-- Main Content Area -->
    <main class="flex-grow pb-16 md:pb-0">
        @yield('content')
    </main>

    <!-- Footer Component -->
    @include('components.footer')

    <!-- Notification Toast Container -->
    <div id="toast-container" class="fixed bottom-6 right-6 z-50 flex flex-col space-y-2 pointer-events-none"></div>

    <script>
        function showToast(message, type = 'success') {
            const container = document.getElementById('toast-container');
            const toast = document.createElement('div');
            toast.className = `glass-card !bg-[#111318]/95 !text-white px-4 py-3 rounded-2xl shadow-xl flex items-center gap-3 transition-all duration-300 transform translate-y-3 opacity-0 pointer-events-auto border border-white/10 text-xs font-medium`;
            toast.innerHTML = `<span class="w-2.5 h-2.5 rounded-full bg-[#A8E63A] shadow-[0_0_8px_#A8E63A]"></span> <span class="text-white">${message}</span>`;
            container.appendChild(toast);
            
            setTimeout(() => {
                toast.classList.remove('translate-y-3', 'opacity-0');
            }, 50);

            setTimeout(() => {
                toast.classList.add('opacity-0', 'translate-y-2');
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }
    </script>
    @stack('scripts')
</body>
</html>
