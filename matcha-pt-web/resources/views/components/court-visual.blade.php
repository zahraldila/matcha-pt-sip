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
    <div class="relative w-full min-h-[260px] sm:min-h-[300px] bg-[#14341d] rounded-2xl border-2 border-white/90 p-3 sm:p-4 flex flex-col justify-between overflow-hidden shadow-inner">
        <!-- Court Service Lines -->
        <div class="absolute inset-x-4 sm:inset-x-6 inset-y-3 border border-white/80 pointer-events-none"></div>
        
        <!-- Center Net -->
        <div class="absolute inset-y-0 left-1/2 w-0.5 -translate-x-1/2 bg-white flex flex-col justify-between items-center z-10 pointer-events-none">
            <div class="w-2.5 h-2.5 bg-[#0d2213] border border-white rounded-full -mt-1"></div>
            <span class="bg-[#0d2213] text-lime-300 font-black text-[8px] px-1 py-0.5 rounded tracking-widest uppercase border border-white/40 rotate-90 my-auto">NET</span>
            <div class="w-2.5 h-2.5 bg-[#0d2213] border border-white rounded-full -mb-1"></div>
        </div>

        <!-- Center Service Line -->
        <div class="absolute inset-y-1/2 left-4 sm:left-6 right-4 sm:right-6 h-0.5 -translate-y-1/2 bg-white/80 pointer-events-none"></div>

        <!-- Left Court / Team A -->
        <div class="relative z-20 w-1/2 pr-2 sm:pr-3 flex flex-col justify-between h-full">
            <div class="text-left">
                <span class="text-[9px] font-black uppercase tracking-wider px-2.5 py-0.5 rounded-full bg-white/20 backdrop-blur-xs text-white border border-white/30 shadow-xs">
                    Team A
                </span>
            </div>
            
            <div class="flex flex-col justify-around gap-2 my-auto py-1" id="courtTeamA">
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
                    <div class="flex flex-col items-center justify-center text-center transform transition-transform hover:scale-110 w-fit mx-auto sm:mx-8">
                        <div class="w-9 h-9 sm:w-11 sm:h-11 rounded-full {{ $isFemale ? 'bg-gradient-to-br from-rose-400 to-pink-600' : 'bg-gradient-to-br from-sky-400 to-blue-600' }} text-white border-2 border-white shadow-md flex items-center justify-center text-xs sm:text-sm mb-1">
                            <i class="{{ $isFemale ? 'fa-solid fa-person-dress' : 'fa-solid fa-person' }}"></i>
                        </div>
                        <p class="text-[11px] sm:text-xs font-bold text-white text-center leading-tight drop-shadow-[0_1px_3px_rgba(0,0,0,0.9)] max-w-[110px] sm:max-w-[130px] truncate">
                            {{ $name }}
                        </p>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Right Court / Team B -->
        <div class="relative z-20 w-1/2 pl-2 sm:pl-3 ml-auto flex flex-col justify-between h-full text-right items-end">
            <div class="text-right">
                <span class="text-[9px] font-black uppercase tracking-wider px-2.5 py-0.5 rounded-full bg-white/20 backdrop-blur-xs text-white border border-white/30 shadow-xs">
                    Team B
                </span>
            </div>

            <div class="flex flex-col justify-around gap-2 my-auto py-1 w-full items-center" id="courtTeamB">
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
                    <div class="flex flex-col items-center justify-center text-center transform transition-transform hover:scale-110 w-fit mx-auto sm:mx-8">
                        <div class="w-9 h-9 sm:w-11 sm:h-11 rounded-full {{ $isFemale ? 'bg-gradient-to-br from-rose-400 to-pink-600' : 'bg-gradient-to-br from-sky-400 to-blue-600' }} text-white border-2 border-white shadow-md flex items-center justify-center text-xs sm:text-sm mb-1">
                            <i class="{{ $isFemale ? 'fa-solid fa-person-dress' : 'fa-solid fa-person' }}"></i>
                        </div>
                        <p class="text-[11px] sm:text-xs font-bold text-white text-center leading-tight drop-shadow-[0_1px_3px_rgba(0,0,0,0.9)] max-w-[110px] sm:max-w-[130px] truncate">
                            {{ $name }}
                        </p>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <!-- Resting / Rotation Bench -->
    @if(count($resting) > 0)
        <div class="mt-4 pt-3.5 border-t border-slate-200/50">
            <div class="flex items-center justify-between mb-2 text-xs">
                <span class="font-semibold text-slate-700 flex items-center gap-1.5">
                    <i class="fa-solid fa-mug-hot text-amber-500"></i> Bangku Istirahat & Rotasi Ronde Ini:
                </span>
                <span class="text-[10px] text-slate-400 bg-white/70 px-2 py-0.5 rounded-full border border-slate-200/60">Main di ronde berikutnya</span>
            </div>
            <div class="flex flex-wrap gap-2">
                @foreach($resting as $restPlayer)
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-xl bg-white/80 text-slate-700 text-xs border border-slate-200/70 font-medium shadow-2xs">
                        <i class="fa-regular fa-clock text-slate-400 text-[10px]"></i> {{ $restPlayer }}
                    </span>
                @endforeach
            </div>
        </div>
    @endif
</div>
