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
            <x-badge :type="str_contains(strtolower($game['status']), 'ready') ? 'full' : 'open'">
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
                    <div class="bg-slate-50 p-3 rounded-lg border border-slate-200">
                        <span class="text-slate-500 block mb-0.5">Venue</span>
                        <strong class="text-slate-900 block truncate">{{ $game['venue_name'] }}</strong>
                        <span class="text-emerald-700 text-[11px] font-medium">{{ $game['court_name'] }}</span>
                    </div>
                    <div class="bg-slate-50 p-3 rounded-lg border border-slate-200">
                        <span class="text-slate-500 block mb-0.5">Jadwal</span>
                        <strong class="text-slate-900 block">{{ $game['time'] }} WIB</strong>
                        <span class="text-slate-500 text-[11px]">{{ \Carbon\Carbon::parse($game['date'])->isoFormat('dddd, D MMM Y') }}</span>
                    </div>
                    <div class="bg-slate-50 p-3 rounded-lg border border-slate-200">
                        <span class="text-slate-500 block mb-0.5">Durasi & Kuota</span>
                        <strong class="text-slate-900 block">{{ $game['duration'] }}</strong>
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
                    <span class="text-xs text-emerald-700 font-semibold">{{ $game['host']['phone'] }}</span>
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
            <div class="clean-card rounded-xl p-5 space-y-4">
                <div class="space-y-1">
                    <h3 class="text-sm font-bold text-slate-900">Drawing & Mulai Pertandingan</h3>
                    <p class="text-xs text-slate-500">
                        Pemain telah lengkap. Host dapat mengacak tim dan memulai scoring poin.
                    </p>
                </div>

                <div class="space-y-2 pt-2">
                    <a href="{{ route('games.drawing', $game['id']) }}" class="w-full text-center py-2.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-xs shadow-sm transition-colors flex items-center justify-center gap-1.5">
                        <i class="fa-solid fa-shuffle text-[11px]"></i> Buka Drawing Tim
                    </a>

                    <a href="{{ route('scoring.live', $game['id']) }}" class="w-full text-center py-2.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-800 font-semibold text-xs border border-slate-200 transition-colors flex items-center justify-center gap-1.5">
                        <i class="fa-solid fa-stopwatch text-[11px]"></i> Live Match Scoring
                    </a>
                </div>

                <div class="pt-3 border-t border-slate-100 text-[11px] text-slate-500 space-y-1.5">
                    <p class="flex items-center gap-1.5">
                        <i class="fa-solid fa-check text-emerald-600"></i> Drawing otomatis seimbang
                    </p>
                    <p class="flex items-center gap-1.5">
                        <i class="fa-solid fa-check text-emerald-600"></i> Visualisasi lapangan tennis/padel
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
