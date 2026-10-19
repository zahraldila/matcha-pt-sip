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

    @php
        $sessionAllParticipants = $game['participants'] ?? [];
        $activeMembers = [];
        $restingMembers = [];
        $withdrawnMembers = [];

        foreach ($sessionAllParticipants as $part) {
            $pId = (int) ($part['id'] ?? 0);
            if (in_array($pId, $withdrawnPlayerIds ?? [], true)) {
                $withdrawnMembers[] = $part;
            } elseif (in_array($pId, $pausedPlayerIds ?? [], true)) {
                $restingMembers[] = $part;
            } else {
                $activeMembers[] = $part;
            }
        }

        $minReq = ($isSingleMode ?? false) ? 2 : 4;
        $isShortOnPlayers = count($activeMembers) < $minReq;
    @endphp

    {{-- Flash Notifications (Warning, Error, Success, Validation Errors) --}}
    @if(session('warning') || session('error') || session('success') || $errors->any())
        <div id="flashNotificationContainer" class="space-y-2">
            @if($errors->any())
                <div class="p-4 rounded-2xl bg-rose-50 border border-rose-300 text-rose-900 text-xs font-bold flex items-center gap-3 shadow-xs">
                    <i class="fa-solid fa-circle-exclamation text-rose-600 text-base shrink-0"></i>
                    <div class="space-y-0.5">
                        @foreach($errors->all() as $err)
                            <p>{{ $err }}</p>
                        @endforeach
                    </div>
                </div>
            @endif
            @if(session('warning'))
                <div class="p-4 rounded-2xl bg-amber-50 border border-amber-300 text-amber-900 text-xs font-bold space-y-2.5 shadow-xs">
                    <div class="flex items-center gap-3">
                        <i class="fa-solid fa-triangle-exclamation text-amber-600 text-base shrink-0"></i>
                        <span>{{ session('warning') }}</span>
                    </div>
                    @if($isHost && count($restingMembers) > 0)
                        <div class="pt-2 border-t border-amber-200/80 flex flex-wrap items-center gap-2">
                            <span class="text-[11px] text-amber-800 font-semibold">Tindakan Cepat:</span>
                            @foreach($restingMembers as $rPart)
                                @php
                                    $rPId = (int) ($rPart['id'] ?? 0);
                                    $rPName = $rPart['name'] ?? 'Pemain';
                                @endphp
                                <button type="button" onclick="reactivatePlayerLive({{ $rPId }}, '{{ addslashes($rPName) }}')" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold text-xs shadow-xs cursor-pointer transition-all active:scale-95">
                                    <i class="fa-solid fa-play text-[8px]"></i> Aktifkan {{ $rPName }} (Siap Main)
                                </button>
                            @endforeach
                            @if($allCourtsCompleted ?? false)
                                <button type="button" onclick="submitGlobalFinish(event)" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-800 hover:bg-slate-900 text-white font-bold text-xs shadow-xs cursor-pointer transition-all active:scale-95">
                                    <i class="fa-solid fa-trophy text-[9px] text-[#A8E63A]"></i> Selesaikan Sesi Lebih Awal
                                </button>
                            @endif
                        </div>
                    @endif
                </div>
            @endif
            @if(session('error') && session('error') !== session('warning') && (!session('warning') || !str_contains((string) session('error'), (string) session('warning'))))
                <div class="p-4 rounded-2xl bg-rose-50 border border-rose-300 text-rose-900 text-xs font-bold space-y-2.5 shadow-xs">
                    <div class="flex items-center gap-3">
                        <i class="fa-solid fa-circle-exclamation text-rose-600 text-base shrink-0"></i>
                        <span>{{ session('error') }}</span>
                    </div>
                    @if($isHost && count($restingMembers) > 0)
                        <div class="pt-2 border-t border-rose-200/80 flex flex-wrap items-center gap-2">
                            <span class="text-[11px] text-rose-800 font-semibold">Tindakan Cepat:</span>
                            @foreach($restingMembers as $rPart)
                                @php
                                    $rPId = (int) ($rPart['id'] ?? 0);
                                    $rPName = $rPart['name'] ?? 'Pemain';
                                @endphp
                                <button type="button" onclick="reactivatePlayerLive({{ $rPId }}, '{{ addslashes($rPName) }}')" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold text-xs shadow-xs cursor-pointer transition-all active:scale-95">
                                    <i class="fa-solid fa-play text-[8px]"></i> Aktifkan {{ $rPName }} (Siap Main)
                                </button>
                            @endforeach
                            @if($allCourtsCompleted ?? false)
                                <button type="button" onclick="submitGlobalFinish(event)" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-800 hover:bg-slate-900 text-white font-bold text-xs shadow-xs cursor-pointer transition-all active:scale-95">
                                    <i class="fa-solid fa-trophy text-[9px] text-[#A8E63A]"></i> Selesaikan Sesi Lebih Awal
                                </button>
                            @endif
                        </div>
                    @endif
                </div>
            @endif
            @if(session('success'))
                <div id="flashSuccessBanner" class="p-4 rounded-2xl bg-emerald-50 border border-emerald-300 text-[#063B00] text-xs font-bold flex items-center justify-between gap-3 shadow-xs transition-all duration-500 ease-in-out overflow-hidden">
                    <div class="flex items-center gap-3 min-w-0">
                        <i class="fa-solid fa-circle-check text-emerald-600 text-base shrink-0"></i>
                        <span class="truncate">{{ session('success') }}</span>
                    </div>
                    <button type="button" onclick="dismissFlashSuccessBanner()" class="text-emerald-700 hover:text-emerald-950 p-1 rounded-lg hover:bg-emerald-100 transition-colors shrink-0 cursor-pointer" aria-label="Tutup pemberitahuan">
                        <i class="fa-solid fa-xmark text-sm"></i>
                    </button>
                </div>
            @endif
        </div>
    @endif

    {{-- Network Quality & Offline Status Banner --}}
    <div id="networkStatusBannerContainer" class="hidden transition-all duration-300">
        <!-- Rendered dynamically by updateNetworkStatusUI -->
    </div>

    {{-- Round / Set Selector (Set 1, Set 2, Set 3) --}}
    @php
        $isTeamFormat = str_contains(strtolower($game['match_format'] ?? ''), 'team');
        // Team Americano menggunakan istilah 'Set' (pasangan tetap), sedangkan Americano menggunakan 'Ronde' (pasangan berganti)
        $unitTabLabel = $isTeamFormat ? 'Set' : 'Ronde';
        $drawingRounds = !empty($game['drawing']) ? array_keys($game['drawing']) : [];
        if (!empty($drawingRounds)) {
            $allRoundsList = $drawingRounds;
        } elseif ($scoringSystem['is_sets']) {
            $numSets = max(count($drawingRounds), (int) ($scoringSystem['max_sets'] ?? 1));
            $allRoundsList = array_map(fn ($roundNumber) => "round_{$roundNumber}", range(1, $numSets));
        } else {
            $allRoundsList = ['round_1'];
        }

        // Deduplikasi dan batasi sesuai scoring system jika total_of / is_sets
        $dedupRounds = [];
        foreach ($allRoundsList as $rk) {
            $rNum = (int) (preg_replace('/[^0-9]/', '', (string) $rk) ?: 1);
            if ($rNum > 0 && !isset($dedupRounds[$rNum])) {
                $dedupRounds[$rNum] = "round_{$rNum}";
            }
        }
        ksort($dedupRounds);
        if (!empty($scoringSystem['is_total_of']) || ($scoringSystem['category'] ?? '') === 'total_of' || !empty($scoringSystem['is_sets'])) {
            $maxSets = (int) ($scoringSystem['max_sets'] ?? 3);
            if ($maxSets > 0) {
                $dedupRounds = array_slice($dedupRounds, 0, $maxSets, true);
            }
        }
        $allRoundsList = array_values($dedupRounds);

        $currentRIndex = array_search($activeRound, $allRoundsList);
        $nextRoundKey = ($currentRIndex !== false && isset($allRoundsList[$currentRIndex + 1])) ? $allRoundsList[$currentRIndex + 1] : null;
        if ($canFinishSessionEarly ?? false) {
            $nextRoundKey = null;
        }
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
                    $rNumber = (int) (preg_replace('/[^0-9]/', '', $rKey) ?: '1');
                    $currActiveNum = (int) (preg_replace('/[^0-9]/', '', $savedScores['_meta']['active_round'] ?? 'round_1') ?: 1);
                    $tabDisplayTitle = "{$unitTabLabel} {$rNumber}";
                    $isTabActive = ($activeRound === $rKey);
                    $isRoundAccessible = $roundAccess[$rKey] ?? false;
                    // Non-host hanya bisa mengklik ronde yang sudah aktif dibuka host atau ronde lampau
                    if (! $isHost && $rNumber > $currActiveNum) {
                        $isRoundAccessible = false;
                    }
                    if (($canFinishSessionEarly ?? false) && $rNumber > (int) (preg_replace('/[^0-9]/', '', $activeRound) ?: 1)) {
                        $isRoundAccessible = false;
                    }
                    $rScore = $savedScores[$rKey] ?? ($savedScores["{$rKey}_court_1"] ?? []);
                    $isRCompleted = \App\Services\Scoring\ScoringService::isMatchTerminal($rScore['status'] ?? '');
                    if (! $isRCompleted && isset($game['drawing'][$rKey]['matches'])) {
                        $roundMatches = $game['drawing'][$rKey]['matches'];
                        if (! empty($roundMatches)) {
                            $allMTerminal = true;
                            foreach ($roundMatches as $mSubIdx => $mSub) {
                                $subKey = count($roundMatches) > 1 ? "{$rKey}_court_".($mSubIdx + 1) : $rKey;
                                $subScore = $savedScores[$subKey] ?? ($savedScores["{$rKey}_court_1"] ?? []);
                                if (! \App\Services\Scoring\ScoringService::isMatchTerminal($subScore['status'] ?? '')) {
                                    $allMTerminal = false;
                                    break;
                                }
                            }
                            $isRCompleted = $allMTerminal;
                        }
                    }
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
                        @elseif($rNumber === $currActiveNum)
                            <span class="text-[9px] px-1.5 py-0.2 rounded-md font-extrabold flex items-center gap-1 {{ $isTabActive ? 'bg-white/20 text-[#A8E63A]' : 'bg-rose-50 text-rose-700 border border-rose-200' }}">
                                <span class="w-1.5 h-1.5 rounded-full bg-rose-500 animate-pulse"></span> Live
                            </span>
                        @endif
                    </a>
                @else
                    <div class="px-3.5 py-1.5 rounded-xl text-xs font-bold border flex items-center gap-1.5 bg-slate-100/90 text-slate-400 border-slate-200/80 cursor-not-allowed select-none">
                        <span>{{ $tabDisplayTitle }}</span>
                        <span class="text-[9px] px-1.5 py-0.2 rounded-md bg-slate-200/80 text-slate-500 font-semibold flex items-center gap-1">
                            @if(! $isHost && ($roundAccess[$rKey] ?? false) && $rNumber > $currActiveNum)
                                <i class="fa-solid fa-clock text-[8px] text-amber-500"></i> Menunggu Host
                            @else
                                <i class="fa-solid fa-lock text-[8px]"></i> Terkunci
                            @endif
                        </span>
                    </div>
                @endif
            @endforeach
        </div>
    </div>
    @endif

    @php
        $activeRoundNumInt = (int) (preg_replace('/[^0-9]/', '', $activeRound) ?: 1);
        $currActiveNumInt = (int) (preg_replace('/[^0-9]/', '', $savedScores['_meta']['active_round'] ?? 'round_1') ?: 1);
        $isViewingPastRound = $activeRoundNumInt < $currActiveNumInt;
    @endphp

    @if($isViewingPastRound)
        <div class="p-3.5 rounded-2xl bg-amber-50/80 border border-amber-200/80 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs shadow-2xs">
            <div class="flex items-center gap-2.5 text-amber-900">
                <i class="fa-solid fa-clock-rotate-left text-amber-600 text-sm shrink-0"></i>
                <span class="font-medium">
                    Anda sedang melihat riwayat skor <strong>{{ $unitTabLabel }} {{ $activeRoundNumInt }}</strong> (Telah Selesai).
                </span>
            </div>
            <a href="{{ route('scoring.live', ['id' => $game['id'], 'format' => request('format', $game['match_format'] ?? 'Americano'), 'round' => "round_{$currActiveNumInt}"]) }}" 
               class="inline-flex items-center justify-center gap-1.5 px-3.5 py-1.5 rounded-xl bg-[#063B00] hover:bg-[#042a00] text-white font-bold text-xs shadow-xs transition-all active:scale-95 shrink-0">
                <span class="w-2 h-2 rounded-full bg-rose-400 animate-pulse"></span>
                <span>Tonton {{ $unitTabLabel }} {{ $currActiveNumInt }} (Sedang Live)</span>
                <i class="fa-solid fa-arrow-right text-[10px]"></i>
            </a>
        </div>
    @endif

    
    @php
        $courtCount = $matchContext['court_count'] ?? count($matchContext['matches'] ?? []);
        $activeRoundNum = preg_replace('/[^0-9]/', '', $activeRound) ?: '1';
        $uncompletedCourtNames = [];
        foreach ($matchContext['matches'] ?? [] as $checkIdx => $checkMatch) {
            $checkKey = \App\Services\Scoring\ScoringService::buildMatchKey($activeRound, $checkIdx + 1, $courtCount);
            $checkScore = $savedScores[$checkKey] ?? ($courtCount > 1 ? [] : ($savedScores[$activeRound] ?? ($savedScores["{$activeRound}_court_1"] ?? [])));
            if (! \App\Services\Scoring\ScoringService::isMatchTerminal($checkScore['status'] ?? '')) {
                $uncompletedCourtNames[$checkIdx] = $checkMatch['court_name'] ?? ('Court ' . ($checkIdx + 1));
            }
        }
        $allCourtsCompleted = empty($uncompletedCourtNames);
    @endphp
    @if($courtCount > 1)
    <!-- Court Tab Selector (Ketika Sesi Menggunakan > 1 Court) -->
    <div class="glass-card rounded-2xl p-4 border border-white/90 shadow-2xs space-y-3">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-slate-200/50 pb-2.5">
            <div class="flex items-center gap-2">
                <span class="text-xs font-bold text-slate-800 flex items-center gap-1.5">
                    <i class="fa-solid fa-table-tennis-paddle-ball text-[#063B00]"></i> Pilih Lapangan (Court):
                </span>
                <span class="text-[10px] font-bold text-slate-500 bg-slate-100 px-2 py-0.5 rounded-full border border-slate-200">
                    {{ $courtCount }} Lapangan
                </span>
            </div>
            <span class="text-[11px] text-slate-500 font-semibold">
                Klik tab lapangan di bawah untuk berganti tampilan scoring
            </span>
        </div>

        <!-- Court Tab Buttons -->
        <div class="flex flex-wrap items-center gap-2.5" id="courtTabsContainer">
            @foreach($matchContext['matches'] ?? [] as $tabIdx => $tabMatch)
                @php
                    $tabKey = \App\Services\Scoring\ScoringService::buildMatchKey($activeRound, $tabIdx + 1, $courtCount);
                    $tabScore = $savedScores[$tabKey] ?? [];
                    $isTabStopped = (($tabScore['status'] ?? '') === 'stopped');
                    $isTabDone = (($tabScore['status'] ?? '') === 'completed');
                    $tabGamesA = $tabScore['games_a'] ?? ($tabScore['score_a'] ?? 0);
                    $tabGamesB = $tabScore['games_b'] ?? ($tabScore['score_b'] ?? 0);
                    $isCurrentTabActive = ($tabIdx === (int) request('court', 0));
                    $cTabName = $tabMatch['court_name'] ?? ('Court ' . ($tabIdx + 1));
                @endphp
                <button type="button" onclick="switchActiveCourtTab({{ $tabIdx }})" id="btnCourtTab_{{ $tabIdx }}"
                    class="court-tab-btn px-4 py-2.5 rounded-xl text-xs font-bold border transition-all flex items-center gap-2 shadow-2xs cursor-pointer hover:scale-[1.01] active:scale-95
                           {{ $isCurrentTabActive ? 'bg-[#063B00] text-white border-[#063B00] shadow-xs ring-2 ring-[#063B00]/20' : 'bg-white text-slate-700 border-slate-200 hover:border-[#063B00]/50 hover:bg-slate-50' }}">
                    <i class="fa-solid fa-table-tennis-paddle-ball text-[10px] {{ $isCurrentTabActive ? 'text-[#A8E63A]' : 'text-slate-400' }}"></i>
                    <span>{{ $cTabName }}</span>
                    <span id="tabMiniBadge_{{ $tabIdx }}" class="text-[10px] px-2 py-0.5 rounded-md font-extrabold {{ $isTabStopped ? ($isCurrentTabActive ? 'bg-amber-500 text-white' : 'bg-amber-50 text-amber-900 border border-amber-300') : ($isTabDone ? ($isCurrentTabActive ? 'bg-white/20 text-[#A8E63A]' : 'bg-emerald-50 text-emerald-700 border border-emerald-200') : ($isCurrentTabActive ? 'bg-black/20 text-white' : 'bg-slate-100 text-slate-600 border border-slate-200')) }}">
                        @if($isTabStopped)
                            ⚠️ Dihentikan ({{ $tabGamesA }} - {{ $tabGamesB }})
                        @elseif($isTabDone)
                            ✓ Selesai ({{ $tabGamesA }} - {{ $tabGamesB }})
                        @else
                            {{ $tabGamesA }} - {{ $tabGamesB }}
                        @endif
                    </span>
                </button>
            @endforeach
        </div>
    </div>
    @endif

    <div id="courtBoardsWrapper" class="space-y-6">
        @foreach($matchContext['matches'] ?? [] as $mIdx => $matchData)
            @php
                $mKey = \App\Services\Scoring\ScoringService::buildMatchKey($activeRound, $mIdx + 1, $courtCount);
                $currentScore = $savedScores[$mKey] ?? ($courtCount > 1 ? [] : ($savedScores[$activeRound] ?? []));
                $isMCompleted = (($currentScore['status'] ?? '') === 'completed');
                $isMStopped = (($currentScore['status'] ?? '') === 'stopped');
                $isCourtVisible = ($courtCount <= 1 || $mIdx === (int) request('court', 0));

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
                    ?? (count($matchContext['matches'] ?? []) > 1 ? "Court " . ($mIdx + 1) . " - Tim A" : "TIM A"))));

                $tBName = $matchData['team_b_name'] 
                    ?? ($matchData['team_b']['display_name'] 
                    ?? ($matchData['team_b']['name'] 
                    ?? ($matchData['teamB_display'] 
                    ?? (count($matchContext['matches'] ?? []) > 1 ? "Court " . ($mIdx + 1) . " - Tim B" : "TIM B"))));
            @endphp
        <!-- Live Scoreboard Display (Subtle Glass) -->

        <div id="courtBoardContainer_{{ $mIdx }}" class="court-board-pane {{ $isCourtVisible ? '' : 'hidden' }} glass-card rounded-3xl p-6 sm:p-8 space-y-6 border border-white/90 shadow-sm relative">
        
        <!-- Locked Badge Notification if this set match is completed -->
        <div id="lockedBadge_{{ $mIdx }}" class="{{ $isMCompleted ? '' : 'hidden' }} p-3 bg-amber-50 border border-amber-200 rounded-2xl text-amber-900 text-xs font-bold flex items-center justify-between shadow-2xs">
            <span class="flex items-center gap-2">
                <i class="fa-solid fa-lock text-amber-600 text-sm"></i>
                <span id="lockedBadgeText_{{ $mIdx }}">Skor {{ $unitTabLabel }} {{ $activeRoundNum }} pada {{ $matchData['court_name'] ?? ('Court ' . ($mIdx + 1)) }} sudah selesai dan terkunci.</span>
            </span>
            <span class="text-[10px] bg-amber-200/80 px-2 py-0.5 rounded-full uppercase tracking-wider font-extrabold text-amber-800">Final Score</span>
        </div>

        <!-- Stopped Badge Notification if this match was stopped -->
        <div id="stoppedBadge_{{ $mIdx }}" class="{{ $isMStopped ? '' : 'hidden' }} p-3 bg-rose-50 border border-rose-200 rounded-2xl text-rose-900 text-xs font-bold flex items-center justify-between shadow-2xs">
            <span class="flex items-center gap-2">
                <i class="fa-solid fa-hand text-rose-600 text-sm"></i>
                <span id="stoppedBadgeText_{{ $mIdx }}">Pertandingan pada {{ $matchData['court_name'] ?? ('Court ' . ($mIdx + 1)) }} telah dihentikan oleh Host.</span>
            </span>
            <span class="text-[10px] bg-rose-200/80 px-2 py-0.5 rounded-full uppercase tracking-wider font-extrabold text-rose-800">Dihentikan</span>
        </div>

        <!-- Match Info Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 text-xs text-slate-500 border-b border-slate-200/50 pb-3">
            <div class="flex min-w-0 flex-1 items-center gap-2 overflow-hidden">
                <strong class="min-w-0 max-w-[45%] truncate whitespace-nowrap text-slate-800" title="{{ $game['venue_name'] }}">{{ $game['venue_name'] }}</strong>
                <span class="shrink-0">&bull;</span>
                <span class="min-w-0 max-w-[30%] truncate whitespace-nowrap text-[#063B00] font-bold" title="{{ $matchData['court_name'] ?? ('Court ' . ($mIdx + 1)) }}">{{ $matchData['court_name'] ?? ('Court ' . ($mIdx + 1)) }}</span>
                <span class="shrink-0">&bull;</span>
                <span class="shrink-0 font-bold text-slate-700">{{ $unitTabLabel }} {{ $activeRoundNum }}</span>
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
                    <i class="fa-solid fa-trophy text-[11px]"></i> {{ $unitTabLabel }} {{ $activeRoundNum }} dari {{ $scoringSystem['is_sets'] ? ($scoringSystem['max_sets'] ?? count($allRoundsList)) : count($allRoundsList) }}
                </span>
                <span class="font-medium text-slate-400">
                    @if($scoringSystem['is_total_of'] ?? false)
                        Format: <strong class="text-white">Total of {{ $scoringSystem['max_sets'] ?? 3 }} Set</strong>
                    @else
                        Target: <strong class="text-white">{{ $scoringSystem['target_games'] ?? 8 }} Games</strong>
                    @endif
                </span>
            </div>

            <div class="flex items-center justify-center gap-6 sm:gap-10 py-1">
                <div class="text-center">
                    <span class="text-[10px] uppercase font-bold text-slate-400 block">Games Tim A</span>
                    <span id="displayGameScoreA_{{ $mIdx }}" class="text-3xl sm:text-4xl font-black text-white">
                        {{ $currentScore['games_a'] ?? ($currentScore['score_a'] ?? 0) }}
                    </span>
                </div>
                <div class="text-slate-500 font-black text-2xl sm:text-3xl">&mdash;</div>
                <div class="text-center">
                    <span class="text-[10px] uppercase font-bold text-slate-400 block">Games Tim B</span>
                    <span id="displayGameScoreB_{{ $mIdx }}" class="text-3xl sm:text-4xl font-black text-white">
                        {{ $currentScore['games_b'] ?? ($currentScore['score_b'] ?? 0) }}
                    </span>
                </div>
            </div>

            <div id="setHistoryContainer_{{ $mIdx }}" class="flex items-center justify-center gap-2 flex-wrap pt-1 text-[11px]">
                <span class="text-slate-400 font-semibold" id="currentSetLabel_{{ $mIdx }}">
                    Status: <strong class="text-white">{{ $isMStopped ? ($unitTabLabel . ' Dihentikan') : ($isMCompleted ? ($unitTabLabel . ' Selesai & Terkunci') : 'Sedang Berlangsung') }}</strong>
                </span>
                <span id="currentSetGamesBadge_{{ $mIdx }}" class="hidden"></span>
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
                    <span id="subScoreLabelA_{{ $mIdx }}" class="hidden"></span>
                </div>

                <!-- Point Button — hanya tampil untuk Host -->
                @if($isHost)
                    @if($isMCompleted)
                        <button id="btnAddA_{{ $mIdx }}" disabled class="w-full py-3.5 rounded-xl bg-slate-100 text-slate-400 font-bold text-sm border border-slate-200 cursor-not-allowed flex items-center justify-center gap-2">
                            <i class="fa-solid fa-lock text-xs"></i> Skor Terkunci ({{ $unitTabLabel }} Selesai)
                        </button>
                    @else
                        <button id="btnAddA_{{ $mIdx }}" onclick="addPoint('A', {{ $mIdx }})" class="w-full py-3.5 rounded-xl bg-[#063B00] hover:bg-[#042a00] text-white font-bold text-sm shadow-sm transition-all hover:scale-[1.01] active:scale-95 flex items-center justify-center gap-2 cursor-pointer">
                            <i class="fa-solid fa-plus text-xs text-[#A8E63A]"></i> Tambah Poin Tim A
                        </button>
                    @endif
                @else
                    <div class="w-full py-2.5 rounded-xl bg-slate-50 border border-slate-200/70 text-center text-xs text-slate-400 font-medium">
                        <i class="fa-solid fa-eye text-[10px] mr-1"></i> Read-Only
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
                    <span id="subScoreLabelB_{{ $mIdx }}" class="hidden"></span>
                </div>

                <!-- Point Button — hanya tampil untuk Host -->
                @if($isHost)
                    @if($isMCompleted)
                        <button id="btnAddB_{{ $mIdx }}" disabled class="w-full py-3.5 rounded-xl bg-slate-100 text-slate-400 font-bold text-sm border border-slate-200 cursor-not-allowed flex items-center justify-center gap-2">
                            <i class="fa-solid fa-lock text-xs"></i> Skor Terkunci ({{ $unitTabLabel }} Selesai)
                        </button>
                    @else
                        <button id="btnAddB_{{ $mIdx }}" onclick="addPoint('B', {{ $mIdx }})" class="w-full py-3.5 rounded-xl bg-[#063B00] hover:bg-[#042a00] text-white font-bold text-sm shadow-sm transition-all hover:scale-[1.01] active:scale-95 flex items-center justify-center gap-2 cursor-pointer">
                            <i class="fa-solid fa-plus text-xs text-[#A8E63A]"></i> Tambah Poin Tim B
                        </button>
                    @endif
                @else
                    <div class="w-full py-2.5 rounded-xl bg-slate-50 border border-slate-200/70 text-center text-xs text-slate-400 font-medium">
                        <i class="fa-solid fa-eye text-[10px] mr-1"></i> Read-Only
                    </div>
                @endif
            </div>
        </div>

        <!-- Score Status Notice -->
        <div id="matchNotice_{{ $mIdx }}" class="p-3.5 bg-white/90 rounded-xl border border-slate-200/70 text-center text-xs font-semibold text-slate-700 shadow-2xs {{ ($isMCompleted && !$isMStopped) ? '' : 'hidden' }}">
            @if($isMCompleted && !$isMStopped)
                🔒 <strong>{{ $unitTabLabel }} {{ $activeRoundNum }} Selesai & Terkunci</strong> &bull; Skor: {{ $currentScore['games_a'] ?? 0 }} &mdash; {{ $currentScore['games_b'] ?? 0 }}
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

        <!-- Match Stopped Banner -->
        <div id="matchStoppedBanner_{{ $mIdx }}" class="{{ $isMStopped ? '' : 'hidden' }} p-5 rounded-2xl bg-amber-50 border border-amber-300 text-center space-y-2 shadow-sm">
            <div class="text-3xl">⚠️</div>
            <p class="text-base font-black text-amber-900" id="stoppedMsg_{{ $mIdx }}">
                {{ $matchData['court_name'] ?? ('Court ' . ($mIdx + 1)) }} Dihentikan Lebih Awal pada {{ $unitTabLabel }} {{ $activeRoundNum }}
            </p>
            <p class="text-xs text-amber-800 font-medium" id="stoppedSubMsg_{{ $mIdx }}">
                Alasan: <strong id="stoppedReasonText_{{ $mIdx }}">{{ $currentScore['stop_reason'] ?? 'Dihentikan oleh Host' }}</strong>
            </p>
            @php
                $stoppedPlayerNames = is_array($currentScore['withdrawn_player_names'] ?? null) ? $currentScore['withdrawn_player_names'] : [];
                $stoppedPlayerReasons = is_array($currentScore['player_reasons'] ?? null) ? $currentScore['player_reasons'] : [];
                $stoppedPlayerDetails = [];
                foreach ($stoppedPlayerNames as $stoppedPlayerName) {
                    $stoppedPlayer = collect($game['participants'] ?? [])->first(fn ($participant) => \App\Services\Scoring\ScoringService::cleanPlayerName($participant['name'] ?? '') === \App\Services\Scoring\ScoringService::cleanPlayerName((string) $stoppedPlayerName));
                    $stoppedPlayerId = $stoppedPlayer['id'] ?? null;
                    $stoppedPlayerReason = $stoppedPlayerId !== null ? ($stoppedPlayerReasons[$stoppedPlayerId] ?? null) : null;
                    $stoppedPlayerReason = $stoppedPlayerReason ?: ($stoppedPlayerReasons[$stoppedPlayerName] ?? ($currentScore['stop_reason'] ?? 'Alasan tidak dicatat'));
                    $stoppedPlayerDetails[] = $stoppedPlayerName.': '.$stoppedPlayerReason;
                }
                $stoppedPlayerLabel = in_array($currentScore['stop_type'] ?? '', ['temporary_pause', 'pause_players'], true) ? 'Pemain di Bench' : 'Pemain Keluar';
            @endphp
            <p class="text-[11px] text-rose-700 font-semibold {{ !empty($stoppedPlayerDetails) ? '' : 'hidden' }}" id="stoppedWithdrawnNotice_{{ $mIdx }}">
                {{ $stoppedPlayerLabel }}: {{ implode('; ', $stoppedPlayerDetails) }}
            </p>
        </div>

        <!-- Controls: Kunci & Selesaikan / Akhiri Pertandingan Court Ini — hanya untuk Host -->
        @if($isHost)
        <div class="flex items-center justify-between gap-3 pt-3 border-t border-slate-200/50">
            <div class="flex items-center gap-2">
                <button type="button" id="btnManualComplete_{{ $mIdx }}" onclick="manualCompleteSet({{ $mIdx }})"
                   class="{{ ($isMCompleted || $isMStopped) ? 'hidden' : '' }} px-4 py-2.5 rounded-xl bg-amber-50 border border-amber-300 text-amber-900 text-xs font-bold hover:bg-amber-100 shadow-2xs transition-colors flex items-center gap-1.5 justify-center cursor-pointer">
                    <i class="fa-solid fa-lock text-amber-600"></i> Kunci &amp; Selesaikan
                </button>
                <button type="button" id="btnStopMatch_{{ $mIdx }}" onclick="openStopMatchModal({{ $mIdx }})"
                   class="{{ ($isMCompleted || $isMStopped) ? 'hidden' : '' }} px-4 py-2.5 rounded-xl bg-rose-50 border border-rose-300 text-rose-900 text-xs font-bold hover:bg-rose-100 shadow-2xs transition-colors flex items-center gap-1.5 justify-center cursor-pointer">
                    <i class="fa-solid fa-stop text-rose-600"></i> Akhiri Pertandingan
                </button>
            </div>
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

        <!-- Action Buttons (Khusus Host) -->
        <div class="flex items-center gap-3 flex-wrap justify-end">
            @if($isHost)
                @if($nextRoundKey)
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
                            <span id="btnNextRoundText">Lanjut ke {{ $unitTabLabel }} {{ $nextRoundNum }} (Lihat Drawing Lapangan)</span>
                            <i id="btnNextRoundIcon" class="fa-solid fa-arrow-right text-[11px] text-[#A8E63A]"></i>
                        </button>
                    </form>
                @endif
                <form id="globalFinishForm" action="{{ route('scoring.finish') }}" method="POST" class="{{ (($allCourtsCompleted ?? false) && (! $nextRoundKey || ($canFinishSessionEarly ?? false))) ? '' : 'hidden' }} m-0">
                    @csrf
                    <input type="hidden" name="game_id"         value="{{ $game['id'] }}">
                    <input type="hidden" name="round"           value="{{ $activeRound }}">
                    <input type="hidden" name="scoring_system"  value="{{ $scoringSystem['label'] }}">
                    <input type="hidden" name="match_key"       id="globalFinishMatchKey" value="">
                    <input type="hidden" name="status"          id="globalFinishStatus" value="">
                    <input type="hidden" name="is_stopped"      id="globalFinishIsStopped" value="0">
                    <!-- Frontend tidak lagi mengirim set_number secara eksplisit -->
                    <input type="hidden" name="score_a"         id="globalFinishScoreA" value="0">
                    <input type="hidden" name="score_b"         id="globalFinishScoreB" value="0">
                    <input type="hidden" name="sets_a"          id="globalFinishSetsA" value="0">
                    <input type="hidden" name="sets_b"          id="globalFinishSetsB" value="0">
                    <input type="hidden" name="games_a"         id="globalFinishGamesA" value="0">
                    <input type="hidden" name="games_b"         id="globalFinishGamesB" value="0">
                    <input type="hidden" name="set_history"     id="globalFinishSetHistory" value="">
                    <input type="hidden" name="winner_team"     id="globalFinishWinnerTeam" value="">

                    <button type="submit" id="btnGlobalFinishSession" onclick="submitGlobalFinish(event)"
                        class="px-6 py-2.5 rounded-xl bg-[#063B00] hover:bg-[#042a00] text-white font-extrabold text-xs shadow-md transition-all hover:scale-[1.01] active:scale-95 flex items-center justify-center gap-2 cursor-pointer">
                        <i class="fa-solid fa-trophy text-[11px] text-[#A8E63A]"></i> Selesaikan Sesi &amp; Lihat Juara
                    </button>
                </form>
            @endif
        </div>
    </div>
    {{-- Panel Kesiapan Pemain Saat Ini (Persistent Session Membership) --}}
    @php
        $sessionAllParticipants = $game['participants'] ?? [];
        $activeMembers = [];
        $restingMembers = [];
        $withdrawnMembers = [];

        foreach ($sessionAllParticipants as $part) {
            $pId = (int) ($part['id'] ?? 0);
            if (in_array($pId, $withdrawnPlayerIds ?? [], true)) {
                $withdrawnMembers[] = $part;
            } elseif (in_array($pId, $pausedPlayerIds ?? [], true)) {
                $restingMembers[] = $part;
            } else {
                $activeMembers[] = $part;
            }
        }

        $isSingleMode = $isSingleMode ?? (strtolower($game['jenis_permainan'] ?? ($session->jenis_permainan ?? 'Double')) === 'single');
        $minReq = $isSingleMode ? 2 : 4;
        $isShortOnPlayers = count($activeMembers) < $minReq;
    @endphp

    <div class="glass-card rounded-2xl p-5 border border-white/90 space-y-4 shadow-2xs">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-100 pb-3">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-emerald-50 text-emerald-700 border border-emerald-200 flex items-center justify-center text-sm shrink-0 shadow-2xs">
                    <i class="fa-solid fa-users-viewfinder"></i>
                </div>
                <div>
                    <h3 class="text-xs sm:text-sm font-bold text-slate-900 flex items-center gap-2 flex-wrap">
                        <span>Status Pemain &amp; Rotasi Sesi</span>
                        <span id="readinessActiveCount" class="text-[10px] font-semibold text-emerald-800 bg-emerald-100 px-2 py-0.5 rounded-full border border-emerald-200">
                            {{ count($activeMembers) }} Siap / Aktif
                        </span>
                        <span id="readinessRestingCount" class="text-[10px] font-semibold text-amber-800 bg-amber-100 px-2 py-0.5 rounded-full border border-amber-200 {{ count($restingMembers) > 0 ? '' : 'hidden' }}">
                            {{ count($restingMembers) }} Istirahat
                        </span>
                        <span id="readinessWithdrawnCount" class="text-[10px] font-semibold text-rose-800 bg-rose-100 px-2 py-0.5 rounded-full border border-rose-200 {{ count($withdrawnMembers) > 0 ? '' : 'hidden' }}">
                            {{ count($withdrawnMembers) }} Keluar
                        </span>
                    </h3>
                    <p class="text-[10px] sm:text-xs text-slate-400 font-medium">Status partisipasi aktual seluruh peserta dan alokasi rotasi ronde ini</p>
                </div>
            </div>

            <div id="readinessWarning" class="items-center gap-1.5 px-3 py-1.5 rounded-xl bg-amber-50 border border-amber-300 text-amber-800 text-[11px] font-bold shadow-2xs shrink-0 max-w-full {{ $isShortOnPlayers ? 'flex' : 'hidden' }}">
                <i class="fa-solid fa-triangle-exclamation text-amber-600"></i>
                <span id="readinessWarningText">Tersisa {{ count($activeMembers) }} pemain (min. {{ $minReq }}). Aktifkan pemain istirahat.</span>
            </div>
        </div>

        <div class="space-y-1.5">
            <span class="text-[11px] font-bold text-slate-600 flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-full bg-emerald-500"></span> Partisipasi Pemain Sesi
            </span>
            <div id="sessionReadinessRoster" class="flex flex-wrap items-center gap-2 pt-0.5">
                {{-- Pemain Aktif / Rotasi --}}
                @foreach($activeMembers as $part)
                    @php
                        $pName = $part['name'] ?? 'Pemain';
                        $pId = (int) ($part['id'] ?? 0);
                    @endphp
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-white text-slate-700 border border-slate-200 text-xs font-medium shadow-2xs">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 shrink-0"></span>
                        <span>{{ $pName }}</span>
                        <span class="text-[9px] font-semibold text-emerald-700 bg-emerald-50 px-1.5 py-0.5 rounded border border-emerald-200">Siap</span>
                    </span>
                @endforeach

                {{-- Pemain Keluar (Withdrawn) --}}
                @foreach($withdrawnMembers as $part)
                    @php
                        $pName = $part['name'] ?? 'Pemain';
                    @endphp
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-rose-50 text-slate-800 border border-rose-300 text-xs font-medium shadow-2xs opacity-80">
                        <span class="w-2 h-2 rounded-full bg-rose-500 shrink-0"></span>
                        <span class="line-through text-slate-500">{{ $pName }}</span>
                        <span class="text-[9px] font-bold text-rose-800 bg-rose-100 px-1.5 py-0.5 rounded border border-rose-300">Keluar</span>
                    </span>
                @endforeach
            </div>
        </div>

        {{-- Tim Istirahat Set Ini --}}
        @php
            $visibleRoundBench = [];
            $visibleRoundBenchIds = [];

            // 1. Masukkan pemain rotasi bench normal dari drawing
            foreach ($matchContext['resting'] ?? [] as $benchPlayer) {
                $benchName = is_array($benchPlayer) ? ($benchPlayer['name'] ?? $benchPlayer['nama'] ?? '') : (string) $benchPlayer;
                $benchPlayerId = is_array($benchPlayer) ? ($benchPlayer['id'] ?? $benchPlayer['player_id'] ?? null) : null;
                if (! $benchPlayerId && $benchName !== '') {
                    $matchedParticipant = collect($game['participants'] ?? [])->first(fn ($participant) => \App\Services\Scoring\ScoringService::cleanPlayerName($participant['name'] ?? '') === \App\Services\Scoring\ScoringService::cleanPlayerName($benchName));
                    $benchPlayerId = $matchedParticipant['id'] ?? null;
                }
                if ($benchPlayerId && in_array((int) $benchPlayerId, $withdrawnPlayerIds ?? [], true)) {
                    continue;
                }
                $visibleRoundBench[] = $benchPlayer;
                if ($benchPlayerId) {
                    $visibleRoundBenchIds[] = (int) $benchPlayerId;
                }
            }

            // 2. Sertakan juga seluruh pemain yang sedang istirahat sementara (resting / paused)
            foreach ($restingMembers as $rMember) {
                $rMemId = (int) ($rMember['id'] ?? 0);
                if ($rMemId && ! in_array($rMemId, $visibleRoundBenchIds, true)) {
                    $visibleRoundBench[] = $rMember;
                    $visibleRoundBenchIds[] = $rMemId;
                }
            }
        @endphp
        <div id="liveRoundBenchPanel" class="pt-3 border-t border-slate-100 space-y-2 {{ count($visibleRoundBench) > 0 ? '' : 'hidden' }}">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold text-slate-700 flex items-center gap-1.5">
                    <i class="fa-solid fa-mug-hot text-amber-600"></i>
                    Bangku Istirahat (Bench) {{ $unitTabLabel }} Ini
                    <span id="liveRoundBenchCount" class="text-[10px] font-semibold text-amber-700 bg-amber-50 px-2 py-0.5 rounded-full border border-amber-200">
                        {{ count($visibleRoundBench) }} Pemain
                    </span>
                </span>
                <span class="text-[10px] text-slate-400 font-medium">Rotasi bermain pada {{ $unitTabLabel }} berikutnya</span>
            </div>
            <div id="liveRoundBenchList" class="flex flex-wrap items-center gap-2 pt-0.5">
                @foreach($visibleRoundBench as $rPlayer)
                    @php
                        $rName = is_array($rPlayer) ? ($rPlayer['name'] ?? $rPlayer['nama'] ?? '') : $rPlayer;
                        $rId = is_array($rPlayer) ? ($rPlayer['id'] ?? null) : null;
                        if (!$rId) {
                            foreach($game['participants'] ?? [] as $part) {
                                if (($part['name'] ?? '') === $rName) {
                                    $rId = $part['id'] ?? null;
                                    break;
                                }
                            }
                        }
                        $currActiveNum = (int) preg_replace('/[^0-9]/', '', $savedScores['_meta']['active_round'] ?? '1');
                        $thisRoundNum = (int) preg_replace('/[^0-9]/', '', $activeRound ?? '1');
                        $isPastRound = $thisRoundNum < $currActiveNum;
                        $roundData = $game['drawing'][$activeRound] ?? [];
                        $roundPausedPlayers = $roundData['paused_players'] ?? [];
                        $roundPausedIds = $roundData['paused_player_ids'] ?? [];
                        if ($isPastRound) {
                            $isPaused = in_array($rName, $roundPausedPlayers, true) || ($rId && in_array((int)$rId, $roundPausedIds, true));
                        } else {
                            $isPaused = $rId && in_array((int)$rId, $pausedPlayerIds ?? [], true);
                        }
                    @endphp
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl {{ $isPaused ? 'bg-amber-50 text-slate-800 border border-amber-300' : 'bg-white text-slate-700 border border-slate-200' }} text-xs font-medium shadow-2xs">
                        <span class="w-1.5 h-1.5 rounded-full {{ $isPaused ? 'bg-amber-600' : 'bg-amber-500' }} shrink-0"></span>
                        {{ $rName }}
                        @if($isPaused)
                            <span class="text-[9px] font-bold text-amber-800 bg-amber-100 px-1.5 py-0.5 rounded border border-amber-300">Istirahat</span>
                            @if(! $isPastRound && $isHost)
                                <button type="button" onclick="reactivatePlayerLive({{ $rId }}, '{{ addslashes($rName) }}')" class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-[9px] shadow-2xs cursor-pointer ml-1 transition-all active:scale-95">
                                    <i class="fa-solid fa-play text-[7px]"></i> Siap Main
                                </button>
                            @endif
                        @else
                            <span class="text-[9px] font-bold text-amber-700 bg-amber-50 px-1.5 py-0.5 rounded border border-amber-200">Bench</span>
                        @endif
                    </span>
                @endforeach
            </div>
        </div>
    </div>
    <!-- End Match Modal (Dua Kondisi: Tetap di Sesi / Keluar Sesi Permanen) -->
    <div id="stopMatchModal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6 bg-slate-900/60 backdrop-blur-xs overflow-y-auto animate-in fade-in duration-150">
        <div class="relative w-full bg-white rounded-3xl shadow-2xl border border-slate-100 overflow-hidden flex flex-col my-auto transition-all" style="max-width: 580px; width: 100%; box-sizing: border-box; max-height: min(90vh, 850px);">
            <!-- Modal Header -->
            <div class="px-5 py-3.5 sm:px-6 sm:py-4 bg-gradient-to-r from-rose-50/90 via-white to-amber-50/60 border-b border-rose-100/70 flex items-center justify-between shrink-0">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-rose-50 border border-rose-200/80 text-rose-600 flex items-center justify-center text-base shadow-2xs shrink-0">
                        <i class="fa-solid fa-hand"></i>
                    </div>
                    <div>
                        <h3 class="text-sm sm:text-base font-black text-slate-900 tracking-tight">Akhiri Pertandingan (End Match)</h3>
                        <p class="text-[11px] text-slate-500 font-medium">Hentikan match aktif ini tanpa menyelesaikan seluruh sesi</p>
                    </div>
                </div>
                <button type="button" onclick="closeStopMatchModal()" class="w-8 h-8 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-400 hover:text-slate-700 flex items-center justify-center text-xs transition-colors cursor-pointer">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <!-- Modal Content (Scrollable) -->
            <div class="p-5 sm:p-6 overflow-y-auto space-y-4 text-xs text-slate-700">
                <!-- Match Snapshot Box -->
                <div class="p-3.5 bg-gradient-to-br from-slate-50 to-slate-100/70 border border-slate-200/80 rounded-2xl space-y-2.5 shadow-2xs">
                    <div class="flex items-center justify-between gap-2 flex-wrap">
                        <span class="font-extrabold text-slate-800 text-xs flex items-center gap-1.5" id="modalMatchTitle">
                            <span class="w-2 h-2 rounded-full bg-[#A8E63A] ring-2 ring-[#063B00]/20"></span>
                            <span id="modalMatchRoundCourt">Round 1 &bull; Court 1</span>
                        </span>
                        <span class="text-[10px] font-black px-2.5 py-0.5 rounded-full bg-white shadow-2xs text-amber-900 border border-amber-200/90" id="modalMatchScoreSnapshot">
                            Skor: 0 — 0
                        </span>
                    </div>
                    <div class="grid grid-cols-5 items-center gap-2 pt-1 border-t border-slate-200/60 text-xs">
                        <div class="col-span-2 p-2 rounded-xl bg-white border border-slate-200/70 text-center font-bold text-slate-800 truncate shadow-2xs" id="modalTeamANames">
                            Tim A
                        </div>
                        <div class="col-span-1 text-center font-black text-[11px] text-slate-400">
                            VS
                        </div>
                        <div class="col-span-2 p-2 rounded-xl bg-white border border-slate-200/70 text-center font-bold text-slate-800 truncate shadow-2xs" id="modalTeamBNames">
                            Tim B
                        </div>
                    </div>
                </div>

                <!-- Condition Selector (2 Main Options) -->
                <div class="space-y-1.5">
                    <label class="block font-bold text-slate-800 text-xs">Pilih Kondisi Penghentian <span class="text-rose-500">*</span></label>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                        <!-- Option 1: Tetap di Sesi -->
                        <label class="relative flex flex-col p-3 rounded-2xl border-2 border-emerald-600 bg-emerald-50/50 cursor-pointer transition-all select-none shadow-2xs hover:shadow-xs" id="labelConditionA">
                            <input type="radio" name="stopCondition" value="keep_in_session" checked onchange="onStopConditionChange()" class="sr-only">
                            <div class="flex items-center gap-2">
                                <span class="w-4 h-4 rounded-full border-2 border-emerald-600 flex items-center justify-center shrink-0" id="radioCircleA">
                                    <span class="w-2 h-2 rounded-full bg-emerald-600"></span>
                                </span>
                                <span class="font-extrabold text-xs text-slate-800">1. Tetap di Sesi (Pemain Lanjut)</span>
                            </div>
                            <p class="text-[10px] text-slate-500 font-medium mt-1.5 leading-snug">
                                Match berhenti, semua pemain lanjut ke ronde berikutnya atau istirahat sejenak ke bench.
                            </p>
                        </label>

                        <!-- Option 2: Keluar Sesi Permanen -->
                        <label class="relative flex flex-col p-3 rounded-2xl border border-slate-200 bg-white cursor-pointer transition-all select-none hover:border-slate-300 hover:bg-slate-50/50" id="labelConditionC">
                            <input type="radio" name="stopCondition" value="withdraw_players" onchange="onStopConditionChange()" class="sr-only">
                            <div class="flex items-center gap-2">
                                <span class="w-4 h-4 rounded-full border-2 border-slate-300 flex items-center justify-center shrink-0" id="radioCircleC">
                                    <span class="w-2 h-2 rounded-full bg-rose-600 hidden"></span>
                                </span>
                                <span class="font-extrabold text-xs text-slate-800">2. Keluar Sesi (Permanen)</span>
                            </div>
                            <p class="text-[10px] text-slate-500 font-medium mt-1.5 leading-snug">
                                Pemain cedera berat atau harus pulang. Ditarik permanen dari seluruh sisa ronde.
                            </p>
                        </label>
                    </div>
                </div>

                <!-- Condition 1 Inputs (Tetap di Sesi & Opsi Bench) -->
                <div id="sectionConditionA" class="space-y-3">
                    <div class="space-y-1" id="containerStopReasonA">
                        <label for="stopReasonA" class="block font-bold text-slate-800 text-xs">
                            Alasan Penghentian <span class="text-rose-500">*</span>
                        </label>
                        <textarea id="stopReasonA" rows="2" placeholder="Contoh: Waktu sewa lapangan habis, court basah karena hujan, dll."
                            oninput="hideStopMatchError()"
                            class="w-full px-3.5 py-2 rounded-xl border border-slate-200 text-xs bg-slate-50/50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#063B00]/20 focus:border-[#063B00] transition-all placeholder:text-slate-400"></textarea>
                    </div>

                    <!-- Optional Bench / Pause Checkbox Section -->
                    <div class="p-3.5 bg-amber-50/70 rounded-2xl border border-amber-200/90 space-y-2.5 shadow-2xs">
                        <label class="flex items-start gap-2.5 cursor-pointer select-none">
                            <input type="checkbox" id="chkPausePlayersToggle" onchange="onTogglePausePlayersCheckbox()" class="rounded text-amber-600 focus:ring-amber-500 w-4 h-4 mt-0.5 shrink-0">
                            <div>
                                <span class="font-extrabold text-xs text-amber-950 block">
                                    Istirahatkan pemain tertentu sementara ke Bangku Cadangan (Bench)
                                </span>
                                <span class="text-[10px] text-amber-800/80 font-medium block mt-0.5">
                                    Centang opsi ini jika ada pemain yang butuh istirahat sejenak (ke toilet / kram ringan).
                                </span>
                            </div>
                        </label>

                        <div id="wrapperPausePlayersSection" class="hidden pl-6 space-y-2.5 pt-2 border-t border-amber-200/60">
                            <p class="text-[11px] text-slate-700 font-medium leading-snug">
                                Pilih pemain yang butuh jeda istirahat (pemain masuk cadangan sampai Host menekan tombol <strong>Siap Main</strong>):
                            </p>
                            <div id="condPausePlayersList" class="grid grid-cols-1 sm:grid-cols-2 gap-2"></div>
                            <div class="space-y-1 pt-1">
                                <label for="stopReasonPause" class="block font-bold text-slate-700 text-[11px]">
                                    Keterangan Istirahat (Opsional)
                                </label>
                                <input type="text" id="stopReasonPause" placeholder="Contoh: Ke toilet sebentar, kram ringan, istirahat sejenak"
                                    oninput="hideStopMatchError()"
                                    class="w-full px-3 py-1.5 rounded-xl border border-slate-200 text-xs bg-white focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all placeholder:text-slate-400">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Condition 2 Inputs (Permanent Withdrawal) -->
                <div id="sectionConditionC" class="hidden space-y-3">
                    <div class="space-y-1">
                        <label class="block font-bold text-slate-800 text-xs">
                            Pilih Pemain yang Keluar dari Sesi (Permanen) <span class="text-rose-500">*</span>
                        </label>
                        <p class="text-[10px] text-slate-500">Pilih satu atau lebih peserta match ini yang tidak dapat melanjutkan sesi:</p>
                        <div id="condBPlayersList" class="space-y-2 pt-1"></div>
                    </div>
                </div>

                <!-- Impact Summary Box -->
                <div class="p-3.5 bg-amber-50/70 border border-amber-200/90 rounded-2xl space-y-1.5 text-[11px] text-amber-900 shadow-2xs">
                    <div class="flex items-center gap-1.5 font-bold">
                        <i class="fa-solid fa-circle-info text-amber-600"></i>
                        <span>Dampak terhadap Pertandingan &amp; Jadwal:</span>
                    </div>
                    <ul class="list-disc list-inside space-y-1 text-[10px] text-amber-800/90 pl-1" id="impactBulletList">
                        <li>Match court ini akan ditandai <strong>Dihentikan</strong> dengan skor parsial terakhir yang sah.</li>
                        <li>Ronde berikutnya dapat dimulai setelah semua court aktif mencapai status terminal.</li>
                        <li>Drawing dan seluruh pemain tetap berpartisipasi pada ronde mendatang.</li>
                    </ul>
                </div>

                <!-- Error Container -->
                <div id="modalStopError" class="hidden p-3 rounded-2xl bg-rose-50 border border-rose-200 text-rose-800 text-xs font-semibold flex items-center gap-2">
                    <i class="fa-solid fa-circle-exclamation text-rose-600 shrink-0"></i>
                    <span id="modalStopErrorMsg"></span>
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="px-5 py-3.5 sm:px-6 bg-slate-50/80 border-t border-slate-100 flex items-center justify-end gap-2.5 shrink-0">
                <button type="button" onclick="closeStopMatchModal()"
                    class="px-4 py-2 rounded-xl bg-white border border-slate-200 text-slate-700 text-xs font-bold hover:bg-slate-100 transition-colors cursor-pointer">
                    Batal
                </button>
                <button type="button" id="btnConfirmStopMatch" onclick="confirmStopMatch()"
                    class="px-4 py-2 rounded-xl bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold shadow-xs hover:shadow transition-all flex items-center gap-1.5 cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed hover:scale-[1.01]">
                    <i class="fa-solid fa-check"></i>
                    <span>Konfirmasi Akhiri Pertandingan</span>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL REAKTIVASI PEMAIN (SIAP MAIN) DI LIVE SCORING -->
