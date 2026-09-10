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
        
        <!-- Left: Court Graphic Visualizer & Bench (2 Cols) -->
        <div class="lg:col-span-2 space-y-5">
            
            <!-- Section Title & Match Count Badge -->
            <div class="flex items-center justify-between pb-1 border-b border-slate-200/60">
                <div class="flex items-center gap-2">
                    <span class="w-3 h-3 rounded-full bg-emerald-600 animate-pulse"></span>
                    <h3 class="text-xs sm:text-sm font-bold uppercase tracking-wider text-slate-900 flex items-center gap-1.5">
                        <i class="fa-solid fa-table-tennis-paddle-ball text-[#063B00]"></i>
                        Live Preview Lapangan &bull; <span id="labelCurrentRoundTitle" class="text-[#063B00]">{{ $unitLabel }} 1</span>
                    </h3>
                </div>
                <span class="text-xs font-semibold text-slate-500 bg-white px-2.5 py-1 rounded-full border border-slate-200 shadow-2xs" id="labelTotalMatches">
                    {{ count($activeRound['matches'] ?? []) ?: 1 }} Lapangan Berjalan
                </span>
            </div>

            <!-- 2D Court Visualizer Container (Simultaneous Multi-Court Preview) -->
            <div id="courtsMultiContainer" class="{{ count($activeRound['matches'] ?? []) > 1 ? 'grid grid-cols-1 md:grid-cols-2 gap-4' : 'w-full max-w-2xl mx-auto' }}">
                @php
                    $initMatches = !empty($activeRound['matches']) ? $activeRound['matches'] : [
                        [
                            'court_name' => 'Court 1',
                            'court' => 1,
                            'team_a' => $activeRound['teamA'] ?? [],
                            'team_b' => $activeRound['teamB'] ?? [],
                            'team_a_names' => $activeRound['teamA_names'] ?? [],
                            'team_b_names' => $activeRound['teamB_names'] ?? [],
                            'status' => 'Scheduled'
                        ]
                    ];
                @endphp

                @foreach($initMatches as $mIdx => $m)
                    @php
                        $cName = $m['court_name'] ?? ('Court ' . ($m['court'] ?? ($mIdx + 1)));
                        $tAPlayers = !empty($m['team_a_names']) ? $m['team_a_names'] : (is_array($m['team_a']) ? array_column($m['team_a'], 'name') : []);
                        if (empty($tAPlayers) && is_array($m['team_a'])) {
                            $tAPlayers = array_map(fn($p) => is_array($p) ? ($p['name'] ?? $p['nama'] ?? '') : (string)$p, $m['team_a']);
                        }
                        $tBPlayers = !empty($m['team_b_names']) ? $m['team_b_names'] : (is_array($m['team_b']) ? array_column($m['team_b'], 'name') : []);
                        if (empty($tBPlayers) && is_array($m['team_b'])) {
                            $tBPlayers = array_map(fn($p) => is_array($p) ? ($p['name'] ?? $p['nama'] ?? '') : (string)$p, $m['team_b']);
                        }
                        $tAName = $m['team_a']['name'] ?? ($m['team_a_name'] ?? (count($initMatches) > 1 ? "Court " . ($mIdx + 1) . " - Team A" : "Team A"));
                        $tBName = $m['team_b']['name'] ?? ($m['team_b_name'] ?? (count($initMatches) > 1 ? "Court " . ($mIdx + 1) . " - Team B" : "Team B"));
                    @endphp

                    <div class="rounded-3xl glass-card p-4 sm:p-5 shadow-sm border border-white/80 space-y-3">
                        <div class="flex items-center justify-between border-b border-slate-200/50 pb-2.5">
                            <div class="flex items-center gap-2">
                                <span class="w-2.5 h-2.5 rounded-full bg-emerald-600 animate-pulse"></span>
                                <h4 class="text-xs font-bold uppercase tracking-wider text-slate-800">
                                    {{ $cName }}
                                </h4>
                                @if(!empty($m['slot_number']))
                                    <span class="text-[9px] font-black uppercase text-indigo-700 bg-indigo-50 border border-indigo-200 px-1.5 py-0.5 rounded-md">Slot {{ $m['slot_number'] }}</span>
                                @endif
                            </div>
                            <span class="text-[10px] text-slate-500 font-semibold bg-white/70 px-2 py-0.5 rounded-full border border-slate-200/60 shadow-xs">
                                {{ $m['status'] ?? 'Scheduled' }}
                            </span>
                        </div>

                        <!-- Realistic Padel & Tennis Court Graphic (Authentic Proportions & Lines) -->
                        <div class="relative w-full aspect-[16/10] min-h-[300px] sm:min-h-[340px] md:min-h-[360px] bg-gradient-to-b from-[#123e22] via-[#10371e] to-[#0c2b17] rounded-3xl border-2 border-white/90 p-4 sm:p-5 flex flex-col justify-between overflow-hidden shadow-lg">
                            <!-- Outer Safety Boundary Line -->
                            <div class="absolute inset-3 sm:inset-4 border-2 border-white/85 rounded-xl pointer-events-none shadow-xs"></div>
                            
                            <!-- Vertical Service Lines (Left & Right Boxes) -->
                            <div class="absolute inset-y-3 sm:inset-y-4 left-[28%] w-0.5 bg-white/70 pointer-events-none"></div>
                            <div class="absolute inset-y-3 sm:inset-y-4 left-[72%] w-0.5 bg-white/70 pointer-events-none"></div>

                            <!-- Center Service T-Line -->
                            <div class="absolute top-1/2 left-[28%] right-[28%] h-0.5 -translate-y-1/2 bg-white/70 pointer-events-none"></div>

                            <!-- Center Net with Net Posts & Badge -->
                            <div class="absolute inset-y-0 left-1/2 w-1 -translate-x-1/2 bg-white flex flex-col justify-between items-center z-10 pointer-events-none shadow-md">
                                <div class="w-3 h-3 bg-[#050608] border-2 border-white rounded-full -mt-1 shadow-xs"></div>
                                <span class="bg-[#050608]/90 text-[#A8E63A] font-black text-[7.5px] px-1.5 py-0.5 rounded tracking-widest uppercase border border-[#A8E63A]/50 rotate-90 my-auto shadow-xs">NET</span>
                                <div class="w-3 h-3 bg-[#050608] border-2 border-white rounded-full -mb-1 shadow-xs"></div>
                            </div>

                            <div class="relative z-20 flex justify-between h-full">
                                <!-- Team A (Left Half) -->
                                <div class="w-1/2 pr-2 flex flex-col justify-between h-full">
                                    <span class="text-[9px] font-black uppercase tracking-wider px-2.5 py-0.5 rounded-full bg-black/40 backdrop-blur-xs text-[#A8E63A] border border-[#A8E63A]/40 w-fit shadow-xs">
                                        {{ $tAName }}
                                    </span>
                                    <div class="flex flex-col justify-around gap-2 my-auto py-2 h-full">
                                        @foreach($tAPlayers as $pName)
                                            @php
                                                $isFemale = preg_match('/gisel|davina|marame|putri|anastasia|sarah|siti|female|wanita|dewi|maya|lisa|naykila|sisil/i', $pName);
                                            @endphp
                                            <div class="flex flex-col items-center justify-center text-center transform transition-transform hover:scale-105 w-fit mx-auto sm:mx-6 my-auto">
                                                <div class="w-9 h-9 sm:w-11 sm:h-11 rounded-full {{ $isFemale ? 'bg-gradient-to-br from-rose-400 to-pink-600' : 'bg-gradient-to-br from-sky-400 to-blue-600' }} text-white border-2 border-white shadow-lg flex items-center justify-center text-xs sm:text-sm mb-1 ring-2 ring-black/20">
                                                    <i class="{{ $isFemale ? 'fa-solid fa-person-dress' : 'fa-solid fa-person' }}"></i>
                                                </div>
                                                <span class="text-[10px] sm:text-[11px] font-bold text-white text-center leading-tight bg-black/50 backdrop-blur-xs px-2.5 py-0.5 rounded-full border border-white/20 shadow-xs max-w-[95px] sm:max-w-[120px] truncate">
                                                    {{ $pName }}
                                                </span>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>

                                <!-- Team B (Right Half) -->
                                <div class="w-1/2 pl-2 flex flex-col justify-between h-full items-end text-right">
                                    <span class="text-[9px] font-black uppercase tracking-wider px-2.5 py-0.5 rounded-full bg-black/40 backdrop-blur-xs text-[#A8E63A] border border-[#A8E63A]/40 w-fit shadow-xs">
                                        {{ $tBName }}
                                    </span>
                                    <div class="flex flex-col justify-around gap-2 my-auto py-2 h-full items-end w-full">
                                        @foreach($tBPlayers as $pName)
                                            @php
                                                $isFemale = preg_match('/gisel|davina|marame|putri|anastasia|sarah|siti|female|wanita|dewi|maya|lisa|naykila|sisil/i', $pName);
                                            @endphp
                                            <div class="flex flex-col items-center justify-center text-center transform transition-transform hover:scale-105 w-fit mx-auto sm:mx-6 my-auto">
                                                <div class="w-9 h-9 sm:w-11 sm:h-11 rounded-full {{ $isFemale ? 'bg-gradient-to-br from-rose-400 to-pink-600' : 'bg-gradient-to-br from-sky-400 to-blue-600' }} text-white border-2 border-white shadow-lg flex items-center justify-center text-xs sm:text-sm mb-1 ring-2 ring-black/20">
                                                    <i class="{{ $isFemale ? 'fa-solid fa-person-dress' : 'fa-solid fa-person' }}"></i>
                                                </div>
                                                <span class="text-[10px] sm:text-[11px] font-bold text-white text-center leading-tight bg-black/50 backdrop-blur-xs px-2.5 py-0.5 rounded-full border border-white/20 shadow-xs max-w-[95px] sm:max-w-[120px] truncate">
                                                    {{ $pName }}
                                                </span>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <!-- Bangku Istirahat (Bench) - Tepat di Bawah Preview Lapangan 2D -->
            <div id="benchContainer" class="glass-card rounded-2xl p-4 sm:p-5 border border-white/90 shadow-sm space-y-3">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2.5">
                        <span class="w-7 h-7 rounded-xl bg-amber-50 text-amber-700 border border-amber-200 flex items-center justify-center text-xs shadow-2xs">
                            <i class="fa-solid fa-chair"></i>
                        </span>
                        <div>
                            <h4 class="text-xs font-bold text-slate-800 uppercase tracking-wider flex items-center gap-1.5">
                                Bangku Istirahat (Bench)
                            </h4>
                            <p class="text-[11px] text-slate-500">Pemain yang giliran istirahat pada ronde/set ini (tidak aktif di Court 1 / Court 2)</p>
                        </div>
                    </div>
                    <span id="labelBenchCount" class="text-xs font-bold px-3 py-1 rounded-full bg-amber-50 text-amber-800 border border-amber-200 shadow-2xs">
                        {{ count($activeRound['resting'] ?? []) }} Pemain
                    </span>
                </div>

                <div id="benchListContainer" class="flex flex-wrap gap-2.5 pt-1">
                    @forelse($activeRound['resting'] ?? [] as $rName)
                        <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl bg-white text-slate-800 text-xs border border-slate-200 font-medium shadow-2xs">
                            <span class="w-5 h-5 rounded-full bg-amber-100 text-amber-800 text-[10px] flex items-center justify-center font-bold">
                                <i class="fa-solid fa-mug-hot text-[9px]"></i>
                            </span>
                            {{ $rName }}
                            <span class="text-[9px] font-bold text-amber-700 bg-amber-50 px-1.5 py-0.5 rounded border border-amber-200">Bench</span>
                        </span>
                    @empty
                        <p class="text-xs text-slate-400 py-1">Semua pemain aktif bertanding di ronde/set ini.</p>
                    @endforelse
                </div>
            </div>

            <!-- Multi-Court Schedule List on Active Round -->
            <div class="glass-card rounded-2xl p-4 space-y-3">
                <div class="flex items-center justify-between">
                    <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wider flex items-center gap-1.5">
                        <i class="fa-solid fa-table-tennis-paddle-ball text-[#063B00]"></i>
                        Rincian Alokasi Match Lapangan
                    </h3>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5" id="courtMatchesContainer">
                    @forelse($initMatches as $mIdx => $m)
                        @php
                            $courtLabel = $m['court_name'] ?? ('Court ' . ($m['court'] ?? ($mIdx + 1)));
                            $statusLabel = $m['status'] ?? 'Scheduled';
                            $teamAName = $m['team_a']['name'] ?? ($m['team_a_name'] ?? 'Team A');
                            $teamBName = $m['team_b']['name'] ?? ($m['team_b_name'] ?? 'Team B');
                            $teamAPlayers = !empty($m['team_a_names']) ? $m['team_a_names'] : (is_array($m['team_a']) ? array_column($m['team_a'], 'name') : []);
                            $teamBPlayers = !empty($m['team_b_names']) ? $m['team_b_names'] : (is_array($m['team_b']) ? array_column($m['team_b'], 'name') : []);
                        @endphp
                        <div class="p-3 bg-slate-50/80 rounded-xl border border-slate-200/70 text-xs space-y-1.5 transition-all">
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

        <!-- Right: Team Roster Cards & Action (1 Col) -->
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
                        <span class="text-[10px] font-semibold text-slate-400">Court 1 (Sisi Kiri)</span>
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
                        <span class="text-[10px] font-semibold text-slate-400">Court 1 (Sisi Kanan)</span>
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

                <!-- Resting Bench Card in Sidebar -->
                <div class="p-3.5 rounded-2xl bg-white/80 border border-slate-200/80 space-y-2 shadow-2xs">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-slate-700">Bangku Cadangan / Istirahat</span>
                        <span class="text-[10px] text-amber-700 font-bold bg-amber-50 px-2 py-0.5 rounded-full border border-amber-200" id="labelRestingCount">{{ count($activeRound['resting'] ?? []) }} Pemain</span>
                    </div>
                    <div class="space-y-1.5" id="rosterResting">
                        @forelse($activeRound['resting'] ?? [] as $idx => $pName)
                            <div class="flex items-center justify-between bg-white p-2 rounded-xl border border-slate-200/70 text-xs">
                                <span class="text-slate-700">{{ $idx + 1 }}. {{ $pName }}</span>
                                <span class="text-[10px] text-amber-600 font-semibold bg-amber-50 px-2 py-0.5 rounded border border-amber-200">Bench</span>
                            </div>
                        @empty
                            <div class="text-center py-2 text-slate-400 text-xs">
                                Semua pemain aktif bertanding di ronde ini.
                            </div>
                        @endforelse
                    </div>
                </div>

                <!-- CTA Button to Live Scoring / Lock Drawing -->
                @if($isLocked ?? false)
                    <a href="{{ route('scoring.live', ['id' => $game['id'], 'format' => $game['match_format'] ?? 'Americano']) }}" class="block text-center py-3.5 rounded-2xl bg-amber-600 hover:bg-amber-700 text-white font-extrabold text-xs shadow-md transition-all hover:scale-[1.01] active:scale-95">
                        <i class="fa-solid fa-lock mr-1.5"></i> <span>Buka Scoring Live (Match Sedang Berjalan)</span> <i class="fa-solid fa-arrow-right text-[10px] ml-1"></i>
                    </a>
                @else
                    <form method="POST" action="{{ route('games.lock', $game['id']) }}" class="block">
                        @csrf
                        <input type="hidden" name="format" value="{{ $game['match_format'] ?? 'Americano' }}">
                        <button type="submit" class="w-full text-center py-3.5 rounded-2xl bg-[#063B00] hover:bg-[#042a00] text-white font-extrabold text-xs shadow-md transition-all hover:scale-[1.01] active:scale-95 cursor-pointer">
                            <span>Kunci Tim & Buka Scoring Live</span> <i class="fa-solid fa-arrow-right text-[10px] text-[#A8E63A] ml-1"></i>
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    let roundsData = @json($drawingData['rounds'] ?? []);
    let participantsMap = @json($participantsMap ?? []);
    let allParticipants = @json($game['participants'] ?? []);
    const unitLabel = @json($unitLabel ?? 'Round');
    let currentRoundKey = {{ $firstRoundKey }};
    const isSessionLocked = @json($isLocked ?? false);

    function cleanPlayerName(n) {
        if (!n) return '';
        if (typeof n === 'object') n = n.name || n.nama || '';
        return String(n).trim().toLowerCase();
    }

    function extractMatchPlayers(teamData, namesArray) {
        if (Array.isArray(namesArray) && namesArray.length > 0) return namesArray;
        if (!teamData) return [];
        if (Array.isArray(teamData)) {
            return teamData.map(p => typeof p === 'object' ? (p.name || p.nama || '') : String(p)).filter(Boolean);
        }
        if (typeof teamData === 'object') {
            if (Array.isArray(teamData.player_names)) return teamData.player_names;
            if (Array.isArray(teamData.players)) return teamData.players.map(p => p.name || p.nama || '').filter(Boolean);
            if (teamData.name) return [teamData.name];
        }
        return [String(teamData)];
    }

    function isFemalePlayer(name) {
        if (!name) return false;
        const clean = cleanPlayerName(name);
        const p = allParticipants.find(x => cleanPlayerName(x.name) === clean) || participantsMap[name] || participantsMap[clean];
        if (p && p.gender) {
            return ['female', 'perempuan', 'f', 'p', 'wanita'].includes(String(p.gender).toLowerCase());
        }
        return /gisel|davina|marame|putri|anastasia|sarah|siti|female|wanita|dewi|maya|lisa|naykila|sisil/i.test(name);
    }

    function buildCourtCardHtml(match, matchIdx, totalMatches) {
        const courtName = match.court_name || `Court ${match.court || (matchIdx + 1)}`;
        const slotBadge = match.slot_number ? `<span class="text-[9px] font-black uppercase text-indigo-700 bg-indigo-50 border border-indigo-200 px-1.5 py-0.5 rounded-md">Slot ${match.slot_number}</span>` : '';
        
        let teamAName = 'Team A';
        if (match.team_a && match.team_a.name) teamAName = match.team_a.name;
        else if (match.team_a_name) teamAName = match.team_a_name;
        else if (totalMatches > 1) teamAName = `${courtName} - Team A`;
        
        let teamBName = 'Team B';
        if (match.team_b && match.team_b.name) teamBName = match.team_b.name;
        else if (match.team_b_name) teamBName = match.team_b_name;
        else if (totalMatches > 1) teamBName = `${courtName} - Team B`;

        const playersA = extractMatchPlayers(match.team_a, match.team_a_names);
        const playersB = extractMatchPlayers(match.team_b, match.team_b_names);

        const playersAHtml = playersA.map(name => {
            const female = isFemalePlayer(name);
            return `
                <div class="flex flex-col items-center justify-center text-center transform transition-transform hover:scale-105 w-fit mx-auto sm:mx-6 my-auto">
                    <div class="w-9 h-9 sm:w-11 sm:h-11 rounded-full ${female ? 'bg-gradient-to-br from-rose-400 to-pink-600' : 'bg-gradient-to-br from-sky-400 to-blue-600'} text-white border-2 border-white shadow-lg flex items-center justify-center text-xs sm:text-sm mb-1 ring-2 ring-black/20">
                        <i class="${female ? 'fa-solid fa-person-dress' : 'fa-solid fa-person'}"></i>
                    </div>
                    <span class="text-[10px] sm:text-[11px] font-bold text-white text-center leading-tight bg-black/50 backdrop-blur-xs px-2.5 py-0.5 rounded-full border border-white/20 shadow-xs max-w-[95px] sm:max-w-[120px] truncate">
                        ${name}
                    </span>
                </div>
            `;
        }).join('');

        const playersBHtml = playersB.map(name => {
            const female = isFemalePlayer(name);
            return `
                <div class="flex flex-col items-center justify-center text-center transform transition-transform hover:scale-105 w-fit mx-auto sm:mx-6 my-auto">
                    <div class="w-9 h-9 sm:w-11 sm:h-11 rounded-full ${female ? 'bg-gradient-to-br from-rose-400 to-pink-600' : 'bg-gradient-to-br from-sky-400 to-blue-600'} text-white border-2 border-white shadow-lg flex items-center justify-center text-xs sm:text-sm mb-1 ring-2 ring-black/20">
                        <i class="${female ? 'fa-solid fa-person-dress' : 'fa-solid fa-person'}"></i>
                    </div>
                    <span class="text-[10px] sm:text-[11px] font-bold text-white text-center leading-tight bg-black/50 backdrop-blur-xs px-2.5 py-0.5 rounded-full border border-white/20 shadow-xs max-w-[95px] sm:max-w-[120px] truncate">
                        ${name}
                    </span>
                </div>
            `;
        }).join('');

        return `
            <div class="rounded-3xl glass-card p-4 sm:p-5 shadow-sm border border-white/80 space-y-3">
                <div class="flex items-center justify-between border-b border-slate-200/50 pb-2.5">
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-emerald-600 animate-pulse"></span>
                        <h4 class="text-xs font-bold uppercase tracking-wider text-slate-800">
                            ${courtName}
                        </h4>
                        ${slotBadge}
                    </div>
                    <span class="text-[10px] text-slate-500 font-semibold bg-white/70 px-2 py-0.5 rounded-full border border-slate-200/60 shadow-xs">
                        ${match.status || 'Scheduled'}
                    </span>
                </div>

                <!-- Realistic Padel & Tennis Court Graphic (Authentic Proportions & Lines) -->
                <div class="relative w-full aspect-[16/10] min-h-[300px] sm:min-h-[340px] md:min-h-[360px] bg-gradient-to-b from-[#123e22] via-[#10371e] to-[#0c2b17] rounded-3xl border-2 border-white/90 p-4 sm:p-5 flex flex-col justify-between overflow-hidden shadow-lg">
                    <!-- Outer Safety Boundary Line -->
                    <div class="absolute inset-3 sm:inset-4 border-2 border-white/85 rounded-xl pointer-events-none shadow-xs"></div>
                    
                    <!-- Vertical Service Lines (Left & Right Boxes) -->
                    <div class="absolute inset-y-3 sm:inset-y-4 left-[28%] w-0.5 bg-white/70 pointer-events-none"></div>
                    <div class="absolute inset-y-3 sm:inset-y-4 left-[72%] w-0.5 bg-white/70 pointer-events-none"></div>

                    <!-- Center Service T-Line -->
                    <div class="absolute top-1/2 left-[28%] right-[28%] h-0.5 -translate-y-1/2 bg-white/70 pointer-events-none"></div>

                    <!-- Center Net with Net Posts & Badge -->
                    <div class="absolute inset-y-0 left-1/2 w-1 -translate-x-1/2 bg-white flex flex-col justify-between items-center z-10 pointer-events-none shadow-md">
                        <div class="w-3 h-3 bg-[#050608] border-2 border-white rounded-full -mt-1 shadow-xs"></div>
                        <span class="bg-[#050608]/90 text-[#A8E63A] font-black text-[7.5px] px-1.5 py-0.5 rounded tracking-widest uppercase border border-[#A8E63A]/50 rotate-90 my-auto shadow-xs">NET</span>
                        <div class="w-3 h-3 bg-[#050608] border-2 border-white rounded-full -mb-1 shadow-xs"></div>
                    </div>

                    <div class="relative z-20 flex justify-between h-full">
                        <!-- Team A (Left Half) -->
                        <div class="w-1/2 pr-2 flex flex-col justify-between h-full">
                            <span class="text-[9px] font-black uppercase tracking-wider px-2.5 py-0.5 rounded-full bg-black/40 backdrop-blur-xs text-[#A8E63A] border border-[#A8E63A]/40 w-fit shadow-xs">
                                ${teamAName}
                            </span>
                            <div class="flex flex-col justify-around gap-2 my-auto py-2 h-full">
                                ${playersAHtml}
                            </div>
                        </div>

                        <!-- Team B (Right Half) -->
                        <div class="w-1/2 pl-2 flex flex-col justify-between h-full items-end text-right">
                            <span class="text-[9px] font-black uppercase tracking-wider px-2.5 py-0.5 rounded-full bg-black/40 backdrop-blur-xs text-[#A8E63A] border border-[#A8E63A]/40 w-fit shadow-xs">
                                ${teamBName}
                            </span>
                            <div class="flex flex-col justify-around gap-2 my-auto py-2 h-full items-end w-full">
                                ${playersBHtml}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    function switchRound(roundNum, notify = true) {
        currentRoundKey = roundNum;

        // Update tabs styling
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

        // Ambil data matches (Court 1, Court 2, dst)
        let matches = roundData.matches || [];
        if (matches.length === 0) {
            matches = [{
                court_name: 'Court 1',
                court: 1,
                team_a: roundData.teamA || roundData.team_a || [],
                team_b: roundData.teamB || roundData.team_b || [],
                team_a_names: roundData.teamA_names || [],
                team_b_names: roundData.teamB_names || [],
                status: 'Scheduled'
            }];
        }

        // Update Total Matches label
        const labelTotalMatches = document.getElementById('labelTotalMatches');
        if (labelTotalMatches) {
            labelTotalMatches.innerText = `${matches.length} Lapangan Berjalan`;
        }

        // 1. RENDER SEMUA LAPANGAN SEKALIGUS (Court 1 & Court 2 preview)
        const courtsMultiContainer = document.getElementById('courtsMultiContainer');
        if (courtsMultiContainer) {
            if (matches.length > 1) {
                courtsMultiContainer.className = 'grid grid-cols-1 md:grid-cols-2 gap-4';
            } else {
                courtsMultiContainer.className = 'w-full max-w-2xl mx-auto';
            }
            courtsMultiContainer.innerHTML = matches.map((m, idx) => buildCourtCardHtml(m, idx, matches.length)).join('');
        }

        // 2. HITUNG & RENDER BANGKU ISTIRAHAT SECARA REAKTIF
        // Rumus: Total peserta - semua pemain yang bertanding di semua court pada ronde ini
        const activePlayerNames = new Set();
        matches.forEach(m => {
            const pA = extractMatchPlayers(m.team_a, m.team_a_names);
            const pB = extractMatchPlayers(m.team_b, m.team_b_names);
            pA.forEach(n => activePlayerNames.add(cleanPlayerName(n)));
            pB.forEach(n => activePlayerNames.add(cleanPlayerName(n)));
        });

        let restingPlayers = [];
        if (allParticipants && allParticipants.length > 0) {
            restingPlayers = allParticipants.filter(p => !activePlayerNames.has(cleanPlayerName(p.name)));
        } else if (roundData.resting && roundData.resting.length > 0) {
            restingPlayers = roundData.resting.map(name => ({ name }));
        }

        // Render Bench di Bawah Lapangan
        const benchListContainer = document.getElementById('benchListContainer');
        const labelBenchCount = document.getElementById('labelBenchCount');
        if (labelBenchCount) {
            labelBenchCount.innerText = `${restingPlayers.length} Pemain`;
        }
        if (benchListContainer) {
            if (restingPlayers.length > 0) {
                benchListContainer.innerHTML = restingPlayers.map(p => `
                    <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl bg-white text-slate-800 text-xs border border-slate-200 font-medium shadow-2xs">
                        <span class="w-5 h-5 rounded-full bg-amber-100 text-amber-800 text-[10px] flex items-center justify-center font-bold">
                            <i class="fa-solid fa-mug-hot text-[9px]"></i>
                        </span>
                        ${p.name || p}
                        <span class="text-[9px] font-bold text-amber-700 bg-amber-50 px-1.5 py-0.5 rounded border border-amber-200">Bench</span>
                    </span>
                `).join('');
            } else {
                benchListContainer.innerHTML = `<p class="text-xs text-slate-400 py-1">Semua pemain aktif bertanding di ronde/set ini.</p>`;
            }
        }

        // Render Bench di Sidebar Kanan
        const rosterResting = document.getElementById('rosterResting');
        const labelRestingCount = document.getElementById('labelRestingCount');
        if (labelRestingCount) labelRestingCount.innerText = `${restingPlayers.length} Pemain`;
        if (rosterResting) {
            if (restingPlayers.length > 0) {
                rosterResting.innerHTML = restingPlayers.map((p, idx) => `
                    <div class="flex items-center justify-between bg-white p-2 rounded-xl border border-slate-200/70 text-xs">
                        <span class="text-slate-700">${idx + 1}. ${p.name || p}</span>
                        <span class="text-[10px] text-amber-600 font-semibold bg-amber-50 px-2 py-0.5 rounded border border-amber-200">Bench</span>
                    </div>
                `).join('');
            } else {
                rosterResting.innerHTML = `<div class="text-center py-2 text-slate-400 text-xs">Semua pemain aktif bertanding di ronde ini.</div>`;
            }
        }

        // 3. Render Match Schedule Summary List di Bawah Bench
        const matchesContainer = document.getElementById('courtMatchesContainer');
        if (matchesContainer) {
            matchesContainer.innerHTML = matches.map((m, idx) => {
                const courtLabel = m.court_name || `Court ${m.court || (idx + 1)}`;
                const slotBadge = m.slot_number ? `<span class="text-[9px] font-black uppercase text-indigo-700 bg-indigo-50 border border-indigo-200 px-1.5 py-0.5 rounded-md">Slot ${m.slot_number}</span>` : '';
                const pA = extractMatchPlayers(m.team_a, m.team_a_names);
                const pB = extractMatchPlayers(m.team_b, m.team_b_names);
                const tAName = m.team_a && m.team_a.name ? m.team_a.name : (pA.join(' & ') || 'Team A');
                const tBName = m.team_b && m.team_b.name ? m.team_b.name : (pB.join(' & ') || 'Team B');

                return `
                    <div class="p-3 bg-slate-50/80 rounded-xl border border-slate-200/70 text-xs space-y-1.5 transition-all">
                        <div class="flex items-center justify-between border-b border-slate-200/60 pb-1">
                            <div class="flex items-center gap-1.5">
                                <span class="font-bold text-[#063B00]">${courtLabel}</span>
                                ${slotBadge}
                            </div>
                            <span class="text-[10px] font-semibold text-slate-500 bg-white px-2 py-0.5 rounded-full border border-slate-200">
                                ${m.status || 'Scheduled'}
                            </span>
                        </div>
                        <div class="flex items-center justify-between text-slate-800 font-semibold pt-0.5">
                            <span class="truncate max-w-[45%] text-[#063B00]">${tAName}</span>
                            <span class="text-[10px] text-slate-400 font-black">VS</span>
                            <span class="truncate max-w-[45%] text-slate-700 text-right">${tBName}</span>
                        </div>
                        <div class="flex items-center justify-between text-[10px] text-slate-500">
                            <span class="truncate max-w-[45%]">${pA.join(' & ')}</span>
                            <span class="truncate max-w-[45%] text-right">${pB.join(' & ')}</span>
                        </div>
                    </div>
                `;
            }).join('');
        }

        // 4. Update Roster Sidebar Kanan untuk Court 1
        const primaryMatch = matches[0] || {};
        const pA = extractMatchPlayers(primaryMatch.team_a, primaryMatch.team_a_names);
        const pB = extractMatchPlayers(primaryMatch.team_b, primaryMatch.team_b_names);
        const tAName = primaryMatch.team_a && primaryMatch.team_a.name ? primaryMatch.team_a.name : (pA.join(' & ') || 'Team A');
        const tBName = primaryMatch.team_b && primaryMatch.team_b.name ? primaryMatch.team_b.name : (pB.join(' & ') || 'Team B');

        const labelA = document.getElementById('labelTeamAName');
        if (labelA) labelA.innerText = tAName;
        const labelB = document.getElementById('labelTeamBName');
        if (labelB) labelB.innerText = tBName;

        const rosterA = document.getElementById('rosterTeamA');
        if (rosterA) {
            rosterA.innerHTML = pA.map((name, idx) => `
                <div class="flex items-center justify-between bg-white p-2 rounded-xl border border-slate-200/70 text-xs shadow-2xs">
                    <span class="font-semibold text-slate-800">${idx + 1}. ${name}</span>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-50 text-emerald-800 border border-emerald-200">Player ${idx + 1}</span>
                </div>
            `).join('');
        }

        const rosterB = document.getElementById('rosterTeamB');
        if (rosterB) {
            rosterB.innerHTML = pB.map((name, idx) => `
                <div class="flex items-center justify-between bg-white p-2 rounded-xl border border-slate-200/70 text-xs shadow-2xs">
                    <span class="font-semibold text-slate-800">${idx + 1}. ${name}</span>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-[#eaf3eb] text-[#245b2c] border border-[#bedfc1]">Player ${idx + 1}</span>
                </div>
            `).join('');
        }

        if (notify && typeof showToast === 'function') {
            showToast(`Beralih ke ${unitLabel} ${roundNum}`);
        }
    }

    function runDrawingAnimation() {
        if (isSessionLocked) {
            if (typeof showToast === 'function') {
                showToast('Jadwal sudah dikunci karena pertandingan sudah berjalan.');
            }
            return;
        }

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
                const courtContainer = document.getElementById('courtsMultiContainer');
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
