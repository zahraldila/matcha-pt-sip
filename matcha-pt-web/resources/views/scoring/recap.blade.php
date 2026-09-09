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

        <div class="relative space-y-4">
            @php
                $isSets = $scoringSystem['is_sets'];
                $winnerTeamLabel = $lastScore['winner_team'] ?? 'Team A';
                $finalScoreA     = (int) ($lastScore['score_a'] ?? 0);
                $finalScoreB     = (int) ($lastScore['score_b'] ?? 0);
                $setsA           = (int) ($lastScore['sets_a'] ?? 0);
                $setsB           = (int) ($lastScore['sets_b'] ?? 0);
                $gamesA          = (int) ($lastScore['games_a'] ?? 0);
                $gamesB          = (int) ($lastScore['games_b'] ?? 0);
                $setHistory      = $lastScore['set_history'] ?? [];

                if ($isSets) {
                    if ($setsA === 0 && $setsB === 0 && !empty($setHistory)) {
                        foreach ($setHistory as $sh) {
                            if (($sh['score_a'] ?? 0) > ($sh['score_b'] ?? 0)) $setsA++;
                            elseif (($sh['score_b'] ?? 0) > ($sh['score_a'] ?? 0)) $setsB++;
                        }
                    }
                    $displayScoreA = (string) ($setsA > 0 ? $setsA : ($finalScoreA > 0 ? $finalScoreA : $gamesA));
                    $displayScoreB = (string) ($setsB > 0 ? $setsB : ($finalScoreB > 0 ? $finalScoreB : $gamesB));
                    $scoreLabel = 'Skor Akhir (Sets)';
                    if ($setsA > $setsB) {
                        $winnerTeamLabel = 'Team A';
                    } elseif ($setsB > $setsA) {
                        $winnerTeamLabel = 'Team B';
                    }
                } else {
                    $displayScoreA = (string) ($gamesA > 0 ? $gamesA : $finalScoreA);
                    $displayScoreB = (string) ($gamesB > 0 ? $gamesB : $finalScoreB);
                    $scoreLabel = "Skor Akhir (Games — {$scoringSystem['label']})";
                    if ($gamesA > $gamesB) {
                        $winnerTeamLabel = 'Team A';
                    } elseif ($gamesB > $gamesA) {
                        $winnerTeamLabel = 'Team B';
                    }
                }

                $winnerNames = $winnerTeamLabel === 'Team A' ? ($lastScore['team_a'] ?? []) : ($lastScore['team_b'] ?? []);
                $loserNames  = $winnerTeamLabel === 'Team A' ? ($lastScore['team_b'] ?? []) : ($lastScore['team_a'] ?? []);
            @endphp

            <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-amber-100/80 border border-amber-200 text-amber-900 text-xs font-bold">
                <i class="fa-solid fa-trophy text-amber-600"></i> Match Winner &bull; {{ $winnerTeamLabel }}
            </div>

            <h1 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">
                {{ $game['title'] ?? 'Matcha Session' }}
            </h1>
            <p class="text-xs text-slate-500">
                <span class="font-bold text-slate-700">{{ $lastScore['round_title'] ?? 'Round 1' }}</span> &bull; 
                {{ $game['venue_name'] ?? 'Arena Olahraga' }} &bull; 
                <span class="bg-white/80 px-2 py-0.5 rounded-md border border-slate-200 text-slate-700 font-semibold">{{ $game['sport'] }} &bull; {{ $scoringSystem['label'] }}</span>
            </p>

            <!-- Scoreboard Big Visual -->
            <div class="max-w-lg mx-auto py-4 px-3 bg-white/70 backdrop-blur-md rounded-2xl border border-slate-200/80 shadow-xs space-y-3">
                <div class="grid grid-cols-3 items-center">
                    <!-- Team A -->
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
                            <span class="{{ (int)$displayScoreA >= (int)$displayScoreB ? 'text-[#063B00]' : 'text-slate-400' }}">{{ $displayScoreA }}</span>
                            :
                            <span class="{{ (int)$displayScoreB > (int)$displayScoreA ? 'text-[#063B00]' : 'text-slate-400' }}">{{ $displayScoreB }}</span>
                        </div>
                        <span class="text-[10px] font-semibold text-slate-500 block">
                            {{ $scoreLabel }}
                        </span>
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

                <!-- Set History Breakdown Pills (jika sistem berbasis Set) -->
                @if($isSets && !empty($setHistory))
                <div class="pt-2 border-t border-slate-200/60 flex items-center justify-center gap-1.5 flex-wrap">
                    <span class="text-[10px] font-bold uppercase text-slate-400 mr-1">Rincian Game:</span>
                    @foreach($setHistory as $s)
                    <span class="px-2.5 py-0.5 rounded-lg bg-white border border-slate-200 text-slate-800 text-xs font-extrabold shadow-2xs">
                        Set {{ $s['set'] }}: <span class="text-[#063B00]">{{ $s['score_a'] }}</span> &mdash; <span>{{ $s['score_b'] }}</span>
                    </span>
                    @endforeach
                </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Grand-Slam Style Tournament Set Scoreboard Table --}}
    @if($isSets && !empty($setHistory))
    <div class="glass-card rounded-3xl p-6 border border-white space-y-4">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-xl bg-[#EBF8D8] border border-[#063B00]/20 flex items-center justify-center text-[#063B00] text-xs">
                    <i class="fa-solid fa-table-cells"></i>
                </div>
                <div>
                    <h2 class="text-sm font-bold text-slate-900">Papan Skor Rincian Set (Set Scoreboard)</h2>
                    <p class="text-[11px] text-slate-500">Format {{ $scoringSystem['label'] }} &bull; Standar Turnamen Tennis &amp; Padel</p>
                </div>
            </div>
            <span class="px-2.5 py-1 rounded-full bg-[#EBF8D8] text-[#063B00] text-[10px] font-extrabold border border-[#063B00]/20">
                Total {{ count($setHistory) }} Set
            </span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="border-b border-slate-200 text-[10px] font-black uppercase tracking-wider text-slate-400">
                        <th class="py-2.5 px-3">Tim / Pemain</th>
                        @foreach($setHistory as $s)
                            <th class="py-2.5 px-3 text-center">Set {{ $s['set'] }}</th>
                        @endforeach
                        <th class="py-2.5 px-3 text-center bg-[#EBF8D8]/50 text-[#063B00] rounded-t-lg">Total Set</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    {{-- Team A Row --}}
                    <tr class="{{ $winnerTeamLabel === 'Team A' ? 'bg-amber-50/40 font-semibold' : '' }}">
                        <td class="py-3 px-3">
                            <div class="flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full {{ $winnerTeamLabel === 'Team A' ? 'bg-[#063B00]' : 'bg-slate-300' }}"></span>
                                <div>
                                    <div class="flex items-center gap-1.5">
                                        <span class="font-bold text-slate-900">Team A</span>
                                        @if($winnerTeamLabel === 'Team A')
                                            <span class="text-[10px] px-1.5 py-0.2 rounded bg-amber-100 text-amber-800 font-bold border border-amber-200">WINNER 🏆</span>
                                        @endif
                                    </div>
                                    <p class="text-[11px] text-slate-500">{{ implode(' & ', $lastScore['team_a'] ?? ['Team A']) }}</p>
                                </div>
                            </div>
                        </td>
                        @foreach($setHistory as $s)
                            @php
                                $sScoreA = (int) ($s['score_a'] ?? 0);
                                $sScoreB = (int) ($s['score_b'] ?? 0);
                                $isSetWinner = $sScoreA > $sScoreB;
                            @endphp
                            <td class="py-3 px-3 text-center">
                                <span class="inline-block px-2.5 py-1 rounded-lg text-xs font-black {{ $isSetWinner ? 'bg-[#EBF8D8] text-[#063B00] border border-[#063B00]/30 shadow-2xs' : 'text-slate-600 bg-slate-50' }}">
                                    {{ $sScoreA }}
                                </span>
                            </td>
                        @endforeach
                        <td class="py-3 px-3 text-center bg-[#EBF8D8]/40">
                            <span class="text-base font-black {{ $winnerTeamLabel === 'Team A' ? 'text-[#063B00]' : 'text-slate-700' }}">
                                {{ $setsA }}
                            </span>
                        </td>
                    </tr>

                    {{-- Team B Row --}}
                    <tr class="{{ $winnerTeamLabel === 'Team B' ? 'bg-amber-50/40 font-semibold' : '' }}">
                        <td class="py-3 px-3">
                            <div class="flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full {{ $winnerTeamLabel === 'Team B' ? 'bg-[#063B00]' : 'bg-slate-300' }}"></span>
                                <div>
                                    <div class="flex items-center gap-1.5">
                                        <span class="font-bold text-slate-900">Team B</span>
                                        @if($winnerTeamLabel === 'Team B')
                                            <span class="text-[10px] px-1.5 py-0.2 rounded bg-amber-100 text-amber-800 font-bold border border-amber-200">WINNER 🏆</span>
                                        @endif
                                    </div>
                                    <p class="text-[11px] text-slate-500">{{ implode(' & ', $lastScore['team_b'] ?? ['Team B']) }}</p>
                                </div>
                            </div>
                        </td>
                        @foreach($setHistory as $s)
                            @php
                                $sScoreA = (int) ($s['score_a'] ?? 0);
                                $sScoreB = (int) ($s['score_b'] ?? 0);
                                $isSetWinner = $sScoreB > $sScoreA;
                            @endphp
                            <td class="py-3 px-3 text-center">
                                <span class="inline-block px-2.5 py-1 rounded-lg text-xs font-black {{ $isSetWinner ? 'bg-[#EBF8D8] text-[#063B00] border border-[#063B00]/30 shadow-2xs' : 'text-slate-600 bg-slate-50' }}">
                                    {{ $sScoreB }}
                                </span>
                            </td>
                        @endforeach
                        <td class="py-3 px-3 text-center bg-[#EBF8D8]/40">
                            <span class="text-base font-black {{ $winnerTeamLabel === 'Team B' ? 'text-[#063B00]' : 'text-slate-700' }}">
                                {{ $setsB }}
                            </span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    @endif

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
                $rScore = $effectiveScores[$rKey] ?? null;
                $isDone = ($rScore['status'] ?? '') === 'completed';
                $rTitle = ucfirst(str_replace('_', ' ', $rKey));
                $rSetsHist = $rScore['set_history'] ?? [];
                $rWinner = $rScore['winner_team'] ?? null;
                if ($isDone && !$rWinner) {
                    if ($isSets) {
                        $rWinner = ($rScore['sets_a'] ?? 0) >= ($rScore['sets_b'] ?? 0) ? 'Team A' : 'Team B';
                    } else {
                        $rWinner = ($rScore['games_a'] ?? 0) >= ($rScore['games_b'] ?? 0) ? 'Team A' : 'Team B';
                    }
                }
            @endphp
            <div class="p-4 rounded-2xl bg-white border border-slate-200/80 shadow-2xs space-y-2.5">
                <div class="flex items-center justify-between text-xs">
                    <span class="font-black text-slate-800 flex items-center gap-1.5">
                        <i class="fa-solid fa-flag-checkered text-slate-400"></i> {{ $rTitle }}
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
                    <div class="flex items-center justify-between p-2 rounded-xl {{ $isDone && $rWinner === 'Team A' ? 'bg-[#EBF8D8]/50 font-bold' : 'bg-slate-50' }}">
                        <div class="flex items-center gap-2 truncate pr-2">
                            <span class="w-1.5 h-1.5 rounded-full {{ $isDone && $rWinner === 'Team A' ? 'bg-[#063B00]' : 'bg-slate-300' }}"></span>
                            <span class="truncate text-slate-800">{{ implode(' & ', $rData['team_a'] ?? ['Team A']) }}</span>
                            @if($isDone && $rWinner === 'Team A')
                                <span class="text-[9px] px-1 rounded bg-[#EBF8D8] text-[#063B00] font-black shrink-0">WIN</span>
                            @endif
                        </div>
                        <span class="font-black text-sm {{ $isDone && $rWinner === 'Team A' ? 'text-[#063B00]' : 'text-slate-600' }} shrink-0">
                            @if($isDone)
                                {{ $isSets ? ($rScore['sets_a'] ?? 0) : ($rScore['games_a'] ?? $rScore['score_a'] ?? 0) }}
                            @else
                                -
                            @endif
                        </span>
                    </div>

                    <!-- Team B -->
                    <div class="flex items-center justify-between p-2 rounded-xl {{ $isDone && $rWinner === 'Team B' ? 'bg-[#EBF8D8]/50 font-bold' : 'bg-slate-50' }}">
                        <div class="flex items-center gap-2 truncate pr-2">
                            <span class="w-1.5 h-1.5 rounded-full {{ $isDone && $rWinner === 'Team B' ? 'bg-[#063B00]' : 'bg-slate-300' }}"></span>
                            <span class="truncate text-slate-800">{{ implode(' & ', $rData['team_b'] ?? ['Team B']) }}</span>
                            @if($isDone && $rWinner === 'Team B')
                                <span class="text-[9px] px-1 rounded bg-[#EBF8D8] text-[#063B00] font-black shrink-0">WIN</span>
                            @endif
                        </div>
                        <span class="font-black text-sm {{ $isDone && $rWinner === 'Team B' ? 'text-[#063B00]' : 'text-slate-600' }} shrink-0">
                            @if($isDone)
                                {{ $isSets ? ($rScore['sets_b'] ?? 0) : ($rScore['games_b'] ?? $rScore['score_b'] ?? 0) }}
                            @else
                                -
                            @endif
                        </span>
                    </div>
                </div>

                @if($isDone && $isSets && !empty($rSetsHist))
                <div class="pt-2 border-t border-slate-100 flex items-center gap-1.5 flex-wrap">
                    <span class="text-[10px] font-bold text-slate-400">Rincian:</span>
                    @foreach($rSetsHist as $sh)
                    <span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-slate-100 text-slate-700">
                        Set {{ $sh['set'] }}: {{ $sh['score_a'] }}-{{ $sh['score_b'] }}
                    </span>
                    @endforeach
                </div>
                @endif
            </div>
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
                $medalEmoji = $player['medal']['emoji'];
                $bgClass = match($player['rank']) {
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

        <!-- Kudos Grid -->
        <div class="space-y-3">
            @foreach($rankedPlayers as $player)
            @if($loop->index === 0) @continue @endif
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
    function toggleKudos(button) {
        if (button.classList.contains('bg-[#063B00]')) {
            button.classList.remove('bg-[#063B00]', 'text-white', 'border-[#063B00]');
            button.classList.add('bg-slate-50', 'text-slate-700', 'border-slate-200');
        } else {
            button.classList.add('bg-[#063B00]', 'text-white', 'border-[#063B00]');
            button.classList.remove('bg-slate-50', 'text-slate-700', 'border-slate-200');
            showToast('Kudos berhasil diberikan! 👏');
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
            showToast('Link rekap disalin ke clipboard!');
        }
    }
</script>
@endpush
@endsection
