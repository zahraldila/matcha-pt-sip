@extends('layouts.app')

@section('content')
@php
    $firstRound = $rounds[1] ?? (reset($rounds) ?: null);
@endphp

<div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">

    <!-- Header & Breadcrumb -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-200/50 pb-4">
        <div>
            <a href="{{ route('games.show', $game['id']) }}" class="text-xs text-slate-500 hover:text-slate-800 inline-flex items-center gap-1.5 mb-2 transition-colors">
                <i class="fa-solid fa-arrow-left"></i> Kembali ke Detail Game
            </a>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-bold text-slate-900">
                    Drawing & Jadwal Pertandingan
                </h1>
                <span class="inline-flex items-center gap-1.5 text-[11px] font-black uppercase tracking-wider px-2.5 py-1 rounded-full bg-indigo-50 text-indigo-700 border border-indigo-200">
                    <i class="fa-solid fa-user-group text-[10px]"></i> Team Americano
                </span>
            </div>
            <p class="text-xs text-slate-500 mt-0.5">Pasangan tim tetap bertanding melawan seluruh tim lain dalam sistem Round-Robin</p>
        </div>

        <div class="flex items-center gap-2.5">
            <button id="shuffleBtn" onclick="runDrawingAnimation()" class="px-4 py-2 rounded-xl bg-white/80 hover:bg-white border border-slate-200/80 text-slate-800 font-semibold text-xs shadow-xs transition-all flex items-center gap-1.5 hover:border-[#063B00] cursor-pointer">
                <i class="fa-solid fa-arrows-rotate text-slate-500" id="shuffleIcon"></i> Acak Ulang Jadwal
            </button>
        </div>
    </div>

    @php
        $rounds = $drawingData['rounds'] ?? [];
        $firstRoundKey = array_key_first($rounds) ?? 1;
        $activeRound = $rounds[$firstRoundKey] ?? [
            'teamA' => ['Billy Santoso (Host)', 'Gisel Anastasia'],
            'teamB' => ['Fahri Dhani', 'Davina Putri'],
            'resting' => ['Andi Wijaya', 'Marame Nagoan'],
            'team_a' => ['name' => 'Team Alpha'],
            'team_b' => ['name' => 'Team Beta'],
            'matches' => [],
        ];
    @endphp

    <!-- Match Rounds Tab Selector -->
    <div class="flex items-center gap-2 overflow-x-auto pb-1 border-b border-slate-200/50" id="roundsTabContainer">
        @foreach($rounds as $rNum => $rData)
            <button onclick="switchRound({{ $rNum }})" id="tabRound{{ $rNum }}" class="px-4 py-2 rounded-xl text-xs font-bold transition-all shrink-0 {{ $loop->first ? 'bg-[#063B00] text-white shadow-xs' : 'glass-card text-slate-600 hover:text-[#050608]' }}">
                {{ $rData['round_title'] ?? "Ronde {$rNum}" }}
                @if($loop->first)
                    <span class="text-[10px] opacity-80 font-normal ml-1">(Pembuka)</span>
                @elseif($loop->last)
                    <span class="text-[10px] opacity-80 font-normal ml-1">(Final)</span>
                @endif
            </button>
        @endforeach
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

            <!-- Multi-Court Schedule List on Active Round -->
            <div class="glass-card rounded-2xl p-4 space-y-3">
                <div class="flex items-center justify-between">
                    <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wider flex items-center gap-1.5">
                        <i class="fa-solid fa-table-tennis-paddle-ball text-[#063B00]"></i>
                        Alokasi Lapangan <span id="labelCurrentRoundTitle" class="text-[#063B00]">Ronde 1</span>
                    </h3>
                    <span class="text-[10px] text-slate-400 font-semibold" id="labelTotalMatches">
                        {{ count($activeRound['matches'] ?? []) }} Match Berjalan
                    </span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5" id="courtMatchesContainer">
                    @forelse($activeRound['matches'] ?? [] as $m)
                        <div class="p-3 bg-slate-50/80 rounded-xl border border-slate-200/70 text-xs space-y-1.5">
                            <div class="flex items-center justify-between border-b border-slate-200/60 pb-1">
                                <div class="flex items-center gap-1.5">
                                    <span class="font-bold text-[#063B00]">{{ $m['court_name'] }}</span>
                                    @if(!empty($m['slot_number']))
                                        <span class="text-[9px] font-black uppercase text-indigo-700 bg-indigo-50 border border-indigo-200 px-1.5 py-0.5 rounded-md">Slot {{ $m['slot_number'] }}</span>
                                    @endif
                                </div>
                                <span class="text-[10px] font-semibold text-slate-500 bg-white px-2 py-0.5 rounded-full border border-slate-200">
                                    {{ $m['status'] }}
                                </span>
                            </div>
                            <div class="flex items-center justify-between text-slate-800 font-semibold pt-0.5">
                                <span class="truncate max-w-[45%] text-[#063B00]">{{ $m['team_a']['name'] }}</span>
                                <span class="text-[10px] text-slate-400 font-black">VS</span>
                                <span class="truncate max-w-[45%] text-slate-700 text-right">{{ $m['team_b']['name'] }}</span>
                            </div>
                            <div class="flex items-center justify-between text-[10px] text-slate-500">
                                <span class="truncate max-w-[45%]">{{ implode(' & ', $m['team_a']['player_names'] ?? []) }}</span>
                                <span class="truncate max-w-[45%] text-right">{{ implode(' & ', $m['team_b']['player_names'] ?? []) }}</span>
                            </div>
                        </div>
                    @empty
                        <div class="col-span-2 text-center py-4 text-slate-400 text-xs">
                            Jadwal match untuk ronde ini siap dimainkan.
                        </div>
                    @endforelse
                </div>
            </div>

            <!-- Tournament Settings Summary -->
            <div class="glass-card rounded-2xl p-4 flex flex-wrap items-center justify-between gap-3 text-xs text-slate-500">
                <span>Format: <strong class="text-[#050608]">Team Americano ({{ $drawingData['total_teams'] ?? 4 }} Tim Tetap)</strong></span>
                <span>Total Ronde: <strong class="text-[#050608]">{{ $drawingData['total_rounds'] ?? 3 }} Ronde</strong></span>
                <span>Total Match: <strong class="text-[#050608]">{{ $drawingData['total_matches'] ?? 6 }} Match</strong></span>
                <span>Scoring: <strong class="text-[#050608]">{{ $game['scoring_system'] }}</strong></span>
            </div>
        </div>

        <!-- Right: Team Roster Cards (1 Col) -->
        <div class="space-y-4">
            <div class="glass-card rounded-3xl p-5 space-y-4 border border-white/90 shadow-sm">
                <div class="flex items-center justify-between">
                    <h3 class="text-xs font-bold text-[#050608] uppercase tracking-wider flex items-center gap-1.5">
                        <i class="fa-solid fa-users text-[#063B00]"></i> Roster Pertandingan
                    </h3>
                    <span class="text-[10px] bg-indigo-50 text-indigo-700 font-bold px-2 py-0.5 rounded-full border border-indigo-200">Fixed Pairs</span>
                </div>

                <!-- Team A Roster Card -->
                <div class="p-3.5 rounded-2xl bg-white/80 border border-slate-200/80 space-y-2 shadow-2xs">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-extrabold text-[#063B00]" id="labelTeamAName">
                            {{ $activeRound['primary_match']['team_a']['name'] ?? 'TEAM ALPHA' }}
                        </span>
                        <span class="text-[10px] font-semibold text-slate-400">Sisi Kiri (Court 1)</span>
                    </div>
                    <div class="space-y-1.5" id="rosterTeamA">
                        <div class="flex items-center justify-between bg-white p-2 rounded-xl border border-slate-200/70 text-xs shadow-2xs">
                            <span class="font-semibold text-[#050608]">1. Billy Santoso</span>
                            <x-badge type="intermediate">Intermediate</x-badge>
                        </div>
                        <div class="flex items-center justify-between bg-white p-2 rounded-xl border border-slate-200/70 text-xs shadow-2xs">
                            <span class="font-semibold text-[#050608]">2. Gisel Anastasia</span>
                            <x-badge type="beginner">Beginner</x-badge>
                        </div>
                    </div>
                </div>

                <!-- Team B Roster Card -->
                <div class="p-3.5 rounded-2xl bg-white/80 border border-slate-200/80 space-y-2 shadow-2xs">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-extrabold text-slate-800" id="labelTeamBName">
                            {{ $activeRound['primary_match']['team_b']['name'] ?? 'TEAM BETA' }}
                        </span>
                        <span class="text-[10px] font-semibold text-slate-400">Sisi Kanan (Court 1)</span>
                    </div>
                    <div class="space-y-1.5" id="rosterTeamB">
                        <div class="flex items-center justify-between bg-white p-2 rounded-xl border border-slate-200/70 text-xs shadow-2xs">
                            <span class="font-semibold text-[#050608]">1. Fahri Dhani</span>
                            <x-badge type="advanced">Advanced</x-badge>
                        </div>
                        <div class="flex items-center justify-between bg-white p-2 rounded-xl border border-slate-200/70 text-xs shadow-2xs">
                            <span class="font-semibold text-[#050608]">2. Davina Putri</span>
                            <x-badge type="newbie">Newbie</x-badge>
                        </div>
                    </div>
                </div>

                <!-- Resting Bench Card -->
                <div class="p-3.5 rounded-2xl bg-white/80 border border-slate-200/80 space-y-2 shadow-2xs">
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

                <!-- CTA Button to Live Scoring -->
                <a href="{{ route('scoring.live', $game['id']) }}" class="block text-center py-3.5 rounded-2xl bg-[#063B00] hover:bg-[#042a00] text-white font-extrabold text-xs shadow-md transition-all hover:scale-[1.01] active:scale-95">
                    <span>Kunci Tim & Buka Scoring Live</span> <i class="fa-solid fa-arrow-right text-[10px] text-[#A8E63A] ml-1"></i>
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
                tab.className = 'px-3.5 py-1.5 rounded-xl text-xs font-semibold transition-all bg-[#063B00] text-white shadow-xs';
            } else {
                tab.className = 'px-3.5 py-1.5 rounded-xl text-xs font-semibold transition-all glass-card text-slate-600 hover:text-[#050608]';
            }
        }

        const data = rounds[roundNum];
        renderRoster(data);
        showToast(`Beralih ke Ronde ${roundNum}`);
    }

    function isFemaleName(name) {
        return /gisel|davina|marame|putri|anastasia|sarah|siti|female|wanita|dewi|maya|lisa/i.test(name);
    }

    function renderRoster(data) {
        // Render Team A Roster
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

        // Render Team B Roster
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

        // Render Resting Bench
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

        // 6. Update Court Visualizer Graphic
        const courtA = document.getElementById('courtTeamA');
        if (courtA) {
            courtA.innerHTML = data.teamA.map((name, idx) => {
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

        const courtB = document.getElementById('courtTeamB');
        if (courtB) {
            courtB.innerHTML = data.teamB.map((name, idx) => {
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
            window.location.reload();
        }, 400);
    }
</script>
@endpush
@endsection
