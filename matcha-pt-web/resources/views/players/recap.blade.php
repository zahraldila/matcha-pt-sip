@extends('layouts.app')

@section('content')
<div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
    
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-200/50 pb-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">
                Match Recap &amp; Statistik
            </h1>
            <p class="text-xs text-slate-500 mt-0.5">
                @if($isHost && $activeTab === 'host')
                    Riwayat penyelenggaraan sesi mabar dan turnamen yang kamu pimpin
                @else
                    Ringkasan performa karier bertanding dan statistik kemenangan pemain
                @endif
            </p>
        </div>

        <div class="flex items-center gap-2.5">
            <a href="{{ route('player.profile') }}" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-white hover:bg-slate-50 text-slate-700 font-semibold text-xs border border-slate-200 shadow-2xs transition-all hover:border-[#063B00]">
                <i class="fa-solid fa-id-card text-slate-400 text-[11px]"></i> <span>Profil Saya</span>
            </a>
            @if($isHost && $activeTab === 'host')
                <a href="{{ route('games.create') }}" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-[#063B00] hover:bg-[#042a00] text-white font-semibold text-xs shadow-xs transition-all hover:scale-[1.01] cursor-pointer">
                    <i class="fa-solid fa-plus text-[10px] text-[#A8E63A]"></i> Buat Sesi Mabar Baru
                </a>
            @else
                <button onclick="openShareModal()" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-[#063B00] hover:bg-[#042a00] text-white font-semibold text-xs shadow-xs transition-all hover:scale-[1.01] cursor-pointer">
                    <i class="fa-solid fa-arrow-up-from-bracket text-[10px] text-[#A8E63A]"></i> Bagikan Rekap
                </button>
            @endif
        </div>
    </div>

    {{-- Dual-Mode Tab Switcher (Hanya tampil jika user adalah Host) --}}
    @if($isHost)
    <div class="flex items-center gap-2 border-b border-slate-200/60 pb-3">
        <a href="{{ route('player.recap', ['tab' => 'host']) }}" 
           class="px-4 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-2 {{ $activeTab === 'host' ? 'bg-[#063B00] text-white shadow-xs' : 'glass-card text-slate-600 hover:text-slate-900' }}">
            <i class="fa-solid fa-crown text-[11px] {{ $activeTab === 'host' ? 'text-[#A8E63A]' : 'text-amber-500' }}"></i>
            <span>Riwayat Sesi Mabar (Host)</span>
            <span class="text-[10px] px-2 py-0.2 rounded-full {{ $activeTab === 'host' ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-600' }}">
                {{ $hostStats['total_sessions'] }}
            </span>
        </a>

        <a href="{{ route('player.recap', ['tab' => 'career']) }}" 
           class="px-4 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-2 {{ $activeTab === 'career' ? 'bg-[#063B00] text-white shadow-xs' : 'glass-card text-slate-600 hover:text-slate-900' }}">
            <i class="fa-solid fa-chart-line text-[11px] {{ $activeTab === 'career' ? 'text-[#A8E63A]' : 'text-emerald-600' }}"></i>
            <span>Rekap Karir Pemain ({{ $user->nama ?? 'Pemain' }})</span>
        </a>
    </div>
    @endif

    {{-- ========================================================================= --}}
    {{-- TAB 1: RIWAYAT SESI MABAR (HOST VIEW) --}}
    {{-- ========================================================================= --}}
    @if($isHost && $activeTab === 'host')
        
        <!-- Host Overall Metrics -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <div class="glass-card p-4 rounded-2xl border border-white/80 text-center space-y-1 shadow-2xs">
                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Total Sesi Di-Host</span>
                <p class="text-2xl font-extrabold text-[#063B00]">{{ $hostStats['total_sessions'] }}</p>
                <span class="text-[10px] text-slate-400">Sesi Mabar</span>
            </div>

            <div class="glass-card p-4 rounded-2xl border border-white/80 text-center space-y-1 shadow-2xs">
                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Pemain Terlayani</span>
                <p class="text-2xl font-extrabold text-slate-800">{{ $hostStats['total_players'] }}</p>
                <span class="text-[10px] text-slate-400">Total Peserta</span>
            </div>

            <div class="glass-card p-4 rounded-2xl border border-white/80 text-center space-y-1 shadow-2xs">
                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Sesi Siap / Selesai</span>
                <p class="text-2xl font-extrabold text-emerald-700">{{ $hostStats['completed_sessions'] }}</p>
                <span class="text-[10px] text-slate-400">Match Selesai</span>
            </div>

            <div class="glass-card p-4 rounded-2xl border border-white/80 text-center space-y-1 shadow-2xs">
                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Venue Favorit</span>
                <p class="text-sm font-extrabold text-slate-900 truncate px-1 mt-1">{{ $hostStats['favorite_venue'] }}</p>
                <span class="text-[10px] text-slate-400">Sering Digunakan</span>
            </div>
        </div>

        <!-- List of Hosted Sessions -->
        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-bold text-slate-900 uppercase tracking-wider flex items-center gap-2">
                    <i class="fa-solid fa-list-check text-[#063B00]"></i> Daftar Sesi yang Kamu Selenggarakan
                </h2>
                <span class="text-xs text-slate-500 font-semibold">{{ $hostSessions->count() }} Sesi Tercatat</span>
            </div>

            @forelse($hostSessions as $session)
                <div class="glass-card rounded-3xl p-5 sm:p-6 border border-white/90 space-y-4 shadow-2xs hover:shadow-xs transition-shadow">
                    
                    <!-- Card Top Header -->
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-200/50 pb-3">
                        <div class="space-y-1">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="bg-[#063B00] text-white font-bold text-[10px] px-2.5 py-0.5 rounded-full uppercase tracking-wider">
                                    {{ $session['sport'] }}
                                </span>
                                <span class="text-xs font-extrabold text-slate-900">{{ $session['title'] }}</span>
                            </div>
                            <p class="text-xs text-slate-500 flex items-center gap-1.5 flex-wrap">
                                <span><i class="fa-solid fa-location-dot text-rose-500 text-[10px]"></i> {{ $session['venue'] }} ({{ $session['court'] }})</span>
                                <span>&bull;</span>
                                <span><i class="fa-regular fa-calendar text-slate-400 text-[10px]"></i> {{ $session['date'] }}, {{ $session['time'] }}</span>
                            </p>
                        </div>

                        <!-- Status Badge -->
                        <div>
                            @php
                                $statusLower = strtolower($session['status']);
                                $badgeClass = match(true) {
                                    str_contains($statusLower, 'ready') => 'bg-emerald-50 text-emerald-800 border-emerald-200',
                                    str_contains($statusLower, 'in progress') || str_contains($statusLower, 'live') => 'bg-rose-50 text-rose-800 border-rose-200',
                                    str_contains($statusLower, 'completed') || str_contains($statusLower, 'finished') => 'bg-slate-100 text-slate-700 border-slate-200',
                                    default => 'bg-sky-50 text-sky-800 border-sky-200',
                                };
                            @endphp
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold border {{ $badgeClass }}">
                                <span class="w-1.5 h-1.5 rounded-full {{ str_contains($statusLower, 'in progress') ? 'bg-rose-500 animate-pulse' : 'bg-current' }}"></span>
                                {{ $session['status'] }}
                            </span>
                        </div>
                    </div>

                    <!-- Players Preview & Quick Summary -->
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div>
                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-1.5">
                                Pemain Terdaftar ({{ $session['joined_count'] }} / {{ $session['quota'] }})
                            </span>
                            <div class="flex items-center gap-1.5 flex-wrap">
                                @forelse($session['players'] as $p)
                                    <span class="inline-flex items-center gap-1 text-[11px] font-semibold bg-white px-2.5 py-1 rounded-xl border border-slate-200/70 text-slate-700 shadow-2xs">
                                        <i class="{{ in_array(strtolower($p['gender'] ?? 'male'), ['female', 'perempuan', 'wanita']) ? 'fa-solid fa-person-dress text-rose-500' : 'fa-solid fa-person text-sky-600' }} text-[10px]"></i>
                                        {{ $p['name'] }}
                                    </span>
                                @empty
                                    <span class="text-xs text-slate-400 italic">Belum ada pemain yang bergabung</span>
                                @endforelse
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="flex items-center gap-2 flex-wrap sm:shrink-0 pt-2 sm:pt-0">
                            <a href="{{ route('scoring.recap', $session['id']) }}" class="px-3.5 py-2 rounded-xl bg-amber-50 hover:bg-amber-100 text-amber-900 border border-amber-200/80 font-bold text-xs shadow-2xs transition-all flex items-center gap-1.5">
                                <i class="fa-solid fa-ranking-star text-[10px] text-amber-600"></i> Rekap Juara
                            </a>

                            <a href="{{ route('games.drawing', $session['id']) }}" class="px-3.5 py-2 rounded-xl bg-white hover:bg-slate-50 border border-slate-200 text-slate-800 font-bold text-xs shadow-2xs transition-all flex items-center gap-1.5">
                                <i class="fa-solid fa-shuffle text-[10px] text-slate-500"></i> Drawing
                            </a>

                            <a href="{{ route('scoring.live', $session['id']) }}" class="px-3.5 py-2 rounded-xl bg-[#063B00] hover:bg-[#042a00] text-white font-bold text-xs shadow-xs transition-all flex items-center gap-1.5">
                                <i class="fa-solid fa-circle-play text-[10px] text-[#A8E63A]"></i> Scoring Live
                            </a>
                        </div>
                    </div>
                </div>
            @empty
                <div class="glass-card rounded-3xl p-10 text-center space-y-3 border border-white/80">
                    <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-[#063B00] flex items-center justify-center text-xl mx-auto shadow-2xs">
                        <i class="fa-solid fa-trophy"></i>
                    </div>
                    <h3 class="text-base font-bold text-slate-800">Belum Ada Sesi yang Dibuat</h3>
                    <p class="text-xs text-slate-500 max-w-md mx-auto">
                        Kamu belum menyelenggarakan sesi mabar. Buat sesi mabar pertama kamu sekarang untuk mengundang pemain dan memulai drawing!
                    </p>
                    <div class="pt-2">
                        <a href="{{ route('games.create') }}" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-[#063B00] hover:bg-[#042a00] text-white font-bold text-xs shadow-md transition-all">
                            <i class="fa-solid fa-plus text-[#A8E63A]"></i> Buat Sesi Mabar Pertama
                        </a>
                    </div>
                </div>
            @endforelse

            {{-- Pagination Navigation --}}
            @if($hostSessions->hasPages())
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pt-4 border-t border-slate-200/60">
                    <p class="text-xs text-slate-500">
                        Menampilkan <span class="font-bold text-slate-800">{{ $hostSessions->firstItem() }}</span> - <span class="font-bold text-slate-800">{{ $hostSessions->lastItem() }}</span> dari <span class="font-bold text-slate-800">{{ $hostSessions->total() }}</span> sesi
                    </p>

                    <div class="flex items-center gap-1.5 self-center sm:self-auto">
                        {{-- Previous Button --}}
                        @if ($hostSessions->onFirstPage())
                            <span class="px-3 py-1.5 rounded-xl text-xs font-semibold text-slate-300 bg-slate-100/60 cursor-not-allowed">
                                <i class="fa-solid fa-chevron-left mr-1"></i> Prev
                            </span>
                        @else
                            <a href="{{ $hostSessions->previousPageUrl() }}" class="px-3 py-1.5 rounded-xl text-xs font-bold text-slate-700 glass-card hover:bg-[#063B00] hover:text-white transition-all shadow-2xs">
                                <i class="fa-solid fa-chevron-left mr-1"></i> Prev
                            </a>
                        @endif

                        {{-- Page Number Links --}}
                        @foreach ($hostSessions->getUrlRange(max(1, $hostSessions->currentPage() - 2), min($hostSessions->lastPage(), $hostSessions->currentPage() + 2)) as $page => $url)
                            @if ($page == $hostSessions->currentPage())
                                <span class="w-8 h-8 rounded-xl text-xs font-bold bg-[#063B00] text-white flex items-center justify-center shadow-xs">
                                    {{ $page }}
                                </span>
                            @else
                                <a href="{{ $url }}" class="w-8 h-8 rounded-xl text-xs font-bold text-slate-700 glass-card hover:bg-slate-100 flex items-center justify-center transition-all shadow-2xs">
                                    {{ $page }}
                                </a>
                            @endif
                        @endforeach

                        {{-- Next Button --}}
                        @if ($hostSessions->hasMorePages())
                            <a href="{{ $hostSessions->nextPageUrl() }}" class="px-3 py-1.5 rounded-xl text-xs font-bold text-slate-700 glass-card hover:bg-[#063B00] hover:text-white transition-all shadow-2xs">
                                Next <i class="fa-solid fa-chevron-right ml-1"></i>
                            </a>
                        @else
                            <span class="px-3 py-1.5 rounded-xl text-xs font-semibold text-slate-300 bg-slate-100/60 cursor-not-allowed">
                                Next <i class="fa-solid fa-chevron-right ml-1"></i>
                            </span>
                        @endif
                    </div>
                </div>
            @endif
        </div>

    {{-- ========================================================================= --}}
    {{-- TAB 2: REKAP KARIR PEMAIN (PLAYER CAREER STATS) --}}
    {{-- ========================================================================= --}}
    @else

        <!-- Strava-Style Shareable Activity Card (Subtle Frosted Glass) -->
        <div id="stravaCard" class="glass-card rounded-3xl p-6 sm:p-8 space-y-6 border border-white/90 shadow-sm">
            
            <!-- Brand Header -->
            <div class="flex items-center justify-between border-b border-slate-200/40 pb-4">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-xl bg-[#063B00] flex items-center justify-center text-[#A8E63A] text-xs shadow-xs">
                        <i class="fa-solid fa-table-tennis-paddle-ball"></i>
                    </div>
                    <div>
                        <span class="text-[10px] font-bold tracking-wider text-[#063B00] uppercase">Player Performance Summary</span>
                        <h3 class="text-sm font-bold text-slate-900 leading-tight">Rekap Pertandingan &amp; Statistik Bermain</h3>
                    </div>
                </div>
                <div>
                    <span class="text-xs font-semibold text-slate-600 bg-white/80 px-3 py-1 rounded-full border border-slate-200/60 shadow-2xs">
                        {{ date('d M Y') }}
                    </span>
                </div>
            </div>

            <!-- Player Profile & Highlight -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div class="flex items-center gap-3.5">
                    <img src="{{ $recap['player']['avatar'] }}" alt="{{ $recap['player']['name'] }}" class="w-14 h-14 rounded-full object-cover ring-2 ring-[#063B00]/40 shadow-xs">
                    <div>
                        <h2 class="text-lg font-bold text-slate-900 flex items-center gap-2">
                            {{ $recap['player']['name'] }}
                            <span class="text-xs bg-[#EBF8D8] text-[#063B00] px-2 py-0.5 rounded-full border border-[#063B00]/25 font-semibold">{{ $recap['player']['level'] }}</span>
                        </h2>
                        <p class="text-xs text-slate-500">{{ $recap['player']['username'] }} &bull; Role: <strong class="text-slate-700">{{ $recap['player']['role'] ?? 'Member' }}</strong></p>
                    </div>
                </div>

                <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl bg-amber-50/80 border border-amber-200/70 text-amber-900 font-bold text-xs shadow-2xs">
                    <span>{{ $recap['player']['streak'] }}</span>
                </div>
            </div>

            <!-- Big Stat Numbers -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 pt-1">
                <div class="bg-white/70 backdrop-blur-xs p-4 rounded-2xl border border-slate-200/60 text-center shadow-2xs">
                    <span class="text-[10px] text-slate-400 font-medium block uppercase">Total Tanding</span>
                    <strong class="text-2xl font-bold text-slate-900 mt-0.5 block">{{ $recap['player']['total_matches'] }}</strong>
                    <span class="text-[10px] text-slate-400">Pertandingan</span>
                </div>

                <div class="bg-white/70 backdrop-blur-xs p-4 rounded-2xl border border-slate-200/60 text-center shadow-2xs">
                    <span class="text-[10px] text-slate-400 font-medium block uppercase">Kemenangan</span>
                    <strong class="text-2xl font-bold text-[#063B00] mt-0.5 block">{{ $recap['player']['wins'] }}W</strong>
                    <span class="text-[10px] text-slate-400">{{ $recap['player']['losses'] }}x Kalah</span>
                </div>

                <div class="bg-white/70 backdrop-blur-xs p-4 rounded-2xl border border-slate-200/60 text-center shadow-2xs">
                    <span class="text-[10px] text-slate-400 font-medium block uppercase">Win Rate %</span>
                    <strong class="text-2xl font-bold text-slate-800 mt-0.5 block">{{ $recap['player']['win_rate'] }}</strong>
                    <span class="text-[10px] text-[#063B00] font-semibold">Persentase</span>
                </div>

                <div class="bg-white/70 backdrop-blur-xs p-4 rounded-2xl border border-slate-200/60 text-center shadow-2xs">
                    <span class="text-[10px] text-slate-400 font-medium block uppercase">Waktu Bermain</span>
                    <strong class="text-2xl font-bold text-slate-900 mt-0.5 block">{{ $recap['player']['total_hours'] }}</strong>
                    <span class="text-[10px] text-slate-400">Total di Lapangan</span>
                </div>
            </div>
        </div>

        @if(!$recap['has_matches'])
            <!-- Empty State for New Players (BUG-MEM-003) -->
            <div class="glass-card rounded-3xl p-8 sm:p-12 text-center space-y-4 border border-white/90 shadow-sm">
                <div class="w-16 h-16 rounded-3xl bg-[#EBF8D8] border border-[#063B00]/20 text-[#063B00] flex items-center justify-center text-2xl mx-auto shadow-xs">
                    <i class="fa-solid fa-table-tennis-paddle-ball"></i>
                </div>
                <div class="space-y-1.5 max-w-md mx-auto">
                    <h3 class="text-base font-black text-slate-900">Belum Ada Riwayat Pertandingan</h3>
                    <p class="text-xs text-slate-500 leading-relaxed">
                        Anda belum memiliki riwayat pertandingan yang selesai. Ikuti dan selesaikan sesi mabar padel atau tenis untuk mulai mencatat performa karier, win rate, dan statistik bermain Anda di sini!
                    </p>
                </div>
                <div class="pt-2 flex justify-center gap-3">
                    <a href="{{ route('games.index') }}" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-2xl bg-[#063B00] hover:bg-[#042a00] text-white font-bold text-xs shadow-md transition-all hover:scale-[1.02] cursor-pointer">
                        <i class="fa-solid fa-calendar-days text-[#A8E63A]"></i>
                        <span>Jelajahi Jadwal Mabar</span>
                    </a>
                    <a href="{{ route('player.profile') }}" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-2xl bg-white hover:bg-slate-50 border border-slate-200 text-slate-700 font-bold text-xs shadow-2xs transition-all">
                        <i class="fa-solid fa-id-card text-slate-400"></i>
                        <span>Lengkapi Profil</span>
                    </a>
                </div>
            </div>
        @else
            <!-- Match History & Head-to-Head Section -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Left: Match History List (2 Cols) -->
                <div class="lg:col-span-2 space-y-3">
                    <h3 class="text-sm font-bold text-slate-900">
                        Riwayat Pertandingan Terakhir
                    </h3>

                    <div class="space-y-2.5">
                        @foreach($recap['recent_matches'] as $match)
                            <div class="glass-card p-4 rounded-2xl flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                <div class="space-y-1">
                                    <div class="flex items-center gap-2">
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold {{ $match['result'] === 'WIN' ? 'bg-[#EBF8D8] text-[#063B00] border border-[#063B00]/25' : ($match['result'] === 'DRAW' ? 'bg-slate-100 text-slate-700 border border-slate-200' : 'bg-rose-50 text-rose-800 border border-rose-200') }}">
                                            {{ $match['result'] }}
                                        </span>
                                        <span class="font-bold text-xs text-slate-900">{{ $match['sport'] }}</span>
                                        <span class="text-slate-300">&bull;</span>
                                        <span class="text-xs text-slate-500">{{ $match['venue'] }}</span>
                                    </div>

                                    <p class="text-xs text-slate-600">
                                        Partner: <strong class="text-slate-800">{{ $match['partner'] }}</strong> 
                                        vs Lawan: <span>{{ implode(' & ', $match['opponents']) }}</span>
                                    </p>
                                </div>

                                <div class="text-right sm:border-l sm:border-slate-200/50 sm:pl-4">
                                    <span class="text-[10px] text-slate-400 block">{{ $match['match_date'] }}</span>
                                    <span class="text-sm font-bold text-slate-900">Skor: {{ $match['score'] }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <!-- Right: Head-to-Head Stats (1 Col) -->
                <div class="space-y-3">
                    <h3 class="text-sm font-bold text-slate-900">
                        Rekor Lawan (Head-to-Head)
                    </h3>

                    <div class="glass-card rounded-2xl p-4 space-y-3">
                        @if(empty($recap['head_to_head']))
                            <p class="text-xs text-slate-400 text-center py-4">Belum ada statistik head-to-head lawan.</p>
                        @else
                            <p class="text-xs text-slate-500 leading-relaxed">
                                Statistik kemenangan vs lawan bermain yang tercatat di sistem:
                            </p>

                            <div class="space-y-3">
                                @foreach($recap['head_to_head'] as $h2h)
                                    <div class="bg-white/70 p-3 rounded-xl border border-slate-200/50 space-y-1.5 shadow-2xs">
                                        <div class="flex items-center justify-between text-xs font-semibold">
                                            <span class="text-slate-800">{{ $h2h['opponent'] }}</span>
                                            <span class="text-[#063B00] font-bold">{{ $h2h['win'] }}W - {{ $h2h['lose'] }}L</span>
                                        </div>
                                        <div class="w-full bg-slate-200/70 rounded-full h-1.5 overflow-hidden">
                                            @php
                                                $h2hPercent = $h2h['played'] > 0 ? ($h2h['win'] / $h2h['played']) * 100 : 0;
                                            @endphp
                                            <div class="bg-[#063B00] h-1.5 rounded-full" style="width: {{ $h2hPercent }}%"></div>
                                        </div>
                                        <div class="flex justify-between text-[10px] text-slate-400">
                                            <span>{{ $h2h['played'] }}x main</span>
                                            <span>{{ round($h2hPercent) }}% win</span>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endif
    @endif
</div>

<!-- ========================================================= -->
<!-- 1. MODAL: SHARE OPTIONS MODAL -->
<!-- ========================================================= -->
<div id="shareOptionsModal" class="fixed inset-0 z-[100] hidden bg-slate-950/60 backdrop-blur-sm flex items-center justify-center p-4 transition-all">
    <div class="bg-white rounded-3xl max-w-md w-full p-6 sm:p-7 space-y-6 relative border border-slate-200 shadow-2xl animate-in fade-in zoom-in duration-200">
        <!-- Close Button -->
        <button onclick="closeShareModal()" class="absolute top-5 right-5 w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-500 flex items-center justify-center transition-colors cursor-pointer" title="Tutup">
            <i class="fa-solid fa-xmark text-sm"></i>
        </button>

        <!-- Header -->
        <div class="space-y-1 pr-6">
            <div class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-[#EBF8D8] text-[#063B00] text-[10px] font-bold border border-[#063B00]/20">
                <i class="fa-solid fa-share-nodes"></i> Share Player Recap
            </div>
            <h3 class="text-xl font-black text-slate-900 tracking-tight">Bagikan Rekap Karir</h3>
            <p class="text-xs text-slate-500">Rayakan performa dan rekor bermainmu bersama teman atau komunitas!</p>
        </div>

        <!-- Options Cards -->
        <div class="space-y-3">
            <!-- Option 1: Share as Web Preview -->
            <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200/80 hover:border-[#063B00]/30 transition-all space-y-3">
                <div class="flex items-start gap-3">
                    <div class="w-9 h-9 rounded-xl bg-emerald-100 border border-emerald-200 flex items-center justify-center text-[#063B00] text-sm shrink-0">
                        <i class="fa-solid fa-globe"></i>
                    </div>
                    <div>
                        <h4 class="text-xs font-bold text-slate-900">Share as Web Preview</h4>
                        <p class="text-[11px] text-slate-500 leading-relaxed">Tampilkan link profil rekap statistik karirmu. Cocok untuk grup WhatsApp / Telegram.</p>
                    </div>
                </div>
                <div class="flex items-center gap-2 pt-1">
                    <button id="btnCopyWebLink" onclick="copyWebLink(this)" class="flex-1 py-2 rounded-xl bg-white border border-slate-200 hover:bg-slate-100 text-slate-700 text-xs font-bold transition-all flex items-center justify-center gap-1.5 cursor-pointer shadow-2xs">
                        <i class="fa-solid fa-copy text-slate-400"></i> <span>Salin Link</span>
                    </button>
                    <button id="btnShareWebDirect" onclick="shareWebDirect(this)" class="flex-1 py-2 rounded-xl bg-[#063B00] hover:bg-[#042a00] text-white text-xs font-bold transition-all flex items-center justify-center gap-1.5 cursor-pointer shadow-2xs">
                        <i class="fa-solid fa-paper-plane text-[#A8E63A]"></i> <span>Bagikan Link</span>
                    </button>
                </div>
            </div>

            <!-- Option 2: Share as Image (Template Studio) -->
            <div class="p-4 rounded-2xl bg-gradient-to-br from-emerald-50 via-lime-50 to-white border border-[#063B00]/25 hover:border-[#063B00] transition-all space-y-3 shadow-2xs">
                <div class="flex items-start gap-3">
                    <div class="w-9 h-9 rounded-xl bg-[#063B00] text-[#A8E63A] flex items-center justify-center text-sm shrink-0 shadow-xs">
                        <i class="fa-solid fa-palette"></i>
                    </div>
                    <div>
                        <div class="flex items-center gap-1.5">
                            <h4 class="text-xs font-bold text-slate-900">Share as Image / Story</h4>
                            <span class="px-1.5 py-0.2 rounded text-[9px] font-extrabold bg-[#063B00] text-white">9:16</span>
                        </div>
                        <p class="text-[11px] text-slate-600 leading-relaxed">Ubah statistik karirmu jadi kartu gambar story ala Strava/SKOR dengan fotomu dari galeri!</p>
                    </div>
                </div>
                <button onclick="openTemplateStudio()" class="w-full py-2.5 rounded-xl bg-[#063B00] hover:bg-[#042a00] text-white font-black text-xs transition-all flex items-center justify-center gap-2 cursor-pointer shadow-md hover:scale-[1.01]">
                    <i class="fa-solid fa-wand-magic-sparkles text-[#A8E63A]"></i> Pilih Template Story
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ========================================================= -->
<!-- 2. MODAL: TEMPLATE STUDIO (9:16 STORY CUSTOMIZER) -->
<!-- ========================================================= -->
<div id="templateStudioModal" class="fixed inset-0 z-[100] hidden bg-slate-950/70 backdrop-blur-md overflow-y-auto p-2 sm:p-6 pb-24 sm:pb-8 transition-all flex items-start sm:items-center justify-center">
    <div class="max-w-4xl w-full bg-white rounded-3xl border border-slate-200 shadow-2xl text-slate-900 overflow-hidden my-3 sm:my-auto animate-in fade-in zoom-in duration-200">
        
        <!-- Studio Header -->
        <div class="px-5 sm:px-6 py-3.5 sm:py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/80">
            <div class="space-y-0.5">
                <div class="flex items-center gap-2">
                    <h3 class="text-sm sm:text-base font-black text-slate-900">Select Template Story</h3>
                    <span class="text-[9px] sm:text-[10px] font-extrabold px-2 py-0.5 rounded-full bg-[#EBF8D8] text-[#063B00] border border-[#063B00]/20">9:16 HD</span>
                </div>
                <p class="text-[10px] sm:text-[11px] text-slate-500">Pilih template, pasang foto dokumentasi mabar dari galeri, dan unduh/bagikan.</p>
            </div>
            <!-- Clean Close Button -->
            <button onclick="closeTemplateStudio()" class="w-8 h-8 rounded-full bg-white hover:bg-slate-200 border border-slate-200 text-slate-500 flex items-center justify-center text-xs transition-colors cursor-pointer shrink-0" title="Tutup">
                <i class="fa-solid fa-xmark text-sm"></i>
            </button>
        </div>

        <!-- Studio Workspace Grid -->
        <div class="p-3 sm:p-6 grid grid-cols-1 lg:grid-cols-12 gap-5 sm:gap-6 items-center">
            
            <!-- LEFT: 9:16 Live Story Preview Card with < > Navigation Arrows -->
            <div class="lg:col-span-6 flex items-center justify-center gap-1.5 sm:gap-3">
                
                <!-- Prev Button (<) -->
                <button type="button" onclick="prevTemplate()" class="w-8 h-8 sm:w-10 sm:h-10 rounded-full bg-slate-100 hover:bg-[#063B00] hover:text-white border border-slate-200 text-slate-700 shadow-sm flex items-center justify-center font-bold text-xs sm:text-sm cursor-pointer transition-all shrink-0 hover:scale-105" title="Template Sebelumnya">
                    <i class="fa-solid fa-chevron-left"></i>
                </button>

                <!-- Story Card Wrapper (Rasio 9:16 HD) -->
                <div id="storyCardContainer" class="w-[240px] xs:w-[270px] sm:w-[320px] aspect-[9/16] rounded-[24px] sm:rounded-[26px] overflow-hidden shadow-2xl relative select-none border border-slate-800 bg-[#090d10] text-white flex flex-col justify-between p-3.5 sm:p-4.5" style="box-shadow: 0 20px 40px -10px rgba(0,0,0,0.5);">
                    
                    <!-- Background Layer: User Custom Uploaded Photo -->
                    <div id="storyBgPhoto" class="absolute inset-0 bg-cover bg-center transition-all duration-300" style="background-image: none;"></div>
                    
                    <!-- Gradient & Frosted Overlay to guarantee high contrast on any photo -->
                    <div id="storyOverlayTint" class="absolute inset-0 bg-gradient-to-b from-black/80 via-black/35 to-black/90 pointer-events-none"></div>

                    <!-- Ambient Court Grid Pattern (If no photo uploaded yet) -->
                    <div id="storyDefaultGridPattern" class="absolute inset-0 opacity-15 pointer-events-none bg-[radial-gradient(#A8E63A_1px,transparent_1px)] [background-size:16px_16px]"></div>

                    <!-- ---------------------------------------------------- -->
                    <!-- TEMPLATE 1: STRAVA-STYLE ATHLETIC STATS CARD -->
                    <!-- ---------------------------------------------------- -->
                    <div id="tpl_strava" class="template-view relative z-10 h-full flex flex-col justify-between">
                        <!-- Top Header Strava Style -->
                        <div class="space-y-1.5 border-b border-white/10 pb-2.5">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-1.5">
                                    <span class="w-2 h-2 rounded-full bg-[#A8E63A] animate-pulse"></span>
                                    <span class="text-[9px] font-black tracking-widest text-[#A8E63A] uppercase">MATCHA ATHLETE</span>
                                </div>
                                <div class="flex items-center gap-1 bg-black/40 backdrop-blur-md px-2 py-0.5 rounded-lg border border-white/10">
                                    <img src="{{ asset('images/logo.svg') }}" class="w-4 h-4" alt="Matcha">
                                    <span class="text-[9px] font-black tracking-wider text-white">MATCHA</span>
                                </div>
                            </div>
                            
                            <!-- Player Profile Info -->
                            <div class="flex items-center gap-2.5 pt-1">
                                <img src="{{ $recap['player']['avatar'] }}" alt="{{ $recap['player']['name'] }}" class="w-10 h-10 rounded-full object-cover border-2 border-[#A8E63A] shadow-md shrink-0">
                                <div class="min-w-0 flex-1">
                                    <h4 class="text-xs font-black text-white truncate leading-tight flex items-center gap-1.5">
                                        {{ $recap['player']['name'] }}
                                        <span class="px-1.5 py-0.2 rounded text-[8px] font-black bg-[#A8E63A] text-[#063B00]">{{ $recap['player']['level'] }}</span>
                                    </h4>
                                    <p class="text-[9px] text-slate-300 truncate">
                                        {{ $recap['player']['username'] }} &bull; <span class="text-[#A8E63A] font-bold">{{ $recap['player']['role'] ?? 'Member' }}</span>
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Big Athletic Metrics Grid -->
                        <div class="bg-black/65 backdrop-blur-xl border border-white/15 rounded-2xl p-2.5 sm:p-3 shadow-2xl space-y-2 my-auto">
                            <div class="grid grid-cols-2 gap-2 text-center">
                                <div class="p-2 rounded-xl bg-[#063B00]/70 border border-[#A8E63A]/40">
                                    <p class="text-[8px] font-bold text-[#A8E63A] uppercase tracking-wider">Win Rate</p>
                                    <p class="text-2xl font-black text-[#A8E63A] mt-0.5">{{ $recap['player']['win_rate'] }}</p>
                                </div>
                                <div class="p-2 rounded-xl bg-white/5 border border-white/5">
                                    <p class="text-[8px] font-bold text-slate-400 uppercase tracking-wider">Match Record</p>
                                    <p class="text-base font-black text-slate-200 mt-0.5">{{ $recap['player']['wins'] }}W - {{ $recap['player']['losses'] }}L</p>
                                </div>
                                <div class="p-2 rounded-xl bg-white/5 border border-white/5">
                                    <p class="text-[8px] font-bold text-slate-400 uppercase tracking-wider">Total Tanding</p>
                                    <p class="text-base font-black text-white mt-0.5">{{ $recap['player']['total_matches'] }} Match</p>
                                </div>
                                <div class="p-2 rounded-xl bg-white/5 border border-white/5">
                                    <p class="text-[8px] font-bold text-slate-400 uppercase tracking-wider">Waktu Main</p>
                                    <p class="text-base font-black text-slate-200 mt-0.5">{{ $recap['player']['total_hours'] }}</p>
                                </div>
                            </div>

                            <!-- Performance Streak Badge -->
                            <div class="pt-0.5 text-center">
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-amber-500/20 text-amber-300 border border-amber-400/30 text-[9px] font-bold">
                                    {{ $recap['player']['streak'] }}
                                </span>
                            </div>
                        </div>

                        <!-- Footer -->
                        <div class="pt-2 text-center border-t border-white/10 flex items-center justify-between text-[8px] text-slate-400">
                            <span>MATCHA Tennis &amp; Padel</span>
                            <span class="font-bold text-[#A8E63A]">{{ date('d M Y') }}</span>
                        </div>
                    </div>

                    <!-- ---------------------------------------------------- -->
                    <!-- TEMPLATE 2: PLAYER CAREER CARD & H2H -->
                    <!-- ---------------------------------------------------- -->
                    <div id="tpl_career" class="template-view relative z-10 h-full flex flex-col justify-between hidden">
                        <!-- Top Header -->
                        <div class="flex items-center justify-between pb-2 border-b border-white/10">
                            <div class="flex items-center gap-2">
                                <img src="{{ asset('images/logo.svg') }}" class="w-5 h-5" alt="Matcha">
                                <div>
                                    <span class="text-xs font-black tracking-wider text-white block">MATCHA</span>
                                    <span class="text-[8px] font-bold text-[#A8E63A] uppercase tracking-widest block -mt-0.5">CAREER CARD</span>
                                </div>
                            </div>
                            <span class="text-[9px] font-extrabold px-2 py-0.5 rounded bg-[#063B00] text-[#A8E63A] border border-[#A8E63A]/30">
                                {{ $recap['player']['level'] }}
                            </span>
                        </div>

                        <!-- Player Hero Showcase -->
                        <div class="space-y-2.5 my-auto">
                            <div class="text-center space-y-1.5">
                                <div class="relative inline-block">
                                    <img src="{{ $recap['player']['avatar'] }}" alt="{{ $recap['player']['name'] }}" class="w-16 h-16 sm:w-20 sm:h-20 rounded-full object-cover mx-auto ring-4 ring-[#A8E63A]/60 shadow-xl">
                                    <span class="absolute -bottom-1 right-0 w-6 h-6 rounded-full bg-[#063B00] border-2 border-[#A8E63A] flex items-center justify-center text-white text-[10px]">
                                        🎾
                                    </span>
                                </div>
                                <h3 class="text-sm font-black text-white truncate">{{ $recap['player']['name'] }}</h3>
                                <p class="text-[9px] text-slate-400">{{ $recap['player']['community'] ?? 'Matcha Community Player' }}</p>
                            </div>

                            <!-- Career Highlights Box -->
                            <div class="bg-black/65 backdrop-blur-xl border border-white/15 rounded-2xl p-2.5 sm:p-3 shadow-2xl space-y-2">
                                <div class="flex items-center justify-between p-2 rounded-xl bg-gradient-to-r from-[#063B00]/80 to-black/60 border border-[#A8E63A]/40">
                                    <div>
                                        <p class="text-[8px] font-bold text-slate-300 uppercase">Performa Karir</p>
                                        <p class="text-xs font-black text-[#A8E63A]">{{ $recap['player']['win_rate'] }} Win Rate</p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-[8px] font-bold text-slate-300 uppercase">Rekor</p>
                                        <p class="text-xs font-black text-white">{{ $recap['player']['wins'] }}W - {{ $recap['player']['losses'] }}L</p>
                                    </div>
                                </div>

                                <!-- Head-to-Head Mini Preview -->
                                @if(!empty($recap['head_to_head']))
                                    <div class="space-y-1 pt-0.5">
                                        <p class="text-[8px] font-bold text-slate-400 uppercase tracking-wider text-left">Top Rivals (H2H)</p>
                                        @foreach(array_slice($recap['head_to_head'], 0, 2) as $h2h)
                                            <div class="flex items-center justify-between text-[9px] p-1.5 rounded-lg bg-white/5 border border-white/5">
                                                <span class="text-slate-200 font-semibold truncate">{{ $h2h['opponent'] }}</span>
                                                <span class="text-[#A8E63A] font-black shrink-0">{{ $h2h['win'] }}W - {{ $h2h['lose'] }}L</span>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </div>

                        <!-- Footer -->
                        <div class="pt-2 text-center border-t border-white/10">
                            <p class="text-[8px] text-slate-400">Matcha Match Arena &bull; Verified Player</p>
                        </div>
                    </div>

                    <!-- ---------------------------------------------------- -->
                    <!-- TEMPLATE 3: RECENT MATCHES HIGHLIGHTS -->
                    <!-- ---------------------------------------------------- -->
                    <div id="tpl_highlights" class="template-view relative z-10 h-full flex flex-col justify-between hidden">
                        <!-- Top Header -->
                        <div class="flex items-center justify-between pb-2 border-b border-white/10">
                            <div class="flex items-center gap-2">
                                <img src="{{ asset('images/logo.svg') }}" class="w-5 h-5" alt="Matcha">
                                <div>
                                    <h4 class="text-xs font-black text-white truncate max-w-[150px]">{{ $recap['player']['name'] }}</h4>
                                    <p class="text-[8px] text-[#A8E63A] font-bold">Recent Match Highlights</p>
                                </div>
                            </div>
                            <span class="text-[9px] font-extrabold px-2 py-0.5 rounded bg-white/10 text-slate-200 border border-white/15">
                                {{ $recap['player']['total_matches'] }} Matches
                            </span>
                        </div>

                        <!-- Match Result Cards List -->
                        <div class="space-y-2 my-auto">
                            @if(!empty($recap['recent_matches']))
                                @foreach(array_slice($recap['recent_matches'], 0, 3) as $m)
                                    <div class="bg-black/65 backdrop-blur-xl border border-white/15 rounded-xl p-2 sm:p-2.5 shadow-md space-y-1">
                                        <div class="flex items-center justify-between text-[8px]">
                                            <span class="font-bold text-slate-400">{{ $m['sport'] }} &bull; {{ $m['venue'] }}</span>
                                            <span class="px-1.5 py-0.2 rounded font-black {{ $m['result'] === 'WIN' ? 'bg-[#A8E63A] text-[#063B00]' : ($m['result'] === 'DRAW' ? 'bg-slate-200 text-slate-800' : 'bg-rose-500 text-white') }}">
                                                {{ $m['result'] }}
                                            </span>
                                        </div>
                                        <div class="flex items-center justify-between text-[9px] pt-0.5">
                                            <div class="truncate text-slate-300 pr-1">
                                                vs <span class="font-bold text-white">{{ implode(' & ', $m['opponents']) }}</span>
                                            </div>
                                            <span class="font-black text-xs text-[#A8E63A] shrink-0">{{ $m['score'] }}</span>
                                        </div>
                                    </div>
                                @endforeach
                            @else
                                <div class="bg-black/65 backdrop-blur-xl border border-white/15 rounded-xl p-4 text-center space-y-1">
                                    <p class="text-xs font-bold text-white">Belum Ada Match Selesai</p>
                                    <p class="text-[9px] text-slate-400">Ikuti sesi mabar untuk menampilkan highlight pertandingan.</p>
                                </div>
                            @endif
                        </div>

                        <!-- Footer -->
                        <div class="pt-2 text-center border-t border-white/10">
                            <span class="text-[8px] font-bold text-[#A8E63A]">
                                Record: {{ $recap['player']['wins'] }}W - {{ $recap['player']['losses'] }}L &bull; Matcha App
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Next Button (>) -->
                <button type="button" onclick="nextTemplate()" class="w-8 h-8 sm:w-10 sm:h-10 rounded-full bg-slate-100 hover:bg-[#063B00] hover:text-white border border-slate-200 text-slate-700 shadow-sm flex items-center justify-center font-bold text-xs sm:text-sm cursor-pointer transition-all shrink-0 hover:scale-105" title="Template Selanjutnya">
                    <i class="fa-solid fa-chevron-right"></i>
                </button>
            </div>

            <!-- RIGHT: Customization Controls & Actions -->
            <div class="lg:col-span-6 space-y-4">
                
                <!-- 1. Select Template Tabs -->
                <div class="space-y-1.5">
                    <label class="text-xs font-bold text-slate-700 uppercase tracking-wider block">
                        <i class="fa-solid fa-shapes text-[#063B00] mr-1"></i> Pilih Template
                    </label>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                        <button onclick="switchTemplate(0)" id="tplBtn_0" class="tpl-btn p-2.5 rounded-2xl bg-[#063B00] border-2 border-[#063B00] text-left transition-all cursor-pointer shadow-xs">
                            <p class="text-xs font-black text-white">1. Athletic Strava</p>
                            <p class="text-[10px] text-slate-200">Personal performance</p>
                        </button>
                        <button onclick="switchTemplate(1)" id="tplBtn_1" class="tpl-btn p-2.5 rounded-2xl bg-white border-2 border-slate-200 text-left hover:border-slate-300 hover:bg-slate-50 transition-all cursor-pointer shadow-2xs">
                            <p class="text-xs font-black text-slate-900">2. Career Card</p>
                            <p class="text-[10px] text-slate-500">Player hero &amp; rivals</p>
                        </button>
                        <button onclick="switchTemplate(2)" id="tplBtn_2" class="tpl-btn p-2.5 rounded-2xl bg-white border-2 border-slate-200 text-left hover:border-slate-300 hover:bg-slate-50 transition-all cursor-pointer shadow-2xs">
                            <p class="text-xs font-black text-slate-900">3. Match Highlights</p>
                            <p class="text-[10px] text-slate-500">Rekap skor terakhir</p>
                        </button>
                    </div>
                </div>

                <!-- 2. Insert Photo Background from Local Device -->
                <div class="space-y-2 bg-slate-50 border border-slate-200/80 p-3.5 rounded-2xl shadow-2xs">
                    <div class="flex items-center justify-between">
                        <label class="text-xs font-bold text-slate-800 uppercase tracking-wider block">
                            <i class="fa-solid fa-image text-[#063B00] mr-1"></i> Background Foto Lapangan
                        </label>
                        <span id="photoBadge" class="hidden text-[10px] font-bold px-2 py-0.5 rounded-full bg-[#EBF8D8] text-[#063B00] border border-[#063B00]/25">Foto Terpasang</span>
                    </div>
                    <p class="text-[11px] text-slate-500">Pasang foto momen mabar dari galeri HP atau komputermu.</p>
                    
                    <input type="file" id="recapBgInput" accept="image/*" class="hidden" onchange="handlePhotoUpload(event)">
                    
                    <div class="flex items-center gap-2 pt-0.5">
                        <button type="button" onclick="document.getElementById('recapBgInput').click()" class="flex-1 py-2.5 rounded-xl bg-white hover:bg-slate-100 border border-slate-200 text-slate-800 font-bold text-xs transition-all flex items-center justify-center gap-2 cursor-pointer shadow-2xs">
                            <i class="fa-solid fa-camera text-[#063B00]"></i> <span id="photoBtnLabel">Insert Photo dari Galeri</span>
                        </button>
                        <button type="button" id="removePhotoBtn" onclick="removePhoto()" class="hidden px-3 py-2.5 rounded-xl bg-rose-50 hover:bg-rose-100 border border-rose-200 text-rose-700 text-xs font-bold transition-all cursor-pointer" title="Hapus foto">
                            <i class="fa-solid fa-trash-can"></i>
                        </button>
                    </div>
                </div>

                <!-- 3. Overlay Filter Style -->
                <div class="space-y-1.5">
                    <label class="text-xs font-bold text-slate-700 uppercase tracking-wider block">
                        <i class="fa-solid fa-sliders text-[#063B00] mr-1"></i> Filter Gelap Overlay
                    </label>
                    <div class="flex items-center gap-2">
                        <button onclick="setOverlayTheme('contrast')" id="filterBtn_contrast" class="filter-btn flex-1 py-2 rounded-xl bg-[#063B00] border-2 border-[#063B00] text-white text-xs font-bold transition-all cursor-pointer shadow-xs">
                            Dark Contrast
                        </button>
                        <button onclick="setOverlayTheme('matcha')" id="filterBtn_matcha" class="filter-btn flex-1 py-2 rounded-xl bg-white border-2 border-slate-200 text-slate-700 hover:bg-slate-50 text-xs font-bold transition-all cursor-pointer shadow-2xs">
                            Matcha Glow
                        </button>
                        <button onclick="setOverlayTheme('clean')" id="filterBtn_clean" class="filter-btn flex-1 py-2 rounded-xl bg-white border-2 border-slate-200 text-slate-700 hover:bg-slate-50 text-xs font-bold transition-all cursor-pointer shadow-2xs">
                            Minimal
                        </button>
                    </div>
                </div>

                <!-- 4. Export Actions -->
                <div class="pt-2 space-y-2 mb-6 sm:mb-0">
                    <button type="button" id="btnShareStory" onclick="exportAndShareStory('share')" class="w-full py-3.5 rounded-2xl bg-[#063B00] hover:bg-[#042a00] text-white font-black text-sm transition-all shadow-md flex items-center justify-center gap-2 cursor-pointer border border-[#A8E63A]/40 active:scale-[0.99]">
                        <i class="fa-solid fa-share-nodes text-[#A8E63A]"></i> <span class="text-white font-bold">Share Image / Story</span>
                    </button>
                    <button type="button" id="btnDownloadStory" onclick="exportAndShareStory('download')" class="w-full py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 border border-slate-300 text-slate-800 font-bold text-xs transition-all flex items-center justify-center gap-2 cursor-pointer shadow-2xs active:scale-[0.99]">
                        <i class="fa-solid fa-download text-[#063B00]"></i> <span>Download PNG (1080x1920)</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<!-- Include html2canvas-pro & html-to-image for high-res story card exports -->
<script src="https://cdn.jsdelivr.net/npm/html2canvas-pro@latest/dist/html2canvas-pro.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html-to-image/1.11.11/html-to-image.min.js"></script>

<script>
    // Modal Control: Share Options Modal
    function openShareModal() {
        const modal = document.getElementById('shareOptionsModal');
        if (modal) modal.classList.remove('hidden');
    }

    function closeShareModal() {
        const modal = document.getElementById('shareOptionsModal');
        if (modal) modal.classList.add('hidden');
    }

    function shareRecap() {
        openShareModal();
    }

    function copyWebLink(btn) {
        const targetBtn = btn || document.getElementById('btnCopyWebLink');
        navigator.clipboard.writeText(window.location.href);
        
        if (targetBtn) {
            const originalHtml = targetBtn.innerHTML;
            targetBtn.innerHTML = `<i class="fa-solid fa-circle-check text-emerald-600"></i> <span class="text-emerald-700 font-extrabold">Tersalin!</span>`;
            targetBtn.classList.add('!bg-emerald-50', '!border-emerald-300');
            
            setTimeout(() => {
                targetBtn.innerHTML = originalHtml;
                targetBtn.classList.remove('!bg-emerald-50', '!border-emerald-300');
            }, 2000);
        }

        if (typeof showToast === 'function') {
            showToast('Link rekap karir berhasil disalin ke clipboard! 📋');
        } else {
            alert('Link rekap karir berhasil disalin ke clipboard!');
        }
    }

    function shareWebDirect(btn) {
        const playerName = '{{ addslashes($recap["player"]["name"] ?? "Pemain") }}';
        const winRate = '{{ addslashes($recap["player"]["win_rate"] ?? "0%") }}';
        const wins = '{{ addslashes($recap["player"]["wins"] ?? 0) }}';
        const losses = '{{ addslashes($recap["player"]["losses"] ?? 0) }}';
        const totalMatches = '{{ addslashes($recap["player"]["total_matches"] ?? 0) }}';
        const streak = '{{ addslashes($recap["player"]["streak"] ?? "") }}';
        const url = window.location.href;
        
        const shareTitle = `Rekap Karir Pemain MATCHA — ${playerName}`;
        const shareText = `🎾 *REKAP KARIR PEMAIN MATCHA* 🏆\n` +
                          `👤 *Pemain:* ${playerName}\n` +
                          `🔥 *Performa:* ${streak}\n` +
                          `⚡ *Win Rate:* ${winRate} (${wins}W - ${losses}L dari ${totalMatches} pertandingan)\n\n` +
                          `Lihat statistik & riwayat tanding lengkap di:\n${url}`;

        if (navigator.share) {
            navigator.share({
                title: shareTitle,
                text: shareText,
                url: url,
            }).then(() => {
                if (typeof showToast === 'function') {
                    showToast('Rekap karir berhasil dibagikan! 🚀');
                }
            }).catch((err) => {
                if (err.name !== 'AbortError') {
                    window.open(`https://api.whatsapp.com/send?text=${encodeURIComponent(shareText)}`, '_blank');
                }
            });
        } else {
            const waUrl = `https://api.whatsapp.com/send?text=${encodeURIComponent(shareText)}`;
            window.open(waUrl, '_blank');
            if (typeof showToast === 'function') {
                showToast('Membuka WhatsApp untuk membagikan rekap... 💬');
            }
        }
    }

    // Modal Control: Template Studio
    function openTemplateStudio() {
        closeShareModal();
        const studio = document.getElementById('templateStudioModal');
        if (studio) studio.classList.remove('hidden');
    }

    function closeTemplateStudio() {
        const studio = document.getElementById('templateStudioModal');
        if (studio) studio.classList.add('hidden');
    }

    // Template Switching Logic
    const templateIds = ['tpl_strava', 'tpl_career', 'tpl_highlights'];
    let currentTemplateIdx = 0;

    function nextTemplate() {
        const nextIdx = (currentTemplateIdx + 1) % templateIds.length;
        switchTemplate(nextIdx);
    }

    function prevTemplate() {
        const prevIdx = (currentTemplateIdx - 1 + templateIds.length) % templateIds.length;
        switchTemplate(prevIdx);
    }

    function switchTemplate(idx) {
        currentTemplateIdx = idx;
        
        // Switch Template Views
        templateIds.forEach((id, i) => {
            const el = document.getElementById(id);
            if (el) {
                if (i === idx) {
                    el.classList.remove('hidden');
                } else {
                    el.classList.add('hidden');
                }
            }
        });

        // Update Tab Buttons
        for (let i = 0; i < 3; i++) {
            const btn = document.getElementById(`tplBtn_${i}`);
            if (btn) {
                const titleEl = btn.querySelector('p:first-child');
                const descEl  = btn.querySelector('p:last-child');
                if (i === idx) {
                    btn.className = 'tpl-btn p-2.5 rounded-2xl bg-[#063B00] border-2 border-[#063B00] text-left transition-all cursor-pointer shadow-xs';
                    if (titleEl) titleEl.className = 'text-xs font-black text-white';
                    if (descEl) descEl.className = 'text-[10px] text-slate-200';
                } else {
                    btn.className = 'tpl-btn p-2.5 rounded-2xl bg-white border-2 border-slate-200 text-left hover:border-slate-300 hover:bg-slate-50 transition-all cursor-pointer shadow-2xs';
                    if (titleEl) titleEl.className = 'text-xs font-black text-slate-900';
                    if (descEl) descEl.className = 'text-[10px] text-slate-500';
                }
            }
        }
    }

    // Local Photo Upload & Live Background Injection
    function handlePhotoUpload(event) {
        const file = event.target.files && event.target.files[0];
        if (!file) return;

        const reader = new FileReader();
        reader.onload = function(e) {
            const bgContainer = document.getElementById('storyBgPhoto');
            const defaultGrid = document.getElementById('storyDefaultGridPattern');
            const photoBadge  = document.getElementById('photoBadge');
            const removeBtn   = document.getElementById('removePhotoBtn');
            const photoLabel  = document.getElementById('photoBtnLabel');

            if (bgContainer) {
                bgContainer.style.backgroundImage = `url("${e.target.result}")`;
            }
            if (defaultGrid) {
                defaultGrid.classList.add('opacity-0');
            }
            if (photoBadge) photoBadge.classList.remove('hidden');
            if (removeBtn) removeBtn.classList.remove('hidden');
            if (photoLabel) photoLabel.innerText = 'Ganti Foto Lapangan';

            if (typeof showToast === 'function') {
                showToast('Foto latar belakang berhasil dipasang! 📸');
            }
        };
        reader.readAsDataURL(file);
    }

    function removePhoto() {
        const bgContainer = document.getElementById('storyBgPhoto');
        const defaultGrid = document.getElementById('storyDefaultGridPattern');
        const photoBadge  = document.getElementById('photoBadge');
        const removeBtn   = document.getElementById('removePhotoBtn');
        const photoLabel  = document.getElementById('photoBtnLabel');
        const fileInput   = document.getElementById('recapBgInput');

        if (bgContainer) bgContainer.style.backgroundImage = 'none';
        if (defaultGrid) defaultGrid.classList.remove('opacity-0');
        if (photoBadge) photoBadge.classList.add('hidden');
        if (removeBtn) removeBtn.classList.add('hidden');
        if (photoLabel) photoLabel.innerText = 'Insert Photo dari Galeri';
        if (fileInput) fileInput.value = '';
    }

    // Overlay Theme Filters
    function setOverlayTheme(theme) {
        const overlay = document.getElementById('storyOverlayTint');
        const buttons = {
            contrast: document.getElementById('filterBtn_contrast'),
            matcha: document.getElementById('filterBtn_matcha'),
            clean: document.getElementById('filterBtn_clean'),
        };

        Object.keys(buttons).forEach(k => {
            if (buttons[k]) {
                if (k === theme) {
                    buttons[k].className = 'filter-btn flex-1 py-2 rounded-xl bg-[#063B00] border-2 border-[#063B00] text-white text-xs font-bold transition-all cursor-pointer shadow-xs';
                } else {
                    buttons[k].className = 'filter-btn flex-1 py-2 rounded-xl bg-white border-2 border-slate-200 text-slate-700 hover:bg-slate-50 text-xs font-bold transition-all cursor-pointer shadow-2xs';
                }
            }
        });

        if (overlay) {
            if (theme === 'contrast') {
                overlay.className = 'absolute inset-0 bg-gradient-to-b from-black/80 via-black/35 to-black/90 pointer-events-none';
            } else if (theme === 'matcha') {
                overlay.className = 'absolute inset-0 bg-gradient-to-b from-[#063B00]/85 via-black/40 to-[#063B00]/95 pointer-events-none';
            } else if (theme === 'clean') {
                overlay.className = 'absolute inset-0 bg-gradient-to-b from-black/55 via-transparent to-black/75 pointer-events-none';
            }
        }
    }

    // High Resolution Image Generator (9:16 HD Export)
    async function exportAndShareStory(mode = 'share') {
        const card = document.getElementById('storyCardContainer');
        const btnShare = document.getElementById('btnShareStory');
        const btnDownload = document.getElementById('btnDownloadStory');

        if (!card) return;

        // Save original button states
        const origShareHtml = btnShare ? btnShare.innerHTML : '';
        const origDownloadHtml = btnDownload ? btnDownload.innerHTML : '';

        // Button Loading State
        if (mode === 'download' && btnDownload) {
            btnDownload.disabled = true;
            btnDownload.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> <span>Mengunduh HD Story...</span>';
            if (btnShare) btnShare.disabled = true;
        } else if (btnShare) {
            btnShare.disabled = true;
            btnShare.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> <span>Generating HD Story...</span>';
            if (btnDownload) btnDownload.disabled = true;
        }

        const resetButtons = () => {
            if (btnShare) {
                btnShare.disabled = false;
                btnShare.innerHTML = origShareHtml;
            }
            if (btnDownload) {
                btnDownload.disabled = false;
                btnDownload.innerHTML = origDownloadHtml;
            }
        };

        try {
            let blob = null;

            // Method 1: Primary - html2canvas
            const h2c = (typeof html2canvas === 'function') 
                ? html2canvas 
                : (typeof window !== 'undefined' && window.html2canvas ? (typeof window.html2canvas.default === 'function' ? window.html2canvas.default : window.html2canvas) : null);

            if (h2c && typeof h2c === 'function') {
                try {
                    const canvas = await h2c(card, {
                        scale: 2.5,
                        useCORS: true,
                        allowTaint: false,
                        backgroundColor: '#090d10',
                        logging: false,
                    });
                    blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/png', 0.95));
                } catch (e) {
                    console.warn('MATCHA_EXPORT: html2canvas failed, error=', e);
                }
            }

            // Method 2: Fallback htmlToImage
            if (!blob && typeof htmlToImage !== 'undefined' && htmlToImage.toPng) {
                try {
                    const dataUrl = await htmlToImage.toPng(card, {
                        pixelRatio: 2.5,
                        backgroundColor: '#090d10',
                        cacheBust: true,
                        skipFonts: true,
                    });
                    const res = await fetch(dataUrl);
                    blob = await res.blob();
                } catch (e) {
                    console.warn('MATCHA_EXPORT: htmlToImage fallback failed:', e);
                }
            }

            if (!blob) {
                throw new Error('Tidak dapat membuat file gambar story dari browser.');
            }

            const playerNameSlug = '{{ \Illuminate\Support\Str::slug($recap["player"]["name"] ?? "Player") }}';
            const filename = `MATCHA-Career-Recap-${playerNameSlug}.png`;
            const file = new File([blob], filename, { type: 'image/png' });

            const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);

            // Trigger Mobile Native Share sheet if on mobile device
            if (mode === 'share' && isMobile && navigator.canShare && navigator.canShare({ files: [file] })) {
                try {
                    await navigator.share({
                        title: 'MATCHA Career Story',
                        text: 'Cek rekap performa bertanding dan statistik bermainku di MATCHA! 🎾',
                        files: [file],
                    });
                    if (typeof showToast === 'function') {
                        showToast('Story berhasil dibagikan! 🎉');
                    }
                } catch (err) {
                    if (err.name !== 'AbortError') {
                        downloadBlob(blob, filename);
                    }
                }
            } else {
                // Direct Download mode (Desktop or fallback)
                downloadBlob(blob, filename);
            }

            resetButtons();

        } catch (err) {
            console.error('MATCHA_EXPORT_ERROR:', err);
            const errMsg = err && (err.message || err.toString()) ? (err.message || err.toString()) : 'Gagal memproses gambar';
            alert('Gagal membuat gambar story: ' + errMsg);
            resetButtons();
        }
    }

    function downloadBlob(blob, filename) {
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        setTimeout(() => URL.revokeObjectURL(url), 2000);

        if (typeof showToast === 'function') {
            showToast('Gambar story berhasil diunduh! 📥');
        } else {
            alert('Gambar story berhasil diunduh ke perangkatmu!');
        }
    }
</script>
@endpush
@endsection
