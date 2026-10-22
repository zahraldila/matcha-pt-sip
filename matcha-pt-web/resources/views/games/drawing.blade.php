@extends('layouts.app')

@section('content')
@php
    $rounds = !empty($rounds) ? $rounds : ($drawingData['rounds'] ?? []);
    $firstRoundKey = !empty($rounds) ? array_key_first($rounds) : 1;
    $roundAccess = $roundAccess ?? [];
    $roundStatus = $roundStatus ?? [];
    $activeRoundNum = $activeRoundNum ?? ($firstRoundKey ?? 1);
    $activeRoundKey = $activeRoundNum;
    $activeRound = (!empty($rounds) && isset($rounds[$activeRoundKey])) ? $rounds[$activeRoundKey] : (
        (!empty($rounds) && isset($rounds[$firstRoundKey])) ? $rounds[$firstRoundKey] : [
            'teamA' => [],
            'teamB' => [],
            'resting' => [],
            'team_a' => ['name' => 'Tim A'],
            'team_b' => ['name' => 'Tim B'],
            'matches' => [],
        ]
    );
    $isSetBased = str_contains(strtolower($game['scoring_system'] ?? ''), 'total of') || str_contains(strtolower($game['scoring_system'] ?? ''), 'best of');
    $isTeamFormat = str_contains(strtolower($game['match_format'] ?? ''), 'team');
    // Team Americano menggunakan istilah 'Set' (pasangan tetap), sedangkan Americano menggunakan 'Ronde' (pasangan berganti)
    $unitLabel = $isTeamFormat ? 'Set' : 'Ronde';

    // Baca mode Single/Double dari drawingData atau session
    $drawingMode     = $drawingData['mode'] ?? 'Double';
    $isSingleMode    = strtolower($drawingMode) === 'single';
    $modeBadgeLabel  = $isSingleMode ? 'Single (1 vs 1)' : 'Double (2 vs 2)';
    $matchFormatFull = ($game['match_format'] ?? 'Americano');
    // Jika Americano (bukan Team Americano), tambahkan mode label
    if (!str_contains(strtolower($matchFormatFull), 'team')) {
        $matchFormatFull = 'Americano ' . ($isSingleMode ? 'Single' : 'Double');
    }

    $getGenderHelper = function($pName) use ($participantsMap, $game) {
        $nameStr = is_array($pName) ? ($pName['name'] ?? $pName['nama'] ?? '') : (string)$pName;
        $nameClean = strtolower(trim($nameStr));
        $gender = null;
        if (is_array($pName) && !empty($pName['gender'])) {
            $gender = $pName['gender'];
        } elseif (isset($participantsMap[$nameStr]['gender'])) {
            $gender = $participantsMap[$nameStr]['gender'];
        } elseif (isset($participantsMap[$nameClean]['gender'])) {
            $gender = $participantsMap[$nameClean]['gender'];
        } elseif (!empty($game['participants'])) {
            foreach ($game['participants'] as $gp) {
                $gpName = is_array($gp) ? ($gp['name'] ?? $gp['nama'] ?? '') : (string)$gp;
                if (strtolower(trim($gpName)) === $nameClean && !empty($gp['gender'])) {
                    $gender = $gp['gender'];
                    break;
                }
            }
        }
        if ($gender) {
            $g = strtolower(trim($gender));
            if (in_array($g, ['female', 'perempuan', 'f', 'p', 'wanita', 'cewe', 'cewek', 'woman', 'girl'])) return true;
            if (in_array($g, ['male', 'laki-laki', 'laki - laki', 'pria', 'm', 'l', 'cowo', 'cowok', 'man', 'boy'])) return false;
        }
        return (bool) preg_match('/gisel|davina|marame|putri|anastasia|sarah|siti|female|wanita|dewi|maya|lisa|naykila|sisil|aura|zahra/i', $nameStr);
    };
@endphp

