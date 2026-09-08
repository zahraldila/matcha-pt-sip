@props(['game'])

@php
    $sportType = strtolower($game['sport']) === 'tennis' ? 'tennis' : 'padel';
    $statusType = match(true) {
        str_contains(strtolower($game['status']), 'in progress') => 'playing',
        str_contains(strtolower($game['status']), 'ready') => 'full',
        str_contains(strtolower($game['status']), 'open') => 'open',
        default => 'default',
    };
    $isFull = $game['joined_count'] >= $game['quota'];
@endphp

<div class="glass-card rounded-2xl p-5 flex flex-col justify-between group">
    <div>
        <!-- Card Top Bar: Badges -->
        <div class="flex items-center justify-between mb-3.5">
            <div class="flex items-center gap-1.5">
                <x-badge :type="$sportType">
                    {{ $game['sport'] }}
                </x-badge>
                <span class="text-[11px] text-slate-500 font-medium px-2 py-0.5 rounded-full bg-white/80 border border-slate-200/50 backdrop-blur-xs">
                    {{ $game['match_format'] }}
                </span>
            </div>
            <x-badge :type="$statusType">
                {{ $game['status'] }}
            </x-badge>
        </div>

        <!-- Title -->
        <h3 class="text-sm sm:text-base font-bold text-[#050608] group-hover:text-[#063B00] transition-colors leading-snug mb-3">
            <a href="{{ route('games.show', $game['id']) }}">{{ $game['title'] }}</a>
        </h3>

        <!-- Match Info Pill Details -->
        <div class="space-y-1.5 text-xs text-slate-600 mb-4 bg-white/60 backdrop-blur-xs p-3 rounded-xl border border-slate-200/50">
            <div class="flex items-center gap-2">
                <i class="fa-solid fa-location-dot text-slate-400 w-3.5 text-center text-[11px]"></i>
                <span class="font-semibold text-[#050608] truncate">{{ $game['venue_name'] }}</span>
                <span class="text-slate-300">&bull;</span>
                <span class="text-slate-500 truncate text-[11px]">{{ $game['court_name'] }}</span>
            </div>
            <div class="flex items-center gap-2 text-[11px]">
                <i class="fa-regular fa-calendar text-slate-400 w-3.5 text-center"></i>
                <span>{{ \Carbon\Carbon::parse($game['date'])->isoFormat('dddd, D MMMM Y') }}</span>
            </div>
            <div class="flex items-center gap-2 text-[11px]">
                <i class="fa-regular fa-clock text-slate-400 w-3.5 text-center"></i>
                <span class="font-semibold text-[#050608]">{{ $game['time'] }} WIB</span>
                <span class="text-slate-400">({{ $game['duration'] }})</span>
            </div>
        </div>

        <!-- Quota Progress Indicator -->
        <div class="mb-4">
            <div class="flex justify-between items-center text-xs mb-1 font-medium">
                <span class="text-slate-500 text-[11px]">
                    Ketersediaan Slot
                </span>
                <span class="font-bold text-[#050608] text-xs">{{ $game['joined_count'] }} / {{ $game['quota'] }} Pemain</span>
            </div>
            <div class="w-full bg-slate-200/60 rounded-full h-1.5 overflow-hidden">
                @php
                    $percentage = min(100, ($game['joined_count'] / $game['quota']) * 100);
                @endphp
                <div class="bg-gradient-to-r from-[#063B00] to-[#A8E63A] h-1.5 rounded-full transition-all duration-300" style="width: {{ $percentage }}%"></div>
            </div>
        </div>

        <!-- Participants & Host Footer Preview -->
        <div class="flex items-center justify-between py-2 border-t border-slate-200/40 mb-3 text-xs">
            <div class="flex items-center -space-x-1.5 overflow-hidden">
                @foreach($game['participants'] as $idx => $participant)
                    @if($idx < 4)
                        <img src="{{ $participant['avatar'] ?? 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=80&q=80' }}" 
                             alt="{{ $participant['name'] }}" 
                             title="{{ $participant['name'] }} ({{ $participant['level'] }})" 
                             class="inline-block h-6 w-6 rounded-full ring-2 ring-white object-cover">
                    @endif
                @endforeach
                @if(count($game['participants']) > 4)
                    <div class="inline-flex items-center justify-center h-6 w-6 rounded-full ring-2 ring-white bg-slate-100 text-[10px] font-bold text-slate-600">
                        +{{ count($game['participants']) - 4 }}
                    </div>
                @endif
            </div>
            <span class="text-[11px] text-slate-500">
                Host: <strong class="text-[#050608]">{{ $game['host']['name'] }}</strong>
            </span>
        </div>
    </div>

    <!-- Action Buttons with Role Checking -->
    <div class="grid grid-cols-2 gap-2 pt-2 border-t border-slate-200/40">
        <a href="{{ route('games.show', $game['id']) }}" class="text-center py-2 px-3 rounded-xl bg-white/80 hover:bg-white text-slate-700 text-xs font-semibold transition-all border border-slate-200/60 shadow-xs">
            Detail
        </a>

        @if($isFull)
            {{-- Kuota Penuh --}}
            @if(Auth::check() && Auth::user()->role === 'host')
                <a href="{{ route('games.drawing', $game['id']) }}" class="text-center py-2 px-3 rounded-xl bg-[#063B00] hover:bg-[#042a00] text-white text-xs font-bold shadow-xs transition-all hover:scale-[1.01]">
                    🎲 Drawing Tim
                </a>
            @else
                <button type="button" disabled class="text-center py-2 px-3 rounded-xl bg-slate-100 text-slate-400 text-xs font-bold border border-slate-200/60 cursor-not-allowed">
                    Slot Penuh
                </button>
            @endif
        @else
            {{-- Slot Masih Terbuka --}}
            <button onclick="showJoinModal('{{ $game['id'] }}', '{{ $game['title'] }}')" class="text-center py-2 px-3 rounded-xl bg-[#063B00] hover:bg-[#042a00] text-white text-xs font-bold shadow-xs transition-all hover:scale-[1.01] cursor-pointer">
                Gabung Slot
            </button>
        @endif
    </div>
</div>
