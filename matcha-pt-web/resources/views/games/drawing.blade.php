@extends('layouts.app')

@section('content')
@php
    $firstRound = $rounds[1] ?? (reset($rounds) ?: null);
@endphp
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
        </div>
    </div>

    <!-- Match Rounds Tab Selector -->
    <div class="flex items-center gap-2 overflow-x-auto pb-1 border-b border-slate-200/50">
        @foreach($rounds as $rNum => $rData)
            <button onclick="switchRound({{ $rNum }})" id="tabRound{{ $rNum }}" class="px-3.5 py-1.5 rounded-xl text-xs font-semibold transition-all {{ $loop->first ? 'bg-[#063B00] text-white shadow-xs' : 'glass-card text-slate-600 hover:text-[#050608]' }}">
                {{ $rData['round_name'] ?? 'Ronde ' . $rNum }}
            </button>
        @endforeach
    </div>

    <!-- Drawing Visual Presentation -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
        
        <!-- Left: Court Graphic Visualizer (2 Cols) -->
        <div class="lg:col-span-2 space-y-4">
            <div id="courtContainer">
                @if($firstRound)
                    <x-court-visual 
                        :sport="$game['sport']"
                        :teamA="$firstRound['teamA']"
                        :teamB="$firstRound['teamB']"
                        :resting="$firstRound['resting']"
                    />
                @endif
            </div>

            <!-- Match Settings Summary -->
            <div class="glass-card rounded-2xl p-4 flex items-center justify-between text-xs text-slate-500">
                <span>Format: <strong class="text-[#050608]">{{ $game['match_format'] }} ({{ $game['quota'] }} Pemain)</strong></span>
                <span>Durasi: <strong class="text-[#050608]">{{ $game['duration'] }}</strong></span>
                <span>Scoring: <strong class="text-[#050608]">{{ $game['scoring_system'] }}</strong></span>
            </div>
        </div>

        <!-- Right: Team Roster Cards (1 Col) -->
        <div class="space-y-4">
            <div class="glass-card rounded-3xl p-5 space-y-4 border border-white/90">
                <div class="flex items-center justify-between">
                    <h3 class="text-xs font-bold text-[#050608] uppercase tracking-wider">
                        Roster Tim
                    </h3>
                    <span class="text-[10px] bg-[#EBF8D8] text-[#1e4e26] font-semibold px-2 py-0.5 rounded-full border border-[#C4E992]">Seimbang</span>
                </div>

                <!-- Team A Roster Card -->
                <div class="p-3.5 rounded-2xl bg-white/70 border border-slate-200/60 space-y-2 shadow-2xs">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-[#050608]">TEAM A</span>
                        <span class="text-[10px] text-slate-500">Sisi Kiri</span>
                    </div>
                    <div class="space-y-1.5" id="rosterTeamA">
                        @if($firstRound && !empty($firstRound['team_a']))
                            @foreach($firstRound['team_a'] as $pIdx => $p)
                                <div class="flex items-center justify-between bg-white p-2 rounded-xl border border-slate-200/70 text-xs shadow-2xs">
                                    <span class="font-semibold text-[#050608]">{{ $pIdx + 1 }}. {{ $p['name'] }}</span>
                                    <x-badge :type="strtolower($p['level'] ?? 'intermediate')">{{ $p['level'] ?? 'Intermediate' }}</x-badge>
                                </div>
                            @endforeach
                        @endif
                    </div>
                </div>

                <!-- Team B Roster Card -->
                <div class="p-3.5 rounded-2xl bg-white/70 border border-slate-200/60 space-y-2 shadow-2xs">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-[#050608]">TEAM B</span>
                        <span class="text-[10px] text-slate-500">Sisi Kanan</span>
                    </div>
                    <div class="space-y-1.5" id="rosterTeamB">
                        @if($firstRound && !empty($firstRound['team_b']))
                            @foreach($firstRound['team_b'] as $pIdx => $p)
                                <div class="flex items-center justify-between bg-white p-2 rounded-xl border border-slate-200/70 text-xs shadow-2xs">
                                    <span class="font-semibold text-[#050608]">{{ $pIdx + 1 }}. {{ $p['name'] }}</span>
                                    <x-badge :type="strtolower($p['level'] ?? 'intermediate')">{{ $p['level'] ?? 'Intermediate' }}</x-badge>
                                </div>
                            @endforeach
                        @endif
                    </div>
                </div>

                <!-- Resting Bench Card -->
                <div class="p-3.5 rounded-2xl bg-white/70 border border-slate-200/60 space-y-2 shadow-2xs">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-slate-700" id="restingTitle">Bangku Istirahat {{ $firstRound['round_name'] ?? 'Ronde 1' }}</span>
                        <span class="text-[10px] text-slate-500" id="restingCountBadge">{{ count($firstRound['resting'] ?? []) }} Pemain</span>
                    </div>
                    <div class="space-y-1.5" id="rosterResting">
                        @if($firstRound && !empty($firstRound['resting_players']))
                            @foreach($firstRound['resting_players'] as $pIdx => $p)
                                <div class="flex items-center justify-between bg-white p-2 rounded-xl border border-slate-200/70 text-xs">
                                    <span class="text-slate-700">{{ $pIdx + 1 }}. {{ $p['name'] }}</span>
                                    <x-badge :type="strtolower($p['level'] ?? 'intermediate')">{{ $p['level'] ?? 'Bench' }}</x-badge>
                                </div>
                            @endforeach
                        @else
                            <div class="text-slate-400 text-xs italic py-1">Tidak ada pemain di bangku cadangan</div>
                        @endif
                    </div>
                </div>

                <!-- CTA -->
                <a href="{{ route('scoring.live', $game['id']) }}" class="block text-center py-2.5 rounded-xl bg-[#063B00] hover:bg-[#042a00] text-white font-semibold text-xs shadow-xs transition-all hover:scale-[1.01]">
                    Kunci Tim & Buka Scoring
                </a>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    const rounds = @json($rounds);

    function switchRound(roundNum) {
        Object.keys(rounds).forEach(n => {
            const tab = document.getElementById(`tabRound${n}`);
            if (tab) {
                if (parseInt(n) === parseInt(roundNum)) {
                    tab.className = 'px-3.5 py-1.5 rounded-xl text-xs font-semibold transition-all bg-[#063B00] text-white shadow-xs';
                } else {
                    tab.className = 'px-3.5 py-1.5 rounded-xl text-xs font-semibold transition-all glass-card text-slate-600 hover:text-[#050608]';
                }
            }
        });

        const data = rounds[roundNum];
        if (data) {
            renderRoster(data, roundNum);
            showToast(`Beralih ke ${data.round_name || 'Ronde ' + roundNum}`);
        }
    }

    function isFemaleName(name) {
        return /gisel|davina|marame|putri|anastasia|sarah|siti|female|wanita/i.test(name);
    }

    function renderRoster(data, roundNum) {
        const teamA = data.team_a || data.teamA || [];
        const teamB = data.team_b || data.teamB || [];
        const resting = data.resting_players || data.resting || [];

        const getName = (p) => typeof p === 'object' && p !== null ? (p.name || '') : String(p || '');
        const getLevel = (p) => typeof p === 'object' && p !== null ? (p.level || 'Intermediate') : 'Intermediate';

        // Render Team A Roster
        const rosterTeamA = document.getElementById('rosterTeamA');
        if (rosterTeamA && teamA.length >= 2) {
            rosterTeamA.innerHTML = `
                <div class="flex items-center justify-between bg-white p-2 rounded-xl border border-slate-200/70 text-xs shadow-2xs">
                    <span class="font-semibold text-[#050608]">1. ${getName(teamA[0])}</span>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-50 text-emerald-800 border border-emerald-200">${getLevel(teamA[0])}</span>
                </div>
                <div class="flex items-center justify-between bg-white p-2 rounded-xl border border-slate-200/70 text-xs shadow-2xs">
                    <span class="font-semibold text-[#050608]">2. ${getName(teamA[1])}</span>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-lime-50 text-lime-800 border border-lime-200">${getLevel(teamA[1])}</span>
                </div>
            `;
        }

        // Render Team B Roster
        const rosterTeamB = document.getElementById('rosterTeamB');
        if (rosterTeamB && teamB.length >= 2) {
            rosterTeamB.innerHTML = `
                <div class="flex items-center justify-between bg-white p-2 rounded-xl border border-slate-200/70 text-xs shadow-2xs">
                    <span class="font-semibold text-[#050608]">1. ${getName(teamB[0])}</span>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-[#eaf3eb] text-[#245b2c] border border-[#bedfc1]">${getLevel(teamB[0])}</span>
                </div>
                <div class="flex items-center justify-between bg-white p-2 rounded-xl border border-slate-200/70 text-xs shadow-2xs">
                    <span class="font-semibold text-[#050608]">2. ${getName(teamB[1])}</span>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-slate-100 text-slate-700 border border-slate-200">${getLevel(teamB[1])}</span>
                </div>
            `;
        }

        // Update Resting Title & Badge
        const restingTitle = document.getElementById('restingTitle');
        if (restingTitle && roundNum) {
            restingTitle.textContent = `Bangku Istirahat Ronde ${roundNum}`;
        }
        const restingCountBadge = document.getElementById('restingCountBadge');
        if (restingCountBadge) {
            restingCountBadge.textContent = `${resting.length} Pemain`;
        }

        // Render Resting Bench
        const rosterResting = document.getElementById('rosterResting');
        if (rosterResting) {
            if (resting.length === 0) {
                rosterResting.innerHTML = `<div class="text-slate-400 text-xs italic py-1">Tidak ada pemain di bangku cadangan</div>`;
            } else {
                rosterResting.innerHTML = resting.map((p, idx) => `
                    <div class="flex items-center justify-between bg-white p-2 rounded-xl border border-slate-200/70 text-xs">
                        <span class="text-slate-700">${idx + 1}. ${getName(p)}</span>
                        <span class="text-[10px] text-slate-400 font-semibold">${getLevel(p)}</span>
                    </div>
                `).join('');
            }
        }

        // Update Court Visualizer Team A Cards
        const courtA = document.getElementById('courtTeamA');
        if (courtA && teamA.length >= 2) {
            courtA.innerHTML = [getName(teamA[0]), getName(teamA[1])].map((name) => {
                const female = isFemaleName(name);
                return `
                    <div class="flex flex-col items-center justify-center text-center transform transition-transform hover:scale-110 w-fit mx-auto sm:mx-8">
                        <div class="w-9 h-9 sm:w-11 sm:h-11 rounded-full ${female ? 'bg-gradient-to-br from-rose-400 to-pink-600' : 'bg-gradient-to-br from-sky-400 to-blue-600'} text-white border-2 border-white shadow-md flex items-center justify-center text-xs sm:text-sm mb-1">
                            <i class="${female ? 'fa-solid fa-person-dress' : 'fa-solid fa-person'}"></i>
                        </div>
                        <p class="text-[11px] sm:text-xs font-bold text-white text-center leading-tight drop-shadow-[0_1px_3px_rgba(0,0,0,0.9)] max-w-[110px] sm:max-w-[130px] truncate">
                            ${name}
                        </p>
                    </div>
                `;
            }).join('');
        }

        // Update Court Visualizer Team B Cards
        const courtB = document.getElementById('courtTeamB');
        if (courtB && teamB.length >= 2) {
            courtB.innerHTML = [getName(teamB[0]), getName(teamB[1])].map((name) => {
                const female = isFemaleName(name);
                return `
                    <div class="flex flex-col items-center justify-center text-center transform transition-transform hover:scale-110 w-fit mx-auto sm:mx-8">
                        <div class="w-9 h-9 sm:w-11 sm:h-11 rounded-full ${female ? 'bg-gradient-to-br from-rose-400 to-pink-600' : 'bg-gradient-to-br from-sky-400 to-blue-600'} text-white border-2 border-white shadow-md flex items-center justify-center text-xs sm:text-sm mb-1">
                            <i class="${female ? 'fa-solid fa-person-dress' : 'fa-solid fa-person'}"></i>
                        </div>
                        <p class="text-[11px] sm:text-xs font-bold text-white text-center leading-tight drop-shadow-[0_1px_3px_rgba(0,0,0,0.9)] max-w-[110px] sm:max-w-[130px] truncate">
                            ${name}
                        </p>
                    </div>
                `;
            }).join('');
        }
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
