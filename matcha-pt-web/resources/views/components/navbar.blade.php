<header class="sticky top-0 z-40 bg-white/80 backdrop-blur-xl border-b border-white/80 shadow-[0_1px_10px_rgba(0,0,0,0.02)]">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16">
            <!-- Brand Logo -->
            <div class="flex items-center gap-8">
                <a href="{{ route('dashboard') }}" class="flex items-center gap-3 group">
                    <img src="{{ asset('images/logo.svg') }}" alt="Matcha Logo" class="w-9 h-9 rounded-xl shadow-xs group-hover:scale-105 transition-all">
                    <div>
                        <span class="text-base font-black tracking-tight text-[#050608] leading-none block">
                            MATCHA
                        </span>
                        <span class="text-[9px] font-extrabold tracking-widest text-[#063B00] uppercase -mt-0.5 block">
                            MATCH ARENA
                        </span>
                    </div>
                </a>

                <!-- Desktop Nav -->
                <nav class="hidden md:flex items-center gap-1">
                    <a href="{{ route('dashboard') }}" class="px-3 py-1.5 rounded-xl text-xs font-semibold transition-all {{ request()->routeIs('dashboard') ? 'bg-[#063B00] text-white shadow-xs font-bold' : 'text-slate-600 hover:text-[#063B00] hover:bg-white/60' }}">
                        Dashboard
                    </a>
                    <a href="{{ route('games.index') }}" class="px-3 py-1.5 rounded-xl text-xs font-semibold transition-all {{ request()->routeIs('games.*') ? 'bg-[#063B00] text-white shadow-xs font-bold' : 'text-slate-600 hover:text-[#063B00] hover:bg-white/60' }}">
                        Jadwal Mabar
                    </a>
                    <a href="{{ route('venues.index') }}" class="px-3 py-1.5 rounded-xl text-xs font-semibold transition-all {{ request()->routeIs('venues.*') ? 'bg-[#063B00] text-white shadow-xs font-bold' : 'text-slate-600 hover:text-[#063B00] hover:bg-white/60' }}">
                        Venue & Court
                    </a>
                    <a href="{{ route('communities.index') }}" class="px-3 py-1.5 rounded-xl text-xs font-semibold transition-all {{ request()->routeIs('communities.*') ? 'bg-[#063B00] text-white shadow-xs font-bold' : 'text-slate-600 hover:text-[#063B00] hover:bg-white/60' }}">
                        Komunitas
                    </a>
                    <a href="{{ route('player.recap') }}" class="px-3 py-1.5 rounded-xl text-xs font-semibold transition-all {{ request()->routeIs('player.*') ? 'bg-[#063B00] text-white shadow-xs font-bold' : 'text-slate-600 hover:text-[#063B00] hover:bg-white/60' }}">
                        Match Recap
                    </a>
                </nav>
            </div>

            <!-- Header Actions -->
            <div class="flex items-center gap-2">
                <a href="{{ route('login') }}" class="hidden sm:inline-flex text-xs font-semibold text-slate-600 hover:text-[#063B00] px-3 py-1.5 rounded-xl hover:bg-white/60 transition-colors">
                    Masuk
                </a>
                <a href="{{ route('register') }}" class="hidden sm:inline-flex text-xs font-semibold text-slate-600 hover:text-[#063B00] px-3 py-1.5 rounded-xl hover:bg-white/60 transition-colors">
                    Daftar
                </a>
                <a href="{{ route('games.create') }}" class="inline-flex items-center gap-1.5 bg-[#063B00] hover:bg-[#042a00] text-white font-semibold px-3.5 py-1.5 rounded-xl text-xs transition-all shadow-xs hover:shadow-sm hover:scale-[1.02]">
                    <i class="fa-solid fa-plus text-[10px]"></i> <span class="hidden sm:inline">Host Game</span><span class="sm:hidden">Host</span>
                </a>

                <!-- User Profile / Mobile Toggle -->
                <div class="flex items-center pl-1 sm:pl-2 border-l border-slate-200/60">
                    <a href="{{ route('player.profile') }}" class="flex items-center gap-2 p-1 rounded-xl hover:bg-white/60 transition-colors" title="Profil Member">
                        <img src="https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=80&q=80" alt="Avatar" class="w-7 h-7 rounded-full object-cover ring-1 ring-slate-200">
                        <span class="hidden lg:inline text-xs font-semibold text-[#050608]">Billy</span>
                    </a>
                    
                    <!-- Mobile Hamburger Button -->
                    <button onclick="toggleMobileMenu()" class="md:hidden ml-1 p-2 text-slate-600 hover:text-[#063B00] focus:outline-none rounded-lg hover:bg-slate-100">
                        <i class="fa-solid fa-bars text-sm"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Mobile Slide-down Menu -->
    <div id="mobileMenu" class="hidden md:hidden border-t border-slate-100 bg-white/95 backdrop-blur-xl px-4 pt-3 pb-4 space-y-2 shadow-lg">
        <a href="{{ route('dashboard') }}" class="block px-3 py-2 rounded-xl text-xs font-semibold {{ request()->routeIs('dashboard') ? 'bg-[#063B00] text-white font-bold' : 'text-slate-600 hover:bg-slate-50' }}">
            <i class="fa-solid fa-house mr-2 {{ request()->routeIs('dashboard') ? 'text-white' : 'text-slate-400' }}"></i> Dashboard
        </a>
        <a href="{{ route('games.index') }}" class="block px-3 py-2 rounded-xl text-xs font-semibold {{ request()->routeIs('games.*') ? 'bg-[#063B00] text-white font-bold' : 'text-slate-600 hover:bg-slate-50' }}">
            <i class="fa-solid fa-trophy mr-2 {{ request()->routeIs('games.*') ? 'text-white' : 'text-slate-400' }}"></i> Jadwal Mabar
        </a>
        <a href="{{ route('venues.index') }}" class="block px-3 py-2 rounded-xl text-xs font-semibold {{ request()->routeIs('venues.*') ? 'bg-[#063B00] text-white font-bold' : 'text-slate-600 hover:bg-slate-50' }}">
            <i class="fa-solid fa-location-dot mr-2 {{ request()->routeIs('venues.*') ? 'text-white' : 'text-slate-400' }}"></i> Venue & Court
        </a>
        <a href="{{ route('communities.index') }}" class="block px-3 py-2 rounded-xl text-xs font-semibold {{ request()->routeIs('communities.*') ? 'bg-[#063B00] text-white font-bold' : 'text-slate-600 hover:bg-slate-50' }}">
            <i class="fa-solid fa-users mr-2 {{ request()->routeIs('communities.*') ? 'text-white' : 'text-slate-400' }}"></i> Komunitas
        </a>
        <a href="{{ route('player.recap') }}" class="block px-3 py-2 rounded-xl text-xs font-semibold {{ request()->routeIs('player.*') ? 'bg-[#063B00] text-white font-bold' : 'text-slate-600 hover:bg-slate-50' }}">
            <i class="fa-solid fa-chart-line mr-2 {{ request()->routeIs('player.*') ? 'text-white' : 'text-slate-400' }}"></i> Match Recap
        </a>
        <div class="pt-2 border-t border-slate-100 flex gap-2">
            <a href="{{ route('login') }}" class="flex-1 text-center py-2 rounded-lg bg-slate-100 text-slate-700 text-xs font-semibold">Masuk</a>
            <a href="{{ route('register') }}" class="flex-1 text-center py-2 rounded-lg bg-[#063B00] text-white text-xs font-semibold">Daftar</a>
        </div>
    </div>
