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
        $allRoundsList = $scoringSystem['is_sets']
            ? array_map(fn ($roundNumber) => "round_{$roundNumber}", range(1, $scoringSystem['max_sets']))
            : ['round_1'];
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
                        $isRoundAccessible = $roundAccess[$rKey] ?? false;
                        // Cek apakah ronde/set ini sudah completed di savedScores
                        $rScore = $savedScores[$rKey] ?? ($savedScores["{$rKey}_court_1"] ?? []);
                        $isRCompleted = (($rScore['status'] ?? '') === 'completed');
                    @endphp
                    @if($isRoundAccessible)
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
                    @else
                        <span class="px-3.5 py-1.5 rounded-xl text-xs font-bold border flex items-center gap-1.5 bg-slate-100 text-slate-400 border-slate-200 cursor-not-allowed">
                            <span>{{ $tabDisplayTitle }}</span>
                            <span class="text-[9px] px-1.5 py-0.2 rounded-md bg-slate-200 text-slate-500">🔒 Terkunci</span>
                        </span>
                    @endif
                @endforeach
            </div>
        </div>
        <span class="text-[11px] text-slate-500 font-semibold">
            Format: <strong class="{{ $isTeamFormat ? 'text-indigo-700' : ($isLiveSingleMode ? 'text-amber-700' : 'text-[#063B00]') }}">{{ $matchFormatLabel }}</strong>
        </span>
    </div>
    @endif

    
    @php
        $courtCount = $matchContext['court_count'] ?? count($matchContext['matches'] ?? []);
        $activeRoundNum = preg_replace('/[^0-9]/', '', $activeRound) ?: '1';
        $uncompletedCourtNames = [];
        foreach ($matchContext['matches'] ?? [] as $checkIdx => $checkMatch) {
            $checkKey = ($courtCount > 1) ? "{$activeRound}_court_" . ($checkIdx + 1) : $activeRound;
            $checkScore = $savedScores[$checkKey] ?? ($courtCount > 1 ? [] : ($savedScores[$activeRound] ?? []));
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
                    <span>Sync: <strong id="topSyncTimer_{{ $mIdx }}" class="font-black">1.5s</strong></span>
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
                    Status: <strong>{{ $isMCompleted ? ($unitTabLabel . ' Selesai') : 'Sedang Berlangsung' }}</strong>
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
        <div id="matchCompletedBanner_{{ $mIdx }}" class="{{ $isMCompleted ? '' : 'hidden' }} p-5 rounded-2xl bg-[#EBF8D8] border border-[#063B00]/30 text-center space-y-3 shadow-sm">
            <div class="text-3xl">🏆</div>
            <p class="text-base font-black text-[#063B00]" id="completedMsg_{{ $mIdx }}">
                {{ $matchData['court_name'] ?? ('Court ' . ($mIdx + 1)) }} telah selesai pada {{ $unitTabLabel }} {{ $activeRoundNum }}!
            </p>
            <p class="text-xs text-slate-600 font-medium" id="completedSubMsg_{{ $mIdx }}">
                Skor Akhir: <strong>{{ $currentScore['games_a'] ?? 0 }} &mdash; {{ $currentScore['games_b'] ?? 0 }} Games</strong> &bull; Poin telah dicatat ke klasemen.
            </p>

            @php
                $otherUnfinished = array_diff_key($uncompletedCourtNames ?? [], [$mIdx => true]);
                $otherNamesStr = implode(', ', $otherUnfinished);
            @endphp

            <!-- Status Tunggu Court Lain (Jika ada court lain yang belum selesai) -->
            <div id="waitingOtherCourts_{{ $mIdx }}" class="{{ ($isMCompleted && !($allCourtsCompleted ?? false)) ? '' : 'hidden' }} p-3 rounded-xl bg-amber-50 border border-amber-200 text-center">
                <div class="flex items-center justify-center gap-2 text-xs font-bold text-amber-900">
                    <span class="w-2 h-2 rounded-full bg-amber-500 animate-pulse"></span>
                    <span id="waitingOtherCourtsText_{{ $mIdx }}">
                        Menunggu {{ $otherNamesStr ?: 'court lain' }} menyelesaikan {{ $unitTabLabel }} {{ $activeRoundNum }} sebelum melanjutkan ke ronde berikutnya.
                    </span>
                </div>
            </div>

            <!-- Tombol Navigasi Ronde Berikutnya (Hanya tampil jika SELURUH court telah selesai) -->
            <div id="nextRoundNav_{{ $mIdx }}" class="{{ ($isMCompleted && ($allCourtsCompleted ?? false)) ? '' : 'hidden' }} pt-2">
                @if($nextRoundKey)
                    @php
                        $nextRoundNum = preg_replace('/[^0-9]/', '', $nextRoundKey) ?: '2';
                    @endphp
                    <a href="{{ route('scoring.live', ['id' => $game['id'], 'format' => request('format', $game['match_format'] ?? 'Americano'), 'round' => $nextRoundKey, 'court' => $courtIndex]) }}"
                       class="inline-flex items-center gap-2 px-6 py-3 rounded-2xl bg-[#063B00] hover:bg-[#042a00] text-white font-extrabold text-xs shadow-md transition-all hover:scale-[1.02] active:scale-95">
                        <span>Lanjut ke {{ $unitTabLabel }} {{ $nextRoundNum }} (Susunan Pasangan Baru)</span>
                        <i class="fa-solid fa-arrow-right text-[10px] text-[#A8E63A]"></i>
                    </a>
                @else
                    <a href="{{ route('scoring.recap', $game['id']) }}"
                       class="inline-flex items-center gap-2 px-6 py-3 rounded-2xl bg-amber-600 hover:bg-amber-700 text-white font-extrabold text-xs shadow-md transition-all hover:scale-[1.02] active:scale-95">
                        <span>🏁 Semua Set Selesai — Buka Klasemen Akhir &amp; Podium</span>
                        <i class="fa-solid fa-trophy text-[10px] text-amber-200"></i>
                    </a>
                @endif
            </div>
        </div>

        <!-- Controls: Selesaikan Sesi & Lihat Juara — hanya untuk Host -->
        @if($isHost)
        <div class="flex flex-col sm:flex-row items-center justify-between gap-3 pt-3 border-t border-slate-200/50">
            <div class="flex items-center gap-2 flex-wrap w-full sm:w-auto">
                <a href="{{ route('scoring.recap', $game['id']) }}"
                   class="px-4 py-2.5 rounded-xl bg-white border border-slate-200 text-slate-700 text-xs font-semibold hover:bg-slate-50 shadow-2xs transition-colors flex items-center gap-1.5 justify-center">
                    <i class="fa-solid fa-ranking-star text-[10px] text-amber-500"></i> Lihat Klasemen Sementara
                </a>

                <button type="button" id="btnManualComplete_{{ $mIdx }}" onclick="manualCompleteSet({{ $mIdx }})"
                   class="{{ $isMCompleted ? 'hidden' : '' }} px-4 py-2.5 rounded-xl bg-amber-50 border border-amber-300 text-amber-900 text-xs font-bold hover:bg-amber-100 shadow-2xs transition-colors flex items-center gap-1.5 justify-center cursor-pointer">
                    <i class="fa-solid fa-lock text-amber-600"></i> Kunci &amp; Selesaikan {{ $unitTabLabel }} Ini
                </button>

                <div class="flex items-center gap-1.5 text-[11px] text-slate-400 pl-1">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                    <span>Sinkron realtime: <strong id="hostSyncTimer_{{ $mIdx }}" class="text-emerald-700 font-bold">1.5s</strong></span>
                </div>
            </div>

            <form id="finishForm_{{ $mIdx }}" action="{{ route('scoring.finish') }}" method="POST" class="w-full sm:w-auto">
                @csrf
                <input type="hidden" name="game_id"         value="{{ $game['id'] }}">
                <input type="hidden" name="round"           value="{{ $activeRound }}">
                <input type="hidden" name="match_key"       value="{{ $mKey }}">
                <input type="hidden" name="court"           value="{{ $mIdx + 1 }}">
                <input type="hidden" name="scoring_system"  value="{{ $scoringSystem['label'] }}">
                <input type="hidden" name="score_a"         id="finishScoreA_{{ $mIdx }}" value="0">
                <input type="hidden" name="score_b"         id="finishScoreB_{{ $mIdx }}" value="0">
                <input type="hidden" name="sets_a"          id="finishSetsA_{{ $mIdx }}" value="{{ $currentScore['sets_a'] ?? 0 }}">
                <input type="hidden" name="sets_b"          id="finishSetsB_{{ $mIdx }}" value="{{ $currentScore['sets_b'] ?? 0 }}">
                <input type="hidden" name="games_a"         id="finishGamesA_{{ $mIdx }}" value="{{ $currentScore['games_a'] ?? 0 }}">
                <input type="hidden" name="games_b"         id="finishGamesB_{{ $mIdx }}" value="{{ $currentScore['games_b'] ?? 0 }}">
                <input type="hidden" name="set_number"      id="finishSetNumber_{{ $mIdx }}" value="{{ $currentScore['set_number'] ?? 1 }}">
                <input type="hidden" name="point_display_a" id="finishPointDisplayA_{{ $mIdx }}" value="0">
                <input type="hidden" name="point_display_b" id="finishPointDisplayB_{{ $mIdx }}" value="0">
                <input type="hidden" name="set_history"     id="finishSetHistory_{{ $mIdx }}" value="{{ json_encode($currentScore['set_history'] ?? []) }}">
                <input type="hidden" name="winner_team"     id="finishWinnerTeam_{{ $mIdx }}" value="">

                <button type="submit" id="btnFinishSession_{{ $mIdx }}" onclick="submitFinish(event, {{ $mIdx }})"
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
                <span>Skor sinkron otomatis realtime setiap <strong id="countdownTimer_{{ $mIdx }}" class="text-sky-700 font-bold">1.5s</strong></span>
            </div>
            <a href="{{ route('scoring.recap', $game['id']) }}"
               class="px-4 py-2 rounded-xl bg-white border border-slate-200 text-slate-700 text-xs font-semibold hover:bg-slate-50 shadow-2xs transition-colors flex items-center gap-1.5">
                <i class="fa-solid fa-ranking-star text-[10px]"></i> Lihat Klasemen Sementara
            </a>
        </div>
        @endif
    </div>


        @endforeach
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
            winnerTeam: {!! json_encode($mScore['winner_team'] ?? null) !!},
            isFinishing: false,
            courtNum: {{ $mIdx + 1 }},
            courtName: {!! json_encode($m['court_name'] ?? ('Court ' . ($mIdx + 1))) !!},
            matchKey: '{{ $mKey }}',
            serverVersion: {{ (int) ($mScore['version'] ?? 0) }},
            localVersion: {{ (int) ($mScore['version'] ?? 0) }},
            pendingSaves: 0,
            lastLocalActionTime: 0
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
        st.clientSeq = (st.clientSeq || 0) + 1;
        const clientSeq = st.clientSeq;

        // 1. Mutasi state lokal
        if (team === 'A') {
            handlePointWonByA(cIdx);
        } else {
            handlePointWonByB(cIdx);
        }

        // 2. Optimistic UI update seketika (0ms render time)
        updateDisplay(cIdx);
        const tUiDone = performance.now();
        console.log(`[Optimistic UI] Court ${st.courtNum} +1 ${team} rendered in ${(tUiDone - tClick).toFixed(2)}ms (localVersion=${st.localVersion}, pendingSaves=${st.pendingSaves})`);

        // 3. Simpan asinkron ke server
        saveScore(cIdx, null, clientSeq, tClick);
    }

    function handlePointWonByA(cIdx) {
        let st = courtsState[cIdx];
        if (st.isDeuce) {
            if (st.advantage === 'A') {
                gameWonBy('A', cIdx);
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
                gameWonBy('A', cIdx);
            }
        }
    }

    function handlePointWonByB(cIdx) {
        let st = courtsState[cIdx];
        if (st.isDeuce) {
            if (st.advantage === 'B') {
                gameWonBy('B', cIdx);
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
                gameWonBy('B', cIdx);
            }
        }
    }

    // ── Game Dimenangkan ─────────────────────────────────────────────────────
    function gameWonBy(team, cIdx) {
        resetPoints(cIdx);
        let st = courtsState[cIdx];
        if (team === 'A') {
            st.gamesA++;
            showToast('🎉 Game Won by Team A!');
        } else {
            st.gamesB++;
            showToast('🎉 Game Won by Team B!');
        }
        checkSetWinner(cIdx);
    }

    function checkSetWinner(cIdx) {
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
            st.winnerTeam = setWon;
            st.setsA = (setWon === 'Team A') ? 1 : 0;
            st.setsB = (setWon === 'Team B') ? 1 : 0;
            updateDisplay(cIdx);
            saveScore(cIdx, 'completed', st.localVersion);
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
        const clientSeq = st.localVersion;

        st.matchDone = true;
        st.winnerTeam = (st.gamesA >= st.gamesB) ? 'Team A' : 'Team B';
        st.setsA = (st.winnerTeam === 'Team A') ? 1 : 0;
        st.setsB = (st.winnerTeam === 'Team B') ? 1 : 0;
        updateDisplay(cIdx);
        saveScore(cIdx, 'completed', clientSeq, tClick);
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
            setLbl.innerHTML = `Status: <strong>${st.matchDone ? (UNIT_TAB_LABEL + ' Selesai') : 'Sedang Berlangsung'}</strong>`;
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
        syncFinishFormInputs(cIdx);
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
        const allCourtsDone = courtKeys.length > 0 && courtKeys.every(k => courtsState[k].matchDone);

        courtKeys.forEach(k => {
            const st = courtsState[k];
            const waitingBox = document.getElementById('waitingOtherCourts_' + k);
            const waitingText = document.getElementById('waitingOtherCourtsText_' + k);
            const nextNav = document.getElementById('nextRoundNav_' + k);

            if (st.matchDone) {
                if (!allCourtsDone) {
                    const otherUnfinished = courtKeys
                        .filter(otherK => otherK !== k && !courtsState[otherK].matchDone)
                        .map(otherK => courtsState[otherK].courtName || ('Court ' + courtsState[otherK].courtNum));
                    
                    const waitingNames = otherUnfinished.join(', ') || 'court lain';
                    if (waitingBox) waitingBox.classList.remove('hidden');
                    if (waitingText) {
                        waitingText.textContent = `Menunggu ${waitingNames} menyelesaikan ${UNIT_TAB_LABEL} ${ACTIVE_ROUND_NUM} sebelum melanjutkan ke ronde berikutnya.`;
                    }
                    if (nextNav) nextNav.classList.add('hidden');
                } else {
                    if (waitingBox) waitingBox.classList.add('hidden');
                    if (nextNav) nextNav.classList.remove('hidden');
                }
            } else {
                if (waitingBox) waitingBox.classList.add('hidden');
                if (nextNav) nextNav.classList.add('hidden');
            }
        });
    }

    async function saveScore(cIdx, status = null, clientSeq = null, tClick = null) {
        let st = courtsState[cIdx];
        if (st.isFinishing) return;

        st.clientSeq = (st.clientSeq || 0) + 1;
        const displays = getPointDisplays(cIdx);
        const currentStatus = status ?? (st.matchDone ? 'completed' : 'in_progress');
        const reqSeq = clientSeq || st.clientSeq;
        const body = {
            game_id         : GAME_ID,
            round           : ACTIVE_ROUND,
            match_key       : st.matchKey,
            court           : st.courtNum,
            scoring_type    : SCORING_TYPE,
            score_a         : st.gamesA,
            score_b         : st.gamesB,
            point_display_a : displays.a,
            point_display_b : displays.b,
            set_number      : Number(ACTIVE_ROUND_NUM),
            sets_a          : (st.gamesA >= st.gamesB && currentStatus === 'completed') ? 1 : 0,
            sets_b          : (st.gamesB > st.gamesA && currentStatus === 'completed') ? 1 : 0,
            games_a         : st.gamesA,
            games_b         : st.gamesB,
            set_history     : [{ set: Number(ACTIVE_ROUND_NUM), score_a: st.gamesA, score_b: st.gamesB }],
            idx_a           : st.idxA,
            idx_b           : st.idxB,
            is_deuce        : st.isDeuce,
            advantage       : st.advantage,
            winner_team     : st.winnerTeam || (st.gamesA >= st.gamesB ? 'Team A' : 'Team B'),
            status          : currentStatus,
            client_id       : CLIENT_ID,
            client_version  : reqSeq,
            client_seq      : reqSeq,
        };

        try {
            const res = await fetch(UPDATE_URL, {
                method : 'POST',
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

                if (incomingVer > (st.serverVersion || 0)) {
                    st.serverVersion = incomingVer;
                }
                if (incomingVer > (st.localVersion || 0)) {
                    st.localVersion = incomingVer;
                }

                if (tClick) {
                    const roundtripMs = performance.now() - tClick;
                    console.log(`[Network Roundtrip] Court ${st.courtNum} save roundtrip: ${roundtripMs.toFixed(2)}ms (serverVersion=${st.serverVersion})`);
                }
            } else {
                console.error(`Update score failed for court ${st.courtNum}:`, await res.text());
            }
        } catch (err) {
            console.warn(`Gagal simpan skor court ${st.courtNum}:`, err);
        } finally {
            st.pendingSaves = Math.max(0, (st.pendingSaves || 1) - 1);
            if (st.pendingSaves === 0) {
                st.localVersion = Math.max(st.localVersion || 0, st.serverVersion || 0);
            }
        }
    }

    function syncFinishFormInputs(cIdx) {
        let st = courtsState[cIdx];
        const finA       = document.getElementById('finishScoreA_' + cIdx);
        const finB       = document.getElementById('finishScoreB_' + cIdx);
        const finSetsA   = document.getElementById('finishSetsA_' + cIdx);
        const finSetsB   = document.getElementById('finishSetsB_' + cIdx);
        const finGamesA  = document.getElementById('finishGamesA_' + cIdx);
        const finGamesB  = document.getElementById('finishGamesB_' + cIdx);
        const finSetNum  = document.getElementById('finishSetNumber_' + cIdx);
        const finPDispA  = document.getElementById('finishPointDisplayA_' + cIdx);
        const finPDispB  = document.getElementById('finishPointDisplayB_' + cIdx);
        const finHistory = document.getElementById('finishSetHistory_' + cIdx);
        const finWinner  = document.getElementById('finishWinnerTeam_' + cIdx);

        const curWinner  = st.winnerTeam || (st.gamesA >= st.gamesB ? 'Team A' : 'Team B');
        const displays   = getPointDisplays(cIdx);

        if (finPDispA)  finPDispA.value  = displays.a;
        if (finPDispB)  finPDispB.value  = displays.b;
        if (finSetsA)   finSetsA.value   = (st.gamesA >= st.gamesB) ? 1 : 0;
        if (finSetsB)   finSetsB.value   = (st.gamesB > st.gamesA) ? 1 : 0;
        if (finGamesA)  finGamesA.value  = st.gamesA;
        if (finGamesB)  finGamesB.value  = st.gamesB;
        if (finSetNum)  finSetNum.value  = Number(ACTIVE_ROUND_NUM);
        if (finHistory) finHistory.value = JSON.stringify([{ set: Number(ACTIVE_ROUND_NUM), score_a: st.gamesA, score_b: st.gamesB }]);
        if (finA)       finA.value       = st.gamesA;
        if (finB)       finB.value       = st.gamesB;
        if (finWinner)  finWinner.value  = curWinner;
    }

    function submitFinish(event, cIdx) {
        if (event) event.preventDefault();
        
        let st = courtsState[cIdx];
        st.isFinishing = true;

        syncFinishFormInputs(cIdx);

        const btn = document.getElementById('btnFinishSession_' + cIdx);
        if (btn) {
            btn.disabled = true;
            btn.classList.add('opacity-75', 'cursor-not-allowed');
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin text-xs text-[#A8E63A]"></i> Menyimpan Sesi...';
        }

        showToast(`Menyimpan hasil akhir Court ${st.courtNum}...`);

        const form = document.getElementById('finishForm_' + cIdx);
        if (form) form.submit();
    }

    // Polling realtime untuk sinkronisasi skor (1.5 detik per siklus)
    Object.keys(courtsState).forEach(cIdx => {
        let st = courtsState[cIdx];
        const POLL_URL = `{{ url('scoring/get-score') }}/${GAME_ID}/${ACTIVE_ROUND}?court=${cIdx}&match_key=${st.matchKey}`;
        
        const timerEl = document.getElementById('countdownTimer_' + cIdx);
        const hostTimerEl = document.getElementById('hostSyncTimer_' + cIdx);
        const topTimerEl = document.getElementById('topSyncTimer_' + cIdx);

        let isPolling = false;
        let nextPollTime = Date.now() + 1500;

        // Visual countdown ticker yang berjalan halus & valid menghitung mundur dari 1.5s ke 0.0s
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
                const pollStart = performance.now();
                const pollUrlWithTs = `${POLL_URL}&_t=${Date.now()}`;
                const res = await fetch(pollUrlWithTs, {
                    cache: 'no-store',
                    headers: { 'Accept': 'application/json' }
                });
                if (!res.ok) return;
                const data = await res.json();
                const pollDuration = performance.now() - pollStart;

                const incomingVer = Number(data.version || 0);

                // Aturan Reconcile & Stale Protection:
                // 1. Jika ada save lokal in-flight (pendingSaves > 0) dan version polling < localVersion,
                //    abaikan agar tidak menimpa aksi lokal yang belum selesai tersimpan
                if ((st.pendingSaves || 0) > 0 && incomingVer < (st.localVersion || 0)) {
                    return;
                }

                // 2. Jika tidak ada save lokal in-flight, tetapi version polling < serverVersion saat ini,
                //    abaikan response polling lama/terlambat di jaringan
                if ((st.pendingSaves || 0) === 0 && incomingVer < (st.serverVersion || 0)) {
                    return;
                }

                // 3. Jangan batalkan status matchDone jika lokal sudah completed dan server belum
                if (st.matchDone && data.status !== 'completed') {
                    return;
                }

                // Update serverVersion dan localVersion jika incomingVer >= serverVersion
                if (incomingVer >= (st.serverVersion || 0)) {
                    st.serverVersion = incomingVer;
                    if ((st.pendingSaves || 0) === 0) {
                        st.localVersion = incomingVer;
                    }
                }

                // Cek apakah data berubah sebelum re-render untuk mencegah flicker
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
                    st.winnerTeam !== newWinner
                );

                if (hasChanged) {
                    console.log(`[Poll Applied] Court ${st.courtNum} sync to version ${incomingVer} (Games: ${newGamesA}-${newGamesB}, Point: ${data.point_display_a}:${data.point_display_b}) in ${pollDuration.toFixed(1)}ms`);
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

                    const dispA  = document.getElementById('scoreDisplayA_' + cIdx);
                    const dispB  = document.getElementById('scoreDisplayB_' + cIdx);
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

                    updateDisplay(cIdx);
                }
            } catch (err) {
                // Ignore polling errors
            } finally {
                isPolling = false;
                nextPollTime = Date.now() + 1500;
                updateTimerDisplay();
            }
        };

        // Jalankan polling loop tiap 1.5 detik
        setInterval(executePoll, 1500);

        // Jalankan polling awal segera (setelah 200ms)
        setTimeout(executePoll, 200);
    });

    // Helper Toast (keep as is if defined elsewhere or we can define it)
    if (typeof showToast !== 'function') {
        window.showToast = function(msg) {
            console.log("TOAST:", msg);
            // fallback toast if needed
        }
    }

    // Inisialisasi awal untuk semua court
    Object.keys(courtsState).forEach(cIdx => {
        updateDisplay(cIdx);
    });
    syncRoundCompletionStatus();
</script>
@endpush
@endsection

