@extends('layouts.app')

@section('content')
<div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
    
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-200/50 pb-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">
                Rekap Pertandingan & Statistik
            </h1>
            <p class="text-xs text-slate-500 mt-0.5">Ringkasan aktivitas pertandingan dan statistik kemenangan pemain</p>
        </div>

        <button onclick="shareRecap()" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-xs shadow-xs transition-all hover:scale-[1.01]">
            <i class="fa-solid fa-arrow-up-from-bracket text-[10px]"></i> Bagikan Rekap
        </button>
    </div>

    <!-- Strava-Style Shareable Activity Card (Subtle Frosted Glass) -->
    <div id="stravaCard" class="glass-card rounded-3xl p-6 sm:p-8 space-y-6 border border-white/90">
        
        <!-- Brand Header -->
        <div class="flex items-center justify-between border-b border-slate-200/40 pb-4">
            <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-xl bg-gradient-to-tr from-emerald-600 to-teal-500 flex items-center justify-center text-white text-xs shadow-xs">
                    <i class="fa-solid fa-table-tennis-paddle-ball"></i>
                </div>
                <div>
                    <span class="text-[10px] font-bold tracking-wider text-emerald-800 uppercase">Match Activity Summary</span>
                    <h3 class="text-sm font-bold text-slate-900 leading-tight">Sesi Mabar JTK Bonang Arena</h3>
                </div>
            </div>
            <div>
                <span class="text-xs font-semibold text-slate-600 bg-white/80 px-3 py-1 rounded-full border border-slate-200/60 shadow-2xs">
                    Hari Ini, 08 Sep 2026
                </span>
            </div>
        </div>

        <!-- Player Profile & Highlight -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="flex items-center gap-3.5">
                <img src="{{ $recap['player']['avatar'] }}" alt="{{ $recap['player']['name'] }}" class="w-14 h-14 rounded-full object-cover ring-2 ring-emerald-600/70 shadow-xs">
                <div>
                    <h2 class="text-lg font-bold text-slate-900 flex items-center gap-2">
                        {{ $recap['player']['name'] }}
                        <span class="text-xs bg-emerald-50 text-emerald-800 px-2 py-0.5 rounded-full border border-emerald-200/60 font-semibold">{{ $recap['player']['level'] }}</span>
                    </h2>
                    <p class="text-xs text-slate-500">{{ $recap['player']['username'] }} &bull; {{ $recap['player']['community'] }}</p>
                </div>
            </div>

            <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl bg-amber-50/80 border border-amber-200/70 text-amber-900 font-bold text-xs shadow-2xs">
                <span>{{ $recap['player']['streak'] }}</span>
            </div>
        </div>

        <!-- Big Stat Numbers -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 pt-1">
            <div class="bg-white/70 backdrop-blur-xs p-4 rounded-2xl border border-slate-200/60 text-center shadow-2xs">
                <span class="text-[10px] text-slate-400 font-medium block uppercase">Total Tanding</span>
                <strong class="text-2xl font-bold text-slate-900 mt-0.5 block">{{ $recap['player']['total_matches'] }}</strong>
                <span class="text-[10px] text-slate-400">Pertandingan</span>
            </div>

            <div class="bg-white/70 backdrop-blur-xs p-4 rounded-2xl border border-slate-200/60 text-center shadow-2xs">
                <span class="text-[10px] text-slate-400 font-medium block uppercase">Kemenangan</span>
                <strong class="text-2xl font-bold text-emerald-700 mt-0.5 block">{{ $recap['player']['wins'] }}W</strong>
                <span class="text-[10px] text-slate-400">{{ $recap['player']['losses'] }}x Kalah</span>
            </div>

            <div class="bg-white/70 backdrop-blur-xs p-4 rounded-2xl border border-slate-200/60 text-center shadow-2xs">
                <span class="text-[10px] text-slate-400 font-medium block uppercase">Win Rate %</span>
                <strong class="text-2xl font-bold text-slate-800 mt-0.5 block">{{ $recap['player']['win_rate'] }}</strong>
                <span class="text-[10px] text-emerald-700 font-semibold">Persentase</span>
            </div>

            <div class="bg-white/70 backdrop-blur-xs p-4 rounded-2xl border border-slate-200/60 text-center shadow-2xs">
                <span class="text-[10px] text-slate-400 font-medium block uppercase">Waktu Bermain</span>
                <strong class="text-2xl font-bold text-slate-900 mt-0.5 block">{{ $recap['player']['total_hours'] }}</strong>
                <span class="text-[10px] text-slate-400">Total di Lapangan</span>
            </div>
        </div>
    </div>

    <!-- Match History & Head-to-Head Section -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        <!-- Left: Match History List (2 Cols) -->
        <div class="lg:col-span-2 space-y-3">
            <h3 class="text-sm font-bold text-slate-900">
                Riwayat Pertandingan Terakhir
            </h3>

            <div class="space-y-2.5">
                @foreach($recap['recent_matches'] as $match)
                    <div class="glass-card p-4 rounded-2xl flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div class="space-y-1">
                            <div class="flex items-center gap-2">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold {{ $match['result'] === 'WIN' ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-rose-50 text-rose-800 border border-rose-200' }}">
                                    {{ $match['result'] }}
                                </span>
                                <span class="font-bold text-xs text-slate-900">{{ $match['sport'] }}</span>
                                <span class="text-slate-300">&bull;</span>
                                <span class="text-xs text-slate-500">{{ $match['venue'] }}</span>
                            </div>

                            <p class="text-xs text-slate-600">
                                Partner: <strong class="text-slate-800">{{ $match['partner'] }}</strong> 
                                vs Lawan: <span>{{ implode(' & ', $match['opponents']) }}</span>
                            </p>
                        </div>

                        <div class="text-right sm:border-l sm:border-slate-200/50 sm:pl-4">
                            <span class="text-[10px] text-slate-400 block">{{ $match['match_date'] }}</span>
                            <span class="text-sm font-bold text-slate-900">Skor: {{ $match['score'] }}</span>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Right: Head-to-Head Stats (1 Col) -->
        <div class="space-y-3">
            <h3 class="text-sm font-bold text-slate-900">
                Rekor Lawan (Head-to-Head)
            </h3>

            <div class="glass-card rounded-2xl p-4 space-y-3">
                <p class="text-xs text-slate-500 leading-relaxed">
                    Statistik kemenangan vs lawan bermain yang tercatat di sistem:
                </p>

                <div class="space-y-3">
                    @foreach($recap['head_to_head'] as $h2h)
                        <div class="bg-white/70 p-3 rounded-xl border border-slate-200/50 space-y-1.5 shadow-2xs">
                            <div class="flex items-center justify-between text-xs font-semibold">
                                <span class="text-slate-800">{{ $h2h['opponent'] }}</span>
                                <span class="text-emerald-700 font-bold">{{ $h2h['win'] }}W - {{ $h2h['lose'] }}L</span>
                            </div>
                            <div class="w-full bg-slate-200/70 rounded-full h-1.5 overflow-hidden">
                                @php
                                    $h2hPercent = ($h2h['win'] / $h2h['played']) * 100;
                                @endphp
                                <div class="bg-gradient-to-r from-emerald-600 to-teal-500 h-1.5 rounded-full" style="width: {{ $h2hPercent }}%"></div>
                            </div>
                            <div class="flex justify-between text-[10px] text-slate-400">
                                <span>{{ $h2h['played'] }}x main</span>
                                <span>{{ round($h2hPercent) }}% win</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    function shareRecap() {
        showToast('Kartu rekap siap dibagikan!');
    }
</script>
@endpush
@endsection
