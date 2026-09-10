@extends('layouts.app')

@section('content')
<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
    
    <!-- Header -->
    <div class="flex items-center justify-between border-b border-slate-200/50 pb-4">
        <div>
            <a href="{{ route('games.drawing', $game['id']) }}" class="text-xs text-slate-500 hover:text-slate-800 inline-flex items-center gap-1.5 mb-2 transition-colors">
                <i class="fa-solid fa-arrow-left"></i> Kembali ke Drawing Lapangan
            </a>
            <h1 class="text-2xl font-bold text-slate-900">
                @if($isHost)
                    Live Match Scoring Console
                @else
                    Live Match — Papan Skor
                @endif
            </h1>
            <p class="text-xs text-slate-500 mt-0.5">
                @if($isHost)
                    Host menekan tombol untuk mencatat poin game &amp; game score secara langsung
                @else
                    Skor diperbarui otomatis secara realtime &bull; <span class="font-semibold text-[#063B00]">Mode Penonton</span>
                @endif
            </p>
        </div>

        <div class="flex flex-col items-end gap-1.5">
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-rose-50/90 text-rose-800 border border-rose-200/60 text-xs font-semibold shadow-xs">
                <span class="w-2 h-2 rounded-full bg-rose-600 animate-pulse"></span> MATCH LIVE
            </span>
            {{-- Badge role --}}
            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold
                @if($isHost) bg-[#063B00] text-[#A8E63A]
                @elseif($userRole === 'member') bg-sky-50 text-sky-700 border border-sky-200
                @else bg-slate-100 text-slate-500 border border-slate-200 @endif">
                @if($isHost)
                    <i class="fa-solid fa-crown text-[9px]"></i> Host
                @elseif($userRole === 'member')
                    <i class="fa-solid fa-eye text-[9px]"></i> Member — Penonton
                @else
                    <i class="fa-solid fa-user text-[9px]"></i> {{ ucfirst($userRole) }}
                @endif
            </span>
        </div>
    </div>

    {{-- Round / Set Selector (Set 1, Set 2, Set 3) --}}
    @php
        $isTeamFormat = str_contains(strtolower($game['match_format'] ?? ''), 'team');
        $unitTabLabel = $isTeamFormat ? 'Ronde' : ($scoringSystem['is_sets'] ? 'Set' : 'Ronde');
        $allRoundsList = $matchContext['all_rounds'] ?? [];
        $currentRIndex = array_search($activeRound, $allRoundsList);
        $nextRoundKey = ($currentRIndex !== false && isset($allRoundsList[$currentRIndex + 1])) ? $allRoundsList[$currentRIndex + 1] : null;
        $isCurrentSetCompleted = (($currentScore['status'] ?? '') === 'completed');
    @endphp

    @if(($matchContext['total_rounds'] ?? 0) > 1 || count($allRoundsList) > 1)
    <div class="glass-card rounded-2xl p-3 flex flex-wrap items-center justify-between gap-3 border border-white/90 shadow-2xs">
        <div class="flex items-center gap-2 flex-wrap">
            <span class="text-xs font-bold text-slate-700 flex items-center gap-1.5">
                <i class="fa-solid fa-layer-group text-[#063B00]"></i> Pilih {{ $unitTabLabel }}:
            </span>
            <div class="flex items-center gap-2 overflow-x-auto py-0.5">
                @foreach($allRoundsList as $rKey)
                    @php
                        $rNumber = preg_replace('/[^0-9]/', '', $rKey) ?: '1';
                        $tabDisplayTitle = "{$unitTabLabel} {$rNumber}";
                        $isTabActive = ($activeRound === $rKey);
                        // Cek apakah ronde/set ini sudah completed di savedScores
                        $rScore = $savedScores[$rKey] ?? ($savedScores["{$rKey}_court_1"] ?? []);
                        $isRCompleted = (($rScore['status'] ?? '') === 'completed');
                    @endphp
                    <a href="{{ route('scoring.live', ['id' => $game['id'], 'format' => request('format', $game['match_format'] ?? 'Americano'), 'round' => $rKey, 'court' => $courtIndex]) }}"
                       class="px-3.5 py-1.5 rounded-xl text-xs font-bold border transition-all flex items-center gap-1.5
                              {{ $isTabActive
                                   ? 'bg-[#063B00] text-white border-[#063B00] shadow-xs'
                                   : 'bg-white text-slate-700 border-slate-200 hover:border-[#063B00]/40 shadow-2xs' }}">
                        <span>{{ $tabDisplayTitle }}</span>
                        @if($isRCompleted)
                            <span class="text-[9px] px-1.5 py-0.2 rounded-md {{ $isTabActive ? 'bg-white/20 text-[#A8E63A]' : 'bg-emerald-50 text-emerald-700 border border-emerald-200' }}">✓ Terkunci</span>
                        @endif
                    </a>
                @endforeach
            </div>
        </div>
        <span class="text-[11px] text-slate-500 font-semibold">
            @if(str_contains(strtolower($game['match_format'] ?? ''), 'team'))
                Format: <strong class="text-indigo-700">Team Americano (Tim Tetap)</strong>
            @else
                Format: <strong class="text-[#063B00]">Americano (Partner Berganti Tiap Set)</strong>
            @endif
        </span>
    </div>
    @endif

    {{-- Multi-Court Selector Tabs (Jika 2 Court berjalan bersamaan di Set ini) --}}
    @if(($matchContext['court_count'] ?? 1) > 1 || count($matchContext['matches'] ?? []) > 1)
    <div class="glass-card rounded-2xl p-3 flex flex-wrap items-center justify-between gap-2 border border-white/90 shadow-2xs">
        <div class="flex items-center gap-2 flex-wrap">
            <span class="text-xs font-bold text-slate-700 flex items-center gap-1.5">
                <i class="fa-solid fa-table-tennis-paddle-ball text-[#063B00]"></i> Pilih Lapangan:
            </span>
            <div class="flex items-center gap-1.5">
                @foreach($matchContext['matches'] as $mIdx => $m)
                    @php
                        $cLabel = $m['court_name'] ?? ('Court ' . ($m['court'] ?? ($mIdx + 1)));
                        $isCourtActive = ($courtIndex === $mIdx);
                        $mKey = "{$activeRound}_court_" . ($mIdx + 1);
                        $mSaved = $savedScores[$mKey] ?? [];
                        $isMCompleted = (($mSaved['status'] ?? '') === 'completed');
                    @endphp
                    <a href="{{ route('scoring.live', ['id' => $game['id'], 'format' => request('format', $game['match_format'] ?? 'Americano'), 'round' => $activeRound, 'court' => $mIdx]) }}"
                       class="px-3 py-1.5 rounded-xl text-xs font-bold border transition-all flex items-center gap-1.5
                              {{ $isCourtActive ? 'bg-[#063B00] text-white border-[#063B00] shadow-xs' : 'bg-white text-slate-700 border-slate-200 hover:border-[#063B00]/50 shadow-2xs' }}">
                        <span>{{ $cLabel }}</span>
                        @if($isMCompleted)
                            <span class="text-[9px] px-1.5 py-0.2 rounded-full {{ $isCourtActive ? 'bg-white/20 text-[#A8E63A]' : 'bg-emerald-100 text-emerald-800' }} font-extrabold">✓ Selesai</span>
                        @endif
                    </a>
                @endforeach
            </div>
        </div>
        <span class="text-[11px] text-slate-500 font-semibold">
            Mencatat skor untuk: <strong class="text-[#063B00]">{{ $matchContext['court_name'] ?? 'Court 1' }}</strong>
        </span>
    </div>
    @endif

    <!-- Live Scoreboard Display (Subtle Glass) -->
    <div class="glass-card rounded-3xl p-6 sm:p-8 space-y-6 border border-white/90 shadow-sm relative">
        
        <!-- Locked Badge Notification if this set match is completed -->
        @if($isCurrentSetCompleted)
        <div class="p-3 bg-amber-50 border border-amber-200 rounded-2xl text-amber-900 text-xs font-bold flex items-center justify-between shadow-2xs">
            <span class="flex items-center gap-2">
                <i class="fa-solid fa-lock text-amber-600 text-sm"></i>
                Skor {{ $unitTabLabel }} {{ preg_replace('/[^0-9]/', '', $activeRound) ?: '1' }} pada {{ $matchContext['court_name'] ?? 'Court 1' }} sudah selesai dan terkunci.
            </span>
            <span class="text-[10px] bg-amber-200/80 px-2 py-0.5 rounded-full uppercase tracking-wider font-extrabold text-amber-800">Final Score</span>
        </div>
        @endif

        <!-- Match Info Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 text-xs text-slate-500 border-b border-slate-200/50 pb-3">
            <div>
                <strong class="text-slate-800">{{ $game['venue_name'] }}</strong> &bull; <span class="text-[#063B00] font-bold">{{ $matchContext['court_name'] ?? 'Court 1' }}</span> &bull; <span class="font-bold text-slate-700">{{ $unitTabLabel }} {{ preg_replace('/[^0-9]/', '', $activeRound) ?: '1' }}</span>
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                <span class="bg-[#063B00] text-white px-2.5 py-1 rounded-full font-bold text-[11px] shadow-2xs">
                    {{ $game['sport'] }}
                </span>
                <span class="bg-white/90 px-3 py-1 rounded-full border border-slate-200/80 font-bold text-slate-800 shadow-2xs">
                    {{ $scoringSystem['label'] }}
                </span>
            </div>
        </div>

        {{-- SET / GAME HEADER BAR --}}
        <div class="bg-slate-900 text-white rounded-2xl p-4 sm:p-5 text-center space-y-2 shadow-sm relative overflow-hidden">
            <div class="flex items-center justify-between text-xs text-slate-300 border-b border-slate-800 pb-2">
                <span class="font-bold uppercase tracking-wider text-[#A8E63A] flex items-center gap-1.5">
                    <i class="fa-solid fa-trophy text-[11px]"></i> {{ $unitTabLabel }} {{ preg_replace('/[^0-9]/', '', $activeRound) ?: '1' }} SCORE
                </span>
                <span class="font-medium text-slate-400">
                    Target: <strong class="text-white">{{ $scoringSystem['is_sets'] ? '1 Set Padel' : ($scoringSystem['target_games'] . ' Games') }}</strong>
                </span>
            </div>

            <div class="flex items-center justify-center gap-6 sm:gap-10 py-1">
                <div class="text-center">
                    <span class="text-[10px] uppercase font-bold text-slate-400 block">Games Team A</span>
                    <span id="displayGameScoreA" class="text-3xl sm:text-4xl font-black text-white">
                        {{ $currentScore['games_a'] ?? ($currentScore['score_a'] ?? 0) }}
                    </span>
                </div>
                <div class="text-slate-500 font-black text-2xl sm:text-3xl">&mdash;</div>
                <div class="text-center">
                    <span class="text-[10px] uppercase font-bold text-slate-400 block">Games Team B</span>
                    <span id="displayGameScoreB" class="text-3xl sm:text-4xl font-black text-white">
                        {{ $currentScore['games_b'] ?? ($currentScore['score_b'] ?? 0) }}
                    </span>
                </div>
            </div>

            <div id="setHistoryContainer" class="flex items-center justify-center gap-2 flex-wrap pt-1 text-[11px]">
                <span class="text-slate-400 font-semibold" id="currentSetLabel">
                    Status: <strong>{{ $isCurrentSetCompleted ? 'Set Selesai' : 'Sedang Berlangsung' }}</strong>
                </span>
                <span id="currentSetGamesBadge" class="px-2.5 py-0.5 rounded-md bg-white/10 text-[#A8E63A] font-bold border border-white/10">
                    Game Score: {{ $currentScore['games_a'] ?? 0 }} &mdash; {{ $currentScore['games_b'] ?? 0 }}
                </span>
            </div>
        </div>

        <!-- Dynamic Scoreboard: POINT SCORING (0 -> 15 -> 30 -> 40 -> Game) -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 items-center">
            
            <!-- Team A Side -->
            <div class="bg-white/80 backdrop-blur-md rounded-2xl p-6 border border-slate-200/80 text-center space-y-4 shadow-xs relative">
                <span class="inline-block px-2.5 py-0.5 rounded-md bg-[#EBF8D8] text-[#063B00] border border-[#063B00]/25 font-extrabold text-[10px] uppercase tracking-wider">
                    {{ $matchContext['team_a_name'] ?? 'TEAM A' }}
                </span>
                
                <div class="space-y-0.5">
                    @forelse($matchContext['team_a'] as $playerName)
                        <h3 class="text-sm font-bold text-slate-900">{{ $playerName }}</h3>
                    @empty
                        <h3 class="text-sm font-bold text-slate-400 italic">Tim A belum ditentukan</h3>
                    @endforelse
                </div>

                <!-- Live Point Big Display (0, 15, 30, 40, ADV) -->
                <div class="py-2">
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">CURRENT POINT</span>
                    <div id="scoreDisplayA" class="text-6xl font-black text-slate-900 tracking-tight transition-all duration-300">
                        {{ $currentScore['point_display_a'] ?? '0' }}
                    </div>
                    <span id="subScoreLabelA" class="text-xs text-slate-500 font-semibold block mt-1">
                        Games Won: {{ $currentScore['games_a'] ?? 0 }} Game
                    </span>
                </div>

                <!-- Point Button — hanya tampil untuk Host -->
                @if($isHost)
                    @if($isCurrentSetCompleted)
                        <button disabled class="w-full py-3.5 rounded-xl bg-slate-100 text-slate-400 font-bold text-sm border border-slate-200 cursor-not-allowed flex items-center justify-center gap-2">
                            <i class="fa-solid fa-lock text-xs"></i> Skor Terkunci (Set Selesai)
                        </button>
                    @else
                        <button id="btnAddA" onclick="addPoint('A')" class="w-full py-3.5 rounded-xl bg-[#063B00] hover:bg-[#042a00] text-white font-bold text-sm shadow-sm transition-all hover:scale-[1.01] active:scale-95 flex items-center justify-center gap-2 cursor-pointer">
                            <i class="fa-solid fa-plus text-xs text-[#A8E63A]"></i> Tambah Poin Team A
                        </button>
                    @endif
                @else
                    <div class="w-full py-2.5 rounded-xl bg-slate-50 border border-slate-200/70 text-center text-xs text-slate-400 font-medium">
                        <i class="fa-solid fa-eye text-[10px] mr-1"></i> Read-Only (Member View)
                    </div>
                @endif
            </div>

            <!-- Team B Side -->
            <div class="bg-white/80 backdrop-blur-md rounded-2xl p-6 border border-slate-200/80 text-center space-y-4 shadow-xs relative">
                <span class="inline-block px-2.5 py-0.5 rounded-md bg-[#EBF8D8] text-[#063B00] border border-[#063B00]/25 font-extrabold text-[10px] uppercase tracking-wider">
                    {{ $matchContext['team_b_name'] ?? 'TEAM B' }}
                </span>

                <div class="space-y-0.5">
                    @forelse($matchContext['team_b'] as $playerName)
                        <h3 class="text-sm font-bold text-slate-900">{{ $playerName }}</h3>
                    @empty
                        <h3 class="text-sm font-bold text-slate-400 italic">Tim B belum ditentukan</h3>
                    @endforelse
                </div>

                <!-- Live Point Big Display (0, 15, 30, 40, ADV) -->
                <div class="py-2">
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">CURRENT POINT</span>
                    <div id="scoreDisplayB" class="text-6xl font-black text-slate-900 tracking-tight transition-all duration-300">
                        {{ $currentScore['point_display_b'] ?? '0' }}
                    </div>
                    <span id="subScoreLabelB" class="text-xs text-slate-500 font-semibold block mt-1">
                        Games Won: {{ $currentScore['games_b'] ?? 0 }} Game
                    </span>
                </div>

                <!-- Point Button — hanya tampil untuk Host -->
                @if($isHost)
                    @if($isCurrentSetCompleted)
                        <button disabled class="w-full py-3.5 rounded-xl bg-slate-100 text-slate-400 font-bold text-sm border border-slate-200 cursor-not-allowed flex items-center justify-center gap-2">
                            <i class="fa-solid fa-lock text-xs"></i> Skor Terkunci (Set Selesai)
                        </button>
                    @else
                        <button id="btnAddB" onclick="addPoint('B')" class="w-full py-3.5 rounded-xl bg-[#063B00] hover:bg-[#042a00] text-white font-bold text-sm shadow-sm transition-all hover:scale-[1.01] active:scale-95 flex items-center justify-center gap-2 cursor-pointer">
                            <i class="fa-solid fa-plus text-xs text-[#A8E63A]"></i> Tambah Poin Team B
                        </button>
                    @endif
                @else
                    <div class="w-full py-2.5 rounded-xl bg-slate-50 border border-slate-200/70 text-center text-xs text-slate-400 font-medium">
                        <i class="fa-solid fa-eye text-[10px] mr-1"></i> Read-Only (Member View)
                    </div>
                @endif
            </div>
        </div>

        <!-- Score Status Notice -->
        <div id="matchNotice" class="p-3.5 bg-white/90 rounded-xl border border-slate-200/70 text-center text-xs font-semibold text-slate-700 shadow-2xs">
            @if($isCurrentSetCompleted)
                🔒 <strong>{{ $unitTabLabel }} {{ preg_replace('/[^0-9]/', '', $activeRound) ?: '1' }} Selesai & Terkunci</strong> &bull; Skor: {{ $currentScore['games_a'] ?? 0 }} &mdash; {{ $currentScore['games_b'] ?? 0 }}
            @else
                Point: <strong>0 &mdash; 0</strong> &bull; <em>Game sedang berlangsung</em>
            @endif
        </div>

        <!-- Match Completed Banner -->
        <div id="matchCompletedBanner" class="{{ $isCurrentSetCompleted ? '' : 'hidden' }} p-5 rounded-2xl bg-[#EBF8D8] border border-[#063B00]/30 text-center space-y-3 shadow-sm">
            <div class="text-3xl">🏆</div>
            <p class="text-base font-black text-[#063B00]" id="completedMsg">
                {{ $unitTabLabel }} {{ preg_replace('/[^0-9]/', '', $activeRound) ?: '1' }} Selesai! Skor Terkunci.
            </p>
            <p class="text-xs text-slate-600 font-medium">
                Poin pada Set ini telah dicatat dan diakumulasikan ke klasemen pemain.
            </p>
            
            @if($nextRoundKey)
                @php
                    $nextRoundNum = preg_replace('/[^0-9]/', '', $nextRoundKey) ?: '2';
                @endphp
                <div class="pt-2">
                    <a href="{{ route('scoring.live', ['id' => $game['id'], 'format' => request('format', $game['match_format'] ?? 'Americano'), 'round' => $nextRoundKey, 'court' => $courtIndex]) }}"
                       class="inline-flex items-center gap-2 px-6 py-3 rounded-2xl bg-[#063B00] hover:bg-[#042a00] text-white font-extrabold text-xs shadow-md transition-all hover:scale-[1.02] active:scale-95">
                        <span>Lanjut ke {{ $unitTabLabel }} {{ $nextRoundNum }} (Susunan Pasangan Baru)</span>
                        <i class="fa-solid fa-arrow-right text-[10px] text-[#A8E63A]"></i>
                    </a>
                </div>
            @else
                <div class="pt-2">
                    <a href="{{ route('scoring.recap', $game['id']) }}"
                       class="inline-flex items-center gap-2 px-6 py-3 rounded-2xl bg-amber-600 hover:bg-amber-700 text-white font-extrabold text-xs shadow-md transition-all hover:scale-[1.02] active:scale-95">
                        <span>🏁 Semua Set Selesai — Buka Klasemen Akhir &amp; Podium</span>
                        <i class="fa-solid fa-trophy text-[10px] text-amber-200"></i>
                    </a>
                </div>
            @endif
        </div>

        <!-- Controls: Selesaikan Sesi & Lihat Juara — hanya untuk Host -->
        @if($isHost)
        <div class="flex flex-col sm:flex-row items-center justify-between gap-3 pt-3 border-t border-slate-200/50">
            <div class="flex items-center gap-2 flex-wrap w-full sm:w-auto">
                <a href="{{ route('scoring.recap', $game['id']) }}"
                   class="px-4 py-2.5 rounded-xl bg-white border border-slate-200 text-slate-700 text-xs font-semibold hover:bg-slate-50 shadow-2xs transition-colors flex items-center gap-1.5 justify-center">
                    <i class="fa-solid fa-ranking-star text-[10px] text-amber-500"></i> Lihat Klasemen Sementara
                </a>

                @if(!$isCurrentSetCompleted)
                    <button type="button" onclick="manualCompleteSet()"
                       class="px-4 py-2.5 rounded-xl bg-amber-50 border border-amber-300 text-amber-900 text-xs font-bold hover:bg-amber-100 shadow-2xs transition-colors flex items-center gap-1.5 justify-center cursor-pointer">
                        <i class="fa-solid fa-lock text-amber-600"></i> Kunci &amp; Selesaikan {{ $unitTabLabel }} Ini
                    </button>
                @endif
            </div>

            <form id="finishForm" action="{{ route('scoring.finish') }}" method="POST" class="w-full sm:w-auto">
                @csrf
                <input type="hidden" name="game_id"         value="{{ $game['id'] }}">
                <input type="hidden" name="round"           value="{{ $activeRound }}">
                <input type="hidden" name="match_key"       value="{{ $matchKey }}">
                <input type="hidden" name="court"           value="{{ $courtIndex + 1 }}">
                <input type="hidden" name="scoring_system"  value="{{ $scoringSystem['label'] }}">
                <input type="hidden" name="score_a"         id="finishScoreA" value="0">
                <input type="hidden" name="score_b"         id="finishScoreB" value="0">
                <input type="hidden" name="sets_a"          id="finishSetsA" value="{{ $currentScore['sets_a'] ?? 0 }}">
                <input type="hidden" name="sets_b"          id="finishSetsB" value="{{ $currentScore['sets_b'] ?? 0 }}">
                <input type="hidden" name="games_a"         id="finishGamesA" value="{{ $currentScore['games_a'] ?? 0 }}">
                <input type="hidden" name="games_b"         id="finishGamesB" value="{{ $currentScore['games_b'] ?? 0 }}">
                <input type="hidden" name="set_number"      id="finishSetNumber" value="{{ $currentScore['set_number'] ?? 1 }}">
                <input type="hidden" name="point_display_a" id="finishPointDisplayA" value="0">
                <input type="hidden" name="point_display_b" id="finishPointDisplayB" value="0">
                <input type="hidden" name="set_history"     id="finishSetHistory" value="{{ json_encode($currentScore['set_history'] ?? []) }}">
                <input type="hidden" name="winner_team"     id="finishWinnerTeam" value="">

                <button type="submit" id="btnFinishSession" onclick="submitFinish(event)"
                    class="w-full sm:w-auto px-5 py-2.5 rounded-xl bg-[#063B00] hover:bg-[#042a00] text-white font-extrabold text-xs shadow-md transition-all hover:scale-[1.01] active:scale-95 flex items-center justify-center gap-2 cursor-pointer">
                    <i class="fa-solid fa-flag-checkered text-[11px] text-[#A8E63A]"></i> Selesaikan Sesi &amp; Lihat Juara
                </button>
            </form>
        </div>
        @else
        {{-- Member: info bahwa skor diperbarui otomatis --}}
        <div class="flex items-center justify-between pt-3 border-t border-slate-200/50">
            <div class="flex items-center gap-2 text-xs text-slate-500">
                <span class="w-2 h-2 rounded-full bg-sky-500 animate-pulse"></span>
                Skor sinkron otomatis realtime setiap <strong id="countdownTimer">2</strong> detik
            </div>
            <a href="{{ route('scoring.recap', $game['id']) }}"
               class="px-4 py-2 rounded-xl bg-white border border-slate-200 text-slate-700 text-xs font-semibold hover:bg-slate-50 shadow-2xs transition-colors flex items-center gap-1.5">
                <i class="fa-solid fa-ranking-star text-[10px]"></i> Lihat Klasemen Sementara
            </a>
        </div>
        @endif
    </div>

    {{-- Tim Istirahat Set Ini --}}
    @if(!empty($matchContext['resting']))
    <div class="glass-card rounded-2xl px-5 py-4 border border-white/90 flex flex-col sm:flex-row sm:items-center justify-between gap-3 shadow-2xs">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-amber-50 text-amber-700 border border-amber-200 flex items-center justify-center text-xs shrink-0 shadow-2xs">
                <i class="fa-solid fa-mug-hot"></i>
            </div>
            <div>
                <p class="text-xs font-bold text-slate-800 flex items-center gap-1.5">
                    Bangku Istirahat (Bench) {{ $unitTabLabel }} Ini
                    <span class="text-[10px] font-semibold text-amber-700 bg-amber-50 px-2 py-0.5 rounded-full border border-amber-200">
                        {{ count($matchContext['resting']) }} Pemain
                    </span>
                </p>
                <div class="flex flex-wrap gap-1.5 pt-1">
                    @foreach($matchContext['resting'] as $rPlayer)
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-white text-slate-700 text-xs font-medium border border-slate-200 shadow-2xs">
                            <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                            {{ is_array($rPlayer) ? ($rPlayer['name'] ?? $rPlayer['nama'] ?? '') : $rPlayer }}
                        </span>
                    @endforeach
                </div>
            </div>
        </div>
        <span class="text-[11px] text-slate-400 font-medium">Rotasi bermain pada Set berikutnya</span>
    </div>
    @endif
</div>

@push('scripts')
<script>
    // ── Config dari PHP ──────────────────────────────────────────────────────
    const SCORING_TYPE   = '{{ $scoringSystem['type'] }}';       // 'total_of_sets' | 'first_to_games'
    const IS_SETS        = {{ $scoringSystem['is_sets'] ? 'true' : 'false' }};
    const TARGET_SETS    = {{ $scoringSystem['target_sets'] ?? 2 }};
    const MAX_SETS       = {{ $scoringSystem['max_sets'] ?? 3 }};
    const TARGET_GAMES   = {{ $scoringSystem['target_games'] ?? 6 }};
    const GAME_ID        = {{ $game['id'] }};
    const ACTIVE_ROUND   = '{{ $activeRound }}';
    const COURT_INDEX    = {{ $courtIndex ?? 0 }};
    const COURT_NUM      = {{ ($courtIndex ?? 0) + 1 }};
    const MATCH_KEY      = '{{ $matchKey ?? $activeRound }}';
    const UNIT_TAB_LABEL = '{{ $unitTabLabel }}';
    const CSRF_TOKEN     = document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}';
    const UPDATE_URL     = '{{ route('scoring.update-score') }}';
    const IS_HOST        = {{ $isHost ? 'true' : 'false' }};
    const RECAP_URL      = '{{ route('scoring.recap', $game['id']) }}';

    // ── Point Ladder ─────────────────────────────────────────────────────────
    const tennisPoints = ['0', '15', '30', '40'];

    // ── State ────────────────────────────────────────────────────────────────
    let idxA         = {{ (int) ($currentScore['idx_a'] ?? 0) }};
    let idxB         = {{ (int) ($currentScore['idx_b'] ?? 0) }};
    let isDeuce      = {{ ($currentScore['is_deuce'] ?? false) ? 'true' : 'false' }};
    let advantage    = {!! json_encode($currentScore['advantage'] ?? null) !!};

    let gamesA       = {{ (int) ($currentScore['games_a'] ?? ($currentScore['score_a'] ?? 0)) }};
    let gamesB       = {{ (int) ($currentScore['games_b'] ?? ($currentScore['score_b'] ?? 0)) }};

    let setNumber    = {{ (int) ($currentScore['set_number'] ?? 1) }};
    let setsA        = {{ (int) ($currentScore['sets_a'] ?? 0) }};
    let setsB        = {{ (int) ($currentScore['sets_b'] ?? 0) }};
    let setHistory   = {!! json_encode($currentScore['set_history'] ?? []) !!} || [];

    let matchDone    = {{ (($currentScore['status'] ?? '') === 'completed') ? 'true' : 'false' }};
    let winnerTeam   = {!! json_encode($currentScore['winner_team'] ?? null) !!};

    let isFinishing           = false;
    let activeAbortController = null;

    // ── Point Display Resolution ─────────────────────────────────────────────
    function getPointDisplays() {
        if (isDeuce) {
            if (advantage === 'A') return { a: 'ADV', b: '40' };
            if (advantage === 'B') return { a: '40', b: 'ADV' };
            return { a: '40', b: '40' };
        }
        return {
            a: tennisPoints[idxA] || '0',
            b: tennisPoints[idxB] || '0',
        };
    }

    // ── Tambah Poin (0 -> 15 -> 30 -> 40 -> Game) ───────────────────────────
    function addPoint(team) {
        if (matchDone) {
            showToast('Skor Set ini sudah selesai dan terkunci.');
            return;
        }

        if (team === 'A') {
            handlePointWonByA();
        } else {
            handlePointWonByB();
        }

        updateDisplay();
        saveScore();
    }

    function handlePointWonByA() {
        if (isDeuce) {
            if (advantage === 'A') {
                gameWonBy('A');
            } else if (advantage === 'B') {
                advantage = null;
                showToast('Kembali ke Deuce (40 - 40)!');
            } else {
                advantage = 'A';
                showToast('Advantage Team A!');
            }
        } else {
            if (idxA < 3) {
                idxA++;
                if (idxA === 3 && idxB === 3) {
                    isDeuce = true;
                    advantage = null;
                    showToast('Deuce (40 - 40)!');
                }
            } else if (idxA === 3 && idxB < 3) {
                gameWonBy('A');
            }
        }
    }

    function handlePointWonByB() {
        if (isDeuce) {
            if (advantage === 'B') {
                gameWonBy('B');
            } else if (advantage === 'A') {
                advantage = null;
                showToast('Kembali ke Deuce (40 - 40)!');
            } else {
                advantage = 'B';
                showToast('Advantage Team B!');
            }
        } else {
            if (idxB < 3) {
                idxB++;
                if (idxA === 3 && idxB === 3) {
                    isDeuce = true;
                    advantage = null;
                    showToast('Deuce (40 - 40)!');
                }
            } else if (idxB === 3 && idxA < 3) {
                gameWonBy('B');
            }
        }
    }

    // ── Game Dimenangkan ─────────────────────────────────────────────────────
    function gameWonBy(team) {
        resetPoints();

        if (team === 'A') {
            gamesA++;
            showToast('🎉 Game Won by Team A!');
        } else {
            gamesB++;
            showToast('🎉 Game Won by Team B!');
        }

        checkSetWinner();
    }

    // Evaluasi apakah set selesai dalam format Americano
    function checkSetWinner() {
        let setWon = null;
        
        // Aturan standar: menang jika mencapai 6 game dengan selisih 2, atau 7-5 / 7-6, atau mencapai TARGET_GAMES
        if ((gamesA >= 6 && gamesA - gamesB >= 2) || (gamesA === 7 && gamesB === 6)) {
            setWon = 'Team A';
        } else if ((gamesB >= 6 && gamesB - gamesA >= 2) || (gamesB === 7 && gamesA === 6)) {
            setWon = 'Team B';
        } else if (TARGET_GAMES > 0 && gamesA >= TARGET_GAMES) {
            setWon = 'Team A';
        } else if (TARGET_GAMES > 0 && gamesB >= TARGET_GAMES) {
            setWon = 'Team B';
        }

        if (setWon) {
            matchDone = true;
            winnerTeam = setWon;
            setsA = (setWon === 'Team A') ? 1 : 0;
            setsB = (setWon === 'Team B') ? 1 : 0;
            showCompletedBanner(setWon);
            saveScore('completed');
        }
    }

    // Manual Selesaikan & Kunci Set oleh Host
    function manualCompleteSet() {
        if (matchDone) return;
        const conf = confirm(`Apakah Anda yakin ingin menyelesaikan dan mengunci skor ${UNIT_TAB_LABEL} ini?`);
        if (!conf) return;

        matchDone = true;
        winnerTeam = (gamesA >= gamesB) ? 'Team A' : 'Team B';
        setsA = (winnerTeam === 'Team A') ? 1 : 0;
        setsB = (winnerTeam === 'Team B') ? 1 : 0;
        showCompletedBanner(winnerTeam);
        updateDisplay();
        saveScore('completed');
        showToast(`Skor ${UNIT_TAB_LABEL} berhasil dikunci!`);
    }

    function resetPoints() {
        idxA = 0;
        idxB = 0;
        isDeuce = false;
        advantage = null;
    }

    // ── Update Display UI ────────────────────────────────────────────────────
    function updateDisplay() {
        const dispA  = document.getElementById('scoreDisplayA');
        const dispB  = document.getElementById('scoreDisplayB');
        const subA   = document.getElementById('subScoreLabelA');
        const subB   = document.getElementById('subScoreLabelB');
        const gameDispA = document.getElementById('displayGameScoreA');
        const gameDispB = document.getElementById('displayGameScoreB');
        const curBadge = document.getElementById('currentSetGamesBadge');
        const notice = document.getElementById('matchNotice');
        const displays = getPointDisplays();

        if (dispA) dispA.innerText = displays.a;
        if (dispB) dispB.innerText = displays.b;
        if (gameDispA) gameDispA.innerText = gamesA;
        if (gameDispB) gameDispB.innerText = gamesB;
        if (curBadge) curBadge.innerText = `Game Score: ${gamesA} — ${gamesB}`;
        if (subA) subA.innerText = `Games Won: ${gamesA} Game`;
        if (subB) subB.innerText = `Games Won: ${gamesB} Game`;

        if (notice) {
            if (matchDone) {
                notice.innerHTML = `🔒 <strong>${UNIT_TAB_LABEL} Selesai & Terkunci</strong> &bull; Skor Akhir: <strong>${gamesA} — ${gamesB}</strong> (${winnerTeam || 'Selesai'})`;
            } else if (isDeuce) {
                if (advantage === 'A') {
                    notice.innerHTML = '<strong class="text-[#063B00]">ADVANTAGE TEAM A</strong> &bull; Butuh 1 poin lagi untuk memenangkan game';
                } else if (advantage === 'B') {
                    notice.innerHTML = '<strong class="text-slate-900">ADVANTAGE TEAM B</strong> &bull; Butuh 1 poin lagi untuk memenangkan game';
                } else {
                    notice.innerHTML = '<strong class="text-amber-700">DEUCE (40 - 40)</strong> &bull; Perebutan advantage point';
                }
            } else {
                notice.innerHTML = `${UNIT_TAB_LABEL} score: <strong>${gamesA}</strong> — <strong>${gamesB}</strong> &bull; Point: <strong>${displays.a} : ${displays.b}</strong>`;
            }
        }

        const bA = document.getElementById('btnAddA');
        const bB = document.getElementById('btnAddB');
        if (matchDone) {
            showCompletedBanner(winnerTeam || 'Pertandingan');
            if (bA) {
                bA.disabled = true;
                bA.className = 'w-full py-3.5 rounded-xl bg-slate-100 text-slate-400 font-bold text-sm border border-slate-200 cursor-not-allowed flex items-center justify-center gap-2';
                bA.innerHTML = '<i class="fa-solid fa-lock text-xs"></i> Skor Terkunci (Set Selesai)';
            }
            if (bB) {
                bB.disabled = true;
                bB.className = 'w-full py-3.5 rounded-xl bg-slate-100 text-slate-400 font-bold text-sm border border-slate-200 cursor-not-allowed flex items-center justify-center gap-2';
                bB.innerHTML = '<i class="fa-solid fa-lock text-xs"></i> Skor Terkunci (Set Selesai)';
            }
        } else {
            if (bA) {
                bA.disabled = false;
                bA.classList.remove('opacity-50', 'pointer-events-none', 'cursor-not-allowed');
            }
            if (bB) {
                bB.disabled = false;
                bB.classList.remove('opacity-50', 'pointer-events-none', 'cursor-not-allowed');
            }
        }

        syncFinishFormInputs();
    }

    // ── Banner Match Selesai ─────────────────────────────────────────────────
    function showCompletedBanner(winner) {
        const banner = document.getElementById('matchCompletedBanner');
        const msg    = document.getElementById('completedMsg');
        const scoreSummary = `Skor: ${gamesA} — ${gamesB} Games`;

        if (msg) msg.textContent = `🏆 ${winner} Memenangkan ${UNIT_TAB_LABEL} Ini! (${scoreSummary})`;
        if (banner) banner.classList.remove('hidden');

        const bA = document.getElementById('btnAddA');
        const bB = document.getElementById('btnAddB');
        if (bA) {
            bA.disabled = true;
            bA.className = 'w-full py-3.5 rounded-xl bg-slate-100 text-slate-400 font-bold text-sm border border-slate-200 cursor-not-allowed flex items-center justify-center gap-2';
            bA.innerHTML = '<i class="fa-solid fa-lock text-xs"></i> Skor Terkunci (Set Selesai)';
        }
        if (bB) {
            bB.disabled = true;
            bB.className = 'w-full py-3.5 rounded-xl bg-slate-100 text-slate-400 font-bold text-sm border border-slate-200 cursor-not-allowed flex items-center justify-center gap-2';
            bB.innerHTML = '<i class="fa-solid fa-lock text-xs"></i> Skor Terkunci (Set Selesai)';
        }
    }

    // ── Simpan Skor ke Server (AJAX Polling / Cache) ───────────────────────────
    async function saveScore(status = null) {
        if (isFinishing) return;

        if (activeAbortController) {
            try { activeAbortController.abort(); } catch(e) {}
        }
        activeAbortController = new AbortController();

        const displays = getPointDisplays();
        const currentStatus = status ?? (matchDone ? 'completed' : 'in_progress');
        const body = {
            game_id         : GAME_ID,
            round           : ACTIVE_ROUND,
            match_key       : MATCH_KEY,
            court           : COURT_NUM,
            scoring_type    : SCORING_TYPE,
            score_a         : gamesA,
            score_b         : gamesB,
            point_display_a : displays.a,
            point_display_b : displays.b,
            set_number      : 1,
            sets_a          : (gamesA >= gamesB && currentStatus === 'completed') ? 1 : 0,
            sets_b          : (gamesB > gamesA && currentStatus === 'completed') ? 1 : 0,
            games_a         : gamesA,
            games_b         : gamesB,
            set_history     : [{ set: 1, score_a: gamesA, score_b: gamesB }],
            idx_a           : idxA,
            idx_b           : idxB,
            is_deuce        : isDeuce,
            advantage       : advantage,
            winner_team     : winnerTeam || (gamesA >= gamesB ? 'Team A' : 'Team B'),
            status          : currentStatus,
        };

        try {
            const res = await fetch(UPDATE_URL, {
                method : 'POST',
                headers: {
                    'Content-Type' : 'application/json',
                    'X-CSRF-TOKEN' : CSRF_TOKEN,
                    'Accept'       : 'application/json',
                },
                signal: activeAbortController.signal,
                body: JSON.stringify(body),
            });
            if (!res.ok) {
                console.error('Update score failed:', await res.text());
            }
        } catch (err) {
            if (err.name !== 'AbortError') {
                console.warn('Gagal simpan skor:', err);
            }
        }
    }

    // ── Sinkronkan Input Form Finish Tersembunyi ─────────────────────────────
    function syncFinishFormInputs() {
        const finA       = document.getElementById('finishScoreA');
        const finB       = document.getElementById('finishScoreB');
        const finSetsA   = document.getElementById('finishSetsA');
        const finSetsB   = document.getElementById('finishSetsB');
        const finGamesA  = document.getElementById('finishGamesA');
        const finGamesB  = document.getElementById('finishGamesB');
        const finSetNum  = document.getElementById('finishSetNumber');
        const finPDispA  = document.getElementById('finishPointDisplayA');
        const finPDispB  = document.getElementById('finishPointDisplayB');
        const finHistory = document.getElementById('finishSetHistory');
        const finWinner  = document.getElementById('finishWinnerTeam');

        const curWinner  = winnerTeam || (gamesA >= gamesB ? 'Team A' : 'Team B');
        const displays   = getPointDisplays();

        if (finPDispA)  finPDispA.value  = displays.a;
        if (finPDispB)  finPDispB.value  = displays.b;
        if (finSetsA)   finSetsA.value   = (gamesA >= gamesB) ? 1 : 0;
        if (finSetsB)   finSetsB.value   = (gamesB > gamesA) ? 1 : 0;
        if (finGamesA)  finGamesA.value  = gamesA;
        if (finGamesB)  finGamesB.value  = gamesB;
        if (finSetNum)  finSetNum.value  = 1;
        if (finHistory) finHistory.value = JSON.stringify([{ set: 1, score_a: gamesA, score_b: gamesB }]);
        if (finA)       finA.value       = gamesA;
        if (finB)       finB.value       = gamesB;
        if (finWinner)  finWinner.value  = curWinner;
    }

    // ── Submit Form Finish ────────────────────────────────────────────────────
    function submitFinish(event) {
        if (event) {
            event.preventDefault();
        }

        isFinishing = true;
        if (activeAbortController) {
            try { activeAbortController.abort(); } catch(e) {}
        }

        syncFinishFormInputs();

        const btn = document.getElementById('btnFinishSession');
        if (btn) {
            btn.disabled = true;
            btn.classList.add('opacity-75', 'cursor-not-allowed');
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin text-xs text-[#A8E63A]"></i> Menyimpan Sesi &amp; Menghitung Juara...';
        }

        showToast('Menyimpan hasil akhir pertandingan...');

        const form = document.getElementById('finishForm');
        if (form) {
            form.submit();
        }
    }

    // ── Auto-Refresh Realtime untuk Member / Penonton (Polling via fetch) ─────
    if (!IS_HOST) {
        const POLL_URL      = `{{ url('scoring/get-score') }}/${GAME_ID}/${ACTIVE_ROUND}?court=${COURT_INDEX}&match_key=${MATCH_KEY}`;
        const POLL_INTERVAL = 1500;
        let countdown       = 2;
        const timerEl       = document.getElementById('countdownTimer');

        setInterval(() => {
            countdown--;
            if (timerEl) timerEl.textContent = countdown <= 0 ? 2 : countdown;
            if (countdown <= 0) countdown = 2;
        }, 1000);

        setInterval(async () => {
            try {
                const res = await fetch(POLL_URL, { headers: { 'Accept': 'application/json' } });
                if (!res.ok) return;
                const data = await res.json();

                // Sinkron state dari server
                idxA        = data.idx_a ?? 0;
                idxB        = data.idx_b ?? 0;
                isDeuce     = !!data.is_deuce;
                advantage   = data.advantage ?? null;
                gamesA      = data.games_a ?? (data.score_a ?? 0);
                gamesB      = data.games_b ?? (data.score_b ?? 0);
                setsA       = data.sets_a ?? 0;
                setsB       = data.sets_b ?? 0;
                setNumber   = 1;
                setHistory  = data.set_history ?? [];
                matchDone   = (data.status === 'completed');
                winnerTeam  = data.winner_team ?? null;

                const dispA  = document.getElementById('scoreDisplayA');
                const dispB  = document.getElementById('scoreDisplayB');
                const targetA = String(data.point_display_a ?? '0');
                const targetB = String(data.point_display_b ?? '0');

                if (dispA && dispA.innerText !== targetA) {
                    dispA.innerText = targetA;
                    dispA.classList.add('scale-110');
                    setTimeout(() => dispA.classList.remove('scale-110'), 300);
                }
                if (dispB && dispB.innerText !== targetB) {
                    dispB.innerText = targetB;
                    dispB.classList.add('scale-110');
                    setTimeout(() => dispB.classList.remove('scale-110'), 300);
                }

                updateDisplay();
            } catch (e) {
                // Ignore network errors
            }
        }, POLL_INTERVAL);
    }

    // Inisialisasi awal
    updateDisplay();
</script>
@endpush
@endsection
