@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-200/60 pb-4">
        <div>
            <h1 class="text-2xl font-bold text-[#050608]">
                Jadwal Mabar & Turnamen
            </h1>
            <p class="text-xs text-slate-500 mt-0.5">Daftar sesi mabar aktif yang dibuat oleh Host Komunitas</p>
        </div>

        <a href="{{ route('games.create') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-[#063B00] hover:bg-[#042a00] text-white font-semibold text-xs shadow-xs transition-all hover:scale-[1.01]">
            <i class="fa-solid fa-plus text-[10px]"></i> Buat Sesi Mabar Baru
        </a>
    </div>

    <!-- Filters Bar -->
    <div class="glass-card p-3.5 rounded-2xl flex flex-wrap items-center justify-between gap-3 border border-white/90">
        <div class="flex items-center gap-2">
            <a href="{{ route('games.index', ['sport' => 'all']) }}" class="px-3.5 py-1.5 rounded-xl text-xs font-semibold transition-all {{ ($selectedSport ?? 'all') === 'all' ? 'bg-[#063B00] text-white shadow-xs font-bold' : 'glass-card text-slate-600 hover:text-[#050608]' }}">
                Semua Cabang
            </a>
            <a href="{{ route('games.index', ['sport' => 'tennis']) }}" class="px-3.5 py-1.5 rounded-xl text-xs font-semibold transition-all {{ ($selectedSport ?? '') === 'tennis' ? 'bg-[#063B00] text-white shadow-xs font-bold' : 'glass-card text-slate-600 hover:text-[#050608]' }}">
                🎾 Tennis
            </a>
            <a href="{{ route('games.index', ['sport' => 'padel']) }}" class="px-3.5 py-1.5 rounded-xl text-xs font-semibold transition-all {{ ($selectedSport ?? '') === 'padel' ? 'bg-[#063B00] text-white shadow-xs font-bold' : 'glass-card text-slate-600 hover:text-[#050608]' }}">
                🏓 Padel
            </a>
        </div>

        <div class="text-xs text-slate-500">
            Total <strong class="text-[#050608]">{{ count($games) }}</strong> sesi pertandingan
        </div>
    </div>

    <!-- Grid of Games -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @foreach($games as $game)
            <x-game-card :game="$game" />
        @endforeach
    </div>
</div>
@endsection
