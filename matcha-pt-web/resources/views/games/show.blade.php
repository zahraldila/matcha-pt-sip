@extends('layouts.app')

@section('content')
<div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-200 pb-4">
        <div>
            <a href="{{ route('games.index') }}" class="text-xs text-slate-500 hover:text-slate-800 inline-flex items-center gap-1.5 mb-2 transition-colors">
                <i class="fa-solid fa-arrow-left"></i> Kembali ke Semua Jadwal
            </a>
            <h1 class="text-2xl font-bold text-slate-900">
                {{ $game['title'] }}
            </h1>
        </div>

        <div class="flex items-center gap-2">
            <x-badge :type="strtolower($game['sport']) === 'tennis' ? 'tennis' : 'padel'">
                {{ $game['sport'] }}
            </x-badge>
            <x-badge :type="str_contains(strtolower($game['status']), 'selesai') || !empty($game['is_finished']) ? 'finished' : (str_contains(strtolower($game['status']), 'ready') ? 'full' : 'open')">
                {{ $game['status'] }}
            </x-badge>
        </div>
    </div>

    <!-- Main Content -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Details & Participants (2 Cols) -->
        <div class="lg:col-span-2 space-y-6">
            <div class="clean-card rounded-xl p-5 sm:p-6 space-y-4">
                <h3 class="text-sm font-bold text-slate-900">
                    Informasi Pelaksanaan
                </h3>

                <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 text-xs">
                    <div class="bg-slate-50 p-3 rounded-lg border border-slate-200 min-w-0">
                        <span class="text-slate-500 block mb-0.5">Venue</span>
                        <strong class="text-slate-900 block min-w-0 truncate whitespace-nowrap" title="{{ $game['venue_name'] }}">{{ $game['venue_name'] }}</strong>
                        <span class="text-emerald-700 text-[11px] font-medium block min-w-0 truncate whitespace-nowrap" title="{{ $game['court_name'] }}">{{ $game['court_name'] }}</span>
                    </div>
                    <div class="bg-slate-50 p-3 rounded-lg border border-slate-200">
                        <span class="text-slate-500 block mb-0.5">Jadwal</span>
                        <strong class="text-slate-900 block">{{ $game['time'] }} WIB</strong>
                        <span class="text-slate-500 text-[11px]">{{ \Carbon\Carbon::parse($game['date'])->isoFormat('dddd, D MMM Y') }}</span>
                    </div>
                    <div class="bg-slate-50 p-3 rounded-lg border border-slate-200">
                        <span class="text-slate-500 block mb-0.5">Durasi & Kuota</span>
                        <strong class="text-slate-900 block">{{ $game['duration'] ?? '-' }}</strong>
                        <span class="text-slate-700 font-semibold text-[11px]">{{ $game['joined_count'] }} / {{ $game['quota'] }} Pemain</span>
                    </div>
                    <div class="bg-slate-50 p-3 rounded-lg border border-slate-200">
                        <span class="text-slate-500 block mb-0.5">Format</span>
                        <strong class="text-slate-900 block">{{ $game['match_format'] }}</strong>
                    </div>
                    <div class="bg-slate-50 p-3 rounded-lg border border-slate-200 sm:col-span-2">
                        <span class="text-slate-500 block mb-0.5">Sistem Scoring</span>
                        <strong class="text-slate-900 block">{{ $game['scoring_system'] }}</strong>
                    </div>
                </div>

                <!-- Host info -->
                <div class="p-3.5 rounded-lg bg-emerald-50 border border-emerald-100 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <img src="{{ $game['host']['avatar'] }}" alt="{{ $game['host']['name'] }}" class="w-9 h-9 rounded-full object-cover ring-1 ring-emerald-600">
                        <div>
                            <p class="text-[11px] text-emerald-800 font-medium">Host Sesi Mabar:</p>
                            <p class="text-xs font-bold text-slate-900">{{ $game['host']['name'] }} <span class="text-slate-500 font-normal">({{ $game['host']['level'] }})</span></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Participant Table -->
            <div class="clean-card rounded-xl p-5 sm:p-6 space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-bold text-slate-900">
                        Daftar Peserta ({{ count($game['participants']) }}/{{ $game['quota'] }})
                    </h3>
                    <span class="text-xs font-semibold text-emerald-800 bg-emerald-50 px-2.5 py-0.5 rounded-full border border-emerald-200">
                        Kuota Lengkap
                    </span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="text-slate-500 bg-slate-50 uppercase tracking-wider text-[10px] border-b border-slate-200">
                            <tr>
                                <th class="py-2.5 px-3">#</th>
                                <th class="py-2.5 px-3">Nama</th>
                                <th class="py-2.5 px-3">Status</th>
                                <th class="py-2.5 px-3">Skill Level</th>
                                <th class="py-2.5 px-3">Gender / Usia</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($game['participants'] as $index => $player)
                                <tr class="hover:bg-slate-50/80 transition-colors">
                                    <td class="py-2.5 px-3 text-slate-400 font-medium">{{ $index + 1 }}</td>
                                    <td class="py-2.5 px-3">
                                        <div class="flex items-center gap-2">
                                            <img src="{{ $player['avatar'] ?? 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=60&q=80' }}" class="w-6 h-6 rounded-full object-cover">
                                            <span class="font-semibold text-slate-800">{{ $player['name'] }}</span>
                                        </div>
                                    </td>
                                    <td class="py-2.5 px-3">
                                        @if($player['is_member'])
                                             <span class="text-[10px] font-semibold px-2 py-0.5 rounded bg-emerald-50 text-emerald-800 border border-emerald-200">Member</span>
                                        @else
                                            <span class="text-[10px] font-semibold px-2 py-0.5 rounded bg-slate-100 text-slate-600 border border-slate-200">Guest</span>
                                        @endif
                                    </td>
                                    <td class="py-2.5 px-3">
                                        @php
                                            $lvl = strtolower($player['level']);
                                        @endphp
                                        <x-badge :type="$lvl">{{ $player['level'] }}</x-badge>
                                    </td>
                                    <td class="py-2.5 px-3 text-slate-500">
                                        {{ $player['gender'] }}, {{ $player['age'] }} th
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Sidebar Actions -->
        <div class="space-y-4">
            @if(!empty($game['is_finished']) || str_contains(strtolower($game['status']), 'selesai'))
                <div class="glass-card rounded-3xl p-5 space-y-4 border border-amber-200/60 bg-gradient-to-br from-amber-50/50 via-white to-emerald-50/30 shadow-xs">
                    <div class="space-y-1.5">
                        <div class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-amber-100 text-amber-900 border border-amber-200 text-[10px] font-extrabold uppercase tracking-wider">
                            <i class="fa-solid fa-trophy text-amber-600 text-[10px]"></i> Selesai Mabar
                        </div>
                        <h3 class="text-sm font-extrabold text-slate-900">Hasil Akhir &amp; Podium</h3>
                        <p class="text-xs text-slate-500 leading-relaxed">
                            Seluruh pertandingan pada sesi mabar ini telah selesai dimainkan dan skor akhir telah direkam secara resmi.
                        </p>
                    </div>

                    <div class="space-y-2 pt-2">
                        <a href="{{ route('scoring.recap', $game['id']) }}" class="w-full text-center py-3 rounded-xl bg-gradient-to-r from-amber-600 to-amber-700 hover:from-amber-700 hover:to-amber-800 text-white font-extrabold text-xs shadow-md transition-all hover:scale-[1.01] active:scale-95 flex items-center justify-center gap-2 cursor-pointer">
                            <i class="fa-solid fa-trophy text-xs text-amber-200"></i> Buka Hasil Akhir &amp; Podium
                        </a>
                    </div>

                    <div class="pt-3 border-t border-slate-100 text-[11px] text-slate-500 space-y-1.5">
                        <p class="flex items-center gap-1.5 text-emerald-800 font-medium">
                            <i class="fa-solid fa-circle-check text-emerald-600"></i> Rekap skor &amp; statistik tersimpan
                        </p>
                        <p class="flex items-center gap-1.5 text-amber-800 font-medium">
                            <i class="fa-solid fa-medal text-amber-600"></i> Peringkat klasemen &amp; juara tersedia
                        </p>
                    </div>
                </div>
            @else
                <div class="glass-card rounded-3xl p-5 space-y-4 border border-white/90">
                    <div class="space-y-1">
                        <h3 class="text-sm font-bold text-[#050608]">Drawing &amp; Mulai Pertandingan</h3>
                        <p class="text-xs text-slate-500">
                            Pemain telah lengkap. Host dapat mengacak tim dan memulai scoring poin.
                        </p>
                    </div>

                    <div class="space-y-2 pt-2">
                        <a href="{{ route('games.drawing', $game['id']) }}" class="w-full text-center py-2.5 rounded-xl bg-[#063B00] hover:bg-[#042a00] text-white font-semibold text-xs shadow-xs transition-all hover:scale-[1.01] flex items-center justify-center gap-1.5">
                            <i class="fa-solid fa-shuffle text-[11px]"></i> Buka Drawing Tim
                        </a>

                        <a href="{{ route('scoring.live', $game['id']) }}" class="w-full text-center py-2.5 rounded-xl bg-white hover:bg-slate-50 text-[#063B00] font-semibold text-xs border-1.5 border-[#063B00] transition-all shadow-xs flex items-center justify-center gap-1.5">
                            <i class="fa-solid fa-stopwatch text-[11px]"></i> Live Match Scoring
                        </a>
                    </div>

                    <div class="pt-3 border-t border-slate-100 text-[11px] text-slate-500 space-y-1.5">
                        <p class="flex items-center gap-1.5">
                            <i class="fa-solid fa-check text-[#063B00]"></i> Drawing otomatis seimbang
                        </p>
                        <p class="flex items-center gap-1.5">
                            <i class="fa-solid fa-check text-[#063B00]"></i> Visualisasi lapangan tennis/padel
                        </p>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
