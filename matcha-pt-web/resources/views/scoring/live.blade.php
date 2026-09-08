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
                Live Match Scoring Console
            </h1>
            <p class="text-xs text-slate-500 mt-0.5">Host menekan tombol untuk mencatat poin pertandingan secara langsung di lapangan</p>
        </div>

        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-rose-50/90 text-rose-800 border border-rose-200/60 text-xs font-semibold shadow-xs">
            <span class="w-2 h-2 rounded-full bg-rose-600 animate-pulse"></span> MATCH LIVE
        </span>
    </div>

    <!-- Live Scoreboard Display (Subtle Glass) -->
    <div class="glass-card rounded-3xl p-6 sm:p-8 space-y-6 border border-white/90">
        
        <!-- Match Info Header -->
        <div class="flex items-center justify-between text-xs text-slate-500 border-b border-slate-200/50 pb-3">
            <div>
                <strong class="text-slate-800">{{ $game['venue_name'] }}</strong> &bull; {{ $game['court_name'] }}
            </div>
            <div>
                <span class="bg-white/80 px-2.5 py-1 rounded-full border border-slate-200/60 font-medium text-slate-700 shadow-2xs">
                    {{ $game['sport'] }} &bull; Sistem Tennis (0, 15, 30, 40, Deuce, Game)
                </span>
            </div>
        </div>

        <!-- Dynamic Scoreboard -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 items-center">
            
            <!-- Team A Side -->
            <div class="bg-white/70 backdrop-blur-md rounded-2xl p-6 border border-slate-200/70 text-center space-y-4 shadow-xs">
                <span class="inline-block px-2.5 py-0.5 rounded-md bg-emerald-100/80 text-emerald-900 font-extrabold text-[10px] uppercase tracking-wider">
                    TEAM A
                </span>
                
                <div class="space-y-0.5">
                    <h3 class="text-sm font-bold text-slate-900">Billy Santoso</h3>
                    <h3 class="text-sm font-bold text-slate-900">Gisel Anastasia</h3>
                </div>

                <!-- Live Point Big Display -->
                <div class="py-2">
                    <div id="scoreDisplayA" class="text-6xl font-black text-slate-900 tracking-tight">
                        15
                    </div>
                    <span id="setScoreA" class="text-xs text-slate-500 font-semibold block mt-1">Games Won: 2 Sets</span>
                </div>

                <!-- Point Button -->
                <button onclick="addPoint('A')" class="w-full py-3.5 rounded-xl bg-[#1c4927] hover:bg-[#255d33] text-white font-bold text-sm shadow-sm transition-all hover:scale-[1.01] active:scale-95 flex items-center justify-center gap-2">
                    <i class="fa-solid fa-plus text-xs"></i> Tambah Poin Team A
                </button>
            </div>

            <!-- Team B Side -->
            <div class="bg-white/70 backdrop-blur-md rounded-2xl p-6 border border-slate-200/70 text-center space-y-4 shadow-xs">
                <span class="inline-block px-2.5 py-0.5 rounded-md bg-[#eaf3eb] text-[#245b2c] font-extrabold text-[10px] uppercase tracking-wider border border-[#c1dec4]">
                    TEAM B
                </span>

                <div class="space-y-0.5">
                    <h3 class="text-sm font-bold text-slate-900">Fahri Dhani</h3>
                    <h3 class="text-sm font-bold text-slate-900">Davina Putri</h3>
                </div>

                <!-- Live Point Big Display -->
                <div class="py-2">
                    <div id="scoreDisplayB" class="text-6xl font-black text-slate-900 tracking-tight">
                        30
                    </div>
                    <span id="setScoreB" class="text-xs text-slate-500 font-semibold block mt-1">Games Won: 1 Set</span>
                </div>

                <!-- Point Button -->
                <button onclick="addPoint('B')" class="w-full py-3.5 rounded-xl bg-[#2d5237] hover:bg-[#376242] text-white font-bold text-sm shadow-sm transition-all hover:scale-[1.01] active:scale-95 flex items-center justify-center gap-2">
                    <i class="fa-solid fa-plus text-xs"></i> Tambah Poin Team B
                </button>
            </div>
        </div>

        <!-- Score Status Notice -->
        <div id="matchNotice" class="p-3.5 bg-white/80 rounded-xl border border-slate-200/60 text-center text-xs font-medium text-slate-700 shadow-2xs">
            Sistem Tennis: Poin bertahap 0 &rarr; 15 &rarr; 30 &rarr; 40 &rarr; Game (Mendukung Deuce & Advantage).
        </div>

        <!-- Controls: Reset & Finish Game -->
        <div class="flex flex-col sm:flex-row items-center justify-between gap-3 pt-3 border-t border-slate-200/50">
            <button onclick="resetScore()" class="px-3.5 py-2 rounded-xl bg-white hover:bg-slate-50 text-slate-600 text-xs font-semibold border border-slate-200/70 shadow-xs transition-colors flex items-center gap-1.5">
                <i class="fa-solid fa-rotate-left text-[10px]"></i> Reset Skor Game Ini
            </button>

            <button onclick="finishMatch()" class="w-full sm:w-auto px-5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-xs shadow-xs transition-all hover:scale-[1.01] flex items-center justify-center gap-2">
                <i class="fa-solid fa-check text-[11px]"></i> Selesai Match & Simpan Rekap
            </button>
        </div>
    </div>