<div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">

    @if(session('success'))
        <div class="flex items-center gap-3 p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-semibold shadow-2xs transition-all">
            <i class="fa-solid fa-circle-check text-emerald-600 text-base shrink-0"></i>
            <span class="flex-1">{{ session('success') }}</span>
        </div>
    @endif
    @if(session('warning'))
        <div class="flex items-center gap-3 p-4 rounded-2xl bg-amber-50 border border-amber-200 text-amber-800 text-xs font-semibold shadow-2xs transition-all">
            <i class="fa-solid fa-triangle-exclamation text-amber-600 text-base shrink-0"></i>
            <span class="flex-1">{{ session('warning') }}</span>
        </div>
    @endif

    {{-- Network Quality & Offline Status Banner --}}
    <div id="networkStatusBannerContainer" class="hidden transition-all duration-300">
        <!-- Rendered dynamically by updateNetworkStatusUI -->
    </div>

    <!-- Header & Breadcrumb -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-200/50 pb-4">
        <div>
            <a href="{{ route('games.show', $game['id']) }}" class="text-xs text-slate-500 hover:text-slate-800 inline-flex items-center gap-1.5 mb-2 transition-colors">
                <i class="fa-solid fa-arrow-left"></i> Kembali ke Detail Game
            </a>

            <div class="flex flex-wrap items-center gap-3">
                <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">
                    Drawing &amp; Jadwal Pertandingan
                </h1>
                <span class="inline-flex items-center gap-1.5 text-[11px] font-black uppercase tracking-wider px-3 py-1 rounded-full
                    {{ $isSingleMode ? 'bg-amber-50 text-amber-700 border border-amber-200' : 'bg-indigo-50 text-indigo-700 border border-indigo-200' }}">
                    <i class="fa-solid {{ $isSingleMode ? 'fa-person' : 'fa-people-group' }} text-[10px]"></i>
                    {{ $matchFormatFull }}
                </span>
            </div>
            <p class="text-xs text-slate-500 mt-1">Sistem drawing pertandingan dan rotasi {{ $isSetBased ? 'Set Permainan' : 'Round-Robin' }} &bull; {{ $modeBadgeLabel }}</p>
        </div>

        <div class="flex items-center gap-2.5">
            @if($isLocked ?? false)
                <span class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-amber-50 text-amber-800 border border-amber-200 text-xs font-bold shadow-2xs" title="Pertandingan sudah dimulai, jadwal tim tidak dapat diacak ulang">
                    <i class="fa-solid fa-lock text-amber-600"></i> Tim Terkunci (Match Berjalan)
                </span>
            @elseif($isHost ?? false)
                <button id="shuffleBtn" onclick="runDrawingAnimation()" class="px-4 py-2 rounded-xl bg-white/80 hover:bg-white border border-slate-200/80 text-slate-800 font-semibold text-xs shadow-xs transition-all flex items-center gap-1.5 hover:border-[#063B00] cursor-pointer">
                    <i class="fa-solid fa-arrows-rotate text-slate-500" id="shuffleIcon"></i> Acak Ulang Jadwal
                </button>
            @endif
        </div>
    </div>

    <!-- Nama Game & Informasi Sesi (Di Atas Tombol Ronde) -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-white/70 backdrop-blur-xs rounded-2xl p-4 border border-slate-200/70 shadow-2xs">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-[#063B00] text-[#A8E63A] flex items-center justify-center text-base shadow-xs shrink-0">
                <i class="fa-solid fa-gamepad"></i>
            </div>
            <div>
                <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Nama Game</span>
                <h2 class="text-base sm:text-lg font-black text-slate-900 tracking-tight leading-tight">
                    {{ $game['title'] ?? $game['nama_session'] ?? 'Mabar Match' }}
                </h2>
            </div>
        </div>

        @if(!empty($game['venue_name']))
            <div class="flex items-center gap-2 self-start sm:self-auto">
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-100/90 text-slate-700 text-xs font-bold border border-slate-200/80">
                    <i class="fa-solid fa-location-dot text-amber-500 text-xs"></i> {{ $game['venue_name'] }}
                </span>
            </div>
        @endif
    </div>

    <!-- Match Rounds Tab Selector -->
    <div class="flex items-center gap-2 overflow-x-auto pb-1 border-b border-slate-200/50" id="roundsTabContainer">
        @foreach($rounds as $rNum => $rData)
            @php
                $baseNum = $rData['round'] ?? $rData['round_number'] ?? $rNum;
                $cleanTitle = "{$unitLabel} {$baseNum}";
                $isRoundAccessible = $roundAccess[$rNum] ?? ($loop->first);
                $roundStat = $roundStatus[$rNum] ?? ($isRoundAccessible ? 'ready' : 'locked');
                $isCurrentlyActive = ($rNum == $activeRoundNum);
            @endphp
            <button 
                type="button"
                onclick="switchRound({{ $rNum }})" 
                id="tabRound{{ $rNum }}" 
                class="px-4 py-2 rounded-xl text-xs font-bold transition-all shrink-0 flex items-center gap-1.5 cursor-pointer
                    {{ $isCurrentlyActive ? 'bg-[#063B00] text-white shadow-xs' : 'glass-card text-slate-600 hover:text-[#050608]' }}
                    {{ !$isRoundAccessible ? 'opacity-70 bg-slate-100/60' : '' }}"
                data-accessible="{{ $isRoundAccessible ? '1' : '0' }}"
                data-status="{{ $roundStat }}"
            >
                @if(!$isRoundAccessible)
                    <i class="fa-solid fa-lock text-[10px] {{ $isCurrentlyActive ? 'text-white/80' : 'text-slate-400' }}"></i>
                @elseif($roundStat === 'completed')
                    <i class="fa-solid fa-circle-check text-[11px] {{ $isCurrentlyActive ? 'text-[#A8E63A]' : 'text-emerald-600' }}"></i>
                @elseif($roundStat === 'in_progress')
                    <span class="w-1.5 h-1.5 rounded-full bg-amber-400 animate-pulse"></span>
                @endif

                <span>{{ $cleanTitle }}</span>

                @if(!$isRoundAccessible)
                    <span class="text-[9px] font-normal px-1.5 py-0.5 rounded {{ $isCurrentlyActive ? 'bg-white/20 text-white' : 'bg-slate-200/80 text-slate-500' }}">Terkunci</span>
                @elseif($roundStat === 'completed')
                    <span class="text-[9px] font-normal px-1.5 py-0.5 rounded {{ $isCurrentlyActive ? 'bg-white/20 text-white' : 'bg-emerald-100 text-emerald-800' }}">Selesai</span>
                @elseif($loop->first && count($rounds) > 1)
                    <span class="text-[10px] opacity-80 font-normal ml-0.5">(Pembuka)</span>
                @elseif($loop->last && count($rounds) > 1)
                    <span class="text-[10px] opacity-80 font-normal ml-0.5">(Final)</span>
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
                    $isPastRoundInitial = (($roundStatus[$activeRoundNum] ?? '') === 'completed') || ((int) $activeRoundNum < (int) ($roundsProgress['active_round_num'] ?? $activeRoundNum));
                    $withdrawnIdsSet = array_map('intval', $withdrawnPlayerIds ?? []);
                    $pausedIdsSet = array_map('intval', $pausedPlayerIds ?? []);
                    $eligibleActivesSSR = collect($game['participants'] ?? [])->filter(function($p) use ($withdrawnIdsSet, $pausedIdsSet) {
                        $pId = (int) ($p['id'] ?? 0);
                        return $pId > 0 && !in_array($pId, $withdrawnIdsSet, true) && !in_array($pId, $pausedIdsSet, true);
                    })->values();
                    $minReqSSR = $isSingleMode ? 2 : 4;

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

                    $ssrHasNoPlayableMatches = true;
                    foreach ($initMatches as $im) {
                        $pACount = count(!empty($im['team_a_names']) ? $im['team_a_names'] : (is_array($im['team_a'] ?? null) ? $im['team_a'] : []));
                        $pBCount = count(!empty($im['team_b_names']) ? $im['team_b_names'] : (is_array($im['team_b'] ?? null) ? $im['team_b'] : []));
                        if ($pACount > 0 || $pBCount > 0) {
                            $ssrHasNoPlayableMatches = false;
                            break;
                        }
                    }

                    if (!$isPastRoundInitial && $ssrHasNoPlayableMatches && $eligibleActivesSSR->count() >= $minReqSSR) {
                        if ($isSingleMode) {
                            $initMatches[0]['team_a'] = [$eligibleActivesSSR[0]];
                            $initMatches[0]['team_a_names'] = [$eligibleActivesSSR[0]['name'] ?? ''];
                            $initMatches[0]['team_b'] = [$eligibleActivesSSR[1]];
                            $initMatches[0]['team_b_names'] = [$eligibleActivesSSR[1]['name'] ?? ''];
                        } else {
                            $initMatches[0]['team_a'] = [$eligibleActivesSSR[0], $eligibleActivesSSR[1]];
                            $initMatches[0]['team_a_names'] = [$eligibleActivesSSR[0]['name'] ?? '', $eligibleActivesSSR[1]['name'] ?? ''];
                            $initMatches[0]['team_b'] = [$eligibleActivesSSR[2], $eligibleActivesSSR[3]];
                            $initMatches[0]['team_b_names'] = [$eligibleActivesSSR[2]['name'] ?? '', $eligibleActivesSSR[3]['name'] ?? ''];
                        }
                    }
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
                        $tAName = $m['team_a']['name'] ?? ($m['team_a_name'] ?? (count($initMatches) > 1 ? "Court " . ($mIdx + 1) . " - Tim A" : "Tim A"));
                        $tBName = $m['team_b']['name'] ?? ($m['team_b_name'] ?? (count($initMatches) > 1 ? "Court " . ($mIdx + 1) . " - Tim B" : "Tim B"));
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
                                        @forelse($tAPlayers as $pName)
                                            @php
                                                $isFemale = $getGenderHelper($pName);
                                                $pDisplayName = is_array($pName) ? ($pName['name'] ?? $pName['nama'] ?? '') : (string)$pName;
                                            @endphp
                                            <div class="flex flex-col items-center justify-center text-center transform transition-transform hover:scale-105 w-fit mx-auto sm:mx-6 my-auto">
                                                <div class="w-9 h-9 sm:w-11 sm:h-11 rounded-full {{ $isFemale ? 'bg-gradient-to-br from-rose-400 to-pink-600' : 'bg-gradient-to-br from-sky-400 to-blue-600' }} text-white border-2 border-white shadow-lg flex items-center justify-center text-xs sm:text-sm mb-1 ring-2 ring-black/20">
                                                    <i class="{{ $isFemale ? 'fa-solid fa-person-dress' : 'fa-solid fa-person' }}"></i>
                                                </div>
                                                <span class="text-[10px] sm:text-[11px] font-bold text-white text-center leading-tight bg-black/50 backdrop-blur-xs px-2.5 py-0.5 rounded-full border border-white/20 shadow-xs max-w-[95px] sm:max-w-[120px] truncate">
                                                    {{ $pDisplayName }}
                                                </span>
                                            </div>
                                        @empty
                                            <div class="flex flex-col items-center justify-center text-center my-auto py-2">
                                                <span class="text-[10px] sm:text-[11px] font-semibold text-slate-300 italic bg-black/40 backdrop-blur-xs px-3 py-1 rounded-full border border-white/10">
                                                    Belum ada pemain
                                                </span>
                                            </div>
                                        @endforelse
                                    </div>
                                </div>

                                <!-- Team B (Right Half) -->
                                <div class="w-1/2 pl-2 flex flex-col justify-between h-full items-end text-right">
                                    <span class="text-[9px] font-black uppercase tracking-wider px-2.5 py-0.5 rounded-full bg-black/40 backdrop-blur-xs text-[#A8E63A] border border-[#A8E63A]/40 w-fit shadow-xs">
                                        {{ $tBName }}
                                    </span>
                                    <div class="flex flex-col justify-around gap-2 my-auto py-2 h-full items-end w-full">
                                        @forelse($tBPlayers as $pName)
                                            @php
                                                $isFemale = $getGenderHelper($pName);
                                                $pDisplayName = is_array($pName) ? ($pName['name'] ?? $pName['nama'] ?? '') : (string)$pName;
                                            @endphp
                                            <div class="flex flex-col items-center justify-center text-center transform transition-transform hover:scale-105 w-fit mx-auto sm:mx-6 my-auto">
                                                <div class="w-9 h-9 sm:w-11 sm:h-11 rounded-full {{ $isFemale ? 'bg-gradient-to-br from-rose-400 to-pink-600' : 'bg-gradient-to-br from-sky-400 to-blue-600' }} text-white border-2 border-white shadow-lg flex items-center justify-center text-xs sm:text-sm mb-1 ring-2 ring-black/20">
                                                    <i class="{{ $isFemale ? 'fa-solid fa-person-dress' : 'fa-solid fa-person' }}"></i>
                                                </div>
                                                <span class="text-[10px] sm:text-[11px] font-bold text-white text-center leading-tight bg-black/50 backdrop-blur-xs px-2.5 py-0.5 rounded-full border border-white/20 shadow-xs max-w-[95px] sm:max-w-[120px] truncate">
                                                    {{ $pDisplayName }}
                                                </span>
                                            </div>
                                        @empty
                                            <div class="flex flex-col items-center justify-center text-center my-auto py-2">
                                                <span class="text-[10px] sm:text-[11px] font-semibold text-slate-300 italic bg-black/40 backdrop-blur-xs px-3 py-1 rounded-full border border-white/10">
                                                    Belum ada pemain
                                                </span>
                                            </div>
                                        @endforelse
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <!-- Bangku Istirahat (Bench) - Tepat di Bawah Preview Lapangan 2D -->
            <div id="benchContainer" class="glass-card rounded-2xl p-4 sm:p-5 border border-white/90 shadow-sm space-y-3">
                @php
                    $activeRoundIsPast = (($roundStatus[$activeRoundNum] ?? '') === 'completed') || ((int) $activeRoundNum < (int) ($roundsProgress['active_round_num'] ?? $activeRoundNum));
                    $withdrawnNames = collect($game['participants'] ?? [])
                        ->filter(fn ($participant) => in_array((int) ($participant['id'] ?? 0), $withdrawnPlayerIds ?? [], true))
                        ->pluck('name')
                        ->all();
                    $initialBenchNames = $activeRound['resting'] ?? [];
                    if (! $activeRoundIsPast) {
                        $initialBenchNames = array_values(array_filter($initialBenchNames, fn ($name) => ! in_array($name, $withdrawnNames, true)));
                        $pausedNames = collect($game['participants'] ?? [])
                            ->filter(fn ($participant) => in_array((int) ($participant['id'] ?? 0), $pausedPlayerIds ?? [], true))
                            ->pluck('name')
                            ->all();
                        $initialBenchNames = array_values(array_unique(array_merge($initialBenchNames, $pausedNames)));
                    }
                @endphp
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
                        {{ count($initialBenchNames) }} Pemain
                    </span>
                </div>

                <div id="benchListContainer" class="flex flex-wrap gap-2.5 pt-1">
                    @forelse($initialBenchNames as $rName)
                        @php
                            $rId = null;
                            foreach($game['participants'] ?? [] as $part) {
                                if (($part['name'] ?? '') === $rName) {
                                    $rId = $part['id'] ?? null;
                                    break;
                                }
                            }
                            $isPastRound = $activeRoundIsPast;
                            $roundPausedPlayers = $activeRound['paused_players'] ?? [];
                            $roundPausedIds = $activeRound['paused_player_ids'] ?? [];
                            $isPaused = ($rId && in_array((int)$rId, $pausedPlayerIds ?? [], true))
                                || ($rId && in_array((int)$rId, $roundPausedIds, true))
                                || in_array($rName, $roundPausedPlayers, true);
                        @endphp
                        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl {{ $isPaused ? 'bg-amber-50/80 text-slate-800 border border-amber-200' : 'bg-white text-slate-800 border border-slate-200' }} text-xs font-medium shadow-2xs">
                            <span class="w-5 h-5 rounded-full {{ $isPaused ? 'bg-amber-200 text-amber-800' : 'bg-amber-100 text-amber-800' }} text-[10px] flex items-center justify-center font-bold">
                                <i class="fa-solid {{ $isPaused ? 'fa-bed' : 'fa-mug-hot' }} text-[9px]"></i>
                            </span>
                            {{ $rName }}
                            <span class="text-[9px] font-bold {{ $isPaused ? 'text-amber-800 bg-amber-100 border border-amber-300' : 'text-amber-700 bg-amber-50 border border-amber-200' }} px-1.5 py-0.5 rounded border">
                                {{ $isPaused ? 'Istirahat' : 'Bench' }}
                            </span>
                            @if(! $isPastRound && $isPaused && ($isHost ?? false))
                                <button type="button" onclick="reactivatePlayer({{ $rId }}, '{{ addslashes($rName) }}')" class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-[10px] shadow-xs transition-colors cursor-pointer ml-1">
                                    <i class="fa-solid fa-play text-[8px]"></i> Siap Main
                                </button>
                            @endif
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
                            $teamAName = $m['team_a']['name'] ?? ($m['team_a_name'] ?? 'Tim A');
                            $teamBName = $m['team_b']['name'] ?? ($m['team_b_name'] ?? 'Tim B');
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
                <span>Format: <strong class="text-[#050608]">{{ $matchFormatFull }} ({{ $drawingData['total_teams'] ?? count($game['participants'] ?? []) }} {{ isset($drawingData['format']) && str_contains(strtolower($drawingData['format']), 'team') ? 'Tim Tetap' : 'Peserta' }})</strong></span>
                <span>Total {{ $unitLabel }}: <strong class="text-[#050608]">{{ $drawingData['total_rounds'] ?? count($rounds) }} {{ $unitLabel }}</strong></span>
                <span>Scoring: <strong class="text-[#050608]">{{ $game['scoring_system'] }}</strong></span>
                @if(!str_contains(strtolower($matchFormatFull), 'team'))
                <span>
                    Mode:
                    <strong class="{{ $isSingleMode ? 'text-amber-700' : 'text-indigo-700' }}">
                        {{ $isSingleMode ? '1 vs 1 (Single)' : '2 vs 2 (Double)' }}
                    </strong>
                </span>
                @endif
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

                <!-- Courts Roster Container -->
                <div id="courtsRosterContainer" class="space-y-3">
                    @forelse($initMatches as $mIdx => $m)
                        @php
                            $courtLabel = $m['court_name'] ?? ('Court ' . ($m['court'] ?? ($m['court_number'] ?? ($mIdx + 1))));
                            $statusLabel = $m['status'] ?? 'Scheduled';
                            $teamAName = (!empty($m['team_a']) && is_array($m['team_a']) && !empty($m['team_a']['name']))
                                ? $m['team_a']['name']
                                : ($m['team_a_name'] ?? 'Tim A');
                            $teamBName = (!empty($m['team_b']) && is_array($m['team_b']) && !empty($m['team_b']['name']))
                                ? $m['team_b']['name']
                                : ($m['team_b_name'] ?? 'Tim B');
                            $teamAPlayers = !empty($m['team_a_names']) ? array_values($m['team_a_names']) : (is_array($m['team_a']) ? array_values(array_column($m['team_a'], 'name')) : []);
                            if (empty($teamAPlayers) && is_array($m['team_a'])) {
                                $teamAPlayers = array_values(array_map(fn($p) => is_array($p) ? ($p['name'] ?? $p['nama'] ?? '') : (string)$p, $m['team_a']));
                            }
                            $teamBPlayers = !empty($m['team_b_names']) ? array_values($m['team_b_names']) : (is_array($m['team_b']) ? array_values(array_column($m['team_b'], 'name')) : []);
                            if (empty($teamBPlayers) && is_array($m['team_b'])) {
                                $teamBPlayers = array_values(array_map(fn($p) => is_array($p) ? ($p['name'] ?? $p['nama'] ?? '') : (string)$p, $m['team_b']));
                            }
                        @endphp
                        <div class="p-3.5 rounded-2xl bg-white/80 border border-slate-200/80 space-y-2.5 shadow-2xs">
                            <div class="flex items-center justify-between border-b border-slate-200/50 pb-2">
                                <div class="flex items-center gap-1.5">
                                    <span class="w-2 h-2 rounded-full bg-emerald-600 animate-pulse"></span>
                                    <span class="text-xs font-black uppercase tracking-wider text-[#063B00]">{{ $courtLabel }}</span>
                                    @if(!empty($m['slot_number']))
                                        <span class="text-[9px] font-black uppercase text-indigo-700 bg-indigo-50 border border-indigo-200 px-1.5 py-0.5 rounded-md">Slot {{ $m['slot_number'] }}</span>
                                    @endif
                                </div>
                                <span class="text-[10px] font-semibold text-slate-500 bg-slate-50 px-2 py-0.5 rounded-full border border-slate-200/60 shadow-2xs">
                                    {{ $statusLabel }}
                                </span>
                            </div>

                            <!-- Team A -->
                            <div class="space-y-1.5">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-extrabold text-[#063B00]">{{ $teamAName }}</span>
                                    <span class="text-[10px] font-semibold text-slate-400">Sisi Kiri</span>
                                </div>
                                <div class="space-y-1">
                                    @forelse($teamAPlayers as $pIdx => $pName)
                                        @php
                                            $pId = null;
                                            if (!empty($participantsMap[$pName]['id'])) {
                                                $pId = $participantsMap[$pName]['id'];
                                            } elseif (!empty($participantsMap[strtolower(trim($pName))]['id'])) {
                                                $pId = $participantsMap[strtolower(trim($pName))]['id'];
                                            } else {
                                                foreach ($game['participants'] ?? [] as $part) {
                                                    if (($part['name'] ?? '') === $pName) {
                                                        $pId = $part['id'] ?? null;
                                                        break;
                                                    }
                                                }
                                            }
                                            $isPastRoundPhp = (($roundStatus[$activeRoundNum] ?? '') === 'completed') || ((int)$activeRoundNum < (int)($roundsProgress['active_round_num'] ?? $activeRoundNum));
                                        @endphp
                                        <div class="flex items-center justify-between bg-white p-2 rounded-xl border border-slate-200/70 text-xs shadow-2xs gap-2">
                                            <span class="font-semibold text-slate-800 truncate min-w-0 flex-1">{{ $pIdx + 1 }}. {{ $pName }}</span>
                                            <div class="flex items-center gap-1.5 shrink-0">
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-50 text-emerald-800 border border-emerald-200">Player {{ $pIdx + 1 }}</span>
                                            </div>
                                        </div>
                                    @empty
                                        <p class="text-[11px] text-slate-400 py-1 text-center">Belum ada pemain</p>
                                    @endforelse
                                </div>
                            </div>

                            <!-- Team B -->
                            <div class="space-y-1.5 pt-2 border-t border-slate-100">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-extrabold text-slate-800">{{ $teamBName }}</span>
                                    <span class="text-[10px] font-semibold text-slate-400">Sisi Kanan</span>
                                </div>
                                <div class="space-y-1">
                                    @forelse($teamBPlayers as $pIdx => $pName)
                                        @php
                                            $pId = null;
                                            if (!empty($participantsMap[$pName]['id'])) {
                                                $pId = $participantsMap[$pName]['id'];
                                            } elseif (!empty($participantsMap[strtolower(trim($pName))]['id'])) {
                                                $pId = $participantsMap[strtolower(trim($pName))]['id'];
                                            } else {
                                                foreach ($game['participants'] ?? [] as $part) {
                                                    if (($part['name'] ?? '') === $pName) {
                                                        $pId = $part['id'] ?? null;
                                                        break;
                                                    }
                                                }
                                            }
                                            $isPastRoundPhp = (($roundStatus[$activeRoundNum] ?? '') === 'completed') || ((int)$activeRoundNum < (int)($roundsProgress['active_round_num'] ?? $activeRoundNum));
                                        @endphp
                                        <div class="flex items-center justify-between bg-white p-2 rounded-xl border border-slate-200/70 text-xs shadow-2xs gap-2">
                                            <span class="font-semibold text-slate-800 truncate min-w-0 flex-1">{{ $pIdx + 1 }}. {{ $pName }}</span>
                                            <div class="flex items-center gap-1.5 shrink-0">
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-[#eaf3eb] text-[#245b2c] border border-[#bedfc1]">Player {{ $pIdx + 1 }}</span>
                                            </div>
                                        </div>
                                    @empty
                                        <p class="text-[11px] text-slate-400 py-1 text-center">Belum ada pemain</p>
                                    @endforelse
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="p-4 text-center text-xs text-slate-400">
                            Belum ada alokasi court pada ronde ini.
                        </div>
                    @endforelse
                </div>

                <!-- Resting Bench Card in Sidebar -->
                <div class="p-3.5 rounded-2xl bg-white/80 border border-slate-200/80 space-y-2 shadow-2xs">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-slate-700">Bangku Cadangan / Istirahat</span>
                        <span class="text-[10px] text-amber-700 font-bold bg-amber-50 px-2 py-0.5 rounded-full border border-amber-200" id="labelRestingCount">{{ count($initialBenchNames) }} Pemain</span>
                    </div>
                    <div class="space-y-1.5" id="rosterResting">
                        @forelse($initialBenchNames as $idx => $pName)
                            @php
                                $rId = null;
                                if (!empty($participantsMap[$pName]['id'])) {
                                    $rId = $participantsMap[$pName]['id'];
                                } elseif (!empty($participantsMap[strtolower(trim($pName))]['id'])) {
                                    $rId = $participantsMap[strtolower(trim($pName))]['id'];
                                } else {
                                    foreach($game['participants'] ?? [] as $part) {
                                        if (($part['name'] ?? '') === $pName) {
                                            $rId = $part['id'] ?? null;
                                            break;
                                        }
                                    }
                                }
                                $isPastRound = (($roundStatus[$activeRoundNum] ?? '') === 'completed') || ((int)$activeRoundNum < (int)($roundsProgress['active_round_num'] ?? $activeRoundNum));
                                $roundPausedPlayers = $activeRound['paused_players'] ?? [];
                                $roundPausedIds = $activeRound['paused_player_ids'] ?? [];
                                $isPaused = ($rId && in_array((int)$rId, $pausedPlayerIds ?? [], true))
                                    || ($rId && in_array((int)$rId, $roundPausedIds, true))
                                    || in_array($pName, $roundPausedPlayers, true);
                            @endphp
                            <div class="flex items-center justify-between bg-white p-2 rounded-xl border {{ $isPaused ? 'border-amber-300 bg-amber-50/40' : 'border-slate-200/70' }} text-xs gap-2">
                                <span class="text-slate-700 truncate min-w-0 flex-1">{{ $idx + 1 }}. {{ $pName }}</span>
                                <div class="flex items-center gap-1.5 shrink-0">
                                    <span class="text-[10px] font-semibold {{ $isPaused ? 'text-amber-800 bg-amber-100 border border-amber-300' : 'text-amber-600 bg-amber-50 border border-amber-200' }} px-2 py-0.5 rounded">
                                        {{ $isPaused ? 'Istirahat' : 'Bench' }}
                                    </span>
                                    @if(! $isPastRound && ($isHost ?? false) && $rId)
                                        @if($isPaused)
                                            <button type="button" onclick="reactivatePlayer({{ $rId }}, '{{ addslashes($pName) }}')" class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-[9px] shadow-2xs transition-all cursor-pointer">
                                                <i class="fa-solid fa-play text-[8px]"></i> Siap Main
                                            </button>
                                        @else
                                            <button type="button" onclick="openPlayerManageModal({{ $rId }}, '{{ addslashes($pName) }}', 'pause')" class="px-1.5 py-0.5 rounded-md bg-amber-50 hover:bg-amber-100 text-amber-800 font-bold text-[9px] border border-amber-300/80 transition-all cursor-pointer shadow-2xs" title="Istirahatkan sementara">
                                                Istirahat
                                            </button>
                                            <button type="button" onclick="openPlayerManageModal({{ $rId }}, '{{ addslashes($pName) }}', 'withdraw')" class="px-1.5 py-0.5 rounded-md bg-rose-50 hover:bg-rose-100 text-rose-800 font-bold text-[9px] border border-rose-300/80 transition-all cursor-pointer shadow-2xs" title="Keluarkan dari sesi">
                                                Keluar
                                            </button>
                                        @endif
                                    @endif
                                </div>
                            </div>
                        @empty
                            <div class="text-center py-2 text-slate-400 text-xs">
                                Semua pemain aktif bertanding di ronde ini.
                            </div>
                        @endforelse
                    </div>

                    @php
                        $withdrawnList = [];
                        foreach($game['participants'] ?? [] as $part) {
                            if (in_array((int)($part['id'] ?? 0), $withdrawnPlayerIds ?? [], true)) {
                                $withdrawnList[] = $part;
                            }
                        }
                    @endphp
                    <div id="sidebarWithdrawnContainer" class="pt-2 border-t border-slate-100 space-y-1.5 {{ count($withdrawnList) > 0 ? '' : 'hidden' }}">
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Pemain Keluar (Sesi)</span>
                        <div class="space-y-1" id="sidebarWithdrawnList">
                            @foreach($withdrawnList as $wp)
                                <div class="flex items-center justify-between p-1.5 rounded-lg bg-rose-50/50 border border-rose-100 text-[11px]">
                                    <span class="text-slate-500 line-through">{{ $wp['name'] ?? 'Pemain' }}</span>
                                    <span class="text-[9px] font-bold text-rose-700 bg-rose-100/70 px-1.5 py-0.5 rounded border border-rose-200">Keluar</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <!-- Banner Edukatif: Belum Ada Pemain Bertanding -->
                <div id="noPlayableMatchesBanner" class="hidden mb-3 p-3.5 rounded-2xl bg-amber-50 border border-amber-200 text-amber-900 text-xs shadow-2xs transition-all">
                    <div class="flex items-start gap-2.5">
                        <i class="fa-solid fa-triangle-exclamation text-amber-600 text-sm mt-0.5 shrink-0"></i>
                        <div class="space-y-0.5">
                            <p class="font-bold text-slate-900">Belum Ada Pemain Bertanding</p>
                            <p class="text-[11px] text-slate-600 leading-relaxed">
                                Semua pertandingan di ronde ini belum memiliki pemain yang siap bertanding. Silakan aktifkan kembali pemain melalui tombol <strong class="text-emerald-700">Siap Main</strong> di Bangku Cadangan untuk memulai pertandingan.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- CTA Button to Live Scoring / Lock Drawing -->
                @if($isLocked ?? false)
                    @php
                        $targetRoundStat = $roundStatus[$activeRoundNum] ?? 'ready';
                        $initialCtaLabel = ($targetRoundStat === 'completed')
                            ? "Buka Rekap / Skor {$unitLabel} {$activeRoundNum}"
                            : "Mulai Scoring Live {$unitLabel} {$activeRoundNum}";
                    @endphp
                    <a id="ctaLiveBtn" onclick="onHostOpenScoring(event)" href="{{ route('scoring.live', ['id' => $game['id'], 'format' => $game['match_format'] ?? 'Americano', 'round' => 'round_'.$activeRoundNum]) }}" class="block text-center py-3.5 rounded-2xl bg-[#063B00] hover:bg-[#042a00] text-white font-extrabold text-xs shadow-md transition-all hover:scale-[1.01] active:scale-95">
                        <i class="fa-solid fa-play mr-1.5 text-[#A8E63A]"></i> <span id="ctaLiveText">{{ $initialCtaLabel }}</span> <i class="fa-solid fa-arrow-right text-[10px] text-[#A8E63A] ml-1"></i>
                    </a>
                @elseif($isHost ?? false)
                    <form method="POST" action="{{ route('games.lock', $game['id']) }}" class="block" id="formLockDrawing">
                        @csrf
                        <input type="hidden" name="format" value="{{ $game['match_format'] ?? 'Americano' }}">
                        <input type="hidden" name="round" id="lockRoundInput" value="round_{{ $activeRoundNum }}">
                        <button type="submit" id="btnLockSubmit" class="w-full text-center py-3.5 rounded-2xl bg-[#063B00] hover:bg-[#042a00] text-white font-extrabold text-xs shadow-md transition-all hover:scale-[1.01] active:scale-95 cursor-pointer">
                            <span id="lockSubmitText">Kunci Tim &amp; Buka Scoring Live</span> <i class="fa-solid fa-arrow-right text-[10px] text-[#A8E63A] ml-1"></i>
                        </button>
                    </form>
                @else
                    <div class="w-full text-center py-3.5 rounded-2xl bg-slate-50 border border-slate-200 text-slate-500 font-semibold text-xs" id="ctaSpectatorText">
                        <i class="fa-solid fa-eye mr-1"></i> Mode Penonton: drawing hanya dapat dilihat
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

<!-- MODAL REAKTIVASI PEMAIN (SIAP MAIN) -->
<div id="reactivateConfirmModal" class="fixed inset-0 items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs overflow-y-auto animate-in fade-in duration-150" style="display: none; z-index: 99999;" onclick="if(event.target === this) closeReactivateModal();">
    <div class="bg-white rounded-3xl p-6 shadow-2xl space-y-4 my-8 border border-slate-100 flex flex-col" style="max-width: 440px; width: 100%; box-sizing: border-box;" onclick="event.stopPropagation();">
        <div class="flex items-start gap-3.5">
            <div class="w-11 h-11 rounded-2xl bg-emerald-50 text-[#063B00] flex items-center justify-center text-lg border border-emerald-100/80 shadow-2xs shrink-0 mt-0.5">
                <i class="fa-solid fa-user-check"></i>
            </div>
            <div class="space-y-1">
                <h3 class="text-sm font-black text-slate-900">Aktifkan Kembali Pemain</h3>
                <p class="text-xs text-slate-500 leading-relaxed">
                    Aktifkan kembali <strong id="reactivatePlayerNameDisplay" class="text-slate-800 font-extrabold">-</strong> untuk ikut dalam jadwal pertandingan ronde berikutnya? Status istirahat akan dihentikan dan pemain kembali ke rotasi jadwal.
                </p>
            </div>
        </div>

        <div id="reactivateModalError" class="hidden p-2.5 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-xs font-semibold flex items-center gap-2">
            <i class="fa-solid fa-circle-exclamation text-rose-600"></i>
            <span id="reactivateModalErrorMsg"></span>
        </div>

        <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-2.5">
            <button
                type="button"
                onclick="closeReactivateModal()"
                class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs transition-colors cursor-pointer"
            >
                Batal
            </button>
            <button
                type="button"
                id="btnConfirmReactivate"
                onclick="executeReactivatePlayer()"
                class="px-5 py-2 rounded-xl bg-[#063B00] hover:bg-[#042a00] text-white font-extrabold text-xs shadow-md shadow-[#063B00]/20 flex items-center gap-1.5 transition-all cursor-pointer hover:scale-[1.01]"
            >
                <i class="fa-solid fa-play text-[10px]"></i>
                <span id="btnConfirmReactivateText">Ya, Siap Main</span>
            </button>
        </div>
    </div>
</div>

<!-- MODAL KELOLA STATUS PEMAIN (ISTIRAHAT / KELUAR) -->
<div id="playerManageActionModal" class="fixed inset-0 items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs overflow-y-auto animate-in fade-in duration-150" style="display: none; z-index: 99999;" onclick="if(event.target === this) closePlayerManageModal();">
    <div class="bg-white rounded-3xl p-6 shadow-2xl space-y-4 my-8 border border-slate-100 flex flex-col" style="max-width: 440px; width: 100%; box-sizing: border-box;" onclick="event.stopPropagation();">
        <div class="flex items-start gap-3.5">
            <div id="playerManageModalIconContainer" class="w-11 h-11 rounded-2xl bg-amber-50 text-amber-700 flex items-center justify-center text-lg border border-amber-100/80 shadow-2xs shrink-0 mt-0.5">
                <i id="playerManageModalIcon" class="fa-solid fa-pause"></i>
            </div>
            <div class="space-y-1">
                <h3 id="playerManageModalTitle" class="text-sm font-black text-slate-900">Istirahatkan Pemain</h3>
                <p id="playerManageModalDesc" class="text-xs text-slate-500 leading-relaxed">
                    Istirahatkan sementara <strong id="playerManageModalPlayerName" class="text-slate-800 font-extrabold">-</strong> dari jadwal pertandingan ronde mendatang?
                </p>
            </div>
        </div>

        <div class="space-y-1.5">
            <label for="playerManageReason" class="block text-xs font-bold text-slate-700">
                Alasan <span class="text-rose-500">* (Wajib diisi)</span>
            </label>
            <input
                type="text"
                id="playerManageReason"
                class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-[#063B00]/30 focus:border-[#063B00] placeholder:text-slate-400"
                placeholder="Contoh: Cedera ringan, toilet break, urusan mendadak"
                maxlength="150"
            />
            <p class="text-[10px] text-slate-400">Alasan diperlukan untuk transparansi dan catatan sesi pertandingan.</p>
        </div>

        <div id="playerManageQuotaWarning" class="hidden p-3 rounded-xl bg-amber-50 border border-amber-300 text-amber-900 text-[11px] leading-relaxed">
            <i class="fa-solid fa-triangle-exclamation text-amber-600 mr-1"></i>
            <span id="playerManageQuotaWarningText"></span>
        </div>

        <div id="playerManageModalError" class="hidden p-2.5 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-xs font-semibold flex items-center gap-2">
            <i class="fa-solid fa-circle-exclamation text-rose-600"></i>
            <span id="playerManageModalErrorMsg"></span>
        </div>

        <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-2.5">
            <button
                type="button"
                onclick="closePlayerManageModal()"
                class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs transition-colors cursor-pointer"
            >
                Batal
            </button>
            <button
                type="button"
                id="btnConfirmPlayerManage"
                onclick="executePlayerManageAction()"
                class="px-5 py-2 rounded-xl bg-amber-600 hover:bg-amber-700 text-white font-extrabold text-xs shadow-md flex items-center gap-1.5 transition-all cursor-pointer hover:scale-[1.01]"
            >
                <i id="btnConfirmPlayerManageIcon" class="fa-solid fa-check text-[10px]"></i>
                <span id="btnConfirmPlayerManageText">Konfirmasi</span>
            </button>
        </div>
    </div>
</div>

<!-- MODAL PERINGATAN PEMAIN SEDANG BERTANDING -->
<div id="playerActiveMatchPromptModal" class="fixed inset-0 items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs overflow-y-auto animate-in fade-in duration-150" style="display: none; z-index: 99999;" onclick="if(event.target === this) closePlayerActiveMatchPromptModal();">
    <div class="bg-white rounded-3xl p-6 shadow-2xl space-y-4 my-8 border border-slate-100 flex flex-col" style="max-width: 440px; width: 100%; box-sizing: border-box;" onclick="event.stopPropagation();">
        <div class="flex items-start gap-3.5">
            <div class="w-11 h-11 rounded-2xl bg-amber-50 text-amber-700 flex items-center justify-center text-lg border border-amber-200/80 shadow-2xs shrink-0 mt-0.5">
                <i class="fa-solid fa-triangle-exclamation"></i>
            </div>
            <div class="space-y-1">
                <h3 class="text-sm font-black text-slate-900">Pemain Sedang Bertanding</h3>
                <p id="playerActiveMatchPromptDesc" class="text-xs text-slate-600 leading-relaxed">
                    Pemain ini sedang aktif bertanding di lapangan.
                </p>
            </div>
        </div>

        <div class="p-3.5 rounded-2xl bg-slate-50 border border-slate-200/80 space-y-2 text-xs">
            <div class="font-bold text-slate-800 flex items-center gap-1.5">
                <i class="fa-solid fa-circle-info text-[#063B00]"></i>
                <span>Petunjuk Penghentian:</span>
            </div>
            <p class="text-[11px] text-slate-600 leading-relaxed">
                Untuk menghentikan pemain dan mencatat skor parsial secara sah, silakan gunakan fitur <strong class="text-rose-700 font-extrabold">End Match</strong> langsung pada court terkait di halaman scoring.
            </p>
        </div>

        <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-2.5">
            <button
                type="button"
                onclick="closePlayerActiveMatchPromptModal()"
                class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs transition-colors cursor-pointer"
            >
                Nanti Saja
            </button>
            <a
                href="#"
                id="btnGoToCourtScoring"
                class="px-5 py-2 rounded-xl bg-[#063B00] hover:bg-[#042a00] text-white font-extrabold text-xs shadow-md shadow-[#063B00]/20 flex items-center gap-1.5 transition-all cursor-pointer hover:scale-[1.01]"
            >
                <i class="fa-solid fa-arrow-up-right-from-square text-[10px]"></i>
                <span id="btnGoToCourtScoringText">Buka Scoring Court</span>
            </a>
        </div>
    </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/@supabase/supabase-js@2"></script>
<script>
    let roundsData = @json(!empty($rounds) ? $rounds : ($drawingData['rounds'] ?? []));
    let roundAccessData = @json($roundAccess ?? []);
    let roundStatusData = @json($roundStatus ?? []);
    let participantsMap = @json($participantsMap ?? []);
    let allParticipants = @json($game['participants'] ?? []);
    let pausedPlayerIds = @json($pausedPlayerIds ?? []);
    let withdrawnPlayerIds = @json($withdrawnPlayerIds ?? []);
    const unitLabel = @json($unitLabel ?? 'Round');
    let currentRoundKey = {{ $activeRoundNum }};
    const serverActiveRoundNum = {{ $activeRoundNum }};
    const isSessionLocked = @json($isLocked ?? false);
    const isHostUser = @json($isHost ?? false);
    const gameSessionId = {{ (int) $game['id'] }};
    const baseLiveScoringUrl = '{{ route('scoring.live', ['id' => $game['id'], 'format' => $game['match_format'] ?? 'Americano']) }}';
    const liveScoringUrl = '{{ route('scoring.live', ['id' => $game['id'], 'format' => $game['match_format'] ?? 'Americano', 'round' => 'round_'.$activeRoundNum]) }}';
    const isSingleMode = @json($isSingleMode ?? false);
    const isSingle4Player = @json($isSingleMode && count($game['participants'] ?? []) === 4);
    const SUPABASE_URL = '{{ config('services.supabase.url') }}';
    const SUPABASE_KEY = '{{ config('services.supabase.public_key') }}';

    // ── Network Quality & Offline Banner Controller ───────────────────────────
    let currentNetworkState = 'normal';
    let recoveredHideTimeout = null;
    let consecutivePollFailures = 0;

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
            container.innerHTML = `
                <div class="p-3.5 mb-3 rounded-2xl bg-rose-50 border border-rose-300 text-rose-900 flex items-start sm:items-center justify-between gap-3 shadow-xs transition-all duration-200">
                    <div class="min-w-0">
                        <div class="font-bold text-xs sm:text-sm text-rose-950 flex items-center gap-1.5 flex-wrap">
                            <span>Koneksi Terputus (Mode Offline)</span>
                            <span class="inline-block w-2 h-2 rounded-full bg-rose-500 animate-ping"></span>
                        </div>
                        <p class="text-[11px] sm:text-xs text-rose-700 mt-0.5 leading-snug">
                            Jadwal pertandingan ditampilkan dari data lokal. Pembaruan akan otomatis disinkronkan segera setelah internet pulih.
                        </p>
                    </div>
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-rose-100 text-rose-700 text-[10px] font-semibold shrink-0 self-start sm:self-center">
                        <i class="fa-solid fa-floppy-disk text-[10px]"></i> Data lokal
                    </span>
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
                            Respon server memakan waktu ${latencySec}. Pembaruan jadwal atau pengacakan mungkin memerlukan beberapa saat.
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
                            Jaringan internet kembali stabil.
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

    if (typeof navigator !== 'undefined' && !navigator.onLine) {
        updateNetworkStatusUI('offline');
    }

    window.addEventListener('offline', () => {
        updateNetworkStatusUI('offline');
    });

    window.addEventListener('online', () => {
        updateNetworkStatusUI('recovered');
    });

    function getActiveUnlockedRound() {
        const allKeys = Object.keys(roundsData).map(Number).sort((a, b) => a - b);
        if (allKeys.length === 0) return 1;
        for (const k of allKeys) {
            if (roundAccessData[k] !== false && roundStatusData[k] !== 'completed') {
                return k;
            }
        }
        return allKeys[allKeys.length - 1];
    }

    function updateCtaButton(targetRound, isAccessible, activeUnlocked) {
        const roundToUse = isAccessible ? targetRound : activeUnlocked;
        const targetStat = roundStatusData[roundToUse] || 'ready';

        const ctaLiveBtn = document.getElementById('ctaLiveBtn');
        const ctaLiveText = document.getElementById('ctaLiveText');
        const lockRoundInput = document.getElementById('lockRoundInput');
        const lockSubmitText = document.getElementById('lockSubmitText');
        const btnLockSubmit = document.getElementById('btnLockSubmit');
        const noPlayableBanner = document.getElementById('noPlayableMatchesBanner');
        const formLockDrawing = document.getElementById('formLockDrawing');

        // Cek apakah ada pertandingan yang dapat dimainkan (memiliki pemain di kedua tim)
        const roundObj = roundsData[roundToUse] || roundsData[`round_${roundToUse}`];
        let hasPlayableMatches = false;
        if (roundObj) {
            const mList = roundObj.matches || [];
            if (mList.length > 0) {
                hasPlayableMatches = mList.some(m => {
                    const pA = extractMatchPlayers(m.team_a, m.team_a_names);
                    const pB = extractMatchPlayers(m.team_b, m.team_b_names);
                    return pA.length > 0 && pB.length > 0;
                });
            } else if (!roundObj.has_insufficient_players) {
                const pA = extractMatchPlayers(roundObj.teamA || roundObj.team_a, roundObj.teamA_names);
                const pB = extractMatchPlayers(roundObj.teamB || roundObj.team_b, roundObj.teamB_names);
                hasPlayableMatches = pA.length > 0 && pB.length > 0;
            }
        }

        // Tampilkan atau sembunyikan banner edukatif jika tidak ada pemain yang siap bertanding
        if (noPlayableBanner) {
            if (!hasPlayableMatches && isAccessible) {
                noPlayableBanner.classList.remove('hidden');
            } else {
                noPlayableBanner.classList.add('hidden');
            }
        }

        if (formLockDrawing) {
            formLockDrawing.onsubmit = function(e) {
                if (!hasPlayableMatches) {
                    e.preventDefault();
                    if (typeof showToast === 'function') {
                        showToast('Tidak dapat memulai scoring. Belum ada pemain yang bertanding pada ronde ini.', 'warning');
                    }
                    return false;
                }
            };
        }

        if (ctaLiveBtn) {
            const separator = baseLiveScoringUrl.includes('?') ? '&' : '?';
            ctaLiveBtn.href = `${baseLiveScoringUrl}${separator}round=round_${roundToUse}`;
            if (!hasPlayableMatches && isAccessible) {
                ctaLiveBtn.classList.add('opacity-50', 'cursor-not-allowed', 'pointer-events-none');
                ctaLiveBtn.classList.remove('hover:scale-[1.01]', 'active:scale-95');
                if (ctaLiveText) {
                    ctaLiveText.innerText = 'Belum Ada Pemain Bertanding';
                }
            } else {
                ctaLiveBtn.classList.remove('opacity-50', 'cursor-not-allowed', 'pointer-events-none');
                ctaLiveBtn.classList.add('hover:scale-[1.01]', 'active:scale-95');
                if (ctaLiveText) {
                    if (!isAccessible) {
                        ctaLiveText.innerText = `Buka Scoring ${unitLabel} ${activeUnlocked} (Aktif)`;
                    } else if (targetStat === 'completed') {
                        ctaLiveText.innerText = `Buka Rekap / Skor ${unitLabel} ${roundToUse}`;
                    } else {
                        ctaLiveText.innerText = `Buka Scoring Live ${unitLabel} ${roundToUse}`;
                    }
                }
            }
        }

        if (lockRoundInput) {
            lockRoundInput.value = `round_${roundToUse}`;
        }

        if (btnLockSubmit) {
            if (!hasPlayableMatches && isAccessible) {
                btnLockSubmit.disabled = true;
                btnLockSubmit.className = 'w-full text-center py-3.5 rounded-2xl bg-slate-200 text-slate-400 font-extrabold text-xs shadow-none cursor-not-allowed transition-all';
                if (lockSubmitText) {
                    lockSubmitText.innerText = 'Belum Ada Pemain Bertanding';
                }
            } else {
                btnLockSubmit.disabled = false;
                btnLockSubmit.className = 'w-full text-center py-3.5 rounded-2xl bg-[#063B00] hover:bg-[#042a00] text-white font-extrabold text-xs shadow-md transition-all hover:scale-[1.01] active:scale-95 cursor-pointer';
                if (lockSubmitText) {
                    if (!isAccessible) {
                        lockSubmitText.innerText = `Buka Scoring ${unitLabel} ${activeUnlocked} (Aktif)`;
                    } else {
                        lockSubmitText.innerText = `Kunci Tim & Buka Scoring Live (${unitLabel} ${roundToUse})`;
                    }
                }
            }
        }
    }

    // ── Realtime Drawing Sync & Lock Detector (Untuk Penonton / Player) ──────
    function applyRemoteDrawingUpdate(newRounds, newParticipantsMap, message = 'Jadwal & susunan tim diacak ulang oleh Host! 🎲') {
        if (!newRounds || Object.keys(newRounds).length === 0) return;

        const roundWithdrawnIds = Object.values(newRounds)
            .flatMap(round => Array.isArray(round?.withdrawn_player_ids) ? round.withdrawn_player_ids : [])
            .map(Number);
        if (roundWithdrawnIds.length > 0) {
            withdrawnPlayerIds = [...new Set([...withdrawnPlayerIds.map(Number), ...roundWithdrawnIds])];
        }

        const roundPausedIds = Object.values(newRounds)
            .flatMap(round => Array.isArray(round?.paused_player_ids) ? round.paused_player_ids : [])
            .map(Number);
        const curRoundObj = newRounds[currentRoundKey] || newRounds[`round_${currentRoundKey}`];
        if (curRoundObj && Array.isArray(curRoundObj.paused_player_ids)) {
            pausedPlayerIds = curRoundObj.paused_player_ids.map(Number);
        } else if (roundPausedIds.length > 0) {
            pausedPlayerIds = [...new Set(roundPausedIds)];
        }

        const oldJson = JSON.stringify(roundsData);
        const newJson = JSON.stringify(newRounds);
        if (oldJson === newJson) return;

        roundsData = newRounds;
        if (newParticipantsMap && Object.keys(newParticipantsMap).length > 0) {
            participantsMap = newParticipantsMap;
            if (Array.isArray(allParticipants)) {
                Object.values(newParticipantsMap).forEach(pItem => {
                    if (pItem && pItem.name) {
                        const existingIdx = allParticipants.findIndex(x => cleanPlayerName(x) === cleanPlayerName(pItem.name));
                        if (existingIdx >= 0) {
                            allParticipants[existingIdx] = { ...allParticipants[existingIdx], ...pItem };
                        } else {
                            allParticipants.push(pItem);
                        }
                    }
                });
            }
        }

        const roundKeys = Object.keys(roundsData).map(Number);
        if (!roundKeys.includes(currentRoundKey) && roundKeys.length > 0) {
            currentRoundKey = roundKeys[0];
        }

        switchRound(currentRoundKey, false);

        // Visual flash feedback on court container
        const courtContainer = document.getElementById('courtsMultiContainer');
        if (courtContainer) {
            courtContainer.classList.add('scale-[0.98]', 'transition-all', 'duration-200');
            setTimeout(() => {
                courtContainer.classList.remove('scale-[0.98]');
            }, 200);
        }

        if (typeof showToast === 'function' && message) {
            showToast(message);
        }
    }

    function onHostOpenScoring(event) {
        if (!isHostUser) return;

        const roundObj = roundsData[currentRoundKey];
        let hasPlayableMatches = false;
        if (roundObj && !roundObj.has_insufficient_players) {
            const mList = roundObj.matches || [];
            if (mList.length > 0) {
                hasPlayableMatches = mList.some(m => {
                    const pA = extractMatchPlayers(m.team_a, m.team_a_names);
                    const pB = extractMatchPlayers(m.team_b, m.team_b_names);
                    return pA.length > 0 && pB.length > 0;
                });
            } else {
                const pA = extractMatchPlayers(roundObj.teamA || roundObj.team_a, roundObj.teamA_names);
                const pB = extractMatchPlayers(roundObj.teamB || roundObj.team_b, roundObj.teamB_names);
                hasPlayableMatches = pA.length > 0 && pB.length > 0;
            }
        }

        if (!hasPlayableMatches) {
            if (event) {
                event.preventDefault();
                event.stopPropagation();
            }
            if (typeof showToast === 'function') {
                showToast('Tidak dapat membuka scoring. Belum ada pemain yang bertanding pada ronde ini.', 'warning');
            }
            return false;
        }

        if (window.supabase && SUPABASE_URL && SUPABASE_KEY) {
            try {
                const sb = window.supabase.createClient(SUPABASE_URL, SUPABASE_KEY);
                const separator = baseLiveScoringUrl.includes('?') ? '&' : '?';
                const targetUrl = `${baseLiveScoringUrl}${separator}round=round_${currentRoundKey}`;
                sb.channel('session_' + gameSessionId).send({
                    type: 'broadcast',
                    event: 'scoring_opened',
                    payload: {
                        session_id: gameSessionId,
                        round: 'round_' + currentRoundKey,
                        round_num: currentRoundKey,
                        redirect_url: targetUrl
                    }
                }).catch(err => console.warn('[Supabase Realtime] onHostOpenScoring send warning:', err));
            } catch (err) {}
        }
    }

    if (!isHostUser) {
        let isRedirecting = false;
        const triggerRedirectToLive = (url) => {
            if (isRedirecting) return;
            isRedirecting = true;
            if (typeof showToast === 'function') {
                showToast(`🏆 Host telah membuka ${unitLabel} ${currentRoundKey}! Membuka Live Scoring...`);
            }
            setTimeout(() => {
                const separator = baseLiveScoringUrl.includes('?') ? '&' : '?';
                window.location.href = url || `${baseLiveScoringUrl}${separator}round=round_${currentRoundKey}`;
            }, 400);
        };

        // 1. Supabase Realtime Listener (Instant Push for drawing_locked, scoring_opened, score_update, drawing_shuffled)
        if (window.supabase && SUPABASE_URL && SUPABASE_KEY) {
            try {
                const sb = window.supabase.createClient(SUPABASE_URL, SUPABASE_KEY);
                sb.channel('session_' + gameSessionId)
                    .on('broadcast', { event: 'drawing_locked' }, ({ payload }) => {
                        console.log('[Drawing Realtime] Drawing locked event received:', payload);
                        triggerRedirectToLive(payload?.redirect_url);
                    })
                    .on('broadcast', { event: 'scoring_opened' }, ({ payload }) => {
                        console.log('[Drawing Realtime] scoring_opened event received:', payload);
                        const targetRound = payload?.round || ('round_' + (payload?.round_num || currentRoundKey));
                        const separator = baseLiveScoringUrl.includes('?') ? '&' : '?';
                        const targetUrl = payload?.redirect_url || `${baseLiveScoringUrl}${separator}round=${targetRound}`;
                        triggerRedirectToLive(targetUrl);
                    })
                    .on('broadcast', { event: 'score_update' }, ({ payload }) => {
                        console.log('[Drawing Realtime] score_update event received:', payload);
                        const targetRound = payload?.session_active_round || payload?.active_round || ('round_' + currentRoundKey);
                        const separator = baseLiveScoringUrl.includes('?') ? '&' : '?';
                        const targetUrl = `${baseLiveScoringUrl}${separator}round=${targetRound}`;
                        triggerRedirectToLive(targetUrl);
                    })
                    .on('broadcast', { event: 'drawing_shuffled' }, ({ payload }) => {
                        console.log('[Drawing Realtime] Drawing shuffled event received:', payload);
                        if (payload?.paused_player_ids) pausedPlayerIds = payload.paused_player_ids;
                        const newRounds = payload?.rounds || payload?.drawingData?.rounds;
                        const newMap = payload?.participantsMap;
                        applyRemoteDrawingUpdate(newRounds, newMap, '🎲 Jadwal pertandingan telah diperbarui oleh Host!');
                    })
                    .subscribe((status) => {
                        console.log('[Drawing Realtime] Status:', status);
                    });
            } catch (err) {
                console.warn('[Drawing Realtime] Init error:', err);
            }
        }

        // 2. Smart Polling Fallback (setiap 1.2 detik untuk deteksi lock, scoring dibuka, dan shuffle otomatis)
        const checkLockInterval = setInterval(async () => {
            if (isRedirecting) {
                clearInterval(checkLockInterval);
                return;
            }
            const pollStart = performance.now();
            try {
                const res = await fetch(`{{ route('games.drawing', $game['id']) }}?json=1&round=${currentRoundKey}&_t=${Date.now()}`, {
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
                if (pollDuration > 2500) {
                    updateNetworkStatusUI('slow', { latencyMs: pollDuration });
                } else if (currentNetworkState === 'slow' || currentNetworkState === 'offline') {
                    updateNetworkStatusUI('recovered');
                }

                const data = await res.json();
                
                // Jika ronde pertama baru di-lock
                if (!isSessionLocked && data && data.isLocked) {
                    clearInterval(checkLockInterval);
                    triggerRedirectToLive(data.redirect_url);
                    return;
                }

                // Jika scoring untuk ronde saat ini sudah dibuka oleh Host (Realtime flag atau status in_progress)
                if (data && (data.scoring_opened || (data.round_status && data.round_status[currentRoundKey] === 'in_progress'))) {
                    clearInterval(checkLockInterval);
                    const separator = baseLiveScoringUrl.includes('?') ? '&' : '?';
                    const targetUrl = data.redirect_url || `${baseLiveScoringUrl}${separator}round=round_${currentRoundKey}`;
                    triggerRedirectToLive(targetUrl);
                    return;
                }

                if (data && (data.rounds || data.drawingData?.rounds)) {
                    if (data.round_access) roundAccessData = data.round_access;
                    if (data.round_status) roundStatusData = data.round_status;
                    if (data.paused_player_ids) pausedPlayerIds = data.paused_player_ids;
                    if (Array.isArray(data.withdrawn_player_ids)) withdrawnPlayerIds = data.withdrawn_player_ids;
                    const newRounds = data.rounds || data.drawingData?.rounds;
                    applyRemoteDrawingUpdate(newRounds, data.participantsMap, '🎲 Jadwal diperbarui otomatis.');
                }
            } catch (e) {
                consecutivePollFailures++;
                const pollDuration = performance.now() - pollStart;
                if (!navigator.onLine) {
                    updateNetworkStatusUI('offline');
                } else if (consecutivePollFailures >= 2) {
                    updateNetworkStatusUI('slow', { latencyMs: pollDuration });
                }
            }
        }, 1200);
    }

    function cleanPlayerName(n) {
        if (!n) return '';
        if (typeof n === 'object') n = n.name || n.nama || '';
        return String(n).trim().toLowerCase();
    }

    function getPlayerIdByName(name) {
        if (!name) return null;
        const clean = cleanPlayerName(name);
        if (Array.isArray(allParticipants)) {
            const found = allParticipants.find(x => cleanPlayerName(x.name || x) === clean);
            if (found) return found.id || found.player_id;
        }
        if (participantsMap) {
            if (participantsMap[name] && (participantsMap[name].id || participantsMap[name].player_id)) {
                return participantsMap[name].id || participantsMap[name].player_id;
            }
            if (participantsMap[clean] && (participantsMap[clean].id || participantsMap[clean].player_id)) {
                return participantsMap[clean].id || participantsMap[clean].player_id;
            }
        }
        return null;
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
        let p = null;
        if (typeof name === 'object' && name.gender) {
            p = name;
        } else {
            p = (Array.isArray(allParticipants) ? allParticipants.find(x => cleanPlayerName(x) === clean) : null)
                || (participantsMap ? (participantsMap[name] || participantsMap[clean]) : null);
        }
        if (p && p.gender) {
            const g = String(p.gender).trim().toLowerCase();
            if (['female', 'perempuan', 'f', 'p', 'wanita', 'cewe', 'cewek', 'woman', 'girl'].includes(g)) {
                return true;
            }
            if (['male', 'laki-laki', 'laki - laki', 'pria', 'm', 'l', 'cowo', 'cowok', 'man', 'boy'].includes(g)) {
                return false;
            }
        }
        const rawName = typeof name === 'object' ? (name.name || name.nama || '') : String(name);
        return /gisel|davina|marame|putri|anastasia|sarah|siti|female|wanita|dewi|maya|lisa|naykila|sisil|aura|zahra/i.test(rawName);
    }

    function buildCourtCardHtml(match, matchIdx, totalMatches) {
        const courtName = match.court_name || `Court ${match.court || (matchIdx + 1)}`;
        const slotBadge = match.slot_number ? `<span class="text-[9px] font-black uppercase text-indigo-700 bg-indigo-50 border border-indigo-200 px-1.5 py-0.5 rounded-md">Slot ${match.slot_number}</span>` : '';
        
        let teamAName = 'Tim A';
        if (match.team_a && match.team_a.name) teamAName = match.team_a.name;
        else if (match.team_a_name) teamAName = match.team_a_name;
        else if (totalMatches > 1) teamAName = `${courtName} - Tim A`;
        
        let teamBName = 'Tim B';
        if (match.team_b && match.team_b.name) teamBName = match.team_b.name;
        else if (match.team_b_name) teamBName = match.team_b_name;
        else if (totalMatches > 1) teamBName = `${courtName} - Tim B`;

        const playersA = extractMatchPlayers(match.team_a, match.team_a_names);
        const playersB = extractMatchPlayers(match.team_b, match.team_b_names);

        const playersAHtml = playersA.length > 0 ? playersA.map(name => {
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
        }).join('') : `
            <div class="flex flex-col items-center justify-center text-center my-auto py-2">
                <span class="text-[10px] sm:text-[11px] font-semibold text-slate-300 italic bg-black/40 backdrop-blur-xs px-3 py-1 rounded-full border border-white/10">
                    Belum ada pemain
                </span>
            </div>
        `;

        const playersBHtml = playersB.length > 0 ? playersB.map(name => {
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
        }).join('') : `
            <div class="flex flex-col items-center justify-center text-center my-auto py-2">
                <span class="text-[10px] sm:text-[11px] font-semibold text-slate-300 italic bg-black/40 backdrop-blur-xs px-3 py-1 rounded-full border border-white/10">
                    Belum ada pemain
                </span>
            </div>
        `;

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
        const isAccessible = roundAccessData[roundNum] !== false;
        const activeUnlocked = getActiveUnlockedRound();

        // 1. Update Tabs Visual
        Object.keys(roundsData).forEach(n => {
            const tab = document.getElementById(`tabRound${n}`);
            if (tab) {
                const isAcc = roundAccessData[n] !== false;
                const isSelected = parseInt(n) === roundNum;
                let classes = 'px-4 py-2 rounded-xl text-xs font-bold transition-all shrink-0 flex items-center gap-1.5 cursor-pointer ';
                if (isSelected) {
                    classes += 'bg-[#063B00] text-white shadow-xs';
                } else if (!isAcc) {
                    classes += 'glass-card text-slate-400 hover:text-slate-700 opacity-80';
                } else {
                    classes += 'glass-card text-slate-600 hover:text-[#050608]';
                }
                tab.className = classes;
            }
        });

        // 2. Update Label Header
        const roundTitleElem = document.getElementById('labelCurrentRoundTitle');
        if (roundTitleElem) {
            roundTitleElem.innerText = `${unitLabel} ${roundNum}`;
        }
        const labelTotalMatches = document.getElementById('labelTotalMatches');

        const roundData = roundsData[roundNum];

        // 3. JIKA RONDE TERKUNCI (Tahap 2): Tampilkan Locked State UI yang elegan
        if (!isAccessible) {
            if (labelTotalMatches) {
                labelTotalMatches.innerText = 'Terkunci';
            }

            const courtsMultiContainer = document.getElementById('courtsMultiContainer');
            if (courtsMultiContainer) {
                courtsMultiContainer.className = 'w-full max-w-2xl mx-auto';
                courtsMultiContainer.innerHTML = `
                    <div class="rounded-3xl glass-card p-6 sm:p-10 shadow-sm border border-slate-200/80 text-center space-y-4 max-w-2xl mx-auto my-2">
                        <div class="w-16 h-16 rounded-2xl bg-amber-50 border border-amber-200 flex items-center justify-center mx-auto text-2xl text-amber-700 shadow-inner">
                            <i class="fa-solid fa-lock"></i>
                        </div>
                        <div class="space-y-1.5">
                            <span class="text-[11px] font-black uppercase tracking-wider text-amber-800 bg-amber-100/70 border border-amber-200 px-3 py-1 rounded-full">
                                ${unitLabel} Terkunci
                            </span>
                            <h4 class="text-lg sm:text-xl font-black text-slate-900 tracking-tight pt-1">
                                ${unitLabel} ${roundNum} Belum Dimulai
                            </h4>
                            <p class="text-xs sm:text-sm text-slate-600 max-w-md mx-auto leading-relaxed">
                                Bagan drawing dan susunan pemain untuk <strong>${unitLabel} ${roundNum}</strong> akan terbuka secara otomatis setelah <strong>${unitLabel} ${roundNum - 1}</strong> selesai dimainkan.
                            </p>
                            ${isSingle4Player && roundNum >= 3 ? `
                                <p class="text-[11px] text-[#063B00] font-semibold bg-[#063B00]/5 border border-[#063B00]/15 rounded-xl p-2.5 max-w-md mx-auto mt-2">
                                    <i class="fa-solid fa-circle-info mr-1"></i> Pada ronde ini, pertandingan akan mempertemukan pemain secara adil berdasarkan hasil ronde sebelumnya.
                                </p>
                            ` : ''}
                        </div>
                        <div class="pt-2">
                            <button type="button" onclick="switchRound(${activeUnlocked})" class="px-4 py-2 rounded-xl bg-[#063B00] hover:bg-[#042a00] text-white text-xs font-bold transition-all shadow-xs inline-flex items-center gap-1.5 cursor-pointer">
                                <i class="fa-solid fa-arrow-left"></i> Buka ${unitLabel} ${activeUnlocked} (Aktif)
                            </button>
                        </div>
                    </div>
                `;
            }

            // Bench Container di Bawah Lapangan
            const benchListContainer = document.getElementById('benchListContainer');
            const labelBenchCount = document.getElementById('labelBenchCount');
            if (labelBenchCount) labelBenchCount.innerText = '0 Pemain';
            if (benchListContainer) {
                benchListContainer.innerHTML = `<p class="text-xs text-slate-400 py-1"><i class="fa-solid fa-lock text-slate-300 mr-1.5"></i>Bangku cadangan akan dirilis setelah ${unitLabel} ${roundNum - 1} selesai dimainkan.</p>`;
            }

            // Bench Roster Sidebar
            const rosterResting = document.getElementById('rosterResting');
            const labelRestingCount = document.getElementById('labelRestingCount');
            if (labelRestingCount) labelRestingCount.innerText = '0 Pemain';
            if (rosterResting) {
                rosterResting.innerHTML = `<div class="text-center py-2 text-slate-400 text-xs"><i class="fa-solid fa-lock text-slate-300 mr-1.5"></i>Terkunci</div>`;
            }
            const sidebarWithdrawnContainer = document.getElementById('sidebarWithdrawnContainer');
            if (sidebarWithdrawnContainer) sidebarWithdrawnContainer.classList.add('hidden');

            // Match Schedule Summary
            const matchesContainer = document.getElementById('courtMatchesContainer');
            if (matchesContainer) {
                matchesContainer.innerHTML = `
                    <div class="col-span-1 sm:col-span-2 text-center py-4 text-slate-400 text-xs">
                        <i class="fa-solid fa-lock text-slate-300 mr-1.5"></i> Alokasi lapangan terkunci sampai ${unitLabel} ${roundNum - 1} selesai dimainkan.
                    </div>
                `;
            }

            // Roster Sidebar Kanan
            const rosterCourtsContainer = document.getElementById('courtsRosterContainer');
            if (rosterCourtsContainer) {
                rosterCourtsContainer.innerHTML = `
                    <div class="p-4 text-center text-xs text-slate-400">
                        <i class="fa-solid fa-lock text-slate-300 mr-1.5"></i> Susunan roster pemain terkunci sampai ${unitLabel} ${roundNum - 1} selesai dimainkan.
                    </div>
                `;
            }

            // CTA Action Button / Form
            updateCtaButton(roundNum, false, activeUnlocked);

            if (notify && typeof showToast === 'function') {
                showToast(`${unitLabel} ${roundNum} terkunci. Selesaikan ${unitLabel} ${roundNum - 1} terlebih dahulu.`);
            }
            return;
        }

        // 4. JIKA RONDE TERBUKA (ACCESSIBLE): Render Normal
        if (!roundData) return;

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

        const tabEl = document.getElementById(`tabRound${roundNum}`);
        const roundStatusVal = tabEl ? tabEl.dataset.status : (roundStatusData[roundNum] || '');
        const isPastRound = (roundStatusVal === 'completed') || (Number(roundNum) < Number(serverActiveRoundNum));
        const withdrawnIds = new Set((withdrawnPlayerIds || []).map(Number));
        const withdrawnNames = new Set((allParticipants || [])
            .filter(p => withdrawnIds.has(Number(p.id || p.player_id)))
            .map(p => cleanPlayerName(p.name || p.nama || '')));
        const pausedIds = new Set((pausedPlayerIds || []).map(Number));

        // Auto-assign ke Court 1 jika seluruh match kosong padahal kuota pemain aktif mencukupi (Parity dengan Mobile)
        const isSingleGame = isSingleMode;
        const minReq = isSingleGame ? 2 : 4;
        const eligibleActives = (allParticipants || []).filter(p => {
            const pId = Number(p.id || p.player_id);
            return pId > 0 && !withdrawnIds.has(pId) && !pausedIds.has(pId);
        });

        const hasNoPlayableMatches = matches.every(m => {
            const pA = extractMatchPlayers(m.team_a, m.team_a_names);
            const pB = extractMatchPlayers(m.team_b, m.team_b_names);
            return pA.length === 0 && pB.length === 0;
        });

        if (!isPastRound && hasNoPlayableMatches && eligibleActives.length >= minReq) {
            if (isSingleGame) {
                matches[0].team_a = [eligibleActives[0]];
                matches[0].team_a_names = [eligibleActives[0].name || eligibleActives[0].nama];
                matches[0].team_b = [eligibleActives[1]];
                matches[0].team_b_names = [eligibleActives[1].name || eligibleActives[1].nama];
            } else {
                matches[0].team_a = [eligibleActives[0], eligibleActives[1]];
                matches[0].team_a_names = [eligibleActives[0].name || eligibleActives[0].nama, eligibleActives[1].name || eligibleActives[1].nama];
                matches[0].team_b = [eligibleActives[2], eligibleActives[3]];
                matches[0].team_b_names = [eligibleActives[2].name || eligibleActives[2].nama, eligibleActives[3].name || eligibleActives[3].nama];
            }
            roundData.matches = matches;
            delete roundData.has_insufficient_players;
        }

        if (labelTotalMatches) {
            labelTotalMatches.innerText = `${matches.length} Lapangan Berjalan`;
        }

        // Render Courts 2D Multi Visualizer
        const courtsMultiContainer = document.getElementById('courtsMultiContainer');
        if (courtsMultiContainer) {
            if (matches.length > 1) {
                courtsMultiContainer.className = 'grid grid-cols-1 md:grid-cols-2 gap-4';
            } else {
                courtsMultiContainer.className = 'w-full max-w-2xl mx-auto';
            }
            courtsMultiContainer.innerHTML = matches.map((m, idx) => buildCourtCardHtml(m, idx, matches.length)).join('');
        }

        // Hitung & Render Resting Bench
        const activePlayerNames = new Set();
        matches.forEach(m => {
            const pA = extractMatchPlayers(m.team_a, m.team_a_names);
            const pB = extractMatchPlayers(m.team_b, m.team_b_names);
            pA.forEach(n => activePlayerNames.add(cleanPlayerName(n)));
            pB.forEach(n => activePlayerNames.add(cleanPlayerName(n)));
        });
        let restingPlayers = [];
        if (allParticipants && allParticipants.length > 0) {
            restingPlayers = allParticipants.filter(p =>
                (!activePlayerNames.has(cleanPlayerName(p.name)) || pausedIds.has(Number(p.id || p.player_id))) &&
                (isPastRound || !withdrawnIds.has(Number(p.id || p.player_id)))
            );
        } else if (roundData.resting && roundData.resting.length > 0) {
            restingPlayers = roundData.resting
                .filter(name => isPastRound || !withdrawnNames.has(cleanPlayerName(name)))
                .map(name => ({ name }));
        }

        // Render Bench di Bawah Lapangan
        const benchListContainer = document.getElementById('benchListContainer');
        const labelBenchCount = document.getElementById('labelBenchCount');
        if (labelBenchCount) {
            labelBenchCount.innerText = `${restingPlayers.length} Pemain`;
        }
        if (benchListContainer) {
            if (restingPlayers.length > 0) {
                benchListContainer.innerHTML = restingPlayers.map(p => {
                    const pName = typeof p === 'object' && p !== null ? (p.name || p.nama || '') : String(p);
                    let pId = typeof p === 'object' && p !== null ? (p.id || p.player_id) : null;
                    if (!pId && Array.isArray(allParticipants)) {
                        const found = allParticipants.find(x => cleanPlayerName(x.name || x) === cleanPlayerName(pName));
                        if (found) pId = found.id;
                    }
                    const roundPausedPlayers = Array.isArray(roundData?.paused_players) ? roundData.paused_players : [];
                    const roundPausedIds = Array.isArray(roundData?.paused_player_ids) ? roundData.paused_player_ids.map(Number) : [];
                    const isPaused = (pId && Array.isArray(pausedPlayerIds) && pausedPlayerIds.map(Number).includes(Number(pId)))
                        || (pId && roundPausedIds.includes(Number(pId)))
                        || roundPausedPlayers.includes(pName);
                    const safeName = pName.replace(/'/g, "\\'");

                    return `
                        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl ${isPaused ? 'bg-amber-50/80 text-slate-800 border border-amber-200' : 'bg-white text-slate-800 border border-slate-200'} text-xs font-medium shadow-2xs">
                            <span class="w-5 h-5 rounded-full ${isPaused ? 'bg-amber-200 text-amber-800' : 'bg-amber-100 text-amber-800'} text-[10px] flex items-center justify-center font-bold">
                                <i class="fa-solid ${isPaused ? 'fa-bed' : 'fa-mug-hot'} text-[9px]"></i>
                            </span>
                            ${pName}
                            <span class="text-[9px] font-bold ${isPaused ? 'text-amber-800 bg-amber-100 border border-amber-300' : 'text-amber-700 bg-amber-50 border border-amber-200'} px-1.5 py-0.5 rounded border">
                                ${isPaused ? 'Istirahat' : 'Bench'}
                            </span>
                            ${!isPastRound && isPaused && isHostUser ? `
                                <button type="button" onclick="reactivatePlayer(${pId}, '${safeName}')" class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-[10px] shadow-xs transition-colors cursor-pointer ml-1">
                                    <i class="fa-solid fa-play text-[8px]"></i> Siap Main
                                </button>
                            ` : ''}
                        </span>
                    `;
                }).join('');
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
                rosterResting.innerHTML = restingPlayers.map((p, idx) => {
                    const pName = typeof p === 'object' && p !== null ? (p.name || p.nama || '') : String(p);
                    const pId = getPlayerIdByName(pName) || (typeof p === 'object' && p !== null ? (p.id || p.player_id) : null);
                    const roundPausedPlayers = Array.isArray(roundData?.paused_players) ? roundData.paused_players : [];
                    const roundPausedIds = Array.isArray(roundData?.paused_player_ids) ? roundData.paused_player_ids.map(Number) : [];
                    const isPaused = (pId && Array.isArray(pausedPlayerIds) && pausedPlayerIds.map(Number).includes(Number(pId)))
                        || (pId && roundPausedIds.includes(Number(pId)))
                        || roundPausedPlayers.includes(pName);
                    const safeName = pName.replace(/'/g, "\\'");

                    return `
                        <div class="flex items-center justify-between bg-white p-2 rounded-xl border ${isPaused ? 'border-amber-300 bg-amber-50/40' : 'border-slate-200/70'} text-xs gap-2">
                            <span class="text-slate-700 truncate min-w-0 flex-1">${idx + 1}. ${pName}</span>
                            <div class="flex items-center gap-1.5 shrink-0">
                                <span class="text-[10px] font-semibold ${isPaused ? 'text-amber-800 bg-amber-100 border border-amber-300' : 'text-amber-600 bg-amber-50 border border-amber-200'} px-2 py-0.5 rounded">
                                    ${isPaused ? 'Istirahat' : 'Bench'}
                                </span>
                                ${!isPastRound && isHostUser && pId ? (
                                    isPaused ? `
                                        <button type="button" onclick="reactivatePlayer(${pId}, '${safeName}')" class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-[9px] shadow-2xs transition-all cursor-pointer">
                                            <i class="fa-solid fa-play text-[8px]"></i> Siap Main
                                        </button>
                                    ` : `
                                        <button type="button" onclick="openPlayerManageModal(${pId}, '${safeName}', 'pause')" class="px-1.5 py-0.5 rounded-md bg-amber-50 hover:bg-amber-100 text-amber-800 font-bold text-[9px] border border-amber-300/80 transition-all cursor-pointer shadow-2xs" title="Istirahatkan sementara">
                                            Istirahat
                                        </button>
                                        <button type="button" onclick="openPlayerManageModal(${pId}, '${safeName}', 'withdraw')" class="px-1.5 py-0.5 rounded-md bg-rose-50 hover:bg-rose-100 text-rose-800 font-bold text-[9px] border border-rose-300/80 transition-all cursor-pointer shadow-2xs" title="Keluarkan dari sesi">
                                            Keluar
                                        </button>
                                    `
                                ) : ''}
                            </div>
                        </div>
                    `;
                }).join('');
            } else {
                rosterResting.innerHTML = `<div class="text-center py-2 text-slate-400 text-xs">Semua pemain aktif bertanding di ronde ini.</div>`;
            }
        }

        const sidebarWithdrawnContainer = document.getElementById('sidebarWithdrawnContainer');
        const sidebarWithdrawnList = document.getElementById('sidebarWithdrawnList');
        if (sidebarWithdrawnContainer && sidebarWithdrawnList) {
            const currentWithdrawn = (allParticipants || []).filter(p => withdrawnIds.has(Number(p.id || p.player_id)));
            if (currentWithdrawn.length > 0) {
                sidebarWithdrawnContainer.classList.remove('hidden');
                sidebarWithdrawnList.innerHTML = currentWithdrawn.map(wp => `
                    <div class="flex items-center justify-between p-1.5 rounded-lg bg-rose-50/50 border border-rose-100 text-[11px]">
                        <span class="text-slate-500 line-through">${wp.name || 'Pemain'}</span>
                        <span class="text-[9px] font-bold text-rose-700 bg-rose-100/70 px-1.5 py-0.5 rounded border border-rose-200">Keluar</span>
                    </div>
                `).join('');
            } else {
                sidebarWithdrawnContainer.classList.add('hidden');
                sidebarWithdrawnList.innerHTML = '';
            }
        }

        // Render Match Summary List
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

        // Render Roster Sidebar Kanan
        const rosterCourtsContainer = document.getElementById('courtsRosterContainer');
        if (rosterCourtsContainer) {
            rosterCourtsContainer.innerHTML = matches.map((m, mIdx) => {
                const courtLabel = m.court_name || `Court ${m.court || (m.court_number || (mIdx + 1))}`;
                const slotBadge = m.slot_number ? `<span class="text-[9px] font-black uppercase text-indigo-700 bg-indigo-50 border border-indigo-200 px-1.5 py-0.5 rounded-md">Slot ${m.slot_number}</span>` : '';
                const statusLabel = m.status || 'Scheduled';

                const pA = extractMatchPlayers(m.team_a, m.team_a_names);
                const pB = extractMatchPlayers(m.team_b, m.team_b_names);

                let tAName = 'Tim A';
                if (m.team_a && typeof m.team_a === 'object' && !Array.isArray(m.team_a) && m.team_a.name) {
                    tAName = m.team_a.name;
                } else if (m.team_a_name) {
                    tAName = m.team_a_name;
                }

                let tBName = 'Tim B';
                if (m.team_b && typeof m.team_b === 'object' && !Array.isArray(m.team_b) && m.team_b.name) {
                    tBName = m.team_b.name;
                } else if (m.team_b_name) {
                    tBName = m.team_b_name;
                }

                const pAHtml = pA.length > 0 ? pA.map((name, pIdx) => {
                    return `
                        <div class="flex items-center justify-between bg-white p-2 rounded-xl border border-slate-200/70 text-xs shadow-2xs gap-2">
                            <span class="font-semibold text-slate-800 truncate min-w-0 flex-1">${pIdx + 1}. ${name}</span>
                            <div class="flex items-center gap-1.5 shrink-0">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-50 text-emerald-800 border border-emerald-200">Player ${pIdx + 1}</span>
                            </div>
                        </div>
                    `;
                }).join('') : '<p class="text-[11px] text-slate-400 py-1 text-center">Belum ada pemain</p>';

                const pBHtml = pB.length > 0 ? pB.map((name, pIdx) => {
                    return `
                        <div class="flex items-center justify-between bg-white p-2 rounded-xl border border-slate-200/70 text-xs shadow-2xs gap-2">
                            <span class="font-semibold text-slate-800 truncate min-w-0 flex-1">${pIdx + 1}. ${name}</span>
                            <div class="flex items-center gap-1.5 shrink-0">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-[#eaf3eb] text-[#245b2c] border border-[#bedfc1]">Player ${pIdx + 1}</span>
                            </div>
                        </div>
                    `;
                }).join('') : '<p class="text-[11px] text-slate-400 py-1 text-center">Belum ada pemain</p>';

                return `
                    <div class="p-3.5 rounded-2xl bg-white/80 border border-slate-200/80 space-y-2.5 shadow-2xs">
                        <div class="flex items-center justify-between border-b border-slate-200/50 pb-2">
                            <div class="flex items-center gap-1.5">
                                <span class="w-2 h-2 rounded-full bg-emerald-600 animate-pulse"></span>
                                <span class="text-xs font-black uppercase tracking-wider text-[#063B00]">${courtLabel}</span>
                                ${slotBadge}
                            </div>
                            <span class="text-[10px] font-semibold text-slate-500 bg-slate-50 px-2 py-0.5 rounded-full border border-slate-200/60 shadow-2xs">
                                ${statusLabel}
                            </span>
                        </div>

                        <!-- Team A -->
                        <div class="space-y-1.5">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-extrabold text-[#063B00]">${tAName}</span>
                                <span class="text-[10px] font-semibold text-slate-400">Sisi Kiri</span>
                            </div>
                            <div class="space-y-1">
                                ${pAHtml}
                            </div>
                        </div>

                        <!-- Team B -->
                        <div class="space-y-1.5 pt-2 border-t border-slate-100">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-extrabold text-slate-800">${tBName}</span>
                                <span class="text-[10px] font-semibold text-slate-400">Sisi Kanan</span>
                            </div>
                            <div class="space-y-1">
                                ${pBHtml}
                            </div>
                        </div>
                    </div>
                `;
            }).join('');
        }

        // Update CTA Button
        updateCtaButton(roundNum, true, activeUnlocked);

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

        const roundObj = roundsData[currentRoundKey];
        if (roundObj && roundObj.has_insufficient_players) {
            if (typeof showToast === 'function') {
                showToast('Pemain aktif tidak mencukupi untuk diacak. Silakan aktifkan kembali pemain melalui Bangku Cadangan.', 'warning');
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
                if (data.round_access) roundAccessData = data.round_access;
                if (data.round_status) roundStatusData = data.round_status;
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

    let pendingReactivate = { playerId: null, playerName: '' };

    /**
     * Buka modal konfirmasi untuk mengaktifkan kembali pemain yang sedang istirahat.
     */
    function reactivatePlayer(playerId, playerName) {
        if (!isHostUser) return;
        pendingReactivate = { playerId, playerName };
        const nameEl = document.getElementById('reactivatePlayerNameDisplay');
        if (nameEl) nameEl.textContent = playerName;
        const errEl = document.getElementById('reactivateModalError');
        if (errEl) errEl.classList.add('hidden');
        const modal = document.getElementById('reactivateConfirmModal');
        if (modal) modal.style.display = 'flex';
    }

    function closeReactivateModal() {
        const modal = document.getElementById('reactivateConfirmModal');
        if (modal) modal.style.display = 'none';
        pendingReactivate = { playerId: null, playerName: '' };
    }

    /**
     * Eksekusi reaktivasi pemain ke backend setelah dikonfirmasi pada modal.
     */
    async function executeReactivatePlayer() {
        if (!isHostUser || !pendingReactivate.playerId) return;
        const { playerId, playerName } = pendingReactivate;

        const btn = document.getElementById('btnConfirmReactivate');
        const btnText = document.getElementById('btnConfirmReactivateText');
        const errEl = document.getElementById('reactivateModalError');
        const errMsgEl = document.getElementById('reactivateModalErrorMsg');

        if (btn) btn.disabled = true;
        if (btnText) btnText.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-1"></i> Menyimpan...';
        if (errEl) errEl.classList.add('hidden');

        try {
            const resp = await fetch('{{ route('scoring.reactivate-player') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    game_id: gameSessionId,
                    player_id: playerId,
                    round: 'round_' + currentRoundKey
                })
            });

            const data = await resp.json();
            if (!resp.ok || !data.success) {
                const msg = data.message || 'Gagal mengaktifkan kembali pemain.';
                if (errEl && errMsgEl) {
                    errMsgEl.textContent = msg;
                    errEl.classList.remove('hidden');
                } else if (typeof showToast === 'function') {
                    showToast(msg, 'error');
                }
                if (btn) btn.disabled = false;
                if (btnText) btnText.textContent = 'Ya, Siap Main';
                return;
            }

            closeReactivateModal();

            if (typeof showToast === 'function') {
                showToast(data.message || `Pemain ${playerName} telah siap dan dimasukkan kembali ke jadwal!`);
            }

            // Hapus dari local pausedPlayerIds
            pausedPlayerIds = pausedPlayerIds.filter(id => Number(id) !== Number(playerId));
            if (Array.isArray(data.paused_player_ids)) {
                pausedPlayerIds = data.paused_player_ids.map(Number);
            }

            // Jika response menyertakan data ronde terbaru, langsung terapkan
            if (data.rounds && Object.keys(data.rounds).length > 0) {
                applyRemoteDrawingUpdate(data.rounds, participantsMap, data.message);
            } else {
                switchRound(currentRoundKey, false);
                // Trigger smart polling update untuk sinkronisasi
                try {
                    const pollRes = await fetch(`{{ route('games.drawing', $game['id']) }}?json=1&round=${currentRoundKey}&_t=${Date.now()}`, {
                        headers: { 'Accept': 'application/json' }
                    });
                    if (pollRes.ok) {
                        const pollData = await pollRes.json();
                        if (pollData.rounds) {
                            if (pollData.paused_player_ids) pausedPlayerIds = pollData.paused_player_ids.map(Number);
                            applyRemoteDrawingUpdate(pollData.rounds, pollData.participantsMap, data.message);
                        }
                    }
                } catch (e) {
                    switchRound(currentRoundKey, false);
                }
            }
        } catch (err) {
            console.error('[reactivatePlayer] Error:', err);
            if (errEl && errMsgEl) {
                errMsgEl.textContent = 'Terjadi kesalahan koneksi saat mengaktifkan pemain.';
                errEl.classList.remove('hidden');
            } else if (typeof showToast === 'function') {
                showToast('Terjadi kesalahan koneksi saat mengaktifkan pemain.', 'error');
            }
        } finally {
            if (btn) btn.disabled = false;
            if (btnText) btnText.textContent = 'Ya, Siap Main';
        }
    }

    let pendingPlayerManage = { playerId: null, playerName: '', action: 'pause' };

    function openPlayerManageModal(playerId, playerName, action = 'pause') {
        if (!isHostUser) return;
        pendingPlayerManage = { playerId, playerName, action };

        const modal = document.getElementById('playerManageActionModal');
        const titleEl = document.getElementById('playerManageModalTitle');
        const descEl = document.getElementById('playerManageModalDesc');
        const nameEl = document.getElementById('playerManageModalPlayerName');
        const iconEl = document.getElementById('playerManageModalIcon');
        const iconContainer = document.getElementById('playerManageModalIconContainer');
        const reasonInput = document.getElementById('playerManageReason');
        const quotaWarning = document.getElementById('playerManageQuotaWarning');
        const quotaWarningText = document.getElementById('playerManageQuotaWarningText');
        const errEl = document.getElementById('playerManageModalError');
        const btn = document.getElementById('btnConfirmPlayerManage');
        const btnText = document.getElementById('btnConfirmPlayerManageText');

        if (nameEl) nameEl.textContent = playerName;
        if (reasonInput) reasonInput.value = '';
        if (quotaWarning) quotaWarning.classList.add('hidden');
        if (errEl) errEl.classList.add('hidden');
        if (btn) btn.disabled = false;

        if (action === 'withdraw') {
            const minimumPlayers = isSingleMode ? 2 : 4;
            const withdrawnIds = new Set((withdrawnPlayerIds || []).map(Number));
            const availableBefore = allParticipants.filter(participant => {
                const participantId = Number(participant.id || participant.player_id);
                return participantId > 0 && !withdrawnIds.has(participantId);
            }).length;
            const isNewWithdrawal = !withdrawnIds.has(Number(playerId));
            if (isNewWithdrawal && availableBefore >= minimumPlayers && availableBefore - 1 < minimumPlayers) {
                if (quotaWarningText) {
                    const formatLabel = isSingleMode ? 'Single' : 'Double';
                    quotaWarningText.textContent = `Peringatan: jika ${playerName} dikeluarkan, hanya ${availableBefore - 1} pemain yang tersisa dari kuota minimal ${minimumPlayers} untuk ${formatLabel}. Setelah pertandingan yang sedang berjalan dihentikan, sesi tidak dapat lanjut ke ronde berikutnya dan akan berakhir di recap/leaderboard.`;
                }
                if (quotaWarning) quotaWarning.classList.remove('hidden');
            }
            if (titleEl) titleEl.textContent = 'Keluarkan Pemain dari Sesi';
            if (descEl) descEl.innerHTML = `Keluarkan <strong class="text-slate-800 font-extrabold">${playerName}</strong> secara permanen dari sesi ini? Pemain tidak akan dijadwalkan pada seluruh ronde mendatang.`;
            if (iconEl) iconEl.className = 'fa-solid fa-user-xmark';
            if (iconContainer) iconContainer.className = 'w-11 h-11 rounded-2xl bg-rose-50 text-rose-700 flex items-center justify-center text-lg border border-rose-100/80 shadow-2xs shrink-0 mt-0.5';
            if (btn) btn.className = 'px-5 py-2 rounded-xl bg-rose-600 hover:bg-rose-700 text-white font-extrabold text-xs shadow-md flex items-center gap-1.5 transition-all cursor-pointer hover:scale-[1.01]';
            if (btnText) btnText.textContent = 'Ya, Keluarkan Pemain';
        } else {
            if (titleEl) titleEl.textContent = 'Istirahatkan Pemain Sementara';
            if (descEl) descEl.innerHTML = `Istirahatkan sementara <strong class="text-slate-800 font-extrabold">${playerName}</strong> dari ronde mendatang? Pemain dapat diaktifkan kembali nanti saat sudah siap main.`;
            if (iconEl) iconEl.className = 'fa-solid fa-pause';
            if (iconContainer) iconContainer.className = 'w-11 h-11 rounded-2xl bg-amber-50 text-amber-700 flex items-center justify-center text-lg border border-amber-100/80 shadow-2xs shrink-0 mt-0.5';
            if (btn) btn.className = 'px-5 py-2 rounded-xl bg-amber-600 hover:bg-amber-700 text-white font-extrabold text-xs shadow-md flex items-center gap-1.5 transition-all cursor-pointer hover:scale-[1.01]';
            if (btnText) btnText.textContent = 'Ya, Istirahatkan Pemain';
        }

        if (modal) modal.style.display = 'flex';
        if (reasonInput) setTimeout(() => reasonInput.focus(), 50);
    }

    function closePlayerManageModal() {
        const modal = document.getElementById('playerManageActionModal');
        if (modal) modal.style.display = 'none';
        const btn = document.getElementById('btnConfirmPlayerManage');
        const btnText = document.getElementById('btnConfirmPlayerManageText');
        if (btn) btn.disabled = false;
        if (btnText) {
            btnText.textContent = (pendingPlayerManage && pendingPlayerManage.action === 'withdraw')
                ? 'Ya, Keluarkan Pemain'
                : 'Ya, Istirahatkan Pemain';
        }
        pendingPlayerManage = { playerId: null, playerName: '', action: 'pause' };
    }

    function openPlayerActiveMatchPromptModal({ courtNum, message, targetUrl, playerName }) {
        const modal = document.getElementById('playerActiveMatchPromptModal');
        const descEl = document.getElementById('playerActiveMatchPromptDesc');
        const btnLink = document.getElementById('btnGoToCourtScoring');
        const btnText = document.getElementById('btnGoToCourtScoringText');

        if (descEl) {
            if (playerName) {
                descEl.innerHTML = `<strong class="text-slate-800 font-extrabold">${playerName}</strong> sedang aktif bertanding di <strong>Court ${courtNum}</strong>. Pertandingan yang sedang berjalan harus dihentikan melalui court tersebut.`;
            } else if (message) {
                descEl.textContent = message;
            } else {
                descEl.textContent = `Pemain sedang bertanding di Court ${courtNum}. Silakan gunakan End Match pada court terkait untuk menghentikan pertandingan.`;
            }
        }

        if (btnText) {
            btnText.textContent = `Buka Scoring Court ${courtNum}`;
        }

        if (btnLink) {
            btnLink.href = targetUrl;
        }

        if (modal) modal.style.display = 'flex';
    }

    function closePlayerActiveMatchPromptModal() {
        const modal = document.getElementById('playerActiveMatchPromptModal');
        if (modal) modal.style.display = 'none';
    }

    async function executePlayerManageAction() {
        if (!isHostUser || !pendingPlayerManage.playerId) return;
        const { playerId, playerName, action } = pendingPlayerManage;

        const reasonInput = document.getElementById('playerManageReason');
        const reason = (reasonInput?.value || '').trim();
        const errEl = document.getElementById('playerManageModalError');
        const errMsgEl = document.getElementById('playerManageModalErrorMsg');
        const btn = document.getElementById('btnConfirmPlayerManage');
        const btnText = document.getElementById('btnConfirmPlayerManageText');

        if (!reason) {
            if (errEl && errMsgEl) {
                errMsgEl.textContent = 'Alasan wajib diisi sebelum menyimpan perubahan.';
                errEl.classList.remove('hidden');
            }
            if (reasonInput) reasonInput.focus();
            return;
        }

        if (btn) btn.disabled = true;
        const origText = btnText ? btnText.textContent : 'Konfirmasi';
        if (btnText) btnText.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-1"></i> Menyimpan...';
        if (errEl) errEl.classList.add('hidden');

        try {
            const resp = await fetch('{{ route('scoring.manage-player-status') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    game_id: gameSessionId,
                    player_id: playerId,
                    action: action,
                    reason: reason,
                    round: 'round_' + currentRoundKey
                })
            });

            const data = await resp.json();
            if (!resp.ok || !data.success) {
                if (data.is_playing) {
                    const courtNum = data.court || 1;
                    const separator = baseLiveScoringUrl.includes('?') ? '&' : '?';
                    const ctaLiveUrl = `${baseLiveScoringUrl}${separator}round=round_${currentRoundKey}&court=${courtNum - 1}`;
                    const targetPlayerName = playerName;
                    closePlayerManageModal();
                    openPlayerActiveMatchPromptModal({
                        courtNum: courtNum,
                        message: data.message,
                        targetUrl: ctaLiveUrl,
                        playerName: targetPlayerName
                    });
                    return;
                }

                const msg = data.message || 'Gagal memperbarui status pemain.';
                if (errEl && errMsgEl) {
                    errMsgEl.textContent = msg;
                    errEl.classList.remove('hidden');
                } else if (typeof showToast === 'function') {
                    showToast(msg, 'error');
                }
                if (btn) btn.disabled = false;
                if (btnText) btnText.textContent = origText;
                return;
            }

            closePlayerManageModal();
            if (typeof showToast === 'function') {
                showToast(data.message || 'Status pemain berhasil diperbarui.');
            }
            setTimeout(() => {
                window.location.reload();
            }, 600);
        } catch (err) {
            console.error('[executePlayerManageAction] Error:', err);
            if (errEl && errMsgEl) {
                errMsgEl.textContent = 'Terjadi kesalahan koneksi saat memperbarui status pemain.';
                errEl.classList.remove('hidden');
            } else if (typeof showToast === 'function') {
                showToast('Terjadi kesalahan koneksi saat memperbarui status pemain.', 'error');
            }
        } finally {
            if (btn) btn.disabled = false;
            if (btnText) btnText.textContent = origText;
        }
    }

    // Sinkronisasi otomatis data ronde saat halaman dimuat
    document.addEventListener('DOMContentLoaded', () => {
        const initialRoundKey = {{ $activeRoundNum }};
        if (typeof switchRound === 'function') {
            switchRound(initialRoundKey, false);
        }
    });
</script>
@endpush
@endsection
