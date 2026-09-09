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

    {{-- Round Selector (jika ada multiple rounds) --}}
    @if(($matchContext['total_rounds'] ?? 0) > 1)
    <div class="flex items-center gap-2 flex-wrap">
        <span class="text-xs font-semibold text-slate-500">Pilih Round:</span>
        @foreach(($matchContext['all_rounds'] ?? []) as $rKey)
            <a href="{{ route('scoring.live', $game['id']) }}?round={{ $rKey }}"
               class="px-3 py-1 rounded-xl text-xs font-bold border transition-all
                      {{ $activeRound === $rKey
                           ? 'bg-[#063B00] text-white border-[#063B00]'
                           : 'bg-white text-slate-600 border-slate-200 hover:border-[#063B00]/40' }}">
                {{ ucfirst(str_replace('_', ' ', $rKey)) }}
            </a>
        @endforeach
    </div>
    @endif

    <!-- Live Scoreboard Display (Subtle Glass) -->
    <div class="glass-card rounded-3xl p-6 sm:p-8 space-y-6 border border-white/90 shadow-sm">
        
        <!-- Match Info Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 text-xs text-slate-500 border-b border-slate-200/50 pb-3">
            <div>
                <strong class="text-slate-800">{{ $game['venue_name'] }}</strong> &bull; {{ $game['court_name'] }}
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                <span class="bg-[#063B00] text-white px-2.5 py-1 rounded-full font-bold text-[11px] shadow-2xs">
                    {{ $game['sport'] }}
                </span>
                <span class="bg-white/90 px-3 py-1 rounded-full border border-slate-200/80 font-bold text-slate-800 shadow-2xs">
                    Format: {{ $scoringSystem['label'] }}
                </span>
            </div>
        </div>

        {{-- SET / GAME HEADER BAR --}}
        @if($scoringSystem['is_sets'])
        <!-- SETS TRACKER (Total of X Sets) -->
        <div class="bg-slate-900 text-white rounded-2xl p-4 sm:p-5 text-center space-y-2 shadow-sm relative overflow-hidden">
            <div class="flex items-center justify-between text-xs text-slate-300 border-b border-slate-800 pb-2">
                <span class="font-bold uppercase tracking-wider text-[#A8E63A]">
                    <i class="fa-solid fa-trophy text-[11px] mr-1"></i> SET SCORE
                </span>
                <span class="font-medium text-slate-400">
                    Target: <strong class="text-white">{{ $scoringSystem['target_sets'] }} Set</strong> (Maks. {{ $scoringSystem['max_sets'] }} Set)
                </span>
            </div>

            <div class="flex items-center justify-center gap-6 sm:gap-10 py-1">
                <div class="text-center">
                    <span class="text-[10px] uppercase font-bold text-slate-400 block">Set A</span>
                    <span id="displaySetScoreA" class="text-3xl sm:text-4xl font-black text-white">
                        {{ $currentScore['sets_a'] ?? 0 }}
                    </span>
                </div>
                <div class="text-slate-500 font-black text-2xl sm:text-3xl">&mdash;</div>
                <div class="text-center">
                    <span class="text-[10px] uppercase font-bold text-slate-400 block">Set B</span>
                    <span id="displaySetScoreB" class="text-3xl sm:text-4xl font-black text-white">
                        {{ $currentScore['sets_b'] ?? 0 }}
                    </span>
                </div>
            </div>

            <!-- Set History Chips -->
            <div id="setHistoryContainer" class="flex items-center justify-center gap-2 flex-wrap pt-1 text-[11px]">
                <span class="text-slate-400 font-semibold" id="currentSetLabel">
                    Set {{ $currentScore['set_number'] ?? 1 }} Sedang Berlangsung:
                </span>
                <span id="currentSetGamesBadge" class="px-2.5 py-0.5 rounded-md bg-white/10 text-[#A8E63A] font-bold border border-white/10">
                    Game: {{ $currentScore['games_a'] ?? 0 }} &mdash; {{ $currentScore['games_b'] ?? 0 }}
                </span>
                <div id="setChipsWrapper" class="inline-flex items-center gap-1.5">
                    {{-- Dynamically rendered past sets --}}
                </div>
            </div>
        </div>
        @else
        <!-- GAME TARGET TRACKER (First to X Games) -->
        <div class="bg-slate-900 text-white rounded-2xl p-4 sm:p-5 text-center space-y-2 shadow-sm">
            <div class="flex items-center justify-between text-xs text-slate-300 border-b border-slate-800 pb-2">
                <span class="font-bold uppercase tracking-wider text-[#A8E63A]">
                    <i class="fa-solid fa-gamepad text-[11px] mr-1"></i> GAME SCORE
                </span>
                <span class="font-medium text-slate-400">
                    Target Menang: <strong class="text-[#A8E63A]">{{ $scoringSystem['target_games'] }} Game</strong>
                </span>
            </div>

            <div class="flex items-center justify-center gap-6 sm:gap-10 py-1">
                <div class="text-center">
                    <span class="text-[10px] uppercase font-bold text-slate-400 block">Games Team A</span>
                    <span id="displayGameScoreA" class="text-3xl sm:text-4xl font-black text-white">
                        {{ $currentScore['games_a'] ?? 0 }}
                    </span>
                </div>
                <div class="text-slate-500 font-black text-2xl sm:text-3xl">&mdash;</div>
                <div class="text-center">
                    <span class="text-[10px] uppercase font-bold text-slate-400 block">Games Team B</span>
                    <span id="displayGameScoreB" class="text-3xl sm:text-4xl font-black text-white">
                        {{ $currentScore['games_b'] ?? 0 }}
                    </span>
                </div>
            </div>
            <p class="text-[11px] text-slate-400 font-medium">
                Pemain pertama yang mencapai <strong class="text-white">{{ $scoringSystem['target_games'] }} Game</strong> memenangkan pertandingan.
            </p>
        </div>
        @endif

        <!-- Dynamic Scoreboard: POINT SCORING (0 -> 15 -> 30 -> 40 -> Game) -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 items-center">
            
            <!-- Team A Side -->
            <div class="bg-white/80 backdrop-blur-md rounded-2xl p-6 border border-slate-200/80 text-center space-y-4 shadow-xs relative">
                <span class="inline-block px-2.5 py-0.5 rounded-md bg-[#EBF8D8] text-[#063B00] border border-[#063B00]/25 font-extrabold text-[10px] uppercase tracking-wider">
                    TEAM A
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
                        @if($scoringSystem['is_sets'])
                            Current Set: {{ $currentScore['games_a'] ?? 0 }} Game &bull; Total: {{ $currentScore['sets_a'] ?? 0 }} Set
                        @else
                            Games Won: {{ $currentScore['games_a'] ?? 0 }} / {{ $scoringSystem['target_games'] }} Game
                        @endif
                    </span>
                </div>

                <!-- Point Button — hanya tampil untuk Host -->
                @if($isHost)
                <button id="btnAddA" onclick="addPoint('A')" class="w-full py-3.5 rounded-xl bg-[#063B00] hover:bg-[#042a00] text-white font-bold text-sm shadow-sm transition-all hover:scale-[1.01] active:scale-95 flex items-center justify-center gap-2 cursor-pointer">
                    <i class="fa-solid fa-plus text-xs text-[#A8E63A]"></i> Tambah Poin Team A
                </button>
                @else
                <div class="w-full py-2.5 rounded-xl bg-slate-50 border border-slate-200/70 text-center text-xs text-slate-400 font-medium">
                    <i class="fa-solid fa-eye text-[10px] mr-1"></i> Read-Only (Member View)
                </div>
                @endif
            </div>

            <!-- Team B Side -->
            <div class="bg-white/80 backdrop-blur-md rounded-2xl p-6 border border-slate-200/80 text-center space-y-4 shadow-xs relative">
                <span class="inline-block px-2.5 py-0.5 rounded-md bg-[#EBF8D8] text-[#063B00] border border-[#063B00]/25 font-extrabold text-[10px] uppercase tracking-wider">
                    TEAM B
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
                        @if($scoringSystem['is_sets'])
                            Current Set: {{ $currentScore['games_b'] ?? 0 }} Game &bull; Total: {{ $currentScore['sets_b'] ?? 0 }} Set
                        @else
                            Games Won: {{ $currentScore['games_b'] ?? 0 }} / {{ $scoringSystem['target_games'] }} Game
                        @endif
                    </span>
                </div>

                <!-- Point Button — hanya tampil untuk Host -->
                @if($isHost)
                <button id="btnAddB" onclick="addPoint('B')" class="w-full py-3.5 rounded-xl bg-[#063B00] hover:bg-[#042a00] text-white font-bold text-sm shadow-sm transition-all hover:scale-[1.01] active:scale-95 flex items-center justify-center gap-2 cursor-pointer">
                    <i class="fa-solid fa-plus text-xs text-[#A8E63A]"></i> Tambah Poin Team B
                </button>
                @else
                <div class="w-full py-2.5 rounded-xl bg-slate-50 border border-slate-200/70 text-center text-xs text-slate-400 font-medium">
                    <i class="fa-solid fa-eye text-[10px] mr-1"></i> Read-Only (Member View)
                </div>
                @endif
            </div>
        </div>

        <!-- Score Status Notice -->
        <div id="matchNotice" class="p-3.5 bg-white/90 rounded-xl border border-slate-200/70 text-center text-xs font-semibold text-slate-700 shadow-2xs">
            Point: <strong>0 &mdash; 0</strong> &bull; <em>Game sedang berlangsung</em>
        </div>

        <!-- Match Completed Banner (hidden by default) -->
        <div id="matchCompletedBanner" class="hidden p-5 rounded-2xl bg-[#EBF8D8] border border-[#063B00]/30 text-center space-y-2 shadow-sm">
            <div class="text-3xl">🏆</div>
            <p class="text-base font-black text-[#063B00]" id="completedMsg">Match Selesai!</p>
            <p class="text-xs text-slate-600 font-medium">Pertandingan telah dimenangkan. Klik tombol di bawah untuk menyimpan dan melihat hasil rekap.</p>
        </div>

        <!-- Controls: Reset & Finish Game — hanya untuk Host -->
        @if($isHost)
        <div class="flex flex-col sm:flex-row items-center justify-between gap-3 pt-3 border-t border-slate-200/50">
            <div class="flex items-center gap-2">
                <button onclick="resetCurrentGamePoint()" class="px-3 py-2 rounded-xl bg-white hover:bg-slate-50 text-slate-600 text-xs font-semibold border border-slate-200/80 shadow-2xs transition-colors flex items-center gap-1.5 cursor-pointer">
                    <i class="fa-solid fa-arrow-rotate-left text-[10px]"></i> Reset Poin Game Ini
                </button>
                <button onclick="resetFullMatch()" class="px-3 py-2 rounded-xl bg-rose-50 hover:bg-rose-100 text-rose-700 text-xs font-semibold border border-rose-200 shadow-2xs transition-colors flex items-center gap-1.5 cursor-pointer">
                    <i class="fa-solid fa-trash-can text-[10px]"></i> Reset Skor Match
                </button>
            </div>

            <form id="finishForm" action="{{ route('scoring.finish') }}" method="POST" class="w-full sm:w-auto">
                @csrf
                <input type="hidden" name="game_id"         value="{{ $game['id'] }}">
                <input type="hidden" name="round"           value="{{ $activeRound }}">
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

                <button type="submit" onclick="submitFinish(event)"
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

    {{-- Tim Istirahat --}}
    @if(!empty($matchContext['resting']))
    <div class="glass-card rounded-2xl px-5 py-4 border border-white/90 flex items-center gap-3">
        <div class="w-8 h-8 rounded-xl bg-slate-100 flex items-center justify-center text-slate-500 text-xs shrink-0">
            <i class="fa-solid fa-couch"></i>
        </div>
        <div>
            <p class="text-xs font-bold text-slate-700">Pemain Istirahat (Resting)</p>
            <p class="text-xs text-slate-500">{{ implode(' &bull; ', $matchContext['resting']) }}</p>
        </div>
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
    const TARGET_GAMES   = {{ $scoringSystem['target_games'] ?? 8 }};
    const GAME_ID        = {{ $game['id'] }};
    const ACTIVE_ROUND   = '{{ $activeRound }}';
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

    let gamesA       = {{ (int) ($currentScore['games_a'] ?? 0) }};
    let gamesB       = {{ (int) ($currentScore['games_b'] ?? 0) }};

    let setNumber    = {{ (int) ($currentScore['set_number'] ?? 1) }};
    let setsA        = {{ (int) ($currentScore['sets_a'] ?? 0) }};
    let setsB        = {{ (int) ($currentScore['sets_b'] ?? 0) }};
    let setHistory   = {!! json_encode($currentScore['set_history'] ?? []) !!} || [];

    let matchDone    = {{ (($currentScore['status'] ?? '') === 'completed') ? 'true' : 'false' }};
    let winnerTeam   = {!! json_encode($currentScore['winner_team'] ?? null) !!};

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
            showToast('Pertandingan sudah selesai. Selesaikan sesi untuk melihat rekap.');
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
                // Team A wins game!
                gameWonBy('A');
            } else if (advantage === 'B') {
                // B loses advantage, back to deuce
                advantage = null;
                showToast('Kembali ke Deuce (40 - 40)!');
            } else {
                // Was 40-40, A gains advantage
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
                // Team A wins game!
                gameWonBy('A');
            }
        }
    }

    function handlePointWonByB() {
        if (isDeuce) {
            if (advantage === 'B') {
                // Team B wins game!
                gameWonBy('B');
            } else if (advantage === 'A') {
                // A loses advantage, back to deuce
                advantage = null;
                showToast('Kembali ke Deuce (40 - 40)!');
            } else {
                // Was 40-40, B gains advantage
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
                // Team B wins game!
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

        if (IS_SETS) {
            // Evaluasi apakah set selesai (Tennis set rule: 6 games with margin 2, or 7-5, or 7-6)
            checkSetWinner();
        } else {
            // First to X Games: cek apakah mencapai target game
            checkFirstToGamesWinner();
        }
    }

    function checkSetWinner() {
        let setWon = null;
        if ((gamesA >= 6 && gamesA - gamesB >= 2) || (gamesA === 7 && gamesB === 6)) {
            setWon = 'A';
        } else if ((gamesB >= 6 && gamesB - gamesA >= 2) || (gamesB === 7 && gamesA === 6)) {
            setWon = 'B';
        }

        if (setWon) {
            // Catat ke setHistory
            setHistory.push({
                set: setNumber,
                score_a: gamesA,
                score_b: gamesB,
            });

            if (setWon === 'A') {
                setsA++;
                showToast(`🏆 Team A memenangkan Set ${setNumber} (${gamesA} - ${gamesB})!`);
            } else {
                setsB++;
                showToast(`🏆 Team B memenangkan Set ${setNumber} (${gamesB} - ${gamesA})!`);
            }

            // Reset games untuk set berikutnya
            gamesA = 0;
            gamesB = 0;
            setNumber++;

            // Cek apakah match selesai (mencapai target sets)
            if (setsA >= TARGET_SETS) {
                matchDone = true;
                winnerTeam = 'Team A';
                showCompletedBanner('Team A');
            } else if (setsB >= TARGET_SETS) {
                matchDone = true;
                winnerTeam = 'Team B';
                showCompletedBanner('Team B');
            }
        }
    }

    function checkFirstToGamesWinner() {
        if (gamesA >= TARGET_GAMES) {
            matchDone = true;
            winnerTeam = 'Team A';
            showCompletedBanner('Team A');
        } else if (gamesB >= TARGET_GAMES) {
            matchDone = true;
            winnerTeam = 'Team B';
            showCompletedBanner('Team B');
        }
    }

    function resetPoints() {
        idxA = 0;
        idxB = 0;
        isDeuce = false;
        advantage = null;
    }

    function resetCurrentGamePoint() {
        resetPoints();
        updateDisplay();
        showToast('Poin game ini di-reset ke 0 - 0.');
        saveScore();
    }

    function resetFullMatch() {
        if (!confirm('Yakin ingin mereset seluruh skor match ini dari awal?')) return;
        resetPoints();
        gamesA = 0;
        gamesB = 0;
        setsA = 0;
        setsB = 0;
        setNumber = 1;
        setHistory = [];
        matchDone = false;
        winnerTeam = null;

        const banner = document.getElementById('matchCompletedBanner');
        if (banner) banner.classList.add('hidden');
        const bA = document.getElementById('btnAddA');
        const bB = document.getElementById('btnAddB');
        if (bA) bA.disabled = false;
        if (bB) bB.disabled = false;

        updateDisplay();
        showToast('Seluruh skor match telah di-reset.');
        saveScore('in_progress');
    }

    // ── Update Display UI ────────────────────────────────────────────────────
    function updateDisplay() {
        const dispA  = document.getElementById('scoreDisplayA');
        const dispB  = document.getElementById('scoreDisplayB');
        const subA   = document.getElementById('subScoreLabelA');
        const subB   = document.getElementById('subScoreLabelB');
        const notice = document.getElementById('matchNotice');
        const displays = getPointDisplays();

        if (dispA) dispA.innerText = displays.a;
        if (dispB) dispB.innerText = displays.b;

        if (IS_SETS) {
            // Update Sets Tracker
            const setDispA = document.getElementById('displaySetScoreA');
            const setDispB = document.getElementById('displaySetScoreB');
            if (setDispA) setDispA.innerText = setsA;
            if (setDispB) setDispB.innerText = setsB;

            const curSetLabel = document.getElementById('currentSetLabel');
            if (curSetLabel) curSetLabel.innerText = `Set ${setNumber} Sedang Berlangsung:`;

            const curBadge = document.getElementById('currentSetGamesBadge');
            if (curBadge) curBadge.innerText = `Game: ${gamesA} — ${gamesB}`;

            // Render past set chips
            const chipsWrapper = document.getElementById('setChipsWrapper');
            if (chipsWrapper) {
                if (setHistory.length > 0) {
                    chipsWrapper.innerHTML = setHistory.map(s => 
                        `<span class="px-2 py-0.5 rounded-md bg-white/20 text-white font-semibold">Set ${s.set}: ${s.score_a}-${s.score_b}</span>`
                    ).join('');
                } else {
                    chipsWrapper.innerHTML = '';
                }
            }

            if (subA) subA.innerText = `Current Set: ${gamesA} Game • Total: ${setsA} Set`;
            if (subB) subB.innerText = `Current Set: ${gamesB} Game • Total: ${setsB} Set`;

            if (notice) {
                if (isDeuce) {
                    if (advantage === 'A') {
                        notice.innerHTML = '<strong class="text-[#063B00]">ADVANTAGE TEAM A</strong> &bull; Butuh 1 poin lagi untuk memenangkan game';
                    } else if (advantage === 'B') {
                        notice.innerHTML = '<strong class="text-slate-900">ADVANTAGE TEAM B</strong> &bull; Butuh 1 poin lagi untuk memenangkan game';
                    } else {
                        notice.innerHTML = '<strong class="text-amber-700">DEUCE (40 - 40)</strong> &bull; Perebutan advantage point';
                    }
                } else {
                    notice.innerHTML = `Set ${setNumber} &bull; Game score: <strong>${gamesA}</strong> — <strong>${gamesB}</strong> &bull; Point: <strong>${displays.a} : ${displays.b}</strong>`;
                }
            }
        } else {
            // Update First to Games Tracker
            const gameDispA = document.getElementById('displayGameScoreA');
            const gameDispB = document.getElementById('displayGameScoreB');
            if (gameDispA) gameDispA.innerText = gamesA;
            if (gameDispB) gameDispB.innerText = gamesB;

            if (subA) subA.innerText = `Games Won: ${gamesA} / ${TARGET_GAMES} Game`;
            if (subB) subB.innerText = `Games Won: ${gamesB} / ${TARGET_GAMES} Game`;

            if (notice) {
                if (isDeuce) {
                    if (advantage === 'A') {
                        notice.innerHTML = '<strong class="text-[#063B00]">ADVANTAGE TEAM A</strong> &bull; Butuh 1 poin lagi untuk memenangkan game';
                    } else if (advantage === 'B') {
                        notice.innerHTML = '<strong class="text-slate-900">ADVANTAGE TEAM B</strong> &bull; Butuh 1 poin lagi untuk memenangkan game';
                    } else {
                        notice.innerHTML = '<strong class="text-amber-700">DEUCE (40 - 40)</strong> &bull; Perebutan advantage point';
                    }
                } else {
                    notice.innerHTML = `Game score: <strong>${gamesA}</strong> — <strong>${gamesB}</strong> (Target ${TARGET_GAMES}) &bull; Point: <strong>${displays.a} : ${displays.b}</strong>`;
                }
            }
        }

        if (matchDone && winnerTeam) {
            showCompletedBanner(winnerTeam);
        }
    }

    // ── Banner Match Selesai ─────────────────────────────────────────────────
    function showCompletedBanner(winner) {
        const banner = document.getElementById('matchCompletedBanner');
        const msg    = document.getElementById('completedMsg');
        let scoreSummary = '';
        if (IS_SETS) {
            scoreSummary = `Set Score: ${setsA} — ${setsB}`;
            if (setHistory.length > 0) {
                scoreSummary += ' (' + setHistory.map(s => `${s.score_a}-${s.score_b}`).join(', ') + ')';
            }
        } else {
            scoreSummary = `Game Score: ${gamesA} — ${gamesB} (Target ${TARGET_GAMES})`;
        }

        if (msg) msg.textContent = `🏆 ${winner} Memenangkan Pertandingan! ${scoreSummary}`;
        if (banner) banner.classList.remove('hidden');

        const bA = document.getElementById('btnAddA');
        const bB = document.getElementById('btnAddB');
        if (bA) bA.disabled = true;
        if (bB) bB.disabled = true;
    }

    // ── Simpan Skor ke Server (AJAX Polling / Cache) ───────────────────────────
    async function saveScore(status = null) {
        const displays = getPointDisplays();
        const body = {
            game_id         : GAME_ID,
            round           : ACTIVE_ROUND,
            scoring_type    : SCORING_TYPE,
            score_a         : IS_SETS ? setsA : gamesA,
            score_b         : IS_SETS ? setsB : gamesB,
            point_display_a : displays.a,
            point_display_b : displays.b,
            set_number      : setNumber,
            sets_a          : setsA,
            sets_b          : setsB,
            games_a         : gamesA,
            games_b         : gamesB,
            set_history     : setHistory,
            idx_a           : idxA,
            idx_b           : idxB,
            is_deuce        : isDeuce,
            advantage       : advantage,
            winner_team     : winnerTeam,
            status          : status ?? (matchDone ? 'completed' : 'in_progress'),
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
            if (!res.ok) {
                console.error('Update score failed:', await res.text());
            }
        } catch (err) {
            console.warn('Gagal simpan skor:', err);
        }
    }

    // ── Submit Form Finish ────────────────────────────────────────────────────
    function submitFinish(event) {
        const finA = document.getElementById('finishScoreA');
        const finB = document.getElementById('finishScoreB');
        const finSetsA = document.getElementById('finishSetsA');
        const finSetsB = document.getElementById('finishSetsB');
        const finGamesA = document.getElementById('finishGamesA');
        const finGamesB = document.getElementById('finishGamesB');
        const finSetNum = document.getElementById('finishSetNumber');
        const finPDispA = document.getElementById('finishPointDisplayA');
        const finPDispB = document.getElementById('finishPointDisplayB');
        const finHistory = document.getElementById('finishSetHistory');
        const finWinner = document.getElementById('finishWinnerTeam');

        let finalHistory = Array.isArray(setHistory) ? [...setHistory] : [];
        if (IS_SETS) {
            // Jika ada games di set aktif yang belum tercatat ke history
            const alreadyLogged = finalHistory.some(s => s.set === setNumber);
            if (!alreadyLogged && (gamesA > 0 || gamesB > 0)) {
                finalHistory.push({
                    set: setNumber,
                    score_a: gamesA,
                    score_b: gamesB,
                });
            }

            // Hitung sets dari finalHistory
            let calcSetsA = 0;
            let calcSetsB = 0;
            finalHistory.forEach(s => {
                if (s.score_a > s.score_b) calcSetsA++;
                else if (s.score_b > s.score_a) calcSetsB++;
            });

            if (calcSetsA === 0 && calcSetsB === 0 && (gamesA > 0 || gamesB > 0)) {
                if (gamesA >= gamesB) calcSetsA = 1;
                else calcSetsB = 1;
            }

            setsA = Math.max(setsA, calcSetsA);
            setsB = Math.max(setsB, calcSetsB);

            if (!winnerTeam) {
                winnerTeam = setsA >= setsB ? 'Team A' : 'Team B';
            }
        } else {
            if (!winnerTeam) {
                winnerTeam = gamesA >= gamesB ? 'Team A' : 'Team B';
            }
        }

        const displays = getPointDisplays();
        if (finPDispA) finPDispA.value = displays.a;
        if (finPDispB) finPDispB.value = displays.b;
        if (finSetsA) finSetsA.value = setsA;
        if (finSetsB) finSetsB.value = setsB;
        if (finGamesA) finGamesA.value = gamesA;
        if (finGamesB) finGamesB.value = gamesB;
        if (finSetNum) finSetNum.value = setNumber;
        if (finHistory) finHistory.value = JSON.stringify(finalHistory);

        if (IS_SETS) {
            finA.value = setsA;
            finB.value = setsB;
        } else {
            finA.value = gamesA;
            finB.value = gamesB;
        }
        if (finWinner) finWinner.value = winnerTeam;

        showToast('Menyimpan hasil akhir pertandingan...');
    }

    // ── Auto-Refresh Realtime untuk Member / Penonton (Polling via fetch) ─────
    if (!IS_HOST) {
        const POLL_URL      = `{{ url('scoring/get-score') }}/${GAME_ID}/${ACTIVE_ROUND}`;
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
                gamesA      = data.games_a ?? 0;
                gamesB      = data.games_b ?? 0;
                setsA       = data.sets_a ?? 0;
                setsB       = data.sets_b ?? 0;
                setNumber   = data.set_number ?? 1;
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