</header>

<!-- Mobile Bottom Navigation Bar (App-like experience for smartphone users) -->
<nav class="md:hidden fixed bottom-0 left-0 right-0 z-40 bg-white/90 backdrop-blur-xl border-t border-slate-200/80 px-2 py-1.5 flex items-center justify-around shadow-[0_-2px_10px_rgba(0,0,0,0.03)]">
    <a href="{{ route('dashboard') }}" class="flex flex-col items-center justify-center p-1 text-[10px] font-semibold {{ request()->routeIs('dashboard') ? 'text-[#063B00]' : 'text-slate-500' }}">
        <i class="fa-solid fa-house text-sm mb-0.5"></i>
        <span>Home</span>
    </a>
    <a href="{{ route('games.index') }}" class="flex flex-col items-center justify-center p-1 text-[10px] font-semibold {{ request()->routeIs('games.*') ? 'text-[#063B00]' : 'text-slate-500' }}">
        <i class="fa-solid fa-trophy text-sm mb-0.5"></i>
        <span>Mabar</span>
    </a>
    <a href="{{ route('games.create') }}" class="flex flex-col items-center justify-center p-1 -mt-4">
        <div class="w-11 h-11 rounded-full bg-[#063B00] text-white flex items-center justify-center shadow-md">
            <i class="fa-solid fa-plus text-base"></i>
        </div>
        <span class="text-[9px] font-bold text-slate-700 mt-0.5">Host</span>
    </a>
    <a href="{{ route('venues.index') }}" class="flex flex-col items-center justify-center p-1 text-[10px] font-semibold {{ request()->routeIs('venues.*') ? 'text-[#063B00]' : 'text-slate-500' }}">
        <i class="fa-solid fa-location-dot text-sm mb-0.5"></i>
        <span>Venue</span>
    </a>
    <a href="{{ route('player.recap') }}" class="flex flex-col items-center justify-center p-1 text-[10px] font-semibold {{ request()->routeIs('player.*') ? 'text-[#063B00]' : 'text-slate-500' }}">
        <i class="fa-solid fa-chart-line text-sm mb-0.5"></i>
        <span>Recap</span>
    </a>
</nav>

<script>
    function toggleMobileMenu() {
        const menu = document.getElementById('mobileMenu');
        menu.classList.toggle('hidden');
    }
</script>
