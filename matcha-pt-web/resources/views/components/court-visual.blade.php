@props(['sport' => 'Padel', 'teamA' => [], 'teamB' => [], 'resting' => [], 'participantsMap' => []])

<div class="w-full max-w-2xl mx-auto rounded-3xl glass-card p-6 shadow-sm border border-white/80">
    <!-- Header -->
    <div class="flex items-center justify-between mb-4 border-b border-slate-200/50 pb-3">
        <div class="flex items-center gap-2">
            <span class="w-2.5 h-2.5 rounded-full bg-emerald-600 animate-pulse"></span>
            <h4 class="text-xs font-bold uppercase tracking-wider text-slate-800">
                Posisi Lapangan {{ $sport }} &bull; Live Matchup
            </h4>
        </div>
        <span class="text-xs text-slate-500 font-medium bg-white/70 px-2.5 py-0.5 rounded-full border border-slate-200/60 shadow-xs">
            Standar Lapangan Resmi
        </span>
    </div>

    <!-- Court Graphic Container -->
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
            <!-- Left Court / Team A -->
            <div class="w-1/2 pr-2 flex flex-col justify-between h-full">
                <div class="text-left">
                    <span class="text-[9px] font-black uppercase tracking-wider px-2.5 py-0.5 rounded-full bg-black/40 backdrop-blur-xs text-[#A8E63A] border border-[#A8E63A]/40 w-fit shadow-xs">
                        Team A
                    </span>
                </div>
                
                <div class="flex flex-col justify-around gap-2 my-auto py-2 h-full" id="courtTeamA">
                    @foreach($teamA as $idx => $player)
                        @php
                            $name = is_array($player) ? ($player['name'] ?? ($player['nama'] ?? '')) : (string)$player;
                            $gender = is_array($player) 
                                ? ($player['gender'] ?? ($participantsMap[$name]['gender'] ?? null)) 
                                : ($participantsMap[$name]['gender'] ?? null);
                            
                            $isFemale = $gender 
                                ? in_array(strtolower($gender), ['female', 'perempuan', 'f', 'p', 'wanita']) 
                                : preg_match('/gisel|davina|marame|putri|anastasia|sarah|siti|female|wanita|mau|sekarang|naykila|sisil/i', $name);
                        @endphp
                        <div class="flex flex-col items-center justify-center text-center transform transition-transform hover:scale-105 w-fit mx-auto sm:mx-6 my-auto">
                            <div class="w-9 h-9 sm:w-11 sm:h-11 rounded-full {{ $isFemale ? 'bg-gradient-to-br from-rose-400 to-pink-600' : 'bg-gradient-to-br from-sky-400 to-blue-600' }} text-white border-2 border-white shadow-lg flex items-center justify-center text-xs sm:text-sm mb-1 ring-2 ring-black/20">
                                <i class="{{ $isFemale ? 'fa-solid fa-person-dress' : 'fa-solid fa-person' }}"></i>
                            </div>
                            <span class="text-[10px] sm:text-[11px] font-bold text-white text-center leading-tight bg-black/50 backdrop-blur-xs px-2.5 py-0.5 rounded-full border border-white/20 shadow-xs max-w-[95px] sm:max-w-[120px] truncate">
                                {{ $name }}
                            </span>
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- Right Court / Team B -->
            <div class="w-1/2 pl-2 flex flex-col justify-between h-full items-end text-right">
                <div class="text-right">
                    <span class="text-[9px] font-black uppercase tracking-wider px-2.5 py-0.5 rounded-full bg-black/40 backdrop-blur-xs text-[#A8E63A] border border-[#A8E63A]/40 w-fit shadow-xs">
                        Team B
                    </span>
                </div>

                <div class="flex flex-col justify-around gap-2 my-auto py-2 h-full items-end w-full" id="courtTeamB">
                    @foreach($teamB as $idx => $player)
                        @php
                            $name = is_array($player) ? ($player['name'] ?? ($player['nama'] ?? '')) : (string)$player;
                            $gender = is_array($player) 
                                ? ($player['gender'] ?? ($participantsMap[$name]['gender'] ?? null)) 
                                : ($participantsMap[$name]['gender'] ?? null);
                            
                            $isFemale = $gender 
                                ? in_array(strtolower($gender), ['female', 'perempuan', 'f', 'p', 'wanita']) 
                                : preg_match('/gisel|davina|marame|putri|anastasia|sarah|siti|female|wanita|mau|sekarang|naykila|sisil/i', $name);
                        @endphp
                        <div class="flex flex-col items-center justify-center text-center transform transition-transform hover:scale-105 w-fit mx-auto sm:mx-6 my-auto">
                            <div class="w-9 h-9 sm:w-11 sm:h-11 rounded-full {{ $isFemale ? 'bg-gradient-to-br from-rose-400 to-pink-600' : 'bg-gradient-to-br from-sky-400 to-blue-600' }} text-white border-2 border-white shadow-lg flex items-center justify-center text-xs sm:text-sm mb-1 ring-2 ring-black/20">
                                <i class="{{ $isFemale ? 'fa-solid fa-person-dress' : 'fa-solid fa-person' }}"></i>
                            </div>
                            <span class="text-[10px] sm:text-[11px] font-bold text-white text-center leading-tight bg-black/50 backdrop-blur-xs px-2.5 py-0.5 rounded-full border border-white/20 shadow-xs max-w-[95px] sm:max-w-[120px] truncate">
                                {{ $name }}
                            </span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <!-- Resting / Rotation Bench -->
    <div id="courtVisualRestingSection" class="mt-4 pt-3.5 border-t border-slate-200/50 {{ count($resting) > 0 ? '' : 'hidden' }}">
        <div class="flex items-center justify-between mb-2 text-xs">
            <span class="font-semibold text-slate-700 flex items-center gap-1.5">
                <i class="fa-solid fa-mug-hot text-amber-500"></i> Bangku Istirahat & Rotasi Ronde Ini:
            </span>
            <span class="text-[10px] text-slate-400 bg-white/70 px-2 py-0.5 rounded-full border border-slate-200/60">Main di ronde berikutnya</span>
        </div>
        <div class="flex flex-wrap gap-2" id="courtVisualRestingList">
            @foreach($resting as $restPlayer)
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-xl bg-white/80 text-slate-700 text-xs border border-slate-200/70 font-medium shadow-2xs">
                    <i class="fa-regular fa-clock text-slate-400 text-[10px]"></i> {{ $restPlayer }}
                </span>
            @endforeach
        </div>
    </div>
</div>
