@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-200/60 pb-4">
        <div>
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-[#A8E63A]/20 border border-[#063B00]/25 text-[#050608] text-xs font-semibold shadow-xs mb-2">
                <span class="w-1.5 h-1.5 rounded-full bg-[#063B00]"></span>
                Jadwal & Komunitas Mabar
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-[#050608] tracking-tight">
                Jadwal Mabar & Turnamen
            </h1>
            <p class="text-xs sm:text-sm text-slate-500 mt-0.5">Temukan sesi mabar aktif, pantau sesi yang kamu ikuti, atau kelola jadwal turnamenmu.</p>
        </div>

        @if(Auth::check() && Auth::user()->role === 'host')
            <a href="{{ route('games.schedule') }}" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-[#063B00] hover:bg-[#042a00] text-white font-bold text-xs shadow-md transition-all hover:scale-[1.01] shrink-0">
                <i class="fa-solid fa-plus text-[#A8E63A] text-xs"></i> <span>Buat Sesi Mabar Baru</span>
            </a>
        @endif
    </div>

    <!-- 1. Primary Filter Tabs (Semua Sesi / Sesi di Venue Saya / Mabar Saya / Dikelola Saya) -->
    <div class="flex items-center gap-2 overflow-x-auto pb-1 border-b border-slate-200/50 scrollbar-none text-xs font-semibold">
        <!-- Tab 1: Semua Sesi (Eksplorasi) -->
        <a href="{{ route('games.index', ['tab' => 'all', 'sport' => $selectedSport ?? 'all']) }}" 
           class="px-4 py-2.5 rounded-xl transition-all flex items-center gap-2 whitespace-nowrap {{ ($activeTab ?? 'all') === 'all' ? 'bg-[#063B00] text-white shadow-xs font-bold' : 'glass-card text-slate-600 hover:text-[#050608] hover:bg-white' }}">
            <i class="fa-solid fa-earth-americas text-xs {{ ($activeTab ?? 'all') === 'all' ? 'text-[#A8E63A]' : 'text-slate-400' }}"></i>
            <span>Semua Sesi</span>
            <span class="px-2 py-0.5 rounded-full text-[10px] font-black {{ ($activeTab ?? 'all') === 'all' ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-600' }}">
                {{ $countAll ?? count($games) }}
            </span>
        </a>

        @auth
            <!-- Tab Khusus Venue Owner: Sesi di Venue Saya -->
            @if(Auth::user()->role === 'venue_owner' || count($ownedVenueIds ?? []) > 0 || ($countVenue ?? 0) > 0)
                <a href="{{ route('games.index', ['tab' => 'venue', 'sport' => $selectedSport ?? 'all']) }}" 
                   class="px-4 py-2.5 rounded-xl transition-all flex items-center gap-2 whitespace-nowrap {{ ($activeTab ?? '') === 'venue' ? 'bg-[#063B00] text-white shadow-xs font-bold' : 'glass-card text-slate-600 hover:text-[#050608] hover:bg-white' }}">
                    <i class="fa-solid fa-location-dot text-xs {{ ($activeTab ?? '') === 'venue' ? 'text-[#A8E63A]' : 'text-sky-500' }}"></i>
                    <span>Sesi di Venue Saya</span>
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-black {{ ($activeTab ?? '') === 'venue' ? 'bg-sky-400 text-sky-950' : 'bg-sky-50 text-sky-800 border border-sky-200' }}">
                        {{ $countVenue ?? 0 }}
                    </span>
                </a>
            @endif

            <!-- Tab 2: Mabar yang Saya Ikuti (Player Scope) -->
            <a href="{{ route('games.index', ['tab' => 'joined', 'sport' => $selectedSport ?? 'all']) }}" 
               class="px-4 py-2.5 rounded-xl transition-all flex items-center gap-2 whitespace-nowrap {{ ($activeTab ?? '') === 'joined' ? 'bg-[#063B00] text-white shadow-xs font-bold' : 'glass-card text-slate-600 hover:text-[#050608] hover:bg-white' }}">
                <i class="fa-solid fa-circle-check text-xs {{ ($activeTab ?? '') === 'joined' ? 'text-[#A8E63A]' : 'text-emerald-500' }}"></i>
                <span>Mabar Saya / Diikuti</span>
                <span class="px-2 py-0.5 rounded-full text-[10px] font-black {{ ($activeTab ?? '') === 'joined' ? 'bg-[#A8E63A] text-[#063B00]' : 'bg-emerald-50 text-emerald-800 border border-emerald-200' }}">
                    {{ $countJoined ?? 0 }}
                </span>
            </a>

            <!-- Tab 3: Dikelola Saya (Host Scope) -->
            @if(Auth::user()->role === 'host' || ($countHosted ?? 0) > 0)
                <a href="{{ route('games.index', ['tab' => 'hosted', 'sport' => $selectedSport ?? 'all']) }}" 
                   class="px-4 py-2.5 rounded-xl transition-all flex items-center gap-2 whitespace-nowrap {{ ($activeTab ?? '') === 'hosted' ? 'bg-[#063B00] text-white shadow-xs font-bold' : 'glass-card text-slate-600 hover:text-[#050608] hover:bg-white' }}">
                    <i class="fa-solid fa-crown text-xs {{ ($activeTab ?? '') === 'hosted' ? 'text-[#A8E63A]' : 'text-amber-500' }}"></i>
                    <span>Dikelola Saya (Host)</span>
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-black {{ ($activeTab ?? '') === 'hosted' ? 'bg-amber-400 text-amber-950' : 'bg-amber-50 text-amber-800 border border-amber-200' }}">
                        {{ $countHosted ?? 0 }}
                    </span>
                </a>
            @endif
        @endauth
    </div>

    <!-- 2. Secondary Sub-Filters Bar (Sport Category & Counter) -->
    <div class="glass-card p-3 sm:p-3.5 rounded-2xl flex flex-wrap items-center justify-between gap-3 border border-white/90 shadow-2xs">
        <div class="flex items-center gap-2 overflow-x-auto scrollbar-none py-0.5">
            <a href="{{ route('games.index', ['tab' => $activeTab ?? 'all', 'sport' => 'all']) }}" 
               class="px-3.5 py-1.5 rounded-xl text-xs font-semibold transition-all whitespace-nowrap {{ ($selectedSport ?? 'all') === 'all' ? 'bg-[#063B00] text-white shadow-xs font-bold' : 'glass-card text-slate-600 hover:text-[#050608]' }}">
                Semua Cabang
            </a>
            <a href="{{ route('games.index', ['tab' => $activeTab ?? 'all', 'sport' => 'tennis']) }}" 
               class="px-3.5 py-1.5 rounded-xl text-xs font-semibold transition-all whitespace-nowrap {{ ($selectedSport ?? '') === 'tennis' ? 'bg-[#063B00] text-white shadow-xs font-bold' : 'glass-card text-slate-600 hover:text-[#050608]' }}">
                🎾 Tennis
            </a>
            <a href="{{ route('games.index', ['tab' => $activeTab ?? 'all', 'sport' => 'padel']) }}" 
               class="px-3.5 py-1.5 rounded-xl text-xs font-semibold transition-all whitespace-nowrap {{ ($selectedSport ?? '') === 'padel' ? 'bg-[#063B00] text-white shadow-xs font-bold' : 'glass-card text-slate-600 hover:text-[#050608]' }}">
                🏓 Padel
            </a>
        </div>

        <div class="text-xs text-slate-500">
            Menampilkan <strong class="text-[#050608]">{{ count($games) }}</strong> sesi
            @if(($activeTab ?? 'all') === 'joined')
                <span>yang kamu ikuti</span>
            @elseif(($activeTab ?? 'all') === 'hosted')
                <span>yang kamu kelola</span>
            @elseif(($activeTab ?? 'all') === 'venue')
                <span>di venue milikmu</span>
            @endif
        </div>
    </div>

    <!-- 3. Grid of Games or Empty States -->
    @if(count($games) > 0)
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($games as $game)
                <x-game-card :game="$game" />
            @endforeach
        </div>
    @else
        <!-- Rich Clean Empty State -->
        <div class="glass-card rounded-3xl p-8 sm:p-12 text-center max-w-xl mx-auto border border-white/90 space-y-4 shadow-sm">
            <div class="w-16 h-16 rounded-3xl bg-[#EBF8D8] border border-[#063B00]/20 flex items-center justify-center text-[#063B00] text-2xl mx-auto shadow-xs">
                @if(($activeTab ?? 'all') === 'joined')
                    <i class="fa-solid fa-calendar-xmark"></i>
                @elseif(($activeTab ?? 'all') === 'hosted')
                    <i class="fa-solid fa-crown text-amber-600"></i>
                @elseif(($activeTab ?? 'all') === 'venue')
                    <i class="fa-solid fa-location-dot text-sky-600"></i>
                @else
                    <i class="fa-solid fa-magnifying-glass"></i>
                @endif
            </div>

            <div class="space-y-1.5">
                <h3 class="text-base sm:text-lg font-bold text-slate-900">
                    @if(($activeTab ?? 'all') === 'joined')
                        Belum Ada Sesi yang Kamu Ikuti
                    @elseif(($activeTab ?? 'all') === 'hosted')
                        Belum Ada Sesi yang Kamu Kelola
                    @elseif(($activeTab ?? 'all') === 'venue')
                        Belum Ada Sesi di Venue Milikmu
                    @else
                        Tidak Ada Sesi Mabar Ditemukan
                    @endif
                </h3>
                <p class="text-xs sm:text-sm text-slate-500 max-w-sm mx-auto leading-relaxed">
                    @if(($activeTab ?? 'all') === 'joined')
                        Kamu belum terdaftar di jadwal mabar manapun. Jelajahi sesi terbuka dan gabung slot sekarang!
                    @elseif(($activeTab ?? 'all') === 'hosted')
                        Kamu belum membuat sesi mabar. Buat sesi mabar barumu untuk mengundang teman dan komunitas!
                    @elseif(($activeTab ?? 'all') === 'venue')
                        Belum ada sesi mabar komunitas yang dijadwalkan di venue milikmu saat ini.
                    @else
                        Tidak ada sesi pertandingan yang sesuai dengan kategori atau filter yang dipilih.
                    @endif
                </p>
            </div>

            <div class="pt-2">
                @if(($activeTab ?? 'all') === 'joined')
                    <a href="{{ route('games.index', ['tab' => 'all']) }}" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-[#063B00] hover:bg-[#042a00] text-white font-bold text-xs shadow-md transition-all hover:scale-[1.01]">
                        <i class="fa-solid fa-earth-americas text-[#A8E63A]"></i> <span>Jelajahi Semua Sesi</span>
                    </a>
                @elseif(($activeTab ?? 'all') === 'hosted')
                    <a href="{{ route('games.schedule') }}" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-[#063B00] hover:bg-[#042a00] text-white font-bold text-xs shadow-md transition-all hover:scale-[1.01]">
                        <i class="fa-solid fa-plus text-[#A8E63A]"></i> <span>Buat Sesi Mabar</span>
                    </a>
                @elseif(($activeTab ?? 'all') === 'venue')
                    <a href="{{ route('venues.index') }}" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-[#063B00] hover:bg-[#042a00] text-white font-bold text-xs shadow-md transition-all hover:scale-[1.01]">
                        <i class="fa-solid fa-building text-[#A8E63A]"></i> <span>Kelola Venue & Lapangan</span>
                    </a>
                @else
                    <a href="{{ route('games.index', ['tab' => 'all', 'sport' => 'all']) }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-white hover:bg-slate-50 text-slate-700 font-semibold text-xs border border-slate-200 shadow-xs transition-all">
                        <i class="fa-solid fa-rotate-left text-slate-400"></i> <span>Reset Filter</span>
                    </a>
                @endif
            </div>
        </div>
    @endif
</div>

<x-join-modal />
@endsection
