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
            <button onclick="openShareModal()" class="px-3.5 py-1.5 rounded-xl bg-[#063B00] text-white hover:bg-[#042a00] text-xs font-bold shadow-xs hover:shadow-sm transition-all flex items-center gap-2 cursor-pointer">
                <i class="fa-solid fa-share-nodes text-[#A8E63A]"></i> <span>Bagikan</span>
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

        <div class="relative space-y-3">
            @php
                $isSets = $scoringSystem['is_sets'] ?? true;
                $topPlayer = $rankedPlayers[0] ?? null;
            @endphp

            @if($topPlayer)
            <div class="inline-flex items-center gap-1.5 px-3.5 py-1 rounded-full bg-amber-100/80 border border-amber-200 text-amber-900 text-xs font-bold shadow-2xs">
                <i class="fa-solid fa-trophy text-amber-600"></i> Juara 1 &bull; {{ $topPlayer['name'] }}
            </div>
            @else
            <div class="inline-flex items-center gap-1.5 px-3.5 py-1 rounded-full bg-[#EBF8D8] border border-[#063B00]/25 text-[#063B00] text-xs font-bold shadow-2xs">
                <i class="fa-solid fa-circle-check text-[#063B00]"></i> Pertandingan Selesai
            </div>
            @endif

            <h1 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">
                {{ $game['title'] ?? 'Matcha Session' }}
            </h1>
            <p class="text-xs text-slate-500">
                <span class="font-bold text-slate-700">{{ count($game['drawing'] ?? []) }} Ronde Selesai</span> &bull; 
                {{ $game['venue_name'] ?? 'Arena Olahraga' }} &bull; 
                <span class="bg-white/80 px-2 py-0.5 rounded-md border border-slate-200 text-slate-700 font-semibold">{{ $game['sport'] }} &bull; {{ $scoringSystem['label'] }}</span>
            </p>

            <!-- Quick Summary Badges -->
            <div class="flex items-center justify-center gap-2 pt-2 flex-wrap">
                <span class="px-3 py-1 rounded-full bg-white/80 border border-slate-200 text-slate-700 text-xs font-semibold shadow-2xs">
                    <i class="fa-solid fa-users text-slate-400 mr-1"></i> {{ count($rankedPlayers) }} Peserta
                </span>
                <span class="px-3 py-1 rounded-full bg-white/80 border border-slate-200 text-slate-700 text-xs font-semibold shadow-2xs">
                    <i class="fa-solid fa-flag-checkered text-slate-400 mr-1"></i> {{ count($game['drawing'] ?? []) }} Ronde
                </span>
                <span class="px-3 py-1 rounded-full bg-[#EBF8D8] border border-[#063B00]/25 text-[#063B00] text-xs font-bold shadow-2xs">
                    <i class="fa-solid fa-circle-check mr-1 text-[#063B00]"></i> Rekap Final Selesai
                </span>
            </div>
        </div>
    </div>

    {{-- 📋 Riwayat Pertandingan Seluruh Ronde --}}
    @if(!empty($game['drawing']))
    <div class="glass-card rounded-3xl p-6 border border-white space-y-4">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-xl bg-slate-100 border border-slate-200 flex items-center justify-center text-slate-700 text-xs">
                    <i class="fa-solid fa-list-ol"></i>
                </div>
                <div>
                    <h2 class="text-sm font-bold text-slate-900">Riwayat Hasil Pertandingan Seluruh Ronde</h2>
                    <p class="text-[11px] text-slate-500">Rekapitulasi skor tiap match drawing dalam sesi ini</p>
                </div>
            </div>
            <span class="text-[11px] font-semibold text-slate-500">
                {{ count($game['drawing']) }} Ronde
            </span>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            @foreach($game['drawing'] as $rKey => $rData)
            @php
                $roundMatches = !empty($rData['matches']) ? $rData['matches'] : [
                    [
                        'court' => 1,
                        'court_name' => 'Court 1',
                        'team_a' => $rData['team_a'] ?? [],
                        'team_b' => $rData['team_b'] ?? [],
                        'team_a_names' => $rData['team_a'] ?? [],
                        'team_b_names' => $rData['team_b'] ?? [],
                    ]
                ];
                $rTitle = ucfirst(str_replace('_', ' ', $rKey));
            @endphp

            @foreach($roundMatches as $mIdx => $m)
            @php
                $mCourt = $m['court'] ?? ($mIdx + 1);
                $mCourtName = $m['court_name'] ?? "Court {$mCourt}";
                $mKey = "{$rKey}_court_{$mCourt}";
                $mScore = $effectiveScores[$mKey] ?? ($mIdx === 0 ? ($effectiveScores[$rKey] ?? []) : []);
                $isDone = ($mScore['status'] ?? '') === 'completed';
                $mSetsHist = $mScore['set_history'] ?? [];
                $mWinner = $mScore['winner_team'] ?? null;
                if ($isDone && !$mWinner) {
                    if ($isSets) {
                        $mWinner = ($mScore['sets_a'] ?? 0) >= ($mScore['sets_b'] ?? 0) ? 'Team A' : 'Team B';
                    } else {
                        $mWinner = ($mScore['games_a'] ?? 0) >= ($mScore['games_b'] ?? 0) ? 'Team A' : 'Team B';
                    }
                }
                $mTeamA = $m['team_a_names'] ?? ($m['teamA_names'] ?? ($m['team_a'] ?? []));
                $mTeamB = $m['team_b_names'] ?? ($m['teamB_names'] ?? ($m['team_b'] ?? []));
            @endphp
            <div class="p-4 rounded-2xl bg-white border border-slate-200/80 shadow-2xs space-y-2.5">
                <div class="flex items-center justify-between text-xs">
                    <span class="font-black text-slate-800 flex items-center gap-1.5">
                        <i class="fa-solid fa-flag-checkered text-slate-400"></i> {{ $rTitle }} 
                        @if(count($roundMatches) > 1)
                            <span class="text-[10px] text-slate-500 font-semibold">({{ $mCourtName }})</span>
                        @endif
                    </span>
                    @if($isDone)
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-[#EBF8D8] text-[#063B00] border border-[#063B00]/20">
                            Selesai
                        </span>
                    @else
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-slate-100 text-slate-500 border border-slate-200">
                            Belum Dimainkan
                        </span>
                    @endif
                </div>

                <div class="space-y-1.5 text-xs">
                    <!-- Team A -->
                    <div class="flex items-center justify-between p-2 rounded-xl {{ $isDone && $mWinner === 'Team A' ? 'bg-[#EBF8D8]/50 font-bold' : 'bg-slate-50' }}">
                        <div class="flex items-center gap-2 truncate pr-2">
                            <span class="w-1.5 h-1.5 rounded-full {{ $isDone && $mWinner === 'Team A' ? 'bg-[#063B00]' : 'bg-slate-300' }}"></span>
                            <span class="truncate text-slate-800">{{ implode(' & ', is_array($mTeamA) ? $mTeamA : [$mTeamA]) }}</span>
                            @if($isDone && $mWinner === 'Team A')
                                <span class="text-[9px] px-1 rounded bg-[#EBF8D8] text-[#063B00] font-black shrink-0">WIN</span>
                            @endif
                        </div>
                        <span class="font-black text-sm {{ $isDone && $mWinner === 'Team A' ? 'text-[#063B00]' : 'text-slate-600' }} shrink-0">
                            @if($isDone)
                                {{ $isSets ? ($mScore['sets_a'] ?? 0) : ($mScore['games_a'] ?? $mScore['score_a'] ?? 0) }}
                            @else
                                -
                            @endif
                        </span>
                    </div>

                    <!-- Team B -->
                    <div class="flex items-center justify-between p-2 rounded-xl {{ $isDone && $mWinner === 'Team B' ? 'bg-[#EBF8D8]/50 font-bold' : 'bg-slate-50' }}">
                        <div class="flex items-center gap-2 truncate pr-2">
                            <span class="w-1.5 h-1.5 rounded-full {{ $isDone && $mWinner === 'Team B' ? 'bg-[#063B00]' : 'bg-slate-300' }}"></span>
                            <span class="truncate text-slate-800">{{ implode(' & ', is_array($mTeamB) ? $mTeamB : [$mTeamB]) }}</span>
                            @if($isDone && $mWinner === 'Team B')
                                <span class="text-[9px] px-1 rounded bg-[#EBF8D8] text-[#063B00] font-black shrink-0">WIN</span>
                            @endif
                        </div>
                        <span class="font-black text-sm {{ $isDone && $mWinner === 'Team B' ? 'text-[#063B00]' : 'text-slate-600' }} shrink-0">
                            @if($isDone)
                                {{ $isSets ? ($mScore['sets_b'] ?? 0) : ($mScore['games_b'] ?? $mScore['score_b'] ?? 0) }}
                            @else
                                -
                            @endif
                        </span>
                    </div>
                </div>

                @if($isDone && $isSets && !empty($mSetsHist))
                <div class="pt-2 border-t border-slate-100 flex items-center gap-1.5 flex-wrap">
                    <span class="text-[10px] font-bold text-slate-400">Rincian:</span>
                    @foreach($mSetsHist as $sh)
                    <span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-slate-100 text-slate-700">
                        Set {{ $sh['set'] }}: {{ $sh['score_a'] }}-{{ $sh['score_b'] }}
                    </span>
                    @endforeach
                </div>
                @endif
            </div>
            @endforeach
            @endforeach
        </div>
    </div>
    @endif

    <!-- 🏆 Podium Klasemen Akhir -->
    <div class="glass-card rounded-3xl p-6 sm:p-8 border border-white space-y-5">
        <div class="flex items-center gap-2.5 border-b border-slate-100 pb-4">
            <div class="w-8 h-8 rounded-xl bg-amber-50 border border-amber-100 flex items-center justify-center text-amber-700 text-xs">
                <i class="fa-solid fa-ranking-star"></i>
            </div>
            <div>
                <h2 class="text-sm font-bold text-slate-900">Podium &amp; Klasemen Akhir</h2>
                <p class="text-[11px] text-slate-500">
                    @if($isSets)
                        Diurutkan: Match Menang &rarr; Total Set &rarr; Total Game &rarr; Selisih Game
                    @else
                        Diurutkan: Match Menang &rarr; Total Game Menang &rarr; Selisih Game
                    @endif
                </p>
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
                    <p class="text-[10px] text-slate-400">
                        @if($isSets)
                            {{ $p2['sets_won'] }} Sets &bull; {{ $p2['games_won'] }} Games
                        @else
                            {{ $p2['games_won'] }} Games Won
                        @endif
                    </p>
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
                    <span class="inline-block text-[10px] px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 border border-amber-200 font-bold">
                        @if($isSets)
                            {{ $p1['sets_won'] }} Sets &bull; {{ $p1['games_won'] }} Games
                        @else
                            {{ $p1['games_won'] }} Games Won
                        @endif
                    </span>
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
                    <p class="text-[10px] text-slate-400">
                        @if($isSets)
                            {{ $p3['sets_won'] }} Sets &bull; {{ $p3['games_won'] }} Games
                        @else
                            {{ $p3['games_won'] }} Games Won
                        @endif
                    </p>
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
            <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider px-1">Ranking &amp; Statistik</p>
            @foreach($rankedPlayers as $player)
            @php
                $isTop3 = $player['rank'] <= 3;
                $medalEmoji = $player['medal']['emoji'] ?? null;
                $bgClass = match($player['rank'] ?? 99) {
                    1 => 'bg-amber-50/70 border-amber-200/80',
                    2 => 'bg-slate-50/70 border-slate-200/80',
                    3 => 'bg-orange-50/70 border-orange-200/80',
                    default => 'bg-white border-slate-200/70',
                };
            @endphp
            <div class="p-2.5 sm:p-3.5 rounded-2xl {{ $bgClass }} border flex items-center gap-2 sm:gap-3 shadow-2xs">
                {{-- Rank --}}
                <div class="w-6 sm:w-8 text-center shrink-0">
                    @if($medalEmoji)
                        <span class="text-base sm:text-lg leading-none">{{ $medalEmoji }}</span>
                    @else
                        <span class="text-[10px] sm:text-xs font-black text-slate-400">#{{ $player['rank'] }}</span>
                    @endif
                </div>

                {{-- Avatar --}}
                @if(!empty($player['avatar']))
                    <img src="{{ $player['avatar'] }}" class="w-7 h-7 sm:w-8 sm:h-8 rounded-full object-cover border border-slate-200 shrink-0" alt="{{ $player['name'] }}">
                @else
                    <div class="w-7 h-7 sm:w-8 sm:h-8 rounded-full bg-slate-100 flex items-center justify-center shrink-0 text-slate-500 text-[10px] sm:text-xs font-bold">{{ mb_substr($player['name'], 0, 1) }}</div>
                @endif

                {{-- Nama & Level --}}
                <div class="flex-1 min-w-0">
                    <p class="text-xs font-bold text-slate-900 truncate">{{ $player['name'] }}</p>
                    <p class="text-[9px] sm:text-[10px] text-slate-400 truncate">{{ $player['level'] }} &bull; {{ $player['matches'] }} match</p>
                </div>

                {{-- Stats Columns --}}
                <div class="flex items-center gap-1 sm:gap-3 text-[9px] sm:text-[10px] shrink-0">
                    <div class="text-center min-w-[26px] sm:min-w-[36px]">
                        <p class="font-black text-[#063B00] text-xs sm:text-sm">{{ $player['wins'] }}</p>
                        <p class="text-slate-400 font-medium text-[8px] sm:text-[10px]">Win</p>
                    </div>

                    @if($isSets)
                    <div class="text-center min-w-[26px] sm:min-w-[36px]">
                        <p class="font-black text-slate-700 text-xs sm:text-sm">{{ $player['sets_won'] }}</p>
                        <p class="text-slate-400 font-medium text-[8px] sm:text-[10px]">Sets</p>
                    </div>
                    @endif

                    <div class="text-center min-w-[26px] sm:min-w-[36px]">
                        <p class="font-black text-slate-700 text-xs sm:text-sm">{{ $player['games_won'] }}</p>
                        <p class="text-slate-400 font-medium text-[8px] sm:text-[10px]">Games</p>
                    </div>

                    <div class="text-center min-w-[28px] sm:min-w-[40px]">
                        @php $diff = $isSets ? $player['game_diff'] : $player['game_diff']; @endphp
                        <p class="font-black text-xs sm:text-sm {{ $diff >= 0 ? 'text-[#063B00]' : 'text-rose-600' }}">
                            {{ $diff >= 0 ? '+' : '' }}{{ $diff }}
                        </p>
                        <p class="text-slate-400 font-medium text-[8px] sm:text-[10px]">Selisih</p>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>

    <!-- Kudos & Compliments Giving (Untuk Seluruh Player) -->
    <div class="glass-card rounded-3xl p-6 border border-white space-y-4">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-xl bg-amber-50 border border-amber-100 flex items-center justify-center text-amber-700 text-xs">
                    <i class="fa-solid fa-medal"></i>
                </div>
                <div>
                    <h2 class="text-sm font-bold text-slate-900">Beri Kudos untuk Seluruh Pemain (Kudos System)</h2>
                    <p class="text-[11px] text-slate-500">Apresiasi skill &amp; sportivitas seluruh pemain di lapangan</p>
                </div>
            </div>
            <span class="text-[11px] font-semibold text-slate-400">Pilih badge</span>
        </div>

        <!-- Kudos Grid -->
        <div class="space-y-3">
            @php
                $allKudosPlayers = !empty($rankedPlayers) ? $rankedPlayers : ($game['participants'] ?? []);
                $kudosBadges = ['🎾 Super Forehand', '🛡️ Solid Defense', '🤝 Fun Partner', '💥 Killer Smash', '⭐ MVP Play', '✨ Fair Play'];
            @endphp
            @foreach($allKudosPlayers as $player)
            <div class="p-3.5 rounded-2xl bg-white border border-slate-200/80 flex flex-col sm:flex-row sm:items-center justify-between gap-3 shadow-2xs hover:border-[#063B00]/20 transition-all">
                <div class="flex items-center gap-3">
                    @if(!empty($player['avatar']))
                        <img src="{{ $player['avatar'] }}" class="w-9 h-9 rounded-full object-cover border border-slate-200 shrink-0" alt="{{ $player['name'] }}">
                    @else
                        <div class="w-9 h-9 rounded-full bg-slate-100 flex items-center justify-center shrink-0 text-slate-600 text-xs font-bold">{{ mb_substr($player['name'] ?? 'P', 0, 1) }}</div>
                    @endif
                    <div>
                        <div class="flex items-center gap-1.5">
                            <h4 class="text-xs font-bold text-slate-900">{{ $player['name'] }}</h4>
                            @if(!empty($player['rank']) && $player['rank'] <= 3)
                                <span class="text-[10px]">{{ $player['medal']['emoji'] ?? '' }}</span>
                            @endif
                        </div>
                        <p class="text-[10px] text-slate-400">
                            @if(!empty($player['medal']['label']))
                                {{ $player['medal']['label'] }} &bull; 
                            @endif
                            {{ $player['level'] ?? 'Player' }}
                        </p>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-1.5">
                    @foreach($kudosBadges as $badge)
                    <button type="button" onclick="toggleKudos(this, '{{ addslashes($player['name']) }}')"
                        class="px-2.5 py-1 rounded-xl text-[11px] font-semibold border border-slate-200 bg-slate-50 text-slate-700 hover:bg-[#EBF8D8] hover:border-[#063B00]/30 hover:text-[#063B00] transition-all cursor-pointer">
                        {{ $badge }}
                    </button>
                    @endforeach
                </div>
            </div>
            @endforeach
        </div>
    </div>

    <!-- Strava-like Player Stats Card -->
    <div class="glass-card rounded-3xl p-6 sm:p-8 border border-white space-y-5">
        <div class="flex items-center justify-between border-b border-slate-100 pb-4">
            <div class="flex items-center gap-3">
                @if(!empty($playerRecap['avatar']))
                    <img src="{{ $playerRecap['avatar'] }}" class="w-12 h-12 rounded-full object-cover border-2 border-[#063B00] shadow" alt="{{ $playerRecap['player_name'] }}">
                @endif
                <div>
                    <h3 class="text-sm font-bold text-slate-900">{{ $playerRecap['player_name'] ?? 'Pemain Matcha' }}</h3>
                    <p class="text-xs text-slate-500">Statistik Pertandingan Hari Ini &bull; {{ $game['sport'] }}</p>
                </div>
            </div>
            <span class="px-3 py-1 rounded-full bg-[#EBF8D8] text-[#063B00] text-xs font-bold border border-[#063B00]/20">
                Level {{ $playerRecap['level'] ?? 'Intermediate' }}
            </span>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <div class="p-3.5 rounded-2xl bg-white/70 border border-slate-200/80 text-center shadow-2xs">
                <span class="text-xs text-slate-400 font-medium block">Total Poin</span>
                <span class="text-xl font-black text-slate-900">{{ $playerRecap['total_points'] ?? 24 }}</span>
            </div>
            <div class="p-3.5 rounded-2xl bg-white/70 border border-slate-200/80 text-center shadow-2xs">
                <span class="text-xs text-slate-400 font-medium block">Durasi Main</span>
                <span class="text-xl font-black text-slate-900">{{ $playerRecap['duration_played'] ?? '1j 45m' }}</span>
            </div>
            <div class="p-3.5 rounded-2xl bg-white/70 border border-slate-200/80 text-center shadow-2xs">
                <span class="text-xs text-slate-400 font-medium block">Kalori Terbakar</span>
                <span class="text-xl font-black text-slate-900">{{ $playerRecap['calories_burned'] ?? 520 }} <span class="text-xs font-normal text-slate-500">kcal</span></span>
            </div>
            <div class="p-3.5 rounded-2xl bg-white/70 border border-slate-200/80 text-center shadow-2xs">
                <span class="text-xs text-slate-400 font-medium block">Win Rate</span>
                <span class="text-xl font-black text-[#063B00]">{{ $playerRecap['win_rate'] ?? '75%' }}</span>
            </div>
        </div>
    </div>

</div>

<!-- ========================================================= -->
<!-- 1. MODAL: SHARE GAME OPTIONS (GAYA SKOR REFERENSI) -->
<!-- ========================================================= -->
<div id="shareOptionsModal" class="fixed inset-0 z-[100] hidden bg-slate-950/60 backdrop-blur-sm flex items-center justify-center p-4 transition-all">
    <div class="bg-white rounded-3xl max-w-md w-full p-6 sm:p-7 space-y-6 relative border border-slate-200 shadow-2xl animate-in fade-in zoom-in duration-200">
        <!-- Close Button -->
        <button onclick="closeShareModal()" class="absolute top-5 right-5 w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-500 flex items-center justify-center transition-colors cursor-pointer">
            <i class="fa-solid fa-xmark text-sm"></i>
        </button>

        <!-- Header -->
        <div class="space-y-1 pr-6">
            <div class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-[#EBF8D8] text-[#063B00] text-[10px] font-bold border border-[#063B00]/20">
                <i class="fa-solid fa-share-nodes"></i> Share Game
            </div>
            <h3 class="text-xl font-black text-slate-900 tracking-tight">Bagikan Hasil Pertandingan</h3>
            <p class="text-xs text-slate-500">Rayakan serunya momen mabar dan kemenangan bersama teman atau komunitasmu!</p>
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
                        <p class="text-[11px] text-slate-500 leading-relaxed">Tampilkan hasil pertandingan lengkap dalam format web. Cocok untuk grup WhatsApp / Telegram.</p>
                    </div>
                </div>
                <div class="flex items-center gap-2 pt-1">
                    <button onclick="copyWebLink()" class="flex-1 py-2 rounded-xl bg-white border border-slate-200 hover:bg-slate-100 text-slate-700 text-xs font-bold transition-all flex items-center justify-center gap-1.5 cursor-pointer shadow-2xs">
                        <i class="fa-solid fa-copy text-slate-400"></i> Salin Link
                    </button>
                    <button onclick="shareWebDirect()" class="flex-1 py-2 rounded-xl bg-[#063B00] hover:bg-[#042a00] text-white text-xs font-bold transition-all flex items-center justify-center gap-1.5 cursor-pointer shadow-2xs">
                        <i class="fa-solid fa-paper-plane text-[#A8E63A]"></i> Bagikan Link
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
                        <p class="text-[11px] text-slate-600 leading-relaxed">Ubah hasil mabar jadi kartu gambar story ala Strava/SKOR dengan foto dari galerimu!</p>
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
<!-- 2. MODAL: TEMPLATE STUDIO (9:16 STORY CUSTOMIZER - LIGHT MODE) -->
<!-- ========================================================= -->
<div id="templateStudioModal" class="fixed inset-0 z-[100] hidden bg-slate-950/70 backdrop-blur-md overflow-y-auto p-2 sm:p-6 pb-24 sm:pb-8 transition-all flex items-start sm:items-center justify-center">
    <div class="max-w-4xl w-full bg-white rounded-3xl border border-slate-200 shadow-2xl text-slate-900 overflow-hidden my-3 sm:my-auto animate-in fade-in zoom-in duration-200">
        
        <!-- Studio Header (Clean & Light) -->
        <div class="px-5 sm:px-6 py-3.5 sm:py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/80">
            <div class="space-y-0.5">
                <div class="flex items-center gap-2">
                    <h3 class="text-sm sm:text-base font-black text-slate-900">Select Template Story</h3>
                    <span class="text-[9px] sm:text-[10px] font-extrabold px-2 py-0.5 rounded-full bg-[#EBF8D8] text-[#063B00] border border-[#063B00]/20">9:16 HD</span>
                </div>
                <p class="text-[10px] sm:text-[11px] text-slate-500">Pilih template, pasang foto dokumentasi mabar dari galeri, dan unduh/bagikan.</p>
            </div>
            <!-- Single Clean Close Button -->
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
                    <!-- TEMPLATE 1: MINIMALIST PODIUM (BOTTOM OVERLAY) -->
                    <!-- ---------------------------------------------------- -->
                    <div id="tpl_podium" class="template-view relative z-10 h-full flex flex-col justify-between">
                        <!-- Top Header -->
                        <div class="flex items-center justify-between">
                            <div>
                                <h4 class="text-xs font-black tracking-tight text-white truncate max-w-[160px]">{{ $game['title'] ?? 'Matcha Session' }}</h4>
                                <p class="text-[9px] text-[#A8E63A] font-bold flex items-center gap-1">
                                    <span>{{ count($rankedPlayers) }} Players</span> &bull; 
                                    <span>{{ count($game['drawing'] ?? []) }} Rounds</span> &bull; 
                                    <span>{{ $scoringSystem['label'] }}</span>
                                </p>
                            </div>
                            <!-- Matcha Brand Logo -->
                            <div class="flex items-center gap-1.5 bg-black/40 backdrop-blur-md px-2 py-1 rounded-xl border border-white/10">
                                <img src="{{ asset('images/logo.svg') }}" class="w-5 h-5" alt="Matcha">
                                <span class="text-[10px] font-black tracking-wider text-white">MATCHA</span>
                            </div>
                        </div>

                        <!-- Bottom Frosted Podium Overlay (With Match Results Tag Right on Top) -->
                        <div class="space-y-1.5 mt-auto">
                            <div class="text-center">
                                <span class="inline-flex items-center gap-1.5 px-3 py-0.5 rounded-full bg-[#EBF8D8]/20 border border-[#A8E63A]/40 text-[#A8E63A] text-[9px] font-black backdrop-blur-md shadow-xs">
                                    <i class="fa-solid fa-trophy text-amber-400"></i> Match Results
                                </span>
                            </div>

                            <div class="bg-black/65 backdrop-blur-xl border border-white/15 rounded-2xl p-2.5 sm:p-3 shadow-2xl space-y-2">
                                @php
                                    $p1 = $rankedPlayers[0] ?? null;
                                    $p2 = $rankedPlayers[1] ?? null;
                                    $p3 = $rankedPlayers[2] ?? null;
                                @endphp
                                
                                <div class="grid grid-cols-3 gap-1.5 items-end pt-1">
                                    <!-- 2nd Place -->
                                    <div class="text-center space-y-1">
                                        @if($p2)
                                        <div class="relative inline-block">
                                            <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-full bg-slate-700 border-2 border-slate-300 mx-auto flex items-center justify-center text-xs font-bold text-white shadow">
                                                {{ mb_substr($p2['name'], 0, 1) }}
                                            </div>
                                            <span class="absolute -top-1.5 -right-1 w-4 h-4 rounded-full bg-slate-300 text-slate-900 text-[8px] font-black flex items-center justify-center shadow">2</span>
                                        </div>
                                        <p class="text-[10px] font-bold text-slate-200 truncate">{{ $p2['name'] }}</p>
                                        <p class="text-[8px] text-slate-400 font-semibold">{{ $p2['wins'] }}-{{ $p2['losses'] }}-0 &bull; {{ $p2['game_diff'] >= 0 ? '+' : '' }}{{ $p2['game_diff'] }}</p>
                                        <span class="inline-block px-1.5 py-0.5 rounded bg-slate-800 text-slate-200 text-[9px] font-black border border-slate-700">{{ $p2['points_for'] ?? $p2['games_won'] }} pts</span>
                                        @endif
                                    </div>

                                    <!-- 1st Place (Center / Taller) -->
                                    <div class="text-center space-y-1">
                                        @if($p1)
                                        <div class="relative inline-block">
                                            <div class="w-11 h-11 sm:w-12 sm:h-12 rounded-full bg-amber-600/60 border-2 border-amber-400 mx-auto flex items-center justify-center text-sm font-black text-white shadow-lg ring-2 ring-amber-400/40">
                                                {{ mb_substr($p1['name'], 0, 1) }}
                                            </div>
                                            <span class="absolute -top-2 -right-1 w-5 h-5 rounded-full bg-gradient-to-tr from-amber-500 to-amber-300 text-amber-950 text-[9px] font-black flex items-center justify-center shadow">🥇</span>
                                        </div>
                                        <p class="text-[11px] font-black text-amber-300 truncate">{{ $p1['name'] }}</p>
                                        <p class="text-[8px] text-amber-200/80 font-bold">{{ $p1['wins'] }}-{{ $p1['losses'] }}-0 &bull; {{ $p1['game_diff'] >= 0 ? '+' : '' }}{{ $p1['game_diff'] }}</p>
                                        <span class="inline-block px-2 py-0.5 rounded bg-amber-400 text-amber-950 text-[10px] font-black shadow">{{ $p1['points_for'] ?? $p1['games_won'] }} pts</span>
                                        @endif
                                    </div>

                                    <!-- 3rd Place -->
                                    <div class="text-center space-y-1">
                                        @if($p3)
                                        <div class="relative inline-block">
                                            <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-full bg-orange-950 border-2 border-orange-400 mx-auto flex items-center justify-center text-xs font-bold text-white shadow">
                                                {{ mb_substr($p3['name'], 0, 1) }}
                                            </div>
                                            <span class="absolute -top-1.5 -right-1 w-4 h-4 rounded-full bg-orange-400 text-orange-950 text-[8px] font-black flex items-center justify-center shadow">3</span>
                                        </div>
                                        <p class="text-[10px] font-bold text-slate-200 truncate">{{ $p3['name'] }}</p>
                                        <p class="text-[8px] text-slate-400 font-semibold">{{ $p3['wins'] }}-{{ $p3['losses'] }}-0 &bull; {{ $p3['game_diff'] >= 0 ? '+' : '' }}{{ $p3['game_diff'] }}</p>
                                        <span class="inline-block px-1.5 py-0.5 rounded bg-slate-800 text-slate-200 text-[9px] font-black border border-slate-700">{{ $p3['points_for'] ?? $p3['games_won'] }} pts</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ---------------------------------------------------- -->
                    <!-- TEMPLATE 2: GLASS LEADERBOARD CARD (TOP OVERLAY) -->
                    <!-- ---------------------------------------------------- -->
                    <div id="tpl_leaderboard" class="template-view relative z-10 h-full flex flex-col justify-between hidden">
                        <!-- Top Header & Brand -->
                        <div class="flex items-center justify-between pb-2 border-b border-white/10">
                            <div class="flex items-center gap-2">
                                <img src="{{ asset('images/logo.svg') }}" class="w-6 h-6" alt="Matcha">
                                <div>
                                    <span class="text-xs font-black tracking-wider text-white block">MATCHA</span>
                                    <span class="text-[8px] font-bold text-[#A8E63A] uppercase tracking-widest block -mt-0.5">LEADERBOARD</span>
                                </div>
                            </div>
                            <span class="text-[9px] font-extrabold px-2 py-0.5 rounded bg-[#063B00] text-[#A8E63A] border border-[#A8E63A]/30">
                                {{ $game['sport'] }}
                            </span>
                        </div>

                        <!-- Leaderboard Glass Table Card -->
                        <div class="bg-black/65 backdrop-blur-xl border border-white/15 rounded-2xl p-2.5 sm:p-3 shadow-2xl space-y-1.5 my-auto">
                            <div class="flex items-center justify-between text-[9px] font-extrabold text-slate-400 px-2 pb-1 border-b border-white/10">
                                <div class="w-6 text-left">POS</div>
                                <div class="flex-1 text-left">PLAYER</div>
                                <div class="w-10 text-center">W-L</div>
                                <div class="w-8 text-center">DIFF</div>
                                <div class="w-10 text-right">PTS</div>
                            </div>

                            @foreach(array_slice($rankedPlayers, 0, 4) as $idx => $rp)
                            @php
                                $isFirst = $idx === 0;
                                $rowBg = $isFirst ? 'bg-amber-400/20 border border-amber-400/40 text-amber-200' : 'bg-white/5 border border-white/5 text-white';
                            @endphp
                            <div class="flex items-center justify-between p-1.5 rounded-xl {{ $rowBg }} text-[10px]">
                                <div class="w-6 font-black text-center">
                                    @if($idx === 0) 🥇
                                    @elseif($idx === 1) 🥈
                                    @elseif($idx === 2) 🥉
                                    @else #{{ $idx + 1 }}
                                    @endif
                                </div>
                                <div class="flex-1 flex items-center gap-1.5 truncate px-1 font-bold">
                                    <div class="w-5 h-5 rounded-full bg-slate-700 flex items-center justify-center text-[9px] shrink-0">
                                        {{ mb_substr($rp['name'], 0, 1) }}
                                    </div>
                                    <span class="truncate">{{ $rp['name'] }}</span>
                                </div>
                                <div class="w-10 text-center font-semibold text-[9px] text-slate-300">
                                    {{ $rp['wins'] }}-{{ $rp['losses'] }}
                                </div>
                                <div class="w-8 text-center font-black text-[9px] {{ $rp['game_diff'] >= 0 ? 'text-[#A8E63A]' : 'text-rose-400' }}">
                                    {{ $rp['game_diff'] >= 0 ? '+' : '' }}{{ $rp['game_diff'] }}
                                </div>
                                <div class="w-10 text-right font-black text-xs text-white">
                                    {{ $rp['points_for'] ?? $rp['games_won'] }}
                                </div>
                            </div>
                            @endforeach
                        </div>

                        <!-- Bottom Watermark -->
                        <div class="pt-2 text-center border-t border-white/10">
                            <p class="text-[10px] font-black text-white truncate">{{ $game['title'] ?? 'Matcha Session' }}</p>
                            <p class="text-[8px] text-slate-400">{{ $game['venue_name'] ?? 'Arena Olahraga' }} &bull; Matcha Match Arena</p>
                        </div>
                    </div>

                    <!-- ---------------------------------------------------- -->
                    <!-- TEMPLATE 3: MATCH HIGHLIGHTS & SCORES -->
                    <!-- ---------------------------------------------------- -->
                    <div id="tpl_matches" class="template-view relative z-10 h-full flex flex-col justify-between hidden">
                        <!-- Top Header -->
                        <div class="flex items-center justify-between pb-2 border-b border-white/10">
                            <div class="flex items-center gap-2">
                                <img src="{{ asset('images/logo.svg') }}" class="w-6 h-6" alt="Matcha">
                                <div>
                                    <h4 class="text-xs font-black text-white truncate max-w-[150px]">{{ $game['title'] ?? 'Matcha Session' }}</h4>
                                    <p class="text-[8px] text-[#A8E63A] font-bold">Match Recap Highlights</p>
                                </div>
                            </div>
                            <span class="text-[9px] font-extrabold px-2 py-0.5 rounded bg-white/10 text-slate-200 border border-white/15">
                                {{ count($game['drawing'] ?? []) }} Rounds
                            </span>
                        </div>

                        <!-- Match Result Cards List -->
                        <div class="space-y-2 my-auto">
                            @php
                                $sampleDrawing = array_slice($game['drawing'] ?? [], 0, 3, true);
                            @endphp
                            @foreach($sampleDrawing as $rKey => $rData)
                            @php
                                $rTitle = ucfirst(str_replace('_', ' ', $rKey));
                                $mScore = $effectiveScores[$rKey] ?? [];
                                $isDone = ($mScore['status'] ?? '') === 'completed';
                                $mWinner = $mScore['winner_team'] ?? 'Team A';
                                $teamA = $rData['team_a'] ?? ['Team A'];
                                $teamB = $rData['team_b'] ?? ['Team B'];
                                $scoreStrA = $isSets ? ($mScore['sets_a'] ?? 0) : ($mScore['games_a'] ?? $mScore['score_a'] ?? 0);
                                $scoreStrB = $isSets ? ($mScore['sets_b'] ?? 0) : ($mScore['games_b'] ?? $mScore['score_b'] ?? 0);
                            @endphp
                            <div class="bg-black/65 backdrop-blur-xl border border-white/15 rounded-xl p-2 sm:p-2.5 shadow-md space-y-1.5">
                                <div class="flex items-center justify-between text-[9px] font-black text-[#A8E63A]">
                                    <span><i class="fa-solid fa-flag-checkered mr-1"></i> {{ $rTitle }}</span>
                                    <span class="text-slate-400 font-semibold">Court 1</span>
                                </div>
                                <div class="grid grid-cols-2 gap-2 text-[10px]">
                                    <!-- Team A -->
                                    <div class="p-1.5 rounded-lg {{ $mWinner === 'Team A' ? 'bg-[#063B00]/80 border border-[#A8E63A]/40' : 'bg-white/5' }} flex items-center justify-between">
                                        <span class="truncate font-bold text-slate-200 text-[9px]">{{ implode(' & ', is_array($teamA) ? $teamA : [$teamA]) }}</span>
                                        <span class="font-black text-xs {{ $mWinner === 'Team A' ? 'text-[#A8E63A]' : 'text-slate-400' }} ml-1">{{ $scoreStrA }}</span>
                                    </div>
                                    <!-- Team B -->
                                    <div class="p-1.5 rounded-lg {{ $mWinner === 'Team B' ? 'bg-[#063B00]/80 border border-[#A8E63A]/40' : 'bg-white/5' }} flex items-center justify-between">
                                        <span class="truncate font-bold text-slate-200 text-[9px]">{{ implode(' & ', is_array($teamB) ? $teamB : [$teamB]) }}</span>
                                        <span class="font-black text-xs {{ $mWinner === 'Team B' ? 'text-[#A8E63A]' : 'text-slate-400' }} ml-1">{{ $scoreStrB }}</span>
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        </div>

                        <!-- Footer -->
                        <div class="pt-2 text-center border-t border-white/10">
                            <span class="text-[9px] font-bold text-[#A8E63A]"><i class="fa-solid fa-trophy mr-1"></i> Winner: {{ $rankedPlayers[0]['name'] ?? 'Champion' }}</span>
                        </div>
                    </div>

                    <!-- ---------------------------------------------------- -->
                    <!-- TEMPLATE 4: STRAVA-STYLE ATHLETIC STATS CARD -->
                    <!-- ---------------------------------------------------- -->
                    <div id="tpl_strava" class="template-view relative z-10 h-full flex flex-col justify-between hidden">
                        <!-- Top Header Strava Style -->
                        <div class="space-y-1 border-b border-white/10 pb-2.5">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-1.5">
                                    <span class="w-2 h-2 rounded-full bg-[#A8E63A] animate-pulse"></span>
                                    <span class="text-[9px] font-black tracking-widest text-[#A8E63A] uppercase">MATCHA ACTIVITY</span>
                                </div>
                                <img src="{{ asset('images/logo.svg') }}" class="w-5 h-5" alt="Matcha">
                            </div>
                            <!-- Player Profile Info -->
                            <div class="flex items-center gap-2.5 pt-1">
                                <div class="w-9 h-9 rounded-full bg-emerald-600 border-2 border-[#A8E63A] flex items-center justify-center font-black text-xs text-white shadow" id="stravaAvatarInitial">
                                    {{ mb_substr($rankedPlayers[0]['name'] ?? 'M', 0, 1) }}
                                </div>
                                <div>
                                    <h4 class="text-xs font-black text-white leading-tight" id="stravaPlayerName">
                                        {{ $rankedPlayers[0]['name'] ?? 'Pemain Matcha' }}
                                    </h4>
                                    <p class="text-[9px] text-slate-300">
                                        {{ $game['sport'] }} &bull; <span class="text-[#A8E63A] font-bold">{{ $game['venue_name'] ?? 'Matcha Arena' }}</span>
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Big Athletic Metrics Grid (Clean Strava-like - Without Calories) -->
                        <div class="bg-black/65 backdrop-blur-xl border border-white/15 rounded-2xl p-3 shadow-2xl space-y-2.5 my-auto">
                            <div class="grid grid-cols-2 gap-2 text-center">
                                <div class="p-2 rounded-xl bg-white/5 border border-white/5">
                                    <p class="text-[8px] font-bold text-slate-400 uppercase tracking-wider">Total Poin</p>
                                    <p class="text-2xl font-black text-white mt-0.5" id="stravaPoints">
                                        {{ $rankedPlayers[0]['points_for'] ?? ($playerRecap['total_points'] ?? 24) }}
                                    </p>
                                </div>
                                <div class="p-2 rounded-xl bg-[#063B00]/70 border border-[#A8E63A]/40">
                                    <p class="text-[8px] font-bold text-[#A8E63A] uppercase tracking-wider">Win Rate</p>
                                    <p class="text-2xl font-black text-[#A8E63A] mt-0.5" id="stravaWinRate">
                                        {{ !empty($rankedPlayers[0]['matches']) ? round(($rankedPlayers[0]['wins'] / $rankedPlayers[0]['matches']) * 100) . '%' : ($playerRecap['win_rate'] ?? '75%') }}
                                    </p>
                                </div>
                                <div class="p-2 rounded-xl bg-white/5 border border-white/5">
                                    <p class="text-[8px] font-bold text-slate-400 uppercase tracking-wider">Match Record</p>
                                    <p class="text-base font-black text-slate-200 mt-0.5" id="stravaRecord">
                                        {{ $rankedPlayers[0]['wins'] ?? 2 }}W - {{ $rankedPlayers[0]['losses'] ?? 1 }}L
                                    </p>
                                </div>
                                <div class="p-2 rounded-xl bg-white/5 border border-white/5">
                                    <p class="text-[8px] font-bold text-slate-400 uppercase tracking-wider">Durasi Main</p>
                                    <p class="text-base font-black text-slate-200 mt-0.5" id="stravaCalTime">
                                        {{ $playerRecap['duration_played'] ?? '1j 45m' }}
                                    </p>
                                </div>
                            </div>

                            <!-- Kudos Badges Highlight Pills -->
                            <div class="pt-0.5 flex items-center justify-center gap-1.5 flex-wrap">
                                <span class="px-2 py-0.5 rounded-full text-[8px] font-bold bg-[#A8E63A]/20 text-[#A8E63A] border border-[#A8E63A]/30">🎾 Super Forehand</span>
                                <span class="px-2 py-0.5 rounded-full text-[8px] font-bold bg-amber-400/20 text-amber-300 border border-amber-400/30">⭐ MVP Play</span>
                            </div>
                        </div>

                        <!-- Footer -->
                        <div class="pt-2 text-center border-t border-white/10 flex items-center justify-between text-[8px] text-slate-400">
                            <span>MATCHA Tennis & Padel</span>
                            <span class="font-bold text-[#A8E63A]">{{ date('d M Y') }}</span>
                        </div>
                    </div>
                </div>

                <!-- Next Button (>) -->
                <button type="button" onclick="nextTemplate()" class="w-9 h-9 sm:w-10 sm:h-10 rounded-full bg-slate-100 hover:bg-[#063B00] hover:text-white border border-slate-200 text-slate-700 shadow-sm flex items-center justify-center font-bold text-sm cursor-pointer transition-all shrink-0 hover:scale-105" title="Template Selanjutnya">
                    <i class="fa-solid fa-chevron-right"></i>
                </button>
            </div>

            <!-- RIGHT: Customization Controls & Actions (Light Mode Theme) -->
            <div class="lg:col-span-6 space-y-4">
                
                <!-- 1. Select Template Tabs -->
                <div class="space-y-1.5">
                    <label class="text-xs font-bold text-slate-700 uppercase tracking-wider block">
                        <i class="fa-solid fa-shapes text-[#063B00] mr-1"></i> Pilih Template
                    </label>
                    <div class="grid grid-cols-2 gap-2">
                        <button onclick="switchTemplate(0)" id="tplBtn_0" class="tpl-btn p-2.5 rounded-2xl bg-[#063B00] border-2 border-[#063B00] text-left transition-all cursor-pointer shadow-xs">
                            <p class="text-xs font-black text-white">1. Minimalist Podium</p>
                            <p class="text-[10px] text-slate-200">Bottom 3-avatar overlay</p>
                        </button>
                        <button onclick="switchTemplate(1)" id="tplBtn_1" class="tpl-btn p-2.5 rounded-2xl bg-white border-2 border-slate-200 text-left hover:border-slate-300 hover:bg-slate-50 transition-all cursor-pointer shadow-2xs">
                            <p class="text-xs font-black text-slate-900">2. Glass Leaderboard</p>
                            <p class="text-[10px] text-slate-500">Tabel ranking 1st - 4th</p>
                        </button>
                        <button onclick="switchTemplate(2)" id="tplBtn_2" class="tpl-btn p-2.5 rounded-2xl bg-white border-2 border-slate-200 text-left hover:border-slate-300 hover:bg-slate-50 transition-all cursor-pointer shadow-2xs">
                            <p class="text-xs font-black text-slate-900">3. Match Highlights</p>
                            <p class="text-[10px] text-slate-500">Rekap skor tiap match</p>
                        </button>
                        <button onclick="switchTemplate(3)" id="tplBtn_3" class="tpl-btn p-2.5 rounded-2xl bg-white border-2 border-slate-200 text-left hover:border-slate-300 hover:bg-slate-50 transition-all cursor-pointer shadow-2xs">
                            <p class="text-xs font-black text-slate-900">4. Strava Athletic</p>
                            <p class="text-[10px] text-slate-500">Personal performance card</p>
                        </button>
                    </div>
                </div>

                <!-- 2. Player Selector (For Strava Template) -->
                <div id="playerSelectorWrapper" class="space-y-1.5 hidden">
                    <label class="text-xs font-bold text-slate-700 uppercase tracking-wider block">
                        <i class="fa-solid fa-user text-[#063B00] mr-1"></i> Pilih Pemain untuk Highlight
                    </label>
                    <select id="stravaPlayerSelect" onchange="onSelectStravaPlayer(this.value)" class="w-full bg-white border border-slate-200 rounded-xl px-3 py-2 text-xs font-bold text-slate-800 shadow-2xs focus:outline-none focus:border-[#063B00]">
                        @foreach($rankedPlayers as $rp)
                        <option value="{{ $rp['name'] }}" 
                            data-points="{{ $rp['points_for'] ?? $rp['games_won'] }}" 
                            data-wins="{{ $rp['wins'] }}" 
                            data-losses="{{ $rp['losses'] }}"
                            data-matches="{{ $rp['matches'] }}">
                            #{{ $rp['rank'] }} &bull; {{ $rp['name'] }} ({{ $rp['points_for'] ?? $rp['games_won'] }} pts)
                        </option>
                        @endforeach
                    </select>
                </div>

                <!-- 3. Insert Photo Background from Local Device -->
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

                <!-- 4. Overlay Filter Style -->
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

                <!-- 5. Export Actions -->
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
<!-- Include html2canvas-pro (supports modern CSS & oklch color) and html-to-image fallback -->
<script src="https://cdn.jsdelivr.net/npm/html2canvas-pro@latest/dist/html2canvas-pro.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html-to-image/1.11.11/html-to-image.min.js"></script>

<script>
    // Kudos logic
    function toggleKudos(button, playerName) {
        if (button.classList.contains('bg-[#063B00]')) {
            button.classList.remove('bg-[#063B00]', 'text-white', 'border-[#063B00]');
            button.classList.add('bg-slate-50', 'text-slate-700', 'border-slate-200');
        } else {
            button.classList.add('bg-[#063B00]', 'text-white', 'border-[#063B00]');
            button.classList.remove('bg-slate-50', 'text-slate-700', 'border-slate-200');
            if (typeof showToast === 'function') {
                showToast('Kudos untuk ' + (playerName || 'pemain') + ' berhasil diberikan! 👏');
            }
        }
    }

    // Modal Control: Share Options Modal
    function openShareModal() {
        document.getElementById('shareOptionsModal').classList.remove('hidden');
    }

    function closeShareModal() {
        document.getElementById('shareOptionsModal').classList.add('hidden');
    }

    function copyWebLink() {
        navigator.clipboard.writeText(window.location.href);
        if (typeof showToast === 'function') {
            showToast('Link rekap berhasil disalin ke clipboard!');
        } else {
            alert('Link rekap berhasil disalin ke clipboard!');
        }
    }

    function shareWebDirect() {
        const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
        if (isMobile && navigator.share) {
            navigator.share({
                title: 'Hasil Match MATCHA - {{ $game["title"] ?? "Mabar" }}',
                text: 'Cek hasil pertandingan mabar hari ini di MATCHA!',
                url: window.location.href,
            }).catch(() => {
                copyWebLink();
            });
        } else {
            copyWebLink();
        }
    }

    // Modal Control: Template Studio
    function openTemplateStudio() {
        closeShareModal();
        document.getElementById('templateStudioModal').classList.remove('hidden');
    }

    function closeTemplateStudio() {
        document.getElementById('templateStudioModal').classList.add('hidden');
    }

    // Template Switching Logic
    const templateIds = ['tpl_podium', 'tpl_leaderboard', 'tpl_matches', 'tpl_strava'];
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
        for (let i = 0; i < 4; i++) {
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

        // Show/Hide Strava Player Selector
        const playerSel = document.getElementById('playerSelectorWrapper');
        if (playerSel) {
            if (idx === 3) {
                playerSel.classList.remove('hidden');
            } else {
                playerSel.classList.add('hidden');
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

    // Strava Player Selector Handler
    function onSelectStravaPlayer(playerName) {
        const select = document.getElementById('stravaPlayerSelect');
        const selectedOpt = select ? select.options[select.selectedIndex] : null;
        if (!selectedOpt) return;

        const points = selectedOpt.getAttribute('data-points') || '0';
        const wins = parseInt(selectedOpt.getAttribute('data-wins') || '0');
        const losses = parseInt(selectedOpt.getAttribute('data-losses') || '0');
        const matches = parseInt(selectedOpt.getAttribute('data-matches') || '1');

        const nameEl = document.getElementById('stravaPlayerName');
        const avatarEl = document.getElementById('stravaAvatarInitial');
        const ptsEl = document.getElementById('stravaPoints');
        const wrEl = document.getElementById('stravaWinRate');
        const recEl = document.getElementById('stravaRecord');

        if (nameEl) nameEl.innerText = playerName;
        if (avatarEl) avatarEl.innerText = playerName.charAt(0);
        if (ptsEl) ptsEl.innerText = points;
        if (recEl) recEl.innerText = `${wins}W - ${losses}L`;
        if (wrEl) {
            const rate = matches > 0 ? Math.round((wins / matches) * 100) : 0;
            wrEl.innerText = `${rate}%`;
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
            console.log('MATCHA_EXPORT: Starting export mode=', mode);
            let blob = null;

            // Method 1: Primary - html2canvas (html2canvas-pro supports oklch & modern CSS)
            const h2c = (typeof html2canvas === 'function') 
                ? html2canvas 
                : (typeof window !== 'undefined' && window.html2canvas ? (typeof window.html2canvas.default === 'function' ? window.html2canvas.default : window.html2canvas) : null);

            console.log('MATCHA_EXPORT: h2c resolver result =', typeof h2c);

            if (h2c && typeof h2c === 'function') {
                try {
                    const canvas = await h2c(card, {
                        scale: 2.5,
                        useCORS: true,
                        allowTaint: false,
                        backgroundColor: '#090d10',
                        logging: false,
                    });
                    console.log('MATCHA_EXPORT: canvas created, dimensions=', canvas.width, canvas.height);
                    blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/png', 0.95));
                    console.log('MATCHA_EXPORT: blob created successfully, size=', blob ? blob.size : 0);
                } catch (e) {
                    console.warn('MATCHA_EXPORT: html2canvas failed, error=', e);
                }
            }

            // Method 2: Fallback htmlToImage
            if (!blob && typeof htmlToImage !== 'undefined' && htmlToImage.toPng) {
                try {
                    console.log('MATCHA_EXPORT: Trying htmlToImage fallback...');
                    const dataUrl = await htmlToImage.toPng(card, {
                        pixelRatio: 2.5,
                        backgroundColor: '#090d10',
                        cacheBust: true,
                        skipFonts: true,
                    });
                    const res = await fetch(dataUrl);
                    blob = await res.blob();
                    console.log('MATCHA_EXPORT: htmlToImage blob created, size=', blob ? blob.size : 0);
                } catch (e) {
                    console.warn('MATCHA_EXPORT: htmlToImage fallback failed:', e);
                }
            }

            if (!blob) {
                throw new Error('Tidak dapat membuat file gambar story dari browser.');
            }

            const filename = `MATCHA-Recap-{{ \Illuminate\Support\Str::slug($game['title'] ?? 'Session') }}.png`;
            const file = new File([blob], filename, { type: 'image/png' });

            const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);

            // Trigger Mobile Native Share sheet if on mobile device
            if (mode === 'share' && isMobile && navigator.canShare && navigator.canShare({ files: [file] })) {
                try {
                    await navigator.share({
                        title: 'MATCHA Match Story',
                        text: 'Cek hasil pertandingan mabar hari ini di MATCHA! 🎾',
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
        console.log('MATCHA_EXPORT: Triggering download for file=', filename);
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