</div>

@push('scripts')
<script>
    const tennisPoints = ['0', '15', '30', '40'];
    let idxA = 1; // 15
    let idxB = 2; // 30
    let gamesWonA = 2;
    let gamesWonB = 1;
    let isDeuce = false;
    let advantage = null;

    function addPoint(team) {
        if (team === 'A') {
            if (isDeuce) {
                if (advantage === 'A') {
                    gamesWonA++;
                    resetPoints();
                    showToast('🎉 Game Won by Team A!');
                    updateDisplay();
                    return;
                } else if (advantage === 'B') {
                    advantage = null;
                    showToast('Kembali ke Deuce (40 - 40)!');
                    updateDisplay();
                    return;
                } else {
                    advantage = 'A';
                    showToast('Advantage Team A!');
                    updateDisplay();
                    return;
                }
            }

            if (idxA < 3) {
                idxA++;
            } else if (idxA === 3 && idxB < 3) {
                gamesWonA++;
                resetPoints();
                showToast('🎉 Game Won by Team A!');
            } else if (idxA === 3 && idxB === 3) {
                isDeuce = true;
                advantage = 'A';
                showToast('Advantage Team A!');
            }
        } else {
            if (isDeuce) {
                if (advantage === 'B') {
                    gamesWonB++;
                    resetPoints();
                    showToast('🎉 Game Won by Team B!');
                    updateDisplay();
                    return;
                } else if (advantage === 'A') {
                    advantage = null;
                    showToast('Kembali ke Deuce (40 - 40)!');
                    updateDisplay();
                    return;
                } else {
                    advantage = 'B';
                    showToast('Advantage Team B!');
                    updateDisplay();
                    return;
                }
            }

            if (idxB < 3) {
                idxB++;
            } else if (idxB === 3 && idxA < 3) {
                gamesWonB++;
                resetPoints();
                showToast('🎉 Game Won by Team B!');
            } else if (idxB === 3 && idxA === 3) {
                isDeuce = true;
                advantage = 'B';
                showToast('Advantage Team B!');
            }
        }

        if (idxA === 3 && idxB === 3 && !advantage) {
            isDeuce = true;
        }

        updateDisplay();
    }

    function resetPoints() {
        idxA = 0;
        idxB = 0;
        isDeuce = false;
        advantage = null;
    }

    function resetScore() {
        resetPoints();
        gamesWonA = 0;
        gamesWonB = 0;
        updateDisplay();
        showToast('Skor pertandingan di-reset.');
    }

    function updateDisplay() {
        const displayA = document.getElementById('scoreDisplayA');
        const displayB = document.getElementById('scoreDisplayB');
        const notice = document.getElementById('matchNotice');

        if (isDeuce) {
            if (advantage === 'A') {
                displayA.innerText = 'ADV';
                displayB.innerText = '40';
                notice.innerHTML = '<strong class="text-emerald-700">Advantage Team A</strong> (Butuh 1 poin lagi untuk memenangkan game)';
            } else if (advantage === 'B') {
                displayA.innerText = '40';
                displayB.innerText = 'ADV';
                notice.innerHTML = '<strong class="text-slate-900">Advantage Team B</strong> (Butuh 1 poin lagi untuk memenangkan game)';
            } else {
                displayA.innerText = '40';
                displayB.innerText = '40';
                notice.innerHTML = '<strong class="text-amber-700">DEUCE (40 - 40)</strong>';
            }
        } else {
            displayA.innerText = tennisPoints[idxA];
            displayB.innerText = tennisPoints[idxB];
            notice.innerHTML = `Game score: <strong>${gamesWonA}</strong> - <strong>${gamesWonB}</strong> (Sistem Tennis)`;
        }

        document.getElementById('setScoreA').innerText = `Games Won: ${gamesWonA} Set`;
        document.getElementById('setScoreB').innerText = `Games Won: ${gamesWonB} Set`;
    }

    function finishMatch() {
        showToast('Menyimpan data hasil pertandingan...');
        setTimeout(() => {
            window.location.href = "{{ route('scoring.recap', $game['id']) }}";
        }, 1200);
    }
</script>
@endpush
@endsection
