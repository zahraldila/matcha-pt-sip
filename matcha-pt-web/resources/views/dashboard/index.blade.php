@extends('layouts.app')

@section('content')
<div class="space-y-12 pb-16">
    
    <!-- Hero Section: Clean Frosted Glass Sport Hub -->
    <div class="py-12 sm:py-16">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">
            
            <div class="max-w-3xl space-y-3">
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full glass-subtle text-emerald-900 text-xs font-semibold shadow-xs">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-600"></span>
                    Tennis & Padel Community Platform
                </div>
                <h1 class="text-3xl sm:text-5xl font-extrabold text-slate-900 tracking-tight leading-tight">
                    Main bareng, drawing tim seimbang, dan catat skor pertandingan.
                </h1>
                <p class="text-slate-600 text-sm sm:text-base leading-relaxed max-w-2xl font-normal">
                    Temukan jadwal mabar tenis dan padel di sekitarmu, pembagian tim otomatis dengan drawing seimbang, dan simpan riwayat pertandinganmu.
                </p>
            </div>

            <!-- Clean Frosted Glass Search & Filter Card -->
            <div class="glass-card rounded-3xl p-5 sm:p-6 shadow-sm max-w-5xl border border-white/80">
                <!-- Tabs: Mabar / Venue / Komunitas -->
                <div class="flex items-center gap-2 pb-4 mb-4 border-b border-slate-200/50 text-xs font-semibold">
                    <button class="px-4 py-2 rounded-xl bg-[#163820] text-white shadow-xs flex items-center gap-2">
                        <i class="fa-solid fa-users text-xs text-lime-400"></i> Jadwal Mabar
                    </button>
                    <a href="{{ route('venues.index') }}" class="px-4 py-2 rounded-xl bg-white/80 text-slate-600 hover:text-slate-900 hover:bg-white border border-slate-200/60 flex items-center gap-2 transition-colors shadow-xs">
                        <i class="fa-solid fa-location-dot text-xs text-slate-400"></i> Sewa Lapangan
                    </a>
                    <a href="{{ route('communities.index') }}" class="px-4 py-2 rounded-xl bg-white/80 text-slate-600 hover:text-slate-900 hover:bg-white border border-slate-200/60 flex items-center gap-2 transition-colors shadow-xs">
                        <i class="fa-solid fa-shield-halved text-xs text-slate-400"></i> Komunitas
                    </a>
                </div>

                <!-- Search Input Row -->
                <form action="{{ route('games.index') }}" method="GET" class="grid grid-cols-1 sm:grid-cols-4 gap-3 text-xs">
                    <div>
                        <label class="block font-medium text-slate-600 mb-1">Cabang Olahraga</label>
                        <select name="sport" class="w-full bg-white/90 border border-slate-200/70 rounded-xl px-3.5 py-2.5 text-slate-800 font-medium focus:border-emerald-600 focus:outline-none shadow-xs">
                            <option value="all">Semua Cabang (All)</option>
                            <option value="tennis">🎾 Tennis</option>
                            <option value="padel">🏓 Padel</option>
                        </select>
                    </div>

                    <div>
                        <label class="block font-medium text-slate-600 mb-1">Kota / Lokasi</label>
                        <select class="w-full bg-white/90 border border-slate-200/70 rounded-xl px-3.5 py-2.5 text-slate-800 font-medium focus:border-emerald-600 focus:outline-none shadow-xs">
                            <option value="all">Semua Kota</option>
                            <option value="jakarta">Jakarta Pusat / Selatan</option>
                            <option value="bandung">Bandung</option>
                        </select>
                    </div>

                    <div>
                        <label class="block font-medium text-slate-600 mb-1">Pilih Tanggal</label>
                        <input type="date" value="2026-09-12" class="w-full bg-white/90 border border-slate-200/70 rounded-xl px-3.5 py-2.5 text-slate-800 font-medium focus:border-emerald-600 focus:outline-none shadow-xs">
                    </div>

                    <div class="flex items-end">
                        <button type="submit" class="w-full py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-xs shadow-sm transition-all hover:scale-[1.01] flex items-center justify-center gap-2">
                            <i class="fa-solid fa-magnifying-glass"></i> Cari Jadwal Mabar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Main Container -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-12">
        
        <!-- Category Pill Switcher -->
        <div class="flex items-center gap-2.5 overflow-x-auto pb-1">
            <a href="{{ route('dashboard', ['sport' => 'all']) }}" class="flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-semibold transition-all whitespace-nowrap {{ ($selectedSport ?? 'all') === 'all' ? 'bg-[#163820] text-white shadow-xs' : 'glass-card text-slate-600 hover:text-slate-900' }}">
                <i class="fa-solid fa-layer-group text-[11px]"></i> Semua Cabang
            </a>
            <a href="{{ route('dashboard', ['sport' => 'tennis']) }}" class="flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-semibold transition-all whitespace-nowrap {{ ($selectedSport ?? '') === 'tennis' ? 'bg-[#163820] text-white shadow-xs' : 'glass-card text-slate-600 hover:text-slate-900' }}">
                <span>🎾</span> Tennis Lapangan
            </a>
            <a href="{{ route('dashboard', ['sport' => 'padel']) }}" class="flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-semibold transition-all whitespace-nowrap {{ ($selectedSport ?? '') === 'padel' ? 'bg-[#163820] text-white shadow-xs' : 'glass-card text-slate-600 hover:text-slate-900' }}">
                <span>🏓</span> Padel Arena
            </a>
        </div>

        <!-- Mabar List Grid -->
        <div class="space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-slate-200/50 pb-3">
                <div>
                    <h2 class="text-lg font-bold text-slate-900">
                        Jadwal Mabar Terbuka
                    </h2>
                    <p class="text-xs text-slate-500">Pilih sesi permainan dan gabung ke kuota pemain</p>
                </div>
                <a href="{{ route('games.index') }}" class="text-xs font-semibold text-emerald-700 hover:underline">
                    Semua Jadwal &rarr;
                </a>
            </div>

            <!-- Games Grid with Glass Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                @forelse($games as $game)
                    <x-game-card :game="$game" />
                @empty
                    <div class="col-span-full py-12 text-center text-slate-400 glass-card rounded-2xl">
                        <i class="fa-solid fa-calendar-xmark text-3xl mb-2 text-slate-300"></i>
                        <p class="text-xs font-medium">Tidak ada jadwal mabar aktif saat ini.</p>
                    </div>
                @endforelse
            </div>
        </div>

        <!-- Two Columns: Featured Venues & Player Performance Recap -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 pt-2">
            
            <!-- Left: Featured Venues (2 Cols) -->
            <div class="lg:col-span-2 space-y-4">
                <div class="flex items-center justify-between border-b border-slate-200/50 pb-3">
                    <h3 class="text-base font-bold text-slate-900">
                        Venue & Lapangan Rekomendasi
                    </h3>
                    <a href="{{ route('venues.index') }}" class="text-xs text-emerald-700 hover:underline font-semibold">Semua Venue &rarr;</a>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    @foreach($venues as $venue)
                        <div class="glass-card rounded-2xl overflow-hidden group flex flex-col justify-between">
                            <div>
                                <div class="relative h-40 overflow-hidden">
                                    <img src="{{ $venue['image'] }}" alt="{{ $venue['name'] }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                                    <div class="absolute top-3 left-3">
                                        <x-badge :type="strtolower($venue['sport']) === 'tennis' ? 'tennis' : 'padel'">
                                            {{ $venue['sport'] }}
                                        </x-badge>
                                    </div>
                                </div>
                                <div class="p-4 space-y-2 text-xs">
                                    <h4 class="font-bold text-slate-900 text-sm truncate">{{ $venue['name'] }}</h4>
                                    <p class="text-slate-500 text-xs truncate flex items-center gap-1">
                                        <i class="fa-solid fa-location-dot text-slate-400 text-[10px]"></i> {{ $venue['city'] }}
                                    </p>
                                    
                                    <div class="pt-2 flex justify-between items-center text-slate-500 border-t border-slate-100 text-[11px]">
                                        <span>{{ $venue['operating_hours'] }}</span>
                                        <span class="font-semibold text-emerald-700">{{ count($venue['courts']) }} Court</span>
                                    </div>
                                </div>
                            </div>
                            <div class="p-4 pt-0">
                                <a href="{{ route('venues.show', $venue['id']) }}" class="block text-center py-2 rounded-xl bg-white/80 hover:bg-white text-slate-800 font-semibold text-xs border border-slate-200/60 shadow-xs transition-colors">
                                    Cek Jadwal Court
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- Right: Clean Frosted Glass Player Performance Card (1 Col) -->
            <div class="space-y-4">
                <div class="flex items-center justify-between border-b border-slate-200/50 pb-3">
                    <h3 class="text-base font-bold text-slate-900">
                        Performa Pemain
                    </h3>
                    <a href="{{ route('player.recap') }}" class="text-xs text-emerald-700 hover:underline font-semibold">Statistik &rarr;</a>
                </div>

                <div class="glass-card rounded-2xl p-5 space-y-4">
                    <div class="flex items-center gap-3.5">
                        <img src="{{ $playerRecap['player']['avatar'] }}" alt="Profile" class="w-11 h-11 rounded-full object-cover ring-2 ring-emerald-600/60 shadow-xs">
                        <div>
                            <h4 class="text-sm font-bold text-slate-900">{{ $playerRecap['player']['name'] }}</h4>
                            <p class="text-xs text-slate-500">{{ $playerRecap['player']['level'] }} &bull; {{ $playerRecap['player']['community'] }}</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-2 bg-white/60 p-3 rounded-xl border border-slate-200/50 text-center text-xs">
                        <div>
                            <span class="text-[10px] text-slate-400 block font-medium">Main</span>
                            <strong class="text-base font-bold text-slate-900">{{ $playerRecap['player']['total_matches'] }}x</strong>
                        </div>
                        <div>
                            <span class="text-[10px] text-slate-400 block font-medium">Menang</span>
                            <strong class="text-base font-bold text-emerald-700">{{ $playerRecap['player']['wins'] }}W</strong>
                        </div>
                        <div>
                            <span class="text-[10px] text-slate-400 block font-medium">Win Rate</span>
                            <strong class="text-base font-bold text-slate-800">{{ $playerRecap['player']['win_rate'] }}</strong>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <span class="text-xs font-semibold text-slate-700 block">Match Terakhir:</span>
                        @foreach(array_slice($playerRecap['recent_matches'], 0, 2) as $m)
                            <div class="p-2.5 rounded-xl bg-white/60 border border-slate-200/50 flex items-center justify-between text-xs">
                                <div>
                                    <span class="font-bold text-slate-800">{{ $m['sport'] }}</span>
                                    <p class="text-[10px] text-slate-500">{{ $m['venue'] }} &bull; Skor: <strong class="text-slate-700">{{ $m['score'] }}</strong></p>
                                </div>
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold {{ $m['result'] === 'WIN' ? 'bg-emerald-50 text-emerald-800 border border-emerald-100' : 'bg-rose-50 text-rose-800 border border-rose-100' }}">
                                    {{ $m['result'] }}
                                </span>
                            </div>
                        @endforeach
                    </div>

                    <a href="{{ route('player.recap') }}" class="block text-center py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-semibold text-xs shadow-xs transition-colors">
                        Buka Rekap Match Lengkap
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Quick Join -->
<div id="joinModal" class="fixed inset-0 z-50 bg-slate-900/30 backdrop-blur-xs hidden items-center justify-center p-4">
    <div class="glass-card !bg-white/95 max-w-md w-full rounded-3xl p-6 border border-white space-y-4 shadow-2xl">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <h3 class="text-sm font-bold text-slate-900 flex items-center gap-2">
                <i class="fa-solid fa-user-plus text-emerald-600"></i> Gabung Sesi Mabar
            </h3>
            <button onclick="closeJoinModal()" class="text-slate-400 hover:text-slate-600 text-lg leading-none">&times;</button>
        </div>

        <p class="text-xs text-slate-600">
            Sesi mabar yang dipilih: <strong id="modalGameTitle" class="text-slate-900"></strong>
        </p>

        <form onsubmit="handleJoinSubmit(event)" class="space-y-3 text-xs">
            <div>
                <label class="block text-slate-700 font-semibold mb-1">Nama Pemain</label>
                <input type="text" id="joinPlayerName" value="Billy Santoso" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-800 focus:border-emerald-600 focus:bg-white focus:outline-none" required>
            </div>
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="block text-slate-700 font-semibold mb-1">Gender</label>
                    <select class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-800 focus:border-emerald-600 focus:bg-white focus:outline-none">
                        <option value="Male">Laki-laki</option>
                        <option value="Female">Perempuan</option>
                    </select>
                </div>
                <div>
                    <label class="block text-slate-700 font-semibold mb-1">Level Permainan</label>
                    <select class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-800 focus:border-emerald-600 focus:bg-white focus:outline-none">
                        <option value="Newbie">Newbie</option>
                        <option value="Beginner">Beginner</option>
                        <option value="Intermediate" selected>Intermediate</option>
                        <option value="Advanced">Advanced</option>
                    </select>
                </div>
            </div>
            <div>
                <label class="block text-slate-700 font-semibold mb-1">Nomor WhatsApp</label>
                <input type="text" value="0812-9988-7766" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-slate-800 focus:border-emerald-600 focus:bg-white focus:outline-none" required>
            </div>

            <div class="flex gap-2 pt-3 border-t border-slate-100">
                <button type="button" onclick="closeJoinModal()" class="flex-1 py-2.5 rounded-xl bg-slate-100 text-slate-700 font-semibold hover:bg-slate-200 transition-colors">
                    Batal
                </button>
                <button type="submit" class="flex-1 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-semibold shadow-xs transition-colors">
                    Konfirmasi Gabung
                </button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
    function showJoinModal(gameId, gameTitle) {
        document.getElementById('modalGameTitle').innerText = gameTitle;
        document.getElementById('joinModal').classList.remove('hidden');
        document.getElementById('joinModal').classList.add('flex');
    }

    function closeJoinModal() {
        document.getElementById('joinModal').classList.add('hidden');
        document.getElementById('joinModal').classList.remove('flex');
    }

    function handleJoinSubmit(e) {
        e.preventDefault();
        closeJoinModal();
        showToast('Berhasil bergabung ke sesi mabar!');
    }
</script>
@endpush
@endsection
