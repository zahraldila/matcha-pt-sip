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
            <div class="flex items-center gap-1.5">
                <span id="realtimeSyncBadge" class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 text-[10px] font-bold">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span> Realtime Active
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
    </div>

    {{-- Flash Notifications (Warning, Error, Success) --}}
    @if(session('warning') || session('error') || session('success'))
        <div class="space-y-2">
            @if(session('warning'))
                <div class="p-4 rounded-2xl bg-amber-50 border border-amber-300 text-amber-900 text-xs font-bold flex items-center gap-3 shadow-xs">
                    <i class="fa-solid fa-triangle-exclamation text-amber-600 text-base shrink-0"></i>
                    <span>{{ session('warning') }}</span>
                </div>
            @endif
            @if(session('error'))
                <div class="p-4 rounded-2xl bg-rose-50 border border-rose-300 text-rose-900 text-xs font-bold flex items-center gap-3 shadow-xs">
                    <i class="fa-solid fa-circle-exclamation text-rose-600 text-base shrink-0"></i>
                    <span>{{ session('error') }}</span>
                </div>
            @endif
            @if(session('success'))
                <div class="p-4 rounded-2xl bg-emerald-50 border border-emerald-300 text-[#063B00] text-xs font-bold flex items-center gap-3 shadow-xs">
                    <i class="fa-solid fa-circle-check text-emerald-600 text-base shrink-0"></i>
                    <span>{{ session('success') }}</span>
                </div>
            @endif
        </div>
    @endif

    {{-- Round / Set Selector (Set 1, Set 2, Set 3) --}}
    @php
        $isTeamFormat = str_contains(strtolower($game['match_format'] ?? ''), 'team');
        // Team Americano menggunakan istilah 'Set' (pasangan tetap), sedangkan Americano menggunakan 'Ronde' (pasangan berganti)
        $unitTabLabel = $isTeamFormat ? 'Set' : 'Ronde';
        $drawingRounds = !empty($game['drawing']) ? array_keys($game['drawing']) : [];
        if ($scoringSystem['is_sets']) {
            $numSets = max(count($drawingRounds), (int) ($scoringSystem['max_sets'] ?? 1));
            $allRoundsList = array_map(fn ($roundNumber) => "round_{$roundNumber}", range(1, $numSets));
        } else {
            $allRoundsList = !empty($drawingRounds) ? $drawingRounds : ['round_1'];
        }
        $currentRIndex = array_search($activeRound, $allRoundsList);
        $nextRoundKey = ($currentRIndex !== false && isset($allRoundsList[$currentRIndex + 1])) ? $allRoundsList[$currentRIndex + 1] : null;
        $isCurrentSetCompleted = (($currentScore['status'] ?? '') === 'completed');

        // Baca mode Single/Double dari session (via $game atau $matchContext)
        // $game dibangun dari getGameData() yang juga mengakses jenis_permainan
        $liveDrawingMode  = $game['jenis_permainan'] ?? 'Double'; // Akan ada jika getGameData() mengembalikannya
        $isLiveSingleMode = strtolower($liveDrawingMode) === 'single';
        $matchFormatLabel = $isTeamFormat
            ? 'Team Americano (Tim Tetap)'
            : ('Americano ' . ($isLiveSingleMode ? 'Single (1 vs 1)' : 'Double (2 vs 2)'));
    @endphp

    @if(!empty($allRoundsList))
    <div class="glass-card rounded-2xl p-4 border border-white/90 shadow-2xs space-y-3">
        <!-- Baris Atas: Label & Format Info -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-slate-200/50 pb-2.5">
            <div class="flex items-center gap-2">
                <span class="text-xs font-bold text-slate-800 flex items-center gap-1.5">
                    <i class="fa-solid fa-layer-group text-[#063B00]"></i> Pilih {{ $unitTabLabel }}:
                </span>
                <span class="text-[10px] font-bold text-slate-500 bg-slate-100 px-2 py-0.5 rounded-full border border-slate-200">
                    {{ count($allRoundsList) }} {{ $unitTabLabel }}
                </span>
            </div>
            <span class="text-[11px] text-slate-500 font-semibold">
                Format: <strong class="{{ $isTeamFormat ? 'text-indigo-700' : ($isLiveSingleMode ? 'text-amber-700' : 'text-[#063B00]') }}">{{ $matchFormatLabel }}</strong>
            </span>
        </div>

        <!-- Baris Bawah: Pills Tab Selector (Wrap Bersih, Tanpa Scrollbar) -->
        <div class="flex flex-wrap items-center gap-2">
            @foreach($allRoundsList as $rKey)
                @php
                    $rNumber = preg_replace('/[^0-9]/', '', $rKey) ?: '1';
                    $tabDisplayTitle = "{$unitTabLabel} {$rNumber}";
                    $isTabActive = ($activeRound === $rKey);
                    $isRoundAccessible = $roundAccess[$rKey] ?? false;
                    $rScore = $savedScores[$rKey] ?? ($savedScores["{$rKey}_court_1"] ?? []);
                    $isRCompleted = (($rScore['status'] ?? '') === 'completed');
                @endphp
                @if($isRoundAccessible)
                    <a href="{{ route('scoring.live', ['id' => $game['id'], 'format' => request('format', $game['match_format'] ?? 'Americano'), 'round' => $rKey, 'court' => $courtIndex]) }}"
                       class="px-3.5 py-1.5 rounded-xl text-xs font-bold border transition-all flex items-center gap-1.5 shadow-2xs hover:scale-[1.02] active:scale-95
                              {{ $isTabActive
                                   ? 'bg-[#063B00] text-white border-[#063B00] shadow-xs ring-2 ring-[#063B00]/20'
                                   : 'bg-white text-slate-700 border-slate-200 hover:border-[#063B00]/50 hover:bg-slate-50' }}">
                        <span>{{ $tabDisplayTitle }}</span>
                        @if($isRCompleted)
                            <span class="text-[9px] px-1.5 py-0.2 rounded-md font-extrabold {{ $isTabActive ? 'bg-white/20 text-[#A8E63A]' : 'bg-emerald-50 text-emerald-700 border border-emerald-200' }}">✓ Selesai</span>
                        @endif
                    </a>
                @else
                    <div class="px-3.5 py-1.5 rounded-xl text-xs font-bold border flex items-center gap-1.5 bg-slate-100/90 text-slate-400 border-slate-200/80 cursor-not-allowed select-none">
                        <span>{{ $tabDisplayTitle }}</span>
                        <span class="text-[9px] px-1.5 py-0.2 rounded-md bg-slate-200/80 text-slate-500 font-semibold flex items-center gap-1">
                            <i class="fa-solid fa-lock text-[8px]"></i> Terkunci
                        </span>
                    </div>
                @endif
            @endforeach
        </div>
    </div>
    @endif

    
    @php
        $courtCount = $matchContext['court_count'] ?? count($matchContext['matches'] ?? []);
        $activeRoundNum = preg_replace('/[^0-9]/', '', $activeRound) ?: '1';
        $uncompletedCourtNames = [];
        foreach ($matchContext['matches'] ?? [] as $checkIdx => $checkMatch) {
            $checkKey = ($courtCount > 1) ? "{$activeRound}_court_" . ($checkIdx + 1) : $activeRound;
            $checkScore = $savedScores[$checkKey] ?? ($courtCount > 1 ? [] : ($savedScores[$activeRound] ?? ($savedScores["{$activeRound}_court_1"] ?? [])));
            if (($checkScore['status'] ?? '') !== 'completed') {
                $uncompletedCourtNames[$checkIdx] = $checkMatch['court_name'] ?? ('Court ' . ($checkIdx + 1));
            }
        }
        $allCourtsCompleted = empty($uncompletedCourtNames);
    @endphp
    <div class="grid grid-cols-1 {{ $courtCount > 1 ? 'lg:grid-cols-2' : '' }} gap-6">
        @foreach($matchContext['matches'] ?? [] as $mIdx => $matchData)
            @php
                $mKey = ($courtCount > 1) ? "{$activeRound}_court_" . ($mIdx + 1) : $activeRound;
                $currentScore = $savedScores[$mKey] ?? ($courtCount > 1 ? [] : ($savedScores[$activeRound] ?? []));
                $isMCompleted = (($currentScore['status'] ?? '') === 'completed');

                $tAPlayers = !empty($matchData['team_a_names']) 
                    ? $matchData['team_a_names'] 
                    : (!empty($matchData['teamA_names']) 
                        ? $matchData['teamA_names'] 
                        : (isset($matchData['team_a']['player_names']) 
                            ? $matchData['team_a']['player_names'] 
                            : ($matchData['team_a'] ?? [])));
                if (!is_array($tAPlayers)) {
                    $tAPlayers = [$tAPlayers];
                }

                $tBPlayers = !empty($matchData['team_b_names']) 
                    ? $matchData['team_b_names'] 
                    : (!empty($matchData['teamB_names']) 
                        ? $matchData['teamB_names'] 
                        : (isset($matchData['team_b']['player_names']) 
                            ? $matchData['team_b']['player_names'] 
                            : ($matchData['team_b'] ?? [])));
                if (!is_array($tBPlayers)) {
                    $tBPlayers = [$tBPlayers];
                }

                $tAName = $matchData['team_a_name'] 
                    ?? ($matchData['team_a']['display_name'] 
                    ?? ($matchData['team_a']['name'] 
                    ?? ($matchData['teamA_display'] 
                    ?? (count($matchContext['matches'] ?? []) > 1 ? "Court " . ($mIdx + 1) . " - Team A" : "TEAM A"))));

                $tBName = $matchData['team_b_name'] 
                    ?? ($matchData['team_b']['display_name'] 
                    ?? ($matchData['team_b']['name'] 
                    ?? ($matchData['teamB_display'] 
                    ?? (count($matchContext['matches'] ?? []) > 1 ? "Court " . ($mIdx + 1) . " - Team B" : "TEAM B"))));
            @endphp
        <!-- Live Scoreboard Display (Subtle Glass) -->

    <div class="glass-card rounded-3xl p-6 sm:p-8 space-y-6 border border-white/90 shadow-sm relative">
        
        <!-- Locked Badge Notification if this set match is completed -->
        <div id="lockedBadge_{{ $mIdx }}" class="{{ $isMCompleted ? '' : 'hidden' }} p-3 bg-amber-50 border border-amber-200 rounded-2xl text-amber-900 text-xs font-bold flex items-center justify-between shadow-2xs">
            <span class="flex items-center gap-2">
                <i class="fa-solid fa-lock text-amber-600 text-sm"></i>
                <span id="lockedBadgeText_{{ $mIdx }}">Skor {{ $unitTabLabel }} {{ $activeRoundNum }} pada {{ $matchData['court_name'] ?? ('Court ' . ($mIdx + 1)) }} sudah selesai dan terkunci.</span>
            </span>
            <span class="text-[10px] bg-amber-200/80 px-2 py-0.5 rounded-full uppercase tracking-wider font-extrabold text-amber-800">Final Score</span>
        </div>

        <!-- Match Info Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 text-xs text-slate-500 border-b border-slate-200/50 pb-3">
            <div class="flex items-center gap-2 flex-wrap">
                <strong class="text-slate-800">{{ $game['venue_name'] }}</strong> &bull; <span class="text-[#063B00] font-bold">{{ $matchData['court_name'] ?? ('Court ' . ($mIdx + 1)) }}</span> &bull; <span class="font-bold text-slate-700">{{ $unitTabLabel }} {{ $activeRoundNum }}</span>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200/80 shadow-2xs">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                    <span>Sync: <strong id="topSyncTimer_{{ $mIdx }}" class="font-black">0.8s</strong></span>
                </span>
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
                    <i class="fa-solid fa-trophy text-[11px]"></i> {{ $unitTabLabel }} {{ $activeRoundNum }} SCORE
                </span>
                <span class="font-medium text-slate-400">
                    Target: <strong class="text-white">{{ $scoringSystem['is_sets'] ? ($scoringSystem['target_sets'] . ' Set') : ($scoringSystem['target_games'] . ' Games') }}</strong>
                </span>
            </div>

            <div class="flex items-center justify-center gap-6 sm:gap-10 py-1">
                <div class="text-center">
                    <span class="text-[10px] uppercase font-bold text-slate-400 block">Games Team A</span>
                    <span id="displayGameScoreA_{{ $mIdx }}" class="text-3xl sm:text-4xl font-black text-white">
                        {{ $currentScore['games_a'] ?? ($currentScore['score_a'] ?? 0) }}
                    </span>
                </div>
                <div class="text-slate-500 font-black text-2xl sm:text-3xl">&mdash;</div>
                <div class="text-center">
                    <span class="text-[10px] uppercase font-bold text-slate-400 block">Games Team B</span>
                    <span id="displayGameScoreB_{{ $mIdx }}" class="text-3xl sm:text-4xl font-black text-white">
                        {{ $currentScore['games_b'] ?? ($currentScore['score_b'] ?? 0) }}
                    </span>
                </div>
            </div>

            <div id="setHistoryContainer_{{ $mIdx }}" class="flex items-center justify-center gap-2 flex-wrap pt-1 text-[11px]">
                <span class="text-slate-400 font-semibold" id="currentSetLabel_{{ $mIdx }}">
                    Status: <strong>{{ $isMCompleted ? ($unitTabLabel . ' Selesai & Terkunci') : 'Sedang Berlangsung' }}</strong>
                </span>
                <span id="currentSetGamesBadge_{{ $mIdx }}" class="px-2.5 py-0.5 rounded-md bg-white/10 text-[#A8E63A] font-bold border border-white/10">
                    Game Score: {{ $currentScore['games_a'] ?? 0 }} &mdash; {{ $currentScore['games_b'] ?? 0 }}
                </span>
            </div>
        </div>

        <!-- Dynamic Scoreboard: POINT SCORING (0 -> 15 -> 30 -> 40 -> Game) -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 items-center">
            
            <!-- Team A Side -->
            <div class="bg-white/80 backdrop-blur-md rounded-2xl p-6 border border-slate-200/80 text-center space-y-4 shadow-xs relative">
                <span class="inline-block px-2.5 py-0.5 rounded-md bg-[#EBF8D8] text-[#063B00] border border-[#063B00]/25 font-extrabold text-[10px] uppercase tracking-wider">
                    {{ $tAName }}
                </span>
                
                <div class="space-y-0.5">
                    @forelse($tAPlayers as $playerItem)
                        @php
                            $pName = is_array($playerItem) 
                                ? ($playerItem['name'] ?? $playerItem['nama'] ?? '') 
                                : (is_object($playerItem) ? ($playerItem->name ?? $playerItem->nama ?? '') : (string) $playerItem);
                        @endphp
                        @if($pName)
                            <h3 class="text-sm font-bold text-slate-900">{{ $pName }}</h3>
                        @endif
                    @empty
                        <h3 class="text-sm font-bold text-slate-400 italic">Tim A belum ditentukan</h3>
                    @endforelse
                </div>

                <!-- Live Point Big Display (0, 15, 30, 40, ADV) -->
                <div class="py-2">
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">CURRENT POINT</span>
                    <div id="scoreDisplayA_{{ $mIdx }}" class="text-6xl font-black text-slate-900 tracking-tight transition-all duration-300">
                        {{ $currentScore['point_display_a'] ?? '0' }}
                    </div>
                    <span id="subScoreLabelA_{{ $mIdx }}" class="text-xs text-slate-500 font-semibold block mt-1">
                        Games Won: {{ $currentScore['games_a'] ?? 0 }} Game
                    </span>
                </div>

                <!-- Point Button — hanya tampil untuk Host -->
                @if($isHost)
                    @if($isMCompleted)
                        <button id="btnAddA_{{ $mIdx }}" disabled class="w-full py-3.5 rounded-xl bg-slate-100 text-slate-400 font-bold text-sm border border-slate-200 cursor-not-allowed flex items-center justify-center gap-2">
                            <i class="fa-solid fa-lock text-xs"></i> Skor Terkunci ({{ $unitTabLabel }} Selesai)
                        </button>
                    @else
                        <button id="btnAddA_{{ $mIdx }}" onclick="addPoint('A', {{ $mIdx }})" class="w-full py-3.5 rounded-xl bg-[#063B00] hover:bg-[#042a00] text-white font-bold text-sm shadow-sm transition-all hover:scale-[1.01] active:scale-95 flex items-center justify-center gap-2 cursor-pointer">
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
                    {{ $tBName }}
                </span>

                <div class="space-y-0.5">
                    @forelse($tBPlayers as $playerItem)
                        @php
                            $pName = is_array($playerItem) 
                                ? ($playerItem['name'] ?? $playerItem['nama'] ?? '') 
                                : (is_object($playerItem) ? ($playerItem->name ?? $playerItem->nama ?? '') : (string) $playerItem);
                        @endphp
                        @if($pName)
                            <h3 class="text-sm font-bold text-slate-900">{{ $pName }}</h3>
                        @endif
                    @empty
                        <h3 class="text-sm font-bold text-slate-400 italic">Tim B belum ditentukan</h3>
                    @endforelse
                </div>

                <!-- Live Point Big Display (0, 15, 30, 40, ADV) -->
                <div class="py-2">
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">CURRENT POINT</span>
                    <div id="scoreDisplayB_{{ $mIdx }}" class="text-6xl font-black text-slate-900 tracking-tight transition-all duration-300">
                        {{ $currentScore['point_display_b'] ?? '0' }}
                    </div>
                    <span id="subScoreLabelB_{{ $mIdx }}" class="text-xs text-slate-500 font-semibold block mt-1">
                        Games Won: {{ $currentScore['games_b'] ?? 0 }} Game
                    </span>
                </div>

                <!-- Point Button — hanya tampil untuk Host -->
                @if($isHost)
                    @if($isMCompleted)
                        <button id="btnAddB_{{ $mIdx }}" disabled class="w-full py-3.5 rounded-xl bg-slate-100 text-slate-400 font-bold text-sm border border-slate-200 cursor-not-allowed flex items-center justify-center gap-2">
                            <i class="fa-solid fa-lock text-xs"></i> Skor Terkunci ({{ $unitTabLabel }} Selesai)
                        </button>
                    @else
                        <button id="btnAddB_{{ $mIdx }}" onclick="addPoint('B', {{ $mIdx }})" class="w-full py-3.5 rounded-xl bg-[#063B00] hover:bg-[#042a00] text-white font-bold text-sm shadow-sm transition-all hover:scale-[1.01] active:scale-95 flex items-center justify-center gap-2 cursor-pointer">
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
        <div id="matchNotice_{{ $mIdx }}" class="p-3.5 bg-white/90 rounded-xl border border-slate-200/70 text-center text-xs font-semibold text-slate-700 shadow-2xs">
            @if($isMCompleted)
                🔒 <strong>{{ $unitTabLabel }} {{ $activeRoundNum }} Selesai & Terkunci</strong> &bull; Skor: {{ $currentScore['games_a'] ?? 0 }} &mdash; {{ $currentScore['games_b'] ?? 0 }}
            @else
                Point: <strong>0 &mdash; 0</strong> &bull; <em>Game sedang berlangsung</em>
            @endif
        </div>

        <!-- Match Completed Banner -->
        <div id="matchCompletedBanner_{{ $mIdx }}" class="{{ $isMCompleted ? '' : 'hidden' }} p-5 rounded-2xl bg-[#EBF8D8] border border-[#063B00]/30 text-center space-y-2 shadow-sm">
            <div class="text-3xl">🏆</div>
            <p class="text-base font-black text-[#063B00]" id="completedMsg_{{ $mIdx }}">
                {{ $matchData['court_name'] ?? ('Court ' . ($mIdx + 1)) }} telah selesai pada {{ $unitTabLabel }} {{ $activeRoundNum }}!
            </p>
            <p class="text-xs text-slate-600 font-medium" id="completedSubMsg_{{ $mIdx }}">
                Skor Akhir: <strong>{{ $currentScore['games_a'] ?? 0 }} &mdash; {{ $currentScore['games_b'] ?? 0 }} Games</strong> &bull; Poin telah dicatat ke klasemen.
            </p>
        </div>

        <!-- Controls: Kunci & Selesaikan Court Ini — hanya untuk Host -->
        @if($isHost)
        <div class="flex items-center justify-between gap-3 pt-3 border-t border-slate-200/50">
            <button type="button" id="btnManualComplete_{{ $mIdx }}" onclick="manualCompleteSet({{ $mIdx }})"
               class="{{ $isMCompleted ? 'hidden' : '' }} px-4 py-2.5 rounded-xl bg-amber-50 border border-amber-300 text-amber-900 text-xs font-bold hover:bg-amber-100 shadow-2xs transition-colors flex items-center gap-1.5 justify-center cursor-pointer">
                <i class="fa-solid fa-lock text-amber-600"></i> Kunci &amp; Selesaikan Court Ini
            </button>

            <div class="flex items-center gap-1.5 text-[11px] text-slate-400 pl-1 ml-auto">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                <span>Sinkron realtime: <strong id="hostSyncTimer_{{ $mIdx }}" class="text-emerald-700 font-bold">0.8s</strong></span>
            </div>
        </div>
        @else
        {{-- Member: info bahwa skor diperbarui otomatis --}}
        <div class="flex items-center justify-between pt-3 border-t border-slate-200/50">
            <div class="flex items-center gap-2 text-xs text-slate-500">
                <span class="w-2 h-2 rounded-full bg-sky-500 animate-pulse"></span>
                <span>Skor sinkron otomatis realtime setiap <strong id="countdownTimer_{{ $mIdx }}" class="text-sky-700 font-bold">0.8s</strong></span>
            </div>
        </div>
        @endif
    </div>
        @endforeach
    </div>

    <!-- Global Session Control & Navigation Bar (Single Unified Control) -->
    <div id="globalSessionControlBar" class="glass-card rounded-2xl p-5 border border-white/90 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
        <!-- Status Indicator & Info -->
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-emerald-50 text-[#063B00] border border-emerald-200 flex items-center justify-center text-base shrink-0 shadow-2xs">
                <i class="fa-solid fa-flag-checkered"></i>
            </div>
            <div>
                <div class="flex items-center gap-2">
                    <span id="globalRoundStatusDot" class="w-2.5 h-2.5 rounded-full {{ ($allCourtsCompleted ?? false) ? 'bg-emerald-500' : 'bg-amber-500 animate-pulse' }}"></span>
                    <h3 id="globalRoundStatusTitle" class="text-sm font-extrabold text-slate-800">
                        @if($allCourtsCompleted ?? false)
                            Seluruh Pertandingan {{ $unitTabLabel }} {{ $activeRoundNum }} Selesai!
                        @else
                            Status Pertandingan {{ $unitTabLabel }} {{ $activeRoundNum }}
                        @endif
                    </h3>
                </div>
                <p id="globalRoundStatusDesc" class="text-xs text-slate-500 font-medium mt-0.5">
                    @if($allCourtsCompleted ?? false)
                        @if($isHost)
                            Semua court telah menyelesaikan pertandingan. Silakan lanjut ke {{ $nextRoundKey ? strtolower($unitTabLabel) . ' berikutnya' : 'hasil akhir & podium' }}.
                        @else
                            Semua court telah menyelesaikan pertandingan. Menunggu Host {{ $nextRoundKey ? 'memulai ' . strtolower($unitTabLabel) . ' berikutnya' : 'menyelesaikan sesi' }}...
                        @endif
                    @else
                        @php
                            $uncompletedNames = array_values($uncompletedCourtNames ?? []);
                            $uncompletedStr = implode(', ', $uncompletedNames);
                        @endphp
                        {{ $uncompletedStr ? 'Menunggu ' . $uncompletedStr . ' menyelesaikan pertandingan...' : 'Pertandingan sedang berlangsung pada seluruh court...' }}
                    @endif
                </p>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="flex items-center gap-3 flex-wrap justify-end">
            <a href="{{ route('scoring.recap', $game['id']) }}"
               class="px-4 py-2.5 rounded-xl bg-white border border-slate-200 text-slate-700 text-xs font-semibold hover:bg-slate-50 shadow-2xs transition-colors flex items-center gap-1.5 justify-center">
                <i class="fa-solid fa-ranking-star text-[11px] text-amber-500"></i> Klasemen Sementara
            </a>

            @if($nextRoundKey)
                @if($isHost)
                    @php
                        $nextRoundNum = preg_replace('/[^0-9]/', '', $nextRoundKey) ?: '2';
                    @endphp
                    <form id="globalNextRoundForm" action="{{ route('scoring.next-round') }}" method="POST" class="{{ ($allCourtsCompleted ?? false) ? '' : 'hidden' }} m-0">
                        @csrf
                        <input type="hidden" name="game_id" value="{{ $game['id'] }}">
                        <input type="hidden" name="current_round" value="{{ $activeRound }}">
                        <input type="hidden" name="next_round" value="{{ $nextRoundKey }}">
                        <input type="hidden" name="format" value="{{ request('format', $game['match_format'] ?? 'Americano') }}">
                        <input type="hidden" name="court" value="{{ $courtIndex }}">

                        <button type="submit" id="btnGlobalNextRound" onclick="submitNextRound(event)"
                            class="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl bg-[#063B00] hover:bg-[#042a00] text-white font-extrabold text-xs shadow-md transition-all hover:scale-[1.01] active:scale-95 cursor-pointer">
                            <span id="btnNextRoundText">Lanjut ke {{ $unitTabLabel }} {{ $nextRoundNum }} (Pertandingan Berikutnya)</span>
                            <i id="btnNextRoundIcon" class="fa-solid fa-arrow-right text-[11px] text-[#A8E63A]"></i>
                        </button>
                    </form>
                @endif
            @else
                @if($isHost)
                    <form id="globalFinishForm" action="{{ route('scoring.finish') }}" method="POST" class="{{ ($allCourtsCompleted ?? false) ? '' : 'hidden' }} m-0">
                        @csrf
                        <input type="hidden" name="game_id"         value="{{ $game['id'] }}">
                        <input type="hidden" name="round"           value="{{ $activeRound }}">
                        <input type="hidden" name="scoring_system"  value="{{ $scoringSystem['label'] }}">
                        <input type="hidden" name="score_a"         id="globalFinishScoreA" value="0">
                        <input type="hidden" name="score_b"         id="globalFinishScoreB" value="0">
                        <input type="hidden" name="sets_a"          id="globalFinishSetsA" value="0">
                        <input type="hidden" name="sets_b"          id="globalFinishSetsB" value="0">
                        <input type="hidden" name="games_a"         id="globalFinishGamesA" value="0">
                        <input type="hidden" name="games_b"         id="globalFinishGamesB" value="0">
                        <input type="hidden" name="set_number"      id="globalFinishSetNumber" value="{{ $activeRoundNum }}">
                        <input type="hidden" name="winner_team"     id="globalFinishWinnerTeam" value="">

                        <button type="submit" id="btnGlobalFinishSession" onclick="submitGlobalFinish(event)"
                            class="px-6 py-2.5 rounded-xl bg-[#063B00] hover:bg-[#042a00] text-white font-extrabold text-xs shadow-md transition-all hover:scale-[1.01] active:scale-95 flex items-center justify-center gap-2 cursor-pointer">
                            <i class="fa-solid fa-trophy text-[11px] text-[#A8E63A]"></i> Selesaikan Sesi &amp; Lihat Juara
                        </button>
                    </form>
                @else
                    <a id="btnGlobalRecap" href="{{ route('scoring.recap', $game['id']) }}"
                       class="{{ ($allCourtsCompleted ?? false) ? '' : 'hidden' }} inline-flex items-center gap-2 px-6 py-2.5 rounded-xl bg-amber-600 hover:bg-amber-700 text-white font-extrabold text-xs shadow-md transition-all hover:scale-[1.01] active:scale-95">
                        <span>🏁 Selesai — Buka Klasemen Akhir &amp; Podium</span>
                        <i class="fa-solid fa-trophy text-[11px] text-amber-200"></i>
                    </a>
                @endif
            @endif
        </div>
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
        <span class="text-[11px] text-slate-400 font-medium">Rotasi bermain pada {{ $unitTabLabel }} berikutnya</span>
    </div>
    @endif
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/@supabase/supabase-js@2"></script>
<script>
    // ── Config dari PHP ──────────────────────────────────────────────────────
    const SCORING_TYPE   = '{{ $scoringSystem['type'] }}';
    const IS_SETS        = {{ $scoringSystem['is_sets'] ? 'true' : 'false' }};
    const TARGET_SETS    = {{ $scoringSystem['target_sets'] ?? 2 }};
    const MAX_SETS       = {{ $scoringSystem['max_sets'] ?? 3 }};
    const TARGET_GAMES   = {{ $scoringSystem['target_games'] ?? 6 }};
    const GAME_ID        = {{ $game['id'] }};
    const ACTIVE_ROUND   = '{{ $activeRound }}';
    const ACTIVE_ROUND_NUM = '{{ preg_replace('/[^0-9]/', '', $activeRound) ?: '1' }}';
    const UNIT_TAB_LABEL = '{{ $unitTabLabel }}';
    const CSRF_TOKEN     = document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}';
    const UPDATE_URL     = '{{ route('scoring.update-score') }}';
    const IS_HOST        = {{ $isHost ? 'true' : 'false' }};
    const RECAP_URL      = '{{ route('scoring.recap', $game['id']) }}';
    const CLIENT_ID      = 'cli_' + Math.random().toString(36).substring(2, 9) + '_' + Date.now();
    const SUPABASE_URL   = '{{ config('services.supabase.url') }}';
    const SUPABASE_KEY   = '{{ config('services.supabase.key') }}';

    // ── Point Ladder ─────────────────────────────────────────────────────────
    const tennisPoints = ['0', '15', '30', '40'];

    // ── Multi-Court State ────────────────────────────────────────────────────
    const courtsState = {};
    @foreach($matchContext['matches'] ?? [] as $mIdx => $m)
        @php
            $courtCount = $matchContext['court_count'] ?? count($matchContext['matches'] ?? []);
            $mKey = ($courtCount > 1) ? "{$activeRound}_court_" . ($mIdx + 1) : $activeRound;
            $mScore = $savedScores[$mKey] ?? ($courtCount > 1 ? [] : ($savedScores[$activeRound] ?? []));
        @endphp
        courtsState[{{ $mIdx }}] = {
            matchId: {{ (int) ($m['match_id'] ?? 0) }},
            idxA: {{ (int) ($mScore['idx_a'] ?? 0) }},
            idxB: {{ (int) ($mScore['idx_b'] ?? 0) }},
            isDeuce: {{ ($mScore['is_deuce'] ?? false) ? 'true' : 'false' }},
            advantage: {!! json_encode($mScore['advantage'] ?? null) !!},
            gamesA: {{ (int) ($mScore['games_a'] ?? ($mScore['score_a'] ?? 0)) }},
            gamesB: {{ (int) ($mScore['games_b'] ?? ($mScore['score_b'] ?? 0)) }},
            setNumber: {{ (int) ($mScore['set_number'] ?? 1) }},
            setsA: {{ (int) ($mScore['sets_a'] ?? 0) }},
            setsB: {{ (int) ($mScore['sets_b'] ?? 0) }},
            setHistory: {!! json_encode($mScore['set_history'] ?? []) !!} || [],
            matchDone: {{ (($mScore['status'] ?? '') === 'completed') ? 'true' : 'false' }},
            completionSavePending: false,
            completionSaveSucceeded: {{ (($mScore['status'] ?? '') === 'completed') ? 'true' : 'false' }},
            winnerTeam: {!! json_encode($mScore['winner_team'] ?? null) !!},
            isFinishing: false,
            courtNum: {{ $mIdx + 1 }},
            courtName: {!! json_encode($m['court_name'] ?? ('Court ' . ($mIdx + 1))) !!},
            matchKey: '{{ $mKey }}',
            serverVersion: {{ (int) ($mScore['version'] ?? 0) }},
            localVersion: {{ (int) ($mScore['version'] ?? 0) }},
            clientSeq: 0,
            pendingSaves: 0,
            lastLocalActionTime: 0,
            saveQueue: [],
            saveWorker: null,
            activeSaveController: null,
            activeSaveIsCompletion: false
        };
    @endforeach

    // ── Point Display Resolution ─────────────────────────────────────────────
    function getPointDisplays(cIdx) {
        let st = courtsState[cIdx];
        if (st.isDeuce) {
            if (st.advantage === 'A') return { a: 'ADV', b: '40' };
            if (st.advantage === 'B') return { a: '40', b: 'ADV' };
            return { a: '40', b: '40' };
        }
        return {
            a: tennisPoints[st.idxA] || '0',
            b: tennisPoints[st.idxB] || '0',
        };
    }

    // ── Tambah Poin (Optimistic UI: 0ms render, async save ke server) ───────────
    function addPoint(team, cIdx) {
        let st = courtsState[cIdx];
        if (st.matchDone) {
            showToast('Skor Set ini sudah selesai dan terkunci.');
            return;
        }
        const tClick = performance.now();
        st.lastLocalActionTime = Date.now();
        st.localVersion = (st.localVersion || 0) + 1;
        st.pendingSaves = (st.pendingSaves || 0) + 1;
        st.clientSeq = (st.clientSeq || 0) + 1; // Increment SATU KALI per aksi
        const clientSeq = st.clientSeq;
        const baseVersion = st.serverVersion || 0;
        const eventId = 'evt_' + CLIENT_ID + '_' + Date.now() + '_' + Math.random().toString(36).substring(2, 8);

        // 1. Mutasi state lokal
        if (team === 'A') {
            handlePointWonByA(cIdx, clientSeq, tClick, baseVersion, eventId);
        } else {
            handlePointWonByB(cIdx, clientSeq, tClick, baseVersion, eventId);
        }

        // 2. Optimistic UI update seketika (0ms render time)
        updateDisplay(cIdx);
        const tUiDone = performance.now();
        console.log(`[Optimistic UI] Court ${st.courtNum} +1 ${team} rendered in ${(tUiDone - tClick).toFixed(2)}ms (clientSeq=${clientSeq}, baseVersion=${baseVersion}, eventId=${eventId})`);

        // 3. Simpan asinkron ke server jika belum selesai (jika selesai, checkSetWinner sudah memicu queueScoreSave)
        if (!st.matchDone) {
            queueScoreSave(cIdx, null, clientSeq, tClick, eventId, 'add_point', team, baseVersion);
        }
    }

    function handlePointWonByA(cIdx, clientSeq, tClick, baseVersion, eventId) {
        let st = courtsState[cIdx];
        if (st.isDeuce) {
            if (st.advantage === 'A') {
                gameWonBy('A', cIdx, clientSeq, tClick, baseVersion, eventId);
            } else if (st.advantage === 'B') {
                st.advantage = null;
                showToast('Kembali ke Deuce (40 - 40)!');
            } else {
                st.advantage = 'A';
                showToast('Advantage Team A!');
            }
        } else {
            if (st.idxA < 3) {
                st.idxA++;
                if (st.idxA === 3 && st.idxB === 3) {
                    st.isDeuce = true;
                    st.advantage = null;
                    showToast('Deuce (40 - 40)!');
                }
            } else if (st.idxA === 3 && st.idxB < 3) {
                gameWonBy('A', cIdx, clientSeq, tClick, baseVersion, eventId);
            }
        }
    }

    function handlePointWonByB(cIdx, clientSeq, tClick, baseVersion, eventId) {
        let st = courtsState[cIdx];
        if (st.isDeuce) {
            if (st.advantage === 'B') {
                gameWonBy('B', cIdx, clientSeq, tClick, baseVersion, eventId);
            } else if (st.advantage === 'A') {
                st.advantage = null;
                showToast('Kembali ke Deuce (40 - 40)!');
            } else {
                st.advantage = 'B';
                showToast('Advantage Team B!');
            }
        } else {
            if (st.idxB < 3) {
                st.idxB++;
                if (st.idxB === 3 && st.idxA === 3) {
                    st.isDeuce = true;
                    st.advantage = null;
                    showToast('Deuce (40 - 40)!');
                }
            } else if (st.idxB === 3 && st.idxA < 3) {
                gameWonBy('B', cIdx, clientSeq, tClick, baseVersion, eventId);
            }
        }
    }

    // ── Game Dimenangkan ─────────────────────────────────────────────────────
    function gameWonBy(team, cIdx, clientSeq, tClick, baseVersion, eventId) {
        resetPoints(cIdx);
        let st = courtsState[cIdx];
        if (team === 'A') {
            st.gamesA++;
            showToast('🎉 Game Won by Team A!');
        } else {
            st.gamesB++;
            showToast('🎉 Game Won by Team B!');
        }
        checkSetWinner(cIdx, clientSeq, tClick, baseVersion, team, eventId);
    }

    function checkSetWinner(cIdx, clientSeq = null, tClick = null, baseVersion = 0, teamWon = null, eventId = null) {
        let st = courtsState[cIdx];
        let setWon = null;
        
        if (!IS_SETS) {
            if (TARGET_GAMES > 0 && st.gamesA >= TARGET_GAMES) {
                setWon = 'Team A';
            } else if (TARGET_GAMES > 0 && st.gamesB >= TARGET_GAMES) {
                setWon = 'Team B';
            }
        } else if ((st.gamesA >= 6 && st.gamesA - st.gamesB >= 2) || (st.gamesA === 7 && st.gamesB === 6)) {
            setWon = 'Team A';
        } else if ((st.gamesB >= 6 && st.gamesB - st.gamesA >= 2) || (st.gamesB === 7 && st.gamesA === 6)) {
            setWon = 'Team B';
        }

        if (setWon) {
            st.matchDone = true;
            st.completionSavePending = true;
            st.completionSaveSucceeded = false;
            st.winnerTeam = setWon;
            st.setsA = (setWon === 'Team A') ? 1 : 0;
            st.setsB = (setWon === 'Team B') ? 1 : 0;
            updateDisplay(cIdx);
            syncRoundCompletionStatus();

            const cSeq = clientSeq || st.clientSeq || 1;
            const bVer = baseVersion || st.serverVersion || 0;
            const compEventId = eventId || ('evt_comp_' + CLIENT_ID + '_' + Date.now() + '_' + Math.random().toString(36).substring(2, 8));
            queueScoreSave(cIdx, 'completed', cSeq, tClick, compEventId, 'completion', (setWon === 'Team A' ? 'A' : 'B'), bVer);
        }
    }

    function manualCompleteSet(cIdx) {
        let st = courtsState[cIdx];
        if (st.matchDone) return;
        if (!IS_SETS && Math.max(st.gamesA, st.gamesB) < TARGET_GAMES) {
            showToast(`First to ${TARGET_GAMES} belum mencapai target.`);
            return;
        }
        const courtLabel = st.courtName || ('Court ' + st.courtNum);
        const conf = confirm(`Apakah Anda yakin ingin menyelesaikan dan mengunci skor pada ${courtLabel}?`);
        if (!conf) return;

        const tClick = performance.now();
        st.lastLocalActionTime = Date.now();
        st.localVersion = (st.localVersion || 0) + 1;
        st.pendingSaves = (st.pendingSaves || 0) + 1;
        st.clientSeq = (st.clientSeq || 0) + 1; // Increment SATU KALI per aksi manual
        const clientSeq = st.clientSeq;
        const baseVersion = st.serverVersion || 0;
        const winnerTeam = (st.gamesA >= st.gamesB) ? 'Team A' : 'Team B';

        st.matchDone = true;
        st.completionSavePending = true;
        st.completionSaveSucceeded = false;
        st.winnerTeam = winnerTeam;
        st.setsA = (winnerTeam === 'Team A') ? 1 : 0;
        st.setsB = (winnerTeam === 'Team B') ? 1 : 0;
        updateDisplay(cIdx);
        syncRoundCompletionStatus();

        const compEventId = 'evt_manual_' + CLIENT_ID + '_' + Date.now() + '_' + Math.random().toString(36).substring(2, 8);
        queueScoreSave(cIdx, 'completed', clientSeq, tClick, compEventId, 'completion', (winnerTeam === 'Team A' ? 'A' : 'B'), baseVersion);
        showToast(`Skor ${courtLabel} berhasil dikunci!`);
    }

    function resetPoints(cIdx) {
        let st = courtsState[cIdx];
        st.idxA = 0;
        st.idxB = 0;
        st.isDeuce = false;
        st.advantage = null;
    }

    // ── Update Display UI ────────────────────────────────────────────────────
    function updateDisplay(cIdx) {
        let st = courtsState[cIdx];
        const dispA  = document.getElementById('scoreDisplayA_' + cIdx);
        const dispB  = document.getElementById('scoreDisplayB_' + cIdx);
        const subA   = document.getElementById('subScoreLabelA_' + cIdx);
        const subB   = document.getElementById('subScoreLabelB_' + cIdx);
        const gameDispA = document.getElementById('displayGameScoreA_' + cIdx);
        const gameDispB = document.getElementById('displayGameScoreB_' + cIdx);
        const curBadge = document.getElementById('currentSetGamesBadge_' + cIdx);
        const notice = document.getElementById('matchNotice_' + cIdx);
        const setLbl = document.getElementById('currentSetLabel_' + cIdx);
        const lockedBadge = document.getElementById('lockedBadge_' + cIdx);
        const lockedBadgeText = document.getElementById('lockedBadgeText_' + cIdx);
        const btnManual = document.getElementById('btnManualComplete_' + cIdx);
        const displays = getPointDisplays(cIdx);
        const courtLabel = st.courtName || ('Court ' + st.courtNum);

        if (dispA) dispA.innerText = displays.a;
        if (dispB) dispB.innerText = displays.b;
        if (gameDispA) gameDispA.innerText = st.gamesA;
        if (gameDispB) gameDispB.innerText = st.gamesB;
        if (curBadge) curBadge.innerText = `Game Score: ${st.gamesA} — ${st.gamesB}`;
        if (subA) subA.innerText = `Games Won: ${st.gamesA} Game`;
        if (subB) subB.innerText = `Games Won: ${st.gamesB} Game`;

        if (setLbl) {
            setLbl.innerHTML = `Status: <strong>${st.matchDone ? (UNIT_TAB_LABEL + ' Selesai & Terkunci') : 'Sedang Berlangsung'}</strong>`;
        }

        if (notice) {
            if (st.matchDone) {
                notice.innerHTML = `🔒 <strong>${UNIT_TAB_LABEL} ${ACTIVE_ROUND_NUM} Selesai & Terkunci</strong> &bull; Skor: <strong>${st.gamesA} — ${st.gamesB}</strong> (${st.winnerTeam || 'Selesai'})`;
            } else if (st.isDeuce) {
                if (st.advantage === 'A') {
                    notice.innerHTML = '<strong class="text-[#063B00]">ADVANTAGE TEAM A</strong> &bull; Butuh 1 poin lagi untuk memenangkan game';
                } else if (st.advantage === 'B') {
                    notice.innerHTML = '<strong class="text-slate-900">ADVANTAGE TEAM B</strong> &bull; Butuh 1 poin lagi untuk memenangkan game';
                } else {
                    notice.innerHTML = '<strong class="text-amber-700">DEUCE (40 - 40)</strong> &bull; Perebutan advantage point';
                }
            } else {
                notice.innerHTML = `${UNIT_TAB_LABEL} score: <strong>${st.gamesA}</strong> — <strong>${st.gamesB}</strong> &bull; Point: <strong>${displays.a} : ${displays.b}</strong>`;
            }
        }

        const bA = document.getElementById('btnAddA_' + cIdx);
        const bB = document.getElementById('btnAddB_' + cIdx);

        if (st.matchDone) {
            if (lockedBadge) {
                lockedBadge.classList.remove('hidden');
                if (lockedBadgeText) {
                    lockedBadgeText.textContent = `Skor ${UNIT_TAB_LABEL} ${ACTIVE_ROUND_NUM} pada ${courtLabel} sudah selesai dan terkunci.`;
                }
            }
            if (btnManual) {
                btnManual.classList.add('hidden');
            }
            showCompletedBanner(st.winnerTeam || 'Pertandingan', cIdx);
            if (bA) {
                bA.disabled = true;
                bA.className = 'w-full py-3.5 rounded-xl bg-slate-100 text-slate-400 font-bold text-sm border border-slate-200 cursor-not-allowed flex items-center justify-center gap-2';
                bA.innerHTML = `<i class="fa-solid fa-lock text-xs"></i> Skor Terkunci (${UNIT_TAB_LABEL} Selesai)`;
            }
            if (bB) {
                bB.disabled = true;
                bB.className = 'w-full py-3.5 rounded-xl bg-slate-100 text-slate-400 font-bold text-sm border border-slate-200 cursor-not-allowed flex items-center justify-center gap-2';
                bB.innerHTML = `<i class="fa-solid fa-lock text-xs"></i> Skor Terkunci (${UNIT_TAB_LABEL} Selesai)`;
            }
        } else {
            if (lockedBadge) {
                lockedBadge.classList.add('hidden');
            }
            if (btnManual) {
                btnManual.classList.remove('hidden');
            }
            const banner = document.getElementById('matchCompletedBanner_' + cIdx);
            if (banner) {
                banner.classList.add('hidden');
            }
            if (bA) {
                bA.disabled = false;
                bA.className = 'w-full py-3.5 rounded-xl bg-[#063B00] hover:bg-[#042a00] text-white font-bold text-sm shadow-sm transition-all hover:scale-[1.01] active:scale-95 flex items-center justify-center gap-2 cursor-pointer';
                bA.innerHTML = '<i class="fa-solid fa-plus text-xs text-[#A8E63A]"></i> Tambah Poin Team A';
            }
            if (bB) {
                bB.disabled = false;
                bB.className = 'w-full py-3.5 rounded-xl bg-[#063B00] hover:bg-[#042a00] text-white font-bold text-sm shadow-sm transition-all hover:scale-[1.01] active:scale-95 flex items-center justify-center gap-2 cursor-pointer';
                bB.innerHTML = '<i class="fa-solid fa-plus text-xs text-[#A8E63A]"></i> Tambah Poin Team B';
            }
        }

        syncRoundCompletionStatus();
    }

    function showCompletedBanner(winner, cIdx) {
        const banner = document.getElementById('matchCompletedBanner_' + cIdx);
        const msg    = document.getElementById('completedMsg_' + cIdx);
        const subMsg = document.getElementById('completedSubMsg_' + cIdx);
        let st = courtsState[cIdx];
        const courtLabel = st.courtName || ('Court ' + st.courtNum);

        if (msg) msg.textContent = `🏆 ${courtLabel} telah selesai pada ${UNIT_TAB_LABEL} ${ACTIVE_ROUND_NUM}!`;
        if (subMsg) subMsg.innerHTML = `Skor Akhir: <strong>${st.gamesA} &mdash; ${st.gamesB} Games</strong> (${winner}) &bull; Poin telah dicatat ke klasemen.`;
        if (banner) banner.classList.remove('hidden');

        const bA = document.getElementById('btnAddA_' + cIdx);
        const bB = document.getElementById('btnAddB_' + cIdx);
        if (bA) {
            bA.disabled = true;
            bA.className = 'w-full py-3.5 rounded-xl bg-slate-100 text-slate-400 font-bold text-sm border border-slate-200 cursor-not-allowed flex items-center justify-center gap-2';
            bA.innerHTML = `<i class="fa-solid fa-lock text-xs"></i> Skor Terkunci (${UNIT_TAB_LABEL} Selesai)`;
        }
        if (bB) {
            bB.disabled = true;
            bB.className = 'w-full py-3.5 rounded-xl bg-slate-100 text-slate-400 font-bold text-sm border border-slate-200 cursor-not-allowed flex items-center justify-center gap-2';
            bB.innerHTML = `<i class="fa-solid fa-lock text-xs"></i> Skor Terkunci (${UNIT_TAB_LABEL} Selesai)`;
        }

        const lockedBadge = document.getElementById('lockedBadge_' + cIdx);
        const lockedBadgeText = document.getElementById('lockedBadgeText_' + cIdx);
        if (lockedBadge) {
            lockedBadge.classList.remove('hidden');
            if (lockedBadgeText) {
                lockedBadgeText.textContent = `Skor ${UNIT_TAB_LABEL} ${ACTIVE_ROUND_NUM} pada ${courtLabel} sudah selesai dan terkunci.`;
            }
        }

        const btnManual = document.getElementById('btnManualComplete_' + cIdx);
        if (btnManual) {
            btnManual.classList.add('hidden');
        }

        syncRoundCompletionStatus();
    }

    function syncRoundCompletionStatus() {
        const courtKeys = Object.keys(courtsState);
        const allCourtsDone = courtKeys.length > 0 && courtKeys.every(k => (
            courtsState[k].matchDone &&
            !courtsState[k].completionSavePending &&
            courtsState[k].completionSaveSucceeded
        ));

        const unfinishedCourts = courtKeys
            .filter(k => !courtsState[k].matchDone)
            .map(k => courtsState[k].courtName || ('Court ' + courtsState[k].courtNum));

        // Update Global Session Control Bar
        const globalTitle = document.getElementById('globalRoundStatusTitle');
        const globalDesc  = document.getElementById('globalRoundStatusDesc');
        const globalDot   = document.getElementById('globalRoundStatusDot');
        const formNext    = document.getElementById('globalNextRoundForm');
        const btnNext     = document.getElementById('btnGlobalNextRound');
        const formFinish  = document.getElementById('globalFinishForm');
        const btnRecap    = document.getElementById('btnGlobalRecap');

        if (allCourtsDone) {
            if (globalDot) {
                globalDot.className = 'w-2.5 h-2.5 rounded-full bg-emerald-500';
            }
            if (globalTitle) {
                globalTitle.textContent = `Seluruh Pertandingan ${UNIT_TAB_LABEL} ${ACTIVE_ROUND_NUM} Selesai!`;
                globalTitle.className = 'text-sm font-extrabold text-[#063B00]';
            }
            if (globalDesc) {
                if (IS_HOST) {
                    globalDesc.textContent = `Semua court telah mencatat skor akhir. Silakan lanjut ke ${formNext ? UNIT_TAB_LABEL.toLowerCase() + ' berikutnya' : 'hasil akhir & podium'}.`;
                } else {
                    globalDesc.textContent = `Semua court telah selesai. Menunggu Host ${formNext ? 'memulai ' + UNIT_TAB_LABEL.toLowerCase() + ' berikutnya' : 'menyelesaikan sesi'}...`;
                }
            }
            if (IS_HOST) {
                if (formNext) formNext.classList.remove('hidden');
                if (formFinish) formFinish.classList.remove('hidden');
                if (btnRecap) btnRecap.classList.add('hidden');
            } else {
                if (formNext) formNext.classList.add('hidden');
                if (formFinish) formFinish.classList.add('hidden');
                if (btnRecap) btnRecap.classList.remove('hidden');
            }
        } else {
            if (globalDot) {
                globalDot.className = 'w-2.5 h-2.5 rounded-full bg-amber-500 animate-pulse';
            }
            if (globalTitle) {
                globalTitle.textContent = `Status Pertandingan ${UNIT_TAB_LABEL} ${ACTIVE_ROUND_NUM}`;
                globalTitle.className = 'text-sm font-extrabold text-slate-800';
            }
            if (globalDesc) {
                const namesStr = unfinishedCourts.join(', ');
                globalDesc.textContent = namesStr 
                    ? `Menunggu ${namesStr} menyelesaikan pertandingan...` 
                    : `Pertandingan sedang berlangsung pada seluruh court...`;
            }
            if (formNext) formNext.classList.add('hidden');
            if (formFinish) formFinish.classList.add('hidden');
            if (btnRecap) btnRecap.classList.add('hidden');
        }
    }

    function applyServerScore(cIdx, data, incomingVer) {
        let st = courtsState[cIdx];
        if (!st || !data) return;

        const newIdxA = data.idx_a ?? 0;
        const newIdxB = data.idx_b ?? 0;
        const newIsDeuce = !!data.is_deuce;
        const newAdv = data.advantage ?? null;
        const newGamesA = data.games_a ?? (data.score_a ?? 0);
        const newGamesB = data.games_b ?? (data.score_b ?? 0);
        const newSetsA = data.sets_a ?? 0;
        const newSetsB = data.sets_b ?? 0;
        const newMatchDone = (data.status === 'completed');
        const newWinner = data.winner_team ?? null;

        const hasChanged = (
            st.idxA !== newIdxA ||
            st.idxB !== newIdxB ||
            st.isDeuce !== newIsDeuce ||
            st.advantage !== newAdv ||
            st.gamesA !== newGamesA ||
            st.gamesB !== newGamesB ||
            st.setsA !== newSetsA ||
            st.setsB !== newSetsB ||
            st.matchDone !== newMatchDone ||
            st.winnerTeam !== newWinner ||
            incomingVer > (st.serverVersion || 0)
        );

        if (hasChanged) {
            st.idxA        = newIdxA;
            st.idxB        = newIdxB;
            st.isDeuce     = newIsDeuce;
            st.advantage   = newAdv;
            st.gamesA      = newGamesA;
            st.gamesB      = newGamesB;
            st.setsA       = newSetsA;
            st.setsB       = newSetsB;
            st.setNumber   = (data.set_number ?? 1);
            st.setHistory  = data.set_history ?? [];
            st.matchDone   = newMatchDone;
            st.winnerTeam  = newWinner;
            if (incomingVer > (st.serverVersion || 0)) {
                st.serverVersion = incomingVer;
            }
            if ((st.pendingSaves || 0) === 0 && incomingVer > (st.localVersion || 0)) {
                st.localVersion = incomingVer;
            }
            if (newMatchDone) {
                st.completionSavePending = false;
                st.completionSaveSucceeded = true;
            }

            const dispA  = document.getElementById('scoreDisplayA_' + cIdx);
            const dispB  = document.getElementById('scoreDisplayB_' + cIdx);
            const defaultDispA = newIsDeuce ? (newAdv === 'A' ? 'ADV' : '40') : (tennisPoints[newIdxA] || '0');
            const defaultDispB = newIsDeuce ? (newAdv === 'B' ? 'ADV' : '40') : (tennisPoints[newIdxB] || '0');
            const targetA = String(data.point_display_a ?? defaultDispA);
            const targetB = String(data.point_display_b ?? defaultDispB);

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

            updateDisplay(cIdx);
        }
    }

    // Helper untuk mencocokkan incoming event (Realtime/Polling/Postgres) ke court index
    function findCourtIndex(payload) {
        if (!payload) return null;

        // 1. Direct match dengan index state lokal
        if (payload.court !== undefined && courtsState[payload.court] !== undefined) {
            return payload.court;
        }

        // 2. Match berdasarkan match_key (contoh: round_1_court_1 atau round_1)
        if (payload.match_key) {
            for (const [idx, st] of Object.entries(courtsState)) {
                if (st.matchKey === payload.match_key) return idx;
            }
        }

        // 3. Match berdasarkan match_id (ID di database tb_match)
        if (payload.match_id) {
            for (const [idx, st] of Object.entries(courtsState)) {
                if (st.matchId && Number(st.matchId) === Number(payload.match_id)) return idx;
            }
        }

        // 4. Match berdasarkan courtNum (1-indexed)
        if (payload.court_num !== undefined) {
            for (const [idx, st] of Object.entries(courtsState)) {
                if (Number(st.courtNum) === Number(payload.court_num)) return idx;
            }
        }
        if (payload.court !== undefined) {
            for (const [idx, st] of Object.entries(courtsState)) {
                if (Number(st.courtNum) === Number(payload.court)) return idx;
            }
        }

        // Fallback jika hanya terdapat 1 court pada match
        const keys = Object.keys(courtsState);
        if (keys.length === 1) return keys[0];

        return null;
    }

    // Handler Utama Seluruh Scoring Event (Supabase Realtime + Polling Fallback)
    // Menjalankan Aturan Monotonic & Stale Protection (Requirement 6)
    function handleIncomingScoreEvent(payload, sourceName = 'Realtime') {
        if (!payload) return;
        const cIdx = findCourtIndex(payload);
        if (cIdx === null || courtsState[cIdx] === undefined) return;

        let st = courtsState[cIdx];
        const incomingVer = Number(payload.server_version ?? payload.version ?? 0);
        const localVer = Number(st.serverVersion || 0);

        // Aturan Reconcile & Stale Protection:
        // 1. incoming server_version < local server_version -> IGNORE (mencegah rollback skor)
        if (incomingVer < localVer) {
            console.log(`[${sourceName}] Stale IGNORE: incoming version (${incomingVer}) < local serverVersion (${localVer})`);
            return;
        }

        // 2. incoming server_version == local server_version -> IGNORE (idempotent, data sudah sinkron)
        if (incomingVer === localVer) {
            if (payload.status === 'completed' && !st.matchDone) {
                st.matchDone = true;
                st.completionSaveSucceeded = true;
                st.completionSavePending = false;
                syncRoundCompletionStatus();
            }
            return;
        }

        // 3. Jika ada local scoring event yang belum mendapat konfirmasi server (pendingSaves > 0)
        //    dan incomingVer <= localVer -> JANGAN overwrite optimistic state dengan snapshot lama
        if ((st.pendingSaves || 0) > 0 && incomingVer <= (st.serverVersion || 0)) {
            console.log(`[${sourceName}] Pending Save Protection: incomingVer (${incomingVer}) <= serverVersion (${st.serverVersion})`);
            return;
        }

        // 4. Jika match lokal sudah matchDone dan pendingSaves > 0, jangan biarkan server status != completed membatalkan
        if (st.matchDone && (st.pendingSaves || 0) > 0 && payload.status !== 'completed') {
            return;
        }

        // 5. incoming server_version > local server_version -> APPLY mutasi terbaru
        console.log(`[${sourceName}] APPLY: Court ${st.courtNum} version updated (${localVer} -> ${incomingVer})`);
        applyServerScore(cIdx, payload, incomingVer);
        syncRoundCompletionStatus();
    }


    function queueScoreSave(cIdx, status = null, clientSeq = null, tClick = null, eventId = null, action = 'add_point', team = null, baseVersion = 0) {
        let st = courtsState[cIdx];
        const isCompletionSave = status === 'completed';
        if (isCompletionSave && st.activeSaveController && !st.activeSaveIsCompletion) {
            st.activeSaveController.abort();
        }
        const snapshot = {
            idxA: st.idxA,
            idxB: st.idxB,
            isDeuce: st.isDeuce,
            advantage: st.advantage,
            gamesA: st.gamesA,
            gamesB: st.gamesB,
            winnerTeam: st.winnerTeam,
            matchDone: st.matchDone,
            pointDisplays: getPointDisplays(cIdx),
        };
        st.saveQueue = st.saveQueue || [];
        st.saveQueue.push({ status, clientSeq, tClick, eventId, action, team, baseVersion, snapshot });

        if (! st.saveWorker) {
            st.saveWorker = (async () => {
                while (st.saveQueue && st.saveQueue.length > 0) {
                    const queued = st.saveQueue.shift();
                    await saveScore(cIdx, queued.status, queued.clientSeq, queued.tClick, queued.snapshot, queued.eventId, queued.action, queued.team, queued.baseVersion);
                }
            })().finally(() => {
                st.saveWorker = null;
            });
        }

        return st.saveWorker;
    }

    async function saveScore(cIdx, status = null, clientSeq = null, tClick = null, snapshot = null, eventId = null, action = 'add_point', team = null, baseVersion = 0) {
        let st = courtsState[cIdx];
        if (st.isFinishing) return;

        const isCompletionSave = status === 'completed' || (!snapshot && st.matchDone);
        if (isCompletionSave) {
            st.completionSavePending = true;
            st.completionSaveSucceeded = false;
            syncRoundCompletionStatus();
        }

        const scoreState = snapshot || st;
        const displays = snapshot ? snapshot.pointDisplays : getPointDisplays(cIdx);
        const currentStatus = status ?? (scoreState.matchDone ? 'completed' : 'in_progress');
        const reqSeq = clientSeq || st.clientSeq || 1;
        const reqBaseVer = baseVersion || st.serverVersion || 0;
        const body = {
            game_id         : GAME_ID,
            round           : ACTIVE_ROUND,
            match_key       : st.matchKey,
            court           : st.courtNum,
            scoring_type    : SCORING_TYPE,
            event_id        : eventId,
            action          : action || 'add_point',
            point_won_by    : team,
            base_version    : reqBaseVer,
            score_a         : scoreState.gamesA,
            score_b         : scoreState.gamesB,
            point_display_a : displays.a,
            point_display_b : displays.b,
            set_number      : Number(ACTIVE_ROUND_NUM),
            sets_a          : (scoreState.gamesA >= scoreState.gamesB && currentStatus === 'completed') ? 1 : 0,
            sets_b          : (scoreState.gamesB > scoreState.gamesA && currentStatus === 'completed') ? 1 : 0,
            games_a         : scoreState.gamesA,
            games_b         : scoreState.gamesB,
            set_history     : [{ set: Number(ACTIVE_ROUND_NUM), score_a: scoreState.gamesA, score_b: scoreState.gamesB }],
            idx_a           : scoreState.idxA,
            idx_b           : scoreState.idxB,
            is_deuce        : scoreState.isDeuce,
            advantage       : scoreState.advantage,
            winner_team     : scoreState.winnerTeam || (scoreState.gamesA >= scoreState.gamesB ? 'Team A' : 'Team B'),
            status          : currentStatus,
            client_id       : CLIENT_ID,
            client_version  : reqSeq,
            client_seq      : reqSeq,
        };

        const requestController = new AbortController();
        st.activeSaveController = requestController;
        st.activeSaveIsCompletion = isCompletionSave;

        try {
            const res = await fetch(UPDATE_URL, {
                method : 'POST',
                keepalive: true,
                signal: requestController.signal,
                headers: {
                    'Content-Type' : 'application/json',
                    'X-CSRF-TOKEN' : CSRF_TOKEN,
                    'Accept'       : 'application/json',
                },
                body: JSON.stringify(body),
            });
            if (res.ok) {
                const data = await res.json();
                const incomingVer = Number(data.version || 0);

                // 1. Cek penolakan stale / duplicate (Requirement 1 & 7)
                if (data.stale_ignored || data.duplicate) {
                    if (isCompletionSave) {
                        st.completionSavePending = false;
                        st.completionSaveSucceeded = (data.saved?.status === 'completed');
                        if (data.saved) {
                            applyServerScore(cIdx, data.saved, incomingVer);
                        }
                        syncRoundCompletionStatus();
                        return;
                    }
                    if (data.saved) {
                        applyServerScore(cIdx, data.saved, incomingVer);
                    }
                    return;
                }

                // 2. Cek mutasi concurrent yang digabung di server (Requirement 3 & 5)
                if (data.merged && data.saved) {
                    applyServerScore(cIdx, data.saved, incomingVer);
                }

                // 3. Normal Completion Success (Requirement 7)
                if (isCompletionSave && data.success !== false) {
                    st.matchDone = true;
                    st.completionSaveSucceeded = true;
                    st.completionSavePending = false;
                    if (data.saved) {
                        applyServerScore(cIdx, data.saved, incomingVer);
                    } else {
                        updateDisplay(cIdx);
                    }
                    syncRoundCompletionStatus();
                }

                if (incomingVer > (st.serverVersion || 0)) {
                    st.serverVersion = incomingVer;
                }
                if (incomingVer > (st.localVersion || 0)) {
                    st.localVersion = incomingVer;
                }

                // Broadcast state terbaru via Supabase Realtime Channel client-side (near 0ms peer broadcast)
                if (realtimeChannel && data.saved) {
                    realtimeChannel.send({
                        type: 'broadcast',
                        event: 'score_update',
                        payload: {
                            ...data.saved,
                            court: cIdx,
                            match_key: st.matchKey,
                            server_version: incomingVer,
                            version: incomingVer,
                            last_event_id: data.last_event_id || eventId
                        }
                    }).catch(e => console.warn('[Supabase Realtime] broadcast send warning:', e));
                }

                if (tClick) {
                    const roundtripMs = performance.now() - tClick;
                    console.log(`[Network Roundtrip] Court ${st.courtNum} save roundtrip: ${roundtripMs.toFixed(2)}ms (serverVersion=${st.serverVersion})`);
                }
            } else {
                console.error(`Update score failed for court ${st.courtNum}:`, await res.text());
                if (isCompletionSave) {
                    st.completionSavePending = false;
                    st.completionSaveSucceeded = false;
                    syncRoundCompletionStatus();
                }
            }
        } catch (err) {
            if (err.name !== 'AbortError') {
                console.warn(`Gagal simpan skor court ${st.courtNum}:`, err);
            }
            if (isCompletionSave) {
                st.completionSavePending = false;
                st.completionSaveSucceeded = false;
                syncRoundCompletionStatus();
            }
        } finally {
            if (st.activeSaveController === requestController) {
                st.activeSaveController = null;
                st.activeSaveIsCompletion = false;
            }
            st.pendingSaves = Math.max(0, (st.pendingSaves || 1) - 1);
            if (isCompletionSave && !st.completionSaveSucceeded) {
                st.completionSavePending = false;
                syncRoundCompletionStatus();
            }
            if (st.pendingSaves === 0) {
                st.localVersion = Math.max(st.localVersion || 0, st.serverVersion || 0);
            }
        }
    }

    let isSubmittingNextRound = false;
    async function submitNextRound(event) {
        if (event) event.preventDefault();
        if (isSubmittingNextRound) return;

        // Requirement 8: Next Round HARUS menunggu seluruh pending save selesai dan completion terkonfirmasi server
        const courtKeys = Object.keys(courtsState);
        const hasPending = courtKeys.some(k => (
            (courtsState[k].pendingSaves || 0) > 0 ||
            courtsState[k].completionSavePending === true
        ));
        const allCompletedAndSucceeded = courtKeys.length > 0 && courtKeys.every(k => (
            courtsState[k].matchDone &&
            courtsState[k].completionSaveSucceeded === true
        ));

        if (hasPending || !allCompletedAndSucceeded) {
            showToast('Menunggu konfirmasi penyimpanan skor akhir ke server...');
            const btn = document.getElementById('btnGlobalNextRound');
            const text = document.getElementById('btnNextRoundText');
            const icon = document.getElementById('btnNextRoundIcon');
            if (icon) icon.className = 'fa-solid fa-spinner fa-spin text-xs text-[#A8E63A]';
            if (text) text.textContent = 'Menyimpan skor akhir...';

            const drainStart = Date.now();
            while (Date.now() - drainStart < 3000) {
                await new Promise(r => setTimeout(r, 100));
                const stillPending = courtKeys.some(k => (
                    (courtsState[k].pendingSaves || 0) > 0 ||
                    courtsState[k].completionSavePending === true
                ));
                const nowSucceeded = courtKeys.every(k => (
                    courtsState[k].matchDone &&
                    courtsState[k].completionSaveSucceeded === true
                ));
                if (!stillPending && nowSucceeded) {
                    break;
                }
            }
        }

        // Re-check final status
        const canSubmit = courtKeys.length > 0 && courtKeys.every(k => (
            (courtsState[k].pendingSaves || 0) === 0 &&
            !courtsState[k].completionSavePending &&
            courtsState[k].completionSaveSucceeded === true
        ));

        if (!canSubmit) {
            showToast('Skor belum terkonfirmasi tersimpan di server. Silakan coba lagi.');
            const text = document.getElementById('btnNextRoundText');
            const icon = document.getElementById('btnNextRoundIcon');
            if (icon) icon.className = 'fa-solid fa-arrow-right text-[11px] text-[#A8E63A]';
            if (text) text.textContent = 'Lanjut ke {{ $unitTabLabel }} {{ $nextRoundNum ?? "Berikutnya" }}';
            return;
        }

        isSubmittingNextRound = true;
        const btn = document.getElementById('btnGlobalNextRound');
        const text = document.getElementById('btnNextRoundText');
        const icon = document.getElementById('btnNextRoundIcon');
        if (btn) {
            btn.disabled = true;
            btn.classList.add('opacity-75', 'cursor-not-allowed', 'pointer-events-none');
            if (icon) {
                icon.className = 'fa-solid fa-spinner fa-spin text-xs text-[#A8E63A]';
            }
            if (text) {
                text.textContent = 'Membuka {{ $unitTabLabel }} {{ $nextRoundNum ?? "Berikutnya" }}...';
            }
        }
        showToast('Membuka {{ $unitTabLabel }} {{ $nextRoundNum ?? "Berikutnya" }}...');
        const form = document.getElementById('globalNextRoundForm');
        if (form) form.submit();
    }

    function submitGlobalFinish(event) {
        if (event) event.preventDefault();

        // Cari snapshot state representatif dari court yang ada
        const firstCIdx = Object.keys(courtsState)[0] || 0;
        const st = courtsState[firstCIdx] || {};

        const finA       = document.getElementById('globalFinishScoreA');
        const finB       = document.getElementById('globalFinishScoreB');
        const finSetsA   = document.getElementById('globalFinishSetsA');
        const finSetsB   = document.getElementById('globalFinishSetsB');
        const finGamesA  = document.getElementById('globalFinishGamesA');
        const finGamesB  = document.getElementById('globalFinishGamesB');
        const finWinner  = document.getElementById('globalFinishWinnerTeam');

        if (finA)       finA.value       = st.gamesA || 0;
        if (finB)       finB.value       = st.gamesB || 0;
        if (finSetsA)   finSetsA.value   = (st.gamesA >= st.gamesB) ? 1 : 0;
        if (finSetsB)   finSetsB.value   = (st.gamesB > st.gamesA) ? 1 : 0;
        if (finGamesA)  finGamesA.value  = st.gamesA || 0;
        if (finGamesB)  finGamesB.value  = st.gamesB || 0;
        if (finWinner)  finWinner.value  = st.winnerTeam || (st.gamesA >= st.gamesB ? 'Team A' : 'Team B');

        const btn = document.getElementById('btnGlobalFinishSession');
        if (btn) {
            btn.disabled = true;
            btn.classList.add('opacity-75', 'cursor-not-allowed');
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin text-xs text-[#A8E63A]"></i> Menyimpan Sesi...';
        }

        showToast('Menyimpan hasil akhir seluruh sesi pertandingan...');
        const form = document.getElementById('globalFinishForm');
        if (form) form.submit();
    }

    // Supabase Realtime Client & Channel
    let supabaseClient = null;
    let realtimeChannel = null;

    function initSupabaseRealtime() {
        if (!window.supabase || !SUPABASE_URL || !SUPABASE_KEY) {
            console.warn('[Supabase Realtime] Supabase credentials or SDK missing. Using Polling Fallback.');
            const badge = document.getElementById('realtimeSyncBadge');
            if (badge) {
                badge.innerHTML = '<span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span> Polling Fallback';
                badge.className = 'inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-amber-50 text-amber-700 border border-amber-200 text-[10px] font-bold';
            }
            return;
        }

        try {
            supabaseClient = window.supabase.createClient(SUPABASE_URL, SUPABASE_KEY, {
                realtime: {
                    params: {
                        eventsPerSecond: 10
                    }
                }
            });

            const channelName = 'session_' + GAME_ID;
            realtimeChannel = supabaseClient.channel(channelName);

            realtimeChannel
                .on('broadcast', { event: 'score_update' }, ({ payload }) => {
                    console.log('[Supabase Realtime] score_update broadcast received:', payload);
                    handleIncomingScoreEvent(payload, 'Realtime Broadcast');
                })
                .on('broadcast', { event: 'match_status_update' }, ({ payload }) => {
                    console.log('[Supabase Realtime] match_status_update received:', payload);
                    handleIncomingScoreEvent(payload, 'Realtime Status');
                })
                .on('postgres_changes', {
                    event: '*',
                    schema: 'public',
                    table: 'tb_score',
                    filter: `game_id=eq.${GAME_ID}`
                }, (payload) => {
                    console.log('[Supabase Realtime] postgres_changes tb_score:', payload);
                    if (payload.new) {
                        handleIncomingScoreEvent(payload.new, 'Realtime DB Changes');
                    }
                })
                .subscribe((status) => {
                    console.log('[Supabase Realtime] Status:', status);
                    const badge = document.getElementById('realtimeSyncBadge');
                    if (badge) {
                        if (status === 'SUBSCRIBED') {
                            badge.innerHTML = '<i class="fa-solid fa-bolt text-emerald-500 text-[9px]"></i> Realtime Active';
                            badge.className = 'inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 text-[10px] font-bold';
                        } else if (status === 'CHANNEL_ERROR' || status === 'TIMED_OUT') {
                            badge.innerHTML = '<span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span> Polling Fallback';
                            badge.className = 'inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-amber-50 text-amber-700 border border-amber-200 text-[10px] font-bold';
                        }
                    }
                });
        } catch (err) {
            console.warn('[Supabase Realtime] Init exception:', err);
        }
    }

    // Polling realtime untuk sinkronisasi skor (0.8 detik per siklus) — WAJIB TETAP ADA SEBAGAI FALLBACK/RECONCILIATION
    const POLL_INTERVAL_MS = 800;
    Object.keys(courtsState).forEach(cIdx => {
        let st = courtsState[cIdx];
        const POLL_URL = `{{ url('scoring/get-score') }}/${GAME_ID}/${ACTIVE_ROUND}?court=${cIdx}&match_key=${st.matchKey}`;
        
        const timerEl = document.getElementById('countdownTimer_' + cIdx);
        const hostTimerEl = document.getElementById('hostSyncTimer_' + cIdx);
        const topTimerEl = document.getElementById('topSyncTimer_' + cIdx);

        let isPolling = false;
        let nextPollTime = Date.now() + POLL_INTERVAL_MS;

        // Visual countdown ticker yang berjalan halus & valid menghitung mundur dari 0.8s ke 0.0s
        const updateTimerDisplay = () => {
            const now = Date.now();
            const remainingMs = Math.max(0, nextPollTime - now);
            const secStr = (remainingMs / 1000).toFixed(1) + 's';
            if (timerEl) timerEl.textContent = secStr;
            if (hostTimerEl) hostTimerEl.textContent = secStr;
            if (topTimerEl) topTimerEl.textContent = secStr;
        };

        setInterval(updateTimerDisplay, 100);

        const executePoll = async () => {
            if (isPolling) return;
            isPolling = true;
            try {
                const pollUrlWithTs = `${POLL_URL}&_t=${Date.now()}`;
                const res = await fetch(pollUrlWithTs, {
                    cache: 'no-store',
                    headers: { 'Accept': 'application/json' }
                });
                if (!res.ok) return;
                const data = await res.json();
                data.court = cIdx;
                data.match_key = st.matchKey;

                // Salurkan seluruh response polling melalui handler monotonic yang sama (Requirement 6)
                handleIncomingScoreEvent(data, 'Polling 0.8s');
            } catch (err) {
                // Ignore network polling error
            } finally {
                isPolling = false;
                nextPollTime = Date.now() + POLL_INTERVAL_MS;
                updateTimerDisplay();
            }
        };

        // Jalankan polling loop tiap 0.8 detik
        setInterval(executePoll, POLL_INTERVAL_MS);

        // Jalankan polling awal secara bertahap (staggered) agar tidak bertabrakan
        setTimeout(executePoll, 100 + (Number(cIdx) * 150));
    });

    // Helper Toast
    if (typeof showToast !== 'function') {
        window.showToast = function(msg) {
            console.log("TOAST:", msg);
        }
    }

    // Inisialisasi awal untuk semua court & Realtime
    Object.keys(courtsState).forEach(cIdx => {
        updateDisplay(cIdx);
    });
    syncRoundCompletionStatus();
    initSupabaseRealtime();
</script>
@endpush