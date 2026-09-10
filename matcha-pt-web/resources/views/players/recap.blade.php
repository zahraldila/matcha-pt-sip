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
            @if($isHost && $activeTab === 'host')
                <a href="{{ route('games.create') }}" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-[#063B00] hover:bg-[#042a00] text-white font-semibold text-xs shadow-xs transition-all hover:scale-[1.01] cursor-pointer">
                    <i class="fa-solid fa-plus text-[10px] text-[#A8E63A]"></i> Buat Sesi Mabar Baru
                </a>
            @else
                <button onclick="shareRecap()" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-[#063B00] hover:bg-[#042a00] text-white font-semibold text-xs shadow-xs transition-all hover:scale-[1.01] cursor-pointer">
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
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold {{ $match['result'] === 'WIN' ? 'bg-[#EBF8D8] text-[#063B00] border border-[#063B00]/25' : 'bg-rose-50 text-rose-800 border border-rose-200' }}">
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
                                        $h2hPercent = ($h2h['win'] / $h2h['played']) * 100;
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
                </div>
            </div>
        </div>
    @endif
</div>

@push('scripts')
<script>
    function shareRecap() {
        if (typeof showToast === 'function') {
            showToast('Kartu rekap siap dibagikan! 🚀');
        }
    }
</script>
@endpush
@endsection
