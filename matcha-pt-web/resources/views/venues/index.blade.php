@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-200/60 pb-4">
        <div>
            <h1 class="text-2xl font-bold text-[#050608]">
                Direktori Venue & Court
            </h1>
            <p class="text-xs text-slate-500 mt-0.5">Daftar lokasi lapangan Tennis & Padel dengan informasi jam operasional dan fasilitas</p>
        </div>

        @if(Auth::check() && Auth::user()->role === 'venue_owner')
            <a href="{{ route('venues.create') }}" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-[#063B00] hover:bg-[#042a00] text-white font-semibold text-xs shadow-xs transition-all hover:scale-[1.01]">
                <i class="fa-solid fa-plus text-[10px]"></i> Daftarkan Venue Baru
            </a>
        @endif
    </div>

    <!-- Grid of Venues -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @foreach($venues as $venue)
            <div class="glass-card rounded-3xl overflow-hidden flex flex-col justify-between group border border-white/90">
                <div>
                    <div class="relative h-44 overflow-hidden">
                        <img src="{{ $venue['image'] }}" alt="{{ $venue['name'] }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                        <div class="absolute top-3 left-3">
                            <x-badge :type="strtolower($venue['sport']) === 'tennis' ? 'tennis' : 'padel'">
                                {{ $venue['sport'] }}
                            </x-badge>
                        </div>
                    </div>

                    <div class="p-5 space-y-3 text-xs">
                        <div>
                            <h3 class="text-base font-bold text-[#050608] leading-tight">{{ $venue['name'] }}</h3>
                            <p class="text-slate-500 text-xs mt-0.5 flex items-center gap-1">
                                <i class="fa-solid fa-location-dot text-slate-400 text-[10px]"></i> {{ $venue['city'] }}
                            </p>
                        </div>

                        <p class="text-slate-600 line-clamp-2 leading-relaxed">{{ $venue['description'] }}</p>

                        <div class="bg-white/70 p-3 rounded-2xl border border-slate-200/60 space-y-1.5">
                            <div class="flex justify-between items-center text-slate-500">
                                <span>Jam Operasi:</span>
                                <strong class="text-[#050608]">{{ $venue['operating_hours'] }}</strong>
                            </div>
                            <div class="flex justify-between items-center text-slate-500">
                                <span>Jumlah Court:</span>
                                <strong class="text-[#063B00] font-bold">{{ count($venue['courts']) }} Lapangan</strong>
                            </div>
                        </div>

                        <div class="space-y-1">
                            <span class="text-[11px] font-semibold text-slate-500">Fasilitas:</span>
                            <div class="flex flex-wrap gap-1">
                                @foreach(array_slice($venue['facilities'], 0, 3) as $fac)
                                    <span class="px-2 py-0.5 rounded-lg bg-slate-100 text-[10px] text-slate-600 border border-slate-200/80">
                                        {{ $fac }}
                                    </span>
                                @endforeach
                                @if(count($venue['facilities']) > 3)
                                    <span class="px-2 py-0.5 rounded-lg bg-slate-100 text-[10px] text-slate-400 border border-slate-200/80">
                                        +{{ count($venue['facilities']) - 3 }}
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                <div class="p-5 pt-0">
                    <a href="{{ route('venues.show', $venue['id']) }}" class="block text-center py-2.5 rounded-xl bg-[#063B00] hover:bg-[#042a00] text-white font-semibold text-xs transition-all shadow-xs hover:scale-[1.01]">
                        Lihat Court & Jadwal
                    </a>
                </div>
            </div>
        @endforeach
    </div>
</div>
@endsection
