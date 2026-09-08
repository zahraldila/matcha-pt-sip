<footer class="bg-white border-t border-slate-200 mt-16 py-8 text-slate-500 text-xs">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row items-center justify-between gap-4">
        <div class="flex items-center gap-2">
            <span class="font-bold text-[#050608]">Matcha Application</span>
            <span class="text-slate-300">&bull;</span>
            <span class="text-[#063B00] font-semibold">PT SIP</span>
        </div>
        <p>&copy; 2026 Matcha App. Platform Manajemen Komunitas & Pertandingan Tennis & Padel.</p>
        <div class="flex items-center gap-4 text-slate-600">
            <a href="{{ route('dashboard') }}" class="hover:text-[#063B00] transition-colors">Home</a>
            <a href="{{ route('venues.index') }}" class="hover:text-[#063B00] transition-colors">Venues</a>
            <a href="{{ route('games.index') }}" class="hover:text-[#063B00] transition-colors">Games</a>
            <a href="{{ route('communities.index') }}" class="hover:text-[#063B00] transition-colors">Communities</a>
        </div>
    </div>
</footer>
