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
            <div class="flex items-center gap-2.5">
                
                @guest
                    <!-- Guest State: Masuk & Daftar -->
                    <a href="{{ route('login') }}" class="text-xs font-bold text-slate-700 hover:text-[#063B00] px-3.5 py-1.5 rounded-xl hover:bg-white/60 transition-colors">
                        Masuk
                    </a>
                    <a href="{{ route('register') }}" class="hidden sm:inline-flex text-xs font-bold text-[#063B00] bg-[#EBF8D8] border border-[#063B00]/25 hover:bg-[#A8E63A]/30 px-3.5 py-1.5 rounded-xl transition-all shadow-2xs">
                        Daftar
                    </a>
                    <a href="{{ route('games.create') }}" class="inline-flex items-center gap-1.5 bg-[#063B00] hover:bg-[#042a00] text-white font-bold px-3.5 py-1.5 rounded-xl text-xs transition-all shadow-xs hover:shadow-sm hover:scale-[1.02]">
                        <i class="fa-solid fa-plus text-[10px] text-[#A8E63A]"></i> <span class="hidden sm:inline">Host Game</span><span class="sm:hidden">Host</span>
                    </a>
                @endguest

                @auth
                    <!-- Role-Based Action Button -->
                    @if(Auth::user()->role === 'host')
                        <a href="{{ route('games.create') }}" class="inline-flex items-center gap-1.5 bg-[#063B00] hover:bg-[#042a00] text-white font-bold px-3.5 py-1.5 rounded-xl text-xs transition-all shadow-xs hover:shadow-sm hover:scale-[1.02]">
                            <i class="fa-solid fa-plus text-[10px] text-[#A8E63A]"></i> <span class="hidden sm:inline">Host Game</span><span class="sm:hidden">Host</span>
                        </a>
                    @elseif(Auth::user()->role === 'venue_owner')
                        <a href="{{ route('venues.create') }}" class="inline-flex items-center gap-1.5 bg-[#063B00] hover:bg-[#042a00] text-white font-bold px-3.5 py-1.5 rounded-xl text-xs transition-all shadow-xs hover:shadow-sm hover:scale-[1.02]">
                            <i class="fa-solid fa-plus text-[10px] text-[#A8E63A]"></i> <span class="hidden sm:inline">Tambah Venue</span><span class="sm:hidden">Venue</span>
                        </a>
                    @else
                        <!-- Role: member (Pemain) -->
                        <a href="{{ route('communities.create') }}" class="inline-flex items-center gap-1.5 bg-[#EBF8D8] border border-[#063B00]/25 hover:bg-[#A8E63A]/30 text-[#063B00] font-bold px-3.5 py-1.5 rounded-xl text-xs transition-all shadow-2xs hover:scale-[1.02]">
                            <i class="fa-solid fa-plus text-[10px]"></i> <span class="hidden sm:inline">Komunitas</span><span class="sm:hidden">Komunitas</span>
                        </a>
                    @endif

                    <!-- Authenticated User Dropdown -->
                    <div class="relative flex items-center pl-2 border-l border-slate-200/60">
                        <div class="flex items-center gap-2 cursor-pointer group" onclick="toggleUserDropdown()">
                            <div class="w-8 h-8 rounded-full bg-[#063B00] border-2 border-[#A8E63A]/40 flex items-center justify-center text-white text-xs font-black shadow-xs">
                                {{ strtoupper(substr(Auth::user()->nama ?? 'U', 0, 1)) }}
                            </div>
                            <div class="hidden lg:block text-left">
                                <span class="text-xs font-extrabold text-[#050608] leading-tight block truncate max-w-[110px]">
                                    {{ Auth::user()->nama }}
                                </span>
                                <span class="text-[9px] font-bold uppercase tracking-wider {{ Auth::user()->role === 'host' ? 'text-amber-700' : (Auth::user()->role === 'venue_owner' ? 'text-sky-700' : 'text-[#063B00]') }} block">
                                    {{ Auth::user()->role === 'venue_owner' ? 'Venue Owner' : (Auth::user()->role === 'host' ? 'Host Game' : 'Member') }}
                                </span>
                            </div>
                            <i class="fa-solid fa-chevron-down text-[10px] text-slate-400 group-hover:text-slate-600 transition-transform"></i>
                        </div>

                        <!-- Dropdown Menu -->
                        <div id="userDropdown" class="hidden absolute right-0 top-11 w-48 bg-white/95 backdrop-blur-2xl rounded-2xl p-2 border border-slate-200/80 shadow-xl space-y-1 text-xs z-50">
                            <div class="px-3 py-2 border-b border-slate-100">
                                <p class="font-extrabold text-slate-900 truncate">{{ Auth::user()->nama }}</p>
                                <p class="text-[10px] text-slate-400 truncate">{{ Auth::user()->email }}</p>
                            </div>
                            <a href="{{ route('player.profile') }}" class="flex items-center gap-2.5 px-3 py-2 rounded-xl text-slate-700 hover:bg-slate-50 font-semibold transition-colors">
                                <i class="fa-solid fa-id-card text-slate-400 text-xs"></i> Profil & Rating
                            </a>
                            <a href="{{ route('player.recap') }}" class="flex items-center gap-2.5 px-3 py-2 rounded-xl text-slate-700 hover:bg-slate-50 font-semibold transition-colors">
                                <i class="fa-solid fa-chart-line text-slate-400 text-xs"></i> Rekap Karir
                            </a>
                            <form id="desktopLogoutForm" action="{{ route('logout') }}" method="POST" class="pt-1 border-t border-slate-100">
                                @csrf
                                <button type="button" onclick="confirmLogout('desktopLogoutForm')" class="w-full flex items-center gap-2.5 px-3 py-2 rounded-xl text-rose-600 hover:bg-rose-50 font-bold transition-colors text-left cursor-pointer">
                                    <i class="fa-solid fa-right-from-bracket text-xs"></i> Keluar
                                </button>
                            </form>
                        </div>
                    </div>
                @endauth

                <!-- Mobile Hamburger Button -->
                <button onclick="toggleMobileMenu()" class="md:hidden p-2 text-slate-600 hover:text-[#063B00] focus:outline-none rounded-lg hover:bg-slate-100">
                    <i class="fa-solid fa-bars text-sm"></i>
                </button>
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

        @guest
            <div class="pt-2 border-t border-slate-100 flex gap-2">
                <a href="{{ route('login') }}" class="flex-1 text-center py-2.5 rounded-xl bg-slate-100 text-slate-700 text-xs font-bold">Masuk</a>
                <a href="{{ route('register') }}" class="flex-1 text-center py-2.5 rounded-xl bg-[#063B00] text-white text-xs font-bold">Daftar</a>
            </div>
        @else
            <form id="mobileLogoutForm" action="{{ route('logout') }}" method="POST" class="pt-2 border-t border-slate-100">
                @csrf
                <button type="button" onclick="confirmLogout('mobileLogoutForm')" class="w-full text-center py-2 rounded-xl bg-rose-50 text-rose-700 text-xs font-bold cursor-pointer">
                    Keluar (Logout)
                </button>
            </form>
        @endguest
    </div>
