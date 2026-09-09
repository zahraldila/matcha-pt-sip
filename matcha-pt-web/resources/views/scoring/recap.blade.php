@extends('layouts.app')

@section('content')
<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
    
    <!-- Top Action Breadcrumbs -->
    <div class="flex items-center justify-between">
        <a href="{{ route('games.index') }}" class="text-xs text-slate-500 hover:text-slate-800 inline-flex items-center gap-1.5 transition-colors">
            <i class="fa-solid fa-arrow-left"></i> Kembali ke Daftar Mabar
        </a>
        <div class="flex items-center gap-2">
            <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-[#EBF8D8] text-[#063B00] border border-[#063B00]/25">
                <i class="fa-solid fa-circle-check text-[#063B00]"></i> Match Finished
            </span>
            <button onclick="shareRecap()" class="px-3 py-1 rounded-xl bg-white border border-slate-200 text-slate-700 text-xs font-semibold hover:bg-slate-50 shadow-2xs transition-colors flex items-center gap-1.5">
                <i class="fa-solid fa-share-nodes text-slate-400"></i> Bagikan
            </button>
        </div>
    </div>

    {{-- Flash message --}}
    @if(session('success'))
    <div class="p-3.5 rounded-2xl bg-[#EBF8D8] border border-[#063B00]/25 text-xs font-semibold text-[#063B00] flex items-center gap-2">
        <i class="fa-solid fa-circle-check"></i> {{ session('success') }}
    </div>
    @endif

    <!-- Match Result Showcase (Glass Hero) -->
    <div class="glass-card rounded-3xl p-6 sm:p-8 text-center relative overflow-hidden border border-white">
        <div class="absolute top-0 right-0 transform translate-x-8 -translate-y-8 w-40 h-40 bg-[#A8E63A]/20 rounded-full blur-2xl pointer-events-none"></div>
        <div class="absolute bottom-0 left-0 transform -translate-x-8 translate-y-8 w-40 h-40 bg-[#EBF8D8]/50 rounded-full blur-2xl pointer-events-none"></div>

        <div class="relative space-y-4">
            @php
                $winnerTeamLabel = 'Team A';
                $winnerNames     = [];
                $loserNames      = [];
                $finalScoreA     = 0;
                $finalScoreB     = 0;

                if ($lastScore) {
                    $finalScoreA = $lastScore['score_a'];
                    $finalScoreB = $lastScore['score_b'];
                    if ($finalScoreA >= $finalScoreB) {
                        $winnerTeamLabel = 'Team A';
                        $winnerNames     = $lastScore['team_a'] ?? [];
                        $loserNames      = $lastScore['team_b'] ?? [];
                    } else {
                        $winnerTeamLabel = 'Team B';
                        $winnerNames     = $lastScore['team_b'] ?? [];
                        $loserNames      = $lastScore['team_a'] ?? [];
                    }
                }
            @endphp

            <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-amber-100/80 border border-amber-200 text-amber-900 text-xs font-bold">
                <i class="fa-solid fa-trophy text-amber-600"></i> Match Winner &bull; {{ $winnerTeamLabel }}
            </div>

            <h1 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">
                {{ $game['title'] ?? 'Padel Weekend Americano' }}
            </h1>
            <p class="text-xs text-slate-500">
                {{ $game['venue_name'] ?? 'GBK Padel Arena' }} &bull; {{ $game['date'] ?? '' }} {{ $game['time'] ?? '' }}
            </p>

            <!-- Scoreboard Big Visual -->
            <div class="grid grid-cols-3 items-center max-w-lg mx-auto py-4 bg-white/70 backdrop-blur-md rounded-2xl border border-slate-200/80 shadow-xs">
                <!-- Team A / Winner -->
                <div class="p-3 text-center space-y-1">
                    <span class="text-[10px] font-bold tracking-wider {{ $winnerTeamLabel === 'Team A' ? 'text-[#063B00]' : 'text-slate-500' }} uppercase">Team A</span>
                    <p class="text-xs sm:text-sm font-black text-slate-900 leading-tight">
                        @forelse($lastScore['team_a'] ?? [] as $pName)
                            {{ $pName }}<br>
                        @empty
                            —
                        @endforelse
                    </p>
                    @if($winnerTeamLabel === 'Team A')
                        <span class="inline-block text-[10px] px-2 py-0.5 rounded-full bg-[#EBF8D8] text-[#063B00] border border-[#063B00]/25 font-bold">WINNER 🏆</span>
                    @else
                        <span class="inline-block text-[10px] px-2 py-0.5 rounded-full bg-slate-100 text-slate-500 font-semibold">Runner-up</span>
                    @endif
                </div>

                <!-- Final Score Points -->
                <div class="text-center space-y-1">
                    <div class="text-3xl sm:text-4xl font-black text-slate-900 tracking-tight">
                        <span class="{{ $finalScoreA >= $finalScoreB ? 'text-[#063B00]' : 'text-slate-400' }}">{{ $finalScoreA }}</span>
                        :
                        <span class="{{ $finalScoreB > $finalScoreA ? 'text-[#063B00]' : 'text-slate-400' }}">{{ $finalScoreB }}</span>
                    </div>
                    <span class="text-[10px] font-semibold text-slate-400">Skor Akhir</span>
                </div>

                <!-- Team B -->
                <div class="p-3 text-center space-y-1">
                    <span class="text-[10px] font-bold tracking-wider {{ $winnerTeamLabel === 'Team B' ? 'text-[#063B00]' : 'text-slate-500' }} uppercase">Team B</span>
                    <p class="text-xs sm:text-sm font-black text-slate-900 leading-tight">
                        @forelse($lastScore['team_b'] ?? [] as $pName)
                            {{ $pName }}<br>
                        @empty
                            —
                        @endforelse
                    </p>
                    @if($winnerTeamLabel === 'Team B')
                        <span class="inline-block text-[10px] px-2 py-0.5 rounded-full bg-[#EBF8D8] text-[#063B00] border border-[#063B00]/25 font-bold">WINNER 🏆</span>
                    @else
                        <span class="inline-block text-[10px] px-2 py-0.5 rounded-full bg-slate-100 text-slate-500 font-semibold">Runner-up</span>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- 🏆 Podium Klasemen Akhir -->
    <div class="glass-card rounded-3xl p-6 sm:p-8 border border-white space-y-5">
        <div class="flex items-center gap-2.5 border-b border-slate-100 pb-4">
            <div class="w-8 h-8 rounded-xl bg-amber-50 border border-amber-100 flex items-center justify-center text-amber-700 text-xs">
                <i class="fa-solid fa-ranking-star"></i>
            </div>
            <div>
                <h2 class="text-sm font-bold text-slate-900">Podium &amp; Klasemen Akhir</h2>
                <p class="text-[11px] text-slate-500">Diurutkan: Poin Menang &rarr; Total Poin &rarr; Selisih Poin</p>
            </div>
        </div>

        {{-- Top 3 Podium Visual --}}
        @if(count($rankedPlayers) >= 1)
        <div class="grid grid-cols-3 gap-3 items-end pb-2">
            
            {{-- Juara 2 (Silver) — kiri --}}
            @php $p2 = $rankedPlayers[1] ?? null; @endphp
            <div class="flex flex-col items-center gap-2">
                @if($p2)
                <div class="text-center space-y-1">
                    @if($p2['avatar'])
                        <img src="{{ $p2['avatar'] }}" class="w-12 h-12 rounded-full object-cover border-2 border-slate-300 mx-auto shadow" alt="{{ $p2['name'] }}">
                    @else
                        <div class="w-12 h-12 rounded-full bg-slate-200 flex items-center justify-center mx-auto text-slate-500 font-bold">{{ mb_substr($p2['name'], 0, 1) }}</div>
                    @endif
                    <p class="text-[11px] font-bold text-slate-700 leading-tight">{{ $p2['name'] }}</p>
                    <p class="text-[10px] text-slate-400">{{ $p2['points_for'] }} pts</p>
                </div>
                <div class="w-full bg-slate-200 rounded-t-xl py-5 text-center">
                    <span class="text-2xl">🥈</span>
                    <p class="text-[10px] font-black text-slate-600 mt-1">2nd</p>
                </div>
                @endif
            </div>

            {{-- Juara 1 (Gold) — tengah, lebih tinggi --}}
            @php $p1 = $rankedPlayers[0] ?? null; @endphp
            <div class="flex flex-col items-center gap-2">
                @if($p1)
                <div class="text-center space-y-1">
                    @if($p1['avatar'])
                        <img src="{{ $p1['avatar'] }}" class="w-14 h-14 rounded-full object-cover border-2 border-amber-400 mx-auto shadow-lg ring-2 ring-amber-200" alt="{{ $p1['name'] }}">
                    @else
                        <div class="w-14 h-14 rounded-full bg-amber-100 flex items-center justify-center mx-auto text-amber-700 font-bold text-lg">{{ mb_substr($p1['name'], 0, 1) }}</div>
                    @endif
                    <p class="text-xs font-black text-slate-900 leading-tight">{{ $p1['name'] }}</p>
                    <span class="inline-block text-[10px] px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 border border-amber-200 font-bold">{{ $p1['points_for'] }} pts</span>
                </div>
                <div class="w-full bg-gradient-to-t from-amber-400 to-amber-300 rounded-t-xl py-8 text-center shadow-md">
                    <span class="text-3xl">🥇</span>
                    <p class="text-[11px] font-black text-amber-900 mt-1">JUARA!</p>
                </div>
                @endif
            </div>

            {{-- Juara 3 (Bronze) — kanan --}}
            @php $p3 = $rankedPlayers[2] ?? null; @endphp
            <div class="flex flex-col items-center gap-2">
                @if($p3)
                <div class="text-center space-y-1">
                    @if($p3['avatar'])
                        <img src="{{ $p3['avatar'] }}" class="w-11 h-11 rounded-full object-cover border-2 border-orange-300 mx-auto shadow" alt="{{ $p3['name'] }}">
                    @else
                        <div class="w-11 h-11 rounded-full bg-orange-100 flex items-center justify-center mx-auto text-orange-700 font-bold">{{ mb_substr($p3['name'], 0, 1) }}</div>
                    @endif
                    <p class="text-[11px] font-bold text-slate-700 leading-tight">{{ $p3['name'] }}</p>
                    <p class="text-[10px] text-slate-400">{{ $p3['points_for'] }} pts</p>
                </div>
                <div class="w-full bg-orange-200 rounded-t-xl py-4 text-center">
                    <span class="text-xl">🥉</span>
                    <p class="text-[10px] font-black text-orange-700 mt-1">3rd</p>
                </div>
                @endif
            </div>
        </div>
        @endif

        {{-- Tabel Ranking Lengkap --}}
        <div class="space-y-2">
            <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider px-1">Ranking Lengkap</p>
            @foreach($rankedPlayers as $player)
            @php
                $isTop3 = $player['rank'] <= 3;
                $medalEmoji = $player['medal']['emoji'];
                $bgClass = match($player['rank']) {
                    1 => 'bg-amber-50 border-amber-200/80',
                    2 => 'bg-slate-50 border-slate-200/80',
                    3 => 'bg-orange-50 border-orange-200/80',
                    default => 'bg-white border-slate-200/70',
                };
            @endphp
            <div class="p-3.5 rounded-2xl {{ $bgClass }} border flex items-center gap-3 shadow-2xs">
                {{-- Rank --}}
                <div class="w-8 text-center shrink-0">
                    @if($medalEmoji)
                        <span class="text-lg leading-none">{{ $medalEmoji }}</span>
                    @else
                        <span class="text-xs font-black text-slate-400">#{{ $player['rank'] }}</span>
                    @endif
                </div>

                {{-- Avatar --}}
                @if($player['avatar'])
                    <img src="{{ $player['avatar'] }}" class="w-8 h-8 rounded-full object-cover border border-slate-200 shrink-0" alt="{{ $player['name'] }}">
                @else
                    <div class="w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center shrink-0 text-slate-500 text-xs font-bold">{{ mb_substr($player['name'], 0, 1) }}</div>
                @endif

                {{-- Nama & Level --}}
                <div class="flex-1 min-w-0">
                    <p class="text-xs font-bold text-slate-900 truncate">{{ $player['name'] }}</p>
                    <p class="text-[10px] text-slate-400">{{ $player['level'] }} &bull; {{ $player['matches'] }} match</p>
                </div>

                {{-- Stats --}}
                <div class="flex items-center gap-3 text-[10px] shrink-0">
                    <div class="text-center">
                        <p class="font-black text-[#063B00] text-sm">{{ $player['wins'] }}</p>
                        <p class="text-slate-400 font-medium">Menang</p>
                    </div>
                    <div class="text-center">
                        <p class="font-black text-slate-700 text-sm">{{ $player['points_for'] }}</p>
                        <p class="text-slate-400 font-medium">Poin</p>
                    </div>
                    <div class="text-center">
                        <p class="font-black text-sm {{ $player['point_diff'] >= 0 ? 'text-[#063B00]' : 'text-rose-600' }}">
                            {{ $player['point_diff'] >= 0 ? '+' : '' }}{{ $player['point_diff'] }}
                        </p>
                        <p class="text-slate-400 font-medium">Selisih</p>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>

    <!-- Kudos & Compliments Giving -->
    <div class="glass-card rounded-3xl p-6 border border-white space-y-4">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-xl bg-amber-50 border border-amber-100 flex items-center justify-center text-amber-700 text-xs">
                    <i class="fa-solid fa-medal"></i>
                </div>
                <div>
                    <h2 class="text-sm font-bold text-slate-900">Beri Kudos untuk Teman Main (Kudos System)</h2>
                    <p class="text-[11px] text-slate-500">Apresiasi skill &amp; sportivitas pemain di lapangan</p>
                </div>
            </div>
            <span class="text-[11px] font-semibold text-slate-400">Pilih badge</span>
        </div>

        <!-- Kudos Grid — render semua peserta kecuali diri sendiri (dummy: kecuali rank 1) -->
        <div class="space-y-3">
            @foreach($rankedPlayers as $player)
            @if($loop->index === 0) @continue @endif {{-- Skip pemain pertama (pov: kamu) --}}
            <div class="p-3.5 rounded-2xl bg-white border border-slate-200/80 flex flex-col sm:flex-row sm:items-center justify-between gap-3 shadow-2xs">
                <div class="flex items-center gap-3">
                    @if($player['avatar'])
                        <img src="{{ $player['avatar'] }}" class="w-8 h-8 rounded-full object-cover border border-slate-200" alt="{{ $player['name'] }}">
                    @else
                        <div class="w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center text-slate-500 text-xs font-bold">{{ mb_substr($player['name'], 0, 1) }}</div>
                    @endif
                    <div>
                        <h4 class="text-xs font-bold text-slate-900">{{ $player['name'] }}</h4>
                        <p class="text-[10px] text-slate-400">{{ $player['medal']['label'] }} &bull; {{ $player['level'] }}</p>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-1.5">
                    @php
                        $kudosBadges = ['🎾 Super Forehand', '🛡️ Solid Defense', '🤝 Fun Partner', '💥 Killer Smash', '⭐ MVP Play', '✨ Fair Play'];
                        $randomBadges = array_slice($kudosBadges, ($loop->index % 3) * 1, 3);
                    @endphp
                    @foreach($randomBadges as $badge)
                    <button type="button" onclick="toggleKudos(this)"
                        class="px-2.5 py-1 rounded-xl text-[11px] font-semibold border border-slate-200 bg-slate-50 text-slate-700 hover:bg-[#EBF8D8] hover:border-[#063B00]/30 hover:text-[#063B00] transition-all cursor-pointer">
                        {{ $badge }}
                    </button>
                    @endforeach
                </div>
            </div>
            @endforeach
        </div>
    </div>

    <!-- Strava-Style Activity Recap Banner -->
    <div class="p-6 rounded-3xl bg-gradient-to-r from-[#063B00] to-[#042a00] text-white flex flex-col sm:flex-row items-center justify-between gap-4 shadow-xl">
        <div class="space-y-1 text-center sm:text-left">
            <span class="text-[10px] font-bold uppercase tracking-widest text-[#A8E63A]">Strava-like Recap Card</span>
            <h3 class="text-lg font-black">Bagikan Kartu Statistik Pertandinganmu</h3>
            <p class="text-xs text-white/80">Posting statistik match ke Instagram Story atau WhatsApp Group komunitasmu.</p>
        </div>
        <a href="{{ route('player.recap') }}" class="px-5 py-2.5 rounded-xl bg-[#A8E63A] hover:bg-[#92d628] text-[#050608] font-extrabold text-xs shadow-md transition-all hover:scale-105 shrink-0">
            Lihat Kartu Recap &rarr;
        </a>
    </div>

</div>

@push('scripts')
<script>
    function toggleKudos(btn) {
        if (btn.classList.contains('bg-[#063B00]')) {
            btn.className = 'px-2.5 py-1 rounded-xl text-[11px] font-semibold border border-slate-200 bg-slate-50 text-slate-700 hover:bg-[#EBF8D8] hover:border-[#063B00]/30 hover:text-[#063B00] transition-all cursor-pointer';
            showToast('Kudos dibatalkan.');
        } else {
            btn.className = 'px-2.5 py-1 rounded-xl text-[11px] font-semibold border border-[#063B00] bg-[#063B00] text-white shadow-xs transition-all cursor-pointer';
            showToast('Kudos berhasil dikirimkan! 🌟');
        }
    }

    function shareRecap() {
        const title  = '{{ addslashes($game['title'] ?? 'Matcha Match') }}';
        const winner = '{{ addslashes($winnerTeamLabel ?? 'Team A') }}';
        if (navigator.share) {
            navigator.share({
                title: `Hasil Pertandingan — ${title}`,
                text : `${winner} menang {{ $finalScoreA ?? 0 }}-{{ $finalScoreB ?? 0 }}! Cek skor selengkapnya di Matcha.`,
                url  : window.location.href
            }).catch(() => {});
        } else {
            navigator.clipboard?.writeText(window.location.href);
            showToast('Link ringkasan pertandingan berhasil disalin ke clipboard!');
        }
    }
</script>
@endpush
@endsection
