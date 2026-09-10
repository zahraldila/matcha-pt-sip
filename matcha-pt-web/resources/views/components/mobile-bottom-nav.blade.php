{{-- Mobile App-Style Bottom Navigation Bar (Active on Mobile/Tablet screens md:hidden) --}}
<nav class="md:hidden fixed bottom-0 inset-x-0 z-50 bg-white/95 backdrop-blur-2xl border-t border-slate-200/80 shadow-[0_-4px_24px_rgba(0,0,0,0.08)] px-2 pb-[max(0.5rem,env(safe-area-inset-bottom))] pt-1.5 transition-all">
    <div class="max-w-md mx-auto grid grid-cols-5 items-center">
        
        <!-- 1. Dashboard (Home) -->
        @php
            $isDashboard = request()->routeIs('dashboard');
        @endphp
        <a href="{{ route('dashboard') }}" class="flex flex-col items-center justify-center py-1 group transition-all">
            <div class="w-8 h-8 rounded-xl flex items-center justify-center transition-all {{ $isDashboard ? 'bg-[#EBF8D8] text-[#063B00] shadow-2xs' : 'text-slate-500 group-hover:text-slate-800' }}">
                <i class="fa-solid fa-house text-sm"></i>
            </div>
            <span class="text-[10px] font-bold mt-0.5 tracking-tight {{ $isDashboard ? 'text-[#063B00]' : 'text-slate-500 group-hover:text-slate-800' }}">
                Beranda
            </span>
        </a>

        <!-- 2. Jadwal Mabar -->
        @php
            $isGames = request()->routeIs('games.*') && !request()->routeIs('games.create');
        @endphp
        <a href="{{ route('games.index') }}" class="flex flex-col items-center justify-center py-1 group transition-all">
            <div class="w-8 h-8 rounded-xl flex items-center justify-center transition-all {{ $isGames ? 'bg-[#EBF8D8] text-[#063B00] shadow-2xs' : 'text-slate-500 group-hover:text-slate-800' }}">
                <i class="fa-solid fa-trophy text-sm"></i>
            </div>
            <span class="text-[10px] font-bold mt-0.5 tracking-tight {{ $isGames ? 'text-[#063B00]' : 'text-slate-500 group-hover:text-slate-800' }}">
                Mabar
            </span>
        </a>

        <!-- 3. Central Floating Action Button (Host Game) -->
        <div class="flex flex-col items-center justify-center -mt-6">
            @php
                $createRoute = route('games.create');
                $btnIcon = 'fa-plus';
                $btnLabel = 'Host';
                
                if (Auth::check() && Auth::user()->role === 'venue_owner') {
                    $createRoute = route('venues.create');
                    $btnLabel = 'Venue';
                } elseif (Auth::check() && Auth::user()->role === 'member') {
                    $createRoute = route('communities.create');
                    $btnLabel = 'Komunitas';
                }
                $isCreateActive = request()->routeIs('games.create') || request()->routeIs('venues.create') || request()->routeIs('communities.create');
            @endphp
            <a href="{{ $createRoute }}" class="w-13 h-13 rounded-full bg-gradient-to-tr from-[#063B00] to-[#0a5202] text-[#A8E63A] border-4 border-white shadow-lg flex items-center justify-center text-lg active:scale-95 transition-all hover:scale-105 group relative">
                <i class="fa-solid {{ $btnIcon }} text-base group-hover:rotate-90 transition-transform"></i>
                <span class="absolute -top-1 -right-1 w-3.5 h-3.5 bg-[#A8E63A] rounded-full border-2 border-white"></span>
            </a>
            <span class="text-[10px] font-extrabold mt-1 text-[#063B00] tracking-tight">
                {{ $btnLabel }}
            </span>
        </div>

        <!-- 4. Venue & Court -->
        @php
            $isVenues = request()->routeIs('venues.*') && !request()->routeIs('venues.create');
        @endphp
        <a href="{{ route('venues.index') }}" class="flex flex-col items-center justify-center py-1 group transition-all">
            <div class="w-8 h-8 rounded-xl flex items-center justify-center transition-all {{ $isVenues ? 'bg-[#EBF8D8] text-[#063B00] shadow-2xs' : 'text-slate-500 group-hover:text-slate-800' }}">
                <i class="fa-solid fa-location-dot text-sm"></i>
            </div>
            <span class="text-[10px] font-bold mt-0.5 tracking-tight {{ $isVenues ? 'text-[#063B00]' : 'text-slate-500 group-hover:text-slate-800' }}">
                Venue
            </span>
        </a>

        <!-- 5. Match Recap / Profile -->
        @php
            $isRecap = request()->routeIs('player.*');
        @endphp
        <a href="{{ route('player.recap') }}" class="flex flex-col items-center justify-center py-1 group transition-all">
            <div class="w-8 h-8 rounded-xl flex items-center justify-center transition-all {{ $isRecap ? 'bg-[#EBF8D8] text-[#063B00] shadow-2xs' : 'text-slate-500 group-hover:text-slate-800' }}">
                <i class="fa-solid fa-chart-line text-sm"></i>
            </div>
            <span class="text-[10px] font-bold mt-0.5 tracking-tight {{ $isRecap ? 'text-[#063B00]' : 'text-slate-500 group-hover:text-slate-800' }}">
                Recap
            </span>
        </a>

    </div>
</nav>