</header>

<!-- Logout Confirmation Modal -->
<div id="logoutConfirmModal" class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-sm hidden flex items-center justify-center p-4">
    <div class="bg-white/95 backdrop-blur-2xl border border-white/80 rounded-3xl p-6 sm:p-8 max-w-md w-full shadow-2xl space-y-5 animate-in fade-in zoom-in duration-200">
        <div class="w-14 h-14 rounded-3xl bg-rose-100 border border-rose-200 text-rose-600 flex items-center justify-center text-2xl mx-auto shadow-xs">
            <i class="fa-solid fa-right-from-bracket"></i>
        </div>
        <div class="text-center space-y-1.5">
            <h3 class="text-base font-black text-slate-900">Konfirmasi Keluar Akun</h3>
            <p class="text-xs text-slate-500 leading-relaxed">
                Apakah Anda yakin ingin keluar dari akun? Anda perlu masuk kembali untuk mengakses sesi mabar dan profil Anda.
            </p>
        </div>
        <div class="flex gap-2.5 pt-2">
            <button type="button" onclick="closeLogoutModal()" class="flex-1 py-3 rounded-2xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs transition-all">
                Batal
            </button>
            <button type="button" onclick="submitActiveLogoutForm()" class="flex-1 py-3 rounded-2xl bg-rose-600 hover:bg-rose-700 text-white font-black text-xs shadow-md transition-all">
                Ya, Keluar Akun
            </button>
        </div>
    </div>
</div>

<script>
    let activeLogoutFormId = 'desktopLogoutForm';

    function toggleMobileMenu() {
        const menu = document.getElementById('mobileMenu');
        menu.classList.toggle('hidden');
    }

    function toggleUserDropdown() {
        const dropdown = document.getElementById('userDropdown');
        if (dropdown) {
            dropdown.classList.toggle('hidden');
        }
    }

    function confirmLogout(formId) {
        activeLogoutFormId = formId;
        const modal = document.getElementById('logoutConfirmModal');
        if (modal) {
            modal.classList.remove('hidden');
        }
    }

    function closeLogoutModal() {
        const modal = document.getElementById('logoutConfirmModal');
        if (modal) {
            modal.classList.add('hidden');
        }
    }

    function submitActiveLogoutForm() {
        const form = document.getElementById(activeLogoutFormId);
        if (form) {
            form.submit();
        }
    }

    document.addEventListener('click', function(e) {
        const dropdown = document.getElementById('userDropdown');
        if (dropdown && !e.target.closest('.relative.flex.items-center')) {
            dropdown.classList.add('hidden');
        }
    });
</script>