<div id="reactivateConfirmModalLive" class="fixed inset-0 items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs overflow-y-auto animate-in fade-in duration-150" style="display: none; z-index: 99999;" onclick="if(event.target === this) closeReactivateLiveModal();">
    <div class="bg-white rounded-3xl p-6 shadow-2xl space-y-4 my-8 border border-slate-100 flex flex-col" style="max-width: 440px; width: 100%; box-sizing: border-box;" onclick="event.stopPropagation();">
        <div class="flex items-start gap-3.5">
            <div class="w-11 h-11 rounded-2xl bg-emerald-50 text-[#063B00] flex items-center justify-center text-lg border border-emerald-100/80 shadow-2xs shrink-0 mt-0.5">
                <i class="fa-solid fa-user-check"></i>
            </div>
            <div class="space-y-1">
                <h3 class="text-sm font-black text-slate-900">Aktifkan Kembali Pemain</h3>
                <p class="text-xs text-slate-500 leading-relaxed">
                    Aktifkan kembali <strong id="reactivateLivePlayerNameDisplay" class="text-slate-800 font-extrabold">-</strong> untuk ikut dalam jadwal pertandingan ronde berikutnya? Status istirahat akan dihentikan dan pemain kembali ke rotasi jadwal.
                </p>
            </div>
        </div>

        <div id="reactivateLiveModalError" class="hidden p-2.5 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-xs font-semibold flex items-center gap-2">
            <i class="fa-solid fa-circle-exclamation text-rose-600"></i>
            <span id="reactivateLiveModalErrorMsg"></span>
        </div>

        <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-2.5">
            <button
                type="button"
                onclick="closeReactivateLiveModal()"
                class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs transition-colors cursor-pointer"
            >
                Batal
            </button>
            <button
                type="button"
                id="btnConfirmReactivateLive"
                onclick="executeReactivatePlayerLive()"
                class="px-5 py-2 rounded-xl bg-[#063B00] hover:bg-[#042a00] text-white font-extrabold text-xs shadow-md shadow-[#063B00]/20 flex items-center gap-1.5 transition-all cursor-pointer hover:scale-[1.01]"
            >
                <i class="fa-solid fa-play text-[10px]"></i>
                <span id="btnConfirmReactivateLiveText">Ya, Siap Main</span>
            </button>
        </div>
    </div>
