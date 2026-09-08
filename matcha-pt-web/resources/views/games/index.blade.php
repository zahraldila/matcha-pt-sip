@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-200 pb-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">
                Jadwal Mabar & Turnamen
            </h1>
            <p class="text-xs text-slate-500 mt-0.5">Daftar sesi mabar aktif yang dibuat oleh Host Komunitas</p>
        </div>

        <a href="{{ route('games.create') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-xs shadow-sm transition-colors">
            <i class="fa-solid fa-plus text-[10px]"></i> Buat Sesi Mabar Baru
        </a>
    </div>

    <!-- Filters Bar -->
    <div class="clean-card p-3.5 rounded-xl flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-2">
            <a href="{{ route('games.index', ['sport' => 'all']) }}" class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors {{ ($selectedSport ?? 'all') === 'all' ? 'bg-slate-900 text-white' : 'bg-slate-50 text-slate-600 hover:bg-slate-100 border border-slate-200' }}">
                Semua Cabang
            </a>
            <a href="{{ route('games.index', ['sport' => 'tennis']) }}" class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors {{ ($selectedSport ?? '') === 'tennis' ? 'bg-slate-900 text-white' : 'bg-slate-50 text-slate-600 hover:bg-slate-100 border border-slate-200' }}">
                🎾 Tennis
            </a>
            <a href="{{ route('games.index', ['sport' => 'padel']) }}" class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors {{ ($selectedSport ?? '') === 'padel' ? 'bg-slate-900 text-white' : 'bg-slate-50 text-slate-600 hover:bg-slate-100 border border-slate-200' }}">
                🏓 Padel
            </a>
        </div>

        <div class="text-xs text-slate-500">
            Total <strong>{{ count($games) }}</strong> sesi pertandingan
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
