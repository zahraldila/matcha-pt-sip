@props(['sport' => 'Padel', 'teamA' => [], 'teamB' => [], 'resting' => []])

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
    <div class="relative w-full aspect-[16/10] bg-[#163820] rounded-2xl border-2 border-white/90 p-4 flex flex-col justify-between overflow-hidden shadow-inner">
        <!-- Court Service Lines -->
        <div class="absolute inset-x-6 inset-y-3 border border-white/80 pointer-events-none"></div>
        <!-- Center Net -->
        <div class="absolute inset-y-0 left-1/2 w-0.5 -translate-x-1/2 bg-white flex flex-col justify-between items-center z-10">
            <div class="w-2.5 h-2.5 bg-[#0f2415] border border-white rounded-full -mt-1"></div>
            <span class="bg-[#0f2415] text-lime-300 font-bold text-[8px] px-1 py-0.5 rounded tracking-widest uppercase border border-white/40 rotate-90 my-auto">NET</span>
            <div class="w-2.5 h-2.5 bg-[#0f2415] border border-white rounded-full -mb-1"></div>
        </div>

        <!-- Center Service Line -->
        <div class="absolute inset-y-1/2 left-6 right-6 h-0.5 -translate-y-1/2 bg-white/80 pointer-events-none"></div>

        <!-- Left Court / Team A -->
        <div class="relative z-20 w-1/2 pr-3 flex flex-col justify-around h-full">
            <div class="text-left">
                <span class="text-[10px] font-extrabold tracking-wider uppercase px-2.5 py-0.5 rounded-md bg-white text-[#163820] shadow-sm">
                    Team A
                </span>
            </div>
            
            <div class="grid grid-rows-2 gap-2 my-auto">
                @foreach($teamA as $idx => $player)
                    <div class="bg-white/95 backdrop-blur-md border border-white/90 rounded-xl p-2.5 flex items-center gap-2.5 shadow-sm transform transition-transform hover:scale-105">
                        <div class="w-6 h-6 rounded-lg bg-emerald-100 flex items-center justify-center font-extrabold text-[#163820] text-[10px]">
                            A{{ $idx + 1 }}
                        </div>
                        <div class="truncate">
                            <p class="text-xs font-bold text-slate-800 truncate">{{ $player }}</p>
                            <p class="text-[9px] text-slate-500 font-medium">{{ $idx === 0 ? 'Posisi Kiri' : 'Posisi Kanan' }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Right Court / Team B -->
        <div class="relative z-20 w-1/2 pl-3 ml-auto flex flex-col justify-around h-full text-right">
            <div class="text-right">
                <span class="text-[10px] font-extrabold tracking-wider uppercase px-2.5 py-0.5 rounded-md bg-white text-[#163820] shadow-sm">
                    Team B
                </span>
            </div>

            <div class="grid grid-rows-2 gap-2 my-auto">
                @foreach($teamB as $idx => $player)
                    <div class="bg-white/95 backdrop-blur-md border border-white/90 rounded-xl p-2.5 flex items-center justify-end gap-2.5 shadow-sm text-right transform transition-transform hover:scale-105">
                        <div class="truncate">
                            <p class="text-xs font-bold text-slate-800 truncate">{{ $player }}</p>
                            <p class="text-[9px] text-slate-500 font-medium">{{ $idx === 0 ? 'Posisi Kiri' : 'Posisi Kanan' }}</p>
                        </div>
                        <div class="w-6 h-6 rounded-lg bg-lime-100 flex items-center justify-center font-extrabold text-[#163820] text-[10px]">
                            B{{ $idx + 1 }}
                        </div>
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
