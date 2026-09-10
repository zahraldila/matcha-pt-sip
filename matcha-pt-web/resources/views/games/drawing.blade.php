@extends('layouts.app')

@section('content')
@php
    $rounds = $drawingData['rounds'] ?? ($rounds ?? []);
    $firstRoundKey = !empty($rounds) ? array_key_first($rounds) : 1;
    $activeRound = (!empty($rounds) && isset($rounds[$firstRoundKey])) ? $rounds[$firstRoundKey] : [
        'teamA' => [],
        'teamB' => [],
        'resting' => [],
        'team_a' => ['name' => 'Team A'],
        'team_b' => ['name' => 'Team B'],
        'matches' => [],
    ];
    $isSetBased = str_contains(strtolower($game['scoring_system'] ?? ''), 'total of') || str_contains(strtolower($game['scoring_system'] ?? ''), 'best of');
    $unitLabel = $isSetBased ? 'Set' : 'Round';
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
                    <i class="fa-solid fa-trophy text-[10px]"></i> {{ $game['match_format'] ?? 'Americano' }}
                </span>
            </div>
            <p class="text-xs text-slate-500 mt-0.5">Sistem drawing pertandingan dan rotasi {{ $isSetBased ? 'Set Permainan' : 'Round-Robin' }}</p>
        </div>

        <div class="flex items-center gap-2.5">
            @if($isLocked ?? false)
                <span class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-amber-50 text-amber-800 border border-amber-200 text-xs font-bold shadow-2xs" title="Pertandingan sudah dimulai, jadwal tim tidak dapat diacak ulang">
                    <i class="fa-solid fa-lock text-amber-600"></i> Tim Terkunci (Match Berjalan)
                </span>
            @else
                <button id="shuffleBtn" onclick="runDrawingAnimation()" class="px-4 py-2 rounded-xl bg-white/80 hover:bg-white border border-slate-200/80 text-slate-800 font-semibold text-xs shadow-xs transition-all flex items-center gap-1.5 hover:border-[#063B00] cursor-pointer">
                    <i class="fa-solid fa-arrows-rotate text-slate-500" id="shuffleIcon"></i> Acak Ulang Jadwal
                </button>
            @endif
        </div>
    </div>

    <!-- Match Rounds Tab Selector -->
    <div class="flex items-center gap-2 overflow-x-auto pb-1 border-b border-slate-200/50" id="roundsTabContainer">
        @foreach($rounds as $rNum => $rData)
            @php
                $baseNum = $rData['round'] ?? $rData['round_number'] ?? $rNum;
                $cleanTitle = "{$unitLabel} {$baseNum}";
            @endphp
            <button onclick="switchRound({{ $rNum }})" id="tabRound{{ $rNum }}" class="px-4 py-2 rounded-xl text-xs font-bold transition-all shrink-0 {{ $loop->first ? 'bg-[#063B00] text-white shadow-xs' : 'glass-card text-slate-600 hover:text-[#050608]' }}">
                {{ $cleanTitle }}
                @if($loop->first && count($rounds) > 1)
                    <span class="text-[10px] opacity-80 font-normal ml-1">(Pembuka)</span>
                @elseif($loop->last && count($rounds) > 1)
                    <span class="text-[10px] opacity-80 font-normal ml-1">(Final)</span>
                @elseif(count($rounds) > 2)
                    <span class="text-[10px] opacity-80 font-normal ml-1">({{ $isSetBased ? 'Lanjutan' : 'Rotasi' }})</span>
                @endif
            </button>
        @endforeach
    </div>

    <!-- Drawing Visual Presentation -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
        
        <!-- Left: Court Graphic Visualizer (2 Cols) -->
        <div class="lg:col-span-2 space-y-4">
            
            <!-- Court Selector Tabs for Multi-Court Session -->
            <div id="courtSelectorContainer" class="flex flex-wrap items-center justify-between gap-2 pb-1 {{ count($activeRound['matches'] ?? []) > 1 ? '' : 'hidden' }}">
                <div class="flex items-center gap-1.5 overflow-x-auto" id="courtTabsWrapper">
                    @if(count($activeRound['matches'] ?? []) > 1)
                        @foreach($activeRound['matches'] as $mIdx => $m)
                            <button onclick="selectCourtMatch({{ $mIdx }})" id="btnCourtTab{{ $mIdx }}" class="px-3 py-1.5 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5 cursor-pointer {{ $mIdx === 0 ? 'bg-[#063B00] text-white shadow-xs' : 'glass-card text-slate-600 hover:text-slate-900' }}">
                                <i class="fa-solid fa-table-tennis-paddle-ball text-[10px]"></i>
                                {{ $m['court_name'] ?? ('Court ' . ($m['court'] ?? ($mIdx + 1))) }}
                            </button>
                        @endforeach
                    @endif
                </div>
                <span class="text-[11px] text-slate-500 font-semibold" id="labelActiveCourtIndicator">
                    Visualisasi Lapangan: <strong class="text-[#063B00]" id="labelCurrentActiveCourt">{{ $activeRound['matches'][0]['court_name'] ?? 'Court 1' }}</strong>
                </span>
            </div>

            <div id="courtContainer">
                <x-court-visual 
                    :sport="$game['sport']"
                    :teamA="$activeRound['teamA'] ?? []"
                    :teamB="$activeRound['teamB'] ?? []"
                    :resting="$activeRound['resting'] ?? []"
                    :participantsMap="$participantsMap ?? []"
                />
            </div>

            <!-- Multi-Court Schedule List on Active Round -->
            <div class="glass-card rounded-2xl p-4 space-y-3">
                <div class="flex items-center justify-between">
                    <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wider flex items-center gap-1.5">
                        <i class="fa-solid fa-table-tennis-paddle-ball text-[#063B00]"></i>
                        Alokasi Lapangan <span id="labelCurrentRoundTitle" class="text-[#063B00]">{{ $unitLabel }} 1</span>
                    </h3>
                    <span class="text-[10px] text-slate-400 font-semibold" id="labelTotalMatches">
                        {{ count($activeRound['matches'] ?? []) }} Match Berjalan
                    </span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5" id="courtMatchesContainer">
                    @forelse($activeRound['matches'] ?? [] as $mIdx => $m)
                        @php
                            $courtLabel = $m['court_name'] ?? ('Court ' . ($m['court'] ?? ($mIdx + 1)));
                            $statusLabel = $m['status'] ?? 'Scheduled';
                            
                            // Extract Team A name & player list
                            if (is_array($m['team_a']) && isset($m['team_a']['name'])) {
                                $teamAName = $m['team_a']['name'];
                                $teamAPlayers = $m['team_a']['player_names'] ?? [];
                            } elseif (!empty($m['team_a_names'])) {
                                $teamAName = implode(' & ', $m['team_a_names']);
                                $teamAPlayers = $m['team_a_names'];
                            } elseif (is_array($m['team_a'])) {
                                $teamAPlayers = array_column($m['team_a'], 'name');
                                $teamAName = implode(' & ', $teamAPlayers);
                            } else {
                                $teamAName = 'Team A';
                                $teamAPlayers = [];
                            }

                            // Extract Team B name & player list
                            if (is_array($m['team_b']) && isset($m['team_b']['name'])) {
                                $teamBName = $m['team_b']['name'];
                                $teamBPlayers = $m['team_b']['player_names'] ?? [];
                            } elseif (!empty($m['team_b_names'])) {
                                $teamBName = implode(' & ', $m['team_b_names']);
                                $teamBPlayers = $m['team_b_names'];
                            } elseif (is_array($m['team_b'])) {
                                $teamBPlayers = array_column($m['team_b'], 'name');
                                $teamBName = implode(' & ', $teamBPlayers);
                            } else {
                                $teamBName = 'Team B';
                                $teamBPlayers = [];
                            }
                        @endphp
                        <div onclick="selectCourtMatch({{ $mIdx }})" id="matchCard{{ $mIdx }}" class="p-3 bg-slate-50/80 rounded-xl border border-slate-200/70 text-xs space-y-1.5 transition-all hover:bg-white hover:shadow-xs cursor-pointer {{ $mIdx === 0 ? 'ring-2 ring-[#063B00] bg-white shadow-xs' : '' }}">
                            <div class="flex items-center justify-between border-b border-slate-200/60 pb-1">
                                <div class="flex items-center gap-1.5">
                                    <span class="font-bold text-[#063B00]">{{ $courtLabel }}</span>
                                    @if(!empty($m['slot_number']))
                                        <span class="text-[9px] font-black uppercase text-indigo-700 bg-indigo-50 border border-indigo-200 px-1.5 py-0.5 rounded-md">Slot {{ $m['slot_number'] }}</span>
                                    @endif
                                </div>
                                <span class="text-[10px] font-semibold text-slate-500 bg-white px-2 py-0.5 rounded-full border border-slate-200">
                                    {{ $statusLabel }}
                                </span>
                            </div>
                            <div class="flex items-center justify-between text-slate-800 font-semibold pt-0.5">
                                <span class="truncate max-w-[45%] text-[#063B00]">{{ $teamAName }}</span>
                                <span class="text-[10px] text-slate-400 font-black">VS</span>
                                <span class="truncate max-w-[45%] text-slate-700 text-right">{{ $teamBName }}</span>
                            </div>
                            <div class="flex items-center justify-between text-[10px] text-slate-500">
                                <span class="truncate max-w-[45%]">{{ implode(' & ', $teamAPlayers) }}</span>
                                <span class="truncate max-w-[45%] text-right">{{ implode(' & ', $teamBPlayers) }}</span>
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
                <span>Format: <strong class="text-[#050608]">{{ $drawingData['format'] ?? ($game['match_format'] ?? 'Team Americano') }} ({{ $drawingData['total_teams'] ?? count($game['participants'] ?? []) }} {{ isset($drawingData['format']) && str_contains(strtolower($drawingData['format']), 'team') ? 'Tim Tetap' : 'Peserta' }})</strong></span>
                <span>Total {{ $unitLabel }}: <strong class="text-[#050608]">{{ $drawingData['total_rounds'] ?? count($rounds) }} {{ $unitLabel }}</strong></span>
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
                    <span class="text-[10px] bg-indigo-50 text-indigo-700 font-bold px-2 py-0.5 rounded-full border border-indigo-200">{{ $game['match_format'] ?? 'Round-Robin' }}</span>
                </div>

                <!-- Team A Roster Card -->
                <div class="p-3.5 rounded-2xl bg-white/80 border border-slate-200/80 space-y-2 shadow-2xs">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-extrabold text-[#063B00]" id="labelTeamAName">
                            {{ $activeRound['primary_match']['team_a']['name'] ?? ($activeRound['team_a']['name'] ?? 'TEAM ALPHA') }}
                        </span>
                        <span class="text-[10px] font-semibold text-slate-400">Sisi Kiri (Court 1)</span>
                    </div>
                    <div class="space-y-1.5" id="rosterTeamA">
                        @forelse($activeRound['teamA'] ?? [] as $idx => $pName)
                            <div class="flex items-center justify-between bg-white p-2 rounded-xl border border-slate-200/70 text-xs shadow-2xs">
                                <span class="font-semibold text-[#050608]">{{ $idx + 1 }}. {{ $pName }}</span>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-50 text-emerald-800 border border-emerald-200">Player {{ $idx + 1 }}</span>
                            </div>
                        @empty
                            <p class="text-[11px] text-slate-400 py-1 text-center">Belum ada pemain</p>
                        @endforelse
                    </div>
                </div>

                <!-- Team B Roster Card -->
                <div class="p-3.5 rounded-2xl bg-white/80 border border-slate-200/80 space-y-2 shadow-2xs">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-extrabold text-slate-800" id="labelTeamBName">
                            {{ $activeRound['primary_match']['team_b']['name'] ?? ($activeRound['team_b']['name'] ?? 'TEAM BETA') }}
                        </span>
                        <span class="text-[10px] font-semibold text-slate-400">Sisi Kanan (Court 1)</span>
                    </div>
                    <div class="space-y-1.5" id="rosterTeamB">
                        @forelse($activeRound['teamB'] ?? [] as $idx => $pName)
                            <div class="flex items-center justify-between bg-white p-2 rounded-xl border border-slate-200/70 text-xs shadow-2xs">
                                <span class="font-semibold text-[#050608]">{{ $idx + 1 }}. {{ $pName }}</span>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-[#eaf3eb] text-[#245b2c] border border-[#bedfc1]">Player {{ $idx + 1 }}</span>
                            </div>
                        @empty
                            <p class="text-[11px] text-slate-400 py-1 text-center">Belum ada pemain</p>
                        @endforelse
                    </div>
                </div>

                <!-- Resting Bench Card -->
                <div class="p-3.5 rounded-2xl bg-white/80 border border-slate-200/80 space-y-2 shadow-2xs">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-slate-700">Bangku Istirahat</span>
                        <span class="text-[10px] text-slate-500" id="labelRestingCount">{{ count($activeRound['resting'] ?? []) }} Pemain</span>
                    </div>
                    <div class="space-y-1.5" id="rosterResting">
                        @forelse($activeRound['resting'] ?? [] as $idx => $pName)
                            <div class="flex items-center justify-between bg-white p-2 rounded-xl border border-slate-200/70 text-xs">
                                <span class="text-slate-700">{{ $idx + 1 }}. {{ $pName }}</span>
                                <span class="text-[10px] text-slate-400 font-semibold">Bench</span>
                            </div>
                        @empty
                            <div class="text-center py-2 text-slate-400 text-xs">
                                Semua pemain aktif bertanding di ronde ini.
                            </div>
                        @endforelse
                    </div>
                </div>

                <!-- CTA Button to Live Scoring -->
                <a href="{{ route('scoring.live', ['id' => $game['id'], 'format' => $game['match_format'] ?? 'Americano']) }}" class="block text-center py-3.5 rounded-2xl bg-[#063B00] hover:bg-[#042a00] text-white font-extrabold text-xs shadow-md transition-all hover:scale-[1.01] active:scale-95">
                    <span>Kunci Tim & Buka Scoring Live</span> <i class="fa-solid fa-arrow-right text-[10px] text-[#A8E63A] ml-1"></i>
                </a>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    let roundsData = @json($drawingData['rounds'] ?? []);
    let participantsMap = @json($participantsMap ?? []);
    const unitLabel = @json($unitLabel ?? 'Round');
    let currentRoundKey = {{ $firstRoundKey }};
    let activeMatchIndex = 0;

    function switchRound(roundNum, notify = true) {
        currentRoundKey = roundNum;

        Object.keys(roundsData).forEach(n => {
            const tab = document.getElementById(`tabRound${n}`);
            if (tab) {
                if (parseInt(n) === roundNum) {
                    tab.className = 'px-4 py-2 rounded-xl text-xs font-bold transition-all shrink-0 bg-[#063B00] text-white shadow-xs';
                } else {
                    tab.className = 'px-4 py-2 rounded-xl text-xs font-bold transition-all shrink-0 glass-card text-slate-600 hover:text-[#050608]';
                }
            }
        });

        const roundData = roundsData[roundNum];
        if (!roundData) return;

        // Update label in visualizer
        const roundTitleElem = document.getElementById('labelCurrentRoundTitle');
        if (roundTitleElem) {
            roundTitleElem.innerText = `${unitLabel} ${roundNum}`;
        }

        // Update Total Matches label
        const labelTotalMatches = document.getElementById('labelTotalMatches');
        if (labelTotalMatches && roundData.matches) {
            labelTotalMatches.innerText = `${roundData.matches.length} Match Berjalan`;
        }

        const matches = roundData.matches || [];

        // Render Court Selector Tabs jika match > 1
        const courtSelectorContainer = document.getElementById('courtSelectorContainer');
        const courtTabsWrapper = document.getElementById('courtTabsWrapper');

        if (courtSelectorContainer && courtTabsWrapper) {
            if (matches.length > 1) {
                courtSelectorContainer.classList.remove('hidden');
                courtTabsWrapper.innerHTML = matches.map((m, idx) => {
                    const cLabel = m.court_name || `Court ${m.court || (idx + 1)}`;
                    const isSelected = idx === activeMatchIndex;
                    return `
                        <button onclick="selectCourtMatch(${idx})" id="btnCourtTab${idx}" class="px-3 py-1.5 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5 cursor-pointer ${isSelected ? 'bg-[#063B00] text-white shadow-xs' : 'glass-card text-slate-600 hover:text-slate-900'}">
                            <i class="fa-solid fa-table-tennis-paddle-ball text-[10px]"></i>
                            ${cLabel}
                        </button>
                    `;
                }).join('');
            } else {
                courtSelectorContainer.classList.add('hidden');
                courtTabsWrapper.innerHTML = '';
            }
        }

        // Render Matches list
        const matchesContainer = document.getElementById('courtMatchesContainer');
        if (matchesContainer && matches.length > 0) {
            matchesContainer.innerHTML = matches.map((m, idx) => {
                const courtLabel = m.court_name || `Court ${m.court || (idx + 1)}`;
                const slotBadge = m.slot_number ? `<span class="text-[9px] font-black uppercase text-indigo-700 bg-indigo-50 border border-indigo-200 px-1.5 py-0.5 rounded-md">Slot ${m.slot_number}</span>` : '';
                const statusLabel = m.status || 'Scheduled';
                
                let teamAName = 'Team A';
                let teamAPlayers = [];
                if (m.team_a && m.team_a.name) {
                    teamAName = m.team_a.name;
                    teamAPlayers = m.team_a.player_names || (m.team_a.players ? m.team_a.players.map(p => p.name || p.nama) : []);
                } else if (m.team_a_names) {
                    teamAName = m.team_a_names.join(' & ');
                    teamAPlayers = m.team_a_names;
                } else if (Array.isArray(m.team_a)) {
                    teamAPlayers = m.team_a.map(p => typeof p === 'object' ? (p.name || p.nama) : String(p));
                    teamAName = teamAPlayers.join(' & ');
                }

                let teamBName = 'Team B';
                let teamBPlayers = [];
                if (m.team_b && m.team_b.name) {
                    teamBName = m.team_b.name;
                    teamBPlayers = m.team_b.player_names || (m.team_b.players ? m.team_b.players.map(p => p.name || p.nama) : []);
                } else if (m.team_b_names) {
                    teamBName = m.team_b_names.join(' & ');
                    teamBPlayers = m.team_b_names;
                } else if (Array.isArray(m.team_b)) {
                    teamBPlayers = m.team_b.map(p => typeof p === 'object' ? (p.name || p.nama) : String(p));
                    teamBName = teamBPlayers.join(' & ');
                }

                const isSelected = (idx === activeMatchIndex);

                return `
                    <div onclick="selectCourtMatch(${idx})" id="matchCard${idx}" class="p-3 bg-slate-50/80 rounded-xl border border-slate-200/70 text-xs space-y-1.5 transition-all hover:bg-white hover:shadow-xs cursor-pointer ${isSelected ? 'ring-2 ring-[#063B00] bg-white shadow-xs' : ''}">
                        <div class="flex items-center justify-between border-b border-slate-200/60 pb-1">
                            <div class="flex items-center gap-1.5">
                                <span class="font-bold text-[#063B00]">${courtLabel}</span>
                                ${slotBadge}
                            </div>
                            <span class="text-[10px] font-semibold text-slate-500 bg-white px-2 py-0.5 rounded-full border border-slate-200">
                                ${statusLabel}
                            </span>
                        </div>
                        <div class="flex items-center justify-between text-slate-800 font-semibold pt-0.5">
                            <span class="truncate max-w-[45%] text-[#063B00]">${teamAName}</span>
                            <span class="text-[10px] text-slate-400 font-black">VS</span>
                            <span class="truncate max-w-[45%] text-slate-700 text-right">${teamBName}</span>
                        </div>
                        <div class="flex items-center justify-between text-[10px] text-slate-500">
                            <span class="truncate max-w-[45%]">${teamAPlayers.join(' & ')}</span>
                            <span class="truncate max-w-[45%] text-right">${teamBPlayers.join(' & ')}</span>
                        </div>
                    </div>
                `;
            }).join('');
        }

        // Tampilkan match yang aktif
        const targetIdx = Math.min(activeMatchIndex, Math.max(0, matches.length - 1));
        selectCourtMatch(targetIdx, false);

        // Update Resting Bench
        renderRestingBench(roundData.resting || []);

        if (notify && typeof showToast === 'function') {
            showToast(`Beralih ke ${unitLabel} ${roundNum}`);
        }
    }

    function selectCourtMatch(matchIdx, notify = true) {
        activeMatchIndex = matchIdx;
        const roundData = roundsData[currentRoundKey];
        if (!roundData) return;

        const matches = roundData.matches || [];
        if (matches.length === 0) {
            renderCourtGraphic(roundData.teamA || [], roundData.teamB || []);
            return;
        }

        const targetMatch = matches[matchIdx] || matches[0];
        const courtName = targetMatch.court_name || `Court ${targetMatch.court || (matchIdx + 1)}`;

        // Update Court Header indicator
        const courtLabelElem = document.getElementById('labelCurrentActiveCourt');
        if (courtLabelElem) courtLabelElem.innerText = courtName;

        // Update Tab states
        matches.forEach((_, idx) => {
            const btn = document.getElementById(`btnCourtTab${idx}`);
            if (btn) {
                if (idx === matchIdx) {
                    btn.className = 'px-3 py-1.5 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5 cursor-pointer bg-[#063B00] text-white shadow-xs';
                } else {
                    btn.className = 'px-3 py-1.5 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5 cursor-pointer glass-card text-slate-600 hover:text-slate-900';
                }
            }
            const matchCard = document.getElementById(`matchCard${idx}`);
            if (matchCard) {
                if (idx === matchIdx) {
                    matchCard.classList.add('ring-2', 'ring-[#063B00]', 'bg-white', 'shadow-xs');
                } else {
                    matchCard.classList.remove('ring-2', 'ring-[#063B00]', 'bg-white', 'shadow-xs');
                }
            }
        });

        // Ekstrak Pemain Team A & Team B
        let teamAPlayers = [];
        let teamBPlayers = [];
        let teamAName = 'TEAM ALPHA';
        let teamBName = 'TEAM BETA';

        if (targetMatch.team_a) {
            if (targetMatch.team_a.name) teamAName = targetMatch.team_a.name;
            if (targetMatch.team_a.player_names) teamAPlayers = targetMatch.team_a.player_names;
            else if (targetMatch.team_a.players) teamAPlayers = targetMatch.team_a.players.map(p => p.name || p.nama);
            else if (Array.isArray(targetMatch.team_a)) teamAPlayers = targetMatch.team_a.map(p => typeof p === 'object' ? (p.name || p.nama) : String(p));
        } else if (targetMatch.team_a_names) {
            teamAPlayers = targetMatch.team_a_names;
            teamAName = teamAPlayers.join(' & ');
        }

        if (targetMatch.team_b) {
            if (targetMatch.team_b.name) teamBName = targetMatch.team_b.name;
            if (targetMatch.team_b.player_names) teamBPlayers = targetMatch.team_b.player_names;
            else if (targetMatch.team_b.players) teamBPlayers = targetMatch.team_b.players.map(p => p.name || p.nama);
            else if (Array.isArray(targetMatch.team_b)) teamBPlayers = targetMatch.team_b.map(p => typeof p === 'object' ? (p.name || p.nama) : String(p));
        } else if (targetMatch.team_b_names) {
            teamBPlayers = targetMatch.team_b_names;
            teamBName = teamBPlayers.join(' & ');
        }

        // Update Labels
        const labelA = document.getElementById('labelTeamAName');
        if (labelA) labelA.innerText = teamAName;
        const labelB = document.getElementById('labelTeamBName');
        if (labelB) labelB.innerText = teamBName;

        // Render Roster Kanan
        const rosterA = document.getElementById('rosterTeamA');
        if (rosterA) {
            rosterA.innerHTML = teamAPlayers.map((name, idx) => `
                <div class="flex items-center justify-between bg-white p-2 rounded-xl border border-slate-200/70 text-xs shadow-2xs">
                    <span class="font-semibold text-slate-800">${idx + 1}. ${name}</span>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-50 text-emerald-800 border border-emerald-200">Player ${idx + 1}</span>
                </div>
            `).join('');
        }

        const rosterB = document.getElementById('rosterTeamB');
        if (rosterB) {
            rosterB.innerHTML = teamBPlayers.map((name, idx) => `
                <div class="flex items-center justify-between bg-white p-2 rounded-xl border border-slate-200/70 text-xs shadow-2xs">
                    <span class="font-semibold text-slate-800">${idx + 1}. ${name}</span>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-[#eaf3eb] text-[#245b2c] border border-[#bedfc1]">Player ${idx + 1}</span>
                </div>
            `).join('');
        }

        // Render Court Graphic
        renderCourtGraphic(teamAPlayers, teamBPlayers);

        if (notify && typeof showToast === 'function') {
            showToast(`Menampilkan visualisasi ${courtName}`);
        }
    }

    function renderRestingBench(resting) {
        const rosterResting = document.getElementById('rosterResting');
        const restingCount = document.getElementById('labelRestingCount');
        if (restingCount) {
            restingCount.innerText = `${resting.length} Pemain`;
        }
        if (rosterResting) {
            if (resting.length > 0) {
                rosterResting.innerHTML = resting.map((name, idx) => `
                    <div class="flex items-center justify-between bg-white p-2 rounded-xl border border-slate-200/70 text-xs">
                        <span class="text-slate-700">${idx + 1}. ${name}</span>
                        <span class="text-[10px] text-slate-400 font-semibold">Bench</span>
                    </div>
                `).join('');
            } else {
                rosterResting.innerHTML = `
                    <div class="text-center py-2 text-slate-400 text-xs">
                        Semua pemain aktif bertanding di ronde ini.
                    </div>
                `;
            }
        }

        const courtRestingSec = document.getElementById('courtVisualRestingSection');
        const courtRestingList = document.getElementById('courtVisualRestingList');
        if (courtRestingSec && courtRestingList) {
            if (resting.length > 0) {
                courtRestingSec.classList.remove('hidden');
                courtRestingList.innerHTML = resting.map(name => `
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-xl bg-white/80 text-slate-700 text-xs border border-slate-200/70 font-medium shadow-2xs">
                        <i class="fa-regular fa-clock text-slate-400 text-[10px]"></i> ${name}
                    </span>
                `).join('');
            } else {
                courtRestingSec.classList.add('hidden');
                courtRestingList.innerHTML = '';
            }
        }
    }

    function renderCourtGraphic(teamA, teamB) {
        const courtA = document.getElementById('courtTeamA');
        if (courtA) {
            courtA.innerHTML = teamA.map(name => {
                const female = isFemalePlayer(name);
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
            courtB.innerHTML = teamB.map(name => {
                const female = isFemalePlayer(name);
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

    function isFemalePlayer(name) {
        if (!name) return false;
        const p = participantsMap[name] || participantsMap[name.trim()];
        if (p && p.gender) {
            return ['female', 'perempuan', 'f', 'p', 'wanita'].includes(String(p.gender).toLowerCase());
        }
        return /gisel|davina|marame|putri|anastasia|sarah|siti|female|wanita|dewi|maya|lisa|mau|sekarang|naykila|sisil/i.test(name);
    }

    function runDrawingAnimation() {
        const shuffleBtn = document.getElementById('shuffleBtn');
        const shuffleIcon = document.getElementById('shuffleIcon');
        
        if (shuffleBtn) {
            shuffleBtn.disabled = true;
            shuffleBtn.classList.add('opacity-60', 'pointer-events-none');
        }
        if (shuffleIcon) {
            shuffleIcon.classList.add('animate-spin');
        }

        const formatParam = encodeURIComponent('{{ $game['match_format'] ?? 'Americano' }}');
        const fetchUrl = `{{ route('games.drawing', $game['id']) }}?format=${formatParam}&seed=${Date.now()}&shuffle=1&json=1`;

        fetch(fetchUrl, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            if (data && (data.rounds || (data.drawingData && data.drawingData.rounds))) {
                roundsData = data.rounds || data.drawingData.rounds;
                if (data.participantsMap) {
                    participantsMap = data.participantsMap;
                }

                // Cek apakah currentRoundKey masih tersedia di ronde baru
                const roundKeys = Object.keys(roundsData).map(Number);
                if (!roundKeys.includes(currentRoundKey) && roundKeys.length > 0) {
                    currentRoundKey = roundKeys[0];
                }

                // Render ulang ronde aktif tanpa toast perpindahan ronde
                switchRound(currentRoundKey, false);

                // Animasi flash halus pada court container
                const courtContainer = document.getElementById('courtContainer');
                if (courtContainer) {
                    courtContainer.classList.add('scale-[0.98]', 'transition-all', 'duration-200');
                    setTimeout(() => {
                        courtContainer.classList.remove('scale-[0.98]');
                    }, 200);
                }

                if (typeof showToast === 'function') {
                    showToast('Jadwal & rotasi pemain berhasil diacak ulang! 🎲');
                }
            } else {
                window.location.reload();
            }
        })
        .catch(err => {
            console.warn('Asynchronous shuffle failed, falling back to page reload:', err);
            window.location.reload();
        })
        .finally(() => {
            setTimeout(() => {
                if (shuffleIcon) shuffleIcon.classList.remove('animate-spin');
                if (shuffleBtn) {
                    shuffleBtn.classList.remove('opacity-60', 'pointer-events-none');
                    shuffleBtn.disabled = false;
                }
            }, 300);
        });
    }

    // Sinkronisasi otomatis data ronde pertama saat halaman dimuat
    document.addEventListener('DOMContentLoaded', () => {
        const firstKey = {{ $firstRoundKey }};
        if (typeof switchRound === 'function') {
            switchRound(firstKey, false);
        }
    });
</script>
@endpush
@endsection
