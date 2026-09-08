@extends('layouts.app')

@section('content')
<div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
    
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-200/50 pb-4">
        <div>
            <a href="{{ route('games.show', $game['id']) }}" class="text-xs text-slate-500 hover:text-slate-800 inline-flex items-center gap-1.5 mb-2 transition-colors">
                <i class="fa-solid fa-arrow-left"></i> Kembali ke Detail Game
            </a>
            <h1 class="text-2xl font-bold text-slate-900">
                Drawing & Pembagian Tim
            </h1>
            <p class="text-xs text-slate-500 mt-0.5">Sistem membagi pemain secara seimbang berdasarkan level pemain</p>
        </div>

        <div class="flex items-center gap-2.5">
            <button id="shuffleBtn" onclick="runDrawingAnimation()" class="px-4 py-2 rounded-xl bg-white/80 hover:bg-white border border-slate-200/80 text-slate-800 font-semibold text-xs shadow-xs transition-all flex items-center gap-1.5">
                <i class="fa-solid fa-arrows-rotate text-slate-500" id="shuffleIcon"></i> Acak Ulang Tim
            </button>
            <a href="{{ route('scoring.live', $game['id']) }}" class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-xs shadow-xs transition-all hover:scale-[1.01] flex items-center gap-1.5">
                <span>Mulai Scoring</span> <i class="fa-solid fa-arrow-right text-[10px]"></i>
            </a>
        </div>
    </div>

    <!-- Match Rounds Tab Selector -->
    <div class="flex items-center gap-2 overflow-x-auto pb-1 border-b border-slate-200/50">
        <button onclick="switchRound(1)" id="tabRound1" class="px-3.5 py-1.5 rounded-xl text-xs font-semibold transition-all bg-[#163820] text-white shadow-xs">
            Ronde 1 (Pembuka)
        </button>
        <button onclick="switchRound(2)" id="tabRound2" class="px-3.5 py-1.5 rounded-xl text-xs font-semibold transition-all glass-card text-slate-600 hover:text-slate-900">
            Ronde 2 (Rotasi)
        </button>
        <button onclick="switchRound(3)" id="tabRound3" class="px-3.5 py-1.5 rounded-xl text-xs font-semibold transition-all glass-card text-slate-600 hover:text-slate-900">
            Ronde 3 (Final)
        </button>
    </div>

    <!-- Drawing Visual Presentation -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
        
        <!-- Left: Court Graphic Visualizer (2 Cols) -->
        <div class="lg:col-span-2 space-y-4">
            <div id="courtContainer">
                <x-court-visual 
                    :sport="$game['sport']"
                    :teamA="['Billy Santoso (Host)', 'Gisel Anastasia']"
                    :teamB="['Fahri Dhani', 'Davina Putri']"
                    :resting="['Andi Wijaya', 'Marame Nagoan']"
                />
            </div>

            <!-- Match Settings Summary -->
            <div class="glass-card rounded-2xl p-4 flex items-center justify-between text-xs text-slate-500">
                <span>Format: <strong class="text-slate-800">{{ $game['match_format'] }} ({{ $game['quota'] }} Pemain)</strong></span>
                <span>Durasi: <strong class="text-slate-800">{{ $game['duration'] }}</strong></span>
                <span>Scoring: <strong class="text-slate-800">{{ $game['scoring_system'] }}</strong></span>
            </div>
        </div>

        <!-- Right: Team Roster Cards (1 Col) -->
        <div class="space-y-4">
            <div class="glass-card rounded-3xl p-5 space-y-4 border border-white/90">
                <div class="flex items-center justify-between">
                    <h3 class="text-xs font-bold text-slate-900 uppercase tracking-wider">
                        Roster Tim
                    </h3>
                    <span class="text-[10px] bg-emerald-50 text-emerald-800 font-semibold px-2 py-0.5 rounded-full border border-emerald-200/60">Seimbang</span>
                </div>

                <!-- Team A Roster Card -->
                <div class="p-3.5 rounded-2xl bg-white/70 border border-slate-200/60 space-y-2 shadow-2xs">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-slate-900">TEAM A</span>
                        <span class="text-[10px] text-slate-500">Sisi Kiri</span>
                    </div>
                    <div class="space-y-1.5" id="rosterTeamA">
                        <div class="flex items-center justify-between bg-white p-2 rounded-xl border border-slate-200/70 text-xs shadow-2xs">
                            <span class="font-semibold text-slate-800">1. Billy Santoso</span>
                            <x-badge type="intermediate">Intermediate</x-badge>
                        </div>
                        <div class="flex items-center justify-between bg-white p-2 rounded-xl border border-slate-200/70 text-xs shadow-2xs">
                            <span class="font-semibold text-slate-800">2. Gisel Anastasia</span>
                            <x-badge type="beginner">Beginner</x-badge>
                        </div>
                    </div>
                </div>

                <!-- Team B Roster Card -->
                <div class="p-3.5 rounded-2xl bg-white/70 border border-slate-200/60 space-y-2 shadow-2xs">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-slate-900">TEAM B</span>
                        <span class="text-[10px] text-slate-500">Sisi Kanan</span>
                    </div>
                    <div class="space-y-1.5" id="rosterTeamB">
                        <div class="flex items-center justify-between bg-white p-2 rounded-xl border border-slate-200/70 text-xs shadow-2xs">
                            <span class="font-semibold text-slate-800">1. Fahri Dhani</span>
                            <x-badge type="advanced">Advanced</x-badge>
                        </div>
                        <div class="flex items-center justify-between bg-white p-2 rounded-xl border border-slate-200/70 text-xs shadow-2xs">
                            <span class="font-semibold text-slate-800">2. Davina Putri</span>
                            <x-badge type="newbie">Newbie</x-badge>
                        </div>
                    </div>
                </div>

                <!-- Resting Bench Card -->
                <div class="p-3.5 rounded-2xl bg-white/70 border border-slate-200/60 space-y-2 shadow-2xs">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-slate-700">Bangku Istirahat Ronde 1</span>
                        <span class="text-[10px] text-slate-500">2 Pemain</span>
                    </div>
                    <div class="space-y-1.5" id="rosterResting">
                        <div class="flex items-center justify-between bg-white p-2 rounded-xl border border-slate-200/70 text-xs">
                            <span class="text-slate-700">1. Andi Wijaya</span>
                            <x-badge type="intermediate">Intermediate</x-badge>
                        </div>
                        <div class="flex items-center justify-between bg-white p-2 rounded-xl border border-slate-200/70 text-xs">
                            <span class="text-slate-700">2. Marame Nagoan</span>
                            <x-badge type="beginner">Beginner</x-badge>
                        </div>
                    </div>
                </div>

                <!-- CTA -->
                <a href="{{ route('scoring.live', $game['id']) }}" class="block text-center py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-xs shadow-xs transition-all hover:scale-[1.01]">
                    Kunci Tim & Buka Scoring
                </a>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    const rounds = {
        1: {
            teamA: ['Billy Santoso (Host)', 'Gisel Anastasia'],
            teamB: ['Fahri Dhani', 'Davina Putri'],
            resting: ['Andi Wijaya', 'Marame Nagoan']
        },
        2: {
            teamA: ['Billy Santoso (Host)', 'Andi Wijaya'],
            teamB: ['Marame Nagoan', 'Gisel Anastasia'],
            resting: ['Fahri Dhani', 'Davina Putri']
        },
        3: {
            teamA: ['Fahri Dhani', 'Marame Nagoan'],
            teamB: ['Andi Wijaya', 'Davina Putri'],
            resting: ['Billy Santoso (Host)', 'Gisel Anastasia']
        }
    };

    function switchRound(roundNum) {
        [1, 2, 3].forEach(n => {
            const tab = document.getElementById(`tabRound${n}`);
            if (n === roundNum) {
                tab.className = 'px-3.5 py-1.5 rounded-xl text-xs font-semibold transition-all bg-[#163820] text-white shadow-xs';
            } else {
                tab.className = 'px-3.5 py-1.5 rounded-xl text-xs font-semibold transition-all glass-card text-slate-600 hover:text-slate-900';
            }
        });

        const data = rounds[roundNum];
        renderRoster(data);
        showToast(`Beralih ke Ronde ${roundNum}`);
    }

    function renderRoster(data) {
        document.getElementById('rosterTeamA').innerHTML = `
            <div class="flex items-center justify-between bg-white p-2 rounded-xl border border-slate-200/70 text-xs shadow-2xs">
                <span class="font-semibold text-slate-800">1. ${data.teamA[0]}</span>
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-50 text-emerald-800 border border-emerald-200">Player 1</span>
            </div>
            <div class="flex items-center justify-between bg-white p-2 rounded-xl border border-slate-200/70 text-xs shadow-2xs">
                <span class="font-semibold text-slate-800">2. ${data.teamA[1]}</span>
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-lime-50 text-lime-800 border border-lime-200">Player 2</span>
            </div>
        `;

        document.getElementById('rosterTeamB').innerHTML = `
            <div class="flex items-center justify-between bg-white p-2 rounded-xl border border-slate-200/70 text-xs shadow-2xs">
                <span class="font-semibold text-slate-800">1. ${data.teamB[0]}</span>
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-[#eaf3eb] text-[#245b2c] border border-[#bedfc1]">Player 1</span>
            </div>
            <div class="flex items-center justify-between bg-white p-2 rounded-xl border border-slate-200/70 text-xs shadow-2xs">
                <span class="font-semibold text-slate-800">2. ${data.teamB[1]}</span>
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-slate-100 text-slate-700 border border-slate-200">Player 2</span>
            </div>
        `;

        document.getElementById('rosterResting').innerHTML = `
            <div class="flex items-center justify-between bg-white p-2 rounded-xl border border-slate-200/70 text-xs">
                <span class="text-slate-700">1. ${data.resting[0]}</span>
                <span class="text-[10px] text-slate-400 font-semibold">Bench</span>
            </div>
            <div class="flex items-center justify-between bg-white p-2 rounded-xl border border-slate-200/70 text-xs">
                <span class="text-slate-700">2. ${data.resting[1]}</span>
                <span class="text-[10px] text-slate-400 font-semibold">Bench</span>
            </div>
        `;
    }

    function runDrawingAnimation() {
        const icon = document.getElementById('shuffleIcon');
        icon.classList.add('animate-spin');
        
        setTimeout(() => {
            icon.classList.remove('animate-spin');
            showToast('Drawing berhasil diacak ulang!');
        }, 500);
    }
</script>
@endpush
@endsection
