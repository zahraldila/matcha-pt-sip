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
                    Host menekan tombol untuk mencatat poin pertandingan secara langsung di lapangan
                @else
                    Skor diperbarui otomatis setiap beberapa detik &bull; <span class="font-semibold text-[#063B00]">Mode Penonton</span>
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
    <div class="glass-card rounded-3xl p-6 sm:p-8 space-y-6 border border-white/90">
        
        <!-- Match Info Header -->
        <div class="flex items-center justify-between text-xs text-slate-500 border-b border-slate-200/50 pb-3">
            <div>
                <strong class="text-slate-800">{{ $game['venue_name'] }}</strong> &bull; {{ $game['court_name'] }}
            </div>
            <div>
                <span class="bg-white/80 px-2.5 py-1 rounded-full border border-slate-200/60 font-medium text-slate-700 shadow-2xs">
                    {{ $game['sport'] }} &bull; {{ $scoringSystem['label'] }}
                </span>
            </div>
        </div>

        <!-- Dynamic Scoreboard -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 items-center">
            
            <!-- Team A Side -->
            <div class="bg-white/70 backdrop-blur-md rounded-2xl p-6 border border-slate-200/70 text-center space-y-4 shadow-xs">
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

                <!-- Live Point Big Display -->
                <div class="py-2">
                    <div id="scoreDisplayA" class="text-6xl font-black text-slate-900 tracking-tight transition-all duration-300">
                        {{ $currentScore['point_display_a'] ?? ($scoringSystem['type'] === 'tennis' ? '0' : ($currentScore['score_a'] ?? 0)) }}
                    </div>
                    <span id="setScoreA" class="text-xs text-slate-500 font-semibold block mt-1">
                        @if($scoringSystem['type'] === 'tennis')
                            Games Won: {{ $currentScore['games_a'] ?? 0 }} Set
                        @else
                            Poin: {{ $currentScore['score_a'] ?? 0 }}
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
                    <i class="fa-solid fa-eye text-[10px] mr-1"></i> Read-Only
                </div>
                @endif
            </div>

            <!-- Team B Side -->
            <div class="bg-white/70 backdrop-blur-md rounded-2xl p-6 border border-slate-200/70 text-center space-y-4 shadow-xs">
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

                <!-- Live Point Big Display -->
                <div class="py-2">
                    <div id="scoreDisplayB" class="text-6xl font-black text-slate-900 tracking-tight transition-all duration-300">
                        {{ $currentScore['point_display_b'] ?? ($scoringSystem['type'] === 'tennis' ? '0' : ($currentScore['score_b'] ?? 0)) }}
                    </div>
                    <span id="setScoreB" class="text-xs text-slate-500 font-semibold block mt-1">
                        @if($scoringSystem['type'] === 'tennis')
                            Games Won: {{ $currentScore['games_b'] ?? 0 }} Set
                        @else
                            Poin: {{ $currentScore['score_b'] ?? 0 }}
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
                    <i class="fa-solid fa-eye text-[10px] mr-1"></i> Read-Only
                </div>
                @endif
            </div>
        </div>

        <!-- Score Status Notice -->
        <div id="matchNotice" class="p-3.5 bg-white/80 rounded-xl border border-slate-200/60 text-center text-xs font-medium text-slate-700 shadow-2xs">
            @if($scoringSystem['type'] === 'tennis')
                @if(($currentScore['is_deuce'] ?? false))
                    @if(($currentScore['advantage'] ?? null) === 'A')
                        <strong class="text-[#063B00]">Advantage Team A</strong> (Butuh 1 poin lagi untuk memenangkan game)
                    @elseif(($currentScore['advantage'] ?? null) === 'B')
                        <strong class="text-slate-900">Advantage Team B</strong> (Butuh 1 poin lagi untuk memenangkan game)
                    @else
                        <strong class="text-amber-700">DEUCE (40 - 40)</strong>
                    @endif
                @else
                    Game score: <strong>{{ $currentScore['games_a'] ?? 0 }}</strong> &mdash; <strong>{{ $currentScore['games_b'] ?? 0 }}</strong>
                @endif
            @elseif($scoringSystem['type'] === 'americano')
                Americano: Poin <strong>{{ $currentScore['score_a'] ?? 0 }}</strong> vs <strong>{{ $currentScore['score_b'] ?? 0 }}</strong> &bull; Target <strong>{{ $scoringSystem['target'] }} poin</strong>
            @else
                Poin: <strong>{{ $currentScore['score_a'] ?? 0 }}</strong> vs <strong>{{ $currentScore['score_b'] ?? 0 }}</strong>
            @endif
        </div>

        <!-- Match Completed Banner (hidden by default) -->
        <div id="matchCompletedBanner" class="hidden p-4 rounded-2xl bg-[#EBF8D8] border border-[#063B00]/25 text-center space-y-2">
            <div class="text-2xl">🏆</div>
            <p class="text-sm font-black text-[#063B00]" id="completedMsg">Match Selesai!</p>
            <p class="text-xs text-slate-600">Tekan tombol di bawah untuk menyimpan dan melihat rekap.</p>
        </div>

        <!-- Controls: Reset & Finish Game — hanya untuk Host -->
        @if($isHost)
        <div class="flex flex-col sm:flex-row items-center justify-between gap-3 pt-3 border-t border-slate-200/50">
            <button onclick="resetScore()" class="px-3.5 py-2 rounded-xl bg-white hover:bg-slate-50 text-slate-600 text-xs font-semibold border border-slate-200/70 shadow-xs transition-colors flex items-center gap-1.5">
                <i class="fa-solid fa-rotate-left text-[10px]"></i> Reset Skor Game Ini
            </button>

            <form id="finishForm" action="{{ route('scoring.finish') }}" method="POST" class="w-full sm:w-auto">
                @csrf
                <input type="hidden" name="game_id"  value="{{ $game['id'] }}">
                <input type="hidden" name="round"    value="{{ $activeRound }}">
                <input type="hidden" name="score_a"  id="finishScoreA" value="{{ $currentScore['score_a'] }}">
                <input type="hidden" name="score_b"  id="finishScoreB" value="{{ $currentScore['score_b'] }}">

                <button type="submit" onclick="submitFinish(event)"
                    class="w-full sm:w-auto px-5 py-2.5 rounded-xl bg-[#063B00] hover:bg-[#042a00] text-white font-bold text-xs shadow-xs transition-all hover:scale-[1.01] flex items-center justify-center gap-2 cursor-pointer">
                    <i class="fa-solid fa-flag-checkered text-[11px] text-[#A8E63A]"></i> Selesaikan Sesi &amp; Lihat Juara
                </button>
            </form>
        </div>
        @else
        {{-- Member: info bahwa skor diperbarui otomatis --}}
        <div class="flex items-center justify-between pt-3 border-t border-slate-200/50">
            <div class="flex items-center gap-2 text-xs text-slate-500">
                <span class="w-2 h-2 rounded-full bg-sky-400 animate-pulse"></span>
                Skor diperbarui otomatis setiap <strong id="countdownTimer">2</strong> detik
            </div>
            <a href="{{ route('scoring.recap', $game['id']) }}"
               class="px-4 py-2 rounded-xl bg-white border border-slate-200 text-slate-600 text-xs font-semibold hover:bg-slate-50 shadow-2xs transition-colors flex items-center gap-1.5">
                <i class="fa-solid fa-ranking-star text-[10px]"></i> Lihat Klasemen
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
    const SCORING_TYPE   = '{{ $scoringSystem['type'] }}';
    const SCORE_TARGET   = {{ $scoringSystem['target'] ?? 'null' }};
    const GAME_ID        = {{ $game['id'] }};
    const ACTIVE_ROUND   = '{{ $activeRound }}';
    const CSRF_TOKEN     = document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}';
    const UPDATE_URL     = '{{ route('scoring.update-score') }}';
    const IS_HOST        = {{ $isHost ? 'true' : 'false' }};
    const RECAP_URL      = '{{ route('scoring.recap', $game['id']) }}';

    // ── State ────────────────────────────────────────────────────────────────
    const tennisPoints = ['0', '15', '30', '40'];
    let scoreA     = {{ (int) ($currentScore['score_a'] ?? 0) }};
    let scoreB     = {{ (int) ($currentScore['score_b'] ?? 0) }};
    let idxA       = {{ (int) ($currentScore['idx_a'] ?? 0) }};
    let idxB       = {{ (int) ($currentScore['idx_b'] ?? 0) }};
    let gamesA     = {{ (int) ($currentScore['games_a'] ?? 0) }};
    let gamesB     = {{ (int) ($currentScore['games_b'] ?? 0) }};
    let isDeuce    = {{ ($currentScore['is_deuce'] ?? false) ? 'true' : 'false' }};
    let advantage  = {!! json_encode($currentScore['advantage'] ?? null) !!};
    let matchDone  = {{ (($currentScore['status'] ?? '') === 'completed') ? 'true' : 'false' }};

    function getPointDisplays() {
        if (SCORING_TYPE === 'tennis') {
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
        return {
            a: String(scoreA),
            b: String(scoreB),
        };
    }

    // ── Inisialisasi display ─────────────────────────────────────────────────
    updateDisplay();

    // ── Tambah Poin ──────────────────────────────────────────────────────────
    function addPoint(team) {
        if (matchDone) {
            showToast('Match sudah selesai. Tekan "Selesaikan Sesi" untuk melihat rekap.');
            return;
        }

        if (SCORING_TYPE === 'tennis') {
            addTennisPoint(team);
        } else {
            addDirectPoint(team);
        }

        updateDisplay();
        saveScore(); // Auto-save setelah setiap poin
    }

    // ── Mode: Americano / Direct Points ─────────────────────────────────────
    function addDirectPoint(team) {
        if (team === 'A') scoreA++;
        else              scoreB++;

        // Cek apakah sudah mencapai target
        if (SCORE_TARGET && (scoreA >= SCORE_TARGET || scoreB >= SCORE_TARGET)) {
            matchDone = true;
            const winner = scoreA >= SCORE_TARGET ? 'Team A' : 'Team B';
            showToast(`🏆 ${winner} Menang! Match selesai.`);
            showCompletedBanner(winner);
        }
    }

    // ── Mode: Tennis ─────────────────────────────────────────────────────────
    function addTennisPoint(team) {
        if (team === 'A') {
            if (isDeuce) {
                if (advantage === 'A') {
                    gamesA++;
                    resetPoints();
                    showToast('🎉 Game Won by Team A!');
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
                    }
                } else if (idxA === 3 && idxB < 3) {
                    gamesA++;
                    resetPoints();
                    showToast('🎉 Game Won by Team A!');
                }
            }
        } else {
            if (isDeuce) {
                if (advantage === 'B') {
                    gamesB++;
                    resetPoints();
                    showToast('🎉 Game Won by Team B!');
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
                    }
                } else if (idxB === 3 && idxA < 3) {
                    gamesB++;
                    resetPoints();
                    showToast('🎉 Game Won by Team B!');
                }
            }
        }

        // Simpan nilai games won ke scoreA/scoreB
        scoreA = gamesA;
        scoreB = gamesB;
    }

    function resetPoints() {
        idxA = 0;
        idxB = 0;
        isDeuce = false;
        advantage = null;
    }

    // ── Reset Semua ──────────────────────────────────────────────────────────
    function resetScore() {
        resetPoints();
        scoreA = 0; scoreB = 0;
        gamesA = 0; gamesB = 0;
        matchDone = false;
        const banner = document.getElementById('matchCompletedBanner');
        if (banner) banner.classList.add('hidden');
        const bA = document.getElementById('btnAddA');
        const bB = document.getElementById('btnAddB');
        if (bA) bA.disabled = false;
        if (bB) bB.disabled = false;
        updateDisplay();
        showToast('Skor pertandingan di-reset.');
        saveScore('in_progress');
    }

    // ── Update Tampilan ──────────────────────────────────────────────────────
    function updateDisplay() {
        const dispA  = document.getElementById('scoreDisplayA');
        const dispB  = document.getElementById('scoreDisplayB');
        const notice = document.getElementById('matchNotice');
        const setA   = document.getElementById('setScoreA');
        const setB   = document.getElementById('setScoreB');
        const displays = getPointDisplays();

        if (dispA) dispA.innerText = displays.a;
        if (dispB) dispB.innerText = displays.b;

        if (SCORING_TYPE === 'tennis') {
            if (setA) setA.innerText = `Games Won: ${gamesA} Set`;
            if (setB) setB.innerText = `Games Won: ${gamesB} Set`;

            if (notice) {
                if (isDeuce) {
                    if (advantage === 'A') {
                        notice.innerHTML = '<strong class="text-[#063B00]">Advantage Team A</strong> (Butuh 1 poin lagi untuk memenangkan game)';
                    } else if (advantage === 'B') {
                        notice.innerHTML = '<strong class="text-slate-900">Advantage Team B</strong> (Butuh 1 poin lagi untuk memenangkan game)';
                    } else {
                        notice.innerHTML = '<strong class="text-amber-700">DEUCE (40 - 40)</strong>';
                    }
                } else {
                    notice.innerHTML = `Game score: <strong>${gamesA}</strong> — <strong>${gamesB}</strong>`;
                }
            }
        } else {
            if (setA) setA.innerText = `Poin: ${scoreA}`;
            if (setB) setB.innerText = `Poin: ${scoreB}`;

            if (notice) {
                const target = SCORE_TARGET;
                if (target) {
                    notice.innerHTML = `Poin saat ini: <strong>${scoreA}</strong> vs <strong>${scoreB}</strong> — Target <strong>${target}</strong> poin.`;
                } else {
                    notice.innerHTML = `Skor: <strong>${scoreA}</strong> vs <strong>${scoreB}</strong>`;
                }
            }
        }

        // Update hidden input finish form (hanya ada di tampilan host)
        const finA = document.getElementById('finishScoreA');
        const finB = document.getElementById('finishScoreB');
        if (finA) finA.value = (SCORING_TYPE === 'tennis' && gamesA === 0 && gamesB === 0) ? idxA : scoreA;
        if (finB) finB.value = (SCORING_TYPE === 'tennis' && gamesA === 0 && gamesB === 0) ? idxB : scoreB;
    }

    // ── Banner Match Selesai ─────────────────────────────────────────────────
    function showCompletedBanner(winner) {
        const banner = document.getElementById('matchCompletedBanner');
        const msg    = document.getElementById('completedMsg');
        if (msg) msg.textContent = `🏆 ${winner} Menang! Skor Akhir: ${scoreA} — ${scoreB}`;
        if (banner) banner.classList.remove('hidden');
        const bA = document.getElementById('btnAddA');
        const bB = document.getElementById('btnAddB');
        if (bA) bA.disabled = true;
        if (bB) bB.disabled = true;
    }

    // ── Simpan Skor ke Server (AJAX) ─────────────────────────────────────────
    async function saveScore(status = null) {
        const displays = getPointDisplays();
        const body = {
            game_id         : GAME_ID,
            round           : ACTIVE_ROUND,
            scoring_type    : SCORING_TYPE,
            score_a         : scoreA,
            score_b         : scoreB,
            point_display_a : displays.a,
            point_display_b : displays.b,
            games_a         : gamesA,
            games_b         : gamesB,
            idx_a           : idxA,
            idx_b           : idxB,
            is_deuce        : isDeuce,
            advantage       : advantage,
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
        if (finA) finA.value = (SCORING_TYPE === 'tennis' && gamesA === 0 && gamesB === 0) ? idxA : scoreA;
        if (finB) finB.value = (SCORING_TYPE === 'tennis' && gamesA === 0 && gamesB === 0) ? idxB : scoreB;
        showToast('Menyimpan data hasil pertandingan...');
    }

    // ── Auto-Refresh untuk Member & Penonton (Real-time Polling via fetch) ────
    if (!IS_HOST) {
        const POLL_URL      = `{{ url('scoring/get-score') }}/${GAME_ID}/${ACTIVE_ROUND}`;
        const POLL_INTERVAL = 1500; // 1.5 detik
        let   countdown     = 2;
        const timerEl       = document.getElementById('countdownTimer');

        // Countdown display
        setInterval(() => {
            countdown--;
            if (timerEl) timerEl.textContent = countdown <= 0 ? 2 : countdown;
            if (countdown <= 0) countdown = 2;
        }, 1000);

        // Polling: fetch skor terbaru dari server setiap 1.5 detik
        setInterval(async () => {
            try {
                const res  = await fetch(POLL_URL, { headers: { 'Accept': 'application/json' } });
                if (!res.ok) return;
                const data = await res.json();

                const dispA  = document.getElementById('scoreDisplayA');
                const dispB  = document.getElementById('scoreDisplayB');
                const setA   = document.getElementById('setScoreA');
                const setB   = document.getElementById('setScoreB');
                const notice = document.getElementById('matchNotice');

                const targetA = String(data.point_display_a ?? data.score_a ?? '0');
                const targetB = String(data.point_display_b ?? data.score_b ?? '0');

                // Update Big Displays dengan micro-animation pop
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

                // Update sub-label & notice
                if (data.scoring_type === 'tennis') {
                    const gA = data.games_a ?? 0;
                    const gB = data.games_b ?? 0;
                    if (setA) setA.innerText = `Games Won: ${gA} Set`;
                    if (setB) setB.innerText = `Games Won: ${gB} Set`;

                    if (notice) {
                        if (data.is_deuce) {
                            if (data.advantage === 'A') {
                                notice.innerHTML = '<strong class="text-[#063B00]">Advantage Team A</strong> (Butuh 1 poin lagi untuk memenangkan game)';
                            } else if (data.advantage === 'B') {
                                notice.innerHTML = '<strong class="text-slate-900">Advantage Team B</strong> (Butuh 1 poin lagi untuk memenangkan game)';
                            } else {
                                notice.innerHTML = '<strong class="text-amber-700">DEUCE (40 - 40)</strong>';
                            }
                        } else {
                            notice.innerHTML = `Game score: <strong>${gA}</strong> — <strong>${gB}</strong>`;
                        }
                    }
                } else {
                    if (setA) setA.innerText = `Poin: ${data.score_a}`;
                    if (setB) setB.innerText = `Poin: ${data.score_b}`;

                    if (notice) {
                        if (SCORE_TARGET) {
                            notice.innerHTML = `Poin saat ini: <strong>${data.score_a}</strong> vs <strong>${data.score_b}</strong> — Target <strong>${SCORE_TARGET}</strong> poin.`;
                        } else {
                            notice.innerHTML = `Skor: <strong>${data.score_a}</strong> — <strong>${data.score_b}</strong>`;
                        }
                    }
                }

                // Jika match selesai, tampilkan banner
                if (data.status === 'completed') {
                    const banner = document.getElementById('matchCompletedBanner');
                    if (banner && banner.classList.contains('hidden')) {
                        banner.classList.remove('hidden');
                        const msg = document.getElementById('completedMsg');
                        if (msg) {
                            const winner = ((data.games_a ?? data.score_a) >= (data.games_b ?? data.score_b)) ? 'Team A' : 'Team B';
                            msg.textContent = `🏆 ${winner} Menang! Match Selesai.`;
                        }
                    }
                }
            } catch (e) {
                // Silent fail
            }
        }, POLL_INTERVAL);
    }
</script>
@endpush
@endsection