</div>

<!-- MODAL PERINGATAN PEMAIN AKTIF KURANG DI LIVE SCORING -->
<div id="insufficientPlayersWarningModal" class="fixed inset-0 items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs overflow-y-auto animate-in fade-in duration-150" style="display: none; z-index: 99999;" onclick="if(event.target === this) closeInsufficientPlayersWarningModal();">
    <div class="bg-white rounded-3xl p-6 shadow-2xl space-y-4 my-8 border border-slate-100 flex flex-col" style="max-width: 460px; width: 100%; box-sizing: border-box;" onclick="event.stopPropagation();">
        <div class="flex items-start gap-3.5">
            <div class="w-12 h-12 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center text-xl border border-amber-200/80 shadow-2xs shrink-0 mt-0.5">
                <i class="fa-solid fa-triangle-exclamation"></i>
            </div>
            <div class="space-y-1">
                <h3 class="text-sm font-black text-slate-900">Pemain Aktif Kurang dari Kuota</h3>
                <p id="insufficientPlayersWarningMessage" class="text-xs text-slate-600 leading-relaxed">
                    Jumlah pemain aktif tidak mencukupi untuk melanjutkan ronde berikutnya.
                </p>
            </div>
        </div>

        <div class="p-3.5 rounded-2xl bg-amber-50/80 border border-amber-200/90 text-amber-900 text-xs space-y-2">
            <div class="font-bold flex items-center gap-1.5 text-amber-950">
                <i class="fa-solid fa-circle-info text-amber-600"></i>
                <span>Petunjuk untuk Host:</span>
            </div>
            <p id="insufficientPlayersHostGuidance" class="leading-relaxed text-[11px] text-amber-800">
                Pertandingan ini telah berhasil dihentikan. Jadwal ronde berikutnya akan ditunda sementara hingga Host mengaktifkan kembali pemain istirahat melalui tombol <strong class="font-extrabold text-[#063B00]">[Siap Main]</strong> di Bangku Cadangan.
            </p>
            <div id="insufficientPlayersActionContainer" class="hidden"></div>
        </div>

        <div class="pt-3 border-t border-slate-100 flex items-center justify-end">
            <button
                type="button"
                onclick="closeInsufficientPlayersWarningModal()"
                class="w-full sm:w-auto px-5 py-2.5 rounded-xl bg-[#063B00] hover:bg-[#042a00] text-white font-extrabold text-xs shadow-md shadow-[#063B00]/20 flex items-center justify-center gap-1.5 transition-all cursor-pointer hover:scale-[1.01]"
            >
                <i class="fa-solid fa-check text-[11px]"></i>
                <span>Saya Mengerti</span>
            </button>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/@supabase/supabase-js@2"></script>
