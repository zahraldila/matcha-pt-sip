@extends('layouts.app')

@section('content')
@php
    $rounds = $rounds ?? ($drawingData['rounds'] ?? []);
    $firstRoundKey = array_key_first($rounds) ?? 1;
    $firstRound = $rounds[$firstRoundKey] ?? null;
    $isTeamFormat = str_contains(strtolower($game['match_format'] ?? ''), 'team');
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
                @if($isTeamFormat)
                    <span class="inline-flex items-center gap-1.5 text-[11px] font-black uppercase tracking-wider px-2.5 py-1 rounded-full bg-indigo-50 text-indigo-700 border border-indigo-200">
                        <i class="fa-solid fa-user-group text-[10px]"></i> Team Americano (Fixed Pairs)
                    </span>
                @else
                    <span class="inline-flex items-center gap-1.5 text-[11px] font-black uppercase tracking-wider px-2.5 py-1 rounded-full bg-[#EBF8D8] text-[#063B00] border border-[#C4E992]">
                        <i class="fa-solid fa-arrows-rotate text-[10px]"></i> Americano (Rotating Pairs)
                    </span>
                @endif
            </div>
            <p class="text-xs text-slate-500 mt-0.5">
                @if($isTeamFormat)
                    Pasangan tim tetap bertanding melawan seluruh tim lain dalam sistem Round-Robin
                @else
                    Setiap pemain berpasangan secara bergantian di setiap ronde pertandingan
                @endif
            </p>
        </div>

        <div class="flex items-center gap-2.5">
            <button id="shuffleBtn" onclick="runDrawingAnimation()" class="px-4 py-2 rounded-xl bg-white/80 hover:bg-white border border-slate-200/80 text-slate-800 font-semibold text-xs shadow-xs transition-all flex items-center gap-1.5 hover:border-[#063B00] cursor-pointer">
                <i class="fa-solid fa-arrows-rotate text-slate-500" id="shuffleIcon"></i> Acak Ulang Jadwal
            </button>
        </div>
    </div>

    <!-- Match Rounds Tab Selector -->
    <div class="flex items-center gap-2 overflow-x-auto pb-1 border-b border-slate-200/50" id="roundsTabContainer">
        @foreach($rounds as $rNum => $rData)
            <button onclick="switchRound({{ $rNum }})" id="tabRound{{ $rNum }}" class="px-4 py-2 rounded-xl text-xs font-bold transition-all shrink-0 {{ $loop->first ? 'bg-[#063B00] text-white shadow-xs' : 'glass-card text-slate-600 hover:text-[#050608]' }}">
                {{ $rData['round_title'] ?? ($rData['round_name'] ?? "Ronde {$rNum}") }}
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
            
            <!-- Current Active Court Visualizer -->
            <div id="courtContainer" class="relative">
                @if($firstRound)
                    @php
                        $teamANames = is_array($firstRound['teamA'] ?? null) 
                            ? array_map(fn($p) => is_array($p) ? ($p['name'] ?? '') : (string)$p, $firstRound['teamA'])
                            : (is_array($firstRound['team_a'] ?? null) ? array_map(fn($p) => is_array($p) ? ($p['name'] ?? '') : (string)$p, $firstRound['team_a']) : []);
                        
                        $teamBNames = is_array($firstRound['teamB'] ?? null)
                            ? array_map(fn($p) => is_array($p) ? ($p['name'] ?? '') : (string)$p, $firstRound['teamB'])
                            : (is_array($firstRound['team_b'] ?? null) ? array_map(fn($p) => is_array($p) ? ($p['name'] ?? '') : (string)$p, $firstRound['team_b']) : []);
                        
                        $restingNames = is_array($firstRound['resting'] ?? null)
                            ? array_map(fn($p) => is_array($p) ? ($p['name'] ?? '') : (string)$p, $firstRound['resting'])
                            : (is_array($firstRound['resting_players'] ?? null) ? array_map(fn($p) => is_array($p) ? ($p['name'] ?? '') : (string)$p, $firstRound['resting_players']) : []);
                    @endphp
                    <x-court-visual 
                        :sport="$game['sport']"
                        :teamA="$teamANames"
                        :teamB="$teamBNames"
                        :resting="$restingNames"
                    />
                @endif
            </div>

            <!-- Multi-Court Schedule List on Active Round -->
            <div class="glass-card rounded-2xl p-4 space-y-3">
                <div class="flex items-center justify-between">
                    <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wider flex items-center gap-1.5">
                        <i class="fa-solid fa-table-tennis-paddle-ball text-[#063B00]"></i>
                        Alokasi Lapangan <span id="labelCurrentRoundTitle" class="text-[#063B00]">Ronde 1</span>
                    </h3>
                    <span class="text-[10px] text-slate-400 font-semibold" id="labelTotalMatches">
                        {{ count($firstRound['matches'] ?? []) }} Match Berjalan
                    </span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5" id="courtMatchesContainer">
                    @forelse($firstRound['matches'] ?? [] as $m)
                        <div class="p-3 bg-slate-50/80 rounded-xl border border-slate-200/70 text-xs space-y-1.5">
                            <div class="flex items-center justify-between border-b border-slate-200/60 pb-1">
                                <div class="flex items-center gap-1.5">
                                    <span class="font-bold text-[#063B00]">{{ $m['court_name'] ?? 'Court 1' }}</span>
                                    @if(!empty($m['slot_number']))
                                        <span class="text-[9px] font-black uppercase text-indigo-700 bg-indigo-50 border border-indigo-200 px-1.5 py-0.5 rounded-md">Slot {{ $m['slot_number'] }}</span>
                                    @endif
                                </div>
                                <span class="text-[10px] font-semibold text-slate-500 bg-white px-2 py-0.5 rounded-full border border-slate-200">
                                    {{ $m['status'] ?? 'Scheduled' }}
                                </span>
                            </div>
                            <div class="flex items-center justify-between text-slate-800 font-semibold pt-0.5">
                                <span class="truncate max-w-[45%] text-[#063B00]">{{ $m['team_a']['name'] ?? 'Team A' }}</span>
                                <span class="text-[10px] text-slate-400 font-black">VS</span>
                                <span class="truncate max-w-[45%] text-slate-700 text-right">{{ $m['team_b']['name'] ?? 'Team B' }}</span>
                            </div>
                            <div class="flex items-center justify-between text-[10px] text-slate-500">
                                <span class="truncate max-w-[45%]">{{ implode(' & ', $m['team_a']['player_names'] ?? ($m['teamA_names'] ?? [])) }}</span>
                                <span class="truncate max-w-[45%] text-right">{{ implode(' & ', $m['team_b']['player_names'] ?? ($m['teamB_names'] ?? [])) }}</span>
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
                <span>Format: <strong class="text-[#050608]">{{ $game['match_format'] }} ({{ $drawingData['total_teams'] ?? count($game['participants']) }} {{ $isTeamFormat ? 'Tim' : 'Pemain' }})</strong></span>
                <span>Total Ronde: <strong class="text-[#050608]">{{ count($rounds) }} Ronde</strong></span>
                <span>Total Match: <strong class="text-[#050608]">{{ $drawingData['total_matches'] ?? array_sum(array_map(fn($r) => count($r['matches'] ?? []), $rounds)) }} Match</strong></span>
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
                    @if($isTeamFormat)
                        <span class="text-[10px] bg-indigo-50 text-indigo-700 font-bold px-2 py-0.5 rounded-full border border-indigo-200">Fixed Pairs</span>
                    @else
                        <span class="text-[10px] bg-[#EBF8D8] text-[#063B00] font-bold px-2 py-0.5 rounded-full border border-[#C4E992]">Rotasi</span>
                    @endif
                </div>

                <!-- Team A Roster Card -->
                <div class="p-3.5 rounded-2xl bg-white/80 border border-slate-200/80 space-y-2 shadow-2xs">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-extrabold text-[#063B00]" id="labelTeamAName">
                            {{ $firstRound['primary_match']['team_a']['name'] ?? ($firstRound['team_a']['name'] ?? 'TEAM A') }}
                        </span>
                        <span class="text-[10px] font-semibold text-slate-400">Sisi Kiri (Court 1)</span>
                    </div>
                    <div class="space-y-1.5" id="rosterTeamA">
                        @if($firstRound)
                            @php
                                $teamAList = $firstRound['teamA'] ?? ($firstRound['team_a'] ?? []);
                            @endphp
                            @foreach($teamAList as $idx => $p)
                                @php
                                    $pName = is_array($p) ? ($p['name'] ?? '') : (string)$p;
                                    $pLevel = is_array($p) ? ($p['level'] ?? 'Intermediate') : 'Player ' . ($idx + 1);
                                @endphp
                                <div class="flex items-center justify-between bg-slate-50/70 p-2 rounded-xl border border-slate-200/60 text-xs shadow-2xs">
                                    <span class="font-bold text-slate-800">{{ $idx + 1 }}. {{ $pName }}</span>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-50 text-emerald-800 border border-emerald-200">{{ $pLevel }}</span>
                                </div>
                            @endforeach
                        @endif
                    </div>
                </div>

                <!-- Team B Roster Card -->
                <div class="p-3.5 rounded-2xl bg-white/80 border border-slate-200/80 space-y-2 shadow-2xs">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-extrabold text-slate-800" id="labelTeamBName">
                            {{ $firstRound['primary_match']['team_b']['name'] ?? ($firstRound['team_b']['name'] ?? 'TEAM B') }}
                        </span>
                        <span class="text-[10px] font-semibold text-slate-400">Sisi Kanan (Court 1)</span>
                    </div>
                    <div class="space-y-1.5" id="rosterTeamB">
                        @if($firstRound)
                            @php
                                $teamBList = $firstRound['teamB'] ?? ($firstRound['team_b'] ?? []);
                            @endphp
                            @foreach($teamBList as $idx => $p)
                                @php
                                    $pName = is_array($p) ? ($p['name'] ?? '') : (string)$p;
                                    $pLevel = is_array($p) ? ($p['level'] ?? 'Intermediate') : 'Player ' . ($idx + 1);
                                @endphp
                                <div class="flex items-center justify-between bg-slate-50/70 p-2 rounded-xl border border-slate-200/60 text-xs shadow-2xs">
                                    <span class="font-bold text-slate-800">{{ $idx + 1 }}. {{ $pName }}</span>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-slate-100 text-slate-700 border border-slate-200">{{ $pLevel }}</span>
                                </div>
                            @endforeach
                        @endif
                    </div>
                </div>

                <!-- Resting Bench Card -->
                <div class="p-3.5 rounded-2xl bg-white/80 border border-slate-200/80 space-y-2 shadow-2xs">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-slate-700" id="restingTitle">Bangku Istirahat / Antrean</span>
                        <span class="text-[10px] text-slate-500 font-semibold" id="restingCountBadge">
                            {{ count($restingNames ?? []) }} Pemain
                        </span>
                    </div>
                    <div class="space-y-1.5" id="rosterResting">
                        @forelse($restingNames ?? [] as $idx => $rPlayer)
                            <div class="flex items-center justify-between bg-slate-50/70 p-2 rounded-xl border border-slate-200/60 text-xs">
                                <span class="text-slate-700 font-medium">{{ $idx + 1 }}. {{ $rPlayer }}</span>
                                <span class="text-[10px] text-slate-400 font-semibold">Bench / BYE</span>
                            </div>
                        @empty
                            <p class="text-[11px] text-slate-400 italic py-1 text-center">Semua pemain bertanding di ronde ini.</p>
                        @endforelse
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
    const rounds = @json($rounds);

    function switchRound(roundNum) {
        Object.keys(rounds).forEach(n => {
            const tab = document.getElementById(`tabRound${n}`);
            if (tab) {
                if (parseInt(n) === parseInt(roundNum)) {
                    tab.className = 'px-4 py-2 rounded-xl text-xs font-bold transition-all shrink-0 bg-[#063B00] text-white shadow-xs';
                } else {
                    tab.className = 'px-4 py-2 rounded-xl text-xs font-bold transition-all shrink-0 glass-card text-slate-600 hover:text-[#050608]';
                }
            }
        });

        const data = rounds[roundNum];
        if (!data) return;

        renderRoster(data, roundNum);
        showToast(`Beralih ke Ronde ${roundNum}`);
    }

    function isFemaleName(name) {
        return /gisel|davina|marame|putri|anastasia|sarah|siti|female|wanita|dewi|maya|lisa/i.test(name);
    }

    const getName = (p) => typeof p === 'object' && p !== null ? (p.name || '') : String(p || '');
    const getLevel = (p, defaultIdx) => typeof p === 'object' && p !== null ? (p.level || 'Player ' + defaultIdx) : 'Player ' + defaultIdx;

    function renderRoster(data, roundNum) {
        // 1. Update Ronde Title & Court Matches
        const lblTitle = document.getElementById('labelCurrentRoundTitle');
        if (lblTitle) lblTitle.innerText = `Ronde ${roundNum}`;

        const matchesContainer = document.getElementById('courtMatchesContainer');
        const matchesCountBadge = document.getElementById('labelTotalMatches');

        const matchesList = data.matches || [];
        if (matchesContainer) {
            if (matchesCountBadge) matchesCountBadge.innerText = `${matchesList.length} Match Berjalan`;
            
            if (matchesList.length === 0) {
                matchesContainer.innerHTML = `<div class="col-span-2 text-center py-4 text-slate-400 text-xs">Jadwal match untuk ronde ini siap dimainkan.</div>`;
            } else {
                matchesContainer.innerHTML = matchesList.map(m => {
                    const teamAName = m.team_a ? (m.team_a.name || 'Team A') : 'Team A';
                    const teamBName = m.team_b ? (m.team_b.name || 'Team B') : 'Team B';
                    const teamAPlayers = m.team_a ? (m.team_a.player_names || m.teamA_names || []) : (m.teamA_names || []);
                    const teamBPlayers = m.team_b ? (m.team_b.player_names || m.teamB_names || []) : (m.teamB_names || []);

                    return `
                        <div class="p-3 bg-slate-50/80 rounded-xl border border-slate-200/70 text-xs space-y-1.5">
                            <div class="flex items-center justify-between border-b border-slate-200/60 pb-1">
                                <div class="flex items-center gap-1.5">
                                    <span class="font-bold text-[#063B00]">${m.court_name || 'Court 1'}</span>
                                    ${m.slot_number ? `<span class="text-[9px] font-black uppercase text-indigo-700 bg-indigo-50 border border-indigo-200 px-1.5 py-0.5 rounded-md">Slot ${m.slot_number}</span>` : ''}
                                </div>
                                <span class="text-[10px] font-semibold text-slate-500 bg-white px-2 py-0.5 rounded-full border border-slate-200">
                                    ${m.status || 'Scheduled'}
                                </span>
                            </div>
                            <div class="flex items-center justify-between text-slate-800 font-semibold pt-0.5">
                                <span class="truncate max-w-[45%] text-[#063B00]">${teamAName}</span>
                                <span class="text-[10px] text-slate-400 font-black">VS</span>
                                <span class="truncate max-w-[45%] text-slate-700 text-right">${teamBName}</span>
                            </div>
                            <div class="flex items-center justify-between text-[10px] text-slate-500">
                                <span class="truncate max-w-[45%]">${teamAPlayers.map(getName).join(' & ')}</span>
                                <span class="truncate max-w-[45%] text-right">${teamBPlayers.map(getName).join(' & ')}</span>
                            </div>
                        </div>
                    `;
                }).join('');
            }
        }

        // 2. Update Team Labels in Roster Sidebar
        const teamA = data.teamA || data.team_a || [];
        const teamB = data.teamB || data.team_b || [];
        const resting = data.resting || data.resting_players || [];

        const teamAName = data.primary_match ? (data.primary_match.team_a.name || 'TEAM A') : (data.team_a && data.team_a.name ? data.team_a.name : 'TEAM A');
        const teamBName = data.primary_match ? (data.primary_match.team_b.name || 'TEAM B') : (data.team_b && data.team_b.name ? data.team_b.name : 'TEAM B');
        
        const lblTeamA = document.getElementById('labelTeamAName');
        const lblTeamB = document.getElementById('labelTeamBName');
        if (lblTeamA) lblTeamA.innerText = teamAName;
        if (lblTeamB) lblTeamB.innerText = teamBName;

        // 3. Render Team A Roster
        const rosterTeamA = document.getElementById('rosterTeamA');
        if (rosterTeamA) {
            rosterTeamA.innerHTML = (Array.isArray(teamA) ? teamA : []).map((p, idx) => `
                <div class="flex items-center justify-between bg-slate-50/70 p-2 rounded-xl border border-slate-200/60 text-xs shadow-2xs">
                    <span class="font-bold text-slate-800">${idx + 1}. ${getName(p)}</span>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-50 text-emerald-800 border border-emerald-200">${getLevel(p, idx + 1)}</span>
                </div>
            `).join('');
        }

        // 4. Render Team B Roster
        const rosterTeamB = document.getElementById('rosterTeamB');
        if (rosterTeamB) {
            rosterTeamB.innerHTML = (Array.isArray(teamB) ? teamB : []).map((p, idx) => `
                <div class="flex items-center justify-between bg-slate-50/70 p-2 rounded-xl border border-slate-200/60 text-xs shadow-2xs">
                    <span class="font-bold text-slate-800">${idx + 1}. ${getName(p)}</span>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-slate-100 text-slate-700 border border-slate-200">${getLevel(p, idx + 1)}</span>
                </div>
            `).join('');
        }

        // 5. Render Resting Bench
        const rosterResting = document.getElementById('rosterResting');
        const restingBadge = document.getElementById('restingCountBadge');
        if (rosterResting) {
            const restingList = Array.isArray(resting) ? resting : [];
            if (restingBadge) restingBadge.innerText = `${restingList.length} Pemain`;
            
            if (restingList.length === 0) {
                rosterResting.innerHTML = `<p class="text-[11px] text-slate-400 italic py-1 text-center">Semua pemain bertanding di ronde ini.</p>`;
            } else {
                rosterResting.innerHTML = restingList.map((p, idx) => `
                    <div class="flex items-center justify-between bg-slate-50/70 p-2 rounded-xl border border-slate-200/60 text-xs">
                        <span class="text-slate-700 font-medium">${idx + 1}. ${getName(p)}</span>
                        <span class="text-[10px] text-slate-400 font-semibold">Bench / BYE</span>
                    </div>
                `).join('');
            }
        }

        // 6. Update Court Visualizer Graphic
        const courtA = document.getElementById('courtTeamA');
        if (courtA && Array.isArray(teamA)) {
            courtA.innerHTML = teamA.map(p => {
                const name = getName(p);
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
        if (courtB && Array.isArray(teamB)) {
            courtB.innerHTML = teamB.map(p => {
                const name = getName(p);
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
