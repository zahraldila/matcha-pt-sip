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
            <button onclick="shareRecap()" class="px-3 py-1 rounded-xl bg-white border border-slate-200 text-slate-700 text-xs font-semibold hover:bg-slate-50 shadow-2xs transition-colors flex items-center gap-1.5 cursor-pointer">
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
                @if(!empty($player['avatar']))
                    <img src="{{ $player['avatar'] }}" class="w-8 h-8 rounded-full object-cover border border-slate-200 shrink-0" alt="{{ $player['name'] }}">
                @else
                    <div class="w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center shrink-0 text-slate-500 text-xs font-bold">{{ mb_substr($player['name'], 0, 1) }}</div>
                @endif

                {{-- Nama & Level --}}
                <div class="flex-1 min-w-0">
                    <p class="text-xs font-bold text-slate-900 truncate">{{ $player['name'] }}</p>
                    <p class="text-[10px] text-slate-400">{{ $player['level'] }} &bull; {{ $player['matches'] }} match</p>
                </div>

                {{-- Stats Columns --}}
                <div class="flex items-center gap-3 text-[10px] shrink-0">
                    <div class="text-center min-w-[36px]">
                        <p class="font-black text-[#063B00] text-sm">{{ $player['wins'] }}</p>
                        <p class="text-slate-400 font-medium">Win</p>
                    </div>

                    @if($isSets)
                    <div class="text-center min-w-[36px]">
                        <p class="font-black text-slate-700 text-sm">{{ $player['sets_won'] }}</p>
                        <p class="text-slate-400 font-medium">Sets</p>
                    </div>
                    @endif

                    <div class="text-center min-w-[36px]">
                        <p class="font-black text-slate-700 text-sm">{{ $player['games_won'] }}</p>
                        <p class="text-slate-400 font-medium">Games</p>
                    </div>

                    <div class="text-center min-w-[40px]">
                        @php $diff = $isSets ? $player['game_diff'] : $player['game_diff']; @endphp
                        <p class="font-black text-sm {{ $diff >= 0 ? 'text-[#063B00]' : 'text-rose-600' }}">
                            {{ $diff >= 0 ? '+' : '' }}{{ $diff }}
                        </p>
                        <p class="text-slate-400 font-medium">Selisih</p>
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

@push('scripts')
<script>
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

    function shareRecap() {
        if (navigator.share) {
            navigator.share({
                title: 'Hasil Match MATCHA',
                text: 'Cek hasil pertandingan mabar hari ini di MATCHA!',
                url: window.location.href,
            }).catch(() => {});
        } else {
            navigator.clipboard.writeText(window.location.href);
            if (typeof showToast === 'function') {
                showToast('Link rekap disalin ke clipboard!');
            }
        }
    }
</script>
@endpush
@endsection