<script>
    // ── Config dari PHP ──────────────────────────────────────────────────────
    const SCORING_TYPE       = '{{ $scoringSystem['type'] }}';
    const IS_TOTAL_OF        = {{ ($scoringSystem['is_total_of'] ?? false) ? 'true' : 'false' }};
    const TARGET_TOTAL_GAMES = {{ (int) ($scoringSystem['target_total_games'] ?? ($scoringSystem['target_games'] ?? 3)) }};
    const IS_SETS            = {{ $scoringSystem['is_sets'] ? 'true' : 'false' }};
    const TARGET_SETS        = {{ $scoringSystem['target_sets'] ?? 1 }};
    const MAX_SETS           = {{ $scoringSystem['max_sets'] ?? 1 }};
    const TARGET_GAMES       = {{ (int) ($scoringSystem['target_games'] ?? 8) }};
    const GAME_ID        = {{ $game['id'] }};
    const ACTIVE_ROUND   = '{{ $activeRound }}';
    const ACTIVE_ROUND_NUM = '{{ preg_replace('/[^0-9]/', '', $activeRound) ?: '1' }}';
    const SESSION_ACTIVE_ROUND_NUM = {{ (int) (preg_replace('/[^0-9]/', '', $savedScores['_meta']['active_round'] ?? 'round_1') ?: 1) }};
    const IS_VIEWING_PAST_ROUND = {{ ((int) (preg_replace('/[^0-9]/', '', $activeRound) ?: 1)) < ((int) (preg_replace('/[^0-9]/', '', $savedScores['_meta']['active_round'] ?? 'round_1') ?: 1)) ? 'true' : 'false' }};
    const UNIT_TAB_LABEL = '{{ $unitTabLabel }}';
    const CSRF_TOKEN     = document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}';
    const UPDATE_URL     = '{{ route('scoring.update-score') }}';
    const IS_HOST        = {{ $isHost ? 'true' : 'false' }};
    const RECAP_URL      = '{{ route('scoring.recap', $game['id']) }}';
    const STOP_MATCH_URL = '{{ route('scoring.stop-match') }}';
    const TOTAL_PLAYERS_COUNT = {{ count($game['participants'] ?? ($session->players ?? [])) }};
    const IS_SINGLE_MODE      = {{ strtolower($game['jenis_permainan'] ?? ($session->jenis_permainan ?? 'Double')) === 'single' ? 'true' : 'false' }};
    const MIN_REQUIRED_PLAYERS = IS_SINGLE_MODE ? 2 : 4;
    const SESSION_PARTICIPANTS = @json($game['participants'] ?? []);
    const INITIAL_LIVE_BENCH = @json($visibleRoundBench);
    let sessionPausedPlayerIds = new Set(@json($pausedPlayerIds ?? []).map(Number));
    let sessionWithdrawnPlayerIds = new Set(@json($withdrawnPlayerIds ?? []).map(Number));
    let sessionPlayerReasons = {};

    function escapeLiveStatusText(value) {
        return String(value ?? '').replace(/[&<>"']/g, character => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        })[character]);
    }

    function cleanPlayerName(name) {
        if (!name) return '';
        if (typeof name === 'object') name = name.name || name.nama || '';
        return String(name).trim().toLowerCase();
    }

    function getSessionPlayerById(playerId) {
        return SESSION_PARTICIPANTS.find(player => Number(player.id || player.player_id) === Number(playerId));
    }

    function renderSessionPlayerReadiness() {
        const activeMembers = SESSION_PARTICIPANTS.filter(player => {
            const playerId = Number(player.id || player.player_id);
            return !sessionPausedPlayerIds.has(playerId) && !sessionWithdrawnPlayerIds.has(playerId);
        });
        const restingMembers = SESSION_PARTICIPANTS.filter(player => sessionPausedPlayerIds.has(Number(player.id || player.player_id)));
        const withdrawnMembers = SESSION_PARTICIPANTS.filter(player => sessionWithdrawnPlayerIds.has(Number(player.id || player.player_id)));

        const activeCount = document.getElementById('readinessActiveCount');
        const restingCount = document.getElementById('readinessRestingCount');
        const withdrawnCount = document.getElementById('readinessWithdrawnCount');
        const warning = document.getElementById('readinessWarning');
        const warningText = document.getElementById('readinessWarningText');
        const roster = document.getElementById('sessionReadinessRoster');
        if (activeCount) activeCount.textContent = `${activeMembers.length} Siap / Aktif`;
        if (restingCount) {
            restingCount.textContent = `${restingMembers.length} Istirahat`;
            restingCount.classList.toggle('hidden', restingMembers.length === 0);
        }
        if (withdrawnCount) {
            withdrawnCount.textContent = `${withdrawnMembers.length} Keluar`;
            withdrawnCount.classList.toggle('hidden', withdrawnMembers.length === 0);
        }
        if (warning && warningText) {
            const isShort = activeMembers.length < MIN_REQUIRED_PLAYERS;
            warning.classList.toggle('hidden', !isShort);
            warning.classList.toggle('flex', isShort);
            warningText.textContent = `Tersisa ${activeMembers.length} pemain (min. ${MIN_REQUIRED_PLAYERS}). Aktifkan pemain istirahat.`;
        }
        if (!roster) return;

        const statusMembers = [
            ...activeMembers.map(player => ({ player, status: 'active' })),
            ...withdrawnMembers.map(player => ({ player, status: 'withdrawn' })),
        ];
        roster.innerHTML = statusMembers.map(({ player, status }) => {
            const playerId = Number(player.id || player.player_id);
            const playerName = escapeLiveStatusText(player.name || player.nama || 'Pemain');
            const reason = escapeLiveStatusText(sessionPlayerReasons[playerId] || '');
            if (status === 'resting') {
                return `<span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-amber-50 text-slate-800 border border-amber-300 text-xs font-semibold shadow-2xs" title="${reason}"><span class="w-1.5 h-1.5 rounded-full bg-amber-600 shrink-0"></span><span class="font-bold">${playerName}</span><span class="text-[9px] font-bold text-amber-800 bg-amber-100 px-1.5 py-0.5 rounded border border-amber-300">Istirahat</span>${reason ? `<span class="text-[10px] text-amber-900">${reason}</span>` : ''}${IS_HOST ? `<button type="button" data-reactivate-player="${playerId}" data-player-name="${playerName}" class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-[9px] shadow-2xs cursor-pointer ml-1 transition-all active:scale-95"><i class="fa-solid fa-play text-[7px]"></i> Siap Main</button>` : ''}</span>`;
            }
            if (status === 'withdrawn') {
                return `<span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-rose-50 text-slate-800 border border-rose-300 text-xs font-medium shadow-2xs opacity-80" title="${reason}"><span class="w-2 h-2 rounded-full bg-rose-500 shrink-0"></span><span class="line-through text-slate-500">${playerName}</span><span class="text-[9px] font-bold text-rose-800 bg-rose-100 px-1.5 py-0.5 rounded border border-rose-300">Keluar</span>${reason ? `<span class="text-[10px] text-rose-900">${reason}</span>` : ''}</span>`;
            }
            return `<span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-white text-slate-700 border border-slate-200 text-xs font-medium shadow-2xs"><span class="w-2 h-2 rounded-full bg-emerald-500 shrink-0"></span><span>${playerName}</span><span class="text-[9px] font-semibold text-emerald-700 bg-emerald-50 px-1.5 py-0.5 rounded border border-emerald-200">Siap</span></span>`;
        }).join('');

        roster.querySelectorAll('[data-reactivate-player]').forEach(button => {
            button.addEventListener('click', () => reactivatePlayerLive(Number(button.dataset.reactivatePlayer), button.dataset.playerName || 'Pemain'));
        });
    }

    function renderLiveRoundBench() {
        const benchPanel = document.getElementById('liveRoundBenchPanel');
        const benchList = document.getElementById('liveRoundBenchList');
        const benchCount = document.getElementById('liveRoundBenchCount');
        if (!benchPanel || !benchList || !benchCount) return;

        const benchMembers = new Map();
        INITIAL_LIVE_BENCH.forEach(entry => {
            const playerName = typeof entry === 'object' && entry !== null ? (entry.name || entry.nama || '') : String(entry);
            const playerId = typeof entry === 'object' && entry !== null ? (entry.id || entry.player_id) : null;
            const player = playerId ? getSessionPlayerById(playerId) : SESSION_PARTICIPANTS.find(item => cleanPlayerName(item.name || item.nama || '') === cleanPlayerName(playerName));
            if (player) benchMembers.set(Number(player.id || player.player_id), player);
        });

        // Sertakan juga seluruh pemain yang sedang istirahat sementara
        sessionPausedPlayerIds.forEach(playerId => {
            const player = getSessionPlayerById(playerId);
            if (player && !sessionWithdrawnPlayerIds.has(Number(playerId))) {
                benchMembers.set(Number(player.id || player.player_id), player);
            }
        });

        sessionWithdrawnPlayerIds.forEach(playerId => benchMembers.delete(Number(playerId)));

        const players = [...benchMembers.values()];
        benchCount.textContent = `${players.length} Pemain`;
        benchPanel.classList.toggle('hidden', players.length === 0);
        if (players.length === 0) {
            benchList.innerHTML = '<span class="text-xs text-slate-400">Semua pemain aktif bertanding di ronde ini.</span>';
            return;
        }

        benchList.innerHTML = players.map(player => {
            const playerId = Number(player.id || player.player_id);
            const playerName = escapeLiveStatusText(player.name || player.nama || 'Pemain');
            const isPaused = sessionPausedPlayerIds.has(playerId);
            const reason = escapeLiveStatusText(sessionPlayerReasons[playerId] || '');
            return `<span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl ${isPaused ? 'bg-amber-50 text-slate-800 border border-amber-300' : 'bg-white text-slate-700 border border-slate-200'} text-xs font-medium shadow-2xs" title="${reason}"><span class="w-1.5 h-1.5 rounded-full ${isPaused ? 'bg-amber-600' : 'bg-amber-500'} shrink-0"></span>${playerName}${isPaused ? `<span class="text-[9px] font-bold text-amber-800 bg-amber-100 px-1.5 py-0.5 rounded border border-amber-300">Istirahat</span>${reason ? `<span class="text-[10px] text-amber-900">${reason}</span>` : ''}${IS_HOST ? `<button type="button" data-reactivate-player="${playerId}" data-player-name="${playerName}" class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-[9px] shadow-2xs cursor-pointer ml-1 transition-all active:scale-95"><i class="fa-solid fa-play text-[7px]"></i> Siap Main</button>` : ''}` : '<span class="text-[9px] font-bold text-amber-700 bg-amber-50 px-1.5 py-0.5 rounded border border-amber-200">Bench</span>'}</span>`;
        }).join('');

        benchList.querySelectorAll('[data-reactivate-player]').forEach(button => {
            button.addEventListener('click', () => reactivatePlayerLive(Number(button.dataset.reactivatePlayer), button.dataset.playerName || 'Pemain'));
        });
    }

    function syncSessionPlayerStatusFromStop(rawPayload) {
        const payload = rawPayload?.saved && typeof rawPayload.saved === 'object'
            ? { ...rawPayload, ...rawPayload.saved }
            : rawPayload;
        if (!payload || payload.status !== 'stopped') return;

        const isPause = ['temporary_pause', 'pause_players'].includes(payload.stop_type);
        const isWithdraw = ['withdraw', 'withdraw_players'].includes(payload.stop_type);
        if (!isPause && !isWithdraw) return;

        const playerIds = new Set((payload.withdrawn_player_ids || []).map(Number));
        (payload.withdrawn_player_names || []).forEach(name => {
            const player = SESSION_PARTICIPANTS.find(item => cleanPlayerName(item.name || item.nama || '') === cleanPlayerName(name));
            if (player) playerIds.add(Number(player.id || player.player_id));
        });
        const reasons = payload.player_reasons || {};

        playerIds.forEach(playerId => {
            if (isPause) {
                sessionWithdrawnPlayerIds.delete(playerId);
                sessionPausedPlayerIds.add(playerId);
            } else {
                sessionPausedPlayerIds.delete(playerId);
                sessionWithdrawnPlayerIds.add(playerId);
            }

            const player = getSessionPlayerById(playerId);
            const playerName = player?.name || player?.nama || '';
            const reason = reasons[playerId] || (playerName ? reasons[playerName] : null) || payload.stop_reason || '';
            if (reason) sessionPlayerReasons[playerId] = reason;
        });

        renderSessionPlayerReadiness();
        renderLiveRoundBench();
    }
    
    // OFFLINE QUEUE: Persist CLIENT_ID to prevent client_seq reset on refresh
    const lsClientIdKey  = `matcha_client_id_${GAME_ID}`;
    let CLIENT_ID        = localStorage.getItem(lsClientIdKey);
    if (!CLIENT_ID) {
        CLIENT_ID = 'cli_' + Math.random().toString(36).substring(2, 9) + '_' + Date.now();
        try { localStorage.setItem(lsClientIdKey, CLIENT_ID); } catch(e) {}
    }
    
    const SUPABASE_URL   = '{{ config('services.supabase.url') }}';
    const SUPABASE_KEY   = '{{ config('services.supabase.public_key') }}';

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
        // OFFLINE QUEUE: Load from localStorage only if Host; Purge if Player
        let savedQueue_{{ $mIdx }} = [];
        let savedSeq_{{ $mIdx }} = 0;
        if (IS_HOST) {
            try {
                const rawQueue = localStorage.getItem(`matcha_queue_${GAME_ID}_{{ $mKey }}`);
                if (rawQueue) savedQueue_{{ $mIdx }} = JSON.parse(rawQueue);
                const rawSeq = localStorage.getItem(`matcha_seq_${GAME_ID}_{{ $mKey }}`);
                if (rawSeq) savedSeq_{{ $mIdx }} = parseInt(rawSeq, 10);
            } catch(e) {}
        } else {
            try {
                localStorage.removeItem(`matcha_queue_${GAME_ID}_{{ $mKey }}`);
                localStorage.removeItem(`matcha_seq_${GAME_ID}_{{ $mKey }}`);
            } catch(e) {}
        }

        @php
            $mParticipants = [];
            $mTeamAPlayers = !empty($m['team_a_names']) ? $m['team_a_names'] : (!empty($m['teamA_names']) ? $m['teamA_names'] : ($m['team_a'] ?? []));
            $mTeamBPlayers = !empty($m['team_b_names']) ? $m['team_b_names'] : (!empty($m['teamB_names']) ? $m['teamB_names'] : ($m['team_b'] ?? []));
            foreach ([['team' => 'Tim A', 'list' => is_array($mTeamAPlayers) ? $mTeamAPlayers : [$mTeamAPlayers]], ['team' => 'Tim B', 'list' => is_array($mTeamBPlayers) ? $mTeamBPlayers : [$mTeamBPlayers]]] as $tGrp) {
                foreach ($tGrp['list'] as $pItem) {
                    $pName = is_array($pItem) ? ($pItem['name'] ?? $pItem['nama'] ?? '') : (is_object($pItem) ? ($pItem->name ?? $pItem->nama ?? '') : (string) $pItem);
                    $pId = is_array($pItem) ? ($pItem['id'] ?? $pItem['user_id'] ?? null) : (is_object($pItem) ? ($pItem->id ?? $pItem->user_id ?? null) : null);
                    if ($pId === null && !empty($game['participants'])) {
                        $cleanPName = \App\Services\Scoring\ScoringService::cleanPlayerName($pName);
                        foreach ($game['participants'] as $gp) {
                            if (\App\Services\Scoring\ScoringService::cleanPlayerName($gp['name'] ?? '') === $cleanPName) {
                                $pId = $gp['id'] ?? null;
                                break;
                            }
                        }
                    }
                    if ($pName) {
                        $mParticipants[] = [
                            'name' => $pName,
                            'id' => $pId,
                            'team' => $tGrp['team']
                        ];
                    }
                }
            }
            $isMStoppedInitial = (($mScore['status'] ?? '') === 'stopped');
            $isMCompletedInitial = (($mScore['status'] ?? '') === 'completed');
            $isMDoneInitial = $isMCompletedInitial || $isMStoppedInitial;
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
            matchDone: {{ $isMDoneInitial ? 'true' : 'false' }},
            isStopped: {{ $isMStoppedInitial ? 'true' : 'false' }},
            stopType: {!! json_encode($mScore['stop_type'] ?? null) !!},
            stopReason: {!! json_encode($mScore['stop_reason'] ?? null) !!},
            withdrawnPlayerNames: {!! json_encode($mScore['withdrawn_player_names'] ?? []) !!},
            playerReasons: {!! json_encode($mScore['player_reasons'] ?? []) !!},
            participants: {!! json_encode($mParticipants) !!},
            completionSavePending: false,
            completionSaveSucceeded: {{ $isMDoneInitial ? 'true' : 'false' }},
            winnerTeam: {!! json_encode($mScore['winner_team'] ?? null) !!},
            isFinishing: false,
            courtNum: {{ $mIdx + 1 }},
            courtName: {!! json_encode($m['court_name'] ?? ('Court ' . ($mIdx + 1))) !!},
            matchKey: '{{ $mKey }}',
            serverVersion: {{ (int) ($mScore['version'] ?? 0) }},
            localVersion: {{ (int) ($mScore['version'] ?? 0) }},
            clientSeq: savedSeq_{{ $mIdx }},
            pendingSaves: savedQueue_{{ $mIdx }}.length,
            lastLocalActionTime: 0,
            lastClickTime: 0,
            isGameSyncing: false,
            saveQueue: savedQueue_{{ $mIdx }},
            inFlightQueue: [],
            saveWorker: null,
            activeSaveController: null,
            activeSaveIsCompletion: false
        };
    @endforeach

    // OFFLINE QUEUE: Resume any pending offline saves immediately (Host only)
    if (IS_HOST) {
        setTimeout(() => {
            for (const [cIdx, st] of Object.entries(courtsState)) {
                if (st.saveQueue && st.saveQueue.length > 0) {
                    console.log(`[Offline Sync] Recovered ${st.saveQueue.length} pending events for court ${cIdx}. Triggering retry...`);
                    queueScoreSave(cIdx, null, null, null, 'retry_drain');
                }
            }
            recoverOrphanedQueues();
        }, 500);
    }

    function recoverOrphanedQueues() {
        if (!IS_HOST) return;
        const prefix = `matcha_queue_${GAME_ID}_`;
        const activeMatchKeys = Object.values(courtsState).map(st => st.matchKey);
        
        for (let i = 0; i < localStorage.length; i++) {
            const key = localStorage.key(i);
            if (key && key.startsWith(prefix)) {
                const matchKey = key.substring(prefix.length);
                if (!activeMatchKeys.includes(matchKey)) {
                    console.log(`[Offline Sync] Found orphaned queue for matchKey: ${matchKey}. Attempting to sync...`);
                    syncOrphanedQueue(matchKey);
                }
            }
        }
    }

    async function syncOrphanedQueue(matchKey) {
        const queueKey = `matcha_queue_${GAME_ID}_${matchKey}`;
        const seqKey = `matcha_seq_${GAME_ID}_${matchKey}`;
        let batchEvents = [];
        try {
            const raw = localStorage.getItem(queueKey);
            if (raw) batchEvents = JSON.parse(raw);
        } catch (e) {
            console.error('[Offline Sync] Failed to parse orphaned queue:', e);
            localStorage.removeItem(queueKey);
            return;
        }

        if (!batchEvents || batchEvents.length === 0) {
            localStorage.removeItem(queueKey);
            localStorage.removeItem(seqKey);
            return;
        }

        const firstEvent = batchEvents[0];
        const targetRound = firstEvent.roundKey || firstEvent.round || (matchKey.includes('_court_') ? matchKey.split('_court_')[0] : matchKey);
        const courtNum = firstEvent.courtNum || (matchKey.includes('_court_') ? parseInt(matchKey.split('_court_')[1], 10) : 1);
        const targetMatchId = firstEvent.matchId || null;
        let isCompletionSave = false;
        let lastClientSeq = 1;
        let lastBaseVersion = 0;
        let latestSnapshot = null;
        
        const backendEvents = batchEvents.map(e => {
            if (e.status === 'completed' || e.action === 'completion') {
                isCompletionSave = true;
            }
            lastClientSeq = Math.max(lastClientSeq, e.clientSeq || 1);
            lastBaseVersion = Math.max(lastBaseVersion, e.baseVersion || 0);
            if (e.snapshot) latestSnapshot = e.snapshot;
            return {
                action: e.action || 'add_point',
                team: e.team,
                event_id: e.eventId,
                tClick: e.tClick,
                status: e.status
            };
        });

        if (!latestSnapshot) {
            localStorage.removeItem(queueKey);
            localStorage.removeItem(seqKey);
            return;
        }

        const currentStatus = isCompletionSave ? 'completed' : 'in_progress';
        const isWalkoverSave = batchEvents.some(e => e.action === 'walkover');
        
        const body = {
            game_id         : GAME_ID,
            round           : targetRound,
            match_key       : matchKey,
            match_id        : targetMatchId,
            court           : courtNum,
            scoring_type    : SCORING_TYPE,
            action          : 'batch_events',
            events          : backendEvents,
            base_version    : lastBaseVersion,
            score_a         : latestSnapshot.gamesA,
            score_b         : latestSnapshot.gamesB,
            point_display_a : latestSnapshot.pointDisplays?.a || '0',
            point_display_b : latestSnapshot.pointDisplays?.b || '0',
            set_number      : 1,
            sets_a          : (latestSnapshot.gamesA >= latestSnapshot.gamesB && currentStatus === 'completed') ? 1 : 0,
            sets_b          : (latestSnapshot.gamesB > latestSnapshot.gamesA && currentStatus === 'completed') ? 1 : 0,
            games_a         : latestSnapshot.gamesA,
            games_b         : latestSnapshot.gamesB,
            set_history     : [{ set: 1, score_a: latestSnapshot.gamesA, score_b: latestSnapshot.gamesB }],
            idx_a           : latestSnapshot.idxA,
            idx_b           : latestSnapshot.idxB,
            is_deuce        : latestSnapshot.isDeuce,
            advantage       : latestSnapshot.advantage,
            winner_team     : (latestSnapshot.gamesA >= latestSnapshot.gamesB ? 'Team A' : 'Team B'),
            status          : currentStatus,
            client_id       : CLIENT_ID,
            client_version  : lastClientSeq,
            client_seq      : lastClientSeq,
            is_walkover     : isWalkoverSave,
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
                console.log(`[Offline Sync] Orphaned queue for ${matchKey} successfully synced!`);
                localStorage.removeItem(queueKey);
                localStorage.removeItem(seqKey);
            } else {
                console.warn(`[Offline Sync] Orphaned queue sync failed for ${matchKey}, will retry later.`);
            }
        } catch (e) {
            console.warn(`[Offline Sync] Orphaned queue network error for ${matchKey}, will retry later.`, e);
        }
    }

    // ── Network Quality & Offline Banner Controller ───────────────────────────
    let currentNetworkState = 'normal'; // 'normal', 'slow', 'offline', 'recovered'
    let recoveredHideTimeout = null;
    let consecutivePollFailures = 0;

    function getTotalPendingSaves() {
        let total = 0;
        for (const cIdx in courtsState) {
            const st = courtsState[cIdx];
            if (st) {
                total += (st.saveQueue?.length || 0) + (st.inFlightQueue?.length || 0);
            }
        }
        return total;
    }

    function updateNetworkStatusUI(status, details = {}) {
        const container = document.getElementById('networkStatusBannerContainer');
        if (!container) return;

        if (recoveredHideTimeout) {
            clearTimeout(recoveredHideTimeout);
            recoveredHideTimeout = null;
        }

        currentNetworkState = status;

        if (status === 'normal') {
            container.classList.add('hidden');
            container.innerHTML = '';
            return;
        }

        container.classList.remove('hidden');

        if (status === 'offline') {
            const pending = getTotalPendingSaves();
            const pendingBadge = pending > 0
                ? `<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-rose-200/80 text-rose-800 text-[10px] font-bold shrink-0 self-start sm:self-center">
                       <i class="fa-solid fa-rotate fa-spin text-[9px]"></i> ${pending} skor antre
                   </span>`
                : `<span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-lg bg-rose-100 text-rose-700 text-[10px] font-semibold shrink-0 self-start sm:self-center">
                       <i class="fa-solid fa-floppy-disk text-[10px]"></i> Tersimpan lokal
                   </span>`;

            container.innerHTML = `
                <div class="p-3.5 mb-3 rounded-2xl bg-rose-50 border border-rose-300 text-rose-900 flex items-start sm:items-center justify-between gap-3 shadow-xs transition-all duration-200">
                    <div class="min-w-0">
                        <div class="font-bold text-xs sm:text-sm text-rose-950 flex items-center gap-1.5 flex-wrap">
                            <span>Koneksi Terputus (Mode Offline)</span>
                            <span class="inline-block w-2 h-2 rounded-full bg-rose-500 animate-ping"></span>
                        </div>
                        <p class="text-[11px] sm:text-xs text-rose-700 mt-0.5 leading-snug">
                            Skor tetap dicatat di layar dan tersimpan aman di perangkat lokal Anda. Sistem akan otomatis menyinkronkan ke server segera setelah internet pulih.
                        </p>
                    </div>
                    ${pendingBadge}
                </div>
            `;
        } else if (status === 'slow') {
            const latencyMs = details.latencyMs || 0;
            const latencySec = latencyMs > 0 ? (latencyMs / 1000).toFixed(1) + 's' : '>2.5s';
            const latencyBadge = latencyMs > 0 ? `~${latencySec}` : 'Lambat';

            container.innerHTML = `
                <div class="p-3.5 mb-3 rounded-2xl bg-amber-50 border border-amber-300 text-amber-900 flex items-start sm:items-center justify-between gap-3 shadow-xs transition-all duration-200">
                    <div class="min-w-0">
                        <div class="font-bold text-xs sm:text-sm text-amber-950 flex items-center gap-1.5 flex-wrap">
                            <span>Koneksi Jaringan Lambat</span>
                            <span class="text-[11px] font-semibold text-amber-700">(${latencyBadge})</span>
                        </div>
                        <p class="text-[11px] sm:text-xs text-amber-800 mt-0.5 leading-snug">
                            Respon server memakan waktu ${latencySec}. Input skor Anda tetap berjalan lancar di layar dan antrean sedang disinkronkan ke server.
                        </p>
                    </div>
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-amber-200/80 text-amber-900 text-[10px] font-bold shrink-0 self-start sm:self-center">
                        <i class="fa-solid fa-gauge-high text-[10px]"></i> ${latencyBadge}
                    </span>
                </div>
            `;
        } else if (status === 'recovered') {
            container.innerHTML = `
                <div class="p-3.5 mb-3 rounded-2xl bg-emerald-50 border border-emerald-300 text-emerald-900 flex items-start sm:items-center justify-between gap-3 shadow-xs transition-all duration-200">
                    <div class="min-w-0">
                        <div class="font-bold text-xs sm:text-sm text-emerald-950 flex items-center gap-1.5 flex-wrap">
                            <span>Koneksi Normal Kembali</span>
                        </div>
                        <p class="text-[11px] sm:text-xs text-emerald-800 mt-0.5 leading-snug">
                            Jaringan internet kembali stabil dan seluruh skor tersinkronkan ke server secara aman.
                        </p>
                    </div>
                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-emerald-100 text-emerald-800 text-[10px] font-bold shrink-0 self-start sm:self-center">
                        <i class="fa-solid fa-check text-[10px]"></i> Terhubung
                    </span>
                </div>
            `;

            recoveredHideTimeout = setTimeout(() => {
                if (currentNetworkState === 'recovered') {
                    updateNetworkStatusUI('normal');
                }
            }, 3500);
        }
    }

    // Initial check for offline state on load
    if (typeof navigator !== 'undefined' && !navigator.onLine) {
        updateNetworkStatusUI('offline');
    }

    // ── Court Tab Switching ──────────────────────────────────────────────────
    function switchActiveCourtTab(courtIdx) {
        // 1. Update tab button styles
        document.querySelectorAll('.court-tab-btn').forEach((btn, idx) => {
            const miniBadge = document.getElementById(`tabMiniBadge_${idx}`);
            const isDone = miniBadge && miniBadge.textContent.includes('Selesai');
            const icon = btn.querySelector('i');
            if (idx === courtIdx) {
                btn.className = 'court-tab-btn px-4 py-2.5 rounded-xl text-xs font-bold border transition-all flex items-center gap-2 shadow-xs ring-2 ring-[#063B00]/20 bg-[#063B00] text-white border-[#063B00] cursor-pointer hover:scale-[1.01] active:scale-95';
                if (icon) icon.className = 'fa-solid fa-table-tennis-paddle-ball text-[10px] text-[#A8E63A]';
                if (miniBadge) {
                    miniBadge.className = `text-[10px] px-2 py-0.5 rounded-md font-extrabold ${isDone ? 'bg-white/20 text-[#A8E63A]' : 'bg-black/20 text-white'}`;
                }
            } else {
                btn.className = 'court-tab-btn px-4 py-2.5 rounded-xl text-xs font-bold border transition-all flex items-center gap-2 shadow-2xs bg-white text-slate-700 border-slate-200 hover:border-[#063B00]/50 hover:bg-slate-50 cursor-pointer hover:scale-[1.01] active:scale-95';
                if (icon) icon.className = 'fa-solid fa-table-tennis-paddle-ball text-[10px] text-slate-400';
                if (miniBadge) {
                    miniBadge.className = `text-[10px] px-2 py-0.5 rounded-md font-extrabold ${isDone ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-slate-100 text-slate-600 border border-slate-200'}`;
                }
            }
        });

        // 2. Show selected board container, hide others
        document.querySelectorAll('.court-board-pane').forEach((pane, idx) => {
            if (idx === courtIdx) {
                pane.classList.remove('hidden');
            } else {
                pane.classList.add('hidden');
            }
        });

        // 3. Update hidden input on nextRound form
        const courtInput = document.querySelector('form#globalNextRoundForm input[name="court"]');
        if (courtInput) {
            courtInput.value = courtIdx;
        }

        // 4. Update browser URL search param without full reload
        try {
            const url = new URL(window.location.href);
            url.searchParams.set('court', courtIdx);
            window.history.replaceState({}, '', url.toString());
        } catch(e) {}
    }

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

    // ── Tambah Poin (Optimistic UI: 0ms render, 300ms micro-debounce, async save ke server) ───────────
    function addPoint(team, cIdx) {
        let st = courtsState[cIdx];
        if (st.matchDone) {
            showToast('Skor pertandingan ini sudah selesai dan terkunci.');
            return;
        }
        if (st.isGameSyncing) {
            showToast(`Sedang menyinkronkan Game ${st.gamesA + st.gamesB}...`);
            return;
        }

        // Micro-Debounce (300ms): Mencegah double tap pada layar sentuh
        const now = performance.now();
        if (st.lastClickTime && (now - st.lastClickTime < 300)) {
            console.log(`[Micro-Debounce] 300ms throttle on court ${st.courtNum}`);
            return;
        }
        st.lastClickTime = now;

        const tClick = performance.now();
        st.lastLocalActionTime = Date.now();
        st.localVersion = (st.localVersion || 0) + 1;
        st.clientSeq = (st.clientSeq || 0) + 1; // Increment SATU KALI per aksi
        const clientSeq = st.clientSeq;
        const baseVersion = st.serverVersion || 0;
        const eventId = 'evt_' + CLIENT_ID + '_' + Date.now() + '_' + Math.random().toString(36).substring(2, 8);

        // 1. Mutasi state lokal terlebih dahulu (Tennis point ladder 0 -> 15 -> 30 -> 40 -> Game)
        if (team === 'A') {
            handlePointWonByA(cIdx, clientSeq, tClick, baseVersion, eventId);
        } else {
            handlePointWonByB(cIdx, clientSeq, tClick, baseVersion, eventId);
        }

        // 2. Simpan ke antrean DENGAN snapshot state yang sudah ter-update
        const isCompleted = st.matchDone;
        queueScoreSave(cIdx, isCompleted ? 'completed' : null, clientSeq, tClick, eventId, isCompleted ? 'completion' : 'add_point', team, baseVersion);

        // 3. Optimistic UI update seketika (0ms render time)
        updateDisplay(cIdx);
        const tUiDone = performance.now();
        console.log(`[Optimistic UI] Court ${st.courtNum} +1 ${team} rendered in ${(tUiDone - tClick).toFixed(2)}ms (clientSeq=${clientSeq}, baseVersion=${baseVersion}, eventId=${eventId})`);
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
                showToast('Advantage Tim A!');
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
                showToast('Advantage Tim B!');
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

    // ── Game Dimenangkan (Sinkronisasi Skor Besar) ──────────────────────────
    function gameWonBy(team, cIdx, clientSeq, tClick, baseVersion, eventId) {
        resetPoints(cIdx);
        let st = courtsState[cIdx];
        if (team === 'A') {
            st.gamesA++;
        } else {
            st.gamesB++;
        }
        
        checkSetWinner(cIdx, clientSeq, tClick, baseVersion, team, eventId);

        if (!st.matchDone) {
            st.isGameSyncing = true;
            const displayTeam = team === 'A' ? 'Tim A' : 'Tim B';
            showToast(`🎉 Game Won by ${displayTeam}! Menyinkronkan data...`);
            // Safety timeout agar lock tidak pernah macet jika offline
            setTimeout(() => {
                if (st.isGameSyncing && !st.matchDone) {
                    st.isGameSyncing = false;
                    updateDisplay(cIdx);
                }
            }, 1200);
        } else {
            const displayTeam = team === 'A' ? 'Tim A' : 'Tim B';
            showToast(`🎉 Set Won by ${displayTeam}!`);
        }
    }

    function determineWinner(gamesA, gamesB) {
        if (IS_TOTAL_OF) {
            if (!isSetFinished(gamesA, gamesB)) {
                return null;
            }
            if (gamesA > gamesB) return 'Team A';
            if (gamesB > gamesA) return 'Team B';
            return null; // Draw / Seri (tanpa pemenang tunggal)
        } else {
            if (TARGET_GAMES > 0) {
                if (gamesA >= TARGET_GAMES && gamesA > gamesB) return 'Team A';
                if (gamesB >= TARGET_GAMES && gamesB > gamesA) return 'Team B';
                if (gamesA >= TARGET_GAMES && gamesB >= TARGET_GAMES) return gamesA >= gamesB ? 'Team A' : 'Team B';
            }
            return null;
        }
    }

    function isSetFinished(gamesA, gamesB) {
        if (IS_TOTAL_OF) {
            // Normal: sisi pertama mencapai 6 game menang, selama lawan maksimal 4
            if ((gamesA === 6 && gamesB <= 4) || (gamesB === 6 && gamesA <= 4)) {
                return true;
            }
            // Jika mencapai 5–5, lanjut hingga salah satu sisi mencapai 7 (7–5 atau 7–6)
            if ((gamesA === 7 && (gamesB === 5 || gamesB === 6)) || (gamesB === 7 && (gamesA === 5 || gamesA === 6))) {
                return true;
            }
            // Toleransi jika skor terisi > 7
            if ((gamesA > 7 && gamesA > gamesB) || (gamesB > 7 && gamesB > gamesA)) {
                return true;
            }
            return false;
        }
        if (TARGET_GAMES > 0) {
            return (gamesA >= TARGET_GAMES || gamesB >= TARGET_GAMES);
        }
        return false;
    }

    function checkSetWinner(cIdx, clientSeq = null, tClick = null, baseVersion = 0, teamWon = null, eventId = null) {
        let st = courtsState[cIdx];
        let isFinished = isSetFinished(st.gamesA, st.gamesB);

        if (isFinished) {
            let setWon = determineWinner(st.gamesA, st.gamesB);
            st.matchDone = true;
            st.completionSavePending = true;
            st.completionSaveSucceeded = false;
            st.winnerTeam = setWon;
            st.setsA = (setWon === 'Team A') ? 1 : 0;
            st.setsB = (setWon === 'Team B') ? 1 : 0;
            updateDisplay(cIdx);
            syncRoundCompletionStatus();

            const tClickAuto = performance.now();
            st.lastLocalActionTime = Date.now();
            st.localVersion = (st.localVersion || 0) + 1;
            st.clientSeq = (st.clientSeq || 0) + 1;
            const clientSeqAuto = st.clientSeq;
            const baseVersionAuto = st.serverVersion || 0;
            const compEventId = 'evt_auto_' + CLIENT_ID + '_' + Date.now() + '_' + Math.random().toString(36).substring(2, 8);
            
            queueScoreSave(cIdx, 'completed', clientSeqAuto, tClickAuto, compEventId, 'auto_completion', (setWon === 'Team A' ? 'A' : (setWon === 'Team B' ? 'B' : null)), baseVersionAuto);
        }
    }

    function manualCompleteSet(cIdx) {
        let st = courtsState[cIdx];
        if (st.matchDone) return;
        
        const isFinished = isSetFinished(st.gamesA, st.gamesB);
        if (!isFinished) {
            if (IS_TOTAL_OF) {
                showToast(`Target set belum tercapai (skor saat ini: ${st.gamesA}–${st.gamesB}). Target 6 game; jika 5–5, lanjut sampai 7.`);
            } else {
                showToast(`Target ${TARGET_GAMES} Games belum dicapai.`);
            }
            return;
        }
        const courtLabel = st.courtName || ('Court ' + st.courtNum);
        const conf = confirm(`Apakah Anda yakin ingin menyelesaikan dan mengunci skor pada ${courtLabel}?`);
        if (!conf) return;

        const setWon = determineWinner(st.gamesA, st.gamesB);
        const tClick = performance.now();
        st.lastLocalActionTime = Date.now();
        st.localVersion = (st.localVersion || 0) + 1;
        st.clientSeq = (st.clientSeq || 0) + 1;
        const clientSeq = st.clientSeq;
        const baseVersion = st.serverVersion || 0;

        st.matchDone = true;
        st.completionSavePending = true;
        st.completionSaveSucceeded = false;
        st.winnerTeam = setWon;
        st.setsA = (setWon === 'Team A') ? 1 : 0;
        st.setsB = (setWon === 'Team B') ? 1 : 0;
        updateDisplay(cIdx);
        syncRoundCompletionStatus();

        const compEventId = 'evt_manual_' + CLIENT_ID + '_' + Date.now() + '_' + Math.random().toString(36).substring(2, 8);
        queueScoreSave(cIdx, 'completed', clientSeq, tClick, compEventId, 'manual_completion', (setWon === 'Team A' ? 'A' : (setWon === 'Team B' ? 'B' : null)), baseVersion);
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
        if (curBadge) {
            curBadge.innerText = '';
        }
        if (subA) {
            subA.innerText = `Games Won: ${st.gamesA} Game`;
        }
        if (subB) {
            subB.innerText = `Games Won: ${st.gamesB} Game`;
        }

        if (setLbl) {
            setLbl.innerHTML = `Status: <strong class="text-white">${st.isStopped ? (UNIT_TAB_LABEL + ' Dihentikan') : (st.matchDone ? (UNIT_TAB_LABEL + ' Selesai & Terkunci') : 'Sedang Berlangsung')}</strong>`;
        }

        if (notice) {
            if (st.isStopped) {
                notice.classList.add('hidden');
            } else if (st.matchDone) {
                notice.classList.remove('hidden');
                notice.innerHTML = `🔒 <strong>${UNIT_TAB_LABEL} ${ACTIVE_ROUND_NUM} Selesai & Terkunci</strong> &bull; Skor: <strong>${st.gamesA} — ${st.gamesB} Games</strong> (${st.winnerTeam || 'Selesai'})`;
            } else if (st.isDeuce) {
                notice.classList.remove('hidden');
                if (st.advantage === 'A') {
                    notice.innerHTML = '<strong class="text-[#063B00]">ADVANTAGE TEAM A</strong> &bull; Butuh 1 poin lagi untuk memenangkan game';
                } else if (st.advantage === 'B') {
                    notice.innerHTML = '<strong class="text-slate-900">ADVANTAGE TEAM B</strong> &bull; Butuh 1 poin lagi untuk memenangkan game';
                } else {
                    notice.innerHTML = '<strong class="text-amber-700">DEUCE (40 - 40)</strong> &bull; Perebutan advantage point';
                }
            } else {
                notice.classList.add('hidden');
                notice.innerHTML = '';
            }
        }

        const bA = document.getElementById('btnAddA_' + cIdx);
        const bB = document.getElementById('btnAddB_' + cIdx);
        const btnStop = document.getElementById('btnStopMatch_' + cIdx);
        const stoppedBadge = document.getElementById('stoppedBadge_' + cIdx);
        const matchStoppedBanner = document.getElementById('matchStoppedBanner_' + cIdx);

        if (st.isStopped) {
            showStoppedBanner(cIdx);
        } else if (st.matchDone) {
            if (stoppedBadge) stoppedBadge.classList.add('hidden');
            if (matchStoppedBanner) matchStoppedBanner.classList.add('hidden');
            if (btnStop) btnStop.classList.add('hidden');
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
        } else if (st.isGameSyncing) {
            if (stoppedBadge) stoppedBadge.classList.add('hidden');
            if (matchStoppedBanner) matchStoppedBanner.classList.add('hidden');
            if (btnStop) btnStop.classList.add('hidden');
            if (lockedBadge) {
                lockedBadge.classList.add('hidden');
            }
            if (btnManual) {
                btnManual.classList.add('hidden');
            }
            const banner = document.getElementById('matchCompletedBanner_' + cIdx);
            if (banner) {
                banner.classList.add('hidden');
            }
            if (bA) {
                bA.disabled = true;
                bA.className = 'w-full py-3.5 rounded-xl bg-amber-500/20 text-amber-900 font-bold text-sm border border-amber-300 cursor-wait flex items-center justify-center gap-2 transition-all';
                bA.innerHTML = `<i class="fa-solid fa-spinner fa-spin text-xs text-amber-700"></i> Menyinkronkan ${IS_SETS ? 'Game ' + (st.gamesA + st.gamesB) : 'Poin'}...`;
            }
            if (bB) {
                bB.disabled = true;
                bB.className = 'w-full py-3.5 rounded-xl bg-amber-500/20 text-amber-900 font-bold text-sm border border-amber-300 cursor-wait flex items-center justify-center gap-2 transition-all';
                bB.innerHTML = `<i class="fa-solid fa-spinner fa-spin text-xs text-amber-700"></i> Menyinkronkan ${IS_SETS ? 'Game ' + (st.gamesA + st.gamesB) : 'Poin'}...`;
            }
        } else {
            if (stoppedBadge) stoppedBadge.classList.add('hidden');
            if (matchStoppedBanner) matchStoppedBanner.classList.add('hidden');
            if (btnStop) btnStop.classList.remove('hidden');
            if (lockedBadge) {
                lockedBadge.classList.add('hidden');
            }
            
            const isTargetReached = isSetFinished(st.gamesA, st.gamesB);
            if (btnManual) {
                btnManual.classList.toggle('hidden', !isTargetReached);
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

        // Update Court Tab Mini Badge if exists
        const tabMiniBadge = document.getElementById('tabMiniBadge_' + cIdx);
        if (tabMiniBadge) {
            const isTabActive = !document.getElementById('courtBoardContainer_' + cIdx)?.classList.contains('hidden');
            if (st.isStopped) {
                tabMiniBadge.innerText = `⚠️ Dihentikan (${st.gamesA} - ${st.gamesB})`;
                tabMiniBadge.className = `text-[10px] px-2 py-0.5 rounded-md font-extrabold ${isTabActive ? 'bg-amber-500 text-white' : 'bg-amber-50 text-amber-900 border border-amber-300'}`;
            } else if (st.matchDone) {
                tabMiniBadge.innerText = `✓ Selesai (${st.gamesA} - ${st.gamesB})`;
                tabMiniBadge.className = `text-[10px] px-2 py-0.5 rounded-md font-extrabold ${isTabActive ? 'bg-white/20 text-[#A8E63A]' : 'bg-emerald-50 text-emerald-700 border border-emerald-200'}`;
            } else {
                tabMiniBadge.innerText = `${st.gamesA} - ${st.gamesB}`;
                tabMiniBadge.className = `text-[10px] px-2 py-0.5 rounded-md font-extrabold ${isTabActive ? 'bg-black/20 text-white' : 'bg-slate-100 text-slate-600 border border-slate-200'}`;
            }
        }

        syncRoundCompletionStatus();
    }

    function showStoppedBanner(cIdx) {
        let st = courtsState[cIdx];
        try {
            if (st && st.matchKey) {
                localStorage.removeItem('matcha_queue_' + GAME_ID + '_' + st.matchKey);
                localStorage.removeItem('matcha_seq_' + GAME_ID + '_' + st.matchKey);
            }
        } catch(e) {}
        
        const compBanner = document.getElementById('matchCompletedBanner_' + cIdx);
        if (compBanner) compBanner.classList.add('hidden');

        const banner = document.getElementById('matchStoppedBanner_' + cIdx);
        const msg = document.getElementById('stoppedMsg_' + cIdx);
        const subMsg = document.getElementById('stoppedSubMsg_' + cIdx);
        const reasonText = document.getElementById('stoppedReasonText_' + cIdx);
        const courtLabel = st.courtName || ('Court ' + st.courtNum);
        const scoreUnit = IS_SETS ? 'Games' : 'Poin';

        if (msg) msg.textContent = `${courtLabel} Dihentikan Lebih Awal pada ${UNIT_TAB_LABEL} ${ACTIVE_ROUND_NUM}`;
        if (reasonText) reasonText.textContent = st.stopReason || 'Dihentikan oleh Host';
        if (subMsg) subMsg.innerHTML = `Alasan: <strong>${st.stopReason || 'Dihentikan oleh Host'}</strong>`;
        
        const withdrawnNotice = document.getElementById('stoppedWithdrawnNotice_' + cIdx);
        if (withdrawnNotice) {
            if (st.withdrawnPlayerNames && st.withdrawnPlayerNames.length > 0) {
                const isBenchStop = ['temporary_pause', 'pause_players'].includes(st.stopType);
                const playerDetails = st.withdrawnPlayerNames.map(playerName => {
                    const participant = (st.participants || []).find(item => cleanPlayerName(item.name || '') === cleanPlayerName(playerName));
                    const playerReason = (participant && st.playerReasons?.[participant.id])
                        || st.playerReasons?.[playerName]
                        || st.stopReason
                        || 'Alasan tidak dicatat';

                    return `${playerName}: ${playerReason}`;
                });
                withdrawnNotice.textContent = `${isBenchStop ? 'Pemain di Bench' : 'Pemain Keluar'}: ${playerDetails.join('; ')}`;
                withdrawnNotice.classList.remove('hidden');
            } else {
                withdrawnNotice.classList.add('hidden');
            }
        }

        if (banner) banner.classList.remove('hidden');

        const notice = document.getElementById('matchNotice_' + cIdx);
        if (notice) notice.classList.add('hidden');

        const bA = document.getElementById('btnAddA_' + cIdx);
        const bB = document.getElementById('btnAddB_' + cIdx);
        if (bA) {
            bA.disabled = true;
            bA.className = 'w-full py-3.5 rounded-xl bg-slate-100 text-slate-400 font-bold text-sm border border-slate-200 cursor-not-allowed flex items-center justify-center gap-2';
            bA.innerHTML = `<i class="fa-solid fa-ban text-xs"></i> Pertandingan Dihentikan`;
        }
        if (bB) {
            bB.disabled = true;
            bB.className = 'w-full py-3.5 rounded-xl bg-slate-100 text-slate-400 font-bold text-sm border border-slate-200 cursor-not-allowed flex items-center justify-center gap-2';
            bB.innerHTML = `<i class="fa-solid fa-ban text-xs"></i> Pertandingan Dihentikan`;
        }

        const lockedBadge = document.getElementById('lockedBadge_' + cIdx);
        if (lockedBadge) lockedBadge.classList.add('hidden');

        const stoppedBadge = document.getElementById('stoppedBadge_' + cIdx);
        const stoppedBadgeText = document.getElementById('stoppedBadgeText_' + cIdx);
        if (stoppedBadge) {
            stoppedBadge.classList.remove('hidden');
            if (stoppedBadgeText) {
                stoppedBadgeText.textContent = `Pertandingan pada ${courtLabel} telah dihentikan oleh Host.`;
            }
        }

        const btnManual = document.getElementById('btnManualComplete_' + cIdx);
        if (btnManual) btnManual.classList.add('hidden');

        const btnStop = document.getElementById('btnStopMatch_' + cIdx);
        if (btnStop) btnStop.classList.add('hidden');
    }

    function showCompletedBanner(winner, cIdx) {
        let st = courtsState[cIdx];
        try {
            if (st && st.matchKey) {
                localStorage.removeItem('matcha_queue_' + GAME_ID + '_' + st.matchKey);
                localStorage.removeItem('matcha_seq_' + GAME_ID + '_' + st.matchKey);
            }
        } catch(e) {}
        const banner = document.getElementById('matchCompletedBanner_' + cIdx);
        const msg    = document.getElementById('completedMsg_' + cIdx);
        const subMsg = document.getElementById('completedSubMsg_' + cIdx);
        const courtLabel = st.courtName || ('Court ' + st.courtNum);
        const scoreUnit = IS_SETS ? 'Games' : 'Poin';

        const winnerLabel = winner ? (winner === 'Team A' ? 'Tim A Menang' : (winner === 'Team B' ? 'Tim B Menang' : winner)) : 'Seri';
        if (msg) msg.textContent = `🏆 ${courtLabel} telah selesai pada ${UNIT_TAB_LABEL} ${ACTIVE_ROUND_NUM}!`;
        if (subMsg) subMsg.innerHTML = `Skor Akhir: <strong>${st.gamesA} &mdash; ${st.gamesB} ${scoreUnit}</strong> (${winnerLabel}) &bull; Poin telah dicatat ke klasemen.`;
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

        // Update all Court Tab Mini Badges
        courtKeys.forEach(k => {
            const tabMiniBadge = document.getElementById('tabMiniBadge_' + k);
            if (tabMiniBadge) {
                const isTabActive = !document.getElementById('courtBoardContainer_' + k)?.classList.contains('hidden');
                const stK = courtsState[k];
                if (stK.isStopped) {
                    tabMiniBadge.innerText = `⚠️ Dihentikan (${stK.gamesA} - ${stK.gamesB})`;
                    tabMiniBadge.className = `text-[10px] px-2 py-0.5 rounded-md font-extrabold ${isTabActive ? 'bg-amber-500 text-white' : 'bg-amber-50 text-amber-900 border border-amber-300'}`;
                } else if (stK.matchDone) {
                    tabMiniBadge.innerText = `✓ Selesai (${stK.gamesA} - ${stK.gamesB})`;
                    tabMiniBadge.className = `text-[10px] px-2 py-0.5 rounded-md font-extrabold ${isTabActive ? 'bg-white/20 text-[#A8E63A]' : 'bg-emerald-50 text-emerald-700 border border-emerald-200'}`;
                } else {
                    tabMiniBadge.innerText = `${stK.gamesA} - ${stK.gamesB}`;
                    tabMiniBadge.className = `text-[10px] px-2 py-0.5 rounded-md font-extrabold ${isTabActive ? 'bg-black/20 text-white' : 'bg-slate-100 text-slate-600 border border-slate-200'}`;
                }
            }
        });

        // Update Global Session Control Bar
        const globalTitle = document.getElementById('globalRoundStatusTitle');
        const globalDesc  = document.getElementById('globalRoundStatusDesc');
        const globalDot   = document.getElementById('globalRoundStatusDot');
        const formNext    = document.getElementById('globalNextRoundForm');
        const btnNext     = document.getElementById('btnGlobalNextRound');
        const formFinish  = document.getElementById('globalFinishForm');
        const btnRecap    = document.getElementById('btnGlobalRecap');
        const withdrawnPlayerCount = SESSION_PARTICIPANTS.filter(player => (
            sessionWithdrawnPlayerIds.has(Number(player.id || player.player_id))
        )).length;
        const canFinishAfterWithdrawal = withdrawnPlayerCount > 0 &&
            SESSION_PARTICIPANTS.length - withdrawnPlayerCount < MIN_REQUIRED_PLAYERS;

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
                    globalDesc.textContent = canFinishAfterWithdrawal
                        ? 'Pemain aktif tidak mencukupi untuk ronde berikutnya. Selesaikan sesi untuk melihat hasil akhir.'
                        : `Semua court telah mencatat skor akhir. Silakan lanjut ke ${formNext ? UNIT_TAB_LABEL.toLowerCase() + ' berikutnya' : 'hasil akhir & podium'}.`;
                } else {
                    globalDesc.textContent = `Semua court telah selesai. Menunggu Host ${formNext ? 'memulai ' + UNIT_TAB_LABEL.toLowerCase() + ' berikutnya' : 'menyelesaikan sesi'}...`;
                }
            }
            if (IS_HOST) {
                if (formNext) formNext.classList.toggle('hidden', canFinishAfterWithdrawal);
                if (formFinish) formFinish.classList.toggle('hidden', !!formNext && !canFinishAfterWithdrawal);
                if (btnRecap) btnRecap.classList.add('hidden');
            } else {
                if (formNext) formNext.classList.add('hidden');
                if (formFinish) formFinish.classList.add('hidden');
                // Non-host: Tombol recap hanya muncul jika sesi telah resmi diselesaikan oleh Host via event / session finished
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

        // HOST PROTECTION: Jika Host sedang memiliki antrean klik atau baru saja beraksi, tahan perubahan UI
        if (IS_HOST) {
            const hasPending = (
                (st.saveQueue && st.saveQueue.length > 0) ||
                (st.inFlightQueue && st.inFlightQueue.length > 0) ||
                (st.pendingSaves || 0) > 0 ||
                (st.lastLocalActionTime && (Date.now() - st.lastLocalActionTime < 1500))
            );
            if (hasPending) {
                if (incomingVer > (st.serverVersion || 0)) {
                    st.serverVersion = incomingVer;
                }
                return;
            }
        }

        const newIdxA = data.idx_a ?? 0;
        const newIdxB = data.idx_b ?? 0;
        const newIsDeuce = !!data.is_deuce;
        const newAdv = data.advantage ?? null;
        const newGamesA = data.games_a ?? (data.score_a ?? 0);
        const newGamesB = data.games_b ?? (data.score_b ?? 0);
        const newSetsA = data.sets_a ?? 0;
        const newSetsB = data.sets_b ?? 0;
        const newMatchDone = (data.status === 'completed' || data.status === 'stopped');
        const newIsStopped = (data.status === 'stopped');
        const newStopType = data.stop_type ?? st.stopType ?? null;
        const newStopReason = data.stop_reason ?? st.stopReason ?? null;
        const newWithdrawnPlayerNames = Array.isArray(data.withdrawn_player_names)
            ? data.withdrawn_player_names
            : (st.withdrawnPlayerNames ?? []);
        const newPlayerReasons = data.player_reasons && typeof data.player_reasons === 'object'
            ? data.player_reasons
            : (st.playerReasons ?? {});
        const newWinner = newIsStopped ? null : (data.winner_team ?? null);

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
            st.isStopped !== newIsStopped ||
            st.stopType !== newStopType ||
            st.stopReason !== newStopReason ||
            JSON.stringify(st.withdrawnPlayerNames ?? []) !== JSON.stringify(newWithdrawnPlayerNames) ||
            JSON.stringify(st.playerReasons ?? {}) !== JSON.stringify(newPlayerReasons) ||
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
            st.isStopped   = newIsStopped;
            st.stopType    = newStopType;
            st.stopReason  = newStopReason;
            st.withdrawnPlayerNames = newWithdrawnPlayerNames;
            st.playerReasons = newPlayerReasons;
            st.winnerTeam  = newWinner;
            syncSessionPlayerStatusFromStop(data);
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

        // 1. MATCH KEY = sumber identitas paling akurat
        if (payload.match_key) {
            for (const [idx, st] of Object.entries(courtsState)) {
                if (st.matchKey === payload.match_key) {
                    return idx;
                }
            }
        }

        // 2. Match berdasarkan match_id
        if (payload.match_id) {
            for (const [idx, st] of Object.entries(courtsState)) {
                if (
                    st.matchId &&
                    Number(st.matchId) === Number(payload.match_id)
                ) {
                    return idx;
                }
            }
        }

        // 3. court_num selalu dianggap 1-based
        if (payload.court_num !== undefined) {
            for (const [idx, st] of Object.entries(courtsState)) {
                if (Number(st.courtNum) === Number(payload.court_num)) {
                    return idx;
                }
            }
        }

        // 4. payload.court bisa berasal dari dua sumber:
        //    - internal JS: 0-based
        //    - broadcast tertentu: 1-based
        //
        //    Jangan prioritaskan ini jika match_key tersedia.
        if (payload.court !== undefined) {
            const courtValue = Number(payload.court);

            // 0-based
            if (courtsState[courtValue] !== undefined) {
                return courtValue;
            }

            // 1-based
            const zeroBased = courtValue - 1;
            if (courtsState[zeroBased] !== undefined) {
                return zeroBased;
            }
        }

        // 5. Kalau hanya ada satu court
        const keys = Object.keys(courtsState);
        if (keys.length === 1) {
            return keys[0];
        }

        return null;
    }

    // Handler Utama Seluruh Scoring Event (Supabase Realtime + Polling Fallback)
    // Menjalankan Aturan Monotonic & Stale Protection (Requirement 6)
    let isTransitioningRound = false;
    let isTransitioningSession = false;

    function handleSessionFinishedEvent(payload, sourceName = 'Realtime') {
        if (isTransitioningSession) return;
        const isFinished = payload?.is_session_finished || payload?.status === 'finished' || payload?.status_session === 'Finished' || payload?.is_finished;
        const recapUrl = payload?.recap_url || `{{ route('scoring.recap', $game['id']) }}`;

        if (isFinished) {
            isTransitioningSession = true;
            console.log(`[${sourceName}] Host finished the entire session! Auto-navigating to recap & podium...`);
            if (typeof showToast === 'function') {
                showToast('🏆 Sesi pertandingan telah selesai! Membuka hasil akhir & podium...');
            }
            const btnRecap = document.getElementById('btnGlobalRecap');
            if (btnRecap) {
                btnRecap.classList.remove('hidden');
            }
            setTimeout(() => {
                window.location.href = recapUrl;
            }, 800);
        }
    }

    function handleRoundAdvancedEvent(payload, sourceName = 'Realtime') {
        if (!payload || isTransitioningRound) return;

        // Host tidak boleh di-hijack oleh Realtime/polling background karena navigasi Host dikendalikan penuh oleh submit form ke Drawing
        if (IS_HOST) {
            console.log(`[${sourceName}] Host ignore auto-transition event (Host advances via bottom button form)`);
            return;
        }

        // JIKA PENONTON/PEMAIN SEDANG SENGAJA MELIHAT RIWAYAT RONDE LAMPAU:
        // DILARANG me-redirect paksa penonton. Biarkan mereka membaca rekap ronde lampau dengan tenang.
        if (IS_VIEWING_PAST_ROUND) {
            console.log(`[${sourceName}] Penonton/Pemain sedang melihat arsip ronde lampau (${ACTIVE_ROUND}). Auto-transition diabaikan.`);
            return;
        }

        const newRound = payload.next_round || payload.active_round || payload.session_active_round;
        if (!newRound || newRound === ACTIVE_ROUND) return;

        const currentRoundNum = parseInt(ACTIVE_ROUND_NUM, 10) || 1;
        const nextRoundNum = parseInt(newRound.replace(/[^0-9]/g, ''), 10) || 1;

        if (nextRoundNum > currentRoundNum) {
            isTransitioningRound = true;
            console.log(`[${sourceName}] Host advanced round (${ACTIVE_ROUND} -> ${newRound})! Auto-transitioning Player to Drawing...`);
            if (typeof showToast === 'function') {
                showToast(`🏆 Host telah memulai ${UNIT_TAB_LABEL} ${nextRoundNum}! Membuka jadwal & drawing...`);
            }
            setTimeout(() => {
                const drawingTargetUrl = payload.drawing_url || (`{{ url('games') }}/${GAME_ID}/drawing?round=${nextRoundNum}`);
                window.location.href = drawingTargetUrl;
            }, 300);
        }
    }

    function handleIncomingScoreEvent(payload, sourceName = 'Realtime') {
        if (!payload) return;

        if (payload.saved && typeof payload.saved === 'object') {
            payload = { ...payload, ...payload.saved };
        }

        // Auto-Transition ke ronde/set baru jika Host sudah memajukan sesi
        const remoteRound = payload.session_active_round || payload.next_round || payload.active_round;
        if (remoteRound && remoteRound !== ACTIVE_ROUND) {
            handleRoundAdvancedEvent(payload, sourceName);
        }

        // Auto-Transition ke recap jika sesi telah diselesaikan oleh Host
        if (payload.is_session_finished || payload.session_status === 'finished' || payload.status === 'finished') {
            handleSessionFinishedEvent(payload, sourceName);
        }

        const cIdx = findCourtIndex(payload);
        if (cIdx === null || courtsState[cIdx] === undefined) return;

        let st = courtsState[cIdx];
        const incomingVer = Number(payload.server_version ?? payload.version ?? 0);
        const effectiveLocalVer = Math.max(Number(st.localVersion || 0), Number(st.serverVersion || 0));
        const stopDetailsChanged = payload.status === 'stopped' && (
            (payload.stop_type != null && payload.stop_type !== st.stopType) ||
            (payload.stop_reason != null && payload.stop_reason !== st.stopReason) ||
            (Array.isArray(payload.withdrawn_player_names) && JSON.stringify(payload.withdrawn_player_names) !== JSON.stringify(st.withdrawnPlayerNames ?? [])) ||
            (payload.player_reasons && typeof payload.player_reasons === 'object' && JSON.stringify(payload.player_reasons) !== JSON.stringify(st.playerReasons ?? {}))
        );

        // 1. HOST SPECIFIC LOGIC (Master Scorer Protection):
        if (IS_HOST) {
            // Jika payload berasal dari perangkat Host ini sendiri atau versinya <= versi aksi lokal Host:
            // Cukup update serverVersion di background. DILARANG memanggil applyServerScore agar poin tidak rollback!
            if (incomingVer <= effectiveLocalVer || (payload.client_id && payload.client_id === CLIENT_ID)) {
                if (stopDetailsChanged) {
                    applyServerScore(cIdx, payload, incomingVer);
                }
                if (incomingVer > (st.serverVersion || 0)) {
                    st.serverVersion = incomingVer;
                }
                if (payload.status === 'completed' && st.matchDone) {
                    st.completionSaveSucceeded = true;
                    st.completionSavePending = false;
                    syncRoundCompletionStatus();
                }
                return;
            }

            // Anti-Downgrade Games Shield (untuk sinkronisasi antar multi-host):
            const incomingGamesA = Number(payload.games_a ?? payload.score_a ?? 0);
            const incomingGamesB = Number(payload.games_b ?? payload.score_b ?? 0);
            const localGamesA = Number(st.gamesA || 0);
            const localGamesB = Number(st.gamesB || 0);
            const incomingTotalGames = incomingGamesA + incomingGamesB;
            const localTotalGames = localGamesA + localGamesB;

            if (incomingTotalGames < localTotalGames || (incomingGamesA < localGamesA && incomingGamesB <= localGamesB) || (incomingGamesB < localGamesB && incomingGamesA <= localGamesA)) {
                console.log(`[${sourceName}] Host Score Shield: Incoming games (${incomingGamesA}-${incomingGamesB}) < local (${localGamesA}-${localGamesB}). Preserving Host state.`);
                return;
            }

            const hasPendingLocalActions = (
                (st.saveQueue && st.saveQueue.length > 0) ||
                (st.inFlightQueue && st.inFlightQueue.length > 0) ||
                (st.pendingSaves || 0) > 0 ||
                (st.lastLocalActionTime && (Date.now() - st.lastLocalActionTime < 1500))
            );

            if (hasPendingLocalActions) {
                console.log(`[${sourceName}] Host Active Queue Shield: Server version updated (${effectiveLocalVer} -> ${incomingVer}) in background.`);
                st.serverVersion = incomingVer;
                if (payload.status === 'completed' && st.matchDone) {
                    st.completionSaveSucceeded = true;
                    st.completionSavePending = false;
                    syncRoundCompletionStatus();
                }
                return;
            }
        }

        // 2. NON-HOST / SPECTATOR / RECONCILE RULES:
        // Aturan Reconcile & Stale Protection:
        // a. incoming server_version < effectiveLocalVer -> IGNORE (mencegah rollback skor)
        if (incomingVer < effectiveLocalVer) {
            console.log(`[${sourceName}] Stale IGNORE: incoming version (${incomingVer}) < local effectiveVersion (${effectiveLocalVer})`);
            return;
        }

        // b. incoming server_version == effectiveLocalVer -> IGNORE (idempotent, data sudah sinkron)
        if (incomingVer === effectiveLocalVer) {
            if (stopDetailsChanged) {
                applyServerScore(cIdx, payload, incomingVer);
            }
            if (payload.status === 'completed' && !st.matchDone) {
                st.matchDone = true;
                st.completionSaveSucceeded = true;
                st.completionSavePending = false;
                syncRoundCompletionStatus();
            }
            return;
        }

        // c. Jika match lokal sudah matchDone dan pendingSaves > 0, jangan biarkan server status != completed membatalkan
        if (st.matchDone && (st.pendingSaves || 0) > 0 && payload.status !== 'completed') {
            return;
        }

        // d. incoming server_version > effectiveLocalVer -> APPLY mutasi terbaru (untuk Member/penonton atau Host idle saat ada Host lain)
        console.log(`[${sourceName}] APPLY: Court ${st.courtNum} version updated (${effectiveLocalVer} -> ${incomingVer})`);
        applyServerScore(cIdx, payload, incomingVer);
        syncRoundCompletionStatus();
    }


    // ── Antrean Save Poin ke Server (Debounced & Batched & Offline Persistent) ──
    function queueScoreSave(cIdx, status, cSeq, tClick, eventId, action, team, baseVersion) {
        if (!IS_HOST) return;
        let st = courtsState[cIdx];
        
        if (eventId !== 'retry_drain' && eventId != null) {
            const snapshot = {
                idxA: st.idxA,
                idxB: st.idxB,
                isDeuce: st.isDeuce,
                advantage: st.advantage,
                gamesA: st.gamesA,
                gamesB: st.gamesB,
                pointDisplays: getPointDisplays(cIdx),
            };

            st.saveQueue.push({
                cIdx, matchKey: st.matchKey, courtNum: st.courtNum, roundKey: ACTIVE_ROUND, matchId: st.matchId, sessionId: GAME_ID, status, clientSeq: cSeq, tClick, eventId, action, team, baseVersion, snapshot
            });
            st.pendingSaves = st.saveQueue.length + (st.inFlightQueue ? st.inFlightQueue.length : 0);
            
            // OFFLINE QUEUE: Persist to localStorage
            try {
                localStorage.setItem(`matcha_seq_${GAME_ID}_${st.matchKey}`, String(cSeq));
                localStorage.setItem(`matcha_queue_${GAME_ID}_${st.matchKey}`, JSON.stringify([...st.inFlightQueue, ...st.saveQueue]));
            } catch(e) {}

            if (currentNetworkState === 'offline') {
                updateNetworkStatusUI('offline');
            }
        }

        const isCompletionSave = status === 'completed' || action === 'completion';
        if (isCompletionSave && st.activeSaveController && !st.activeSaveIsCompletion) {
            st.activeSaveController.abort();
        }

        if (!st.saveWorker) {
            st.saveWorker = setTimeout(() => {
                const batchEvents = [...st.saveQueue];
                st.saveQueue = [];
                st.inFlightQueue = batchEvents;
                st.pendingSaves = st.inFlightQueue.length;
                
                // Keep the combined inFlightQueue + saveQueue in localStorage
                try {
                    localStorage.setItem(`matcha_queue_${GAME_ID}_${st.matchKey}`, JSON.stringify([...st.inFlightQueue, ...st.saveQueue]));
                } catch(e) {}

                st.saveWorker = null;
                if (batchEvents.length > 0) {
                    saveScoreBatch(cIdx, batchEvents).catch(e => console.warn(e));
                }
            }, 50);
        }
    }

    async function saveScoreBatch(cIdx, batchEvents) {
        if (!IS_HOST) return;
        let st = courtsState[cIdx];
        if (st.isFinishing) return;

        let isCompletionSave = false;
        let lastClientSeq = 1;
        let lastBaseVersion = 0;
        let lastEventId = null;
        let latestSnapshot = null;
        let latestTClick = null;

        const backendEvents = batchEvents.map(e => {
            if (e.status === 'completed' || e.action === 'completion') {
                isCompletionSave = true;
            }

            lastClientSeq = Math.max(lastClientSeq, e.clientSeq || 1);
            lastBaseVersion = Math.max(lastBaseVersion, e.baseVersion || 0);
            lastEventId = e.eventId;
            latestSnapshot = e.snapshot;
            latestTClick = Math.max(latestTClick || 0, e.tClick || 0);

            return {
                action: e.action || 'add_point',
                team: e.team,
                event_id: e.eventId,
                base_version: e.baseVersion,
                tClick: e.tClick,
                status: e.status,

                // PENTING:
                // Kirim snapshot score ke backend pada setiap event.
                games_a: e.snapshot?.gamesA ?? null,
                games_b: e.snapshot?.gamesB ?? null,
                point_display_a: e.snapshot?.pointDisplays?.a ?? null,
                point_display_b: e.snapshot?.pointDisplays?.b ?? null,
            };
        });

        isCompletionSave = isCompletionSave || (!latestSnapshot && st.matchDone);

        if (isCompletionSave) {
            st.completionSavePending = true;
            st.completionSaveSucceeded = false;
            syncRoundCompletionStatus();
        }

        const scoreState = isCompletionSave ? st : (latestSnapshot || st);
        const displays = latestSnapshot ? latestSnapshot.pointDisplays : getPointDisplays(cIdx);
        const currentStatus = isCompletionSave ? 'completed' : (scoreState.matchDone ? 'completed' : 'in_progress');
        const reqSeq = lastClientSeq || st.clientSeq || 1;
        const reqBaseVer = lastBaseVersion || st.serverVersion || 0;
        const isWalkoverSave = batchEvents.some(e => e.action === 'walkover');
        const body = {
            game_id         : GAME_ID,
            round           : ACTIVE_ROUND,
            match_key       : st.matchKey,
            court           : st.courtNum,
            scoring_type    : SCORING_TYPE,
            action          : 'batch_events',
            events          : backendEvents,
            base_version    : reqBaseVer,
            score_a         : scoreState.gamesA,
            score_b         : scoreState.gamesB,
            point_display_a : displays.a,
            point_display_b : displays.b,
            set_number      : scoreState.setNumber || 1,
            sets_a          : (scoreState.gamesA >= scoreState.gamesB && currentStatus === 'completed') ? 1 : 0,
            sets_b          : (scoreState.gamesB > scoreState.gamesA && currentStatus === 'completed') ? 1 : 0,
            games_a         : scoreState.gamesA,
            games_b         : scoreState.gamesB,
            set_history     : [{ set: scoreState.setNumber || 1, score_a: scoreState.gamesA, score_b: scoreState.gamesB }],
            idx_a           : scoreState.idxA,
            idx_b           : scoreState.idxB,
            is_deuce        : scoreState.isDeuce,
            advantage       : scoreState.advantage,
            winner_team     : scoreState.winnerTeam || (scoreState.gamesA >= scoreState.gamesB ? 'Team A' : 'Team B'),
            status          : currentStatus,
            client_id       : CLIENT_ID,
            client_version  : reqSeq,
            client_seq      : reqSeq,
            is_walkover     : isWalkoverSave,
        };

        const requestController = new AbortController();
        st.activeSaveController = requestController;
        st.activeSaveIsCompletion = isCompletionSave;

        let isRetryableError = false;
        const saveFetchStart = performance.now();

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

                // 1. Cek penolakan stale / duplicate / already completed (Requirement 1 & 7)
                if (data.stale_ignored || data.duplicate || data.already_completed || data.saved?.status === 'completed') {
                    if (isCompletionSave || data.saved?.status === 'completed' || data.already_completed) {
                        st.completionSavePending = false;
                        st.completionSaveSucceeded = true;
                        st.matchDone = true;
                        if (!IS_HOST && data.saved) {
                            applyServerScore(cIdx, data.saved, incomingVer);
                        }
                        syncRoundCompletionStatus();
                        return;
                    }
                    if (!IS_HOST && data.saved) {
                        applyServerScore(cIdx, data.saved, incomingVer);
                    }
                    return;
                }

                // 2. Normal Completion Success (Requirement 7)
                if (isCompletionSave && data.success !== false) {
                    st.matchDone = true;
                    st.completionSaveSucceeded = true;
                    st.completionSavePending = false;
                    syncRoundCompletionStatus();
                }

                // 3. UI Synchronization:
                // Untuk HOST: respon server disinkronkan di balik layar jika ada antrean klik lanjutan
                const hasPendingQueue = (st.saveQueue && st.saveQueue.length > 0);
                const isHostRecentlyActive = (st.lastLocalActionTime && (Date.now() - st.lastLocalActionTime < 1500));

                if (!IS_HOST) {
                    if (data.saved) {
                        applyServerScore(cIdx, data.saved, incomingVer);
                    }
                } else {
                    // Host: Hanya terapkan jika ada merge conflict dari host lain dan user sedang idle
                    if (data.merged && data.saved && !hasPendingQueue && !isHostRecentlyActive) {
                        applyServerScore(cIdx, data.saved, incomingVer);
                    }
                }
                
                if (incomingVer > (st.serverVersion || 0)) {
                    st.serverVersion = incomingVer;
                }
                if (incomingVer > (st.localVersion || 0)) {
                    st.localVersion = incomingVer;
                }

                // Broadcast state terbaru via Supabase Realtime Channel client-side (peer broadcast dari snapshot server terkonfirmasi)
                // Terisolasi dari flow penyimpanan agar kegagalan broadcast tidak menyebabkan re-queue skor yang sudah tersimpan
                if (IS_HOST && realtimeChannel && data.saved) {
                    try {
                        const lastEvt = batchEvents && batchEvents.length > 0 ? batchEvents[batchEvents.length - 1] : null;
                        const lastEventId = data.last_event_id || (lastEvt ? lastEvt.eventId : '');
                        realtimeChannel.send({
                            type: 'broadcast',
                            event: 'score_update',
                            payload: {
                                ...data.saved,
                                court: cIdx,
                                match_key: st.matchKey,
                                server_version: incomingVer,
                                version: incomingVer,
                                last_event_id: lastEventId
                            }
                        }).catch(e => console.warn('[Supabase Realtime] broadcast send warning:', e));
                    } catch (e) {
                        console.warn('[Supabase Realtime] peer broadcast warning:', e);
                    }
                }

                const saveRoundtripMs = performance.now() - saveFetchStart;
                if (latestTClick) {
                    const roundtripMs = performance.now() - latestTClick;
                    console.log(`[Network Roundtrip] Court ${st.courtNum} save batch (${batchEvents.length} events) roundtrip: ${roundtripMs.toFixed(2)}ms (serverVersion=${st.serverVersion})`);
                }

                if (saveRoundtripMs > 2500) {
                    updateNetworkStatusUI('slow', { latencyMs: saveRoundtripMs });
                } else if (currentNetworkState === 'slow' || currentNetworkState === 'offline') {
                    if (getTotalPendingSaves() === 0) {
                        updateNetworkStatusUI('recovered');
                    } else if (currentNetworkState === 'offline') {
                        updateNetworkStatusUI('offline');
                    }
                }
            } else if (res.status === 422) {
                const errData = await res.json().catch(() => ({}));
                console.warn(`[Score Rejected 422] Court ${st.courtNum}:`, errData);
                showToast(errData.message || 'Pembaruan skor ditolak oleh server.');
                // Koreksi state lokal dari server authority
                st.inFlightQueue = [];
                if (st.matchDone && !st.completionSaveSucceeded) {
                    st.matchDone = false;
                    st.completionSavePending = false;
                }
                updateDisplay(cIdx);
            } else {
                console.error(`Update score batch failed for court ${st.courtNum}:`, await res.text());
                isRetryableError = true;
            }
        } catch (err) {
            if (err.name !== 'AbortError') {
                console.warn(`Gagal simpan skor batch court ${st.courtNum} (Network Offline/RTO):`, err);
                isRetryableError = true;
            }
        } finally {
            if (st.activeSaveController === requestController) {
                st.activeSaveController = null;
                st.activeSaveIsCompletion = false;
            }
            
            // Lepas Game Won sync lock saat save batch selesai
            if (st.isGameSyncing && !st.matchDone) {
                st.isGameSyncing = false;
                updateDisplay(cIdx);
            }

            if (isRetryableError) {
                // OFFLINE QUEUE: Kembalikan inFlightQueue ke saveQueue dan pertahankan pendingSaves
                st.inFlightQueue = [];
                st.saveQueue = [...batchEvents, ...st.saveQueue];
                st.pendingSaves = st.saveQueue.length;
                try {
                    localStorage.setItem(`matcha_queue_${GAME_ID}_${st.matchKey}`, JSON.stringify(st.saveQueue));
                } catch(e) {}

                const elapsed = performance.now() - saveFetchStart;
                if (!navigator.onLine) {
                    updateNetworkStatusUI('offline');
                } else {
                    updateNetworkStatusUI('slow', { latencyMs: elapsed });
                }
                
                // Jadwalkan retry otomatis 2 detik kemudian
                setTimeout(() => {
                    if (st.saveQueue.length > 0 && !st.saveWorker) {
                        console.log(`[Offline Sync] Retrying ${st.saveQueue.length} events for court ${cIdx}...`);
                        queueScoreSave(cIdx, null, null, null, 'retry_drain');
                    }
                }, 2000);
            } else {
                // Berhasil atau dibatalkan karena ada save baru yang lebih prioritas (AbortError)
                st.inFlightQueue = [];
                st.pendingSaves = st.saveQueue.length;
                try {
                    localStorage.setItem(`matcha_queue_${GAME_ID}_${st.matchKey}`, JSON.stringify(st.saveQueue));
                } catch(e) {}
                
                if (isCompletionSave && !st.completionSaveSucceeded && !st.matchDone) {
                    st.completionSavePending = false;
                    syncRoundCompletionStatus();
                }
                
                if (st.pendingSaves === 0) {
                    st.localVersion = Math.max(st.localVersion || 0, st.serverVersion || 0);
                }

                if (getTotalPendingSaves() === 0 && (currentNetworkState === 'slow' || currentNetworkState === 'offline')) {
                    updateNetworkStatusUI('recovered');
                } else if (currentNetworkState === 'offline') {
                    updateNetworkStatusUI('offline');
                }
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
            (courtsState[k].saveQueue?.length || 0) > 0 ||
            (courtsState[k].inFlightQueue?.length || 0) > 0 ||
            courtsState[k].completionSavePending === true
        ));
        const allCompletedAndSucceeded = courtKeys.length > 0 && courtKeys.every(k => (
            courtsState[k].matchDone &&
            (courtsState[k].completionSaveSucceeded === true || ((courtsState[k].saveQueue?.length || 0) === 0 && (courtsState[k].inFlightQueue?.length || 0) === 0))
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
                    (courtsState[k].saveQueue?.length || 0) > 0 ||
                    (courtsState[k].inFlightQueue?.length || 0) > 0 ||
                    courtsState[k].completionSavePending === true
                ));
                const nowSucceeded = courtKeys.every(k => (
                    courtsState[k].matchDone &&
                    (courtsState[k].completionSaveSucceeded === true || ((courtsState[k].saveQueue?.length || 0) === 0 && (courtsState[k].inFlightQueue?.length || 0) === 0))
                ));
                if (!stillPending && nowSucceeded) {
                    break;
                }
            }
        }

        // Re-check final status
        const canSubmit = courtKeys.length > 0 && courtKeys.every(k => (
            (courtsState[k].saveQueue?.length || 0) === 0 &&
            (courtsState[k].inFlightQueue?.length || 0) === 0 &&
            !courtsState[k].completionSavePending &&
            courtsState[k].matchDone
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
                text.textContent = 'Membuka Drawing {{ $unitTabLabel }} {{ $nextRoundNum ?? "Berikutnya" }}...';
            }
        }
        showToast('Membuka Drawing {{ $unitTabLabel }} {{ $nextRoundNum ?? "Berikutnya" }}...');
        const form = document.getElementById('globalNextRoundForm');
        if (form) form.submit();
    }

    function submitGlobalFinish(event) {
        if (event) event.preventDefault();

        // Ambil court yang sedang aktif / tersedia
        const keys = Object.keys(courtsState);

        if (!keys.length) {
            showToast('Data pertandingan tidak ditemukan.');
            return;
        }

        // Untuk single court, ini adalah court tersebut.
        // Untuk multi-court, pilih court yang sudah selesai terlebih dahulu.
        let cIdx = keys.find(key => courtsState[key]?.matchDone);

        if (cIdx === undefined) {
            cIdx = keys[keys.length - 1];
        }

        const st = courtsState[cIdx] || {};

        const gamesA = Number(st.gamesA || 0);
        const gamesB = Number(st.gamesB || 0);

        const finA       = document.getElementById('globalFinishScoreA');
        const finB       = document.getElementById('globalFinishScoreB');
        const finSetsA   = document.getElementById('globalFinishSetsA');
        const finSetsB   = document.getElementById('globalFinishSetsB');
        const finGamesA  = document.getElementById('globalFinishGamesA');
        const finGamesB  = document.getElementById('globalFinishGamesB');
        const finHistory = document.getElementById('globalFinishSetHistory');
        const finWinner  = document.getElementById('globalFinishWinnerTeam');
        const finMatchKey = document.getElementById('globalFinishMatchKey');
        const finStatus = document.getElementById('globalFinishStatus');
        const finIsStopped = document.getElementById('globalFinishIsStopped');

        if (finStatus) {
            finStatus.value = st.isStopped ? 'stopped' : (st.status || 'completed');
        }
        if (finIsStopped) {
            finIsStopped.value = st.isStopped ? '1' : '0';
        }

        // Score final harus mengambil state TERBARU
        if (finA) {
            finA.value = gamesA;
        }

        if (finB) {
            finB.value = gamesB;
        }

        if (finGamesA) {
            finGamesA.value = gamesA;
        }

        if (finGamesB) {
            finGamesB.value = gamesB;
        }

        // Tentukan winner berdasarkan score final (jika dihentikan, jangan putuskan sepihak)
        const winner = st.isStopped
            ? (st.winnerTeam || '')
            : (st.winnerTeam || (gamesA >= gamesB ? 'Team A' : 'Team B'));

        if (finWinner) {
            finWinner.value = winner;
        }

        // Set score
        if (finSetsA) {
            finSetsA.value = gamesA > gamesB ? 1 : 0;
        }

        if (finSetsB) {
            finSetsB.value = gamesB > gamesA ? 1 : 0;
        }

        // PENTING:
        // Jangan gunakan set_history lama dari cache.
        // Sinkronkan set terakhir dengan gamesA/gamesB terbaru.
        let finalHistory = [];

        if (Array.isArray(st.setHistory) && st.setHistory.length > 0) {
            finalHistory = JSON.parse(JSON.stringify(st.setHistory));

            const lastIndex = finalHistory.length - 1;

            finalHistory[lastIndex] = {
                ...finalHistory[lastIndex],
                set: Number(finalHistory[lastIndex].set || st.setNumber || 1),
                score_a: gamesA,
                score_b: gamesB,
            };
        } else {
            finalHistory = [
                {
                    set: Number(st.setNumber || 1),
                    score_a: gamesA,
                    score_b: gamesB,
                }
            ];
        }

        if (finHistory) {
            finHistory.value = JSON.stringify(finalHistory);
        }

        // Kirim match key yang benar
        if (finMatchKey) {
            finMatchKey.value = st.matchKey || '';
        }

        console.log('[FINAL SCORE]', {
            matchKey: st.matchKey,
            gamesA,
            gamesB,
            setHistory: finalHistory,
            winner
        });

        const btn = document.getElementById('btnGlobalFinishSession');

        if (btn) {
            btn.disabled = true;
            btn.classList.add('opacity-75', 'cursor-not-allowed');
            btn.innerHTML =
                '<i class="fa-solid fa-spinner fa-spin text-xs text-[#A8E63A]"></i> Menyimpan Sesi...';
        }

        showToast('Menyimpan hasil akhir seluruh sesi pertandingan...');

        const form = document.getElementById('globalFinishForm');

        if (form) {
            form.submit();
        }
    }

    // Supabase Realtime Client & Channel
    let supabaseClient = null;
    let realtimeChannel = null;

    function initSupabaseRealtime() {
        const hasSdk = typeof window.supabase !== 'undefined';
        const hasUrl = Boolean(SUPABASE_URL && SUPABASE_URL.trim() !== '');
        const hasKey = Boolean(SUPABASE_KEY && SUPABASE_KEY.trim() !== '');

        if (!hasSdk || !hasUrl || !hasKey) {
            console.warn('[Supabase Realtime] Supabase credentials or SDK missing. Using Polling Fallback.', {
                hasSdk: hasSdk,
                hasUrl: hasUrl,
                hasKey: hasKey
            });
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
                .on('broadcast', { event: 'round_advanced' }, ({ payload }) => {
                    console.log('[Supabase Realtime] round_advanced broadcast received:', payload);
                    handleRoundAdvancedEvent(payload, 'Realtime Broadcast');
                })
                .on('broadcast', { event: 'session_finished' }, ({ payload }) => {
                    console.log('[Supabase Realtime] session_finished broadcast received:', payload);
                    handleSessionFinishedEvent(payload, 'Realtime Broadcast');
                });

            const activeMatchIds = Object.values(courtsState)
                .map(s => Number(s.matchId))
                .filter(id => id > 0);

            if (activeMatchIds.length > 0) {
                const matchFilter = activeMatchIds.length === 1 
                    ? `match_id=eq.${activeMatchIds[0]}` 
                    : `match_id=in.(${activeMatchIds.join(',')})`;

                realtimeChannel.on('postgres_changes', {
                    event: '*',
                    schema: 'public',
                    table: 'tb_score',
                    filter: matchFilter
                }, (payload) => {
                    console.log('[Supabase Realtime] postgres_changes tb_score:', payload);
                    if (payload.new && Number(payload.new.version ?? 0) > 0) {
                        handleIncomingScoreEvent(payload.new, 'Realtime DB Changes');
                    }
                });
            }

            realtimeChannel.subscribe((status, err) => {
                console.log('[Supabase Realtime] Status:', status, err || '');
                const badge = document.getElementById('realtimeSyncBadge');
                if (status === 'SUBSCRIBED') {
                    if (badge) {
                        badge.innerHTML = '<i class="fa-solid fa-bolt text-emerald-500 text-[9px]"></i> Realtime Active';
                        badge.className = 'inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 text-[10px] font-bold';
                    }
                    triggerAllCourtsPoll(true);

                    // Beritahu seluruh Player di halaman Drawing bahwa Scoring untuk ronde ini telah resmi dibuka oleh Host
                    if (IS_HOST && realtimeChannel) {
                        realtimeChannel.send({
                            type: 'broadcast',
                            event: 'scoring_opened',
                            payload: {
                                session_id: GAME_ID,
                                round: ACTIVE_ROUND,
                                round_num: parseInt(ACTIVE_ROUND_NUM, 10),
                                redirect_url: window.location.href
                            }
                        }).catch(e => console.warn('[Supabase Realtime] scoring_opened broadcast error:', e));
                    }
                } else if (status === 'CHANNEL_ERROR' || status === 'TIMED_OUT') {
                    console.warn('[Supabase Realtime] Channel disconnected or errored:', status, err);
                    if (badge) {
                        badge.innerHTML = '<span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span> Polling Fallback';
                        badge.className = 'inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-amber-50 text-amber-700 border border-amber-200 text-[10px] font-bold';
                    }
                }
            });
        } catch (err) {
            console.warn('[Supabase Realtime] Init exception:', err);
        }
    }

    const courtsPollWorkers = {};
    function triggerAllCourtsPoll(isReconnect = false) {
        Object.keys(courtsState).forEach(cIdx => {
            if (typeof courtsPollWorkers[cIdx] === 'function') {
                courtsPollWorkers[cIdx](isReconnect);
            }
        });
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

        const executePoll = async (isReconnect = false) => {
            if (isPolling) return;
            isPolling = true;
            const pollStart = performance.now();
            try {
                let pollUrlWithTs = `${POLL_URL}&_t=${Date.now()}`;
                if (isReconnect) {
                    pollUrlWithTs += '&reconnect=1';
                }
                const res = await fetch(pollUrlWithTs, {
                    cache: 'no-store',
                    headers: { 'Accept': 'application/json' }
                });
                const pollDuration = performance.now() - pollStart;
                if (!res.ok) {
                    consecutivePollFailures++;
                    if (consecutivePollFailures >= 2 && currentNetworkState !== 'offline') {
                        if (!navigator.onLine) {
                            updateNetworkStatusUI('offline');
                        } else {
                            updateNetworkStatusUI('slow', { latencyMs: pollDuration });
                        }
                    }
                    return;
                }
                consecutivePollFailures = 0;
                const data = await res.json();
                data.court = cIdx;
                data.match_key = st.matchKey;

                if (pollDuration > 2500) {
                    updateNetworkStatusUI('slow', { latencyMs: pollDuration });
                } else if ((currentNetworkState === 'slow' || currentNetworkState === 'offline') && getTotalPendingSaves() === 0) {
                    updateNetworkStatusUI('recovered');
                }

                // Salurkan seluruh response polling melalui handler monotonic yang sama (Requirement 6)
                handleIncomingScoreEvent(data, isReconnect ? 'Polling Reconnect' : 'Polling 0.8s');
            } catch (err) {
                consecutivePollFailures++;
                const pollDuration = performance.now() - pollStart;
                if (!navigator.onLine) {
                    updateNetworkStatusUI('offline');
                } else if (consecutivePollFailures >= 2) {
                    updateNetworkStatusUI('slow', { latencyMs: pollDuration });
                }
            } finally {
                isPolling = false;
                nextPollTime = Date.now() + POLL_INTERVAL_MS;
                updateTimerDisplay();
            }
        };

        courtsPollWorkers[cIdx] = executePoll;

        // Jalankan polling loop tiap 0.8 detik
        setInterval(() => executePoll(false), POLL_INTERVAL_MS);

        // Jalankan polling awal secara bertahap (staggered) agar tidak bertabrakan
        setTimeout(() => executePoll(false), 100 + (Number(cIdx) * 150));
    });

    // Pemulihan koneksi & Tab Visibility:
    // Saat tab kembali dibuka setelah background, segera sinkronkan snapshot terbaru tanpa menunggu interval throttled
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
            triggerAllCourtsPoll(true);
        }
    });

    window.addEventListener('offline', () => {
        console.warn('[Network] Offline event detected.');
        updateNetworkStatusUI('offline');
    });

    window.addEventListener('online', () => {
        console.log('[Network] Online event detected. Checking connection & triggering poll/queue drain...');
        triggerAllCourtsPoll(true);
        if (IS_HOST) {
            Object.keys(courtsState).forEach(cIdx => {
                if (courtsState[cIdx].saveQueue?.length > 0 && !courtsState[cIdx].saveWorker) {
                    queueScoreSave(cIdx, null, null, null, 'retry_drain');
                }
            });
        }
    });

    // ── End Match Modal & Controller Functions ──────────────────────────────
    let currentStoppingCourtIdx = null;
    let currentStoppingTarget = null; // { sessionId, matchId, matchKey, round, courtIdx, requestKey, operationId }
    let stopMatchInFlight = {}; // Track in-flight request keyed by ${sessionId}_${round}_${matchId}_${matchKey}

    function openStopMatchModal(cIdx) {
        if (!IS_HOST) return;
        const st = courtsState[cIdx];
        if (!st || st.matchDone) {
            showToast('Pertandingan ini sudah selesai atau dihentikan.');
            return;
        }

        const matchId = st.matchId || 0;
        const matchKey = st.matchKey || `round_${ACTIVE_ROUND}_court_${cIdx + 1}`;
        const requestKey = `${GAME_ID}_${ACTIVE_ROUND}_${matchId}_${matchKey}`;
        currentStoppingCourtIdx = cIdx;
        currentStoppingTarget = {
            sessionId: GAME_ID,
            matchId: matchId,
            matchKey: matchKey,
            round: ACTIVE_ROUND,
            courtIdx: cIdx,
            requestKey: requestKey,
            operationId: null
        };

        const modal = document.getElementById('stopMatchModal');
        const roundCourtEl = document.getElementById('modalMatchRoundCourt');
        const scoreSnapshotEl = document.getElementById('modalMatchScoreSnapshot');
        const teamAEl = document.getElementById('modalTeamANames');
        const teamBEl = document.getElementById('modalTeamBNames');
        const errorEl = document.getElementById('modalStopError');
        if (errorEl) errorEl.classList.add('hidden');

        // Reset status tombol modal sesuai kondisi request target match/court ini
        const btnConfirm = document.getElementById('btnConfirmStopMatch');
        const isThisTargetInFlight = !!stopMatchInFlight[requestKey];
        if (btnConfirm) {
            btnConfirm.disabled = isThisTargetInFlight;
            btnConfirm.innerHTML = isThisTargetInFlight
                ? '<i class="fa-solid fa-spinner fa-spin"></i> Menyimpan penghentian...'
                : '<i class="fa-solid fa-check"></i> <span>Konfirmasi Akhiri Pertandingan</span>';
        }

        const courtLabel = st.courtName || ('Court ' + st.courtNum);
        if (roundCourtEl) roundCourtEl.textContent = `${UNIT_TAB_LABEL} ${ACTIVE_ROUND_NUM} • ${courtLabel}`;
        
        const ptDisplays = getPointDisplays(cIdx);
        const scoreUnit = IS_SETS ? 'Games' : 'Poin';
        if (scoreSnapshotEl) {
            scoreSnapshotEl.textContent = `Skor Terakhir: ${st.gamesA} — ${st.gamesB} ${scoreUnit} (Point: ${ptDisplays.a} : ${ptDisplays.b})`;
        }

        const participants = st.participants || [];
        const teamAParticipants = participants.filter(p => p.team === 'Tim A');
        const teamBParticipants = participants.filter(p => p.team === 'Tim B');
        if (teamAEl) teamAEl.textContent = 'Tim A: ' + (teamAParticipants.map(p => p.name).join(' & ') || 'Tim A');
        if (teamBEl) teamBEl.textContent = 'Tim B: ' + (teamBParticipants.map(p => p.name).join(' & ') || 'Tim B');

        // Render Condition A Players (optional checkbox list)
        const condAPlayersList = document.getElementById('condAPlayersList');
        if (condAPlayersList) {
            condAPlayersList.innerHTML = participants.map((p, pIdx) => `
                <label class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-xl bg-slate-100 hover:bg-slate-200/70 border border-slate-200 text-slate-700 text-xs cursor-pointer transition-colors">
                    <input type="checkbox" name="related_players[]" value="${p.id || p.name}" class="rounded text-[#063B00] focus:ring-[#063B00]">
                    <span>${p.name} <span class="text-[10px] text-slate-400">(${p.team})</span></span>
                </label>
            `).join('');
        }

        // Render Condition B Players (Temporary Pause / Bench)
        const condPausePlayersList = document.getElementById('condPausePlayersList');
        if (condPausePlayersList) {
            condPausePlayersList.innerHTML = participants.map((p, pIdx) => `
                <label class="flex items-center gap-2 p-2 rounded-xl bg-white border border-slate-200/90 hover:border-amber-400 hover:bg-amber-50/40 cursor-pointer transition-all select-none shadow-2xs">
                    <input type="checkbox" name="paused_players[]" value="${p.id || p.name}" data-player-id="${p.id || ''}" data-idx="${pIdx}" data-name="${p.name}" onchange="hideStopMatchError(); updateConditionPauseImpact();"
                        class="rounded text-amber-600 focus:ring-amber-500 w-4 h-4 shrink-0">
                    <div class="min-w-0 flex-1 flex items-center justify-between gap-1.5">
                        <span class="font-bold text-xs text-slate-800 truncate">${p.name}</span>
                        <span class="text-[9px] font-black px-1.5 py-0.5 rounded-md bg-slate-100 text-slate-500 shrink-0">${p.team}</span>
                    </div>
                </label>
            `).join('');
        }

        // Render Condition C Players (Permanent Withdrawal)
        const condBPlayersList = document.getElementById('condBPlayersList');
        if (condBPlayersList) {
            condBPlayersList.innerHTML = participants.map((p, pIdx) => `
                <div class="p-2.5 rounded-2xl border border-slate-200/90 bg-white space-y-2 shadow-2xs">
                    <label class="flex items-center gap-2.5 cursor-pointer select-none">
                        <input type="checkbox" name="withdrawn_players[]" value="${p.id || p.name}" data-player-id="${p.id || ''}" data-idx="${pIdx}" data-name="${p.name}" onchange="hideStopMatchError(); onWithdrawnPlayerToggle(${pIdx})"
                            class="rounded text-rose-600 focus:ring-rose-500 w-4 h-4 shrink-0">
                        <div class="min-w-0 flex-1 flex items-center justify-between gap-1.5">
                            <span class="font-bold text-xs text-slate-800 truncate">${p.name}</span>
                            <span class="text-[9px] font-black px-1.5 py-0.5 rounded-md bg-slate-100 text-slate-500 shrink-0">${p.team}</span>
                        </div>
                    </label>
                    <div id="playerReasonBox_${pIdx}" class="hidden pl-6 space-y-1">
                        <label class="block text-[10px] font-bold text-slate-600">Alasan/Kendala Pemain Ini Keluar <span class="text-rose-500">*</span></label>
                        <input type="text" id="playerNote_${pIdx}" placeholder="Contoh: Cedera hamstring, ada keperluan mendadak"
                            oninput="hideStopMatchError(); updateConditionBImpact();"
                            class="w-full px-2.5 py-1.5 rounded-xl border border-slate-200 text-xs bg-slate-50/50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-rose-500/20 focus:border-rose-500 transition-all">
                    </div>
                </div>
            `).join('');
        }

        // Reset radio
        const radioA = document.querySelector('input[name="stopCondition"][value="keep_in_session"]');
        if (radioA) radioA.checked = true;
        const chkPause = document.getElementById('chkPausePlayersToggle');
        if (chkPause) chkPause.checked = false;
        const wrapPause = document.getElementById('wrapperPausePlayersSection');
        if (wrapPause) wrapPause.classList.add('hidden');
        const reasonA = document.getElementById('stopReasonA');
        if (reasonA) reasonA.value = '';
        const reasonPause = document.getElementById('stopReasonPause');
        if (reasonPause) reasonPause.value = '';

        onStopConditionChange();
        hideStopMatchError();
        if (modal) modal.classList.remove('hidden');
    }

    function hideStopMatchError() {
        const errorEl = document.getElementById('modalStopError');
        if (errorEl) errorEl.classList.add('hidden');
    }

    function closeStopMatchModal() {
        const modal = document.getElementById('stopMatchModal');
        if (modal) modal.classList.add('hidden');
        hideStopMatchError();
        const btnConfirm = document.getElementById('btnConfirmStopMatch');
        if (btnConfirm) {
            btnConfirm.disabled = false;
            btnConfirm.innerHTML = '<i class="fa-solid fa-check"></i> <span>Konfirmasi Akhiri Pertandingan</span>';
        }
        currentStoppingCourtIdx = null;
        currentStoppingTarget = null;
    }

    function onTogglePausePlayersCheckbox() {
        const chk = document.getElementById('chkPausePlayersToggle');
        const wrap = document.getElementById('wrapperPausePlayersSection');
        if (chk && wrap) {
            wrap.classList.toggle('hidden', !chk.checked);
            if (!chk.checked) {
                document.querySelectorAll('input[name="paused_players[]"]').forEach(cb => cb.checked = false);
            }
        }
        hideStopMatchError();
        updateConditionAOrPauseImpact();
    }

    function onStopConditionChange() {
        const selected = document.querySelector('input[name="stopCondition"]:checked')?.value || 'keep_in_session';
        const secA = document.getElementById('sectionConditionA');
        const secC = document.getElementById('sectionConditionC');
        const cardA = document.getElementById('labelConditionA');
        const cardC = document.getElementById('labelConditionC');
        const radioDotA = document.querySelector('#radioCircleA span');
        const radioDotC = document.querySelector('#radioCircleC span');

        if (selected === 'keep_in_session') {
            if (secA) secA.classList.remove('hidden');
            if (secC) secC.classList.add('hidden');
            if (cardA) cardA.className = 'relative flex flex-col p-3 rounded-2xl border-2 border-emerald-600 bg-emerald-50/50 cursor-pointer transition-all select-none shadow-2xs hover:shadow-xs';
            if (cardC) cardC.className = 'relative flex flex-col p-3 rounded-2xl border border-slate-200 bg-white cursor-pointer transition-all select-none hover:border-slate-300 hover:bg-slate-50/50';
            if (radioDotA) radioDotA.classList.remove('hidden');
            if (radioDotC) radioDotC.classList.add('hidden');

            updateConditionAOrPauseImpact();
        } else {
            if (secA) secA.classList.add('hidden');
            if (secC) secC.classList.remove('hidden');
            if (cardA) cardA.className = 'relative flex flex-col p-3 rounded-2xl border border-slate-200 bg-white cursor-pointer transition-all select-none hover:border-slate-300 hover:bg-slate-50/50';
            if (cardC) cardC.className = 'relative flex flex-col p-3 rounded-2xl border-2 border-rose-600 bg-rose-50/50 cursor-pointer transition-all select-none shadow-2xs hover:shadow-xs';
            if (radioDotA) radioDotA.classList.add('hidden');
            if (radioDotC) radioDotC.classList.remove('hidden');

            updateConditionBImpact();
        }
    }

    function onWithdrawnPlayerToggle(pIdx) {
        const box = document.getElementById('playerReasonBox_' + pIdx);
        const chk = document.querySelector(`input[name="withdrawn_players[]"][data-idx="${pIdx}"]`);
        if (box && chk) {
            box.classList.toggle('hidden', !chk.checked);
            if (chk.checked) {
                const noteInput = document.getElementById('playerNote_' + pIdx);
                if (noteInput) noteInput.focus();
            }
        }
        updateConditionBImpact();
    }

    function updateConditionAOrPauseImpact() {
        const impactList = document.getElementById('impactBulletList');
        if (!impactList) return;

        const isPauseChecked = document.getElementById('chkPausePlayersToggle')?.checked || false;
        if (isPauseChecked) {
            const checkedBoxes = Array.from(document.querySelectorAll('input[name="paused_players[]"]:checked'));
            const playerNames = checkedBoxes.map(cb => cb.getAttribute('data-name')).filter(Boolean);
            const namesText = playerNames.length > 0 ? playerNames.join(', ') : '(Belum ada pemain dipilih)';
            const newlyPausedCount = checkedBoxes.length;
            const remainingActive = Math.max(0, TOTAL_PLAYERS_COUNT - newlyPausedCount);
            const isInsufficient = remainingActive < MIN_REQUIRED_PLAYERS;

            let quotaWarningHtml = '';
            if (isInsufficient && newlyPausedCount > 0) {
                quotaWarningHtml = `
                    <li class="!text-amber-950 font-bold bg-amber-100/90 p-2 rounded-xl border border-amber-300 list-none mt-1 text-xs shadow-2xs">
                        ⚠️ <strong>Perhatian Kuota:</strong> Sisa pemain aktif akan menjadi ${remainingActive} orang (kurang dari syarat minimal ${MIN_REQUIRED_PLAYERS} orang). Jadwal ronde berikutnya akan ditunda sementara sampai Host mengaktifkan kembali pemain di Bangku Cadangan.
                    </li>
                `;
            }

            impactList.innerHTML = `
                <li>Match court ini akan ditandai <strong>Dihentikan</strong> dengan skor parsial terakhir yang sah.</li>
                <li>Pemain yang butuh istirahat: <strong>${namesText}</strong> akan otomatis ditempatkan di <strong>Bangku Cadangan (Bench)</strong> pada ronde berikutnya.</li>
                <li>Pemain lain tetap aktif bertanding seperti biasa di lapangan.</li>
                <li>Saat pemain sudah selesai istirahat, Host cukup klik tombol <strong>[ Siap Main ]</strong> di Bangku Cadangan untuk memasukkannya kembali ke rotasi jadwal ronde mendatang.</li>
                ${quotaWarningHtml}
            `;
        } else {
            impactList.innerHTML = `
                <li>Match court ini akan ditandai <strong>Dihentikan</strong> dengan skor parsial terakhir yang sah.</li>
                <li>Ronde berikutnya dapat dimulai setelah semua court aktif mencapai status terminal.</li>
                <li>Drawing dan seluruh pemain <strong>tetap aktif berpartisipasi</strong> pada ronde mendatang.</li>
            `;
        }
    }

    function updateConditionPauseImpact() {
        updateConditionAOrPauseImpact();
    }

    function updateConditionBImpact() {
        const impactList = document.getElementById('impactBulletList');
        if (!impactList) return;

        const checkedBoxes = Array.from(document.querySelectorAll('input[name="withdrawn_players[]"]:checked'));
        const playerNames = checkedBoxes.map(cb => cb.getAttribute('data-name')).filter(Boolean);
        const newlyWithdrawnCount = checkedBoxes.length;
        const remainingActive = Math.max(0, TOTAL_PLAYERS_COUNT - newlyWithdrawnCount);
        const isInsufficient = remainingActive < MIN_REQUIRED_PLAYERS;

        let quotaWarningHtml = '';
        if (isInsufficient && newlyWithdrawnCount > 0) {
            quotaWarningHtml = `
                <li class="!text-rose-950 font-bold bg-rose-100/90 p-2 rounded-xl border border-rose-300 list-none mt-1 text-xs shadow-2xs">
                    ⚠️ <strong>Perhatian Kuota:</strong> Sisa pemain aktif akan menjadi ${remainingActive} orang (kurang dari syarat minimal ${MIN_REQUIRED_PLAYERS} orang). Sesi tidak dapat melanjutkan ronde berikutnya tanpa jumlah pemain yang mencukupi.
                </li>
            `;
        }

        const namesText = playerNames.length > 0 ? playerNames.join(', ') : '(Belum ada pemain dipilih)';
        impactList.innerHTML = `
            <li>Match court ini akan ditandai <strong>Dihentikan</strong> dengan skor parsial terakhir yang sah.</li>
            <li>Pemain yang keluar: <strong>${namesText}</strong> akan ditandai tidak aktif untuk sesi ini (riwayat ronde lampau tetap tersimpan).</li>
            <li>Jadwal sisa ronde mendatang akan disesuaikan otomatis untuk pemain yang masih aktif.</li>
            <li>Partner dan lawan yang tidak keluar tetap aktif di sesi ini.</li>
            ${quotaWarningHtml}
        `;
    }

    async function confirmStopMatch() {
        if (!IS_HOST || !currentStoppingTarget) return;
        const target = currentStoppingTarget;
        const cIdx = target.courtIdx;
        const requestKey = target.requestKey;
        const st = courtsState[cIdx];
        if (!st) return;

        if (stopMatchInFlight[requestKey]) {
            console.log(`[Stop Match] Pertandingan ${requestKey} sedang memproses penghentian.`);
            return;
        }

        const operationId = 'stop_' + requestKey + '_' + Date.now() + '_' + Math.random().toString(36).substr(2, 6);
        target.operationId = operationId;
        stopMatchInFlight[requestKey] = { operationId, timestamp: Date.now() };

        const errorEl = document.getElementById('modalStopError');
        const errorMsgEl = document.getElementById('modalStopErrorMsg');
        const showError = (msg) => {
            if (errorEl && errorMsgEl) {
                errorMsgEl.textContent = msg;
                errorEl.classList.remove('hidden');
            } else {
                showToast(msg);
            }
        };
        if (errorEl) errorEl.classList.add('hidden');

        const mainCondition = document.querySelector('input[name="stopCondition"]:checked')?.value || 'keep_in_session';
        const isPauseChecked = document.getElementById('chkPausePlayersToggle')?.checked || false;
        let selectedCondition = mainCondition;
        let stopReason = '';
        let relatedPlayerIds = [];
        let withdrawnPlayerIds = [];
        let playerNotes = {};

        if (mainCondition === 'keep_in_session') {
            if (isPauseChecked) {
                selectedCondition = 'temporary_pause';
                const pauseChecks = Array.from(document.querySelectorAll('input[name="paused_players[]"]:checked'));
                if (pauseChecks.length === 0) {
                    showError('Pilih minimal satu pemain yang butuh istirahat sementara ke bangku cadangan.');
                    delete stopMatchInFlight[requestKey];
                    return;
                }
                withdrawnPlayerIds = pauseChecks.map(cb => cb.getAttribute('data-player-id') || cb.value);
                const pauseReasonVal = (document.getElementById('stopReasonPause')?.value || '').trim();
                const reasonAVal = (document.getElementById('stopReasonA')?.value || '').trim();
                stopReason = pauseReasonVal || reasonAVal || 'Istirahat sementara (Bench)';
            } else {
                selectedCondition = 'keep_in_session';
                stopReason = (document.getElementById('stopReasonA')?.value || '').trim();
                if (!stopReason) {
                    showError('Wajib mengisi alasan penghentian pertandingan.');
                    delete stopMatchInFlight[requestKey];
                    return;
                }
                const relChecks = document.querySelectorAll('input[name="related_players[]"]:checked');
                relatedPlayerIds = Array.from(relChecks).map(c => c.value);
            }
        } else {
            selectedCondition = 'withdraw_players';
            const withChecks = Array.from(document.querySelectorAll('input[name="withdrawn_players[]"]:checked'));
            if (withChecks.length === 0) {
                showError('Pilih minimal satu pemain yang keluar dari sesi.');
                delete stopMatchInFlight[requestKey];
                return;
            }
            for (const chk of withChecks) {
                const pIdx = chk.getAttribute('data-idx');
                const pId = chk.getAttribute('data-player-id');
                const pName = chk.getAttribute('data-name') || 'Pemain';
                const pVal = pId || chk.value;
                const note = (document.getElementById('playerNote_' + pIdx)?.value || '').trim();
                if (!note) {
                    showError(`Wajib mencatat alasan/kendala untuk ${pName}.`);
                    delete stopMatchInFlight[requestKey];
                    return;
                }
                withdrawnPlayerIds.push(pVal);
                playerNotes[pVal] = note;
                if (pName && pName !== pVal) {
                    playerNotes[pName] = note;
                }
            }
            stopReason = Object.values(playerNotes).filter((v, i, a) => a.indexOf(v) === i).join('; ');
        }

        const btnConfirm = document.getElementById('btnConfirmStopMatch');
        if (btnConfirm) {
            btnConfirm.disabled = true;
            btnConfirm.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Menyimpan penghentian...';
        }

        // Host Requirement: Tunggu pengosongan antrean skor lokal sebelum penghentian
        if ((st.saveQueue && st.saveQueue.length > 0) || (st.inFlightQueue && st.inFlightQueue.length > 0) || (st.pendingSaves || 0) > 0) {
            console.log('[Stop Match] Menunggu pengosongan antrean skor lokal...');
            let waitAttempts = 0;
            while (((st.saveQueue && st.saveQueue.length > 0) || (st.inFlightQueue && st.inFlightQueue.length > 0)) && waitAttempts < 25) {
                await new Promise(r => setTimeout(r, 200));
                waitAttempts++;
            }
            if ((st.saveQueue && st.saveQueue.length > 0) || (st.inFlightQueue && st.inFlightQueue.length > 0)) {
                showError('Masih terdapat antrean skor yang belum selesai tersimpan ke server. Mohon tunggu sesaat dan pastikan koneksi stabil.');
                delete stopMatchInFlight[requestKey];
                if (btnConfirm) {
                    btnConfirm.disabled = false;
                    btnConfirm.innerHTML = '<i class="fa-solid fa-check"></i> <span>Konfirmasi Akhiri Pertandingan</span>';
                }
                return;
            }
        }

        const displays = getPointDisplays(cIdx);
        const payload = {
            game_id: GAME_ID,
            session_id: GAME_ID,
            operation_id: operationId,
            match_id: st.matchId,
            match_key: st.matchKey,
            court_number: st.courtNum,
            court: st.courtNum,
            round_key: ACTIVE_ROUND,
            round: ACTIVE_ROUND,
            stop_type: selectedCondition,
            reason: stopReason,
            stop_reason: stopReason,
            related_player_ids: relatedPlayerIds,
            withdrawn_player_ids: withdrawnPlayerIds,
            withdrawn_players: withdrawnPlayerIds,
            player_notes: playerNotes,
            player_reasons: playerNotes,
            current_games_a: st.gamesA,
            current_games_b: st.gamesB,
            point_a: displays.a,
            point_b: displays.b
        };

        const abortCtrl = new AbortController();
        const timeoutId = setTimeout(() => abortCtrl.abort(), 15000);

        try {
            const resp = await fetch(STOP_MATCH_URL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': CSRF_TOKEN
                },
                body: JSON.stringify(payload),
                signal: abortCtrl.signal
            });
            clearTimeout(timeoutId);

            let resData = {};
            try {
                resData = await resp.json();
            } catch (jsonErr) {
                resData = {};
            }

            if (!resp.ok || !resData.success) {
                let errorMsg = resData.message || 'Gagal mengakhiri pertandingan.';
                if (resp.status >= 500 || /undefined method|Call to|SQLSTATE|Exception|Fatal error|Stack trace/i.test(errorMsg)) {
                    errorMsg = 'Terjadi kendala teknis pada server saat mengakhiri pertandingan. Silakan coba lagi.';
                    if (resData.correlation_id) {
                        errorMsg += ` (Ref: ${resData.correlation_id.substring(0, 8)})`;
                    }
                }
                throw new Error(errorMsg);
            }

            st.matchDone = true;
            st.isStopped = true;
            st.stopType = selectedCondition;
            st.stopReason = stopReason;
            st.withdrawnPlayerNames = resData.withdrawn_player_names || resData.saved?.withdrawn_player_names || [];
            st.playerReasons = resData.player_reasons || resData.saved?.player_reasons || {};
            st.winnerTeam = null;
            st.completionSavePending = false;
            st.completionSaveSucceeded = true;
            syncSessionPlayerStatusFromStop(resData.saved || resData);

            const isTargetStillActive = currentStoppingTarget &&
                currentStoppingTarget.sessionId === target.sessionId &&
                currentStoppingTarget.matchId === target.matchId &&
                currentStoppingTarget.round === target.round &&
                currentStoppingTarget.courtIdx === target.courtIdx &&
                currentStoppingTarget.operationId === operationId;

            // Hanya tutup modal jika modal saat ini masih menargetkan pertandingan dan operasi yang sama
            if (isTargetStillActive) {
                closeStopMatchModal();
            }
            updateDisplay(cIdx);
            syncRoundCompletionStatus();

            showToast(`Pertandingan ${st.courtName || ('Court ' + st.courtNum)} berhasil dihentikan.`);

            if (resData.warning) {
                showInsufficientPlayersWarningModal(resData.warning, resData.stop_type || selectedCondition);
            }
        } catch (err) {
            clearTimeout(timeoutId);
            console.error('[Stop Match Error]:', err);
            const isTimeout = err.name === 'AbortError';
            const msg = isTimeout 
                ? 'Koneksi ke server timeout saat menyimpan penghentian. Silakan periksa koneksi lalu coba lagi.' 
                : (err.message || 'Terjadi kesalahan sistem saat mengakhiri pertandingan.');

            const isTargetStillActive = currentStoppingTarget &&
                currentStoppingTarget.sessionId === target.sessionId &&
                currentStoppingTarget.matchId === target.matchId &&
                currentStoppingTarget.round === target.round &&
                currentStoppingTarget.courtIdx === target.courtIdx &&
                currentStoppingTarget.operationId === operationId;

            if (isTargetStillActive) {
                showError(msg);
                if (btnConfirm) {
                    btnConfirm.disabled = false;
                    btnConfirm.innerHTML = '<i class="fa-solid fa-check"></i> <span>Konfirmasi Akhiri Pertandingan</span>';
                }
            } else {
                showToast(`Court ${cIdx + 1}: ${msg}`);
            }
        } finally {
            delete stopMatchInFlight[requestKey];
            const isTargetStillActive = currentStoppingTarget &&
                currentStoppingTarget.sessionId === target.sessionId &&
                currentStoppingTarget.matchId === target.matchId &&
                currentStoppingTarget.round === target.round &&
                currentStoppingTarget.courtIdx === target.courtIdx &&
                currentStoppingTarget.operationId === operationId;

            if (isTargetStillActive) {
                const btn = document.getElementById('btnConfirmStopMatch');
                if (btn && !st.isStopped) {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fa-solid fa-check"></i> <span>Konfirmasi Akhiri Pertandingan</span>';
                }
            }
        }
    }

    /**
     * Tampilkan modal dialog estetis bergaya Matcha saat pemain aktif tidak mencukupi
     */
    function showInsufficientPlayersWarningModal(message, stopType = '') {
        const modal = document.getElementById('insufficientPlayersWarningModal');
        const msgEl = document.getElementById('insufficientPlayersWarningMessage');
        const guidanceEl = document.getElementById('insufficientPlayersHostGuidance');
        const actionBox = document.getElementById('insufficientPlayersActionContainer');
        const isPermanentWithdrawal = ['withdraw', 'withdraw_players'].includes(stopType);
        if (msgEl && message) {
            msgEl.textContent = message;
        }
        if (guidanceEl) {
            guidanceEl.innerHTML = isPermanentWithdrawal
                ? 'Pemain yang keluar permanen tidak dapat diaktifkan kembali. Jika kuota masih kurang setelah pemain istirahat diaktifkan, selesaikan pertandingan yang masih berjalan lalu pilih <strong class="font-extrabold text-[#063B00]">Selesaikan Sesi &amp; Lihat Juara</strong> untuk membuka recap.'
                : 'Jadwal ronde berikutnya ditunda sementara. Aktifkan kembali pemain istirahat melalui tombol <strong class="font-extrabold text-[#063B00]">[Siap Main]</strong> di Bangku Cadangan.';
        }
        if (actionBox && IS_HOST) {
            const pausedList = [];
            sessionPausedPlayerIds.forEach(pId => {
                const player = getSessionPlayerById(pId);
                if (player && !sessionWithdrawnPlayerIds.has(Number(pId))) {
                    pausedList.push(player);
                }
            });
            if (pausedList.length > 0) {
                actionBox.innerHTML = `
                    <div class="space-y-1.5 pt-2 border-t border-amber-200/60">
                        <span class="text-[11px] font-bold text-amber-950 block">Aktifkan kembali pemain untuk lanjut ke ronde berikutnya:</span>
                        <div class="flex flex-wrap gap-2">
                            ${pausedList.map(p => {
                                const id = Number(p.id || p.player_id);
                                const name = escapeLiveStatusText(p.name || p.nama || 'Pemain');
                                const safeName = (p.name || p.nama || 'Pemain').replace(/'/g, "\\'");
                                return `<button type="button" onclick="closeInsufficientPlayersWarningModal(); reactivatePlayerLive(${id}, '${safeName}')" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold text-xs shadow-xs cursor-pointer transition-all active:scale-95"><i class="fa-solid fa-play text-[8px]"></i> Aktifkan ${name} (Siap Main)</button>`;
                            }).join('')}
                        </div>
                    </div>
                `;
                actionBox.classList.remove('hidden');
            } else {
                actionBox.innerHTML = '';
                actionBox.classList.add('hidden');
            }
        }
        if (modal) {
            modal.style.display = 'flex';
        }
    }

    function closeInsufficientPlayersWarningModal() {
        const modal = document.getElementById('insufficientPlayersWarningModal');
        if (modal) {
            modal.style.display = 'none';
        }
    }

    let pendingReactivateLive = { playerId: null, playerName: '' };

    /**
     * Buka modal konfirmasi untuk mengaktifkan kembali pemain yang sedang istirahat.
     */
    function reactivatePlayerLive(playerId, playerName) {
        if (!IS_HOST) return;
        pendingReactivateLive = { playerId, playerName };
        const nameEl = document.getElementById('reactivateLivePlayerNameDisplay');
        if (nameEl) nameEl.textContent = playerName;
        const errEl = document.getElementById('reactivateLiveModalError');
        if (errEl) errEl.classList.add('hidden');
        const modal = document.getElementById('reactivateConfirmModalLive');
        if (modal) modal.style.display = 'flex';
    }

    function closeReactivateLiveModal() {
        const modal = document.getElementById('reactivateConfirmModalLive');
        if (modal) modal.style.display = 'none';
        pendingReactivateLive = { playerId: null, playerName: '' };
    }

    /**
     * Eksekusi reaktivasi pemain ke backend setelah dikonfirmasi pada modal.
     */
    async function executeReactivatePlayerLive() {
        if (!IS_HOST || !pendingReactivateLive.playerId) return;
        const { playerId, playerName } = pendingReactivateLive;

        const btn = document.getElementById('btnConfirmReactivateLive');
        const btnText = document.getElementById('btnConfirmReactivateLiveText');
        const errEl = document.getElementById('reactivateLiveModalError');
        const errMsgEl = document.getElementById('reactivateLiveModalErrorMsg');

        if (btn) btn.disabled = true;
        if (btnText) btnText.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-1"></i> Menyimpan...';
        if (errEl) errEl.classList.add('hidden');

        try {
            const resp = await fetch('{{ route('scoring.reactivate-player') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': CSRF_TOKEN
                },
                body: JSON.stringify({
                    game_id: GAME_ID,
                    player_id: playerId,
                    round: ACTIVE_ROUND
                })
            });

            const data = await resp.json();
            if (!resp.ok || !data.success) {
                const msg = data.message || 'Gagal mengaktifkan kembali pemain.';
                if (errEl && errMsgEl) {
                    errMsgEl.textContent = msg;
                    errEl.classList.remove('hidden');
                } else {
                    showToast(msg, 'error');
                }
                if (btn) btn.disabled = false;
                if (btnText) btnText.textContent = 'Ya, Siap Main';
                return;
            }

            closeReactivateLiveModal();
            showToast(data.message || `Pemain ${playerName} telah siap dan dimasukkan kembali ke jadwal!`);
            setTimeout(() => {
                window.location.reload();
            }, 800);
        } catch (err) {
            console.error('[reactivatePlayerLive] Error:', err);
            if (errEl && errMsgEl) {
                errMsgEl.textContent = 'Terjadi kesalahan koneksi saat mengaktifkan pemain.';
                errEl.classList.remove('hidden');
            } else {
                showToast('Terjadi kesalahan koneksi saat mengaktifkan pemain.', 'error');
            }
        } finally {
            if (btn) btn.disabled = false;
            if (btnText) btnText.textContent = 'Ya, Siap Main';
        }
    }

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
    renderSessionPlayerReadiness();
    renderLiveBench();
    initSupabaseRealtime();

    // Auto-dismiss Flash Success Banner
    function dismissFlashSuccessBanner() {
        const banner = document.getElementById('flashSuccessBanner');
        if (!banner) return;
        banner.style.transition = 'all 0.5s ease-in-out';
        banner.style.opacity = '0';
        banner.style.transform = 'translateY(-8px)';
        banner.style.maxHeight = '0px';
        banner.style.paddingTop = '0px';
        banner.style.paddingBottom = '0px';
        banner.style.marginTop = '0px';
        banner.style.marginBottom = '0px';
        banner.style.borderWidth = '0px';
        setTimeout(() => {
            banner.remove();
            const container = document.getElementById('flashNotificationContainer');
            if (container && container.children.length === 0) {
                container.remove();
            }
        }, 500);
    }

    if (document.getElementById('flashSuccessBanner')) {
        setTimeout(dismissFlashSuccessBanner, 3500);
    }
</script>
@endpush